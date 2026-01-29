<?php
session_start();
require_once 'config.php';

// Set timezone to Phnom Penh
date_default_timezone_set('Asia/Phnom_Penh');

if (!isset($_SESSION['user_id']) || !isset($_GET['receiver_id'])) {
    exit('Unauthorized or invalid receiver');
}

$sender_id = $_SESSION['user_id'];
$receiver_id = $_GET['receiver_id'];

$stmt = $conn->prepare("SELECT id, sender_id, message, created_at FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) ORDER BY created_at ASC");
$stmt->bind_param("iiii", $sender_id, $receiver_id, $receiver_id, $sender_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $class = $row['sender_id'] == $sender_id ? 'sent' : 'received';
    $message_id = $row['id'];
    // Format created_at in Phnom Penh time with 12-hour format and AM/PM
    $formatted_time = date('h:i A', strtotime($row['created_at']));
    echo "<div class='message $class' data-message-id='$message_id'>
            <span class='message-content'>" . htmlspecialchars($row['message']) . "</span>
            <div class='message-meta'>" . $formatted_time . "</div>
          </div>";
}
?>