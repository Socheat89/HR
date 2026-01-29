<?php
set_time_limit(3600);

function show_response($message, $is_error = false) {
    $class = $is_error ? 'error' : 'success';
    echo "<style>
            body { font-family: 'Koulen', sans-serif; padding: 20px; }
            .result { margin-top: 20px; padding: 15px; border-radius: 5px; border: 1px solid; }
            .success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
            .error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
          </style>";
    echo "<div class='result {$class}'>{$message}</div>";
    echo "<a href='index.html'>ត្រឡប់ទៅវិញ</a>";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $page_id = $_POST['page_id'];
    $access_token = $_POST['access_token'];
    $message = $_POST['message'];

    // ពិនិត្យថាមាន File Upload យ៉ាងហោចណាស់ពីរ
    if (!isset($_FILES['videos']) || !is_array($_FILES['videos']['name']) || count($_FILES['videos']['name']) < 2 || empty($_FILES['videos']['name'][0])) {
        show_response("សូមជ្រើសរើសវីដេអូយ៉ាងហោចណាស់ពីរ។", true);
    }
    
    $video_ids = [];
    $total_files = count($_FILES['videos']['name']);

    // --- ជំហានទី១: Upload វីដេអូនីមួយៗ ---
    for ($i = 0; $i < $total_files; $i++) {
        if ($_FILES['videos']['error'][$i] !== UPLOAD_ERR_OK) {
            show_response("មានបញ្ហាក្នុងការ Upload File: " . $_FILES['videos']['name'][$i], true);
            continue;
        }

        $file_tmp_path = $_FILES['videos']['tmp_name'][$i];
        $file_name = $_FILES['videos']['name'][$i];
        $file_type = $_FILES['videos']['type'][$i];

        $api_url_upload = "https://graph-video.facebook.com/v19.0/{$page_id}/videos";
        
        $post_data_upload = [
            'access_token' => $access_token,
            'description' => 'Video for carousel post: ' . $file_name,
            'source' => new CURLFile($file_tmp_path, $file_type, $file_name)
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url_upload);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_upload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($http_code == 200 && isset($result['id'])) {
            $video_ids[] = $result['id'];
        } else {
            $error_message = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown error during video upload.';
            show_response("បរាជ័យក្នុងការ Upload វីដេអូ '{$file_name}': " . htmlspecialchars($error_message) . " (HTTP Code: {$http_code})", true);
        }
    }

    // --- ពិនិត្យចំនួន video_ids មុនពេលបង្កើត Carousel ---
    if (count($video_ids) < 2) {
        show_response("ត្រូវការយ៉ាងហោចណាស់ពីរវីដេអូដើម្បីបង្កើត Carousel Post។ មានតែ " . count($video_ids) . " វីដេអូប៉ុណ្ណោះដែលបាន Upload ជោគជ័យ។", true);
    }

    // --- ជំហានទី២: បង្កើត Carousel Post ---
    $child_attachments = [];
    foreach ($video_ids as $vid_id) {
        $child_attachments[] = [
            'video_id' => $vid_id,
            'link' => "https://www.facebook.com/{$page_id}/",
            'name' => 'ទស្សនាវីដេអូថ្មី!'
        ];
    }

    $api_url_post = "https://graph.facebook.com/v19.0/{$page_id}/feed";
    
    $post_data_carousel = [
        'message' => $message,
        'child_attachments' => json_encode($child_attachments),
        'access_token' => $access_token,
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url_post);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data_carousel));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);
    
    if ($http_code == 200 && isset($result['id'])) {
        $post_url = "https://www.facebook.com/" . $result['id'];
        show_response("Video Carousel ត្រូវបានបង្ហោះដោយជោគជ័យ! <a href='{$post_url}' target='_blank'>មើល Post</a>");
    } else {
        $error_message = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown error during carousel creation.';
        show_response("បរាជ័យក្នុងការបង្កើត Carousel Post: " . htmlspecialchars($error_message) . " (HTTP Code: {$http_code})", true);
    }
}
?>