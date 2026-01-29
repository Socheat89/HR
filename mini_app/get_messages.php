<?php
header('Content-Type: application/json');

$chatId = isset($_GET['chat_id']) ? $_GET['chat_id'] : '';
$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

if (!$chatId) {
    echo json_encode(['ok' => false, 'error' => 'Missing chat_id']);
    exit;
}

// Check if messages.json exists
$messagesFile = 'messages.json';
if (!file_exists($messagesFile)) {
    file_put_contents($messagesFile, json_encode([]));
}

// Load messages
try {
    $messages = json_decode(file_get_contents($messagesFile), true);
    if ($messages === null) {
        throw new Exception('Invalid JSON in messages.json');
    }
} catch (Exception $e) {
    error_log("Error reading messages.json: " . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Failed to load messages']);
    exit;
}

// Filter messages
$filteredMessages = array_filter($messages, function ($msg) use ($chatId, $lastId) {
    return $msg['chat_id'] === $chatId && $msg['id'] > $lastId;
});

echo json_encode(['ok' => true, 'messages' => array_values($filteredMessages)]);
?>