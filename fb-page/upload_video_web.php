<?php
// Simple web endpoint to upload a video file to a Facebook Page.
// Accepts multipart POST with: page_id, description, video (file) OR video_url

// Load config
$cfgPath = __DIR__ . '/fb_config.php';
if (!file_exists($cfgPath)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => ['message' => 'Missing fb_config.php']]);
    exit;
}
$config = require $cfgPath;

$pageAccessToken = $_POST['page_access_token'] ?? $config['page_access_token'] ?? null;
if (!$pageAccessToken) {
    header('Content-Type: application/json');
    echo json_encode(['error' => ['message' => 'Missing page access token in configuration']]);
    exit;
}

$pageId = $_POST['page_id'] ?? $config['page_id'] ?? null;
$description = $_POST['description'] ?? '';

header('Content-Type: application/json');

// If a video URL is provided, we can create a post with the URL
if (!empty($_POST['video_url'])) {
    $videoUrl = $_POST['video_url'];
    $graphUrl = "https://graph.facebook.com/{$pageId}/feed";
    $postFields = http_build_query([
        'message' => $description,
        'link' => $videoUrl,
        'access_token' => $pageAccessToken,
    ]);

    $ch = curl_init($graphUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        echo json_encode(['error' => ['message' => $err]]);
    } else {
        echo $resp;
    }
    exit;
}

// Handle file upload
if (empty($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => ['message' => 'No uploaded file or upload error']]);
    exit;
}

$uploaded = $_FILES['video'];
$tmpPath = $uploaded['tmp_name'];
$filename = $uploaded['name'];

$graphUrl = "https://graph.facebook.com/{$pageId}/videos";

$postFields = [
    'description' => $description,
    'access_token' => $pageAccessToken,
    'source' => new CURLFile($tmpPath, $uploaded['type'], $filename),
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $graphUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 0);

$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo json_encode(['error' => ['message' => $err]]);
} else {
    echo $response;
}
