<?php
session_start();

// Only allow admins to access this page
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || $_SESSION['user_type'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Database configuration
$db_host = 'localhost';
$db_user = 'samann1_scan_logs_worker_db';
$db_pass = 'scan_logs_worker_db@2025';
$db_name = 'samann1_scan_logs_worker_db';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("ការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យបរាជ័យ: " . $e->getMessage());
}

// Handle Signup (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $new_username = filter_input(INPUT_POST, 'new_username', FILTER_SANITIZE_STRING);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $profile_pic = 'default_profile.png'; // Default value

    // Log incoming POST data for debugging
    error_log("POST Data: " . print_r($_POST, true));
    error_log("FILES Data: " . print_r($_FILES, true));

    // Validate inputs
    if (empty($new_username) || empty($new_password) || empty($confirm_password)) {
        $signup_error = "សូមបំពេញគ្រប់វាលទាំងអស់";
    } elseif ($new_password !== $confirm_password) {
        $signup_error = "ពាក្យសម្ងាត់មិនផ្គូផ្គង";
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = :username");
        $stmt->execute(['username' => $new_username]);
        $admin_exists = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sub_users WHERE username = :username");
        $stmt->execute(['username' => $new_username]);
        $sub_user_exists = $stmt->fetchColumn();
        
        if ($admin_exists > 0 || $sub_user_exists > 0) {
            $signup_error = "ឈ្មោះអ្នកប្រើនេះត្រូវបានប្រើរួចហើយ";
        } else {
            // Handle profile picture upload
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['profile_pic']['tmp_name'];
                $fileName = $_FILES['profile_pic']['name'];
                $fileSize = $_FILES['profile_pic']['size'];
                $fileType = $_FILES['profile_pic']['type'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));

                // Allowed file extensions and size limit
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
                $maxFileSize = 2 * 1024 * 1024; // 2MB

                if (!in_array($fileExtension, $allowedExts)) {
                    $signup_error = "ប្រភេទឯកសារមិនត្រឹមត្រូវ (ទទួលយក JPG, PNG, GIF តែប៉ុណ្ណោះ)";
                } elseif ($fileSize > $maxFileSize) {
                    $signup_error = "ទំហំឯកសារធំជាង 2MB";
                } else {
                    $uploadDir = 'images/profiles/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $newFileName = $new_username . '_' . time() . '.' . $fileExtension;
                    $destPath = $uploadDir . $newFileName;

                    if (move_uploaded_file($fileTmpPath, $destPath)) {
                        $profile_pic = $newFileName;
                    } else {
                        error_log("File upload failed: Unable to move $fileTmpPath to $destPath");
                        $signup_error = "មានបញ្ហាក្នុងការផ្ទុកឡើងរូបភាព";
                    }
                }
            } elseif ($_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
                $signup_error = "កំហុសក្នុងការផ្ទុកឡើងរូបភាព: Error Code " . $_FILES['profile_pic']['error'];
                error_log("File upload error: " . $_FILES['profile_pic']['error']);
            }

            // If no errors, proceed with account creation
            if (!isset($signup_error)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO admins (username, password, profile_pic) VALUES (:username, :password, :profile_pic)");
                $stmt->execute([
                    'username' => $new_username,
                    'password' => $hashed_password,
                    'profile_pic' => $profile_pic
                ]);

                // Set session variables for the newly created admin
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $pdo->lastInsertId();
                $_SESSION['user_type'] = 'admin';
                $_SESSION['admin_username'] = $new_username;
                $_SESSION['admin_profile_pic'] = $profile_pic;

                // Redirect to Panel.php
                header("Location: Panel.php");
                exit;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>បង្កើតគណនី Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Battambang&display=swap" rel="stylesheet">
    <link rel="icon" href="https://i.ibb.co/qFs02VWq/Logo-Van-Van-1.png" type="image/gif" sizes="16x16">
    <style>
        /* Same styles as before */
        body {
            font-family: 'Battambang', sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .signup-container {
            max-width: 420px;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
        }
        .signup-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(to bottom right, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0));
            transform: rotate(30deg);
            pointer-events: none;
        }
        h2 { color: #2a5298; font-weight: 700; margin-bottom: 2rem; }
        .form-control { border: none; border-bottom: 2px solid #e0e0e0; border-radius: 0; padding: 0.8rem 0.5rem; background: transparent; transition: border-color 0.3s ease; }
        .form-control:focus { border-bottom-color: #2a5298; box-shadow: none; }
        .form-label { color: #555; font-size: 1.1rem; }
        .btn-primary { background: linear-gradient(45deg, #2a5298, #1e3c72); border: none; padding: 0.8rem; border-radius: 25px; font-weight: 600; transition: transform 0.2s ease; }
        .btn-primary:hover { transform: translateY(-2px); background: linear-gradient(45deg, #1e3c72, #2a5298); }
        .alert-danger { border-radius: 8px; background: rgba(220, 53, 69, 0.1); border: none; color: #dc3545; }
        .back-link { display: block; text-align: center; margin-top: 1rem; color: #2a5298; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .form-control-file { border: 1px solid #e0e0e0; padding: 0.5rem; border-radius: 5px; }
        .form-text { font-size: 0.9rem; color: #777; }
    </style>
</head>
<body>
    <div class="signup-container">
        <h2 class="text-center">បង្កើតគណនី Admin</h2>

        <!-- Signup Form -->
        <?php if (isset($signup_error)): ?>
            <div class="alert alert-danger"><?php echo $signup_error; ?></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="signup" value="1">
            <div class="mb-4">
                <label class="form-label">ឈ្មោះអ្នកប្រើ</label>
                <input type="text" name="new_username" class="form-control" required>
            </div>
            <div class="mb-4">
                <label class="form-label">ពាក្យសម្ងាត់</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>
            <div class="mb-4">
                <label class="form-label">បញ្ជាក់ពាក្យសម្ងាត់</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <div class="mb-4">
                <label class="form-label">រូបភាពប្រវត្តិរូប</label>
                <input type="file" name="profile_pic" class="form-control form-control-file" accept="image/*">
                <small class="form-text">ទទួលយក JPG, PNG, GIF (អតិបរមា 2MB)</small>
            </div>
            <button type="submit" class="btn btn-primary w-100">ចុះឈ្មោះ</button>
        </form>
        <a href="Panel.php" class="back-link">ត្រឡប់ទៅផ្ទាំងគ្រប់គ្រង</a>
    </div>
</body>
</html>