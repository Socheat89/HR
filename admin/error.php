<?php
session_start();

if (!isset($_SESSION['error_message'])) {
    header("Location: ../index.php");
    exit();
}

$error_message = $_SESSION['error_message'];
unset($_SESSION['error_message']); // Clear the error message after displaying it
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="error-container">
        <h2>Error</h2>
        <p><?php echo htmlspecialchars($error_message); ?></p>
        <a href="dashboard.php" class="btn back-btn">Back to Dashboard</a>
    </div>
</body>
</html>