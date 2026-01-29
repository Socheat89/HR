<?php
include 'includes/db.php';

if (isset($_GET['username'])) {
    $username = trim($_GET['username']);

    // Check if the username exists in the database
    $stmt = $conn->prepare("SELECT id FROM user WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);

    // Return JSON response
    echo json_encode(['exists' => !!$exists]);
} else {
    echo json_encode(['exists' => false]);
}
?>