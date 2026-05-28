<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

// Include database configuration
require_once 'config.php';

$dbHost = DB_HOST;
$dbUser = DB_USER;
$dbPass = DB_PASS;
$dbName = DB_NAME;

// --- START: MODIFIED TELEGRAM CONFIGURATION ---
ini_set('memory_limit', '256M');
$telegramBotToken = "7071927561:AAE5wQn80PkmOUqJlJgoXJJN-48Ol-ufSbE";

// Configuration for multiple Telegram groups with different report formats
$telegramGroups = [
    // Group 1: ផ្ញើជា Link
    [
        'chat_id' => "-1001618361706",
        'report_format' => 'link', // 'link' = ផ្ញើតែ Link
        'thread_ids' => [
            'IT SUPPORT' => 48170,
            'IT MANAGER' => 48170,
            'Administration' => 48152,
            'Brand Supervisor' => 48137,
        ]
    ],
    // Group 2: ផ្ញើរបាយការណ៍ពេញ (Full Content)
    [
        'chat_id' => "-1002845612876",
        'report_format' => 'full', // 'full' = ផ្ញើរបាយការណ៍ពេញ
        'thread_ids' => [
            'IT SUPPORT' => 3,
            'IT MANAGER' => 3,
            'Administration' => 9,
            'Brand Supervisor' => 5,
        ]
    ]
];
// --- END: MODIFIED TELEGRAM CONFIGURATION ---


// Passwords for each position
$positionPasswords = [
    'IT SUPPORT' => 'vvc.asia',
    'IT MANAGER' => 'vvc.asia',
    'Administration' => 'vvc.asia',
    'Brand Supervisor' => 'vvc.asia',
];

// Set timezone to Cambodia (UTC+7)
date_default_timezone_set('Asia/Phnom_Penh');

// Enable error logging to file
ini_set('log_errors', 1);
ini_set('error_log', 'debug_errors.log');
error_reporting(E_ALL);

function exception_handler($exception) {
    file_put_contents('debug_log.txt', "Uncaught Exception: " . $exception->getMessage() . "\n" . $exception->getTraceAsString() . "\n", FILE_APPEND);
}
set_exception_handler('exception_handler');

// Decode input JSON data
$rawInput = file_get_contents('php://input');
file_put_contents('debug_log.txt', "Raw Input Length: " . strlen($rawInput) . "\n", FILE_APPEND);
if (strlen($rawInput) < 200) {
    file_put_contents('debug_log.txt', "Raw Input: " . $rawInput . "\n", FILE_APPEND);
} else {
    file_put_contents('debug_log.txt', "Raw Input Snippet: " . substr($rawInput, 0, 200) . "...\n", FILE_APPEND);
}

$data = json_decode($rawInput, true);

