<?php
// manage_sub_users.php (FINAL, REDESIGNED & SECURE)

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// បង្កើត CSRF token បើមិនទាន់មាន
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- ការកំណត់មូលដ្ឋានទិន្នន័យ ---
$db_host = 'localhost';
$db_user = 'samann1_scan_logs_worker_db';
$db_pass = 'scan_logs_worker_db@2025';
$db_name = 'samann1_scan_logs_worker_db';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'");
} catch (PDOException $e) {
    // ក្នុង Production, គួរតែ Log កំហុសជំនួសឱ្យការបង្ហាញវា
    die("ការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យបរាជ័យ: " . $e->getMessage());
}

$success_message = '';
$error_message = '';

// --- ឡូហ្សិកសម្រាប់គ្រប់គ្រងទិន្នន័យ (POST requests) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ត្រួតពិនិត្យ CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_message = 'CSRF token មិនត្រឹមត្រូវ! សូមព្យាយាមម្តងទៀត។';
    } else {
        // --- បង្កើតអ្នកប្រើប្រាស់ថ្មី ---
        if (isset($_POST['create_user'])) {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $allowed_usernames = $_POST['allowed_usernames'] ?? [];
            if (empty($username) || empty($password) || empty($allowed_usernames)) {
                $error_message = 'សូមបំពេញគ្រប់ប្រអប់ (ឈ្មោះ, ពាក្យសម្ងាត់, និងជ្រើសរើសសិទ្ធិយ៉ាងតិចមួយ)។';
            } else {
                // ពិនិត្យមើលថាតើឈ្មោះអ្នកប្រើប្រាស់មានរួចហើយឬនៅ
                $stmt = $pdo->prepare("SELECT id FROM sub_users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error_message = "ឈ្មោះអ្នកប្រើប្រាស់រង '" . htmlspecialchars($username) . "' មានរួចហើយ។ សូមជ្រើសរើសឈ្មោះផ្សេង។";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $usernames_json = json_encode($allowed_usernames, JSON_UNESCAPED_UNICODE);
                    $stmt = $pdo->prepare("INSERT INTO sub_users (username, password, allowed_usernames, profile_pic) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $usernames_json, 'default_profile.png']);
                    $success_message = "អ្នកប្រើប្រាស់រង '" . htmlspecialchars($username) . "' ត្រូវបានបង្កើតដោយជោគជ័យ!";
                }
            }
        }
        // --- កែសម្រួលអ្នកប្រើប្រាស់ ---
        if (isset($_POST['update_user'])) {
            $user_id = $_POST['user_id_to_edit'] ?? 0;
            $username = trim($_POST['edit_username'] ?? '');
            $password = $_POST['edit_password'] ?? '';
            $allowed_usernames = $_POST['edit_allowed_usernames'] ?? [];
            if (empty($username) || empty($allowed_usernames) || $user_id == 0) {
                 $error_message = 'សូមបំពេញឈ្មោះអ្នកប្រើប្រាស់ និងជ្រើសរើសសិទ្ធិយ៉ាងតិចមួយ។';
            } else {
                // ពិនិត្យមើលថាតើឈ្មោះថ្មីមានអ្នកផ្សេងប្រើហើយឬនៅ (លើកលែងតែ ID របស់ខ្លួនឯង)
                $stmt = $pdo->prepare("SELECT id FROM sub_users WHERE username = ? AND id != ?");
                $stmt->execute([$username, $user_id]);
                if ($stmt->fetch()) {
                    $error_message = "ឈ្មោះអ្នកប្រើប្រាស់ '" . htmlspecialchars($username) . "' មានអ្នកផ្សេងប្រើហើយ។ សូមជ្រើសរើសឈ្មោះផ្សេង។";
                } else {
                    $usernames_json = json_encode($allowed_usernames, JSON_UNESCAPED_UNICODE);
                    if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "UPDATE sub_users SET username = ?, password = ?, allowed_usernames = ? WHERE id = ?";
                        $params = [$username, $hashed_password, $usernames_json, $user_id];
                    } else {
                        $sql = "UPDATE sub_users SET username = ?, allowed_usernames = ? WHERE id = ?";
                        $params = [$username, $usernames_json, $user_id];
                    }
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $success_message = "ព័ត៌មានអ្នកប្រើប្រាស់ '" . htmlspecialchars($username) . "' ត្រូវបានកែសម្រួលដោយជោគជ័យ!";
                }
            }
        }
        // --- លុបអ្នកប្រើប្រាស់ ---
        if (isset($_POST['delete_user'])) {
            $user_id_to_delete = $_POST['user_id_to_delete'] ?? 0;
            if ($user_id_to_delete > 0) {
                $stmt = $pdo->prepare("DELETE FROM sub_users WHERE id = ?");
                $stmt->execute([$user_id_to_delete]);
                $success_message = "អ្នកប្រើប្រាស់ត្រូវបានលុបដោយជោគជ័យ!";
            }
        }
    }
}

