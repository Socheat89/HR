<?php
header('Content-Type: application/json');

// Telegram Bot Configuration
$botToken = "7845947735:AAHJ_PoXTysXnhj8378N9X0e-C920a1REVo";
$miniAppUrl = "https://app.vvc.asia/mini_app/index.html";
$contactUrl = "https://t.me/SK_Smart093";
$imageUrl = "https://images.unsplash.com/photo-1516321318423-f06f85e504b3";
$apiUrl = "https://api.telegram.org/bot$botToken/";

// Hardcoded chat ID for testing (replace with actual chat ID in production)
$telegramChatId = "123456789"; // Replace with your Telegram chat ID

// Log function
function logMessage($message) {
    file_put_contents('bot_log.txt', date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

// Function to send message to Telegram
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
        return false;
    }
    
    $responseData = json_decode($response, true);
    if (!$responseData['ok']) {
        logMessage("Telegram API Error: " . $responseData['description']);
        return false;
    }
    
    curl_close($ch);
    return $responseData;
}

// Function to send welcome message
function sendWelcomeMessage($chatId) {
    global $miniAppUrl, $contactUrl;
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
            [['text' => 'ទិញទំនិញឥឡូវនេះ'], ['text' => 'ទំនាក់ទំនងផ្ទាល់'], ['text' => 'ចែករំលែកទីតាំង']],
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];
    
    return sendMessage($chatId, $caption, $replyMarkup, $keyboardMarkup);
}

// Function to request location
function requestLocation($chatId) {
    $keyboardMarkup = [
        'keyboard' => [
            [['text' => 'ចែករំលែកទីតាំង', 'request_location' => true]],
            [['text' => 'ត្រលប់ក្រោយ']]
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true
    ];
    
    return sendMessage($chatId, "សូមចែករំលែកទីតាំងរបស់អ្នកសម្រាប់ការដឹកជញ្ជូន 📍", null, $keyboardMarkup);
}

// Function to handle order placement
function handleOrder($chatId, $orderData) {
    // Validate order data
    $orderDetails = json_decode($orderData, true);
    if (json_last_error() !== JSON_ERROR_NONE || empty($orderDetails['products'])) {
        sendMessage($chatId, "⚠️ ទិន្នន័យការបញ្ជាទិញមិនត្រឹមត្រូវ!");
        logMessage("Invalid order data for chat $chatId");
        return false;
    }
    
    // Generate order ID
    $orderId = uniqid('order_');
    
    // Ensure orders.json exists
    if (!file_exists('orders.json')) {
        file_put_contents('orders.json', json_encode([]));
    }
    
    // Store order
    $orders = json_decode(file_get_contents('orders.json'), true) ?: [];
    $orders[] = [
        'order_id' => $orderId,
        'chat_id' => $chatId,
        'products' => $orderDetails['products'],
        'total' => $orderDetails['total'] ?? 0,
        'status' => 'pending',
        'timestamp' => time(),
        'last_reminded' => 0 // Track when last reminder was sent
    ];
    file_put_contents('orders.json', json_encode($orders));
    
    // Send confirmation to user
    $message = "✅ <b>ការបញ្ជាទិញរបស់អ្នកត្រូវបានដាក់!</b>\n";
    $message .= "លេខកម្ម៉ង់: $orderId\n";
    $message .= "ស្ថានភាព: កំពុងដំណើរការ\n";
    $message .= "សូមរង់ចាំការជូនដំណឹងបន្ទាប់! 🚚";
    
    $replyMarkup = [
        'inline_keyboard' => [
            [['text' => 'តាមដានការបញ្ជាទិញ', 'callback_data' => "track_$orderId"]]
        ]
    ];
    
    sendMessage($chatId, $message, $replyMarkup);
    logMessage("Order placed: $orderId for chat $chatId");
    
    return $orderId;
}

// Function to handle order tracking
function handleOrderTracking($chatId, $orderId) {
    if (!file_exists('orders.json')) {
        return sendMessage($chatId, "⚠️ មិនមានទិន្នន័យការបញ្ជាទិញ!");
    }
    
    $orders = json_decode(file_get_contents('orders.json'), true) ?: [];
    $order = array_filter($orders, function($o) use ($orderId) {
        return $o['order_id'] === $orderId;
    });
    
    $order = array_shift($order);
    if (!$order) {
        return sendMessage($chatId, "⚠️ រកមិនឃើញការបញ្ជាទិញ $orderId!");
    }
    
    $status = $order['status'];
    $trackingMessage = "📦 <b>ស្ថានភាពការបញ្ជាទិញ</b>\n";
    $trackingMessage .= "លេខកម្ម៉ង់: $orderId\n";
    $trackingMessage .= "ស្ថានភាព: " . ($status === 'pending' ? 'កំពុងដំណើរការ' : 'កំពុងដឹកជញ្ជូន') . "\n";
    $trackingMessage .= "ទីតាំង: សូមចែករំលែកទីតាំងដើម្បីបញ្ជាក់អាសយដ្ឋានដឹកជញ្ជូន 📍";
    
    return sendMessage($chatId, $trackingMessage);
}

// Handle incoming request
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['ok' => false, 'error' => 'Invalid input']);
    exit;
}

