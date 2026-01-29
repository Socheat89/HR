<?php
// Start session for user tracking
session_start();

// --- CONFIGURATION ---
$dbHost = 'localhost';
$dbName = 'samann1_admin_panel';
$dbUser = 'samann1_admin_panel';
$dbPass = 'admin_panel@2025';
$telegramChatId = '-1002496391098';
define('BASE_URL', $_SERVER['PHP_SELF']);

// --- END CONFIGURATION ---

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
require_once __DIR__ . '/includes/telegram.php';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    die("កំហុសក្នុងការភ្ជាប់មូលដ្ឋានទិន្នន័យ: " . $e->getMessage());
}

// Fetch current user details
$currentUserFullName = 'អ្នកប្រើមិនស្គាល់';
$currentUserId = null;
if (isset($_SESSION['user_id'])) {
    $currentUserId = $_SESSION['user_id'];
    $stmtUser = $pdo->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute([$currentUserId]);
    $user = $stmtUser->fetch();
    if ($user) {
        $currentUserFullName = $user['full_name'];
    }
} else {
    // ADDED: Redirect unauthenticated users to login page
    header("Location: login.php");
    exit;
}

$error = null;
$success = null;

// Define request fields (including user_id and requester_name)
$requestFields = [
    'request_type', 'user_id', 'requester_name', 'number_of_days', 'remaining_days',
    'department', 'position', 'branch', 'request_date', 'return_date',
    'late_hours', 'forgot_scan_in', 'forgot_scan_out', 'time_in', 'time_out',
    'total_hours', 'repay_time_in', 'repay_time_out', 'repay_total_hours',
    'reason', 'assigned_to', 'location', 'contact_number', 'signature', 'status'
];

// --- HANDLE ADD NEW REQUEST ---
if (isset($_POST['submit_add_request'])) {
    $newRequestData = [];
    foreach ($requestFields as $field) {
        $newRequestData[$field] = $_POST[$field] ?? null;
    }

    // For Non-Admins, force user_id and requester_name
    if (!$isAdmin) {
        $newRequestData['user_id'] = $currentUserId;
        $newRequestData['requester_name'] = $currentUserFullName;
    } else {
        // For Admins, fetch requester_name from users table based on user_id
        if (!empty($newRequestData['user_id'])) {
            $stmtUser = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
            $stmtUser->execute([$newRequestData['user_id']]);
            $user = $stmtUser->fetch();
            $newRequestData['requester_name'] = $user ? $user['full_name'] : null;
        }
    }

    // Set created_at
    $newRequestData['created_at'] = date('Y-m-d H:i:s');

    // Basic validation
    if (empty($newRequestData['request_type']) || empty($newRequestData['user_id']) || empty($newRequestData['request_date'])) {
        $error = "សូមបំពេញគ្រប់ Field ដែលមានសញ្ញា (*) នៅក្នុងទម្រង់បន្ថែម។";
    } else {
        try {
            $columns = implode(', ', array_keys($newRequestData));
            $placeholders = implode(', ', array_fill(0, count($newRequestData), '?'));
            $sql = "INSERT INTO requests ($columns) VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($newRequestData));
            $newId = $pdo->lastInsertId();

            $message = "🆕 *សំណើថ្មីត្រូវបានបន្ថែម*\n" .
                       "អ្នកប្រើ (អ្នកបន្ថែម): $currentUserFullName\n" .
                       "ប្រភេទស្នើសុំ: {$newRequestData['request_type']}\n" .
                       "អ្នកស្នើសុំ: {$newRequestData['requester_name']}\n" .
                       "កាលបរិច្ឆេទ: " . date('Y-m-d H:i:s');
            sendTelegramMessage($telegramChatId, $message);
            $_SESSION['success_message'] = "សំណើ (ID: $newId) ត្រូវបានបន្ថែមដោយជោគជ័យ។";
            header("Location: " . BASE_URL);
            exit;
        } catch (PDOException $e) {
            $error = "កំហុសក្នុងការបន្ថែមកំណត់ត្រា: " . $e->getMessage();
        }
    }
}

