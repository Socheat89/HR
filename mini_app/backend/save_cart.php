<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['telegram_id']) || !isset($data['cart'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit;
}

$telegram_id = $data['telegram_id'];
$cart = $data['cart'];

try {
    // លុបកន្ត្រកចាស់
    $stmt = $pdo->prepare("DELETE FROM cart WHERE telegram_id = ?");
    $stmt->execute([$telegram_id]);

    // បន្ថែមកន្ត្រកថ្មី
    foreach ($cart as $item) {
        if (!isset($item['id'])) continue; // រំលងប្រសិនបើ item មិនមាន id
        $stmt = $pdo->prepare("INSERT INTO cart (telegram_id, product_id) VALUES (?, ?)");
        $stmt->execute([$telegram_id, $item['id']]);
    }

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error in save_cart.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>