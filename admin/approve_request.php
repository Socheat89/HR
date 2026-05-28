<?php
// Start session and check authentication
session_start();
require_once 'includes/auth.php';
if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}

// Include database connection
require_once 'includes/db.php';
$conn = include 'includes/db.php';

// Get request ID from URL
$request_id = $_GET['id'] ?? '';

if (!$request_id) {
    header("Location: pending_requests.php?error=invalid_request");
    exit();
}

try {
    // Update request status to 'approved' in the database
    $stmt = $conn->prepare("UPDATE requests SET status = 'approved' WHERE id = ? AND status = 'pending'");
    $stmt->execute([$request_id]);

    if ($stmt->rowCount() === 0) {
        header("Location: pending_requests.php?error=no_changes");
        exit();
    }

    // Fetch request details for Telegram notification
    $requestStmt = $conn->prepare("SELECT * FROM requests WHERE id = ?");
    $requestStmt->execute([$request_id]);
    $request = $requestStmt->fetch(PDO::FETCH_ASSOC);

    if ($request) {
        // Send Telegram notification
        require_once 'includes/telegram.php';
        $message = "សួស្ដី: " . htmlspecialchars($request['requester_name']) . "\n" .
                    "✅ <b>សំណើររបស់អ្នកត្រូវបានអនុញ្ញាត</b>\n" .
                   "ប្រភេទស្នើរសុំ: " . htmlspecialchars($request['request_type']) . "\n" .
                   "ចំនួនថ្ងៃ: " . htmlspecialchars($request['number_of_days']) . "\n" .
                   "បុគ្គលិកផ្នែក: " . htmlspecialchars($request['department'] ?? 'N/A') . "\n" .
                   "មូលហេតុ: " . htmlspecialchars($request['reason']) . "\n" .
                   "ថ្ងៃខែឆ្នាំ: " . htmlspecialchars($request['request_date'] ?? 'N/A');
        sendTelegramMessage('-1002496391098', $message); // Replace with your Telegram chat ID
    }

    header("Location: pending_requests.php?success=approved");
    exit();

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header("Location: pending_requests.php?error=database_error");
    exit();
}
?>