<?php
// save_rule.php

// 1. ភ្ជាប់ទៅកាន់ Database
$conn = new mysqli('localhost', 'samann1_facebook-bot', 'facebook-bot!@#', 'samann1_facebook-bot');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. ទទួលទិន្នន័យពី Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keyword = $_POST['keyword'];
    $reply_text = $_POST['reply_text'];
    
    // 3. បញ្ចូលទិន្នន័យទៅក្នុងតារាង
    $stmt = $conn->prepare("INSERT INTO auto_replies (keyword, reply_text, reply_type) VALUES (?, ?, 'text')");
    $stmt->bind_param("ss", $keyword, $reply_text);
    
    if ($stmt->execute()) {
        echo "Rule saved successfully!";
        // បញ្ជូនអ្នកប្រើប្រាស់ត្រឡប់ទៅ Dashboard វិញ
        header('Location: index.php');
    } else {
        echo "Error: " . $stmt->error;
    }
    
    $stmt->close();
}

$conn->close();
?>