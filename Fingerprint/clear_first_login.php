<?php
session_start();

// Clear the first_login flag
if (isset($_SESSION['first_login'])) {
    unset($_SESSION['first_login']);
}

// Send a JSON response
header('Content-Type: application/json');
echo json_encode(['status' => 'success']);
exit;