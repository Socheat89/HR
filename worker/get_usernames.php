<?php
session_start();

// Check if the user is logged in as admin or sub-user
if (!isset($_SESSION['admin_logged_in']) && !isset($_SESSION['sub_user_logged_in'])) {
    header('HTTP/1.1 403 Forbidden');
    exit(json_encode(['error' => 'Unauthorized']));
}

// Determine user roles and branch filter
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$is_sub_user = isset($_SESSION['sub_user_logged_in']) && $_SESSION['sub_user_logged_in'] === true;
$branch_filter = $_SESSION['branch'] ?? null;

// Set response header to JSON
header('Content-Type: application/json; charset=utf-8');

try {
    // Database connection
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    // Get query parameters
    $folder = $_GET['folder'] ?? '';
    $branch = $_GET['branch'] ?? '';
    $search = trim($_GET['search'] ?? ''); // Trim whitespace from search term

    // Validate search term length (optional)
    if ($search && strlen($search) < 2) {
        http_response_code(400);
        exit(json_encode(['error' => 'Search term must be at least 2 characters']));
    }

    // Build the base SQL query
    $sql = "SELECT DISTINCT username 
            FROM scan_logs 
            WHERE username IS NOT NULL AND username != ''";
    $params = [];

    // Add folder filter if provided
    if ($folder) {
        $sql .= " AND folder = ?";
        $params[] = $folder;
    }

    // Add branch filter if provided
    if ($branch) {
        $sql .= " AND branch = ?";
        $params[] = $branch;
    }

    // Add branch filter for sub-users if applicable
    if ($is_sub_user && $branch_filter) {
        $sql .= " AND branch = ?";
        $params[] = $branch_filter;
    }

    // Add search filter if provided (case-insensitive)
    if ($search) {
        $sql .= " AND LOWER(username) LIKE ?";
        $params[] = "%" . strtolower($search) . "%"; // Case-insensitive search
    }

    // Order the results
    $sql .= " ORDER BY username";

    // Prepare and execute the query
    $stmt = $pdo->prepare($sql);
    foreach ($params as $index => $value) {
        $stmt->bindValue($index + 1, $value);
    }
    $stmt->execute();

    // Fetch usernames as a single column array
    $usernames = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Return the result as JSON
    echo json_encode($usernames, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // Handle database errors
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    // Handle other errors
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
?>