// Validate required fields
if (!$data || empty($data['date']) || empty($data['name']) || empty($data['position']) || !isset($data['tasks'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ទិន្នន័យមិនគ្រប់គ្រាន់'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Function to generate unique report link
function generateReportLink($reportId) {
    $baseUrl = "https://app.vvc.asia/report.php?id=";
    return $baseUrl . $reportId . "&token=" . bin2hex(random_bytes(16));
}

// Function to escape Markdown special characters
function escapeMarkdown($text) {
    $specialChars = ['\\', '*', '_', '`', '[', ']', '(', ')', '~', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
    foreach ($specialChars as $char) {
        $text = str_replace($char, '\\' . $char, $text);
    }
    return $text;
}
function sendTelegramMessage($botToken, $payload) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Increased timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fix for local SSL issues
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($ch) ? curl_error($ch) : null;
    curl_close($ch);

    if ($curlError) {
        throw new Exception("កំហុស cURL: $curlError");
    }

    $response = json_decode($result, true);
    if ($httpCode !== 200 || !$response['ok']) {
        $desc = $response['description'] ?? 'មិនមានការពិពណ៌នា';
        throw new Exception("Telegram API ឆ្លើយតបមិនជោគជ័យ: {$desc} (HTTP Code: {$httpCode})");
    }
    return $response;
}

// Function to send photo to Telegram
function sendTelegramPhoto($botToken, $payload) {
    $url = "https://api.telegram.org/bot{$botToken}/sendPhoto";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload); // Use raw payload for multipart/form-data
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increased timeout for images
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fix for local SSL issues
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_errno($ch) ? curl_error($ch) : null;
    curl_close($ch);

    if ($curlError) {
        throw new Exception("កំហុស cURL (Photo): $curlError");
    }

    $response = json_decode($result, true);
    if ($httpCode !== 200 || !$response['ok']) {
        $desc = $response['description'] ?? 'មិនមានការពិពណ៌នា';
        throw new Exception("Telegram Photo API ឆ្លើយតបមិនជោគជ័យ: {$desc} (HTTP Code: {$httpCode})");
    }
    return $response;
}


try {
    file_put_contents('debug_log.txt', "Attempting DB connection...\n", FILE_APPEND);
    // Connect to database
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();

    // Parse date and time
    $currentDateTime = new DateTime('now', new DateTimeZone('Asia/Phnom_Penh'));
    
    // =========================================================================
    // === កន្លែងដែលបានកែប្រែ៖ ប្តូរទម្រង់ពី 'm/d/Y' ទៅជា 'Y-m-d' ===
    // =========================================================================
    $providedDate = DateTime::createFromFormat('Y-m-d', $data['date'], new DateTimeZone('Asia/Phnom_Penh'));
    
    if ($providedDate === false) {
        throw new Exception('Invalid date format. Expected Y-m-d.');
    }
    $providedDate->setTime((int)$currentDateTime->format('H'), (int)$currentDateTime->format('i'), (int)$currentDateTime->format('s'));
    $reportDate = $providedDate;

    // Build content summary for backward compatibility
    $contentSummary = "";
    if (!empty($data['tasks'])) {
        foreach ($data['tasks'] as $index => $task) {
            $taskText = trim($task['task'] ?? '');
            if (!empty($taskText)) {
                $contentSummary .= ($index + 1) . ". " . $taskText . " (" . ($task['status'] ?? '0%') . ")\n";
            }
        }
    }

    // Save report to DB (same logic as before)
    $tempLink = generateReportLink('temp');
    $viewPassword = $positionPasswords[$data['position']] ?? 'vvc.asia';
    $userId = $_SESSION['user_id'] ?? 0;

    $stmt = $pdo->prepare("INSERT INTO daily_reports (user_id, report_date, name, position, department, content, report_link, view_password, next_plan_date, next_plan_details) VALUES (:user_id, :report_date, :name, :position, :department, :content, :report_link, :view_password, :next_plan_date, :next_plan_details)");
    $stmt->execute([
        ':user_id' => $userId,
        ':report_date' => $reportDate->format('Y-m-d H:i:s'), 
        ':name' => $data['name'], 
        ':position' => $data['position'], 
        ':department' => $data['department'] ?? null, 
        ':content' => $contentSummary,
        ':report_link' => $tempLink, 
        ':view_password' => password_hash($viewPassword, PASSWORD_DEFAULT), 
        ':next_plan_date' => !empty($data['next_plan_date']) ? date('Y-m-d', strtotime($data['next_plan_date'])) : null, 
        ':next_plan_details' => $data['next_plan_details'] ?? null
    ]);
    $reportId = $pdo->lastInsertId();

    $finalReportLink = str_replace('id=temp', 'id=' . $reportId, $tempLink);
    $updateStmt = $pdo->prepare("UPDATE daily_reports SET report_link = :report_link WHERE id = :id");
    $updateStmt->execute([':report_link' => $finalReportLink, ':id' => $reportId]);

    if (!empty($data['tasks'])) {
        $taskStmt = $pdo->prepare("INSERT INTO report_tasks (report_id, time, task, status, due_date, description, problem, solution, no) VALUES (:report_id, :time, :task, :status, :due_date, :description, :problem, :solution, :no)");
        foreach ($data['tasks'] as $index => $task) {
            if (empty(trim($task['task']))) continue;
            $taskStmt->execute([':report_id' => $reportId, ':time' => !empty($task['time']) ? date('H:i:s', strtotime($task['time'])) : null, ':task' => $task['task'] ?? null, ':status' => $task['status'] ?? null, ':due_date' => !empty($task['dueDate']) ? date('Y-m-d', strtotime($task['dueDate'])) : null, ':description' => $task['description'] ?? null, ':problem' => $task['problem'] ?? null, ':solution' => $task['solution'] ?? null, ':no' => $task['no'] ?? ($index + 1)]);
        }
    }
    $pdo->commit();
    file_put_contents('debug_log.txt', "DB Commit Successful. Report ID: $reportId\n", FILE_APPEND);

    // --- START: MODIFIED TELEGRAM SENDING LOGIC ---
    file_put_contents('debug_log.txt', "Starting Telegram Process\n", FILE_APPEND);

    // Loop through each configured group to build and send the specific message format
    foreach ($telegramGroups as $group) {
        $telegramMessage = ''; // Reset message for each group
        $chatId = $group['chat_id'];
        $threadId = $group['thread_ids'][$data['position']] ?? null;

        // Prepare the common header for both formats
        $telegramDate = $reportDate->format('d/m/Y h:i A');
        $messageHeader = "📝 *របាយការណ៍ប្រចាំថ្ងៃ*\n"
                       . "ឈ្មោះ ៖ " . escapeMarkdown($data['name']) . "\n"
                       . "តួនាទី ៖ " . escapeMarkdown($data['position']) . "\n"
                       . "ថ្ងៃខែឆ្នាំ ៖ " . escapeMarkdown($telegramDate) . "\n";
        if (!empty($data['department'])) {
            $messageHeader .= "ផ្នែក ៖ " . escapeMarkdown($data['department']) . "\n";
        }

        // Build the message based on the group's report_format
        if ($group['report_format'] === 'full') {
            // **FORMAT FOR GROUP 2: FULL CONTENT**
            $telegramMessage = $messageHeader . "--------------------------------------\n\n*កិច្ចការដែលបានធ្វើ*\n";
            
            $hasTasks = false;
            foreach ($data['tasks'] as $index => $task) {
                $task_desc = escapeMarkdown(trim($task['task']));
                if (empty($task_desc)) continue;
                $hasTasks = true;

                $time = !empty($task['time']) ? escapeMarkdown($task['time']) : '_N/A_';
                $status = !empty($task['status']) ? escapeMarkdown($task['status']) : '_N/A_';
                
                $telegramMessage .= "\n*TASK " . ($index + 1) . ":* " . $task_desc . "\n"
                                  . "  - *ម៉ោង:* " . $time . "\n"
                                  . "  - *ស្ថានភាព:* " . $status . "\n";
            }
            if (!$hasTasks) {
                $telegramMessage .= "_មិនមានកិច្ចការត្រូវរាយការណ៍_\n";
            }

            // Add next day plan to the full report
            $nextPlanDate = !empty($data['next_plan_date']) ? date('d-M-Y', strtotime($data['next_plan_date'])) : 'N/A';
            $nextPlanDetails = !empty($data['next_plan_details']) ? escapeMarkdown($data['next_plan_details']) : 'មិនមាន';
            $telegramMessage .= "\n--------------------------------------\n"
                              . "📋 *ផែនការសម្រាប់ថ្ងៃបន្ទាប់* ({$nextPlanDate})\n"
                              . "{$nextPlanDetails}";

        } else {
            // **FORMAT FOR GROUP 1: LINK ONLY**
            $telegramMessage = $messageHeader . "[របាយការណ៍ពេលល្ងាច](" . $finalReportLink . ")";
        }

        // Prepare the final payload for Telegram
        $telegramPayload = [
            'chat_id' => $chatId,
            'parse_mode' => 'Markdown',
        ];

        if ($threadId !== null) {
            $telegramPayload['message_thread_id'] = $threadId;
        }

        // Handle Photo if present and it's the full report group or specifically requested
        if (!empty($data['image']) && $group['report_format'] === 'full') {
            // Detect mime type and extension
            $imageParts = explode(',', $data['image']);
            $header = $imageParts[0];
            $imageData = $imageParts[1];
            $decodedImage = base64_decode($imageData);
            
            $extension = 'png';
            $mimeType = 'image/png';
            if (strpos($header, 'image/jpeg') !== false) {
                $extension = 'jpg';
                $mimeType = 'image/jpeg';
            }

            // Create a temporary file for the photo
            $tempDir = sys_get_temp_dir();
            $tempPhotoPath = $tempDir . DIRECTORY_SEPARATOR . 'report_' . uniqid() . '.' . $extension;
            file_put_contents('debug_log.txt', "Processing image for group {$group['chat_id']}. Format: $mimeType. Temp Path: $tempPhotoPath\n", FILE_APPEND);
            
            if (file_put_contents($tempPhotoPath, $decodedImage) === false) {
                 file_put_contents('debug_log.txt', "Failed to write temp image to $tempPhotoPath\n", FILE_APPEND);
                 // Fallback to text message if image fails
                 $telegramPayload['text'] = $telegramMessage;
                 sendTelegramMessage($telegramBotToken, $telegramPayload);
                 continue;
            }

            $telegramPayload['photo'] = new CURLFile($tempPhotoPath, $mimeType, 'report.' . $extension);
            $telegramPayload['caption'] = $telegramMessage;
            
            try {
                file_put_contents('debug_log.txt', "Sending photo to Telegram group: {$group['chat_id']}\n", FILE_APPEND);
                sendTelegramPhoto($telegramBotToken, $telegramPayload);
                file_put_contents('debug_log.txt', "Photo sent successfully.\n", FILE_APPEND);
            } catch (Exception $e) {
                 file_put_contents('debug_log.txt', "Photo sending failed: " . $e->getMessage() . "\n", FILE_APPEND);
                 // Retry with text only
                 unset($telegramPayload['photo']);
                 unset($telegramPayload['caption']);
                 $telegramPayload['text'] = $telegramMessage;
                 sendTelegramMessage($telegramBotToken, $telegramPayload);
                 file_put_contents('debug_log.txt', "Fallback text message sent.\n", FILE_APPEND);
            } finally {
                // Delete temp file
                if (file_exists($tempPhotoPath)) {
                    @unlink($tempPhotoPath);
                }
            }
        } else {
            file_put_contents('debug_log.txt', "Sending text message to Telegram group: {$group['chat_id']}\n", FILE_APPEND);
            try {
                $telegramPayload['text'] = $telegramMessage;
                sendTelegramMessage($telegramBotToken, $telegramPayload);
                file_put_contents('debug_log.txt', "Text message sent successfully.\n", FILE_APPEND);
            } catch (Exception $e) {
                file_put_contents('debug_log.txt', "Text sending failed: " . $e->getMessage() . "\n", FILE_APPEND);
                throw $e;
            }
        }
    }
    // --- END: MODIFIED TELEGRAM SENDING LOGIC ---

    // --- END: MODIFIED TELEGRAM SENDING LOGIC ---

    echo json_encode(['success' => true, 'message' => 'បានបញ្ជូនរបាយការណ៍ដោយជោគជ័យ'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    file_put_contents('debug_log.txt', "Exception caught: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'កំហុស: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>