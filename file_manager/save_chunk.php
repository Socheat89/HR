<?php
session_start();

// ផ្ទៀងផ្ទាត់ CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// ពិនិត្យមើលថាមាន chunk និង sessionId
if (!isset($_FILES['chunk']) || !isset($_POST['sessionId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing chunk or session ID']);
    exit;
}

$sessionId = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['sessionId']); // Sanitize sessionId
$tempDir = "Uploads/temp/{$sessionId}/";
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

// ផ្លាស់ទី chunk ទៅ temporary directory
$chunkFile = $tempDir . time() . '.chunk';
if (!move_uploaded_file($_FILES['chunk']['tmp_name'], $chunkFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save chunk']);
    exit;
}

http_response_code(200);
echo json_encode(['status' => 'Chunk saved']);
?>