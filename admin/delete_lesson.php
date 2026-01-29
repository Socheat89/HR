<?php
include 'includes/auth.php';
if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}
include 'includes/db.php';
$conn = include 'includes/db.php';

// Check if lesson ID is provided
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $lesson_id = intval($_GET['id']);

    try {
        // Begin transaction
        $conn->beginTransaction();

        // Delete associated media
        $stmt = $conn->prepare("
            DELETE FROM lesson_photos WHERE lesson_id = :id
        ");
        $stmt->bindParam(':id', $lesson_id, PDO::PARAM_INT);
        $stmt->execute();

        // Delete lesson
        $stmt = $conn->prepare("
            DELETE FROM lessons WHERE id = :id
        ");
        $stmt->bindParam(':id', $lesson_id, PDO::PARAM_INT);
        $stmt->execute();

        // Commit transaction
        $conn->commit();

        // Redirect to lessons page
        header("Location: lessons.php");
        exit();
    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Database error: " . $e->getMessage());
        header("Location: lessons.php?error=delete_failed");
        exit();
    }
} else {
    header("Location: lessons.php?error=invalid_request");
    exit();
}
?>