<?php
// Centralized Database Connection
require_once __DIR__ . '/../db_connection.php';

header('Content-Type: application/json');

try {
    $pdo = getPDO();
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
    exit;
}

$phone = $_POST['phone'] ?? '';
$lat   = $_POST['lat'] ?? '';
$lng   = $_POST['lng'] ?? '';

if (!$phone || !$lat || !$lng) {
    echo json_encode(["status" => "error", "message" => "Missing phone, lat, or lng"]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id FROM employee_locations WHERE phone = ?");
    $stmt->execute([$phone]);

    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("UPDATE employee_locations SET latitude = ?, longitude = ?, updated_at = NOW() WHERE phone = ?");
        $stmt->execute([$lat, $lng, $phone]);
        echo json_encode(["status" => "success", "message" => "Location updated"]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO employee_locations (phone, latitude, longitude, updated_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$phone, $lat, $lng]);
        echo json_encode(["status" => "success", "message" => "Location saved"]);
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Operation failed: " . $e->getMessage()]);
}
?>
