<?php
/**
 * Upload a video file to a Facebook Page.
 * Usage: php fb_upload_video.php /full/path/to/video.mp4 "Optional video description"
 */

$config = require __DIR__ . '/fb_config.php';

if ($argc < 2) {
    echo "Usage: php fb_upload_video.php /full/path/to/video.mp4 \"Optional description\"\n";
    exit(1);
}

$videoPath = $argv[1];
$description = $argv[2] ?? '';

if (!file_exists($videoPath)) {
    echo "File not found: $videoPath\n";
    exit(1);
}

$pageId = $config['page_id'];
$pageAccessToken = $config['page_access_token'];

$url = "https://graph.facebook.com/{$pageId}/videos";

$postFields = [
    'description' => $description,
    'access_token' => $pageAccessToken,
    'source' => new CURLFile($videoPath),
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 0);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo "cURL error: $err\n";
    exit(1);
}

echo "Response:\n$response\n";
