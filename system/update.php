<?php
session_start();
include '../payroll/db_payroll.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $task_id = $_POST['id'];
    $user_id = $_SESSION['user_id'];

    // Get current status
    $sql = "SELECT status FROM tasks WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $task_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();

    if ($task) {
        $new_status = ($task['status'] === 'completed') ? 'pending' : 'completed';
        $sql = "UPDATE tasks SET status = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sii', $new_status, $task_id, $user_id);
        $stmt->execute();
    }
    $stmt->close();
}
header('Location: ../system/todo-list.php');
exit;
?>