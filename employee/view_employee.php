<?php
// [Your PHP code remains unchanged up to the HTML section]
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

include '../admin/includes/db.php';

if (!$conn) {
    die("ការតភ្ជាប់មូលដ្ឋានទិន្នន័យបានបរាជ័យ៖ មិនអាចបង្កើតការតភ្ជាប់បានទេ");
}

$employee_data = null;
$error_message = null;
$success_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    try {
        $employee_id = $_POST['employee_id'] ?? '';
        $search_month = $_POST['search_month'] ?? '';
        $sql = "SELECT * FROM employees_data WHERE 1=1";
        $params = [];
        if (!empty($employee_id)) {
            $sql .= " AND employee_id = :employee_id";
            $params[':employee_id'] = $employee_id;
        }
        if (!empty($search_month)) {
            $sql .= " AND search_month = :search_month";
            $params[':search_month'] = $search_month;
        }
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $employee_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$employee_data) {
            $error_message = "រកមិនឃើញបុគ្គលិកសម្រាប់លក្ខខណ្ឌដែលបានផ្តល់ឱ្យទេ។";
        }
    } catch (Exception $e) {
        $error_message = "កំហុស៖ " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_submit'])) {
    try {
        $data = [
            'leave_request' => $_POST['leave_request'] ?? '',
            'notes' => $_POST['notes'] ?? '',
            'late_arrival' => $_POST['late_arrival'] ?? '',
            'early_leave' => $_POST['early_leave'] ?? '',
            'overtime' => $_POST['overtime'] ?? '',
            'salary_deduction' => $_POST['salary_deduction'] ?? 0.00,
            'annual_leave_balance' => $_POST['annual_leave_balance'] ?? 0,
            'birth_date' => !empty($_POST['birth_date']) ? $_POST['birth_date'] : null,
            'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            'id_card_number' => $_POST['id_card_number'] ?? '',
            'contract_end_date' => !empty($_POST['contract_end_date']) ? $_POST['contract_end_date'] : null,
            'gender' => $_POST['gender'] ?? '',
            'search_month' => !empty($_POST['start_date']) ? date('Y-m', strtotime($_POST['start_date'])) : null,
            'certificate' => $_POST['certificate'] ?? '',
            'employee_id' => $_POST['employee_id']
        ];
        $sql = "UPDATE employees_data SET 
            leave_request = :leave_request, notes = :notes, late_arrival = :late_arrival,
            early_leave = :early_leave, overtime = :overtime, salary_deduction = :salary_deduction,
            annual_leave_balance = :annual_leave_balance, birth_date = :birth_date,
            start_date = :start_date, id_card_number = :id_card_number,
            contract_end_date = :contract_end_date, gender = :gender, search_month = :search_month,
            certificate = :certificate
            WHERE employee_id = :employee_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute($data);
        $success_message = "ទិន្នន័យបុគ្គលិកត្រូវបានធ្វើបច្ចុប្បន្នភាពដោយជោគជ័យ!";
        $stmt = $conn->prepare("SELECT * FROM employees_data WHERE employee_id = :employee_id");
        $stmt->execute([':employee_id' => $data['employee_id']]);
        $employee_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error_message = "កំហុសក្នុងការកែសម្រួល៖ " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    try {
        $employee_id = $_POST['employee_id'];
        $stmt = $conn->prepare("SELECT photo_path FROM employees_data WHERE employee_id = :employee_id");
        $stmt->execute([':employee_id' => $employee_id]);
        $files = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($files) {
            if ($files['photo_path'] && file_exists($files['photo_path'])) {
                unlink($files['photo_path']);
            }
        }
        $sql = "DELETE FROM employees_data WHERE employee_id = :employee_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':employee_id' => $employee_id]);
        $success_message = "ទិន្នន័យបុគ្គលិកត្រូវបានលុបចោលដោយជោគជ័យ!";
        $employee_data = null;
    } catch (Exception $e) {
        $error_message = "កំហុសក្នុងការលុប៖ " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>មើលទិន្នន័យបុគ្គលិក</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Noto Sans Khmer', 'Arial', sans-serif;
            background: #f0f4f8;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 30px;
            color: #1e293b;
        }

        .container {
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            width: 100%;
            max-width: 900px;
        }

        h1 {
            text-align: center;
            color: #3b82f6;
            margin-bottom: 30px;
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .search-form {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 10px;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .search-form input[type="text"],
        .search-form input[type="month"] {
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            flex: 1;
            min-width: 200px;
            background: #fff;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .search-form input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            outline: none;
        }

        .search-form button {
            padding: 12px 25px;
            background: #3b82f6;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .search-form button:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .result-card {
            display: none;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.06);
            margin-bottom: 25px;
            border-left: 4px solid #3b82f6;
        }

        .result-card.show {
            display: block;
            animation: slideIn 0.5s ease-out;
        }

        .result-card .data-row {
            display: grid;
            grid-template-columns: 1fr 2fr;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .result-card .data-row:last-child {
            border-bottom: none;
        }

        .result-card .label {
            font-weight: 600;
            color: #1e293b;
            padding-right: 15px;
        }

        .result-card .value {
            color: #475569;
            word-break: break-word;
        }

        .result-card .value a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }

        .result-card .value a:hover {
            text-decoration: underline;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .action-buttons button {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .edit-btn {
            background: #10b981;
            color: #fff;
        }

        .edit-btn:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .delete-btn {
            background: #ef4444;
            color: #fff;
        }

        .delete-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .print-btn {
            background: #3b82f6;
            color: #fff;
        }

        .print-btn:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
            position: relative;
            animation: zoomIn 0.3s ease-out;
        }

        .modal-content h2 {
            color: #1e293b;
            margin-bottom: 20px;
            font-size: 1.6rem;
            font-weight: 600;
        }

        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.8rem;
            color: #64748b;
            cursor: pointer;
            border: none;
            background: none;
            transition: color 0.3s ease;
        }

        .close-btn:hover {
            color: #1e293b;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #1e293b;
            font-size: 0.95rem;
            font-weight: 500;
        }

        input, textarea, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            background: #f8fafc;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        input:focus, textarea:focus, select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            outline: none;
        }

        .file-preview {
            margin-top: 10px;
        }

        .file-preview a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }

        .file-preview a:hover {
            text-decoration: underline;
        }

        .save-btn {
            width: 100%;
            padding: 14px;
            background: #10b981;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease;
        }

        .save-btn:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .message {
            text-align: center;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 500;
            animation: popIn 0.4s ease;
        }

        .success { background: #ecfdf5; color: #065f46; }
        .error { background: #fef2f2; color: #991b1b; }

        @keyframes slideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes zoomIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        @keyframes popIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        /* Updated Print UI with Horizontal Layout */
        @media print {
            @page {
                size: A4;
                margin: 1mm;
            }

            body {
                background: #fff;
                margin: 0;
                padding: 0;
                font-family: 'Noto Sans Khmer', 'Arial', sans-serif;
                font-size: 11pt;
                color: #1a1a1a;
                line-height: 1.5;
                position: relative;
            }

            .container {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
                width: 100%;
                margin: 0;
            }

            h1, .search-form, .action-buttons, .message, .modal {
                display: none !important;
            }

            .result-card {
                display: block !important;
                background: none;
                padding: 0;
                box-shadow: none;
                margin: 0;
                border: none;
            }

            .print-header {
                display: block !important;
                position: relative;
                margin-bottom: 30px;
                padding-bottom: 15px;
                border-bottom: 5px double rgb(236, 201, 0);
                page-break-after: avoid;
            }

            .print-header::before {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 150px;
                height: 150px;
                background: url('https://i.ibb.co/r2JWnd2x/Logo-Van-Van-1.png') no-repeat center;
                background-size: contain;
                opacity: 0.1;
                z-index: -1;
            }

            .print-header .header-top {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 15px;
            }

            .print-header .logo {
                width: 90px;
                height: 90px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .print-header .logo img {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }

            .print-header .header-title {
                text-align: center;
                flex-grow: 1;
            }

            .print-header .header-title h2 {
                font-size: 20pt;
                font-weight: 700;
                color: rgb(236, 201, 0);
                margin-bottom: 8px;
                text-transform: uppercase;
            }

            .print-header .header-title p {
                font-size: 13pt;
                color: #4a4a4a;
                font-weight: 500;
            }

            .print-header .header-details {
                font-size: 10pt;
                color: #666;
                text-align: right;
            }

            .print-header .header-details p {
                margin: 4px 0;
            }

            /* New Table Style with Horizontal Layout */
            .result-card {
                display: block !important;
                width: 100%;
                margin-top: 20px;
                page-break-inside: auto;
            }

            /* Section Header */
            .result-card .section-header {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 15px;
                padding-bottom: 8px;
                border-bottom: 1px solid #e5e7eb;
                page-break-after: avoid;
            }

            .result-card .section-header::before {
                content: "👤"; /* Person icon (emoji used for simplicity; replace with SVG/icon font for production) */
                font-size: 16pt;
                color: #3b82f6; /* Blue icon */
            }

            .result-card .section-header span {
                font-size: 14pt;
                font-weight: 700;
                color: #3b82f6; /* Blue text */
            }

            /* Data Row Container */
            .result-card .data-row-container {
                display: flex;
                flex-wrap: wrap; /* Allow wrapping to the next line if needed */
                gap: 10px; /* Space between items */
                page-break-inside: auto;
            }

            /* Data Row */
            .result-card .data-row {
                display: flex;
                align-items: center;
                gap: 10px;
                background: #f8fafc; /* Light gray background */
                border-radius: 8px;
                padding: 10px 15px;
                flex: 1 1 30%; /* Each item takes approximately 30% of the width, allowing 3 items per row */
                min-width: 250px; /* Minimum width to ensure readability */
                box-sizing: border-box;
                page-break-inside: avoid;
            }

            .result-card .data-row::before {
                /* Icon for each row (using pseudo-element with emoji; replace with SVG/icon font for production) */
                font-size: 14pt;
                color: #3b82f6; /* Blue icon */
                width: 20px;
                text-align: center;
            }

            /* Assign specific icons to rows based on their position */
            .result-card .data-row:nth-child(1)::before { content: "🪪"; } /* ID */
            .result-card .data-row:nth-child(2)::before { content: "👤"; } /* Name */
            .result-card .data-row:nth-child(3)::before { content: "🏢"; } /* Department */
            .result-card .data-row:nth-child(4)::before { content: "💼"; } /* Position */
            .result-card .data-row:nth-child(5)::before { content: "📍"; } /* Branch */
            .result-card .data-row:nth-child(6)::before { content: "📞"; } /* Contact */
            .result-card .data-row:nth-child(7)::before { content: "🕒"; } /* Overtime */
            .result-card .data-row:nth-child(8)::before { content: "💸"; } /* Salary Deduction */
            .result-card .data-row:nth-child(9)::before { content: "🎂"; } /* Birth Date */
            .result-card .data-row:nth-child(10)::before { content: "📅"; } /* Start Date */
            .result-card .data-row:nth-child(11)::before { content: "🪪"; } /* ID Card */
            .result-card .data-row:nth-child(12)::before { content: "📅"; } /* Contract End */
            .result-card .data-row:nth-child(13)::before { content: "⚥"; } /* Gender */
            .result-card .data-row:nth-child(14)::before { content: "📅"; } /* Search Month */
            .result-card .data-row:nth-child(15)::before { content: "📷"; } /* Photo */
            .result-card .data-row:nth-child(16)::before { content: "🎓"; } /* Certificate */

            .result-card .data-row .label {
                font-weight: 600;
                color: #1e293b; /* Dark gray */
                flex: 1;
            }

            .result-card .data-row .value {
                font-weight: 400;
                color: #475569; /* Lighter gray */
                flex: 1;
                word-break: break-word;
            }

            .result-card .data-row .value a {
                color: #3b82f6; /* Blue for links */
                text-decoration: underline;
            }

            .print-footer {
                display: block !important;
                margin-top: 30px;
                padding-top: 15px;
                border-top: 3px solid rgb(236, 201, 0);
                font-size: 9pt;
                color: #666;
                page-break-before: avoid;
            }

            .print-footer .footer-content {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
            }

            .print-footer .footer-left {
                text-align: left;
            }

            .print-footer .footer-right {
                text-align: right;
            }

            /* Updated Signature Section with 3 Approvers */
            .print-footer .signature-container {
                display: flex;
                justify-content: space-between;
                margin-top: 40px;
                gap: 20px;
                page-break-inside: avoid;
            }

            .print-footer .signature {
                flex: 1;
                text-align: center;
            }

            .print-footer .signature-line {
                width: 150px; /* Adjusted width for 3 signatures */
                border-top: 1px solid #666;
                margin: 60px auto 10px;
            }

            .print-footer .page-number::before {
                content: "ទំព័រ " counter(page) " នៃ " counter(pages);
            }
        }

        /* Responsive Design (Screen Only) */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .form-group.full-width {
                grid-column: span 1;
            }
            .container {
                padding: 20px;
            }
            h1 {
                font-size: 1.6rem;
            }
            .search-form {
                flex-direction: column;
                padding: 10px;
            }
            .search-form input, .search-form button {
                width: 100%;
            }
            .result-card .data-row {
                grid-template-columns: 1fr;
                gap: 5px;
            }
            .modal-content {
                width: 90%;
                padding: 15px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 15px;
                margin: 10px;
            }
            h1 {
                font-size: 1.4rem;
            }
            .action-buttons button {
                padding: 10px 20px;
                font-size: 0.9rem;
            }
            .save-btn {
                padding: 12px;
                font-size: 1rem;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1>មើលទិន្នន័យបុគ្គលិក</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php elseif (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" class="search-form">
            <input type="text" name="employee_id" placeholder="លេខសម្គាល់បុគ្គលិក">
            <input type="month" name="search_month" placeholder="ជ្រើសរើសខែ">
            <button type="submit" name="search">ស្វែងរក</button>
        </form>

        <?php if ($employee_data): ?>
            <div class="print-header" style="display: none;">
                <div class="header-top">
                    <div class="logo"><img src="https://i.ibb.co/r2JWnd2x/Logo-Van-Van-1.png" alt=""></div>
                    <div class="header-details">
                        <p>លេខសម្គាល់៖ <?php echo htmlspecialchars($employee_data['employee_id'] ?? ''); ?></p>
                        <p>ខែ៖ <?php echo htmlspecialchars($employee_data['search_month'] ?? ''); ?></p>
                        <p>ថ្ងៃបោះពុម្ព៖ <?php echo date('Y-m-d'); ?></p>
                    </div>
                </div>
                <div class="header-title">
                    <h2>របាយការណ៍ទិន្នន័យបុគ្គលិក</h2>
                    <p>ក្រុមហ៊ុន វណ្ណ វណ្ច ខេមបូឌា</p>
                </div>
            </div>
            <div class="result-card show">
                <div class="section-header">
                    <span>ព័ត៌មានផ្ទាល់ខ្លួន</span>
                </div>
                <div class="data-row-container">
                    <div class="data-row">
                        <span class="label">លេខសម្គាល់បុគ្គលិក:</span>
                        <span class="value"><?php echo htmlspecialchars($employee_data['employee_id'] ?? ''); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="label">ឈ្មោះស្នើសុំ:</span>
                        <span class="value"><?php echo htmlspecialchars($employee_data['leave_request'] ?? ''); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="label">កំណត់ហេតុ</span>
                        <span class="value"><?php echo htmlspecialchars($employee_data['notes'] ?? ''); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="label">តួនាទី:</span>
                        <span class="value"><?php echo htmlspecialchars($employee_data['late_arrival'] ?? ''); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="label">ការចាកចេញមុន:</span>
                        <span class="value"><?php echo htmlspecialchars($employee_data['early_leave'] ?? ''); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="label">ថែមម៉ោង:</span>
                        <span class="value"><?php echo htmlspecialchars($employee_data['overtime'] ?? ''); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="label">ការកាត់ប្រាក់ខែ:</span>
                        <span class="value"><?php echo htmlspecialchars($employee_data['salary_deduction'] ?? '0.00'); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="label">សមតុល្យច្បាប់ឈប់សម្រាកប្រចាំឆ្នាំ:</span>
                        <span class="value"><?php echo htmlspecialchars($employee_data['annual_leave_balance'] ?? '0'); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="label">ថ្ងៃខែឆ្នាំកំណើត:</span>
                        <span class="value"><?php echo htmlspecialchars($employee_data['birth_date'] ?? ''); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="label">ថ្ងៃចាប់ផ្តើមធ្វើការ:</span>
                        <span class="value"><?php echo htmlspecialchars($employee_data['start_date'] ?? ''); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="label">លេខអត្តសញ្ញាណប័ណ្ណ:</span>
                        <span class="value"><?php echo htmlspecialchars($employee_data['id_card_number'] ?? ''); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="label">ថ្ងៃបញ្ចប់កិច្ចសន្យា:</span>
                        <span class="value"><?php echo htmlspecialchars($employee_data['contract_end_date'] ?? ''); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="label">ភេទ:</span>
                        <span class="value"><?php echo htmlspecialchars($employee_data['gender'] ?? ''); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="label">ខែស្វែងរក:</span>
                        <span class="value"><?php echo htmlspecialchars($employee_data['search_month'] ?? ''); ?></span>
                    </div>
                    <div class="data-row">
                        <span class="label">រូបថត:</span>
                        <span class="value">
                            <?php if ($employee_data['photo_path']): ?>
                                <a href="<?php echo htmlspecialchars($employee_data['photo_path']); ?>" target="_blank">មើលរូបថត</a>
                            <?php else: ?>
                                គ្មានរូបថត
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="data-row">
                        <span class="label">វិញ្ញាបនបត្រ:</span>
                        <span class="value">
                            <?php echo htmlspecialchars($employee_data['certificate'] ?? 'គ្មានវិញ្ញាបនបត្រ'); ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="print-footer" style="display: none;">
                <div class="footer-content">
                    <div class="footer-left">
                        <p>បោះពុម្ព៖ <?php echo date('Y-m-d H:i:s'); ?></p>
                        <p>ទំព័រ <span class="page-number"></span></p>
                    </div>
                    <div class="footer-right">
                        <p>ក្រុមហ៊ុន វណ្ណ វណ្ច ខេមបូឌា</p>
                        <p>អាសយដ្ឋាន៖ ភ្នំពេញ, កម្ពុជា</p>
                        <p>ទូរស័ព្ទ៖ +855 12 345 678</p>
                    </div>
                </div>
                <div class="signature-container">
                    <div class="signature">
                        <div class="signature-line"></div>
                        <p>ហត្ថលេខា និង ឈ្មោះអ្នកអនុម័ត</p>
                    </div>
                    <div class="signature">
                        <div class="signature-line"></div>
                        <p>ហត្ថលេខា និង ឈ្មោះអ្នកអនុម័ត</p>
                    </div>
                    <div class="signature">
                        <div class="signature-line"></div>
                        <p>ហត្ថលេខា និង ឈ្មោះអ្នកអនុម័ត</p>
                    </div>
                </div>
            </div>

            <form method="POST" class="action-buttons">
                <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($employee_data['employee_id'] ?? ''); ?>">
                <button type="button" class="edit-btn" onclick="showModal()">កែសម្រួល</button>
                <button type="submit" name="delete" class="delete-btn" onclick="return confirm('តើអ្នកប្រាកដជាចង់លុបទិន្នន័យនេះឬ?');">លុប</button>
                <button type="button" class="print-btn" onclick="printEmployeeData()">បោះពុម្ព</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($employee_data): ?>
        <div id="editModal" class="modal">
            <div class="modal-content">
                <button class="close-btn" onclick="hideModal()">×</button>
                <h2>កែសម្រួលទិន្នន័យបុគ្គលិក</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>លេខសម្គាល់បុគ្គលិក</label>
                            <input type="text" name="employee_id" value="<?php echo htmlspecialchars($employee_data['employee_id'] ?? ''); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>សំណើច្បាប់ឈប់សម្រាក</label>
                            <input type="text" name="leave_request" value="<?php echo htmlspecialchars($employee_data['leave_request'] ?? ''); ?>">
                        </div>
                        <div class="form-group full-width">
                            <label>កំណត់ចំណាំ</label>
                            <textarea name="notes" rows="4"><?php echo htmlspecialchars($employee_data['notes'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>ការមកយឺត</label>
                            <input type="text" name="late_arrival" value="<?php echo htmlspecialchars($employee_data['late_arrival'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>ការចាកចេញមុន</label>
                            <input type="text" name="early_leave" value="<?php echo htmlspecialchars($employee_data['early_leave'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>ម៉ោងបន្ថែម</label>
                            <input type="text" name="overtime" value="<?php echo htmlspecialchars($employee_data['overtime'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>ការកាត់ប្រាក់ខែ</label>
                            <input type="number" step="0.01" name="salary_deduction" value="<?php echo htmlspecialchars($employee_data['salary_deduction'] ?? '0.00'); ?>">
                        </div>
                        <div class="form-group">
                            <label>សមតុល្យច្បាប់ឈប់សម្រាកប្រចាំឆ្នាំ</label>
                            <input type="number" name="annual_leave_balance" value="<?php echo htmlspecialchars($employee_data['annual_leave_balance'] ?? '0'); ?>">
                        </div>
                        <div class="form-group">
                            <label>ថ្ងៃខែឆ្នាំកំណើត</label>
                            <input type="date" name="birth_date" value="<?php echo htmlspecialchars($employee_data['birth_date'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>ថ្ងៃចាប់ផ្តើមធ្វើការ</label>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($employee_data['start_date'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>លេខអត្តសញ្ញាណប័ណ្ណ</label>
                            <input type="text" name="id_card_number" value="<?php echo htmlspecialchars($employee_data['id_card_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>ថ្ងៃបញ្ចប់កិច្ចសន្យា</label>
                            <input type="date" name="contract_end_date" value="<?php echo htmlspecialchars($employee_data['contract_end_date'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>ភេទ</label>
                            <select name="gender">
                                <option value="">ជ្រើសរើសភេទ</option>
                                <option value="បុរស" <?php echo ($employee_data['gender'] ?? '') === 'បុរស' ? 'selected' : ''; ?>>បុរស</option>
                                <option value="ស្ត្រី" <?php echo ($employee_data['gender'] ?? '') === 'ស្ត្រី' ? 'selected' : ''; ?>>ស្ត្រី</option>
                                <option value="ផ្សេងទៀត" <?php echo ($employee_data['gender'] ?? '') === 'ផ្សេងទៀត' ? 'selected' : ''; ?>>ផ្សេងទៀត</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>រូបថត</label>
                            <?php if ($employee_data['photo_path']): ?>
                                <div class="file-preview">
                                    <a href="<?php echo htmlspecialchars($employee_data['photo_path']); ?>" target="_blank">មើលរូបថត</a>
                                </div>
                            <?php endif; ?>
                            <input type="file" name="photo" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label>វិញ្ញាបនបត្រ</label>
                            <input type="text" name="certificate" value="<?php echo htmlspecialchars($employee_data['certificate'] ?? ''); ?>" placeholder="បញ្ចូលវិញ្ញាបនបត្រ">
                        </div>
                        <div class="form-group full-width">
                            <button type="submit" name="edit_submit" class="save-btn">រក្សាទុក</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function showModal() {
            document.getElementById('editModal').classList.add('show');
        }

        function hideModal() {
            document.getElementById('editModal').classList.remove('show');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const resultCard = document.querySelector('.result-card');
            if (resultCard && <?php echo $employee_data ? 'true' : 'false'; ?>) {
                resultCard.classList.add('show');
            }
        });

        function printEmployeeData() {
            window.print();
        }
    </script>
</body>
</html>
