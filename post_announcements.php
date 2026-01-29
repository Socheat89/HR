<?php
session_start([
    'cookie_secure' => true, // Enable if using HTTPS
    'cookie_httponly' => true,
    'use_strict_mode' => true,
]);

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'សូមចូលគណនីសិន!';
    header("Location: login.php");
    exit();
}

if (isset($_GET['logout']) && hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Database connection
try {
    $db = new PDO("mysql:host=localhost;dbname=samann1_admin_panel;charset=utf8mb4", "samann1_admin_panel", "admin_panel@2025");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage(), 3, '/var/log/php_errors.log');
    $_SESSION['error'] = 'កំហុសបច្ចេកទេស! សូមទាក់ទងអ្នកគ្រប់គ្រង។';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Function to send Telegram message
function sendTelegramMessage($botToken, $chatId, $threadId, $message, $inlineKeyboard = null) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'message_thread_id' => $threadId,
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true,
    ];

    if ($inlineKeyboard) {
        $data['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200 || json_decode($response, true)['ok'] !== true) {
        error_log("Telegram API error: HTTP $httpCode, Response: $response, CURL Error: $error", 3, '/var/log/php_errors.log');
        return false;
    }
    return true;
}

// Function to get user names from user IDs
function getUserNames($db, $userIds) {
    if (empty($userIds)) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($userIds), '?'));
    $stmt = $db->prepare("SELECT full_name FROM users WHERE id IN ($placeholders)");
    $stmt->execute($userIds);
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'full_name');
}

// Telegram configuration
$telegramBotToken = '7599531092:AAHkvzpFsSwZHxHXRPvJJpKSQH-KO-HPuAM';
$telegramChatId = '-1001167276739';
$telegramThreadId = 80296;

