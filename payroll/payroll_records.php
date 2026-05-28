<?php
include '../payroll/db_payroll.php'; // Include the database connection

// Fetch payroll records from the database
$records = $pdo->query("SELECT * FROM payrolls ORDER BY created_at DESC");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll Records</title>
    <style>
        /* Styling similar to the previous page */
    </style>
</head>
<body>

<div class="container">
    <h2>Payroll Records</h2>

    <form method="post" action="export_excel.php">
        <button type="submit" name="export_excel">Export to Excel</button>
    </form>

    <form method="post" action="../prints/export_pdf.php">
        <button type="submit" name="export_pdf">Export to PDF</button>
    </form>

    <?php if ($records->rowCount() > 0): ?>
        <table>
            <tr>
                <th>ID</th>
                <th>Employee</th>
                <th>Base Salary</th>
                <th>Overtime</th>
                <th>Bonus</th>
                <th>Deductions</th>
                <th>Net Salary</th>
                <th>Bonus Detail</th>
                <th>Deductions Detail</th>
                <th>Created At</th>
            </tr>
            <?php while ($row = $records->fetch()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['requester_name']; ?></td>
                    <td><?php echo $row['base_salary']; ?></td>
                    <td><?php echo $row['overtime']; ?></td>
                    <td><?php echo $row['bonus']; ?></td>
                    <td><?php echo $row['deductions']; ?></td>
                    <td><?php echo $row['net_salary']; ?></td>
                    <td><?php echo $row['bonus_detail']; ?></td>
                    <td><?php echo $row['deduction_detail']; ?></td>
                    <td><?php echo $row['created_at']; ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No payroll records found.</p>
    <?php endif; ?>
</div>

</body>
</html>