// --- HANDLE EDIT REQUEST ---
if (isset($_POST['edit_id'])) {
    $edit_id = (int)$_POST['edit_id'];

    $stmtOriginal = $pdo->prepare("SELECT r.*, u.full_name FROM requests r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
    $stmtOriginal->execute([$edit_id]);
    $originalRequest = $stmtOriginal->fetch();

    if (!$originalRequest) {
        $error = "រកមិនឃើញសំណើដែលត្រូវកែសម្រួលទេ។";
    } elseif ($isAdmin || ($originalRequest['user_id'] == $currentUserId)) {
        $updateFields = [];
        foreach ($requestFields as $field) {
            if (isset($_POST[$field])) {
                $updateFields[$field] = $_POST[$field];
            }
        }

        // ADDED: Explicitly prevent non-admins from modifying user_id and requester_name
        if (!$isAdmin) {
            $updateFields['user_id'] = $originalRequest['user_id'];
            $updateFields['requester_name'] = $originalRequest['requester_name'];
        } else {
            // For Admins, update requester_name based on user_id
            if (isset($updateFields['user_id']) && $updateFields['user_id'] != $originalRequest['user_id']) {
                $stmtUser = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                $stmtUser->execute([$updateFields['user_id']]);
                $user = $stmtUser->fetch();
                $updateFields['requester_name'] = $user ? $user['full_name'] : $originalRequest['requester_name'];
            }
        }

        if (!empty($updateFields)) {
            $setParts = [];
            $updateValues = [];
            foreach ($updateFields as $key => $value) {
                $setParts[] = "$key = ?";
                $updateValues[] = $value;
            }
            $setClause = implode(', ', $setParts);
            $updateValues[] = $edit_id;

            try {
                $stmtUpdate = $pdo->prepare("UPDATE requests SET $setClause WHERE id = ?");
                $stmtUpdate->execute($updateValues);

                $changes = [];
                foreach ($updateFields as $key => $newValue) {
                    $oldValue = $originalRequest[$key] ?? 'មិនមាន';
                    if ((string)$oldValue != (string)$newValue) {
                        if ($key === 'user_id') {
                            $stmtOldUser = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                            $stmtOldUser->execute([$oldValue]);
                            $oldUser = $stmtOldUser->fetch();
                            $oldName = $oldUser ? $oldUser['full_name'] : 'មិនស្គាល់';
                            $newName = $updateFields['requester_name'] ?? 'មិនស្គាល់';
                            $changes[] = "$key: '$oldName' -> '$newName'";
                        } else {
                            $changes[] = "$key: '$oldValue' -> '$newValue'";
                        }
                    }
                }
                if (!empty($changes)) {
                    $editedBy = $isAdmin ? "(Admin) $currentUserFullName" : $currentUserFullName;
                    $message = "✏️ ការស្នើសុំត្រូវបានកែដោយ: $editedBy\n" .
                               "__________________\n" .
                               "លេខសម្គាល់: $edit_id\n" .
                               "ប្រភេទស្នើសុំ: {$updateFields['request_type']}\n" .
                               "អ្នកស្នើសុំ: {$updateFields['requester_name']}\n" .
                               "ការផ្លាស់ប្តូរ:\n" . implode("\n", $changes) . "\n" .
                               "កាលបរិច្ឆេទ: " . date('Y-m-d H:i:s');
                    sendTelegramMessage($telegramChatId, $message);
                }
                $_SESSION['success_message'] = "សំណើ (ID: $edit_id) ត្រូវបានកែសម្រួលដោយជោគជ័យ។";
            } catch (PDOException $e) {
                $error = "កំហុសក្នុងការកែសម្រួល: " . $e->getMessage();
            }
        } else {
            $_SESSION['success_message'] = "មិនមានការផ្លាស់ប្តូរត្រូវបានធ្វើឡើងចំពោះសំណើ (ID: $edit_id)។";
        }

        header("Location: " . BASE_URL);
        exit;
    } else {
        $error = "អ្នកមិនមានសិទ្ធិកែសម្រួលសំណើនេះទេ។";
    }
}

// --- HANDLE DELETE REQUEST ---
if (isset($_POST['delete_id']) && $isAdmin) {
    $delete_id = (int)$_POST['delete_id'];
    
    $stmtFetch = $pdo->prepare("SELECT r.*, u.full_name FROM requests r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
    $stmtFetch->execute([$delete_id]);
    $requestToDelete = $stmtFetch->fetch();
    
    if ($requestToDelete) {
        try {
            $stmtDelete = $pdo->prepare("DELETE FROM requests WHERE id = ?");
            $stmtDelete->execute([$delete_id]);
            
            $message = "🗑️ *ការលុបការស្នើសុំ*\n" .
                       "អ្នកប្រើ (Admin): $currentUserFullName\n" .
                       "លេខសម្គាល់: {$requestToDelete['id']}\n" .
                       "ប្រភេទស្នើសុំ: {$requestToDelete['request_type']}\n" .
                       "អ្នកស្នើសុំ: {$requestToDelete['requester_name']}\n" .
                       "ហេតុផល: {$requestToDelete['reason']}\n" .
                       "កាលបរិច្ឆេទលុប: " . date('Y-m-d H:i:s');
            sendTelegramMessage($telegramChatId, $message);
            $_SESSION['success_message'] = "សំណើ (ID: $delete_id) ត្រូវបានលុបដោយជោគជ័យ។";
        } catch (PDOException $e) {
            $error = "កំហុសក្នុងការលុប: " . $e->getMessage();
        }
    } else {
        $error = "រកមិនឃើញសំណើដែលត្រូវលុបទេ។";
    }
    header("Location: " . BASE_URL);
    exit;
} elseif (isset($_POST['delete_id']) && !$isAdmin) {
    $error = "អ្នកមិនមានសិទ្ធិលុបកំណត់ត្រាទេ។";
}

// --- FETCH REQUESTS FOR DISPLAY ---
$sql = "SELECT r.*, u.full_name AS user_full_name FROM requests r LEFT JOIN users u ON r.user_id = u.id";
$params = [];
if (!$isAdmin) {
    if ($currentUserId !== null) {
        $sql .= " WHERE r.user_id = ?";
        $params[] = $currentUserId;
    } else {
        $sql .= " WHERE 1 = 0";
    }
}
$sql .= " ORDER BY r.request_date DESC, r.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "កំហុសមូលដ្ឋានទិន្នន័យពេលទាញទិន្នន័យ: " . $e->getMessage();
    $requests = [];
}

// Retrieve flash messages
if (empty($success) && isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (empty($error) && isset($_SESSION['error_message'])) {
    $error = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
     <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2x/Logo-Van-Van-1.png">
    <title>គ្រប់គ្រងសំណើ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap');

        body {
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 20px;
        }
        body, .btn, .modal-title, .form-table td, .main-footer th, .report-title, input::placeholder, .span, .form-label {
            font-family: 'Noto Sans Khmer', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .required-field::after { content: " *"; color: red; }
        .btn-edit { background-color: #ffc107; border: none; padding: 6px 12px; font-size: 0.9rem; border-radius: 5px; color: white; transition: background-color 0.3s ease; margin-right: 5px; }
        .btn-edit:hover { background-color: #e0a800; color: white; }
        .btn-delete { background-color: #dc3545; border: none; padding: 6px 12px; font-size: 0.9rem; border-radius: 5px; color: white; transition: background-color 0.3s ease; }
        .btn-delete:hover { background-color: #c82333; color: white; }
        .btn-detail { background-color: #17a2b8; border: none; padding: 6px 12px; font-size: 0.9rem; border-radius: 5px; color: white; transition: background-color 0.3s ease; margin-right: 5px; }
        .btn-detail:hover { background-color: #138496; color: white; }
        .btn-print { background-color: #28a745; border: none; padding: 6px 12px; font-size: 0.9rem; border-radius: 5px; color: white; transition: background-color 0.3s ease; }
        .btn-print:hover { background-color: #218838; color: white; }
        .edit-field { width: 100%; padding: 5px; border: 1px solid #ced4da; border-radius: 4px; display: none; font-family: 'Noto Sans Khmer', sans-serif; }
        .detail-item.editing .display-text { display: none; }
        .detail-item.editing .edit-field { display: block; }
        .report-container { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .report-title { color: #2c3e50; font-size: 2rem; font-weight: 700; text-align: center; margin-bottom: 1.5rem; text-transform: uppercase; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e0e6f0; font-family: 'Noto Sans Khmer', sans-serif; }
        th { background-color: #3498db; color: white; font-weight: 600; }
        tr:hover { background-color: #f5f7fa; }
        .btn-back { background-color: #7f8c8d; border: none; padding: 10px 20px; font-size: 1rem; border-radius: 8px; transition: background-color 0.3s ease, transform 0.2s ease; color: white; text-decoration: none; display: inline-block; margin-top: 20px; }
        .btn-back:hover { background-color: #6c757d; transform: translateY(-2px); }
        .modal-content { border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); }
        .modal-header { background-color: #3498db; color: white; border-top-left-radius: 15px; border-top-right-radius: 15px; }
        .modal-title { font-weight: 600; }
        .modal-body { padding: 2rem; background-color: #f8f9fa; }
        .section-header { font-size: 1.2rem; font-weight: 600; color: #2c3e50; margin-bottom: 1rem; border-bottom: 2px solid #3498db; padding-bottom: 0.3rem; font-family: 'Noto Sans Khmer', sans-serif;}
        .detail-row { display: flex; flex-wrap: wrap; gap: 1rem; }
        .detail-item { flex: 1 1 45%; background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05); margin-bottom: 1rem; }
        .detail-item i { color: #3498db; margin-right: 0.5rem; }
        .detail-item strong { color: #2c3e50; font-weight: 600; font-family: 'Noto Sans Khmer', sans-serif;}
        .detail-item span { color: #34495e; font-family: 'Noto Sans Khmer', sans-serif;}
        .modal-footer { border-top: none; padding: 1rem 2rem; }
        .print-request-form { font-family: 'Noto Sans Khmer', sans-serif; margin-bottom: 20px; }
        .print-request-form .container { max-width: 800px; margin: 0 auto; border: 2px solid #000; padding: 10px; }
        .print-request-form .header { text-align: center; }
        .print-request-form .header img { max-width: 200px; height: auto; }
        .print-request-form .form-table { width: 100%; border-collapse: collapse; }
        .print-request-form .form-table td { border: 1px solid #000; padding: 8px; font-family: 'Noto Sans Khmer', sans-serif; font-size: 14px; }
        .icon-group { display: flex; flex-wrap: wrap; gap: 15px; margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 8px; border: 1px solid #e0e6f0; align-items: center; justify-content: center; }
        .request-icon-print {
            display: flex;
            align-items: center;
            font-size: 10px;
            font-family: 'Noto Sans Khmer', sans-serif;
            padding: 6px 10px;
            border-radius: 5px;
            background-color: #f0f0f0;
            color: #555;
            opacity: 0.7;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .request-icon-print.selected {
            background-color: #28a745 !important;
            color: #ffffff !important;
            opacity: 1 !important;
            font-weight: bold;
        }
        .print-request-form .main-footer { width: 100%; border: 1px solid #000; border-collapse: collapse; margin-top: 20px; }
        .print-request-form .main-footer th { border: none; padding: 8px; font-family: 'Noto Sans Khmer', sans-serif; font-size: 14px; color: black; }
        .print-request-form .main-footer tr { border: none; }
        .table-actions button, .table-actions a { margin-right: 5px; margin-bottom: 5px; }

        @media print {
            body * { visibility: hidden; }
            .print-request-form, .print-request-form * { visibility: visible; }
            .print-request-form { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 0; }
            .report-container { display: none; }
            .no-print { display: none !important; }
            @page { size: A4; margin: 3mm; }
            .request-icon-print {
                background-color: #f0f0f0 !important;
                color: #555 !important;
                opacity: 0.7 !important;
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            .request-icon-print.selected {
                background-color: #28a745 !important;
                color: #ffffff !important;
                opacity: 1 !important;
                font-weight: bold !important;
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
        }
        
      /* === START SIGNATURE SIZE CHANGE === */
        .print-request-form .main-footer .signature-img {
            max-width: 150px;
            max-height: 75px;
            object-fit: contain;
            vertical-align: middle;
        }
        @media print {
            .print-request-form .main-footer .signature-img {
                max-width: 150px;
                max-height: 75px;
                object-fit: contain;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
        /* === END SIGNATURE SIZE CHANGE === */

        @media (max-width: 768px) {
            .report-container { padding: 0.5rem; }
            th, td { font-size: 11px; padding: 8px; }
            .detail-item { flex: 1 1 100%; }
            .request-icon-print { font-size: 9px; padding: 5px 8px; }
            .print-logo { max-width: 150px; height: auto; }
            .report-title { font-size: 1.5rem; }
            .btn-detail, .btn-delete, .btn-print, .btn-edit { font-size: 0.8rem; padding: 5px 10px; }
        }
        .span { display: block; text-align: center; margin: 10px 0; font-family: 'Noto Sans Khmer', sans-serif;}
        .back-btn { background: #6c757d; border: none; padding: 10px 15px; font-size: 1rem; border-radius: 8px; transition: all 0.3s ease; display: inline-flex; align-items: center; font-family: 'Noto Sans Khmer', sans-serif; color: white; text-decoration: none; cursor: pointer; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
        .back-btn:hover { background: #5a6268; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(108, 117, 125, 0.4); color: white; }
        .back-btn i { margin-right: 8px; font-size: 1.1rem; }
        @media (max-width: 768px) {
            .back-btn { font-size: 0.9rem; padding: 8px 12px; border-radius: 10px; }
            .back-btn i { font-size: 1rem; margin-right: 6px; }
        }
        .alert { margin-top: 15px; margin-bottom:15px; }
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="action-bar no-print">
            <h2 class="report-title" style="margin-bottom:0;">បញ្ជីសំណើ</h2>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addRequestModal">
                <i class="fas fa-plus"></i> បន្ថែមសំណើថ្មី
            </button>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success no-print"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger no-print"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="mb-3 no-print">
            <input type="text" id="searchInput" class="form-control" placeholder="ស្វែងរក (ID, ឈ្មោះ, ប្រភេទ, ហេតុផល...)....">
        </div>

        <?php if (empty($requests)): ?>
            <p class="text-center">មិនមានសំណើណាមួយត្រូវបានរកឃើញទេ។</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ប្រភេទសំណើ</th>
                            <th>ឈ្មោះអ្នកស្នើសុំ</th>
                            <th>ផ្នែក</th>
                            <th>កាលបរិច្ឆេទស្នើសុំ</th>
                            <th style="min-width: 150px;">មូលហេតុ</th>
                            <th class="no-print" style="min-width: 180px;">សកម្មភាព</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['id'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($request['request_type'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($request['requester_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(date("d-M-Y", strtotime($request['request_date'] ?? 'now')) ?? 'N/A'); ?></td>
                                <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($request['reason'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($request['reason'] ?? 'N/A'); ?>
                                </td>
                                <td class="no-print table-actions">
                                    <button class="btn btn-sm btn-detail" data-bs-toggle="modal" data-bs-target="#detailModal" 
                                        data-request='<?php echo htmlspecialchars(json_encode($request), ENT_QUOTES, 'UTF-8'); ?>'
                                        data-can-edit="<?php echo ($isAdmin || $request['user_id'] == $currentUserId) ? 'true' : 'false'; ?>">
                                        <i class="fas fa-eye"></i> មើល/កែ
                                    </button>
                                    <?php if ($isAdmin): ?>
                                        <button class="btn btn-sm btn-delete" data-bs-toggle="modal" data-bs-target="#deleteConfirmModal"
                                            data-id="<?php echo $request['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($request['request_type'] . ' ដោយ ' . $request['requester_name']); ?>">
                                            <i class="fas fa-trash"></i> លុប
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="text-center mt-4 no-print">
            <button type="button" class="btn btn-info" id="printRequestFormButton">
                <i class="fas fa-print"></i> បោះពុម្ពសំណើ (ទាំងអស់ដែលបង្ហាញ)
            </button>
            <button type="button" class="back-btn btn btn-secondary" onclick="window.location.href='https://app.vvc.asia/requests_menu.php'">
                <i class="fas fa-arrow-left"></i> ត្រឡប់ទៅ Menu
            </button>
        </div>
    </div>

    <!-- Add Request Modal -->
    <div class="modal fade" id="addRequestModal" tabindex="-1" aria-labelledby="addRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form method="POST" action="<?php echo BASE_URL; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addRequestModalLabel"><i class="fas fa-plus-circle"></i> បន្ថែមសំណើថ្មី</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="section-header"><i class="fas fa-user"></i> ព័ត៌មានបុគ្គល</div>
                        <div class="detail-row">
                            <?php if ($isAdmin): ?>
                                <div class="detail-item">
                                    <label for="add_user_id" class="form-label required-field">អ្នកប្រើ ID:</label>
                                    <input type="text" name="user_id" id="add_user_id" class="form-control" required>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($currentUserId); ?>">
                            <?php endif; ?>
                            <div class="detail-item">
                                <label for="add_requester_name" class="form-label required-field">ឈ្មោះអ្នកស្នើសុំ:</label>
                                <input type="text" name="requester_name" id="add_requester_name" class="form-control"
                                       value="<?php echo htmlspecialchars($currentUserFullName); ?>" <?php echo !$isAdmin ? 'readonly' : ''; ?> required>
                            </div>
                            <div class="detail-item">
                                <label for="add_department" class="form-label">ផ្នែក:</label>
                                <input type="text" name="department" id="add_department" class="form-control">
                            </div>
                            <div class="detail-item">
                                <label for="add_position" class="form-label">តំណែង:</label>
                                <input type="text" name="position" id="add_position" class="form-control">
                            </div>
                            <div class="detail-item">
                                <label for="add_branch" class="form-label">សាខា:</label>
                                <input type="text" name="branch" id="add_branch" class="form-control">
                            </div>
                            <div class="detail-item">
                                <label for="add_contact_number" class="form-label">លេខទូរស័ព្ទ:</label>
                                <input type="text" name="contact_number" id="add_contact_number" class="form-control">
                            </div>
                        </div>

                        <div class="section-header mt-3"><i class="fas fa-file-alt"></i> ព័ត៌មានសំណើ</div>
                        <div class="detail-row">
                            <div class="detail-item">
                                <label for="add_request_type" class="form-label required-field">ប្រភេទសំណើ:</label>
                                <input type="text" name="request_type" id="add_request_type" class="form-control" required>
                                <small class="form-text text-muted">ឧ. សម្រាកប្រចាំឆ្នាំ, ភ្លេចស្កេនមេដៃ, ល.</small>
                            </div>
                            <div class="detail-item">
                                <label for="add_request_date" class="form-label required-field">កាលបរិច្ឆេទស្នើសុំ/ឈប់:</label>
                                <input type="date" name="request_date" id="add_request_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="detail-item">
                                <label for="add_return_date" class="form-label">ថ្ងៃចូលធ្វើការវិញ/ថ្ងៃសងវិញ:</label>
                                <input type="date" name="return_date" id="add_return_date" class="form-control">
                            </div>
                            <div class="detail-item">
                                <label for="add_number_of_days" class="form-label">ចំនួនថ្ងៃឈប់:</label>
                                <input type="number" step="0.1" name="number_of_days" id="add_number_of_days" class="form-control">
                            </div>
                            <div class="detail-item">
                                <label for="add_remaining_days" class="form-label">ថ្ងៃឈប់នៅសល់:</label>
                                <input type="number" step="0.1" name="remaining_days" id="add_remaining_days" class="form-control">
                            </div>
                            <div class="detail-item" style="flex-basis: 100%;">
                                <label for="add_reason" class="form-label">មូលហេតុ:</label>
                                <textarea name="reason" id="add_reason" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="detail-item">
                                <label for="add_assigned_to" class="form-label">ប្រគល់ការងារឱ្យ:</label>
                                <input type="text" name="assigned_to" id="add_assigned_to" class="form-control">
                            </div>
                            <div class="detail-item">
                                <label for="add_location" class="form-label">ទីតាំងពេលឈប់:</label>
                                <input type="text" name="location" id="add_location" class="form-control">
                            </div>
                        </div>

                        <div class="section-header mt-3"><i class="fas fa-clock"></i> ព័ត៌មានពេលវេលា (បំពេញបើចាំបាច់)</div>
                        <div class="detail-row">
                            <div class="detail-item"><label for="add_time_in" class="form-label">ម៉ោងចូល (ចេញមុន):</label><input type="time" name="time_in" id="add_time_in" class="form-control"></div>
                            <div class="detail-item"><label for="add_time_out" class="form-label">ម៉ោងចេញ (ចេញមុន):</label><input type="time" name="time_out" id="add_time_out" class="form-control"></div>
                            <div class="detail-item"><label for="add_total_hours" class="form-label">ម៉ោងសរុប (ចេញមុន):</label><input type="text" name="total_hours" id="add_total_hours" class="form-control"></div>
                            <div class="detail-item"><label for="add_repay_time_in" class="form-label">ម៉ោងចូលសង:</label><input type="time" name="repay_time_in" id="add_repay_time_in" class="form-control"></div>
                            <div class="detail-item"><label for="add_repay_time_out" class="form-label">ម៉ោងចេញសង:</label><input type="time" name="repay_time_out" id="add_repay_time_out" class="form-control"></div>
                            <div class="detail-item"><label for="add_repay_total_hours" class="form-label">ម៉ោងសងសរុប:</label><input type="text" name="repay_total_hours" id="add_repay_total_hours" class="form-control"></div>
                            <div class="detail-item"><label for="add_late_hours" class="form-label">ម៉ោងមកយឺត:</label><input type="text" name="late_hours" id="add_late_hours" class="form-control"></div>
                            <div class="detail-item"><label for="add_forgot_scan_in" class="form-label">ភ្លេចស្កេនចូល (ម៉ោង):</label><input type="text" name="forgot_scan_in" id="add_forgot_scan_in" class="form-control" placeholder="HH:MM"></div>
                            <div class="detail-item"><label for="add_forgot_scan_out" class="form-label">ភ្លេចស្កេនចេញ (ម៉ោង):</label><input type="text" name="forgot_scan_out" id="add_forgot_scan_out" class="form-control" placeholder="HH:MM"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> បោះបង់</button>
                        <button type="submit" name="submit_add_request" class="btn btn-primary"><i class="fas fa-plus-circle"></i> បន្ថែមសំណើ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Detail/Edit Modal -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailModalLabel"><i class="fas fa-info-circle"></i> ពត៌មានលំអិតនៃសំណើ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="editForm" action="<?php echo BASE_URL; ?>">
                    <div class="modal-body">
                        <input type="hidden" name="edit_id" id="edit_id_field">
                        <input type="hidden" name="user_id" id="edit_user_id_field">
                        <!-- Personal Info Section -->
                        <div class="section-header"><i class="fas fa-user"></i> ព័ត៌មានបុគ្គល</div>
                        <div class="detail-row">
                            <div class="detail-item"><i class="fas fa-id-badge"></i> <strong>ID:</strong> <span class="display-text" data-field="id"></span></div>
                            <?php if ($isAdmin): ?>
                                <div class="detail-item"><i class="fas fa-user"></i> <strong>អ្នកប្រើ ID:</strong> 
                                    <span class="display-text" data-field="user_id"></span>
                                    <input type="text" name="user_id" class="edit-field form-control form-control-sm" data-edit-field="user_id">
                                </div>
                            <?php endif; ?>
                            <div class="detail-item"><i class="fas fa-user"></i> <strong>ឈ្មោះអ្នកស្នើសុំ:</strong> 
                                <span class="display-text" data-field="requester_name"></span>
                                <input type="text" name="requester_name" class="edit-field form-control form-control-sm" data-edit-field="requester_name" <?php echo !$isAdmin ? 'readonly' : ''; ?>>
                            </div>
                            <div class="detail-item"><i class="fas fa-building"></i> <strong>ផ្នែក:</strong> 
                                <span class="display-text" data-field="department"></span>
                                <input type="text" name="department" class="edit-field form-control form-control-sm" data-edit-field="department">
                            </div>
                            <div class="detail-item"><i class="fas fa-briefcase"></i> <strong>តំណែង:</strong> 
                                <span class="display-text" data-field="position"></span>
                                <input type="text" name="position" class="edit-field form-control form-control-sm" data-edit-field="position">
                            </div>
                            <div class="detail-item"><i class="fas fa-map-marker-alt"></i> <strong>សាខា:</strong> 
                                <span class="display-text" data-field="branch"></span>
                                <input type="text" name="branch" class="edit-field form-control form-control-sm" data-edit-field="branch">
                            </div>
                            <div class="detail-item"><i class="fas fa-phone"></i> <strong>លេខទូរស័ព្ទ:</strong> 
                                <span class="display-text" data-field="contact_number"></span>
                                <input type="text" name="contact_number" class="edit-field form-control form-control-sm" data-edit-field="contact_number">
                            </div>
                        </div>
                        <!-- Request Info Section -->
                        <div class="section-header mt-3"><i class="fas fa-file-alt"></i> ព័ត៌មានសំណើ</div>
                        <div class="detail-row">
                            <div class="detail-item"><i class="fas fa-clipboard-list"></i> <strong>ប្រភេទសំណើ:</strong> 
                                <span class="display-text" data-field="request_type"></span>
                                <input type="text" name="request_type" class="edit-field form-control form-control-sm" data-edit-field="request_type">
                            </div>
                            <div class="detail-item"><i class="fas fa-calendar-day"></i> <strong>កាលបរិច្ឆេទស្នើសុំ:</strong> 
                                <span class="display-text" data-field="request_date" data-format="date"></span>
                                <input type="date" name="request_date" class="edit-field form-control form-control-sm" data-edit-field="request_date">
                            </div>
                            <div class="detail-item"><i class="fas fa-calendar-check"></i> <strong>ថ្ងៃចូលធ្វើការវិញ:</strong> 
                                <span class="display-text" data-field="return_date" data-format="date"></span>
                                <input type="date" name="return_date" class="edit-field form-control form-control-sm" data-edit-field="return_date">
                            </div>
                            <div class="detail-item"><i class="fas fa-sort-numeric-down"></i> <strong>ចំនួនថ្ងៃឈប់:</strong> 
                                <span class="display-text" data-field="number_of_days"></span>
                                <input type="number" step="0.1" name="number_of_days" class="edit-field form-control form-control-sm" data-edit-field="number_of_days">
                            </div>
                            <div class="detail-item"><i class="fas fa-hourglass-half"></i> <strong>ថ្ងៃឈប់នៅសល់:</strong> 
                                <span class="display-text" data-field="remaining_days"></span>
                                <input type="number" step="0.1" name="remaining_days" class="edit-field form-control form-control-sm" data-edit-field="remaining_days">
                            </div>
                            <div class="detail-item" style="flex-basis: 100%;"><i class="fas fa-comment"></i> <strong>មូលហេតុ:</strong> 
                                <span class="display-text" data-field="reason" style="white-space: pre-wrap;"></span>
                                <textarea name="reason" class="edit-field form-control form-control-sm" data-edit-field="reason" rows="3"></textarea>
                            </div>
                            <div class="detail-item"><i class="fas fa-user-tie"></i> <strong>ប្រគល់ការងារឱ្យ:</strong> 
                                <span class="display-text" data-field="assigned_to"></span>
                                <input type="text" name="assigned_to" class="edit-field form-control form-control-sm" data-edit-field="assigned_to">
                            </div>
                            <div class="detail-item"><i class="fas fa-map"></i> <strong>ទីតាំងពេលឈប់:</strong> 
                                <span class="display-text" data-field="location"></span>
                                <input type="text" name="location" class="edit-field form-control form-control-sm" data-edit-field="location">
                            </div>
                        </div>
                        <!-- Time Details Section -->
                        <div class="section-header mt-3"><i class="fas fa-clock"></i> ព័ត៌មានពេលវេលា</div>
                        <div class="detail-row">
                            <div class="detail-item"><i class="fas fa-sign-in-alt"></i> <strong>ម៉ោងចូល (ចេញមុន):</strong> 
                                <span class="display-text" data-field="time_in" data-format="time"></span>
                                <input type="time" name="time_in" class="edit-field form-control form-control-sm" data-edit-field="time_in">
                            </div>
                            <div class="detail-item"><i class="fas fa-sign-out-alt"></i> <strong>ម៉ោងចេញ (ចេញមុន):</strong> 
                                <span class="display-text" data-field="time_out" data-format="time"></span>
                                <input type="time" name="time_out" class="edit-field form-control form-control-sm" data-edit-field="time_out">
                            </div>
                            <div class="detail-item"><i class="fas fa-hourglass"></i> <strong>ម៉ោងសរុប (ចេញមុន):</strong> 
                                <span class="display-text" data-field="total_hours"></span>
                                <input type="text" name="total_hours" class="edit-field form-control form-control-sm" data-edit-field="total_hours">
                            </div>
                            <div class="detail-item"><i class="fas fa-sign-in-alt"></i> <strong>ម៉ោងចូលសង:</strong> 
                                <span class="display-text" data-field="repay_time_in" data-format="time"></span>
                                <input type="time" name="repay_time_in" class="edit-field form-control form-control-sm" data-edit-field="repay_time_in">
                            </div>
                            <div class="detail-item"><i class="fas fa-sign-out-alt"></i> <strong>ម៉ោងចេញសង:</strong> 
                                <span class="display-text" data-field="repay_time_out" data-format="time"></span>
                                <input type="time" name="repay_time_out" class="edit-field form-control form-control-sm" data-edit-field="repay_time_out">
                            </div>
                            <div class="detail-item"><i class="fas fa-hourglass-end"></i> <strong>ម៉ោងសងសរុប:</strong> 
                                <span class="display-text" data-field="repay_total_hours"></span>
                                <input type="text" name="repay_total_hours" class="edit-field form-control form-control-sm" data-edit-field="repay_total_hours">
                            </div>
                            <div class="detail-item"><i class="fas fa-exclamation-triangle"></i> <strong>ម៉ោងមកយឺត:</strong> 
                                <span class="display-text" data-field="late_hours"></span>
                                <input type="text" name="late_hours" class="edit-field form-control form-control-sm" data-edit-field="late_hours">
                            </div>
                            <div class="detail-item"><i class="fas fa-fingerprint"></i> <strong>ភ្លេចស្កេនចូល:</strong> 
                                <span class="display-text" data-field="forgot_scan_in"></span>
                                <input type="text" name="forgot_scan_in" class="edit-field form-control form-control-sm" data-edit-field="forgot_scan_in">
                            </div>
                            <div class="detail-item"><i class="fas fa-fingerprint"></i> <strong>ភ្លេចស្កេនចេញ:</strong> 
                                <span class="display-text" data-field="forgot_scan_out"></span>
                                <input type="text" name="forgot_scan_out" class="edit-field form-control form-control-sm" data-edit-field="forgot_scan_out">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="detail_close_button"><i class="fas fa-times"></i> បិទ</button>
                        <button type="button" class="btn btn-warning" id="detail_edit_button" style="display:none;"><i class="fas fa-edit"></i> កែសម្រួល</button>
                        <button type="submit" class="btn btn-primary" id="detail_save_button" style="display: none;"><i class="fas fa-save"></i> រក្សាទុក</button>
                        <button type="button" class="btn btn-info" id="detail_print_button"><i class="fas fa-print"></i> បោះពុម្ព</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <?php if ($isAdmin): ?>
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle"></i> បញ្ជាក់ការលុប</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>តើអ្នកពិតជាចង់លុបសំណើ "<span id="deleteRequestNameDisplay"></span>" មែនទេ?</p>
                    <p class="text-danger">សកម្មភាពនេះមិនអាចមិនធ្វើវិញបានទេ។</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="<?php echo BASE_URL; ?>" style="display: inline;">
                        <input type="hidden" name="delete_id" id="deleteConfirmIdInput">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-ban"></i> បោះបង់</button>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> លុប</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Printable Form Container (hidden) -->
    <div class="print-request-form" id="printableForm" style="display: none;">
        <div class="header">
            <img src="https://i.ibb.co/x86F4TfC/Logo-Van-Van-2.png" alt="VanVan Cambodia Logo" class="print-logo">
        </div>
        <span class="span">សំណើសុំច្បាប់ឈប់សម្រាក់ ប្ដូរដេអូស ចេញមុនម៉ោង មកយឺត និងភ្លេចស្កេនមេដៃវត្តមាន</span>
        <div class="container" id="printContainer">
            <!-- Dynamically populated content will go here -->
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Pass PHP variables to JavaScript
    const isAdminJS = <?php echo json_encode($isAdmin); ?>;
    const currentUserIdJS = <?php echo json_encode($currentUserId); ?>;
    const currentUserFullNameJS = <?php echo json_encode($currentUserFullName); ?>;

    document.addEventListener('DOMContentLoaded', function () {
        const detailModalEl = document.getElementById('detailModal');
        const detailModalInstance = new bootstrap.Modal(detailModalEl);
        let currentRequestForDetailModal;

        function formatDate(dateString) {
            if (!dateString || dateString === '0000-00-00' || dateString === 'N/A') return 'N/A';
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return dateString;
                const day = String(date.getDate()).padStart(2, '0');
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const year = date.getFullYear();
                return `${day}-${month}-${year}`;
            } catch (e) { return dateString; }
        }
        
        function formatTime(timeString) {
            if (!timeString || timeString === '00:00:00' || timeString === 'N/A') return 'N/A';
            return timeString.substring(0, 5);
        }

        function populateDetailModal(requestData, canEditThisRequest) {
    currentRequestForDetailModal = requestData;

    document.getElementById('edit_id_field').value = requestData.id || '';
    document.getElementById('edit_user_id_field').value = requestData.user_id || '';

    detailModalEl.querySelectorAll('.display-text').forEach(span => {
        const fieldName = span.dataset.field;
        let value = requestData[fieldName] || 'N/A';
        if (span.dataset.format === 'date') value = formatDate(value);
        if (span.dataset.format === 'time') value = formatTime(value);
        if (fieldName === 'signature' && value !== 'N/A' && value.startsWith('data:image/')) {
            const img = span.querySelector('#signature-img');
            if (img) {
                img.src = value;
                img.style.display = 'inline';
                span.textContent = '';
            }
        } else if (fieldName === 'signature') {
            const img = span.querySelector('#signature-img');
            if (img) img.style.display = 'none';
            span.textContent = value;
        } else {
            span.textContent = value;
        }
    });

    detailModalEl.querySelectorAll('.edit-field').forEach(input => {
        const fieldName = input.dataset.editField;
        let value = requestData[fieldName] || '';
        if (input.type === 'date' && value) {
            try {
                const d = new Date(value);
                if (!isNaN(d.getTime())) {
                    value = d.toISOString().split('T')[0];
                } else { value = ''; }
            } catch (e) { value = ''; }
        }
        input.value = value;
        if (fieldName === 'requester_name' || fieldName === 'user_id') {
            input.readOnly = !isAdminJS;
        } else {
            input.readOnly = false;
        }
    });

    toggleDetailModalEditMode(false, canEditThisRequest);
}

        function toggleDetailModalEditMode(isEditing, canEditThisRequest) {
            const editButton = document.getElementById('detail_edit_button');
            const saveButton = document.getElementById('detail_save_button');
            const closeButton = document.getElementById('detail_close_button');
            const printButton = document.getElementById('detail_print_button');

            detailModalEl.querySelectorAll('.detail-item').forEach(item => {
                const displaySpan = item.querySelector('.display-text');
                const editInput = item.querySelector('.edit-field');
                if (displaySpan && editInput) {
                    const fieldName = editInput.dataset.editField;
                    if (!isAdminJS && (fieldName === 'user_id' || fieldName === 'requester_name')) {
                        // Non-admins cannot edit user_id or requester_name
                        editInput.style.display = 'none';
                        displaySpan.style.display = 'inline';
                    } else {
                        if (isEditing) {
                            displaySpan.style.display = 'none';
                            editInput.style.display = 'block';
                        } else {
                            displaySpan.style.display = 'inline';
                            editInput.style.display = 'none';
                        }
                    }
                }
            });

            if (editButton) editButton.style.display = (isEditing || !canEditThisRequest) ? 'none' : 'inline-block';
            if (saveButton) saveButton.style.display = isEditing && canEditThisRequest ? 'inline-block' : 'none';
            if (printButton) printButton.style.display = isEditing ? 'none' : 'inline-block';
            if (closeButton) closeButton.innerHTML = isEditing ? '<i class="fas fa-times"></i> បោះបង់' : '<i class="fas fa-times"></i> បិទ';
        }

        detailModalEl.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            try {
                const requestData = JSON.parse(button.getAttribute('data-request'));
                const canEditThisRequest = button.getAttribute('data-can-edit') === 'true';
                populateDetailModal(requestData, canEditThisRequest);
            } catch (e) {
                console.error('Error populating detail/edit modal:', e);
            }
        });

        const detailEditButton = document.getElementById('detail_edit_button');
        if (detailEditButton) {
            detailEditButton.addEventListener('click', function() {
                toggleDetailModalEditMode(true, true);
            });
        }

        const detailCloseButton = document.getElementById('detail_close_button');
        if (detailCloseButton) {
            detailCloseButton.addEventListener('click', function(e) {
                const saveButton = document.getElementById('detail_save_button');
                if (saveButton && saveButton.style.display !== 'none') {
                    e.preventDefault();
                    const canEdit = document.getElementById('detail_edit_button').style.display === 'none';
                    toggleDetailModalEditMode(false, canEdit);
                }
            });
        }

        <?php if ($isAdmin): ?>
        const deleteConfirmModalEl = document.getElementById('deleteConfirmModal');
        if (deleteConfirmModalEl) {
            deleteConfirmModalEl.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                document.getElementById('deleteConfirmIdInput').value = button.getAttribute('data-id');
                document.getElementById('deleteRequestNameDisplay').textContent = button.getAttribute('data-name');
            });
        }
        <?php endif; ?>
       // --- Print Functionality (FIXED AND IMPROVED FOR SIGNATURES) ---
        function populatePrintForm(requestsToPrint) {
            const container = document.getElementById('printContainer');
            container.innerHTML = ''; // Clear previous print content

            requestsToPrint.forEach(request => {
                // Validate essential data. Skip this request if ID is missing.
                if (!request || !request.id) {
                    console.error("Skipping a request because it has no ID.", request);
                    return; // 'continue' to the next item in forEach
                }
                const reqId = request.id;

                // Gracefully handle null or undefined request types.
                const requestType = request.request_type || ''; // Ensure requestType is at least an empty string

                // Helper function to safely get values, defaulting to a non-breaking space for layout
                const reqSafe = (key, def = '&nbsp;') => (request[key] !== null && request[key] !== undefined && request[key] !== '') ? request[key] : def;

                // --- FIX FOR SIGNATURE: Check for multiple signature formats ---
                const signatureValue = reqSafe('signature', 'N/A');
                let signatureHtml = '_________________________'; // Default to a line

                if (signatureValue && signatureValue !== 'N/A' && signatureValue !== '&nbsp;') {
                    // 1. Check if it's a full Base64 Data URL
                    if (signatureValue.startsWith('data:image/')) {
                        signatureHtml = `<img src="${signatureValue}" alt="Signature" class="signature-img">`;
                    
                    // 2. Check if it's a full web URL
                    } else if (signatureValue.startsWith('http')) {
                        signatureHtml = `<img src="${signatureValue}" alt="Signature" class="signature-img">`;

                    // 3. Check if it looks like a path to an image file (contains extension)
                    } else if (/\.(png|jpg|jpeg|gif|svg)$/i.test(signatureValue)) {
                        signatureHtml = `<img src="${signatureValue}" alt="Signature" class="signature-img">`;

                    // 4. Fallback check: If it's a very long string, assume it's raw Base64 and add the prefix
                    } else if (signatureValue.length > 100 && /^[A-Za-z0-9+/=]+$/.test(signatureValue)) {
                        signatureHtml = `<img src="data:image/png;base64,${signatureValue}" alt="Signature" class="signature-img">`;
                    }
                }
                // If none of the above, it remains a line '_________________________'

                // Now, build the HTML content for the current request
                let formContent = `
                    <table class="form-table">
                        <tr>
                            <td colspan="5" class="value">
                                <div class="icon-group">
                                    <div class="request-icon-print" id="print-annual-${reqId}">សម្រាកប្រចាំឆ្នាំ (Annual Leave)</div>
                                    <div class="request-icon-print" id="print-sick-${reqId}">សម្រាកដោយជំងឺ (Sick Leave)</div>
                                    <div class="request-icon-print" id="print-forgot-fp-${reqId}">ភ្លេចស្កេនមេដៃ (Forgot FP)</div>
                                    <div class="request-icon-print" id="print-maternity-${reqId}">សម្រាកលំហែមាតុភាព (Maternity Leave)</div>
                                    <div class="request-icon-print" id="print-ot-${reqId}">ថែមម៉ោង (OT)</div>
                                    <div class="request-icon-print" id="print-early-${reqId}">ចេញមុនម៉ោង (Early)</div>
                                    <div class="request-icon-print" id="print-changing-off-${reqId}">ប្តូរថ្ងៃសម្រាក (Changing day off)</div>
                                    <div class="request-icon-print" id="print-special-${reqId}">សម្រាកពិសេស (Special Leave)</div>
                                    <div class="request-icon-print" id="print-late-${reqId}">មកយឺត (Late)</div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td style="text-align: left; width:8rem;">ឈ្មោះអ្នកស្នើរសុំ៖</td><td>${reqSafe('requester_name')}</td>
                            <td>ចំនួនថ្ងៃ/ច្បាប់នៅសល់៖</td><td>${reqSafe('number_of_days')} ថ្ងៃ</td><td>${reqSafe('remaining_days')} ថ្ងៃ</td>
                        </tr>
                        <tr>
                            <td style="text-align: left; width:8rem;">ផ្នែក/មុខតំណែង/សាខា៖</td><td>${reqSafe('department')}</td>
                            <td>${reqSafe('position')}</td><td colspan="2">${reqSafe('branch')}</td>
                        </tr>
                        <tr>
                            <td style="text-align: left;">ថ្ងៃខែឆ្នាំសុំឈប់៖</td><td>${formatDate(reqSafe('request_date'))}</td>
                            <td>ចំនួនម៉ោងយឺត/ចេញមុន៖</td><td colspan="2">${reqSafe('late_hours')}</td>
                        </tr>
                        <tr>
                            <td style="text-align: left;">ថ្ងៃចូលធ្វើការវិញ/ថ្ងៃសងវិញ៖</td><td>${formatDate(reqSafe('return_date'))}</td>
                            <td>ភ្លេចស្កេនមេដៃ៖</td><td>${reqSafe('forgot_scan_in')}</td><td>${reqSafe('forgot_scan_out')}</td>
                        </tr>
                        <tr>
                            <td style="text-align: left;">ម៉ោងចេញចូល(ការងារ)៖</td>
                            <td style="text-align: left;"><p style="display: inline-flex;">ម៉ោងចូល៖</p><p style="padding-left: 1rem; display: inline-flex;">${formatTime(reqSafe('time_in'))}</p></td>
                            <td style="text-align: left;"><p style="display: inline-flex;">ម៉ោងចេញ៖</p><p style="padding-left: 1rem; display: inline-flex;">${formatTime(reqSafe('time_out'))}</p></td>
                            <td colspan="2" style="text-align: left;"><p style="display: inline-flex;">ម៉ោងសរុប៖</p><p style="padding-left: 1rem; display: inline-flex;">${reqSafe('total_hours')}</p></td>
                        </tr>
                        <tr>
                            <td style="text-align: left;">ម៉ោងធ្វើការសងវិញ៖</td>
                            <td style="text-align: left;"><p style="display: inline-flex;">ម៉ោងចូលសង៖</p><p style="padding-left: 0.2rem; display: inline-flex;">${formatTime(reqSafe('repay_time_in'))}</p></td>
                            <td style="text-align: left;"><p style="display: inline-flex;">ម៉ោងចេញសង៖</p><p style="padding-left: 0.2rem; display: inline-flex;">${formatTime(reqSafe('repay_time_out'))}</p></td>
                            <td colspan="2" style="text-align: left;"><p style="display: inline-flex;">ម៉ោងសងសរុប៖</p><p style="padding-left: 0.2rem; display: inline-flex;">${reqSafe('repay_total_hours')}</p></td>
                        </tr>
                        <tr><td style="text-align: left;">មូលហេតុ៖</td><td colspan="4" style="text-align: left; white-space: pre-wrap;">${reqSafe('reason')}</td></tr>
                        <tr><td style="text-align: left;">ទីកន្លែងអំឡុងពេលឈប់៖</td><td colspan="4" style="text-align: left;">${reqSafe('location')}</td></tr>
                        <tr><td style="text-align: left;">លេខទំនាក់ទំនងបន្ទាន់៖</td><td style="text-align: left;">${reqSafe('contact_number')}</td>
                            <td>ប្រគល់ការងារឱ្យ៖</td><td colspan="2" style="text-align: left;">${reqSafe('assigned_to')}</td>
                        </tr>
                    </table>
                    <table class="main-footer">
                        <tr><th style="text-align: left;"><p>បញ្ជាក់/អនុម័តដោយ</p></th><th><p>ឈ្មោះ (Name)</p></th><th><p>ហត្ថលេខា (Signature)</p></th><th colspan="2"><p>ថ្ងៃខែឆ្នាំ (Date)</p></th></tr>
                        <tr><th style="text-align: left;"><p>អ្នកស្នើរសុំ</p></th><th>${reqSafe('requester_name')}</th><th>${signatureHtml}</th><th colspan="2">${formatDate(reqSafe('request_date'))}</th></tr>
                        <tr><th style="text-align: left;"><p>ប្រធានផ្នែក</p></th><th>_________________________</th><th>_________________________</th><th colspan="2">_________________________</th></tr>
                        <tr><th style="text-align: left;"><p>ប្រធានធនធានមនុស្ស</p></th><th>_________________________</th><th>_________________________</th><th colspan="2">_________________________</th></tr>
                        <tr><th style="text-align: left;"><p>ប្រធានគ្រប់គ្រងទូទៅ</p></th><th>_________________________</th><th>_________________________</th><th colspan="2">_________________________</th></tr>
                        <tr><th style="text-align: left;"><p>អគ្គនាយិកា</p></th><th>_________________________</th><th>_________________________</th><th colspan="2">_________________________</th></tr>
                    </table>
                    <div style="page-break-after: always;"></div>`;
                
                container.insertAdjacentHTML('beforeend', formContent);

                // This part highlights the correct icon. It's now safer.
                const requestTypesMap = { 'សម្រាកប្រចាំឆ្នាំ (Annual Leave)': `print-annual-${reqId}`, 'សម្រាកដោយជំងឺ (Sick Leave)': `print-sick-${reqId}`, 'ភ្លេចស្កេនមេដៃ (Forgot FP)': `print-forgot-fp-${reqId}`, 'សម្រាកលំហែមាតុភាព (Maternity Leave)': `print-maternity-${reqId}`, 'ថែមម៉ោង (OT)': `print-ot-${reqId}`, 'ចេញមុនម៉ោង (Early)': `print-early-${reqId}`, 'ប្តូរថ្ងៃសម្រាក (Changing day off)': `print-changing-off-${reqId}`, 'សម្រាកពិសេស (Special Leave)': `print-special-${reqId}`, 'មកយឺត (Late)': `print-late-${reqId}` };
                
                setTimeout(() => {
                    // Split is now safe because requestType is guaranteed to be a string.
                    const requestTypeArray = requestType.split(',').map(type => type.trim());
                    requestTypeArray.forEach(type => {
                        let iconIdToSelect;
                        for (const key in requestTypesMap) {
                            if (type === key || key.includes(`(${type})`)) {
                                iconIdToSelect = requestTypesMap[key];
                                break;
                            }
                        }
                        if (iconIdToSelect) {
                            const iconElement = document.getElementById(iconIdToSelect);
                            if (iconElement) iconElement.classList.add('selected');
                        }
                    });
                }, 50);
            });
        }
        const printMainButton = document.getElementById('printRequestFormButton');
        if(printMainButton){
            printMainButton.addEventListener('click', function() {
                const allVisibleRequests = [];
                document.querySelectorAll('table tbody tr:not([style*="display: none"]) .btn-detail').forEach(button => {
                     try { allVisibleRequests.push(JSON.parse(button.getAttribute('data-request'))); } catch (e) { console.error("Error parsing for main print:", e); }
                });
                if (allVisibleRequests.length === 0) { alert("មិនមានសំណើដើម្បីបោះពុម្ពទេ។"); return; }
                const printContentEl = document.getElementById('printableForm');
                printContentEl.style.display = 'block';
                populatePrintForm(allVisibleRequests);
                setTimeout(() => { window.print(); printContentEl.style.display = 'none'; }, 250);
            });
        }
        
        const detailPrintButton = document.getElementById('detail_print_button');
        if(detailPrintButton){
            detailPrintButton.addEventListener('click', function() {
                if (!currentRequestForDetailModal) { alert("មិនមានទិន្នន័យសំណើដើម្បីបោះពុម្ពពី Modal ទេ។"); return; }
                const printContentEl = document.getElementById('printableForm');
                printContentEl.style.display = 'block';
                populatePrintForm([currentRequestForDetailModal]);
                setTimeout(() => { window.print(); printContentEl.style.display = 'none'; }, 250);
            });
        }

        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                document.querySelectorAll('table tbody tr').forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(searchTerm) ? '' : 'none';
                });
            });
        }
    });
    </script>
</body>
</html>