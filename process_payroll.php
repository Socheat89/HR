<?php
require_once 'db_payroll.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payroll Calculation</title>
    <style>
        .container { max-width: 1000px; margin: 20px auto; padding: 20px; }
        .section { margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input, select { width: 100%; padding: 8px; box-sizing: border-box; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        th { background-color: #f2f2f2; }
        .success { color: green; margin-bottom: 15px; }
        button { padding: 8px 15px; margin: 5px 0; }
        .calculation-details { background-color: #f9f9f9; padding: 10px; }
        .debug { color: #666; font-size: 12px; margin-top: 10px; }
        .print-btn {
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
        .print-btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Calculation Section -->
        <div class="section">
            <h2>គណនាប្រាក់ខែ (Payroll Calculation)</h2>
            <form method="GET">
                <div class="form-group">
                    <label>ជ្រើសរើសបុគ្គលិក (Select Employee):</label>
                    <select name="calc_employee_id" onchange="this.form.submit()">
                        <option value="">-- ជ្រើសរើស --</option>
                        <?php
                        $stmt = $pdo->query("SELECT * FROM employees");
                        while($row = $stmt->fetch()) {
                            $selected = isset($_GET['calc_employee_id']) && $_GET['calc_employee_id'] == $row['id'] ? 'selected' : '';
                            echo "<option value='{$row['id']}' $selected>{$row['name']} ({$row['position']})</option>";
                        }
                        ?>
                    </select>
                </div>
            </form>

            <?php
            if(isset($_GET['calc_employee_id']) && !empty($_GET['calc_employee_id'])) {
                $employee_id = $_GET['calc_employee_id'];
                $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
                $stmt->execute([$employee_id]);
                $employee = $stmt->fetch();
                
                if($employee) {
                    $stmt = $pdo->prepare("SELECT * FROM payroll_records WHERE employee_id = ? ORDER BY created_at DESC LIMIT 1");
                    $stmt->execute([$employee_id]);
                    $payroll = $stmt->fetch();
                    
                    if($payroll) {
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
                        <div class="calculation-details">
                            <button class="print-btn" onclick="window.location.href='print_payroll.php?calc_employee_id=<?php echo $employee_id; ?>'">បោះពុម្ព (Print)</button>
                            <h3>ព័ត៌មានលម្អិតនៃការគណនា (Calculation Details)</h3>
                            <p><strong>ឈ្មោះ (Name):</strong> <?php echo htmlspecialchars($employee['name']); ?></p>
                            <p><strong>ខែ (Month):</strong> <?php echo htmlspecialchars($payroll['month']); ?></p>
                            <p><strong>ប្រាក់ខែមូលដ្ឋាន (Base Salary):</strong> $<?php echo number_format($base_salary, 2); ?></p>
                            <p><strong>ថ្ងៃធ្វើការ (Working Days):</strong> <?php echo $working_days; ?></p>
                            <p><strong>អត្រាប្រាក់ខែក្នុងមួយថ្ងៃ (Daily Rate):</strong> $<?php echo number_format($daily_rate, 2); ?></p>
                            <p><strong>ប្រាក់ខែសម្រាប់ថ្ងៃធ្វើការ (Worked Salary):</strong> $<?php echo number_format($worked_salary, 2); ?></p>
                            
                            <h4>កាត់លុយ (Deductions):</h4>
                            <table>
                                <tr><th>មូលហេតុ</th><th>ចំនួន ($)</th></tr>
                                <?php 
                                if(!empty($deductions)) {
                                    foreach($deductions as $deduction) { 
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($deduction['reason']); ?></td>
                                            <td>$<?php echo number_format($deduction['amount'], 2); ?></td>
                                        </tr>
                                        <?php 
                                    }
                                } else {
                                    echo "<tr><td colspan='2'>មិនមានការកាត់លុយ</td></tr>";
                                }
                                ?>
                                <tr><td><strong>សរុប</strong></td><td><strong>$<?php echo number_format($total_deductions, 2); ?></strong></td></tr>
                            </table>
                            
                            <h4>ប្រាក់ថែមថ្ងៃ (Overtime):</h4>
                            <table>
                                <tr><th>ប្រភេទ</th><th>ចំនួនថ្ងៃ</th><th>ទឹកប្រាក់ ($)</th></tr>
                                <tr>
                                    <td>OT</td>
                                    <td><?php echo number_format($ot_count, 2); ?> ថ្ងៃ (Days)</td>
                                    <td>$<?php echo number_format($ot_amount, 2); ?></td>
                                </tr>
                            </table>

                            <h4>បូកបន្ថែមផ្សេងៗ (Other Additions):</h4>
                            <table>
                                <tr><th>ប្រភេទ</th><th>ទឹកប្រាក់ ($)</th></tr>
                                <?php 
                                if(!empty($other_additions)) {
                                    foreach($other_additions as $addition) { 
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($addition['type']); ?></td>
                                            <td>$<?php echo number_format($addition['amount'], 2); ?></td>
                                        </tr>
                                        <?php 
                                    }
                                } else {
                                    echo "<tr><td colspan='3'>មិនមានការបូកបន្ថែមផ្សេងៗ</td></tr>";
                                }
                                ?>
                                <tr><td><strong>សរុបបន្ថែម (Total Additions)</strong></td><td><strong>$<?php echo number_format($total_additions, 2); ?></strong></td></tr>
                            </table>
                            
                            <h4>លទ្ធផល (Result):</h4>
                            <p><strong>ប្រាក់ខែសុទ្ធ (Net Salary):</strong> $<?php echo number_format($net_salary, 2); ?></p>
                        </div>
                        <?php
                    } else {
                        echo "<p>មិនទាន់មានកំណត់ត្រាប្រាក់ខែសម្រាប់បុគ្គលិកនេះទេ!</p>";
                    }
                } else {
                    echo "<p>បុគ្គលិកមិនត្រូវបានរកឃើញ!</p>";
                }
            }
            ?>
            <a href="payroll.php">ត្រឡប់ទៅទំព័រគ្រប់គ្រងប្រាក់ខែ</a>
        </div>
    </div>
</body>
</html>