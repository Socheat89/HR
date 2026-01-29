<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
$dbHost = 'localhost';
$dbUser = 'samann1_Fingerprint';
$dbPass = 'Fingerprint@2025';
$dbName = 'samann1_fingerprint_db';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    // Get and trim POST data
    $username = trim($_POST['ឈ្មោះ'] ?? '');
    $userId = trim($_POST['ID'] ?? ''); // Capture user ID from POST data
    $action = trim($_POST['ប្រភេទស្កេន'] ?? '');
    $date = trim($_POST['ថ្ងៃ'] ?? ''); // Expected: mm/dd/yyyy
    $time = trim($_POST['ម៉ោង'] ?? ''); // Expected: HH:mm:ss
    $location = trim($_POST['location'] ?? '');
    $status = trim($_POST['status'] ?? '🔴Late'); // Default to "Late"
    $address = trim($_POST['address'] ?? '');

    // Validation: Required fields
    if (empty($username) || empty($userId) || empty($action) || empty($location)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields: username, user_id, action, or location', 'scanStatus' => '🔴Error'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validate and parse location
    if (!preg_match('/Lat:\s*([\d.-]+),\s*Long:\s*([\d.-]+)/i', $location, $matches)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid location format. Expected: "Lat: X, Long: Y"', 'scanStatus' => '🔴Error'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $latitude = (float)$matches[1];
    $longitude = (float)$matches[2];

    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid latitude or longitude values', 'scanStatus' => '🔴Error'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Parse mm/dd/yyyy date and HH:mm:ss time explicitly
    $dateTime = DateTime::createFromFormat('m/d/Y H:i:s', "$date $time");
    if ($dateTime === false) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid date or time format. Expected: mm/dd/yyyy HH:mm:ss', 'scanStatus' => '🔴Error'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $timestamp = $dateTime->format('Y-m-d H:i:s'); // Convert to MySQL DATETIME

    // Server-side time-based status validation
    $scanTime = (int)$dateTime->format('H') * 60 + (int)$dateTime->format('i'); // Convert to minutes
    $finalStatus = $status; // Start with client-sent status

    // Define time ranges (in minutes) - matches client-side logic
    $ranges = [
        ['start' => 6 * 60, 'end' => 7 * 60 + 30, 'status' => '🔵Good'], // 6:00 AM - 7:30 AM
        ['start' => 7 * 60 + 31, 'end' => 9 * 60, 'status' => '🔴Late'], // 7:31 AM - 9:00 AM
        ['start' => 9 * 60 + 1, 'end' => 15 * 60, 'status' => '🔵Good'], // 9:01 AM - 3:00 PM
        ['start' => 15 * 60 + 1, 'end' => 17 * 60 + 59, 'status' => '🔴Late'], // 3:01 PM - 5:59 PM
        ['start' => 18 * 60, 'end' => 23 * 60 + 59, 'status' => '🔵Good'] // 6:00 PM - 11:59 PM
    ];

    // Override status based on server-side time check
    foreach ($ranges as $range) {
        if ($scanTime >= $range['start'] && $scanTime <= $range['end']) {
            $finalStatus = $range['status'];
            break;
        }
    }

    // Insert into database
    $stmt = $pdo->prepare("
        INSERT INTO scan_logs (username, user_id, action, timestamp, latitude, longitude, status, address)
        VALUES (:username, :user_id, :action, :timestamp, :latitude, :longitude, :status, :address)
    ");
    $stmt->execute([
        ':username' => $username,
        ':user_id' => $userId, // Add user_id to the database
        ':action' => $action,
        ':timestamp' => $timestamp,
        ':latitude' => $latitude,
        ':longitude' => $longitude,
        ':status' => $finalStatus,
        ':address' => $address
    ]);

    http_response_code(201);
    echo json_encode(['status' => 'success', 'message' => 'Log saved successfully', 'scanStatus' => $finalStatus], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage(), 'scanStatus' => '🔴Error'], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage(), 'scanStatus' => '🔴Error'], JSON_UNESCAPED_UNICODE);
}
?>