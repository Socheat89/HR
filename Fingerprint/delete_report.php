<?php
$dbHost = 'localhost';
$dbUser = 'samann1_daily_report_db';
$dbPass = 'daily_report_db';
$dbName = 'samann1_daily_report_db';

$pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$id = $_GET['id'] ?? '';
$pdo->beginTransaction();
$pdo->prepare("DELETE FROM report_tasks WHERE report_id = ?")->execute([$id]);
$pdo->prepare("DELETE FROM reports WHERE id = ?")->execute([$id]);
$pdo->commit();

header('Location: admin_panel.php');
exit;