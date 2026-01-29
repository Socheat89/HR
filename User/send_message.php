<?php
session_start();
require_once 'config.php';

// Set timezone to Phnom Penh
date_default_timezone_set('Asia/Phnom_Penh');

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

$sender_id = $_SESSION['user_id'];
$receiver_id = $_POST['receiver_id'];
$message = $_POST['message'];
$edit_message_id = isset($_POST['edit_message_id']) ? $_POST['edit_message_id'] : null;

if (empty($receiver_id) || empty($message)) {
    exit('Invalid input');
}

if ($edit_message_id) {
    // Update existing message
    $stmt = $conn->prepare("UPDATE messages SET message = ?, created_at = NOW() WHERE id = ? AND sender_id = ?");
    $stmt->bind_param("sii", $message, $edit_message_id, $sender_id);
    $stmt->execute();
} else {
    // Insert new message
    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $sender_id, $receiver_id, $message);
    $stmt->execute();
}

echo 'Success';
?>