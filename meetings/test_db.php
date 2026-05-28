<?php
header("Content-Type: application/json");
require_once __DIR__ . '/includes/db.php';

$res = [];
try {
    $stmt = $pdo->query("SHOW TABLES");
    $res['tables'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach($res['tables'] as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table` ");
        $res['counts'][$table] = $stmt->fetchColumn();
    }
    
    echo json_encode(["status" => "success", "data" => $res]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
