<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // អនុញ្ញាត CORS
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// ការកំណត់ភ្ជាប់ Database
$servername = "localhost";
$username = "samann1_location_db";
$password = "samann1_location_db"; // ផ្លាស់ប្តូរលេខសម្ងាត់បើចាំបាច់
$dbname = "samann1_location_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "ការភ្ជាប់បរាជ័យ: " . $conn->connect_error]);
    exit;
}

// ទទួលទិន្នន័យពី Client
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['user_id']) || !isset($data['latitude']) || !isset($data['longitude'])) {
    echo json_encode(["status" => "error", "message" => "ទិន្នន័យមិនគ្រប់គ្រាន់"]);
    $conn->close();
    exit;
}

$user_id = $conn->real_escape_string($data['user_id']);
$latitude = $conn->real_escape_string($data['latitude']);
$longitude = $conn->real_escape_string($data['longitude']);
$accuracy = isset($data['accuracy']) ? $conn->real_escape_string($data['accuracy']) : NULL;

// បញ្ចូលទិន្នន័យទៅ Database
$sql = "INSERT INTO locations (user_id, latitude, longitude, accuracy) VALUES ('$user_id', '$latitude', '$longitude', " . ($accuracy !== NULL ? "'$accuracy'" : "NULL") . ")";
if ($conn->query($sql) === TRUE) {
    echo json_encode(["status" => "success", "message" => "ទីតាំងត្រូវបានរក្សាទុក"]);
} else {
    echo json_encode(["status" => "error", "message" => "កំហុស: " . $conn->error]);
}

$conn->close();
?>