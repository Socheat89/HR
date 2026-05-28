<?php
// FILE: user_request_form.php
session_start();

// ===== START: បន្ថែមកូដកំណត់តំបន់ម៉ោងនៅទីនេះ =====
// កំណត់តំបន់ម៉ោង (Timezone) ទៅ 'Asia/Phnom_Penh' ដើម្បីធានាថាម៉ោងទាំងអស់ត្រឹមត្រូវ
date_default_timezone_set('Asia/Phnom_Penh');
// ===== END: បញ្ចប់ការបន្ថែម =====

// --- 1. LOGIN & DATABASE SETUP ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
require_once 'db_connect.php'; // ត្រូវប្រាកដថាឯកសារនេះមានសម្រាប់ភ្ជាប់ PDO

// --- 2. CONFIGURATION ---
define('TELEGRAM_BOT_TOKEN', '7956372367:AAFvDDLVBoIkkE2QB0XSXLtfFlcUYXiPRHQ');
define('TELEGRAM_CHAT_ID', '-1002861640900');

// --- TELEGRAM NOTIFICATION FUNCTION (រក្សាទុកដដែល) ---
function sendTelegramNotification($message)
{
    if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID')) {
        error_log("Telegram token or chat ID is not defined.");
        return;
    }
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = ['chat_id' => TELEGRAM_CHAT_ID, 'text' => $message, 'parse_mode' => 'HTML'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("Telegram cURL Error: " . curl_error($ch));
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code !== 200) {
        error_log("Telegram API returned HTTP code: " . $http_code . ". Response: " . $result);
    }
    curl_close($ch);
    $response = json_decode($result, true);
    if (isset($response['ok']) && !$response['ok']) {
        error_log("Telegram API Error for Chat ID " . TELEGRAM_CHAT_ID . ": " . ($response['description'] ?? 'Unknown error'));
    }
}


// --- 3. PAGE & DATA SETUP (រក្សាទុកដដែល) ---
$current_page = basename($_SERVER['PHP_SELF']);
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
$submitted_data = $_SESSION['submitted_data'] ?? [];
unset($_SESSION['submitted_data']);
$logged_in_user_id = $_SESSION['user_id'];
$logged_in_user_name = 'Guest';
$items_for_js = [];
$available_items_keyed = [];
try {
    $stmt_user = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt_user->execute([$logged_in_user_id]);
    $user_name_from_db = $stmt_user->fetchColumn();
    if ($user_name_from_db) {
        $logged_in_user_name = $user_name_from_db;
    }
    $stmt_items = $pdo->prepare("SELECT id, item_name, price, quantity, image_path FROM stock_items ORDER BY (quantity > 0) DESC, item_name ASC");
    $stmt_items->execute();
    $items_for_js = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    $dropdown_items = $items_for_js;
    foreach ($items_for_js as $item) {
        $available_items_keyed[$item['id']] = $item;
    }
} catch (PDOException $e) {
    $error_message = "Could not load data from the database.";
    $items_for_js = [];
    $dropdown_items = [];
    error_log("Data fetch failed: " . $e->getMessage());
}
$request_locations = ["ការិយាល័យកណ្ដាល", "ហាងទំនិញ 318", "CH1", "CKD", "ST1", "PSP", "SK ឈូកមាស", "SK Cosmetics"];


