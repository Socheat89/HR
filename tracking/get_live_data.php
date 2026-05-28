<?php
// ../tracking/get_live_data.php
require '../system/db.php';
header('Content-Type: application/json');

$tracking_id = $_GET['id'] ?? null;
if (!$tracking_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tracking ID is required.']);
    exit();
}

try {
    // 1. Check if the trip is still live
    $stmt_trip = $pdo->prepare("SELECT is_live FROM trips WHERE id = ?");
    $stmt_trip->execute([$tracking_id]);
    $trip = $stmt_trip->fetch(PDO::FETCH_ASSOC);

    // 2. Fetch all points for the path
    $stmt_points = $pdo->prepare("SELECT latitude, longitude FROM tracking_points WHERE tracking_id = ? ORDER BY timestamp ASC");
    $stmt_points->execute([$tracking_id]);
    $points = $stmt_points->fetchAll(PDO::FETCH_ASSOC);

    // Format the path for Leaflet Polyline
    $path = array_map(function($point) {
        return [(float)$point['latitude'], (float)$point['longitude']];
    }, $points);

    // Get the very last point
    $latest_point = end($path) ?: null;

    echo json_encode([
        'status' => 'success',
        'is_live' => $trip ? (bool)$trip['is_live'] : false,
        'path' => $path,
        'latest_point' => $latest_point
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>