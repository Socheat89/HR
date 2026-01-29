<?php
// Start session for user tracking
session_start();

// Include the telegram.php file
require_once __DIR__ . '/includes/telegram.php'; // Ensure this path is correct

// Set UTF-8 encoding for the script
header('Content-Type: text/html; charset=UTF-8');

// Check login status
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirect to login page
    exit;
}

// Database Connection Details (Define globally for both POST and GET)
$host = 'localhost';
$dbname = 'samann1_admin_panel';
$user = 'samann1_admin_panel';
$password = 'admin_panel@2025';

// =================================================================================
// SECTION 1: HANDLE FORM SUBMISSION (POST REQUEST)
// This block will execute ONLY when the form is submitted.
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');

    $pdo_post = null;

    try {
        $pdo_post = new PDO("mysql:host=$host;dbname=$dbname", $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo_post->exec("SET NAMES 'utf8mb4'");

        // If this is an AJAX request to create or fetch a department head, handle it and exit early.
    if (isset($_POST['action']) && in_array($_POST['action'], ['create_dept_head','get_dept_head','save_head_signature','get_annual_balance'])) {
            header('Content-Type: application/json; charset=UTF-8');
            $action = $_POST['action'];
            try {
                // Ensure a lightweight table exists to store custom department heads per user.
                $pdo_post->exec("CREATE TABLE IF NOT EXISTS user_custom_heads (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    full_name VARCHAR(255) NOT NULL,
                    creator_id INT NOT NULL,
                    signature LONGTEXT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                    // Ensure signature column exists (in case table was created earlier without it)
                    $colCheck = $pdo_post->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'user_custom_heads' AND COLUMN_NAME = 'signature'");
                    $colCheck->execute([$dbname]);
                    if ($colCheck->fetchColumn() == 0) {
                        // add the signature column
                        try {
                            $pdo_post->exec("ALTER TABLE user_custom_heads ADD COLUMN signature LONGTEXT NULL");
                        } catch (Exception $e) {
                            // non-fatal: continue without signature column
                        }
                    }

                // If action is create, insert a new row
                if ($action === 'create_dept_head') {
                    $newName = isset($_POST['name']) ? trim($_POST['name']) : '';
                    if ($newName === '') {
                        echo json_encode(['success' => false, 'message' => 'ឈ្មោះគ្មានទិន្នន័យ']);
                        exit;
                    }
                    $ins = $pdo_post->prepare('INSERT INTO user_custom_heads (full_name, creator_id) VALUES (?, ?)');
                    $ins->execute([$newName, $_SESSION['user_id']]);
                    $newId = $pdo_post->lastInsertId();
                    echo json_encode(['success' => true, 'id' => $newId, 'name' => $newName]);
                    exit;
                }

                // If action is get, fetch row by id and ensure it belongs to the current user
                if ($action === 'get_dept_head') {
                    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid id']); exit; }
                    $sel = $pdo_post->prepare('SELECT id, full_name, signature FROM user_custom_heads WHERE id = ? AND creator_id = ? LIMIT 1');
                    $sel->execute([$id, $_SESSION['user_id']]);
                    $row = $sel->fetch(PDO::FETCH_ASSOC);
                    if (!$row) { echo json_encode(['success'=>false,'message'=>'មិនមានប្រធានផ្នែកនេះ']); exit; }
                    echo json_encode(['success'=>true,'id'=>$row['id'],'name'=>$row['full_name'],'signature'=>$row['signature']]);
                    exit;
                }

                // If action is save signature for a head
                if ($action === 'save_head_signature') {
                    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    $sig = isset($_POST['signature']) ? trim($_POST['signature']) : null;
                    if ($id <= 0) { echo json_encode(['success'=>false,'message'=>'Invalid id']); exit; }
                    $up = $pdo_post->prepare('UPDATE user_custom_heads SET signature = ? WHERE id = ? AND creator_id = ?');
                    $up->execute([$sig, $id, $_SESSION['user_id']]);
                    echo json_encode(['success'=>true]);
                    exit;
                }

                // If action is get annual balance
                if ($action === 'get_annual_balance') {
                    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : $_SESSION['user_id'];
                    $sel = $pdo_post->prepare('SELECT annual_leave_balance FROM users WHERE id = ? LIMIT 1');
                    $sel->execute([$userId]);
                    $row = $sel->fetch(PDO::FETCH_ASSOC);
                    $balance = $row ? (float)$row['annual_leave_balance'] : 0;
                    echo json_encode(['success' => true, 'balance' => $balance]);
                    exit;
                }
            } catch (Exception $ex) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'មិនអាចដំណើរការ: ' . $ex->getMessage()]);
                exit;
            }
            // end AJAX handler
        }

        // Use the user_id submitted from the hidden input in the form
        $userIdForRequest = isset($_POST['user_id']) ? (int)$_POST['user_id'] : $_SESSION['user_id'];

        $requesterName = isset($_POST['requester_name']) ? trim($_POST['requester_name']) : 'អ្នកប្រើមិនស្គាល់';

        $request_type_str = '';
        if (isset($_POST['request_type']) && is_array($_POST['request_type'])) {
            $request_types = array_map('trim', array_filter($_POST['request_type'], 'is_string'));
            $request_type_str = !empty($request_types) ? implode(', ', $request_types) : '';
        }

    // Capture department head name (new field)
    $departmentHeadName = isset($_POST['department_head_name']) ? trim($_POST['department_head_name']) : null;

    // Signature is handled via table_reqeuest.php; leave null on submit
    $signatureData = null;
    $signatureDate = null;

        if (empty($request_type_str) || empty($_POST['request_date'])) {
            throw new Exception("សូមបំពេញគ្រប់ Field ដែលមានសញ្ញា (*) នៅក្នុងទម្រង់បន្ថែម។");
        }

        // Build INSERT dynamically — only include department_head_name if the column exists in the database
        $columns = [
            'request_type', 'user_id', 'requester_name'
        ];
        $values = [
            $request_type_str, $userIdForRequest, $requesterName
        ];

        // Get list of existing columns in requests table
        $colsStmt = $pdo_post->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'requests'");
        $colsStmt->execute([$dbname]);
        $existingCols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);

        $hasDepartmentHeadColumn = in_array('department_head_name', $existingCols);
        $hasDeptHeadSignature = in_array('department_head_signature', $existingCols);
        $hasDeptHeadSignatureDate = in_array('department_head_signature_date', $existingCols);

        if ($hasDepartmentHeadColumn) {
            $columns[] = 'department_head_name';
            $values[] = $departmentHeadName;
        }

        // Append the rest of fields (these are expected to exist in the table)
        $otherFields = [
            'number_of_days', 'remaining_days', 'department', 'position', 'branch',
            'request_date', 'return_date', 'late_hours', 'forgot_scan_in', 'forgot_scan_out',
            'time_in', 'time_out', 'total_hours', 'repay_time_in', 'repay_time_out', 'repay_total_hours',
            'reason', 'assigned_to', 'location', 'contact_number'
        ];

        foreach ($otherFields as $f) {
            $columns[] = $f;
            switch ($f) {
                case 'number_of_days':
                case 'remaining_days':
                    $values[] = (isset($_POST[$f]) && $_POST[$f] !== '') ? floatval($_POST[$f]) : null;
                    break;
                case 'contact_number':
                    $values[] = !empty($_POST[$f]) ? trim($_POST[$f]) : null;
                    break;
                default:
                    $values[] = !empty($_POST[$f]) ? trim($_POST[$f]) : null;
                    break;
            }
        }

        // Signature columns (we leave them null on submit)
        $columns[] = 'signature'; $values[] = $signatureData;
        $columns[] = 'signature_date'; $values[] = $signatureDate;

        // Department head signature if provided and column exists
        $departmentHeadSignature = isset($_POST['department_head_signature']) ? trim($_POST['department_head_signature']) : null;
        $deleteDeptHeadSignature = isset($_POST['delete_department_head_signature']) && $_POST['delete_department_head_signature'] === '1';
        if ($hasDeptHeadSignature) {
            if ($departmentHeadSignature && !$deleteDeptHeadSignature) {
                $columns[] = 'department_head_signature'; $values[] = $departmentHeadSignature;
                if ($hasDeptHeadSignatureDate) { $columns[] = 'department_head_signature_date'; $values[] = date('Y-m-d'); }
            } else {
                // no signature provided or marked deleted
                $columns[] = 'department_head_signature'; $values[] = null;
                if ($hasDeptHeadSignatureDate) { $columns[] = 'department_head_signature_date'; $values[] = null; }
            }
        }

        // created_at and status
        $columns[] = 'created_at'; $values[] = date('Y-m-d H:i:s');
        $columns[] = 'status'; $values[] = 'pending';

        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $sql = "INSERT INTO requests (" . implode(', ', $columns) . ") VALUES ($placeholders)";
        $stmt = $pdo_post->prepare($sql);
        $stmt->execute($values);

        $newId = $pdo_post->lastInsertId();

        // Update annual leave balance if it's an annual leave request
        if (strpos($request_type_str, 'សម្រាកប្រចាំឆ្នាំ') !== false && isset($_POST['number_of_days']) && $_POST['number_of_days'] !== '') {
            $daysToSubtract = (float)$_POST['number_of_days'];
            $updateStmt = $pdo_post->prepare('UPDATE users SET annual_leave_balance = annual_leave_balance - ? WHERE id = ?');
            $updateStmt->execute([$daysToSubtract, $userIdForRequest]);
        }

        $chatId = '-1002496391098';
        $message = "សំណើរថ្មី៖\n" .
                  "- លេខសម្គាល់: $newId\n" .
                  "- ប្រភេទ៖ " . htmlspecialchars($request_type_str) . "\n" .
                  "- ឈ្មោះ៖ " . htmlspecialchars($requesterName) . "\n" .
                  "- ប្រធានផ្នែក៖ " . htmlspecialchars($departmentHeadName ?? ($_POST['department_head_name'] ?? 'N/A')) . "\n" .
                  "- ផ្នែក៖ " . htmlspecialchars($_POST['department'] ?? 'N/A') . "\n" .
                  "- ថ្ងៃ៖ " . htmlspecialchars($_POST['request_date'] ?? 'N/A') . "\n" .
                  "- ចំនួនថ្ងៃ: " . (isset($_POST['number_of_days']) && $_POST['number_of_days'] !== '' ? htmlspecialchars($_POST['number_of_days']) : 'N/A') . "\n" .
                  "- មូលហេតុ៖ " . htmlspecialchars($_POST['reason'] ?? 'N/A') . "\n" .
                  "- ម៉ោង៖ " . (isset($_POST['time_in']) && !empty($_POST['time_in']) ? htmlspecialchars($_POST['time_in']) : 'N/A');

        if (!sendTelegramMessage($chatId, $message)) {
            error_log("Failed to send Telegram message for request ID: $newId");
        }

        echo json_encode([
            'success' => true,
            'message' => "សំណើ (ID: $newId) ត្រូវបានបញ្ជូនដោយជោគជ័យ!",
            'redirectUrl' => 'https://app.vvc.asia/admin/table_report.php'
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "កំហុស: " . $e->getMessage()]);
        error_log("Error during request submission: " . $e->getMessage());
    }

    exit; // IMPORTANT: Stop script execution after handling POST
}


