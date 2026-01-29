<?php
session_start();
header('Content-Type: application/json; charset=UTF-8');

// Function to send a JSON error response and exit
function send_json_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// **ฟังก์ชันตรวจสอบใหม่** (ไม่หยุดการทำงาน) เพื่อใช้กับการโหลดข้อมูล
// Returns true if the data URL is a valid PNG, otherwise false.
function is_valid_png_data_url($dataUrl) {
    if (!is_string($dataUrl) || !preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $m)) {
        return false;
    }
    $b64 = $m[1];
    if (empty($b64)) {
        return false;
    }
    $raw = base64_decode($b64, true);
    if ($raw === false || empty($raw)) {
        return false;
    }
    // PNG signature check (\x89PNG\r\n\x1a\n)
    if (strlen($raw) < 8 || substr($raw, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        return false;
    }
    return true;
}


// Function to validate and normalize a PNG data URL for saving (stops on error)
function validate_and_normalize_png_data_url($dataUrl) {
    if (!is_valid_png_data_url($dataUrl)) {
         send_json_error('ទម្រង់ Data URL របស់ហត្ថលេខាមិនត្រឹមត្រូវ ឬខូច។');
    }
    // Re-encode to ensure data is consistent
    $parts = explode(',', $dataUrl, 2);
    $raw = base64_decode($parts[1]);
    return 'data:image/png;base64,' . base64_encode($raw);
}


// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    send_json_error('សូមចូលប្រើប្រាស់ប្រព័ន្ធជាមុនសិន។', 401);
}

// Database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=samann1_admin_panel', 'samann1_admin_panel', 'admin_panel@2025', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    error_log("Database Connection Error in ajax_signatures: " . $e->getMessage());
    send_json_error('មានបញ្ហាក្នុងការតភ្ជាប់មូលដ្ឋានទិន្នន័យ។', 500);
}

$action = $_POST['action'] ?? '';
$current_user_id = (int)$_SESSION['user_id'];
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

switch ($action) {
    case 'load':
        try {
            $target_user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : $current_user_id;
            if (!$is_admin && $target_user_id !== $current_user_id) {
                send_json_error('មិនមានសិទ្ធិគ្រប់គ្រាន់។', 403);
            }

            $stmt = $pdo->prepare("SELECT id, signature_data FROM user_signatures WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
            $stmt->execute([$target_user_id]);
            $signatures = $stmt->fetchAll();

            // **การแก้ไขที่สำคัญ**: กรองข้อมูลลายเซ็นที่เสียออกอย่างเข้มงวด
            // โดยใช้ฟังก์ชัน `is_valid_png_data_url` ที่สร้างขึ้นใหม่
            $clean_signatures = [];
            foreach ($signatures as $sig) {
                if (isset($sig['signature_data']) && is_valid_png_data_url($sig['signature_data'])) {
                    $clean_signatures[] = $sig;
                }
            }

            echo json_encode(['success' => true, 'signatures' => $clean_signatures]);

        } catch (PDOException $e) {
            error_log("Error loading signatures: " . $e->getMessage());
            send_json_error('មានបញ្ហាក្នុងការទាញយកហត្ថលេខា។');
        }
        break;

    case 'save':
        try {
            $signature_data = $_POST['signature_data'] ?? null;
            $target_user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : $current_user_id;

            if (!$is_admin && $target_user_id !== $current_user_id) {
                send_json_error('មិនមានសិទ្ធិគ្រប់គ្រាន់។', 403);
            }
            if (empty($signature_data)) {
                send_json_error('ទិន្នន័យហត្ថលេខាមិនត្រឹមត្រូវ។');
            }

            $normalizedDataUrl = validate_and_normalize_png_data_url($signature_data);

            $checkStmt = $pdo->prepare("SELECT id FROM user_signatures WHERE user_id = ? AND signature_data = ?");
            $checkStmt->execute([$target_user_id, $normalizedDataUrl]);
            if ($checkStmt->fetch()) {
                 echo json_encode(['success' => true, 'message' => 'ហត្ថលេខានេះមានរួចហើយ។']);
                 exit;
            }

            $stmt = $pdo->prepare("INSERT INTO user_signatures (user_id, signature_data) VALUES (?, ?)");
            $stmt->execute([$target_user_id, $normalizedDataUrl]);
            $new_id = $pdo->lastInsertId();

            echo json_encode(['success' => true, 'id' => $new_id, 'message' => 'រក្សាទុកហត្ថលេខាបានជោគជ័យ។']);
        } catch (PDOException $e) {
            error_log("Error saving signature: " . $e->getMessage());
            send_json_error('មានបញ្ហាក្នុងការរក្សាទុកហត្ថលេខា។');
        }
        break;

    case 'delete':
        try {
            $signature_id = isset($_POST['signature_id']) ? (int)$_POST['signature_id'] : 0;
            if ($signature_id <= 0) {
                send_json_error('លេខសម្គាល់ហត្ថលេខាមិនត្រឹមត្រូវ។');
            }
            
            $sql = "DELETE FROM user_signatures WHERE id = ?";
            $params = [$signature_id];

            if (!$is_admin) {
                $sql .= " AND user_id = ?";
                $params[] = $current_user_id;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true]);
            } else {
                send_json_error('មិនអាចលុបហត្ថលេខាបានទេ ឬរកមិនឃើញ។', 404);
            }
        } catch (PDOException $e) {
            error_log("Error deleting signature: " . $e->getMessage());
            send_json_error('មានបញ្ហាក្នុងការលុបហត្ថលេខា។');
        }
        break;

    default:
        send_json_error('សកម្មភាពមិនត្រឹមត្រូវ។');
        break;
}
?>