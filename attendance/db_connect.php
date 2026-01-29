<?php
$host = 'localhost';
$dbname = 'samann1_admin_panel';
$username = 'samann1_admin_panel';
$password = 'admin_panel@2025';


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");
   
} catch (PDOException $e) {
    $error_msg = "Connection failed: " . $e->getMessage();
    error_log($error_msg); // Log to server error log
    die("<div style='color: red; font-weight: bold;'>ការតភ្ជាប់មិនជោគជ័យ៖ $error_msg</div>");
}
?>