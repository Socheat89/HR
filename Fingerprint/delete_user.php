<?php
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'samann1_Fingerprint', 'Fingerprint@2025', 'samann1_fingerprint_db');

$id = $_GET['id'];
$conn->query("DELETE FROM users WHERE id = $id");
echo json_encode(['status' => 'success']);
$conn->close();
?>