<?php
session_start();

// Authentication check to ensure the user is logged in as admin or sub-user
if (!isset($_SESSION['admin_logged_in']) && !isset($_SESSION['sub_user_logged_in'])) {
    header('HTTP/1.1 403 Forbidden');
    exit(json_encode(['error' => 'Unauthorized']));
}

$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$is_sub_user = isset($_SESSION['sub_user_logged_in']) && $_SESSION['sub_user_logged_in'] === true;
$branch_filter = $_SESSION['branch'] ?? null;

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    $folder = $_GET['folder'] ?? '';

    // Build the query to fetch distinct branches for the selected folder
    $sql = "SELECT DISTINCT branch 
            FROM scan_logs 
            WHERE branch IS NOT NULL AND branch != ''";
    $params = [];

    if ($folder) {
        $sql .= " AND folder = ?";
        $params[] = $folder;
    }
    if ($is_sub_user && $branch_filter) {
        $sql .= " AND branch = ?";
        $params[] = $branch_filter;
    }

    $sql .= " ORDER BY branch";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $index => $value) {
        $stmt->bindValue($index + 1, $value);
    }
    $stmt->execute();

    $branches = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($branches, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
?>