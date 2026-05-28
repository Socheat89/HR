<?php

// =======================================================
// កំណត់តម្លៃថេរ (CONSTANTS)
// =======================================================

// ដាក់ TOKEN របស់ Telegram Bot របស់អ្នកនៅទីនេះ
define('TELEGRAM_BOT_TOKEN', '7680086124:AAHrvdz-mOx3pO1Ijqvh7BHTeGh2JB5JuwQ');

// ដាក់ API KEY របស់ OpenAI របស់អ្នកនៅទីនេះ
define('OPENAI_API_KEY', 'YOUR-OPENAI-API-KEY-HERE');

// =======================================================
// ផ្នែកទទួលទិន្នន័យពី TELEGRAM (WEBHOOK)
// =======================================================

// ទទួលទិន្នន័យ JSON ដែលបានផ្ញើពី Telegram
$update = json_decode(file_get_contents('php://input'), true);

// ពិនិត្យមើលថាតើមានសារផ្ញើមកដែរឬទេ
if (!isset($update['message'])) {
    // បើមិនមានទេ ให้ออกจากកម្មវិធី
    exit();
}

// ទាញយកទិន្នន័យចាំបាច់ចេញមក
$chat_id = $update['message']['chat']['id']; // ID របស់បន្ទប់ជជែក
$user_question = $update['message']['text']; // សារដែលអ្នកប្រើប្រាស់បានផ្ញើមក

// =======================================================
// ផ្នែកហៅប្រើ AI ដើម្បីដំណើរការសំណួរ
// =======================================================

// បង្កើត Function ដើម្បីส่งคำถามไปให้ OpenAI
function getAiResponse($prompt) {
    $api_url = 'https://api.openai.com/v1/chat/completions';

    // ទិន្នន័យដែលត្រូវផ្ញើទៅកាន់ API (Payload)
    $data = [
        'model' => 'gpt-3.5-turbo', // អាចប្តូរទៅជាម៉ូដែលផ្សេងទៀតបាន ឧទាហរណ៍ gpt-4
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.7, // តម្លៃនៃការច្នៃប្រឌិតរបស់ចម្លើយ (0.1 - 1.0)
        'max_tokens' => 1500, // จำนวน Token អតិបរមាสำหรับคำตอบ
    ];

    // បង្កើត cURL request
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // ពិនិត្យមើលថាតើការហៅ API បានជោគជ័យឬអត់
    if ($http_code !== 200 || $response === false) {
        // បើមិនជោគជ័យ ត្រូវផ្ញើសារជូនដំណឹង
        return "សូមអភ័យទោស មានបញ្ហាក្នុងការភ្ជាប់ទៅកាន់ AI។";
    }

    $result = json_decode($response, true);
    
    // ទាញយកចម្លើយពី AI ចេញមក
    return $result['choices'][0]['message']['content'];
}


// =======================================================
// ផ្នែកផ្ញើសារត្រឡប់ទៅអ្នកប្រើប្រាស់ក្នុង TELEGRAM
// =======================================================

// បង្កើត Function ដើម្បីផ្ញើសារត្រឡប់ទៅវិញ
function sendMessage($chatId, $text) {
    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $params = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown' // អាចប្រើ Markdown ដើម្បីរចនាទម្រង់អត្ថបទបាន
    ];
    
    // ផ្ញើ request
    file_get_contents($url . '?' . http_build_query($params));
}

// =======================================================
// ដំណើរការหลัก (MAIN EXECUTION)
// =======================================================

// 1. ផ្ញើ Action "typing..." ដើម្បីឱ្យអ្នកប្រើប្រាស់ដឹងថា Bot កំពុងដំណើរការ
file_get_contents('https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendChatAction?chat_id=' . $chat_id . '&action=typing');

// 2. ទទួលចម្លើយពី AI
$ai_answer = getAiResponse($user_question);

// 3. ផ្ញើចម្លើយត្រឡប់ទៅអ្នកប្រើប្រាស់វិញ
sendMessage($chat_id, $ai_answer);

?>