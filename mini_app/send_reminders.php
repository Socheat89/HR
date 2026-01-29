<?php
// Set timezone
date_default_timezone_set('Asia/Phnom_Penh');

// Telegram Bot Configuration
$botToken = "7845947735:AAHJ_PoXTysXnhj8378N9X0e-C920a1REVo";
$miniAppUrl = "https://app.vvc.asia/mini_app/index.html";
$apiUrl = "https://api.telegram.org/bot$botToken/";

// Log function
function logMessage($message) {
    file_put_contents('bot_log.txt', date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

// Function to send message to Telegram
function sendMessage($chatId, $text, $replyMarkup = null) {
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

// Function to send purchase reminders
function sendPurchaseReminders() {
    global $miniAppUrl;
    
    if (!file_exists('orders.json')) {
        logMessage("No orders found for reminders");
        return;
    }
    
    $orders = json_decode(file_get_contents('orders.json'), true) ?: [];
    $currentTime = time();
    $oneDay = 24 * 60 * 60; // Seconds in a day
    
    // Define multiple target hours for reminders (9:00 AM, 2:00 PM, 6:00 PM)
    $targetHours = [12, 13, 14];
    
    // Get current hour in server time
    $currentHour = (int) date('H', $currentTime);
    
    // Check if current hour is in target hours
    if (!in_array($currentHour, $targetHours)) {
        logMessage("Not a target hour for reminders (Current: $currentHour, Target: " . implode(', ', $targetHours) . ")");
        return;
    }
    
    $remindersSent = 0;
    foreach ($orders as &$order) {
        $orderTime = $order['timestamp'];
        $lastReminded = $order['last_reminded'] ?? 0;
        
        // Check if order is older than 24 hours and hasn't been reminded in the last 24 hours
        if (($currentTime - $orderTime) > $oneDay && ($currentTime - $lastReminded) > $oneDay) {
            $chatId = $order['chat_id'];
            $orderId = $order['order_id'];
            // Handle products safely; assume 'name' field or fallback
            $products = !empty($order['products']) ? implode(", ", array_map(function($p) {
                return $p['name'] ?? 'Unknown Product';
            }, $order['products'])) : "ផលិតផលផ្សេងៗ";
            
            $message = "🛒 <b>សូមកុំភ្លេចទិញម្តងទៀត!</b>\n";
            $message .= "អ្នកបានទិញ: $products\n";
            $message .= "លេខកម្ម៉ង់: $orderId\n";
            $message .= "សូមពិនិត្យមើលផលិតផលថ្មីៗរបស់យើង! 🚀";
            
            $replyMarkup = [
                'inline_keyboard' => [
                    [['text' => '🛍️ ទិញឥឡូវ', 'web_app' => ['url' => $miniAppUrl]]]
                ]
            ];
            
            $result = sendMessage($chatId, $message, $replyMarkup);
            if ($result) {
                $order['last_reminded'] = $currentTime;
                logMessage("Reminder sent for order $orderId to chat $chatId at hour $currentHour");
                $remindersSent++;
            } else {
                logMessage("Failed to send reminder for order $orderId to chat $chatId at hour $currentHour");
            }
        }
    }
    
    // Save updated orders
    file_put_contents('orders.json', json_encode($orders));
    logMessage("Processed $remindersSent reminders at hour $currentHour");
}

// Run the reminder function
sendPurchaseReminders();

echo "Reminders processed successfully!";
?>