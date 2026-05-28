<?php
session_start();
require_once '../system/log.php';

// Only admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit();
}

$db = new PDO("mysql:host=localhost;dbname=samann1_admin_panel;charset=utf8mb4", 'root', '');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$poll_id = filter_input(INPUT_POST, 'poll_id', FILTER_VALIDATE_INT);
$winner_id = filter_input(INPUT_POST, 'winner_id', FILTER_VALIDATE_INT);
$positions = json_decode($_POST['positions'], true);

if ($poll_id && $winner_id && is_array($positions)) {
    // Delete old positions
    $stmt_del = $db->prepare("DELETE FROM certificate_positions WHERE poll_id = :poll_id AND winner_id = :winner_id");
    $stmt_del->execute(['poll_id' => $poll_id, 'winner_id' => $winner_id]);

    // Insert new
    $stmt_ins = $db->prepare("INSERT INTO certificate_positions (poll_id, winner_id, element_id, x, y) VALUES (:poll_id, :winner_id, :element_id, :x, :y)");
    foreach ($positions as $pos) {
        $stmt_ins->execute([
            'poll_id' => $poll_id,
            'winner_id' => $winner_id,
            'element_id' => $pos['id'],
            'x' => $pos['x'],
            'y' => $pos['y']
        ]);
    }
    echo 'Saved';
} else {
    http_response_code(400);
}
?>
