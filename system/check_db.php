<?php
require 'c:/xampp/htdocs/hr-new/HRM/db_connection.php';
$pdo = getPDO();
echo "daily_reports columns:\n";
print_r($pdo->query('SHOW COLUMNS FROM daily_reports')->fetchAll(PDO::FETCH_COLUMN));
echo "\nreport_tasks columns:\n";
print_r($pdo->query('SHOW COLUMNS FROM report_tasks')->fetchAll(PDO::FETCH_COLUMN));
