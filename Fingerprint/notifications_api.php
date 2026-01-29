<?php
// Set timezone to Phnom Penh
date_default_timezone_set('Asia/Phnom_Penh');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$host = 'localhost';
$dbname = 'samann1_fingerprint_db';
$username = 'samann1_Fingerprint';
$password = 'Fingerprint@2025';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set MySQL timezone to UTC+7 (Phnom Penh)
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage(), 3, 'error.log');
    sendResponse(500, 'error', 'Database connection failed');
}

// Helper function for JSON responses
function sendResponse($httpCode, $status, $message, $data = []) {
    http_response_code($httpCode);
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {
    case 'get_notifications_count':
        getNotificationsCount($pdo);
        break;
    case 'get_notifications':
        getNotifications($pdo);
        break;
    case 'push_notification':
        pushNotification($pdo);
        break;
    case 'mark_notifications_read':
        markNotificationsRead($pdo);
        break;
    case 'edit_notification':
        editNotification($pdo);
        break;
    case 'delete_notification':
        deleteNotification($pdo);
        break;
    default:
        sendResponse(400, 'error', 'Invalid action');
}

function getNotificationsCount($pdo) {
    $user_id = isset($_GET['id']) ? trim($_GET['id']) : null;
    try {
        $sql = "SELECT COUNT(*) as unread FROM notifications WHERE read_status = 0";
        if ($user_id) {
            $sql .= " AND (user_id = :user_id OR user_id IS NULL)";
        } else {
            $sql .= " AND user_id IS NULL";
        }
        $stmt = $pdo->prepare($sql);
        if ($user_id) {
            $stmt->bindParam(':user_id', $user_id);
        }
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        sendResponse(200, 'success', 'Notification count retrieved', ['unread' => (int)$result['unread']]);
    } catch (PDOException $e) {
        error_log("Failed to fetch notification count: " . $e->getMessage(), 3, 'error.log');
        sendResponse(500, 'error', 'Failed to fetch notification count');
    }
}

function getNotifications($pdo) {
    $user_id = isset($_GET['id']) ? trim($_GET['id']) : null;
    try {
        $sql = "SELECT id, user_id, title, message, time, read_status FROM notifications";
        if ($user_id) {
            $sql .= " WHERE user_id = :user_id OR user_id IS NULL";
        }
        $sql .= " ORDER BY time DESC";
        $stmt = $pdo->prepare($sql);
        if ($user_id) {
            $stmt->bindParam(':user_id', $user_id);
        }
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format time with AM/PM for each notification
        foreach ($notifications as &$notification) {
            $dateTime = new DateTime($notification['time']);
            $notification['time_formatted'] = $dateTime->format('Y-m-d h:i A'); // Add AM/PM format
        }
        unset($notification); // Unset reference to avoid side effects
        
        sendResponse(200, 'success', 'Notifications retrieved', ['notifications' => $notifications]);
    } catch (PDOException $e) {
        error_log("Failed to fetch notifications: " . $e->getMessage(), 3, 'error.log');
        sendResponse(500, 'error', 'Failed to fetch notifications');
    }
}

function pushNotification($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(405, 'error', 'Method not allowed');
        return;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(400, 'error', 'Invalid JSON data');
        return;
    }
    $title = isset($data['title']) ? trim($data['title']) : '';
    $message = isset($data['message']) ? trim($data['message']) : '';
    $user_id = isset($data['user_id']) ? trim($data['user_id']) : null;

    if (empty($title) || empty($message)) {
        sendResponse(400, 'error', 'Title and message are required');
        return;
    }

    try {
        $sql = "INSERT INTO notifications (title, message, user_id, time, read_status) VALUES (:title, :message, :user_id, :time, 0)";
        $stmt = $pdo->prepare($sql);
        $currentTime = date('Y-m-d H:i:s'); // Store in database as Y-m-d H:i:s
        $stmt->execute([
            ':title' => $title,
            ':message' => $message,
            ':user_id' => $user_id,
            ':time' => $currentTime
        ]);
        
        // Format time with AM/PM for response
        $dateTime = new DateTime($currentTime);
        $formattedTime = $dateTime->format('Y-m-d h:i A');
        
        sendResponse(201, 'success', 'Notification created successfully', [
            'notification' => [
                'title' => $title,
                'message' => $message,
                'user_id' => $user_id,
                'time' => $currentTime,
                'time_formatted' => $formattedTime
            ]
        ]);
    } catch (PDOException $e) {
        error_log("Failed to create notification: " . $e->getMessage(), 3, 'error.log');
        sendResponse(500, 'error', 'Failed to create notification');
    }
}

function markNotificationsRead($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(405, 'error', 'Method not allowed');
        return;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(400, 'error', 'Invalid JSON data');
        return;
    }
    $user_id = isset($data['id']) ? trim($data['id']) : null;
    if (!$user_id) {
        sendResponse(400, 'error', 'User ID is required');
        return;
    }
    try {
        $sql = "UPDATE notifications SET read_status = 1 WHERE user_id = :user_id OR user_id IS NULL";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        sendResponse(200, 'success', 'Notifications marked as read');
    } catch (PDOException $e) {
        error_log("Failed to mark notifications as read: " . $e->getMessage(), 3, 'error.log');
        sendResponse(500, 'error', 'Failed to mark notifications as read');
    }
}

function editNotification($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(405, 'error', 'Method not allowed');
        return;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(400, 'error', 'Invalid JSON data');
        return;
    }
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    $title = isset($data['title']) ? trim($data['title']) : '';
    $message = isset($data['message']) ? trim($data['message']) : '';
    $user_id = isset($data['user_id']) ? trim($data['user_id']) : null;

    if (!$id || empty($title) || empty($message)) {
        sendResponse(400, 'error', 'ID, title, and message are required');
        return;
    }

    try {
        $sql = "UPDATE notifications SET title = :title, message = :message, user_id = :user_id WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':title' => $title,
            ':message' => $message,
            ':user_id' => $user_id
        ]);
        sendResponse(200, 'success', 'Notification updated successfully');
    } catch (PDOException $e) {
        error_log("Failed to update notification: " . $e->getMessage(), 3, 'error.log');
        sendResponse(500, 'error', 'Failed to update notification');
    }
}

function deleteNotification($pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(405, 'error', 'Method not allowed');
        return;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(400, 'error', 'Invalid JSON data');
        return;
    }
    $id = isset($data['id']) ? (int)$data['id'] : 0;

    if (!$id) {
        sendResponse(400, 'error', 'Notification ID is required');
        return;
    }

    try {
        $sql = "DELETE FROM notifications WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        sendResponse(200, 'success', 'Notification deleted successfully');
    } catch (PDOException $e) {
        error_log("Failed to delete notification: " . $e->getMessage(), 3, 'error.log');
        sendResponse(500, 'error', 'Failed to delete notification');
    }
}
?>