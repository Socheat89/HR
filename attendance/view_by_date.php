<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php';

// បង្កើត CSRF token ប្រសិនបើមិនទាន់មាន
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// =============================================================================
// ការកំណត់រចនាសម្ព័ន្ធ (CONFIGURATION)
// =============================================================================
$table_configs = [
    'office_staff' => [
        'label' => 'បុគ្គលិកការិយាល័យកណ្តាល',
        'db_table' => 'office_staff',
        'type' => 'office',
        'columns' => ['it', 'admin', 'account', 'sale', 'staff_318', 'reports_date']
    ],
    'sk_store_staff' => [
        'label' => 'បុគ្គលិកហាង អេស ខេ',
        'db_table' => 'sk_store_staff',
        'type' => 'store',
        'columns' => ['gm', 'manager_store', 'manager_stock', 'staff_skks2', 'staff_nr3', 'reports_date']
    ],
    'warehouse_staff' => [
        'label' => 'បុគ្គលិកឃ្លាំង',
        'db_table' => 'warehouse_staff',
        'type' => 'warehouse',
        'columns' => ['ckd', 'psp', 'reports_date']
    ],
    'new_staff' => [
        'label' => 'បុគ្គលិកសុំច្បាប់, ដេអូស, ប្តូរដេអូស និងចូលថ្មី',
        'db_table' => 'new_staff',
        'type' => 'new_staff',
        'columns' => ['number', 'name', 'role', 'note', 'reports_date']
    ]
];

$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

$selected_date = $_GET['date'] ?? date('Y-m-d');
$all_records = [];
$all_totals = [];
$overall_total = 0;

/**
 * មុខងារសម្រាប់លុបកំណត់ត្រា
 * @return string សារជោគជ័យ ឬបរាជ័យ
 */
function handleDeletePost(PDO $pdo, string $db_table): string {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) return "បរាជ័យ: មិនមាន ID សម្រាប់លុប។";
    $stmt = $pdo->prepare("DELETE FROM {$db_table} WHERE id = ?");
    $success = $stmt->execute([$id]);
    return $success ? "កំណត់ត្រាត្រូវបានលុបជោគជ័យ!" : "បរាជ័យក្នុងការលុបកំណត់ត្រា!";
}

function tableHasTotal(array $config): bool {
    return in_array($config['type'], ['office', 'store', 'warehouse'], true);
}