// Fetch users
$stmt = $db->prepare("SELECT id, full_name FROM users WHERE id != :current_user_id");
$stmt->execute(['current_user_id' => (int)$_SESSION['user_id']]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle create announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create' && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    if (!isset($_POST['title'], $_POST['date'], $_POST['text'], $_POST['user_ids'])) {
        $_SESSION['error'] = 'សូមបំពេញគ្រប់វាលទាំងអស់!';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    $user_ids = array_filter((array)$_POST['user_ids'], fn($id) => is_numeric($id) && $id > 0);
    if (empty($user_ids)) {
        $_SESSION['error'] = 'សូមជ្រើសរើសបុគ្គលិកយ៉ាងតិចម្នាក់!';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("INSERT INTO announcements (title, date, text) VALUES (:title, :date, :text)");
        $stmt->execute([
            'title' => htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8'),
            'date' => htmlspecialchars($_POST['date'], ENT_QUOTES, 'UTF-8'),
            'text' => htmlspecialchars($_POST['text'], ENT_QUOTES, 'UTF-8')
        ]);
        $announcement_id = $db->lastInsertId();
        $stmt = $db->prepare("INSERT INTO announcement_users (announcement_id, user_id) VALUES (:announcement_id, :user_id)");
        foreach ($user_ids as $user_id) {
            $stmt->execute([
                'announcement_id' => $announcement_id,
                'user_id' => (int)$user_id
            ]);
        }

        // Prepare Telegram message
        $title = htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8');
        $date = htmlspecialchars($_POST['date'], ENT_QUOTES, 'UTF-8');
        $text = htmlspecialchars($_POST['text'], ENT_QUOTES, 'UTF-8');
        $user_names = getUserNames($db, $user_ids);
        $user_list = $user_names ? "\n👥 <b>អ្នកទទួល</b>: " . implode(', ', $user_names) : '';
        
        $message = "📢 <b>ការជូនដំណឹងថ្មី: $title</b>\n"
                 . "🗓 <i>$date</i>\n"
                 . "📝 <b>ខ្លឹមសារ</b>:\n$text\n"
                 . $user_list;
        $message = mb_substr($message, 0, 4096, 'UTF-8');

        // Inline keyboard
        $inlineKeyboard = [
            [
                ['text' => 'មើលលម្អិត', 'url' =>'https://app.vvc.asia/announcements.php?id=' . $announcement_id],
              
            ]
        ];

        // Send to Telegram
        if (sendTelegramMessage($telegramBotToken, $telegramChatId, $telegramThreadId, $message, $inlineKeyboard)) {
            $_SESSION['success'] = 'ការជូនដំណឹងបានបង្ហោះ និងផ្ញើទៅ Telegram ដោយជោគជ័យ!';
        } else {
            $_SESSION['error'] = 'ការជូនដំណឹងបានបង្ហោះ ប៉ុន្តែមានបញ្ហាក្នុងការផ្ញើទៅ Telegram!';
        }

        $db->commit();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Create error: " . $e->getMessage(), 3, '/var/log/php_errors.log');
        $_SESSION['error'] = 'កំហុសក្នុងការបង្ហោះ! សូមព្យាយាមម្តងទៀត។';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Handle edit announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit' && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    if (!isset($_POST['announcement_id'], $_POST['title'], $_POST['date'], $_POST['text'], $_POST['user_ids'])) {
        $_SESSION['error'] = 'សូមបំពេញគ្រប់វាលទាំងអស់!';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    $user_ids = array_filter((array)$_POST['user_ids'], fn($id) => is_numeric($id) && $id > 0);
    if (empty($user_ids)) {
        $_SESSION['error'] = 'សូមជ្រើសរើសបុគ្គលិកយ៉ាងតិចម្នាក់!';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("UPDATE announcements SET title = :title, date = :date, text = :text WHERE id = :id");
        $stmt->execute([
            'id' => (int)$_POST['announcement_id'],
            'title' => htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8'),
            'date' => htmlspecialchars($_POST['date'], ENT_QUOTES, 'UTF-8'),
            'text' => htmlspecialchars($_POST['text'], ENT_QUOTES, 'UTF-8')
        ]);
        $stmt = $db->prepare("DELETE FROM announcement_users WHERE announcement_id = :announcement_id");
        $stmt->execute(['announcement_id' => (int)$_POST['announcement_id']]);
        $stmt = $db->prepare("INSERT INTO announcement_users (announcement_id, user_id) VALUES (:announcement_id, :user_id)");
        foreach ($user_ids as $user_id) {
            $stmt->execute([
                'announcement_id' => (int)$_POST['announcement_id'],
                'user_id' => (int)$user_id
            ]);
        }

        // Prepare Telegram message
        $title = htmlspecialchars($_POST['title'], ENT_QUOTES, 'UTF-8');
        $date = htmlspecialchars($_POST['date'], ENT_QUOTES, 'UTF-8');
        $text = htmlspecialchars($_POST['text'], ENT_QUOTES, 'UTF-8');
        $user_names = getUserNames($db, $user_ids);
        $user_list = $user_names ? "\n👥 <b>អ្នកទទួល</b>: " . implode(', ', $user_names) : '';
        
        $message = "📢 <b>ការជូនដំណឹងបានកែប្រែ: $title</b>\n"
                 . "🗓 <i>$date</i>\n"
                 . "📝 <b>ខ្លឹមសារ</b>:\n$text\n"
                 . $user_list;
        $message = mb_substr($message, 0, 4096, 'UTF-8');

        // Inline keyboard
        $inlineKeyboard = [
            [
                ['text' => 'មើលលម្អិត', 'url' =>'https://app.vvc.asia/announcements.php?id=' . $_POST['announcement_id']],
             
            ]
        ];

        // Send to Telegram
        if (sendTelegramMessage($telegramBotToken, $telegramChatId, $telegramThreadId, $message, $inlineKeyboard)) {
            $_SESSION['success'] = 'ការជូនដំណឹងបានកែប្រែ និងផ្ញើទៅ Telegram ដោយជោគជ័យ!';
        } else {
            $_SESSION['error'] = 'ការជូនដំណឹងបានកែប្រែ ប៉ុន្តែមានបញ្ហាក្នុងការផ្ញើទៅ Telegram!';
        }

        $db->commit();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Edit error: " . $e->getMessage(), 3, '/var/log/php_errors.log');
        $_SESSION['error'] = 'កំហុសក្នុងការកែប្រែ! សូមព្យាយាមម្តងទៀត។';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Handle delete announcement
if (isset($_GET['delete']) && is_numeric($_GET['delete']) && hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    $db->beginTransaction();
    try {
        // Fetch announcement details for Telegram message
        $stmt = $db->prepare("SELECT title FROM announcements WHERE id = :id");
        $stmt->execute(['id' => (int)$_GET['delete']]);
        $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
        $title = $announcement ? htmlspecialchars($announcement['title'], ENT_QUOTES, 'UTF-8') : 'Unknown';

        $stmt = $db->prepare("DELETE FROM announcements WHERE id = :id");
        $stmt->execute(['id' => (int)$_GET['delete']]);
        $stmt = $db->prepare("DELETE FROM announcement_users WHERE announcement_id = :id");
        $stmt->execute(['id' => (int)$_GET['delete']]);

        // Prepare Telegram message
        $message = "🗑 <b>ការជូនដំណឹងបានលុប</b>\n"
                 . "📢 <b>ចំណងជើង</b>: $title\n"
                 . "🆔 ID: {$_GET['delete']}";
        $message = mb_substr($message, 0, 4096, 'UTF-8');

        // Send to Telegram
        if (!sendTelegramMessage($telegramBotToken, $telegramChatId, $telegramThreadId, $message)) {
            $_SESSION['error'] = 'ការជូនដំណឹងបានលុប ប៉ុន្តែមានបញ្ហាក្នុងការផ្ញើទៅ Telegram!';
        } else {
            $_SESSION['success'] = 'ការជូនដំណឹងបានលុបដោយជោគជ័យ!';
        }

        $db->commit();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Delete error: " . $e->getMessage(), 3, '/var/log/php_errors.log');
        $_SESSION['error'] = 'កំហុសក្នុងការលុប! សូមព្យាយាមម្តងទៀត។';
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Fetch all announcements with user names
try {
    $stmt = $db->prepare("
        SELECT a.id, a.title, a.date, a.text, GROUP_CONCAT(u.full_name) as full_names, GROUP_CONCAT(au.user_id) as user_ids
        FROM announcements a
        LEFT JOIN announcement_users au ON a.id = au.announcement_id
        LEFT JOIN users u ON au.user_id = u.id
        GROUP BY a.id
        ORDER BY a.created_at DESC
    ");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch error: " . $e->getMessage(), 3, '/var/log/php_errors.log');
    $_SESSION['error'] = 'កំហុសក្នុងការទាញទិន្នន័យ! សូមព្យាយាមម្តងទៀត។';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4f46e5">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
    <title>បង្ហោះការជូនដំណឹង | HRM Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css"/>
    <link rel="manifest" href="manifest.json">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --secondary: #4338ca;
            --accent: #06b6d4;
            --dark: #1e293b;
            --light: #f8fafc;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray: #64748b;
        }
       body {
    font-family: 'Noto Sans Khmer', 'Roboto', system-ui, -apple-system, sans-serif;
    background-color: var(--light);
    color: var(--dark);
    line-height: 1.6;
    margin: 0;
    padding: 0;
}
        .app-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            padding-bottom: 100px;
        }
        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            margin-bottom: 30px;
            position: relative;
        }
        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .logo-img {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            object-fit: cover;
        }
        /* For Khmer-specific text */
.khmer-text {
    font-family: 'Noto Sans Khmer', sans-serif;
}

/* For non-Khmer text (e.g., English labels) */
.english-text {
    font-family: 'Roboto', sans-serif;
}
        .app-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .user-avatar:hover {
            transform: scale(1.1);
        }
        .announcement-form {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        .announcements {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--dark);
        }
        .section-title i {
            color: var(--primary);
        }
        .announcement-item {
            padding: 15px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        .announcement-item:hover {
            background: rgba(79, 70, 229, 0.05);
        }
        .announcement-item:last-child {
            border-bottom: none;
        }
        .announcement-title {
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .announcement-title i {
            color: var(--primary);
            font-size: 0.9rem;
        }
        .announcement-date {
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 8px;
        }
        .announcement-text {
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .announcement-user {
            font-size: 0.85rem;
            color: var(--success);
            font-weight: 500;
            margin-bottom: 8px;
        }
        .form-group label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }
        .form-control {
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            padding: 10px;
            font-size: 0.95rem;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 5px rgba(79, 70, 229, 0.3);
        }
        .choices {
            font-size: 0.95rem;
        }
        .choices__inner {
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background-color: #fff;
            padding: 5px;
            min-height: 40px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }
        .choices__list--multiple .choices__item {
            background-color: #1877f2;
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            padding: 4px 8px;
            margin: 2px;
            font-size: 0.9rem;
        }
        .choices__list--multiple .choices__item--selectable::after {
            content: '\f00d';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            margin-left: 5px;
            font-size: 0.8rem;
            cursor: pointer;
        }
        .choices__list--dropdown {
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            margin-top: 5px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .choices__list--dropdown .choices__item--selectable {
            padding: 8px 12px;
            transition: background-color 0.2s ease;
        }
        .choices__list--dropdown .choices__item--selectable.is-highlighted {
            background-color: rgba(79, 70, 229, 0.1);
        }
        .choices__input {
            background-color: transparent;
            border: none;
            font-size: 0.95rem;
            padding: 5px;
            flex-grow: 1;
        }
        .choices__input:focus {
            outline: none;
        }
        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 70, 229, 0.3);
        }
        .btn-edit, .btn-delete {
            padding: 5px 10px;
            margin-left: 10px;
            border-radius: 8px;
            font-size: 0.9rem;
        }
        .btn-edit {
            background-color: var(--warning);
            color: white;
        }
        .btn-edit:hover {
            background-color: #d97706;
        }
        .btn-delete {
            background-color: var(--danger);
            color: white;
        }
        .btn-delete:hover {
            background-color: #dc2626;
        }
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            display: flex;
            justify-content: space-around;
            padding: 15px 0;
            box-shadow: 0 -5px 15px rgba(0, 0, 0, 0.05);
            z-index: 1000;
        }
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--gray);
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        .nav-item.active {
            color: var(--primary);
            transform: translateY(-5px);
        }
        .nav-icon {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        @media (max-width: 768px) {
            .app-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .user-profile {
                width: 100%;
                justify-content: flex-end;
            }
            .choices__inner {
                min-height: 50px;
            }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate__animated {
            animation: fadeIn 0.5s ease-out forwards;
        }
        a {
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Modern App Header -->
        <header class="app-header animate__animated animate__fadeIn">
            <a href="homes.php">
                <div class="logo-container">
                    <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="HRM Logo" class="logo-img">
                    <h1 class="app-title">បង្ហោះការជូនដំណឹង</h1>
                </div>
            </a>
            <div class="user-profile">
                <div class="user-avatar">
                    <a href="https://app.vvc.asia/admin/profile.php"><i class="fas fa-user"></i></a>
                </div>
                <a href="?logout=true&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-danger btn-sm">ចាកចេញ</a>
            </div>
        </header>
        
        <!-- Announcement Form -->
        <section class="announcement-form animate__animated animate__fadeIn">
            <h2 class="section-title">
            <i class="fas fa-plus-circle"></i>
            បង្កើតការជូនដំណឹងថ្មី
            </h2>
            <form method="POST" action="">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="form-group mb-3">
                <label for="user_ids">ជ្រើសរើសបុគ្គលិក</label>
                <select class="form-control" id="user_ids" name="user_ids[]" multiple required aria-label="ជ្រើសរើសបុគ្គលិក">
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                <?php endforeach; ?>
                </select>
                <small class="form-text text-muted">វាយឈ្មោះដើម្បីស្វែងរក និងជ្រើសរើសបុគ្គលិក</small>
            </div>
            <div class="form-group mb-3">
                <label for="title">ចំណងជើង</label>
                <input type="text" class="form-control" id="title" name="title" placeholder="បញ្ចូលចំណងជើង" required>
            </div>
            <div class="form-group mb-3">
                <label for="date">កាលបរិច្ឆេទ</label>
                <input type="text" class="form-control" id="date" name="date" placeholder="ឧ. ថ្ងៃចន្ទ ១៦ មិថុនា ២៥៦៨" required>
            </div>
            <div class="form-group mb-3">
                <label for="text">អត្ថបទ</label>
                <textarea class="form-control" id="text" name="text" rows="5" placeholder="បញ្ចូលអត្ថបទការជូនដំណឹង" required></textarea>
            </div>
            <button type="submit" class="btn btn-submit">បង្ហោះ</button>
            </form>
        </section>
    
        
        <!-- Announcements List -->
        <section class="announcements animate__animated animate__fadeIn">
            <h2 class="section-title">
                <i class="fas fa-bullhorn"></i>
                ការជូនដំណឹងដែលបានបង្ហោះ
            </h2>
            <?php if (empty($announcements)): ?>
                <p class="text-center text-muted">មិនទាន់មានការជូនដំណឹង។</p>
            <?php else: ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-item">
                        <h3 class="announcement-title">
                            <i class="fas fa-circle"></i>
                            <?php echo htmlspecialchars($announcement['title']); ?>
                            <div class="ms-auto">
                                <button class="btn btn-edit btn-sm" data-bs-toggle="modal" data-bs-target="#editModal" 
                                    data-id="<?php echo $announcement['id']; ?>" 
                                    data-title="<?php echo htmlspecialchars($announcement['title']); ?>" 
                                    data-date="<?php echo htmlspecialchars($announcement['date']); ?>" 
                                    data-text="<?php echo htmlspecialchars($announcement['text']); ?>" 
                                    data-user-ids="<?php echo htmlspecialchars($announcement['user_ids'] ?: ''); ?>">
                                    <i class="fas fa-edit"></i> កែ
                                </button>
                                <a href="?delete=<?php echo $announcement['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                   class="btn btn-delete btn-sm" 
                                   onclick="return confirm('តើអ្នកប្រាកដជាចង់លុបការជូនដំណឹងនេះមែនទេ?')">
                                    <i class="fas fa-trash"></i> លុប
                                </a>
                            </div>
                        </h3>
                        <div class="announcement-user">សម្រាប់: <?php echo htmlspecialchars($announcement['full_names'] ?: 'គ្មានអ្នកទទួល'); ?></div>
                        <div class="announcement-date"><?php echo htmlspecialchars($announcement['date']); ?></div>
                        <p class="announcement-text"><?php echo htmlspecialchars($announcement['text']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
    
    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">កែការជូនដំណឹង</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="announcement_id" id="edit_announcement_id">
                        <div class="form-group mb-3">
                            <label for="edit_user_ids">ជ្រើសរើសបុគ្គលិក</label>
                            <select class="form-control" id="edit_user_ids" name="user_ids[]" multiple required aria-label="ជ្រើសរើសបុគ្គលិក">
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label for="edit_title">ចំណងជើង</label>
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="edit_date">កាលបរិច្ឆេទ</label>
                            <input type="text" class="form-control" id="edit_date" name="date" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="edit_text">អត្ថបទ</label>
                            <textarea class="form-control" id="edit_text" name="text" rows="5" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-submit">រក្សាទុក</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav d-lg-none">
        <a href="homes.php" class="nav-item">
            <i class="fas fa-home nav-icon"></i>
            <span>ទំព័រដើម</span>
        </a>
        <a href="#" class="nav-item">
            <i class="fas fa-calendar nav-icon"></i>
            <span>កាលវិភាគ</span>
        </a>
        <a href="#" class="nav-item">
            <i class="fas fa-tasks nav-icon"></i>
            <span>ការងារ</span>
        </a>
        <a href="https://app.vvc.asia/admin/profile.php" class="nav-item">
            <i class="fas fa-user nav-icon"></i>
            <span>គណនី</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
    <script>
        // Initialize Choices.js for create form
        const userSelect = document.querySelector('#user_ids');
        const choices = new Choices(userSelect, {
            removeItemButton: true,
            searchEnabled: true,
            searchPlaceholderValue: 'វាយឈ្មោះបុគ្គលិក...',
            noResultsText: 'រកមិនឃើញបុគ្គលិក',
            itemSelectText: '',
            maxItemCount: -1,
            classNames: {
                containerOuter: 'choices',
                containerInner: 'choices__inner',
                input: 'choices__input',
                inputCloned: 'choices__input--cloned',
                list: 'choices__list',
                listItems: 'choices__list--multiple',
                listSingle: 'choices__list--single',
                listDropdown: 'choices__list--dropdown',
                item: 'choices__item',
                itemSelectable: 'choices__item--selectable',
                itemDisabled: 'choices__item--disabled',
                itemChoice: 'choices__item--choice',
                placeholder: 'choices__placeholder',
                group: 'choices__group',
                groupHeading: 'choices__heading',
                button: 'choices__button',
                activeState: 'is-active',
                focusState: 'is-focused',
                openState: 'is-open',
                disabledState: 'is-disabled',
                highlightedState: 'is-highlighted',
                selectedState: 'is-selected',
                flippedState: 'is-flipped',
                loadingState: 'is-loading',
                noResults: 'has-no-results',
                noChoices: 'has-no-choices'
            }
        });

        // Initialize Choices.js for edit form
        const editUserSelect = document.querySelector('#edit_user_ids');
        const editChoices = new Choices(editUserSelect, {
            removeItemButton: true,
            searchEnabled: true,
            searchPlaceholderValue: 'វាយឈ្មោះបុគ្គលិក...',
            noResultsText: 'រកមិនឃើញបុគ្គលិក',
            itemSelectText: '',
            maxItemCount: -1,
            classNames: {
                containerOuter: 'choices',
                containerInner: 'choices__inner',
                input: 'choices__input',
                inputCloned: 'choices__input--cloned',
                list: 'choices__list',
                listItems: 'choices__list--multiple',
                listSingle: 'choices__list--single',
                listDropdown: 'choices__list--dropdown',
                item: 'choices__item',
                itemSelectable: 'choices__item--selectable',
                itemDisabled: 'choices__item--disabled',
                itemChoice: 'choices__item--choice',
                placeholder: 'choices__placeholder',
                group: 'choices__group',
                groupHeading: 'choices__heading',
                button: 'choices__button',
                activeState: 'is-active',
                focusState: 'is-focused',
                openState: 'is-open',
                disabledState: 'is-disabled',
                highlightedState: 'is-highlighted',
                selectedState: 'is-selected',
                flippedState: 'is-flipped',
                loadingState: 'is-loading',
                noResults: 'has-no-results',
                noChoices: 'has-no-choices'
            }
        });

        // Populate edit modal
        document.querySelectorAll('.btn-edit').forEach(button => {
            button.addEventListener('click', () => {
                const id = button.getAttribute('data-id');
                const title = button.getAttribute('data-title');
                const date = button.getAttribute('data-date');
                const text = button.getAttribute('data-text');
                const userIds = button.getAttribute('data-user-ids').split(',').filter(id => id);

                document.querySelector('#edit_announcement_id').value = id;
                document.querySelector('#edit_title').value = title;
                document.querySelector('#edit_date').value = date;
                document.querySelector('#edit_text').value = text;
                editChoices.setChoiceByValue(userIds);
            });
        });

        // Service Worker registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('service-worker.js')
                    .then(registration => {
                        console.log('ServiceWorker registration successful');
                    })
                    .catch(err => {
                        console.log('ServiceWorker registration failed: ', err);
                    });
            });
        }
    </script>
</body>
</html>