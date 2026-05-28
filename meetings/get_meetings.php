<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// Include the meeting database connection file
require_once __DIR__ . '/includes/db.php';

try {
    // $pdo is defined in includes/db.php
    if (!isset($pdo)) {
        throw new Exception("Database connection not found.");
    }

    // Check if absent-register table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'absent-register'");
    $hasAbsentTable = $stmt->fetch();

    $sql = "SELECT id, id_number, name, gender, date, time, location, meeting_type, NULL as reason, 'attended' as status, created_at FROM `meetings-register` ";
    
    if ($hasAbsentTable) {
        $sql .= " UNION SELECT id, id_number, name, gender, date, time, location, meeting_type, reason, 'absent' as status, created_at FROM `absent-register` ";
    }
    
    $sql .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return data as JSON
    echo json_encode([
        "status" => "success",
        "data" => $meetings
    ]);
} catch (PDOException $e) {
    // Return error response
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>