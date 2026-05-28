<?php
session_start();
include '../system/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'សូមចូលប្រព័ន្ធជាមុន!';
    header('Location: ../auth/login.php');
    exit;
}

// Check if user is admin
try {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || $user['role'] !== 'admin') {
        $_SESSION['error'] = 'អ្នកមិនមានសិទ្ធិកែប្រែទិន្នន័យបុគ្គលិកទេ!';
        header('Location: ../system/table.php');
        exit;
    }
    error_log('../system/form_input.php - user_id: ' . $_SESSION['user_id'] . ', role: ' . $user['role']);
} catch (PDOException $e) {
    $_SESSION['error'] = 'មិនអាចទាញទិន្នន័យអ្នកប្រើប្រាស់បានទេ: ' . $e->getMessage();
    error_log('Error fetching user role in form_input.php: ' . $e->getMessage());
    header('Location: ../system/table.php');
    exit;
}

// Initialize session messages
$_SESSION['error'] = $_SESSION['error'] ?? '';
$_SESSION['success'] = $_SESSION['success'] ?? '';

// Generate CSRF token
$_SESSION['csrf_token'] = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));

// Fetch users for the name dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM users ORDER BY name");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log('../system/form_input.php - Fetched ' . count($users) . ' users for dropdown');
} catch (PDOException $e) {
    $_SESSION['error'] = 'មិនអាចទាញឈ្មោះអ្នកប្រើបានទេ: ' . $e->getMessage();
    error_log('Error fetching users in form_input.php: ' . $e->getMessage());
    $users = [];
}

