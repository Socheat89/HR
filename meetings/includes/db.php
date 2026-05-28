<?php
// Centralized Database Connection
require_once __DIR__ . "/../../db_connection.php";

try {
    $pdo = getPDO();
    $conn = $pdo;
} catch (Exception $e) {
    header("Content-Type: application/json; charset=UTF-8");
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $e->getMessage()]));
}
?>
