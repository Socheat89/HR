<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Database connection details
$db_host = 'localhost';
$db_name = 'samann1_scan_logs_worker_db';
$db_user = 'samann1_scan_logs_worker_db';
$db_pass = 'scan_logs_worker_db@2025';

$message = '';
$message_type = '';

function displayValue($data): string {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // ====================================================================
    // NEW: Handle All Actions (Single, Selected, All)
    // ====================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $log_ids = $_POST['log_ids'] ?? [];

        // Ensure log_ids is an array for selected actions
        if (($action === 'restore_selected' || $action === 'delete_selected_perm') && !is_array($log_ids)) {
            $log_ids = [];
        }

        // Get all columns from scan_logs to avoid column mismatch
        $cols_res = $pdo->query("SHOW COLUMNS FROM scan_logs");
        $table_columns = $cols_res->fetchAll(PDO::FETCH_COLUMN);
        $columns_string = '`' . implode('`, `', $table_columns) . '`';

        $pdo->beginTransaction();
        try {
            switch ($action) {
                // --- ACTION: RESTORE SELECTED ---
                case 'restore_selected':
                    if (empty($log_ids)) {
                        $message = "សូមជ្រើសរើសកំណត់ត្រាយ៉ាងហោចណាស់មួយដើម្បីស្តារឡើងវិញ។";
                        $message_type = 'warning';
                    } else {
                        $placeholders = implode(',', array_fill(0, count($log_ids), '?'));
                        $stmt_copy = $pdo->prepare("INSERT INTO scan_logs ($columns_string) SELECT $columns_string FROM deleted_logs WHERE id IN ($placeholders)");
                        $stmt_copy->execute($log_ids);
                        $stmt_delete = $pdo->prepare("DELETE FROM deleted_logs WHERE id IN ($placeholders)");
                        $stmt_delete->execute($log_ids);
                        $message = "បានស្តារកំណត់ត្រាចំនួន " . count($log_ids) . " ឡើងវិញដោយជោគជ័យ។";
                        $message_type = 'success';
                    }
                    break;

                // --- ACTION: DELETE SELECTED PERMANENTLY ---
                case 'delete_selected_perm':
                     if (empty($log_ids)) {
                        $message = "សូមជ្រើសរើសកំណត់ត្រាយ៉ាងហោចណាស់មួយដើម្បីលុប។";
                        $message_type = 'warning';
                    } else {
                        $placeholders = implode(',', array_fill(0, count($log_ids), '?'));
                        $stmt_delete = $pdo->prepare("DELETE FROM deleted_logs WHERE id IN ($placeholders)");
                        $stmt_delete->execute($log_ids);
                        $message = "បានលុបកំណត់ត្រាចំនួន " . count($log_ids) . " ជាអចិន្ត្រៃយ៍។";
                        $message_type = 'success';
                    }
                    break;

                // --- ACTION: RESTORE ALL ---
                case 'restore_all':
                    $stmt_copy = $pdo->query("INSERT INTO scan_logs ($columns_string) SELECT $columns_string FROM deleted_logs");
                    $count = $stmt_copy->rowCount();
                    if ($count > 0) {
                        $pdo->query("DELETE FROM deleted_logs");
                        $message = "បានស្តារកំណត់ត្រាទាំងអស់ ($count) ឡើងវិញដោយជោគជ័យ។";
                    } else {
                        $message = "មិនមានកំណត់ត្រាដើម្បីស្តារឡើងវិញទេ។";
                    }
                    $message_type = 'success';
                    break;

                // --- ACTION: DELETE ALL PERMANENTLY ---
                case 'delete_all_perm':
                    $stmt = $pdo->query("SELECT COUNT(*) FROM deleted_logs");
                    $count = $stmt->fetchColumn();
                    if ($count > 0) {
                        $pdo->query("TRUNCATE TABLE deleted_logs");
                        $message = "បានលុបកំណត់ត្រាទាំងអស់ ($count) ជាអចិន្ត្រៃយ៍។";
                    } else {
                        $message = "មិនមានកំណត់ត្រាដើម្បីលុបទេ។";
                    }
                    $message_type = 'success';
                    break;
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "មានបញ្ហាក្នុងការដំណើរការ: " . $e->getMessage();
            $message_type = 'danger';
        }
    }


    // ====================================================================
    // NEW: Fetch data with filtering
    // ====================================================================
    $selected_staff_type = $_GET['staff_type'] ?? 'all';
    
    $whereClauses = [];
    $params = [];

    if ($selected_staff_type === 'professional') {
        $professional_folders = ['ជំនាញ', 'ហាងទំនិញ៣១៨', 'SK Chuk Meas', 'ឃ្លាំង', 'SK Cosmetic'];
        $placeholders = implode(',', array_fill(0, count($professional_folders), '?'));
        $whereClauses[] = "folder IN ($placeholders)";
        $params = array_merge($params, $professional_folders);
    } elseif ($selected_staff_type === 'worker') {
        $whereClauses[] = "folder = ?";
        $params[] = 'កម្មករ';
    }

    $whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
    
    $stmt = $pdo->prepare("SELECT * FROM deleted_logs $whereSql ORDER BY deleted_at DESC");
    $stmt->execute($params);
    $deleted_logs = $stmt->fetchAll();

} catch (PDOException $e) {
    $error_message = "មានបញ្ហាក្នុងការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ទិន្នន័យបានលុប (Drafts)</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #05165e; --primary-color-dark: #031044; --secondary-color: #6c757d;
            --success-color: #198754; --danger-color: #dc3545; --warning-color: #ffc107;
            --light-bg: #f8f9fa; --border-color: #dee2e6; --text-color: #212529; --text-muted: #6c757d;
            --border-radius: 0.5rem; --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        body { font-family: 'Kantumruy Pro', sans-serif; background-color: var(--light-bg); margin: 0; color: var(--text-color); }
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 1.5rem; }
        .main-header { padding: 1.5rem 2rem; margin-bottom: 2rem; background: linear-gradient(135deg, var(--primary-color-dark), var(--primary-color)); color: white; border-radius: var(--border-radius); box-shadow: var(--box-shadow); display: flex; justify-content: space-between; align-items: center; }
        .btn { display: inline-block; padding: 0.6rem 1.2rem; font-size: 1rem; font-weight: 500; text-align: center; text-decoration: none; cursor: pointer; border: 1px solid transparent; border-radius: var(--border-radius); transition: all 0.2s ease-in-out; }
        .btn i { margin-right: 0.5rem; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: .875rem; }
        .btn-success { color: #fff; background-color: var(--success-color); border-color: var(--success-color); }
        .btn-success:hover { background-color: #157347; border-color: #146c43; }
        .btn-danger { color: #fff; background-color: var(--danger-color); border-color: var(--danger-color); }
        .btn-danger:hover { background-color: #bb2d3b; border-color: #b02a37; }
        .btn-outline-light { color: #f8f9fa; border-color: rgba(255, 255, 255, 0.5); }
        .btn-outline-light:hover { color: var(--primary-color); background-color: #f8f9fa; }
        .btn-outline-secondary { color: var(--secondary-color); border-color: var(--border-color); background-color: #fff; }
        .btn-outline-secondary:hover { color: #fff; background-color: var(--secondary-color); }
        .card { border: none; box-shadow: var(--box-shadow); border-radius: var(--border-radius); margin-bottom: 2rem; background-color: #fff; }
        .card-header { padding: 1.25rem 1.5rem; background-color: #fff; border-bottom: 1px solid var(--border-color); }
        .card-header h5 { margin: 0; font-weight: 600; font-size: 1.2rem; }
        .card-body { padding: 1.5rem; }
        .card-body.p-0 { padding: 0; }
        .table-container { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 1rem; vertical-align: middle; border-top: 1px solid var(--border-color); white-space: nowrap; }
        .table thead.table-header-custom th { background-color: var(--primary-color); color: white; text-align: left; }
        .alert { padding: 1rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: var(--border-radius); }
        .alert-success { color: #0f5132; background-color: #d1e7dd; border-color: #badbcc; }
        .alert-danger { color: #842029; background-color: #f8d7da; border-color: #f5c2c7; }
        .alert-warning { color: #664d03; background-color: #fff3cd; border-color: #ffecb5; }
        .action-buttons form { display: inline-block; margin: 0 2px;}

        /* NEW Styles */
        .page-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background-color: #fff;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow-sm);
        }
        .action-group {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        .btn-group { display: flex; border-radius: var(--border-radius); overflow: hidden; }
        .btn-group .btn { border-radius: 0; background-color: #fff; border: 1px solid var(--border-color); color: var(--text-muted); padding: 0.5rem 1rem; }
        .btn-group .btn:not(:last-child) { border-right: none; }
        .btn-group .btn:hover { background-color: #e9ecef; }
        .btn-group .btn.active { background-color: var(--primary-color); border-color: var(--primary-color); color: #fff; }
        .table th:first-child, .table td:first-child {
            width: 50px; text-align: center;
        }
    </style>
</head>
<body>
<div class="container">
    <header class="main-header">
        <h1 style="font-size: 1.5rem;">
            <i class="fas fa-trash-restore"></i> ទិន្នន័យបានលុប (Drafts)
        </h1>
        <a href="Panel.php" class="btn btn-outline-light">
            <i class="fas fa-arrow-left"></i> ត្រឡប់ទៅផ្ទាំងគ្រប់គ្រង
        </a>
    </header>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?= displayValue($error_message) ?></div>
    <?php else: ?>
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $message_type ?>"><?= $message ?></div>
        <?php endif; ?>

        <!-- NEW: Action Bar -->
        <div class="page-actions">
            <div class="action-group">
                <div class="btn-group" id="staff-type-group">
                    <a href="?staff_type=all" class="btn <?= ($selected_staff_type === 'all') ? 'active' : '' ?>">ទាំងអស់</a>
                    <a href="?staff_type=professional" class="btn <?= ($selected_staff_type === 'professional') ? 'active' : '' ?>">បុគ្គលិកជំនាញ</a>
                    <a href="?staff_type=worker" class="btn <?= ($selected_staff_type === 'worker') ? 'active' : '' ?>">បុគ្គលិកកម្មករ</a>
                </div>
            </div>
            <div class="action-group">
                <button type="submit" form="draft-form" name="action" value="restore_all" class="btn btn-sm btn-success" id="restore-all-btn">
                    <i class="fas fa-undo-alt"></i> Restore All
                </button>
                <button type="submit" form="draft-form" name="action" value="delete_all_perm" class="btn btn-sm btn-danger" id="delete-all-btn">
                    <i class="fas fa-times-circle"></i> Delete All
                </button>
            </div>
        </div>


        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-history"></i> បញ្ជីកំណត់ត្រាដែលបានរក្សាទុក (<?= count($deleted_logs) ?>)</h5>
            </div>
            <div class="card-body p-0">
                <form method="POST" id="draft-form">
                    <div class="table-container">
                        <table class="table">
                            <thead class="table-header-custom">
                                <tr>
                                    <th><input type="checkbox" id="select-all-checkbox"></th>
                                    <th>ឈ្មោះ</th>
                                    <th>ប្រភេទ</th>
                                    <th>សកម្មភាព</th>
                                    <th>ពេលវេលាស្កេន</th>
                                    <th>ពេលវេលាលុប</th>
                                    <th>ចំណាំ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($deleted_logs)): ?>
                                    <tr><td colspan="7" style="text-align: center; padding: 3rem;">មិនមានទិន្នន័យក្នុង Drafts ទេ។</td></tr>
                                <?php else: ?>
                                    <?php foreach ($deleted_logs as $log): ?>
                                    <tr>
                                        <td><input type="checkbox" name="log_ids[]" value="<?= $log['id'] ?>" class="log-checkbox"></td>
                                        <td><?= displayValue($log['username']) ?></td>
                                        <td><?= displayValue($log['folder']) ?></td>
                                        <td><?= displayValue($log['action']) ?></td>
                                        <td><?= date('d/m/Y h:i:s A', strtotime($log['timestamp'])) ?></td>
                                        <td><?= date('d/m/Y h:i:s A', strtotime($log['deleted_at'])) ?></td>
                                        <td style="white-space: normal;"><?= displayValue($log['noted']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- NEW: Action buttons for selected items -->
                    <?php if (!empty($deleted_logs)): ?>
                    <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                        <button type="submit" name="action" value="restore_selected" class="btn btn-success" id="restore-selected-btn">
                            <i class="fas fa-undo"></i> Restore Selected
                        </button>
                        <button type="submit" name="action" value="delete_selected_perm" class="btn btn-danger" id="delete-selected-btn">
                            <i class="fas fa-trash-alt"></i> Delete Selected
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function() {
    const $selectAllCheckbox = $('#select-all-checkbox');
    const $logCheckboxes = $('.log-checkbox');

    // Select/Deselect all checkboxes
    $selectAllCheckbox.on('change', function() {
        $logCheckboxes.prop('checked', $(this).prop('checked'));
    });

    // If any individual checkbox is changed, update the "select all" checkbox
    $logCheckboxes.on('change', function() {
        if ($logCheckboxes.length === $('.log-checkbox:checked').length) {
            $selectAllCheckbox.prop('checked', true);
        } else {
            $selectAllCheckbox.prop('checked', false);
        }
    });

    // --- Confirmation Dialogs ---

    // For "Selected" actions, check if at least one is selected
    $('#restore-selected-btn, #delete-selected-btn').on('click', function(e) {
        if ($('.log-checkbox:checked').length === 0) {
            e.preventDefault();
            alert('សូមជ្រើសរើសកំណត់ត្រាយ៉ាងហោចណាស់មួយ!');
            return;
        }
        
        const actionText = $(this).text().trim();
        if (!confirm(`តើអ្នកពិតជាចង់ ${actionText} កំណត់ត្រាដែលបានជ្រើសរើសមែនទេ?`)) {
            e.preventDefault();
        }
    });

    // For "All" actions
    $('#restore-all-btn').on('click', function(e) {
        if (!confirm('តើអ្នកពិតជាចង់ Restore កំណត់ត្រា "ទាំងអស់" មែនទេ?')) {
            e.preventDefault();
        }
    });
    
    $('#delete-all-btn').on('click', function(e) {
        if (!confirm('ព្រមាន! តើអ្នកពិតជាចង់លុបកំណត់ត្រា "ទាំងអស់" ជាអចិន្ត្រៃយ៍មែនទេ?\nសកម្មភាពនេះមិនអាចមិនធ្វើវិញបានទេ។')) {
            e.preventDefault();
        }
    });
});
</script>

</body>
</html>