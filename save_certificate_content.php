<?php
session_start();
require_once 'log.php';

// Only admin can save
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Database connection
try {
    $db = new PDO("mysql:host=localhost;dbname=samann1_admin_panel;charset=utf8mb4", "samann1_admin_panel", "admin_panel@2025");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$poll_id = $data['poll_id'] ?? null;
$winner_id = $data['winner_id'] ?? null;
$element_id = $data['element_id'] ?? null;
$content = $data['content'] ?? '';

if (!$poll_id || !$winner_id || !$element_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

try {
    // Insert or update the content
    $stmt = $db->prepare("
        INSERT INTO certificate_positions (poll_id, winner_id, element_id, content)
        VALUES (:poll_id, :winner_id, :element_id, :content)
        ON DUPLICATE KEY UPDATE content = :content
    ");
    $stmt->execute([
        'poll_id' => $poll_id,
        'winner_id' => $winner_id,
        'element_id' => $element_id,
        'content' => $content
    ]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Save content error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Save failed']);
}
?>