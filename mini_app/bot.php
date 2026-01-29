<?php
// Telegram Bot Configuration
$botToken = "7845947735:AAHJ_PoXTysXnhj8378N9X0e-C920a1REVo";
$miniAppUrl = "https://app.vvc.asia/mini_app/index.html";
$contactUrl = "https://t.me/SK_Smart093";
$imageUrl = "https://images.unsplash.com/photo-1516321318423-f06f85e504b3";
$apiUrl = "https://api.telegram.org/bot$botToken/";

// Log function
function logMessage($message) {
    file_put_contents('bot_log.txt', date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

// Function to send photo
function sendPhoto($chatId, $photoUrl, $caption, $replyMarkup = null, $keyboardMarkup = null) {
    global $apiUrl;
    $url = $apiUrl . "sendPhoto";
    
    $postFields = [
        'chat_id' => $chatId,
        'photo' => $photoUrl,
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];
    
    if ($replyMarkup) {
        $postFields['reply_markup'] = json_encode($replyMarkup);
    }
    
    if ($keyboardMarkup) {
        $postFields['reply_markup'] = json_encode($keyboardMarkup);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Remove in production
    $response = curl_exec($ch);
    
    if ($response === false) {
        logMessage("cURL Error: " . curl_error($ch));
    } else {
        $responseData = json_decode($response, true);
        if (!$responseData['ok']) {
            logMessage("Telegram API Error: " . $responseData['description']);
        }
    }
    
    curl_close($ch);
    return $response;
}

// Function to send message
function sendMessage($chatId, $text, $replyMarkup = null, $keyboardMarkup = null) {
    global $apiUrl;
    $url = $apiUrl . "sendMessage";
    
    $postFields = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($replyMarkup) {
        $postFields['reply_markup'] = json_encode($replyMarkup);
    }
    
    if ($keyboardMarkup) {
        $postFields['reply_markup'] = json_encode($keyboardMarkup);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Remove in production
    $response = curl_exec($ch);
    
    if ($response === false) {
        logMessage("cURL Error: " . curl_error($ch));
    } else {
        $responseData = json_decode($response, true);
        if (!$responseData['ok']) {
            logMessage("Telegram API Error: " . $responseData['description']);
        }
    }
    
    curl_close($ch);
    return $response;
}

// Function to send welcome message
function sendWelcomeMessage($chatId) {
    global $imageUrl, $miniAppUrl, $contactUrl;
    
    $caption = "🌟 <b>ទិញឥឡូវ សន្សំភ្លាម! ផលិតផលដ៏ល្អឥតខ្ចោះរង់ចាំអ្នក! 🛒</b> 🌟\n\n🚀 <b>ចាប់ផ្តើមទិញឥឡូវនេះ!</b>";
    
    $replyMarkup = [
        'inline_keyboard' => [
            [
                ['text' => '🎮 បើក Mini App', 'web_app' => ['url' => $miniAppUrl]],
                ['text' => 'ℹ️ ព័ត៌មានបន្ថែម', 'url' => 'https://app.vvc.asia']
            ]
        ]
    ];
    
    $keyboardMarkup = [
        'keyboard' => [
            [['text' => 'ទិញទំនិញឥឡូវនេះ'], ['text' => 'ទំនាក់ទំនងផ្ទាល់']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];
    
    $response = sendPhoto($chatId, $imageUrl, $caption, $replyMarkup, $keyboardMarkup);
    $responseData = json_decode($response, true);
    if (!$responseData['ok']) {
        logMessage("Failed to send photo: " . $responseData['description']);
        $response = sendMessage($chatId, $caption, $replyMarkup, $keyboardMarkup);
        logMessage("Fallback sendMessage response: " . $response);
    }
}

// Get incoming update
$update = json_decode(file_get_contents("php://input"), true);
if (!$update) {
    logMessage("No update received");
    echo json_encode(['ok' => false]);
    exit;
}

// Process chat_member updates
if (isset($update['my_chat_member'])) {
    $chatId = $update['my_chat_member']['chat']['id'];
    $newStatus = $update['my_chat_member']['new_chat_member']['status'];
    
    if ($newStatus === 'member') {
        logMessage("Bot added to chat ID: $chatId");
        sendWelcomeMessage($chatId);
    }
}

// Process message updates
if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $messageText = $update['message']['text'] ?? '';
    
    logMessage("Received message: $messageText from chat ID: $chatId");
    
    // Store the incoming message
    $messages = json_decode(file_get_contents('messages.json'), true) ?: [];
    $messages[] = [
        'id' => count($messages) + 1,
        'chat_id' => "web_user_123", // Map to HTML chat ID
        'text' => $messageText,
        'is_bot' => true,
        'timestamp' => time()
    ];
    file_put_contents('messages.json', json_encode($messages));
    
    // Process the message
    if ($messageText === '/start') {
        sendWelcomeMessage($chatId);
    } elseif ($messageText === 'ទិញទំនិញឥឡូវនេះ') {
        $replyMarkup = [
            'inline_keyboard' => [
                [['text' => '🛒 បើកហាងទំនិញ', 'web_app' => ['url' => $miniAppUrl]]]
            ]
        ];
        sendMessage($chatId, "សូមចុចប៊ូតុងខាងក្រោមដើម្បីទិញទំនិញឥឡូវនេះ! 🛒", $replyMarkup);
    } elseif ($messageText === 'ទំនាក់ទំនងផ្ទាល់') {
        $replyMarkup = [
            'inline_keyboard' => [
                [['text' => '📞 ទំនាក់ទំនងឥឡូវនេះ', 'url' => $contactUrl]]
            ]
        ];
        sendMessage($chatId, "សូមចុចប៊ូតុងខាងក្រោមដើម្បីទំនាក់ទំនងផ្ទាល់! 📲", $replyMarkup);
    } else {
        $text = "សួស្តី! 😊 សូមចុច /start ដើម្បីចាប់ផ្តើមប្រើ Bot របស់យើង!";
        sendMessage($chatId, $text);
        
        // Store bot's response
        $messages = json_decode(file_get_contents('messages.json'), true) ?: [];
        $messages[] = [
            'id' => count($messages) + 1,
            'chat_id' => "web_user_123",
            'text' => $text,
            'is_bot' => true,
            'timestamp' => time()
        ];
        file_put_contents('messages.json', json_encode($messages));
    }
} else {
    logMessage("Invalid update format: " . json_encode($update));
}

echo json_encode(['ok' => true]);
?>