// Load existing employee data if employee_id is provided
$employee = null;
$employee_id = filter_var($_GET['employee_id'] ?? null, FILTER_VALIDATE_INT);
if ($employee_id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$employee_id]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$employee) {
            $_SESSION['error'] = 'រកមិនឃើញបុគ្គលិកជាមួយលេខសម្គាល់នេះទេ!';
            header('Location: ../system/table.php');
            exit;
        }
        error_log('../system/form_input.php - Loaded employee data for id: ' . $employee_id);
    } catch (PDOException $e) {
        $_SESSION['error'] = 'មិនអាចទាញទិន្នន័យបុគ្គលិកបានទេ: ' . $e->getMessage();
        error_log('Error fetching employee in form_input.php: ' . $e->getMessage());
        header('Location: ../system/table.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $employee ? 'កែប្រែទិន្នន័យបុគ្គលិក' : 'បញ្ចូលទិន្នន័យបុគ្គលិកថ្មី'; ?></title>
    <style>
        body {
            font-family: 'Khmer OS', Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333333;
            background-color: #F8F8F8;
        }
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: #FFFFFF;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #FFD700;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        }
        .form-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #FFD700;
        }
        .form-header h1 {
            font-size: 1.8rem;
            color: #D4A017;
            margin: 0;
        }
        .form-header p {
            font-size: 1rem;
            color: #333333;
            margin: 5px 0;
        }
        .form-section {
            margin-bottom: 20px;
            padding: 15px;
            background: #F8F8F8;
            border-radius: 8px;
        }
        .form-section h3 {
            color: #D4A017;
            border-bottom: 1px solid #FFD700;
            padding-bottom: 5px;
            margin: 0 0 10px;
        }
        .form-row {
            display: flex;
            margin-bottom: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .form-label {
            font-weight: bold;
            width: 40%;
            color: #333333;
            padding-right: 10px;
        }
        .form-input {
            width: 60%;
        }
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #FFD700;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 0.9rem;
            background-color: #FFFFFF;
        }
        .form-control:focus {
            outline: none;
            border-color: #D4A017;
            box-shadow: 0 0 5px rgba(212, 160, 23, 0.3);
        }
        .form-control:invalid {
            border-color: #FF6347;
        }
        .submit-btn, .cancel-btn {
            color: #FFFFFF;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin: 5px;
        }
        .submit-btn {
            background-color: #FFD700;
        }
        .submit-btn:hover {
            background-color: #D4A017;
            transform: scale(1.05);
        }
        .cancel-btn {
            background-color: #FF6347;
        }
        .cancel-btn:hover {
            background-color: #D43F2A;
            transform: scale(1.05);
        }
        .alert {
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 6px;
            font-size: 1rem;
        }
        .alert-success {
            background-color: #FFD700;
            color: #333333;
        }
        .alert-error {
            background-color: #FF6347;
            color: #FFFFFF;
        }
        @media (max-width: 600px) {
            .form-container {
                padding: 15px;
            }
            .form-row {
                flex-direction: column;
            }
            .form-label, .form-input {
                width: 100%;
                padding-right: 0;
            }
            .form-label {
                margin-bottom: 5px;
            }
            .form-control {
                font-size: 0.85rem;
            }
            .submit-btn, .cancel-btn {
                width: 100%;
                padding: 10px;
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <?php if ($_SESSION['success']): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if ($_SESSION['error']): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <div class="form-header">
            <h1><?php echo $employee ? 'កែប្រែទិន្នន័យបុគ្គលិក' : 'បញ្ចូលទិន្នន័យបុគ្គលិកថ្មី'; ?></h1>
            <p>សូមបំពេញព័ត៌មានទាំងអស់ខាងក្រោម</p>
        </div>

        <form method="POST" enctype="multipart/form-data" id="employee-form" action="../requests/save_request.php" onsubmit="return validateForm()">
            <input type="hidden" name="section" value="personal">
            <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($employee['id'] ?? ''); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <!-- Personal Information -->
            <div class="form-section">
                <h3>ព័ត៌មានផ្ទាល់ខ្លួន</h3>
                <div class="form-row">
                    <div class="form-label">ឈ្មោះពេញ:</div>
                    <div class="form-input">
                        <select name="name" class="form-control" required>
                            <option value="" disabled <?php echo !$employee ? 'selected' : ''; ?>>ជ្រើសរើសឈ្មោះ</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['name']); ?>" <?php echo ($employee && $employee['name'] === $user['name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-label">ភេទ:</div>
                    <div class="form-input">
                        <select name="gender" class="form-control" required>
                            <option value="" disabled <?php echo !$employee ? 'selected' : ''; ?>>ជ្រើសរើសភេទ</option>
                            <option value="male" <?php echo ($employee && $employee['gender'] === 'male') ? 'selected' : ''; ?>>ប្រុស</option>
                            <option value="female" <?php echo ($employee && $employee['gender'] === 'female') ? 'selected' : ''; ?>>ស្រី</option>
                            <option value="other" <?php echo ($employee && $employee['gender'] === 'other') ? 'selected' : ''; ?>>ផ្សេងទៀត</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-label">ថ្ងៃខែឆ្នាំកំណើត:</div>
                    <div class="form-input">
                        <input type="date" name="dob" class="form-control" value="<?php echo htmlspecialchars($employee['dob'] ?? ''); ?>" required max="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-label">អ៊ីមែល:</div>
                    <div class="form-input">
                        <input type="email" name="email" class="form-control" placeholder="បញ្ចូលអ៊ីមែល" value="<?php echo htmlspecialchars($employee['email'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-label">លេខទូរស័ព្ទ:</div>
                    <div class="form-input">
                        <input type="text" name="phone" class="form-control" placeholder="ឧ. +85512345678" value="<?php echo htmlspecialchars($employee['phone'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-label">រូបភាព:</div>
                    <div class="form-input">
                        <input type="file" name="profile_pic" class="form-control" accept="image/jpeg,image/png,image/gif">
                        <?php if ($employee && $employee['profile_pic']): ?>
                            <p>រូបភាពបច្ចុប្បន្ន: <img src="Uploads/profiles/<?php echo htmlspecialchars($employee['profile_pic']); ?>" alt="Profile Picture" style="width: 50px; height: 50px; border-radius: 50%; border: 2px solid #FFD700;"></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Work Information -->
            <div class="form-section">
                <h3>ព័ត៌មានការងារ</h3>
                <div class="form-row">
                    <div class="form-label">តួនាទី:</div>
                    <div class="form-input">
                        <input type="text" name="position" class="form-control" placeholder="បញ្ចូលតួនាទី" value="<?php echo htmlspecialchars($employee['position'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-label">ផ្នែក:</div>
                    <div class="form-input">
                        <input type="text" name="department" class="form-control" placeholder="បញ្ចូលផ្នែក" value="<?php echo htmlspecialchars($employee['department'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-label">ថ្ងៃចូលធ្វើការ:</div>
                    <div class="form-input">
                        <input type="date" name="join_date" class="form-control" value="<?php echo htmlspecialchars($employee['join_date'] ?? ''); ?>" required max="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-label">លេខបុគ្គលិក:</div>
                    <div class="form-input">
                        <input type="text" name="employee_code" class="form-control" placeholder="បញ្ចូលលេខបុគ្គលិក" value="<?php echo htmlspecialchars($employee['employee_code'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <!-- HR Statistics -->
            <div class="form-section">
                <h3>ស្ថិតិពី HR</h3>
                <div class="form-row">
                    <div class="form-label">ច្បាប់បានប្រើ (ឆ្នាំនេះ):</div>
                    <div class="form-input">
                        <input type="number" name="leave_taken" class="form-control" value="<?php echo htmlspecialchars($employee['leave_taken'] ?? '0'); ?>" min="0" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-label">ច្បាប់នៅសល់ (ថ្ងៃ):</div>
                    <div class="form-input">
                        <input type="number" name="leave_left" class="form-control" value="<?php echo htmlspecialchars($employee['leave_left'] ?? '9'); ?>" min="0" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-label">ភ្លេចស្កេនមេដៃ:</div>
                    <div class="form-input">
                        <input type="number" name="fingerprint_miss" class="form-control" value="<?php echo htmlspecialchars($employee['fingerprint_miss'] ?? '0'); ?>" min="0" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-label">យឺត (ឆ្នាំនេះ):</div>
                    <div class="form-input">
                        <input type="number" name="late_count" class="form-control" value="<?php echo htmlspecialchars($employee['late_count'] ?? '0'); ?>" min="0" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-label">ប្រាក់ដែលត្រូវកាត់ ($):</div>
                    <div class="form-input">
                        <input type="number" name="salary_cut" class="form-control" value="<?php echo htmlspecialchars($employee['salary_cut'] ?? '0'); ?>" min="0" step="0.01" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <button type="submit" class="submit-btn"><?php echo $employee ? 'ធ្វើបច្ចុប្បន្នភាព' : 'រក្សាទុក'; ?></button>
                <a href="../system/table.php" class="cancel-btn">បោះបង់</a>
            </div>
        </form>
    </div>

    <script>
        function validateForm() {
            const form = document.getElementById('employee-form');
            const email = form.querySelector('input[name="email"]');
            const phone = form.querySelector('input[name="phone"]');
            const dob = form.querySelector('input[name="dob"]');
            const joinDate = form.querySelector('input[name="join_date"]');
            const numberInputs = form.querySelectorAll('input[type="number"]');
            const emailPattern = /^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/;
            const phonePattern = /^\+855[0-9]{8,9}$/;
            const today = new Date().toISOString().split('T')[0];

            // Validate email format
            if (!emailPattern.test(email.value)) {
                alert('សូមបញ្ចូលអ៊ីមែលអោយត្រឹមត្រូវ!');
                email.focus();
                return false;
            }

            // Validate phone format
            if (!phonePattern.test(phone.value)) {
                alert('សូមបញ្ចូលលេខទូរស័ព្ទអោយត្រឹមត្រូវ (ឧ. +85512345678)!');
                phone.focus();
                return false;
            }

            // Validate date of birth (not in the future)
            if (dob.value && dob.value > today) {
                alert('ថ្ងៃខែឆ្នាំកំណើតមិនអាចជាអនាគតទេ!');
                dob.focus();
                return false;
            }

            // Validate join date (not in the future)
            if (joinDate.value && joinDate.value > today) {
                alert('ថ្ងៃចូលធ្វើការមិនអាចជាអនាគតទេ!');
                joinDate.focus();
                return false;
            }

            // Validate non-negative numbers
            for (let input of numberInputs) {
                if (input.value < 0) {
                    alert('សូមបញ្ចូលតម្លៃមិនអាចជាអវិជ្ជមាន!');
                    input.focus();
                    return false;
                }
            }

            // Confirm submission
            return confirm('តើអ្នកប្រាកដថាចង់រក្សាទុកទិន្នន័យនេះមែនទេ?');
        }
    </script>
</body>
</html>