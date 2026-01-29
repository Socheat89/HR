<?php
include 'includes/auth.php';
if (!isLoggedIn() || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'អ្នកមិនមានសិទ្ធិធ្វើសកម្មភាពនេះទេ។';
    header("Location: dashboard.php");
    exit();
}

include 'includes/db.php';
$conn = include 'includes/db.php';

$group_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$group_id) {
    $_SESSION['error'] = 'លេខសម្គាល់ក្រុមមិនត្រឹមត្រូវ។';
    header("Location: dashboard.php?view=groups");
    exit();
}

try {
    // ដោយសារតែយើងបានប្រើ ON DELETE CASCADE នៅក្នុង Database
    // ការលុបក្រុមមួយនឹងលុប Record ទាំងអស់ដែលពាក់ព័ន្ធនៅក្នុងតារាង user_groups ដោយស្វ័យប្រវត្តិ។
    $stmt = $conn->prepare("DELETE FROM groups WHERE id = :id");
    $stmt->execute([':id' => $group_id]);

    if ($stmt->rowCount() > 0) {
        header("Location: dashboard.php?view=groups&success=group_deleted");
    } else {
        $_SESSION['error'] = 'រកមិនឃើញក្រុមដែលត្រូវលុប។';
        header("Location: dashboard.php?view=groups");
    }
    exit();

} catch (PDOException $e) {
    error_log("Group deletion error: " . $e->getMessage());
    $_SESSION['error'] = 'មានបញ្ហាពេលលុបក្រុម។';
    header("Location: dashboard.php?view=groups");
    exit();
}