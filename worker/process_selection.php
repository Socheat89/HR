<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedUsers = $_POST['selected_users'] ?? [];
    
    // Process the selected users (example: store in session or database)
    $_SESSION['selected_users'] = $selectedUsers;
    
    // For demonstration, we'll just display the selection
    echo "<h2>អ្នកបានជ្រើសរើស:</h2>";
    echo "<ul>";
    foreach ($selectedUsers as $user) {
        echo "<li>" . htmlspecialchars($user) . "</li>";
    }
    echo "</ul>";
    echo "<a href='management_panel.php'>ត្រឡប់ក្រោយ</a>";
} else {
    header("Location: management_panel.php");
    exit;
}
?>