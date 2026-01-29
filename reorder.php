<?php
session_start();
include 'db_payroll.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['order']) && is_array($data['order'])) {
    $sql = "UPDATE tasks SET sort_order = ? WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    foreach ($data['order'] as $item) {
        $stmt->bind_param('iii', $item['order'], $item['id'], $user_id);
        $stmt->execute();
    }
    $stmt->close();
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false]);
}
?>