<?php
require_once 'c:/xampp/htdocs/hr-new/HRM/db_connection.php';
$pdo = getPDO();
$stmt = $pdo->query("SHOW COLUMNS FROM daily_reports");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "COLUMNS: " . implode(", ", $columns) . "\n";
