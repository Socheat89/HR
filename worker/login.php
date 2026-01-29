<?php
// login.php

// បង្ហាញកំហុសដើម្បីងាយស្រួលរកបញ្ហា
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
// << កំណត់ Timezone សម្រាប់ប្រទេសកម្ពុជា (ចំណុចកែប្រែទី១)
date_default_timezone_set('Asia/Phnom_Penh');
// រួមបញ្ចូលឯកសារជំនួយសម្រាប់ Telegram ដែលយើងបានបង្កើត
require_once 'telegram_helper.php';

// បើឡុកអ៊ីនរួចហើយ, បញ្ជូនទៅផ្ទាំងគ្រប់គ្រង
if (isset($_SESSION['sub_user_logged_in'])) {
    header('Location: Panel.php');
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
            // ទាញយកព័ត៌មាន រួមទាំង allowed_usernames មកជាមួយ
            $stmt = $pdo->prepare("SELECT id, username, password, allowed_usernames FROM sub_users WHERE username = ?");
            
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Login ជោគជ័យ
                session_regenerate_id(true); // ការពារ Session Fixation
                
                $_SESSION['sub_user_logged_in'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // រក្សាទុក permissions ទៅក្នុង Session
                $_SESSION['allowed_username'] = $user['allowed_usernames'];
                
                // បង្កើត CSRF token ថ្មីបន្ទាប់ពីឡុកអ៊ីន
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                // ===================================================================
                // === START: កូដសម្រាប់ផ្ញើការជូនដំណឹងទៅ TELEGRAM ===
                // ===================================================================
                
                // !!! សូមជំនួសតម្លៃទាំងពីរខាងក្រោមនេះ !!!
                $telegramBotToken = '7680086124:AAHrvdz-mOx3pO1Ijqvh7BHTeGh2JB5JuwQ'; // ដាក់ Bot Token របស់អ្នក
                $telegramChatId   = '-1002802610249';    // ដាក់ Chat ID របស់អ្នក

                // យកព័ត៌មានបន្ថែមដើម្បីដាក់ក្នុងសារ
                $ipAddress = $_SERVER['REMOTE_ADDR'];
                       // << កែសម្រួល Format ម៉ោងឱ្យមាន AM/PM (ចំណុចកែប្រែទី២)
                $loginTime = date('d-m-Y h:i:s A');

                // រៀបចំសារជូនដំណឹង (ប្រើ HTML tags សម្រាប់ធ្វើឱ្យស្អាត)
                $message  = "<b>🔔 User Login Notification</b>\n\n";
                $message .= "A sub-user has successfully logged into the system.\n\n";
                $message .= "<b>👤 Username:</b> <code>" . htmlspecialchars($user['username']) . "</code>\n";
                $message .= "<b>⏰ Time:</b> <code>" . $loginTime . "</code>\n";
                $message .= "<b>🌐 IP Address:</b> <code>" . htmlspecialchars($ipAddress) . "</code>\n";
                
                // ហៅ Function ដើម្បីផ្ញើសារ
                // យើងដាក់ @ ពីមុខដើម្បីការពារកុំឲ្យបង្ហាញ Error បើសិនជាការផ្ញើបរាជ័យ
                // ការធ្វើបែបនេះធានាថាអ្នកប្រើប្រាស់នៅតែអាច Login បាន ទោះបីជា Telegram notification មានបញ្ហាក៏ដោយ
                @sendTelegramNotification($telegramBotToken, $telegramChatId, $message);

                // =================================================================
                // === END: កូដសម្រាប់ផ្ញើការជូនដំណឹងទៅ TELEGRAM ===
                // =================================================================

                // បញ្ជូនទៅកាន់ Panels.php
                header('Location: Panel.php');
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
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts (Battambang) -->
    <link href="https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --background-gradient-start: #71b7e6;
            --background-gradient-end: #9b59b6;
            --card-background: rgba(255, 255, 255, 0.95);
            --card-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            --font-family-khmer: 'Battambang', cursive;
        }
        body {
            font-family: var(--font-family-khmer);
            background: linear-gradient(135deg, var(--background-gradient-start), var(--background-gradient-end));
            display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; padding: 1rem;
        }
        .login-container { max-width: 450px; width: 100%; }
        .login-card {
            background-color: var(--card-background); padding: 2.5rem; box-shadow: var(--card-shadow);
            border-radius: 15px; border-top: 5px solid var(--primary-color); backdrop-filter: blur(5px);
        }
        .login-card .card-title { font-weight: 700; font-size: 1.8rem; color: #333; margin-bottom: 1.5rem; }
        .login-card .card-title i { margin-right: 10px; color: var(--primary-color); }
        .form-control { height: 50px; border-radius: 8px; }
        .form-control:focus { box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25); border-color: var(--primary-color); }
        .input-group-text { background-color: #e9ecef; border: 1px solid #ced4da; width: 50px; justify-content: center; }
        #togglePassword { cursor: pointer; }
        .input-group > .form-control { border-top-right-radius: 0; border-bottom-right-radius: 0; }
        .input-group > #togglePassword { border-top-left-radius: 0; border-bottom-left-radius: 0; border-left: 0; }
        .btn-primary {
            background-color: var(--primary-color); border-color: var(--primary-color); padding: 12px;
            font-size: 1.1rem; font-weight: 700; border-radius: 8px; transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #0b5ed7; border-color: #0a58ca;
            transform: translateY(-2px); box-shadow: 0 4px 10px rgba(13, 110, 253, 0.4);
        }
        .alert-danger { display: flex; align-items: center; }
        .alert-danger i { margin-right: 0.5rem; }
        .form-label { font-weight: 700; color: var(--secondary-color); }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <h2 class="card-title text-center"><i class="fas fa-shield-alt"></i> ចូលប្រើប្រាស់ប្រព័ន្ធ</h2>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div><?php echo htmlspecialchars($error_message); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="mb-3">
                    <label for="username" class="form-label">ឈ្មោះអ្នកប្រើប្រាស់</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control" id="username" name="username" placeholder="បញ្ចូលឈ្មោះអ្នកប្រើប្រាស់" required>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">ពាក្យសម្ងាត់</label>
                    <div class="input-group">
                         <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="បញ្ចូលពាក្យសម្ងាត់" required>
                        <span class="input-group-text" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> ចូលប្រើប្រាស់
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const togglePassword = document.querySelector('#togglePassword');
            const passwordInput = document.querySelector('#password');
            if (togglePassword) {
                togglePassword.addEventListener('click', function() {
                    const icon = this.querySelector('i');
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    icon.classList.toggle('fa-eye-slash');
                    icon.classList.toggle('fa-eye');
                });
            }
        });
    </script>
</body>
</html>