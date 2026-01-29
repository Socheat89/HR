<?php
// duplicate_log.php (UPDATED to use original timestamp)

session_start();
header('Content-Type: application/json; charset=utf-8');

// Security Check 1: Authentication
if ((!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) && (!isset($_SESSION['sub_user_logged_in']) || $_SESSION['sub_user_logged_in'] !== true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please log in.']);
    exit;
}

// Security Check 2: CSRF Token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token.']);
    exit;
}

try {
    // === START: MODIFICATION 1 - Receive and Validate Timestamp ===
    $id = $_POST['id'] ?? null;
    $original_timestamp = $_POST['timestamp'] ?? null; // ទទួល timestamp ដែលបានផ្ញើពី JavaScript

    // ពិនិត្យមើលថា ID និង timestamp ត្រូវបានផ្ញើមក
    if (empty($id) || !is_numeric($id) || empty($original_timestamp)) {
        throw new Exception('Invalid ID or missing timestamp provided.');
    }

    // (Optional but recommended) ពិនិត្យមើលទម្រង់របស់ timestamp
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $original_timestamp);
    if (!$d || $d->format('Y-m-d H:i:s') !== $original_timestamp) {
        throw new Exception('Invalid timestamp format provided.');
    }
    // === END: MODIFICATION 1 ===


    // Database connection
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    // 1. Fetch the original record
    $stmt = $pdo->prepare("SELECT * FROM scan_logs WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $original_log = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$original_log) {
        throw new Exception('Record not found.');
    }

    // 2. Prepare data for the new record
    unset($original_log['id']); // ដក ID ចេញដើម្បីឱ្យ database បង្កើត ID ថ្មីដោយស្វ័យប្រវត្តិ
    $original_log['noted'] = ($original_log['noted'] ? rtrim($original_log['noted'], ' (ចម្លង)') . ' ' : '') . '(ចម្លង)'; // បន្ថែមចំណាំ (ចៀសវាងការបន្ថែមซ้ำซ้อน)
    
    // === START: MODIFICATION 2 - Use the Original Timestamp ===
    // ជំនួសបន្ទាត់កូដចាស់: $original_log['timestamp'] = date('Y-m-d H:i:s');
    // ដោយប្រើ timestamp ដែលបានផ្ញើមកពី JavaScript
    $original_log['timestamp'] = $original_timestamp;
    // === END: MODIFICATION 2 ===


    $columns = array_keys($original_log);
    $placeholders = array_map(fn($col) => ':' . $col, $columns);
    
    // 3. Insert the new record
    $sql = "INSERT INTO scan_logs (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
    $insert_stmt = $pdo->prepare($sql);
    $insert_stmt->execute($original_log);
    
    // ផ្ញើសារជូនដំណឹងដែលបញ្ជាក់ច្បាស់លាស់
    echo json_encode(['success' => true, 'message' => 'ទិន្នន័យត្រូវបានចម្លងដោយជោគជ័យ ដោយប្រើថ្ងៃខែឆ្នាំដដែល។']);

} catch (Exception $e) {
    // Log the detailed error, show generic message
    error_log("Duplicate log error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'An error occurred while duplicating data. Please check the logs.']);
}
?>