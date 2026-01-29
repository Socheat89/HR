<?php
// Database configuration
$host = 'localhost';
$dbname = 'samann1_admin_panel';
$username = 'samann1_admin_panel';
$password = 'admin_panel@2025';

try {
    // Define DSN with UTF-8 encoding
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,    // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,          // Fetch as associative arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                     // Use real prepared statements
        PDO::ATTR_PERSISTENT         => true,                      // Persistent connection for performance
    ];

    // Establish the connection
    $conn = new PDO($dsn, $username, $password, $options);

    // Explicitly set character encoding (redundancy for safety)
    $conn->exec("SET NAMES 'utf8mb4'");

    // Return the connection object
    return $conn;
} catch (PDOException $e) {
    // Log the error for debugging
    error_log("Database connection failed: " . $e->getMessage());
    
    // Display a user-friendly message and exit
    die("មានបញ្ហាក្នុងការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ។ សូមព្យាយាមម្តងទៀតនៅពេលក្រោយ។");
}