<?php
header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_admin_panel", "samann1_admin_panel", "admin_panel@2025", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    echo json_encode([]);
    exit;
}

// Get last known location per phone
$stmt = $pdo->query("
    SELECT l.*
    FROM employee_locations l
    INNER JOIN (
        SELECT phone, MAX(updated_at) AS latest
        FROM employee_locations
        GROUP BY phone
    ) latest ON l.phone = latest.phone AND l.updated_at = latest.latest
    ORDER BY l.updated_at DESC
");

$rows = $stmt->fetchAll();
echo json_encode($rows);
?>
