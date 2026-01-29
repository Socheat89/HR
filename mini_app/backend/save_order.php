<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');

require 'db_connect.php';

// Log the incoming request
$requestData = file_get_contents('php://input');
error_log("Incoming request to save_order.php: " . $requestData);

$data = json_decode($requestData, true);
if (!$data || !isset($data['telegram_id']) || !isset($data['total']) || !isset($data['points']) || !isset($data['cart'])) {
    error_log("Invalid request data: " . json_encode($data));
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or missing request data']);
    exit;
}

$telegram_id = $data['telegram_id'];
$total = floatval($data['total']);
$points = intval($data['points']);
$cart = $data['cart'];

// Validate cart
if (!is_array($cart) || empty($cart)) {
    error_log("Cart is empty or invalid: " . json_encode($cart));
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Cart is empty or invalid']);
    exit;
}

// Validate telegram_id
if (!is_numeric($telegram_id) || $telegram_id <= 0) {
    error_log("Invalid telegram_id: " . $telegram_id);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid telegram_id']);
    exit;
}

// Validate total and points
if (!is_numeric($total) || $total <= 0) {
    error_log("Invalid total: " . $total);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid total']);
    exit;
}
if (!is_numeric($points) || $points < 0) {
    error_log("Invalid points: " . $points);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid points']);
    exit;
}

// Check if the user exists in the users table
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE telegram_id = ?");
    $stmt->execute([$telegram_id]);
    $userExists = $stmt->fetchColumn();
    if (!$userExists) {
        error_log("User does not exist for telegram_id: " . $telegram_id);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'User does not exist']);
        exit;
    }
} catch (PDOException $e) {
    error_log("Error checking user existence: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error checking user existence']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Save the order
    $stmt = $pdo->prepare("INSERT INTO orders (telegram_id, total) VALUES (?, ?)");
    $stmt->execute([$telegram_id, $total]);
    error_log("Order inserted for telegram_id: $telegram_id, total: $total");

    // Update points
    $stmt = $pdo->prepare("INSERT INTO points (telegram_id, points) VALUES (?, ?) ON DUPLICATE KEY UPDATE points = ?");
    $stmt->execute([$telegram_id, $points, $points]);
    error_log("Points updated for telegram_id: $telegram_id, points: $points");

    // Clear the cart
    $stmt = $pdo->prepare("DELETE FROM cart WHERE telegram_id = ?");
    $stmt->execute([$telegram_id]);
    error_log("Cart cleared for telegram_id: $telegram_id");

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database error in save_order.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Unexpected error in save_order.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unexpected error: ' . $e->getMessage()]);
}
?>