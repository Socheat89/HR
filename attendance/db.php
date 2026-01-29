<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Database configuration
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'samann1_admin_panel';
$username = getenv('DB_USER') ?: 'samann1_admin_panel';
$password = getenv('DB_PASS') ?: 'admin_panel@2025';
$port = getenv('DB_PORT') ?: '3306'; // Default MySQL port
$charset = 'utf8mb4';

// DSN (Data Source Name) for PDO
$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";

try {
    // Initialize PDO connection
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Set additional MySQL settings
    $pdo->exec("SET NAMES '$charset'");
    $pdo->exec("SET CHARACTER SET $charset");

} catch (PDOException $e) {
    // Detailed error message for debugging
    $error_msg = "ការតភ្ជាប់មិនជោគជ័យ៖ " . $e->getMessage();
    $detailed_error = "Connection failed: " . $e->getMessage() . " (Code: " . $e->getCode() . ")";

    // Log to server error log
    error_log($detailed_error);

    // Display detailed error for debugging
    die("<div style='color: red; font-weight: bold; text-align: center;'>$error_msg<br>Details: $detailed_error</div>");
}
?>