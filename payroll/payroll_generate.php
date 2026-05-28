<?php
include '../payroll/db_payroll.php'; // Include the database connection

// Fetch employee names from requests table (or any table with employees)
$employees = $pdo->query("SELECT DISTINCT requester_name FROM requests");

if (isset($_POST['generate'])) {
    // Retrieve form data
    $employee_id = $_POST['employee_id'];
    $base_salary = $_POST['base_salary'];
    $overtime = $_POST['overtime'] ?? 0;
    $bonus = $_POST['bonus'] ?? 0;
    $deductions = $_POST['deductions'] ?? 0;
    $bonus_detail = $_POST['bonus_detail'] ?? '';
    $deduction_detail = $_POST['deduction_detail'] ?? '';

    // Calculate net salary
    $net_salary = $base_salary + $overtime + $bonus - $deductions;

    // Insert data into the payrolls table
    $stmt = $pdo->prepare("INSERT INTO payrolls (requester_name, base_salary, overtime, bonus, deductions, net_salary, bonus_detail, deduction_detail) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$employee_id, $base_salary, $overtime, $bonus, $deductions, $net_salary, $bonus_detail, $deduction_detail]);

    // Success message
    echo "<script>alert('Payroll generated successfully for $employee_id. Net Salary: $$net_salary');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate Payroll</title>
    <style>
        /* Styling similar to the previous page */
    </style>
</head>
<body>

<div class="container">
    <h2>Payroll Generator</h2>

    <!-- Form to generate payroll -->
    <form action="../payroll/payroll_generate.php" method="post">
        <label>Select Employee</label>
        <select name="employee_id" required>
            <?php while ($row = $employees->fetch()) { ?>
                <option value="<?php echo $row['requester_name']; ?>"><?php echo $row['requester_name']; ?></option>
            <?php } ?>
        </select>

        <label>Base Salary ($)</label>
        <input type="number" name="base_salary" required step="0.01">

        <label>Overtime ($)</label>
        <input type="number" name="overtime" step="0.01">

        <label>Bonus ($)</label>
        <input type="number" name="bonus" step="0.01">

        <label>Deductions ($)</label>
        <input type="number" name="deductions" step="0.01">

        <label>Bonus Detail</label>
        <textarea name="bonus_detail"></textarea>

        <label>Deductions Detail</label>
        <textarea name="deduction_detail"></textarea>

        <button type="submit" name="generate">Generate Payroll</button>
    </form>
</div>

</body>
</html>
