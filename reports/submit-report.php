<?php
header('Content-Type: application/json; charset=utf-8');

// Include database configuration
require_once '../system/config.php';

$dbHost = DB_HOST;
$dbUser = DB_USER;
$dbPass = DB_PASS;
$dbName = DB_NAME;

// Telegram configuration
$telegramBotToken = "7071927561:AAE5wQn80PkmOUqJlJgoXJJN-48Ol-ufSbE";
$telegramChatId = "-1001618361706";

// Thread IDs based on position
$threadIds = [
    "ព័ត៌មានវិទ្យា" => 23912,
    "គិតលុយ" => 48137,
    "រដ្ឋបាលទូទៅ" => 23914,
    "បុគ្គលិកផ្នែកលក់" => 48137,
    "បុគ្គលិកផ្នែកស្តុក318" => 48137,
    "ប្រធានផ្នែកគ្រប់គ្រងស្តកទំនិញទូទៅ" => 48143,
    "ប្រធានឃ្លាំង៣១៨និងហាងទំនិញ" => 48137,
    "បុគ្គលិកផ្នែកគណនេយ្យ" => 48139,
    "ប្រមូលសាច់ប្រាក់" => 48139,
    "ប្រធានឃ្លាំង CH1" => 48143,
    "រដ្ឋបាលឃ្លាំង CH1" => 48143,
    "ជំនួយការប្រធានឃ្លាំង CH1" => 48143,
    "ប្រធានឃ្លាំង CKD" => 48143,
    "ជំនួយការប្រធានឃ្លាំង CKD" => 48143,
    "ប្រធានរដ្ឋបាលឃ្លាំង CKD" => 48143,
    "ប្រធានឃ្លាំង ST1" => 48143,
    "ប្រធានឃ្លាំង PSP" => 48143,
];

// Passwords for each position
$positionPasswords = [
    "ព័ត៌មានវិទ្យា" => "vvc.asia",
    "គិតលុយ" => "vvc.asia",
    "រដ្ឋបាលទូទៅ" => "vvc.asia",
    "បុគ្គលិកផ្នែកលក់" => "vvc.asia",
    "បុគ្គលិកផ្នែកស្តុក318" => "vvc.asia",
    "ប្រធានផ្នែកគ្រប់គ្រងស្តកទំនិញទូទៅ" => "vvc.asia",
    "ប្រធានឃ្លាំង៣១៨និងហាងទំនិញ" => "vvc.asia",
    "បុគ្គលិកផ្នែកគណនេយ្យ" => "vvc.asia",
    "ប្រមូលសាច់ប្រាក់" => "vvc.asia",
    "ប្រធានឃ្លាំង CH1" => "vvc.asia",
    "រដ្ឋបាលឃ្លាំង CH1" => "vvc.asia",
    "ជំនួយការប្រធានឃ្លាំង CH1" => "vvc.asia",
    "ប្រធានឃ្លាំង CKD" => "vvc.asia",
    "ជំនួយការប្រធានឃ្លាំង CKD" => "vvc.asia",
    "ប្រធានរដ្ឋបាលឃ្លាំង CKD" => "vvc.asia",
    "ប្រធានឃ្លាំង ST1" => "vvc.asia",
    "ប្រធានឃ្លាំង PSP" => "vvc.asia",
];

// Set timezone to Cambodia (UTC+7)
date_default_timezone_set('Asia/Phnom_Penh');

// Get form data
$name = $_POST['Name'] ?? '';
$email = $_POST['Email'] ?? '';
$position = $_POST['Position'] ?? '';
$content = $_POST['Content'] ?? '';
$rawDate = $_POST['Date'] ?? date('Y-m-d\TH:i');

// Convert datetime-local input to desired formats
$dateTime = new DateTime($rawDate);
$telegramDate = $dateTime->format('m/d/Y h:i A');
$dbDate = $dateTime->format('Y-m-d H:i:s');

// Validate data
if (empty($name) || empty($position) || empty($content)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ទិន្នន័យមិនគ្រប់គ្រាន់'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Function to generate unique report link
function generateReportLink($reportId) {
    $baseUrl = "https://app.vvc.asia/report.php?id="; // Replace with your domain
    return $baseUrl . $reportId . "&token=" . bin2hex(random_bytes(16));
}

try {
    // Connect to database
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");

    // Start transaction
    $pdo->beginTransaction();

    // Generate report link and get password
    $reportLink = generateReportLink('temp'); // Temporary ID, updated later
    $viewPassword = $positionPasswords[$position] ?? 'Default@123'; // Fallback password

    // Insert into daily_reports
    $stmt = $pdo->prepare("
        INSERT INTO daily_reports (report_date, name, position, department, report_link, view_password)
        VALUES (:report_date, :name, :position, :department, :report_link, :view_password)
    ");
    $stmt->execute([
        ':report_date' => $dbDate,
        ':name' => $name,
        ':position' => $position,
        ':department' => null,
        ':report_link' => $reportLink,
        ':view_password' => password_hash($viewPassword, PASSWORD_DEFAULT)
    ]);
    $reportId = $pdo->lastInsertId();

    // Update report link with actual ID
    $reportLink = str_replace('id=temp', 'id=' . $reportId, $reportLink);
    $updateStmt = $pdo->prepare("UPDATE daily_reports SET report_link = :report_link WHERE id = :id");
    $updateStmt->execute([':report_link' => $reportLink, ':id' => $reportId]);

    // Insert into report_tasks
    $taskStmt = $pdo->prepare("
        INSERT INTO report_tasks (report_id, time, task)
        VALUES (:report_id, :time, :task)
    ");
    $taskStmt->execute([
        ':report_id' => $reportId,
        ':time' => $dbDate,
        ':task' => $content
    ]);

    // Commit database transaction
    $pdo->commit();

    // Determine thread ID
    $messageThreadId = $threadIds[$position] ?? 23912;

    // Prepare Telegram message (link embedded in text)
    $telegramMessage = "📝 *របាយការណ៍ប្រចាំថ្ងៃ*\n";
    $telegramMessage .= "ឈ្មោះ ៖ " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "\n";
    $telegramMessage .= "តួនាទី ៖ " . htmlspecialchars($position, ENT_QUOTES, 'UTF-8') . "\n";
    $telegramMessage .= "ថ្ងៃខែឆ្នាំ ៖ " . htmlspecialchars($telegramDate, ENT_QUOTES, 'UTF-8') . "\n";
    if (!empty($email)) {
        $telegramMessage .= "អ៊ីមែល ៖ " . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "\n";
    }
    $telegramMessage .= "[របាយការណ៍ពេលល្ងាច](" . $reportLink . ")";

    // Send to Telegram
    $telegramUrl = "https://api.telegram.org/bot$telegramBotToken/sendMessage";
    $telegramData = [
        'chat_id' => $telegramChatId,
        'text' => $telegramMessage,
        'parse_mode' => 'Markdown',
        'message_thread_id' => $messageThreadId,
    ];

    $ch = curl_init($telegramUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($telegramData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);

    if (curl_errno($ch)) {
        $curlError = curl_error($ch);
        curl_close($ch);
        throw new Exception("កំហុស cURL: $curlError");
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $telegramResponse = json_decode($result, true);
    if (!$telegramResponse['ok']) {
        throw new Exception("Telegram API ឆ្លើយតបមិនជោគជ័យ: " . ($telegramResponse['description'] ?? 'មិនមានការពិពណ៌នា') . " (HTTP Code: $httpCode)");
    }

    // Success response
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'កំហុស: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>