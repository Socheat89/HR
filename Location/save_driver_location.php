<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // អនុញ្ញាតឱ្យទូរស័ព្ទចូលប្រើ API
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// ភ្ជាប់ទៅ MySQL
$db = new PDO("mysql:host=localhost;dbname=samann1_location_db", "samann1_location_db", "location_db@2025");

try {
    // ទទួលទិន្នន័យ JSON ពី request
    $data = json_decode(file_get_contents('php://input'), true);
    
    $driver_id = $data['driver_id'] ?? null;
    $latitude = $data['latitude'] ?? null;
    $longitude = $data['longitude'] ?? null;
    $status = $data['status'] ?? 'offline';

    // ផ្ទៀងផ្ទាត់ទិន្នន័យ
    if (!$driver_id || !$latitude || !$longitude) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit;
    }

    // រក្សាទុកទីតាំង
    $stmt = $db->prepare("INSERT INTO driver_locations (driver_id, latitude, longitude, status, timestamp) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$driver_id, $latitude, $longitude, $status]);

    echo json_encode(['status' => 'success', 'message' => 'Location saved']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>