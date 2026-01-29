<?php
require_once 'db_connect.php';

try {
    // Fetch data from all tables
    $office_stmt = $pdo->query("SELECT * FROM office_staff ORDER BY id DESC");
    $office_data = $office_stmt->fetchAll(PDO::FETCH_ASSOC);

    $store_318_stmt = $pdo->query("SELECT * FROM store_318_staff ORDER BY id DESC");
    $store_318_data = $store_318_stmt->fetchAll(PDO::FETCH_ASSOC);

    $warehouse_stmt = $pdo->query("SELECT * FROM warehouse_staff ORDER BY id DESC");
    $warehouse_data = $warehouse_stmt->fetchAll(PDO::FETCH_ASSOC);

    $new_staff_stmt = $pdo->query("SELECT * FROM new_staff ORDER BY id DESC");
    $new_staff_data = $new_staff_stmt->fetchAll(PDO::FETCH_ASSOC);

    $total_stmt = $pdo->query("SELECT * FROM total_staff ORDER BY id DESC");
    $total_data = $total_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "កំហុសក្នុងការទាញយកទិន្នន័យ: " . $e->getMessage();
    error_log("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>មើលទិន្នន័យបុគ្គលិក</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans Khmer', sans-serif;
            margin: 20px;
            background-color: #f9f9f9;
        }
        .section {
            margin: 20px 0;
            padding: 20px;
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        h2 {
            background-color: rgb(255, 96, 220);
            color: white;
            padding: 10px;
            margin: 0 0 20px 0;
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
        }
        .error {
            color: red;
            font-weight: bold;
            margin-bottom: 20px;
        }
        a {
            display: inline-block;
            margin: 20px 0;
            color: #4CAF50;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>ទិន្នន័យបុគ្គលិកទាំងអស់</h1>

    <?php if (isset($error_message)): ?>
        <div class="error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Office Staff Section -->
    <div class="section">
        <h2>បុគ្គលិកការិយាល័យកណ្តាល</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>ចំនួនសរុប</th>
                <th>ភេទស្រី</th>
                <th>ភេទប្រុស</th>
                <th>ទីតាំង</th>
            </tr>
            <?php foreach ($office_data as $row): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['total']; ?></td>
                    <td><?php echo $row['female']; ?></td>
                    <td><?php echo $row['male']; ?></td>
                    <td><?php echo $row['location']; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Store 318 Staff Section -->
    <div class="section">
        <h2>បុគ្គលិកហាងទំនិញ៣១៨</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>ចំនួនសរុប</th>
                <th>ភេទស្រី</th>
                <th>ភេទប្រុស</th>
            </tr>
            <?php foreach ($store_318_data as $row): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['total']; ?></td>
                    <td><?php echo $row['female']; ?></td>
                    <td><?php echo $row['male']; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Warehouse Staff Section -->
    <div class="section">
        <h2>បុគ្គលិកជំនាញតាមឃ្លាំង</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>ចំនួនសរុប</th>
                <th>CH1</th>
                <th>CKD</th>
                <th>ST1</th>
                <th>PSP</th>
            </tr>
            <?php foreach ($warehouse_data as $row): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['total']; ?></td>
                    <td><?php echo $row['ch1']; ?></td>
                    <td><?php echo $row['ckd']; ?></td>
                    <td><?php echo $row['st1']; ?></td>
                    <td><?php echo $row['psp']; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- New Staff Section -->
    <div class="section">
        <h2>បុគ្គលិកសុំច្បាប់ ដេអូស ប្តូរដេអូស និងចូលថ្មី</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>ល.រ</th>
                <th>ឈ្មោះ</th>
                <th>តួនាទី</th>
                <th>អធិប្បាយ</th>
                <th>ការិយាល័យកណ្តាល</th>
                <th>៣១៨</th>
                <th>ឃ្លាំង</th>
            </tr>
            <?php foreach ($new_staff_data as $row): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['number']; ?></td>
                    <td><?php echo $row['name']; ?></td>
                    <td><?php echo $row['role']; ?></td>
                    <td><?php echo $row['note']; ?></td>
                    <td><?php echo $row['office_central']; ?></td>
                    <td><?php echo $row['store_318']; ?></td>
                    <td><?php echo $row['warehouse']; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <!-- Total Staff Section -->
    <div class="section">
        <h2>សរុបបុគ្គលិកទាំងអស់</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>សរុប</th>
                <th>ការិយាល័យកណ្តាល</th>
                <th>៣១៨</th>
                <th>ឃ្លាំង</th>
                <th>សរុបទាំងអស់</th>
            </tr>
            <?php foreach ($total_data as $row): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['total']; ?></td>
                    <td><?php echo $row['office_central']; ?></td>
                    <td><?php echo $row['store_318']; ?></td>
                    <td><?php echo $row['warehouse']; ?></td>
                    <td><?php echo $row['grand_total']; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <a href="index.php">ត្រលប់ទៅទំព័រមុន</a>
</body>
</html>