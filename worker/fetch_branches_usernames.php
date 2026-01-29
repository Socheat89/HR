<?php
header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    $folder = $_GET['folder'] ?? 'កម្មករ';

    $branchStmt = $pdo->prepare("SELECT DISTINCT branch FROM scan_logs WHERE folder = :folder AND branch IS NOT NULL AND branch != '' ORDER BY branch");
    $branchStmt->execute([':folder' => $folder]);
    $branches = $branchStmt->fetchAll(PDO::FETCH_COLUMN);

    $usernamesByBranch = [];
    foreach ($branches as $branch) {
        $usernameStmt = $pdo->prepare("SELECT DISTINCT username FROM scan_logs 
                                      WHERE folder = :folder AND branch = :branch AND username IS NOT NULL AND username != '' 
                                      ORDER BY username");
        $usernameStmt->execute([':folder' => $folder, ':branch' => $branch]);
        $usernamesByBranch[$branch] = $usernameStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    echo json_encode([
        'branches' => $branches,
        'usernames' => $usernamesByBranch
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>