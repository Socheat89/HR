<?php
// FILE: stock_counting.php
session_start();

// កំណត់ Timezone ទៅម៉ោងនៅភ្នំពេញ
date_default_timezone_set('Asia/Phnom_Penh');

// --- ចំណុចកែសម្រួលទី១ ---
// ហៅ Logic រួមសម្រាប់រាប់ចំនួន Notification ដែលនឹងហៅ db_connect.php ដោយស្វ័យប្រវត្តិ
require_once 'nav_logic.php';

try {
    // require_once 'db_connect.php'; // << មិនចាំបាច់ទៀតទេ ព្រោះ nav_logic.php បានហៅរួចរាល់

    // Ensure UTF-8 encoding for PHP output
    header('Content-Type: text/html; charset=utf-8');

    $current_page = basename($_SERVER['PHP_SELF']);

    // Fetch all items for stock counting
    $stmt = $pdo->prepare("SELECT * FROM stock_items ORDER BY item_name ASC");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Check if stock_transfers table exists
    $table_exists = false;
    try {
        $stmt_check = $pdo->query("SHOW TABLES LIKE 'stock_transfers'");
        if ($stmt_check->rowCount() > 0) {
            $table_exists = true;
        }
    } catch (PDOException $e) {
        error_log("Error checking stock_transfers table: " . $e->getMessage());
    }

    // Handle saving stock counts
    $error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
    $success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
    if (isset($_POST['save_counts'])) {
        $physical_counts = $_POST['physical_count'];
        $phase = $_POST['phase'] ?? 'Morning';
        $current_date = date('Y-m-d H:i:s');

        foreach ($physical_counts as $item_id => $physical_qty) {
            if ($physical_qty === '' || !is_numeric($physical_qty)) {
                continue;
            }
            $stmt = $pdo->prepare("SELECT item_name, quantity FROM stock_items WHERE id = ?");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($item) {
                $system_qty = $item['quantity'];
                $difference = $physical_qty - $system_qty;
                $stmt = $pdo->prepare("INSERT INTO stock_count_history (item_id, item_name, system_qty, physical_qty, difference, count_date, phase) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$item_id, $item['item_name'], $system_qty, $physical_qty, $difference, $current_date, $phase]);
            }
        }
        $_SESSION['success_message'] = "ការរាប់ស្តុក ($phase) ត្រូវបានរក្សាទុកជោគជ័យ!";
        header("Location: stock_counting.php?tab=history&search_date=" . date('Y-m-d'));
        exit;
    }

    // Handle editing stock counts
    if (isset($_POST['edit_count'])) {
        $history_id = $_POST['history_id'];
        $physical_qty = $_POST['physical_qty'];
        $count_date = $_POST['count_date'];
        $phase = $_POST['phase'];
        if (!is_numeric($physical_qty) || $physical_qty < 0) {
            $error_message = "បរិមាណរាប់មិនត្រឹមត្រូវ។";
        } elseif (empty($count_date) || empty($phase)) {
            $error_message = "ទិន្នន័យមិនត្រឹមត្រូវ។";
        } else {
            $stmt = $pdo->prepare("SELECT item_id, system_qty FROM stock_count_history WHERE id = ?");
            $stmt->execute([$history_id]);
            $history_item = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($history_item) {
                $difference = $physical_qty - $history_item['system_qty'];
                $stmt = $pdo->prepare("UPDATE stock_count_history SET physical_qty = ?, difference = ?, count_date = ?, phase = ? WHERE id = ?");
                $stmt->execute([$physical_qty, $difference, $count_date, $phase, $history_id]);
                $_SESSION['success_message'] = "ការកែប្រែបានជោគជ័យ!";
                header("Location: stock_counting.php?tab=history&search_date=" . urlencode(date('Y-m-d', strtotime($count_date))) . "&phase=" . urlencode($phase));
                exit;
            } else {
                $error_message = "រកមិនឃើញកំណត់ត្រា។";
            }
        }
        $_SESSION['error_message'] = $error_message;
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
        exit;
    }

    // Handle deleting stock counts
    if (isset($_POST['delete_selected'])) {
        if (!empty($_POST['history_ids']) && is_array($_POST['history_ids'])) {
            $ids_to_delete = $_POST['history_ids'];
            $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
            $sql = "DELETE FROM stock_count_history WHERE id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($ids_to_delete)) {
                $_SESSION['success_message'] = "ទិន្នន័យដែលបានជ្រើសរើសត្រូវបានលុបជោគជ័យ!";
            } else {
                $_SESSION['error_message'] = "មានបញ្ហាក្នុងការលុបទិន្នន័យ។";
            }
        } else {
            $_SESSION['error_message'] = "សូមជ្រើសរើសទិន្នន័យណាមួយដើម្បីលុប។";
        }
        header("Location: stock_counting.php?tab=history&search_date=" . urlencode($_GET['search_date'] ?? date('Y-m-d')) . "&phase=" . urlencode($_GET['phase'] ?? ''));
        exit;
    }

    // Handle search by date and phase
    $search_date = isset($_GET['search_date']) ? $_GET['search_date'] : (isset($_GET['tab']) && $_GET['tab'] == 'history' ? date('Y-m-d') : '');
    $search_phase = isset($_GET['phase']) ? $_GET['phase'] : '';
    $history_items = [];
    if ($search_date) {
        $query = "SELECT * FROM stock_count_history WHERE DATE(count_date) = ?";
        $params = [$search_date];
        if ($search_phase) {
            $query .= " AND phase = ?";
            $params[] = $search_phase;
        }
        $query .= " ORDER BY item_name ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $history_items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // Clear session messages after displaying
    unset($_SESSION['error_message']);
    unset($_SESSION['success_message']);

} catch (PDOException $e) {
    $error_message = "កំហុសមូលដ្ឋានទិន្នន័យ: " . $e->getMessage();
    $items = $history_items = [];
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ផ្ទាំងគ្រប់គ្រងការរាប់ស្តុក</title>
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2/Logo-Van-Van-1.png">
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
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Kantumruy Pro', 'Poppins', sans-serif; }
        body { background: var(--light-gray); color: var(--text-color); line-height: 1.5; overflow-x: hidden; }
        .container { display: flex; min-height: 100vh; }
        .main-content { flex: 1; padding: 1rem; width: 100%; }
        @keyframes fadeInScale { from { opacity: 0; transform: scale(0.8) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        @keyframes fadeOutSlide { from { opacity: 1; transform: scale(1) translateY(0); } to { opacity: 0; transform: scale(0.8) translateY(20px); } }
        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1050; opacity: 0; transition: opacity 0.3s ease; align-items: center; justify-content: center; }
        .modal.show { opacity: 1; display: flex; }
        .modal-content { background: #fff; padding: 1.5rem; width: 90%; max-width: 400px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); transform: scale(0.8) translateY(20px); opacity: 0; transition: transform 0.3s ease, opacity 0.3s ease; }
        .modal-content.show { transform: scale(1) translateY(0); opacity: 1; animation: fadeInScale 0.3s ease-out forwards; }
        .modal-content.hide { animation: fadeOutSlide 0.3s ease-out forwards; }
        .modal-content .close { float: right; font-size: 1.5rem; cursor: pointer; transition: transform 0.2s ease; }
        .modal-content .close:hover { transform: rotate(90deg); }
        .modal-content h3 { margin-bottom: 1rem; color: #2c3e50; }
        .sidebar { width: 250px; flex-shrink: 0; background: #fff; box-shadow: 2px 0 10px rgba(0,0,0,0.05); padding: 2rem 1rem; display: none; position: fixed; top: 0; left: 0; height: 100vh; z-index: 1000; overflow-y: auto; }
        .sidebar .nav-item { display: flex; align-items: center; padding: 1rem; color: #030303; text-decoration: none; font-size: 1rem; border-radius: 8px; margin-bottom: 0.5rem; transition: all 0.2s ease; position: relative; }
        .notification-badge { background-color: #e74c3c; color: white; border-radius: 12px; padding: 2px 8px; font-size: 0.75rem; font-weight: 600; margin-left: auto; min-width: 20px; height: 20px; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .sidebar .nav-item:hover { background: #ecf0f1; transform: translateX(5px); }
        .sidebar .nav-item.active { color: #fff; background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .sidebar .nav-item i { margin-right: 0.85rem; font-size: 1.1rem; width: 20px; text-align: center; }
        .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-around; padding: 0.5rem 0; border-radius: 20px 20px 0 0; z-index: 999; }
        .bottom-nav .nav-item { text-align: center; padding: 0.5rem; color: #7f8c8d; text-decoration: none; font-size: 0.75rem; flex: 1; }
        .bottom-nav .nav-item.active { color: var(--primary-color); }
        .bottom-nav .nav-item i { display: block; font-size: 1.25rem; margin-bottom: 0.25rem; }
        .header { background: linear-gradient(135deg, #00b4db, #0083b0); color: #fff; padding: 1.5rem 1rem; border-radius: 0 0 20px 20px; text-align: center; margin-bottom: 1.5rem; }
        .header h1 { font-size: 1.5rem; font-weight: 600; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); overflow: hidden; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #e5e7eb; vertical-align: middle; }
        th { background: #f9fafb; font-weight: 600; text-transform: uppercase; font-size: 0.8rem; }
        .difference-missing { color: #e74c3c; font-weight: 600; }
        .difference-excess { color: #2ecc71; font-weight: 600; }
        .form-group { display: flex; flex-direction: column; gap: 1rem; margin-top: 1.5rem; }
        .form-group label { font-weight: 500; }
        .form-group button, .edit-btn, #printButton, #exportButton, #deleteSelectedBtn { 
            padding: 10px 15px; border: none; border-radius: 6px; cursor: pointer; 
            font-weight: 500; color: #fff; transition: all 0.2s ease; font-size: 0.9rem;
        }
        .form-group button:hover, .edit-btn:hover, #printButton:hover, #exportButton:hover, #deleteSelectedBtn:hover { 
            transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
        }
        #saveButton { background: var(--primary-color); }
        .edit-btn { background: #f39c12; font-size: 0.8rem; }
        #printButton { background: #3498db; }
        #exportButton { background: #27ae60; }
        #deleteSelectedBtn { background: var(--danger-color); }
        #deleteSelectedBtn:disabled {
            background: #f8b4b4;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        @media (min-width: 769px) { .sidebar { display: block; } .main-content { padding: 2rem; margin-left: 250px; } .header { border-radius: 12px; } .bottom-nav { display: none; } }
        @media (max-width: 768px) { .main-content { padding-bottom: 5rem; } }
        .tab-buttons { display: flex; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; }
        .tab-btn { flex: 1; padding: 1rem; border: none; background: none; cursor: pointer; font-size: 1rem; font-weight: 600; color: #64748b; transition: all 0.2s ease; border-bottom: 3px solid transparent; }
        .tab-btn:hover { background: #e2e8f0; }
        .tab-btn.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
        .tab-content { display: none; padding: 1.5rem; animation: slideIn 0.4s ease; }
        .tab-content.active { display: block; }
        input[type="text"], input[type="number"], input[type="date"], input[type="datetime-local"], select {
            width: 100%; padding: 10px; font-size: 1rem; border: 1px solid #e2e8f0; border-radius: 6px;
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
            -webkit-appearance: none; appearance: none;
        }
        select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat; background-position: right .75rem center; background-size: 16px 12px;
        }
        input:focus, select:focus { border-color: var(--primary-color); outline: 0; box-shadow: 0 0 0 0.2rem rgba(0, 180, 219, 0.25); }
        .count-input { width: 80px; text-align: center; }
        .count-controls { display: flex; align-items: center; gap: 0.5rem; }
        .quick-btn { border: none; border-radius: 50%; width: 32px; height: 32px; font-size: 1rem; cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .quick-btn:hover { transform: scale(1.1); }
        .btn-minus { background: #fef2f2; color: #e74c3c; }
        .btn-plus { background: #f0fdf4; color: #2ecc71; }
        .btn-correct { background: #eff6ff; color: #3b82f6; font-weight: bold; }
        .row-counted { background-color: #f0fdf4 !important; }
        .toolbar { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: center; }
        .toolbar > * { flex: 1; min-width: 150px; }
        .toolbar .search-form-container { display: contents; }
        .toolbar button { padding: 11px 15px; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="container">

        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="header"><h1>ផ្ទាំងគ្រប់គ្រងការរាប់ស្តុក</h1></div>

            <?php if ($error_message || $success_message): ?>
            <div id="notificationModal" class="modal show">
                <div class="modal-content show">
                    <span class="close" onclick="this.closest('.modal').classList.remove('show')">×</span>
                    <h3 style="color: <?php echo $error_message ? '#c0392b' : '#2ecc71'; ?>;"><?php echo $error_message ? 'កំហុស' : 'ជោគជ័យ'; ?></h3>
                    <p><?php echo htmlspecialchars($error_message ?: $success_message); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="tab-buttons">
                    <button class="tab-btn" data-tab="counting"><i class="fa-solid fa-clipboard-list"></i>&nbsp; រាប់ស្តុក</button>
                    <button class="tab-btn" data-tab="history"><i class="fa-solid fa-clock-rotate-left"></i>&nbsp; ប្រវត្តិ</button>
                </div>

                <div id="counting" class="tab-content">
                    <form id="countingForm" method="POST">
                        <div class="toolbar">
                            <input type="text" id="itemSearch" placeholder="ស្វែងរកឈ្មោះទំនិញ...">
                            <select name="phase" id="phase" required><option value="Morning">ពេលព្រឹក</option><option value="Afternoon">ពេលរសៀល</option></select>
                        </div>
                        <div class="table-container">
                            <table id="countingTable">
                                <thead><tr><th>ឈ្មោះទំនិញ</th><th>បរិមាណរាប់</th><th>ភាពខុសគ្នា</th></tr></thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                    <tr data-name="<?php echo strtolower(htmlspecialchars($item['item_name'])); ?>">
                                        <td><?php echo htmlspecialchars($item['item_name']); ?><br><small>ក្នុងប្រព័ន្ធ: <strong class="system-qty"><?php echo htmlspecialchars($item['quantity']); ?></strong></small></td>
                                        <td>
                                            <div class="count-controls">
                                                <button type="button" class="quick-btn btn-minus">-</button>
                                                <input type="number" class="count-input physical-count" name="physical_count[<?php echo $item['id']; ?>]" min="0" inputmode="numeric">
                                                <button type="button" class="quick-btn btn-plus">+</button>
                                                <button type="button" class="quick-btn btn-correct" title="បរិមាណត្រឹមត្រូវ">✓</button>
                                            </div>
                                        </td>
                                        <td class="difference">-</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="form-group"><button type="submit" id="saveButton" name="save_counts">រក្សាទុក</button></div>
                    </form>
                </div>

                <div id="history" class="tab-content">
                    <div class="toolbar">
                        <form method="GET" class="search-form-container">
                            <input type="hidden" name="tab" value="history">
                            <input type="date" name="search_date" value="<?php echo htmlspecialchars($search_date); ?>" onchange="this.form.submit()">
                            <select name="phase" onchange="this.form.submit()">
                                <option value="">ទាំងអស់</option>
                                <option value="Morning" <?php echo $search_phase == 'Morning' ? 'selected' : ''; ?>>ពេលព្រឹក</option>
                                <option value="Afternoon" <?php echo $search_phase == 'Afternoon' ? 'selected' : ''; ?>>ពេលរសៀល</option>
                            </select>
                        </form>
                        <button type="button" id="printButton"><i class="fa-solid fa-print"></i> បោះពុម្ព</button>
                        <button type="button" id="exportButton"><i class="fa-solid fa-file-excel"></i> ទាញយក Excel</button>
                    </div>

                    <form id="historyForm" method="POST" action="stock_counting.php?<?php echo http_build_query($_GET); ?>">
                        <?php if (!empty($history_items)): ?>
                            <div class="toolbar" style="justify-content: flex-end; margin-bottom: 1rem;">
                                <button type="submit" name="delete_selected" id="deleteSelectedBtn" disabled>
                                    <i class="fa-solid fa-trash-can"></i> លុបទិន្នន័យដែលបានជ្រើសរើស
                                </button>
                            </div>
                            <div class="table-container">
                                <table id="historyTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 1%;"><input type="checkbox" id="selectAll"></th>
                                            <th>ឈ្មោះទំនិញ</th>
                                            <th>ប្រព័ន្ធ</th>
                                            <th>បានរាប់</th>
                                            <th>ខុសគ្នា</th>
                                            <th>កាលបរិច្ឆេទ & ដំណាក់កាល</th>
                                            <th>សកម្មភាព</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($history_items as $item): ?>
                                        <tr>
                                            <td><input type="checkbox" class="row-checkbox" name="history_ids[]" value="<?php echo $item['id']; ?>"></td>
                                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['system_qty']); ?></td>
                                            <td><?php echo htmlspecialchars($item['physical_qty']); ?></td>
                                            <td class="<?php echo $item['difference'] < 0 ? 'difference-missing' : ($item['difference'] > 0 ? 'difference-excess' : ''); ?>">
                                                <?php if ($item['difference'] < 0) { echo "បាត់ " . abs($item['difference']); } elseif ($item['difference'] > 0) { echo "លើស " . $item['difference']; } else { echo "គ្រប់"; } ?>
                                            </td>
                                            <td><?php echo date('d/m/Y h:i A', strtotime($item['count_date'])); ?> (<?php echo htmlspecialchars($item['phase'] == 'Morning' ? 'ព្រឹក' : 'រសៀល'); ?>)</td>
                                            <td>
                                                 <button type="button" class="edit-btn" data-id="<?php echo $item['id']; ?>" data-item-name="<?php echo htmlspecialchars($item['item_name']); ?>" data-physical-qty="<?php echo $item['physical_qty']; ?>" data-count-date="<?php echo date('Y-m-d\TH:i', strtotime($item['count_date'])); ?>" data-phase="<?php echo htmlspecialchars($item['phase']); ?>">កែប្រែ</button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php elseif ($search_date): ?>
                            <p style="text-align: center; padding: 1rem;">រកមិនឃើញកំណត់ត្រាសម្រាប់ថ្ងៃទី <?php echo htmlspecialchars(date('d-M-Y', strtotime($search_date))); ?>។</p>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div id="editModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="this.closest('.modal').classList.remove('show')">×</span>
                    <h3>កែប្រែការរាប់ស្តុក</h3>
                    <form id="editForm" method="POST" action="stock_counting.php?<?php echo http_build_query($_GET); ?>">
                        <input type="hidden" name="history_id" id="editHistoryId">
                        <div class="form-group" style="margin-top:0;"><label>ឈ្មោះទំនិញ:</label><input type="text" id="editItemName" readonly style="background:#f1f5f9; cursor: not-allowed;"></div>
                        <div class="form-group"><label for="editPhysicalQty">បរិមាណរាប់:</label><input type="number" id="editPhysicalQty" name="physical_qty" min="0" required></div>
                        <div class="form-group"><label for="editCountDate">កាលបរិច្ឆេទ:</label><input type="datetime-local" id="editCountDate" name="count_date" required></div>
                        <div class="form-group"><label for="editPhase">ដំណាក់កាល:</label><select name="phase" id="editPhase" required><option value="Morning">ពេលព្រឹក</option><option value="Afternoon">ពេលរសៀល</option></select></div>
                        <div class="form-group"><button type="submit" name="edit_count">រក្សាទុកការកែប្រែ</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="bottom-nav">
        <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>"><i class="fa-solid fa-house"></i> ផ្ទាំងគ្រប់គ្រង</a>
        <a href="index.php" class="nav-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>"><i class="fa-solid fa-box-archive"></i> ទំនិញ</a>
        <a href="reports.php" class="nav-item <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>"><i class="fa-solid fa-chart-simple"></i> របាយការណ៍</a>
        <a href="stock_counting.php" class="nav-item <?php echo $current_page == 'stock_counting.php' ? 'active' : ''; ?>"><i class="fa-solid fa-clipboard-list"></i> ការរាប់ស្តុក</a>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.tab-btn');
        const contents = document.querySelectorAll('.tab-content');
        const editModal = document.getElementById('editModal');
        const notificationModal = document.getElementById('notificationModal');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                contents.forEach(c => c.classList.remove('active'));
                tab.classList.add('active');
                document.getElementById(tab.dataset.tab).classList.add('active');
                const url = new URL(window.location);
                url.searchParams.set('tab', tab.dataset.tab);
                window.history.replaceState({}, '', url);
            });
        });

        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab') || 'counting';
        const tabToActivate = document.querySelector(`.tab-btn[data-tab="${activeTab}"]`);
        if(tabToActivate) tabToActivate.click(); else document.querySelector('.tab-btn[data-tab="counting"]').click();
        
        if (activeTab === 'history' && !urlParams.has('search_date')) {
             const searchDateInput = document.querySelector('input[name="search_date"]');
             if (searchDateInput && !searchDateInput.value) searchDateInput.valueAsDate = new Date();
        }

        const countingTable = document.getElementById('countingTable');
        function updateDifference(input) {
            const row = input.closest('tr');
            if (!row) return;
            const diffCell = row.querySelector('.difference');
            if (input.value === '') {
                diffCell.textContent = '-';
                diffCell.className = 'difference';
                row.classList.remove('row-counted');
                return;
            }
            const physicalQty = parseInt(input.value, 10);
            if (isNaN(physicalQty)) return;
            row.classList.add('row-counted');
            const systemQty = parseInt(row.querySelector('.system-qty').textContent, 10) || 0;
            const difference = physicalQty - systemQty;
            if (difference < 0) {
                diffCell.textContent = `បាត់ ${Math.abs(difference)}`;
                diffCell.className = 'difference difference-missing';
            } else if (difference > 0) {
                diffCell.textContent = `លើស ${difference}`;
                diffCell.className = 'difference difference-excess';
            } else {
                diffCell.textContent = 'គ្រប់';
                diffCell.className = 'difference';
            }
        }
        
        if (countingTable) {
            countingTable.addEventListener('click', function(e) {
                const target = e.target.closest('.quick-btn');
                if (!target) return;
                const row = target.closest('tr');
                if(!row) return;
                const input = row.querySelector('.physical-count');
                if (!input) return;
                let currentValue = parseInt(input.value, 10) || 0;
                if (target.classList.contains('btn-plus')) input.value = currentValue + 1;
                else if (target.classList.contains('btn-minus')) { if (currentValue > 0) input.value = currentValue - 1; }
                else if (target.classList.contains('btn-correct')) { const systemQty = parseInt(row.querySelector('.system-qty').textContent, 10); input.value = isNaN(systemQty) ? 0 : systemQty; }
                input.dispatchEvent(new Event('input', { bubbles: true }));
            });
            countingTable.addEventListener('input', function(e) { if (e.target.classList.contains('physical-count')) updateDifference(e.target); });
        }

        const itemSearchInput = document.getElementById('itemSearch');
        if (itemSearchInput && countingTable) {
            itemSearchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase().trim();
                const rows = countingTable.querySelectorAll('tbody tr');
                rows.forEach(row => { row.style.display = (row.dataset.name || '').includes(searchTerm) ? '' : 'none'; });
            });
        }
        
        window.onclick = function(event) {
            if (event.target == notificationModal && notificationModal) notificationModal.classList.remove('show');
            if (event.target == editModal && editModal) editModal.classList.remove('show');
        };
        document.querySelectorAll('.modal .close').forEach(closeBtn => { closeBtn.addEventListener('click', function() { this.closest('.modal').classList.remove('show'); }); });

        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('editHistoryId').value = this.dataset.id;
                document.getElementById('editItemName').value = this.dataset.itemName;
                document.getElementById('editPhysicalQty').value = this.dataset.physicalQty;
                document.getElementById('editCountDate').value = this.dataset.countDate;
                document.getElementById('editPhase').value = this.dataset.phase;
                if(editModal) {
                    editModal.classList.add('show');
                    editModal.querySelector('.modal-content').classList.add('show');
                }
            });
        });

        const historyForm = document.getElementById('historyForm');
        const selectAllCheckbox = document.getElementById('selectAll');
        const rowCheckboxes = document.querySelectorAll('.row-checkbox');
        const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');

        function updateDeleteButtonState() {
            if (!deleteSelectedBtn) return;
            const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
            deleteSelectedBtn.disabled = checkedCount === 0;
        }

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                rowCheckboxes.forEach(checkbox => { checkbox.checked = this.checked; });
                updateDeleteButtonState();
            });
        }

        rowCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                if (selectAllCheckbox) {
                    const totalCheckboxes = rowCheckboxes.length;
                    const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
                    selectAllCheckbox.checked = totalCheckboxes > 0 && totalCheckboxes === checkedCount;
                }
                updateDeleteButtonState();
            });
        });
        
        if (historyForm) {
            historyForm.addEventListener('submit', function(e) {
                const submitter = e.submitter || (document.activeElement.type === 'submit' ? document.activeElement : null);
                if (submitter && submitter.name === 'delete_selected') {
                    const checkedCount = document.querySelectorAll('#historyForm .row-checkbox:checked').length;
                    if (checkedCount === 0) {
                        alert('សូមជ្រើសរើសទិន្នន័យណាមួយដើម្បីលុប។');
                        e.preventDefault();
                        return;
                    }
                    const confirmation = confirm(`តើអ្នកប្រាកដទេថាចង់លុបទិន្នន័យចំនួន ${checkedCount} ដែលបានជ្រើសរើស? \n\nសកម្មភាពនេះមិនអាចមិនធ្វើវិញបានទេ!`);
                    if (!confirmation) e.preventDefault();
                }
            });
        }
        updateDeleteButtonState();

        const printButton = document.getElementById('printButton');
        if (printButton) {
            printButton.addEventListener('click', function() {
                const tableToPrint = document.getElementById('historyTable');
                if (!tableToPrint || tableToPrint.rows.length <= 1) { alert('មិនមានទិន្នន័យក្នុងតារាងប្រវត្តិដើម្បីបោះពុម្ពទេ!'); return; }
                const clonedTable = tableToPrint.cloneNode(true);
                clonedTable.querySelectorAll('th:first-child, td:first-child, th:last-child, td:last-child').forEach(el => el.remove());
                const printWindow = window.open('', '', 'height=600,width=800');
                printWindow.document.write('<html><head><title>របាយការណ៍រាប់ស្តុក</title><style>');
                printWindow.document.write(`@import url('https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;700&display=swap'); body { font-family: 'Kantumruy Pro', sans-serif; } table { width: 100%; border-collapse: collapse; font-size: 12px; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; } h1 { text-align: center; } .difference-missing { color: #e74c3c; font-weight: bold; } .difference-excess { color: #2ecc71; font-weight: bold; } @media print { body { -webkit-print-color-adjust: exact; } }`);
                printWindow.document.write('</style></head><body>');
                const searchDateVal = document.querySelector('input[name="search_date"]').value;
                const searchPhaseVal = document.querySelector('select[name="phase"]').value;
                printWindow.document.write('<h1>របាយការណ៍រាប់ស្តុក</h1>');
                if (searchDateVal) printWindow.document.write(`<p><strong>កាលបរិច្ឆេទ:</strong> ${new Date(searchDateVal).toLocaleDateString('km-KH', { day: '2-digit', month: 'long', year: 'numeric' })}</p>`);
                if (searchPhaseVal) printWindow.document.write(`<p><strong>ដំណាក់កាល:</strong> ${searchPhaseVal == 'Morning' ? 'ពេលព្រឹក' : 'ពេលរសៀល'}</p>`);
                printWindow.document.write(clonedTable.outerHTML);
                printWindow.document.write('</body></html>');
                printWindow.document.close();
                printWindow.focus();
                setTimeout(() => { printWindow.print(); }, 500);
            });
        }

        const exportButton = document.getElementById('exportButton');
        if (exportButton) {
            exportButton.addEventListener('click', function() {
                const tableToExport = document.getElementById('historyTable');
                if (!tableToExport || tableToExport.rows.length <= 1) { alert('មិនមានទិន្នន័យក្នុងតារាងប្រវត្តិដើម្បីទាញយកទេ!'); return; }
                let csv = [];
                const rows = tableToExport.querySelectorAll("tr");
                const headers = [];
                rows[0].querySelectorAll("th").forEach((header, index) => { if (index > 0 && index < rows[0].querySelectorAll("th").length - 1) headers.push('"' + header.innerText.replace(/"/g, '""') + '"'); });
                csv.push(headers.join(','));
                for (let i = 1; i < rows.length; i++) {
                    const row = [], cols = rows[i].querySelectorAll("td");
                    for (let j = 1; j < cols.length - 1; j++) {
                        let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/\s+/g, " ").trim();
                        data = '"' + data.replace(/"/g, '""') + '"';
                        row.push(data);
                    }
                    csv.push(row.join(","));
                }
                const bom = "\uFEFF";
                const csvFile = new Blob([bom + csv.join("\n")], { type: "text/csv;charset=utf-8;" });
                const downloadLink = document.createElement("a");
                downloadLink.href = URL.createObjectURL(csvFile);
                const searchDateVal = document.querySelector('input[name="search_date"]').value || 'report';
                downloadLink.download = `stock_count_history_${searchDateVal}.csv`;
                downloadLink.style.display = "none";
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);
            });
        }
    });
    </script>
</body>
</html>