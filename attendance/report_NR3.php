<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ត្រូវប្រាកដថាអ្នកមាន file db_connect.php ហើយវាបង្កើត object $pdo
require_once 'db_connect.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =============================================================================
// ការកំណត់រចនាសម្ព័ន្ធ (CONFIGURATION)
// =============================================================================
$main_db_table = 'nr3_consolidated_staff';
$new_staff_db_table = 'nr3_new_staff';

$department_configs = [
    'store'    => ['label' => 'បុគ្គលិក NR3'],
    'intern'   => ['label' => 'បុគ្គលិកកម្មសិក្សា'],
    'stock'    => ['label' => 'ផ្នែកស្តុក'],
    'sales'    => ['label' => 'ផ្នែកលក់'],
    'cashier'  => ['label' => 'ផ្នែកគិតលុយ'],
];

// =============================================================================
// FUNCTION សម្រាប់គ្រប់គ្រងការបញ្ជូនទិន្នន័យ (POST REQUESTS)
// =============================================================================
function handle_post_request(PDO $pdo, string $main_db_table, string $new_staff_db_table): string
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF Token.']);
            exit();
        }
        return "បរាជ័យ: សំណើមិនត្រឹមត្រូវ (Invalid CSRF Token)។";
    }

    // --- [AJAX] Inline edit for attendance table ---
    if (isset($_POST['action']) && $_POST['action'] == 'update_single_attendance') {
        header('Content-Type: application/json');
        $date = $_POST['date'] ?? null;
        $column = $_POST['column'] ?? null;
        $value = isset($_POST['value']) ? (int)$_POST['value'] : 0;
        
        // បញ្ជីអនុញ្ញាត (Allowed columns)
        $allowed_columns = [
            'store_female', 'store_male', 
            'intern_female', 'intern_male', 
            'stock_female', 'stock_male', 
            'sales_female', 'sales_male', 
            'cashier_female', 'cashier_male'
        ];
        
        if (!$date || !$column || !in_array($column, $allowed_columns)) {
            echo json_encode(['success' => false, 'message' => 'ទិន្នន័យមិនត្រឹមត្រូវ។']);
            exit();
        }

        try {
            $sql = "INSERT INTO {$main_db_table} (reports_date, {$column}) VALUES (:reports_date, :value) ON DUPLICATE KEY UPDATE {$column} = :value_update";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['reports_date' => $date, 'value' => $value, 'value_update' => $value]);
            echo json_encode(['success' => true, 'message' => 'រក្សាទុកទិន្នន័យរួចរាល់!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
    
    // --- [AJAX] Inline edit for new staff table ---
    if (isset($_POST['action']) && $_POST['action'] == 'update_new_staff_inline') {
        header('Content-Type: application/json');
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $column = $_POST['column'] ?? null;
        $value = $_POST['value'] ?? '';

        $allowed_columns = ['number', 'name', 'role', 'note', 'reports_date'];
        if (!$id || !$column || !in_array($column, $allowed_columns)) {
            echo json_encode(['success' => false, 'message' => 'ទិន្នន័យមិនត្រឹមត្រូវ។']);
            exit();
        }

        try {
            $sql = "UPDATE {$new_staff_db_table} SET {$column} = :value WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['value' => $value, 'id' => $id]);
            echo json_encode(['success' => true, 'message' => 'រក្សាទុកទិន្នន័យរួចរាល់!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // --- [AJAX] Create a new empty staff row for inline editing ---
    if (isset($_POST['action']) && $_POST['action'] == 'create_new_staff_row') {
        header('Content-Type: application/json');
        $date = $_POST['date'] ?? date('Y-m-d');
        try {
            $stmt = $pdo->prepare("INSERT INTO {$new_staff_db_table} (name, role, note, reports_date, number) VALUES ('', '', '', ?, '')");
            $stmt->execute([$date]);
            $newId = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'new_id' => $newId]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // --- [FORM SUBMISSION] Save new staff from Modal ---
    if (isset($_POST['save_new_staff'])) {
        $records_to_save = json_decode($_POST['save_new_staff'], true);
        if (json_last_error() !== JSON_ERROR_NONE) return "បរាជ័យ: ទិន្នន័យបុគ្គលិកថ្មីមិនត្រឹមត្រូវ។";
        
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO {$new_staff_db_table} (number, name, role, note, reports_date) VALUES (?, ?, ?, ?, ?)");
            foreach ($records_to_save as $record) {
                $stmt->execute([$record['number'] ?? '', $record['name'] ?? '', $record['role'] ?? '', $record['note'] ?? '', $record['reports_date'] ?? date('Y-m-d')]);
            }
            $pdo->commit();
            return "បានរក្សាទុកកំណត់ត្រាបុគ្គលិកថ្មីដោយជោគជ័យ។";
        } catch (Exception $e) {
            $pdo->rollBack();
            return "បរាជ័យក្នុងការរក្សាទុកបុគ្គលិកថ្មី! " . $e->getMessage();
        }
    }

    // --- [AJAX] Delete staff handler ---
    if (isset($_POST['action']) && $_POST['action'] === 'delete_new_staff_ajax') {
        header('Content-Type: application/json');
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'បរាជ័យ: មិនមាន ID សម្រាប់លុប។']);
            exit();
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM {$new_staff_db_table} WHERE id = ?");
            $success = $stmt->execute([$id]);
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'កំណត់ត្រាត្រូវបានលុបជោគជ័យ!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'បរាជ័យក្នុងការលុប!']);
            }
        } catch (Exception $e) {
             echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
    
    // --- Fallback for old delete form submission ---
    if (isset($_POST['delete_new_staff'])) {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) return "បរាជ័យ: មិនមាន ID សម្រាប់លុប។";
        $stmt = $pdo->prepare("DELETE FROM {$new_staff_db_table} WHERE id = ?");
        return $stmt->execute([$id]) ? "កំណត់ត្រាត្រូវបានលុបជោគជ័យ!" : "បរាជ័យក្នុងការលុប!";
    }
    
    return '';
}

// =============================================================================
// MAIN EXECUTION FLOW
// =============================================================================
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);
$selected_date = $_GET['date'] ?? date('Y-m-d');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $crud_message = handle_post_request($pdo, $main_db_table, $new_staff_db_table);
        if (!empty($crud_message)) {
            $_SESSION['message'] = $crud_message;
            header("Location: " . $_SERVER['PHP_SELF'] . "?date=" . urlencode($selected_date));
            exit();
        }
    }
    $stmt = $pdo->prepare("SELECT * FROM {$main_db_table} WHERE reports_date = ?");
    $stmt->execute([$selected_date]);
    $daily_record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$daily_record) {
        $daily_record = ['reports_date' => $selected_date];
        $columns = [
            'store_female', 'store_male', 'intern_female', 'intern_male', 
            'stock_female', 'stock_male', 'sales_female', 'sales_male', 
            'cashier_female', 'cashier_male'
        ];
        foreach($columns as $col){ $daily_record[$col] = 0; }
    }

    $stmt = $pdo->prepare("SELECT * FROM {$new_staff_db_table} WHERE reports_date = ? ORDER BY id ASC");
    $stmt->execute([$selected_date]);
    $new_staff_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $message = "កំហុសក្នុងការទាក់ទងទិន្នន័យ: " . $e->getMessage();
    $daily_record = null;
    $new_staff_records = [];
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>របាយការណ៍វត្តមានបុគ្គលិក - NR3</title>
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2/Logo-Van-Van-1.png">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        /* CSS ទុកដូចកូដចាស់របស់អ្នកទាំងអស់ */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal.show { opacity: 1; }
        @keyframes slideDown { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-content { background-color: #ffffff; margin: auto; border: none; border-radius: 12px; width: 90%; max-width: 700px; box-shadow: 0 5px 20px rgba(0,0,0,0.2); animation: slideDown 0.4s ease-out; overflow: hidden; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 25px; width: 100%; background-color: #f7f9fc; border-bottom: 1px solid #e9ecef; }
        .modal-header h2 { margin: 0; font-size: 22px; color: #333; }
        .modal-close-btn { font-size: 28px; font-weight: bold; color: #888; cursor: pointer; background: none; border: none; padding: 0; }
        .modal-body { padding: 25px; max-height: 65vh; overflow-y: auto; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #555; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ced4da; border-radius: 6px; box-sizing: border-box; font-family: 'Noto Sans Khmer', sans-serif; font-size: 15px; }
        .pending-section { margin-top: 25px; border-top: 1px solid #e9ecef; padding-top: 20px; }
        #pendingList { list-style-type: none; padding: 0; max-height: 200px; overflow-y: auto; }
        #pendingList li { background: #f8f9fa; border: 1px solid #e9ecef; padding: 12px 15px; margin-bottom: 8px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; }
        #pendingList li .remove-pending { background: #ff4d4f; color: white; border: none; border-radius: 4px; padding: 2px 8px; cursor: pointer; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 18px 25px; background-color: #f7f9fc; border-top: 1px solid #e9ecef; }
        .modal .btn { padding: 10px 22px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 15px; }
        .modal .btn-primary { background-color: #0d6efd; color: white; }
        .modal .btn-secondary { background-color: #6c757d; color: white; }
        
        body { font-family: 'Noto Sans Khmer', sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2 { text-align: center; color: #333; }
        .text-h1 { color: #05165e; font-family:Khmer OS Muol Light; font-size:20px; }
        .text-h2 { color: black; font-family:Kh KoulenL; font-size:18px; }
        .text-h3 { color:black; font-family:Kh KoulenL; font-size:18px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: center; vertical-align: middle; }
        td.editable { cursor: pointer; background-color: #f9f9f9; }
        td.editable:hover { background-color: #e8f4ff; }
        td.editing { padding: 0; }
        td.editing input, td.editing textarea { width: 100%; height: 100%; border: 2px solid #007bff; box-sizing: border-box; text-align: center; padding: 8px; font-family: inherit; font-size: inherit;}
        thead th { background-color: #05165e; color: white; }
        tfoot th, tfoot td { background-color: #e9ecef; font-weight: bold; }
        .message { text-align: center; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; }
        .success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; }
        .top-controls { display: flex; justify-content: center; align-items: center; gap: 15px; background-color: #f7f9fc; padding: 15px 20px; margin-bottom: 25px; border-radius: 10px; border: 1px solid #e9ecef; }
        #datePicker { padding: 8px 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 15px; cursor: pointer; }
        button, .action-btn { background-color: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; font-family: 'Noto Sans Khmer', sans-serif; font-size: 15px;}
        .delete-btn { background-color: #dc3545; padding: 6px 12px; font-size: 14px; }
        #toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; }
        .toast { padding: 15px 20px; margin-top: 10px; border-radius: 5px; color: white; font-size: 16px; opacity: 0; transform: translateY(20px); transition: all 0.3s ease-in-out; }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast-success { background-color: #28a745; }
        .toast-error { background-color: #dc3545; }
        .table-footer-actions { text-align: center; margin-top: -10px; margin-bottom: 20px; }
        .add-row-button-footer { background-color: #0d6efd; color: white; border: none; border-radius: 6px; padding: 8px 16px; font-size: 15px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; margin: 0; }
        .screenshot-btn { background-color: #fd7e14; padding: 9px 15px; }
        .hide-for-screenshot .actions-column { display: none; }
        #spinner { border: 8px solid #f3f3f3; border-top: 8px solid #3498db; border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .dateofday { text-align: center; color: #05165e; font-family: 'Kh Battambang', sans-serif; font-size: 16px; margin-top: 10px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="top-controls">
            <form id="dateFilterForm" method="GET" action="">
                <label for="datePicker" style="font-weight: bold;">មើលតាមថ្ងៃ៖ </label>
                <input type="date" id="datePicker" name="date" value="<?= htmlspecialchars($selected_date) ?>">
            </form>
            <button type="button" class="screenshot-btn" id="screenshotBtn" title="ថតរូបតារាង">
                <i class="fas fa-camera"></i> ថតរូបតារាង
            </button>
        </div>
        
        <?php if ($message): ?><div class="message <?= strpos($message, 'បរាជ័យ') !== false || strpos($message, 'កំហុស') !== false ? 'error' : 'success' ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>

        <div id="capture-area">
            <h1 class="text-h1">របាយការណ៍វត្តមានបុគ្គលិក - NR3</h1>
            
            <?php
                function toKhmerNumber($number) {
                    $khmerDigits = ['០','១','២','៣','៤','៥','៦','៧','៨','៩'];
                    return str_replace(range(0,9), $khmerDigits, $number);
                }
                try {
                    $date_obj = new DateTime($selected_date);
                    $khmer_days = ['អាទិត្យ', 'ច័ន្ទ', 'អង្គារ', 'ពុធ', 'ព្រហស្បតិ៍', 'សុក្រ', 'សៅរ៍'];
                    $khmer_months = ['មករា', 'កក្កដា', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា', 'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ'];
                    $weekday_index = (int)$date_obj->format('w');
                    $day = toKhmerNumber($date_obj->format('d'));
                    $month_index = (int)$date_obj->format('m') - 1;
                    $year = toKhmerNumber($date_obj->format('Y'));
                    $khmer_date_string = " ថ្ងៃ ". $khmer_days[$weekday_index] . " ទី".$day . " ខែ".$khmer_months[$month_index] . " ឆ្នាំ " . $year;
                    echo '<h2 class="dateofday">' . htmlspecialchars($khmer_date_string) . '</h2>';
                } catch(Exception $e) {}
            ?>

            <h2 class="text-h2">ចំនួនបុគ្គលិកតាមផ្នែក</h2>
            <table id="attendance-table">
                <thead>
                    <tr>
                        <th>ព័ត៌មាន</th>
                        <?php foreach ($department_configs as $config): ?>
                            <th><?= htmlspecialchars($config['label']) ?></th>
                        <?php endforeach; ?>
                        <th>សរុបរួម</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($daily_record): $column_totals = array_fill_keys(array_keys($department_configs), 0); ?>
                    <tr>
                        <th class="gender-header">ស្រី</th>
                        <?php 
                        $row_total = 0; 
                        foreach ($department_configs as $key => $config): 
                            $val = $daily_record["{$key}_female"] ?? 0; 
                            if ($key !== 'store') { $row_total += $val; }
                            $column_totals[$key] += $val; 
                        ?>
                        <td class="editable" data-column="<?= $key ?>_female"><?= $val ?></td>
                        <?php endforeach; ?>
                        <td id="female_row_total"><?= $row_total ?></td>
                    </tr>
                    <tr>
                        <th class="gender-header">ប្រុស</th>
                        <?php 
                        $row_total = 0; 
                        foreach ($department_configs as $key => $config): 
                            $val = $daily_record["{$key}_male"] ?? 0;
                            if ($key !== 'store') { $row_total += $val; }
                            $column_totals[$key] += $val; 
                        ?>
                        <td class="editable" data-column="<?= $key ?>_male"><?= $val ?></td>
                        <?php endforeach; ?>
                        <td id="male_row_total"><?= $row_total ?></td>
                    </tr>
                <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th>សរុបរួមតាមផ្នែក</th>
                        <?php 
                        $grand_total = 0; 
                        foreach ($column_totals as $key => $total): 
                            if ($key !== 'store') { $grand_total += $total; }
                        ?>
                        <td data-total-column="<?= $key ?>"><?= $total ?></td>
                        <?php endforeach; ?>
                        <td id="grand_total"><?= $grand_total ?></td>
                    </tr>
                </tfoot>
            </table>

            <h2 class="text-h3">បុគ្គលិកសុំច្បាប់, ដេអូស, ប្តូរដេអូស និងចូលថ្មី</h2>
            <table id="new-staff-table">
                <thead><tr><th>ល.រ</th><th>ឈ្មោះ</th><th>តួនាទី</th><th>អធិប្បាយ</th><th>ថ្ងៃរាយការណ៍</th><th class="actions-column">សកម្មភាព</th></tr></thead>
                <tbody>
                <?php if (!empty($new_staff_records)): foreach ($new_staff_records as $row): ?>
                    <tr data-id="<?= $row['id'] ?>">
                        <td class="editable" data-column="number"><?= htmlspecialchars($row['number']) ?></td>
                        <td class="editable" data-column="name"><?= htmlspecialchars($row['name']) ?></td>
                        <td class="editable" data-column="role"><?= htmlspecialchars($row['role']) ?></td>
                        <td class="editable" data-column="note" style="white-space: pre-wrap;"><?= htmlspecialchars($row['note']) ?></td>
                        <td class="editable" data-column="reports_date"><?= htmlspecialchars($row['reports_date']) ?></td>
                        <td class="actions-column">
                            <form method="POST" class="delete-form">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                <button type="submit" name="delete_new_staff" class="delete-btn">លុប</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr class="no-data-row"><td colspan="6">មិនមានទិន្នន័យសម្រាប់ថ្ងៃនេះទេ។</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div> 

        <div class="table-footer-actions">
            <button type="button" class="add-row-button-footer" onclick="addNewStaffRow()">
                <i class="fas fa-plus"></i> បន្ថែមជួរដេកថ្មី
            </button>
            <button type="button" class="add-row-button-footer" onclick="openNewStaffModal()">
                <i class="fas fa-users"></i> បញ្ចូលច្រើន
            </button>
        </div>
    </div>

    <!-- Modals & Toasts (Same as your code) -->
    <div id="toast-container"></div>
    <div id="newStaffModal" class="modal">
         <div class="modal-content">
            <div class="modal-header">
                <h2>បន្ថែមបុគ្គលិកថ្មី</h2>
                <button class="modal-close-btn" onclick="closeModal('newStaffModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="newStaffForm" onsubmit="event.preventDefault(); addRecordToList();">
                    <div class="form-group"><label>ល.រ</label><input type="text" id="staffNumber"></div>
                    <div class="form-group"><label>ឈ្មោះ</label><input type="text" id="staffName" required></div>
                    <div class="form-group"><label>តួនាទី</label><input type="text" id="staffRole"></div>
                    <div class="form-group"><label>អធិប្បាយ</label><textarea id="staffNote" rows="3"></textarea></div>
                    <button type="submit" class="btn btn-primary">បន្ថែមទៅក្នុងបញ្ជី</button>
                </form>
                <div class="pending-section">
                    <h4>បញ្ជីរង់ចាំរក្សាទុក (<span id="pendingCount">0</span>)</h4>
                    <ul id="pendingList"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('newStaffModal')">បោះបង់</button>
                <button type="button" class="btn btn-primary" onclick="saveAllPendingStaff()">រក្សាទុកទាំងអស់</button>
            </div>
        </div>
    </div>

    <div id="screenshotModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h2>រូបភាពតារាងទិន្នន័យ</h2><button class="modal-close-btn" onclick="closeModal('screenshotModal')">&times;</button></div>
            <div class="modal-body" style="text-align: center;">
                <div id="spinner-container"><div id="spinner"></div><p>កំពុងបង្កើតរូបភាព...</p></div>
                <img id="screenshotPreview" src="" alt="Preview" style="display: none; width:100%;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('screenshotModal')">បិទ</button>
                <button type="button" class="btn btn-primary" id="copyImageBtn">ចម្លងរូបភាព</button>
            </div>
        </div>
    </div>

    <form id="saveNewStaffForm" method="POST" style="display:none;"><input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"><input type="hidden" name="save_new_staff" id="new_staff_data"></form>

<script>
const csrfToken = '<?= $_SESSION['csrf_token'] ?>';
const selectedDate = '<?= htmlspecialchars($selected_date) ?>';
let activeInput = null;

document.getElementById('datePicker').addEventListener('change', function() {
    document.getElementById('dateFilterForm').submit();
});

function updateAttendanceTotals() {
    const table = document.getElementById('attendance-table');
    const departmentKeys = <?= json_encode(array_keys($department_configs)) ?>;
    let columnTotals = {};
    departmentKeys.forEach(key => columnTotals[key] = 0);
    
    let femaleRowTotal = 0;
    table.querySelectorAll('tbody tr:first-child td.editable').forEach(cell => {
        const value = parseInt(cell.textContent) || 0;
        const columnKey = cell.dataset.column.replace('_female', '');
        columnTotals[columnKey] += value;
        if (columnKey !== 'store') femaleRowTotal += value;
    });
    document.getElementById('female_row_total').textContent = femaleRowTotal;

    let maleRowTotal = 0;
    table.querySelectorAll('tbody tr:nth-child(2) td.editable').forEach(cell => {
        const value = parseInt(cell.textContent) || 0;
        const columnKey = cell.dataset.column.replace('_male', '');
        columnTotals[columnKey] += value;
        if (columnKey !== 'store') maleRowTotal += value;
    });
    document.getElementById('male_row_total').textContent = maleRowTotal;

    let grandTotal = 0;
    departmentKeys.forEach(key => {
        const footerCell = table.querySelector(`tfoot td[data-total-column="${key}"]`);
        if (footerCell) footerCell.textContent = columnTotals[key];
        if (key !== 'store') grandTotal += columnTotals[key];
    });
    document.getElementById('grand_total').textContent = grandTotal;
}

async function saveData(action, data) {
    const formData = new FormData();
    formData.append('action', action);
    formData.append('csrf_token', csrfToken);
    for (const key in data) { formData.append(key, data[key]); }
    const response = await fetch(window.location.pathname + "?date=" + selectedDate, { 
        method: 'POST', 
        headers: { 'X-Requested-With': 'XMLHttpRequest' }, 
        body: formData 
    });
    return await response.json();
}

// ... មុខងារ JS ផ្សេងទៀត (makeCellEditable, addNewStaffRow, etc.) ទុកដដែលដូចកូដដើមរបស់អ្នក ...
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => container.removeChild(toast), 300);
    }, 3000);
}

async function addNewStaffRow() {
    const result = await saveData('create_new_staff_row', { date: selectedDate });
    if (result && result.success) {
        location.reload(); // ងាយស្រួលបំផុតគឺ reload ដើម្បីឱ្យ Table render ត្រឹមត្រូវ
    }
}

function makeCellEditable(cell) {
    if (cell.classList.contains('editing')) return;
    const originalValue = cell.innerHTML.replace(/<br\s*[\/]?>/gi, "\n");
    const column = cell.dataset.column;
    cell.classList.add('editing');
    let input = document.createElement(column === 'note' ? 'textarea' : 'input');
    if (column !== 'note') input.type = cell.closest('#attendance-table') ? 'number' : 'text';
    input.value = originalValue;
    cell.innerHTML = '';
    cell.appendChild(input);
    input.focus();

    input.onblur = async () => {
        const newValue = input.value;
        if (newValue !== originalValue) {
            let res;
            if (cell.closest('#attendance-table')) {
                res = await saveData('update_single_attendance', { column, value: newValue, date: selectedDate });
            } else {
                res = await saveData('update_new_staff_inline', { column, value: newValue, id: cell.parentElement.dataset.id });
            }
            cell.innerHTML = res.success ? newValue.replace(/\n/g, '<br>') : originalValue;
            if(res.success && cell.closest('#attendance-table')) updateAttendanceTotals();
            if(res.success) showToast(res.message);
        } else {
            cell.innerHTML = originalValue.replace(/\n/g, '<br>');
        }
        cell.classList.remove('editing');
    };
}

document.addEventListener('click', e => {
    const cell = e.target.closest('td.editable');
    if (cell) makeCellEditable(cell);
});

// Modal Logic
let pendingRecords = [];
function openNewStaffModal() { pendingRecords = []; renderPendingList(); document.getElementById('newStaffModal').style.display = 'flex'; setTimeout(() => document.getElementById('newStaffModal').classList.add('show'), 10); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); setTimeout(() => document.getElementById(id).style.display = 'none', 300); }
function addRecordToList() {
    pendingRecords.push({
        number: document.getElementById('staffNumber').value,
        name: document.getElementById('staffName').value,
        role: document.getElementById('staffRole').value,
        note: document.getElementById('staffNote').value,
        reports_date: selectedDate
    });
    renderPendingList();
    document.getElementById('newStaffForm').reset();
}
function renderPendingList() {
    const list = document.getElementById('pendingList');
    list.innerHTML = '';
    document.getElementById('pendingCount').textContent = pendingRecords.length;
    pendingRecords.forEach((r, i) => {
        list.innerHTML += `<li>${r.name} <button class="remove-pending" onclick="pendingRecords.splice(${i},1);renderPendingList();">&times;</button></li>`;
    });
}
function saveAllPendingStaff() {
    document.getElementById('new_staff_data').value = JSON.stringify(pendingRecords);
    document.getElementById('saveNewStaffForm').submit();
}

// Screenshot Logic
document.getElementById('screenshotBtn').onclick = async () => {
    const area = document.getElementById('capture-area');
    area.classList.add('hide-for-screenshot');
    document.getElementById('screenshotModal').style.display = 'flex';
    document.getElementById('screenshotModal').classList.add('show');
    const canvas = await html2canvas(area, { scale: 2 });
    const img = document.getElementById('screenshotPreview');
    img.src = canvas.toDataURL();
    img.style.display = 'block';
    document.getElementById('spinner-container').style.display = 'none';
    area.classList.remove('hide-for-screenshot');
    
    document.getElementById('copyImageBtn').onclick = () => {
        canvas.toBlob(blob => {
            navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
            showToast('បានចម្លងរូបភាព!');
        });
    };
};
</script>
</body>
</html>