<?php
require_once 'db_connect.php';

// Initialize variables to avoid undefined index errors
$office_data = $warehouse_data = $new_staff = $total_data = $store_318_data = null;
$error_message = '';
$success_message = '';

// Check for success or error messages from save_staff.php
if (isset($_GET['success'])) {
    $success_message = urldecode($_GET['success']);
}
if (isset($_GET['error'])) {
    $error_message = urldecode($_GET['error']);
}

// Fetch the latest Office Staff data
try {
    $stmt_office = $pdo->query("SELECT * FROM office_staff WHERE location = 'ការិយាល័យកណ្តាល' ORDER BY id DESC LIMIT 1");
    $office_data = $stmt_office->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message .= "កំហុសក្នុងការទាក់ទង office_staff: " . $e->getMessage() . " ";
}

// Fetch the latest Warehouse Staff data
try {
    $stmt_warehouse = $pdo->query("SELECT * FROM warehouse_staff ORDER BY id DESC LIMIT 1");
    $warehouse_data = $stmt_warehouse->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message .= "កំហុសក្នុងការទាក់ទង warehouse_staff: " . $e->getMessage() . " ";
}

// Fetch the latest Store 318 Staff data
try {
    $stmt_store_318 = $pdo->query("SELECT * FROM store_318_staff ORDER BY id DESC LIMIT 1");
    $store_318_data = $stmt_store_318->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message .= "កំហុសក្នុងការទាក់ទង store_318_staff: " . $e->getMessage() . " ";
}

// Fetch all New Staff data
try {
    $stmt_new = $pdo->query("SELECT * FROM new_staff ORDER BY id DESC");
    $new_staff = $stmt_new->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message .= "កំហុសក្នុងការទាក់ទង new_staff: " . $e->getMessage() . " ";
    $new_staff = [];
}

// Calculate totals dynamically based on the latest data (for display only)
$total_data = [
    'office_central' => $office_data['total'] ?? null,
    'store_318' => $store_318_data['total'] ?? null,
    'warehouse' => $warehouse_data['total'] ?? null,
    'grand_total' => 89 // Set grand_total to 89 as requested
];

// Check if there is any data to display
$has_data = !empty($office_data) || !empty($store_318_data) || !empty($warehouse_data) || !empty($new_staff);

