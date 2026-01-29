<?php
// attendance.php
session_start();

/*
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}
*/

require_once __DIR__ . '/attendance/db_connect.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- [PHP code remains the same] ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_all_data'])) {
    $report_date = $_POST['report_date'] ?? date('Y-m-d');
    
    $pdo->beginTransaction();

    try {
        // --- 1. Update/Insert Vehicle Loading Staff (with creator_full_name) ---
        $vl_data = [
            'vehicle_drivers' => (int)($_POST['vehicle_drivers'] ?? 0),
            'tricycle_drivers' => (int)($_POST['tricycle_drivers'] ?? 0),
            'load_follow_vehicle' => (int)($_POST['load_follow_vehicle'] ?? 0),
            'load_follow_tricycle' => (int)($_POST['load_follow_tricycle'] ?? 0),
            'load_follow_warehouse' => (int)($_POST['load_follow_warehouse'] ?? 0),
            'creator_full_name' => trim($_POST['creator_full_name'] ?? 'Unknown User'), // Handles the new editable field
            'report_date' => $report_date
        ];
        
        $stmt = $pdo->prepare("SELECT id FROM vehicle_loading_staff WHERE report_date = ?");
        $stmt->execute([$report_date]);
        if ($stmt->fetch()) {
            $sql = "UPDATE vehicle_loading_staff SET vehicle_drivers = :vehicle_drivers, tricycle_drivers = :tricycle_drivers, load_follow_vehicle = :load_follow_vehicle, load_follow_tricycle = :load_follow_tricycle, load_follow_warehouse = :load_follow_warehouse, creator_full_name = :creator_full_name WHERE report_date = :report_date";
        } else {
            $sql = "INSERT INTO vehicle_loading_staff (vehicle_drivers, tricycle_drivers, load_follow_vehicle, load_follow_tricycle, load_follow_warehouse, creator_full_name, report_date) VALUES (:vehicle_drivers, :tricycle_drivers, :load_follow_vehicle, :load_follow_tricycle, :load_follow_warehouse, :creator_full_name, :report_date)";
        }
        $pdo->prepare($sql)->execute($vl_data);

        // --- 2. Update/Insert Available Vehicles ---
        $av_data = [
            'cars' => (int)($_POST['cars'] ?? 0),
            'tricycles' => (int)($_POST['tricycles'] ?? 0),
            'report_date' => $report_date
        ];

        $stmt = $pdo->prepare("SELECT id FROM available_vehicles WHERE report_date = ?");
        $stmt->execute([$report_date]);
        if ($stmt->fetch()) {
            $sql = "UPDATE available_vehicles SET cars = :cars, tricycles = :tricycles WHERE report_date = :report_date";
        } else {
            $sql = "INSERT INTO available_vehicles (cars, tricycles, report_date) VALUES (:cars, :tricycles, :report_date)";
        }
        $pdo->prepare($sql)->execute($av_data);

        // --- 3. Bulk Update Staff Status ---
        if (isset($_POST['ids'])) {
            $ids = $_POST['ids'] ?? [];
            $serial_nos = $_POST['serial_no'] ?? [];
            $staff_names = $_POST['staff_name'] ?? [];
            $roles = $_POST['role'] ?? [];
            $comments = $_POST['comment'] ?? [];
            $new_staff_centrals = $_POST['new_staff_central'] ?? [];
            $new_staff_318s = $_POST['new_staff_318'] ?? [];
            $new_staff_warehouses = $_POST['new_staff_warehouse'] ?? [];

            $stmt_staff = $pdo->prepare(
                "UPDATE staff_status SET serial_no = ?, staff_name = ?, role = ?, comment = ?, 
                new_staff_central = ?, new_staff_318 = ?, new_staff_warehouse = ? WHERE id = ?"
            );

            foreach ($ids as $key => $id) {
                if(!empty($id)) {
                    $stmt_staff->execute([
                        trim($serial_nos[$key] ?? ''),
                        trim($staff_names[$key] ?? ''),
                        trim($roles[$key] ?? ''),
                        trim($comments[$key] ?? ''),
                        (int)($new_staff_centrals[$key] ?? 0),
                        (int)($new_staff_318s[$key] ?? 0),
                        (int)($new_staff_warehouses[$key] ?? 0),
                        (int)$id
                    ]);
                }
            }
        }

        $pdo->commit();
        header("Location: attendance.php?report_date=" . urlencode($report_date) . "&status=success");
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Database Error: " . $e->getMessage());
    }
}
// --- [PHP code continues as normal] ---
function getData($pdo, $table, $date) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE report_date = ?");
        $stmt->execute([$date]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['insert_staff'])) {
    $report_date = $_POST['report_date'] ?? date('Y-m-d');
    $serial_no = $_POST['serial_no'] ?? '';
    $staff_name = $_POST['staff_name'] ?? '';
    $role = $_POST['role'] ?? '';
    $comment = $_POST['comment'] ?? '';
    $new_staff_central = (int) ($_POST['new_staff_central'] ?? 0);
    $new_staff_318 = (int) ($_POST['new_staff_318'] ?? 0);
    $new_staff_warehouse = (int) ($_POST['new_staff_warehouse'] ?? 0);

    if ($serial_no && $staff_name && $role) {
        try {
            $stmt = $pdo->prepare("INSERT INTO staff_status (report_date, serial_no, staff_name, role, comment, new_staff_central, new_staff_318, new_staff_warehouse) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$report_date, $serial_no, $staff_name, $role, $comment, $new_staff_central, $new_staff_318, $new_staff_warehouse]);
            header("Location: attendance.php?report_date=" . urlencode($report_date));
            exit();
        } catch (PDOException $e) {
            die("Insert error: " . $e->getMessage());
        }
    }
}

if (isset($_GET['delete_id'])) {
    $id = (int) ($_GET['delete_id'] ?? 0);
    $report_date = $_GET['report_date'] ?? date('Y-m-d');
    if ($id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM staff_status WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: attendance.php?report_date=" . urlencode($report_date));
            exit();
        } catch (PDOException $e) {
            die("Delete error: " . $e->getMessage());
        }
    }
}

