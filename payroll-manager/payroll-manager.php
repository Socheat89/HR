<?php
// =================================================================
// SECTION -1: LOGIN & ROLE CHECK
// =================================================================
session_start();
// ពិនិត្យមើលថាតើអ្នកប្រើប្រាស់បានចូលឬអត់, ប្រសិនបើមិនបានចូលទេ បញ្ជូនពួកគេទៅកាន់ទំព័រចូល
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}
// រក្សាទុក Role របស់អ្នកប្រើប្រាស់ក្នុងអថេរ ដើម្បីងាយស្រួលប្រើ
$user_role = $_SESSION["role"];


// =================================================================
// SECTION 0: COMPANY & PAYMENT CONFIGURATION
// =================================================================
$company_name = "ក្រុមហ៊ុន វ៉ាន់វ៉ាន់ ឯ.ក";
$company_address = "ភូមិព្រៃទា, សង្កាត់ចោមចៅ, ខណ្ឌពោធិ៍សែនជ័យ, រាជធានីភ្នំពេញ";
$company_logo_url = "https://i.ibb.co/4HWy3xb/Logo-Van-Van-2.png";


// =================================================================
// SECTION 1: DATABASE & AJAX REQUEST HANDLER
// =================================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Phnom_Penh');

$servername = "localhost";
$username = "samann1_payroll-manager";
$password = "payroll-manager@2025";
$dbname = "samann1_payroll-manager";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]));
    } else {
        die("ការតភ្ជាប់បានបរាជ័យ: " . $conn->connect_error);
    }
}
$conn->set_charset("utf8mb4");

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? $_GET['action'] ?? null;
    $id = $_POST['id'] ?? $_GET['id'] ?? null;

    function send_response($status, $message, $data = null) {
        $response = ['status' => $status, 'message' => $message];
        if ($data !== null) { $response['data'] = $data; }
        echo json_encode($response);
        exit();
    }
    
    switch ($action) {
        case 'add_employee':
            $stmt = $conn->prepare("INSERT INTO employees (employee_id, first_name, last_name, position, employee_type, basic_salary, qr_code_string) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssds", $_POST['employee_id'], $_POST['first_name'], $_POST['last_name'], $_POST['position'], $_POST['employee_type'], $_POST['basic_salary'], $_POST['qr_code_string']);
            if ($stmt->execute()) { send_response('success', 'បុគ្គលិកត្រូវបានបន្ថែមដោយជោគជ័យ!'); }
            else { send_response('error', 'ការបន្ថែមបុគ្គលិកមានបញ្ហា: ' . $stmt->error); }
            break;

        case 'update_employee':
            $stmt = $conn->prepare("UPDATE employees SET employee_id=?, first_name=?, last_name=?, position=?, employee_type=?, basic_salary=?, qr_code_string=? WHERE id=?");
            $stmt->bind_param("sssssdsi", $_POST['employee_id'], $_POST['first_name'], $_POST['last_name'], $_POST['position'], $_POST['employee_type'], $_POST['basic_salary'], $_POST['qr_code_string'], $_POST['id']);
            if ($stmt->execute()) { send_response('success', 'ព័ត៌មានបុគ្គលិកត្រូវបានធ្វើបច្ចុប្បន្នភាព!'); }
            else { send_response('error', 'ការធ្វើបច្ចុប្បន្នភាពមានបញ្ហា: ' . $stmt->error); }
            break;

        case 'delete_employee':
            $stmt = $conn->prepare("DELETE FROM employees WHERE id=?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) { send_response('success', 'បុគ្គលិកត្រូវបានលុប!'); }
            else { send_response('error', 'ការលុបមានបញ្ហា: ' . $stmt->error); }
            break;

        case 'add_allowance': case 'add_deduction':
            $table = ($action === 'add_allowance') ? 'allowances' : 'deductions';
            $type_col = ($action === 'add_allowance') ? 'allowance_type' : 'deduction_type';
            $stmt = $conn->prepare("INSERT INTO $table (employee_id, $type_col, amount) VALUES (?, ?, ?)");
            $stmt->bind_param("isd", $_POST['employee_id'], $_POST['type'], $_POST['amount']);
            if ($stmt->execute()) { send_response('success', 'ទិន្នន័យត្រូវបានបន្ថែម!'); }
            else { send_response('error', 'ការបន្ថែមមានបញ្ហា: ' . $stmt->error); }
            break;
            
        case 'delete_allowance': case 'delete_deduction':
            $table = ($action === 'delete_allowance') ? 'allowances' : 'deductions';
            $item_id = $_GET['item_id'] ?? 0;
            $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
            $stmt->bind_param("i", $item_id);
            if ($stmt->execute()) { send_response('success', 'ទិន្នន័យត្រូវបានលុប!'); }
            else { send_response('error', 'ការលុបមានបញ្ហា: ' . $stmt->error); }
            break;

        case 'save_monthly_data':
            $payroll_period_end_date = $_POST['date_to'];
            $employee_ids = $_POST['employee_ids'] ?? [];
            if (!empty($employee_ids)) {
                $stmt = $conn->prepare("INSERT INTO monthly_payroll_data (employee_id, payroll_month, ot_days, ot_rate_per_day, unpaid_leave_deduction, loan_deduction, other_deduction) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE ot_days = VALUES(ot_days), ot_rate_per_day = VALUES(ot_rate_per_day), unpaid_leave_deduction = VALUES(unpaid_leave_deduction), loan_deduction = VALUES(loan_deduction), other_deduction = VALUES(other_deduction)");
                foreach ($employee_ids as $emp_id) {
                    $stmt->bind_param("isddddd", $emp_id, $payroll_period_end_date, $_POST['ot_days'][$emp_id], $_POST['ot_rate_per_day'][$emp_id], $_POST['unpaid_leave_deduction'][$emp_id], $_POST['loan_deduction'][$emp_id], $_POST['other_deduction'][$emp_id]);
                    $stmt->execute();
                }
                send_response('success', 'ទិន្នន័យពង្រាងត្រូវបានរក្សាទុក!');
            } else { send_response('error', 'មិនមានបុគ្គលិកដើម្បីរក្សាទុក!'); }
            break;

        case 'process_bulk_payroll':
            $date_from = $_POST['date_from']; $date_to = $_POST['date_to'];
            $employee_ids = $_POST['employee_ids'] ?? [];
            if (empty($employee_ids)) { send_response('error', 'សូមជ្រើសរើសបុគ្គលិកយ៉ាងតិចម្នាក់!'); }

            // === កំណត់ថ្ងៃបច្ចុប្បន្ន​សម្រាប់​ការ​ត្រួតពិនិត្យ ===
            $today_date = date('Y-m-d');

            $conn->begin_transaction();
            try {
                foreach ($employee_ids as $emp_id) {
                    $emp_res = $conn->query("SELECT * FROM employees WHERE id = $emp_id"); $employee = $emp_res->fetch_assoc();
                    $basic_salary = $employee['basic_salary'];
                    $ot_days = $_POST['ot_days'][$emp_id] ?? 0; $ot_rate_per_day = $_POST['ot_rate_per_day'][$emp_id] ?? 0;
                    $unpaid_leave = $_POST['unpaid_leave_deduction'][$emp_id] ?? 0; $loan = $_POST['loan_deduction'][$emp_id] ?? 0;
                    $other_deduction = $_POST['other_deduction'][$emp_id] ?? 0;
                    
                    $allowances_res = $conn->query("SELECT * FROM allowances WHERE employee_id = $emp_id");
                    $total_allowance = 0; $allowances_breakdown = [];
                    while($row = $allowances_res->fetch_assoc()) { $total_allowance += $row['amount']; $allowances_breakdown[] = ['type' => $row['allowance_type'], 'amount' => $row['amount']]; }
                    
                    $total_ot_earning = $ot_days * $ot_rate_per_day;
                    $gross_salary = $basic_salary + $total_allowance + $total_ot_earning;
                    
                    $deductions_res = $conn->query("SELECT * FROM deductions WHERE employee_id = $emp_id");
                    $total_deduction = 0; $deductions_breakdown = [];
                    while($row = $deductions_res->fetch_assoc()) { $total_deduction += $row['amount']; $deductions_breakdown[] = ['type' => $row['deduction_type'], 'amount' => $row['amount']]; }
                    
                    if($unpaid_leave > 0) { $total_deduction += $unpaid_leave; $deductions_breakdown[] = ['type' => 'ឈប់សម្រាក(សុំច្បាប់)', 'amount' => $unpaid_leave]; }
                    if($loan > 0) { $total_deduction += $loan; $deductions_breakdown[] = ['type' => 'កម្ចី', 'amount' => $loan]; }
                    if($other_deduction > 0) { $total_deduction += $other_deduction; $deductions_breakdown[] = ['type' => 'កាត់ប្រាក់ផ្សេងៗ', 'amount' => $other_deduction]; }
                    
                    $net_salary = $gross_salary - $total_deduction;
                    
                    $payroll_data = [
                        'pay_period' => ['from' => $date_from, 'to' => $date_to],
                        'earnings' => [
                            'basic_salary' => $basic_salary,
                            'allowances' => $allowances_breakdown,
                            'ot_details' => ['days' => $ot_days, 'rate' => $ot_rate_per_day, 'total' => $total_ot_earning],
                        ],
                        'deductions' => $deductions_breakdown,
                        'summary' => [
                            'gross_salary' => $gross_salary,
                            'total_deductions' => $total_deduction,
                            'net_salary' => $net_salary
                        ]
                    ];
                    $payroll_data_json = json_encode($payroll_data, JSON_UNESCAPED_UNICODE);

                    // === LOGIC កែប្រែ​នៅ​ទីនេះ ===
                    // 1. ពិនិត្យមើល​ថា​តើ​មាន​ទិន្នន័យ​ដែល​បាន​ដំណើរការ​ក្នុង​ថ្ងៃ​នេះ​សម្រាប់​បុគ្គលិក​នេះ និង​សម្រាប់​ pay_period_to នេះ​ហើយ​ឬ​នៅ
                    $check_stmt = $conn->prepare("SELECT id FROM payrolls WHERE employee_id = ? AND pay_period_to = ? AND DATE(processed_at) = ?");
                    $check_stmt->bind_param("iss", $emp_id, $date_to, $today_date);
                    $check_stmt->execute();
                    $existing_payroll = $check_stmt->get_result()->fetch_assoc();
                    $check_stmt->close();

                    if ($existing_payroll) {
                        // 2. បើ​មាន, ធ្វើបច្ចុប្បន្នភាព (UPDATE) ទិន្នន័យ​ចាស់
                        $stmt = $conn->prepare("UPDATE payrolls SET pay_period_from = ?, basic_salary = ?, gross_salary = ?, total_deductions = ?, net_salary = ?, payroll_data_json = ?, processed_at = NOW(), is_new = 1 WHERE id = ?");
                        $stmt->bind_param("sddddsi", $date_from, $basic_salary, $gross_salary, $total_deduction, $net_salary, $payroll_data_json, $existing_payroll['id']);
                    } else {
                        // 3. បើ​មិន​មាន, បញ្ចូល (INSERT) ទិន្នន័យ​ថ្មី
                        $stmt = $conn->prepare("INSERT INTO payrolls (employee_id, pay_period_from, pay_period_to, basic_salary, gross_salary, total_deductions, net_salary, payroll_data_json, is_new, processed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
                        $stmt->bind_param("issdddds", $emp_id, $date_from, $date_to, $basic_salary, $gross_salary, $total_deduction, $net_salary, $payroll_data_json);
                    }
                    
                    $stmt->execute();
                    $stmt->close();
                    // === បញ្ចប់ LOGIC កែប្រែ ===
                }
                $conn->commit();
                send_response('success', 'ការគណនាប្រាក់បៀវត្សបានជោគជ័យ!');
            } catch (Exception $e) { 
                $conn->rollback(); 
                send_response('error', "ការដំណើរការមានកំហុស: " . $e->getMessage()); 
            }
            break;

        case 'delete_all_history':
            $sql = "DELETE p FROM payrolls p JOIN employees e ON p.employee_id = e.id WHERE e.employee_type = ? AND YEAR(p.pay_period_to) = ?";
            $params = [$_POST['type'], $_POST['filter_year']];
            $types = "si";
            if (!empty($_POST['filter_month'])) {
                $sql .= " AND MONTH(p.pay_period_to) = ?";
                $params[] = $_POST['filter_month'];
                $types .= "i";
            }
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) { send_response('success', 'ប្រវត្តិដែលបានតម្រៀបត្រូវបានលុប!'); }
            else { send_response('error', 'ការលុបមានបញ្ហា: ' . $stmt->error); }
            break;

        case 'get_updates':
            $view = $_GET['view'] ?? '';
            $response_data = ['has_new' => false, 'data' => []];

            if ($view === 'employees') {
                $last_id = $_GET['last_id'] ?? 0;
                $result = $conn->query("SELECT * FROM employees WHERE id > " . intval($last_id) . " ORDER BY id DESC");
                if ($result->num_rows > 0) {
                    $response_data['has_new'] = true;
                    while ($row = $result->fetch_assoc()) {
                        $response_data['data'][] = $row;
                    }
                }
            } elseif ($view === 'payroll_history') {
                $client_count = $_GET['current_count'] ?? 0;
                
                $employee_view_type = $_GET['type'] ?? 'staff';
                $filter_month = $_GET['filter_month'] ?? '';
                $filter_year = $_GET['filter_year'] ?? date('Y');
                $base_sql = "FROM payrolls p JOIN employees e ON p.employee_id = e.id WHERE e.employee_type = ?";
                $params = [$employee_view_type];
                $param_types = 's';
                $where_clauses[] = "YEAR(p.pay_period_to) = ?";
                $params[] = $filter_year;
                $param_types .= 'i';
                if (!empty($filter_month)) {
                    $where_clauses[] = "MONTH(p.pay_period_to) = ?";
                    $params[] = $filter_month;
                    $param_types .= 'i';
                }
                $sql_where = ' AND ' . implode(' AND ', $where_clauses);
                
                $total_records_stmt = $conn->prepare("SELECT COUNT(p.id) as total " . $base_sql . $sql_where);
                $total_records_stmt->bind_param($param_types, ...$params);
                $total_records_stmt->execute();
                $server_count = $total_records_stmt->get_result()->fetch_assoc()['total'];

                if ($server_count != $client_count) {
                    $response_data['has_new'] = true;
                    $records_per_page = 15;
                    $sql_final = "SELECT p.*, e.first_name, e.last_name " . $base_sql . $sql_where . " ORDER BY p.pay_period_to DESC, e.first_name ASC LIMIT ?";
                    $param_types .= 'i';
                    $params[] = $records_per_page;
                    
                    $history_stmt = $conn->prepare($sql_final);
                    $history_stmt->bind_param($param_types, ...$params);
                    $history_stmt->execute();
                    $history_result = $history_stmt->get_result();
                      while ($row = $history_result->fetch_assoc()) {
                          $response_data['data'][] = $row;
                    }
                }
            }
            echo json_encode($response_data);
            exit();

        default:
            send_response('error', 'Action មិនត្រឹមត្រូវ!');
            break;
    }
}


