<?php
session_start();

// Check if the user is authenticated
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit(json_encode(['error' => 'Unauthorized: Please log in', 'success' => false]));
}

// Set the response header to JSON
header('Content-Type: application/json');

try {
    // Database connection
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db", 
                   'samann1_scan_logs_worker_db', 
                   'scan_logs_worker_db@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    // Get query parameters with sanitization
    $lastId = filter_input(INPUT_GET, 'last_id', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    $startDate = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING);
    $endDate = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING);
    $usernames = filter_input(INPUT_GET, 'usernames', FILTER_SANITIZE_STRING);

    // Validate parameters
    if ($startDate && !DateTime::createFromFormat('Y-m-d', $startDate)) {
        throw new Exception("Invalid start_date format. Use YYYY-MM-DD.");
    }
    if ($endDate && !DateTime::createFromFormat('Y-m-d', $endDate)) {
        throw new Exception("Invalid end_date format. Use YYYY-MM-DD.");
    }

    // Build the base query
    $query = "SELECT id, username, branch, user_id, action, timestamp, latitude, longitude, status, address 
              FROM scan_logs 
              WHERE id > :last_id";
    $params = [':last_id' => $lastId];

    // Apply filters if provided
    if ($startDate) {
        $query .= " AND timestamp >= :start_date";
        $params[':start_date'] = $startDate;
    }
    if ($endDate) {
        $query .= " AND timestamp <= :end_date";
        $params[':end_date'] = $endDate . ' 23:59:59'; // Include full day
    }
    if ($usernames) {
        $usernameArray = explode(',', $usernames);
        if (!empty($usernameArray)) {
            $placeholders = implode(',', array_fill(0, count($usernameArray), '?'));
            $query .= " AND username IN ($placeholders)";
            foreach ($usernameArray as $i => $username) {
                $params[":username_$i"] = trim($username);
            }
        }
    }

    // Finalize query with ordering and limit
    $query .= " ORDER BY timestamp DESC LIMIT 10";
    $stmt = $pdo->prepare($query);

    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    // Execute query and fetch results
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process results
    foreach ($logs as &$log) {
        $log['date'] = date('m/d/Y', strtotime($log['timestamp']));
        $log['time'] = date('h:i:s A', strtotime($log['timestamp']));
        $scanHour = (int)date('H', strtotime($log['timestamp']));
        $log['attendance_status'] = $scanHour < 8 ? 'Good' : 'Late';
    }
    unset($log);

    // Return JSON response
    echo json_encode([
        'logs' => $logs,
        'success' => true,
        'last_id' => !empty($logs) ? max(array_column($logs, 'id')) : $lastId,
        'query' => $query // For debugging
    ]);
} catch (PDOException $e) {
    // Log error and return failure response
    error_log("Database error in fetch_latest_logs.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'success' => false,
        'query' => isset($query) ? $query : 'N/A' // For debugging
    ]);
} catch (Exception $e) {
    // Handle other unexpected errors
    error_log("Unexpected error in fetch_latest_logs.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'error' => 'Unexpected error: ' . $e->getMessage(),
        'success' => false
    ]);
}
?>