<?php
// =================================================================
// SCRIPT សម្រាប់ដំណើរការគណនាប្រាក់ខែដោយស្វ័យប្រវត្តិ (CRON JOB)
// =================================================================

// បង្ហាញ Error ទាំងអស់ (សម្រាប់ Debugging)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- ការតភ្ជាប់មូលដ្ឋានទិន្នន័យ (ចម្លងពីไฟล์หลัก) ---
$servername = "localhost";
$username = "samann1_payroll-manager";
$password = "payroll-manager@2025";
$dbname = "samann1_payroll-manager";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Cron Job Failed: Connection Error: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

echo "Cron Job Started: " . date('Y-m-d H:i:s') . "\n";

// --- កំណត់រយៈពេលគិតប្រាក់ខែ (ឧ: ពីថ្ងៃទី១ ដល់ថ្ងៃចុងខែ) ---
$date_from = date('Y-m-01');
$date_to = date('Y-m-t'); // 't' យកថ្ងៃចុងក្រោយនៃខែបច្ចុប្បន្ន

// --- ពិនិត្យមើលថាតើប្រាក់ខែសម្រាប់ខែនេះត្រូវបានដំណើរការរួចហើយឬនៅ ដើម្បីការពារការដំណើរការซ้ำซ้อน ---
$check_stmt = $conn->prepare("SELECT id FROM payrolls WHERE pay_period_to = ? LIMIT 1");
$check_stmt->bind_param("s", $date_to);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
if ($check_result->num_rows > 0) {
    die("Payroll for the period ending on $date_to has already been processed. Exiting.\n");
}
echo "Processing payroll for period: $date_from to $date_to\n";

// --- ចាប់ផ្តើម Transaction ---
$conn->begin_transaction();

try {
    // --- ទាញយកបុគ្គលិកទាំងអស់ ---
    $employees_res = $conn->query("SELECT * FROM employees");
    if ($employees_res->num_rows === 0) {
        throw new Exception("No employees found.");
    }

    $processed_count = 0;
    while ($employee = $employees_res->fetch_assoc()) {
        $emp_id = $employee['id'];
        $basic_salary = $employee['basic_salary'];

        // --- សម្រាប់ស្វ័យប្រវត្តិ យើងគណនាแค่ប្រាក់ឧបត្ថម្ភ និងការកាត់ប្រាក់ប្រចាំ ---
        // OT និងការកាត់ប្រាក់ផ្សេងៗ (កម្ចី, ឈប់...) គឺต้องใส่ដោយដៃ
        $total_allowance = 0;
        $allowances_breakdown = [];
        $allowances_res = $conn->query("SELECT * FROM allowances WHERE employee_id = $emp_id");
        while($row = $allowances_res->fetch_assoc()) {
            $total_allowance += $row['amount'];
            $allowances_breakdown[] = ['type' => $row['allowance_type'], 'amount' => $row['amount']];
        }

        $gross_salary = $basic_salary + $total_allowance; // OT គឺ 0 ក្នុងការគណនាស្វ័យប្រវត្តិ

        $total_deduction = 0;
        $deductions_breakdown = [];
        $deductions_res = $conn->query("SELECT * FROM deductions WHERE employee_id = $emp_id");
        while($row = $deductions_res->fetch_assoc()) {
            $total_deduction += $row['amount'];
            $deductions_breakdown[] = ['type' => $row['deduction_type'], 'amount' => $row['amount']];
        }

        $net_salary = $gross_salary - $total_deduction;

        // --- បង្កើត JSON data ---
        $payroll_data = [
            'employee_details' => $employee,
            'pay_period' => ['from' => $date_from, 'to' => $date_to],
            'earnings' => [
                'basic_salary' => $basic_salary,
                'allowances' => $allowances_breakdown,
                'ot_details' => ['days' => 0, 'rate' => 0, 'total' => 0], // OT គឺ 0
            ],
            'deductions' => $deductions_breakdown,
            'summary' => [
                'gross_salary' => $gross_salary,
                'total_deductions' => $total_deduction,
                'net_salary' => $net_salary
            ]
        ];
        $payroll_data_json = json_encode($payroll_data, JSON_UNESCAPED_UNICODE);

        // --- បញ្ចូលទៅក្នុងតារាង payrolls ---
        // យើងមិនចាំបាច់បញ្ជាក់ is_new ទេ ព្រោះ DEFAULT គឺ 1
        $stmt = $conn->prepare("INSERT INTO payrolls (employee_id, pay_period_from, pay_period_to, basic_salary, gross_salary, total_deductions, net_salary, payroll_data_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdddds", $emp_id, $date_from, $date_to, $basic_salary, $gross_salary, $total_deduction, $net_salary, $payroll_data_json);
        $stmt->execute();
        $processed_count++;
        echo " - Processed for: " . $employee['first_name'] . "\n";
    }

    // --- រក្សាទុកការเปลี่ยนแปลง ---
    $conn->commit();
    echo "Successfully processed payroll for $processed_count employees.\n";

} catch (Exception $e) {
    // --- បរាជ័យ, ย้อนกลับការเปลี่ยนแปลง ---
    $conn->rollback();
    die("An error occurred during payroll processing: " . $e->getMessage() . "\n");
}

$conn->close();
echo "Cron Job Finished: " . date('Y-m-d H:i:s') . "\n";
?>