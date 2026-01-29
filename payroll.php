<?php
include 'db_payroll.php'; // Include the database connection

if (isset($_POST['generate'])) {
    // Retrieve form data
    $name = $_POST['requester_name'];
    $base_salary = $_POST['base_salary'];
    $overtime = $_POST['overtime'] ?? 0;
    $bonus = $_POST['bonus'] ?? 0;
    $deductions = $_POST['deductions'] ?? 0;

    // Calculate net salary
    $net_salary = $base_salary + $overtime + $bonus - $deductions;

    // Insert data into the database
    $stmt = $pdo->prepare("INSERT INTO payrolls (requester_name, base_salary, overtime, bonus, deductions, net_salary) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $base_salary, $overtime, $bonus, $deductions, $net_salary]);

    // Success message
    echo "<script>alert('Payroll generated successfully for $name. Net Salary: $$net_salary');</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 40px;
        }
        .container {
            max-width: 900px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            color: #333;
        }
        form {
            margin-bottom: 30px;
        }
        input, select {
            padding: 8px;
            width: 100%;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        label {
            font-weight: bold;
            display: block;
        }
        button {
            padding: 10px 20px;
            background-color: #3498db;
            border: none;
            color: white;
            border-radius: 6px;
            cursor: pointer;
        }
        button:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Payroll Generator</h2>

    <!-- Form to generate payroll -->
    <form action="payroll.php" method="post">
        <label>Requester Name</label>
        <input type="text" name="requester_name" required>

        <label>Base Salary ($)</label>
        <input type="number" name="base_salary" required step="0.01">

        <label>Overtime ($)</label>
        <input type="number" name="overtime" step="0.01">

        <label>Bonus ($)</label>
        <input type="number" name="bonus" step="0.01">

        <label>Deductions ($)</label>
        <input type="number" name="deductions" step="0.01">

        <button type="submit" name="generate">Generate Payroll</button>
    </form>

    <!-- Display payroll records -->
    <h3>Payroll Records</h3>
    <?php
    // Fetch and display all payroll records
    $stmt = $pdo->query("SELECT * FROM payrolls ORDER BY created_at DESC");
    if ($stmt->rowCount() > 0) {
        echo "<table border='1' cellspacing='0' cellpadding='10'>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Base Salary</th>
                        <th>Overtime</th>
                        <th>Bonus</th>
                        <th>Deductions</th>
                        <th>Net Salary</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>";

        while ($row = $stmt->fetch()) {
            echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['requester_name']}</td>
                    <td>\${$row['base_salary']}</td>
                    <td>\${$row['overtime']}</td>
                    <td>\${$row['bonus']}</td>
                    <td>\${$row['deductions']}</td>
                    <td>\${$row['net_salary']}</td>
                    <td>{$row['created_at']}</td>
                </tr>";
        }

        echo "</tbody></table>";
    } else {
        echo "<p>No payroll records found.</p>";
    }
    ?>
</div>

</body>
</html>
