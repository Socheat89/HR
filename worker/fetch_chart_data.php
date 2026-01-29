<?php
session_start();

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
    
    $where = [];
    $params = [];
    
    if ($is_sub_user && $branch_filter) {
        $where[] = 'branch = ?';
        $params[] = $branch_filter;
    } elseif ($is_admin && !empty($_GET['branches'])) {
        $branches = explode(',', $_GET['branches']);
        $placeholders = implode(',', array_fill(0, count($branches), '?'));
        $where[] = "branch IN ($placeholders)";
        $params = array_merge($params, $branches);
    }
    if (!empty($_GET['start_date'])) {
        $where[] = 'DATE(timestamp) >= ?';
        $params[] = $_GET['start_date'];
    }
    if (!empty($_GET['end_date'])) {
        $where[] = 'DATE(timestamp) <= ?';
        $params[] = $_GET['end_date'];
    }
    if ($is_admin && !empty($_GET['usernames'])) {
        $usernames = explode(',', $_GET['usernames']);
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $where[] = "username IN ($placeholders)";
        $params = array_merge($params, $usernames);
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $stmt = $pdo->prepare("
        SELECT DATE(timestamp) as scan_date, COUNT(*) as scan_count 
        FROM scan_logs 
        $whereClause 
        GROUP BY DATE(timestamp) 
        ORDER BY scan_date ASC
    ");
    foreach ($params as $index => $value) {
        $stmt->bindValue($index + 1, $value);
    }
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $dates = [];
    $counts = [];
    foreach ($results as $row) {
        $dates[] = date('m/d/Y', strtotime($row['scan_date']));
        $counts[] = (int)$row['scan_count'];
    }
    
    echo json_encode([
        'dates' => $dates,
        'counts' => $counts
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>