function calculateStaffTotal(array $record, array $config): int {
    $total = 0;
    foreach ($config['columns'] as $column) {
        if ($column === 'reports_date') continue;
        $total += (int)($record[$column] ?? 0);
    }
    return $total;
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) {
        throw new InvalidArgumentException('Invalid table or column name.');
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);

    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sk_store_staff (
            id INT AUTO_INCREMENT PRIMARY KEY,
            gm INT NOT NULL DEFAULT 0,
            manager_store INT NOT NULL DEFAULT 0,
            manager_stock INT NOT NULL DEFAULT 0,
            staff_skks2 INT NOT NULL DEFAULT 0,
            staff_nr3 INT NOT NULL DEFAULT 0,
            total INT NOT NULL DEFAULT 0,
            reports_date DATE NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    ensureColumn($pdo, 'sk_store_staff', 'staff_skks2', 'INT NOT NULL DEFAULT 0 AFTER `manager_stock`');
    ensureColumn($pdo, 'sk_store_staff', 'staff_nr3', 'INT NOT NULL DEFAULT 0 AFTER `staff_skks2`');

    // =========================================================================
    // ការគ្រប់គ្រងការបញ្ជូនទិន្នន័យ (CRUD HANDLING)
    // =========================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // --- AJAX request handler for inline editing ---
        if (isset($_POST['action']) && $_POST['action'] == 'update_inline') {
            header('Content-Type: application/json');

            if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF Token.']);
                exit();
            }

            $id = (int)($_POST['id'] ?? 0);
            $key = $_POST['key'] ?? null;
            $column = $_POST['column'] ?? null;
            $value = $_POST['value'] ?? '';

            if (!$id || !$key || !$column || !isset($table_configs[$key])) {
                echo json_encode(['success' => false, 'message' => 'ទិន្នន័យមិនត្រឹមត្រូវ']);
                exit();
            }

            $config = $table_configs[$key];
            if (!in_array($column, $config['columns'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid column specified.']);
                exit();
            }

            try {
                if (tableHasTotal($config)) {
                    $stmt = $pdo->prepare("SELECT * FROM {$config['db_table']} WHERE id = ?");
                    $stmt->execute([$id]);
                    $record = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($record) {
                        $record[$column] = $value;
                        $total = calculateStaffTotal($record, $config);
                        $sql = "UPDATE {$config['db_table']} SET {$column} = :value, total = :total WHERE id = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(['value' => $value, 'total' => $total, 'id' => $id]);
                        echo json_encode(['success' => true, 'message' => 'រក្សាទុកទិន្នន័យ!', 'new_total' => $total]);
                    }
                } else {
                    $sql = "UPDATE {$config['db_table']} SET {$column} = :value WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['value' => $value, 'id' => $id]);
                    echo json_encode(['success' => true, 'message' => 'រក្សាទុកสำเร็จ!']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            exit();
        }

        // --- Keep existing handlers for modal forms ---
        $crud_message = '';
        if (isset($_POST['save_all_pending'])) {
            $records_to_save = json_decode($_POST['save_all_pending'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($records_to_save)) {
                $pdo->beginTransaction();
                try {
                    foreach ($records_to_save as $record) {
                        $key = $record['_key'] ?? null;
                        if (!$key || !isset($table_configs[$key])) continue;

                        $config = $table_configs[$key];
                        $db_table = $config['db_table'];

                        switch ($config['type']) {
                            case 'office':
                                $it = (int)($record['it'] ?? 0); $admin = (int)($record['admin'] ?? 0);
                                $account = (int)($record['account'] ?? 0); $sale = (int)($record['sale'] ?? 0);
                                $staff_318 = (int)($record['staff_318'] ?? 0);
                                $staff_skcm = 0;
                                $staff_nr3 = 0;
                                $total = $it + $admin + $account + $sale + $staff_318;
                                $stmt = $pdo->prepare("INSERT INTO {$db_table} (it, admin, account, sale, staff_318, staff_skcm, staff_nr3, total, reports_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt->execute([$it, $admin, $account, $sale, $staff_318, $staff_skcm, $staff_nr3, $total, $record['reports_date']]);
                                break;

                            case 'store':
                                $gm = (int)($record['gm'] ?? 0);
                                $manager_store = (int)($record['manager_store'] ?? 0);
                                $manager_stock = (int)($record['manager_stock'] ?? 0);
                                $staff_skks2 = (int)($record['staff_skks2'] ?? 0);
                                $staff_nr3 = (int)($record['staff_nr3'] ?? 0);
                                $total = $gm + $manager_store + $manager_stock + $staff_skks2 + $staff_nr3;
                                $stmt = $pdo->prepare("INSERT INTO {$db_table} (gm, manager_store, manager_stock, staff_skks2, staff_nr3, total, reports_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
                                $stmt->execute([$gm, $manager_store, $manager_stock, $staff_skks2, $staff_nr3, $total, $record['reports_date']]);
                                break;
                            
                            case 'warehouse':
                                $ckd = (int)($record['ckd'] ?? 0);
                                $psp = (int)($record['psp'] ?? 0);
                                $total = $ckd + $psp;
                                $stmt = $pdo->prepare("INSERT INTO {$db_table} (ckd, psp, total, reports_date) VALUES (?, ?, ?, ?)");
                                $stmt->execute([$ckd, $psp, $total, $record['reports_date']]);
                                break;

                            case 'new_staff':
                                $stmt = $pdo->prepare("INSERT INTO {$db_table} (number, name, role, note, reports_date) VALUES (?, ?, ?, ?, ?)");
                                $stmt->execute([$record['number'], $record['name'], $record['role'], $record['note'], $record['reports_date']]);
                                break;
                        }
                    }
                    $pdo->commit();
                    $crud_message = "បានរក្សាទុកកំណត់ត្រាថ្មីដោយជោគជ័យ។";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $crud_message = "បរាជ័យក្នុងការរក្សាទុកទិន្នន័យ! " . $e->getMessage();
                }
            } else {
                $crud_message = "បរាជ័យ: ទិន្នន័យដែលបានបញ្ជូនមកមិនត្រឹមត្រូវ។";
            }
        }
        foreach ($table_configs as $key => $config) {
            if (isset($_POST[$key . '_delete'])) {
                $crud_message .= handleDeletePost($pdo, $config['db_table']);
            }
        }

        $_SESSION['message'] = $crud_message;
        header("Location: " . $_SERVER['PHP_SELF'] . "?date=" . urlencode($selected_date));
        exit();
    }

    // =========================================================================
    // ការដึงទិន្នន័យ និងគណនាសរុប (Data Fetching and Totals Calculation)
    // =========================================================================
    foreach ($table_configs as $key => $config) {
        $stmt = $pdo->prepare("SELECT * FROM {$config['db_table']} WHERE reports_date = ? ORDER BY id ASC");
        $stmt->execute([$selected_date]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $all_records[$key] = $records ?: [];
        
        if (tableHasTotal($config) && !empty($records)) {
            $section_total = 0;
            foreach ($records as $record) {
                $section_total += calculateStaffTotal($record, $config);
            }
            $all_totals[$key] = $section_total;
        } else {
            $all_totals[$key] = count($records);
        }
    }
    $overall_total = array_sum($all_totals);

} catch (PDOException $e) {
    $message = "កំហុសក្នុងការទាក់ទងទិន្នន័យ: " . $e->getMessage();
}

// =============================================================================
// មុខងារសម្រាប់បង្ហាញតារាង (Render Table Function)
// =============================================================================
function renderTable(string $key, array $config, array $records) {
    $label = htmlspecialchars($config['label']);
    $type = $config['type'];

    echo "<table><thead>";

    if ($type === 'office') {
        echo "<tr><th colspan='8' class='section-header text-h2'>{$label}</th></tr>";
        echo "<tr><th>IT</th><th>Admin</th><th>Account</th><th>Sale</th><th>318</th><th>សរុប</th><th>ថ្ងៃរាយការណ៍</th><th class='actions-column'>សកម្មភាព</th></tr></thead><tbody>";
        foreach ($records as $record) {
            $total = calculateStaffTotal($record, $config);
            echo "<tr data-id='{$record['id']}' data-key='{$key}'>
                        <td class='editable' data-column='it'>" . htmlspecialchars($record['it'] ?? '0') . "</td>
                        <td class='editable' data-column='admin'>" . htmlspecialchars($record['admin'] ?? '0') . "</td>
                        <td class='editable' data-column='account'>" . htmlspecialchars($record['account'] ?? '0') . "</td>
                        <td class='editable' data-column='sale'>" . htmlspecialchars($record['sale'] ?? '0') . "</td>
                        <td class='editable' data-column='staff_318'>" . htmlspecialchars($record['staff_318'] ?? '0') . "</td>
                        <td data-column='total'><b>" . htmlspecialchars((string)$total) . " នាក់</b></td>
                        <td class='editable' data-column='reports_date'>" . htmlspecialchars($record['reports_date'] ?? '') . "</td>
                        <td class='actions-column'><button class='action-btn delete-btn' onclick=\"deleteRecord({$record['id']}, '{$key}_delete', 'តើអ្នកប្រាកដជាចង់លុប?')\">លុប</button></td>
                    </tr>";
        }
        if (empty($records)) echo "<tr><td colspan='8'>មិនមានទិន្នន័យសម្រាប់ថ្ងៃនេះទេ។</td></tr>";

    } elseif ($type === 'store') {
        echo "<tr><th colspan='8' class='section-header text-h2'>{$label}</th></tr>";
        echo "<tr><th>GM</th><th>Manager store</th><th>Manager stock</th><th>SKKS2</th><th>NR3</th><th>សរុប</th><th>ថ្ងៃរាយការណ៍</th><th class='actions-column'>សកម្មភាព</th></tr></thead><tbody>";
        foreach ($records as $record) {
            $total = calculateStaffTotal($record, $config);
            echo "<tr data-id='{$record['id']}' data-key='{$key}'>
                        <td class='editable' data-column='gm'>" . htmlspecialchars($record['gm'] ?? '0') . "</td>
                        <td class='editable' data-column='manager_store'>" . htmlspecialchars($record['manager_store'] ?? '0') . "</td>
                        <td class='editable' data-column='manager_stock'>" . htmlspecialchars($record['manager_stock'] ?? '0') . "</td>
                        <td class='editable' data-column='staff_skks2'>" . htmlspecialchars($record['staff_skks2'] ?? '0') . "</td>
                        <td class='editable' data-column='staff_nr3'>" . htmlspecialchars($record['staff_nr3'] ?? '0') . "</td>
                        <td data-column='total'><b>" . htmlspecialchars((string)$total) . " នាក់</b></td>
                        <td class='editable' data-column='reports_date'>" . htmlspecialchars($record['reports_date'] ?? '') . "</td>
                        <td class='actions-column'><button class='action-btn delete-btn' onclick=\"deleteRecord({$record['id']}, '{$key}_delete', 'តើអ្នកប្រាកដជាចង់លុប?')\">លុប</button></td>
                    </tr>";
        }
        if (empty($records)) echo "<tr><td colspan='8'>មិនមានទិន្នន័យសម្រាប់ថ្ងៃនេះទេ។</td></tr>";

    } elseif ($type === 'warehouse') {
        echo "<tr><th colspan='5' class='section-header text-h2'>{$label}</th></tr>";
        echo "<tr><th>CKD</th><th>PSP</th><th>សរុប</th><th>ថ្ងៃរាយការណ៍</th><th class='actions-column'>សកម្មភាព</th></tr></thead><tbody>";
        foreach ($records as $record) {
            $total = calculateStaffTotal($record, $config);
            echo "<tr data-id='{$record['id']}' data-key='{$key}'>
                        <td class='editable' data-column='ckd'>" . htmlspecialchars($record['ckd'] ?? '') . "</td>
                        <td class='editable' data-column='psp'>" . htmlspecialchars($record['psp'] ?? '') . "</td>
                        <td data-column='total'><b>" . htmlspecialchars((string)$total) . " នាក់</b></td>
                        <td class='editable' data-column='reports_date'>" . htmlspecialchars($record['reports_date'] ?? '') . "</td>
                        <td class='actions-column'><button class='action-btn delete-btn' onclick=\"deleteRecord({$record['id']}, '{$key}_delete', 'តើអ្នកប្រាកដជាចង់លុប?')\">លុប</button></td>
                    </tr>";
        }
        if (empty($records)) echo "<tr><td colspan='5'>មិនមានទិន្នន័យសម្រាប់ថ្ងៃនេះទេ។</td></tr>";
    
    } elseif ($type === 'new_staff') {
        echo "<tr><th colspan='6' class='section-header text-h2'>{$label}</th></tr>";
        echo "<tr><th>ល.រ</th><th>ឈ្មោះ</th><th>តួនាទី</th><th>អធិប្បាយ</th><th>ថ្ងៃរាយការណ៍</th><th class='actions-column'>សកម្មភាព</th></tr></thead><tbody>";
        foreach ($records as $row) {
            echo "<tr data-id='{$row['id']}' data-key='{$key}'>
                        <td class='editable' data-column='number'>" . htmlspecialchars($row['number'] ?? '') . "</td>
                        <td class='editable' data-column='name'>" . htmlspecialchars($row['name'] ?? '') . "</td>
                        <td class='editable' data-column='role'>" . htmlspecialchars($row['role'] ?? '') . "</td>
                        <td class='editable' data-column='note' style='white-space: pre-wrap;'>" . htmlspecialchars($row['note'] ?? '') . "</td>
                        <td class='editable' data-column='reports_date'>" . htmlspecialchars($row['reports_date'] ?? '') . "</td>
                        <td class='actions-column'><button class='action-btn delete-btn' onclick=\"deleteRecord({$row['id']}, '{$key}_delete', 'តើអ្នកប្រាកដជាចង់លុប?')\">លុប</button></td>
                    </tr>";
        }
        if (empty($records)) echo "<tr><td colspan='6'>មិនមានទិន្នន័យសម្រាប់ថ្ងៃនេះទេ។</td></tr>";
    }

    echo "</tbody></table>";
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>របាយការណ៍បុគ្គលិកប្រចាំថ្ងៃ</title>
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2/Logo-Van-Van-1.png">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        /* --- GENERAL STYLES --- */
        body { font-family: 'Noto Sans Khmer', sans-serif; margin: 0; padding: 20px; background-color: #f8f9fa; color: #343a40; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        h1 { text-align: center; color: #05165e; margin-bottom: 20px;}
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        th, td { border: 1px solid #dee2e6; padding: 12px; text-align: center; vertical-align: middle; }
        th { background-color: #05165e; color: white; }
        .section-header { background-color: #e6cd14; color: #212529; font-weight: bold; font-size: 1.1em; }
        .overall-total { background-color: #e9ecef; font-weight: bold; font-size: 1.1em; }
        button, .action-btn { background-color: #0d6efd; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; font-family: 'Noto Sans Khmer', sans-serif; font-size: 15px; transition: background-color 0.2s; }
        button:hover, .action-btn:hover { background-color: #0b5ed7; }
        .insert-btn { background-color: #198754; }
        .insert-btn:hover { background-color: #157347; }
        .delete-btn { background-color: #dc3545; padding: 6px 12px; }
        .delete-btn:hover { background-color: #bb2d3b; }
        .message { text-align: center; padding: 12px; margin-bottom: 20px; border-radius: 6px; }
        .error { color: #842029; background-color: #f8d7da; border: 1px solid #f5c2c7; }
        .success { color: #0f5132; background-color: #d1e7dd; border: 1px solid #badbcc; }
        
        /* --- Date Form Controls --- */
        .date-form-controls { display: flex; justify-content: center; align-items: center; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; }
        .date-filter-form { display: flex; align-items: center; gap: 10px; }
        .date-form-controls label { font-weight: 600; color: #495057;}
        .date-form-controls input[type="date"] { padding: 9px 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 15px; }

        /* --- Inline Editing --- */
        td.editable { cursor: pointer; background-color: #fcfcfc; }
        td.editable:hover { background-color: #e9f5ff; }
        td.editing { padding: 0; }
        td.editing input, td.editing textarea { width: 100%; height: 100%; border: 2px solid #0d6efd; box-sizing: border-box; text-align: center; padding: 10px; font-family: inherit; font-size: inherit; border-radius: 0;}
        td.editing input[type="date"] { padding: 9px; }
        td.editing textarea { text-align: left; resize: vertical; }

        /* --- Toast Notifications --- */
        #toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; }
        .toast { padding: 15px 20px; margin-top: 10px; border-radius: 6px; color: white; font-size: 16px; opacity: 0; transform: translateY(20px); transition: all 0.3s ease-in-out; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast-success { background-color: #198754; }
        .toast-error { background-color: #dc3545; }

        /* --- Redesigned Modal Styles --- */
        @keyframes slideDown { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal.show { display: flex; opacity: 1; }
        .modal-content { background-color: #ffffff; margin: auto; border: none; border-radius: 12px; width: 90%; max-width: 650px; box-shadow: 0 5px 20px rgba(0,0,0,0.2); animation: slideDown 0.4s ease-out; overflow: hidden; display: flex; flex-direction: column; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 25px; background-color: #f7f9fc; border-bottom: 1px solid #e9ecef; }
        .modal-header h2 { margin: 0; font-size: 22px; color: #333; }
        .modal-close-btn { font-size: 28px; font-weight: bold; color: #888; cursor: pointer; background: none; border: none; padding: 0; line-height: 1; }
        .modal-close-btn:hover { color: #333; }
        .modal-body { padding: 25px; max-height: 65vh; overflow-y: auto; }
        .modal-body label { display: block; margin-top: 15px; margin-bottom: 6px; font-weight: 600; text-align: left; }
        .modal-body input[type="date"], .modal-body input[type="number"], .modal-body input[type="text"], .modal-body select, .modal-body textarea {
            padding: 12px; border: 1px solid #ced4da; border-radius: 6px; font-family: 'Noto Sans Khmer', sans-serif; font-size: 15px; width: 100%; box-sizing: border-box;
        }
        .modal-body select { font-size: 1.1em; }
        .modal-body input:focus, .modal-body select:focus, .modal-body textarea:focus { border-color: #86b7fe; outline: 0; box-shadow: 0 0 0 0.25rem rgba(13,110,253,.25); }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 18px 25px; background-color: #f7f9fc; border-top: 1px solid #e9ecef; }
        #pendingListContainer { margin-top: 25px; border-top: 1px solid #e9ecef; padding-top: 20px; }
        #pendingList { list-style-type: none; padding: 0; }
        #pendingList li { background: #f8f9fa; border: 1px solid #e9ecef; padding: 12px 15px; margin-bottom: 8px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; }
        .btn-secondary { background-color: #6c757d; }
        .btn-secondary:hover { background-color: #5c636a; }
        .btn-primary { background-color: #0d6efd; }
        .btn-primary:hover { background-color: #0b5ed7; }

        /* --- CSS សម្រាប់ Screenshot --- */
        .screenshot-btn { background-color: #fd7e14; }
        .screenshot-btn:hover { background-color: #e46d0a; }

        #screenshotModal .modal-content { max-width: 95%; width: auto; }
        #screenshotPreview { max-width: 100%; height: auto; border: 1px solid #ddd; }
        #spinner { border: 8px solid #f3f3f3; border-top: 8px solid #3498db; border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

        /* Class សម្រាប់លាក់ elements ពេលថតរូប */
        .hide-for-screenshot .actions-column {
            display: none;
        }
        
        .text-h1{
            color: #05165e;
            font-family:Khmer OS Muol Light;
            font-size:20px;
        }
        .text-h2{
            color: black;
            font-family:Kh KoulenL;
            font-size:18px;
        }
        .text-h3{
            color:black;
            font-family:Kh KoulenL;
            font-size:18px;
        }

        /* --- [បានបន្ថែម] CSS សម្រាប់បង្ហាញថ្ងៃខែឆ្នាំ --- */
               .dateofday { 
            text-align: center; 
            color: #05165e; 
            font-family: 'Kh Battambang', 'Kh Battambang', sans-serif; 
            font-size: 16px; 
            margin-top: 10px; 
            font-weight: bold; 
        }
        
    </style>
</head>
<body>
    <div class="container">
        <div class="date-form-controls">
            <form method="GET" action="" class="date-filter-form" id="dateFilterForm">
                <label for="datePicker">ជ្រើសរើសថ្ងៃ:</label>
                <input type="date" name="date" id="datePicker" value="<?php echo htmlspecialchars($selected_date); ?>">
            </form>
            <button class="action-btn insert-btn" onclick="openInsertModal()">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="vertical-align: -3px; margin-right: 6px;"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/><path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/></svg>
                បញ្ចូលទិន្នន័យថ្មី
            </button>
            <button class="action-btn screenshot-btn" id="screenshotBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-camera-fill" viewBox="0 0 16 16" style="vertical-align: -3px; margin-right: 6px;">
                  <path d="M10.5 8.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0z"/>
                  <path d="M2 4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1.172a2 2 0 0 1-1.414-.586l-.828-.828A2 2 0 0 0 9.172 2H6.828a2 2 0 0 0-1.414.586l-.828-.828A2 2 0 0 1 3.172 4H2zm.5 2a.5.5 0 1 1 0-1 .5.5 0 0 1 0 1z"/>
                </svg>
                ថតរូបតារាង
            </button>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'បរាជ័យ') !== false || strpos($message, 'កំហុស') !== false ? 'error' : 'success'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div id="capture-area">
            <h1 class="text-h1">របាយការណ៍បុគ្គលិកប្រចាំថ្ងៃ</h1>

            <?php
                function toKhmerNumber($number) {
                    $khmerDigits = ['០','១','២','៣','៤','៥','៦','៧','៨','៩'];
                    return str_replace(range(0,9), $khmerDigits, $number);
                }
                try {
                    $date_obj = new DateTime($selected_date);
                    $khmer_days = ['អាទិត្យ', 'ច័ន្ទ', 'អង្គារ', 'ពុធ', 'ព្រហស្បតិ៍', 'សុក្រ', 'សៅរ៍'];
                    $khmer_months = ['មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា', 'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ'];
                    $weekday_index = (int)$date_obj->format('w');
                    $day = toKhmerNumber($date_obj->format('d'));
                    $month_index = (int)$date_obj->format('m') - 1;
                    $year = toKhmerNumber($date_obj->format('Y'));
                    $khmer_date_string = "ថ្ងៃ ". $khmer_days[$weekday_index] . " ទី".$day . " ខែ".$khmer_months[$month_index] . " ឆ្នាំ " . $year;
                    echo '<h2 class="dateofday">' . htmlspecialchars($khmer_date_string) . '</h2>';
                } catch(Exception $e) {
                    // មិនបង្ហាញអ្វីទេបើកាលបរិច្ឆេទមិនត្រឹមត្រូវ
                }
            ?>
            
            <?php
            foreach ($table_configs as $key => $config) {
                 renderTable($key, $config, $all_records[$key] ?? []);
            }
            ?>
            
            <table id="summary-table">
                <thead>
                    <tr><th colspan="<?php echo count($table_configs) + 1; ?>" class="section-header">សរុបរួម</th></tr>
                    <tr>
                        <?php foreach ($table_configs as $key => $config): ?>
                            <th><?php echo htmlspecialchars($config['label']); ?></th>
                        <?php endforeach; ?>
                        <th>សរុបទាំងអស់</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="overall-total">
                        <?php foreach ($all_totals as $key => $total): ?>
                            <td data-summary-key="<?= $key ?>"><?php echo htmlspecialchars($total); ?> នាក់</td>
                        <?php endforeach; ?>
                        <td id="grand-total"><?php echo htmlspecialchars($overall_total); ?> នាក់</td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>

    <div id="toast-container"></div>

    <div id="insertModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>បញ្ចូលទិន្នន័យថ្មី</h2>
                <button class="modal-close-btn" onclick="closeModal('insertModal')">&times;</button>
            </div>
            <div class="modal-body">
                <select id="modal_menu_insert" onchange="generateForm('insert')"></select>
                <div id="dynamicFormInsert"></div>
                <div id="addToListBtnContainer" style="text-align: center; margin-top: 20px; display: none;">
                    <button type="button" class="btn-primary" onclick="addRecordToList()">បន្ថែមទៅក្នុងបញ្ជី</button>
                </div>
                <div id="pendingListContainer" style="display: none;">
                    <h4 style="margin-bottom: 10px;">បញ្ជីរង់ចាំការរក្សាទុក</h4>
                    <ul id="pendingList"></ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('insertModal')">បោះបង់</button>
                <button type="button" class="btn-primary" onclick="saveAllPending()">រក្សាទុកទាំងអស់</button>
            </div>
        </div>
    </div>

    <div id="screenshotModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>រូបភាពតារាងទិន្នន័យ</h2>
                <button class="modal-close-btn" onclick="closeModal('screenshotModal')">&times;</button>
            </div>
            <div class="modal-body" style="text-align: center;">
                <div id="spinner-container">
                    <div id="spinner"></div>
                    <p>កំពុងបង្កើតរូបភាព...</p>
                </div>
                <img id="screenshotPreview" src="" alt="Screenshot Preview" style="display: none;">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('screenshotModal')">បិទ</button>
                <button type="button" class="btn-primary" id="copyImageBtn">ចម្លងរូបភាព</button>
            </div>
        </div>
    </div>


<script>
    // ... JavaScript code remains exactly the same ...
    const csrfToken = '<?= $_SESSION['csrf_token'] ?>';
    const tableConfigs = <?php echo json_encode($table_configs); ?>;
    const allRecords = <?php echo json_encode($all_records); ?>;
    const selectedDate = '<?php echo htmlspecialchars($selected_date); ?>';
    let activeInput = null;

    const datePicker = document.getElementById('datePicker');
    if(datePicker) {
        datePicker.addEventListener('change', function() {
            document.getElementById('dateFilterForm').submit();
        });
    }

    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(() => { toast.classList.add('show'); }, 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => { container.removeChild(toast); }, 300);
        }, 3000);
    }

    async function saveData(action, data) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('csrf_token', csrfToken);
        for (const key in data) { formData.append(key, data[key]); }
        try {
            const response = await fetch(window.location.pathname, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();
            if (result.success) {
                showToast(result.message || 'រក្សាទុកទិន្នន័យ!');
            } else {
                showToast(result.message || 'មានបញ្ហាក្នុងការរក្សាទុក', 'error');
            }
            return result;
        } catch (error) {
            console.error('Fetch Error:', error);
            showToast('បរាជ័យក្នុងការតភ្ជាប់ទៅ Server', 'error');
            return { success: false };
        }
    }

    function revertCell(cell, originalValue) {
        cell.classList.remove('editing');
        cell.innerHTML = originalValue;
    }
    
    function updateRowAndGrandTotals(row) {
        const key = row.dataset.key;
        if (!key) return;

        const config = tableConfigs[key];
        if (['office', 'store', 'warehouse'].includes(config.type)) {
            let newRowTotal = 0;
            const numberCells = row.querySelectorAll('.editable[data-column]');
            numberCells.forEach(cell => {
                const colName = cell.dataset.column;
                if (config.columns.includes(colName) && colName !== 'reports_date') {
                    newRowTotal += parseInt(cell.textContent) || 0;
                }
            });
            const totalCell = row.querySelector('td[data-column="total"]');
            if (totalCell) {
                totalCell.innerHTML = `<b>${newRowTotal} នាក់</b>`;
            }
        }
        
        let sectionTotal = 0;
        const allRowsForKey = document.querySelectorAll(`tr[data-key="${key}"]`);
        
        if (['office', 'store', 'warehouse'].includes(config.type)) {
            allRowsForKey.forEach(r => {
                const totalCell = r.querySelector('td[data-column="total"]');
                if (totalCell) {
                    sectionTotal += parseInt(totalCell.innerText.replace(/[^0-9]/g, '')) || 0;
                }
            });
        } else {
            sectionTotal = allRowsForKey.length;
        }

        const summaryCell = document.querySelector(`td[data-summary-key="${key}"]`);
        if (summaryCell) {
            summaryCell.innerHTML = `${sectionTotal} នាក់`;
        }

        let grandTotal = 0;
        const allSummaryCells = document.querySelectorAll('#summary-table td[data-summary-key]');
        allSummaryCells.forEach(cell => {
             grandTotal += parseInt(cell.innerText.replace(/[^0-9]/g, '')) || 0;
        });
        const grandTotalCell = document.getElementById('grand-total');
        if (grandTotalCell) {
            grandTotalCell.innerHTML = `${grandTotal} នាក់`;
        }
    }

    function makeCellEditable(cell) {
        if (cell.classList.contains('editing')) return;
        const originalValue = cell.innerHTML.trim();
        const originalText = cell.textContent.trim();
        const column = cell.dataset.column;
        cell.classList.add('editing');

        let inputElement;
        if (column === 'note') {
            inputElement = document.createElement('textarea');
            inputElement.rows = 3;
        } else if (column === 'reports_date') {
            inputElement = document.createElement('input');
            inputElement.type = 'date';
        } else {
            inputElement = document.createElement('input');
            const rowKey = cell.closest('tr').dataset.key;
            const config = tableConfigs[rowKey];
            inputElement.type = (config.type === 'new_staff' && (column ==='name' || column==='role' || column==='number')) ? 'text' : 'number';
            if (inputElement.type === 'number') inputElement.min = "0";
        }
        inputElement.value = originalText;
        
        cell.innerHTML = '';
        cell.appendChild(inputElement);
        inputElement.focus();
        activeInput = inputElement;

        const saveAndRevert = async () => {
            const newValue = inputElement.value.trim();
            if (newValue !== originalText) {
                const row = cell.closest('tr');
                const data = {
                    id: row.dataset.id,
                    key: row.dataset.key,
                    column: column,
                    value: newValue
                };
                
                const result = await saveData('update_inline', data);
                
                if (result.success) {
                    cell.innerHTML = (column === 'note') ? newValue.replace(/\n/g, '<br>') : newValue;
                    updateRowAndGrandTotals(row);
                } else {
                    cell.innerHTML = originalValue;
                }

            } else {
                cell.innerHTML = originalValue;
            }
            cell.classList.remove('editing');
            activeInput = null;
        };

        inputElement.addEventListener('blur', saveAndRevert);
        inputElement.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && (column !== 'note' || e.ctrlKey)) { 
                e.preventDefault(); 
                inputElement.blur(); 
            } else if (e.key === 'Escape') { 
                revertCell(cell, originalValue); 
                activeInput = null; 
            }
        });
    }

    document.addEventListener('click', (e) => {
        if (e.target.matches('td.editable')) {
            if (activeInput && activeInput !== e.target.querySelector('input, textarea')) {
                activeInput.blur();
            }
            setTimeout(() => makeCellEditable(e.target), 50);
        }
    });

    let pendingRecords = [];

    function openInsertModal() {
        pendingRecords = []; 
        renderPendingList();
        generateDropdownOptions('modal_menu_insert');
        document.getElementById('modal_menu_insert').value = '';
        document.getElementById('dynamicFormInsert').innerHTML = '';
        document.getElementById('addToListBtnContainer').style.display = 'none';
        document.getElementById('pendingListContainer').style.display = 'none';
        
        const modal = document.getElementById('insertModal');
        modal.style.display = 'flex';
        setTimeout(() => modal.classList.add('show'), 10);
    }
    
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.classList.remove('show');
        setTimeout(() => { modal.style.display = 'none'; }, 300);
    }

    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            closeModal(event.target.id);
        }
    }

    function generateForm(mode, record = null) {
        const selectElement = document.getElementById(`modal_menu_${mode}`);
        const key = selectElement.value;
        const formContainer = document.getElementById(`dynamicForm${mode.charAt(0).toUpperCase() + mode.slice(1)}`);
        
        if (mode === 'insert') {
            document.getElementById('addToListBtnContainer').style.display = key ? 'block' : 'none';
        }
        if (!key) { formContainer.innerHTML = ''; return; }

        const config = tableConfigs[key];
        let html = '';
        const val = (fieldName) => record ? (record[fieldName] ?? '') : '';
        
        switch (config.type) {
            case 'office':
                html += `<label>IT:</label><input type="number" name="it" min="0" value="${val('it') || '0'}">
                         <label>Admin:</label><input type="number" name="admin" min="0" value="${val('admin') || '0'}">
                         <label>Account:</label><input type="number" name="account" min="0" value="${val('account') || '0'}">
                         <label>Sale:</label><input type="number" name="sale" min="0" value="${val('sale') || '0'}">
                         <label>318:</label><input type="number" name="staff_318" min="0" value="${val('staff_318') || '0'}">
                         <label>ថ្ងៃរាយការណ៍:</label><input type="date" name="reports_date" value="${record ? val('reports_date') : selectedDate}" required>`;
                break;
            case 'store':
                html += `<label>GM:</label><input type="number" name="gm" min="0" value="${val('gm') || '0'}">
                         <label>Manager store:</label><input type="number" name="manager_store" min="0" value="${val('manager_store') || '0'}">
                         <label>Manager stock:</label><input type="number" name="manager_stock" min="0" value="${val('manager_stock') || '0'}">
                         <label>SKKS2:</label><input type="number" name="staff_skks2" min="0" value="${val('staff_skks2') || '0'}">
                         <label>NR3:</label><input type="number" name="staff_nr3" min="0" value="${val('staff_nr3') || '0'}">
                         <label>ថ្ងៃរាយការណ៍:</label><input type="date" name="reports_date" value="${record ? val('reports_date') : selectedDate}" required>`;
                break;
            case 'warehouse':
                html += `<label>CKD:</label><input type="number" name="ckd" min="0" value="${val('ckd') || '0'}">
                         <label>PSP:</label><input type="number" name="psp" min="0" value="${val('psp') || '0'}">
                         <label>ថ្ងៃរាយការណ៍:</label><input type="date" name="reports_date" value="${record ? val('reports_date') : selectedDate}" required>`;
                break;
            case 'new_staff':
                html += `<label>ល.រ (ស្រេចចិត្ត):</label><input type="text" name="number" value="${val('number')}" placeholder="ឧ. A01">
                         <label>ឈ្មោះ:</label><input type="text" name="name" value="${val('name')}" placeholder="ឈ្មោះ" required>
                         <label>តួនាទី:</label><input type="text" name="role" value="${val('role')}" placeholder="តួនាទី" required>
                         <label>អធិប្បាយ:</label><textarea name="note" placeholder="ឧ. សុំច្បាប់, ដេអូស..." rows="3">${val('note')}</textarea>
                         <label>ថ្ងៃរាយការណ៍:</label><input type="date" name="reports_date" value="${record ? val('reports_date') : selectedDate}" required>`;
                break;
        }
        formContainer.innerHTML = `<form id="tempForminsert">${html}</form>`;
    }
    
    function addRecordToList() {
        const key = document.getElementById('modal_menu_insert').value;
        if (!key) { alert('សូមជ្រើសរើសប្រភេទជាមុនសិន'); return; }

        const tempForm = document.getElementById('tempForminsert');
        const formData = new FormData(tempForm);
        const record = { _key: key };
        
        if (tableConfigs[key].type === 'new_staff' && (!formData.get('name') || !formData.get('role'))) {
            alert('សូមបំពេញ ឈ្មោះ និងតួនាទី។'); return;
        }
        
        for (let [name, value] of formData.entries()) { record[name] = value; }
        pendingRecords.push(record);
        renderPendingList();
        tempForm.reset();
        if(tempForm.elements['reports_date']) tempForm.elements['reports_date'].value = selectedDate;
    }

    function renderPendingList() {
        const listContainer = document.getElementById('pendingListContainer');
        const listElement = document.getElementById('pendingList');
        if (pendingRecords.length > 0) {
            listContainer.style.display = 'block';
        } else {
            listContainer.style.display = 'none';
        }

        listElement.innerHTML = '';
        pendingRecords.forEach((record, index) => {
            let description = `<b>${tableConfigs[record._key].label}:</b> `;
            if (record._key === 'office_staff') {
                description += `IT: ${record.it || 0}, Admin: ${record.admin || 0}, Sale: ${record.sale || 0}, 318: ${record.staff_318 || 0}`;
            } else if (tableConfigs[record._key].type === 'store') {
                description += `GM: ${record.gm || 0}, Manager store: ${record.manager_store || 0}, Manager stock: ${record.manager_stock || 0}, SKKS2: ${record.staff_skks2 || 0}, NR3: ${record.staff_nr3 || 0}`;
            } else if (record._key === 'warehouse_staff') {
                description += `CKD: ${record.ckd || 0}, PSP: ${record.psp || 0}`;
            } else if (record._key === 'new_staff') {
                description += `${record.name} (${record.role})`;
            }
            const li = document.createElement('li');
            li.innerHTML = `<span>${description}</span> <button class="delete-btn" style="padding: 5px 10px;" onclick="removeRecordFromList(${index})">លុប</button>`;
            listElement.appendChild(li);
        });
    }

    function removeRecordFromList(index) {
        pendingRecords.splice(index, 1);
        renderPendingList();
    }
    function saveAllPending() {
        if (pendingRecords.length === 0) { alert('មិនមានទិន្នន័យក្នុងបញ្ជីដើម្បីរក្សាទុកទេ។'); return; }
        let form = document.createElement('form');
        form.method = 'POST';
        form.action = `<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . "?date=" . urlencode($selected_date); ?>`;
        let input = document.createElement('input');
        input.type = 'hidden'; input.name = 'save_all_pending'; input.value = JSON.stringify(pendingRecords);
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }

    function generateDropdownOptions(selectId) {
        const select = document.getElementById(selectId);
        select.innerHTML = '<option value="">-- ជ្រើសរើសប្រភេទដើម្បីបញ្ចូល --</option>';
        for (const key in tableConfigs) {
            select.innerHTML += `<option value="${key}">${tableConfigs[key].label}</option>`;
        }
    }
    
    function deleteRecord(id, deleteKey, message) {
        if (confirm(message)) {
            let form = document.createElement('form');
            form.method = 'POST'; form.action = `<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . "?date=" . urlencode($selected_date); ?>`;
            form.innerHTML = `<input type="hidden" name="id" value="${id}"><input type="hidden" name="${deleteKey}" value="1">`;
            document.body.appendChild(form);
            form.submit();
        }
    }

    const screenshotBtn = document.getElementById('screenshotBtn');
    const screenshotModal = document.getElementById('screenshotModal');
    const screenshotPreview = document.getElementById('screenshotPreview');
    const copyImageBtn = document.getElementById('copyImageBtn');
    const captureArea = document.getElementById('capture-area');
    const spinnerContainer = document.getElementById('spinner-container');
    let imageBlob = null;

    screenshotBtn.addEventListener('click', async () => {
        screenshotPreview.style.display = 'none';
        spinnerContainer.style.display = 'block';
        screenshotModal.style.display = 'flex';
        setTimeout(() => screenshotModal.classList.add('show'), 10);
        
        captureArea.classList.add('hide-for-screenshot');

        try {
            const canvas = await html2canvas(captureArea, {
                scale: 2, 
                backgroundColor: '#ffffff'
            });
            
            screenshotPreview.src = canvas.toDataURL('image/png');
            screenshotPreview.style.display = 'block';

            canvas.toBlob(function(blob) {
                imageBlob = blob;
            });
            
        } catch (error) {
            console.error('Error taking screenshot:', error);
            showToast('មានបញ្ហាក្នុងការបង្កើតរូបភាព', 'error');
            closeModal('screenshotModal');
        } finally {
            captureArea.classList.remove('hide-for-screenshot');
            spinnerContainer.style.display = 'none';
        }
    });

    copyImageBtn.addEventListener('click', async () => {
        if (!imageBlob) {
            showToast('មិនមានរូបភាពសម្រាប់ចម្លងទេ', 'error');
            return;
        }
        try {
            await navigator.clipboard.write([
                new ClipboardItem({
                    'image/png': imageBlob
                })
            ]);
            showToast('បានចម្លងរូបភាពដោយជោគជ័យ!', 'success');
        } catch (error) {
            console.error('Error copying image:', error);
            showToast('បរាជ័យក្នុងការចម្លងរូបភាព', 'error');
        }
    });
</script>
</body>
</html>
