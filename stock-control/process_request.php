<?php
// FILE: ../requests/process_request.php
session_start();
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 2;
}
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') die("Invalid access method.");

$user_id = $_SESSION['user_id'];
$request_title = htmlspecialchars($_POST['request_title'] ?? 'Item Request');
$location_request = htmlspecialchars($_POST['location_request'] ?? '');
$item_ids = $_POST['item_id'] ?? [];
$request_qtys = $_POST['request_qty'] ?? [];
$notes_arr = $_POST['notes'] ?? [];

try {
    $pdo->beginTransaction();

    // Generate request_no
    $stmt_last_request = $pdo->prepare("SELECT request_no FROM stock_request WHERE request_no LIKE 'REQ%' ORDER BY CAST(SUBSTRING(request_no, 4) AS UNSIGNED) DESC LIMIT 1");
    $stmt_last_request->execute();
    $last_request_no = $stmt_last_request->fetchColumn();
    if ($last_request_no && preg_match('/^REQ\d+$/', $last_request_no)) {
        $last_number = (int)substr($last_request_no, 3);
        $next_number = $last_number + 1;
        $request_no = 'REQ' . $next_number;
    } else {
        $request_no = 'REQ1';
    }

    // Check uniqueness
    $stmt_check_request_no = $pdo->prepare("SELECT COUNT(*) FROM stock_request WHERE request_no = ?");
    $stmt_check_request_no->execute([$request_no]);
    $attempts = 0;
    while ($stmt_check_request_no->fetchColumn() > 0 && $attempts < 10) {
        $next_number++;
        $request_no = 'REQ' . $next_number;
        $stmt_check_request_no->execute([$request_no]);
        $attempts++;
    }
    if ($attempts >= 10) {
        throw new Exception("Unable to generate a unique request number.");
    }

    // Insert into stock_request
    $stmt_req = $pdo->prepare("INSERT INTO stock_request (user_id, request_no, title, location_request, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
    $stmt_req->execute([$user_id, $request_no, $request_title, $location_request]);
    $stock_request_id = $pdo->lastInsertId();

    // Insert items
    for ($i = 0; $i < count($item_ids); $i++) {
        if (!empty($item_ids[$i]) && !empty($request_qtys[$i]) && $request_qtys[$i] > 0) {
            $item_id = (int)$item_ids[$i];
            $requested_quantity = (int)$request_qtys[$i];
            $note = htmlspecialchars($notes_arr[$i] ?? '');

            $stmt_save_item = $pdo->prepare(
                "INSERT INTO stock_request_items (stock_request_id, item_id, requested_quantity, notes) VALUES (?, ?, ?, ?)"
            );
            $stmt_save_item->execute([$stock_request_id, $item_id, $requested_quantity, $note]);
        }
    }

    $pdo->commit();
    header("Location: user_request_form.php?success=Request submitted successfully");
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    die("Error: " . $e->getMessage());
}
?>