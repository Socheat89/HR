<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// កំណត់ការកំហុស
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error_log.txt');

// កំណត់រចនាសម្ព័ន្ធផ្ទុក
define('USE_DATABASE', true); // កំណត់ជា false ដើម្បីប្រើ JSON ឯកសារ
define('JSON_FILE_PATH', 'users.json'); // ទីតាំងឯកសារ JSON

// ភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ (ប្រសិនបើប្រើ MySQL)
if (USE_DATABASE) {
    try {
        require 'db_connect.php';
        $pdo->exec("SET NAMES 'utf8mb4'");
    } catch (Exception $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] កំហុសភ្ជាប់មូលដ្ឋានទិន្នន័យ: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'បរាជ័យក្នុងការភ្ជាប់មូលដ្ឋានទិន្នន័យ']);
        exit;
    }
}

// កត់ត្រាសំណើចូល
$requestData = file_get_contents('php://input');
error_log("[" . date('Y-m-d H:i:s') . "] សំណើចូលទៅ save_user.php: " . $requestData);

// ឌិកូដ JSON សំណើ
$data = json_decode($requestData, true);
if (!$data || !isset($data['telegram_id'])) {
    error_log("[" . date('Y-m-d H:i:s') . "] ទិន្នន័យសំណើមិនត្រឹមត្រូវ: " . json_encode($data));
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'telegram_id មិនត្រឹមត្រូវ ឬបាត់']);
    exit;
}

// ទាញ និងផ្ទៀងផ្ទាត់ទិន្នន័យ
$telegram_id = $data['telegram_id'];
$first_name = isset($data['first_name']) ? trim($data['first_name']) : '';
$last_name = isset($data['last_name']) ? trim($data['last_name']) : '';
$username = isset($data['username']) ? trim($data['username']) : '';
$language = isset($data['language']) ? trim($data['language']) : 'km'; // លំនាំដើមជាខ្មែរ
$photo_url = isset($data['photo_url']) ? trim($data['photo_url']) : '';

// ផ្ទៀងផ្ទាត់ telegram_id
if (!is_numeric($telegram_id) || $telegram_id <= 0) {
    error_log("[" . date('Y-m-d H:i:s') . "] telegram_id មិនត្រឹមត្រូវ: " . $telegram_id);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'telegram_id មិនត្រឹមត្រូវ']);
    exit;
}

// ផ្ទៀងផ្ទាត់ប្រវែង និងខ្លឹមសារ
if (strlen($first_name) > 255) $first_name = substr($first_name, 0, 255);
if (strlen($last_name) > 255) $last_name = substr($last_name, 0, 255);
if (strlen($username) > 255) $username = substr($username, 0, 255);
if (strlen($language) > 10) $language = substr($language, 0, 10);
if (strlen($photo_url) > 1000) $photo_url = substr($photo_url, 0, 1000);
if (empty($first_name)) $first_name = 'មិនស្គាល់';
if (!preg_match('/^[a-zA-Z]{2}$/', $language)) $language = 'km';
if ($photo_url && !filter_var($photo_url, FILTER_VALIDATE_URL)) $photo_url = '';

// រៀបចំទិន្នន័យអ្នកប្រើ
$userData = [
    'telegram_id' => $telegram_id,
    'first_name' => $first_name,
    'last_name' => $last_name,
    'username' => $username,
    'language' => $language,
    'photo_url' => $photo_url,
    'last_login' => date('Y-m-d H:i:s')
];

if (USE_DATABASE) {
    try {
        // បង្កើតតារាងអ្នកប្រើប្រសិនបើមិនទាន់មាន
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                telegram_id BIGINT PRIMARY KEY,
                first_name VARCHAR(255) NOT NULL,
                last_name VARCHAR(255) DEFAULT '',
                username VARCHAR(255) DEFAULT '',
                language VARCHAR(10) DEFAULT 'km',
                photo_url VARCHAR(1000) DEFAULT '',
                user_data JSON,
                last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
        ");

        // បញ្ចូល ឬធ្វើបច្ចុប្បន្នភាពទិន្នន័យ
        $maxRetries = 3;
        $retryCount = 0;
        while ($retryCount < $maxRetries) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (telegram_id, first_name, last_name, username, language, photo_url, user_data, last_login) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                        first_name = ?, 
                        last_name = ?, 
                        username = ?, 
                        language = ?, 
                        photo_url = ?, 
                        user_data = ?, 
                        last_login = ?, 
                        updated_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([
                    $telegram_id, $first_name, $last_name, $username, $language, $photo_url, json_encode($userData, JSON_UNESCAPED_UNICODE), $userData['last_login'],
                    $first_name, $last_name, $username, $language, $photo_url, json_encode($userData, JSON_UNESCAPED_UNICODE), $userData['last_login']
                ]);
                break;
            } catch (PDOException $e) {
                $retryCount++;
                if ($retryCount === $maxRetries || strpos($e->getMessage(), 'Deadlock') === false) {
                    throw $e;
                }
                usleep(100000 * $retryCount);
            }
        }

        error_log("[" . date('Y-m-d H:i:s') . "] រក្សាទុកអ្នកប្រើជោគជ័យទៅមូលដ្ឋានទិន្នន័យសម្រាប់ telegram_id: $telegram_id");
        echo json_encode(['success' => true, 'user' => $userData]);
    } catch (PDOException $e) {
        $errorMessage = 'កំហុសមូលដ្ឋានទិន្នន័យ: ' . $e->getMessage();
        if (strpos($e->getMessage(), 'no such table') !== false) {
            $errorMessage = 'រកមិនឃើញតារាង "users"';
        } elseif (strpos($e->getMessage(), 'duplicate entry') !== false) {
            $errorMessage = 'អ្នកប្រើស្ទួន';
        }
        error_log("[" . date('Y-m-d H:i:s') . "] កំហុស PDO ក្នុង save_user.php: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $errorMessage]);
    } catch (Exception $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] កំហុសមិនរំពឹងទុកក្នុង save_user.php: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'កំហុសមិនរំពឹងទុក: ' . $e->getMessage()]);
    }
} else {
    // ផ្ទុកទៅឯកសារ JSON
    try {
        // អានអ្នកប្រើដែលមានស្រាប់
        $users = [];
        if (file_exists(JSON_FILE_PATH)) {
            $jsonContent = file_get_contents(JSON_FILE_PATH);
            $users = json_decode($jsonContent, true);
            if (!is_array($users)) {
                $users = [];
            }
        }

        // ធ្វើបច្ចុប្បន្នភាព ឬបន្ថែមអ្នកប្រើ
        $users[$telegram_id] = $userData;

        // រក្សាទុកទៅឯកសារ
        file_put_contents(JSON_FILE_PATH, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        error_log("[" . date('Y-m-d H:i:s') . "] រក្សាទុកអ្នកប្រើជោគជ័យទៅឯកសារ JSON សម្រាប់ telegram_id: $telegram_id");
        echo json_encode(['success' => true, 'user' => $userData]);
    } catch (Exception $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] កំហុសក្នុងការរក្សាទុកទៅឯកសារ JSON: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'បរាជ័យក្នុងការរក្សាទុកអ្នកប្រើទៅឯកសារ JSON: ' . $e->getMessage()]);
    }
}
?>