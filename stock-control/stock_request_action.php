<?php
// Set timezone
date_default_timezone_set('Asia/Phnom_Penh');

// Include PDO database connection
require_once 'db_connect.php'; // defines $pdo

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and assign
    $item_id = isset($_POST['item_id']) ? trim($_POST['item_id']) : '';
    $qty = isset($_POST['qty']) ? (int) $_POST['qty'] : 0;
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $created_at = date('Y-m-d H:i:s');

    // Validate inputs
    if (empty($item_id) || $qty <= 0 || empty($reason)) {
        header('Location: stock_request.php?error=1');
        exit;
    }

    try {
        // Prepare and execute insert using PDO
        $stmt = $pdo->prepare(
            "INSERT INTO stock_requests (item_id, qty, reason, created_at)
             VALUES (:item_id, :qty, :reason, :created_at)"
        );
        $stmt->execute([
            ':item_id'   => $item_id,
            ':qty'       => $qty,
            ':reason'    => $reason,
            ':created_at'=> $created_at
        ]);

        // Redirect back with success flag
        header('Location: stock_request.php?success=1');
        exit;
    } catch (PDOException $e) {
        // Log or display error
        error_log('DB Insert Error: ' . $e->getMessage());
        echo "Error: " . htmlspecialchars($e->getMessage());
    }
} else {
    // Redirect if not POST
    header('Location: stock_request.php');
    exit;
}
?>