<?php
session_start();
date_default_timezone_set('Asia/Phnom_Penh');



ob_start();
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Accept-Charset');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
error_reporting(E_ALL);

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_fingerprint_db;charset=utf8mb4", "samann1_Fingerprint", "Fingerprint@2025");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage() . " [SQLSTATE: " . $e->getCode() . "]");
    sendResponse('error', 'Database connection failed');
}

// Include QR code library
require 'vendor/autoload.php';
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// Telegram Groups Configuration
$telegramGroups = [
    'specialist' => [
        'botToken' => '7205851039:AAHBOJmY40GvNl7M0X_FN9Ml0Fg2T_KQpb8',
        'chatId' => '-1002282814819'
    ],
    'worker' => [
        'botToken' => '8116100212:AAEn8nUrU7QqqkSWFaptH6gaRmiMWzNullo',
        'chatId' => '-1002330039942'
    ]
];

// Function to send message to Telegram
function sendTelegramMessage($botToken, $chatId, $message, $parseMode = 'Markdown') {
    $telegramUrl = "https://api.telegram.org/bot$botToken/sendMessage";
    $telegramData = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => $parseMode
    ];

    $ch = curl_init($telegramUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($telegramData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log("Telegram API error: $curlError");
        return ['success' => false, 'error' => $curlError];
    }

    $result = json_decode($response, true);
    if (!$result['ok']) {
        error_log("Telegram API response error: " . $result['description']);
        return ['success' => false, 'error' => $result['description']];
    }

    return ['success' => true];
}

// Load data from MySQL
function loadData($file = null) {
    global $pdo;

    try {
        // Fetch folders
        $stmt = $pdo->query("SELECT id, name FROM folders");
        $folders = $stmt->fetchAll();

        // Fetch users
        $stmt = $pdo->query("SELECT id, username, department, position, branch, workplace, folder, timeSettings FROM users");
        $users = $stmt->fetchAll();
        foreach ($users as &$user) {
            $user['timeSettings'] = json_decode($user['timeSettings'], true);
            if ($user['timeSettings'] === null) {
                error_log("Invalid timeSettings JSON for user ID: " . $user['id']);
                $user['timeSettings'] = ['check_in_ranges' => [], 'scans_ranges' => []];
            }
        }

        // Fetch allowed locations
        $stmt = $pdo->query("SELECT id, name, branch, latitude, longitude, qr_code, users FROM allowed_locations");
        $allowedLocations = $stmt->fetchAll();
        foreach ($allowedLocations as &$location) {
            $location['users'] = json_decode($location['users'], true);
            if ($location['users'] === null) {
                error_log("Invalid users JSON for location ID: " . $location['id']);
                $location['users'] = [];
            }
        }

        // Fetch scans
        $stmt = $pdo->query("SELECT username, user_id AS id, action, department, position, branch, workplace, folder, latitude, longitude, timestamp, scanStatus, address, token, early_reason, location_name FROM scans");
        $scans = $stmt->fetchAll();
        foreach ($scans as &$scan) {
            $scan['timestamp'] = (new DateTime($scan['timestamp']))->format('d/m/Y h:i:s A');
        }

        // Fetch active users
        $stmt = $pdo->query("SELECT token, id, username, loginTime FROM active_users");
        $activeUsersRaw = $stmt->fetchAll();
        $activeUsers = [];
        foreach ($activeUsersRaw as $user) {
            $activeUsers[$user['token']] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'loginTime' => (new DateTime($user['loginTime']))->format('Y-m-d H:i:s')
            ];
        }

        // Fetch settings
        $stmt = $pdo->query("SELECT maxTokensPerUser FROM settings LIMIT 1");
        $settings = $stmt->fetch();
        if (!$settings) {
            $settings = ['maxTokensPerUser' => 3];
            $pdo->prepare("INSERT INTO settings (maxTokensPerUser) VALUES (:maxTokensPerUser)")->execute(['maxTokensPerUser' => 3]);
        }

        return [
            'folders' => $folders,
            'users' => $users,
            'allowedLocations' => $allowedLocations,
            'scans' => $scans,
            'activeUsers' => $activeUsers,
            'settings' => $settings
        ];
    } catch (PDOException $e) {
        error_log("Database query failed: " . $e->getMessage() . " [SQLSTATE: " . $e->getCode() . "]");
        sendResponse('error', 'Failed to load data from database');
    }
}

