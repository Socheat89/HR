<?php
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'samann1_Fingerprint', 'Fingerprint@2025', 'samann1_fingerprint_db');
$data = json_decode(file_get_contents('php://input'), true);
$stmt = $conn->prepare("INSERT INTO time_ranges (type, start_hour, start_minute, end_hour, end_minute) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("siiii", $data['timeRangeType'], $data['startHour'], $data['startMinute'], $data['endHour'], $data['endMinute']);
$stmt->execute();
echo json_encode(['status' => 'success']);
$conn->close();
?>