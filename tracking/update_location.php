<?php
// Script for the driver's app to call and update its location
header('Content-Type: application/json');
$host = 'localhost'; $dbname = 'samann1_admin_panel'; $username = 'root'; $password = '';

// Check if required data is sent from the app
if (!isset($_POST['driver_id']) || !isset($_POST['lat']) || !isset($_POST['lng'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit();
}

$driver_id = (int)$_POST['driver_id'];
$lat = (float)$_POST['lat'];
$lng = (float)$_POST['lng'];

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500); // Internal Server Error
    die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
}
$conn->set_charset("utf8mb4");

// Use INSERT ... ON DUPLICATE KEY UPDATE to either create or update the location
// This is very efficient. It requires driver_id to be a PRIMARY or UNIQUE key.
$sql = "INSERT INTO driver_locations (driver_id, lat, lng) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE lat = VALUES(lat), lng = VALUES(lng)";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Failed to prepare statement.']));
}

$stmt->bind_param("idd", $driver_id, $lat, $lng);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Location updated successfully.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update location.']);
}

$stmt->close();
$conn->close();
?>
