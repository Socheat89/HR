<?php
header('Content-Type: application/json');

$botToken = '7205851039:AAHBOJmY40GvNl7M0X_FN9Ml0Fg2T_KQpb8';
$chatId = '-1002282814819';

$message = $_GET['message'] ?? '';

if ($message) {
    $url = "https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chatId&text=" . urlencode($message);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout after 10 seconds
    $response = curl_exec($ch);
    
    if ($response === false) {
        $error = curl_error($ch);
        echo json_encode(['status' => 'error', 'message' => 'cURL error: ' . $error]);
    } else {
        $result = json_decode($response, true);
        if ($result['ok']) {
            echo json_encode(['status' => 'success', 'response' => $response]);
        } else {
            echo json_encode(['status' => 'error', 'message' => $result['description']]);
        }
    }
    curl_close($ch);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No message provided']);
}
?>