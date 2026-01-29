<?php
/**
 * ផ្ញើសារទៅកាន់ Telegram chat តាមរយៈ Bot API។
 *
 * @param string $botToken Token របស់ Telegram Bot របស់អ្នក។
 * @param string $chatId ID របស់ Chat ដែលអ្នកចង់ផ្ញើសារទៅ។
 * @param string $message សារដែលត្រូវផ្ញើ។
 * @return bool|string លទ្ធផលពី Telegram API ឬ false ប្រសិនបើបរាជ័យ។
 */
function sendTelegramNotification($botToken, $chatId, $message) {
    // រៀបចំ URL សម្រាប់ហៅទៅ Telegram API
    $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";

    // រៀបចំទិន្នន័យដែលត្រូវផ្ញើ (POST data)
    $postData = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML' // អាចប្រើ HTML formatting ក្នុងសារបាន (e.g., <b>, <code>)
    ];

    // ចាប់ផ្ដើមប្រើ cURL ដើម្បីផ្ញើ request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // កំណត់ Timeout ដើម្បីការពារកុំឱ្យទំព័រគាំងយូរ

    // ប្រសិនបើមានបញ្ហា SSL certificate (ជួនកាលកើតមានលើ local server)
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        // ប្រសិនបើមានបញ្ហា cURL, កត់ត្រាវាទុកក្នុង error log របស់ server
        error_log("Telegram Notification cURL Error: " . $error);
        return false;
    }

    return $response;
}
?>