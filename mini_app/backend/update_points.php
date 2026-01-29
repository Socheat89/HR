<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['telegram_id']) || !isset($data['points'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit;
}

$telegram_id = $data['telegram_id'];
$points = $data['points'];

try {
    $stmt = $pdo->prepare("INSERT INTO points (telegram_id, points) VALUES (?, ?) ON DUPLICATE KEY UPDATE points = ?");
    $stmt->execute([$telegram_id, $points, $points]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error in update_points.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>