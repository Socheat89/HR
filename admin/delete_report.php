<?php
ini_set('session.cookie_lifetime', 86400);
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/telegram.php';

$dbHost = 'localhost';
$dbName = 'samann1_admin_panel';
$dbUser = 'samann1_admin_panel';
$dbPass = 'admin_panel@2025';
$telegramChatId = '-1002496391098';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET NAMES 'utf8mb4'");

    if (isset($_GET['id'])) {
        $report_id = (int)$_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM daily_reports WHERE id = ?");
        $stmt->execute([$report_id]);
        $report = $stmt->fetch();

        if ($report) {
            $stmt = $pdo->prepare("DELETE FROM daily_reports WHERE id = ?");
            $stmt->execute([$report_id]);

            $message = "របាយការណ៍ប្រចាំថ្ងៃបានលុប:\n" .
                       "- លេខសម្គាល់: {$report['id']}\n" .
                       "- ឈ្មោះ: {$report['name']}\n" .
                       "- ផ្នែក: {$report['position']}\n" .
                       "- កាលបរិច្ឆេទ: " . date('Y-m-d H:i:s');
            if (!sendTelegramMessage($telegramChatId, $message)) {
                error_log("Failed to send Telegram message for deleted report ID: $report_id");
            }
            header('Location: dashboard.php?view=reports&success=deleted');
            exit;
        } else {
            header('Location: dashboard.php?view=reports&error=invalid_report');
            exit;
        }
    } else {
        header('Location: dashboard.php?view=reports&error=invalid_request');
        exit;
    }
} catch (Exception $e) {
    error_log("Error deleting report: " . $e->getMessage());
    header('Location: dashboard.php?view=reports&error=database_error');
    exit;
}
?>