// =================================================================================
// SECTION 2: PREPARE DATA FOR PAGE DISPLAY (GET REQUEST)
// This part runs when the page is loaded for the first time.
// =================================================================================

$errors = [];
$subordinates = [];
$show_selection_page = false; // Flag to decide which HTML to show
$loggedInUserId = $_SESSION['user_id'];
$pdo = null;
$dynamic_categories = []; // Array to hold dynamic options

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET NAMES 'utf8mb4'");

    // Fetch dynamic options (Department, Position, Branch)
    try {
        // We only need the 'name' from the categories table
        $stmt_cats = $pdo->query("SELECT type, name FROM categories ORDER BY type, name");
        $all_cats = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);
        foreach ($all_cats as $cat) {
            // Store only the name under its respective type
            $dynamic_categories[$cat['type']][] = $cat['name'];
        }
    } catch (Exception $e) {
        error_log("Failed to load dynamic categories: " . $e->getMessage());
        // Continue with empty arrays if loading fails
    }

    // Determine if we need to show the selection page or the form
    $selection_made = isset($_GET['for_user_id']) || isset($_GET['self']);

    if (!$selection_made) {
        // This is the first time the user is visiting the page. Check if they have subordinates.
        $stmt_count = $pdo->prepare("SELECT COUNT(id) FROM users WHERE manager_id = ? AND status = 'active'");
        $stmt_count->execute([$loggedInUserId]);
        if ($stmt_count->fetchColumn() > 0) {
            // Yes, they have subordinates, so prepare to show the selection page
            $show_selection_page = true;
            $stmt_subs = $pdo->prepare("SELECT id, full_name, username, image_url FROM users WHERE manager_id = ? AND status = 'active' ORDER BY full_name ASC");
            $stmt_subs->execute([$loggedInUserId]);
            $subordinates = $stmt_subs->fetchAll();

            // === GET OWN IMAGE ===
            $stmt_self = $pdo->prepare("SELECT image_url FROM users WHERE id = ?");
            $stmt_self->execute([$loggedInUserId]);
            $self_user_data = $stmt_self->fetch();
            // ==========================================

        }
    }

    // Prepare user info for the form.
    if (!$show_selection_page) {
        $targetUserId = $loggedInUserId; // Default to self
        $targetUserName = $_SESSION['username']; // Default to self, but will be overridden

        // Fetch full_name for the logged-in user
        $stmt_self = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmt_self->execute([$loggedInUserId]);
        $selfUser = $stmt_self->fetch(PDO::FETCH_ASSOC);
        $targetUserName = $selfUser ? ($selfUser['full_name'] ?: $_SESSION['username']) : $_SESSION['username'];

        if (isset($_GET['for_user_id'])) {
            $stmt_user = $pdo->prepare("SELECT full_name, username FROM users WHERE id = ?");
            $stmt_user->execute([(int)$_GET['for_user_id']]);
            $targetUser = $stmt_user->fetch();
            if ($targetUser) {
                $targetUserId = (int)$_GET['for_user_id'];
                $targetUserName = $targetUser['full_name'] ?: $targetUser['username'];
            }
        }

        // Load active users for department head select in modal
        try {
            // Load only custom department heads created by the current user.
            // If the table doesn't exist yet, this will return an empty list.
            $stmt_custom = $pdo->prepare("SELECT id, full_name FROM user_custom_heads WHERE creator_id = ? ORDER BY full_name ASC");
            $stmt_custom->execute([$loggedInUserId]);
            $managersList = $stmt_custom->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // If anything goes wrong (table missing, etc.) fall back to empty list.
            $managersList = [];
        }
    }


} catch (Exception $e) {
    $errors[] = "កំហុសក្នុងការតភ្ជាប់មូលដ្ឋានទិន្នន័យ: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $show_selection_page ? 'ជ្រើសរើសបុគ្គលិក' : 'សំណើថ្មី'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;700&display=swap');
        body { background: linear-gradient(120deg, #e0eafc, #cfdef3); font-family: 'Noto Sans Khmer', sans-serif; min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .main-container { background: #fff; border-radius: 20px; box-shadow: 0 8px 40px rgba(0, 0, 0, 0.15); padding: 2.5rem; max-width: 700px; width: 100%; }
        .main-title { color: #1a3c5e; font-size: 1.8rem; font-weight: 700; text-align: center; margin-bottom: 2rem; position: relative; }
        .user-list { list-style: none; padding: 0; margin: 0; max-height: 50vh; overflow-y: auto; }
        .user-item a { display: flex; align-items: center; padding: 15px; margin-bottom: 10px; background: #f8f9fa; border-radius: 12px; text-decoration: none; color: #495057; border: 1px solid #e9ecef; transition: all 0.3s ease; }
        .user-item a:hover { background-color: #e0eafc; border-color: #3498db; transform: translateY(-3px); box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); }
        .user-item img { width: 50px; height: 50px; border-radius: 50%; margin-right: 15px; object-fit: cover; border: 2px solid #fff; }
        .user-item .user-name { font-weight: 600; font-size: 1.1rem; }
        .main-title::after { content: ''; width: 50px; height: 3px; background: #3498db; position: absolute; bottom: -10px; left: 50%; transform: translateX(-50%); }
        .form-group { margin-bottom: 1.5rem; }
        label { color: #2c3e50; font-weight: 500; margin-bottom: 0.5rem; display: block; font-size: 1rem; }
        .required-star { color: red; font-weight: bold; margin-left: 3px; }
        .icon-group { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 1rem; }
        .request-icon { background: #f8f9fa; padding: 12px 15px; border-radius: 10px; cursor: pointer; font-size: 1rem; color: #6c757d; transition: all 0.3s ease; display: flex; align-items: center; border: 1px solid #e9ecef; }
        .request-icon.active { background: #3498db; color: #fff; border-color: #3498db; box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3); }
        .request-icon:hover { background: #e9ecef; color: #2c3e50; }
        .request-icon i { margin-right: 8px; }
        @keyframes point-animation { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
        .hand-pointer { display: none; }
        .hand-pointer.visible { display: inline-block; margin-left: 8px; color: #3498db; font-size: 1.2rem; animation: point-animation 1s ease-in-out infinite; }
        select, input[type="text"], input[type="number"], input[type="date"], input[type="time"] { width: 100%; padding: 12px 15px; border: 1px solid #ced4da; border-radius: 10px; font-size: 1rem; background: #f8f9fa; transition: all 0.3s ease; font-family: 'Noto Sans Khmer', sans-serif; }
        select:focus, input:focus { outline: none; border-color: #3498db; background: #fff; box-shadow: 0 0 8px rgba(52, 152, 219, 0.2); }
        .btn-primary { background: linear-gradient(90deg, #3498db, #2980b9); border: none; padding: 12px; font-size: 1.1rem; font-weight: 600; border-radius: 10px; transition: all 0.3s ease; width: 100%; font-family: 'Noto Sans Khmer', sans-serif; }
        .btn-primary:hover { background: linear-gradient(90deg, #2980b9, #1f6a93); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(52, 152, 219, 0.4); }
        .btn-secondary { background: #6c757d; border: none; padding: 10px 20px; font-size: 1rem; border-radius: 10px; transition: all 0.3s ease; display: inline-flex; align-items: center; font-family: 'Noto Sans Khmer', sans-serif; }
        .btn-secondary:hover { background: #5a6268; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3); }
        .error, .error-container { text-align: center; padding: 12px; border-radius: 10px; margin-bottom: 1.5rem; font-size: 1rem; font-family: 'Noto Sans Khmer', sans-serif; background: #ffe6e6; color: #cc0000; border: 1px solid #ff9999; }
        .error-container { display: none; }

        /* ========= START: NEW STYLE FOR CUSTOM CONFIRM ALERT ========= */
        .custom-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1060; /* Higher than Bootstrap modal */
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .custom-modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        .custom-modal-box {
            background: #fff;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 450px;
            width: 90%;
            font-family: 'Noto Sans Khmer', sans-serif;
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }
        .custom-modal-overlay.show .custom-modal-box {
            transform: scale(1);
        }
        .custom-modal-icon-wrapper {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            background-color: #eaf4ff;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .custom-modal-icon-wrapper i {
            font-size: 3rem;
            color: #3498db;
        }
        .custom-modal-title {
            font-weight: 700;
            color: #1a3c5e;
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
        }
        .custom-modal-text {
            color: #5a6268;
            font-size: 1.1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        .custom-modal-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        .custom-modal-buttons .btn {
            min-width: 120px;
            padding: 10px 20px;
            font-weight: 600;
            border-radius: 8px;
        }
        /* ========= END: NEW STYLE FOR CUSTOM CONFIRM ALERT ========= */
    </style>
</head>
<body>

    <?php if ($show_selection_page): ?>

    <div class="main-container">
        <h2 class="main-title">ដាក់សំណើជំនួស</h2>
        <p class="text-center text-muted mb-4">សូមជ្រើសរើសបុគ្គលិកដែលអ្នកចង់ដាក់សំណើឱ្យ ឬជ្រើសរើសដាក់សម្រាប់ខ្លួនអ្នក។</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars(implode(', ', $errors)); ?></div>
        <?php else: ?>
            <ul class="user-list">
                <li class="user-item">
                    <a href="submit_request.php?self=1">
                        <img src="<?php echo (!empty($self_user_data['image_url']) && file_exists($self_user_data['image_url'])) ? htmlspecialchars($self_user_data['image_url']) : 'https://via.placeholder.com/50/3498db/ffffff?text=ME'; ?>" alt="សម្រាប់ខ្លួនខ្ញុំ">
                        <span class="user-name">សម្រាប់ខ្លួនខ្ញុំ</span>
                    </a>
                </li>

                <?php foreach ($subordinates as $user): ?>
                    <li class="user-item">
                        <a href="submit_request.php?for_user_id=<?php echo htmlspecialchars($user['id']); ?>">
                            <img src="<?php echo (!empty($user['image_url']) && file_exists($user['image_url'])) ? htmlspecialchars($user['image_url']) : 'https://via.placeholder.com/50'; ?>" alt="User Avatar">
                            <span class="user-name"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="text-center mt-4">
            <button type="button" class="btn btn-secondary" onclick="window.history.back();">
                <i class="fas fa-arrow-left me-2"></i>ត្រឡប់ក្រោយ
            </button>
        </div>
    </div>

    <?php else: ?>

    <div class="main-container">
        <h2 class="main-title" style="position:relative;">សំណើថ្មី</h2>

        <?php if (!empty($errors)): ?>
               <p class="error"><?php echo htmlspecialchars(implode(', ', $errors)); ?></p>
        <?php endif; ?>

        <div id="js-error-container" class="error-container"></div>

        <form method="POST" action="" id="requestForm">
            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($targetUserId); ?>">

            <div class="form-group">
                <label>ប្រភេទនៃការស្នើសុំ <span class="required-star">*</span>
                    <span id="hand-pointer-icon" class="hand-pointer">
                        <i class="fas fa-hand-pointer"></i>
                    </span>
                </label>
                <div class="icon-group">
                  <div class="request-icon" data-value="សម្រាកប្រចាំឆ្នាំ (Annual Leave)"><i class="fas fa-calendar-check"></i> សម្រាកប្រចាំឆ្នាំ</div>
                  <div class="request-icon" data-value="ឈប់ឥតច្បាប់"><i class="fas fa-ban"></i> ឈប់ឥតច្បាប់</div>
                  <div class="request-icon" data-value="សម្រាកដោយជំងឺ (Sick Leave)"><i class="fas fa-briefcase-medical"></i> សម្រាកដោយជំងឺ</div>
                  <div class="request-icon" data-value="ភ្លេចស្កេនមេដៃ (Forgot FP)"><i class="fas fa-fingerprint"></i> ភ្លេចស្កេនមេដៃ</div>
                  <div class="request-icon" data-value="សម្រាកលំហែមាតុភាព (Maternity Leave)"><i class="fas fa-baby"></i> សម្រាកមាតុភាព</div>
                  <div class="request-icon" data-value="ថែមម៉ោង (OT)"><i class="fas fa-clock"></i> ថែមម៉ោង</div>
                  <div class="request-icon" data-value="ចេញមុនម៉ោង (Early)"><i class="fas fa-sign-out-alt"></i> ចេញមុនម៉ោង</div>
                  <div class="request-icon" data-value="ប្តូរថ្ងៃសម្រាក (Changing day off)"><i class="fas fa-exchange-alt"></i> ប្តូរថ្ងៃសម្រាក</div>
                  <div class="request-icon" data-value="សម្រាកពិសេស (Special Leave)"><i class="fas fa-star"></i> សម្រាកពិសេស</div>
                  <div class="request-icon" data-value="មកយឺត (Late)"><i class="fas fa-hourglass-half"></i> មកយឺត</div>
                </div>
                <input type="hidden" name="request_type[]" id="selectedRequestTypes" value="">
            </div>

            <div class="form-group" data-name="requester_name">
                <label>ឈ្មោះអ្នកស្នើសុំ <span class="required-star">*</span></label>
                <input type="text" name="requester_name" placeholder="បញ្ចូលឈ្មោះ" value="<?php echo htmlspecialchars($targetUserName); ?>" required>
            </div>
            <div class="form-group" data-name="department_head_name">
                <label>ឈ្មោះប្រធានផ្នែក</label>
                <input type="text" name="department_head_name" placeholder="បញ្ចូលឈ្មោះប្រធានផ្នែក">
            </div>
            <div class="form-group" id="dept-head-signature-group" style="display:none;" data-name="department_head_signature">
                <label>ហត្ថលេខាប្រធានផ្នែក</label>
                <div style="border:1px dashed #ced4da; padding:10px; border-radius:8px; background:#fafafa;">
                    <div id="dept-head-signature-preview" style="min-height:80px; display:flex; align-items:center; justify-content:center;">
                        <img id="dept-head-signature-img" src="" alt="signature" style="max-width:100%; max-height:120px; display:none; object-fit:contain;" />
                        <span id="dept-head-signature-empty" style="color:#888;">មិនមានហត្ថលេខា</span>
                    </div>
                    <input type="file" id="dept_head_signature_file" accept="image/png, image/jpeg, image/gif" style="margin-top:8px; display:block;" />
                    <input type="hidden" name="department_head_signature" id="dept_head_signature_input" value="">
                    <input type="hidden" name="delete_department_head_signature" id="delete_dept_head_signature_input" value="0">
                    <button type="button" id="delete_dept_head_signature_btn" class="btn btn-sm btn-outline-danger" style="display:none; margin-top:8px;">លុបហត្ថលេខា</button>
                </div>
            </div>
            <div class="form-group" data-name="number_of_days">
              <label>ចំនួនថ្ងៃ</label>
              <select name="number_of_days">
                <option value="">ជ្រើសរើសចំនួនថ្ងៃ</option>
                <?php for ($i = 0.5; $i <= 9; $i += 0.5): ?>
                  <option value="<?php echo $i; ?>"><?php echo $i; ?> ថ្ងៃ</option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="form-group" data-name="remaining_days">
              <label>ចំនួនថ្ងៃនៅសល់</label>
              <input type="number" name="remaining_days" step="0.5" readonly style="background-color: #f8f9fa;">
            </div>

            <div class="form-group" data-name="department">
              <label>ផ្នែក</label>
              <select name="department">
                <option value="">ជ្រើសរើសផ្នែក</option>
                <?php
                if (isset($dynamic_categories['department'])) {
                    foreach ($dynamic_categories['department'] as $name) {
                        echo '<option value="' . htmlspecialchars($name) . '">' . htmlspecialchars($name) . '</option>';
                    }
                }
                ?>
              </select>
            </div>
            <div class="form-group" data-name="position">
              <label>មុខតំណែង</label>
              <select name="position">
                <option value="">ជ្រើសរើសមុខតំណែង</option>
                <?php
                if (isset($dynamic_categories['position'])) {
                    foreach ($dynamic_categories['position'] as $name) {
                        echo '<option value="' . htmlspecialchars($name) . '">' . htmlspecialchars($name) . '</option>';
                    }
                }
                ?>
              </select>
            </div>
            <div class="form-group" data-name="branch">
              <label>សាខា <span class="required-star">*</span></label>
              <select name="branch" required>
                <option value="">ជ្រើសរើសសាខា</option>
                <?php
                if (isset($dynamic_categories['branch'])) {
                    foreach ($dynamic_categories['branch'] as $name) {
                        echo '<option value="' . htmlspecialchars($name) . '">' . htmlspecialchars($name) . '</option>';
                    }
                }
                ?>
              </select>
            </div>
            <div class="form-group" data-name="request_date">
              <label>ថ្ងៃស្នើសុំ <span class="required-star">*</span></label>
              <input type="date" name="request_date" required>
            </div>
            <div class="form-group" data-name="return_date">
              <label>ថ្ងៃត្រឡប់មកវិញ</label>
              <input type="date" name="return_date">
            </div>
            <div class="form-group" data-name="late_hours">
              <label>ចំនួនម៉ោងយឺត</label>
              <input type="text" name="late_hours" placeholder="បញ្ចូលចំនួនម៉ោង (ឧ. 2h)">
            </div>
            <div class="form-group" data-name="forgot_scan_in">
              <label>ភ្លេចស្កេនចូល</label>
              <select name="forgot_scan_in">
                <option value="">ជ្រើសរើស</option>
                <option value="ភ្លេចចូល 1ដង">1 ដង</option>
                <option value="ភ្លេចចូល 2ដង">2 ដង</option>
                <option value="ភ្លេចចូល 3ដង">3 ដង</option>
                <option value="ភ្លេចចូល 4ដង">4 ដង</option>
              </select>
            </div>
            <div class="form-group" data-name="forgot_scan_out">
              <label>ភ្លេចស្កេនចេញ</label>
              <select name="forgot_scan_out">
                <option value="">ជ្រើសរើស</option>
                <option value="ភ្លេចចេញ 1ដង">1 ដង</option>
                <option value="ភ្លេចចេញ 2ដង">2 ដង</option>
                <option value="ភ្លេចចេញ 3ដង">3 ដង</option>
                <option value="ភ្លេចចេញ 4ដង">4 ដង</option>
              </select>
            </div>
            <div class="form-group" data-name="time_in">
              <label>ម៉ោងចូល</label>
              <input type="time" name="time_in">
            </div>
            <div class="form-group" data-name="time_out">
              <label>ម៉ោងចេញ</label>
              <input type="time" name="time_out">
            </div>
            <div class="form-group" data-name="total_hours">
              <label>ម៉ោងសរុប</label>
              <input type="text" name="total_hours" placeholder="ឧ. 8h30m">
            </div>
            <div class="form-group" data-name="repay_time_in">
              <label>ម៉ោងចូលសង</label>
              <input type="time" name="repay_time_in">
            </div>
            <div class="form-group" data-name="repay_time_out">
              <label>ម៉ោងចេញសង</label>
              <input type="time" name="repay_time_out">
            </div>
            <div class="form-group" data-name="repay_total_hours">
              <label>ម៉ោងសងសរុប</label>
              <input type="text" name="repay_total_hours" placeholder="ឧ. 8h30m">
            </div>
            <div class="form-group" data-name="reason">
              <label>មូលហេតុ</label>
              <input type="text" name="reason" placeholder="បញ្ចូលមូលហេតុ">
            </div>
            <div class="form-group" data-name="assigned_to">
              <label>ប្រគល់ការងារទៅ</label>
              <input type="text" name="assigned_to" placeholder="ឈ្មោះអ្នកទទួលការងារ">
            </div>
            <div class="form-group" data-name="location">
              <label>ទីតាំង</label>
              <input type="text" name="location" placeholder="បញ្ចូលទីតាំង">
            </div>
            <div class="form-group" data-name="contact_number">
              <label>លេខទំនាក់ទំនង</label>
              <input type="number" name="contact_number" placeholder="ឧ. 012345678">
            </div>

            <!-- Dept Head Selection Modal trigger and markup -->
            <div class="modal fade" id="deptHeadModal" tabindex="-1" aria-labelledby="deptHeadModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-md modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deptHeadModalLabel">ជ្រើសប្រធានផ្នែក និង Upload ហត្ថលេខា</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label>ជ្រើសរើសប្រធានផ្នែក</label>
                                <div class="input-group">
                                    <select id="modal_dept_head_select" class="form-control">
                                        <option value="">-- ជ្រើសឈ្មោះប្រធានផ្នែក --</option>
                                        <?php foreach ($managersList as $m): ?>
                                            <option value="<?php echo (int)$m['id']; ?>"><?php echo htmlspecialchars($m['full_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" id="modal_add_dept_head_btn" class="btn btn-outline-secondary">បង្កើត</button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label>ហត្ថលេខាប្រធានផ្នែក (Upload)</label>
                                <div style="border:1px dashed #ced4da; padding:10px; border-radius:8px; background:#fafafa;">
                                    <div id="modal_dept_head_signature_preview" style="min-height:80px; display:flex; align-items:center; justify-content:center;">
                                        <img id="modal_dept_head_signature_img" src="" alt="signature" style="max-width:100%; max-height:120px; display:none; object-fit:contain;" />
                                        <span id="modal_dept_head_signature_empty" style="color:#888;">មិនមានហត្ថលេខា</span>
                                    </div>
                                    <input type="file" id="modal_dept_head_signature_file" accept="image/png, image/jpeg, image/gif" class="form-control mt-2" />
                                    <div class="mt-2">
                                        <button type="button" id="modal_delete_dept_head_signature_btn" class="btn btn-sm btn-outline-danger" style="display:none;">លុបហត្ថលេខា</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">បោះបង់</button>
                            <button type="button" class="btn btn-primary" id="modal_dept_head_confirm_btn">យល់ព្រម</button>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>បញ្ជូនសំណើរ</button>
        </form>
        <div class="text-center mt-4">
            <button type="button" class="btn btn-secondary" onclick="window.location.href='https://app.vvc.asia/homes.php'">
                <i class="fas fa-arrow-left me-2"></i>ត្រឡប់ក្រោយ
            </button>
        </div>
    </div>

    <div class="modal fade" id="loadingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="loadingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center p-4">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5 class="mb-0" id="loadingModalLabel">កំពុងបញ្ជូនទិន្នន័យ...</h5>
                    <p class="text-muted small mt-2">សូមមេត្តារង់ចាំបន្តិច</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ========= START: NEW HTML FOR CUSTOM CONFIRM ALERT ========= -->
    <div id="customConfirmModal" class="custom-modal-overlay">
        <div class="custom-modal-box">
            <div class="custom-modal-icon-wrapper">
                <i class="fas fa-question-circle"></i>
            </div>
            <h4 class="custom-modal-title">ការបញ្ជាក់</h4>
            <p class="custom-modal-text">មិនបានជ្រើសឈ្មោះប្រធានផ្នែកទេ។ តើអ្នកចង់បន្តដោយគ្មានប្រធានផ្នែកឬ?</p>
            <div class="custom-modal-buttons">
                <button type="button" class="btn btn-secondary" id="customConfirmCancel">បោះបង់</button>
                <button type="button" class="btn btn-primary" id="customConfirmOk">យល់ព្រម</button>
            </div>
        </div>
    </div>
    <!-- ========= END: NEW HTML FOR CUSTOM CONFIRM ALERT ========= -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- =================== START: FULLY CORRECTED JAVASCRIPT =================== -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    // DOM elements
    const icons = document.querySelectorAll('.request-icon');
    const hiddenInput = document.getElementById('selectedRequestTypes');
    const allFormGroups = document.querySelectorAll('#requestForm .form-group[data-name]');
    const handPointerIcon = document.getElementById('hand-pointer-icon');
    const requestForm = document.getElementById('requestForm');
    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
    const errorContainer = document.getElementById('js-error-container');

    // Field visibility config
    const fieldConfig = {
        'សម្រាកប្រចាំឆ្នាំ (Annual Leave)': ['requester_name','department_head_name','number_of_days','remaining_days','department','position','branch','request_date','return_date','time_in','time_out','total_hours','reason','assigned_to','location','contact_number'],
        'ភ្លេចស្កេនមេដៃ (Forgot FP)': ['requester_name','department_head_name','department','position','branch','request_date','forgot_scan_in','forgot_scan_out','reason'],
        'ថែមម៉ោង (OT)': ['requester_name','department_head_name','number_of_days','department','position','branch','request_date','time_in','time_out','total_hours','reason','contact_number'],
        'ចេញមុនម៉ោង (Early)': ['requester_name','department_head_name','department','position','branch','request_date','time_in','time_out','total_hours','late_hours','reason','contact_number'],
        'ប្តូរថ្ងៃសម្រាក (Changing day off)': ['requester_name','department_head_name','number_of_days','department','position','branch','request_date','return_date','time_in','time_out','total_hours','repay_time_in','repay_time_out','repay_total_hours','assigned_to','reason','contact_number'],
        'មកយឺត (Late)': ['requester_name','department_head_name','department','position','branch','request_date','late_hours','time_in','time_out','total_hours','reason','contact_number'],
        'ឈប់ឥតច្បាប់': ['requester_name','department_head_name','number_of_days','department','position','branch','request_date','return_date','time_in','time_out','total_hours','reason','assigned_to','location','contact_number']
    };
    ['សម្រាកដោយជំងឺ (Sick Leave)', 'សម្រាកលំហែមាតុភាព (Maternity Leave)', 'សម្រាកពិសេស (Special Leave)'].forEach(type => {
        fieldConfig[type] = fieldConfig['សម្រាកប្រចាំឆ្នាំ (Annual Leave)'];
    });

    let originalAnnualBalance = 0;

    // ========= START: NEW FUNCTION FOR CUSTOM CONFIRM ALERT =========
    function showCustomConfirm(message) {
        const modal = document.getElementById('customConfirmModal');
        const textElement = modal.querySelector('.custom-modal-text');
        const okButton = document.getElementById('customConfirmOk');
        const cancelButton = document.getElementById('customConfirmCancel');

        textElement.textContent = message;
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);

        return new Promise((resolve) => {
            const close = (value) => {
                modal.classList.remove('show');
                setTimeout(() => {
                    modal.style.display = 'none';
                    okButton.removeEventListener('click', okListener);
                    cancelButton.removeEventListener('click', cancelListener);
                    resolve(value);
                }, 300);
            };

            const okListener = () => close(true);
            const cancelListener = () => close(false);

            okButton.addEventListener('click', okListener);
            cancelButton.addEventListener('click', cancelListener);
        });
    }
    // ========= END: NEW FUNCTION FOR CUSTOM CONFIRM ALERT =========

    function updateFormFields(selectedType) {
        allFormGroups.forEach(group => {
            if (group.getAttribute('data-name')) group.style.display = 'none';
        });

        const deptHeadInput = document.querySelector('input[name="department_head_name"]');
        const deptHeadHasValue = deptHeadInput && deptHeadInput.value.trim() !== '';
        const fieldsToShow = fieldConfig[selectedType] || [];

        if (selectedType) {
            handPointerIcon.classList.remove('visible');
            fieldsToShow.forEach(fieldName => {
                const group = document.querySelector(`.form-group[data-name="${fieldName}"]`);
                if (group) group.style.display = 'block';
            });
            const deptSigGroup = document.getElementById('dept-head-signature-group');
            if (deptSigGroup) {
                deptSigGroup.style.display = deptHeadHasValue ? 'block' : 'none';
            }
        } else {
            handPointerIcon.classList.add('visible');
        }
    }

    let pendingSelectedType = '';
    icons.forEach(icon => {
        icon.addEventListener('click', function () {
            const isActive = this.classList.contains('active');
            if (isActive) {
                this.classList.remove('active');
                hiddenInput.value = '';
                document.querySelector('input[name="department_head_name"]').value = '';
                document.getElementById('dept_head_signature_input').value = '';
                document.getElementById('delete_dept_head_signature_input').value = '1';
                document.querySelector('input[name="remaining_days"]').value = '';
                originalAnnualBalance = 0;
                updateFormFields('');
                pendingSelectedType = '';
                return;
            }

            pendingSelectedType = this.getAttribute('data-value');
            const deptModalEl = document.getElementById('deptHeadModal');
            const deptModal = new bootstrap.Modal(deptModalEl);
            document.getElementById('modal_dept_head_select').value = '';
            document.getElementById('modal_dept_head_signature_img').src = '';
            document.getElementById('modal_dept_head_signature_img').style.display = 'none';
            document.getElementById('modal_dept_head_signature_empty').style.display = 'block';
            document.getElementById('modal_dept_head_signature_file').value = '';
            document.getElementById('modal_delete_dept_head_signature_btn').style.display = 'none';
            deptModal.show();
        });
    });

    // Event listener for number_of_days change to update remaining_days for annual leave
    const numberOfDaysSelect = document.querySelector('select[name="number_of_days"]');
    if (numberOfDaysSelect) {
        numberOfDaysSelect.addEventListener('change', function () {
            if (hiddenInput.value === 'សម្រាកប្រចាំឆ្នាំ (Annual Leave)' && originalAnnualBalance > 0) {
                const selectedDays = parseFloat(this.value) || 0;
                const remaining = Math.max(0, originalAnnualBalance - selectedDays);
                document.querySelector('input[name="remaining_days"]').value = remaining;
            }
        });
    }

    const modalFile = document.getElementById('modal_dept_head_signature_file');
    const modalPreviewImg = document.getElementById('modal_dept_head_signature_img');
    const modalEmptyText = document.getElementById('modal_dept_head_signature_empty');
    const modalDeleteBtn = document.getElementById('modal_delete_dept_head_signature_btn');
    let modalCleanedSignature = '';

    const modalSelect = document.getElementById('modal_dept_head_select');
    if (modalSelect) {
        modalSelect.addEventListener('change', function () {
            const id = this.value;
            modalCleanedSignature = '';
            modalPreviewImg.src = ''; modalPreviewImg.style.display = 'none'; modalEmptyText.style.display = 'block';
            modalDeleteBtn.style.display = 'none';
            modalFile.style.display = 'block'; modalFile.value = '';
            if (!id) return;
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: new URLSearchParams({ action: 'get_dept_head', id: id })
            }).then(r => r.json()).then(res => {
                if (res && res.success && res.signature) {
                    modalCleanedSignature = res.signature;
                    modalPreviewImg.src = res.signature; modalPreviewImg.style.display = 'block'; modalEmptyText.style.display = 'none';
                    modalDeleteBtn.style.display = 'inline-block';
                    modalFile.style.display = 'none';
                }
            }).catch(err => console.error('Failed to load head signature:', err));
        });
    }

    if (modalFile) {
        modalFile.addEventListener('change', async function (e) {
            const file = e.target.files[0];
            if (!file) return;
            if (!/^image\//i.test(file.type)) { alert('សូមជ្រើសរើសរូបភាព (PNG/JPG/GIF)'); return; }
            try {
                const raw = await readFileAsDataURL(file);
                const cleaned = await removeBackgroundToPng(raw, 800, 400);
                modalCleanedSignature = cleaned;
                modalPreviewImg.src = cleaned; modalPreviewImg.style.display = 'block'; modalEmptyText.style.display = 'none';
                modalDeleteBtn.style.display = 'inline-block';
                modalFile.style.display = 'none';
            } catch (err) {
                alert('មានបញ្ហាក្នុងការដំណើរការហត្ថលេខា: ' + err.message);
            }
        });
    }

    modalDeleteBtn?.addEventListener('click', function () {
        modalCleanedSignature = '';
        modalPreviewImg.src = ''; modalPreviewImg.style.display = 'none'; modalEmptyText.style.display = 'block';
        modalDeleteBtn.style.display = 'none';
        modalFile.style.display = 'block'; modalFile.value = '';
    });

    document.getElementById('modal_add_dept_head_btn')?.addEventListener('click', function () {
        const name = prompt('បញ្ចូលឈ្មោះប្រធានផ្នែកថ្មី:');
        if (!name || !name.trim()) return;
        const createBtn = this;
        createBtn.disabled = true;
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: new URLSearchParams({ action: 'create_dept_head', name: name.trim() })
        }).then(r => r.json()).then(res => {
            if (res && res.success) {
                const sel = document.getElementById('modal_dept_head_select');
                const opt = document.createElement('option'); opt.value = res.id; opt.textContent = res.name; opt.selected = true;
                sel.appendChild(opt);
                document.getElementById('modal_dept_head_signature_preview').style.display = 'flex';
                modalCleanedSignature = '';
                modalPreviewImg.src = ''; modalPreviewImg.style.display = 'none'; modalEmptyText.style.display = 'block';
            } else {
                alert('មិនអាចបង្កើតប្រធានផ្នែកថ្មីបាន: ' + (res && res.message ? res.message : 'Unknown'));
            }
        }).catch(err => alert('កំហុសនៅពេលបញ្ចូន: ' + err.message)
        ).finally(() => { createBtn.disabled = false; });
    });

    // ========= START: MODIFIED EVENT LISTENER TO USE CUSTOM ALERT =========
    document.getElementById('modal_dept_head_confirm_btn').addEventListener('click', async function () { // Make function async
        const sel = document.getElementById('modal_dept_head_select');
        const selectedId = sel.value || '';
        const selectedName = (sel.selectedIndex > 0 && sel.options[sel.selectedIndex]) ? sel.options[sel.selectedIndex].text : '';
        
        if (!selectedName) {
            // Use the new custom confirm modal
            const userConfirmed = await showCustomConfirm('មិនបានជ្រើសឈ្មោះប្រធានផ្នែកទេ។ តើអ្នកចង់បន្តដោយគ្មានប្រធានផ្នែកឬ?');
            if (!userConfirmed) {
                return; // User clicked "Cancel", so stop execution
            }
        }
        
        const deptInput = document.querySelector('input[name="department_head_name"]');
        if (deptInput) deptInput.value = selectedName;
        
        const mainHiddenSig = document.getElementById('dept_head_signature_input');
        const mainDeleteSig = document.getElementById('delete_dept_head_signature_input');
        const mainFileInput = document.getElementById('dept_head_signature_file');
        const mainPreviewImg = document.getElementById('dept-head-signature-img');
        const mainEmptyText = document.getElementById('dept-head-signature-empty');
        const mainDeleteBtn = document.getElementById('delete_dept_head_signature_btn');
        
        if (modalCleanedSignature) {
            if (mainHiddenSig) mainHiddenSig.value = modalCleanedSignature;
            if (mainDeleteSig) mainDeleteSig.value = '0';
            if (mainPreviewImg) { mainPreviewImg.src = modalCleanedSignature; mainPreviewImg.style.display = 'block'; }
            if (mainEmptyText) mainEmptyText.style.display = 'none';
            if (mainFileInput) mainFileInput.style.display = 'none';
            if (mainDeleteBtn) mainDeleteBtn.style.display = 'inline-block';
        } else {
            if (mainHiddenSig) mainHiddenSig.value = '';
            if (mainDeleteSig) mainDeleteSig.value = '1';
            if (mainPreviewImg) { mainPreviewImg.src = ''; mainPreviewImg.style.display = 'none'; }
            if (mainEmptyText) mainEmptyText.style.display = 'block';
            if (mainFileInput) { mainFileInput.style.display = 'block'; mainFileInput.value = ''; }
            if (mainDeleteBtn) mainDeleteBtn.style.display = 'none';
        }

        const matchingIcon = Array.from(icons).find(i => i.getAttribute('data-value') === pendingSelectedType);
        icons.forEach(i => i.classList.remove('active'));
        if (matchingIcon) matchingIcon.classList.add('active');
        hiddenInput.value = pendingSelectedType;
        updateFormFields(pendingSelectedType);

        // If annual leave, fetch balance
        if (pendingSelectedType === 'សម្រាកប្រចាំឆ្នាំ (Annual Leave)') {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: new URLSearchParams({ action: 'get_annual_balance', user_id: document.querySelector('input[name="user_id"]').value })
            }).then(r => r.json()).then(res => {
                if (res && res.success) {
                    originalAnnualBalance = res.balance;
                    document.querySelector('input[name="remaining_days"]').value = res.balance;
                }
            }).catch(err => console.error('Failed to load annual balance:', err));
        }

        if (selectedId) {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Accept': 'application/json' },
                body: new URLSearchParams({ action: 'save_head_signature', id: selectedId, signature: modalCleanedSignature })
            }).catch(err => console.error('Failed to save head signature:', err));
        }

        const deptModalEl = document.getElementById('deptHeadModal');
        const deptModal = bootstrap.Modal.getInstance(deptModalEl);
        if (deptModal) deptModal.hide();
        pendingSelectedType = '';
    });
    // ========= END: MODIFIED EVENT LISTENER =========

    const deptHeadInput = document.querySelector('input[name="department_head_name"]');
    if (deptHeadInput) {
        deptHeadInput.addEventListener('input', function () {
            updateFormFields(hiddenInput.value || '');
        });
    }

    function readFileAsDataURL(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = e => resolve(e.target.result);
            reader.onerror = () => reject(new Error('មិនអាចអានរូបភាពបានទេ'));
            reader.readAsDataURL(file);
        });
    }

    async function removeBackgroundToPng(dataUrl, targetW, targetH) {
        const img = await new Promise((resolve, reject) => {
            const im = new Image();
            im.onload = () => resolve(im);
            im.onerror = () => reject(new Error('មិនអាចផ្ទុករូបភាពបានទេ'));
            im.src = dataUrl;
        });

        const scale = Math.min((targetW||img.naturalWidth)/img.naturalWidth, (targetH||img.naturalHeight)/img.naturalHeight, 1);
        const w = Math.max(1, Math.round(img.naturalWidth * scale));
        const h = Math.max(1, Math.round(img.naturalHeight * scale));

        const off = document.createElement('canvas');
        off.width = w; off.height = h;
        const ctx = off.getContext('2d');
        ctx.drawImage(img, 0, 0, w, h);

        function sample(x, y, bw=8, bh=8){
            const sx = Math.max(0, Math.min(w-bw, x));
            const sy = Math.max(0, Math.min(h-bh, y));
            const d = ctx.getImageData(sx, sy, bw, bh).data; let r=0,g=0,b=0,n=0;
            for(let i=0;i<d.length;i+=4){ r+=d[i]; g+=d[i+1]; b+=d[i+2]; n++; }
            return [r/n,g/n,b/n];
        }
        const c1=sample(0,0), c2=sample(w-8,0), c3=sample(0,h-8), c4=sample(w-8,h-8);
        const bg=[(c1[0]+c2[0]+c3[0]+c4[0])/4,(c1[1]+c2[1]+c3[1]+c4[1])/4,(c1[2]+c2[2]+c3[2]+c4[2])/4];
        for (let i=0;i<3;i++){ if(!isFinite(bg[i])) bg[i]=255; }

        const imgData = ctx.getImageData(0,0,w,h); const d=imgData.data;
        const tol=35, tol2=tol*tol, whiteThr=245;
        function dist2(r,g,b){ const dr=r-bg[0], dg=g-bg[1], db=b-bg[2]; return dr*dr+dg*dg+db*db; }
        for(let i=0;i<d.length;i+=4){
            const r=d[i], g=d[i+1], b=d[i+2];
            const nearWhite = (r>=whiteThr && g>=whiteThr && b>=whiteThr);
            const nearBg = dist2(r,g,b) <= tol2;
            if (nearWhite || nearBg) d[i+3]=0;
        }
        ctx.putImageData(imgData,0,0);
        return off.toDataURL('image/png');
    }

    const deptFileInput = document.getElementById('dept_head_signature_file');
    const deptHiddenInput = document.getElementById('dept_head_signature_input');
    const deptPreviewImg = document.getElementById('dept-head-signature-img');
    const deptEmptyText = document.getElementById('dept-head-signature-empty');
    const deptDeleteBtn = document.getElementById('delete_dept_head_signature_btn');

    if (deptFileInput) {
        deptFileInput.addEventListener('change', async function (e) {
            const file = e.target.files[0];
            if (!file) return;
            if (!/^image\//i.test(file.type)) { alert('សូមជ្រើសរើសរូបភាព (PNG/JPG/GIF)'); return; }
            try {
                const raw = await readFileAsDataURL(file);
                const cleaned = await removeBackgroundToPng(raw, 800, 400);
                deptPreviewImg.src = cleaned; deptPreviewImg.style.display = 'block'; deptEmptyText.style.display = 'none';
                deptHiddenInput.value = cleaned; document.getElementById('delete_dept_head_signature_input').value = '0';
                deptDeleteBtn.style.display = 'inline-block';
                if (deptFileInput) deptFileInput.style.display = 'none';
            } catch (err) {
                alert('មានបញ្ហាក្នុងការដំណើរការហត្ថលេខា: ' + err.message);
            }
        });
    }

    if (deptDeleteBtn) {
        deptDeleteBtn.addEventListener('click', function () {
            deptHiddenInput.value = '';
            document.getElementById('delete_dept_head_signature_input').value = '1';
            deptPreviewImg.src = ''; deptPreviewImg.style.display = 'none'; deptEmptyText.style.display = 'block';
            deptDeleteBtn.style.display = 'none';
            if (deptFileInput) { deptFileInput.value = ''; deptFileInput.style.display = 'block'; }
        });
    }

    requestForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        errorContainer.style.display = 'none';
        if (!hiddenInput.value) {
            errorContainer.textContent = 'សូមជ្រើសរើសប្រភេទនៃការស្នើសុំមួយ!';
            errorContainer.style.display = 'block';
            return;
        }
        loadingModal.show();
        const formData = new FormData(requestForm);
        try {
            const response = await fetch(requestForm.action, {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'មានបញ្ហាក្នុងការបញ្ជូនទិន្នន័យ។');
            if (result.success) {
                window.location.href = result.redirectUrl;
            } else {
                throw new Error(result.message || 'មានបញ្ហាដែលមិនបានរំពឹងទុក។');
            }
        } catch (error) {
            loadingModal.hide();
            errorContainer.textContent = error.message;
            errorContainer.style.display = 'block';
        }
    });

    updateFormFields('');
    handPointerIcon.classList.add('visible');
});
</script>
<!-- =================== END: FULLY CORRECTED JAVASCRIPT =================== -->

    <?php endif; ?>

</body>
</html>