// --- ទាញយកទិន្នន័យសម្រាប់បង្ហាញ ---
$sub_users_list = $pdo->query("SELECT id, username, allowed_usernames FROM sub_users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);
$all_available_usernames = $pdo->query("SELECT DISTINCT username FROM scan_logs WHERE username IS NOT NULL AND username != '' ORDER BY username COLLATE utf8mb4_unicode_ci")->fetchAll(PDO::FETCH_COLUMN);

// បង្កើត CSRF token ថ្មីសម្រាប់ request បន្ទាប់
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>គ្រប់គ្រងអ្នកប្រើប្រាស់រង</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Battambang:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- === CSS សម្រាប់រចនាឱ្យស្អាត === -->
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --light-gray: #f0f2f5;
            --border-color: #dee2e6;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --card-radius: 0.75rem;
        }
        body { 
            font-family: 'Battambang', cursive; 
            background-color: var(--light-gray);
            color: #333;
        }
        .container-fluid { max-width: 1200px; }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .page-header .btn { box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .card {
            border: none;
            border-radius: var(--card-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid var(--border-color);
            font-weight: 700;
            font-size: 1.1rem;
            padding: 1rem 1.5rem;
            border-radius: var(--card-radius) var(--card-radius) 0 0;
        }
        .card-header.bg-primary { background-image: linear-gradient(to right, #0d6efd, #3c82f8); }
        .card-body { padding: 1.5rem; }
        .alert {
            border-radius: 0.5rem;
            border-left: 5px solid;
            padding: 1rem 1.25rem;
        }
        .alert-success { border-color: var(--success-color); }
        .alert-danger { border-color: var(--danger-color); }
        .permissions-list-container {
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1rem;
            background-color: #f8f9fa;
        }
        .permissions-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .permissions-list { 
            max-height: 250px; 
            overflow-y: auto;
            padding-right: 10px; /* សម្រាប់ Scrollbar */
        }
        .permissions-list .form-check {
            padding: 0.3rem 0.5rem;
            border-radius: 0.25rem;
            transition: background-color 0.2s ease-in-out;
        }
        .permissions-list .form-check:hover { background-color: #e9ecef; }
        .table-responsive {
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
        }
        .table { margin-bottom: 0; }
        .table thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid var(--border-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            vertical-align: middle;
        }
        .table tbody td { vertical-align: middle; }
        .table tbody tr:last-child td { border-bottom: none; }
        .action-buttons {
            display: flex;
            gap: 0.5rem; /* បង្កើតគម្លាតរវាងប៊ូតុង */
        }
        .action-buttons .btn {
             width: 38px;
             height: 38px;
             display: inline-flex;
             align-items: center;
             justify-content: center;
        }
        .modal-content {
            border-radius: var(--card-radius);
            border: none;
        }
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid var(--border-color);
        }
        .form-text { font-size: 0.875em; }
        .btn { font-weight: 600; }
        .btn i { margin-right: 0.5rem; }
        .btn .fa-trash-alt, .btn .fa-edit { margin-right: 0; }
    </style>
</head>
<body>
    <div class="container-fluid my-4">
        <div class="page-header">
             <h1 class="h3 mb-0"><i class="fas fa-users-cog text-primary"></i> គ្រប់គ្រងអ្នកប្រើប្រាស់រង (Sub-Users)</h1>
             <a href="Panels.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> ត្រឡប់ទៅផ្ទាំងគ្រប់គ្រង</a>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <div><?php echo htmlspecialchars($success_message); ?></div>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <div><?php echo htmlspecialchars($error_message); ?></div>
            </div>
        <?php endif; ?>

        <!-- Form បង្កើតអ្នកប្រើប្រាស់ថ្មី -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user-plus"></i> បង្កើតអ្នកប្រើប្រាស់រងថ្មី</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">ឈ្មោះអ្នកប្រើប្រាស់រង (Username)</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">ពាក្យសម្ងាត់ (Password)</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><strong><i class="fas fa-tasks"></i> កំណត់សិទ្ធិមើល (Permissions)</strong></label>
                        <p class="form-text">អនុញ្ញាតឱ្យ Sub-user នេះមើលប្រវត្តិនៃឈ្មោះដែលបានជ្រើសរើសខាងក្រោម។</p>
                        
                        <div class="permissions-list-container" id="create_permissions_container">
                            <?php if (empty($all_available_usernames)): ?>
                                <p class="text-muted text-center m-0">មិនមានឈ្មោះអ្នកប្រើប្រាស់ក្នុងប្រព័ន្ធដើម្បីកំណត់សិទ្ធិទេ។</p>
                            <?php else: ?>
                                <div class="permissions-list-header">
                                    <input type="text" class="form-control form-control-sm w-50" placeholder="ស្វែងរកឈ្មោះ..." onkeyup="filterCheckboxes('create_permissions_container', this.value)">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="selectAll_create" onclick="toggleAllCheckboxes('create_permissions_container', this.checked)">
                                        <label class="form-check-label" for="selectAll_create">ជ្រើសរើសទាំងអស់</label>
                                    </div>
                                </div>
                                <div class="permissions-list">
                                    <?php foreach ($all_available_usernames as $log_username): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="allowed_usernames[]" value="<?php echo htmlspecialchars($log_username); ?>" id="create_user_<?php echo htmlspecialchars(md5($log_username)); ?>">
                                            <label class="form-check-label" for="create_user_<?php echo htmlspecialchars(md5($log_username)); ?>">
                                                <?php echo htmlspecialchars($log_username); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="submit" name="create_user" class="btn btn-primary"><i class="fas fa-save"></i> បង្កើតអ្នកប្រើប្រាស់</button>
                </form>
            </div>
        </div>

        <!-- បញ្ជីអ្នកប្រើប្រាស់រង -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list-ul"></i> បញ្ជីអ្នកប្រើប្រាស់រង</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">ID</th>
                                <th>ឈ្មោះអ្នកប្រើប្រាស់រង</th>
                                <th>ឈ្មោះដែលបានអនុញ្ញាត</th>
                                <th class="text-center">សកម្មភាព</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sub_users_list)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">មិនទាន់មានអ្នកប្រើប្រាស់រងនៅឡើយទេ។</td></tr>
                            <?php else: ?>
                                <?php foreach ($sub_users_list as $user): ?>
                                <tr>
                                    <td class="ps-3"><?php echo $user['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                    <td>
                                        <?php 
                                            $allowed_list = json_decode($user['allowed_usernames'], true);
                                            if (is_array($allowed_list) && !empty($allowed_list)) {
                                                $display_list = array_map('htmlspecialchars', $allowed_list);
                                                echo implode(', ', $display_list);
                                            } else {
                                                echo '<span class="text-muted">មិនបានកំណត់</span>';
                                            }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="action-buttons justify-content-center">
                                            <button type="button" class="btn btn-warning btn-sm edit-user-btn" 
                                                    title="កែសម្រួល"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editUserModal"
                                                    data-user-id="<?php echo $user['id']; ?>"
                                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                    data-allowed-items='<?php echo htmlspecialchars($user['allowed_usernames'], ENT_QUOTES, 'UTF-8'); ?>'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" onsubmit="return confirm('តើអ្នកពិតជាចង់លុបអ្នកប្រើប្រាស់ <?php echo htmlspecialchars($user['username']); ?> មែនទេ?');" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="user_id_to_delete" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger btn-sm" title="លុប"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal សម្រាប់កែសម្រួល -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editUserModalLabel"><i class="fas fa-user-edit"></i> កែសម្រួលព័ត៌មានអ្នកប្រើប្រាស់</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        <input type="hidden" name="user_id_to_edit" id="edit_user_id">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_username" class="form-label">ឈ្មោះអ្នកប្រើប្រាស់រង</label>
                                <input type="text" class="form-control" id="edit_username" name="edit_username" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_password" class="form-label">ពាក្យសម្ងាត់ថ្មី (New Password)</label>
                                <input type="password" class="form-control" id="edit_password" name="edit_password" aria-describedby="passwordHelp">
                                <div id="passwordHelp" class="form-text">ទុកឲ្យនៅទំនេរ បើមិនចង់ប្តូរពាក្យសម្ងាត់។</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><strong><i class="fas fa-tasks"></i> កែសម្រួលសិទ្ធិមើល</strong></label>
                             <div class="permissions-list-container" id="edit_permissions_container">
                                <?php if (empty($all_available_usernames)): ?>
                                    <p class="text-muted text-center m-0">មិនមានឈ្មោះអ្នកប្រើប្រាស់ដើម្បីជ្រើសរើសទេ។</p>
                                <?php else: ?>
                                    <div class="permissions-list-header">
                                        <input type="text" class="form-control form-control-sm w-50" placeholder="ស្វែងរកឈ្មោះ..." onkeyup="filterCheckboxes('edit_permissions_container', this.value)">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="selectAll_edit" onclick="toggleAllCheckboxes('edit_permissions_container', this.checked)">
                                            <label class="form-check-label" for="selectAll_edit">ជ្រើសរើសទាំងអស់</label>
                                        </div>
                                    </div>
                                    <div class="permissions-list">
                                        <?php foreach ($all_available_usernames as $log_username): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="edit_allowed_usernames[]" value="<?php echo htmlspecialchars($log_username); ?>" id="edit_user_<?php echo htmlspecialchars(md5($log_username)); ?>">
                                                <label class="form-check-label" for="edit_user_<?php echo htmlspecialchars(md5($log_username)); ?>"><?php echo htmlspecialchars($log_username); ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> បោះបង់</button>
                        <button type="submit" name="update_user" class="btn btn-success"><i class="fas fa-save"></i> រក្សាទុកការផ្លាស់ប្តូរ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // --- JavaScript សម្រាប់កែលម្អ UX ---

    // Function សម្រាប់បើក Modal កែសម្រួល និងបញ្ចូលទិន្នន័យចាស់
    document.addEventListener('DOMContentLoaded', function() {
        const editUserModal = document.getElementById('editUserModal');
        if (editUserModal) {
            editUserModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const userId = button.getAttribute('data-user-id');
                const username = button.getAttribute('data-username');
                const itemsJson = button.getAttribute('data-allowed-items');
                
                let allowedItems = [];
                try {
                    if (itemsJson) allowedItems = JSON.parse(itemsJson);
                } catch (e) {
                    console.error("Error parsing allowed items JSON:", e, itemsJson);
                }

                const modal = this;
                modal.querySelector('#edit_user_id').value = userId;
                modal.querySelector('#edit_username').value = username;
                modal.querySelector('#edit_password').value = '';

                const container = modal.querySelector('#edit_permissions_container');
                const checkboxes = container.querySelectorAll('.permissions-list .form-check-input');
                
                // Reset all checkboxes first
                checkboxes.forEach(checkbox => checkbox.checked = false);
                
                // Reset select all toggle and search field
                const selectAllToggle = container.querySelector('input[id^="selectAll"]');
                if(selectAllToggle) selectAllToggle.checked = false;
                const searchInput = container.querySelector('input[type="text"]');
                if(searchInput) searchInput.value = '';

                // Show all checkboxes before checking them
                filterCheckboxes(container.id, '');

                if (Array.isArray(allowedItems)) {
                    allowedItems.forEach(itemName => {
                        const safeItemName = itemName.replace(/\\/g, '\\\\').replace(/"/g, '\\"');
                        const checkboxToSelect = container.querySelector(`input[name="edit_allowed_usernames[]"][value="${safeItemName}"]`);
                        if (checkboxToSelect) {
                            checkboxToSelect.checked = true;
                        }
                    });
                }
            });
        }
    });

    // Function សម្រាប់ Filter/Search ក្នុង Checkbox list
    function filterCheckboxes(containerId, filterValue) {
        const filterText = filterValue.toLowerCase();
        const container = document.getElementById(containerId);
        const checkboxes = container.querySelectorAll('.permissions-list .form-check');
        
        checkboxes.forEach(function(item) {
            const label = item.querySelector('label').textContent.toLowerCase();
            if (label.includes(filterText)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }

    // Function សម្រាប់ "Select All / Deselect All"
    function toggleAllCheckboxes(containerId, isChecked) {
        const container = document.getElementById(containerId);
        // We only want to affect visible checkboxes when a filter is active
        const checkboxes = container.querySelectorAll('.permissions-list .form-check');
        
        checkboxes.forEach(function(item) {
             // If the checkbox item is visible, toggle its checked state
            if (item.style.display !== 'none') {
                 const checkboxInput = item.querySelector('.form-check-input');
                 if (checkboxInput) {
                     checkboxInput.checked = isChecked;
                 }
            }
        });
    }
    </script>
</body>
</html>