<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) && !isset($_SESSION['sub_user_logged_in'])) {
    header('HTTP/1.1 403 Forbidden');
    exit(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    $is_sub_user = isset($_SESSION['sub_user_logged_in']) && $_SESSION['sub_user_logged_in'] === true;
    $branch_filter = $_SESSION['branch'] ?? null;

    // Fetch distinct usernames
    $usernameSql = "SELECT DISTINCT username FROM scan_logs WHERE username IS NOT NULL AND username != ''";
    if ($is_sub_user && $branch_filter) {
        $usernameSql .= " AND branch = ?";
    }
    $usernameStmt = $pdo->prepare($usernameSql);
    if ($is_sub_user && $branch_filter) {
        $usernameStmt->execute([$branch_filter]);
    } else {
        $usernameStmt->execute();
    }
    $usernames = $usernameStmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch distinct branches
    $branchSql = "SELECT DISTINCT branch FROM scan_logs WHERE branch IS NOT NULL AND branch != ''";
    if ($is_sub_user && $branch_filter) {
        $branchSql .= " AND branch = ?";
    }
    $branchStmt = $pdo->prepare($branchSql);
    if ($is_sub_user && $branch_filter) {
        $branchStmt->execute([$branch_filter]);
    } else {
        $branchStmt->execute();
    }
    $branches = $branchStmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch distinct dates
    $dateSql = "SELECT DISTINCT DATE_FORMAT(timestamp, '%m/%d/%Y') as date FROM scan_logs ORDER BY timestamp DESC";
    if ($is_sub_user && $branch_filter) {
        $dateSql .= " AND branch = ?";
    }
    $dateStmt = $pdo->prepare($dateSql);
    if ($is_sub_user && $branch_filter) {
        $dateStmt->execute([$branch_filter]);
    } else {
        $dateStmt->execute();
    }
    $dates = $dateStmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'usernames' => $usernames,
        'branches' => $branches,
        'dates' => $dates
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>