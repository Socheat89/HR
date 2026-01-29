<?php
include 'includes/auth.php';
// Check if user is logged in and is an admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'អ្នកមិនមានសិទ្ធិគ្រប់គ្រាន់ទេ។';
    header("Location: dashboard.php");
    exit();
}

// Check if user ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'លេខសម្គាល់អ្នកប្រើប្រាស់មិនត្រឹមត្រូវទេ។';
    header("Location: dashboard.php");
    exit();
}

$user_id_to_deactivate = (int)$_GET['id'];
$current_user_id = $_SESSION['user_id'];

// Prevent admin from deactivating their own account
if ($user_id_to_deactivate === $current_user_id) {
    $_SESSION['error'] = 'អ្នកមិនអាចបិទគណនីផ្ទាល់ខ្លួនបានទេ។';
    header("Location: dashboard.php");
    exit();
}

include 'includes/db.php';
$conn = include 'includes/db.php';

try {
    // Update user status to 'inactive'
    $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
    $stmt->execute([$user_id_to_deactivate]);

    if ($stmt->rowCount() > 0) {
        // Redirect with success message
        header("Location: dashboard.php?success=deactivated");
    } else {
        $_SESSION['error'] = 'រកមិនឃើញអ្នកប្រើប្រាស់ ឬគណនីត្រូវបានបិទរួចហើយ។';
        header("Location: dashboard.php");
    }
} catch (PDOException $e) {
    error_log("Deactivation error: " . $e->getMessage());
    $_SESSION['error'] = 'មានកំហុសក្នុងការបិទគណនី។';
    header("Location: dashboard.php");
}

exit();
?>