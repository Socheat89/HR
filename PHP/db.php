<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$servername = "localhost"; // ប្រើ localhost នៅទីនេះ
$username = "samann1_App"; // ប្តូរជាមួយឈ្មោះអ្នកប្រើថ្មី
$password = "Vvc@2025"; // ប្តូរជាមួយពាក្យសម្ងាត់ថ្មី
$dbname = "samann1_product_db"; // មូលដ្ឋានទិន្នន័យ

// បង្កើតការតភ្ជាប់
$conn = new mysqli($servername, $username, $password, $dbname);

// ពិនិត្យការតភ្ជាប់
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
