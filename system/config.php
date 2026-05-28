<?php
// Dynamic Configuration based on Centralized Connection
require_once __DIR__ . "/../db_connection.php";

// Set a flag to avoid multiple attempts in the same process if needed
// but for simplicity, we just look at the db_configs
foreach ([
    [
        'host' => 'localhost',
        'dbname' => 'samann1_admin_panel',
        'username' => 'samann1_admin_panel',
        'password' => 'admin_panel@2025'
    ],
    [
        'host' => 'localhost',
        'dbname' => 'samann1_admin_panel',
        'username' => 'root',
        'password' => ''
    ]
] as $config) {
    try {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        $conn = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2
        ]);
        if ($conn) {
            define('DB_HOST', $config['host']);
            define('DB_NAME', $config['dbname']);
            define('DB_USER', $config['username']);
            define('DB_PASS', $config['password']);
            return;
        }
    } catch (Exception $e) {
        continue;
    }
}

// Global fallback if everything fails
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'samann1_admin_panel');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}
?>