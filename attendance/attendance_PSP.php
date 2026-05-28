<?php
// Database connection
session_start();
require_once 'db_connect.php';

// if (!isset($_SESSION['username'])) {
//     header("Location: https://app.vvc.asia/login.php");
//     exit();
// }

$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// --- [PHP code remains the same] ---
$stmt = $pdo->prepare("SELECT id FROM reports_date_ch1 WHERE report_date = ?");
$stmt->execute([$selected_date]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);
$report_id = $report ? $report['id'] : null;

if (!$report_id) {
    $stmt = $pdo->prepare("INSERT INTO reports_date_ch1 (report_date) VALUES (?)");
    $stmt->execute([$selected_date]);
    $report_id = $pdo->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_staff'])) {
        // Check if record exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicle_staff_ch1 WHERE report_id = ?");
        $stmt->execute([$report_id]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            // Update existing record
            $stmt = $pdo->prepare("UPDATE vehicle_staff_ch1 SET 
                drivers = ?, tricycle_318 = ?, tricycle_warehouse = ?, 
                loaders_truck = ?, loaders_318 = ?, loaders_tricycle = ?, 
                loaders_warehouse = ? WHERE report_id = ?");
            $stmt->execute([
                $_POST['drivers'],
                $_POST['tricycle_318'],
                $_POST['tricycle_warehouse'],
                $_POST['loaders_truck'],
                $_POST['loaders_318'],
                $_POST['loaders_tricycle'],
                $_POST['loaders_warehouse'],
                $report_id
            ]);
        } else {
            // Insert new record
            $stmt = $pdo->prepare("INSERT INTO vehicle_staff_ch1 
                (report_id, drivers, tricycle_318, tricycle_warehouse, loaders_truck, loaders_318, loaders_tricycle, loaders_warehouse) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $report_id,
                $_POST['drivers'],
                $_POST['tricycle_318'],
                $_POST['tricycle_warehouse'],
                $_POST['loaders_truck'],
                $_POST['loaders_318'],
                $_POST['loaders_tricycle'],
                $_POST['loaders_warehouse']
            ]);
        }
    }
    
    if (isset($_POST['update_vehicles'])) {
        // Check if record exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM vehicles_ch1 WHERE report_id = ?");
        $stmt->execute([$report_id]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            // Update existing record
            $stmt = $pdo->prepare("UPDATE vehicles_ch1 SET 
                trucks = ?, tricycle_318 = ?, tricycle_warehouse = ? 
                WHERE report_id = ?");
            $stmt->execute([
                $_POST['trucks'],
                $_POST['tricycle_318'],
                $_POST['tricycle_warehouse'],
                $report_id
            ]);
        } else {
            // Insert new record
            $stmt = $pdo->prepare("INSERT INTO vehicles_ch1 
                (report_id, trucks, tricycle_318, tricycle_warehouse) 
                VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $report_id,
                $_POST['trucks'],
                $_POST['tricycle_318'],
                $_POST['tricycle_warehouse']
            ]);
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?date=$selected_date");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM vehicle_staff_ch1 WHERE report_id = ?");
$stmt->execute([$report_id]);
$vehicle_staff = $stmt->fetch(PDO::FETCH_ASSOC) ?: array_fill_keys(['drivers', 'tricycle_318', 'tricycle_warehouse', 
    'loaders_truck', 'loaders_318', 'loaders_tricycle', 'loaders_warehouse'], 0);

$stmt = $pdo->prepare("SELECT * FROM vehicles_ch1 WHERE report_id = ?");
$stmt->execute([$report_id]);
$vehicles = $stmt->fetch(PDO::FETCH_ASSOC) ?: array_fill_keys(['trucks', 'tricycle_318', 'tricycle_warehouse'], 0);
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2x/Logo-Van-Van-1.png">
    <title>មើលទិន្នន័យបុគ្គលិកតាមថ្ងៃ</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        body {
            font-family: 'Noto Sans Khmer', sans-serif;
            margin: 20px;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #05165E;
            font-family: 'Noto Sans Khmer', sans-serif; /* Changed for consistency */
            color: white;
            font-size: 14px;
        }
        .section-header {
            background-color: #e6cd14;
            color: black;
            font-size: 18px;
            font-family: 'Koulen', sans-serif; /* Ensure Koulen font is loaded if used */
            font-weight: 500;
        }
        .overall-total {
            background-color: #ececec9c;
            font-weight: bold;
            font-size: 1.1em;
        }
        .top-controls {
             display: flex; 
             justify-content: center; 
             align-items: center; 
             gap: 15px; 
             margin-bottom: 20px;
        }
        .date-form {
             display: flex; 
             align-items: center; 
             gap: 10px; 
        }
        .general-comment {
            text-align: center;
            margin-bottom: 20px;
        }
        .general-comment input {
            padding: 5px;
            border-radius: 4px;
            font-family: 'Noto Sans Khmer', sans-serif;
            border:none;
            width: 300px;
            text-align: center;
        }
        input[type="date"] {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Noto Sans Khmer', sans-serif;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Noto Sans Khmer', sans-serif;
        }
        button:hover {
            background-color: #45a049;
        }
        .input-data-btn {
            background-color: #2196F3;
            padding: 8px 15px;
            margin: 0 auto;
            display: block;
            width: 150px;
            text-align: center;
        }
        .input-data-btn:hover {
            background-color: #1976D2;
        }
        .action-btn {
            padding: 5px 10px;
            margin: 2px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            color: white;
            text-decoration: none;
            display: inline-block;
        }
        .add-btn { background-color: #2196F3; }
        .edit-btn { background-color: #FFC107; }
        .back-btn { background-color: #05165E; }
        .delete-btn { background-color: #f44336; }
        .action-btn:hover { opacity: 0.9; }
        .editable-cell input[type="number"] {
            width: 60px;
            padding: 4px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }
        .edit-form { margin-top: 10px; }

        /* [ថ្មី] CSS សម្រាប់ Screenshot */
        .screenshot-btn { background-color: #fd7e14; padding: 5px 12px; }
        .screenshot-btn:hover { background-color: #e46d0a; }

        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; }
        .modal.show { opacity: 1; }
        @keyframes slideDown { from { transform: translateY(-30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-content { background-color: #ffffff; margin: auto; border: none; border-radius: 12px; width: 90%; max-width: 95%; width: auto; box-shadow: 0 5px 20px rgba(0,0,0,0.2); animation: slideDown 0.4s ease-out; overflow: hidden; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 18px 25px; background-color: #f7f9fc; border-bottom: 1px solid #e9ecef; }
        .modal-header h2 { margin: 0; font-size: 22px; color: #333; }
        .modal-close-btn { font-size: 28px; font-weight: bold; color: #888; cursor: pointer; background: none; border: none; padding: 0; }
        .modal-close-btn:hover { color: #333; }
        .modal-body { padding: 25px; max-height: 75vh; overflow-y: auto; text-align: center; }
        #screenshotPreview { max-width: 100%; height: auto; border: 1px solid #ddd; }
        #spinner { border: 8px solid #f3f3f3; border-top: 8px solid #3498db; border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 18px 25px; background-color: #f7f9fc; border-top: 1px solid #e9ecef; }
        .modal .btn { padding: 10px 22px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 15px; }
        .modal .btn-primary { background-color: #0d6efd; color: white; }
        .modal .btn-secondary { background-color: #6c757d; color: white; }
        
        #toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 9999; }
        .toast { padding: 15px 20px; margin-top: 10px; border-radius: 5px; color: white; font-size: 16px; opacity: 0; transform: translateY(20px); transition: all 0.3s ease-in-out; }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast-success { background-color: #28a745; }
        .toast-error { background-color: #dc3545; }

        .hide-for-screenshot .actions-column,
        .hide-for-screenshot .edit-form button,
        .hide-for-screenshot .input-data-btn,
        .hide-for-screenshot .add-btn,
        .hide-for-screenshot .final-links {
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
            <a href="attendance_menu.php" class="action-btn back-btn"><i class="fas fa-arrow-left"></i> ត្រឡប់ក្រោយ</a>
            <div class="date-form">
                <form method="GET" action="">
                    <label for="date">ជ្រើសរើសថ្ងៃ: </label>
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($selected_date); ?>">
                    <button type="submit">មើល</button>
                </form>
            </div>
            <button type="button" class="screenshot-btn" id="screenshotBtn" title="ថតរូបតារាង">
                <i class="fas fa-camera"></i> ថតរូបតារាង
            </button>
        </div>
        
        <div id="capture-area">
            <h1>របាយការណ៍វត្តមានបុគ្គលិក និងចំនួនរថយន្តសម្រាប់ដឹកជញ្ជូននៅ PSP</h1>

<?php
                // បង្ហាញថ្ងៃខែឆ្នាំជាភាសាខ្មែរ (day និង year ជាលេខខ្មែរ)
                function toKhmerNumber($number) {
                    $khmerDigits = ['០','១','២','៣','៤','៥','៦','៧','៨','៩'];
                    return str_replace(range(0,9), $khmerDigits, $number);
                }
                try {
                    $date_obj = new DateTime($selected_date);

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

            <form method="POST" class="edit-form">
                <table>
                    <tr><th colspan="7" class="section-header">ផ្នែកបុគ្គលិកវថយន្ត កង់បី និងផ្នែកលើកទំនិញ</th></tr>
                    <tr>
                        <th rowspan="2">បុគ្គលិកផ្នែកបើករថយន្ត</th>
                        <th colspan="2">បុគ្គលិកផ្នែកបើករ៉ឺម៉កកង់បី</th>
                        <th colspan="4">បុគ្គលិកផ្នែកលើកទំនិញ</th>
                    </tr>
                    <tr>
                        <th>៣១៨</th>
                        <th>តាមឃ្លាំង</th>
                        <th>ដើរតាមឡាន</th>
                        <th>៣១៨</th>
                        <th>រ៉ឺម៉កកង់បី</th>
                        <th>តាមឃ្លាំង</th>
                    </tr>
                    <tr class="editable-cell">
                        <td><input type="number" name="drivers" value="<?php echo $vehicle_staff['drivers']; ?>" min="0"></td>
                        <td><input type="number" name="tricycle_318" value="<?php echo $vehicle_staff['tricycle_318']; ?>" min="0"></td>
                        <td><input type="number" name="tricycle_warehouse" value="<?php echo $vehicle_staff['tricycle_warehouse']; ?>" min="0"></td>
                        <td><input type="number" name="loaders_truck" value="<?php echo $vehicle_staff['loaders_truck']; ?>" min="0"></td>
                        <td><input type="number" name="loaders_318" value="<?php echo $vehicle_staff['loaders_318']; ?>" min="0"></td>
                        <td><input type="number" name="loaders_tricycle" value="<?php echo $vehicle_staff['loaders_tricycle']; ?>" min="0"></td>
                        <td><input type="number" name="loaders_warehouse" value="<?php echo $vehicle_staff['loaders_warehouse']; ?>" min="0"></td>
                    </tr>
                </table>
                <button type="submit" name="update_staff" class="action-btn edit-btn" style="margin: 10px auto; display: block;">រក្សាទុក</button>
            </form>

            <form method="POST" class="edit-form">
                <table>
                    <tr><th colspan="5" class="section-header"></th></tr>
                    <tr>
                        <th rowspan="2">រថយន្ត(ឡាន)</th>
                        <th colspan="2">ម៉ូតូរ៉ឺម៉កកង់បី</th>
                        <th colspan="2">សរុប</th>
                    </tr>
                    <tr>
                        <th>៣១៨</th>
                        <th>តាមឃ្លាំង</th>
                        <th>រថយន្ត</th>
                        <th>រ៉ឺម៉កកង់បី</th>
                    </tr>
                    <tr class="editable-cell">
                        <td><input type="number" name="trucks" value="<?php echo $vehicles['trucks']; ?>" min="0"></td>
                        <td><input type="number" name="tricycle_318" value="<?php echo $vehicles['tricycle_318']; ?>" min="0"></td>
                        <td><input type="number" name="tricycle_warehouse" value="<?php echo $vehicles['tricycle_warehouse']; ?>" min="0"></td>
                        <td id="total_trucks"><?php echo $vehicles['trucks']; ?> គ្រឿង</td>
                        <td id="total_tricycles_vehicle"><?php echo $vehicles['tricycle_318'] + $vehicles['tricycle_warehouse']; ?> គ្រឿង</td>
                    </tr>
                </table>
                <button type="submit" name="update_vehicles" class="action-btn edit-btn" style="margin: 10px auto; display: block;">រក្សាទុក</button>
            </form>

            <table>
                <tr>
                    <th colspan="8" class="section-header">
                        បុគ្គលិកសុំច្បាប់ ដេអូស ប្តូរដេអូស និងចូលថ្មី
                        <button onclick="addRequest()" class="action-btn add-btn" style="float: right; margin: 5px;">បន្ថែម</button>
                    </th>
                </tr>
                <tr>
                    <th>ល.រ</th>
                    <th>ឈ្មោះ</th>
                    <th>តួនាទី</th>
                    <th>អធិប្បាយ</th>
                    <th>ផ្នែកបើកកង់បី</th>
                    <th>ផ្នែកលើកទំនិញ</th>
                    <th>រថយន្ត</th>
                    <th class="actions-column">សកម្មភាព</th>
                </tr>
                <?php
                try {
                    $stmt = $pdo->prepare("SELECT * FROM staff_requests_ch1 WHERE report_id = ?");
                    $stmt->execute([$report_id]);
                    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if ($requests) {
                        foreach ($requests as $request) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($request['request_id']) . "</td>";
                            echo "<td>" . htmlspecialchars($request['name']) . "</td>";
                            echo "<td>" . htmlspecialchars($request['role']) . "</td>";
                            echo "<td>" . htmlspecialchars($request['comment']) . "</td>";
                            echo "<td>" . ($request['tricycle_dept'] ? '✓' : '') . "</td>";
                            echo "<td>" . ($request['loading_dept'] ? '✓' : '') . "</td>";
                            echo "<td>" . ($request['truck_dept'] ? '✓' : '') . "</td>";
                            // [ថ្មី] បន្ថែម class ដើម្បីងាយស្រួលលាក់
                            echo "<td class='actions-column'>";
                            echo "<button onclick=\"editRequest('" . htmlspecialchars($request['request_id']) . "')\" class=\"action-btn edit-btn\">កែ</button>";
                            echo "<button onclick=\"confirmDelete('" . htmlspecialchars($request['request_id']) . "')\" class=\"action-btn delete-btn\">លុប</button>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='8'>មិនមានទិន្នន័យសម្រាប់ថ្ងៃនេះ</td></tr>";
                    }
                } catch (PDOException $e) {
                    echo "<tr><td colspan='8'>កំហុស: " . $e->getMessage() . "</td></tr>";
                }
                ?>
            </table>

            <?php
            $total_drivers = $vehicle_staff['drivers'];
            $total_tricycles = $vehicle_staff['tricycle_318'] + $vehicle_staff['tricycle_warehouse'];
            $total_loaders = $vehicle_staff['loaders_truck'] + $vehicle_staff['loaders_318'] + 
                             $vehicle_staff['loaders_tricycle'] + $vehicle_staff['loaders_warehouse'];
            $grand_total = $total_drivers + $total_tricycles + $total_loaders;
            ?>
            <table>
                <tr><th colspan="4" class="section-header">សរុបរួម</th></tr>
                <tr>
                    <th>ផ្នែកបើករថយន្ត</th>
                    <th>ម៉ូតូរ៉ឺម៉កកង់បី</th>
                    <th>លើកទំនិញ</th>
                    <th>សរុបទាំងអស់</th>
                </tr>
                <tr class="overall-total">
                    <td id="total_drivers"><?php echo $total_drivers; ?> នាក់</td>
                    <td id="total_tricycles"><?php echo $total_tricycles; ?> នាក់</td>
                    <td id="total_loaders"><?php echo $total_loaders; ?> នាក់</td>
                    <td id="grand_total"><?php echo $grand_total; ?> នាក់</td>
                </tr>
            </table>

            <button class="input-data-btn" onclick="window.location.href='input_data.php'">បញ្ចូលទិន្នន័យ</button>
        </div> </div>

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
        function confirmDelete(requestId) {
            if (confirm('តើអ្នកប្រាកដទេថាចង់លុបទិន្នន័យនេះ?')) {
                window.location.href = 'delete_request_ch1.php?id=' + requestId + '&date=<?php echo $selected_date; ?>';
            }
        }

        function editRequest(requestId) {
            window.location.href = 'edit_request_ch1.php?id=' + requestId + '&date=<?php echo $selected_date; ?>';
        }

        function addRequest() {
            window.location.href = 'add_request_ch1.php?date=<?php echo $selected_date; ?>';
        }

        function updateTotals() {
            const drivers = parseInt(document.getElementsByName('drivers')[0].value) || 0;
            const tricycle_318 = parseInt(document.getElementsByName('tricycle_318')[0].value) || 0;
            const tricycle_warehouse = parseInt(document.getElementsByName('tricycle_warehouse')[0].value) || 0;
            const loaders_truck = parseInt(document.getElementsByName('loaders_truck')[0].value) || 0;
            const loaders_318 = parseInt(document.getElementsByName('loaders_318')[0].value) || 0;
            const loaders_tricycle = parseInt(document.getElementsByName('loaders_tricycle')[0].value) || 0;
            const loaders_warehouse = parseInt(document.getElementsByName('loaders_warehouse')[0].value) || 0;

            const total_tricycles = tricycle_318 + tricycle_warehouse;
            const total_loaders = loaders_truck + loaders_318 + loaders_tricycle + loaders_warehouse;
            const grand_total = drivers + total_tricycles + total_loaders;

            document.getElementById('total_drivers').textContent = drivers + ' នាក់';
            document.getElementById('total_tricycles').textContent = total_tricycles + ' នាក់';
            document.getElementById('total_loaders').textContent = total_loaders + ' នាក់';
            document.getElementById('grand_total').textContent = grand_total + ' នាក់';

            const trucks = parseInt(document.getElementsByName('trucks')[0].value) || 0;
            const v_tricycle_318 = parseInt(document.getElementsByName('tricycle_318')[1].value) || 0;
            const v_tricycle_warehouse = parseInt(document.getElementsByName('tricycle_warehouse')[1].value) || 0;
            const total_tricycles_vehicle = v_tricycle_318 + v_tricycle_warehouse;

            document.getElementById('total_trucks').textContent = trucks + ' គ្រឿង';
            document.getElementById('total_tricycles_vehicle').textContent = total_tricycles_vehicle + ' គ្រឿង';
        }

        window.onload = function() {
            const inputs = document.querySelectorAll('.editable-cell input[type="number"]');
            inputs.forEach(input => {
                input.addEventListener('input', updateTotals);
            });
            updateTotals();
        }

        // --- [ថ្មី] JavaScript សម្រាប់ Screenshot និងមុខងារពាក់ព័ន្ធ ---

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
                    backgroundColor: '#ffffff',
                    onclone: (document) => {
                        // Ensure input values are visible in the screenshot
                        const inputs = document.querySelectorAll('#capture-area input[type="number"]');
                        inputs.forEach(input => {
                            const cell = input.parentElement;
                            const value = input.value;
                            cell.textContent = value; 
                        });
                    }
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