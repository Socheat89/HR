<?php
// telegram.php
header('Content-Type: application/json');

$telegramToken = "7205851039:AAHBOJmY40GvNl7M0X_FN9Ml0Fg2T_KQpb8";
$chatId = "-1002282814819";

$input = json_decode(file_get_contents("php://input"), true);
$message = $input['message'] ?? '';

if (empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'សូមផ្តល់សារ!']);
    exit;
}

$url = "https://api.telegram.org/bot$telegramToken/sendMessage";
$postData = [
    'chat_id' => $chatId,
    'text' => $message
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    echo json_encode(['status' => 'success', 'response' => json_decode($response, true)]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'មានបញ្ហាក្នុងការផ្ញើសារ!']);
}
?>