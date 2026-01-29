<?php
session_start();
date_default_timezone_set('Asia/Phnom_Penh');

ob_start();
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 30);
error_reporting(E_ALL);

// File paths
$dataFile = 'data.json';
$backupFile = 'data_backup_' . date('Ymd_His') . '.json';

// Include QR code library
require 'vendor/autoload.php';
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// Telegram Groups Configuration
$telegramGroups = [
    'specialist' => [
        'botToken' => '8343607996:AAGe56LsCQvREpWe5qKFH_ti8owd16HBnzk',
        'chatId' => '-1002282814819'
    ],
    'worker' => [
        'botToken' => '8116100212:AAEn8nUrU7QqqkSWFaptH6gaRmiMWzNullo',
        'chatId' => '-1002330039942'
    ]
];

// Shutdown handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal error: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        ob_end_clean();
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'Server error occurred'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
});

// Function to send message to Telegram with retry
function sendTelegramMessage($botToken, $chatId, $message, $parseMode = 'Markdown', $maxRetries = 3) {
    $telegramUrl = "https://api.telegram.org/bot$botToken/sendMessage";
    $telegramData = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => $parseMode
    ];

    $attempt = 0;
    while ($attempt < $maxRetries) {
        $ch = curl_init($telegramUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($telegramData));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("Telegram API error (Attempt " . ($attempt + 1) . "): $curlError");
            $attempt++;
            sleep(2 ** $attempt);
            continue;
        }

        $result = json_decode($response, true);
        if (!$result['ok']) {
            error_log("Telegram API response error (Attempt " . ($attempt + 1) . "): " . $result['description']);
            $attempt++;
            sleep(2 ** $attempt);
            continue;
        }

        return ['success' => true];
    }

    error_log("Failed to send Telegram message after $maxRetries attempts");
    return ['success' => false, 'error' => $curlError ?: $result['description']];
}

// Load data with locking and retry
function loadData($file, $maxRetries = 3) {
    // *** NEW CHANGE: Added 'branches' to the default data structure ***
    $defaultData = [
        'folders' => [],
        'users' => [],
        'allowedLocations' => [],
        'branches' => [], // <-- KEY ថ្មីសម្រាប់រក្សាទុក "ទីតាំងស្កេន"
        'scans' => [],
        'activeUsers' => [],
        'settings' => ['maxTokensPerUser' => 1]
    ];

    if (!file_exists($file)) {
        error_log("Creating new data file: $file");
        file_put_contents($file, json_encode($defaultData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaultData;
    }

    $attempt = 0;
    while ($attempt < $maxRetries) {
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            error_log("Cannot open data file for reading: $file (Attempt " . ($attempt + 1) . ")");
            $attempt++;
            sleep(1);
            continue;
        }

        if (flock($fp, LOCK_SH, $wouldBlock) && !$wouldBlock) {
            $content = file_get_contents($file);
            if ($content === false) {
                flock($fp, LOCK_UN);
                fclose($fp);
                error_log("Cannot read data file: $file");
                sendResponse('error', 'Cannot read data file');
            }

            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Invalid JSON data in file: $file - " . json_last_error_msg());
                flock($fp, LOCK_UN);
                fclose($fp);
                // Attempt to restore from backup
                if (file_exists('data_backup.json')) {
                    error_log("Attempting to restore from backup: data_backup.json");
                    $content = file_get_contents('data_backup.json');
                    $data = json_decode($content, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        return $data;
                    }
                }
                sendResponse('error', 'Invalid JSON data in file');
            }

            flock($fp, LOCK_UN);
            fclose($fp);
            // Ensure all required keys exist
            // *** NEW CHANGE: Added 'branches' to the list of keys to check ***
            foreach (['folders', 'users', 'allowedLocations', 'branches', 'scans', 'activeUsers', 'settings'] as $key) {
                if (!isset($data[$key])) {
                    $data[$key] = $defaultData[$key];
                }
            }
            if (!isset($data['settings']['maxTokensPerUser'])) {
                $data['settings']['maxTokensPerUser'] = 1;
            }
            return $data;
        }

        fclose($fp);
        error_log("Cannot lock file for reading: $file (Attempt " . ($attempt + 1) . ")");
        $attempt++;
        sleep(1);
    }

    error_log("Failed to lock file after $maxRetries attempts: $file");
    sendResponse('error', 'Cannot lock file for reading after retries');
}