// Check for message or callback query
$chatId = $input['message']['chat']['id'] ?? $input['callback_query']['from']['id'] ?? null;
$message = trim($input['message']['text'] ?? '');
$location = $input['message']['location'] ?? null;
$callbackData = $input['callback_query']['data'] ?? null;

if (!$chatId) {
    echo json_encode(['ok' => false, 'error' => 'Missing chat ID']);
    exit;
}

// Process the request
$responseText = '';
if ($message === '/start') {
    sendWelcomeMessage($chatId);
    $responseText = "🌟 Welcome! Check out our shop by clicking the buttons in Telegram!";
} elseif ($message === 'ទិញទំនិញឥឡូវនេះ') {
    $replyMarkup = [
        'inline_keyboard' => [
            [['text' => '🛒 បើកហាងទំនិញ', 'web_app' => ['url' => $miniAppUrl]]]
        ]
    ];
    sendMessage($chatId, "សូមចុចប៊ូតុងខាងក្រោមដើម្បីទិញទំនិញឥឡូវនេះ! 🛒", $replyMarkup);
    $responseText = "Click the button in Telegram to visit our shop!";
} elseif ($message === 'ទំនាក់ទំនងផ្ទាល់') {
    $replyMarkup = [
        'inline_keyboard' => [
            [['text' => '📞 ទំនាក់ទំនងឥឡូវនេះ', 'url' => $contactUrl]]
        ]
    ];
    sendMessage($chatId, "សូមចុចប៊ូតុងខាងក្រោមដើម្បីទំនាក់ទំនងផ្ទាល់! 📲", $replyMarkup);
    $responseText = "Click the button in Telegram to contact us!";
} elseif ($message === 'ចែករំលែកទីតាំង') {
    requestLocation($chatId);
    $responseText = "Please share your location for delivery!";
} elseif ($location) {
    $latitude = $location['latitude'];
    $longitude = $location['longitude'];
    $message = "📍 <b>ទីតាំងរបស់អ្នកត្រូវបានទទួល!</b>\n";
    $message .= "Latitude: $latitude\nLongitude: $longitude\n";
    $message .= "យើងនឹងប្រើទីតាំងនេះសម្រាប់ការដឹកជញ្ជូន។ សូមអរគុណ! 🙏";
    
    sendMessage($chatId, $message);
    logMessage("Location received from chat $chatId: Lat $latitude, Long $longitude");
    $responseText = "Location received!";
} elseif ($callbackData && strpos($callbackData, 'track_') === 0) {
    $orderId = str_replace('track_', '', $callbackData);
    handleOrderTracking($chatId, $orderId);
    $responseText = "Tracking information sent!";
} elseif (isset($input['message']['web_app_data']['data'])) {
    $orderData = $input['message']['web_app_data']['data'];
    $orderId = handleOrder($chatId, $orderData);
    $responseText = $orderId ? "Order $orderId placed successfully!" : "Failed to place order!";
} else {
    sendMessage($chatId, "សូមជ្រើសរើសជម្រើសពីប៊ូតុងខាងក្រោម ឬបញ្ចូលពាក្យបញ្ជាត្រឹមត្រូវ!");
    $responseText = "Invalid command or message!";
}

// Store the message for polling
if (!file_exists('messages.json')) {
    file_put_contents('messages.json', json_encode([]));
}
$messages = json_decode(file_get_contents('messages.json'), true) ?: [];
$messages[] = [
    'id' => count($messages) + 1,
    'chat_id' => $chatId,
    'text' => $message,
    'is_bot' => false,
    'timestamp' => time()
];
file_put_contents('messages.json', json_encode($messages));

// Return response
echo json_encode(['ok' => true, 'message' => $responseText]);
?>