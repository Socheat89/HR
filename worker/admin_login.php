<?php
// ===================================================================
// SECTION 1: CONFIGURATION & INITIALIZATION
// ===================================================================

// កំណត់ព័ត៌មានសម្រាប់តភ្ជាប់ទៅកាន់មូលដ្ឋានទិន្នន័យ
define('DB_HOST', 'localhost');
define('DB_NAME', 'samann1_scan_logs_worker_db');
define('DB_USER', 'samann1_scan_logs_worker_db');
define('DB_PASS', 'scan_logs_worker_db@2025');

// ===================================================================
// START: បន្ថែមកូដថ្មីសម្រាប់ Telegram ===============================
// ===================================================================
// សូម​ប្តូរ​តម្លៃ​ខាង​ក្រោម​នេះ​ជាមួយ​នឹង Bot Token និង Chat ID របស់​អ្នក
define('TELEGRAM_BOT_TOKEN', '7680086124:AAHrvdz-mOx3pO1Ijqvh7BHTeGh2JB5JuwQ'); // <-- ដាក់ Bot Token របស់អ្នកនៅទីនេះ
define('TELEGRAM_CHAT_ID', '-1002802610249');   // <-- ដាក់ Chat ID របស់អ្នកនៅទីនេះ

/**
 * Function សម្រាប់ផ្ញើសារទៅកាន់ Telegram
 * @param string $message សារដែលត្រូវផ្ញើ
 */
function sendTelegramMessage($message) {
    // ពិនិត្យមើលថា Token ឬ Chat ID មិនមែនជាតម្លៃគំរូ
    if (TELEGRAM_BOT_TOKEN === 'YOUR_TELEGRAM_BOT_TOKEN' || TELEGRAM_CHAT_ID === 'YOUR_TELEGRAM_CHAT_ID') {
        // មិនធ្វើអ្វីសោះ ប្រសិនបើការកំណត់មិនទាន់បានបំពេញ
        return;
    }

    $botToken = TELEGRAM_BOT_TOKEN;
    $chatId = TELEGRAM_CHAT_ID;
    
    // URL សម្រាប់ហៅ Telegram API
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    
    // ទិន្នន័យដែលត្រូវផ្ញើ (ប្រើ parse_mode=MarkdownV2 ដើម្បីឲ្យសារមើលទៅស្អាត)
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'MarkdownV2'
    ];
    
    // បង្កើត URL ពេញលេញជាមួយ Query Parameters
    $fullUrl = $url . '?' . http_build_query($data);

    // ប្រើ cURL ដើម្បីផ្ញើ Request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fullUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // កំណត់ Timeout
    curl_exec($ch);
    curl_close($ch);
}
// ===================================================================
// END: បន្ថែមកូដថ្មីសម្រាប់ Telegram =================================
// ===================================================================


// ចាប់ផ្តើម Session ដើម្បីគ្រប់គ្រងការចូលប្រព័ន្ធ
session_start();


// ===================================================================
// SECTION 2: BACKEND LOGIC (PHP)
// ===================================================================

// បង្កើត CSRF Token ប្រសិនបើមិនទាន់មាន
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ប្រសិនបើអ្នកគ្រប់គ្រងបានចូលប្រព័ន្ធរួចហើយ បញ្ជូនទៅកាន់ទំព័រ Panel.php ភ្លាម
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: Panel.php');
    exit;
}

$error_message = '';

