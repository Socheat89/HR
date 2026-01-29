<?php
// ត្រូវតែជាបន្ទាត់ដំបូងបំផុតនៃស្គ្រីប
session_start();

// បើកការรายงานข้อผิดพลาด PHP សម្រាប់ការดีบัก
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php';

// =============================================================================
// ការកំណត់រចនាសម្ព័ន្ធកណ្តាលសម្រាប់តារាងទាំងអស់ (CENTRAL CONFIGURATION)
// ដើម្បីបន្ថែមតារាងថ្មី អ្នកគ្រាន់តែបន្ថែមការកំណត់រចនាសម្ព័ន្ធនៅទីនេះ
// =============================================================================
$table_configs = [
    'office_staff' => [
        'label' => 'បុគ្គលិកការិយាល័យកណ្តាល',
        'db_table' => 'office_staff',
        'type' => 'staff' // ប្រភេទ 'staff' สำหรับตารางที่มี female/male
    ],
    'store_318' => [
        'label' => 'បុគ្គលិកហាងទំនិញ៣១៨',
        'db_table' => 'store_318_staff',
        'type' => 'staff'
    ],
    'chhouk_meas' => [
        'label' => 'បុគ្គលិកហាងគ្រឿងក្រអូបផ្សារឈូកមាស',
        'db_table' => 'chhouk_meas_staff',
        'type' => 'staff'
    ],
    'warehouse' => [
        'label' => 'បុគ្គលិកជំនាញតាមឃ្លាំង',
        'db_table' => 'warehouse_staff',
        'type' => 'warehouse'
    ],
    'new_staff' => [
        'label' => 'បុគ្គលិកសុំច្បាប់ ដេអូស ប្តូរដេអូស និងចូលថ្មី',
        'db_table' => 'new_staff',
        'type' => 'new_staff'
    ]
];

// ទាញយកសារจาก session ប្រសិនបើមាន រួចលុបវាចោល
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

$selected_date = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d');
$all_records = [];
$all_totals = [];


/**
 * จัดการการ Insert/Update สำหรับตารางបុគ្គលិកมาตรฐาน (female, male)
 * @return string សារជោគជ័យ ឬបរាជ័យ
 */
function handleStandardStaffPost(PDO $pdo, string $db_table, string $label): string {
    $id = isset($_POST['id']) && !empty($_POST['id']) ? (int)$_POST['id'] : null;
    $female = isset($_POST['female']) ? (int)$_POST['female'] : 0;
    $male = isset($_POST['male']) ? (int)$_POST['male'] : 0;
    $total = $female + $male;
    $reports_date = $_POST['reports_date'];

    if ($id) {
        $stmt = $pdo->prepare("UPDATE {$db_table} SET total = ?, female = ?, male = ?, reports_date = ? WHERE id = ?");
        $success = $stmt->execute([$total, $female, $male, $reports_date, $id]);
        return $success ? "កំណត់ត្រា ({$label}) ត្រូវបានកែប្រែជោគជ័យ! " : "បរាជ័យក្នុងការកែប្រែកំណត់ត្រា ({$label})! ";
    } else {
        $stmt = $pdo->prepare("INSERT INTO {$db_table} (total, female, male, reports_date) VALUES (?, ?, ?, ?)");
        $success = $stmt->execute([$total, $female, $male, $reports_date]);
        return $success ? "កំណត់ត្រាថ្មី ({$label}) ត្រូវបានបញ្ចូលជោគជ័យ! " : "បរាជ័យក្នុងការបញ្ចូលកំណត់ត្រា ({$label})! ";
    }
}

/**
 * จัดการการลบสำหรับตารางใด ๆ
 * @return string សារជោគជ័យ หรือបរាជ័យ
 */
function handleDeletePost(PDO $pdo, string $db_table): string {
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM {$db_table} WHERE id = ?");
    $success = $stmt->execute([$id]);
    return $success ? "កំណត់ត្រាត្រូវបានលុបជោគជ័យ! " : "បរាជ័យក្នុងការលុបកំណត់ត្រា! ";
}


