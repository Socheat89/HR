<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database connection
require 'includes/db.php'; // Ensure this file contains your database connection details

header('Content-Type: application/json');

// Try fetching notifications
try {
    // Check if the connection is valid
    if (!$conn) {
        throw new Exception("Database connection failed.");
    }

    // Fetch notifications from the database
    $stmt = $conn->query("SELECT message, created_at FROM notifications ORDER BY created_at DESC");
    if (!$stmt) {
        throw new Exception("Error executing SQL query.");
    }

    $notifications = $stmt->fetchAll();
    echo json_encode($notifications);
} catch (PDOException $e) {
    // Log and display database error
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Log and display general errors
    error_log("General error: " . $e->getMessage());
    echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
}
?>
