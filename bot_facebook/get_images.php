<?php
// เพิ่มส่วนนี้ที่ด้านบนสุดเพื่อช่วยในการดีบัก
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$uploadDir = 'image/uploads/';
$response = [];

// ตรวจสอบว่าโฟลเดอร์ uploads มีอยู่จริงและสามารถอ่านได้
if (!is_dir($uploadDir) || !is_readable($uploadDir)) {
    $response = ['success' => false, 'error' => 'Uploads directory not found or not readable.'];
    echo json_encode($response);
    exit;
}

// ใช้ scandir เพื่ออ่านชื่อไฟล์ทั้งหมดในโฟลเดอร์
// และใช้ array_diff เพื่อลบ '.' และ '..' ออกจากผลลัพธ์
$files = array_diff(scandir($uploadDir), ['.', '..']);

$imageUrls = [];

// สร้าง URL เต็มสำหรับแต่ละไฟล์
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'];
// dirname($_SERVER['PHP_SELF']) จะให้ path ของไดเรกทอรีปัจจุบัน
$basePath = rtrim($protocol . $domainName . dirname($_SERVER['PHP_SELF']), '/') . '/' . $uploadDir;

// จัดเรียงไฟล์ตามลำดับเวลาย้อนกลับ (ไฟล์ใหม่ล่าสุดอยู่ก่อน)
// เราจะใช้ filemtime เพื่อความแม่นยำ
usort($files, function($a, $b) use ($uploadDir) {
    return filemtime($uploadDir . $b) - filemtime($uploadDir . $a);
});

foreach ($files as $file) {
    // ตรวจสอบว่าเป็นไฟล์จริง ไม่ใช่โฟลเดอร์ย่อย
    if (is_file($uploadDir . $file)) {
        // ตรวจสอบว่าเป็นรูปภาพที่อนุญาตหรือไม่ (แนะนำให้ทำ)
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $fileExtension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($fileExtension, $allowedExtensions)) {
            $imageUrls[] = $basePath . $file;
        }
    }
}

$response = ['success' => true, 'images' => $imageUrls];
echo json_encode($response);
?>