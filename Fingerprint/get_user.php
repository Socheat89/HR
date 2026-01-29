<?php
header("Content-Type: application/json; charset=UTF-8");
require_once "db_connect.php"; // Ensure you have the correct DB connection file

try {
    // Connect to the database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch distinct user names from logs
    $stmt = $pdo->prepare("SELECT DISTINCT user_name FROM scan_logs ORDER BY user_name ASC");
    $stmt->execute();
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return users as JSON
    echo json_encode($users);
} catch (PDOException $e) {
    echo json_encode(["error" => "Database error: " . $e->getMessage()]);
}
?>
