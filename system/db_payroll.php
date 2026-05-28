<?php
// Centralized Database Connection
require_once __DIR__ . '/../db_connection.php';

// Get MySQLi Connection with fallback logic
$conn = getMySQLi();

// Additional error handling if needed (though getMySQLi handles basics)
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