// Log errors for debugging
if (!empty($error_message)) {
    error_log("Database Error: " . $error_message);
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>របាយការណ៍វត្តមានបុគ្គលិក</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans Khmer', sans-serif;
            direction: ltr;
            margin: 0;
            padding: 10px;
            background-color: #fff;
            text-align: center; /* Center-align body content */
        }
        .header-container {
            text-align: center;
            margin-bottom: 10px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 10px 0;
        }
        td, th {
            border: 1px solid black;
            padding: 4px;
            text-align: center;
            font-size: 10px;
        }
        .header {
            background-color: rgb(255, 96, 220);
            color: aliceblue;
        }
        img {
            width: 100px;
            margin-bottom: 5px;
            vertical-align: middle; /* Align image with text */
        }
        span {
            font-size: 14px;
            display: inline-block; /* Allow centering with image */
            margin-bottom: 10px;
            vertical-align: middle; /* Align with image */
        }
        div {
            margin-top: 10px;
            font-size: 12px;
        }
        .success {
            color: green;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .error {
            color: red;
            font-weight: bold;
            margin-bottom: 10px;
        }
        a {
            display: inline-block;
            margin: 10px 0;
            color: #4CAF50;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        input {
            width: 100%;
            padding: 2px;
            box-sizing: border-box;
            font-family: 'Noto Sans Khmer', sans-serif;
            text-align: center;
            font-size: 10px;
            border: none;
            background: transparent;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 8px 16px;
            border: none;
            cursor: pointer;
            margin: 5px;
        }
        button:hover {
            background-color: #45a049;
        }

        /* Print-specific styles */
        @media print {
            @page {
                size: A4 landscape;
                margin: 10mm;
            }
            body {
                padding: 0;
                margin: 0;
                font-size: 10px;
            }
            .header-container {
                text-align: center;
            }
            img {
                width: 80px;
            }
            span {
                font-size: 12px;
            }
            table {
                width: 100%;
                margin: 5px 0;
            }
            td, th {
                padding: 3px;
                font-size: 9px;
            }
            input {
                border: none;
                background: transparent;
                -webkit-appearance: none;
                -moz-appearance: none;
                appearance: none;
            }
            .success, .error, button, a, div:last-child { /* Hide success, error, buttons, links, and footer */
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header-container">
        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/2/2f/Google_2015_logo.svg/800px-Google_2015_logo.svg.png" alt="">
        <span>របាយការណ៍វត្តមានបុគ្គលិកនៅការិយាល័យកណ្តាល និងតាមឃ្លាំង  
            <?php if ($has_data && $total_data['grand_total'] !== null): ?>
                <br><?php echo $total_data['grand_total']; ?>
            <?php endif; ?>
        </span>
    </div>
    
    <?php if (!empty($success_message)): ?>
        <div class="success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="error">កំហុស: <?php echo $error_message; ?></div>
    <?php endif; ?>

    <?php if ($has_data): ?>
        <form method="POST" action="save_staff.php">
            <table border="1">
                <!-- Office Staff Section -->
                <?php if (!empty($office_data)): ?>
                    <tr>
                        <td colspan="7" class="header">បុគ្គលិកការិយាល័យកណ្តាល</td>
                    </tr>
                    <tr>
                        <td colspan="3">ចំនួនសរុប</td>
                        <td colspan="2">បុគ្គលិកភេទស្រី</td>
                        <td colspan="2">បុគ្គលិកភេទប្រុស</td>
                    </tr>
                    <tr>
                        <td colspan="3">
                            <input type="text" name="office_staff[<?php echo $office_data['id']; ?>][total]" value="<?php echo htmlspecialchars($office_data['total'] ?? ''); ?>">
                        </td>
                        <td colspan="2">
                            <input type="text" name="office_staff[<?php echo $office_data['id']; ?>][female]" value="<?php echo htmlspecialchars($office_data['female'] ?? ''); ?>">
                        </td>
                        <td colspan="2">
                            <input type="text" name="office_staff[<?php echo $office_data['id']; ?>][male]" value="<?php echo htmlspecialchars($office_data['male'] ?? ''); ?>">
                        </td>
                    </tr>
                <?php endif; ?>

                <!-- Store 318 Staff Section -->
                <?php if (!empty($store_318_data)): ?>
                    <tr>
                        <td colspan="7" class="header">បុគ្គលិកហាងទំនិញ៣១៨</td>
                    </tr>
                    <tr>
                        <td colspan="3">ចំនួនសរុប</td>
                        <td colspan="2">បុគ្គលិកភេទស្រី</td>
                        <td colspan="2">បុគ្គលិកភេទប្រុស</td>
                    </tr>
                    <tr>
                        <td colspan="3">
                            <input type="text" name="store_318_staff[<?php echo $store_318_data['id']; ?>][total]" value="<?php echo htmlspecialchars($store_318_data['total'] ?? ''); ?>">
                        </td>
                        <td colspan="2">
                            <input type="text" name="store_318_staff[<?php echo $store_318_data['id']; ?>][female]" value="<?php echo htmlspecialchars($store_318_data['female'] ?? ''); ?>">
                        </td>
                        <td colspan="2">
                            <input type="text" name="store_318_staff[<?php echo $store_318_data['id']; ?>][male]" value="<?php echo htmlspecialchars($store_318_data['male'] ?? ''); ?>">
                        </td>
                    </tr>
                <?php endif; ?>

                <!-- Warehouse Staff Section -->
                <?php if (!empty($warehouse_data)): ?>
                    <tr>
                        <td colspan="7" class="header">បុគ្គលិកជំនាញតាមឃ្លាំង</td>
                    </tr>
                    <tr>
                        <td colspan="2">ចំនួនសរុប</td>
                        <td>CH1</td>
                        <td>CKD</td>
                        <td>ST1</td>
                        <td></td>
                        <td>PSP</td>
                    </tr>
                    <tr>
                        <td colspan="2">
                            <input type="text" name="warehouse_staff[<?php echo $warehouse_data['id']; ?>][total]" value="<?php echo htmlspecialchars($warehouse_data['total'] ?? ''); ?>">
                        </td>
                        <td>
                            <input type="text" name="warehouse_staff[<?php echo $warehouse_data['id']; ?>][ch1]" value="<?php echo htmlspecialchars($warehouse_data['ch1'] ?? ''); ?>">
                        </td>
                        <td>
                            <input type="text" name="warehouse_staff[<?php echo $warehouse_data['id']; ?>][ckd]" value="<?php echo htmlspecialchars($warehouse_data['ckd'] ?? ''); ?>">
                        </td>
                        <td>
                            <input type="text" name="warehouse_staff[<?php echo $warehouse_data['id']; ?>][st1]" value="<?php echo htmlspecialchars($warehouse_data['st1'] ?? ''); ?>">
                        </td>
                        <td></td>
                        <td>
                            <input type="text" name="warehouse_staff[<?php echo $warehouse_data['id']; ?>][psp]" value="<?php echo htmlspecialchars($warehouse_data['psp'] ?? ''); ?>">
                        </td>
                    </tr>
                <?php endif; ?>

                <!-- New Staff Section -->
                <?php if (!empty($new_staff)): ?>
                    <tr>
                        <td colspan="7" class="header">បុគ្គលិកសុំច្បាប់ ដេអូស ប្តូរដេអូស និងចូលថ្មី</td>
                    </tr>
                    <tr>
                        <td rowspan="2">ល.រ</td>
                        <td rowspan="2">ឈ្មោះ</td>
                        <td rowspan="2">តួនាទី</td>
                        <td rowspan="2">អធិប្បាយ</td>
                        <td colspan="3">ចូលថ្មី</td>
                    </tr>
                    <tr>
                        <td>ការិយាល័យកណ្តាល</td>
                        <td>៣៧៨</td>
                        <td>ឃ្លាំង</td>
                    </tr>
                    <?php foreach ($new_staff as $staff): ?>
                        <tr>
                            <td><input type="text" name="new_staff[<?php echo $staff['id']; ?>][number]" value="<?php echo htmlspecialchars($staff['number'] ?? ''); ?>"></td>
                            <td><input type="text" name="new_staff[<?php echo $staff['id']; ?>][name]" value="<?php echo htmlspecialchars($staff['name'] ?? ''); ?>"></td>
                            <td><input type="text" name="new_staff[<?php echo $staff['id']; ?>][role]" value="<?php echo htmlspecialchars($staff['role'] ?? ''); ?>"></td>
                            <td><input type="text" name="new_staff[<?php echo $staff['id']; ?>][note]" value="<?php echo htmlspecialchars($staff['note'] ?? ''); ?>"></td>
                            <td><input type="text" name="new_staff[<?php echo $staff['id']; ?>][office_central]" value="<?php echo htmlspecialchars($staff['office_central'] ?? ''); ?>"></td>
                            <td><input type="text" name="new_staff[<?php echo $staff['id']; ?>][store_318]" value="<?php echo htmlspecialchars($staff['store_318'] ?? ''); ?>"></td>
                            <td><input type="text" name="new_staff[<?php echo $staff['id']; ?>][warehouse]" value="<?php echo htmlspecialchars($staff['warehouse'] ?? ''); ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Total Staff Section -->
                <?php if (!empty($office_data) || !empty($store_318_data) || !empty($warehouse_data)): ?>
                    <tr>
                        <td colspan="7" class="header">សរុបបុគ្គលិកទាំងអស់</td>
                    </tr>
                    <tr>
                        <td rowspan="2">សរុប</td>
                        <td>ការិយាល័យកណ្តាល</td>
                        <td>៣៧៨</td>
                        <td>ឃ្លាំង</td>
                        <td colspan="3">សរុបទាំងអស់</td>
                    </tr>
                    <tr>
                        <td>
                            <input type="text" name="total_data[office_central]" value="<?php echo htmlspecialchars($total_data['office_central'] ?? ''); ?>">
                        </td>
                        <td>
                            <input type="text" name="total_data[store_318]" value="<?php echo htmlspecialchars($total_data['store_318'] ?? ''); ?>">
                        </td>
                        <td>
                            <input type="text" name="total_data[warehouse]" value="<?php echo htmlspecialchars($total_data['warehouse'] ?? ''); ?>">
                        </td>
                        <td colspan="3">
                            <input type="text" name="total_data[grand_total]" value="<?php echo htmlspecialchars($total_data['grand_total'] ?? ''); ?>">
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
            <?php if ($has_data): ?>
                <button type="submit">រក្សាទុក</button>
                <button type="button" onclick="window.print()">បោះពុម្ពជា PDF</button>
            <?php endif; ?>
        </form>
    <?php else: ?>
        <div style="text-align: center; color: #888;">
            មិនមានទិន្នន័យសម្រាប់បង្ហាញទេ។ សូមបញ្ចូលទិន្នន័យថ្មី។
        </div>
    <?php endif; ?>

    <div>
        <span>អរគុណសម្រាប់ការប្រើប្រាស់</span>
        <br>
        <a href="input.php">បញ្ចូលទិន្នន័យថ្មី</a>
    </div>
</body>
</html>