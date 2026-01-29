<?php
/**
 * =================================================================
 * SECTION 1: PHP BACKEND LOGIC
 * This part handles form submission and API communications.
 * =================================================================
 */

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- TELEGRAM CONFIGURATION ---
    define('BOT_TOKEN', '7680086124:AAHrvdz-mOx3pO1Ijqvh7BHTeGh2JB5JuwQ'); 
    define('CHAT_ID', '-1002802610249'); 
    
    // --- FACEBOOK & INSTAGRAM CONFIGURATION ---
    define('FB_PAGE_ID', '746849448504305'); 
    define('IG_USER_ID', '17841476105797400'); 
    define('FB_ACCESS_TOKEN', 'EAAb4Bh6lZCg4BPA7QDOkSYkclgISIxeZCZC14B53tn7Q9xrSlHAZBxFQNc9nnXqSjSnrCntHAOZCxPd3cjLlIWmU32mC52eOqFjoarCQDVLYHz40Vg29UQCKnJjp1rrjMY7sPt2xOKbn8dTM5tlgZAdgfd2eAwqs4UVZCl9yZChgLC4fCd1UEBoV2Nu6UrMwbh6gtc5Df5GZAywZDZD');

    // --- END CONFIGURATION ---

    // Set header to return JSON
    header('Content-Type: application/json');

    // === HELPER FUNCTIONS ===

    /**
     * Sends a final JSON response and stops the script.
     * @param array $results An array of results from each platform.
     */
    function sendFinalResponse($results) {
        $final_messages = [];
        $has_error = false;
        $all_success = true;

        foreach ($results as $platform => $result) {
            $final_messages[] = ucfirst($platform) . ': ' . $result['message'];
            if ($result['status'] === 'error') {
                $has_error = true;
                $all_success = false;
            }
        }
        
        $status = $all_success ? 'success' : ($has_error ? 'partial_error' : 'success');

        echo json_encode(['status' => $status, 'messages' => $final_messages]);
        exit;
    }
    
    /**
     * cURL Executor Function - More Robust Version
     * @param string $url The target URL.
     * @param mixed $payload The data to send (can be an array for file uploads, or a query string).
     * @param bool $is_file_upload Set to true if the payload contains CURLFile objects.
     * @return array The result of the cURL execution.
     */
    function executeCurl($url, $payload, $is_file_upload = false) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['status' => 'error', 'message' => "cURL Error: $error"];
        }
        
        $result = json_decode($response, true);

        if ($http_code >= 400) {
            $error_message = 'Unknown API Error';
            if (isset($result['error']['message'])) {
                $error_message = $result['error']['message'];
            } elseif (is_string($response) && strlen($response) < 500) {
                $error_message = strip_tags($response);
            }
            return ['status' => 'error', 'message' => "API Error (HTTP $http_code): $error_message"];
        }

        if (isset($result['error'])) {
            return ['status' => 'error', 'message' => $result['error']['message'] ?? 'Unknown API Error'];
        }
        
        return ['status' => 'success', 'data' => $result];
    }
    

    // === PLATFORM POSTING FUNCTIONS ===

    /**
     * Post to Telegram
     */
    function postToTelegram($caption, $files, $reply_markup) {
        if (BOT_TOKEN === 'YOUR_BOT_TOKEN' || CHAT_ID === 'YOUR_CHAT_ID') {
            return ['status' => 'error', 'message' => 'សូមកែប្រែ BOT_TOKEN និង CHAT_ID ជាមុនសិន។'];
        }

        $payload = ['chat_id' => CHAT_ID];
        if (count($files['name']) === 1) { // Single photo
            $endpoint = 'sendPhoto';
            $payload['photo'] = new CURLFile($files['tmp_name'][0], $files['type'][0], $files['name'][0]);
            $payload['caption'] = $caption;
            $payload['parse_mode'] = 'HTML';
            if ($reply_markup) $payload['reply_markup'] = $reply_markup;
        } else { // Multiple photos
            $endpoint = 'sendMediaGroup';
            $media = [];
            foreach ($files['tmp_name'] as $i => $tmp_name) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $file_name = 'file' . $i;
                    $payload[$file_name] = new CURLFile($tmp_name, $files['type'][$i], $files['name'][$i]);
                    $media_item = ['type' => 'photo', 'media' => 'attach://' . $file_name];
                    if ($i === 0) {
                        $media_item['caption'] = $caption;
                        $media_item['parse_mode'] = 'HTML';
                    }
                    $media[] = $media_item;
                }
            }
            $payload['media'] = json_encode($media);
        }

        $result = executeCurl('https://api.telegram.org/bot' . BOT_TOKEN . '/' . $endpoint, $payload, true);

        if (isset($result['data']['ok']) && $result['data']['ok']) {
            if ($endpoint === 'sendMediaGroup' && $reply_markup) {
                $btn_payload = ['chat_id' => CHAT_ID, 'text' => '​', 'reply_markup' => $reply_markup];
                executeCurl('https://api.telegram.org/bot' . BOT_TOKEN . '/sendMessage', http_build_query($btn_payload));
            }
            return ['status' => 'success', 'message' => 'បង្ហោះបានជោគជ័យ។'];
        } else {
            return ['status' => 'error', 'message' => $result['data']['description'] ?? 'Unknown Telegram API error.'];
        }
    }

    /**
     * Post to Facebook Page
     */
    function postToFacebookPage($caption, $files) {
         if (FB_PAGE_ID === 'YOUR_FACEBOOK_PAGE_ID' || FB_ACCESS_TOKEN === 'YOUR_FACEBOOK_LONG_LIVED_ACCESS_TOKEN') {
            return ['status' => 'error', 'message' => 'សូមបំពេញ FB_PAGE_ID និង FB_ACCESS_TOKEN។'];
        }
        $graph_url = 'https://graph.facebook.com/v19.0/';

        if (count($files['name']) === 1) { // Single photo post
            $payload = [
                'caption' => $caption,
                'access_token' => FB_ACCESS_TOKEN,
                'source' => new CURLFile($files['tmp_name'][0], $files['type'][0], $files['name'][0])
            ];
            $result = executeCurl($graph_url . FB_PAGE_ID . '/photos', $payload, true);
            return $result['status'] === 'success' ? ['status' => 'success', 'message' => 'បង្ហោះរូបភាព ១ បានជោគជ័យ។'] : $result;
        } else { // Multi-photo post
            $photo_ids = [];
            foreach ($files['tmp_name'] as $key => $tmp_name) {
                $payload = [
                    'published' => 'false',
                    'access_token' => FB_ACCESS_TOKEN,
                    'source' => new CURLFile($tmp_name, $files['type'][$key], $files['name'][$key])
                ];
                $upload_result = executeCurl($graph_url . FB_PAGE_ID . '/photos', $payload, true);
                if ($upload_result['status'] === 'success') {
                    $photo_ids[] = ['media_fbid' => $upload_result['data']['id']];
                } else {
                    return ['status' => 'error', 'message' => 'បញ្ហាក្នុងការ Upload រូបភាពទី ' . ($key+1) . ': ' . $upload_result['message']];
                }
            }

            if (empty($photo_ids)) return ['status' => 'error', 'message' => 'គ្មានរូបភាពណាមួយត្រូវបាន Upload ទេ។'];
            
            $feed_payload = [
                'message' => $caption,
                'attached_media' => json_encode($photo_ids),
                'access_token' => FB_ACCESS_TOKEN
            ];
            $feed_result = executeCurl($graph_url . FB_PAGE_ID . '/feed', http_build_query($feed_payload)); 
            return $feed_result['status'] === 'success' ? ['status' => 'success', 'message' => 'បង្ហោះ Album បានជោគជ័យ។'] : $feed_result;
        }
    }
    