// Save data to MySQL with selective updates
function saveData($data, $file = null, $backupFile = null) {
    global $pdo;

    try {
        $pdo->beginTransaction();

        // Validate data structure
        if (!isset($data['folders']) || !isset($data['users']) || !isset($data['allowedLocations']) ||
            !isset($data['scans']) || !isset($data['activeUsers']) || !isset($data['settings'])) {
            error_log("Invalid data structure in saveData");
            sendResponse('error', 'Invalid data structure');
        }

        // Update folders
        $stmtFolders = $pdo->prepare("INSERT INTO folders (id, name) VALUES (:id_insert, :name_insert) ON DUPLICATE KEY UPDATE name = :name_update");
        foreach ($data['folders'] as $folder) {
            if (!isset($folder['id']) || !isset($folder['name'])) {
                error_log("Invalid folder data: " . print_r($folder, true));
                continue;
            }
            $stmtFolders->execute([
                ':id_insert' => $folder['id'],
                ':name_insert' => $folder['name'],
                ':name_update' => $folder['name']
            ]);
        }

        // Update users
        $stmtUsers = $pdo->prepare("INSERT INTO users (id, username, department, position, branch, workplace, folder, timeSettings) VALUES (:id_insert, :username_insert, :department_insert, :position_insert, :branch_insert, :workplace_insert, :folder_insert, :timeSettings_insert) ON DUPLICATE KEY UPDATE username = :username_update, department = :department_update, position = :position_update, branch = :branch_update, workplace = :workplace_update, folder = :folder_update, timeSettings = :timeSettings_update");
        foreach ($data['users'] as $user) {
            if (!isset($user['id']) || !isset($user['username']) || !isset($user['timeSettings'])) {
                error_log("Invalid user data: " . print_r($user, true));
                continue;
            }
            $timeSettings = json_encode($user['timeSettings'], JSON_UNESCAPED_UNICODE);
            if ($timeSettings === false) {
                error_log("Invalid timeSettings JSON for user ID: " . $user['id']);
                continue;
            }
            $stmtUsers->execute([
                ':id_insert' => $user['id'],
                ':username_insert' => $user['username'],
                ':department_insert' => $user['department'] ?? null,
                ':position_insert' => $user['position'] ?? null,
                ':branch_insert' => $user['branch'] ?? null,
                ':workplace_insert' => $user['workplace'] ?? 'N/A',
                ':folder_insert' => $user['folder'] ?? 'N/A',
                ':timeSettings_insert' => $timeSettings,
                ':username_update' => $user['username'],
                ':department_update' => $user['department'] ?? null,
                ':position_update' => $user['position'] ?? null,
                ':branch_update' => $user['branch'] ?? null,
                ':workplace_update' => $user['workplace'] ?? 'N/A',
                ':folder_update' => $user['folder'] ?? 'N/A',
                ':timeSettings_update' => $timeSettings
            ]);
        }

        // Update allowed locations
        $stmtLocations = $pdo->prepare("INSERT INTO allowed_locations (id, name, branch, latitude, longitude, qr_code, users) VALUES (:id_insert, :name_insert, :branch_insert, :latitude_insert, :longitude_insert, :qr_code_insert, :users_insert) ON DUPLICATE KEY UPDATE name = :name_update, branch = :branch_update, latitude = :latitude_update, longitude = :longitude_update, qr_code = :qr_code_update, users = :users_update");
        foreach ($data['allowedLocations'] as $location) {
            if (!isset($location['id']) || !isset($location['name']) || !isset($location['users'])) {
                error_log("Invalid location data: " . print_r($location, true));
                continue;
            }
            $usersJson = json_encode($location['users'], JSON_UNESCAPED_UNICODE);
            if ($usersJson === false) {
                error_log("Invalid users JSON for location ID: " . $location['id']);
                continue;
            }
            $stmtLocations->execute([
                ':id_insert' => $location['id'],
                ':name_insert' => $location['name'],
                ':branch_insert' => $location['branch'] ?? null,
                ':latitude_insert' => $location['latitude'],
                ':longitude_insert' => $location['longitude'],
                ':qr_code_insert' => $location['qr_code'] ?? null,
                ':users_insert' => $usersJson,
                ':name_update' => $location['name'],
                ':branch_update' => $location['branch'] ?? null,
                ':latitude_update' => $location['latitude'],
                ':longitude_update' => $location['longitude'],
                ':qr_code_update' => $location['qr_code'] ?? null,
                ':users_update' => $usersJson
            ]);
        }

        // Update scans
        $stmtScans = $pdo->prepare("INSERT INTO scans (username, user_id, action, department, position, branch, workplace, folder, latitude, longitude, timestamp, scanStatus, address, token, early_reason, location_name) VALUES (:username, :user_id, :action, :department, :position, :branch, :workplace, :folder, :latitude, :longitude, :timestamp, :scanStatus, :address, :token, :early_reason, :location_name)");
        foreach ($data['scans'] as $scan) {
            if (!isset($scan['id']) || !isset($scan['username']) || !isset($scan['action']) || !isset($scan['timestamp'])) {
                error_log("Invalid scan data: " . print_r($scan, true));
                continue;
            }
            $timestamp = DateTime::createFromFormat('d/m/Y h:i:s A', $scan['timestamp']);
            if ($timestamp === false) {
                error_log("Invalid timestamp format for scan: " . $scan['timestamp']);
                continue;
            }
            $stmtScans->execute([
                ':username' => $scan['username'],
                ':user_id' => $scan['id'],
                ':action' => $scan['action'],
                ':department' => $scan['department'] ?? null,
                ':position' => $scan['position'] ?? null,
                ':branch' => $scan['branch'] ?? null,
                ':workplace' => $scan['workplace'] ?? null,
                ':folder' => $scan['folder'] ?? null,
                ':latitude' => $scan['latitude'] ?? null,
                ':longitude' => $scan['longitude'] ?? null,
                ':timestamp' => $timestamp->format('Y-m-d H:i:s'),
                ':scanStatus' => $scan['scanStatus'],
                ':address' => $scan['address'] ?? null,
                ':token' => $scan['token'] ?? null,
                ':early_reason' => $scan['early_reason'] ?? null,
                ':location_name' => $scan['location_name'] ?? null
            ]);
        }

        // Update active users
        $stmtActiveUsers = $pdo->prepare("INSERT INTO active_users (token, id, username, loginTime) VALUES (:token_insert, :id_insert, :username_insert, :loginTime_insert) ON DUPLICATE KEY UPDATE id = :id_update, username = :username_update, loginTime = :loginTime_update");
        foreach ($data['activeUsers'] as $token => $user) {
            if (!isset($user['id']) || !isset($user['username']) || !isset($user['loginTime'])) {
                error_log("Invalid active user data: " . print_r($user, true));
                continue;
            }
            $stmtActiveUsers->execute([
                ':token_insert' => $token,
                ':id_insert' => $user['id'],
                ':username_insert' => $user['username'],
                ':loginTime_insert' => $user['loginTime'],
                ':id_update' => $user['id'],
                ':username_update' => $user['username'],
                ':loginTime_update' => $user['loginTime']
            ]);
        }

        // Update settings
        $stmtSettings = $pdo->prepare("INSERT INTO settings (maxTokensPerUser) VALUES (:maxTokensPerUser_insert) ON DUPLICATE KEY UPDATE maxTokensPerUser = :maxTokensPerUser_update");
        $stmtSettings->execute([
            ':maxTokensPerUser_insert' => $data['settings']['maxTokensPerUser'] ?? 3,
            ':maxTokensPerUser_update' => $data['settings']['maxTokensPerUser'] ?? 3
        ]);

        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Database save failed: " . $e->getMessage() . " [SQLSTATE: " . $e->getCode() . "]");
        sendResponse('error', 'Failed to save data to database: ' . $e->getMessage());
    }
}

