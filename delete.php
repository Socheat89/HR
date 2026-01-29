<?php
session_start();
include 'db_payroll.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['id'])) {
    $task_id = $_GET['id'];
    $user_id = $_SESSION['user_id'];

    $sql = "DELETE FROM tasks WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $task_id, $user_id);
    $stmt->execute();
    $stmt->close();
}
header('Location: todo-list.php');
exit;
?>