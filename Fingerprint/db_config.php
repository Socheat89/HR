<?php

// Database configuration
$host = 'localhost';          // Usually 'localhost' unless your host specifies otherwise
$dbname = 'samann1_fingerprint_db'; // Replace with your actual database name
$username = 'samann1_Fingerprint';   // Replace with your actual database username
$password = 'Fingerprint@2025';  // Replace with your actual database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Log error to file instead of displaying it in production
    error_log("Connection failed: " . $e->getMessage());
    http_response_code(500);
    die("Database connection failed. Please try again later.");
}
?>