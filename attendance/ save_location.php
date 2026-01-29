<?php
header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_admin_panel", "samann1_admin_panel", "admin_panel@2025", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
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
        $stmt = $pdo->prepare("INSERT INTO employee_locations (phone, latitude, longitude, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$phone, $lat, $lng]);
        echo json_encode(["status" => "success", "message" => "Location saved"]);
    }
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Query failed"]);
}
?>
