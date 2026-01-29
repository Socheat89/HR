<?php
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'samann1_Fingerprint', 'Fingerprint@2025', 'samann1_fingerprint_db');
$data = json_decode(file_get_contents('php://input'), true);
$stmt = $conn->prepare("INSERT INTO scan_status_ranges (start_hour, start_minute, end_hour, end_minute, status) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("iiiis", $data['scanStartHour'], $data['scanStartMinute'], $data['scanEndHour'], $data['scanEndMinute'], $data['scanStatus']);
$stmt->execute();
echo json_encode(['status' => 'success']);
$conn->close();
?>