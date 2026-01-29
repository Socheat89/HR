<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$host = 'localhost';
$dbname = 'samann1_mini_app_db';
$username = 'samann1_mini_app_db';
$password = 'samann1_mini_app_db@2025';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES 'utf8mb4'");
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'ការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យបានបរាជ័យ']);
    exit();
}

// Receive webhook data (adjust based on KHQR provider's payload)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$payment_id = $data['payment_id'] ?? '';
$status = $data['status'] ?? '';
$amount = $data['amount'] ?? 0;
$currency = $data['currency'] ?? '';
$transaction_id = $data['transaction_id'] ?? '';

if (!$payment_id || !$status) {
    http_response_code(400);
    echo json_encode(['error' => 'ទិន្នន័យ Webhook មិនត្រឹមត្រូវ']);
    exit;
}

try {
    // Verify payment exists
    $stmt = $pdo->prepare("SELECT id FROM payments WHERE payment_id = ?");
    $stmt->execute([$payment_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'រកការទូទាត់មិនឃើញ']);
        exit;
    }

    // Update payment status
    $stmt = $pdo->prepare("UPDATE payments SET status = ?, transaction_id = ? WHERE payment_id = ?");
    $stmt->execute([$status, $transaction_id, $payment_id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'មិនអាចធ្វើបច្ចុប្បន្នភាពការទូទាត់បាន']);
}
?>