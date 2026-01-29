<?php
// insert_data.php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$current_user = $_SESSION['username'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Insert vehicle and loading staff
        $vehicle_stmt = $pdo->prepare("
            INSERT INTO vehicle_loading_staff (vehicle_drivers, tricycle_drivers, load_follow_vehicle, load_follow_tricycle, load_follow_warehouse, report_date, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $vehicle_stmt->execute([
            $_POST['vehicle_drivers'],
            $_POST['tricycle_drivers'],
            $_POST['load_follow_vehicle'],
            $_POST['load_follow_tricycle'],
            $_POST['load_follow_warehouse'],
            $_POST['report_date'],
            $current_user
        ]);

        // Insert available vehicles
        $vehicle_count_stmt = $pdo->prepare("
            INSERT INTO available_vehicles (cars, tricycles, report_date, created_by)
            VALUES (?, ?, ?, ?)
        ");
        $vehicle_count_stmt->execute([
            $_POST['cars'],
            $_POST['tricycles'],
            $_POST['report_date'],
            $current_user
        ]);

        // Insert staff status (multiple entries possible)
        if (!empty($_POST['serial_no'])) {
            $staff_stmt = $pdo->prepare("
                INSERT INTO staff_status (serial_no, staff_name, role, comment, new_staff_central, new_staff_318, new_staff_warehouse, report_date, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            for ($i = 0; $i < count($_POST['serial_no']); $i++) {
                $staff_stmt->execute([
                    $_POST['serial_no'][$i],
                    $_POST['staff_name'][$i],
                    $_POST['role'][$i],
                    $_POST['comment'][$i],
                    $_POST['new_staff_central'][$i],
                    $_POST['new_staff_318'][$i],
                    $_POST['new_staff_warehouse'][$i],
                    $_POST['report_date'],
                    $current_user
                ]);
            }
        }

        $success_message = "ទិន្នន័យត្រូវបានបញ្ជូលដោយជោគជ័យ!";
    } catch(PDOException $e) {
        $error_message = "កំហុសក្នុងការបញ្ជូលទិន្នន័យ: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>បញ្ជូលទិន្នន័យវត្តមានបុគ្គលិក</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Noto Sans Khmer', sans-serif;
            background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%);
            min-height: 100vh;
            padding: 30px 15px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            max-width: 1100px;
            width: 100%;
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .container:hover {
            transform: translateY(-5px);
        }

        h1 {
            color: #2c3e50;
            font-size: 2.5em;
            text-align: center;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        h2 {
            color: #34495e;
            font-size: 1.8em;
            text-align: center;
            margin-bottom: 20px;
        }

        h3 {
            color: #2980b9;
            font-size: 1.4em;
            margin-bottom: 15px;
            border-left: 4px solid #3498db;
            padding-left: 10px;
        }

        .form-section {
            margin: 30px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        label {
            display: block;
            font-size: 1.1em;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        input[type="date"],
        input[type="number"],
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #bdc3c7;
            border-radius: 6px;
            font-family: 'Noto Sans Khmer', sans-serif;
            font-size: 1em;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        input[type="date"]:focus,
        input[type="number"]:focus,
        input[type="text"]:focus {
            border-color: #3498db;
            box-shadow: 0 0 8px rgba(52, 152, 219, 0.2);
            outline: none;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
        }

        th, td {
            padding: 12px;
            text-align: center;
            border: 1px solid #e0e0e0;
        }

        th {
            background: linear-gradient(90deg, #3498db, #2980b9);
            color: #ffffff;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.95em;
        }

        td {
            background: #f9f9f9;
            color: #2c3e50;
        }

        .submit-btn,
        .view-btn {
            display: block;
            margin: 10px auto;
            padding: 12px;
            background: linear-gradient(90deg, #3498db, #2980b9);
            color: #ffffff;
            border: none;
            border-radius: 25px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.3s ease;
            text-decoration: none;
            text-align: center;
        }

        .submit-btn:hover,
        .view-btn:hover {
            background: linear-gradient(90deg, #2980b9, #3498db);
            transform: scale(1.05);
        }

        .message {
            text-align: center;
            margin: 15px 0;
            padding: 10px;
            border-radius: 6px;
            font-size: 1em;
            color: #ffffff;
            background: <?php echo isset($error_message) ? '#e74c3c' : '#2ecc71'; ?>;
        }

        .add-row {
            color: #3498db;
            cursor: pointer;
            text-align: right;
            margin: 10px 0;
            font-size: 1em;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .add-row:hover {
            color: #2980b9;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>វណ្ណ វណ្ណ ខេមបូឌា</h1>
        <h2>បញ្ជូលទិន្នន័យវត្តមានបុគ្គលិក</h2>

        <?php if(isset($success_message)): ?>
            <div class="message"><?php echo $success_message; ?></div>
        <?php elseif(isset($error_message)): ?>
            <div class="message"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST">
            <!-- Report Date -->
            <div class="form-section">
                <label>កាលបរិច្ឆេទរបាយការណ៍:</label>
                <input type="date" name="report_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <!-- Vehicle and Loading Staff -->
            <div class="form-section">
                <h3>ផ្នែកបើករថយន្ត រម៉ក់កង់បី និងផ្នែកលើកទំនិញ</h3>
                <table>
                    <tr>
                        <th>ផ្នែកបើករថយន្ត</th>
                        <th>ផ្នែកបើកម៉ូតូកង់បី</th>
                        <th>ដើរតាមឡាន</th>
                        <th>ដើរតាមកង់បី</th>
                        <th>ដើរតាមឃ្លាំង</th>
                    </tr>
                    <tr>
                        <td><input type="number" name="vehicle_drivers" min="0" required></td>
                        <td><input type="number" name="tricycle_drivers" min="0" required></td>
                        <td><input type="number" name="load_follow_vehicle" min="0" required></td>
                        <td><input type="number" name="load_follow_tricycle" min="0" required></td>
                        <td><input type="number" name="load_follow_warehouse" min="0" required></td>
                    </tr>
                </table>
            </div>

            <!-- Available Vehicles -->
            <div class="form-section">
                <h3>ចំនួនរថយន្ត រម៉ក់កង់បី ដែលអាចប្រើប្រាស់បាន</h3>
                <table>
                    <tr>
                        <th>រថយន្ត(ឡាន)</th>
                        <th>រម៉ក់កង់បី</th>
                    </tr>
                    <tr>
                        <td><input type="number" name="cars" min="0" required></td>
                        <td><input type="number" name="tricycles" min="0" required></td>
                    </tr>
                </table>
            </div>

            <!-- Staff Status -->
            <div class="form-section">
                <h3>បុគ្គលិកសុំច្បាប់ ដេអូស ប្តូរដេអូស និងចូលថ្មី</h3>
                <div id="staff-table">
                    <table>
                        <tr>
                            <th>ល.រ</th>
                            <th>ឈ្មោះ</th>
                            <th>តួនាទី</th>
                            <th>អធិប្បាយ</th>
                            <th>ការិយាល័យកណ្តាល</th>
                            <th>៣១៨</th>
                            <th>ឃ្លាំង</th>
                        </tr>
                        <tr class="staff-row">
                            <td><input type="text" name="serial_no[]"></td>
                            <td><input type="text" name="staff_name[]" required></td>
                            <td><input type="text" name="role[]" required></td>
                            <td><input type="text" name="comment[]"></td>
                            <td><input type="number" name="new_staff_central[]" min="0" value="0"></td>
                            <td><input type="number" name="new_staff_318[]" min="0" value="0"></td>
                            <td><input type="number" name="new_staff_warehouse[]" min="0" value="0"></td>
                        </tr>
                    </table>
                    <div class="add-row" onclick="addStaffRow()">បន្ថែមជួរថ្មី</div>
                </div>
            </div>

            <button type="submit" class="submit-btn">បញ្ជូលទិន្នន័យ</button>
            <a href="attendance.php" class="view-btn">មើលទំព័ររបាយការណ៍</a>
        </form>
    </div>

    <script>
        function addStaffRow() {
            const table = document.querySelector('#staff-table table');
            const newRow = table.rows[1].cloneNode(true);
            const inputs = newRow.getElementsByTagName('input');
            for (let input of inputs) {
                input.value = input.type === 'number' ? '0' : '';
            }
            table.appendChild(newRow);
        }
    </script>
</body>
</html>