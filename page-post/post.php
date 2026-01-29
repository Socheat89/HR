<?php

/**
 * =================================================================
 * DEBUGGING: បើកការបង្ហាញកំហុស
 * បិទផ្នែកនេះ (ដោយដាក់ // នៅខាងមុខ) ពេលដាក់ឱ្យប្រើប្រាស់ជាផ្លូវការ
 * =================================================================
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// === ចាប់ផ្តើមកម្មវិធី ===

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$statusMessage = '';
$configFilePath = __DIR__ . '/config.php';
$autoloaderPath = __DIR__ . '/vendor/autoload.php';

try {
    // 1. ពិនិត្យមើល Autoloader របស់ Composer
    if (!file_exists($autoloaderPath)) {
        throw new Exception("Error: Autoloader not found at '{$autoloaderPath}'. Please run 'composer install' in your project directory.");
    }
    require_once $autoloaderPath;

    // 2. ពិនិត្យមើលឯកសារ Config
    if (!file_exists($configFilePath)) {
        throw new Exception("Error: Configuration file 'config.php' not found. Please create it.");
    }
    $config = require $configFilePath;

    // 3. ពិនិត្យមើលព័ត៌មាននៅក្នុង Config
    if (empty($config['page_id']) || empty($config['page_access_token']) || $config['page_id'] === 'YOUR_FACEBOOK_PAGE_ID') {
        throw new Exception("Error: Please fill in your Page ID and Page Access Token in 'config.php'.");
    }

    // Import Guzzle classes
    use GuzzleHttp\Client;
    use GuzzleHttp\Exception\ClientException;

    $apiUrl = 'https://graph.facebook.com/v16.0/'; // ប្រើ API version ថ្មី

    // 4. ពិនិត្យមើលថាតើទម្រង់ត្រូវបានបញ្ជូន (submit) ឬទេ
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST['message'])) {
            throw new Exception("Please enter a message for your post.");
        }
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Please select an image to upload. Error code: " . ($_FILES['image']['error'] ?? 'N/A'));
        }
        
        $message = $_POST['message'];
        $imagePath = $_FILES['image']['tmp_name'];
        $imageName = $_FILES['image']['name'];

        $client = new Client(['base_uri' => $apiUrl]);

        // ធ្វើការ Post ដោយប្រើ multipart/form-data
        $response = $client->post($config['page_id'] . '/photos', [
            'multipart' => [
                ['name' => 'caption', 'contents' => $message],
                ['name' => 'source', 'contents' => fopen($imagePath, 'r'), 'filename' => $imageName],
                ['name' => 'access_token', 'contents' => $config['page_access_token']],
            ]
        ]);

        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        if (isset($data['id'])) {
            $postUrl = 'https://www.facebook.com/' . $data['id'];
            $statusMessage = '<div class="status success">Post ជោគជ័យ! <a href="' . $postUrl . '" target="_blank">ចុចទីនេះដើម្បីមើល Post</a></div>';
        } else {
            throw new Exception("Post failed. API response: " . htmlspecialchars($body));
        }
    }
} catch (ClientException $e) {
    // ចាប់កំហុសជាក់លាក់ពី Facebook API (4xx errors)
    $responseBody = $e->getResponse()->getBody()->getContents();
    $errorData = json_decode($responseBody, true);
    $errorMessage = $errorData['error']['message'] ?? 'An unknown API error occurred.';
    $statusMessage = '<div class="status error"><strong>API Error:</strong> ' . htmlspecialchars($errorMessage) . '</div>';
} catch (Exception $e) {
    // ចាប់កំហុសទូទៅទាំងអស់ (file not found, config error, etc.)
    $statusMessage = '<div class="status error"><strong>System Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</div>';
}

?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post to Facebook Page (Fixed)</title>
    <!-- CSS និង Fonts រក្សាទុកដដែល -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* CSS ទាំងអស់រក្សាទុកដូចដើម */
        body { background-color: #f0f2f5; font-family: 'Noto Sans Khmer', sans-serif; display: flex; justify-content: center; align-items: flex-start; padding: 20px; color: #1c1e21; }
        .container { background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); width: 100%; max-width: 500px; padding: 20px; box-sizing: border-box; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-weight: 500; margin-bottom: 8px; color: #606770; }
        textarea { width: 100%; height: 120px; padding: 10px; border-radius: 6px; border: 1px solid #ccd0d5; font-family: 'Noto Sans Khmer', sans-serif; font-size: 1rem; resize: vertical; }
        .file-input-wrapper { position: relative; overflow: hidden; display: inline-block; border: 1px dashed #ccd0d5; border-radius: 6px; width: 100%; padding: 20px; text-align: center; cursor: pointer; background-color: #f7f8fa; }
        .file-input-wrapper:hover { background-color: #f0f2f5; }
        .file-input-wrapper input[type=file] { font-size: 100px; position: absolute; left: 0; top: 0; opacity: 0; cursor: pointer; }
        .file-input-wrapper .icon { font-size: 2.5rem; color: #1877f2; }
        .file-input-wrapper .text { margin-top: 8px; color: #606770; }
        #image-preview { margin-top: 15px; max-width: 100%; text-align: center; }
        #image-preview img { max-width: 100%; max-height: 250px; border-radius: 6px; border: 1px solid #e4e6eb; }
        .submit-button { width: 100%; background-color: #1877f2; color: white; border: none; border-radius: 6px; padding: 12px 0; font-size: 1.1rem; font-weight: bold; font-family: 'Noto Sans Khmer', sans-serif; margin-top: 16px; cursor: pointer; transition: background-color 0.2s; }
        .submit-button:hover { background-color: #166fe5; }
        .status { padding: 15px; margin-bottom: 20px; border-radius: 6px; font-weight: 500; text-align: left; line-height: 1.5; }
        .status.success { background-color: #e9f6ec; color: #3f814d; border: 1px solid #a7d3b0; }
        .status.error { background-color: #fdeeee; color: #a33a3a; border: 1px solid #f7c7c7; }
        .status a { color: #3f814d; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="text-align: center; margin-top: 0;">បង្កើត Post ទៅកាន់ Facebook Page</h2>
        
        <!-- បង្ហាញសារ Status -->
        <?php if (!empty($statusMessage)) echo $statusMessage; ?>

        <!-- ទម្រង់ HTML -->
        <form action="index.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="message">ខ្លឹមសារ Post:</label>
                <textarea id="message" name="message" rows="5" placeholder="សរសេរអ្វីមួយនៅទីនេះ..." required></textarea>
            </div>
            <div class="form-group">
                <label>ជ្រើសរើសរូបភាព:</label>
                <div class="file-input-wrapper">
                    <input type="file" name="image" id="image" accept="image/png, image/jpeg, image/gif" required onchange="previewImage(event)">
                    <i class="fa-solid fa-cloud-arrow-up icon"></i>
                    <div class="text">ចុចដើម្បីជ្រើសរើសរូបភាព</div>
                </div>
                <div id="image-preview"></div>
            </div>
            <button type="submit" class="submit-button">
                <i class="fa-solid fa-paper-plane"></i> បង្កើត Post
            </button>
        </form>
    </div>

    <!-- JavaScript រក្សាទុកដដែល -->
    <script>
        function previewImage(event) {
            const imagePreview = document.getElementById('image-preview');
            imagePreview.innerHTML = '';
            if (event.target.files && event.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e){
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    imagePreview.appendChild(img);
                };
                reader.readAsDataURL(event.target.files[0]);
            }
        }
    </script>
</body>
</html>