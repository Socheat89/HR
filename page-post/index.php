<?php

/**
 * FINAL ADVANCED VERSION v3 - Correct FFmpeg Path
 * This version corrects the path to the ffmpeg executable, pointing it
 * inside the ffmpeg directory as diagnosed by the debug script.
 */

// ... (All configuration remains the same) ...
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(300);

$pageId          = '746849448504305';
$pageAccessToken = 'EAAb4Bh6lZCg4BPDupF4EDITYWmZCKUUvjR7h25tmT6e4IGSZCemZAvDqiEwnoP02jTUeE7NuW0HsyNpCPZC8DBDcoMALx68kw7I8hKr2M2ZByKQOqDJk15GmQigwnQLqB0PSCrxpsODlYIXBwcRZAZAwtsfb3kv7Siv9elKHYLXPsN73ZBLKl9tnnfHu6aGUzsBmA47TMrQZDZD';
$graphApiVersion = 'v18.0';

define('ROOT_PATH', __DIR__);
define('AUTOLOAD_FILE', ROOT_PATH . '/vendor/autoload.php');
$statusMessages = [];
$tempFilesToClean = [];

try {
    if (!file_exists(AUTOLOAD_FILE)) {
        throw new Exception("FATAL: 'vendor/autoload.php' is missing. Please run 'composer install'.");
    }
    require_once AUTOLOAD_FILE;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // --- FFmpeg Path Definition and Check ---
        // CORRECTED PATH: Points to the executable *inside* the ffmpeg directory.
        $ffmpeg_path = __DIR__ . '/ffmpeg/ffmpeg';

        // Check if the specified file exists and is executable
        if (!is_executable($ffmpeg_path)) {
            throw new Exception("តម្រូវការ Server: រកមិនឃើញកម្មវិធី FFmpeg នៅត្រង់ទីតាំង '" . htmlspecialchars($ffmpeg_path) . "' ឬក៏វាគ្មាន Permission ឲ្យដំណើរការ (ត្រូវកំណត់ Permission 0755)។");
        }
        
        // ... (The rest of the script is the same) ...

        // Input Validation
        $requiredFields = ['main_message', 'video_title', 'video_link', 'image_link'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) throw new Exception("Please fill in all fields. Missing: " . htmlspecialchars($field));
        }
        if (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) throw new Exception("Video file upload failed or not selected.");
        if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) throw new Exception("Image file upload failed or not selected.");

        $client = new \GuzzleHttp\Client(['base_uri' => 'https://graph.facebook.com']);
        $originalVideoPath = $_FILES['video_file']['tmp_name'];
        $tempFilesToClean[] = $originalVideoPath;

        // Automatic Video Conversion
        $convertedVideoPath = sys_get_temp_dir() . '/' . uniqid('converted_', true) . '.mp4';
        $tempFilesToClean[] = $convertedVideoPath;

        $command = escapeshellcmd($ffmpeg_path) . " -y -i " . escapeshellarg($originalVideoPath) . " -vcodec libx264 -acodec aac -pix_fmt yuv420p " . escapeshellarg($convertedVideoPath);
        
        shell_exec($command . " 2>&1");

        if (!file_exists($convertedVideoPath) || filesize($convertedVideoPath) == 0) {
            throw new Exception("ការបំប្លែងវីដេអូដោយស្វ័យប្រវត្តិបានបរាជ័យ។ វីដេអូអាចនឹងខូច ឬមាន Format ដែលមិនស្គាល់។ សូមពិនិត្យមើល Permission របស់ Folder Temp នៅលើ Server។");
        }
        
        $videoToUploadPath = $convertedVideoPath;

        // POST 1: The Video Post
        try {
            $videoCallToAction = json_encode(['type' => 'SHOP_NOW', 'value' => ['link' => $_POST['video_link']]]);
            $videoResponse = $client->post("/{$graphApiVersion}/{$pageId}/videos", [
                'multipart' => [
                    ['name' => 'access_token', 'contents' => $pageAccessToken],
                    ['name' => 'source', 'contents' => fopen($videoToUploadPath, 'r')],
                    ['name' => 'description', 'contents' => $_POST['main_message']],
                    ['name' => 'title', 'contents' => $_POST['video_title']],
                    ['name' => 'call_to_action', 'contents' => $videoCallToAction],
                ]
            ]);
            $videoData = json_decode($videoResponse->getBody()->getContents(), true);
            if (isset($videoData['id'])) {
                $postUrl = 'https://www.facebook.com/' . ($videoData['post_id'] ?? $videoData['id']);
                $statusMessages[] = '<div class="status success">Post វីដេអូបានជោគជ័យ! <a href="' . $postUrl . '" target="_blank">មើលនៅលើ Facebook</a>.</div>';
            } else { throw new Exception("Video post creation failed. API Response: " . json_encode($videoData)); }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseBody = $e->getResponse()->getBody()->getContents();
            $errorData = json_decode($responseBody, true);
            $errorMessage = $errorData['error']['message'] ?? 'An unknown error occurred.';
            $statusMessages[] = '<div class="status error"><strong>ការ Post វីដេអូបរាជ័យ:</strong> ' . htmlspecialchars($errorMessage) . '</div>';
        }

        // POST 2: The Link Post
        try {
            $linkResponse = $client->post("/{$graphApiVersion}/{$pageId}/feed", [
                'form_params' => [
                    'access_token' => $pageAccessToken,
                    'message' => $_POST['main_message'],
                    'link' => $_POST['image_link'],
                ]
            ]);
            $linkData = json_decode($linkResponse->getBody()->getContents(), true);
            if (isset($linkData['id'])) {
                $postUrl = 'https://www.facebook.com/' . $linkData['id'];
                $statusMessages[] = '<div class="status success">Post រូបភាព/Link បានជោគជ័យ! <a href="' . $postUrl . '" target="_blank">មើលនៅលើ Facebook</a>.</div>';
            } else { throw new Exception("Link post creation failed. API Response: " . json_encode($linkData)); }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseBody = $e->getResponse()->getBody()->getContents();
            $errorData = json_decode($responseBody, true);
            $errorMessage = $errorData['error']['message'] ?? 'An unknown error occurred.';
            $statusMessages[] = '<div class="status error"><strong>ការ Post រូបភាព/Link បរាជ័យ:</strong> ' . htmlspecialchars($errorMessage) . '</div>';
        }
    }
} catch (Exception $e) {
    $statusMessages[] = '<div class="status error"><strong>មានបញ្ហារะบบ:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
} finally {
    foreach ($tempFilesToClean as $file) {
        if (file_exists($file)) {
            @unlink($file);
        }
    }
}
?>
<!-- HTML part remains the same -->
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facebook Auto-Converting Poster</title>
    <style>
        body { background-color: #f0f2f5; font-family: 'Noto Sans Khmer', sans-serif; display: flex; justify-content: center; align-items: flex-start; padding: 20px; color: #1c1e21; }
        .container { background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); width: 100%; max-width: 600px; padding: 20px; box-sizing: border-box; }
        h2 { text-align: center; margin-top: 0; }
        h2 small { font-size: 0.8rem; color: #606770; font-weight: 400; display: block; }
        .form-group { margin-bottom: 16px; }
        .card-section { border: 1px solid #e4e6eb; border-radius: 8px; padding: 16px; margin-bottom: 20px; }
        .card-section h3 { margin-top: 0; color: #1877f2; }
        label { display: block; font-weight: 500; margin-bottom: 8px; color: #606770; }
        input[type="text"], input[type="url"], textarea { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccd0d5; font-family: 'Noto Sans Khmer', sans-serif; font-size: 1rem; box-sizing: border-box; }
        textarea { height: 100px; resize: vertical; }
        input[type="file"] { border: 1px solid #ccd0d5; padding: 8px; border-radius: 6px; width: 100%; box-sizing: border-box; }
        .preview { margin-top: 10px; max-width: 100%; text-align: center; }
        .preview img, .preview video { max-width: 100%; max-height: 200px; border-radius: 6px; border: 1px solid #e4e6eb; }
        .submit-button { width: 100%; background-color: #1877f2; color: white; border: none; border-radius: 6px; padding: 12px 0; font-size: 1.1rem; font-weight: bold; font-family: 'Noto Sans Khmer', sans-serif; margin-top: 16px; cursor: pointer; transition: background-color 0.2s; }
        .status { padding: 15px; margin-bottom: 10px; border-radius: 6px; font-weight: 500; line-height: 1.5; word-wrap: break-word; }
        .status.success { background-color: #e9f6ec; color: #3f814d; border: 1px solid #a7d3b0; }
        .status.error { background-color: #fdeeee; color: #a33a3a; border: 1px solid #f7c7c7; }
        .status a { color: #3f814d; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h2>បង្កើត Post ជាមួយប៊ូតុង Shop Now <small>(បំប្លែងវីដេអូស្វ័យប្រវត្តិ និងបង្កើត Post ២)</small></h2>
        <?php if (!empty($statusMessages)) echo implode('', $statusMessages); ?>
        <form action="" method="post" enctype="multipart/form-data">
            
            <div class="form-group">
                <label for="main_message">ខ្លឹមសារ Post រួម:</label>
                <textarea id="main_message" name="main_message" placeholder="សរសេរអ្វីមួយនៅទីនេះ..." required></textarea>
            </div>

            <!-- Video Post Section -->
            <div class="card-section">
                <h3><i class="fa-solid fa-video"></i> Post ទី១: វីដេអូ</h3>
                <div class="form-group">
                    <label for="video_file">ជ្រើសរើសវីដេអូ (Format ណាក៏បាន):</label>
                    <input type="file" id="video_file" name="video_file" accept="video/*" required>
                </div>
                <div class="form-group">
                    <label for="video_title">ចំណងជើងវីដេអូ:</label>
                    <input type="text" id="video_title" name="video_title" placeholder="ចំណងជើងបង្ហាញលើវីដេអូ" required>
                </div>
                <div class="form-group">
                    <label for="video_link">តំណភ្ជាប់ (Link) សម្រាប់ប៊ូតុង Shop Now របស់វីដេអូ:</label>
                    <input type="url" id="video_link" name="video_link" placeholder="https://your-shop.com/video-product" required>
                </div>
            </div>

            <!-- Image Post Section -->
            <div class="card-section">
                <h3><i class="fa-solid fa-image"></i> Post ទី២: រូបភាព/Link</h3>
                <div class="form-group">
                    <label for="image_file">ជ្រើសរើសរូបភាព (សម្រាប់ Preview):</label>
                    <input type="file" id="image_file" name="image_file" accept="image/png, image/jpeg, image/gif" required>
                </div>
                <div class="form-group">
                    <label for="image_link">តំណភ្ជាប់ (Link) សម្រាប់ Post:</label>
                    <input type="url" id="image_link" name="image_link" placeholder="https://your-shop.com/image-product" required>
                </div>
            </div>

            <button type="submit" class="submit-button"><i class="fa-solid fa-paper-plane"></i> បង្កើត Post ទាំងពីរ</button>
        </form>
    </div>
</body>
</html>