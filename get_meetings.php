<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");

// Database connection details
$host = "localhost";
$dbname = "samann1_daily_report_db";
$username = "samann1_daily_report_db";
$password = "samann1_daily_report_db";

try {
    // Connect to database with UTF-8 support
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");

    // Query to fetch data from both tables using UNION
    $sql = "
        SELECT id, id_number, name, gender, date, time, location, meeting_type, NULL as reason, 'attended' as status, created_at 
        FROM meetings
        UNION
        SELECT id, id_number, name, gender, date, time, location, meeting_type, reason, 'absent' as status, created_at 
        FROM absent_meetings
        ORDER BY created_at DESC
    ";
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