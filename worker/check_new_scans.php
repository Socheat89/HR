<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db;charset=utf8", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8'");

    $lastCheck = $_SESSION['last_scan_check'] ?? date('Y-m-d H:i:s', strtotime('-30 seconds'));

    $whereClause = "";
    $params = [];
    if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'sub_user' && isset($_SESSION['branch'])) {
        $whereClause = " AND branch = :branch";
        $params[':branch'] = $_SESSION['branch'];
    }

    // រាប់ការស្កេនថ្មីសរុប
    $query = "SELECT COUNT(*) as new_scans FROM scan_logs WHERE timestamp > :last_check" . $whereClause;
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':last_check', $lastCheck, PDO::PARAM_STR);
    if (!empty($params)) {
        $stmt->bindValue(':branch', $params[':branch'], PDO::PARAM_STR);
    }
    $stmt->execute();
    $newScans = $stmt->fetchColumn();

    // អ្នកប្រើប្រាស់ដែលបានមើល (viewed = 1)
    $viewedQuery = "SELECT username, COUNT(*) as count 
                    FROM scan_logs 
                    WHERE timestamp > :last_check AND viewed = 1" . $whereClause . " 
                    GROUP BY username 
                    LIMIT 5";
    $viewedStmt = $pdo->prepare($viewedQuery);
    $viewedStmt->bindValue(':last_check', $lastCheck, PDO::PARAM_STR);
    if (!empty($params)) {
        $viewedStmt->bindValue(':branch', $params[':branch'], PDO::PARAM_STR);
    }
    $viewedStmt->execute();
    $viewedUsers = $viewedStmt->fetchAll(PDO::FETCH_ASSOC);

    // អ្នកប្រើប្រាស់ដែលមិនទាន់មើល (viewed = 0)
    $notViewedQuery = "SELECT username, COUNT(*) as count 
                       FROM scan_logs 
                       WHERE timestamp > :last_check AND viewed = 0" . $whereClause . " 
                       GROUP BY username 
                       LIMIT 5";
    $notViewedStmt = $pdo->prepare($notViewedQuery);
    $notViewedStmt->bindValue(':last_check', $lastCheck, PDO::PARAM_STR);
    if (!empty($params)) {
        $notViewedStmt->bindValue(':branch', $params[':branch'], PDO::PARAM_STR);
    }
    $notViewedStmt->execute();
    $notViewedUsers = $notViewedStmt->fetchAll(PDO::FETCH_ASSOC);

    $_SESSION['last_scan_check'] = date('Y-m-d H:i:s');

    echo json_encode([
        'new_scans' => $newScans,
        'viewed_users' => $viewedUsers,
        'not_viewed_users' => $notViewedUsers
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>