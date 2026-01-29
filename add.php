<?php
session_start();
include 'db_payroll.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $task = $_POST['task'];
    $description = $_POST['description'] ?? '';
    $due_date = $_POST['due_date'] ?? null;
    $priority = $_POST['priority'];
    $category = $_POST['category'];
    $user_id = $_SESSION['user_id'];

    // Get max sort_order
    $sql = "SELECT MAX(sort_order) as max_order FROM tasks WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $sort_order = ($row['max_order'] ?? 0) + 1;

    // Insert task
    $sql = "INSERT INTO tasks (task, description, due_date, priority, category, user_id, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssssii', $task, $description, $due_date, $priority, $category, $user_id, $sort_order);
    if ($stmt->execute()) {
        header('Location: todo-list.php');
        exit;
    } else {
        echo "Error adding task.";
    }
    $stmt->close();
}
?>