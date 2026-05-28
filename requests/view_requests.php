<?php
session_start();
include '../system/db.php';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role']; // "admin" or "user"

$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01'); // default start of the month
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-t'); // default end of the month

// Deduction rates (example)
$leave_deduction_rate = 5; // Example rate for leave
$late_deduction_rate = 5;  // Example rate for late arrivals

// Prepare the query with date filter
if ($user_role === 'admin') {
    $stmt = $pdo->prepare("
        SELECT
            user_id,
            SUM(request_type = 'leave') AS total_leave,
            SUM(request_type = 'ot') AS total_ot,
            SUM(forgot_scan_in = 1 OR forgot_scan_out = 1) AS total_forgot_scan,
            COUNT(late_hours > 0) AS total_late_count, -- Count late occurrences
            (9 - SUM(request_type = 'leave')) AS remaining_leave
        FROM employee_requests
        WHERE request_date BETWEEN ? AND ?
        GROUP BY user_id
    ");
    $stmt->execute([$start_date, $end_date]);
} else {
    $stmt = $pdo->prepare("
        SELECT
            user_id,
            SUM(request_type = 'leave') AS total_leave,
            SUM(request_type = 'ot') AS total_ot,
            SUM(forgot_scan_in = 1 OR forgot_scan_out = 1) AS total_forgot_scan,
            COUNT(late_hours > 0) AS total_late_count, -- Count late occurrences
            (9 - SUM(request_type = 'leave')) AS remaining_leave
        FROM employee_requests
        WHERE user_id = ? AND request_date BETWEEN ? AND ?
        GROUP BY user_id
    ");
    $stmt->execute([$user_id, $start_date, $end_date]);
}

// Fetch stats
$stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if no data found
if (empty($stats)) {
    $no_data_message = "No data found for this date range.";
} else {
    $no_data_message = "";
}

// Calculate total summary
$totals = [
    'leave' => 0,
    'ot' => 0,
    'forgot' => 0,
    'late' => 0,
    'remain' => 0,
    'money_deducted' => 0, // Total money deducted
];

foreach ($stats as $row) {
    $totals['leave'] += $row['total_leave'];
    $totals['ot'] += $row['total_ot'];
    $totals['forgot'] += $row['total_forgot_scan'];
    $totals['late'] += $row['total_late_count'];
    $totals['remain'] += max(0, $row['remaining_leave']);

    // Calculate money deducted based on leave and late arrivals
    $money_deducted = ($row['total_leave'] * $leave_deduction_rate) + ($row['total_late_count'] * $late_deduction_rate);
    $row['money_deducted'] = $money_deducted; // Add money_deducted to each row
}

// Get user names for display (only once)
$user_names_stmt = $pdo->prepare("SELECT id, name FROM users");
$user_names_stmt->execute();
$user_names = $user_names_stmt->fetchAll(PDO::FETCH_ASSOC);

// Map user IDs to names
$user_names_map = array_column($user_names, 'name', 'id');

// Find the name for the current user_id
$user_name = $user_names_map[$user_id] ?? 'មិនមានឈ្មោះ';
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>ស្ថិតិបុគ្គលិកប្រចាំខែ</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: "Khmer OS Siemreap", Arial, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 25px;
        }

        .date-filter-form {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            background-color: #ecf0f1;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .date-filter-form label {
            font-size: 14px;
            color: #34495e;
            margin-right: 10px;
        }

        .date-filter-form input[type="date"] {
            padding: 8px;
            font-size: 14px;
            border-radius: 5px;
            border: 1px solid #ccc;
            width: 200px;
        }

        .date-filter-form button {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            font-size: 14px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .date-filter-form button:hover {
            background-color: #2980b9;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            overflow-x: auto;
        }

        thead {
            background-color: #3498db;
            color: white;
        }

        th, td {
            padding: 12px 15px;
            text-align: center;
            border-bottom: 1px solid #ddd;
        }

        th {
            font-weight: bold;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            color: white;
            font-size: 0.9em;
        }

        .leave { background: #e74c3c; }
        .ot { background: #2ecc71; }
        .forgot { background: #f39c12; }
        .late { background: #9b59b6; }
        .remain { background: #3498db; }
        .money { background: #34495e; }

        @media screen and (max-width: 768px) {
            .date-filter-form {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .date-filter-form div {
                width: 100%;
            }

            .date-filter-form input[type="date"],
            .date-filter-form button {
                width: 100%;
            }

            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            th, td {
                font-size: 13px;
                padding: 8px;
            }

            .container {
                padding: 15px;
            }

            h2 {
                font-size: 18px;
            }

            .badge {
                font-size: 0.8em;
                padding: 4px 8px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <h2>ស្ថិតិបុគ្គលិកប្រចាំខែ: <?= htmlspecialchars($user_name) ?></h2>

    <form method="POST" action="" class="date-filter-form">
        <div>
            <label for="start_date">ថ្ងៃចាប់ផ្តើម:</label>
            <input type="date" name="start_date" value="<?= $start_date ?>" required>
        </div>
        <div>
            <label for="end_date">ទៅដល់:</label>
            <input type="date" name="end_date" value="<?= $end_date ?>" required>
        </div>
        <div>
            <button type="submit">ត្រួតពិនិត្យ</button>
        </div>
    </form>

    <?php if ($no_data_message): ?>
        <p style="text-align: center; color: red;"><?= $no_data_message ?></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>📆 ច្បាប់</th>
                    <th>💼 OT</th>
                    <th>❌ ភ្លេចស្កេន</th>
                    <th>🐢 មកយឺត</th>
                    <th>✅ ច្បាប់នៅសល់</th>
                    <th>💲 កាត់ប្រាក់</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats as $row): ?>
                    <tr>
                        <td><span class="badge leave"><?= $row['total_leave'] ?: 0 ?> ដង</span></td>
                        <td><span class="badge ot"><?= $row['total_ot'] ?: 0 ?> ដង</span></td>
                        <td><span class="badge forgot"><?= $row['total_forgot_scan'] ?: 0 ?> ដង</span></td>
                        <td><span class="badge late"><?= $row['total_late_count'] ?: 0 ?> ដង</span></td>
                        <td><span class="badge remain"><?= max(0, $row['remaining_leave']) ?> ថ្ងៃ</span></td>
                        <td><span class="badge money">$<?= number_format($row['money_deducted'], 2) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
