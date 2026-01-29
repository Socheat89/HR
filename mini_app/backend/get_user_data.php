<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require 'db_connect.php';

// Validate telegram_id
if (!isset($_GET['telegram_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing telegram_id']);
    exit;
}

$telegram_id = filter_input(INPUT_GET, 'telegram_id', FILTER_VALIDATE_INT);
if ($telegram_id === false || $telegram_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid telegram_id']);
    exit;
}

try {
    // Fetch points
    $stmt = $pdo->prepare("SELECT points FROM points WHERE telegram_id = ?");
    $stmt->execute([$telegram_id]);
    $points = $stmt->fetchColumn();
    $points = $points !== false ? (int)$points : 0;

    // Fetch cart (including product_id and quantity)
    $stmt = $pdo->prepare("SELECT product_id, quantity FROM cart WHERE telegram_id = ?");
    $stmt->execute([$telegram_id]);
    $cart_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format cart as [{ productId, quantity }]
    $cart = [];
    foreach ($cart_rows as $row) {
        $cart[] = [
            'productId' => (int)$row['product_id'],
            'quantity' => (int)$row['quantity']
        ];
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'points' => $points,
        'cart' => $cart
    ]);
} catch (PDOException $e) {
    error_log("Error in get_user_data.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>