function postToInstagram($caption, $files) {
    if (IG_USER_ID === 'YOUR_INSTAGRAM_BUSINESS_ACCOUNT_ID' || FB_ACCESS_TOKEN === 'YOUR_FACEBOOK_LONG_LIVED_ACCESS_TOKEN') {
        return ['status' => 'error', 'message' => 'សូមបំពេញ IG_USER_ID និង FB_ACCESS_TOKEN។'];
    }

    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
        return ['status' => 'error', 'message' => 'Instagram ទាមទារ HTTPS។ Server របស់អ្នកមិនបានបើកទេ។'];
    }
    
    if (!extension_loaded('gd')) {
        return ['status' => 'error', 'message' => 'PHP GD library is not enabled. It is required for image resizing to post on Instagram.'];
    }

    $temp_dir = 'ig_temp_images/';
    if (!is_dir($temp_dir) && !mkdir($temp_dir, 0755, true)) {
        return ['status' => 'error', 'message' => 'មិនអាចបង្កើត temporary directory បានទេ។ សូមពិនិត្យមើល permissions។'];
    }
    
    if (!is_writable($temp_dir)) {
        return ['status' => 'error', 'message' => 'Cannot write to temporary directory: ' . $temp_dir];
    }

    $base_url = "https://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
    $image_urls = [];
    
    foreach ($files['tmp_name'] as $key => $tmp_name) {
        if ($files['error'][$key] === UPLOAD_ERR_OK) {
            $file_type = $files['type'][$key];
            if (!in_array($file_type, ['image/jpeg', 'image/png'])) {
                return ['status' => 'error', 'message' => 'Unsupported image format for file ' . $files['name'][$key] . '. Only JPEG and PNG are allowed.'];
            }

            $new_filename = uniqid('img_ig_resized_', true) . '.jpg';
            $new_filepath = $temp_dir . $new_filename;

            $source_image = null;
            if ($file_type === 'image/jpeg') {
                $source_image = @imagecreatefromjpeg($tmp_name);
            } elseif ($file_type === 'image/png') {
                $source_image = @imagecreatefrompng($tmp_name);
            }

            if (!$source_image) {
                return ['status' => 'error', 'message' => 'Failed to process image: ' . $files['name'][$key]];
            }

            // IG Resizing Logic
            $original_width = imagesx($source_image);
            $original_height = imagesy($source_image);
            $original_ratio = $original_width / $original_height;

            if ($original_ratio > 1.91) { // Landscape
                $target_width = 1080; $target_height = 608;
            } elseif ($original_ratio < 0.8) { // Portrait
                $target_width = 1080; $target_height = 1350;
            } else { // Square
                $target_width = 1080; $target_height = 1080;
            }
            $target_ratio = $target_width / $target_height;

            $src_x = 0; $src_y = 0;
            $src_w = $original_width; $src_h = $original_height;

            if ($original_ratio > $target_ratio) {
                $src_w = $original_height * $target_ratio;
                $src_x = ($original_width - $src_w) / 2;
            } else {
                $src_h = $original_width / $target_ratio;
                $src_y = ($original_height - $src_h) / 2;
            }

            $resized_image = imagecreatetruecolor($target_width, $target_height);
            $white_bg = imagecolorallocate($resized_image, 255, 255, 255);
            imagefill($resized_image, 0, 0, $white_bg);
            imagecopyresampled($resized_image, $source_image, 0, 0, $src_x, $src_y, $target_width, $target_height, $src_w, $src_h);

            if (!imagejpeg($resized_image, $new_filepath, 90)) {
                imagedestroy($source_image);
                imagedestroy($resized_image);
                return ['status' => 'error', 'message' => 'Failed to save resized image: ' . $new_filename];
            }

            $image_url = $base_url . $new_filepath;
            error_log('Generated Image URL: ' . $image_url);
            error_log('File exists: ' . (file_exists($new_filepath) ? 'Yes' : 'No'));

            // Verify image accessibility
            $ch = curl_init($image_url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            error_log('Image URL HTTP Status: ' . $http_code);
            curl_close($ch);
            if ($http_code !== 200) {
                return ['status' => 'error', 'message' => 'Image URL is not accessible: ' . $image_url];
            }
            
            $image_urls[] = $image_url;
            imagedestroy($source_image);
            imagedestroy($resized_image);
        }
    }
    
    if (empty($image_urls)) {
        return ['status' => 'error', 'message' => 'មិនអាចបង្កើត Public URL សម្រាប់រូបភាពណាមួយបានទេ។'];
    }

    $graph_url = 'https://graph.facebook.com/v19.0/';
    $is_carousel = count($image_urls) > 1;
    $final_container_id = null;

    try {
        if ($is_carousel) {
            $child_container_ids = [];
            foreach ($image_urls as $img_url) {
                $payload_array = ['access_token' => FB_ACCESS_TOKEN, 'image_url' => $img_url, 'is_carousel_item' => 'true'];
                error_log('Carousel Child Payload: ' . print_r($payload_array, true));
                $result = executeCurl($graph_url . IG_USER_ID . '/media', http_build_query($payload_array));
                if ($result['status'] === 'success') {
                    $child_container_ids[] = $result['data']['id'];
                } else {
                    throw new Exception('បញ្ហាក្នុងការបង្កើត IG Child Container: ' . $result['message']);
                }
            }
            $carousel_payload_array = ['access_token' => FB_ACCESS_TOKEN, 'caption' => $caption, 'media_type' => 'CAROUSEL', 'children' => implode(',', $child_container_ids)];
            error_log('Carousel Main Payload: ' . print_r($carousel_payload_array, true));
            $creation_result = executeCurl($graph_url . IG_USER_ID . '/media', http_build_query($carousel_payload_array));
            if ($creation_result['status'] !== 'success') {
                throw new Exception('បញ្ហាក្នុងការបង្កើត IG Carousel Container หลัก: ' . $creation_result['message']);
            }
            $final_container_id = $creation_result['data']['id'];
        } else {
            $payload_array = ['access_token' => FB_ACCESS_TOKEN, 'image_url' => $image_urls[0], 'caption' => $caption];
            error_log('Single Photo Payload: ' . print_r($payload_array, true));
            $result = executeCurl($graph_url . IG_USER_ID . '/media', http_build_query($payload_array));
            if ($result['status'] !== 'success') {
                throw new Exception('បញ្ហាក្នុងការបង្កើត IG Single Photo Container: ' . $result['message']);
            }
            $final_container_id = $result['data']['id'];
        }

        $publish_payload_array = ['access_token' => FB_ACCESS_TOKEN, 'creation_id' => $final_container_id];
        error_log('Publish Payload: ' . print_r($publish_payload_array, true));
        $publish_result = executeCurl($graph_url . IG_USER_ID . '/media_publish', http_build_query($publish_payload_array));
        
        if ($publish_result['status'] !== 'success') {
            if (strpos($publish_result['message'], 'The media is not ready for publishing') !== false) {
                sleep(5);
                $retry_result = executeCurl($graph_url . IG_USER_ID . '/media_publish', http_build_query($publish_payload_array));
                if ($retry_result['status'] === 'success') {
                    return ['status' => 'success', 'message' => 'បង្ហោះបានជោគជ័យ (after retry)។'];
                }
                throw new Exception('បញ្ហាក្នុងការ Publish (after retry): ' . $retry_result['message']);
            }
            throw new Exception('បញ្ហាក្នុងការ Publish ទៅ IG: ' . $publish_result['message']);
        }
        return ['status' => 'success', 'message' => 'បង្ហោះបានជោគជ័យ។'];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    } finally {
        foreach (glob($temp_dir . '*') as $file) { @unlink($file); }
        if (is_dir($temp_dir)) { @rmdir($temp_dir); }
    }
}


    // === MAIN SCRIPT LOGIC ===

    $platforms = isset($_POST['platforms']) ? $_POST['platforms'] : [];
    $caption = isset($_POST['caption']) ? $_POST['caption'] : '';
    $files = $_FILES['images'];
    $results = [];

    if (empty($platforms)) {
        echo json_encode(['status' => 'error', 'message' => 'សូមជ្រើសរើសយ៉ាងហោចណាស់មួយ Platform។']); exit;
    }
    if (empty($files['name'][0])) {
        echo json_encode(['status' => 'error', 'message' => 'សូមជ្រើសរើសរូបភាពយ៉ាងហោចណាស់មួយ។']); exit;
    }
    if (count($files['name']) > 10) {
        echo json_encode(['status' => 'error', 'message' => 'អ្នកអាចបង្ហោះរូបភាពបានត្រឹមតែ 10 ប៉ុណ្ណោះក្នុងម្តង។']); exit;
    }

    $reply_markup = null;
    if (in_array('telegram', $platforms)) {
        $inline_keyboard = []; $button_row = [];
        if (isset($_POST['button_labels']) && isset($_POST['button_urls'])) {
            foreach ($_POST['button_labels'] as $i => $label) {
                $url = $_POST['button_urls'][$i] ?? '';
                if (!empty(trim($label)) && !empty(trim($url))) $button_row[] = ['text' => trim($label), 'url' => trim($url)];
            }
        }
        if (!empty($button_row)) $reply_markup = json_encode(['inline_keyboard' => [$button_row]]);
    }
    
    // Execute posting for selected platforms
    if (in_array('telegram', $platforms)) {
        $results['telegram'] = postToTelegram($caption, $files, $reply_markup);
    }
    if (in_array('facebook', $platforms)) {
        $results['facebook'] = postToFacebookPage($caption, $files);
    }
    if (in_array('instagram', $platforms)) {
        $results['instagram'] = postToInstagram($caption, $files);
    }

    sendFinalResponse($results);
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All-in-One Social Media Poster</title>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <style>
        body {
            font-family: 'Kantumruy Pro', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #e0e7ff, #f0f2f5);
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            color: #2d3748;
        }
        .dashboard-container {
            background-color: #ffffff; padding: 30px; border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15); width: 100%; max-width: 700px;
        }
        h1 { text-align: center; color: #1a202c; margin-bottom: 30px; font-size: 28px; }
        .form-group { margin-bottom: 25px; }
        label { display: block; margin-bottom: 10px; font-weight: 600; color: #4a5568; }
        textarea, input[type="text"], input[type="url"] {
            width: 100%; padding: 14px; border: 1px solid #e2e8f0; border-radius: 10px;
            box-sizing: border-box; font-size: 15px; background-color: #f7fafc; transition: all 0.3s ease;
        }
        input:focus, textarea:focus {
            border-color: #3182ce; box-shadow: 0 0 0 4px rgba(49, 130, 206, 0.2);
            background-color: #ffffff; outline: none;
        }
        textarea { resize: vertical; min-height: 140px; }
        
        .platform-selection { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
        .platform-item { display: flex; align-items: center; gap: 8px; }
        .platform-item input[type="checkbox"] { width: 18px; height: 18px; accent-color: #2b6cb0; }
        .platform-item label { margin-bottom: 0; font-weight: 500; cursor: pointer; }

        #image-upload-area { padding: 20px; border: 2px dashed #cbd5e0; border-radius: 10px; background-color: #f8fafc; text-align: center; }
        .file-input-label { display: inline-block; padding: 12px 25px; background-color: #ebf8ff; color: #2b6cb0; border: 1px solid #bee3f8; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s ease; }
        .file-input-label:hover { background: #2b6cb0; color: white; }
        input[type="file"] { display: none; }
        #image-preview { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 15px; margin-top: 20px; min-height: 50px; }
        .image-container-item { position: relative; width: 100%; padding-top: 100%; border-radius: 8px; overflow: hidden; cursor: grab; border: 2px solid #e2e8f0; }
        .image-container-item img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
        .image-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); display: flex; justify-content: center; align-items: center; gap: 10px; opacity: 0; transition: opacity 0.3s ease; }
        .image-container-item:hover .image-overlay { opacity: 1; }
        .image-action-btn { background: rgba(255, 255, 255, 0.9); border: none; width: 36px; height: 36px; border-radius: 50%; color: #2d3748; font-size: 20px; cursor: pointer; display: flex; justify-content: center; align-items: center; transition: all 0.2s ease; }
        .delete-btn { color: #c53030; }
        .replace-btn { color: #2b6cb0; }
        p.help-text { font-size: 13px; color: #718096; margin-top: 10px; margin-bottom: 0; }
        .sortable-ghost { opacity: 0.4; border: 2px dashed #3182ce; }

        .button-row { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; }
        .button-row input[name^="button_labels"] { flex: 1; }
        .button-row input[name^="button_urls"] { flex: 2; }
        .remove-btn, .add-btn { padding: 10px 15px; font-size: 16px; border-radius: 8px; cursor: pointer; border: 1px solid transparent; transition: all 0.2s ease; flex-shrink: 0; }
        .remove-btn { background: #fff1f2; color: #c53030; border-color: #fed7d7; }
        .add-btn { background: #ebf8ff; color: #2b6cb0; border-color: #bee3f8; margin-top: 10px; width: auto; }

        #telegram-buttons-group.disabled { opacity: 0.5; pointer-events: none; }

        button[type="submit"] {
            width: 100%; padding: 16px; background: linear-gradient(45deg, #3182ce, #2b6cb0); color: white; border: none; border-radius: 10px;
            font-size: 18px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        button:disabled { background: #b0c4de; cursor: not-allowed; }
        #status { margin-top: 25px; padding: 14px; border-radius: 10px; text-align: left; font-weight: 500; display: none; line-height: 1.6; }
        .status-success { background-color: #e6fffa; color: #1c4532; border: 1px solid #b2f5ea; }
        .status-error { background-color: #fff1f2; color: #742a2a; border: 1px solid #feb2b2; }
        .status-partial_error { background-color: #feebc8; color: #7b341e; border: 1px solid #fbd38d; }
    </style>
</head>
<body>

<div class="dashboard-container">
    <h1>ផ្ទាំងបញ្ជាបង្ហោះ All-in-One</h1>
    <form id="uploadForm" action="" method="post" enctype="multipart/form-data">
        
        <div class="form-group">
            <label>1. ជ្រើសរើស Platform ដែលត្រូវបង្ហោះទៅ</label>
            <div class="platform-selection">
                <div class="platform-item">
                    <input type="checkbox" id="postTelegram" name="platforms[]" value="telegram" checked>
                    <label for="postTelegram">Telegram</label>
                </div>
                <div class="platform-item">
                    <input type="checkbox" id="postFacebook" name="platforms[]" value="facebook">
                    <label for="postFacebook">Facebook Page</label>
                </div>
                <div class="platform-item">
                    <input type="checkbox" id="postInstagram" name="platforms[]" value="instagram">
                    <label for="postInstagram">Instagram</label>
                </div>
            </div>
             <p class="help-text">ចំណាំ៖ ការបង្ហោះទៅ Instagram ទាមទារឱ្យ Script នេះอยู่บน Public Server (มี HTTPS)។</p>
        </div>

        <div class="form-group">
            <label>2. រូបភាព (អូសដើម្បីតម្រៀប, ដល់ទៅ 10 រូប)</label>
            <div id="image-upload-area">
                <label for="images-input" class="file-input-label">🖼️ ជ្រើសរើស ឬ ទម្លាក់រូបភាព</label>
                <input type="file" id="images-input" name="images[]" multiple accept="image/jpeg, image/png, image/gif">
                <div id="image-preview"></div>
            </div>
        </div>

        <div class="form-group">
            <label for="caption">3. Caption (សម្រាប់គ្រប់ Platform)</label>
            <textarea id="caption" name="caption" placeholder="សរសេរ Caption របស់អ្នកនៅទីនេះ... អាចប្រើ Tag <b>bold</b>, <i>italic</i>, <a>link</a> សម្រាប់តែ Telegram ប៉ុណ្ណោះ។"></textarea>
        </div>
        
        <div class="form-group" id="telegram-buttons-group">
            <label>4. ប៊ូតុង Inline (សម្រាប់តែ Telegram ប៉ុណ្ណោះ)</label>
            <div id="inline-buttons-container">
                <!-- Dynamic buttons will be added here -->
            </div>
            <button type="button" id="add-button-btn" class="add-btn">➕ បន្ថែមប៊ូតុង</button>
        </div>

        <button type="submit" id="submitBtn">🚀 បង្ហោះឥឡូវនេះ</button>
    </form>
    <div id="status"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('uploadForm');
    const imageInput = document.getElementById('images-input');
    const previewContainer = document.getElementById('image-preview');
    const submitBtn = document.getElementById('submitBtn');
    const statusDiv = document.getElementById('status');
    const telegramCheckbox = document.getElementById('postTelegram');
    const telegramButtonsGroup = document.getElementById('telegram-buttons-group');
    const buttonContainer = document.getElementById('inline-buttons-container');

    let fileStore = [];

    const sortable = new Sortable(previewContainer, {
        animation: 150,
        ghostClass: 'sortable-ghost',
        onEnd: (evt) => {
            const [movedItem] = fileStore.splice(evt.oldIndex, 1);
            fileStore.splice(evt.newIndex, 0, movedItem);
            renderPreviews();
        }
    });

    const handleFiles = (files) => {
        const newFiles = Array.from(files);
        const totalFiles = fileStore.length + newFiles.length;
        if (totalFiles > 10) {
            alert(`អ្នកអាចបង្ហោះរូបភាពបានត្រឹមតែ 10 ប៉ុណ្ណោះ។ អ្នកបានជ្រើសរើស ${totalFiles} រូប។`);
            return;
        }
        fileStore.push(...newFiles);
        renderPreviews();
    };
    
    const renderPreviews = () => {
        previewContainer.innerHTML = '';
        fileStore.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const item = document.createElement('div');
                item.className = 'image-container-item';
                item.dataset.index = index;
                item.innerHTML = `
                    <img src="${e.target.result}" title="${file.name}">
                    <div class="image-overlay">
                        <button type="button" class="image-action-btn replace-btn" title="ជំនួសរូបភាព">🔄</button>
                        <button type="button" class="image-action-btn delete-btn" title="លុបរូបភាព">🗑️</button>
                    </div>
                    <input type="file" class="replace-input" accept="image/jpeg, image/png, image/gif" style="display: none;">
                `;
                previewContainer.appendChild(item);
            };
            reader.readAsDataURL(file);
        });
    };

    imageInput.addEventListener('change', (e) => {
        if (e.target.files.length) handleFiles(e.target.files);
        e.target.value = '';
    });
    
    previewContainer.addEventListener('click', (e) => {
        const parentItem = e.target.closest('.image-container-item');
        if (!parentItem) return;
        const index = parseInt(parentItem.dataset.index, 10);

        if (e.target.closest('.delete-btn')) {
            fileStore.splice(index, 1);
            renderPreviews();
        } else if (e.target.closest('.replace-btn')) {
            const replaceInput = parentItem.querySelector('.replace-input');
            replaceInput.click();
            replaceInput.onchange = (evt) => {
                if (evt.target.files.length) {
                    fileStore[index] = evt.target.files[0];
                    renderPreviews();
                }
            };
        }
    });

    telegramCheckbox.addEventListener('change', () => {
        telegramButtonsGroup.classList.toggle('disabled', !telegramCheckbox.checked);
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        if (!document.querySelector('input[name="platforms[]"]:checked')) {
            alert('សូមជ្រើសរើសយ៉ាងហោចណាស់មួយ Platform។'); return;
        }
        if (fileStore.length === 0) {
            alert('សូមជ្រើសរើសរូបភាពយ៉ាងហោចណាស់មួយ។'); return;
        }

        const formData = new FormData(form);
        formData.delete('images[]'); // Clear default files
        fileStore.forEach(file => formData.append('images[]', file, file.name)); // Append sorted files

        submitBtn.disabled = true;
        submitBtn.innerHTML = '⏳ កំពុងដំណើរការ...';
        statusDiv.style.display = 'none';

        try {
            const response = await fetch('', { method: 'POST', body: formData });
            
            if (!response.ok) {
                 throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            
            statusDiv.style.display = 'block';
            statusDiv.className = `status-${result.status || 'error'}`;
            
            if (result.messages && Array.isArray(result.messages)) {
                statusDiv.innerHTML = result.messages.join('<br>');
            } else {
                 statusDiv.textContent = result.message || 'មានបញ្ហាអ្វីម្យ៉ាងកើតឡើង។';
            }

            if (result.status === 'success') {
                form.reset();
                fileStore = [];
                renderPreviews();
                resetDynamicButtons();
                telegramButtonsGroup.classList.remove('disabled');
            }
        } catch (error) {
            statusDiv.style.display = 'block';
            statusDiv.className = 'status-error';
            statusDiv.textContent = 'Error: កើតមានបញ្ហាបច្ចេកទេស។ សូមពិនិត្យមើល Console។ ' + error.message;
            console.error('Fetch Error:', error);
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '🚀 បង្ហោះឥឡូវនេះ';
        }
    });

    const createButtonRow = () => {
        const div = document.createElement('div');
        div.className = 'button-row';
        div.innerHTML = `
            <input type="text" name="button_labels[]" placeholder="ឈ្មោះប៊ូតុង">
            <input type="url" name="button_urls[]" placeholder="Link">
            <button type="button" class="remove-btn">🗑️</button>`;
        return div;
    };
    
    const resetDynamicButtons = () => {
        buttonContainer.innerHTML = '';
        buttonContainer.appendChild(createButtonRow());
    };

    document.getElementById('add-button-btn').addEventListener('click', () => {
        buttonContainer.appendChild(createButtonRow());
    });

    buttonContainer.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-btn') && buttonContainer.querySelectorAll('.button-row').length > 1) {
            e.target.closest('.button-row').remove();
        }
    });

    resetDynamicButtons();
});
</script>

</body>
</html>