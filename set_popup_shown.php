<?php
session_start();

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
error_log("set_popup_shown.php called, Session ID: " . session_id());

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_popup_shown') {
    $_SESSION['announcement_popup_shown'] = true;
    error_log("Session flag set: announcement_popup_shown = true");
    echo json_encode(['success' => true]);
} else {
    error_log("Invalid request: " . print_r($_POST, true));
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>