<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'សូមចូលប្រព័ន្ធជាមុន!';
    header('Location: login.php');
    exit;
}

// Check if user is admin
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || $user['role'] !== 'admin') {
    $_SESSION['error'] = 'អ្នកមិនមានសិទ្ធិកែប្រែទិន្នន័យបុគ្គលិកទេ!';
    header('Location: table.php');
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = 'សកម្មភាពមិនត្រឹមត្រូវ (CSRF token មិនត្រូវគ្នា)!';
    header('Location: table.php');
    exit;
}

// Process form data (example)
try {
    $employee_id = filter_var($_POST['employee_id'] ?? null, FILTER_VALIDATE_INT);
    $name = $_POST['name'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $position = $_POST['position'] ?? '';
    $department = $_POST['department'] ?? '';
    $join_date = $_POST['join_date'] ?? '';
    $employee_code = $_POST['employee_code'] ?? '';
    $leave_taken = filter_var($_POST['leave_taken'] ?? 0, FILTER_VALIDATE_INT);
    $leave_left = filter_var($_POST['leave_left'] ?? 0, FILTER_VALIDATE_INT);
    $fingerprint_miss = filter_var($_POST['fingerprint_miss'] ?? 0, FILTER_VALIDATE_INT);
    $late_count = filter_var($_POST['late_count'] ?? 0, FILTER_VALIDATE_INT);
    $salary_cut = filter_var($_POST['salary_cut'] ?? 0, FILTER_VALIDATE_FLOAT);

    // Handle file upload for profile picture
    $profile_pic = $employee['profile_pic'] ?? null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'Uploads/profiles/';
        $file_name = uniqid() . '_' . basename($_FILES['profile_pic']['name']);
        $upload_path = $upload_dir . $file_name;
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
            $profile_pic = $file_name;
        } else {
            $_SESSION['error'] = 'មិនអាចបង្ហោះរូបភាពបានទេ!';
            header('Location: form_input.php?employee_id=' . $employee_id);
            exit;
        }
    }

    if ($employee_id) {
        // Update existing employee
        $stmt = $pdo->prepare("
            UPDATE employees SET
                name = ?, gender = ?, dob = ?, email = ?, phone = ?,
                profile_pic = ?, position = ?, department = ?, join_date = ?,
                employee_code = ?, leave_taken = ?, leave_left = ?,
                fingerprint_miss = ?, late_count = ?, salary_cut = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $name, $gender, $dob, $email, $phone,
            $profile_pic, $position, $department, $join_date,
            $employee_code, $leave_taken, $leave_left,
            $fingerprint_miss, $late_count, $salary_cut, $employee_id
        ]);
        $_SESSION['success'] = 'ធ្វើបច្ចុប្បន្នភាពបុគ្គលិកជោគជ័យ!';
    } else {
        // Insert new employee
        $stmt = $pdo->prepare("
            INSERT INTO employees (
                name, gender, dob, email, phone, profile_pic,
                position, department, join_date, employee_code,
                leave_taken, leave_left, fingerprint_miss, late_count, salary_cut
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name, $gender, $dob, $email, $phone,
            $profile_pic, $position, $department, $join_date,
            $employee_code, $leave_taken, $leave_left,
            $fingerprint_miss, $late_count, $salary_cut
        ]);
        $_SESSION['success'] = 'បញ្ចូលបុគ្គលិកថ្មីជោគជ័យ!';
    }
    header('Location: table.php');
    exit;
} catch (PDOException $e) {
    $_SESSION['error'] = 'មិនអាចរក្សាទុកទិន្នន័យបុគ្គលិកបានទេ: ' . $e->getMessage();
    error_log('Error saving employee in save_request.php: ' . $e->getMessage());
    header('Location: form_input.php?employee_id=' . $employee_id);
    exit;
}
?>