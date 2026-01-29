<?php
header('Content-Type: application/json; charset=utf-8');

// Database configuration
$dbHost = 'localhost';
$dbUser = 'samann1_daily_report_db';
$dbPass = 'samann1_daily_report_db';
$dbName = 'samann1_daily_report_db';

// --- START: MODIFIED TELEGRAM CONFIGURATION ---
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

// Decode input JSON data
$data = json_decode(file_get_contents('php://input'), true);

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

// Function to send message to Telegram
function sendTelegramMessage($botToken, $payload) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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


try {
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

    // Save report to DB (same logic as before)
    $tempLink = generateReportLink('temp');
    $viewPassword = $positionPasswords[$data['position']] ?? 'Default@2025';

    $stmt = $pdo->prepare("INSERT INTO daily_reports (report_date, name, position, department, report_link, view_password, next_plan_date, next_plan_details) VALUES (:report_date, :name, :position, :department, :report_link, :view_password, :next_plan_date, :next_plan_details)");
    $stmt->execute([':report_date' => $reportDate->format('Y-m-d H:i:s'), ':name' => $data['name'], ':position' => $data['position'], ':department' => $data['department'] ?? null, ':report_link' => $tempLink, ':view_password' => password_hash($viewPassword, PASSWORD_DEFAULT), ':next_plan_date' => !empty($data['next_plan_date']) ? date('Y-m-d', strtotime($data['next_plan_date'])) : null, ':next_plan_details' => $data['next_plan_details'] ?? null]);
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

    // --- START: MODIFIED TELEGRAM SENDING LOGIC ---

    // Loop through each configured group to build and send the specific message format
    foreach ($telegramGroups as $group) {
        $telegramMessage = ''; // Reset message for each group
        $chatId = $group['chat_id'];
        $threadId = $group['thread_ids'][$data['position']] ?? null;

        // Prepare the common header for both formats
        $telegramDate = $reportDate->format('d/m/Y h:i A');
        $messageHeader = "📝 *របាយការណ៍ប្រចាំថ្ងៃ*\n"
                       . "ឈ្មោះ ៖ " . htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8') . "\n"
                       . "តួនាទី ៖ " . htmlspecialchars($data['position'], ENT_QUOTES, 'UTF-8') . "\n"
                       . "ថ្ងៃខែឆ្នាំ ៖ " . htmlspecialchars($telegramDate, ENT_QUOTES, 'UTF-8') . "\n";
        if (!empty($data['department'])) {
            $messageHeader .= "ផ្នែក ៖ " . htmlspecialchars($data['department'], ENT_QUOTES, 'UTF-8') . "\n";
        }

        // Build the message based on the group's report_format
        if ($group['report_format'] === 'full') {
            // **FORMAT FOR GROUP 2: FULL CONTENT**
            $telegramMessage = $messageHeader . "--------------------------------------\n\n*កិច្ចការដែលបានធ្វើ*\n";
            
            $hasTasks = false;
            foreach ($data['tasks'] as $index => $task) {
                $task_desc = htmlspecialchars(trim($task['task']), ENT_QUOTES, 'UTF-8');
                if (empty($task_desc)) continue;
                $hasTasks = true;

                $time = !empty($task['time']) ? htmlspecialchars($task['time'], ENT_QUOTES, 'UTF-8') : '_N/A_';
                $status = !empty($task['status']) ? htmlspecialchars($task['status'], ENT_QUOTES, 'UTF-8') : '_N/A_';
                
                $telegramMessage .= "\n*TASK " . ($index + 1) . ":* " . $task_desc . "\n"
                                  . "  - *ម៉ោង:* " . $time . "\n"
                                  . "  - *ស្ថានភាព:* " . $status . "\n";
            }
            if (!$hasTasks) {
                $telegramMessage .= "_មិនមានកិច្ចការត្រូវរាយការណ៍_\n";
            }

            // Add next day plan to the full report
            $nextPlanDate = !empty($data['next_plan_date']) ? date('d-M-Y', strtotime($data['next_plan_date'])) : 'N/A';
            $nextPlanDetails = !empty($data['next_plan_details']) ? htmlspecialchars($data['next_plan_details'], ENT_QUOTES, 'UTF-8') : 'មិនមាន';
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
            'text' => $telegramMessage,
            'parse_mode' => 'Markdown',
        ];

        if ($threadId !== null) {
            $telegramPayload['message_thread_id'] = $threadId;
        }

        // Send the message
        sendTelegramMessage($telegramBotToken, $telegramPayload);
    }
    // --- END: MODIFIED TELEGRAM SENDING LOGIC ---

    echo json_encode(['success' => true, 'message' => 'បានបញ្ជូនរបាយការណ៍ដោយជោគជ័យ'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'កំហុស: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>