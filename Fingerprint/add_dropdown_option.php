<?php
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'samann1_Fingerprint', 'Fingerprint@2025', 'samann1_fingerprint_db');
$data = json_decode(file_get_contents('php://input'), true);
$stmt = $conn->prepare("INSERT INTO dropdown_options (category, value) VALUES (?, ?)");
$stmt->bind_param("ss", $data['dropdownCategory'], $data['dropdownValue']);
$stmt->execute();
echo json_encode(['status' => 'success']);
$conn->close();
?>