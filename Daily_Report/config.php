<?php
$host = "localhost";
$user = "samann1_location_db";   // Change if needed
$pass = "location@2025";       // Change if needed
$dbname = "samann1_location_db";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
