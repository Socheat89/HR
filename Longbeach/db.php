<?php
$host = "localhost";
$username = "samann1_longbeach_db";
$password = "longbeach@2025";
$dbname = "samann1_longbeach_db";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Ensure UTF-8 encoding for Khmer text
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>