// =================================================================
// SECTION 2: NORMAL PAGE LOAD LOGIC
// =================================================================
$count_res = $conn->query("SELECT COUNT(id) as new_count FROM payrolls WHERE is_new = 1");
$new_payroll_count = $count_res->fetch_assoc()['new_count'];
$page = $_GET['page'] ?? 'home';
$id = $_GET['id'] ?? null;
if ($page === 'payroll_history' && $new_payroll_count > 0) {
    $conn->query("UPDATE payrolls SET is_new = 0 WHERE is_new = 1");
    $new_payroll_count = 0;
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ប្រព័ន្ធគ្រប់គ្រងប្រាក់បៀវត្សរ៍</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/easyqrcodejs@4.4.13/dist/easy.qrcode.min.js"></script>
    <style>
        :root {
            --primary-color: #007BFF; --primary-hover: #0056b3;
            --secondary-color: #28a745; --secondary-hover: #1e7e34;
            --dark-color: #2c3e50; --text-color: #495057; --sidebar-text: #ecf0f1;
            --light-color: #f8f9fa; --white-color: #ffffff;
            --danger-color: #dc3545; --danger-hover: #a71d2a;
            --warning-color: #ffc107; --warning-hover: #d39e00;
            --border-color: #dee2e6;
            --font-family: 'Kantumruy Pro', sans-serif;
            --box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            --border-radius: 8px; --transition-speed: 0.3s;
            --sidebar-width: 260px;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: var(--font-family); background-color: var(--light-color); color: var(--text-color); line-height: 1.6; margin: 0; font-size: 16px; -webkit-font-smoothing: antialiased; }
        
        @keyframes fadeInHighlight {
            from { background-color: #e6ffed; }
            to { background-color: transparent; }
        }
        .new-row-highlight {
            animation: fadeInHighlight 2s ease-in-out;
        }
        .page-wrapper { display: flex; }
        #sidebar { width: var(--sidebar-width); height: 100vh; background-color: var(--dark-color); color: var(--sidebar-text); display: flex; flex-direction: column; position: sticky; top: 0; flex-shrink: 0; transition: width var(--transition-speed) ease; overflow-x: hidden; }
        .sidebar-header { padding: 1.25rem 1.5rem; text-align: center; border-bottom: 1px solid #3e4d5f; }
        .sidebar-header h2 { margin: 0; font-size: 1.5rem; color: var(--white-color); white-space: nowrap; }
        .nav-links { list-style: none; padding: 1rem 0; margin: 0; flex-grow: 1; }
        .nav-links a { display: flex; align-items: center; gap: 1rem; color: var(--sidebar-text); text-decoration: none; padding: 0.9rem 1.5rem; transition: background-color var(--transition-speed), color var(--transition-speed); white-space: nowrap; position: relative; }
        .nav-links a i, .user-info a i { width: 24px; text-align: center; font-size: 1.1rem; flex-shrink: 0; transition: transform 0.2s ease; }
        .nav-links a:hover i { transform: scale(1.1); }
        .nav-links a:hover { background-color: #3e4d5f; color: var(--white-color); }
        .nav-links a.active { background-color: var(--primary-color); color: var(--white-color); font-weight: 500; }
        .user-info { padding: 1.5rem; border-top: 1px solid #3e4d5f; background-color: rgba(0,0,0,0.1); }
        .user-info span { display: block; white-space: nowrap; }
        .user-info b { color: var(--white-color); }
        .user-info a.logout-btn { display: flex; align-items: center; gap: 1rem; margin-top: 0.5rem; padding: 0.5rem 0; color: var(--warning-color); font-weight: 500; text-decoration: none; white-space: nowrap; }
        .user-info a.logout-btn:hover { color: var(--white-color); }
        #main-content { flex-grow: 1; transition: margin-left var(--transition-speed) ease; }
        .top-header { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1.5rem; background-color: var(--white-color); box-shadow: 0 2px 5px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 999; }
        #menu-toggle { background: none; border: none; cursor: pointer; font-size: 1.5rem; color: var(--dark-color); }
        .page-title { font-size: 1.2rem; font-weight: 500; color: var(--dark-color); margin: 0; }
        .page-wrapper.sidebar-collapsed #sidebar { width: 0; }
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 1.5rem; }
        .card { background: var(--white-color); padding: 2rem; margin-top: 2rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow); border: 1px solid var(--border-color); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin: -2rem -2rem 1.5rem -2rem; padding: 1rem 2rem; border-bottom: 1px solid var(--border-color); background-color: var(--light-color); }
        .card-header h2, .card h2, .card h3 { margin-top: 0; color: var(--dark-color); }
        .card h3 { margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        table th, table td { padding: 14px 16px; text-align: left; vertical-align: middle; border-bottom: 1px solid var(--border-color); }
        table thead th { background-color: var(--light-color); font-weight: 600; color: var(--dark-color); border-bottom-width: 2px; }
        table tbody tr:nth-of-type(odd) { background-color: #fdfdfd; }
        table tbody tr:hover { background-color: #f1f8ff; }
        form label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--dark-color); }
        form input[type="text"], form input[type="number"], form input[type="date"], form select, form textarea { width: 100%; padding: 12px 15px; margin-bottom: 1.25rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); box-sizing: border-box; transition: all var(--transition-speed) ease; font-family: var(--font-family); font-size: 1rem; }
        form textarea { min-height: 120px; resize: vertical; }
        form input:focus, form select:focus, form textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2); }
        .btn, input[type="submit"], button.btn { background-color: var(--primary-color); color: var(--white-color); border: none; padding: 12px 22px; cursor: pointer; text-decoration: none; border-radius: var(--border-radius); font-size: 1rem; display: inline-block; transition: all var(--transition-speed) ease; font-family: var(--font-family); font-weight: 500; }
        .btn:hover, input[type="submit"]:hover, button.btn:hover { background-color: var(--primary-hover); transform: translateY(-2px); }
        .btn-edit { background-color: var(--warning-color); } .btn-edit:hover { background-color: var(--warning-hover); }
        .btn-delete { background-color: var(--danger-color); } .btn-delete:hover { background-color: var(--danger-hover); }
        .btn-save-draft { background-color: var(--warning-color); } .btn-save-draft:hover { background-color: var(--warning-hover); }
        input[type="submit"], .btn-process { background-color: var(--secondary-color); } input[type="submit"]:hover, .btn-process:hover { background-color: var(--secondary-hover); }
        .grid-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; }
        .payroll-table-input { width: 110px; padding: 8px; margin-bottom: 0; border: 1px solid var(--border-color); border-radius: 5px; text-align: center; }
        .tab-nav { display: flex; justify-content: center; margin-bottom: 2rem; gap: 0.5rem; background-color: var(--light-color); padding: 0.5rem; border-radius: var(--border-radius); }
        .tab-nav a { padding: 10px 25px; text-decoration: none; font-weight: 500; border-radius: 6px; color: var(--text-color); transition: all var(--transition-speed) ease; border: none; }
        .tab-nav a:hover { background-color: #dde2e6; }
        .tab-nav a.active { background-color: var(--primary-color); color: var(--white-color); box-shadow: 0 2px 8px rgba(0, 123, 255, 0.3); }
        .filter-form { display: flex; gap: 1rem; align-items: flex-end; margin-bottom: 1.5rem; flex-wrap: wrap; background-color: var(--light-color); padding: 1.5rem; border-radius: var(--border-radius); }
        .filter-form .form-group { display: flex; flex-direction: column; }
        .filter-form select, .filter-form input[type="date"] { margin-bottom: 0; }
        .pagination { display: flex; justify-content: center; list-style: none; padding: 0; margin-top: 2rem; }
        .pagination a { color: var(--primary-color); padding: 8px 16px; text-decoration: none; transition: background-color .3s; border: 1px solid var(--border-color); margin: 0 4px; border-radius: var(--border-radius); }
        .pagination a.active { background-color: var(--primary-color); color: white; border-color: var(--primary-color); }
        .pagination a:hover:not(.active) { background-color: #e9ecef; }
        .notification-badge { position: absolute; top: 10px; right: 15px; background-color: var(--danger-color); color: var(--white-color); border-radius: 50%; padding: 2px 7px; font-size: 11px; font-weight: bold; line-height: 1; }
        #notification-area { position: fixed; top: 80px; right: 20px; z-index: 9999; width: 320px; }
        .notification { padding: 15px; margin-bottom: 10px; border-radius: var(--border-radius); color: var(--white-color); box-shadow: var(--box-shadow); opacity: 0; transform: translateX(100%); transition: all 0.5s cubic-bezier(0.25, 0.8, 0.25, 1); }
        .notification.show { opacity: 1; transform: translateX(0); }
        .notification.success { background-color: var(--secondary-color); }
        .notification.error { background-color: var(--danger-color); }
        #qr-preview-container { border: 2px dashed var(--border-color); border-radius: var(--border-radius); padding: 10px; margin-top: -10px; margin-bottom: 20px; min-height: 180px; display: flex; justify-content: center; align-items: center; transition: all 0.3s; }
        #qr-preview-container:has(canvas) { border-style: solid; }
        #qr-preview-container img, #qr-preview-container canvas { max-width: 160px; max-height: 160px; }
        .ot-cell { vertical-align: top !important; padding: 10px !important; background-color: #f8faff; min-width: 280px; }
        .ot-input-wrapper { display: flex; align-items: flex-end; gap: 8px; margin-bottom: 8px; }
        .ot-input-group { flex: 1; display: flex; flex-direction: column; }
        .ot-label { font-size: 0.8em; font-weight: 500; color: #555; margin-bottom: 4px; display: block; text-align: left; }
        .ot-input-wrapper .payroll-table-input { width: 100%; margin-bottom: 0; padding: 10px; font-size: 1em; box-sizing: border-box; }
        .ot-multiplier { font-size: 1.2em; color: #777; font-weight: 600; padding-bottom: 10px; }
        .ot-total-display { margin-top: 0; padding: 10px; background-color: #e6f2ff; border-radius: var(--border-radius); text-align: center; border: 1px solid #b3d7ff; font-weight: 600; color: var(--primary-color); font-size: 1.1em; transition: all 0.2s ease; }
        .deduction-cell { vertical-align: top !important; padding: 10px !important; min-width: 150px; }
        .deduction-label { font-size: 0.8em; font-weight: 500; color: #555; margin-bottom: 4px; display: block; text-align: left; }
        .deduction-cell .payroll-table-input { width: 100%; margin-bottom: 0; padding: 10px; font-size: 1em; box-sizing: border-box; }
        
        /* === DASHBOARD CARDS STYLES === */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        .dashboard-card {
            background-color: var(--white-color);
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 5px solid var(--primary-color);
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        .dashboard-card .card-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            background-color: #e6f2ff;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-shrink: 0;
        }
        .dashboard-card .card-content h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
            color: var(--text-color);
            font-weight: 500;
        }
        .dashboard-card .card-content p {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        .dashboard-card:nth-child(2) { border-color: var(--secondary-color); }
        .dashboard-card:nth-child(2) .card-icon { color: var(--secondary-color); background-color: #e6ffed; }
        .dashboard-card:nth-child(3) { border-color: var(--warning-color); }
        .dashboard-card:nth-child(3) .card-icon { color: var(--warning-color); background-color: #fff8e1; }
        .dashboard-card:nth-child(4) { border-color: #6f42c1; }
        .dashboard-card:nth-child(4) .card-icon { color: #6f42c1; background-color: #f2e6ff; }
        
        /* === PAYSLIP STYLES (UPDATED FOR A5 PRINTING) === */
        .payslip-container { background: #ffffff; border: 1px solid #e0e0e0; border-top: 6px solid #2c3e50; box-shadow: 0 10px 30px rgba(0,0,0,0.07); color: #333; max-width: 210mm; font-size: 14px; margin: 30px auto; padding: 0; }
        .payslip-header { display: flex; justify-content: center; align-items: center; padding: 1.5rem 2rem;  }
        .payslip-logo { max-height: 80px; max-width: 350px; }
        .payslip-title { text-align: center; margin: 2rem 0; }
        .payslip-title h1 { font-size: 2.2em; font-weight: 600; color: #2c3e50; margin: 0; letter-spacing: 1px; }
        .payslip-meta-info { display: flex; justify-content: space-between; align-items: flex-start; gap: 2rem; padding: 0 2rem 1.5rem 2rem; }
        .qr-code-box-top { text-align: center; flex-shrink: 0; min-width: 152px; min-height: 152px; }
        .qr-code-box-top canvas { width: 140px !important; height: 140px !important; border-radius: var(--border-radius); border: 1px solid #e0e0e0; padding: 5px; }
        .employee-details-top { line-height: 2; flex-grow: 1; }
        .employee-details-top p { margin: 0; font-size: 1.1em; }
        .employee-details-top strong { color: #2c3e50; width: 160px; display: inline-block; font-weight: 600; }
        .payslip-body { padding: 1.5rem 2rem 2rem 2rem; }
        .payslip-table { width: 100%; border-collapse: collapse; }
        .payslip-table th, .payslip-table td { padding: 14px; border-bottom: 1px solid #f0f0f0; }
        .payslip-table td:last-child { text-align: right; font-weight: 500; }
        .payslip-table .payslip-section-header td { background-color: #f8f9fa; font-weight: 700; color: #2c3e50; font-size: 1.1em; border-top: 2px solid #e0e0e0; }
        .payslip-table .payslip-gross-salary td { background-color: #e6f2ff; font-weight: 700; color: var(--primary-color); }
        .payslip-table .payslip-total-deduction td { background-color: #ffeeee; font-weight: 700; color: var(--danger-color); }
        .payslip-table .payslip-net-salary td { background-color: #2c3e50; color: var(--white-color); font-size: 1.4em; font-weight: bold; border-bottom: none; }
        .signature-area { margin-top: 5rem; padding-top: 1.5rem; display: flex; justify-content: space-around; border-top: 1px solid #e0e0e0; }
        .signature-box { text-align: center; width: 250px; }
        .signature-box .line { border-bottom: 1px solid #555; margin-top: 4rem; }
        .signature-box p { margin: 8px 0 0; color: #555; }
        .print-controls { text-align: center; margin: 20px 0; }
        
        @media print {
            @page {
                size: auto; /* Let browser handle A4/A5 */
                margin: 10mm;
            }
            body, .container, .page-wrapper { background: white; margin: 0; padding: 0; display: block; }
            #sidebar, .top-header, .print-controls, .dashboard-cards { display: none !important; }
            #main-content { margin-left: 0 !important; }
            
            .payslip-container { 
                page-break-after: always;
                box-shadow: none; border: 1px solid #ccc;
                font-size: 12pt;
                width: 100%; height: 100%;
                display: flex; flex-direction: column;
            }
            .payslip-container:last-child { page-break-after: auto; }
            
            .payslip-body { flex-grow: 1; display: flex; flex-direction: column; padding: 1rem 1.5rem; }
            .payslip-header { padding: 1rem 1.5rem; }
            .payslip-title { margin: 1rem 0; }
            .payslip-meta-info { padding: 0 1.5rem 1rem 1.5rem; margin-top: 2rem; }
            .payslip-table { flex-grow: 1; }
            .payslip-table td { padding: 8px 10px; }
            
            .signature-area { margin-top: auto; padding-top: 1rem; }
            .signature-box .line { margin-top: 2.5rem; }
            
            .qr-code-box-top { display: block !important; } /* Make sure it shows on print */
            .payslip-meta-info { justify-content: space-between !important; }
            .employee-details-top p { text-align: left; }
            
            body * { visibility: hidden; }
            .payslip-container, .payslip-container * { visibility: visible; }
            
            .payslip-net-salary td, .payslip-section-header td, .payslip-gross-salary td, .payslip-total-deduction td { 
                -webkit-print-color-adjust: exact; 
                print-color-adjust: exact; 
            }
        }

        /* === START: CUSTOM CSS FOR FULL-WIDTH TABLES === */
        /* កំណត់ឱ្យ container ពេញ screen សម្រាប់តែទំព័រដែលបានកំណត់ */
        body[data-page="employees"] .container,
        body[data-page="payroll_bulk"] .container,
        body[data-page="payroll_history"] .container {
            max-width: none; /* ដកដែនកំណត់ max-width ចេញ */
            padding-left: 2rem;  /* កំណត់គម្លាតសងខាង */
            padding-right: 2rem;
        }

        /* កាត់បន្ថយគម្លាតក្នុង card ដើម្បីឱ្យតារាងធំជាងមុន */
        body[data-page="employees"] .card,
        body[data-page="payroll_bulk"] .card,
        body[data-page="payroll_history"] .card {
            padding: 1.5rem; 
        }
        /* === END: CUSTOM CSS FOR FULL-WIDTH TABLES === */
    </style>
</head>
<body data-page="<?php echo htmlspecialchars($page); ?>">

    <div id="notification-area"></div>

    <?php if ($page !== 'print_bulk'): ?>
    <div class="page-wrapper">
        <aside id="sidebar">
            <div class="sidebar-header">
                <h2>ប្រព័ន្ធបៀវត្សរ៍</h2>
            </div>
            <ul class="nav-links">
                <li><a href="payroll-manager.php?page=home" class="<?php echo ($page === 'home' ? 'active' : ''); ?>"><i class="fa-solid fa-house"></i> <span>ទំព័រដើម</span></a></li>
                
                <?php if ($user_role === 'manager' || $user_role === 'account'): ?>
                    <li><a href="?page=employees" class="<?php echo (in_array($page, ['employees', 'add_employee', 'edit_employee']) ? 'active' : ''); ?>"><i class="fa-solid fa-users"></i> <span>គ្រប់គ្រងបុគ្គលិក</span></a></li>
                <?php endif; ?>

                <?php if ($user_role === 'manager' || $user_role === 'administration'): ?>
                    <li><a href="?page=payroll_bulk" class="<?php echo ($page === 'payroll_bulk' ? 'active' : ''); ?>"><i class="fa-solid fa-calculator"></i> <span>គណនាប្រាក់បៀវត្ស</span></a></li>
                <?php endif; ?>

                <?php if ($user_role === 'manager' || $user_role === 'account'): ?>
                    <li>
                        <a href="?page=payroll_history" class="<?php echo (in_array($page, ['payroll_history', 'view_payslip']) ? 'active' : ''); ?>">
                            <i class="fa-solid fa-history"></i> <span>ប្រវត្តិបើកប្រាក់បៀវត្ស</span>
                            <?php if ($new_payroll_count > 0): ?>
                                <span class="notification-badge" id="payroll-badge"><?php echo $new_payroll_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="user-info">
                <span>សូមស្វាគមន៍, <b><?php echo htmlspecialchars($_SESSION["username"]); ?></b></span>
                <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> <span>ចាកចេញ</span></a>
            </div>
        </aside>

        <main id="main-content">
            <header class="top-header">
                <button id="menu-toggle"><i class="fa-solid fa-bars"></i></button>
                <h1 class="page-title">
                    <?php 
                        switch($page){
                            case 'home': echo "ផ្ទាំងគ្រប់គ្រង"; break;
                            case 'employees': echo "បញ្ជីបុគ្គលិក"; break;
                            case 'add_employee': echo "បន្ថែមបុគ្គលិកថ្មី"; break;
                            case 'edit_employee': echo "កែសម្រួលព័ត៌មានបុគ្គលិក"; break;
                            case 'payroll_bulk': echo "គណនាប្រាក់បៀវត្ស"; break;
                            case 'payroll_history': echo "ប្រវត្តិបើកប្រាក់បៀវត្ស"; break;
                            case 'view_payslip': echo "ព័ត៌មានលម្អិតប័ណ្ណបើកប្រាក់ខែ"; break;
                            default: echo "សូមស្វាគមន៍";
                        }
                    ?>
                </h1>
            </header>
    <?php else: ?>
        <div class="page-wrapper-print">
            <main id="main-content-print">
    <?php endif; ?>
    
            <div class="container">
                <?php
                    $access_denied = false;
                    if (($page === 'employees' || $page === 'add_employee' || $page === 'edit_employee') && !($user_role === 'manager' || $user_role === 'account')) { $access_denied = true; }
                    if ($page === 'payroll_bulk' && !($user_role === 'manager' || $user_role === 'administration')) { $access_denied = true; }
                    if (($page === 'payroll_history' || $page === 'view_payslip' || $page === 'print_bulk') && !($user_role === 'manager' || $user_role === 'account')) { $access_denied = true; }

                    if ($access_denied) {
                        echo '<div class="card"><h2>ការចូលប្រើប្រាស់ត្រូវបានបដិសេធ</h2><p>អ្នកមិនមានសិទ្ធិក្នុងការមើលទំព័រនេះទេ។</p></div>';
                    } else {
                ?>
                
                <?php if ($page === 'home'):
                    // Fetch data for dashboard cards
                    $employee_counts_res = $conn->query("SELECT COUNT(id) as total, SUM(CASE WHEN employee_type = 'staff' THEN 1 ELSE 0 END) as staff_count, SUM(CASE WHEN employee_type = 'worker' THEN 1 ELSE 0 END) as worker_count FROM employees");
                    $employee_counts = $employee_counts_res->fetch_assoc();
                    $total_employees = $employee_counts['total'] ?? 0;
                    $total_staff = $employee_counts['staff_count'] ?? 0;
                    $total_workers = $employee_counts['worker_count'] ?? 0;

                    $current_month = date('m');
                    $current_year = date('Y');
                    $salary_res = $conn->query("SELECT SUM(net_salary) as total_paid FROM payrolls WHERE MONTH(pay_period_to) = '$current_month' AND YEAR(pay_period_to) = '$current_year'");
                    $total_paid_this_month = $salary_res->fetch_assoc()['total_paid'] ?? 0;
                ?>
                    <div class="dashboard-cards">
                        <div class="dashboard-card">
                            <div class="card-icon"><i class="fa-solid fa-users"></i></div>
                            <div class="card-content">
                                <h4>បុគ្គលិកសរុប</h4>
                                <p><?php echo $total_employees; ?></p>
                            </div>
                        </div>
                        <div class="dashboard-card">
                            <div class="card-icon"><i class="fa-solid fa-user-tie"></i></div>
                            <div class="card-content">
                                <h4>បុគ្គលិកជំនាញ</h4>
                                <p><?php echo $total_staff; ?></p>
                            </div>
                        </div>
                        <div class="dashboard-card">
                            <div class="card-icon"><i class="fa-solid fa-helmet-safety"></i></div>
                            <div class="card-content">
                                <h4>កម្មករ</h4>
                                <p><?php echo $total_workers; ?></p>
                            </div>
                        </div>
                        <div class="dashboard-card">
                            <div class="card-icon"><i class="fa-solid fa-money-bill-wave"></i></div>
                            <div class="card-content">
                                <h4>ប្រាក់ខែបានបើក (ខែនេះ)</h4>
                                <p>$<?php echo number_format($total_paid_this_month, 2); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <h2>សូមស្វាគមន៍មកកាន់ប្រព័ន្ធគ្រប់គ្រងប្រាក់បៀវត្សរ៍!</h2>
                        <p>សូមជ្រើសរើសម៉ឺនុយនៅខាងឆ្វេងដើម្បីចាប់ផ្តើមប្រើប្រាស់។</p>
                    </div>
                <?php endif; ?>

                
                <?php if ($page === 'employees'): ?>
                     <div class="card">
                          <div class="card-header"><h2>បញ្ជីបុគ្គលិក</h2><a href="?page=add_employee" class="btn">បន្ថែមបុគ្គលិកថ្មី</a></div>
                          <table id="employees-table">
                                <thead><tr><th>អត្តលេខ</th><th>ឈ្មោះបុគ្គលិក</th><th>តួនាទី</th><th>ប្រភេទ</th><th>ប្រាក់ខែគោល</th><th>សកម្មភាព</th></tr></thead>
                                <tbody>
                                <?php $result = $conn->query("SELECT * FROM employees ORDER BY id DESC"); while ($row = $result->fetch_assoc()): ?>
                                <tr id="employee-row-<?php echo $row['id']; ?>" data-id="<?php echo $row['id']; ?>">
                                    <td><?php echo htmlspecialchars($row['employee_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['position']); ?></td>
                                    <td><?php echo ($row['employee_type'] == 'staff') ? 'បុគ្គលិកជំនាញ' : 'កម្មករ'; ?></td>
                                    <td>$<?php echo number_format($row['basic_salary'], 2); ?></td>
                                    <td style="display:flex; gap: 0.5rem;">
                                        <a href="?page=edit_employee&id=<?php echo $row['id']; ?>" class="btn btn-edit">កែសម្រួល</a>
                                        <a href="#" class="btn btn-delete btn-delete-employee" data-id="<?php echo $row['id']; ?>">លុប</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                </tbody>
                          </table>
                     </div>
                <?php endif; ?>
                
                <?php if ($page === 'add_employee'): ?>
                     <div class="card">
                          <h2>បន្ថែមបុគ្គលិកថ្មី</h2>
                          <form id="add-employee-form" method="post">
                              <input type="hidden" name="action" value="add_employee">
                              <label>អត្តលេខបុគ្គលិក:</label><input type="text" name="employee_id" required>
                              <label>នាមខ្លួន (First Name):</label><input type="text" name="first_name" required>
                              <label>នាមត្រកូល (Last Name):</label><input type="text" name="last_name" required>
                              <label>តួនាទី:</label><input type="text" name="position" required>
                              <label>ប្រភេទ:</label> <select name="employee_type" required> <option value="staff">បុគ្គលិកជំនាញ</option> <option value="worker" selected>កម្មករ</option> </select>
                              <label>ប្រាក់ខែគោល ($):</label><input type="number" step="0.01" name="basic_salary" required>
                              <label for="qr_code_string">QR Code String (សម្រាប់ទូទាត់):</label>
                              <textarea name="qr_code_string" id="qr_code_string" placeholder="បញ្ចូល KHQR String របស់បុគ្គលិកនៅទីនេះ..."></textarea>
                              <div id="qr-preview-container"></div>
                              <small style="display:block; margin-top:-10px; margin-bottom:20px; color:#777;">(Optional) បញ្ចូល KHQR String ផ្ទាល់ខ្លួនរបស់បុគ្គលិក។ បើមិនមាន, នឹងមិនមាន QR Code បង្ហាញនៅលើប័ណ្ណបើកប្រាក់ខែឡើយ។</small>
                              <input type="submit" value="រក្សាទុក">
                          </form>
                     </div>
                <?php endif; ?>

                <?php if ($page === 'edit_employee' && $id):
                    $stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?"); $stmt->bind_param("i", $id); $stmt->execute();
                    $employee = $stmt->get_result()->fetch_assoc();
                ?>
                    <h2>កែសម្រួលព័ត៌មាន: <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h2>
                    <div class="grid-container">
                        <div class="card">
                            <h3>ព័ត៌មានទូទៅ</h3>
                            <form id="update-employee-form" method="post">
                                <input type="hidden" name="action" value="update_employee"> <input type="hidden" name="id" value="<?php echo $employee['id']; ?>">
                                <label>អត្តលេខបុគ្គលិក:</label><input type="text" name="employee_id" value="<?php echo htmlspecialchars($employee['employee_id']); ?>" required>
                                <label>នាមខ្លួន:</label><input type="text" name="first_name" value="<?php echo htmlspecialchars($employee['first_name']); ?>" required>
                                <label>នាមត្រកូល:</label><input type="text" name="last_name" value="<?php echo htmlspecialchars($employee['last_name']); ?>" required>
                                <label>តួនាទី:</label><input type="text" name="position" value="<?php echo htmlspecialchars($employee['position']); ?>" required>
                                <label>ប្រភេទ:</label> <select name="employee_type" required> <option value="staff" <?php echo ($employee['employee_type'] == 'staff') ? 'selected' : ''; ?>>បុគ្គលិកជំនាញ</option> <option value="worker" <?php echo ($employee['employee_type'] == 'worker') ? 'selected' : ''; ?>>កម្មករ</option> </select>
                                <label>ប្រាក់ខែគោល ($):</label><input type="number" step="0.01" name="basic_salary" value="<?php echo htmlspecialchars($employee['basic_salary']); ?>" required>
                                <label for="qr_code_string">QR Code String (សម្រាប់ទូទាត់):</label>
                                <textarea name="qr_code_string" id="qr_code_string" placeholder="បញ្ចូល KHQR String របស់បុគ្គលិកនៅទីនេះ..."><?php echo htmlspecialchars($employee['qr_code_string']); ?></textarea>
                                <label>QR Code Preview:</label>
                                <div id="qr-preview-container"></div>
                                <input type="submit" value="ធ្វើបច្ចុប្បន្នភាព">
                            </form>
                        </div>
                        <div class="card">
                            <h3>ប្រាក់ឧបត្ថម្ភ (ប្រចាំ)</h3>
                            <form id="add-allowance-form" method="post">
                                <input type="hidden" name="action" value="add_allowance"> <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                <input type="text" name="type" placeholder="ប្រភេទ (ឧ: ប្រាក់សាំង)" required><input type="number" step="0.01" name="amount" placeholder="ចំនួនទឹកប្រាក់ ($)" required>
                                <input type="submit" value="បន្ថែម">
                            </form>
                            <table><?php $items = $conn->query("SELECT * FROM allowances WHERE employee_id=".$employee['id']); while ($row = $items->fetch_assoc()): ?>
                                <tr><td><?php echo htmlspecialchars($row['allowance_type']); ?></td><td>$<?php echo number_format($row['amount'], 2); ?></td><td><a href="#" class="btn btn-delete btn-delete-item" data-action="delete_allowance" data-item-id="<?php echo $row['id']; ?>">លុប</a></td></tr>
                            <?php endwhile; ?></table>
                            <h3 style="margin-top:2rem;">ការកាត់ប្រាក់ (ប្រចាំ)</h3>
                             <form id="add-deduction-form" method="post">
                                 <input type="hidden" name="action" value="add_deduction"> <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                 <input type="text" name="type" placeholder="ប្រភេទ (ឧ: ពន្ធ)" required><input type="number" step="0.01" name="amount" placeholder="ចំនួនទឹកប្រាក់ ($)" required>
                                 <input type="submit" value="បន្ថែម">
                            </form>
                            <table><?php $items = $conn->query("SELECT * FROM deductions WHERE employee_id=".$employee['id']); while ($row = $items->fetch_assoc()): ?>
                                <tr><td><?php echo htmlspecialchars($row['deduction_type']); ?></td><td>$<?php echo number_format($row['amount'], 2); ?></td><td><a href="#" class="btn btn-delete btn-delete-item" data-action="delete_deduction" data-item-id="<?php echo $row['id']; ?>">លុប</a></td></tr>
                            <?php endwhile; ?></table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($page === 'payroll_bulk'):
                                $employee_view_type = $_GET['type'] ?? 'staff';
                                $default_from = date('Y-m-01');
                                $default_to = date('Y-m-t');
                                $date_from = $_GET['date_from'] ?? $default_from;
                                $date_to = $_GET['date_to'] ?? $default_to;
                                $employees_result = $conn->query("SELECT * FROM employees WHERE employee_type = '$employee_view_type' ORDER BY first_name ASC");
                                $employees_to_display = []; while ($emp = $employees_result->fetch_assoc()) { $employees_to_display[] = $emp; }
                                $saved_data = [];
                                if (!empty($employees_to_display)) {
                                    $saved_stmt = $conn->prepare("SELECT * FROM monthly_payroll_data WHERE payroll_month = ?");
                                    $saved_stmt->bind_param("s", $date_to);
                                    $saved_stmt->execute();
                                    $saved_result = $saved_stmt->get_result();
                                    while ($row = $saved_result->fetch_assoc()) { $saved_data[$row['employee_id']] = $row; }
                                }
                ?>
                    <div class="card">
                        <div class="card-header"><h2>គណនាប្រាក់បៀវត្ស</h2></div>
                        <div class="tab-nav">
                            <a href="?page=payroll_bulk&type=staff&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="<?php echo ($employee_view_type == 'staff') ? 'active' : ''; ?>">បុគ្គលិកជំនាញ</a>
                            <a href="?page=payroll_bulk&type=worker&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="<?php echo ($employee_view_type == 'worker') ? 'active' : ''; ?>">កម្មករ</a>
                        </div>
                        <form action="" method="get" class="filter-form">
                            <input type="hidden" name="page" value="payroll_bulk"> <input type="hidden" name="type" value="<?php echo htmlspecialchars($employee_view_type); ?>">
                            <div class="form-group"> <label for="date_from">ចាប់ពីថ្ងៃ:</label> <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($date_from); ?>" style="width: auto;"> </div>
                            <div class="form-group"> <label for="date_to">ដល់ថ្ងៃ:</label> <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($date_to); ?>" style="width: auto;"> </div>
                            <div><input type="submit" value="បង្ហាញទិន្នន័យ"></div>
                        </form>
                        
                        <form id="payroll-bulk-form" method="post">
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($employee_view_type); ?>">
                            <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            <h3><?php echo ($employee_view_type == 'staff') ? 'បុគ្គលិកជំនាញ' : 'កម្មករ'; ?> - សម្រាប់រយៈពេល <?php echo date("d/m/Y", strtotime($date_from)) . ' - ' . date("d/m/Y", strtotime($date_to)); ?></h3>
                            <div style="overflow-x:auto;">
                                <table class="payroll-table">
                                    <thead>
                                        <tr>
                                            <th>ឈ្មោះបុគ្គលិក</th>
                                            <th>ការងារបន្ថែមម៉ោង (OT)</th>
                                            <th>កាត់ប្រាក់ (សុំច្បាប់)</th>
                                            <th>កាត់ប្រាក់ (កម្ចី)</th>
                                            <th>កាត់ប្រាក់ផ្សេងៗ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employees_to_display as $emp): $emp_saved_data = $saved_data[$emp['id']] ?? null; ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?><input type="hidden" name="employee_ids[]" value="<?php echo $emp['id']; ?>"></td>
                                            <td class="ot-cell">
                                                <div class="ot-input-wrapper">
                                                    <div class="ot-input-group">
                                                        <label class="ot-label">ចំនួនថ្ងៃ</label>
                                                        <input type="number" class="payroll-table-input ot-days-input" name="ot_days[<?php echo $emp['id']; ?>]" value="<?php echo $emp_saved_data['ot_days'] ?? '0.00'; ?>" step="0.1">
                                                    </div>
                                                    <span class="ot-multiplier">×</span>
                                                    <div class="ot-input-group">
                                                        <label class="ot-label">OT/ថ្ងៃ ($)</label>
                                                        <input type="number" class="payroll-table-input ot-rate-input" name="ot_rate_per_day[<?php echo $emp['id']; ?>]" value="<?php echo $emp_saved_data['ot_rate_per_day'] ?? '0.00'; ?>" step="0.01">
                                                    </div>
                                                </div>
                                                <div class="ot-total-display">
                                                    សរុប: $<span class="ot-total-amount">0.00</span>
                                                </div>
                                            </td>
                                            <td class="deduction-cell">
                                                <label class="deduction-label">ទឹកប្រាក់ ($)</label>
                                                <input type="number" class="payroll-table-input" name="unpaid_leave_deduction[<?php echo $emp['id']; ?>]" value="<?php echo $emp_saved_data['unpaid_leave_deduction'] ?? '0.00'; ?>" step="0.01">
                                            </td>
                                            <td class="deduction-cell">
                                                <label class="deduction-label">ទឹកប្រាក់ ($)</label>
                                                <input type="number" class="payroll-table-input" name="loan_deduction[<?php echo $emp['id']; ?>]" value="<?php echo $emp_saved_data['loan_deduction'] ?? '0.00'; ?>" step="0.01">
                                            </td>
                                            <td class="deduction-cell">
                                                <label class="deduction-label">ទឹកប្រាក់ ($)</label>
                                                <input type="number" class="payroll-table-input" name="other_deduction[<?php echo $emp['id']; ?>]" value="<?php echo $emp_saved_data['other_deduction'] ?? '0.00'; ?>" step="0.01">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($employees_to_display)) echo '<tr><td colspan="5" style="text-align:center;">មិនមានបុគ្គលិកសម្រាប់បង្ហាញ</td></tr>'; ?>
                                    </tbody>
                                </table>
                            </div> <br>
                            <?php if (!empty($employees_to_display)): ?>
                            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                <button type="submit" name="action" value="save_monthly_data" class="btn btn-save-draft">រក្សាទុកពង្រាង</button>
                                <button type="submit" name="action" value="process_bulk_payroll" class="btn btn-process">ដំណើរការ និងបញ្ចប់ការគណនា</button>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                <?php endif; ?>
                
                <?php if ($page === 'payroll_history'):
                    $records_per_page = 15;
                    $current_page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
                    if ($current_page < 1) { $current_page = 1; }
                    $offset = ($current_page - 1) * $records_per_page;
                    
                    $employee_view_type = $_GET['type'] ?? 'staff';
                    $filter_month = $_GET['filter_month'] ?? '';
                    $filter_year = $_GET['filter_year'] ?? date('Y');

                    $base_sql = "FROM payrolls p JOIN employees e ON p.employee_id = e.id WHERE e.employee_type = ?";
                    
                    $where_clauses = [];
                    $params = [$employee_view_type];
                    $param_types = 's';
                    $where_clauses[] = "YEAR(p.pay_period_to) = ?";
                    $params[] = $filter_year;
                    $param_types .= 'i';

                    if (!empty($filter_month)) {
                        $where_clauses[] = "MONTH(p.pay_period_to) = ?";
                        $params[] = $filter_month;
                        $param_types .= 'i';
                    }

                    $sql_where = ' AND ' . implode(' AND ', $where_clauses);
                    
                    $total_records_stmt = $conn->prepare("SELECT COUNT(p.id) as total " . $base_sql . $sql_where);
                    $total_records_stmt->bind_param($param_types, ...$params);
                    $total_records_stmt->execute();
                    $total_records = $total_records_stmt->get_result()->fetch_assoc()['total'];
                    $total_pages = ceil($total_records / $records_per_page);

                    $sql_final = "SELECT p.*, e.first_name, e.last_name " . $base_sql . $sql_where . " ORDER BY p.pay_period_to DESC, e.first_name ASC LIMIT ? OFFSET ?";
                    $param_types_page = $param_types . 'ii';
                    $params_page = array_merge($params, [$records_per_page, $offset]);
                    
                    $history_stmt = $conn->prepare($sql_final);
                    $history_stmt->bind_param($param_types_page, ...$params_page);
                    $history_stmt->execute();
                    $history_result = $history_stmt->get_result();
                ?>
                    <div class="card">
                        <div class="card-header"><h2>ប្រវត្តិបើកប្រាក់បៀវត្ស</h2></div>
                        <div class="tab-nav">
                            <a href="?page=payroll_history&type=staff&filter_year=<?php echo $filter_year; ?>" class="<?php echo ($employee_view_type == 'staff') ? 'active' : ''; ?>">បុគ្គលិកជំនាញ</a>
                            <a href="?page=payroll_history&type=worker&filter_year=<?php echo $filter_year; ?>" class="<?php echo ($employee_view_type == 'worker') ? 'active' : ''; ?>">កម្មករ</a>
                        </div>
                        
                        <form id="history-controls-form" method="get" class="filter-form">
                            <input type="hidden" name="page" value="payroll_history">
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($employee_view_type); ?>">
                            <div class="form-group">
                                <label for="filter_month">ជ្រើសរើសខែ:</label>
                                <select name="filter_month" id="filter_month" style="width: auto;">
                                    <option value="" <?php echo ($filter_month == '') ? 'selected' : ''; ?>>គ្រប់ខែ</option>
                                    <?php for ($m = 1; $m <= 12; $m++): $month_val = str_pad($m, 2, '0', STR_PAD_LEFT); ?>
                                        <option value="<?php echo $month_val; ?>" <?php echo ($filter_month == $month_val) ? 'selected' : ''; ?>><?php echo $month_val; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                             <div class="form-group">
                                <label for="filter_year">ជ្រើសរើសឆ្នាំ:</label>
                                <select name="filter_year" id="filter_year" style="width: auto;">
                                    <?php $current_year_select = date('Y'); for ($y = $current_year_select + 1; $y >= $current_year_select - 5; $y--): ?>
                                        <option value="<?php echo $y; ?>" <?php echo ($filter_year == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </form>

                        <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 1.5rem;">
                            <?php if($total_records > 0): ?>
                            <a href="?page=print_bulk&type=<?php echo htmlspecialchars($employee_view_type); ?>&filter_month=<?php echo htmlspecialchars($filter_month); ?>&filter_year=<?php echo htmlspecialchars($filter_year); ?>" target="_blank" class="btn">🖨️ បោះពុម្ព</a>
                            <form id="history-actions-form" method="post" style="margin: 0;">
                                <input type="hidden" name="action" value="delete_all_history">
                                <input type="hidden" name="type" value="<?php echo htmlspecialchars($employee_view_type); ?>">
                                <input type="hidden" name="filter_month" value="<?php echo htmlspecialchars($filter_month); ?>">
                                <input type="hidden" name="filter_year" value="<?php echo htmlspecialchars($filter_year); ?>">
                                <button type="submit" class="btn btn-delete">លុបប្រវត្តិ</button>
                            </form>
                            <?php endif; ?>
                        </div>
                        
                         <h3><?php echo ($employee_view_type == 'staff') ? 'បុគ្គលិកជំនាញ' : 'កម្មករ'; ?> (<?php echo empty($filter_month) ? "ឆ្នាំ " . htmlspecialchars($filter_year) : "ខែ " . htmlspecialchars($filter_month) . " ឆ្នាំ " . htmlspecialchars($filter_year); ?>)</h3>
                        <div style="overflow-x:auto;">
                            <table id="history-table">
                                <thead><tr><th>រយៈពេល</th><th>ឈ្មោះបុគ្គលិក</th><th>ប្រាក់បៀវត្សដុល</th><th>ការកាត់ប្រាក់សរុប</th><th>ប្រាក់បៀវត្សសុទ្ធ</th><th>សកម្មភាព</th></tr></thead>
                                <tbody>
                                <?php if ($history_result && $history_result->num_rows > 0): ?>
                                    <?php while ($row = $history_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date("d/m/Y", strtotime($row['pay_period_from'])) . ' - ' . date("d/m/Y", strtotime($row['pay_period_to'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                        <td>$<?php echo number_format($row['gross_salary'], 2); ?></td>
                                        <td>$<?php echo number_format($row['total_deductions'], 2); ?></td>
                                        <td><b>$<?php echo number_format($row['net_salary'], 2); ?></b></td>
                                        <td><a href="?page=view_payslip&payroll_id=<?php echo $row['id']; ?>" class="btn">មើលប័ណ្ណបើកប្រាក់ខែ</a></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" style="text-align:center;">មិនមានប្រវត្តិសម្រាប់រយៈពេលដែលបានជ្រើសរើសនេះទេ</td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                         <?php if ($total_pages > 1): ?>
                         <div class="pagination">
                             <?php $query_params = "page=payroll_history&type=$employee_view_type&filter_month=$filter_month&filter_year=$filter_year"; for ($i = 1; $i <= $total_pages; $i++): ?>
                                 <a href="?<?php echo $query_params; ?>&p=<?php echo $i; ?>" class="<?php echo ($current_page == $i ? 'active' : ''); ?>"><?php echo $i; ?></a>
                             <?php endfor; ?>
                         </div>
                         <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($page === 'view_payslip' && isset($_GET['payroll_id'])):
                    $payroll_id = (int)$_GET['payroll_id'];
                    // ✅ MODIFICATION: Fetch the latest employee data along with the payroll JSON using a LEFT JOIN.
                    $sql = "SELECT p.payroll_data_json, e.*
                            FROM payrolls p
                            LEFT JOIN employees e ON p.employee_id = e.id
                            WHERE p.id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $payroll_id);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if($result->num_rows > 0){
                        $payroll_row = $result->fetch_assoc();
                        $data = json_decode($payroll_row['payroll_data_json']);
                    } else {
                        die("រកមិនឃើញប័ណ្ណបើកប្រាក់ខែទេ");
                    }
                ?>
                    <div class="print-controls"><button onclick="window.print()" class="btn">🖨️ បោះពុម្ព Payslip</button></div>
                    <div class="payslip-container">
                        <div class="payslip-header">
                            <img src="<?php echo $company_logo_url; ?>" alt="Company Logo" class="payslip-logo">
                        </div>
                        <div class="payslip-title"><h1>ប័ណ្ណបើកប្រាក់បៀវត្ស</h1></div>
                        <div class="payslip-meta-info">
                            <div class="employee-details-top">
                                <p><strong>ឈ្មោះបុគ្គលិក:</strong> <?php echo htmlspecialchars($payroll_row['first_name'] . ' ' . $payroll_row['last_name']); ?></p>
                                <p><strong>តួនាទី:</strong> <?php echo htmlspecialchars($payroll_row['position']); ?></p>
                                <p><strong>រយៈពេលបើកប្រាក់:</strong> <?php echo date("d-M-Y", strtotime($data->pay_period->from)); ?> <b>ដល់</b> <?php echo date("d-M-Y", strtotime($data->pay_period->to)); ?></p>
                            </div>
                             <div class="qr-code-box-top"></div>
                        </div>
                        <div class="payslip-body">
                            <table class="payslip-table">
                                <tr class="payslip-section-header"><td colspan="2"><b>ចំណូល (Earnings)</b></td></tr>
                                <tr><td>ប្រាក់ខែគោល</td><td>$ <?php echo number_format($data->earnings->basic_salary, 2); ?></td></tr>
                                <?php foreach($data->earnings->allowances as $item): ?><tr><td><?php echo htmlspecialchars($item->type); ?></td><td>$ <?php echo number_format($item->amount, 2); ?></td></tr><?php endforeach; ?>
                                <tr><td>ប្រាក់បន្ថែមម៉ោង (OT)</td><td>$ <?php echo number_format($data->earnings->ot_details->total, 2); ?></td></tr>
                                <tr class="payslip-gross-salary"><td><b>ប្រាក់ខែសរុប (Gross Salary)</b></td><td><b>$ <?php echo number_format($data->summary->gross_salary, 2); ?></b></td></tr>
                                
                                <tr class="payslip-section-header"><td colspan="2"><b>ការកាត់ប្រាក់ (Deductions)</b></td></tr>
                                <?php if (empty($data->deductions)): ?><tr><td colspan="2" style="text-align:center;">មិនមានការកាត់ប្រាក់</td></tr>
                                <?php else: foreach($data->deductions as $item): ?><tr><td><?php echo htmlspecialchars($item->type); ?></td><td>($ <?php echo number_format($item->amount, 2); ?>)</td></tr><?php endforeach; endif; ?>
                                <tr class="payslip-total-deduction"><td><b>ការកាត់ប្រាក់សរុប</b></td><td><b>($ <?php echo number_format($data->summary->total_deductions, 2); ?>)</b></td></tr>
                                
                                <tr class="payslip-net-salary"><td><b>ប្រាក់ខែនៅសល់ (Net Salary)</b></td><td><b>$ <?php echo number_format($data->summary->net_salary, 2); ?></b></td></tr>
                            </table>
                            <div class="signature-area">
                                <div class="signature-box">
                                    <div class="line"></div>
                                    <p>ហត្ថលេខាអ្នកទទួល</p>
                                    <p><b><?php echo htmlspecialchars($payroll_row['first_name'] . ' ' . $payroll_row['last_name']); ?></b></p>
                                </div>
                                <div class="signature-box">
                                    <div class="line"></div>
                                    <p>ហត្ថលេខាអ្នកប្រគល់</p>
                                    <p><b>លី ស៊ាងអុី</b></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                        $(document).ready(function(){
                            const qrBox = $('.payslip-container .qr-code-box-top');
                            const qrText = <?php 
                                $qr_string_to_generate = '';
                                // ✅ MODIFICATION: Use the latest qr_code_string from the employee table.
                                if (!empty($payroll_row['qr_code_string'])) {
                                    $qr_string_to_generate = $payroll_row['qr_code_string'];
                                }
                                echo json_encode($qr_string_to_generate); 
                            ?>;
                            
                            if (qrText && qrBox.length) {
                                qrBox.empty();
                                new QRCode(qrBox.get(0), {
                                    text: qrText, width: 140, height: 140,
                                    colorDark : "#000000", colorLight : "#ffffff",
                                    correctLevel : QRCode.CorrectLevel.M
                                });
                            }
                        });
                    </script>
                <?php endif; ?>
                
                <?php if ($page === 'print_bulk'):
                    $employee_view_type = $_GET['type'] ?? 'staff'; $filter_month = $_GET['filter_month'] ?? ''; $filter_year = $_GET['filter_year'] ?? date('Y');
                    
                    // ✅ MODIFICATION: Select all necessary employee fields, not just the JSON.
                    $sql = "SELECT p.id, p.payroll_data_json, e.first_name, e.last_name, e.position, e.qr_code_string
                            FROM payrolls p
                            JOIN employees e ON p.employee_id = e.id
                            WHERE e.employee_type = ? AND YEAR(p.pay_period_to) = ?";

                    $params = [$employee_view_type, $filter_year];
                    $types = "si";
                    if(!empty($filter_month)){
                        $sql .= " AND MONTH(p.pay_period_to) = ?";
                        $params[] = $filter_month;
                        $types .= "i";
                    }
                    $sql .= " ORDER BY e.first_name ASC";
                    $stmt = $conn->prepare($sql); $stmt->bind_param($types, ...$params); $stmt->execute();
                    $result = $stmt->get_result();
                ?>
                    <!DOCTYPE html><html lang="km"><head><meta charset="UTF-8"><title>បោះពុម្ព Payslips</title>
                    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet">
                    <style>
                        body { font-family: 'Kantumruy Pro', sans-serif; background-color: #f0f0f0; margin: 0; }
                        .payslip-container { background: #ffffff; color: #333; max-width: 210mm; font-size: 11pt; margin: 20px auto; }
                        @page { size: auto; margin: 10mm; }
                        @media print {
                            body { margin: 0; background-color: white; }
                             .payslip-container { 
                                page-break-after: always; box-shadow: none; border: 1px solid #ccc;
                                font-size: 12pt; width: 100%; height: 100%;
                                display: flex; flex-direction: column;
                            }
                            .payslip-container:last-child { page-break-after: auto; }
                            .payslip-body { flex-grow: 1; display: flex; flex-direction: column; padding: 1rem 1.5rem; }
                            .payslip-header { padding: 1rem 1.5rem; }
                            .payslip-title { margin: 1rem 0; }
                            .payslip-meta-info { padding: 0 1.5rem 1rem 1.5rem; }
                            .payslip-table { flex-grow: 1; }
                            .payslip-table td { padding: 8px 10px; }
                            .signature-area { margin-top: auto; padding-top: 1rem; }
                            .signature-box .line { margin-top: 2.5rem; }
                            .qr-code-box-top { display: block !important; }
                            .payslip-meta-info { justify-content: space-between !important; }
                            .employee-details-top p { text-align: left; }
                            .payslip-net-salary td, .payslip-section-header td, .payslip-gross-salary td, .payslip-total-deduction td { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                        }
                        .payslip-header { display: flex; justify-content: center; align-items: center; padding: 1.5rem 2rem;  }
                        .payslip-logo { max-height: 80px; max-width: 350px; }
                        .payslip-title { text-align: center; margin: 2rem 0; }
                        .payslip-title h1 { font-size: 2.2em; font-weight: 600; color: #2c3e50; margin: 0; }
                        .payslip-meta-info { display: flex; justify-content: space-between; gap: 2rem; align-items: flex-start; padding: 0 2rem 1.5rem 2rem; }
                        .qr-code-box-top { min-width: 152px; min-height: 152px; }
                        .employee-details-top { line-height: 2; flex-grow: 1; }
                        .employee-details-top p { margin: 0; font-size: 1.1em; }
                        .employee-details-top strong { color: #2c3e50; width: 160px; display: inline-block; font-weight: 600; }
                        .payslip-body { padding: 1.5rem 2rem 2rem 2rem; }
                        .payslip-table { width: 100%; border-collapse: collapse; }
                        .payslip-table td { padding: 14px; border-bottom: 1px solid #f0f0f0; }
                        .payslip-table td:last-child { text-align: right; font-weight: 500; }
                        .payslip-section-header td { background-color: #f8f9fa; font-weight: 700; color: #2c3e50; font-size: 1.1em; border-top: 2px solid #e0e0e0; }
                        .payslip-gross-salary td { background-color: #e6f2ff; font-weight: 700; color: #007BFF; }
                        .payslip-total-deduction td { background-color: #ffeeee; font-weight: 700; color: #dc3545; }
                        .payslip-net-salary td { background-color: #2c3e50; color: #ffffff; font-size: 1.4em; font-weight: bold; }
                        .signature-area { margin-top: 5rem; padding-top: 1.5rem; display: flex; justify-content: space-around; border-top: 1px solid #e0e0e0; }
                        .signature-box { text-align: center; width: 250px; }
                        .signature-box .line { border-bottom: 1px solid #555; margin-top: 4rem; }
                        .signature-box p { margin: 8px 0 0; color: #555; }
                    </style>
                    </head><body>
                    <?php while($row = $result->fetch_assoc()): 
                        // $data contains the financial info, $row contains the latest employee info
                        $data = json_decode($row['payroll_data_json']);
                    ?>
                        <div class="payslip-container">
                            <div class="payslip-header">
                                <img src="<?php echo $company_logo_url; ?>" alt="Company Logo" class="payslip-logo">
                            </div>
                            <div class="payslip-title"><h1>ប័ណ្ណបើកប្រាក់បៀវត្ស</h1></div>
                            <div class="payslip-meta-info">
                                <div class="employee-details-top">
                                    <p><strong>ឈ្មោះបុគ្គលិក:</strong> <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></p>
                                    <p><strong>តួនាទី:</strong> <?php echo htmlspecialchars($row['position']); ?></p>
                                    <p><strong>រយៈពេលបើកប្រាក់:</strong> <?php echo date("d-M-Y", strtotime($data->pay_period->from)); ?> <b>ដល់</b> <?php echo date("d-M-Y", strtotime($data->pay_period->to)); ?></p>
                                </div>
                                <div class="qr-code-box-top" id="qr-box-<?php echo $row['id']; ?>"></div>
                            </div>
                            <div class="payslip-body">
                                <table class="payslip-table">
                                    <tr class="payslip-section-header"><td colspan="2"><b>ចំណូល (Earnings)</b></td></tr>
                                    <tr><td>ប្រាក់ខែគោល</td><td>$ <?php echo number_format($data->earnings->basic_salary, 2); ?></td></tr>
                                    <?php foreach($data->earnings->allowances as $item): ?><tr><td><?php echo htmlspecialchars($item->type); ?></td><td>$ <?php echo number_format($item->amount, 2); ?></td></tr><?php endforeach; ?>
                                    <tr><td>ប្រាក់បន្ថែមម៉ោង (OT)</td><td>$ <?php echo number_format($data->earnings->ot_details->total, 2); ?></td></tr>
                                    <tr class="payslip-gross-salary"><td><b>ប្រាក់ខែសរុប (Gross Salary)</b></td><td><b>$ <?php echo number_format($data->summary->gross_salary, 2); ?></b></td></tr>
                                    <tr class="payslip-section-header"><td colspan="2"><b>ការកាត់ប្រាក់ (Deductions)</b></td></tr>
                                    <?php if (empty($data->deductions)): ?><tr><td colspan="2" style="text-align:center;">មិនមានការកាត់ប្រាក់</td></tr>
                                    <?php else: foreach($data->deductions as $item): ?><tr><td><?php echo htmlspecialchars($item->type); ?></td><td>($ <?php echo number_format($item->amount, 2); ?>)</td></tr><?php endforeach; endif; ?>
                                    <tr class="payslip-total-deduction"><td><b>ការកាត់ប្រាក់សរុប</b></td><td><b>($ <?php echo number_format($data->summary->total_deductions, 2); ?>)</b></td></tr>
                                    <tr class="payslip-net-salary"><td><b>ប្រាក់ខែនៅសល់ (Net Salary)</b></td><td><b>$ <?php echo number_format($data->summary->net_salary, 2); ?></b></td></tr>
                                </table>
                                <div class="signature-area">
                                    <div class="signature-box"><div class="line"></div><p>ហត្ថលេខាអ្នកទទួល</p></div>
                                    <div class="signature-box"><div class="line"></div><p>ហត្ថលេខាអ្នកប្រគល់</p></div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    <script src="https://cdn.jsdelivr.net/npm/easyqrcodejs@4.4.13/dist/easy.qrcode.min.js"></script>
                    <script>
                        window.onload = function() {
                            <?php
                                mysqli_data_seek($result, 0);
                                while($row = $result->fetch_assoc()):
                                    // ✅ MODIFICATION: Use the latest qr_code_string from the main query's $row
                                    $qr_string_to_generate = '';
                                    if (!empty($row['qr_code_string'])) {
                                        $qr_string_to_generate = $row['qr_code_string'];
                                    }
                            ?>
                            var qrBox_<?php echo $row['id']; ?> = document.getElementById('qr-box-<?php echo $row['id']; ?>');
                            var qrText_<?php echo $row['id']; ?> = <?php echo json_encode($qr_string_to_generate); ?>;
                            if(qrBox_<?php echo $row['id']; ?> && qrText_<?php echo $row['id']; ?>) {
                                new QRCode(qrBox_<?php echo $row['id']; ?>, {
                                    text: qrText_<?php echo $row['id']; ?>,
                                    width: 140, height: 140,
                                    colorDark : "#000000", colorLight : "#ffffff",
                                    correctLevel : QRCode.CorrectLevel.M
                                });
                            }
                            <?php endwhile; ?>
                            
                            setTimeout(function() { window.print(); }, 500);
                        };
                    </script>
                    </body></html>
                <?php exit(); endif; ?>
                

                <?php 
                    } // បិទ else របស់ access_denied
                ?>
            </div>
    
        <?php if ($page !== 'print_bulk'): ?>
            </main>
        </div>
        <?php else: ?>
            </main>
        </div>
        <?php endif; ?>

<script>
$(document).ready(function() {
    
    // SIDEBAR TOGGLE SCRIPT
    const wrapper = $('.page-wrapper');
    const toggleBtn = $('#menu-toggle');
    if (localStorage.getItem('sidebarState') === 'collapsed') {
        wrapper.addClass('sidebar-collapsed');
    }
    toggleBtn.on('click', function() {
        wrapper.toggleClass('sidebar-collapsed');
        localStorage.setItem('sidebarState', wrapper.hasClass('sidebar-collapsed') ? 'collapsed' : 'expanded');
    });

    // REAL-TIME POLLING SCRIPT
    const currentPage = $('body').data('page');
    let pollingInterval;
    function startPolling(pageType) {
        pollingInterval = setInterval(() => {
            let ajaxData = { action: 'get_updates', view: pageType };
            if (pageType === 'employees') {
                ajaxData.last_id = $('#employees-table tbody tr').first().data('id') || 0;
            } else if (pageType === 'payroll_history') {
                ajaxData.current_count = $('#history-table tbody tr').length;
                ajaxData.type = $('#history-controls-form input[name="type"]').val();
                ajaxData.filter_month = $('#history-controls-form select[name="filter_month"]').val();
                ajaxData.filter_year = $('#history-controls-form select[name="filter_year"]').val();
            }
            $.ajax({
                type: 'GET', url: '', data: ajaxData, dataType: 'json',
                success: function(response) {
                    if (response.has_new) {
                        if (pageType === 'employees') { updateEmployeeTable(response.data); } 
                        else if (pageType === 'payroll_history') { updateHistoryTable(response.data); }
                    }
                },
                error: function() { console.error("Polling request failed."); clearInterval(pollingInterval); }
            });
        }, 7000);
    }
    function updateEmployeeTable(newEmployees) {
        const tableBody = $('#employees-table tbody');
        newEmployees.forEach(emp => {
            if ($(`#employee-row-${emp.id}`).length === 0) {
                let typeText = emp.employee_type === 'staff' ? 'បុគ្គលិកជំនាញ' : 'កម្មករ';
                let salary = parseFloat(emp.basic_salary).toFixed(2);
                const newRowHtml = `
                    <tr id="employee-row-${emp.id}" data-id="${emp.id}" style="display:none;">
                        <td>${emp.employee_id}</td>
                        <td>${emp.first_name} ${emp.last_name}</td>
                        <td>${emp.position}</td>
                        <td>${typeText}</td>
                        <td>$${salary}</td>
                        <td style="display:flex; gap: 0.5rem;">
                            <a href="?page=edit_employee&id=${emp.id}" class="btn btn-edit">កែសម្រួល</a>
                            <a href="#" class="btn btn-delete btn-delete-employee" data-id="${emp.id}">លុប</a>
                        </td>
                    </tr>`;
                let newRow = $(newRowHtml);
                tableBody.prepend(newRow);
                newRow.fadeIn(1000).addClass('new-row-highlight');
                 setTimeout(() => { newRow.removeClass('new-row-highlight'); }, 2000);
            }
        });
    }
    function updateHistoryTable(newData) {
        const tableBody = $('#history-table tbody');
        tableBody.empty();
        if (newData.length === 0) {
            tableBody.html('<tr><td colspan="6" style="text-align:center;">មិនមានប្រវត្តិសម្រាប់រយៈពេលដែលបានជ្រើសរើសនេះទេ</td></tr>');
            return;
        }
        newData.forEach(row => {
            let gross_salary = parseFloat(row.gross_salary).toFixed(2);
            let total_deductions = parseFloat(row.total_deductions).toFixed(2);
            let net_salary = parseFloat(row.net_salary).toFixed(2);
            let period_from = new Date(row.pay_period_from).toLocaleDateString('en-GB');
            let period_to = new Date(row.pay_period_to).toLocaleDateString('en-GB');
            const newRowHtml = `
                <tr style="display:none;">
                    <td>${period_from} - ${period_to}</td> <td>${row.first_name} ${row.last_name}</td>
                    <td>$${gross_salary}</td> <td>$${total_deductions}</td> <td><b>$${net_salary}</b></td>
                    <td><a href="?page=view_payslip&payroll_id=${row.id}" class="btn">មើលប័ណ្ណបើកប្រាក់ខែ</a></td>
                </tr>`;
            let newRow = $(newRowHtml);
            tableBody.append(newRow);
            newRow.fadeIn(1000);
        });
        showNotification("ទិន្នន័យប្រវត្តិបៀវត្សត្រូវបានធ្វើបច្ចុប្បន្នភាព!", 'success');
    }
    if (currentPage === 'employees' || currentPage === 'payroll_history') {
        startPolling(currentPage);
    }

    // OTHER SCRIPTS
    function showNotification(message, type = 'success') {
        const notifId = 'notif-' + Date.now();
        const notif = `<div id="${notifId}" class="notification ${type}">${message}</div>`;
        $('#notification-area').append(notif);
        setTimeout(() => { $(`#${notifId}`).addClass('show'); }, 10);
        setTimeout(() => { $(`#${notifId}`).removeClass('show'); setTimeout(() => { $(`#${notifId}`).remove(); }, 500); }, 5000);
    }
    function handleAjaxFormSubmit(formId, successCallback) {
        $(formId).on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            $.ajax({
                type: 'POST', url: '', data: formData, dataType: 'json',
                success: function(response) {
                    if(response.status === 'success') { showNotification(response.message, 'success'); if (successCallback) { successCallback(response); } } 
                    else { showNotification(response.message, 'error'); }
                },
                error: function() { showNotification('មានបញ្ហាក្នុងការតភ្ជាប់ទៅ Server!', 'error'); }
            });
        });
    }
    handleAjaxFormSubmit('#add-employee-form', () => { setTimeout(() => { window.location.href = '?page=employees'; }, 1000); });
    handleAjaxFormSubmit('#update-employee-form', () => { showNotification('កំពុងដំណើរការ...'); setTimeout(() => { location.reload(); }, 1200); });
    handleAjaxFormSubmit('#add-allowance-form', () => setTimeout(() => location.reload(), 500));
    handleAjaxFormSubmit('#add-deduction-form', () => setTimeout(() => location.reload(), 500));
    $('body').on('click', '.btn-delete-employee', function(e) {
        e.preventDefault();
        if (confirm('តើអ្នកពិតជាចង់លុបបុគ្គលិកនេះមែនទេ?')) {
            const id = $(this).data('id');
            const row = $('#employee-row-' + id);
            $.ajax({
                type: 'GET', url: `?action=delete_employee&id=${id}`, dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') { row.fadeOut(500, function() { $(this).remove(); }); showNotification(response.message, 'success'); } 
                    else { showNotification(response.message, 'error'); }
                }
            });
        }
    });
    $('body').on('click', '.btn-delete-item', function(e) {
        e.preventDefault();
        if (confirm('តើអ្នកពិតជាចង់លុបធាតុនេះមែនទេ?')) {
            const action = $(this).data('action');
            const itemId = $(this).data('item-id');
            $.ajax({
                type: 'GET', url: `?action=${action}&item_id=${itemId}`, dataType: 'json',
                success: (response) => {
                    if (response.status === 'success') { showNotification(response.message, 'success'); setTimeout(() => location.reload(), 500); } 
                    else { showNotification(response.message, 'error'); }
                }
            });
        }
    });
    $('#payroll-bulk-form').on('submit', function(e) {
        e.preventDefault();
        let action = $(document.activeElement).val();
        if (!action) return;
        if (action === 'process_bulk_payroll' && !confirm('តើអ្នកប្រាកដទេថាចង់បញ្ចប់ការគណនា?')) { return; }
        let formData = $(this).serialize() + "&action=" + action;
        $.ajax({
            type: 'POST', url: '', data: formData, dataType: 'json',
            success: function(response) {
                if(response.status === 'success') {
                    showNotification(response.message, 'success');
                    if (action === 'process_bulk_payroll') {
                        setTimeout(() => {
                            const form = $('#payroll-bulk-form');
                            const dateTo = form.find('input[name="date_to"]').val(); const type = form.find('input[name="type"]').val();
                            const month = dateTo.substring(5, 7); const year = dateTo.substring(0, 4);
                            window.location.href = `?page=payroll_history&type=${type}&filter_month=${month}&filter_year=${year}`;
                        }, 1000);
                    }
                } else { showNotification(response.message, 'error'); }
            }
        });
    });
    $('#history-controls-form select').on('change', function() { document.getElementById('history-controls-form').submit(); });
    $('#history-actions-form').on('submit', function(e) {
       e.preventDefault();
       const monthText = $('#history-controls-form').find('select[name="filter_month"] option:selected').text();
       const yearText = $('#history-controls-form').find('select[name="filter_year"] option:selected').text();
       const confirmText = `តើអ្នកពិតជាចង់លុបប្រវត្តិទាំងអស់សម្រាប់ (${monthText} ឆ្នាំ ${yearText}) មែនទេ? សកម្មភាពនេះមិនអាចមិនធ្វើវិញបានទេ!`;
       if (confirm(confirmText)) {
           $.ajax({
               type: 'POST', url: '', data: $(this).serialize(), dataType: 'json',
               success: (response) => {
                   if (response.status === 'success') { showNotification(response.message, 'success'); setTimeout(() => { location.reload(); }, 1200); } 
                   else { showNotification(response.message, 'error'); }
               }
           });
       }
    });
    const qrInput = $('#qr_code_string');
    const qrPreviewContainer = $('#qr-preview-container');
    function generateQrPreview() {
        const qrText = qrInput.val();
        qrPreviewContainer.empty();
        if (qrText && qrText.trim() !== '') {
            try { new QRCode(qrPreviewContainer.get(0), { text: qrText, width: 160, height: 160, colorDark: "#000000", colorLight: "#ffffff", correctLevel: QRCode.CorrectLevel.M });
            } catch (e) { console.error("QR Code generation failed:", e); qrPreviewContainer.text("Error: QR string might be invalid."); }
        }
    }
    if (qrInput.length > 0) { generateQrPreview(); qrInput.on('input', generateQrPreview); }
    function calculateOtTotal(row) {
        const days = parseFloat($(row).find('.ot-days-input').val()) || 0;
        const rate = parseFloat($(row).find('.ot-rate-input').val()) || 0;
        $(row).find('.ot-total-amount').text((days * rate).toFixed(2));
    }
    $('#payroll-bulk-form').on('input', '.ot-days-input, .ot-rate-input', function() { calculateOtTotal($(this).closest('tr')); });
    $('#payroll-bulk-form tbody tr').each(function() { if ($(this).find('.ot-days-input').length > 0) { calculateOtTotal(this); } });

});
</script>

</body>
</html>
<?php $conn->close(); ?>