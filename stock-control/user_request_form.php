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
function sendTelegramNotification($message) {
    if (!defined('TELEGRAM_BOT_TOKEN') || !defined('TELEGRAM_CHAT_ID')) {
        error_log("Telegram token or chat ID is not defined.");
        return;
    }
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = [ 'chat_id' => TELEGRAM_CHAT_ID, 'text' => $message, 'parse_mode' => 'HTML' ];
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
                $request_id, $p_item['item_id'], $p_item['item_name'], $p_item['quantity'], $p_item['note']
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
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("Request submission failed: " . $e->getMessage());
        $_SESSION['error_message'] = "មានបញ្ហាកើតឡើងនៅពេលបញ្ជូនសំណើ។ សូមព្យាយាមម្តងទៀត។ Error: " . $e->getMessage();
        $_SESSION['submitted_data'] = $_POST;
        header("Location: " . $current_page);
        exit;
    }
}
?>
<!DOCTYPE html>
<!-- ... ផ្នែក HTML, CSS, និង JavaScript ទាំងអស់គឺរក្សាទុកដូចដើម ... -->
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Items - HR App</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4f46e5; --primary-hover: #4338ca; --danger-color: #ef4444; --light-gray: #f8fafc;
            --medium-gray: #f1f5f9; --border-color: #e2e8f0; --text-dark: #1e293b; --text-medium: #475569;
            --text-light: #64748b; --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
            --border-radius: 0.75rem;
        }
        body { font-family: 'Kantumruy Pro', sans-serif; background-color: var(--medium-gray); color: var(--text-dark); margin: 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1rem; }
        .hidden { display: none; }
        .app-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; text-decoration: none; }
        .app-header .logo-img { width: 50px; height: 50px; border-radius: 12px; object-fit: cover; }
        .app-header .app-title { font-size: 1.875rem; font-weight: 700; color: var(--text-dark); }
        .page-layout { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: flex-start; }
        .main-content, .sidebar-content { background-color: #ffffff; border-radius: var(--border-radius); box-shadow: var(--shadow); border: 1px solid var(--border-color); }
        .main-content { padding: 2rem; }
        .sidebar-content { position: sticky; top: 1.5rem; }
        .main-content h2 { font-size: 1.5rem; font-weight: 600; margin: 0 0 0.5rem 0; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color); }
        .main-content .form-description { color: var(--text-light); margin-bottom: 2rem; }
        .sidebar-content h3 { font-size: 1.25rem; font-weight: 600; margin: 0; padding: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .stock-list-wrapper { max-height: 500px; overflow-y: auto; }
        .stock-item { display: flex; align-items: center; gap: 1rem; padding: 0.8rem 1.5rem; border-bottom: 1px solid var(--border-color); }
        .stock-item:last-child { border-bottom: none; }
        .stock-item-image { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex-shrink: 0; background-color: var(--medium-gray); }
        .stock-item-placeholder { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background-color: var(--medium-gray); color: var(--text-light); font-size: 1.25rem; flex-shrink: 0; }
        .stock-item-details { flex-grow: 1; min-width: 0; }
        .stock-item-details .item-name { color: var(--text-medium); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .stock-item-details .item-quantity { font-size: 0.875rem; font-weight: 600; color: var(--primary-color); }
        .stock-item-details .item-quantity.out-of-stock { color: var(--danger-color); }
        .empty-stock-message { text-align: center; color: var(--text-light); padding: 2rem; }
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-left-width: 4px; border-radius: 0.375rem; }
        .alert-success { background-color: #f0fdf4; border-color: #22c55e; color: #15803d; }
        .alert-danger { background-color: #fef2f2; border-color: var(--danger-color); color: #b91c1c; }
        .form-grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        .form-group label { display: block; font-weight: 500; color: var(--text-medium); margin-bottom: 0.5rem; }
        .form-control { display: block; width: 100%; padding: 0.625rem 0.875rem; font-size: 1rem; border: 1px solid var(--border-color); border-radius: 0.5rem; box-sizing: border-box; }
        .items-section-header { font-size: 1.25rem; font-weight: 600; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); }
        .table-wrapper { overflow-x: auto; }
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th { text-align: left; padding: 0.75rem 0.5rem; color: var(--text-light); font-weight: 500; font-size: 0.875rem; }
        .items-table td { padding: 0.5rem; vertical-align: top; }
        .items-table tbody tr { border-top: 1px solid var(--border-color); }
        .form-actions { display: flex; flex-direction: column-reverse; justify-content: space-between; align-items: center; gap: 1rem; margin-top: 1.5rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; width: 100%; padding: 0.75rem 1.25rem; font-weight: 600; border-radius: 0.5rem; border: 1px solid transparent; cursor: pointer; text-decoration: none; box-sizing: border-box; }
        .btn-primary { background-color: var(--primary-color); color: #ffffff; }
        .btn-add { background-color: #f0fdf4; color: #16a34a; border: 1px solid #a7f3d0; }
        .btn-remove { display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 50%; color: var(--text-light); background-color: transparent; border: none; }
        .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; background: white; display: none; justify-content: space-around; padding: 10px 0; box-shadow: 0 -5px 15px rgba(0, 0, 0, 0.05); z-index: 1000; border-top: 1px solid var(--border-color); }
        .nav-item { display: flex; flex-direction: column; align-items: center; text-decoration: none; color: var(--text-light); font-size: 0.8rem; }
        .nav-item.active { color: var(--primary-color); transform: translateY(-5px); }
        .nav-icon { font-size: 1.5rem; margin-bottom: 5px; }
        .select2-container .select2-selection--single { height: calc(2.5rem + 2px); border: 1px solid var(--border-color); border-radius: 0.5rem; }
        .select2-container .select2-selection--single .select2-selection__rendered { padding-left: 0.875rem; line-height: 2.5rem; }
        
        @media (min-width: 640px) { .form-actions { flex-direction: row; } .btn { width: auto; } }
        @media (min-width: 768px) { .form-grid { grid-template-columns: repeat(2, 1fr); } }
        
        @media (max-width: 1023px) {
            body { padding-bottom: 80px; }
            .bottom-nav { display: flex; }
            .sidebar-content { position: static; margin-top: 1.5rem; }
            .page-layout { grid-template-columns: 1fr; }
            .main-content { padding: 1.5rem 1rem; }
            .app-title { font-size: 1.5rem; }
        }

        @media (max-width: 767px) {
            .container { padding: 1rem 0.5rem; }
            .main-content { padding: 1rem; }
            .form-grid { grid-template-columns: 1fr; }
            .items-table thead { display: none; }
            .items-table tr { display: block; border: 1px solid var(--border-color); border-radius: var(--border-radius); margin-bottom: 1rem; padding: 0.5rem; }
            .items-table tbody tr { border-top: 1px solid var(--border-color); }
            .items-table td { display: block; padding-left: 45%; position: relative; text-align: right; border-bottom: 1px solid var(--medium-gray); padding-top: 0.75rem; padding-bottom: 0.75rem; }
            .items-table td:last-child { border-bottom: none; padding-top: 1rem; }
            .items-table td[data-label="សកម្មភាព"] { text-align: center; }
            .items-table td::before { content: attr(data-label); position: absolute; left: 0.5rem; width: 40%; font-weight: 600; color: var(--text-medium); text-align: left; }
            .form-actions { flex-direction: column; gap: 0.75rem; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>

    <div class="container">
        <header>
            <a href="../homes.php" class="app-header">
                <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="Logo" class="logo-img">
                <h1 class="app-title">Item Request System</h1>
            </a>
        </header>

        <div class="page-layout">
            <main class="main-content">
                <h2>បង្កើតសំណើសុំសម្ភារៈ</h2>
                <p class="form-description">សូមបំពេញព័ត៌មានខាងក្រោមដើម្បីស្នើសុំសម្ភារៈប្រើប្រាស់។</p>
                <?php if ($success_message): ?>
                    <div class="alert alert-success"><p class="alert-title">ជោគជ័យ!</p><p><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?></p></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><p class="alert-title">មានកំហុស</p><p><?php echo $error_message; ?></p></div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars($current_page, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="requester_name">ឈ្មោះអ្នកស្នើសុំ</label>
                            <input type="text" id="requester_name" class="form-control" value="<?php echo htmlspecialchars($logged_in_user_name, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="location">ទីតាំងស្នើសុំ <span style="color:red;">*</span></label>
                            <select id="location" name="location" class="form-control" required>
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
                    <h3 class="items-section-header">សម្ភារៈដែលស្នើសុំ</h3>
                    <div class="table-wrapper">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th style="width: 45%;">សម្ភារៈ <span style="color:red;">*</span></th>
                                    <th style="width: 15%;">ចំនួន <span style="color:red;">*</span></th>
                                    <th>កំណត់ចំណាំ</th>
                                    <th style="width: 10%; text-align: center;">សកម្មភាព</th>
                                </tr>
                            </thead>
                            <tbody id="item-list"></tbody>
                        </table>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-add" onclick="addItemRow()">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" /></svg>
                            <span>បន្ថែមសម្ភារៈ</span>
                        </button>
                        <button type="submit" name="submit_request" class="btn btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="currentColor"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.428A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" /></svg>
                            <span>បញ្ជូនសំណើ</span>
                        </button>
                    </div>
                </form>
            </main>

            <aside class="sidebar-content">
                <h3>បញ្ជីសម្ភារៈក្នុងស្តុក</h3>
                <div class="stock-list-wrapper">
                    <?php if (!empty($items_for_js)): ?>
                        <?php foreach ($items_for_js as $item): ?>
                            <div class="stock-item">
                                <?php if (!empty($item['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8'); ?>" class="stock-item-image">
                                <?php else: ?>
                                    <div class="stock-item-placeholder"><i class="fas fa-box-open"></i></div>
                                <?php endif; ?>
                                <div class="stock-item-details">
                                    <div class="item-name"><?php echo htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <div class="item-quantity <?php echo ($item['quantity'] <= 0) ? 'out-of-stock' : ''; ?>">
                                        ចំនួនសល់: <?php echo htmlspecialchars($item['quantity'], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="empty-stock-message">មិនមានសម្ភារៈណាមួយនៅក្នុងស្តុកទេ</p>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>

    <nav class="bottom-nav">
        <a href="../homes.php" class="nav-item <?php echo $current_page === 'homes.php' ? 'active' : ''; ?>"><i class="fas fa-home nav-icon"></i><span>ទំព័រដើម</span></a>
        <a href="#" class="nav-item <?php echo $current_page === 'calendar.php' ? 'active' : ''; ?>"><i class="fas fa-calendar nav-icon"></i><span>កាលវិភាគ</span></a>
        <a href="../checklist.php" class="nav-item <?php echo $current_page === 'checklist.php' ? 'active' : ''; ?>"><i class="fas fa-tasks nav-icon"></i><span>ការងារ</span></a>
        <a href="../profile.php" class="nav-item <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>"><i class="fas fa-user nav-icon"></i><span>គណនី</span></a>
    </nav>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
                    style = 'style="color: #ef4444;"';
                }
                itemOptionsHTML += `<option value="${item.id}" ${style}>${itemName}</option>`;
            });

            itemOptionsHTML += `<option value="other" style="font-weight:bold; color:var(--primary-color);">-- ផ្សេងៗ (សូមបញ្ជាក់) --</option>`;

            newRow.innerHTML = `
                <td data-label="សម្ភារៈ">
                    <select name="item_id[]" class="item-select" required>${itemOptionsHTML}</select>
                    <input type="text" name="other_item_name[]" class="other-item-input hidden form-control" style="margin-top: 0.5rem;" placeholder="សូមបញ្ជាក់ឈ្មោះសម្ភារៈ...">
                </td>
                <td data-label="ចំនួន">
                    <input type="number" name="request_qty[]" class="form-control" min="1" placeholder="1" required style="text-align: center;">
                </td>
                <td data-label="កំណត់ចំណាំ">
                    <input type="text" name="notes[]" class="form-control" placeholder="(ស្រេចចិត្ត)">
                </td>
                <td data-label="សកម្មភាព">
                    <button type="button" class="btn-remove" onclick="removeRow(this)"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
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

        $('#item-list').on('change', '.item-select', function() {
            const selectedValue = $(this).val();
            const otherInput = $(this).closest('td').find('.other-item-input');
            if (selectedValue === 'other') {
                otherInput.removeClass('hidden').prop('required', true).focus();
            } else {
                otherInput.addClass('hidden').prop('required', false).val('');
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
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