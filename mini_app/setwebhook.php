<?php

// បញ្ចូល library
require_once 'telegram-bot-sdk/src/Api.php';

use Telegram\Bot\Api;

// បង្កើត instance របស់ Telegram bot
$telegram = new Api('7845947735:AAHJ_PoXTysXnhj8378N9X0e-C920a1REVo');

// Webhook URL (ជំនួសដោយ URL ពិតប្រាកដរបស់អ្នក)
$webhookUrl = 'https://app.vvc.asia/mini_app/bot.php';

// កំណត់ Webhook
try {
    $response = $telegram->setWebhook(['url' => $webhookUrl]);
    echo "Webhook set successfully: " . print_r($response, true);
} catch (Exception $e) {
    echo "Error setting webhook: " . $e->getMessage();
}