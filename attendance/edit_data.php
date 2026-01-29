<?php
require_once 'db_connect.php';

$error_message = '';
$success_message = '';
$edit_mode = false;
$edit_data = [
    'office_staff' => [],
    'store_318_staff' => [],
    'warehouse_staff' => [],
    'new_staff' => [],
    'general_comment' => ''
];

// Check if we're in edit mode
if (isset($_GET['edit_date'])) {
    $edit_date = $_GET['edit_date'];
    $edit_mode = true;

    try {
        // Fetch Office Staff Data
        $stmt = $pdo->prepare("SELECT * FROM office_staff WHERE DATE(created_at) = ?");
        $stmt->execute([$edit_date]);
        $edit_data['office_staff'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch Store 318 Staff Data
        $stmt = $pdo->prepare("SELECT * FROM store_318_staff WHERE DATE(created_at) = ?");
        $stmt->execute([$edit_date]);
        $edit_data['store_318_staff'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch Warehouse Staff Data
        $stmt = $pdo->prepare("SELECT * FROM warehouse_staff WHERE DATE(created_at) = ?");
        $stmt->execute([$edit_date]);
        $edit_data['warehouse_staff'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch New Staff Data
        $stmt = $pdo->prepare("SELECT * FROM new_staff WHERE DATE(created_at) = ?");
        $stmt->execute([$edit_date]);
        $edit_data['new_staff'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch General Comment
        $stmt = $pdo->prepare("SELECT note FROM form_submission_notes WHERE DATE(created_at) = ?");
        $stmt->execute([$edit_date]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);
        $edit_data['general_comment'] = $comment ? $comment['note'] : '';
    } catch (PDOException $e) {
        $error_message = "កំហុសក្នុងការទាញទិន្នន័យ: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    try {
        $pdo->beginTransaction();

        $created_at = $_POST['created_at'] ?? date('Y-m-d');
        $is_update = isset($_POST['is_update']) && $_POST['is_update'] === '1';

        if ($is_update) {
            // Delete existing records for this date
            $pdo->prepare("DELETE FROM office_staff WHERE DATE(created_at) = ?")->execute([$created_at]);
            $pdo->prepare("DELETE FROM store_318_staff WHERE DATE(created_at) = ?")->execute([$created_at]);
            $pdo->prepare("DELETE FROM warehouse_staff WHERE DATE(created_at) = ?")->execute([$created_at]);
            $pdo->prepare("DELETE FROM new_staff WHERE DATE(created_at) = ?")->execute([$created_at]);
            $pdo->prepare("DELETE FROM form_submission_notes WHERE DATE(created_at) = ?")->execute([$created_at]);
        }

        // Office Staff Data
        if (!empty($_POST['office_staff'])) {
            $female = $_POST['office_staff']['female'] ?? 0;
            $male = $_POST['office_staff']['male'] ?? 0;
            $total = $female + $male;
            $location = 'ការិយាល័យកណ្តាល';

            $stmt = $pdo->prepare("INSERT INTO office_staff (total, female, male, location, created_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$total, $female, $male, $location, $created_at . ' 00:00:00']);
        }

        // Store 318 Staff Data
        if (!empty($_POST['store_318_staff'])) {
            $female = $_POST['store_318_staff']['female'] ?? 0;
            $male = $_POST['store_318_staff']['male'] ?? 0;
            $total = $female + $male;

            $stmt = $pdo->prepare("INSERT INTO store_318_staff (total, female, male, created_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$total, $female, $male, $created_at . ' 00:00:00']);
        }

        // Warehouse Staff Data
        if (!empty($_POST['warehouse_staff'])) {
            $ch1 = $_POST['warehouse_staff']['ch1'] ?? 0;
            $ckd = $_POST['warehouse_staff']['ckd'] ?? 0;
            $st1 = $_POST['warehouse_staff']['st1'] ?? 0;
            $psp = $_POST['warehouse_staff']['psp'] ?? 0;
            $total = $ch1 + $ckd + $st1 + $psp;

            $stmt = $pdo->prepare("INSERT INTO warehouse_staff (total, ch1, ckd, st1, psp, created_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$total, $ch1, $ckd, $st1, $psp, $created_at . ' 00:00:00']);
        }

        // New Staff Data
        if (!empty($_POST['new_staff']) && is_array($_POST['new_staff'])) {
            $stmt = $pdo->prepare("INSERT INTO new_staff (number, name, role, note, office_central, store_318, warehouse, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($_POST['new_staff'] as $staff) {
                $number = $staff['number'] ?? '';
                $name = $staff['name'] ?? '';
                $role = $staff['role'] ?? '';
                $note = $staff['note'] ?? '';
                $office_central = $staff['office_central'] ?? 0;
                $store_318 = $staff['store_318'] ?? 0;
                $warehouse = $staff['warehouse'] ?? 0;

                if (!empty($number) || !empty($name)) {
                    $stmt->execute([$number, $name, $role, $note, $office_central, $store_318, $warehouse, $created_at . ' 00:00:00']);
                }
            }
        }

        // General Comment Data
        $general_comment = $_POST['general_comment'] ?? '';
        if (!empty($general_comment)) {
            $stmt = $pdo->prepare("INSERT INTO form_submission_notes (note, created_at) VALUES (?, ?)");
            $stmt->execute([$general_comment, $created_at . ' 00:00:00']);
        }

        $pdo->commit();
        header("Location: view_by_date.php?success=" . urlencode("បាន" . ($is_update ? "ធ្វើបច្ចុប្បន្នភាព" : "បញ្ចូល") . "ទិន្នន័យជោគជ័យ"));
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
    <title><?php echo $edit_mode ? 'កែសម្រួលទិន្នន័យបុគ្គលិក' : 'បញ្ចូលទិន្នន័យបុគ្គលិក'; ?></title>
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
        let rowCounter = <?php echo $edit_mode && !empty($edit_data['new_staff']) ? count($edit_data['new_staff']) : 1; ?>;
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
        <h1><?php echo $edit_mode ? 'កែសម្រួលទិន្នន័យបុគ្គលិក' : 'បញ្ចូលទិន្នន័យបុគ្គលិកថ្មី'; ?></h1>

        <?php if (!empty($error_message)): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="is_update" value="<?php echo $edit_mode ? '1' : '0'; ?>">
            <!-- Date Selection -->
            <div class="date-selection">
                <label for="created_at">ជ្រើសរើសកាលបរិច្ឆេទរបាយការណ៍:</label>
                <input type="date" id="created_at" name="created_at" value="<?php echo $edit_mode ? htmlspecialchars($edit_date) : date('Y-m-d'); ?>" required>
            </div>

            <!-- General Comment Input -->
            <div class="general-comment">
                <label for="general_comment">អធិប្បាយទូទៅ:</label>
                <input type="text" id="general_comment" name="general_comment" placeholder="បញ្ចូលអធិប្បាយទូទៅនៅទីនេះ..." value="<?php echo htmlspecialchars($edit_data['general_comment']); ?>">
            </div>

            <!-- Office Staff Section -->
            <table>
                <tr><th colspan="2" class="section-header">បុគ្គលិកការិយាល័យកណ្តាល</th></tr>
                <tr>
                    <th>បុគ្គលិកភេទស្រី</th>
                    <th>បុគ្គលិកភេទប្រុស</th>
                </tr>
                <tr>
                    <td><input type="number" name="office_staff[female]" value="<?php echo $edit_mode && $edit_data['office_staff'] ? htmlspecialchars($edit_data['office_staff']['female']) : '0'; ?>"></td>
                    <td><input type="number" name="office_staff[male]" value="<?php echo $edit_mode && $edit_data['office_staff'] ? htmlspecialchars($edit_data['office_staff']['male']) : '0'; ?>"></td>
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
                    <td><input type="number" name="store_318_staff[female]" value="<?php echo $edit_mode && $edit_data['store_318_staff'] ? htmlspecialchars($edit_data['store_318_staff']['female']) : '0'; ?>"></td>
                    <td><input type="number" name="store_318_staff[male]" value="<?php echo $edit_mode && $edit_data['store_318_staff'] ? htmlspecialchars($edit_data['store_318_staff']['male']) : '0'; ?>"></td>
                </tr>
            </table>

            <!-- Warehouse Staff Section -->
            <table>
                <tr><th colspan="4" class="section-header">បុគ្គលិកជំនាញតាមឃ្លាំង</th></tr>
                <tr>
                    <th>CH1</th>
                    <th>CKD</th>
                    <th>ST1</th>
                    <th>PSP</th>
                </tr>
                <tr>
                    <td><input type="number" name="warehouse_staff[ch1]" value="<?php echo $edit_mode && $edit_data['warehouse_staff'] ? htmlspecialchars($edit_data['warehouse_staff']['ch1']) : '0'; ?>"></td>
                    <td><input type="number" name="warehouse_staff[ckd]" value="<?php echo $edit_mode && $edit_data['warehouse_staff'] ? htmlspecialchars($edit_data['warehouse_staff']['ckd']) : '0'; ?>"></td>
                    <td><input type="number" name="warehouse_staff[st1]" value="<?php echo $edit_mode && $edit_data['warehouse_staff'] ? htmlspecialchars($edit_data['warehouse_staff']['st1']) : '0'; ?>"></td>
                    <td><input type="number" name="warehouse_staff[psp]" value="<?php echo $edit_mode && $edit_data['warehouse_staff'] ? htmlspecialchars($edit_data['warehouse_staff']['psp']) : '0'; ?>"></td>
                </tr>
            </table>

            <!-- New Staff Section -->
            <table>
                <tr><th colspan="7" class="section-header">បុគ្គលិកសុំច្បាប់ ដេអូស ប្តូរដេអូស និងចូលថ្មី</th></tr>
                <tr>
                    <th>ល.រ</th>
                    <th>ឈ្មោះ</th>
                    <th>តួនាទី</th>
                    <th>អធិប្បាយ</th>
                    <th>ការិយាល័យកណ្តាល</th>
                    <th>៣១៨</th>
                    <th>ឃ្លាំង</th>
                </tr>
                <tbody id="new-staff-rows">
                    <?php if ($edit_mode && !empty($edit_data['new_staff'])): ?>
                        <?php foreach ($edit_data['new_staff'] as $index => $staff): ?>
                            <tr>
                                <td><input type="text" name="new_staff[<?php echo $index; ?>][number]" value="<?php echo htmlspecialchars($staff['number']); ?>"></td>
                                <td><input type="text" name="new_staff[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($staff['name']); ?>"></td>
                                <td><input type="text" name="new_staff[<?php echo $index; ?>][role]" value="<?php echo htmlspecialchars($staff['role']); ?>"></td>
                                <td><input type="text" name="new_staff[<?php echo $index; ?>][note]" value="<?php echo htmlspecialchars($staff['note']); ?>"></td>
                                <td><input type="number" name="new_staff[<?php echo $index; ?>][office_central]" value="<?php echo htmlspecialchars($staff['office_central']); ?>"></td>
                                <td><input type="number" name="new_staff[<?php echo $index; ?>][store_318]" value="<?php echo htmlspecialchars($staff['store_318']); ?>"></td>
                                <td><input type="number" name="new_staff[<?php echo $index; ?>][warehouse]" value="<?php echo htmlspecialchars($staff['warehouse']); ?>"></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td><input type="text" name="new_staff[0][number]"></td>
                            <td><input type="text" name="new_staff[0][name]"></td>
                            <td><input type="text" name="new_staff[0][role]"></td>
                            <td><input type="text" name="new_staff[0][note]"></td>
                            <td><input type="number" name="new_staff[0][office_central]" value="0"></td>
                            <td><input type="number" name="new_staff[0][store_318]" value="0"></td>
                            <td><input type="number" name="new_staff[0][warehouse]" value="0"></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <button type="button" class="add-row-btn" onclick="addNewRow()">+ បន្ថែមបុគ្គលិកថ្មី</button>
            <button type="submit" name="submit"><?php echo $edit_mode ? 'ធ្វើបច្ចុប្បន្នភាព' : 'រក្សាទុកទិន្នន័យ'; ?></button>
            <p><a href="view_by_date.php">ត្រឡប់ទៅទំព័របង្ហាញ</a></p>
        </form>
    </div>
</body>
</html>