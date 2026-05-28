<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");

// Include the meeting database connection file
require_once __DIR__ . '/includes/db.php';

try {
    // $pdo is defined in includes/db.php
    if (!isset($pdo)) {
        throw new Exception("Database connection not found.");
    }

    // Get POST data
    $data = json_decode(file_get_contents("php://input"), true);

    // Prepare SQL statement
    $sql = "INSERT INTO `absent-register` (id_number, name, gender, date, time, location, meeting_type, reason) 
            VALUES (:id_number, :name, :gender, :date, :time, :location, :meeting_type, :reason)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_number' => $data['អត្តលេខ'],
        ':name' => $data['ឈ្មោះ'],
        ':gender' => $data['ភេទ'],
        ':date' => $data['ថ្ងៃខែឆ្នាំ'],
        ':time' => $data['ម៉ោងប្រជុំ'],
        ':location' => $data['ទីតាំងប្រជុំ'],
        ':meeting_type' => $data['ប្រភេទនៃការប្រជុំ'],
        ':reason' => $data['មូលហេតុ']
    ]);

    // Return success response
    echo json_encode(["status" => "success", "message" => "ទិន្នន័យបានរក្សាទុកជោគជ័យ"]);
} catch (PDOException $e) {
    // Return error response
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>