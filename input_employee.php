<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

include 'admin/includes/db.php';

if (!$conn) {
    die("ការតភ្ជាប់មូលដ្ឋានទិន្នន័យបានបរាជ័យ៖ មិនអាចបង្កើតការតភ្ជាប់បានទេ");
}

$error_message = null;
$success_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_submit'])) {
    try {
        // Handle file upload for photo
        $photo_path = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $photo_name = time() . '_' . basename($_FILES['photo']['name']);
            $photo_path = $upload_dir . $photo_name;
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
                throw new Exception("បរាជ័យក្នុងការបញ្ចូលរូបថត");
            }
        }

        // Prepare data for insertion
        $data = [
            'employee_id' => $_POST['employee_id'] ?? '',
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
            'search_month' => !empty($_POST['search_month']) ? $_POST['search_month'] : null, // Use user-provided search_month
            'certificate' => $_POST['certificate'] ?? '',
            'photo_path' => $photo_path
        ];

        // Validate required fields
        if (empty($data['employee_id'])) {
            throw new Exception("សូមបញ្ចូលលេខសម្គាល់បុគ្គលិក");
        }

        // Check if employee_id already exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM employees_data WHERE employee_id = :employee_id");
        $stmt->execute([':employee_id' => $data['employee_id']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("លេខសម្គាល់បុគ្គលិកនេះមានរួចហើយ");
        }

        // Insert data into the database
        $sql = "INSERT INTO employees_data (
            employee_id, leave_request, notes, late_arrival, early_leave, overtime, salary_deduction,
            annual_leave_balance, birth_date, start_date, id_card_number, contract_end_date, gender,
            search_month, certificate, photo_path
        ) VALUES (
            :employee_id, :leave_request, :notes, :late_arrival, :early_leave, :overtime, :salary_deduction,
            :annual_leave_balance, :birth_date, :start_date, :id_card_number, :contract_end_date, :gender,
            :search_month, :certificate, :photo_path
        )";
        $stmt = $conn->prepare($sql);
        $stmt->execute($data);

        $success_message = "ទិន្នន័យបុគ្គលិកត្រូវបានបញ្ចូលដោយជោគជ័យ!";
    } catch (Exception $e) {
        $error_message = "កំហុសក្នុងការបញ្ចូលទិន្នន័យ៖ " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>បញ្ចូលទិន្នន័យបុគ្គលិក</title>
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

        @keyframes popIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        /* Responsive Design */
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
        }

        @media (max-width: 480px) {
            .container {
                padding: 15px;
                margin: 10px;
            }
            h1 {
                font-size: 1.4rem;
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
        <h1>បញ្ចូលទិន្នន័យបុគ្គលិក</h1>

        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php elseif (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group">
                    <label>លេខសម្គាល់បុគ្គលិក <span style="color: red;">*</span></label>
                    <input type="text" name="employee_id" value="<?php echo isset($_POST['employee_id']) ? htmlspecialchars($_POST['employee_id']) : ''; ?>" required>
                </div>
                <div class="form-group">
                    <label>សំណើច្បាប់ឈប់សម្រាក</label>
                    <input type="text" name="leave_request" value="<?php echo isset($_POST['leave_request']) ? htmlspecialchars($_POST['leave_request']) : ''; ?>">
                </div>
                <div class="form-group full-width">
                    <label>កំណត់ហេតុ</label>
                    <textarea name="notes" rows="4"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                </div>
                <div class="form-group">
                    <label>ការមកយឺត</label>
                    <input type="text" name="late_arrival" value="<?php echo isset($_POST['late_arrival']) ? htmlspecialchars($_POST['late_arrival']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>ការចាកចេញមុន</label>
                    <input type="text" name="early_leave" value="<?php echo isset($_POST['early_leave']) ? htmlspecialchars($_POST['early_leave']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>ម៉ោងបន្ថែម</label>
                    <input type="text" name="overtime" value="<?php echo isset($_POST['overtime']) ? htmlspecialchars($_POST['overtime']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>ការកាត់ប្រាក់ខែ</label>
                    <input type="number" step="0.01" name="salary_deduction" value="<?php echo isset($_POST['salary_deduction']) ? htmlspecialchars($_POST['salary_deduction']) : '0.00'; ?>">
                </div>
                <div class="form-group">
                    <label>សមតុល្យច្បាប់ឈប់សម្រាកប្រចាំឆ្នាំ</label>
                    <input type="number" name="annual_leave_balance" value="<?php echo isset($_POST['annual_leave_balance']) ? htmlspecialchars($_POST['annual_leave_balance']) : '0'; ?>">
                </div>
                <div class="form-group">
                    <label>ថ្ងៃខែឆ្នាំកំណើត</label>
                    <input type="date" name="birth_date" value="<?php echo isset($_POST['birth_date']) ? htmlspecialchars($_POST['birth_date']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>ថ្ងៃចាប់ផ្តើមធ្វើការ</label>
                    <input type="date" name="start_date" value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>លេខអត្តសញ្ញាណប័ណ្ណ</label>
                    <input type="text" name="id_card_number" value="<?php echo isset($_POST['id_card_number']) ? htmlspecialchars($_POST['id_card_number']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>ថ្ងៃបញ្ចប់កិច្ចសន្យា</label>
                    <input type="date" name="contract_end_date" value="<?php echo isset($_POST['contract_end_date']) ? htmlspecialchars($_POST['contract_end_date']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>ភេទ</label>
                    <select name="gender">
                        <option value="">ជ្រើសរើសភេទ</option>
                        <option value="បុរស" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'បុរស') ? 'selected' : ''; ?>>បុរស</option>
                        <option value="ស្ត្រី" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'ស្ត្រី') ? 'selected' : ''; ?>>ស្ត្រី</option>
                        <option value="ផ្សេងទៀត" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'ផ្សេងទៀត') ? 'selected' : ''; ?>>ផ្សេងទៀត</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>ខែស្វែងរក</label>
                    <input type="month" name="search_month" value="<?php echo isset($_POST['search_month']) ? htmlspecialchars($_POST['search_month']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>រូបថត</label>
                    <input type="file" name="photo" accept="image/*">
                </div>
                <div class="form-group">
                    <label>វិញ្ញាបនបត្រ</label>
                    <input type="text" name="certificate" value="<?php echo isset($_POST['certificate']) ? htmlspecialchars($_POST['certificate']) : ''; ?>" placeholder="បញ្ចូលវិញ្ញាបនបត្រ">
                </div>
                <div class="form-group full-width">
                    <button type="submit" name="add_submit" class="save-btn">រក្សាទុក</button>
                </div>
            </div>
        </form>
    </div>
</body>
</html>