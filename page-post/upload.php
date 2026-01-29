<?php
// Set headers for JSON response
header('Content-Type: application/json');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

// --- CONFIGURATION ---
$CONFIG = [
    'page_id'           => '746849448504305',
    'page_access_token' => 'EAAb4Bh6lZCg4BPG1Hi0enhlO1XHk7fXp893sYt5dIes1sNBuLCjj68WBpE0JRUmWcu7ieViDpl2SSHajMoEwfZBAVicgupkNKuNRDzec5HqwCDABUiR7oMytdZAYEiLrDvjSLuPyRvh5VVWgJZBtCZBAhhfWfsmt4oPNd8UFySb3sksyOmMnHji5lRZBBdhxZCFsfTUZCsipeO1JzPRWb02GOvAeilQ2dAwBOPYUFS0ZD'
];

/**
 * Helper function to send a standardized JSON response and exit.
 * @param string $status 'success' or 'error'
 * @param string $message A descriptive message.
 * @param array $data Additional data to send.
 */
function send_json_response($status, $message, $data = []) {
    http_response_code($status === 'success' ? 200 : 400); // Set appropriate HTTP status code
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

// --- MAIN SCRIPT LOGIC ---

// 1. Check if it's a POST request
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    send_json_response('error', 'Invalid request method. Only POST is accepted.');
}

// 2. Validate Page ID and Access Token configuration
if (empty($CONFIG['page_id']) || empty($CONFIG['page_access_token']) || $CONFIG['page_id'] === 'YOUR_PAGE_ID') {
    send_json_response('error', 'Server configuration error: Page ID or Access Token is not set.');
}

// 3. Validate incoming data
$quote = isset($_POST['quote']) ? trim($_POST['quote']) : '';
if (empty($quote)) {
    send_json_response('error', 'Caption is missing from the request.');
}

if (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
    // Provide more specific error messages if possible
    $upload_errors = [
        UPLOAD_ERR_INI_SIZE   => "The uploaded file exceeds the upload_max_filesize directive in php.ini.",
        UPLOAD_ERR_FORM_SIZE  => "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.",
        UPLOAD_ERR_PARTIAL    => "The uploaded file was only partially uploaded.",
        UPLOAD_ERR_NO_FILE    => "No file was uploaded.",
        UPLOAD_ERR_NO_TMP_DIR => "Missing a temporary folder.",
        UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk.",
        UPLOAD_ERR_EXTENSION  => "A PHP extension stopped the file upload.",
    ];
    $error_code = $_FILES['video_file']['error'];
    $error_message = $upload_errors[$error_code] ?? "Unknown upload error.";
    send_json_response('error', 'Video file error: ' . $error_message);
}

$video_tmp_path = $_FILES['video_file']['tmp_name'];
$video_name = $_FILES['video_file']['name'];

// 4. Post the video to Facebook Graph API
try {
    $client = new Client(['base_uri' => 'https://graph-video.facebook.com/v19.0/']);
    
    $multipart_data = [
        ['name' => 'access_token', 'contents' => $CONFIG['page_access_token']],
        ['name' => 'description', 'contents' => $quote],
        ['name' => 'source', 'contents' => fopen($video_tmp_path, 'r'), 'filename' => $video_name]
    ];

    $response = $client->request('POST', $CONFIG['page_id'] . '/videos', [
        'multipart' => $multipart_data,
        'timeout' => 3600, // 1 hour timeout for large video uploads
    ]);

    $body = $response->getBody()->getContents();
    $data = json_decode($body, true);

    if (isset($data['id'])) {
        send_json_response('success', 'Video posted successfully!', ['post_id' => $data['id']]);
    } else {
        send_json_response('error', 'Facebook API did not return a Post ID.', ['response' => $body]);
    }

} catch (RequestException $e) {
    $response_body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'No response from Facebook.';
    $error_data = json_decode($response_body, true);
    $error_message = $error_data['error']['message'] ?? 'An unknown Guzzle/Facebook API error occurred.';
    send_json_response('error', $error_message, ['details' => $response_body]);
} catch (Exception $e) {
    send_json_response('error', 'A general server-side error occurred: ' . $e->getMessage());
} finally {
    // Clean up the temporary file
    if (file_exists($video_tmp_path)) {
        unlink($video_tmp_path);
    }
}
?>