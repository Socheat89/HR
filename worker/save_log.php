<?php
// Set headers for JSON response and CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
$dbHost = 'localhost';
$dbUser = 'samann1_scan_logs_worker_db';
$dbPass = 'scan_logs_worker_db@2025';
$dbName = 'samann1_scan_logs_worker_db';

// Backup file for failed database saves
$backupFile = 'backup_scan_logs.json';

// Function to fetch status from a website based on scan time (if needed)
function fetchStatusFromWebsite($url, $scanTime) {
    try {
        $ch = curl_init();
        $fullUrl = $url . '?time=' . urlencode($scanTime);
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Use with caution
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('Failed to fetch status: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Invalid response from status server: HTTP ' . $httpCode);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['status'])) {
            throw new Exception('Invalid status data format');
        }

        // Add icon to the status based on the value
        $status = $data['status'];
        if ($status === 'Good') {
            return '🔵 Good';
        } elseif ($status === 'Late') {
            return '🔴 Late';
        }
        return $status; // Return as-is if not Good or Late
    } catch (Exception $e) {
        error_log("Status fetch error: " . $e->getMessage());
        return null; // Fallback to client-provided status
    }
}

// Function to save to backup file
function saveToBackupFile($data, $file) {
    $backupData = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    if (!is_array($backupData)) $backupData = [];
    $backupData[] = $data;
    file_put_contents($file, json_encode($backupData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

// Function to convert Khmer AM/PM to standard format
function convertKhmerAmPm($timeStr) {
    // Replace Khmer AM/PM with standard English AM/PM
    $timeStr = str_replace('ព្រឹក', 'AM', $timeStr); // "ព្រឹក" means "AM" in Khmer
    $timeStr = str_replace('ល្ងាច', 'PM', $timeStr); // "ល្ងាច" means "PM" in Khmer
    return $timeStr;
}

// Main logic
try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'status' => 'error',
            'message' => 'Method not allowed. Use POST.',
            'scanStatus' => '🔴 Error'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Handle FormData input from frontend
    $postData = $_POST;

    // Log received data for debugging
    file_put_contents('debug.log', "Received data at " . date('Y-m-d H:i:s') . ": " . print_r($postData, true) . "\n", FILE_APPEND);

    // Get and trim POST data
    $username = trim($postData['ឈ្មោះ'] ?? '');
    $userId = trim($postData['ID'] ?? '');
    $action = trim($postData['ប្រភេទស្កេន'] ?? '');
    $date = trim($postData['ថ្ងៃ'] ?? '');
    $time = trim($postData['ម៉ោង'] ?? '');
    $location = trim($postData['location'] ?? '');
    $status = trim($postData['status'] ?? '🔴 Late'); // Use the status sent from frontend (already includes icon)
    $address = trim($postData['address'] ?? '');
    $branch = trim($postData['សាខា'] ?? '');
    $folder = trim($postData['folder'] ?? '');
    $department = trim($postData['Department'] ?? ''); // Optional field (second version only)
    $position = trim($postData['Position'] ?? ''); // Optional field (second version only)
    $earlyReason = trim($postData['earlyReason'] ?? $postData['early_reason'] ?? ''); // Handle both naming conventions

    // Validation: Check required fields
    $requiredFields = [
        'username' => $username,
        'user_id' => $userId,
        'action' => $action,
        'location' => $location,
        'branch' => $branch,
        'folder' => $folder
    ];
    $missingFields = array_filter($requiredFields, function($value) {
        return empty($value);
    });
    if (!empty($missingFields)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing required fields: ' . implode(', ', array_keys($missingFields)),
            'scanStatus' => '🔴 Error'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Validate and parse location
    if (!preg_match('/Lat:\s*([\d.-]+),\s*Long:\s*([\d.-]+)/i', $location, $matches)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid location format. Expected: "Lat: X, Long: Y"',
            'scanStatus' => '🔴 Error'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $latitude = (float)$matches[1];
    $longitude = (float)$matches[2];

    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid latitude or longitude values',
            'scanStatus' => '🔴 Error'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Parse date and time
    $dateTime = false;

    // First attempt: Try parsing as MM/DD/YYYY HH:mm:ss (second version)
    $dateTime = DateTime::createFromFormat('m/d/Y H:i:s', "$date $time");
    if ($dateTime === false) {
        // Second attempt: Try parsing as DD/MM/YYYY HH:mm:ss AM/PM (first version)
        $time = convertKhmerAmPm($time); // Convert Khmer AM/PM to English AM/PM
        $dateTime = DateTime::createFromFormat('d/m/Y h:i:s A', "$date $time");
    }

    if ($dateTime === false) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid date or time format. Expected: MM/DD/YYYY HH:mm:ss or DD/MM/YYYY HH:mm:ss AM/PM',
            'scanStatus' => '🔴 Error'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $timestamp = $dateTime->format('Y-m-d H:i:s');

    // Calculate scan time in minutes for status fetching (if needed)
    $scanTime = (int)$dateTime->format('H') * 60 + (int)$dateTime->format('i');

    // Fetch status from external website (optional, comment out if not needed)
    /*
    $statusUrl = 'https://example.com/api/get-status'; // Replace with your actual endpoint
    $fetchedStatus = fetchStatusFromWebsite($statusUrl, $scanTime);
    $finalStatus = $fetchedStatus !== null ? $fetchedStatus : $status; // Use fetched status if available, otherwise use the one from frontend
    */
    $finalStatus = $status; // Use the status sent from the frontend

    // Prepare data array for database and backup
    $logData = [
        'username' => $username,
        'user_id' => $userId,
        'action' => $action,
        'timestamp' => $timestamp,
        'latitude' => $latitude,
        'longitude' => $longitude,
        'status' => $finalStatus, // Already includes icon
        'address' => $address,
        'branch' => $branch,
        'folder' => $folder,
        'department' => $department, // Optional
        'position' => $position, // Optional
        'early_reason' => $earlyReason, // Include early_reason
        'received_at' => date('Y-m-d H:i:s')
    ];

    // Connect to the database with retry mechanism
    $maxRetries = 3;
    $retryDelay = 1; // seconds
    $pdo = null;
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("SET NAMES utf8mb4");
            break;
        } catch (PDOException $e) {
            if ($attempt === $maxRetries) {
                saveToBackupFile($logData, $backupFile);
                throw new PDOException("Database connection failed after $maxRetries attempts: " . $e->getMessage());
            }
            sleep($retryDelay);
        }
    }

    // Check for duplicate entry
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM scan_logs WHERE username = :username AND user_id = :user_id AND timestamp = :timestamp AND action = :action");
    $stmt->execute([
        ':username' => $username,
        ':user_id' => $userId,
        ':timestamp' => $timestamp,
        ':action' => $action
    ]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'message' => 'Log already exists',
            'scanStatus' => $finalStatus,
            'early_reason' => $earlyReason,
            'telegramSent' => true // Indicate that Telegram message should not be sent again
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Save to database with transaction
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            INSERT INTO scan_logs (username, user_id, action, timestamp, latitude, longitude, status, address, branch, folder, department, position, early_reason)
            VALUES (:username, :user_id, :action, :timestamp, :latitude, :longitude, :status, :address, :branch, :folder, :department, :position, :early_reason)
        ");
        $stmt->execute([
            ':username' => $username,
            ':user_id' => $userId,
            ':action' => $action,
            ':timestamp' => $timestamp,
            ':latitude' => $latitude,
            ':longitude' => $longitude,
            ':status' => $finalStatus,
            ':address' => $address,
            ':branch' => $branch,
            ':folder' => $folder,
            ':department' => $department ?: null, // Allow NULL if not provided
            ':position' => $position ?: null, // Allow NULL if not provided
            ':early_reason' => $earlyReason ?: null // Allow NULL if not provided
        ]);
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        saveToBackupFile($logData, $backupFile);
        throw $e;
    }

    // Success response
    http_response_code(201);
    echo json_encode([
        'status' => 'success',
        'message' => 'Log saved successfully',
        'scanStatus' => $finalStatus,
        'early_reason' => $earlyReason,
        'telegramSent' => false // Indicate that Telegram message can be sent
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage() . '. Data saved to backup.',
        'scanStatus' => '🔴 Error'
    ], JSON_UNESCAPED_UNICODE);
    file_put_contents('debug.log', "Database error at " . date('Y-m-d H:i:s') . ": " . $e->getMessage() . "\n", FILE_APPEND);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage(),
        'scanStatus' => '🔴 Error'
    ], JSON_UNESCAPED_UNICODE);
    file_put_contents('debug.log', "Server error at " . date('Y-m-d H:i:s') . ": " . $e->getMessage() . "\n", FILE_APPEND);
}

// Close the PDO connection
$pdo = null;
?>