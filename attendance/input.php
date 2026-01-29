<?php
require_once 'db_connect.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    try {
        $pdo->beginTransaction();

        // Get the selected report date
        $reports_date = $_POST['reports_date'] ?? date('Y-m-d');

        // Office Staff Data
        if (!empty($_POST['office_staff'])) {
            $female = $_POST['office_staff']['female'] ?? 0;
            $male = $_POST['office_staff']['male'] ?? 0;
            $total = $female + $male; // Calculate total from female and male

            $stmt = $pdo->prepare("INSERT INTO office_staff (total, female, male, reports_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$total, $female, $male, $reports_date]);
        }

        // Store 318 Staff Data
        if (!empty($_POST['store_318_staff'])) {
            $female = $_POST['store_318_staff']['female'] ?? 0;
            $male = $_POST['store_318_staff']['male'] ?? 0;
            $total = $female + $male; // Calculate total from female and male

            $stmt = $pdo->prepare("INSERT INTO store_318_staff (total, female, male, reports_date) VALUES (?, ?, ?, ?)");
            $stmt->execute([$total, $female, $male, $reports_date]);
        }

        // Warehouse Staff Data
        if (!empty($_POST['warehouse_staff'])) {
            $ch1 = $_POST['warehouse_staff']['ch1'] ?? 0;
            $ckd = $_POST['warehouse_staff']['ckd'] ?? 0;
            $st1 = $_POST['warehouse_staff']['st1'] ?? 0;
            $psp = $_POST['warehouse_staff']['psp'] ?? 0;
            $total = $ch1 + $ckd + $st1 + $psp; // Calculate total from components

            $stmt = $pdo->prepare("INSERT INTO warehouse_staff (total, ch1, ckd, st1, psp, reports_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$total, $ch1, $ckd, $st1, $psp, $reports_date]);
        }

        // New Staff Data (Multiple Rows)
        if (!empty($_POST['new_staff']) && is_array($_POST['new_staff'])) {
            $stmt = $pdo->prepare("INSERT INTO new_staff (number, name, role, note, office_central, store_318, warehouse, reports_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($_POST['new_staff'] as $staff) {
                $number = $staff['number'] ?? '';
                $name = $staff['name'] ?? '';
                $role = $staff['role'] ?? '';
                $note = $staff['note'] ?? '';
                $office_central = $staff['office_central'] ?? 0;
                $store_318 = $staff['store_318'] ?? 0;
                $warehouse = $staff['warehouse'] ?? 0;
                
                if (!empty($number) || !empty($name)) {  // Only insert if there's meaningful data
                    $stmt->execute([$number, $name, $role, $note, $office_central, $store_318, $warehouse, $reports_date]);
                }
            }
        }

        // General Comment Data
        $general_comment = $_POST['general_comment'] ?? '';
        if (!empty($general_comment)) {
            $stmt = $pdo->prepare("INSERT INTO form_submission_notes (note, reports_date) VALUES (?, ?)");
            $stmt->execute([$general_comment, $reports_date]);
        }

        $pdo->commit();
        header("Location: view_by_date.php?success=" . urlencode("បានបញ្ចូលទិន្នន័យថ្មីជោគជ័យ"));
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "កំហុសក្នុងការបញ្ចូលទិន្នន័យ: " . $e->getMessage();
        error_log("Database Error in input.php: " . $e->getMessage());
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
        h1 {
            text-align: center;
            color: #333;
        }
        .form-container {
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
            background-color: #4CAF50;
            color: white;
        }
        .section-header {
            background-color: #2196F3;
            color: white;
            font-weight: bold;
        }
        input[type="text"], input[type="number"] {
            width: 100%;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-family: 'Noto Sans Khmer', sans-serif;
        }
        .general-comment, .date-selection {
            margin-bottom: 20px;
        }
        .general-comment label, .date-selection label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .general-comment input[type="text"] {
            width: 100%;
            max-width: 100%;
            min-height: 50px;
            resize: vertical;
        }
        .date-selection input[type="date"] {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .error {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        button:hover {
            background-color: #45a049;
        }
        .add-row-btn {
            background-color: #2196F3;
        }
        .add-row-btn:hover {
            background-color: #1e88e5;
        }
        a {
            color: #2196F3;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
    <script>
        let rowCounter = 1;
        function addNewRow() {
            const tbody = document.getElementById('new-staff-rows');
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td><input type="text" name="new_staff[${rowCounter}][number]"></td>
                <td><input type="text" name="new_staff[${rowCounter}][name]"></td>
                <td><input type="text" name="new_staff[${rowCounter}][role]"></td>
                <td><input type="text" name="new_staff[${rowCounter}][note]"></td>
                <td><input type="number" name="new_staff[${rowCounter}][office_central]" value="0"></td>
                <td><input type="number" name="new_staff[${rowCounter}][store_318]" value="0"></td>
                <td><input type="number" name="new_staff[${rowCounter}][warehouse]" value="0"></td>
            `;
            tbody.appendChild(newRow);
            rowCounter++;
        }
    </script>
</head>
<body>
    <div class="form-container">
        <h1>បញ្ចូលទិន្នន័យបុគ្គលិកថ្មី</h1>

        <?php if (!empty($error_message)): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <!-- Date Selection -->
            <div class="date-selection">
                <label for="reports_date">ជ្រើសរើសកាលបរិច្ឆេទរបាយការណ៍:</label>
                <input type="date" id="reports_date" name="reports_date" value="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <!-- General Comment Input -->
            <div class="general-comment">
                <label for="general_comment">អធិប្បាយទូទៅ:</label>
                <input type="text" id="general_comment" name="general_comment" placeholder="បញ្ចូលអធិប្បាយទូទៅនៅទីនេះ...">
            </div>

            <!-- Office Staff Section -->
            <table>
                <tr><th colspan="2" class="section-header">បុគ្គលិកការិយាល័យកណ្តាល</th></tr>
                <tr>
                    <th>បុគ្គលិកភេទស្រី</th>
                    <th>បុគ្គលិកភេទប្រុស</th>
                </tr>
                <tr>
                    <td><input type="number" name="office_staff[female]" value="0"></td>
                    <td><input type="number" name="office_staff[male]" value="0"></td>
                </tr>
            </table>

            <!-- Store 318 Staff Section -->
            <table>
                <tr><th colspan="2" class="section-header">បុគ្គលិកហាងទំនិញ៣១៨</th></tr>
                <tr>
                    <th>បុគ្គលិកភេទស្រី</th>
                    <th>បុគ្គលិកភេទប្រុស</th>
                </tr>
                <tr>
                    <td><input type="number" name="store_318_staff[female]" value="0"></td>
                    <td><input type="number" name="store_318_staff[male]" value="0"></td>
                </tr>
            </table>

            <!-- Warehouse Staff Section -->
            <table>
                <tr><th colspan="5" class="section-header">បុគ្គលិកឃ្លាំង</th></tr>
                <tr>
                    <th>CH1</th>
                    <th>CKD</th>
                    <th>ST1</th>
                    <th>PSP</th>
                    <th>សរុប</th>
                </tr>
                <tr>
                    <td><input type="number" name="warehouse_staff[ch1]" value="0"></td>
                    <td><input type="number" name="warehouse_staff[ckd]" value="0"></td>
                    <td><input type="number" name="warehouse_staff[st1]" value="0"></td>
                    <td><input type="number" name="warehouse_staff[psp]" value="0"></td>
                    <td><input type="number" name="warehouse_staff[total]" value="0" disabled></td>
                </tr>
            </table>

            <!-- New Staff Section -->
            <table id="new-staff-table">
                <thead>
                    <tr><th colspan="7" class="section-header">បុគ្គលិកថ្មី</th></tr>
                    <tr>
                        <th>លេខសម្គាល់</th>
                        <th>ឈ្មោះ</th>
                        <th>តួនាទី</th>
                        <th>សំណេរ</th>
                        <th>ការិយាល័យកណ្តាល</th>
                        <th>ហាង ៣១៨</th>
                        <th>ឃ្លាំង</th>
                    </tr>
                </thead>
                <tbody id="new-staff-rows">
                    <tr>
                        <td><input type="text" name="new_staff[0][number]"></td>
                        <td><input type="text" name="new_staff[0][name]"></td>
                        <td><input type="text" name="new_staff[0][role]"></td>
                        <td><input type="text" name="new_staff[0][note]"></td>
                        <td><input type="number" name="new_staff[0][office_central]" value="0"></td>
                        <td><input type="number" name="new_staff[0][store_318]" value="0"></td>
                        <td><input type="number" name="new_staff[0][warehouse]" value="0"></td>
                    </tr>
                </tbody>
            </table>
            <button type="button" class="add-row-btn" onclick="addNewRow()">បន្ថែមបញ្ជីបុគ្គលិកថ្មី</button><br><br>

            <!-- Submit Button -->
            <button type="submit" name="submit">បញ្ចូលទិន្នន័យ</button>
        </form>
    </div>
</body>
</html>
