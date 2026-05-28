<?php
// Use centralized database connection logic with fallback
require_once __DIR__ . '/../../db_connection.php';

// Get PDO Connection
$conn = getPDO();

return $conn;
?>