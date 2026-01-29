<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include the database connection
require 'includes/db.php'; // Ensure this file contains your database connection details

// Check if data is being posted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Data you want to insert
        $message = 'This is a new notification message';
        $created_at = date('Y-m-d H:i:s'); // You can customize this based on your needs

        // Prepare the SQL statement
        $stmt = $conn->prepare("INSERT INTO notifications (message, created_at) VALUES (:message, :created_at)");

        // Bind parameters
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':created_at', $created_at);

        // Execute the query
        $stmt->execute();

        echo json_encode(['success' => 'Notification added successfully!']);
    } catch (PDOException $e) {
        // Log the error and send a failure message
        error_log("Error: " . $e->getMessage());
        echo json_encode(['error' => 'Failed to add notification: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid request method.']);
}
?>
