<?php
// --- DATABASE CONNECTION ---
// IMPORTANT: Replace with your actual database credentials
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'samann1_admin_panel'); // Your DB username
define('DB_PASSWORD', 'admin_panel@2025'); // Your DB password
define('DB_NAME', 'samann1_admin_panel'); // Your DB name

// Create connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// *** IMPORTANT: SET CHARACTER SET FOR KHMER FONT SUPPORT ***
// This ensures data sent to and from the database is in UTF-8.
$conn->set_charset("utf8mb4");
?>
