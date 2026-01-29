<?php
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'samann1_Fingerprint', 'Fingerprint@2025', 'samann1_fingerprint_db');

$data = json_decode(file_get_contents('php://input'), true);
$stmt = $conn->prepare("INSERT INTO users (username, user_id, department, position, branch, folder) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssss", $data['username'], $data['user_id'], $data['department'], $data['position'], $data['branch'], $data['folder']);
$stmt->execute();
echo json_encode(['status' => 'success']);
$conn->close();
?>