<?php
// Add this for debugging server-side errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set the content type to JSON for the response
header('Content-Type: application/json');

// --- DATABASE CONNECTION ---
$host = 'localhost';
$dbname = 'samann1_admin_panel';
$username = 'samann1_admin_panel';
$password = 'admin_panel@2025';

// Establish connection
$conn = new mysqli($host, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

// Check for connection errors
if ($conn->connect_error) {
    // Send a JSON response for connection failure
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

// --- DATA VALIDATION ---
// Check if the required POST data is received and not empty
if (!isset($_POST['name']) || empty(trim($_POST['name'])) || !isset($_POST['latitude']) || empty($_POST['latitude']) || !isset($_POST['longitude']) || empty($_POST['longitude'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data. Name and coordinates are required.']);
    exit();
}

$name = trim($_POST['name']);
$latitude = $_POST['latitude'];
$longitude = $_POST['longitude'];

// --- DATABASE INSERT (Using Prepared Statements for Security) ---
$sql = "INSERT INTO customers (name, latitude, longitude) VALUES (?, ?, ?)";

// Prepare the statement to prevent SQL injection
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare the SQL statement: ' . $conn->error]);
    exit();
}

// Bind the parameters (s = string, d = double/decimal)
$stmt->bind_param("sdd", $name, $latitude, $longitude);

// Execute the statement
if ($stmt->execute()) {
    // If successful, send back the new customer data
    echo json_encode([
        'success' => true,
        'newCustomer' => [
            'name' => $name,
            'coordinates' => [(float)$latitude, (float)$longitude]
        ]
    ]);
} else {
    // If execution fails
    echo json_encode(['success' => false, 'message' => 'Failed to save the location: ' . $stmt->error]);
}

// Close the statement and connection
$stmt->close();
$conn->close();
?>