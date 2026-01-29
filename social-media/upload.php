<?php
// Configuration
$BASE_URL = 'https://app.vvc.asia/social-media/uploads/';
$upload_dir = 'social-media/uploads/';

// Increase memory limit and execution time
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 300);
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');

// Enable error reporting and logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'errors.log');

header('Content-Type: application/json');

try {
    // Clean up files older than 24 hours
    $expiration_time = 24 * 60 * 60; // 24 hours in seconds
    if (is_dir($upload_dir)) {
        $files = glob($upload_dir . '*');
        foreach ($files as $file) {
            if (is_file($file) && (time() - filemtime($file)) > $expiration_time) {
                if (unlink($file)) {
                    error_log("Deleted expired file: $file");
                } else {
                    error_log("Failed to delete expired file: $file");
                }
            }
        }
    }

    // Create social-media directory
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception("Failed to create social-media directory.");
        }
    }
    if (!is_writable($upload_dir)) {
        if (!chmod($upload_dir, 0755)) {
            throw new Exception("Failed to set social-media directory permissions to writable.");
        }
    }

    // Validate PHP configuration
    if (!extension_loaded('fileinfo')) {
        throw new Exception("PHP fileinfo extension is not enabled.");
    }

    // Validate media files
    $media_urls = [];
    $max_file_size = 50 * 1024 * 1024; // 50MB
    if (isset($_FILES['media']) && !empty($_FILES['media']['name'][0])) {
        $files = $_FILES['media'];
        $file_count = count($files['name']);

        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file_path = $files['tmp_name'][$i];
                $file_name = $files['name'][$i];
                $file_size = $files['size'][$i];
                $mime_type = mime_content_type($file_path);
                $is_image = strpos($mime_type, 'image/') === 0;
                $is_video = strpos($mime_type, 'video/') === 0;

                if (!$is_image && !$is_video) {
                    error_log("Invalid file type: $file_name, MIME: $mime_type");
                    continue;
                }

                if ($file_size > $max_file_size) {
                    error_log("File too large: $file_name, Size: $file_size");
                    continue;
                }

                $new_file_name = uniqid() . '_' . $file_name;
                $new_file_path = $upload_dir . $new_file_name;
                if (!move_uploaded_file($file_path, $new_file_path)) {
                    error_log("Failed to move file: $file_name to $new_file_path");
                    continue;
                }
                if (!chmod($new_file_path, 0644)) {
                    error_log("Failed to set permissions for file: $new_file_path");
                }

                $file_url = $BASE_URL . $new_file_name;
                $media_urls[] = $file_url;
            } else {
                $error_codes = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds form max size',
                    UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
                ];
                $error_message = $error_codes[$files['error'][$i]] ?? 'Unknown upload error';
                error_log("Upload error for $file_name: $error_message (Code: {$files['error'][$i]})");
            }
        }
    } else {
        throw new Exception("No files uploaded.");
    }

    if (empty($media_urls)) {
        throw new Exception("No valid files uploaded or accessible.");
    }

    echo json_encode([
        'success' => true,
        'urls' => $media_urls
    ]);
} catch (Exception $e) {
    error_log("Upload exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>