$report_date = isset($_GET['report_date']) ? $_GET['report_date'] : date('Y-m-d');

$vehicle_loading = getData($pdo, 'vehicle_loading_staff', $report_date);
$vehicles = getData($pdo, 'available_vehicles', $report_date);
$staff_status_data = [];
try {
    $staff_status_stmt = $pdo->prepare("SELECT * FROM staff_status WHERE report_date = ? ORDER BY serial_no ASC, id ASC");
    $staff_status_stmt->execute([$report_date]);
    $staff_status_data = $staff_status_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { }

$current_user = 'Unknown User'; 
if (!empty($vehicle_loading['creator_full_name'])) {
    $current_user = $vehicle_loading['creator_full_name'];
} else if (isset($_SESSION['username'])) {
    try {
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE username = ?");
        $stmt->execute([$_SESSION['username']]);
        $user_result = $stmt->fetchColumn();
        if ($user_result) {
            $current_user = $user_result;
        }
    } catch (PDOException $e) { }
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2x/Logo-Van-Van-1.png">
    <title>របាយការណ៍វត្តមានបុគ្គលិក</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        :root {
            --color-blue: #05165E;
            --color-gold: #e6cd14;
            --color-green: #1A936F;
            --color-red: #C81D25;
            --color-light-gray: #f8f9fa;
            --color-border: #E0E0E0;
            --color-dark: #000000;
            --font-family: 'Noto Sans Khmer', sans-serif;
            --font-koulen: 'Koulen', sans-serif;
            --font-Battambang: 'Kh Battambang', sans-serif;
        }
        body {
            font-family: var(--font-family);
            margin: 0;
            padding: 20px;
            background-color: #eef2f5;
            color: var(--color-dark);
            font-size: 16px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 25px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        h2 {
            text-align: center;
            color: var(--color-dark);
            margin-bottom: 25px;
            font-weight: 700;
        }
        /* [ថ្មី] រចនាសម្ព័ន្ធសម្រាប់ផ្នែកបញ្ជាខាងលើ */
        .top-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .date-filter-form { /* Renamed for clarity */
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .action-buttons { /* New container for buttons */
            display: flex;
            gap: 10px;
        }
        .date-display {
            text-align: center;
            padding: 10px;
            border: 1px solid var(--color-border);
            border-radius: 5px;
            margin-bottom: 25px;
            font-weight: 500;
            background-color: var(--color-light-gray);
        }
        .action-btn, .bulk-action-btn, .add-btn-container button, .screenshot-btn {
            padding: 8px 18px;
            margin: 0;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: var(--font-koulen);
            font-size: 15px;
            font-weight: 500;
            transition: filter 0.2s ease, box-shadow 0.2s ease;
        }
        .action-btn:hover, .bulk-action-btn:hover, .add-btn-container button:hover, .screenshot-btn:hover {
            filter: brightness(90%);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .edit-btn { background-color: var(--color-green); }
        .delete-btn { background-color: var(--color-red); }
        .add-btn { background-color: var(--color-green); }
        .add-btn-container { text-align: center; margin: 25px 0; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            border: 1px solid var(--color-border);
            border-radius: 5px;
            overflow: hidden;
        }
        th, td {
            border: 1px solid var(--color-border);
            padding: 10px;
            text-align: center;
            vertical-align: middle;
        }
        th {
            background-color: var(--color-blue);
            font-family: var(--font-Battambang);
            font-size: 14px;
            color: white;
            font-weight: 700;
        }
        .section-header {
            background-color: var(--color-gold);
            font-family: var(--font-koulen);
            color: var(--color-dark);
            font-weight: 500;
            font-size: 18px;
        }
        .total-row {
            background-color: var(--color-gold);
            color: var(--color-dark);
            font-weight: 700;
        }
        .overall-total-row {
            font-weight: 700;
            background-color: var(--color-light-gray);
        }
        .inline-edit-input {
            width: 90%;
            max-width: 120px;
            box-sizing: border-box;
            padding: 6px;
            font-family: var(--font-koulen);
            text-align: center;
            border: 1px solid var(--color-blue);
            border-radius: 4px;
            font-size: 15px;
        }
        .editable-cell { padding: 4px; }
        .editable-cell span { padding: 6px; display: inline-block; min-width: 50px; }
        input[type="number"]::-webkit-inner-spin-button, 
        input[type="number"]::-webkit-outer-spin-button { 
            -webkit-appearance: none; 
            margin: 0; 
        }
        input[type="number"] { -moz-appearance: textfield; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal.show { opacity: 1; }
        @keyframes slideDown { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-content { background-color: #fff; margin: auto; padding: 25px; border-radius: 8px; width: 90%; max-width: 500px; box-shadow: 0 5px 20px rgba(0,0,0,0.2); animation: slideDown 0.4s ease-out; }
        .modal-content h3 { margin-top: 0; text-align: center; }
        .modal-content label { display: block; margin: 15px 0 5px; font-weight: 500; }
        .modal-content input { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid var(--color-border); border-radius: 4px; }
        .modal-content .modal-actions { text-align: right; margin-top: 25px; }
        .footer { text-align: right; margin-top: 50px; padding-right: 50px; }
        .footer p { margin: 5px 0; }
        .footer .signature { margin-top: 60px; font-weight: 700; }
        
        /* [ថ្មី] CSS សម្រាប់ Screenshot */
        .screenshot-btn { background-color: #fd7e14; }
        #screenshotModal .modal-content { max-width: 95%; width: auto; }
        #screenshotModal .modal-body { padding: 25px; max-height: 75vh; overflow-y: auto; text-align: center; }
        #screenshotPreview { max-width: 100%; height: auto; border: 1px solid #ddd; }
        #spinner { border: 8px solid #f3f3f3; border-top: 8px solid #3498db; border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        #screenshotModal .modal-header h2 { margin: 0; font-size: 22px; color: #333; }
        #screenshotModal .modal-close-btn { font-size: 28px; font-weight: bold; color: #888; cursor: pointer; background: none; border: none; padding: 0; }
        #screenshotModal .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 25px; border-bottom: 1px solid var(--color-border); }
        #screenshotModal .modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 18px 25px; border-top: 1px solid var(--color-border); }
        #screenshotModal .btn { padding: 10px 22px; border-radius: 6px; font-weight: 600; }
        #screenshotModal .btn-primary { background-color: #0d6efd; color: white; border:none; }
        #screenshotModal .btn-secondary { background-color: #6c757d; color: white; border:none; }
        #toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; }
        .toast { padding: 15px 20px; margin-top: 10px; border-radius: 5px; color: white; font-size: 16px; opacity: 0; transform: translateY(20px); transition: all 0.3s ease-in-out; }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast-success { background-color: #28a745; }
        .toast-error { background-color: #dc3545; }

        .hide-for-screenshot .action-buttons,
        .hide-for-screenshot .actions-column,
        .hide-for-screenshot .add-btn-container,
        .hide-for-screenshot .top-controls {
            display: none !important;
        }
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
    <div class="top-controls">
        <form method="GET" action="" class="date-filter-form">
            <label for="date_filter">កាលបរិច្ឆេទ:</label>
            <input type="date" id="date_filter" name="report_date" value="<?php echo htmlspecialchars($report_date); ?>" onchange="this.form.submit()">
        </form>
        <div class="action-buttons">
             <button type="button" id="editAllBtn" class="bulk-action-btn edit-btn">កែសម្រួល</button>
             <button type="submit" id="saveAllBtn" form="mainReportForm" class="bulk-action-btn edit-btn" style="display:none;">រក្សាទុក</button>
             <button type="button" id="cancelBtn" class="bulk-action-btn delete-btn" style="display:none;">បោះបង់</button>
             <button type="button" id="screenshotBtn" class="screenshot-btn"><i class="fas fa-camera"></i> ថតរូប</button>
        </div>
    </div>
    
    <div id="capture-area">
        <div class="date-display">
            <h2 style="font-family: Khmer OS Muol Light;">របាយការណ៍វត្តមានបុគ្គលិក និងចំនួនយានយន្តការដឹកជញ្ជូន ឃ្លាំងចំការ</h2>
            <?php
                // បង្ហាញថ្ងៃខែឆ្នាំជាភាសាខ្មែរ (day និង year ជាលេខខ្មែរ)
                function toKhmerNumber($number) {
                    $khmerDigits = ['០','១','២','៣','៤','៥','៦','៧','៨','៩'];
                    return str_replace(range(0,9), $khmerDigits, $number);
                }
                try {
                    $date_obj = new DateTime($report_date);

                    $khmer_days = [
                        'អាទិត្យ', 'ច័ន្ទ', 'អង្គារ', 'ពុធ', 'ព្រហស្បតិ៍', 'សុក្រ', 'សៅរ៍'
                    ];
                    $khmer_months = [
                        'មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា',
                        'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ'
                    ];

                    $weekday_index = (int)$date_obj->format('w'); // 0 (អាទិត្យ) ... 6 (សៅរ៍)
                    $day = toKhmerNumber($date_obj->format('d'));
                    $month_index = (int)$date_obj->format('m') - 1;
                    $year = toKhmerNumber($date_obj->format('Y'));

                    $khmer_date_string = " ថ្ងៃ ". $khmer_days[$weekday_index] . " ទី".$day . " ខែ".$khmer_months[$month_index] . " ឆ្នាំ " . $year;
                    echo '<h2 class="dateofday">' . htmlspecialchars($khmer_date_string) . '</h2>';
                } catch(Exception $e) {
                    // មិនបង្ហាញអ្វីទេបើកាលបរិច្ឆេទមិនត្រឹមត្រូវ
                }
            ?>
        </div>
        
        <form id="mainReportForm" method="POST">
            <input type="hidden" name="update_all_data" value="1">
            <input type="hidden" name="report_date" value="<?php echo htmlspecialchars($report_date); ?>">
            
            <table id="vehicleLoadingTable">
                <tr><th colspan="5" class="section-header">ផ្នែកបើករថយន្ត រ៉ឺម៉កកង់បី និងផ្នែកលើកទំនិញ</th></tr>
                 <tr>
                     <th>ផ្នែកបើករថយន្ត</th><th>ផ្នែកបើកម៉ូតូកង់បី</th><th>ដើរតាមឡាន</th><th>ដើរតាមកង់បី</th><th>ដើរតាមឃ្លាំង</th>
                 </tr>
                 <tr>
                     <td class="editable-cell"><span><?php echo (int)($vehicle_loading['vehicle_drivers'] ?? 0); ?> នាក់</span><input type="number" name="vehicle_drivers" value="<?php echo (int)($vehicle_loading['vehicle_drivers'] ?? 0); ?>" class="inline-edit-input" style="display:none;"></td>
                     <td class="editable-cell"><span><?php echo (int)($vehicle_loading['tricycle_drivers'] ?? 0); ?> នាក់</span><input type="number" name="tricycle_drivers" value="<?php echo (int)($vehicle_loading['tricycle_drivers'] ?? 0); ?>" class="inline-edit-input" style="display:none;"></td>
                     <td class="editable-cell"><span><?php echo (int)($vehicle_loading['load_follow_vehicle'] ?? 0); ?> នាក់</span><input type="number" name="load_follow_vehicle" value="<?php echo (int)($vehicle_loading['load_follow_vehicle'] ?? 0); ?>" class="inline-edit-input" style="display:none;"></td>
                     <td class="editable-cell"><span><?php echo (int)($vehicle_loading['load_follow_tricycle'] ?? 0); ?> នាក់</span><input type="number" name="load_follow_tricycle" value="<?php echo (int)($vehicle_loading['load_follow_tricycle'] ?? 0); ?>" class="inline-edit-input" style="display:none;"></td>
                     <td class="editable-cell"><span><?php echo (int)($vehicle_loading['load_follow_warehouse'] ?? 0); ?> នាក់</span><input type="number" name="load_follow_warehouse" value="<?php echo (int)($vehicle_loading['load_follow_warehouse'] ?? 0); ?>" class="inline-edit-input" style="display:none;"></td>
                 </tr>
                 <?php $sum_vehicle = array_sum(array_intersect_key($vehicle_loading, array_flip(['vehicle_drivers', 'tricycle_drivers', 'load_follow_vehicle', 'load_follow_tricycle', 'load_follow_warehouse']))); ?>
                 <tr class="total-row"><td colspan="5"><?php echo $sum_vehicle; ?> នាក់</td></tr>
            </table>

            <table id="availableVehiclesTable">
                <tr><th colspan="4" class="section-header">ចំនួនរថយន្ត រ៉ឺម៉កកង់បី ដែលអាចប្រើប្រាស់បាន</th></tr>
                 <tr>
                     <th>រថយន្ត(ឡាន)</th><th>រ៉ឺម៉កកង់បី</th><th>សរុប រថយន្ត</th><th>សរុប រ៉ឺម៉កកង់បី</th>
                 </tr>
                 <tr>
                     <td class="editable-cell"><span><?php echo (int)($vehicles['cars'] ?? 0); ?> គ្រឿង</span><input type="number" name="cars" value="<?php echo (int)($vehicles['cars'] ?? 0); ?>" class="inline-edit-input" style="display:none;"></td>
                     <td class="editable-cell"><span><?php echo (int)($vehicles['tricycles'] ?? 0); ?> គ្រឿង</span><input type="number" name="tricycles" value="<?php echo (int)($vehicles['tricycles'] ?? 0); ?>" class="inline-edit-input" style="display:none;"></td>
                     <td><?php echo (int)($vehicles['cars'] ?? 0); ?> គ្រឿង</td>
                     <td><?php echo (int)($vehicles['tricycles'] ?? 0); ?> គ្រឿង</td>
                 </tr>
            </table>

            <table id="staffStatusTable">
                <thead>
                    <tr><th colspan="8" class="section-header">បុគ្គលិកសុំច្បាប់ ដេអូស ប្តូរដេអូស និងចូលថ្មី</th></tr>
                    <tr>
                        <th rowspan="2">ល.រ</th><th rowspan="2">ឈ្មោះ</th><th rowspan="2">តួនាទី</th><th rowspan="2">អធិប្បាយ</th><th colspan="3">បុគ្គលិកចូលថ្មី</th>
                        <th rowspan="2" class="actions-column">សកម្មភាព</th>
                    </tr>
                    <tr>
                        <th>ការិយាល័យកណ្តាល</th><th>៣១៨</th><th>ឃ្លាំង</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($staff_status_data): ?>
                        <?php foreach ($staff_status_data as $i => $staff): ?>
                        <tr>
                            <input type="hidden" name="ids[<?php echo $i; ?>]" value="<?php echo $staff['id']; ?>">
                            <td class="editable-cell"><span><?php echo htmlspecialchars($staff['serial_no']); ?></span><input type="text" name="serial_no[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($staff['serial_no']); ?>" class="inline-edit-input" style="display:none;"></td>
                            <td class="editable-cell"><span><?php echo htmlspecialchars($staff['staff_name']); ?></span><input type="text" name="staff_name[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($staff['staff_name']); ?>" class="inline-edit-input" style="display:none;"></td>
                            <td class="editable-cell"><span><?php echo htmlspecialchars($staff['role']); ?></span><input type="text" name="role[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($staff['role']); ?>" class="inline-edit-input" style="display:none;"></td>
                            <td class="editable-cell"><span><?php echo htmlspecialchars($staff['comment']); ?></span><input type="text" name="comment[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($staff['comment']); ?>" class="inline-edit-input" style="display:none;"></td>
                            <td class="editable-cell"><span><?php echo (int)$staff['new_staff_central']; ?></span><input type="number" name="new_staff_central[<?php echo $i; ?>]" value="<?php echo (int)$staff['new_staff_central']; ?>" class="inline-edit-input" style="display:none;"></td>
                            <td class="editable-cell"><span><?php echo (int)$staff['new_staff_318']; ?></span><input type="number" name="new_staff_318[<?php echo $i; ?>]" value="<?php echo (int)$staff['new_staff_318']; ?>" class="inline-edit-input" style="display:none;"></td>
                            <td class="editable-cell"><span><?php echo (int)$staff['new_staff_warehouse']; ?></span><input type="number" name="new_staff_warehouse[<?php echo $i; ?>]" value="<?php echo (int)$staff['new_staff_warehouse']; ?>" class="inline-edit-input" style="display:none;"></td>
                            <td class="actions-column"><a href="attendance.php?report_date=<?php echo urlencode($report_date); ?>&delete_id=<?php echo $staff['id']; ?>" class="action-btn delete-btn delete-row-btn" onclick="return confirm('តើអ្នកប្រាកដជាចង់លុបទិន្នន័យនេះមែនទេ?')">លុប</a></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8">មិនមានទិន្នន័យសម្រាប់ថ្ងៃនេះ</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="add-btn-container">
                <button type="button" class="add-btn" onclick="openModal('insertModal')">បន្ថែមបុគ្គលិក</button>
            </div>
            
            <table id="overallTotalTable">
                <tr><th colspan="4" class="section-header">សរុបរួម</th></tr>
                 <tr><th>ផ្នែកបើករថយន្ត</th><th>ផ្នែកបើកម៉ូតូកង់បី</th><th>ផ្នែកលើកទំនិញ</th><th>សរុបទាំងអស់</th></tr>
                 <tr class="overall-total-row">
                     <td><?php echo (int)($vehicle_loading['vehicle_drivers'] ?? 0); ?> នាក់</td>
                     <td><?php echo (int)($vehicle_loading['tricycle_drivers'] ?? 0); ?> នាក់</td>
                     <td><?php echo (int)($vehicle_loading['load_follow_vehicle'] ?? 0) + (int)($vehicle_loading['load_follow_tricycle'] ?? 0) + (int)($vehicle_loading['load_follow_warehouse'] ?? 0); ?> នាក់</td>
                     <td><?php echo $sum_vehicle; ?> នាក់</td>
                 </tr>
            </table>

            <div class="footer">
                <p>រាជធានីភ្នំពេញ ថ្ងៃទី <?php echo date('d', strtotime($report_date)); ?> ខែ <?php echo date('m', strtotime($report_date)); ?> ឆ្នាំ <?php echo date('Y', strtotime($report_date)); ?></p>
                <p>អ្នករៀបចំរបាយការណ៍</p>
                <div class="signature editable-cell" style="padding: 4px;">
                    <span><?php echo htmlspecialchars($current_user); ?></span>
                    <input type="text" name="creator_full_name" value="<?php echo htmlspecialchars($current_user); ?>" class="inline-edit-input" style="display:none; width: 200px;">
                </div>
            </div>
        </form>
    </div> </div>

<div id="insertModal" class="modal">
    <div class="modal-content">
        <form method="POST">
            <h3>បន្ថែមបុគ្គលិក</h3>
            <input type="hidden" name="insert_staff" value="1">
            <input type="hidden" name="report_date" value="<?php echo htmlspecialchars($report_date); ?>">
            <label>ល.រ:</label><input type="text" name="serial_no" required>
            <label>ឈ្មោះ:</label><input type="text" name="staff_name" required>
            <label>តួនាទី:</label><input type="text" name="role" required>
            <label>អធិប្បាយ:</label><input type="text" name="comment">
            <label>បុគ្គលិកថ្មី ការិយាល័យកណ្តាល:</label><input type="number" name="new_staff_central" value="0">
            <label>បុគ្គលិកថ្មី ៣១៨:</label><input type="number" name="new_staff_318" value="0">
            <label>បុគ្គលិកថ្មី ឃ្លាំង:</label><input type="number" name="new_staff_warehouse" value="0">
            <div class="modal-actions">
                <button type="submit" class="action-btn edit-btn">រក្សាទុក</button>
                <button type="button" class="action-btn delete-btn" onclick="closeModal('insertModal')">បោះបង់</button>
            </div>
        </form>
    </div>
</div>

<div id="toast-container"></div>
<div id="screenshotModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>រូបភាពតារាងទិន្នន័យ</h2>
            <button class="modal-close-btn" onclick="closeModal('screenshotModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="spinner-container">
                <div id="spinner"></div>
                <p>កំពុងបង្កើតរូបភាព...</p>
            </div>
            <img id="screenshotPreview" src="" alt="Screenshot Preview" style="display: none;">
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('screenshotModal')">បិទ</button>
            <button type="button" class="btn btn-primary" id="copyImageBtn">ចម្លងរូបភាព</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const editAllBtn = document.getElementById('editAllBtn');
    const saveAllBtn = document.getElementById('saveAllBtn');
    const cancelBtn = document.getElementById('cancelBtn');

    if (editAllBtn) {
        editAllBtn.addEventListener('click', () => enableGlobalEdit(true));
    }
    
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => location.reload());
    }

    function enableGlobalEdit(isEditing) {
        editAllBtn.style.display = isEditing ? 'none' : 'inline-block';
        saveAllBtn.style.display = isEditing ? 'inline-block' : 'none';
        cancelBtn.style.display = isEditing ? 'inline-block' : 'none';

        document.querySelectorAll('.delete-row-btn').forEach(btn => {
            btn.style.display = isEditing ? 'none' : 'inline-block';
        });

        document.querySelectorAll('.editable-cell').forEach(cell => {
            const span = cell.querySelector('span');
            const input = cell.querySelector('input');
            if (span && input) {
                span.style.display = isEditing ? 'none' : 'inline-block';
                input.style.display = isEditing ? 'inline-block' : 'none';
            }
        });
        
        const addBtnContainer = document.querySelector('.add-btn-container');
        if (addBtnContainer) {
            addBtnContainer.style.display = isEditing ? 'none' : 'block';
        }
    }
});

// --- [ថ្មី] JavaScript សម្រាប់ Modal, Screenshot, និងមុខងារពាក់ព័ន្ធ ---

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('show'), 10);
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.classList.remove('show');
    setTimeout(() => { modal.style.display = 'none'; }, 300);
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal(event.target.id);
    }
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

const screenshotBtn = document.getElementById('screenshotBtn');
const screenshotModal = document.getElementById('screenshotModal');
const copyImageBtn = document.getElementById('copyImageBtn');
const captureArea = document.getElementById('capture-area');
let imageBlob = null;

screenshotBtn.addEventListener('click', async () => {
    const spinnerContainer = document.getElementById('spinner-container');
    const screenshotPreview = document.getElementById('screenshotPreview');
    
    screenshotPreview.style.display = 'none';
    spinnerContainer.style.display = 'block';
    openModal('screenshotModal');
    
    // Hide buttons before capture
    captureArea.classList.add('hide-for-screenshot');

    try {
        const canvas = await html2canvas(captureArea, {
            scale: 2,
            backgroundColor: '#ffffff',
            onclone: (doc) => {
                // This function runs on a clone of the document before screenshot.
                // It ensures that even in edit mode, the values are displayed as text.
                doc.querySelectorAll('.editable-cell').forEach(cell => {
                    const span = cell.querySelector('span');
                    const input = cell.querySelector('input');
                    if (span && input) {
                        span.textContent = input.value + (span.textContent.includes('នាក់') ? ' នាក់' : '') + (span.textContent.includes('គ្រឿង') ? ' គ្រឿង' : '');
                        span.style.display = 'inline-block';
                        input.style.display = 'none';
                    }
                });
            }
        });
        
        screenshotPreview.src = canvas.toDataURL('image/png');
        screenshotPreview.style.display = 'block';

        canvas.toBlob(blob => { imageBlob = blob; });
        
    } catch (error) {
        console.error('Error taking screenshot:', error);
        showToast('មានបញ្ហាក្នុងការបង្កើតរូបភាព', 'error');
        closeModal('screenshotModal');
    } finally {
        // Show buttons again after capture
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
            new ClipboardItem({ 'image/png': imageBlob })
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