// --- 4. HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    // ... ការត្រួតពិនិត្យ CSRF token រក្សាទុកដដែល ...
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error_message'] = "CSRF token mismatch. Please try again.";
        header("Location: " . $current_page);
        exit;
    }

    // ... ការទាញយក និងត្រួតពិនិត្យទិន្នន័យពី Form រក្សាទុកដដែល ...
    $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
    $item_ids = $_POST['item_id'] ?? [];
    $request_qtys = $_POST['request_qty'] ?? [];
    $other_item_names = $_POST['other_item_name'] ?? [];
    $notes = $_POST['notes'] ?? [];
    $errors = [];
    $processed_items = [];
    $telegram_message_items = "";
    if (empty($location) || !in_array($location, $request_locations)) {
        $errors[] = "សូមជ្រើសរើសទីតាំងស្នើសុំដែលត្រឹមត្រូវ។";
    }
    if (empty($item_ids) || count($item_ids) !== count($request_qtys)) {
        $errors[] = "ទិន្នន័យសម្ភារៈដែលបានស្នើសុំមិនត្រឹមត្រូវ។ សូមព្យាយាមម្តងទៀត។";
    } else {
        foreach ($item_ids as $index => $item_id) {
            $qty = filter_var($request_qtys[$index] ?? 0, FILTER_VALIDATE_INT);
            $note = htmlspecialchars($notes[$index] ?? '', ENT_QUOTES, 'UTF-8');
            $item_name = '';
            if ($qty === false || $qty <= 0) {
                $errors[] = "បរិមាណសម្រាប់សម្ភារៈទី " . ($index + 1) . " ត្រូវតែជាលេខធំជាងសូន្យ។";
                continue;
            }
            if ($item_id === 'other') {
                $other_name = trim(filter_var($other_item_names[$index] ?? '', FILTER_SANITIZE_STRING));
                if (empty($other_name)) {
                    $errors[] = "សូមបញ្ជាក់ឈ្មោះសម្ភារៈ 'ផ្សេងៗ' នៅជួរទី " . ($index + 1) . "។";
                    continue;
                }
                $item_name = $other_name . " (ផ្សេងៗ)";
                $processed_items[] = ['item_id' => null, 'item_name' => $other_name, 'quantity' => $qty, 'note' => $note];
            } else {
                $item_id_val = filter_var($item_id, FILTER_VALIDATE_INT);
                if ($item_id_val === false || !isset($available_items_keyed[$item_id_val])) {
                    $errors[] = "សម្ភារៈដែលបានជ្រើសរើសនៅជួរទី " . ($index + 1) . " មិនត្រឹមត្រូវទេ។";
                    continue;
                }
                $stock_item = $available_items_keyed[$item_id_val];
                $item_name = $stock_item['item_name'];
                $processed_items[] = ['item_id' => $item_id_val, 'item_name' => null, 'quantity' => $qty, 'note' => $note];
            }
            $telegram_message_items .= "› " . htmlspecialchars($item_name, ENT_QUOTES, 'UTF-8') . " - <b>ចំនួន:</b> " . $qty . "\n";
        }
    }
    if (empty($processed_items) && empty($errors)) {
        $errors[] = "សូមបន្ថែមសម្ភារៈយ៉ាងហោចណាស់មួយមុខក្នុងសំណើរបស់អ្នក។";
    }
    // ... ការបង្ហាញ Error រក្សាទុកដដែល ...
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
        $_SESSION['submitted_data'] = $_POST;
        header("Location: " . $current_page);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // ===== CHANGE 1: បង្កើត Timestamp នៅក្នុង PHP =====
        $current_datetime = date('Y-m-d H:i:s'); // Format ដែល MySQL យល់

        // ===== CHANGE 2: កែប្រែ Query ដោយប្រើ Placeholder (?) សម្រាប់ created_at និង status 'pending' =====
        $stmt_req = $pdo->prepare(
            "INSERT INTO stock_request (user_id, requester_name, location, created_at, status) VALUES (?, ?, ?, ?, ?)"
        );

        // ===== CHANGE 3: បញ្ជូន Timestamp និង status 'pending' (អក្សរតូច) ទៅក្នុង execute() =====
        $stmt_req->execute([$logged_in_user_id, $logged_in_user_name, $location, $current_datetime, 'pending']);
        $request_id = $pdo->lastInsertId();

        $stmt_item = $pdo->prepare(
            "INSERT INTO stock_request_items (stock_request_id, item_id, item_name_custom, requested_quantity, notes) VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($processed_items as $p_item) {
            $stmt_item->execute([
                $request_id,
                $p_item['item_id'],
                $p_item['item_name'],
                $p_item['quantity'],
                $p_item['note']
            ]);
        }
        $pdo->commit();

        // បង្កើតសារ Telegram (ដោយប្រើ Timestamp ដែលបានបង្កើតខាងលើ)
        $telegram_message = "<b>🛎️ សំណើសម្ភារៈថ្មី (New Item Request) #" . $request_id . "</b>\n\n";
        $telegram_message .= "<b>អ្នកស្នើសុំ (Requester):</b> " . htmlspecialchars($logged_in_user_name, ENT_QUOTES, 'UTF-8') . "\n";
        $telegram_message .= "<b>ទីតាំង (Location):</b> " . htmlspecialchars($location, ENT_QUOTES, 'UTF-8') . "\n";
        // បង្ហាញកាលបរិច្ឆេទដោយបម្លែង Timestamp ដែលបានបង្កើត
        $telegram_message .= "<b>កាលបរិច្ឆេទ (Date):</b> " . date('d-M-Y H:i A', strtotime($current_datetime)) . "\n\n";
        $telegram_message .= "<b>បញ្ជីសម្ភារៈ (Items List):</b>\n";
        $telegram_message .= $telegram_message_items;

        sendTelegramNotification($telegram_message);

        $_SESSION['success_message'] = "សំណើរបស់អ្នកត្រូវបានបញ្ជូនដោយជោគជ័យ។";
        header("Location: " . $current_page);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Request submission failed: " . $e->getMessage());
        $_SESSION['error_message'] = "មានបញ្ហាកើតឡើងនៅពេលបញ្ជូនសំណើ។ សូមព្យាយាមម្តងទៀត។ Error: " . $e->getMessage();
        $_SESSION['submitted_data'] = $_POST;
        header("Location: " . $current_page);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Request Items - HR App</title>

    <!-- Frameworks & Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
        }

        /* Prevent horizontal scrolling */
        html,
        body {
            overflow-x: hidden;
            width: 100%;
        }

        .app-container {
            max-width: 1200px;
            margin: 0 auto;
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

        .back-btn {
            color: var(--gray-500);
            font-size: 1.1rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: color 0.2s;
        }

        .back-btn:hover {
            color: var(--primary);
        }

        /* === LAYOUT === */
        .page-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            align-items: start;
        }

        /* === CARDS === */
        .content-card {
            background: white;
            border-radius: var(--border-radius-lg);
            border: 1px solid var(--gray-200);
            padding: 32px;
            box-shadow: var(--shadow-lg);
            animation: fadeInUp 0.5s ease-out;
        }

        .card-header-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--gray-900);
            border-bottom: 2px solid var(--gray-100);
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--gray-900);
            display: block;
        }

        .form-control,
        .form-select {
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-300);
            padding: 0.75rem 1rem;
            transition: all 0.2s;
            font-size: 0.95rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        /* === STOCK SIDEBAR === */
        .stock-list-wrapper {
            max-height: 500px;
            overflow-y: auto;
            padding-right: 4px;
        }

        .stock-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-bottom: 1px solid var(--gray-100);
            transition: background 0.2s;
        }

        .stock-item:hover {
            background: var(--gray-50);
        }

        .stock-item:last-child {
            border-bottom: none;
        }

        .stock-item-image {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
            background: var(--gray-100);
        }

        .stock-item-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-400);
        }

        .stock-details h4 {
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-800);
        }

        .stock-badge {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--success);
            background: rgba(16, 185, 129, 0.1);
            padding: 2px 8px;
            border-radius: 12px;
            display: inline-block;
            margin-top: 4px;
        }

        .stock-badge.empty {
            color: var(--danger);
            background: rgba(239, 68, 68, 0.1);
        }

        /* === TABLE === */
        .table-responsive {
            border-radius: var(--border-radius);
            overflow: hidden;
            border: 1px solid var(--gray-200);
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-600);
            border-bottom: 1px solid var(--gray-200);
            padding: 12px 16px;
        }

        .table td {
            vertical-align: middle;
            padding: 12px 16px;
            border-bottom: 1px solid var(--gray-100);
        }

        .btn-add {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid transparent;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
        }

        .btn-add:hover {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border: none;
            color: white;
            padding: 0.75rem 2rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.4);
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4);
        }

        .btn-remove {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--danger);
            background: rgba(239, 68, 68, 0.1);
            border: none;
            transition: all 0.2s;
        }

        .btn-remove:hover {
            background: var(--danger);
            color: white;
        }

        /* === SELECT2 CUSTOMIZATION === */
        .select2-container .select2-selection--single {
            height: 48px;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 46px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding-left: 16px;
            color: var(--gray-800);
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

        /* Animation */
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

        /* Responsive */
        @media (max-width: 992px) {
            .page-layout {
                grid-template-columns: 1fr;
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

            .content-card {
                padding: 20px;
            }

            .bottom-nav {
                display: flex;
            }

            /* Table Mobile View */
            .table-mobile thead {
                display: none;
            }

            .table-mobile tr {
                display: block;
                border: 1px solid var(--gray-200);
                border-radius: var(--border-radius);
                margin-bottom: 16px;
                padding: 12px;
                background: var(--gray-50);
            }

            .table-mobile td {
                display: block;
                text-align: right;
                padding: 8px 0;
                border-bottom: 1px dotted var(--gray-200);
                position: relative;
                padding-left: 40%;
            }

            .table-mobile td:last-child {
                border-bottom: none;
            }

            .table-mobile td::before {
                content: attr(data-label);
                position: absolute;
                left: 0;
                width: 35%;
                font-weight: 600;
                text-align: left;
                color: var(--gray-600);
            }

            .btn-actions {
                flex-direction: column;
                gap: 12px;
            }

            .btn-actions button {
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <div class="app-container">

        <!-- Modern Header -->
        <header class="app-header animate__animated animate__fadeInDown">
            <a href="../homes.php" class="logo-container">
                <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="Logo" class="logo-img">
                <h1 class="app-title">ស្នើសុំសម្ភារៈ</h1>
            </a>
            <a href="../homes.php" class="back-btn d-none d-md-flex">
                <i class="fas fa-arrow-left"></i> ត្រឡប់ក្រោយ
            </a>
        </header>

        <div class="page-layout">
            <!-- Main Form -->
            <main class="content-card">
                <h2 class="card-header-title">បង្កើតសំណើសុំសម្ភារៈ</h2>
                <p class="text-muted mb-4">សូមបំពេញព័ត៌មានខាងក្រោមដើម្បីស្នើសុំសម្ភារៈប្រើប្រាស់។</p>

                <?php if ($success_message): ?>
                    <div class="alert alert-success animate__animated animate__fadeInDown">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger animate__animated animate__fadeInDown">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">ឈ្មោះអ្នកស្នើសុំ</label>
                            <input type="text" class="form-control bg-light"
                                value="<?php echo htmlspecialchars($logged_in_user_name, ENT_QUOTES, 'UTF-8'); ?>"
                                readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="location" class="form-label">ទីតាំងស្នើសុំ <span
                                    class="text-danger">*</span></label>
                            <select id="location" name="location" class="form-select" required>
                                <option value="" disabled selected>-- ជ្រើសរើសទីតាំង --</option>
                                <?php
                                $preselected_location = $submitted_data['location'] ?? '';
                                foreach ($request_locations as $loc):
                                    $selected = ($preselected_location === $loc) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo htmlspecialchars($loc, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($loc, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <h3 class="card-header-title mt-2">បញ្ជីសម្ភារៈ</h3>

                    <div class="table-responsive mb-4">
                        <table class="table table-mobile">
                            <thead>
                                <tr>
                                    <th style="width: 45%;">សម្ភារៈ</th>
                                    <th style="width: 15%;">ចំនួន</th>
                                    <th>កំណត់ចំណាំ</th>
                                    <th style="width: 10%; text-align: center;">លុប</th>
                                </tr>
                            </thead>
                            <tbody id="item-list"></tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between btn-actions align-items-center">
                        <button type="button" class="btn btn-add rounded-3" onclick="addItemRow()">
                            <i class="fas fa-plus me-2"></i> បន្ថែមសម្ភារៈ
                        </button>
                        <button type="submit" name="submit_request" class="btn btn-submit">
                            <i class="fas fa-paper-plane me-2"></i> បញ្ជូនសំណើ
                        </button>
                    </div>
                </form>
            </main>

            <!-- Sidebar Stock List -->
            <aside class="content-card">
                <h3 class="card-header-title mb-3 fs-5">ស្តុកសម្ភារៈបច្ចុប្បន្ន</h3>
                <div class="stock-list-wrapper">
                    <?php if (!empty($items_for_js)): ?>
                        <?php foreach ($items_for_js as $item): ?>
                            <div class="stock-item">
                                <?php if (!empty($item['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Item"
                                        class="stock-item-image">
                                <?php else: ?>
                                    <div class="stock-item-placeholder">
                                        <i class="fas fa-box"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="stock-details">
                                    <h4><?php echo htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                    <?php if ($item['quantity'] <= 0): ?>
                                        <span class="stock-badge empty">អស់ស្តុក</span>
                                    <?php else: ?>
                                        <span class="stock-badge">សល់:
                                            <?php echo htmlspecialchars($item['quantity'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-box-open fs-2 mb-2"></i><br>
                            មិនមានទិន្នន័យ
                        </div>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="../homes.php" class="nav-item">
            <i class="fas fa-home nav-icon"></i>
            <span>ទំព័រដើម</span>
        </a>
        <a href="../admin/table_report.php" class="nav-item">
            <i class="fas fa-list-alt nav-icon"></i>
            <span>បញ្ជី</span>
        </a>
        <a href="../checklist.php" class="nav-item">
            <i class="fas fa-tasks nav-icon"></i>
            <span>ការងារ</span>
        </a>
        <a href="../profile.php" class="nav-item">
            <i class="fas fa-user nav-icon"></i>
            <span>គណនី</span>
        </a>
    </nav>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        const dropdownItems = <?php echo json_encode($dropdown_items); ?>;

        function initializeSelect2(selector) {
            $(selector).select2({
                placeholder: '-- ជ្រើសរើសសម្ភារៈ --',
                width: '100%',
                dropdownParent: $(selector).parent()
            });
        }

        function addItemRow() {
            const tableBody = document.getElementById('item-list');
            const newRow = tableBody.insertRow();
            let itemOptionsHTML = `<option></option>`;

            dropdownItems.forEach(item => {
                let itemName = item.item_name;
                let style = "";
                if (parseInt(item.quantity) <= 0) {
                    itemName += " (អស់ស្តុក)";
                }
                itemOptionsHTML += `<option value="${item.id}" ${style}>${itemName}</option>`;
            });

            itemOptionsHTML += `<option value="other">-- ផ្សេងៗ (សូមបញ្ជាក់) --</option>`;

            newRow.innerHTML = `
                <td data-label="សម្ភារៈ">
                    <select name="item_id[]" class="item-select" required>${itemOptionsHTML}</select>
                    <input type="text" name="other_item_name[]" class="other-item-input hidden form-control mt-2" placeholder="សូមបញ្ជាក់ឈ្មោះសម្ភារៈ...">
                </td>
                <td data-label="ចំនួន">
                    <input type="number" name="request_qty[]" class="form-control text-center" min="1" placeholder="1" required>
                </td>
                <td data-label="កំណត់ចំណាំ">
                    <input type="text" name="notes[]" class="form-control" placeholder="(ស្រេចចិត្ត)">
                </td>
                <td data-label="លុប" class="text-center">
                    <button type="button" class="btn-remove mx-auto" onclick="removeRow(this)">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            `;
            initializeSelect2(newRow.querySelector('.item-select'));
        }

        function removeRow(button) {
            const row = button.closest('tr');
            $(row).find('.item-select').select2('destroy');
            row.remove();
            if (document.getElementById('item-list').rows.length === 0) {
                addItemRow();
            }
        }

        $('#item-list').on('change', '.item-select', function () {
            const selectedValue = $(this).val();
            const otherInput = $(this).closest('td').find('.other-item-input');
            if (selectedValue === 'other') {
                otherInput.removeClass('hidden').prop('required', true).focus();
            } else {
                otherInput.addClass('hidden').prop('required', false).val('');
            }
        });

        document.addEventListener('DOMContentLoaded', function () {
            const submittedData = <?php echo json_encode($submitted_data); ?>;
            if (submittedData && submittedData.item_id && submittedData.item_id.length > 0) {
                for (let i = 0; i < submittedData.item_id.length; i++) {
                    addItemRow();
                    const newRow = $('#item-list').find('tr').last();
                    newRow.find('.item-select').val(submittedData.item_id[i]).trigger('change');
                    if (submittedData.item_id[i] === 'other') {
                        newRow.find('.other-item-input').val(submittedData.other_item_name[i]);
                    }
                    newRow.find('input[name="request_qty[]"]').val(submittedData.request_qty[i]);
                    newRow.find('input[name="notes[]"]').val(submittedData.notes[i]);
                }
            } else {
                addItemRow();
            }
        });
    </script>
</body>

</html>