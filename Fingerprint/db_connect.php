<?php
$servername = "localhost"; // Change if using a different host
$username = "samann1_Fingerprint"; // Your MySQL username
$password = "Fingerprint@2025"; // Your MySQL password
$dbname = "samann1_fingerprint_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname );

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
    $conn->set_charset("utf8mb4");
}
?>
