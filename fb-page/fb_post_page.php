<?php
/**
 * Simple script to post a message to a Facebook Page using a Page Access Token.
 * Usage: php fb_post_page.php "Your post message here"
 */

$config = require __DIR__ . '/fb_config.php';

if ($argc < 2) {
    echo "Usage: php fb_post_page.php \"Your message here\"\n";
    exit(1);
}

$message = $argv[1];

$pageId = $config['page_id'];
$pageAccessToken = $config['page_access_token'];

$url = "https://graph.facebook.com/{$pageId}/feed";

$postFields = [
    'message' => $message,
    'access_token' => $pageAccessToken,
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo "cURL error: $err\n";
    exit(1);
}

echo "Response:\n$response\n";
