<?php
// FILE: review_requests.php
session_start();

// ហៅ Logic រួមសម្រាប់រាប់ចំនួន Notification ដែលនឹងហៅ db_connect.php ដោយស្វ័យប្រវត្តិ
require_once 'nav_logic.php';

// ===== START: បន្ថែមកូដកំណត់តំបន់ម៉ោងនៅទីនេះ =====
// កំណត់តំបន់ម៉ោង (Timezone) ទៅ 'Asia/Phnom_Penh' ដើម្បីធានាថាម៉ោងបង្ហាញត្រឹមត្រូវ
date_default_timezone_set('Asia/Phnom_Penh');
// ===== END: បញ្ចប់ការបន្ថែម =====


// =========================================================================
// == ផ្នែកទី១: LOGIC សម្រាប់ដំណើរការលុប (PROCESS DELETE REQUEST) ==
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['request_ids'])) {

    $request_ids = array_filter($_POST['request_ids'], 'ctype_digit');

    if (!empty($request_ids)) {
        try {
            $pdo->beginTransaction();
            $placeholders = implode(',', array_fill(0, count($request_ids), '?'));

            // ជំហានទី១: លុបចេញពីតារាងកូន (stock_request_items) ដោយប្រើឈ្មោះ Column ត្រឹមត្រូវ 'stock_request_id'
            $stmt_items = $pdo->prepare("DELETE FROM stock_request_items WHERE stock_request_id IN ($placeholders)");
            $stmt_items->execute($request_ids);

            // ជំហានទី២: លុបចេញពីតារាងមេ (stock_request)
            $stmt_main = $pdo->prepare("DELETE FROM stock_request WHERE id IN ($placeholders)");
            $stmt_main->execute($request_ids);

            $pdo->commit();
            $_SESSION['success_message'] = "បានលុបសំណើរចំនួន " . count($request_ids) . " ដោយជោគជ័យ។";

        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error_message'] = "ការលុបបានបរាជ័យ: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Invalid request IDs provided.";
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
// =================== ចប់ផ្នែក LOGIC សម្រាប់លុប ===================


// =========================================================================
// == ផ្នែកទី២: LOGIC សម្រាប់ទាញទិន្នន័យមកបង្ហាញ (DISPLAY DATA) ==
// =========================================================================
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 2; // Simulating 'Admin Manager' is logged in
}

header('Content-Type: text/html; charset=utf-8');
$current_page = basename($_SERVER['PHP_SELF']);
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';

unset($_SESSION['error_message']);
unset($_SESSION['success_message']);

try {
    $stmt = $pdo->prepare("
        SELECT
            sr.id, u.full_name, sr.location, sr.created_at
        FROM stock_request sr
        JOIN users u ON sr.user_id = u.id
        WHERE sr.status = 'pending'
        ORDER BY sr.created_at DESC
    ");
    $stmt->execute();
    $pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "កំហុសក្នុងការទាញយកសំណើរ: " . $e->getMessage();
    $pending_requests = [];
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>សំណើរស្តុករង់ចាំការពិនិត្យ</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;600&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bayon&family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #00b4db;
            --primary-hover: #0083b0;
            --light-gray: #f5f7fa;
            --text-color: #2c3e50;
            --danger-color: #e74c3c;
            --danger-hover: #c0392b;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Kantumruy Pro', 'Poppins', sans-serif;
        }
        body { background: var(--light-gray); color: var(--text-color); line-height: 1.5; overflow-x: hidden; }
        @keyframes fadeInScale { from { opacity: 0; transform: scale(0.8) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1050; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: #fff; padding: 1.5rem; width: 90%; max-width: 400px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); animation: fadeInScale 0.3s ease-out forwards; }
        .modal-content .close { float: right; font-size: 1.5rem; cursor: pointer; }
        .container { display: flex; min-height: 100vh; }
        .sidebar { width: 250px; flex-shrink: 0; background: #fff; box-shadow: 2px 0 10px rgba(0,0,0,0.05); padding: 2rem 1rem; display: none; position: fixed; top: 0; left: 0; height: 100vh; z-index: 1000; overflow-y: auto; }
        .sidebar .nav-item { display: flex; align-items: center; padding: 1rem; color: #030303; text-decoration: none; font-size: 1rem; border-radius: 8px; margin-bottom: 0.5rem; transition: all 0.2s ease; position: relative; }
        .sidebar .nav-item:hover { background: #ecf0f1; transform: translateX(5px); }
        .sidebar .nav-item.active { color: #fff; background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .sidebar .nav-item i { margin-right: 0.85rem; font-size: 1.1rem; width: 20px; text-align: center; }
        .notification-badge { background-color: #e74c3c; color: white; border-radius: 12px; padding: 2px 8px; font-size: 0.75rem; font-weight: 600; margin-left: auto; min-width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .main-content { flex: 1; padding: 1rem; width: 100%; }
        .header { background: linear-gradient(135deg, #00b4db, #0083b0); color: #fff; padding: 1.5rem 1rem; border-radius: 0 0 20px 20px; text-align: center; margin-bottom: 1.5rem; }
        .header h1 { font-size: 1.5rem; font-weight: 600; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); animation: slideIn 0.5s ease-out; overflow: hidden; }
        .table-container { overflow-x: auto; }
        .requests-table { width: 100%; border-collapse: collapse; user-select: none; -webkit-user-select: none; -moz-user-select: none; } /* បន្ថែម user-select: none ដើម្បីការពារការ select text */
        .requests-table th, .requests-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        .requests-table th:first-child, .requests-table td:first-child { width: 1%; text-align: center; }
        .requests-table th { font-weight: 600; text-transform: uppercase; font-size: 0.8rem; background: #f8fafc; }
        .action-buttons { display: flex; gap: 8px; }
        .process-btn, .delete-btn { color: #fff; padding: 8px 15px; text-decoration: none; border-radius: 6px; font-size: 0.85rem; transition: all 0.2s ease; display: inline-block; border: none; cursor: pointer; }
        .process-btn { background: #27ae60; }
        .process-btn:hover { background: #229954; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .delete-btn { background: var(--danger-color); }
        .delete-btn:hover { background: var(--danger-hover); transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .table-actions { padding: 1rem 1rem; background-color: #f8fafc; border-top: 1px solid #e2e8f0; }
        .delete-selected-btn { background-color: var(--danger-color); color: #fff; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.9rem; transition: background-color 0.2s; }
        .delete-selected-btn:hover { background-color: var(--danger-hover); }
        .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-around; padding: 0.5rem 0; border-radius: 20px 20px 0 0; z-index: 999; }
        .bottom-nav .nav-item { text-align: center; padding: 0.5rem; color: #7f8c8d; text-decoration: none; font-size: 0.75rem; flex: 1; }
        .bottom-nav .nav-item.active { color: var(--primary-color); }
        .bottom-nav .nav-item i { display: block; font-size: 1.25rem; margin-bottom: 0.25rem; }
        @media (min-width: 769px) { .sidebar { display: block; } .main-content { padding: 2rem; margin-left: 250px; } .header { border-radius: 12px; } .bottom-nav { display: none; } }
        @media (max-width: 768px) { .main-content { padding-bottom: 5rem; } }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>
        <div class="main-content">
            <div class="header"><h1>សំណើរស្តុករង់ចាំការពិនិត្យ</h1></div>
            <?php if ($error_message || $success_message): ?>
            <div id="notificationModal" class="modal show">
                <div class="modal-content">
                    <span class="close" onclick="hideNotificationModal()">×</span>
                    <h3 style="color: <?php echo $error_message ? '#c0392b' : '#2ecc71'; ?>;"><?php echo $error_message ? 'កំហុស' : 'ជោគជ័យ'; ?></h3>
                    <p><?php echo htmlspecialchars($error_message ?: $success_message); ?></p>
                </div>
            </div>
            <?php endif; ?>
            <div class="card">
                <form id="deleteForm" action="" method="post">
                    <div class="table-container">
                        <table class="requests-table">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" id="selectAll"></th>
                                    <th>លេខសម្គាល់សំណើរ</th>
                                    <th>ឈ្មោះអ្នកស្នើសុំ</th>
                                    <th>ទីតាំង</th>
                                    <th>កាលបរិច្ឆេទដាក់ស្នើ</th>
                                    <th>សកម្មភាព</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($pending_requests) > 0): ?>
                                    <?php foreach ($pending_requests as $request): ?>
                                        <tr>
                                            <td><input type="checkbox" name="request_ids[]" class="request-checkbox" value="<?php echo $request['id']; ?>"></td>
                                            <td>#<?php echo $request['id']; ?></td>
                                            <td><?php echo htmlspecialchars($request['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($request['location'] ?? 'គ្មាន'); ?></td>
                                            
                                            <!-- កូដបង្ហាញកាលបរិច្ឆេទនេះនឹងដំណើរការត្រឹមត្រូវដោយសារយើងបានកំណត់ Timezone នៅផ្នែកខាងលើ -->
                                            <td><?php echo date('d/m/Y h:i A', strtotime($request['created_at'])); ?></td>
                                            
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="fulfill_transfer.php?request_id=<?php echo $request['id']; ?>" class="process-btn">ដំណើរការ</a>
                                                    <button type="button" class="delete-btn" onclick="confirmSingleDelete(<?php echo $request['id']; ?>)">លុប</button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" style="text-align: center; padding: 20px;">រកមិនឃើញសំណើរស្តុករង់ចាំ។</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($pending_requests) > 0): ?>
                    <div class="table-actions">
                        <button type="submit" class="delete-selected-btn">លុបសំណើរដែលបានជ្រើសរើស</button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="bottom-nav">
        <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"><i class="fa-solid fa-house"></i> ផ្ទាំងគ្រប់គ្រង</a>
        <a href= class="nav-item <?php echo $current_page ==  ? 'active' : ''; ?>"><i class="fa-solid fa-box-archive"></i> ទំនិញ</a>
        <a href="reports.php" class="nav-item <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>"><i class="fa-solid fa-chart-simple"></i> របាយការណ៍</a>
        <a href="stock_counting.php" class="nav-item <?php echo $current_page == 'stock_counting.php' ? 'active' : ''; ?>"><i class="fa-solid fa-clipboard-list"></i> ការរាប់ស្តុក</a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Logic សម្រាប់ Modal (រក្សាទុកដដែល) ---
            const modal = document.getElementById('notificationModal');
            window.hideNotificationModal = function() { if(modal) modal.classList.remove('show'); }
            window.onclick = function(event) { if (event.target == modal) hideNotificationModal(); }
            if(modal) { setTimeout(() => { hideNotificationModal(); }, 4000); }

            // --- Logic សម្រាប់ Checkbox និង Form (រក្សាទុកដដែល) ---
            const selectAllCheckbox = document.getElementById('selectAll');
            const rowCheckboxes = document.querySelectorAll('.request-checkbox');
            const deleteForm = document.getElementById('deleteForm');

            if(selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    rowCheckboxes.forEach(checkbox => { checkbox.checked = this.checked; });
                });
            }
            if(deleteForm) {
                deleteForm.addEventListener('submit', function(event) {
                    const singleDeleteInput = document.querySelector('input[name="request_ids[]"][type="hidden"]');
                    if (singleDeleteInput) { return; }
                    const checkedCheckboxes = document.querySelectorAll('.request-checkbox:checked');
                    if (checkedCheckboxes.length === 0) {
                        alert('សូមជ្រើសរើសសំណើរយ៉ាងហោចណាស់មួយដើម្បីលុប។');
                        event.preventDefault();
                        return;
                    }
                    if (!confirm('តើអ្នកពិតជាចង់លុបសំណើរ ' + checkedCheckboxes.length + ' ដែលបានជ្រើសរើសមែនទេ?')) {
                        event.preventDefault();
                    }
                });
            }

            // ==========================================================
            // == LOGIC ថ្មីសម្រាប់អូស Select (Drag to Select) ==
            // ==========================================================
            const tableBody = document.querySelector('.requests-table tbody');
            let isDragging = false;

            if (tableBody) {
                // Event នៅពេលចាប់ផ្តើមចុច Mouse
                tableBody.addEventListener('mousedown', function(event) {
                    // បើចុចលើប៊ូតុង, link, ឬ checkbox គឺមិនចាប់ផ្តើមការអូសទេ
                    const targetTag = event.target.tagName.toLowerCase();
                    if (targetTag === 'input' || targetTag === 'a' || targetTag === 'button') {
                        return;
                    }
                    isDragging = true;
                    event.preventDefault(); // ការពារការ select text ដោយអចេតនា
                });

                // Event នៅពេល Mouse កំពុងធ្វើចលនា
                tableBody.addEventListener('mousemove', function(event) {
                    if (isDragging) {
                        const row = event.target.closest('tr'); // រក <tr> ដែល Mouse កំពុងនៅពីលើ
                        if (row) {
                            const checkbox = row.querySelector('.request-checkbox');
                            if (checkbox) {
                                checkbox.checked = true; // ធីក checkbox នោះ
                            }
                        }
                    }
                });
            }

            // Event នៅពេលលែងដៃពី Mouse (បញ្ឈប់ការអូស)
            // ដាក់ Listener លើ window ដើម្បីឲ្យវាដំណើរការ ទោះបីជា Mouse នៅក្រៅតារាងក៏ដោយ
            window.addEventListener('mouseup', function() {
                isDragging = false;
            });
        });

        // --- Function សម្រាប់លុបមួយ (រក្សាទុកដដែល) ---
        function confirmSingleDelete(id) {
            if (confirm('តើអ្នកពិតជាចង់លុបសំណើរលេខ #' + id + ' មែនទេ?')) {
                const form = document.getElementById('deleteForm');
                const existingInputs = form.querySelectorAll('input[type="hidden"][name="request_ids[]"]');
                existingInputs.forEach(input => input.remove());
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'request_ids[]';
                hiddenInput.value = id;
                form.appendChild(hiddenInput);
                form.submit();
            }
        }
    </script>
</body>
</html>