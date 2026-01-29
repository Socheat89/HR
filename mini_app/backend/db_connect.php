<?php
$host = 'localhost';
$dbname = 'samann1_mini_app_db';
$username = 'samann1_mini_app_db';
$password = 'samann1_mini_app_db@2025';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    echo "Connected successfully";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>