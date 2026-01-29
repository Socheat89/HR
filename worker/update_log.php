<?php
// update_log.php (CORRECTED - KEEPS ORIGINAL STRUCTURE)

session_start();
header('Content-Type: application/json; charset=utf-8');

// Security Check 1: Authentication
if ((!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) && (!isset($_SESSION['sub_user_logged_in']) || $_SESSION['sub_user_logged_in'] !== true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please log in.']);
    exit;
}

// Security Check 2: CSRF Token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

try {
    $id = $_POST['id'] ?? null;
    $data = $_POST['data'] ?? [];

    if (empty($id) || !is_numeric($id) || empty($data)) {
        throw new Exception('Invalid input provided.');
    }

    // Database connection
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    // Security Check 3: Whitelist of editable columns from your original code
    $allowed_columns = ['user_id', 'username', 'branch', 'action', 'status', 'early_reason', 'noted'];
    
    $set_parts = [];
    $params = [];
    foreach ($data as $field => $value) {
        if (in_array($field, $allowed_columns)) {
            $set_parts[] = "`" . $field . "` = :" . $field;
            $params[$field] = trim($value);
        }
    }

    // *** MODIFICATION: Handle timestamp update from 'scan_date' and 'scan_time' ***
    if (isset($data['scan_date']) && isset($data['scan_time'])) {
        $datetime_string = $data['scan_date'] . ' ' . $data['scan_time'];
        $d = DateTime::createFromFormat('Y-m-d H:i:s', $datetime_string);
        
        if ($d && $d->format('Y-m-d H:i:s') === $datetime_string) {
            $set_parts[] = "`timestamp` = :timestamp";
            $params['timestamp'] = $datetime_string;
        } else {
            throw new Exception('Invalid date or time format provided.');
        }
    }


    if (empty($set_parts)) {
        throw new Exception('No valid fields to update.');
    }

    $sql = "UPDATE scan_logs SET " . implode(', ', $set_parts) . " WHERE id = :id";
    $params['id'] = $id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Log the detailed error for the admin, but show a generic message to the user
    error_log("Update log error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred while saving data.']);
}
?>