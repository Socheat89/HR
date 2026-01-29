<?php
// File: redirect.php (เวอร์ชัน MySQL)

// ========= การกำหนดค่า (MySQL CONFIGURATION) =========
// !สำคัญ: กรุณาเปลี่ยนค่าเหล่านี้ให้ตรงกับการตั้งค่า Server ของคุณ
$dbHost = 'localhost';      // หรือ IP ของ Database Server
$dbName = 'samann1_facebook-bot';    // ชื่อฐานข้อมูลที่คุณสร้าง
$dbUser = 'samann1_facebook-bot';           // ชื่อผู้ใช้ฐานข้อมูล
$dbPass = 'facebook-bot!@#';               // รหัสผ่าน (ถ้ามี)
// ===================================================

// 1. รับ short code จาก URL (ที่ส่งมาจาก .htaccess)
$short_code = isset($_GET['code']) ? trim($_GET['code']) : '';

if (empty($short_code)) {
    http_response_code(400);
    echo "ลิงก์ไม่ถูกต้อง";
    exit;
}

try {
    // 2. เชื่อมต่อฐานข้อมูล MySQL ด้วย PDO
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);

    // 3. ค้นหา long URL ที่ตรงกับ short code
    $stmt = $pdo->prepare("SELECT long_url FROM links WHERE short_code = ?");
    $stmt->execute([$short_code]);
    $result = $stmt->fetch();

    if ($result && isset($result['long_url'])) {
        // 4. ถ้าเจอ, ทำการ redirect ผู้ใช้ไปยัง long URL นั้น
        header('Location: ' . $result['long_url'], true, 301);
        exit;
    } else {
        // 5. ถ้าไม่เจอ, แสดง Error 404
        http_response_code(404);
        echo "ขออภัย, ไม่พบลิงก์ที่คุณต้องการ";
        exit;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo "เกิดข้อผิดพลาดทางเทคนิค";
    // สำหรับ Debug: error_log("Database Redirect Error: " . $e->getMessage());
    exit;
}
?>