// ពិនិត្យមើលថាតើ Form ត្រូវបានបញ្ជូន (Submit) ដែរឬទេ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ផ្ទៀងផ្ទាត់ CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $error_message = 'ការបញ្ជូនទិន្នន័យមិនត្រឹមត្រូវ! សូមព្យាយាមម្តងទៀត។';
    } elseif (empty($_POST['username']) || empty($_POST['password'])) {
        $error_message = 'សូមបំពេញឈ្មោះអ្នកប្រើប្រាស់ និងពាក្យសម្គាត់។';
    } else {
        $username = $_POST['username'];
        $password = $_POST['password'];

        try {
            // ប្រើប្រាស់ค่าដែលបាន define ខាងលើ
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            $stmt = $pdo->prepare("SELECT id, username, password FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                // បង្កើត Session ID ថ្មី ដើម្បីការពារ Session Fixation
                session_regenerate_id(true);

                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                
                // ===================================================================
                // START: បន្ថែមកូដថ្មី សម្រាប់ផ្ញើ Alert ទៅ Telegram ===============
                // ===================================================================
                
                // ប្រមូលព័ត៌មានសម្រាប់ផ្ញើ
                $loggedInUsername = $admin['username'];
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown IP';
                $dateTime = date('Y-m-d H:i:s');
                $hostname = gethostname() ?: 'Unknown Host';

                // រៀបចំ format សារ (ប្រើ MarkdownV2)
                // ចំណាំ៖ តួអក្សរពិសេសមួយចំនួនដូចជា . - ( ) ត្រូវតែមាន \ នៅពីមុខ
                $message = "✅ *Successful Login Alert*\n\n"
                         . "*Username:* `" . str_replace(['-','.'], ['\-','\.'], $loggedInUsername) . "`\n"
                         . "*IP Address:* `" . str_replace(['-','.'], ['\-','\.'], $ipAddress) . "`\n"
                         . "*Date & Time:* `" . str_replace(['-','.'], ['\-','\.'], $dateTime) . " UTC`\n"
                         . "*Server:* `" . str_replace(['-','.'], ['\-','\.'], $hostname) . "`";

                // ហៅ Function ដើម្បីផ្ញើសារ
                sendTelegramMessage($message);
                
                // ===================================================================
                // END: បន្ថែមកូដថ្មី =================================================
                // ===================================================================

                header('Location: Panel.php');
                exit;
            } else {
                $error_message = 'ឈ្មោះអ្នកប្រើប្រាស់ ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវទេ។';
            }
        } catch (PDOException $e) {
            $error_message = 'មានបញ្ហាក្នុងការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ។';
            // សម្រាប់ Developer: error_log('DB Connection Error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ចូលគណនីអ្នកគ្រប់គ្រង</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --background-gradient: linear-gradient(135deg, #71b7e6, #9b59b6);
            --card-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        body {
            font-family: 'Battambang', sans-serif;
            background: var(--background-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 1rem;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            border: none;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
            background-color: #ffffff;
            overflow: hidden;
        }
        .card-header-custom {
            text-align: center;
            padding: 2rem 1.5rem 1.5rem;
        }
        .header-icon {
            font-size: 3.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        .card-header-custom h3 {
            font-weight: 700;
            color: #333;
        }
        .card-body {
            padding: 0 2rem 2.5rem;
        }
        .password-wrapper {
            position: relative;
        }
        #password-toggle {
            position: absolute;
            top: 50%;
            right: 15px;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--secondary-color);
            z-index: 100;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            border-color: #86b7fe;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.75rem;
            font-weight: 700;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.35);
        }
        .btn-primary:disabled .spinner-border {
            width: 1.2rem;
            height: 1.2rem;
        }
        .alert-danger {
            font-size: 0.9rem;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="card login-card">
    <div class="card-header-custom">
        <div class="header-icon">
            <i class="fa-solid fa-user"></i>
        </div>
        <h3>ផ្ទាំងគ្រប់គ្រង</h3>
    </div>
    <div class="card-body">
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="mb-3">
                <label for="username" class="form-label">ឈ្មោះអ្នកប្រើប្រាស់</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" id="username" name="username" placeholder="សូមបញ្ចូលឈ្មោះគណនី" required>
                </div>
            </div>
            
            <div class="mb-4">
                <label for="password" class="form-label">ពាក្យសម្ងាត់</label>
                <div class="password-wrapper">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" placeholder="សូមបញ្ចូលពាក្យសម្ងាត់" required>
                    </div>
                    <i class="fas fa-eye" id="password-toggle"></i>
                </div>
            </div>
            
            <div class="d-grid">
                <button type="submit" id="submitBtn" class="btn btn-primary btn-lg">
                    <span class="btn-text">ចូលប្រព័ន្ធ</span>
                    <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.getElementById('password-toggle');
    const loginForm = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = submitBtn.querySelector('.btn-text');
    const spinner = submitBtn.querySelector('.spinner-border');

    // Password Visibility Toggle
    if (passwordToggle) {
        passwordToggle.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }

    // Loading state on form submit
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            // Check basic client-side validity before showing spinner
            if(loginForm.checkValidity()) {
                submitBtn.disabled = true;
                btnText.textContent = 'កំពុងដំណើរការ...';
                spinner.classList.remove('d-none');
            }
        });
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>