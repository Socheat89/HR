<?php
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'samann1_Fingerprint', 'Fingerprint@2025', 'samann1_fingerprint_db');

$data = json_decode(file_get_contents('php://input'), true);
$id = $_GET['id'];
$stmt = $conn->prepare("UPDATE users SET username=?, user_id=?, department=?, position=?, branch=?, folder=? WHERE id=?");
$stmt->bind_param("ssssssi", $data['username'], $data['user_id'], $data['department'], $data['position'], $data['branch'], $data['folder'], $id);
$stmt->execute();
echo json_encode(['status' => 'success']);
$conn->close();
?>