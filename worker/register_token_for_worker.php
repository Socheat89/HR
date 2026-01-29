<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Database connection settings
$servername = "localhost";
$username = "samann1_Fingerprint"; // Your username
$password = "Fingerprint@2025"; // Your password
$dbname = "samann1_fingerprint_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check the connection
if ($conn->connect_error) {
    // Provide detailed error information for debugging
    die(json_encode([
        'status' => 'error',
        'message' => 'Connection failed: ' . $conn->connect_error,
        'host' => $servername, // Host for debugging
        'user' => $username, // Username for debugging
    ]));
}

// Set timezone for Phnom Penh
date_default_timezone_set('Asia/Phnom_Penh');
$conn->query("SET time_zone = '+07:00'");

// Get and validate request data
$request = json_decode(file_get_contents('php://input'), true);
if (!$request || !isset($request['token']) || !isset($request['username'])) {
    die(json_encode(['status' => 'error', 'message' => 'Invalid or missing input data']));
}

$browserToken = $request['token'];
$username = $request['username'];

// Basic input validation
if (empty($browserToken) || empty($username) || strlen($browserToken) > 255 || strlen($username) > 50) {
    die(json_encode(['status' => 'error', 'message' => 'Token or username invalid (too long or empty)']));
}

$tokenLimit = 4;

// Check active token count for the user
$stmt = $conn->prepare("SELECT COUNT(*) as active_count FROM allowed_tokens WHERE username = ? AND is_active = 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$activeCount = $row['active_count'];
$stmt->close();

// Check if the token already exists for this username
$stmt = $conn->prepare("SELECT token FROM allowed_tokens WHERE token = ? AND username = ?");
$stmt->bind_param("ss", $browserToken, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['status' => 'success', 'message' => 'Token already registered and matched']);
} else {
    if ($activeCount >= $tokenLimit) {
        echo json_encode(['status' => 'error', 'message' => 'Token limit reached for this username']);
    } else {
        // Insert new token with Phnom Penh timestamp
        $timestamp = date('Y-m-d H:i:s'); // Current time in Phnom Penh, e.g., 2025-03-04 12:52:00
        $stmt = $conn->prepare("INSERT INTO allowed_tokens (token, username, is_active, created_at) VALUES (?, ?, 1, ?)");
        $stmt->bind_param("sss", $browserToken, $username, $timestamp);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Token and username registered',
                'registered_at' => $timestamp
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error registering: ' . $stmt->error]);
        }
        $stmt->close();
    }
}

$conn->close();
?>
