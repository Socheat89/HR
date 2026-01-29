<?php
// Database connection
session_start();
require_once 'db_connect.php'; // Changed from db_connect.php to match your previous naming

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: https://app.vvc.asia/login.php"); // Redirect to login page if not logged in
    exit();
}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        $date = $_POST['date'];
        $stmt = $pdo->prepare("INSERT INTO reports_date_ch1 (report_date) VALUES (?) ON DUPLICATE KEY UPDATE updated_at = NOW()");
        $stmt->execute([$date]);
        $report_id = $pdo->lastInsertId();
        
        if (!$report_id) {
            $stmt = $pdo->prepare("SELECT id FROM reports_date_ch1 WHERE report_date = ?");
            $stmt->execute([$date]);
            $report_id = $stmt->fetchColumn();
        }

        $stmt = $pdo->prepare("
            INSERT INTO vehicle_staff_ch1 (
                report_id, date, drivers, tricycle_318, tricycle_warehouse,
                loaders_truck, loaders_318, loaders_tricycle, loaders_warehouse
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                drivers = VALUES(drivers),
                tricycle_318 = VALUES(tricycle_318),
                tricycle_warehouse = VALUES(tricycle_warehouse),
                loaders_truck = VALUES(loaders_truck),
                loaders_318 = VALUES(loaders_318),
                loaders_tricycle = VALUES(loaders_tricycle),
                loaders_warehouse = VALUES(loaders_warehouse)
        ");
        $stmt->execute([
            $report_id, $date, 
            $_POST['drivers'], $_POST['tricycle_318'], $_POST['tricycle_warehouse'],
            $_POST['loaders_truck'], $_POST['loaders_318'], $_POST['loaders_tricycle'],
            $_POST['loaders_warehouse']
        ]);

        $stmt = $pdo->prepare("
            INSERT INTO vehicles_ch1 (report_id, date, trucks, tricycle_318, tricycle_warehouse)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                trucks = VALUES(trucks),
                tricycle_318 = VALUES(tricycle_318),
                tricycle_warehouse = VALUES(tricycle_warehouse)
        ");
        $stmt->execute([
            $report_id, $date,
            $_POST['trucks'], $_POST['vehicles_tricycle_318'], $_POST['vehicles_tricycle_warehouse']
        ]);

        if (!empty($_POST['requests'])) {
            $stmt = $pdo->prepare("
                INSERT INTO staff_requests_ch1 (
                    report_id, date, request_id, name, role, comment,
                    tricycle_dept, loading_dept, truck_dept
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($_POST['requests'] as $request) {
                $stmt->execute([
                    $report_id, $date,
                    $request['request_id'], $request['name'], $request['role'], $request['comment'],
                    isset($request['tricycle_dept']) ? 1 : 0,
                    isset($request['loading_dept']) ? 1 : 0,
                    isset($request['truck_dept']) ? 1 : 0
                ]);
            }
        }

        $pdo->commit();
        $success = "ទិន្នន័យបានរក្សាទុកដោយជោគជ័យ!";
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "កំហុសក្នុងការរក្សាទុកទិន្នន័យ: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>បញ្ចូលទិន្នន័យបុគ្គលិក</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans Khmer', sans-serif;
            margin: 20px;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        .form-section {
            margin-bottom: 20px;
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
            background-color: #0011ff;
            color: rgb(233, 198, 1);
            font-weight: bold;
        }
        .section-header {
            background-color: rgb(233, 198, 1);
            color: #0011ff;
            font-weight: bold;
        }
        input[type="number"],
        input[type="text"],
        input[type="date"] {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
            box-sizing: border-box;
            font-family: 'Noto Sans Khmer', sans-serif;
        }
        input[type="checkbox"] {
            margin: 0 5px;
        }
        .error {
            color: red;
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #ffebee;
            border-radius: 4px;
        }
        .success {
            color: #2e7d32;
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #e8f5e9;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: block;
            margin: 10px auto;
        }
        button:hover {
            background-color: #45a049;
        }
        .add-button {
            background-color: #2196F3;
        }
        .add-button:hover {
            background-color: #1976D2;
        }
        a {
            color: #2196F3;
            text-decoration: none;
            display: block;
            text-align: center;
            margin-top: 20px;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>បញ្ចូលទិន្នន័យបុគ្គលិក និងរថយន្ត</h1>

        <?php if (isset($error)) echo "<div class='error'>$error</div>"; ?>
        <?php if (isset($success)) echo "<div class='success'>$success</div>"; ?>

        <form method="POST" action="">
            <div class="form-section">
                <table>
                    <tr>
                        <th>កាលបរិច្ឆេទ</th>
                    </tr>
                    <tr>
                        <td><input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required></td>
                    </tr>
                </table>
            </div>

            <div class="form-section">
                <table>
                    <tr><th colspan="7" class="section-header">ផ្នែកបុគ្គលិក</th></tr>
                    <tr>
                        <th>ផ្នែកបើករថយន្ត</th>
                        <th>រ៉ឺម៉កកង់បី ៣១៨</th>
                        <th>រ៉ឺម៉កកង់បី តាមឃ្លាំង</th>
                        <th>លើកទំនិញ ដើរតាមឡាន</th>
                        <th>លើកទំនិញ ៣១៨</th>
                        <th>លើកទំនិញ រ៉ឺម៉កកង់បី</th>
                        <th>លើកទំនិញ តាមឃ្លាំង</th>
                    </tr>
                    <tr>
                        <td><input type="number" name="drivers" min="0" value="0"></td>
                        <td><input type="number" name="tricycle_318" min="0" value="0"></td>
                        <td><input type="number" name="tricycle_warehouse" min="0" value="0"></td>
                        <td><input type="number" name="loaders_truck" min="0" value="0"></td>
                        <td><input type="number" name="loaders_318" min="0" value="0"></td>
                        <td><input type="number" name="loaders_tricycle" min="0" value="0"></td>
                        <td><input type="number" name="loaders_warehouse" min="0" value="0"></td>
                    </tr>
                </table>
            </div>

            <div class="form-section">
                <table>
                    <tr><th colspan="3" class="section-header">រថយន្ត</th></tr>
                    <tr>
                        <th>រថយន្ត(ឡាន)</th>
                        <th>រ៉ឺម៉កកង់បី ៣១៨</th>
                        <th>រ៉ឺម៉កកង់បី តាមឃ្លាំង</th>
                    </tr>
                    <tr>
                        <td><input type="number" name="trucks" min="0" value="0"></td>
                        <td><input type="number" name="vehicles_tricycle_318" min="0" value="0"></td>
                        <td><input type="number" name="vehicles_tricycle_warehouse" min="0" value="0"></td>
                    </tr>
                </table>
            </div>

            <div class="form-section">
                <table id="requests-table">
                    <tr><th colspan="7" class="section-header">បុគ្គលិកសុំច្បាប់/ចូលថ្មី</th></tr>
                    <tr>
                        <th>ល.រ</th>
                        <th>ឈ្មោះ</th>
                        <th>តួនាទី</th>
                        <th>អធិប្បាយ</th>
                        <th>ផ្នែកកង់បី</th>
                        <th>ផ្នែកលើកទំនិញ</th>
                        <th>ផ្នែករថយន្ត</th>
                    </tr>
                    <tr class="request-row">
                        <td><input type="text" name="requests[0][request_id]"></td>
                        <td><input type="text" name="requests[0][name]"></td>
                        <td><input type="text" name="requests[0][role]"></td>
                        <td><input type="text" name="requests[0][comment]"></td>
                        <td><input type="checkbox" name="requests[0][tricycle_dept]"></td>
                        <td><input type="checkbox" name="requests[0][loading_dept]"></td>
                        <td><input type="checkbox" name="requests[0][truck_dept]"></td>
                    </tr>
                </table>
                <button type="button" class="add-button" onclick="addRequestRow()">បន្ថែមបុគ្គលិក</button>
            </div>

            <button type="submit">រក្សាទុកទិន្នន័យ</button>
        </form>

        <a href="attendance_CH1.php">ត្រឡប់ទៅទំព័រមើលទិន្នន័យ</a>
    </div>

    <script>
    let requestCount = 1;
    function addRequestRow() {
        const table = document.getElementById('requests-table');
        const newRow = document.createElement('tr');
        newRow.className = 'request-row';
        newRow.innerHTML = `
            <td><input type="text" name="requests[${requestCount}][request_id]"></td>
            <td><input type="text" name="requests[${requestCount}][name]"></td>
            <td><input type="text" name="requests[${requestCount}][role]"></td>
            <td><input type="text" name="requests[${requestCount}][comment]"></td>
            <td><input type="checkbox" name="requests[${requestCount}][tricycle_dept]"></td>
            <td><input type="checkbox" name="requests[${requestCount}][loading_dept]"></td>
            <td><input type="checkbox" name="requests[${requestCount}][truck_dept]"></td>
        `;
        table.appendChild(newRow);
        requestCount++;
    }
    </script>
</body>
</html>