<?php
header('Content-Type: application/json');

// Database Connection
$host = 'localhost'; $dbname = 'samann1_admin_panel'; $username = 'samann1_admin_panel'; $password = 'admin_panel@2025';
$conn = new mysqli($host, $username, $password, $dbname);
$conn->set_charset("utf8mb4");
if ($conn->connect_error) { die(json_encode(['active' => false, 'message' => 'DB error'])); }

// Get driver_id from GET request
$driver_id = $_GET['driver_id'] ?? null;
if (!$driver_id) {
    die(json_encode(['active' => false, 'message' => 'Driver ID is missing']));
}

// Find an active trip for this driver
$stmt = $conn->prepare("SELECT id, start_lat, start_lng, end_lat, end_lng FROM active_trips WHERE driver_id = ? LIMIT 1");
$stmt->bind_param("s", $driver_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $trip = $result->fetch_assoc();
    echo json_encode(['active' => true, 'trip' => $trip]);
} else {
    echo json_encode(['active' => false]);
}

$stmt->close();
$conn->close();
?>