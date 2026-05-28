<?php
// Centralized Database Connection for the attendance system
require_once __DIR__ . '/../db_connection.php';

$success = false;
foreach ($db_configs as $config) {
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET NAMES 'utf8mb4'");
        
        // Success! Set legacy variables for compatibility
        $host = $config['host'];
        $dbname = $config['dbname'];
        $username = $config['username'];
        $password = $config['password'];
        $success = true;
        break;
    } catch (PDOException $e) {
        continue;
    }
}

if (!$success) {
    $error_msg = "Database connection failed with all provided credentials.";
    error_log($error_msg);
    die("<div style='color: red; font-weight: bold; text-align: center; margin-top: 20px;'>ការតភ្ជាប់មិនជោគជ័យ៖ $error_msg</div>");
}
?>