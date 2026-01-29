<?php
// trigger_push.php - ឯកសារសម្រាប់តេស្តផ្ញើ Notification

require __DIR__ . '/vendor/autoload.php';
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// ================== CONFIGURATION ==================
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'samann1_attendance_db');
define('DB_PASSWORD', 'attendance@2025'); // <-- ត្រូវប្តូរ
define('DB_NAME', 'samann1_attendance_db');

// !! ត្រូវយក VAPID Keys ពី scan.php មកដាក់នៅទីនេះឱ្យដូចគ្នា !!
define('VAPID_PUBLIC_KEY', 'BH8ltrldiZH39zNjlkKEJyb9eaMrhpCLkOq-yaV0w1R8h4WdbL9J-uYJP0UI-1G1-0npZiS5qoh7W4peMx6ClI8');   // <--- ត្រូវប្តូរ
define('VAPID_PRIVATE_KEY', 'u4tDFreGRahEvCQtENDz0A230sStkcRfwQCmXWY7hUg'); // <--- ត្រូវប្តូរ
// ===================================================


// 1. ភ្ជាប់ទៅ Database
$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_error) {
    die("Database Connection Failed: " . $mysqli->connect_error);
}
$mysqli->set_charset("utf8mb4");

// 2. ទាញយក subscriptions ទាំងអស់ពី Database
$sql = "SELECT endpoint, p256dh, auth FROM web_push_subscriptions";
$result = $mysqli->query($sql);

$subscriptions = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $subscriptions[] = Subscription::create([
            'endpoint' => $row['endpoint'],
            'publicKey' => $row['p256dh'],
            'authToken' => $row['auth'],
        ]);
    }
} else {
    echo "No active subscriptions found.";
    exit;
}
$mysqli->close();


// 3. រៀបចំ VAPID authentication
$auth = [
    'VAPID' => [
        'subject' => 'mailto:your-email@example.com', // ដាក់អ៊ីមែលរបស់អ្នក
        'publicKey' => VAPID_PUBLIC_KEY,
        'privateKey' => VAPID_PRIVATE_KEY,
    ],
];

// 4. បង្កើត object WebPush
$webPush = new WebPush($auth);

// 5. រៀបចំ Payload (ข้อมูลที่จะส่ง)
$payload = json_encode([
    'title' => 'សេចក្តីជូនដំណឹងពីប្រព័ន្ធ',
    'body' => 'នេះគឺជាការតេស្ត Web Push Notification. ម៉ោង: ' . date('H:i:s'),
    'url' => '/scan.php' // URL ที่จะเปิดเมื่อผู้ใช้คลิก
]);

// 6. ផ្ញើ Notification ទៅកាន់ subscribers ម្នាក់ៗ
foreach ($subscriptions as $subscription) {
    $webPush->queueNotification($subscription, $payload);
}

// 7. ដំណើរការផ្ញើ និងបង្ហាញលទ្ធផល
echo "Sending notifications...\n<br>";
foreach ($webPush->flush() as $report) {
    $endpoint = $report->getRequest()->getUri()->__toString();
    if ($report->isSuccess()) {
        echo "[v] Message sent successfully for subscription {$endpoint}.\n<br>";
    } else {
        echo "[x] Message failed to send for subscription {$endpoint}: {$report->getReason()}\n<br>";
    }
}
echo "Process finished.";

?>