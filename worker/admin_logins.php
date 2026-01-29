<?php
// login.php

// បង្ហាញកំហុសដើម្បីងាយស្រួលរកបញ្ហា
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// បើឡុកអ៊ីនរួចហើយ, បញ្ជូនទៅផ្ទាំងគ្រប់គ្រង
if (isset($_SESSION['sub_user_logged_in'])) {
    header('Location: Panels.php');
    exit();
}

// បង្កើត CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database Connection
$db_host = 'localhost';
$db_user = 'samann1_scan_logs_worker_db';
$db_pass = 'scan_logs_worker_db@2025';
$db_name = 'samann1_scan_logs_worker_db';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("ការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យបរាជ័យ: " . $e->getMessage());
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ពិនិត្យ CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = 'CSRF token មិនត្រឹមត្រូវ!';
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $error_message = 'សូមបញ្ចូលឈ្មោះអ្នកប្រើប្រាស់ និងពាក្យសម្ងាត់។';
        } else {
            // === START: ចំណុចសំខាន់បំផុត ===
            // ទាញយកព័ត៌មាន រួមទាំង allowed_usernames មកជាមួយ
            $stmt = $pdo->prepare("SELECT id, username, password, allowed_usernames FROM sub_users WHERE username = ?");
            // === END: ចំណុចសំខាន់បំផុត ===
            
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Login ជោគជ័យ
                session_regenerate_id(true); // ការពារ Session Fixation
                
                $_SESSION['sub_user_logged_in'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // === START: ចំណុចសំខាន់បំផុតទី២ ===
                // រក្សាទុក permissions ទៅក្នុង Session
                $_SESSION['allowed_username'] = $user['allowed_usernames'];
                // === END: ចំណុចសំខាន់បំផុតទី២ ===
                
                // បង្កើត CSRF token ថ្មីបន្ទាប់ពីឡុកអ៊ីន
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                // បញ្ជូនទៅកាន់ Panels.php
                header('Location: Panels.php');
                exit();
            } else {
                $error_message = 'ឈ្មោះអ្នកប្រើប្រាស់ ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវ។';
            }
        }
    }
    // បង្កើត CSRF token ថ្មីឡើងវិញបន្ទាប់ពីព្យាយាមឡុកអ៊ីន
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ចូលប្រើប្រាស់សម្រាប់ Sub-User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Battambang&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: 'Battambang', cursive; background-color: #f0f2f5; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { max-width: 400px; width: 100%; padding: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-radius: 8px; background-color: #fff; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2 class="text-center mb-4"><i class="fas fa-user-lock"></i> ចូលប្រើប្រាស់</h2>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
                <label for="username" class="form-label">ឈ្មោះអ្នកប្រើប្រាស់</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">ពាក្យសម្ងាត់</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-primary">ចូលប្រើប្រាស់</button>
            </div>
        </form>
    </div>
</body>
</html>