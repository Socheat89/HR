<?php
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'samann1_mini_app_db';
$username = 'samann1_mini_app_db';
$password = 'samann1_mini_app_db@2025';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
if (!$category_id) {
    echo json_encode(['error' => 'Invalid category ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name FROM subcategories WHERE category_id = ? ORDER BY name");
    $stmt->execute([$category_id]);
    $subcategories = $stmt->fetchAll();
    echo json_encode($subcategories);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Failed to fetch subcategories']);
}
?>