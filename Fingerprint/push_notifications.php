
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection configuration
$host = 'localhost';
$dbname = 'samann1_fingerprint_db';
$username = 'samann1_Fingerprint';
$password = 'Fingerprint@2025';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage(), 3, 'error.log');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Handle API requests
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'push_notification':
        pushNotification($pdo);
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        exit;
}

function pushNotification($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON input']);
        return;
    }

    $title = isset($data['title']) ? trim($data['title']) : '';
    $message = isset($data['message']) ? trim($data['message']) : '';
    $user_id = isset($data['user_id']) ? trim($data['user_id']) : null;

    if (empty($title) || empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Title and message are required']);
        return;
    }

    try {
        $sql = "INSERT INTO notifications (user_id, title, message, time, read_status) VALUES (:user_id, :title, :message, NOW(), 0)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':title' => $title,
            ':message' => $message
        ]);
        echo json_encode(['status' => 'success', 'message' => 'Notification pushed successfully']);
    } catch (PDOException $e) {
        error_log("Failed to push notification: " . $e->getMessage(), 3, 'error.log');
        echo json_encode(['status' => 'error', 'message' => 'Failed to push notification: ' . $e->getMessage()]);
    }
}
?>
