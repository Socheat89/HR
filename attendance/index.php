<?php
require_once 'db_connect.php';

$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d'); // Default to current date

try {
    // Office Staff
    $stmt_office = $pdo->prepare("SELECT total, female, male, created_at FROM office_staff WHERE DATE(created_at) = ? ORDER BY created_at DESC");
    $stmt_office->execute([$selected_date]);
    $office_records = $stmt_office->fetchAll(PDO::FETCH_ASSOC);

    // Store 318 Staff
    $stmt_store_318 = $pdo->prepare("SELECT total, female, male, created_at FROM store_318_staff WHERE DATE(created_at) = ? ORDER BY created_at DESC");
    $stmt_store_318->execute([$selected_date]);
    $store_318_records = $stmt_store_318->fetchAll(PDO::FETCH_ASSOC);

    // Warehouse Staff
    $stmt_warehouse = $pdo->prepare("SELECT total, ch1, ckd, st1, psp, created_at FROM warehouse_staff WHERE DATE(created_at) = ? ORDER BY created_at DESC");
    $stmt_warehouse->execute([$selected_date]);
    $warehouse_records = $stmt_warehouse->fetchAll(PDO::FETCH_ASSOC);

    // New Staff
    $stmt_new_staff = $pdo->prepare("SELECT number, name, role, note, office_central, store_318, warehouse, created_at FROM new_staff WHERE DATE(created_at) = ? ORDER BY created_at DESC");
    $stmt_new_staff->execute([$selected_date]);
    $new_staff_records = $stmt_new_staff->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "កំហុសក្នុងការទាក់ទងទិន្នន័យ: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>មើលទិន្នន័យបុគ្គលិកតាមថ្ងៃ</title>
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
            background-color: #0011ff;
            color: rgb(233, 198, 1);
        }
        .section-header {
            background-color: rgb(233, 198, 1);
            color: #0011ff;
            font-weight: bold;
        }
        .overall-total {
            background-color: #ececec9c;
            font-weight: bold;
            font-size: 1.1em;
        }
        .error {
            color: red;
            text-align: center;
            margin-bottom: 15px;
        }
        .date-form {
            text-align: center;
            margin-bottom: 20px;
        }
        input[type="date"] {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Noto Sans Khmer', sans-serif;
        }
        button, .insert-btn {
            background-color: #4CAF50;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        button:hover, .insert-btn:hover {
            background-color: #45a049;
        }
        .general-comment {
            margin-top: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }
        .general-comment input[type="text"] {
            width: 50%;
            padding: 5px;
            border: none;
            background-color: transparent;
            font-family: 'Noto Sans Khmer', sans-serif;
            font-size: 1em;
            text-align: center;
        }
        a {
            color: #2196F3;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Date Selection Form -->
        <div class="date-form">
            <form method="GET" action="">
                <label for="date">ជ្រើសរើសថ្ងៃ: </label>
                <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($selected_date); ?>">
                <button type="submit">មើល</button>
            </form>
        </div>
        <h1>របាយការណ៍វត្តមានបុគ្គលិកនៅការិយាល័យកណ្តាល និងតាមឃ្លាំង</h1>

        <?php if (isset($error_message)): ?>
            <div class="error"><?php echo $error_message; ?></div>
        <?php endif; ?>

  <!-- General Comment -->
        <div class="general-comment">
            <input type="text" id="general_comment" name="general_comment" 
                   value="ថ្ងៃទី <?php echo date('d', strtotime($selected_date)); ?> ខែ <?php echo date('m', strtotime($selected_date)); ?> ឆ្នាំ <?php echo date('Y', strtotime($selected_date)); ?>" 
                   readonly>
        </div>

        <!-- Office Staff Section -->
        <?php $office_total = 0; ?>
        <table>
            <tr><th colspan="3" class="section-header">បុគ្គលិកការិយាល័យកណ្តាល</th></tr>
            <tr>
                <th>ចំនួនសរុប</th>
                <th>បុគ្គលិកភេទស្រី</th>
                <th>បុគ្គលិកភេទប្រុស</th>
            </tr>
            <?php
            if ($office_records) {
                foreach ($office_records as $row) {
                    $office_total += $row['total'];
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['total']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['female']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['male']) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='3'>មិនមានទិន្នន័យសម្រាប់ថ្ងៃនេះ</td></tr>";
            }
            ?>
        </table>

        <!-- Store 318 Staff Section -->
        <?php $store_318_total = 0; ?>
        <table>
            <tr><th colspan="3" class="section-header">បុគ្គលិកហាងទំនិញ៣១៨</th></tr>
            <tr>
                <th>ចំនួនសរុប</th>
                <th>បុគ្គលិកភេទស្រី</th>
                <th>បុគ្គលិកភេទប្រុស</th>
            </tr>
            <?php
            if ($store_318_records) {
                foreach ($store_318_records as $row) {
                    $store_318_total += $row['total'];
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['total']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['female']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['male']) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='3'>មិនមានទិន្នន័យសម្រាប់ថ្ងៃនេះ</td></tr>";
            }
            ?>
        </table>

        <!-- Warehouse Staff Section -->
        <?php $warehouse_total = 0; ?>
        <table>
            <tr><th colspan="5" class="section-header">បុគ្គលិកជំនាញតាមឃ្លាំង</th></tr>
            <tr>
                <th>ចំនួនសរុប</th>
                <th>CH1</th>
                <th>CKD</th>
                <th>ST1</th>
                <th>PSP</th>
            </tr>
            <?php
            if ($warehouse_records) {
                foreach ($warehouse_records as $row) {
                    $warehouse_total += $row['total'];
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['total']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['ch1']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['ckd']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['st1']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['psp']) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='5'>មិនមានទិន្នន័យសម្រាប់ថ្ងៃនេះ</td></tr>";
            }
            ?>
        </table>

        <!-- New Staff Section -->
        <?php
        $new_staff_total = 0;
        $new_staff_office = 0;
        $new_staff_store = 0;
        $new_staff_warehouse = 0;
        ?>
        <table>
            <tr><th colspan="7" class="section-header">បុគ្គលិកសុំច្បាប់ ដេអូស ប្តូរដេអូស និងចូលថ្មី</th></tr>
            <tr>
                <th>ល.រ</th>
                <th>ឈ្មោះ</th>
                <th>តួនាទី</th>
                <th>អធិប្បាយ</th>
                <th>ផ្នែកបើកកង់បី</th>
                <th>ផ្នែកលើកទំនិញ</th>
                <th>រថយន្ត</th>
            </tr>
            <?php
            if ($new_staff_records) {
                foreach ($new_staff_records as $row) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['number']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['role']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['note']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['office_central']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['store_318']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['warehouse']) . "</td>";
                    echo "</tr>";
                    $new_staff_office += $row['office_central'];
                    $new_staff_store += $row['store_318'];
                    $new_staff_warehouse += $row['warehouse'];
                }
            } else {
                echo "<tr><td colspan='7'>មិនមានទិន្នន័យសម្រាប់ថ្ងៃនេះ</td></tr>";
            }
            $new_staff_total = $new_staff_office + $new_staff_store + $new_staff_warehouse;
            ?>
        </table>

        <!-- Overall Total Section -->
        <table>
            <tr><th colspan="5" class="section-header">សរុបរួម</th></tr>
            <tr>
                <th>ផ្នែកបើករថយន្ត</th>
                <th>ម៉ូតូរ៉ឺម៉កកង់បី</th>
                <th>លើកទំនិញ</th>
                <th>បុគ្គលិកថ្មី</th>
                <th>សរុបទាំងអស់</th>
            </tr>
            <tr class="overall-total">
                <td><?php echo htmlspecialchars($office_total); ?></td>
                <td><?php echo htmlspecialchars($store_318_total); ?></td>
                <td><?php echo htmlspecialchars($warehouse_total); ?></td>
                <td><?php echo htmlspecialchars($new_staff_total); ?></td>
                <td><?php echo htmlspecialchars($office_total + $store_318_total + $warehouse_total + $new_staff_total); ?></td>
            </tr>
        </table>

      
        <!-- Navigation Links -->
        <p>
            <a href="#" onclick="window.history.back(); return false;">ត្រឡប់ក្រោយ</a>
            <a href="input.php" class="insert-btn">បញ្ចូលទិន្នន័យថ្មី</a>
        </p>
    </div>
</body>
</html>