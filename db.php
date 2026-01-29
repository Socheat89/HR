<?php
/**
 * Database Configuration - Optimized for Performance
 * ការកំណត់រចនាសម្ព័ន្ធមូលដ្ឋានទិន្នន័យ - ធ្វើឱ្យប្រសើរសម្រាប់ល្បឿន
 */

// Database configuration
$host = 'localhost';
$dbname = 'samann1_admin_panel';
$username = 'samann1_admin_panel';
$password = 'admin_panel@2025';

try {
    // PDO options for better performance
    $options = [
        // Persistent connection - reuses connections (faster)
        // ការតភ្ជាប់អចិន្ត្រៃយ៍ - ប្រើប្រាស់ការតភ្ជាប់ឡើងវិញ (លឿនជាង)
        PDO::ATTR_PERSISTENT => true,
        
        // Throw exceptions on errors
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        
        // Return associative arrays by default
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        
        // Use native prepared statements (more secure and faster)
        // ប្រើ prepared statements ដើម (សុវត្ថិភាព និងលឿនជាង)
        PDO::ATTR_EMULATE_PREPARES => false,
        
        // Enable buffered queries for faster reads
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ];
    
    // Create a PDO instance with optimized options
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        $options
    );
    
} catch (PDOException $e) {
    // Handle connection errors
    error_log("Database Connection Error: " . $e->getMessage());
    die("Connection failed: " . $e->getMessage());
}
?>