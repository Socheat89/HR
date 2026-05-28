<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'samann1_admin_panel';
$username = 'root';
$password = '';

// Telegram bot configurations
const TELEGRAM_BOT_TOKEN = "7886992632:AAFAlFae5FReigReJqPH8-QsKXowReyUNV0";
const CHAT_ID = "-1002296068912";

// Check if user is logged in
$currentUser = isset($_SESSION['username']) ? $_SESSION['username'] : 'Unknown User';
if ($currentUser === 'Unknown User') {
    header("Location: ../auth/login.php");
    exit;
}

// Connect to database with UTF-8 support
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create survey_responses table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS survey_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        response TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    $conn->exec($sql);
} catch (PDOException $e) {
    die("сс╢ссПсссс╢ссссс╢ссРс: " . $e->getMessage());
}

// Fetch full name based on session username
try {
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE username = :username LIMIT 1");
    $stmt->bindParam(':username', $currentUser);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $full_name = $user ? $user['full_name'] : 'свссссссс╛сс╖ссссс╢сс';
} catch (PDOException $e) {
    $error = "сссас╗ссссс╗ссс╢ссс╢сссссс: " . $e->getMessage();
}

// Function to send message to Telegram
function sendToTelegram($message)
{
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = [
        "chat_id" => CHAT_ID,
        "text" => $message,
        "parse_mode" => "HTML"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && $full_name !== 'свссссссс╛сс╖ссссс╢сс') {
    $response = filter_input(INPUT_POST, 'response', FILTER_SANITIZE_STRING);

    if ($response) {
        try {
            $stmt = $conn->prepare("INSERT INTO survey_responses (full_name, response) VALUES (:full_name, :response)");
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':response', $response);
            $stmt->execute();

            // Prepare Telegram message
            $telegram_message = "Ё сс╢ссссссссПс╖сРссс╕\n";
            $telegram_message .= "сссссБсссПс╖ссс: " . $full_name . "\n";
            $telegram_message .= "сссс╢сссвс╢ссссссН: " . $response . "\n";
            $telegram_message .= "ссБсссБсс╢: " . date('Y-m-d H:i:s');

            sendToTelegram($telegram_message);

            $success = "сс╢ссссссссПс╖сс╢ссссссРс!";
        } catch (PDOException $e) {
            $error = "сссас╗с: " . $e->getMessage();
        }
    } else {
        $error = "сс╝сссссс╝сссПс╖!";
    }
}
?>

<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title> - HR App</title>

    <!-- Frameworks & Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- Favicon & Theme Color -->
    <link rel="icon" type="image/png" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
    <meta name="theme-color" content="#6366f1">

    <style>
        /* === SHARED DESIGN SYSTEM === */
        :root {
            --primary: #6366f1;
            --primary-light: #8b5cf6;
            --primary-dark: #4f46e5;
            --secondary: #06b6d4;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --dark: #0f172a;
            --light: #f8fafc;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-500: #64748b;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --border-radius-xl: 20px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Kantumruy Pro', sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            color: var(--gray-800);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Prevent horizontal scrolling */
        html,
        body {
            overflow-x: hidden;
            width: 100%;
        }

        .app-container {
            width: 100%;
            max-width: 500px;
            padding: 24px;
            padding-bottom: 90px;
        }

        /* === HEADER STYLES === */
        .app-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius-xl);
            padding: 16px 24px;
            margin-bottom: 32px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 16px;
            text-decoration: none;
        }

        .logo-img {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius);
            object-fit: cover;
            box-shadow: var(--shadow);
        }

        .app-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .btn-logout {
            color: var(--danger);
            font-size: 1.1rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: all 0.2s;
            padding: 8px 16px;
            border-radius: var(--border-radius);
            background: rgba(239, 68, 68, 0.1);
        }

        .btn-logout:hover {
            background: var(--danger);
            color: white;
        }

        /* === CARD === */
        .content-card {
            background: white;
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--gray-200);
            padding: 32px;
            box-shadow: var(--shadow-lg);
            animation: bounceIn 0.8s;
            text-align: center;
        }

        .user-greeting {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--gray-900);
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--gray-700);
            display: block;
            text-align: left;
        }

        .form-control {
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-300);
            padding: 1rem;
            transition: all 0.2s;
            font-size: 1rem;
            resize: none;
            background: var(--gray-50);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            outline: none;
            background: white;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border: none;
            color: white;
            padding: 1rem 2rem;
            border-radius: var(--border-radius);
            border-radius: var(--border-radius);
            padding: 16px;
            font-weight: 700;
            width: 100%;
            margin-top: 24px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            filter: brightness(1.1);
        }

        /* === BOTTOM NAV === */
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            justify-content: space-around;
            padding: 8px 0;
            box-shadow: 0 -2px 16px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            border-top-left-radius: var(--border-radius-lg);
            border-top-right-radius: var(--border-radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
            min-height: 64px;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--gray-500);
            font-size: 0.75rem;
            font-weight: 600;
            flex: 1;
            padding: 6px;
        }

        .nav-item.active {
            color: var(--primary);
        }

        .nav-icon {
            font-size: 1.3rem;
            margin-bottom: 2px;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .app-container {
                padding: 12px;
                padding-bottom: 90px;
            }

            .app-header {
                padding: 12px 16px;
                margin-bottom: 20px;
            }

            .logo-img {
                width: 40px;
                height: 40px;
            }

            .app-title {
                font-size: 1.25rem;
            }

            .bottom-nav {
                display: flex;
            }

            .survey-card {
                padding: 24px;
            }
        }
    </style>
</head>

<body>

    <div class="app-container">
        <!-- Modern Header -->
        <header class="app-header animate__animated animate__fadeInDown">
            <a href="homes.php" class="logo-container">
                <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="Logo" class="logo-img">
                <h1 class="app-title"></h1>
            </a>
            <a href="homes.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                <i class="fas fa-arrow-left me-1"></i> 
            </a>
        </header>

        <!-- Status Messages -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-4 animate__animated animate__zoomIn mb-4">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-4 animate__animated animate__shakeX mb-4">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Survey Card -->
        <?php if ($full_name !== 'свссссссс╛сс╖ссссс╢сс'): ?>
            <div class="survey-card">
                <div class="survey-instruction">
                    <i class="fas fa-info-circle me-2"></i>
                     
                </div>

                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label"><i class="fas fa-user"></i> </label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($full_name); ?>" readonly
                            disabled>
                    </div>

                    <div class="mb-4">
                        <label class="form-label"><i class="fas fa-comment-dots"></i> </label>
                        <textarea name="response" class="form-control" rows="6"
                            placeholder=" ..." required></textarea>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i>
                        
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-4 animate__animated animate__shakeX mb-4">
                <i class="fas fa-exclamation-circle me-2"></i>
                сс╖ссвс╢ссссс╛сссссссвссссссс╛!
                сс╝ссс╝ссссПссссПс
            </div>
        <?php endif; ?>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="homes.php" class="nav-item">
            <i class="fas fa-home nav-icon"></i>
            <span></span>
        </a>
        <a href="../requests/requests_menu.php" class="nav-item">
            <i class="fas fa-clipboard-list nav-icon"></i>
            <span></span>
        </a>
        <a href="../system/checklist.php" class="nav-item">
            <i class="fas fa-tasks nav-icon"></i>
            <span></span>
        </a>
        <a href="https://app.vvc.asia/admin/profile.php" class="nav-item">
            <i class="fas fa-user nav-icon"></i>
            <span></span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>