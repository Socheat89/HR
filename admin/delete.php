<?php
// Diagnostic: Output basic info if file is accessed
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['id'])) {
    die("delete.php is working. Please provide an 'id' parameter (e.g., ?id=1) or use POST.");
}

// Include required files
$authFile = 'includes/auth.php';
$dbFile = 'includes/db.php';
$telegramFile = 'includes/telegram.php';

if (!file_exists($authFile) || !file_exists($dbFile) || !file_exists($telegramFile)) {
    die("Error: One or more include files are missing: $authFile, $dbFile, $telegramFile");
}

require_once $authFile;
require_once $dbFile;
require_once $telegramFile;

// Authentication checks
if (!function_exists('isLoggedIn')) {
    die("Error: isLoggedIn() function not defined. Check includes/auth.php");
}

if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

if (!isset($_SESSION) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Database connection
$conn = include $dbFile;
if (!$conn instanceof PDO) {
    die("Error: Database connection failed. Check includes/db.php");
}

// Check for ID
if (!isset($_GET['id']) && !isset($_POST['delete_id'])) {
    header("Location: view_processed_requests.php?error=No ID provided");
    exit();
}

$deleteId = isset($_POST['delete_id']) ? $_POST['delete_id'] : $_GET['id'];

try {
    // Prepare and execute delete query
    $stmt = $conn->prepare("DELETE FROM requests WHERE id = ?");
    $stmt->execute([$deleteId]);

    // Send Telegram notification
    if (!function_exists('sendTelegramMessage')) {
        error_log("sendTelegramMessage function not defined. Check includes/telegram.php");
    } else {
        sendTelegramMessage('-1002496391098', "🗑️ Request ID $deleteId deleted by admin.");
    }

    // Redirect with success
    header("Location: view_processed_requests.php?success=Request deleted successfully");
    exit();
} catch (PDOException $e) {
    // Log and notify error
    error_log("Delete error: " . $e->getMessage());
    if (function_exists('sendTelegramMessage')) {
        sendTelegramMessage('-1002496391098', "❌ Error deleting request ID $deleteId: " . $e->getMessage());
    }

    // Redirect with error
    header("Location: view_processed_requests.php?error=Error deleting request: " . $e->getMessage());
    exit();
}
?>