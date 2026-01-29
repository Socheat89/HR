<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

// Database connection details
$host = "localhost";
$dbname = "samann1_daily_report_db";
$username = "samann1_daily_report_db";
$password = "samann1_daily_report_db";

try {
    // Connect to database with UTF-8 support
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'"); // Ensure UTF-8 encoding

    // Get POST data
    $data = json_decode(file_get_contents("php://input"), true);

    // Prepare SQL statement with Khmer field names
    $sql = "INSERT INTO meetings (id_number, name, gender, date, time, location, meeting_type) 
            VALUES (:id_number, :name, :gender, :date, :time, :location, :meeting_type)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_number' => $data['អត្តលេខ'],
        ':name' => $data['ឈ្មោះ'],
        ':gender' => $data['ភេទ'],
        ':date' => $data['ថ្ងៃខែឆ្នាំ'],
        ':time' => $data['ម៉ោងប្រជុំ'],
        ':location' => $data['ទីតាំងប្រជុំ'],
        ':meeting_type' => $data['ប្រភេទនៃការប្រជុំ']
    ]);

    // Return success response
    echo json_encode(["status" => "success", "message" => "ទិន្នន័យបានរក្សាទុកជោគជ័យ"]);
} catch (PDOException $e) {
    // Return error response
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>