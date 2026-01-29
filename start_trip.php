<?php
header('Content-Type: application/json');

// --- Database Connection ---
$host = 'localhost'; 
$dbname = 'samann1_admin_panel'; 
$username = 'samann1_admin_panel'; 
$password = 'admin_panel@2025';

$conn = new mysqli($host, $username, $password, $dbname);

// Check connection first
if ($conn->connect_error) { 
    http_response_code(500);
    // Log the error for yourself, but don't show details to the user
    error_log("Database connection failed: " . $conn->connect_error);
    die(json_encode(['success' => false, 'message' => 'Database connection failed.'])); 
}

$conn->set_charset("utf8mb4");
// Set timezone for this specific connection to Phnom Penh (UTC+7)
$conn->query("SET time_zone = '+07:00'");


// --- Get Data from POST Request ---
$start_lat = $_POST['start_lat'] ?? null;
$start_lng = $_POST['start_lng'] ?? null;
$end_lat = $_POST['end_lat'] ?? null;
$end_lng = $_POST['end_lng'] ?? null;
$customer_name = $_POST['customer_name'] ?? null; 
$driver_id = $_POST['driver_id'] ?? null;


// --- IMPROVED VALIDATION ---
// Check for presence and correct data type (numeric)
if (
    !isset($driver_id) || !is_numeric($driver_id) ||
    !isset($start_lat) || !is_numeric($start_lat) ||
    !isset($start_lng) || !is_numeric($start_lng) ||
    !isset($end_lat)   || !is_numeric($end_lat) ||
    !isset($end_lng)   || !is_numeric($end_lng) ||
    empty(trim($customer_name)) // Use trim() to avoid names with only spaces
) {
    http_response_code(400); // Bad Request
    die(json_encode([
        'success' => false, 
        'message' => 'Missing or invalid data. Please check all fields.'
    ]));
}


// --- Prepare and Execute SQL Statement ---
// CRITICAL FIX: Added `start_time` column and used the SQL function `NOW()`
$sql = "INSERT INTO active_trips (driver_id, start_lat, start_lng, end_lat, end_lng, customer_name, start_time) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    http_response_code(500);
    error_log("SQL Prepare failed: " . $conn->error); // Log error for debugging
    die(json_encode(['success' => false, 'message' => 'Failed to prepare SQL statement.']));
}

// Bind parameters. The types and number of variables match the `?` in the SQL.
// `NOW()` is a SQL function, so it doesn't need a parameter.
$stmt->bind_param("idddds", $driver_id, $start_lat, $start_lng, $end_lat, $end_lng, $customer_name);

if ($stmt->execute()) {
    // SUCCESS
    $new_trip_id = $conn->insert_id;

    echo json_encode([
        'success' => true, 
        'message' => 'Trip started successfully.',
        'trip_id' => $new_trip_id,
        'driver_id' => (int)$driver_id
    ]);
} else {
    // FAILURE
    http_response_code(500);
    error_log("SQL Execute failed: " . $stmt->error); // Log error for debugging
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to start the trip.'
    ]);
}

$stmt->close(); 
$conn->close();
?>