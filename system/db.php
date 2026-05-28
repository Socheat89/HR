<?php
/**
 * Database Configuration - Optimized for Performance
 * using Centralized Connection Logic
 */

require_once __DIR__ . '/../db_connection.php';

// Get PDO Connection
$pdo = getPDO();

// Ensure compatibility if anything expects variables to be set locally (though unlikely needed with centralized func)
?>
