<?php
// Remove session_start() since it's handled in includes/auth.php
require_once 'includes/auth.php';
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

// Include database connection
require_once 'includes/db.php';

// Get PDO connection using the function
$conn = getDBConnection();

// Get and validate user ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    error_log("Error: Invalid or missing user ID.");
    header("Location: dashboard.php?error=invalid_user_id");
    exit();
}
$id = $_GET['id'];

try {
    // Prepare and execute DELETE query
    $stmt = $conn->prepare("DELETE FROM users WHERE id = :id");
    $stmt->execute(['id' => $id]);

    if ($stmt->rowCount() === 0) {
        // No rows were affected, meaning the user ID wasn't found
        error_log("Error: No user found with ID $id for deletion.");
        header("Location: dashboard.php?error=user_not_found");
        exit();
    }

    // Log successful deletion
    error_log("User with ID $id deleted successfully.");

    // Redirect with success message
    header("Location: dashboard.php?success=user_deleted");
    exit();

} catch (PDOException $e) {
    // Log detailed error
    $errorMessage = "Database error while deleting user ID $id: " . $e->getMessage();
    error_log($errorMessage);

    // Send Telegram notification for critical error (optional, if configured)
    require_once 'includes/telegram.php';
    sendTelegramMessage('-4714007198', "❌ Error deleting user ID $id: " . $e->getMessage());

    // Redirect with error message
    header("Location: dashboard.php?error=delete_failed");
    exit();
}
?>