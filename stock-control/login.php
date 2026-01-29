<?php
session_start();
require_once 'db_connect.php';
$error_message = '';

// If user is already logged in, redirect them away from login page
if (isset($_SESSION['user_id'])) {
    header("Location: user_request_form.php"); // Redirect to a main page
    exit;
}

if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error_message = "Please enter both username and password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, password, role, full_name FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify user exists and password is correct
            if ($user && password_verify($password, $user['password'])) {
                // Password is correct, set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                
                // Redirect to the main request form
                header("Location: user_request_form.php");
                exit;
            } else {
                $error_message = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Stock Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f5f7fa; font-family: 'Poppins', sans-serif; }
        .login-card { background: #fff; padding: 2.5rem 2rem; border-radius: 12px; box-shadow: 0 8px 25px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .login-card h1 { text-align: center; margin-bottom: 1.5rem; color: #333; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 1rem; }
        .login-btn { width: 100%; padding: 12px; background: #00b4db; color: #fff; border: none; border-radius: 8px; font-size: 1.1rem; cursor: pointer; font-weight: 600; }
        .error-message { color: #e74c3c; background: #ffebee; padding: 10px; border-radius: 8px; text-align: center; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>Stock System Login</h1>
        <?php if ($error_message): ?>
            <p class="error-message"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" name="login" class="login-btn">Login</button>
        </form>
    </div>
</body>
</html>