try {
    // =========================================================================
    // การจัดการ CRUD แบบไดนามิก (DYNAMIC CRUD HANDLING)
    // =========================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $crud_message = '';
        foreach ($table_configs as $key => $config) {
            $db_table = $config['db_table'];
            $label = $config['label'];

            // จัดการการส่ง (Submissions)
            if (isset($_POST[$key . '_submit'])) {
                switch ($config['type']) {
                    case 'staff':
                        $crud_message .= handleStandardStaffPost($pdo, $db_table, $label);
                        break;
                    case 'warehouse':
                        $id = isset($_POST['id']) && !empty($_POST['id']) ? (int)$_POST['id'] : null;
                        $ch1 = (int)($_POST['ch1'] ?? 0);
                        $ckd = (int)($_POST['ckd'] ?? 0);
                        $st1 = (int)($_POST['st1'] ?? 0);
                        $psp = (int)($_POST['psp'] ?? 0);
                        $total = $ch1 + $ckd + $st1 + $psp;
                        $reports_date = $_POST['reports_date'];
                        if ($id) {
                            $stmt = $pdo->prepare("UPDATE {$db_table} SET total = ?, ch1 = ?, ckd = ?, st1 = ?, psp = ?, reports_date = ? WHERE id = ?");
                            $success = $stmt->execute([$total, $ch1, $ckd, $st1, $psp, $reports_date, $id]);
                            $crud_message .= $success ? "កំណត់ត្រាឃ្លាំងត្រូវបានកែប្រែជោគជ័យ! " : "បរាជ័យក្នុងការកែប្រែកំណត់ត្រាឃ្លាំង! ";
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO {$db_table} (total, ch1, ckd, st1, psp, reports_date) VALUES (?, ?, ?, ?, ?, ?)");
                            $success = $stmt->execute([$total, $ch1, $ckd, $st1, $psp, $reports_date]);
                            $crud_message .= $success ? "កំណត់ត្រាឃ្លាំងថ្មីត្រូវបានបញ្ចូលជោគជ័យ! " : "បរាជ័យក្នុងការបញ្ចូលកំណត់ត្រាឃ្លាំង! ";
                        }
                        break;
                    case 'new_staff':
                         $id = isset($_POST['id']) && !empty($_POST['id']) ? (int)$_POST['id'] : null;
                         $number = $_POST['number'] ?? ''; $name = $_POST['name'] ?? ''; $role = $_POST['role'] ?? ''; $note = $_POST['note'] ?? '';
                         $office_central = (int)($_POST['office_central'] ?? 0); $store_318 = (int)($_POST['store_318'] ?? 0); $warehouse = (int)($_POST['warehouse'] ?? 0);
                         $reports_date = $_POST['reports_date'] ?? $selected_date;
                         if (empty($number) || empty($name) || empty($role)) {
                             $crud_message .= "កំណត់ត្រាមិនត្រូវបានបញ្ចូល: សូមបំពេញ ល.រ, ឈ្មោះ, និងតួនាទី! ";
                         } else {
                            if ($id) {
                                $stmt = $pdo->prepare("UPDATE {$db_table} SET number = ?, name = ?, role = ?, note = ?, office_central = ?, store_318 = ?, warehouse = ?, reports_date = ? WHERE id = ?");
                                $success = $stmt->execute([$number, $name, $role, $note, $office_central, $store_318, $warehouse, $reports_date, $id]);
                                $crud_message .= $success ? "កំណត់ត្រាបុគ្គលិកថ្មី (ID: $id) ត្រូវបានកែប្រែជោគជ័យ! " : "បរាជ័យក្នុងការកែប្រែកំណត់ត្រាបុគ្គលិកថ្មី! ";
                            } else {
                                $stmt = $pdo->prepare("INSERT INTO {$db_table} (number, name, role, note, office_central, store_318, warehouse, reports_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                $success = $stmt->execute([$number, $name, $role, $note, $office_central, $store_318, $warehouse, $reports_date]);
                                $crud_message .= $success ? "កំណត់ត្រាបុគ្គលិកថ្មីត្រូវបានបញ្ចូលជោគជ័យ! " : "បរាជ័យក្នុងការបញ្ចូលកំណត់ត្រាបុគ្គលិកថ្មី! ";
                            }
                         }
                        break;
                }
            }

            // จัดการการลบ (Deletions)
            if (isset($_POST[$key . '_delete'])) {
                $crud_message .= handleDeletePost($pdo, $db_table);
            }
        }

        $_SESSION['message'] = $crud_message;
        header("Location: " . $_SERVER['PHP_SELF'] . "?date=" . urlencode($selected_date));
        exit();
    }

    // =========================================================================
    // การดึงข้อมูลและคำนวณผลรวมแบบไดนามิก (DYNAMIC DATA FETCHING & TOTALS)
    // =========================================================================
    foreach ($table_configs as $key => $config) {
        $stmt = $pdo->prepare("SELECT * FROM {$config['db_table']} WHERE reports_date = ? ORDER BY id ASC");
        $stmt->execute([$selected_date]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $all_records[$key] = $records ?: [];
        
        if ($config['type'] === 'new_staff') {
             $total = 0;
             foreach ($all_records[$key] as $row) {
                 $total += (int)$row['office_central'] + (int)$row['store_318'] + (int)$row['warehouse'];
             }
             $all_totals[$key] = $total;
        } else {
             $all_totals[$key] = array_sum(array_column($all_records[$key], 'total'));
        }
    }
    $overall_total = array_sum($all_totals);

} catch (PDOException $e) {
    $message = "កំហុសក្នុងការទាក់ទងទិន្នន័យ: " . $e->getMessage();
    error_log("PDOException: " . $e->getMessage());
}

// =============================================================================
// ฟังก์ชันជំនួយสำหรับสร้างตาราง (HELPER FUNCTION TO RENDER TABLES)
// =============================================================================
function renderTable(string $key, array $config, array $records) {
    $label = htmlspecialchars($config['label']);
    $type = $config['type'];
    
    echo "<table><thead>";
    
    if ($type === 'staff') {
        echo "<tr><th colspan='5' class='section-header'>{$label}</th></tr>";
        echo "<tr><th>ចំនួនសរុប</th><th>បុគ្គលិកភេទស្រី</th><th>បុគ្គលិកភេទប្រុស</th><th>ថ្ងៃរាយការណ៍</th><th>សកម្មភាព</th></tr></thead><tbody>";
        foreach ($records as $record) {
            echo "<tr>
                    <td>" . htmlspecialchars($record['total']) . " នាក់</td>
                    <td>" . htmlspecialchars($record['female']) . "</td>
                    <td>" . htmlspecialchars($record['male']) . "</td>
                    <td>" . htmlspecialchars($record['reports_date']) . "</td>
                    <td><button class='action-btn delete-btn' onclick=\"deleteRecord({$record['id']}, '{$key}_delete', 'តើអ្នកប្រាកដជាចង់លុបកំណត់ត្រានេះមែនទេ?')\">លុប</button></td>
                  </tr>";
        }
    } elseif ($type === 'warehouse') {
        echo "<tr><th colspan='7' class='section-header'>{$label}</th></tr>";
        echo "<tr><th>ចំនួនសរុប</th><th>CH1</th><th>CKD</th><th>ST1</th><th>PSP</th><th>ថ្ងៃរាយការណ៍</th><th>សកម្មភាព</th></tr></thead><tbody>";
        foreach ($records as $record) {
            echo "<tr>
                    <td>" . htmlspecialchars($record['total']) . " នាក់</td>
                    <td>" . htmlspecialchars($record['ch1']) . "</td>
                    <td>" . htmlspecialchars($record['ckd']) . "</td>
                    <td>" . htmlspecialchars($record['st1']) . "</td>
                    <td>" . htmlspecialchars($record['psp']) . "</td>
                    <td>" . htmlspecialchars($record['reports_date']) . "</td>
                    <td><button class='action-btn delete-btn' onclick=\"deleteRecord({$record['id']}, '{$key}_delete', 'តើអ្នកប្រាកដជាចង់លុបកំណត់ត្រានេះមែនទេ?')\">លុប</button></td>
                  </tr>";
        }
    } elseif ($type === 'new_staff') {
        echo "<tr><th colspan='9' class='section-header'>{$label}</th></tr>";
        echo "<tr><th>ល.រ</th><th>ឈ្មោះ</th><th>តួនាទី</th><th>អធិប្បាយ</th><th>ការិយាល័យកណ្តាល</th><th>៣១៨</th><th>ឃ្លាំង</th><th>ថ្ងៃរាយការណ៍</th><th>សកម្មភាព</th></tr></thead><tbody>";
        foreach ($records as $row) {
             $confirm_msg = "តើអ្នកប្រាកដជាចង់លុបកំណត់ត្រា (ល.រ: " . addslashes(htmlspecialchars($row['number'])) . ", ឈ្មោះ: " . addslashes(htmlspecialchars($row['name'])) . ") មែនទេ?";
             echo "<tr>
                    <td>" . htmlspecialchars($row['number']) . "</td><td>" . htmlspecialchars($row['name']) . "</td><td>" . htmlspecialchars($row['role']) . "</td>
                    <td>" . htmlspecialchars($row['note']) . "</td><td>" . htmlspecialchars($row['office_central']) . "</td><td>" . htmlspecialchars($row['store_318']) . "</td>
                    <td>" . htmlspecialchars($row['warehouse']) . "</td><td>" . htmlspecialchars($row['reports_date']) . "</td>
                    <td><button class='action-btn delete-btn' onclick=\"deleteRecord({$row['id']}, '{$key}_delete', '{$confirm_msg}')\">លុប</button></td>
                  </tr>";
        }
    }

    if (empty($records)) {
        $colspan = ($type === 'staff') ? 5 : (($type === 'warehouse') ? 7 : 9);
        echo "<tr><td colspan='{$colspan}'>មិនមានទិន្នន័យសម្រាប់ថ្ងៃនេះ។</td></tr>";
    }
    
    echo "</tbody></table>";
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>របាយការណ៍វត្តមានបុគ្គលិក</title>
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2/Logo-Van-Van-1.png">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Noto Sans Khmer', sans-serif; margin: 20px; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; vertical-align: middle; }
        th { background-color: #4a56ff; color: white; }
        .section-header { background-color: rgb(233, 198, 1); color: #0011ff; font-weight: bold; }
        .overall-total { background-color: #ececec9c; font-weight: bold; font-size: 1.1em; }
        .message { text-align: center; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; }
        .success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; }
        .date-form { text-align: center; margin-bottom: 20px; }
        input[type="date"], input[type="number"], input[type="text"], select { padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: 'Noto Sans Khmer', sans-serif; margin: 4px 0; box-sizing: border-box; width: 100%; text-align: center; }
        .modal-select { font-size: 1.2em; padding: 10px; width: 100%; margin-bottom: 20px; }
        button, .action-btn { background-color: #4CAF50; color: white; padding: 8px 15px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        button:hover, .action-btn:hover { opacity: 0.9; }
        .insert-btn { background-color: #28a745; }
        .edit-btn { background-color: #ffc107; }
        .delete-btn { background-color: #dc3545; }
        .close-btn { background-color: #6c757d; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: white; margin: 5% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 600px; max-height: 80vh; overflow-y: auto; box-shadow: 0 0 10px rgba(0,0,0,0.3); }
        .modal-content h2 { margin-top: 0; }
        .modal-content label { display: block; margin-top: 10px; font-weight: bold; text-align: left; }
    </style>
</head>
<body>
    <div class="container">
        <div class="date-form">
            <form method="GET" action="">
                <h1>របាយការណ៍វត្តមានបុគ្គលិកប្រចាំថ្ងៃ</h1>
                <input type="date" name="date" value="<?php echo htmlspecialchars($selected_date); ?>">
                <button type="submit">មើល</button>
            </form>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'បរាជ័យ') !== false || strpos($message, 'កំហុស') !== false ? 'error' : 'success'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- ตารางที่สร้างขึ้นแบบไดนามิก (DYNAMICALLY RENDERED TABLES) -->
        <?php
        foreach ($table_configs as $key => $config) {
            renderTable($key, $config, $all_records[$key]);
        }
        ?>

        <!-- ตารางผลรวมทั้งหมด (OVERALL TOTAL TABLE) -->
        <table>
            <thead>
                <tr><th colspan="<?php echo count($table_configs) + 1; ?>" class="section-header">សរុបរួម</th></tr>
                <tr>
                    <?php foreach ($table_configs as $config): ?>
                        <th><?php echo htmlspecialchars($config['label']); ?></th>
                    <?php endforeach; ?>
                    <th>សរុបទាំងអស់</th>
                </tr>
            </thead>
            <tbody>
                <tr class="overall-total">
                    <?php foreach ($all_totals as $total): ?>
                        <td><?php echo htmlspecialchars($total); ?> នាក់</td>
                    <?php endforeach; ?>
                    <td><?php echo htmlspecialchars($overall_total); ?> នាក់</td>
                </tr>
            </tbody>
        </table>

        <!-- ปุ่มดำเนินการ (ACTION BUTTONS) -->
        <div style="text-align: center; margin-top: 20px;">
            <button class="action-btn insert-btn" onclick="openInsertModal()">បញ្ចូលទិន្នន័យថ្មី</button>
            <button class="action-btn edit-btn" onclick="openEditModal()">កែប្រែទិន្នន័យ</button>
        </div>

    </div> <!--/.container -->

    <!-- MODALS -->
    <div id="insertModal" class="modal">
        <div class="modal-content">
            <form id="insertForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?date=' . urlencode($selected_date); ?>">
                <h2>បញ្ចូលទិន្នន័យថ្មី</h2>
                <select id="modal_menu_insert" class="modal-select" onchange="generateForm('insert')">
                    <option value="">ជ្រើសរើសប្រភេទ</option>
                </select>
                <div id="dynamicFormInsert"></div>
                <div style="text-align: center; margin-top: 20px;">
                    <button type="submit" class="action-btn insert-btn">បញ្ចូល</button>
                    <button type="button" class="action-btn close-btn" onclick="closeModal('insertModal')">បោះបង់</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
             <form id="editForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?date=' . urlencode($selected_date); ?>">
                <h2>កែប្រែទិន្នន័យ</h2>
                <select id="modal_menu_edit" class="modal-select" onchange="generateRecordSelector()">
                    <option value="">ជ្រើសរើសប្រភេទ</option>
                </select>
                <div id="recordSelectorDiv"></div>
                <div id="dynamicFormEdit"></div>
                <div style="text-align: center; margin-top: 20px;">
                    <button type="submit" class="action-btn insert-btn">រក្សាទុក</button>
                    <button type="button" class="action-btn close-btn" onclick="closeModal('editModal')">បោះបង់</button>
                </div>
            </form>
        </div>
    </div>

<script>
    // --- ទិន្នន័យจาก PHP ---
    const tableConfigs = <?php echo json_encode($table_configs); ?>;
    const allRecords = <?php echo json_encode($all_records); ?>;
    const selectedDate = '<?php echo htmlspecialchars($selected_date); ?>';

    // --- ฟังก์ชันជំនួយ ---
    const getNumberOptions = (selectedValue = 0, max = 50) => {
        let options = '';
        for (let i = 0; i <= max; i++) {
            options += `<option value="${i}" ${parseInt(selectedValue) === i ? 'selected' : ''}>${i}</option>`;
        }
        return options;
    };

    const getDateOptions = (selectedValue) => {
        let options = '';
        <?php for ($i = 0; $i < 7; $i++): $date = date('Y-m-d', strtotime("-$i days")); ?>
            options += `<option value='<?php echo $date; ?>' ${selectedValue === '<?php echo $date; ?>' ? 'selected' : ''}><?php echo $date; ?></option>`;
        <?php endfor; ?>
        return options;
    };
    
    // --- ตรรกะการสร้างฟอร์ม (Form Generation Logic) ---
    function generateForm(mode, record = null) {
        const key = document.getElementById(`modal_menu_${mode}`).value;
        const formDiv = document.getElementById(`dynamicForm${mode.charAt(0).toUpperCase() + mode.slice(1)}`);
        if (!key) { formDiv.innerHTML = ''; return; }

        const config = tableConfigs[key];
        let html = '';

        const val = (fieldName) => record ? (record[fieldName] || '') : (fieldName.includes('date') ? selectedDate : (fieldName === 'number' ? '' : 0));
        
        html += `<input type="hidden" name="${key}_submit" value="1">`;
        if (record) { html += `<input type="hidden" name="id" value="${record.id}">`; }

        switch (config.type) {
            case 'staff':
                html += `<h3>${config.label}</h3>
                         <label>បុគ្គលិកភេទស្រី:</label><select name="female">${getNumberOptions(val('female'))}</select>
                         <label>បុគ្គលិកភេទប្រុស:</label><select name="male">${getNumberOptions(val('male'))}</select>
                         <label>ថ្ងៃរាយការណ៍:</label><select name="reports_date" required>${getDateOptions(val('reports_date'))}</select>`;
                break;
            case 'warehouse':
                html += `<h3>${config.label}</h3>
                         <label>CH1:</label><select name="ch1">${getNumberOptions(val('ch1'))}</select>
                         <label>CKD:</label><select name="ckd">${getNumberOptions(val('ckd'))}</select>
                         <label>ST1:</label><select name="st1">${getNumberOptions(val('st1'))}</select>
                         <label>PSP:</label><select name="psp">${getNumberOptions(val('psp'))}</select>
                         <label>ថ្ងៃរាយការណ៍:</label><select name="reports_date" required>${getDateOptions(val('reports_date'))}</select>`;
                break;
            case 'new_staff':
                 const noteOptions = { 'សុំច្បាប់': 'សុំច្បាប់', 'ដេអូស': 'ដេអូស', 'ប្តូរដេអូស': 'ប្តូរដេអូស', 'ចូលថ្មី': 'ចូលថ្មី' };
                 let noteSelect = '<option value="">គ្មាន</option>';
                 for(const [optVal, optLabel] of Object.entries(noteOptions)) {
                    noteSelect += `<option value="${optVal}" ${val('note') === optVal ? 'selected' : ''}>${optLabel}</option>`;
                 }
                html += `<h3>${config.label}</h3>
                         <label>ល.រ:</label><input type="text" name="number" value="${val('number')}" placeholder="ល.រ" required>
                         <label>ឈ្មោះ:</label><input type="text" name="name" value="${val('name')}" placeholder="ឈ្មោះ" required>
                         <label>តួនាទី:</label><input type="text" name="role" value="${val('role')}" placeholder="តួនាទី" required>
                         <label>អធិប្បាយ:</label><select name="note">${noteSelect}</select>
                         <label>ការិយាល័យកណ្តាល:</label><input type="number" name="office_central" min="0" value="${val('office_central')}">
                         <label>៣១៨:</label><input type="number" name="store_318" min="0" value="${val('store_318')}">
                         <label>ឃ្លាំង:</label><input type="number" name="warehouse" min="0" value="${val('warehouse')}">
                         <label>ថ្ងៃរាយការណ៍:</label><input type="date" name="reports_date" value="${val('reports_date')}" required>`;
                break;
        }
        formDiv.innerHTML = html;
    }
    
    function generateRecordSelector() {
        const key = document.getElementById('modal_menu_edit').value;
        const selectorDiv = document.getElementById('recordSelectorDiv');
        const formDiv = document.getElementById('dynamicFormEdit');
        formDiv.innerHTML = '';
        
        if (!key || !allRecords[key] || allRecords[key].length === 0) {
            selectorDiv.innerHTML = '<p>មិនមានកំណត់ត្រាសម្រាប់កែប្រែក្នុងថ្ងៃនេះទេ។</p>';
            return;
        }
        
        const config = tableConfigs[key];
        let options = allRecords[key].map(record => {
            const label = config.type === 'new_staff' ? `ល.រ: ${record.number}, ឈ្មោះ: ${record.name}` : `ID: ${record.id}`;
            return `<option value="${record.id}">${label}</option>`;
        }).join('');
        
        selectorDiv.innerHTML = `<label>ជ្រើសរើសកំណត់ត្រា:</label>
                                 <select id="record_id_selector" class="modal-select" onchange="generateFormForSelectedRecord()">${options}</select>`;
        generateFormForSelectedRecord(); // បង្ហាញข้อมูลสำหรับ record ដំបូងដោយស្វ័យប្រវត្តិ
    }
    
    function generateFormForSelectedRecord() {
        const key = document.getElementById('modal_menu_edit').value;
        const recordId = document.getElementById('record_id_selector').value;
        const record = allRecords[key].find(r => r.id == recordId);
        if (record) {
            generateForm('edit', record);
        }
    }

    // --- ការควบคุม Modal ---
    function openInsertModal() {
        generateDropdownOptions('modal_menu_insert');
        document.getElementById('dynamicFormInsert').innerHTML = '';
        document.getElementById('insertModal').style.display = 'block';
    }

    function openEditModal() {
        generateDropdownOptions('modal_menu_edit');
        document.getElementById('recordSelectorDiv').innerHTML = '';
        document.getElementById('dynamicFormEdit').innerHTML = '';
        document.getElementById('editModal').style.display = 'block';
    }

    function closeModal(modalId) { document.getElementById(modalId).style.display = 'none'; }
    
    function generateDropdownOptions(selectId) {
        const select = document.getElementById(selectId);
        select.innerHTML = '<option value="">ជ្រើសរើសប្រភេទ</option>'; // รีเซ็ต
        for (const key in tableConfigs) {
            select.innerHTML += `<option value="${key}">${tableConfigs[key].label}</option>`;
        }
    }
    
    // --- การดำเนินการ ---
    function deleteRecord(id, deleteKey, message) {
        if (confirm(message)) {
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = `<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . "?date=" . urlencode($selected_date); ?>`;
            form.style.display = 'none';
            form.innerHTML = `<input type="hidden" name="id" value="${id}">
                              <input type="hidden" name="${deleteKey}" value="1">`;
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
</body>
</html>