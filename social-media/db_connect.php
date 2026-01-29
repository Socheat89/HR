<?php
// db_connect.php
$db_host = 'localhost';
$db_user = 'samann1_facebook-bot';
$db_pass = 'facebook-bot!@#';
$db_name = 'samann1_facebook-bot';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("ការតភ្ជាប់បរាជ័យ: " . $conn->connect_error);
}
?>