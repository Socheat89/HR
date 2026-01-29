<?php
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'samann1_Fingerprint', 'Fingerprint@2025', 'samann1_fingerprint_db');
$data = json_decode(file_get_contents('php://input'), true);
$stmt = $conn->prepare("INSERT INTO allowed_locations (latitude, longitude, description) VALUES (?, ?, ?)");
$stmt->bind_param("dds", $data['locationLatitude'], $data['locationLongitude'], $data['locationDescription']);
$stmt->execute();
echo json_encode(['status' => 'success']);
$conn->close();
?>