<?php
// save_push_subscription.php

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Read JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE || !$input || !isset($input['user_id'], $input['username'], $input['subscription'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or malformed JSON input']);
    exit;
}

// Validate and sanitize data
$user_id = preg_replace('/[^\w\-]/', '', $input['user_id']);
$username = htmlspecialchars($input['username'], ENT_QUOTES, 'UTF-8');
$subscription = json_encode($input['subscription'], JSON_UNESCAPED_SLASHES);

if (empty($user_id) || strlen($user_id) > 50) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid user_id']);
    exit;
}

// Save to file (use a database like MySQL in production for scalability)
$dir = __DIR__ . '/push_subscriptions';
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to create directory']);
    exit;
}

$file = $dir . '/' . $user_id . '.json';
if (file_put_contents($file, $subscription) === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to save subscription']);
    exit;
}

// Log the subscription
$log_file = $dir . '/subscriptions.log';
$log_entry = date('c') . " user_id: $user_id, username: $username\n";
if (file_put_contents($log_file, $log_entry, FILE_APPEND) === false) {
    // Log failure silently (don't fail the request)
    error_log("Failed to write to subscriptions.log for user_id: $user_id");
}

echo json_encode(['status' => 'success']);
?>