// Save data with locking and retry
function saveData($data, $file, $backupFile, $maxRetries = 3) {
    $attempt = 0;
    while ($attempt < $maxRetries) {
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            error_log("Cannot open data file: $file (Attempt " . ($attempt + 1) . ")");
            $attempt++;
            sleep(1);
            continue;
        }

        if (flock($fp, LOCK_EX, $wouldBlock) && !$wouldBlock) {
            if (file_exists($file)) {
                copy($file, $backupFile);
            }
            $result = file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            flock($fp, LOCK_UN);
            fclose($fp);
            if ($result === false) {
                error_log("Failed to write data to file: $file");
                sendResponse('error', 'Failed to write data to file');
            }
            return true;
        }

        fclose($fp);
        error_log("Cannot lock file for writing: $file (Attempt " . ($attempt + 1) . ")");
        $attempt++;
        sleep(1);
    }

    error_log("Failed to lock file for writing after $maxRetries attempts: $file");
    sendResponse('error', 'Cannot lock file for writing after retries');
}

// Response function
function sendResponse($status, $message = '', $data = []) {
    $response = array_merge(['status' => $status, 'message' => $message], $data);
    ob_end_clean();
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Generate unique ID for locations
function generateUniqueId() {
    return 'loc_' . bin2hex(random_bytes(4));
}

$data = loadData($dataFile);
$maxTokensPerUser = $data['settings']['maxTokensPerUser'] ?? 1;

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
// *** NEW CHANGE: Added branch actions to the list ***
$adminActions = [
    'add_user', 'edit_user', 'delete_user',
    'add_location', 'edit_location', 'delete_location',
    'revoke_token', 'add_folder', 'edit_folder',
    'delete_folder', 'update_users_folder',
    'set_max_tokens', 'add_branch', 'edit_branch', 'delete_branch' // <-- ACTIONS ថ្មី
];
$action = $_GET['action'] ?? '';

if (in_array($action, $adminActions) && !isAdminAuthenticated()) {
    sendResponse('error', 'Unauthorized: Admin access required');
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        // ... (GET cases remain the same) ...
        case 'get_server_time':
            try {
                $serverTime = (new DateTime())->format(DateTime::ISO8601);
                sendResponse('success', 'Server time retrieved', ['server_time' => $serverTime]);
            } catch (Exception $e) {
                error_log("Failed to get server time: " . $e->getMessage());
                sendResponse('error', 'Failed to retrieve server time');
            }
            break;

        case 'get_data':
            try {
                $data = loadData($dataFile);
                sendResponse('success', 'Data retrieved', ['data' => $data]);
            } catch (Exception $e) {
                error_log("Failed to fetch data: " . $e->getMessage());
                sendResponse('error', 'Failed to fetch data: ' . $e->getMessage());
            }
            break;

        case 'login':
            $id = $_GET['id'] ?? '';
            if (empty($id)) sendResponse('error', 'ID is required');
            if (countActiveTokensForUser($data, $id) >= $maxTokensPerUser) {
                sendResponse('error', 'You do not have access! Please contact the developer.');
            }
            $userIndex = array_search($id, array_column($data['users'], 'id'));
            if ($userIndex !== false) {
                $user = $data['users'][$userIndex];
                $token = bin2hex(random_bytes(16));
                $data['activeUsers'][$token] = [
                    'id' => $id,
                    'username' => $user['username'],
                    'loginTime' => date('Y-m-d H:i:s')
                ];
                saveData($data, $dataFile, $backupFile);
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
            $id = $_GET['id'] ?? '';
            if (empty($id)) sendResponse('error', 'ID is required');
            $userScans = [];
            for ($i = count($data['scans']) - 1; $i >= 0; $i--) {
                if ($data['scans'][$i]['id'] === $id) {
                    $userScans[] = $data['scans'][$i];
                    break;
                }
            }
            if (empty($userScans)) sendResponse('success', 'No scans found', ['data' => null]);
            $lastScan = $userScans[0];
            sendResponse('success', 'Last scan retrieved', [
                'data' => ['scan_type' => $lastScan['action'], 'timestamp' => $lastScan['timestamp']]
            ]);

        default:
            try {
                $data = loadData($dataFile);
                sendResponse('success', 'Data retrieved', ['data' => $data]);
            } catch (Exception $e) {
                error_log("Failed to fetch data: " . $e->getMessage());
                sendResponse('error', 'Failed to fetch data: ' . $e->getMessage());
            }
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (in_array($action, $adminActions)) {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            error_log("Invalid JSON input: " . json_last_error_msg());
            sendResponse('error', 'Invalid JSON input');
        }

        switch ($action) {
            // ... (Existing admin cases) ...

            // *** NEW: Branch Management Logic ***
            case 'add_branch':
                if (empty($input['name'])) {
                    sendResponse('error', 'Branch name is required.');
                }
                // Check for duplicates
                if (array_filter($data['branches'], fn($b) => $b['name'] === $input['name'])) {
                    sendResponse('error', 'Branch name already exists.');
                }
                $data['branches'][] = ['id' => $input['id'], 'name' => $input['name']];
                saveData($data, $dataFile, $backupFile);
                sendResponse('success', 'Branch added successfully');
                break;

            case 'edit_branch':
                if (!isset($input['index']) || empty($input['name'])) {
                    sendResponse('error', 'Invalid data provided.');
                }
                $index = (int)$input['index'];
                if (!isset($data['branches'][$index])) {
                    sendResponse('error', 'Invalid branch index.');
                }

                $newBranchName = $input['name'];
                $oldBranchName = $input['oldName'] ?? $data['branches'][$index]['name'];

                // Check for duplicates (excluding the current one)
                if (array_filter($data['branches'], fn($b, $i) => $b['name'] === $newBranchName && $i != $index, ARRAY_FILTER_USE_BOTH)) {
                    sendResponse('error', 'Branch name already exists.');
                }

                // Update the branch name itself
                $data['branches'][$index]['name'] = $newBranchName;

                // Cascade update to users
                foreach ($data['users'] as &$user) {
                    if (isset($user['branch']) && $user['branch'] === $oldBranchName) {
                        $user['branch'] = $newBranchName;
                    }
                }
                unset($user); // Unset the reference

                // Cascade update to allowed locations
                foreach ($data['allowedLocations'] as &$location) {
                    if (isset($location['branch']) && $location['branch'] === $oldBranchName) {
                        $location['branch'] = $newBranchName;
                    }
                }
                unset($location); // Unset the reference

                saveData($data, $dataFile, $backupFile);
                sendResponse('success', 'Branch updated successfully');
                break;

            case 'delete_branch':
                if (!isset($input['index'])) {
                    sendResponse('error', 'Invalid index provided.');
                }
                $index = (int)$input['index'];
                if (!isset($data['branches'][$index])) {
                    sendResponse('error', 'Invalid branch index.');
                }
                array_splice($data['branches'], $index, 1);
                saveData($data, $dataFile, $backupFile);
                sendResponse('success', 'Branch deleted successfully');
                break;

            case 'set_max_tokens':
                $maxTokens = intval($input['maxTokensPerUser'] ?? 0);
                if ($maxTokens < 1) {
                    sendResponse('error', 'Invalid max tokens value');
                }
                $data['settings']['maxTokensPerUser'] = $maxTokens;
                saveData($data, $dataFile, $backupFile);
                sendResponse('success', 'Max tokens per user updated successfully');
                break;
            
            // ... (All other existing cases from your original file)
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
                saveData($data, $dataFile, $backupFile);
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
                saveData($data, $dataFile, $backupFile);
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
                saveData($data, $dataFile, $backupFile);
                sendResponse('success', 'Folder deleted successfully');

            case 'update_users_folder':
                error_log("Received input for update_users_folder: " . print_r($input, true));
                if (!isset($input['users']) || !is_array($input['users'])) {
                    sendResponse('error', 'Invalid users data');
                }
                $data['users'] = $input['users'];
                saveData($data, $dataFile, $backupFile);
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
                saveData($data, $dataFile, $backupFile);
                sendResponse('success', 'User saved successfully');

            case 'delete_user':
                $index = (int)($input['index'] ?? -1);
                if ($index < 0 || !isset($data['users'][$index])) {
                    sendResponse('error', 'Invalid user index');
                }
                array_splice($data['users'], $index, 1);
                saveData($data, $dataFile, $backupFile);
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

                try {
                    $options = new QROptions([
                        'version' => 5,
                        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                        'scale' => 5,
                    ]);
                    $qrCode = new QRCode($options);
                    $geoUri = "geo:{$location['latitude']},{$location['longitude']}";
                    $qrImage = $qrCode->render($geoUri);
                    $location['qr_code'] = base64_encode($qrImage);
                } catch (Exception $e) {
                    error_log("QR code generation failed: " . $e->getMessage());
                    sendResponse('error', 'Failed to generate QR code: ' . $e->getMessage());
                }

                $data['allowedLocations'][] = $location;
                saveData($data, $dataFile, $backupFile);
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

                try {
                    $options = new QROptions([
                        'version' => 5,
                        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
                        'scale' => 5,
                    ]);
                    $qrCode = new QRCode($options);
                    $geoUri = "geo:{$location['latitude']},{$location['longitude']}";
                    $qrImage = $qrCode->render($geoUri);
                    $location['qr_code'] = base64_encode($qrImage);
                } catch (Exception $e) {
                    error_log("QR code generation failed: " . $e->getMessage());
                    sendResponse('error', 'Failed to generate QR code: ' . $e->getMessage());
                }

                $data['allowedLocations'][$index] = $location;
                saveData($data, $dataFile, $backupFile);
                sendResponse('success', 'Location updated successfully with QR code');

            case 'delete_location':
                $index = (int)($input['index'] ?? -1);
                if ($index < 0 || !isset($data['allowedLocations'][$index])) {
                    sendResponse('error', 'Invalid location index');
                }
                array_splice($data['allowedLocations'], $index, 1);
                saveData($data, $dataFile, $backupFile);
                sendResponse('success', 'Location deleted successfully');

            case 'revoke_token':
                $tokenToRevoke = $input['token'] ?? '';
                if (empty($tokenToRevoke) || !isset($data['activeUsers'][$tokenToRevoke])) {
                    sendResponse('error', 'Invalid token');
                }
                unset($data['activeUsers'][$tokenToRevoke]);
                saveData($data, $dataFile, $backupFile);
                sendResponse('success', 'Token revoked successfully');
        }
    } else {
        // ... (Public-facing POST actions remain the same) ...
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
                saveData($data, $dataFile, $backupFile);
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
                $token = $_POST['token'] ?? '';
                if (!isset($data['activeUsers'][$token])) sendResponse('error', 'Invalid token');
                $user = array_values(array_filter($data['users'], fn($u) => $u['id'] === ($_POST['ID'] ?? '')))[0] ?? null;
                if (!$user) sendResponse('error', 'User not found');

                $scanTime = DateTime::createFromFormat('d/m/Y h:i:s A', ($_POST['ថ្ងៃ'] ?? '') . ' ' . ($_POST['ម៉ោង'] ?? date('d/m/Y h:i:s A')));
                if ($scanTime === false) sendResponse('error', 'Invalid timestamp');

                $locationParts = explode('Lat: ', $_POST['location'] ?? '');
                $latitude = floatval(explode('Long: ', $locationParts[1] ?? '')[0] ?? 0);
                $longitude = floatval(explode('Long: ', $_POST['location'])[1] ?? 0);
                $isValidLocation = false;
                $matchedLocation = null;
                $userId = $user['id'];

                foreach ($data['allowedLocations'] as $loc) {
                    $allowedUser = array_filter($loc['users'] ?? [], fn($u) => $u['user_id'] === $userId);
                    if (!empty($allowedUser)) {
                        $tolerance = floatval($allowedUser[array_key_first($allowedUser)]['tolerance']);
                        if ($tolerance > 100 / 111320) { // Convert 100 meters to degrees
                            sendResponse('error', "Scanning not allowed: Tolerance for user {$userId} at location {$loc['name']} exceeds 100 meters");
                        }
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

                $actionType = $_POST['ប្រភេទស្កេន'] ?? '';
                $scanStatus = $_POST['status'] ?? '';
                $earlyReason = $_POST['early_reason'] ?? '';
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
                    'username' => $_POST['ឈ្មោះ'] ?? '',
                    'id' => $_POST['ID'] ?? '',
                    'action' => $actionType,
                    'department' => $_POST['Department'] ?? '',
                    'position' => $_POST['Position'] ?? '',
                    'branch' => $_POST['សាខា'] ?? '',
                    'workplace' => $_POST['workplace'] ?? $user['workplace'] ?? 'N/A',
                    'folder' => $_POST['folder'] ?? $user['folder'] ?? '',
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'timestamp' => $scanTime->format('d/m/Y h:i:s A'),
                    'scanStatus' => $scanStatus,
                    'address' => $_POST['address'] ?? 'N/A',
                    'token' => $token,
                    'early_reason' => $earlyReason ?: null,
                    'location_name' => $matchedLocation['name'] ?? 'Unknown'
                ];
                $data['scans'][] = $scanData;
                $jsonSaveSuccess = saveData($data, $dataFile, $backupFile);

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
                if (!$jsonSaveSuccess) $response['json_error'] = 'Failed to save to JSON';
                if (!$telegramResult['success']) $response['telegram_error'] = 'Failed to send to Telegram: ' . $telegramResult['error'];
                sendResponse($response['status'], $response['message'], array_diff_key($response, ['status' => '', 'message' => '']));
        }
    }
}

sendResponse('error', 'Invalid request');
?>