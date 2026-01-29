<?php
function sendTelegramMessage($chatId, $message, $botToken = '7827330496:AAH9nRVCLnlIBjs-tXHQs9KE3T7QSX1PkQY') {
    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $response = curl_exec($ch);

    // Debugging output
    if (curl_errno($ch)) {
        error_log("CURL Error: " . curl_error($ch));
    } else {
        error_log("Telegram API Response: " . $response);
    }

    curl_close($ch);
    return $response;
}
?>