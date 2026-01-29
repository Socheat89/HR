<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    // Database connection
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db", 
                   'samann1_scan_logs_worker_db', 
                   'scan_logs_worker_db@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    // Get POST data
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $noted = filter_input(INPUT_POST, 'noted', FILTER_SANITIZE_STRING);

    if (!$id) {
        throw new Exception('Invalid log ID');
    }

    // Update the noted field
    $stmt = $pdo->prepare("UPDATE scan_logs SET noted = :noted WHERE id = :id");
    $stmt->execute([
        ':noted' => $noted,
        ':id' => $id
    ]);

    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Error saving noted: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}