<?php
$host = 'localhost';
$dbname = 'samann1_social_network';
$username = 'samann1_social_network';
$password = 'samann1_social_network';

try {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    // Set MySQL session timezone to Phnom Penh (UTC+7)
    $conn->query("SET time_zone = '+07:00'");
} catch (Exception $e) {
    die("Connection error: " . $e->getMessage());
}
?>