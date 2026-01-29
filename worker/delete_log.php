<?php
// បង្ហាញ Error ទាំងអស់សម្រាប់ Debugging (គួរតែបិទវានៅពេលដាក់ឲ្យប្រើប្រាស់จริง)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// កំណត់ Header ឲ្យច្បាស់ថាការឆ្លើយតបគឺ JSON
header('Content-Type: application/json; charset=utf-8');

// ចាប់ផ្ដើម Session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 3600);
    session_set_cookie_params(3600);
    session_start();
}

// Function សម្រាប់បញ្ជូនការឆ្លើយតបជា JSON ហើយបញ្ឈប់ Script
function send_json_response($data, $http_code = 200) {
    http_response_code($http_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // --- Security Checks ---

    // 1. Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json_response(['success' => false, 'error' => 'Invalid request method.'], 405);
    }

    // 2. Check if user is logged in
    $is_logged_in = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) || 
                    (isset($_SESSION['sub_user_logged_in']) && $_SESSION['sub_user_logged_in'] === true);

    if (!$is_logged_in) {
        send_json_response(['success' => false, 'error' => 'Authentication required. Please log in.'], 401);
    }

    // 3. Verify CSRF token
    if (empty($_SESSION['csrf_token']) || empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        send_json_response(['success' => false, 'error' => 'Invalid CSRF token.'], 403);
    }

    // 4. Validate the log ID
    if (!isset($_POST['id']) || !filter_var($_POST['id'], FILTER_VALIDATE_INT)) {
        send_json_response(['success' => false, 'error' => 'Invalid Log ID.'], 400);
    }
    $log_id = (int)$_POST['id'];

    // --- Database Operation ---
    
    // Database connection
    $pdo = new PDO(
        "mysql:host=localhost;dbname=samann1_scan_logs_worker_db;charset=utf8mb4",
        'samann1_scan_logs_worker_db',
        'scan_logs_worker_db@2025',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Prepare and execute the delete statement
    $stmt = $pdo->prepare("DELETE FROM scan_logs WHERE id = :id");
    $stmt->bindParam(':id', $log_id, PDO::PARAM_INT);
    
    $stmt->execute();

    // Check if a row was actually deleted
    if ($stmt->rowCount() > 0) {
        send_json_response(['success' => true, 'message' => 'ទិន្នន័យត្រូវបានលុបដោយជោគជ័យ។']);
    } else {
        send_json_response(['success' => false, 'error' => 'រកមិនឃើញទិន្នន័យ ឬត្រូវបានលុបរួចហើយ។'], 404);
    }

} catch (PDOException $e) {
    // ចាប់យកកំហុស Database
    error_log("Database Error in delete_log.php: " . $e->getMessage()); // Log error សម្រាប់អ្នកអភិវឌ្ឍន៍
    send_json_response(['success' => false, 'error' => 'មានបញ្ហាទាក់ទងនឹងមូលដ្ឋានទិន្នន័យ។'], 500);

} catch (Throwable $e) {
    // ចាប់យកកំហុសផ្សេងៗទាំងអស់ (All other errors)
    error_log("General Error in delete_log.php: " . $e->getMessage()); // Log error សម្រាប់អ្នកអភិវឌ្ឍន៍
    send_json_response(['success' => false, 'error' => 'មានបញ្ហាមិនរំពឹងទុកកើតឡើង។'], 500);
}
?>