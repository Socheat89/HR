<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$host = "localhost";
$username = "samann1_file_manager_db"; // Replace with your MySQL username
$password = "file_manager_db";     // Replace with your MySQL password
$dbname = "samann1_file_manager_db";

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>