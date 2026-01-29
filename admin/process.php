<?php
require_once 'includes/db.php'; // Changed from db_connect.php to your file

$action = $_POST['action'] ?? $_GET['action'];

switch($action) {
    case 'add_employee':
        $stmt = $pdo->prepare("INSERT INTO employees (first_name, last_name, position, hourly_rate, tax_rate, hire_date) 
            VALUES (:first_name, :last_name, :position, :hourly_rate, :tax_rate, :hire_date)");
        $stmt->execute([
            'first_name' => $_POST['first_name'],
            'last_name' => $_POST['last_name'],
            'position' => $_POST['position'],
            'hourly_rate' => $_POST['hourly_rate'],
            'tax_rate' => $_POST['tax_rate'],
            'hire_date' => $_POST['hire_date']
        ]);
        break;

    case 'add_payroll':
        // Get employee details
        $stmt = $pdo->prepare("SELECT hourly_rate, tax_rate FROM employees WHERE id = ?");
        $stmt->execute([$_POST['employee_id']]);
        $employee = $stmt->fetch();
        
        $regular_pay = $employee['hourly_rate'] * $_POST['hours_worked'];
        $overtime_pay = ($employee['hourly_rate'] * 1.5) * ($_POST['overtime_hours'] ?? 0);
        $gross_pay = $regular_pay + $overtime_pay;
        $tax_amount = $gross_pay * ($employee['tax_rate'] / 100);
        $net_pay = $gross_pay - $tax_amount;

        $stmt = $pdo->prepare("INSERT INTO payroll (employee_id, pay_period_start, pay_period_end, hours_worked, overtime_hours, gross_pay, tax_amount, net_pay, payment_date) 
            VALUES (:employee_id, :pay_period_start, :pay_period_end, :hours_worked, :overtime_hours, :gross_pay, :tax_amount, :net_pay, :payment_date)");
        $stmt->execute([
            'employee_id' => $_POST['employee_id'],
            'pay_period_start' => $_POST['pay_period_start'],
            'pay_period_end' => $_POST['pay_period_end'],
            'hours_worked' => $_POST['hours_worked'],
            'overtime_hours' => $_POST['overtime_hours'] ?? 0,
            'gross_pay' => $gross_pay,
            'tax_amount' => $tax_amount,
            'net_pay' => $net_pay,
            'payment_date' => date('Y-m-d')
        ]);
        break;

    case 'delete_employee':
        $stmt = $pdo->prepare("UPDATE employees SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        break;

    case 'delete_payroll':
        $stmt = $pdo->prepare("DELETE FROM payroll WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        break;
}

header("Location: index.php");
exit();