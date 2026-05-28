<?php
include 'includes/auth.php';
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}
include 'includes/db.php';
$conn = include 'includes/db.php';

// Get the meeting ID from the query string
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: meetings.php");
    exit();
}
$meeting_id = $_GET['id'];

try {
    $conn->beginTransaction();

    // Delete associated photos
    $stmt = $conn->prepare("
        DELETE FROM meeting_photos 
        WHERE meeting_id = :meeting_id
    ");
    $stmt->bindParam(':meeting_id', $meeting_id, PDO::PARAM_INT);
    $stmt->execute();

    // Delete the meeting
    $stmt = $conn->prepare("
        DELETE FROM meetings 
        WHERE id = :id
    ");
    $stmt->bindParam(':id', $meeting_id, PDO::PARAM_INT);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    // Redirect to the meetings list page after successful deletion
    header("Location: meetings.php");
    exit();
} catch (PDOException $e) {
    $conn->rollBack();
    error_log("Database error: " . $e->getMessage());
    $error_message = "An error occurred while deleting the meeting.";
    header("Location: meetings.php?error=" . urlencode($error_message));
    exit();
}
?>