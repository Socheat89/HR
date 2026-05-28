<?php
session_start();
require '../system/db.php';

// Determine if we are on the login or register page
$action = $_GET['action'] ?? 'login';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- LOGIN LOGIC ---
    if ($_POST['form_action'] === 'login') {
        $motorcycle_number = trim($_POST['motorcycle_number']);
        $password = $_POST['password'];

        if (empty($motorcycle_number) || empty($password)) {
            $error_message = 'Motorcycle number and password are required.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM tracking_users WHERE motorcycle_number = ?");
            $stmt->execute([$motorcycle_number]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['motorcycle_number'] = $user['motorcycle_number'];
                $_SESSION['phone_number'] = $user['phone_number'];
                header("Location: ../tracking/tracker.php");
                exit();
            } else {
                $error_message = 'Invalid motorcycle number or password.';
            }
        }
    }

    // --- REGISTRATION LOGIC ---
    if ($_POST['form_action'] === 'register') {
        $full_name = trim($_POST['full_name']);
        $motorcycle_number = trim($_POST['motorcycle_number']);
        $phone_number = trim($_POST['phone_number']);
        $password = $_POST['password'];
        $action = 'register'; // Keep the register form visible after submission

        if (empty($full_name) || empty($motorcycle_number) || empty($phone_number) || empty($password)) {
            $error_message = 'All fields are required.';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM tracking_users WHERE motorcycle_number = ? OR phone_number = ?");
            $stmt->execute([$motorcycle_number, $phone_number]);
            if ($stmt->fetch()) {
                $error_message = 'This motorcycle number or phone number is already registered.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO tracking_users (full_name, motorcycle_number, phone_number, password) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$full_name, $motorcycle_number, $phone_number, $hashed_password])) {
                    $success_message = 'Registration successful! You can now log in.';
                } else {
                    $error_message = 'An error occurred. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $action === 'register' ? 'Register' : 'Login'; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background-color: #f4f7fa; }
        .auth-container { background-color: white; padding: 40px; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); width: 340px; text-align: center; }
        h1 { margin-bottom: 25px; color: #333; }
        .form-group { margin-bottom: 18px; text-align: left; }
        label { display: block; margin-bottom: 6px; font-weight: 500; color: #555;}
        input { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; padding: 14px; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: bold; }
        .btn-login { background-color: #0d6efd; }
        .btn-register { background-color: #198754; }
        .message { padding: 12px; border-radius: 6px; margin-bottom: 20px; text-align: left; }
        .error { background-color: #f8d7da; color: #721c24; }
        .success { background-color: #d1e7dd; color: #0f5132; }
        .switch-link { margin-top: 25px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="auth-container">
        <h1><?php echo $action === 'register' ? 'Create Account' : 'Login'; ?></h1>
        
        <?php if ($error_message): ?><p class="message error"><?php echo htmlspecialchars($error_message); ?></p><?php endif; ?>
        <?php if ($success_message): ?><p class="message success"><?php echo htmlspecialchars($success_message); ?></p><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="form_action" value="<?php echo $action; ?>">

            <?php if ($action === 'register'): ?>
                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" required>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="motorcycle_number">Motorcycle Plate Number</label>
                <input type="text" id="motorcycle_number" name="motorcycle_number" required>
            </div>

            <?php if ($action === 'register'): ?>
                <div class="form-group">
                    <label for="phone_number">Phone Number</label>
                    <input type="tel" id="phone_number" name="phone_number" required>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <?php if ($action === 'register'): ?>
                <button type="submit" class="btn-register">Register</button>
            <?php else: ?>
                <button type="submit" class="btn-login">Log In</button>
            <?php endif; ?>
        </form>

        <div class="switch-link">
            <?php if ($action === 'register'): ?>
                <p>Already have an account? <a href="../auth/login_register.php">Log In</a></p>
            <?php else: ?>
                <p>Don't have an account? <a href="../auth/login_register.php?action=register">Register Now</a></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>