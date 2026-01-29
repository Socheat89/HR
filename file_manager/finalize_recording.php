<?php
session_start();
header('Content-Type: application/json');

// ផ្ទៀងផ្ទាត់ CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// ទទួល JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['sessionId']) || !isset($input['title']) || !isset($input['mimeType'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$sessionId = preg_replace('/[^a-zA-Z0-9_]/', '', $input['sessionId']);
$title = htmlspecialchars($input['title'], ENT_QUOTES, 'UTF-8');
$mimeType = $input['mimeType'];
$extension = ($mimeType === 'audio/mp4;codecs=mp4a.40.2' || $mimeType === 'audio/mp4') ? 'm4a' : 'wav';

$tempDir = "Uploads/temp/{$sessionId}/";
$targetDir = "Uploads/";
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$filename = uniqid() . '.' . $extension;
$finalFile = $targetDir . $filename;

// បញ្ចូលបំណែកៗទៅជាឯកសារតែមួយ
if (is_dir($tempDir)) {
    $chunks = glob($tempDir . '*.chunk');
    sort($chunks); // តម្រៀបដើម្បីធានាលំដាប់ត្រឹមត្រូវ
    $output = fopen($finalFile, 'wb');
    foreach ($chunks as $chunk) {
        $content = file_get_contents($chunk);
        fwrite($output, $content);
        unlink($chunk); // លុប chunk បន្ទាប់ពីបញ្ចូល
    }
    fclose($output);
    rmdir($tempDir); // លុប temporary directory
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No chunks found']);
    exit;
}

// តភ្ជាប់ database
$conn = new mysqli("localhost", "samann1_file_manager_db", "file_manager_db", "samann1_file_manager_db");
if ($conn->connect_error) {
    unlink($finalFile);
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// រក្សាទុកក្នុង database
$sql = "INSERT INTO recordings (title, filename) VALUES (?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    unlink($finalFile);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare database statement']);
    exit;
}
$stmt->bind_param("ss", $title, $filename);
if (!$stmt->execute()) {
    unlink($finalFile);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save to database']);
    exit;
}

$stmt->close();
$conn->close();

http_response_code(200);
echo json_encode(['status' => 'Recording saved', 'filename' => $filename]);
?>