<?php
$servername = "localhost";
$username = "samann1_Fingerprint";
$password = "Fingerprint@2025";
$dbname = "samann1_fingerprint_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

date_default_timezone_set('Asia/Phnom_Penh');
$conn->query("SET time_zone = '+07:00'");
?>