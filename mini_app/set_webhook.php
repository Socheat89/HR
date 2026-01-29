
<?php
$botToken = "7845947735:AAHJ_PoXTysXnhj8378N9X0e-C920a1REVo"; // Replace with your bot token
$webhookUrl = "https://app.vvc.asia/mini_app/bot.php"; // Replace with your PHP script URL

$url = "https://api.telegram.org/bot$botToken/setWebhook?url=" . urlencode($webhookUrl);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

echo $response;
?>
