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
    header("Location: dashboard.php?view=inactive_users");
    exit();
}

$user_id_to_activate = (int)$_GET['id'];

include 'includes/db.php';
$conn = include 'includes/db.php';

try {
    // Update user status to 'active'
    $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ?");
    $stmt->execute([$user_id_to_activate]);

    if ($stmt->rowCount() > 0) {
        // Redirect with success message
        header("Location: dashboard.php?view=inactive_users&success=activated");
    } else {
        $_SESSION['error'] = 'រកមិនឃើញអ្នកប្រើប្រាស់។';
        header("Location: dashboard.php?view=inactive_users");
    }
} catch (PDOException $e) {
    error_log("Activation error: " . $e->getMessage());
    $_SESSION['error'] = 'មានកំហុសក្នុងការបើកគណនីឡើងវិញ។';
    header("Location: dashboard.php?view=inactive_users");
}

exit();
?>