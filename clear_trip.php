<?php
header('Content-Type: application/json');
$host = 'localhost'; $dbname = 'samann1_admin_panel'; $username = 'samann1_admin_panel'; $password = 'admin_panel@2025';
$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) { die(json_encode(['success' => false, 'message' => 'Database connection failed.'])); }

$trip_id = $_POST['trip_id'] ?? null;
if (!$trip_id) { die(json_encode(['success' => false, 'message' => 'Trip ID is missing.'])); }

$stmt = $conn->prepare("DELETE FROM active_trips WHERE id = ?");
$stmt->bind_param("i", $trip_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Trip cleared successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to clear trip.']);
}
$stmt->close(); $conn->close();
?>