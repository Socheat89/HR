<?php
require_once '../payroll/db_payroll.php';

// Get employee ID from GET parameter
$employee_id = isset($_GET['calc_employee_id']) ? $_GET['calc_employee_id'] : null;

if ($employee_id) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT * FROM payroll_records WHERE employee_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$employee_id]);
    $payroll = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>ប័ណ្ណប្រាក់ខែ - <?php echo $employee ? htmlspecialchars($employee['name']) : 'បោះពុម្ព'; ?></title>
    <style>
        body {
            font-family: 'Khmer OS', Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fff;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 10mm;
        }
        .header {
            text-align: center;
            margin-bottom: 5mm;
        }
        .header img {
            max-width: 50mm;
            height: auto;
        }
        .header h1 {
            font-size: 16px;
            color: #000;
            margin: 2mm 0 0;
        }
        .header p {
            font-size: 10px;
            color: #555;
            margin: 0;
        }
        .section {
            margin-bottom: 5mm;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            border: 1px solid #000;
        }
        th, td {
            border: 1px solid #000;
            padding: 2mm;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #fff;
            color: #000;
            font-weight: bold;
        }
        .total-row {
            font-weight: bold;
            background-color: #fff;
        }
        .print-btn {
            padding: 4px 8px;
            background-color: #2ecc71;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            margin-bottom: 5mm;
            font-size: 10px;
        }
        .print-btn:hover {
            background-color: #27ae60;
        }
        .company-info {
            text-align: center;
            margin-bottom: 5mm;
            font-size: 10px;
            color: #555;
        }
        .signature {
            margin-top: 5mm;
            font-size: 10px;
            color: #555;
        }

        /* A5 Portrait Print Styles */
        @media print {
            @page {
                size: A5 portrait;
                margin: 5mm;
            }
            body {
                margin: 0;
                padding: 0;
                background-color: #fff;
            }
            .container {
                width: 138mm; /* 148mm - 10mm margins */
                height: 200mm; /* 210mm - 10mm margins */
                padding: 0;
                margin: 0;
            }
            .print-btn {
                display: none;
            }
            .header {
                margin-bottom: 3mm;
            }
            .header img {
                max-width: 40mm;
            }
            .header h1 {
                font-size: 14px;
                margin: 1mm 0 0;
            }
            .header p {
                font-size: 9px;
            }
            .section {
                margin-bottom: 3mm;
            }
            table {
                font-size: 9px;
                border: 1px solid #000;
            }
            th, td {
                padding: 1.5mm;
                border: 1px solid #000;
            }
            .company-info {
                margin-bottom: 3mm;
                font-size: 9px;
            }
            .signature {
                margin-top: 3mm;
                font-size: 9px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($employee && $payroll): ?>
            <button class="print-btn" onclick="window.print()">បោះពុម្ព (Print)</button>

            <div class="header">
                <img src="logo.png" alt="Company Logo" style="display: none;" onload="this.style.display='block';"> <!-- Placeholder for logo -->
                <h1>ប័ណ្ណប្រាក់ខែ</h1>
                <p>កាលបរិច្ឆេទ: <?php echo date('F j, Y'); ?></p>
            </div>

         
            <div class="section">
                <table>
                    <tr>
                        <td>ឈ្មោះ:</td>
                        <td><?php echo htmlspecialchars($employee['name']); ?></td>
                    </tr>
                    <tr>
                        <td>តួនាទី:</td>
                        <td><?php echo htmlspecialchars($employee['position']); ?></td>
                    </tr>
                    <tr>
                        <td>ខែ/ឆ្នាំ:</td>
                        <td><?php echo htmlspecialchars($payroll['month']); ?></td>
                    </tr>
                </table>
            </div>

            <?php
            // Calculations
            $stmt = $pdo->prepare("SELECT reason, amount FROM deductions WHERE payroll_id = ?");
            $stmt->execute([$payroll['id']]);
            $deductions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_deductions = array_sum(array_column($deductions, 'amount'));

            $stmt = $pdo->prepare("SELECT type, amount FROM additions WHERE payroll_id = ?");
            $stmt->execute([$payroll['id']]);
            $additions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $base_salary = $payroll['new_salary'];
            $working_days = $payroll['working_days'];
            $daily_rate = $working_days > 0 ? $base_salary / $working_days : 0;
            $worked_salary = $daily_rate * $working_days;

            $ot_additions = array_filter($additions, function($a) { return $a['type'] === 'OT'; });
            $other_additions = array_filter($additions, function($a) { return $a['type'] !== 'OT'; });
            $ot_count = array_sum(array_column($ot_additions, 'amount'));
            $ot_amount = $ot_count * $daily_rate;
            $total_other_additions = array_sum(array_column($other_additions, 'amount'));
            $total_additions = $ot_amount + $total_other_additions;
            $net_salary = $worked_salary + $total_additions - $total_deductions;
            ?>

            <div class="section">
                <table>
                    <tr>
                        <th>SCAN OR HERE</th>
                        <th>$</th>
                    </tr>
                    <tr>
                        <td>ថ្ងៃធ្វើការ (Working Days)</td>
                        <td><?php echo number_format($worked_salary, 2); ?></td>
                    </tr>
                    <tr>
                        <td>ធ្វើការបន្ថែម (OT) <?php echo $ot_count; ?> ថ្ងៃ</td>
                        <td><?php echo number_format($ot_amount, 2); ?></td>
                    </tr>
                    <?php foreach ($other_additions as $addition): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($addition['type']); ?></td>
                            <td><?php echo number_format($addition['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($deductions as $deduction): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($deduction['reason']); ?></td>
                            <td><?php echo number_format($deduction['amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($deductions)): ?>
                        <tr>
                            <td>គ្មានការកាត់</td>
                            <td>0.00</td>
                        </tr>
                    <?php endif; ?>
                    <tr class="total-row">
                        <td>សរុបប្រាក់ខែសុទ្ធ (Net Pay)</td>
                        <td><?php echo number_format($net_salary, 2); ?></td>
                    </tr>
                </table>
            </div>

            <div class="signature">
                <p>ហត្ថលេខាបុគ្គលិក: ___________________________ កាលបរិច្ឆេទ: ___________</p>
                <p>អនុញ្ញាតដោយ: ___________________________ កាលបរិច្ឆេទ: ___________</p>
            </div>
        <?php else: ?>
            <button class="print-btn" onclick="window.print()">បោះពុម្ព (Print)</button>
            <p>គ្មានទិន្នន័យប្រាក់ខែដើម្បីបោះពុម្ព។ សូមជ្រើសរើសបុគ្គលិកជាមុនសិន។</p>
        <?php endif; ?>
    </div>
</body>
</html>