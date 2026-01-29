<?php
// เพิ่มส่วนนี้ที่ด้านบนสุดเพื่อช่วยในการดีบัก
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$uploadDir = 'image/uploads/';
$response = ['success' => false, 'error' => 'Invalid request.'];

// ตรวจสอบว่าโฟลเดอร์ uploads มีอยู่จริงและสามารถเขียนได้
if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
    $response = ['success' => false, 'error' => 'Uploads directory not found or not writable. Check permissions (e.g., chmod 755).'];
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $file['tmp_name'];
        $fileName = basename($file['name']); // ใช้ basename เพื่อความปลอดภัย
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // สร้างชื่อไฟล์ใหม่ที่ไม่ซ้ำกัน
        $newFileName = uniqid('', true) . '.' . $fileExtension;

        $allowedfileExtensions = ['jpg', 'jpeg', 'gif', 'png'];
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $dest_path = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                $domainName = $_SERVER['HTTP_HOST'];
                $basePath = rtrim($protocol . $domainName . dirname($_SERVER['PHP_SELF']), '/') . '/';
                $fileUrl = $basePath . $dest_path;

                $response = [
                    'success' => true,
                    'url' => $fileUrl
                ];
            } else {
                $response['error'] = 'Error moving the uploaded file.';
            }
        } else {
            $response['error'] = 'Upload failed. Allowed file types: ' . implode(', ', $allowedfileExtensions);
        }
    } else {
        // แปลงรหัสข้อผิดพลาดเป็นข้อความที่เข้าใจง่าย
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
        ];
        $errorCode = $file['error'];
        $response['error'] = $uploadErrors[$errorCode] ?? 'Unknown upload error.';
    }
}

echo json_encode($response);
?>