// Response function
function sendResponse($status, $message = '', $data = []) {
    $response = array_merge(['status' => $status, 'message' => $message], $data);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Generate unique ID for locations
function generateUniqueId() {
    return 'loc_' . bin2hex(random_bytes(4));
}

$data = loadData();
$maxTokensPerUser = $data['settings']['maxTokensPerUser'] ?? 3;

// Helper functions
function countActiveTokensForUser($data, $userId) {
    return count(array_filter($data['activeUsers'], fn($u) => $u['id'] === $userId));
}

function isAdminAuthenticated() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function validateTimeRanges($ranges) {
    if (!is_array($ranges)) return false;
    foreach ($ranges as $range) {
        if (!isset($range['start']) || !isset($range['end']) || !isset($range['status']) ||
            !is_numeric($range['start']) || !is_numeric($range['end']) ||
            $range['start'] >= $range['end'] || !in_array($range['status'], ['Good', 'Late'])) {
            return false;
        }
    }
    return true;
}

function calculateScanStatus($scanTime, $ranges, $hasEarlyReason = false) {
    if ($hasEarlyReason) return 'Good';
    $scanMinutes = ($scanTime->format('H') * 60) + $scanTime->format('i');
    foreach ($ranges as $range) {
        if ($scanMinutes >= $range['start'] && $scanMinutes <= $range['end']) {
            return $range['status'];
        }
    }
    return 'Late';
}

// Admin-only actions
$adminActions = [
    'add_user', 'edit_user', 'delete_user',
    'add_location', 'edit_location', 'delete_location',
    'revoke_token', 'add_folder', 'edit_folder',
    'delete_folder', 'update_users_folder',
    'set_max_tokens'
];
$action = $_GET['action'] ?? '';

if (in_array($action, $adminActions) && !isAdminAuthenticated()) {
    sendResponse('error', 'Unauthorized: Admin access required');
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'login':
            $id = $_GET['id'] ?? '';
            if (empty($id)) sendResponse('error', 'ID is required');
            if (countActiveTokensForUser($data, $id) >= $maxTokensPerUser) {
                sendResponse('error', 'You do not have access! Please contact the developer.');
            }
            $user = array_values(array_filter($data['users'], fn($u) => $u['id'] === $id));
            if (!empty($user)) {
                $user = $user[0];
                $token = bin2hex(random_bytes(16));
                $data['activeUsers'][$token] = [
                    'id' => $id,
                    'username' => $user['username'],
                    'loginTime' => date('Y-m-d H:i:s')
                ];
                saveData($data);
                $userResponse = [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'department' => $user['department'] ?? '',
                    'position' => $user['position'] ?? '',
                    'branch' => $user['branch'] ?? '',
                    'workplace' => $user['workplace'] ?? 'N/A',
                    'folder' => $user['folder'] ?? '',
                    'timeSettings' => $user['timeSettings'] ?? []
                ];
                sendResponse('success', 'Login successful', ['token' => $token, 'user' => $userResponse]);
            }
            sendResponse('error', 'Invalid ID');

        case 'check_token':
            $token = $_GET['token'] ?? '';
            if (isset($data['activeUsers'][$token])) {
                sendResponse('success', 'Token is valid');
            }
            sendResponse('error', 'Token revoked');

        case 'get_last_scan':
            global $pdo;
            $id = $_GET['id'] ?? '';
            if (empty($id)) sendResponse('error', 'ID is required');
            $stmt = $pdo->prepare("SELECT username, user_id AS id, action, department, position, branch, workplace, folder, latitude, longitude, timestamp, scanStatus, address, token, early_reason, location_name FROM scans WHERE user_id = :id ORDER BY timestamp DESC LIMIT 1");
            $stmt->execute(['id' => $id]);
            $lastScan = $stmt->fetch();
            if (!$lastScan) sendResponse('success', 'No scans found', ['data' => null]);
            $lastScan['timestamp'] = (new DateTime($lastScan['timestamp']))->format('d/m/Y h:i:s A');
            sendResponse('success', 'Last scan retrieved', [
                'data' => ['scan_type' => $lastScan['action'], 'timestamp' => $lastScan['timestamp']]
            ]);

        case 'get_data':
            sendResponse('success', 'Data retrieved', ['data' => $data]);

        default:
            sendResponse('success', 'Data retrieved', ['data' => $data]);
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // For admin actions expecting JSON input
    if (in_array($action, $adminActions)) {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            error_log("Invalid JSON input: " . json_last_error_msg());
            sendResponse('error', 'Invalid JSON input');
        }

        // Ensure UTF-8 encoding for input
        array_walk_recursive($input, function (&$value) {
            if (is_string($value)) {
                $value = mb_convert_encoding($value, 'UTF-8', 'auto');
            }
        });

        switch ($action) {
            case 'set_max_tokens':
                $maxTokens = intval($input['maxTokensPerUser'] ?? 0);
                if ($maxTokens < 1) {
                    sendResponse('error', 'Invalid max tokens value');
                }
                $data['settings']['maxTokensPerUser'] = $maxTokens;
                saveData($data);
                sendResponse('success', 'Max tokens per user updated successfully');
                break;

            case 'add_folder':
                error_log("Received input for add_folder: " . print_r($input, true));
                $requiredFields = ['id', 'name'];
                if (array_diff($requiredFields, array_keys($input))) {
                    sendResponse('error', 'Missing required fields (id, name)');
                }
                $folder = [
                    'id' => $input['id'],
                    'name' => $input['name']
                ];
                if (array_filter($data['folders'], fn($f) => $f['name'] === $folder['name'])) {
                    sendResponse('error', 'Folder name already exists');
                }
                $data['folders'][] = $folder;
                saveData($data);
                sendResponse('success', 'Folder added successfully');

            case 'edit_folder':
                error_log("Received input for edit_folder: " . print_r($input, true));
                $requiredFields = ['index', 'id', 'name'];
                if (array_diff($requiredFields, array_keys($input))) {
                    sendResponse('error', 'Missing required fields (index, id, name)');
                }
                $index = (int)$input['index'];
                if (!isset($data['folders'][$index])) {
                    sendResponse('error', 'Invalid folder index');
                }
                $newFolderName = $input['name'];
                $oldFolderName = $data['folders'][$index]['name'];

                if (array_filter($data['folders'], fn($f, $i) => $f['name'] === $newFolderName && $i != $index, ARRAY_FILTER_USE_BOTH)) {
                    sendResponse('error', 'Folder name already exists');
                }

                $data['folders'][$index] = [
                    'id' => $input['id'],
                    'name' => $newFolderName
                ];
                foreach ($data['users'] as &$user) {
                    if ($user['folder'] === $oldFolderName) {
                        $user['folder'] = $newFolderName;
                    }
                }
                unset($user);
                saveData($data);
                sendResponse('success', 'Folder updated successfully');

            case 'delete_folder':
                error_log("Received input for delete_folder: " . print_r($input, true));
                $index = (int)($input['index'] ?? -1);
                if ($index < 0 || !isset($data['folders'][$index])) {
                    sendResponse('error', 'Invalid folder index');
                }
                $folderName = $data['folders'][$index]['name'];
                foreach ($data['users'] as &$user) {
                    if ($user['folder'] === $folderName) {
                        $user['folder'] = 'N/A';
                    }
                }
                unset($user);
                array_splice($data['folders'], $index, 1);
                saveData($data);
                sendResponse('success', 'Folder deleted successfully');

            case 'update_users_folder':
                error_log("Received input for update_users_folder: " . print_r($input, true));
                if (!isset($input['users']) || !is_array($input['users'])) {
                    sendResponse('error', 'Invalid users data');
                }
                $data['users'] = $input['users'];
                saveData($data);
                sendResponse('success', 'Users folder updated successfully');

            case 'add_user':
            case 'edit_user':
                $requiredFields = ['username', 'id', 'department', 'position', 'branch', 'workplace', 'folder', 'timeSettings'];
                if (array_diff($requiredFields, array_keys($input))) {
                    sendResponse('error', 'Missing required fields');
                }
                if (!isset($input['timeSettings']['check_in_ranges']) || !isset($input['timeSettings']['check_out_ranges']) ||
                    !validateTimeRanges($input['timeSettings']['check_in_ranges']) ||
                    !validateTimeRanges($input['timeSettings']['check_out_ranges'])) {
                    sendResponse('error', 'Invalid time ranges');
                }
                if (!array_filter($data['folders'], fn($f) => $f['name'] === $input['folder']) && $input['folder'] !== 'N/A') {
                    sendResponse('error', 'Invalid folder name');
                }
                if ($action === 'add_user') {
                    if (array_filter($data['users'], fn($u) => $u['id'] === $input['id'])) {
                        sendResponse('error', 'ID already exists');
                    }
                    $data['users'][] = $input;
                } else {
                    $index = (int)($input['index'] ?? -1);
                    if ($index < 0 || !isset($data['users'][$index])) {
                        sendResponse('error', 'Invalid user index');
                    }
                    if (array_filter($data['users'], fn($u, $i) => $u['id'] === $input['id'] && $i != $index, ARRAY_FILTER_USE_BOTH)) {
                        sendResponse('error', 'ID already exists');
                    }
                    $data['users'][$index] = $input;
                }
                saveData($data);
                sendResponse('success', 'User saved successfully');

            case 'delete_user':
                $index = (int)($input['index'] ?? -1);
                if ($index < 0 || !isset($data['users'][$index])) {
                    sendResponse('error', 'Invalid user index');
                }
                array_splice($data['users'], $index, 1);
                saveData($data);
                sendResponse('success', 'User deleted successfully');

            case 'add_location':
                $requiredFields = ['name', 'branch', 'latitude', 'longitude', 'users'];
                if (array_diff($requiredFields, array_keys($input))) {
                    sendResponse('error', 'Missing required fields');
                }
                $location = [
                    'id' => generateUniqueId(),
                    'name' => $input['name'],
                    'branch' => $input['branch'],
                    'latitude' => floatval($input['latitude']),
                    'longitude' => floatval($input['longitude']),
                    'users' => $input['users']
                ];

                if (!is_array($location['users']) || empty($location['users'])) {
                    sendResponse('error', 'At least one user must be specified');
                }
                foreach ($location['users'] as $user) {
                    if (!isset($user['user_id']) || !isset($user['tolerance'])) {
                        sendResponse('error', 'Each user must have user_id and tolerance');
                    }
                    if (!array_filter($data['users'], fn($u) => $u['id'] === $user['user_id'])) {
                        sendResponse('error', "Invalid user ID: {$user['user_id']}");
                    }
                    if (!is_numeric($user['tolerance']) || floatval($user['tolerance']) <= 0) {
                        sendResponse('error', "Tolerance for user {$user['user_id']} must be a positive number");
                    }
                }

                if (array_filter($data['allowedLocations'], fn($loc) => $loc['name'] === $location['name'])) {
                    sendResponse('error', 'Location name already exists');
                }

                // Generate QR code
                try {
                    $options = new QROptions([
                        'version'    => 5,
                        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                        'scale'      => 5,
                    ]);
                    $qrCode = new QRCode($options);
                    $geoUri = "geo:{$location['latitude']},{$location['longitude']}";
                    $qrImage = $qrCode->render($geoUri);
                    $location['qr_code'] = base64_encode($qrImage);
                } catch (Exception $e) {
                    error_log("QR code generation failed: " . $e->getMessage());
                    sendResponse('error', 'Failed to generate QR code');
                }

                $data['allowedLocations'][] = $location;
                saveData($data);
                sendResponse('success', 'Location added successfully with QR code');

            case 'edit_location':
                $requiredFields = ['index', 'name', 'branch', 'latitude', 'longitude', 'users'];
                if (array_diff($requiredFields, array_keys($input))) {
                    sendResponse('error', 'Missing required fields');
                }
                $index = (int)$input['index'];
                if (!isset($data['allowedLocations'][$index])) {
                    sendResponse('error', 'Invalid location index');
                }
                $location = [
                    'id' => $data['allowedLocations'][$index]['id'] ?? generateUniqueId(),
                    'name' => $input['name'],
                    'branch' => $input['branch'],
                    'latitude' => floatval($input['latitude']),
                    'longitude' => floatval($input['longitude']),
                    'users' => $input['users']
                ];

                if (!is_array($location['users']) || empty($location['users'])) {
                    sendResponse('error', 'At least one user must be specified');
                }
                foreach ($location['users'] as $user) {
                    if (!isset($user['user_id']) || !isset($user['tolerance'])) {
                        sendResponse('error', 'Each user must have user_id and tolerance');
                    }
                    if (!array_filter($data['users'], fn($u) => $u['id'] === $user['user_id'])) {
                        sendResponse('error', "Invalid user ID: {$user['user_id']}");
                    }
                    if (!is_numeric($user['tolerance']) || floatval($user['tolerance']) <= 0) {
                        sendResponse('error', "Tolerance for user {$user['user_id']} must be a positive number");
                    }
                }

                if (array_filter($data['allowedLocations'], fn($loc, $i) => $loc['name'] === $location['name'] && $i != $index, ARRAY_FILTER_USE_BOTH)) {
                    sendResponse('error', 'Location name already exists');
                }

                // Generate QR code
                try {
                    $options = new QROptions([
                        'version'    => 5,
                        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                        'scale'      => 5,
                    ]);
                    $qrCode = new QRCode($options);
                    $geoUri = "geo:{$location['latitude']},{$location['longitude']}";
                    $qrImage = $qrCode->render($geoUri);
                    $location['qr_code'] = base64_encode($qrImage);
                } catch (Exception $e) {
                    error_log("QR code generation failed: " . $e->getMessage());
                    sendResponse('error', 'Failed to generate QR code');
                }

                $data['allowedLocations'][$index] = $location;
                saveData($data);
                sendResponse('success', 'Location updated successfully with QR code');

            case 'delete_location':
                $index = (int)($input['index'] ?? -1);
                if ($index < 0 || !isset($data['allowedLocations'][$index])) {
                    sendResponse('error', 'Invalid location index');
                }
                array_splice($data['allowedLocations'], $index, 1);
                saveData($data);
                sendResponse('success', 'Location deleted successfully');

            case 'revoke_token':
                $tokenToRevoke = $input['token'] ?? '';
                if (empty($tokenToRevoke) || !isset($data['activeUsers'][$tokenToRevoke])) {
                    sendResponse('error', 'Invalid token');
                }
                unset($data['activeUsers'][$tokenToRevoke]);
                saveData($data);
                sendResponse('success', 'Token revoked successfully');
        }
    } else {
        // Handle non-admin POST requests (like scan and send_telegram)
        switch ($action) {
            case 'logout':
                $input = json_decode(file_get_contents('php://input'), true);
                if ($input === null) {
                    error_log("Invalid JSON input: " . json_last_error_msg());
                    sendResponse('error', 'Invalid JSON input');
                }
                $token = $input['token'] ?? '';
                if (empty($token) || !isset($data['activeUsers'][$token])) {
                    sendResponse('error', 'Invalid token');
                }
                unset($data['activeUsers'][$token]);
                saveData($data);
                sendResponse('success', 'Logout successful');

            case 'send_telegram':
                $input = json_decode(file_get_contents('php://input'), true);
                if ($input === null) {
                    error_log("Invalid JSON input: " . json_last_error_msg());
                    sendResponse('error', 'Invalid JSON input');
                }
                $message = $input['message'] ?? '';
                $department = strtolower($input['department'] ?? 'specialist');
                $token = $input['token'] ?? '';
                $parse_mode = $input['parse_mode'] ?? 'Markdown';
                if (empty($message)) {
                    sendResponse('error', 'Message is required');
                }
                if (!isset($data['activeUsers'][$token])) {
                    sendResponse('error', 'Invalid token');
                }

                $targetGroup = ($department === 'worker') ? $telegramGroups['worker'] : $telegramGroups['specialist'];
                $result = sendTelegramMessage($targetGroup['botToken'], $targetGroup['chatId'], $message, $parse_mode);

                if (!$result['success']) {
                    sendResponse('error', 'Failed to send message to Telegram', ['telegram_error' => $result['error']]);
                }

                sendResponse('success', 'Message sent to Telegram successfully');
                break;

            default:
                // Handle scan request using FormData
                $token = $_POST['token'] ?? '';
                if (!isset($data['activeUsers'][$token])) sendResponse('error', 'Invalid token');
                $user = array_values(array_filter($data['users'], fn($u) => $u['id'] === ($_POST['ID'] ?? '')))[0] ?? null;
                if (!$user) sendResponse('error', 'User not found');

                // Ensure UTF-8 encoding for POST data
                $postData = [];
                foreach ($_POST as $key => $value) {
                    $postData[$key] = mb_convert_encoding($value, 'UTF-8', 'auto');
                }

                $scanTime = DateTime::createFromFormat('d/m/Y h:i:s A', ($postData['ថ្ងៃ'] ?? '') . ' ' . ($postData['ម៉ោង'] ?? date('d/m/Y h:i:s A')));
                if ($scanTime === false) sendResponse('error', 'Invalid timestamp');

                $locationParts = explode('Lat: ', $postData['location'] ?? '');
                $latitude = floatval(explode('Long: ', $locationParts[1] ?? '')[0] ?? 0);
                $longitude = floatval(explode('Long: ', $postData['location'])[1] ?? 0);
                $isValidLocation = false;
                $matchedLocation = null;
                $userId = $user['id'];

                foreach ($data['allowedLocations'] as $loc) {
                    $allowedUser = array_filter($loc['users'] ?? [], fn($u) => $u['user_id'] === $userId);
                    if (!empty($allowedUser)) {
                        $tolerance = floatval($allowedUser[array_key_first($allowedUser)]['tolerance']);
                        if (abs($latitude - $loc['latitude']) <= $tolerance &&
                            abs($longitude - $loc['longitude']) <= $tolerance) {
                            $isValidLocation = true;
                            $matchedLocation = $loc;
                            break;
                        }
                    }
                }
                if (!$isValidLocation && !empty($data['allowedLocations'])) {
                    sendResponse('error', 'User is not allowed to scan at this location');
                }

                $actionType = $postData['ប្រភេទស្កេន'] ?? '';
                $scanStatus = $postData['status'] ?? '';
                $earlyReason = $postData['early_reason'] ?? '';
                if ($actionType === 'Check-In') {
                    $ranges = $user['timeSettings']['check_in_ranges'] ?? [];
                    if (empty($ranges)) sendResponse('error', 'No check-in ranges configured');
                    $scanStatus = $scanStatus ?: calculateScanStatus($scanTime, $ranges);
                } else {
                    $ranges = $user['timeSettings']['check_out_ranges'] ?? [];
                    if (empty($ranges)) sendResponse('error', 'No check-out ranges configured');
                    $scanStatus = $scanStatus ?: calculateScanStatus($scanTime, $ranges, !empty($earlyReason));
                }

                $scanData = [
                    'username' => $postData['ឈ្មោះ'] ?? '',
                    'id' => $postData['ID'] ?? '',
                    'action' => $actionType,
                    'department' => $postData['Department'] ?? '',
                    'position' => $postData['Position'] ?? '',
                    'branch' => $postData['សាខា'] ?? '',
                    'workplace' => $postData['workplace'] ?? $user['workplace'] ?? 'N/A',
                    'folder' => $postData['folder'] ?? $user['folder'] ?? '',
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'timestamp' => $scanTime->format('d/m/Y h:i:s A'),
                    'scanStatus' => $scanStatus,
                    'address' => $postData['address'] ?? 'N/A',
                    'token' => $token,
                    'early_reason' => $earlyReason ?: null,
                    'location_name' => $matchedLocation['name'] ?? 'Unknown'
                ];
                $data['scans'][] = $scanData;
                $jsonSaveSuccess = saveData($data);

                // Prepare Telegram message
                $locationName = $matchedLocation['name'] ?? 'Unknown';
                $telegramMessage = "ឈ្មោះ: {$scanData['username']}\nប្រភេទស្កេន: {$actionType} (Status: {$scanStatus})\nID: {$scanData['id']}\nDepartment: {$scanData['department']}\nPosition: {$scanData['position']}\nBranch: {$scanData['branch']}\nWorkplace: {$scanData['workplace']}\nថ្ងៃ/ម៉ោង: {$scanData['timestamp']}\nទីតាំង: {$locationName} ({$scanData['address']})\nMap: https://www.google.com/maps?q={$latitude},{$longitude}";
                if ($earlyReason) $telegramMessage .= "\nមូលហេតុចេញមុន: $earlyReason";

                $department = strtolower($scanData['department'] ?? 'specialist');
                $targetGroup = ($department === 'worker') ? $telegramGroups['worker'] : $telegramGroups['specialist'];
                $telegramResult = sendTelegramMessage($targetGroup['botToken'], $targetGroup['chatId'], $telegramMessage);

                $response = [
                    'status' => 'success',
                    'message' => 'Scan recorded successfully',
                    'scanStatus' => $scanStatus,
                    'location_name' => $locationName
                ];
                if (!$jsonSaveSuccess) $response['json_error'] = 'Failed to save to database';
                if (!$telegramResult['success']) $response['telegram_error'] = 'Failed to send to Telegram: ' . $telegramResult['error'];
                sendResponse($response['status'], $response['message'], array_diff_key($response, ['status' => '', 'message' => '']));
        }
    }
}

$unexpectedOutput = ob_get_clean();
if ($unexpectedOutput) {
    error_log("Unexpected output: $unexpectedOutput");
    sendResponse('error', 'Server error: ' . htmlentities($unexpectedOutput));
}
sendResponse('error', 'Invalid request');
?>