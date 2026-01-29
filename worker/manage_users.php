<?php
session_start();

// Redirect to login if not authenticated as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || $_SESSION['user_type'] !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

// User info from session
$admin_username = $_SESSION['admin_username'] ?? 'Admin';
$admin_profile_pic = $_SESSION['admin_profile_pic'] ?? 'default_profile.png';

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("មានបញ្ហាក្នុងការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ: " . $e->getMessage());
}

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $new_username = filter_input(INPUT_POST, 'new_username', FILTER_SANITIZE_STRING);
    $new_password = $_POST['new_password'] ?? '';
    $user_type = $_POST['user_type'] ?? 'sub_user';
    $branch = $_POST['branch'] ?? null;
    $profile_pic = 'default_profile.png';

    if (empty($new_username) || empty($new_password)) {
        $message = "សូមបំពេញគ្រប់វាលទាំងអស់";
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = :username UNION SELECT COUNT(*) FROM sub_users WHERE username = :username");
        $stmt->execute(['username' => $new_username]);
        if (array_sum($stmt->fetchAll(PDO::FETCH_COLUMN)) > 0) {
            $message = "ឈ្មោះអ្នកប្រើនេះត្រូវបានប្រើរួចហើយ";
        } else {
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['profile_pic']['tmp_name'];
                $fileName = $_FILES['profile_pic']['name'];
                $fileSize = $_FILES['profile_pic']['size'];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
                $maxFileSize = 2 * 1024 * 1024;

                if (in_array($fileExtension, $allowedExts) && $fileSize <= $maxFileSize) {
                    $uploadDir = 'images/profiles/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $newFileName = $new_username . '_' . time() . '.' . $fileExtension;
                    $destPath = $uploadDir . $newFileName;
                    if (move_uploaded_file($fileTmpPath, $destPath)) {
                        $profile_pic = $newFileName;
                    }
                }
            }

            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            if ($user_type === 'admin') {
                $stmt = $pdo->prepare("INSERT INTO admins (username, password, profile_pic) VALUES (:username, :password, :profile_pic)");
                $stmt->execute(['username' => $new_username, 'password' => $hashed_password, 'profile_pic' => $profile_pic]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO sub_users (username, password, profile_pic, branch) VALUES (:username, :password, :profile_pic, :branch)");
                $stmt->execute(['username' => $new_username, 'password' => $hashed_password, 'profile_pic' => $profile_pic, 'branch' => $branch]);
            }
            $message = "បានបង្កើតអ្នកប្រើថ្មីដោយជោគជ័យ";
        }
    }
}

// Handle user deletion
if (isset($_GET['delete_id']) && isset($_GET['type'])) {
    $id = filter_var($_GET['delete_id'], FILTER_VALIDATE_INT);
    $type = $_GET['type'];
    if ($id && in_array($type, ['admin', 'sub_user'])) {
        $table = $type === 'admin' ? 'admins' : 'sub_users';
        $stmt = $pdo->prepare("DELETE FROM $table WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $message = "បានលុបអ្នកប្រើដោយជោគជ័យ";
        header("Location: manage_users.php");
        exit;
    }
}

// Fetch all users
$admins = $pdo->query("SELECT id, username, profile_pic, 'admin' as user_type FROM admins")->fetchAll(PDO::FETCH_ASSOC);
$sub_users = $pdo->query("SELECT id, username, profile_pic, branch, 'sub_user' as user_type FROM sub_users")->fetchAll(PDO::FETCH_ASSOC);
$all_users = array_merge($admins, $sub_users);

// Fetch distinct branches for sub-user creation
$branches = $pdo->query("SELECT DISTINCT branch FROM scan_logs WHERE branch IS NOT NULL AND branch != '' ORDER BY branch")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>គ្រប់គ្រងអ្នកប្រើ - ប្រព័ន្ធស្កេនវត្តមាន</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Battambang&display=swap" rel="stylesheet">
    <link rel="icon" href="https://i.ibb.co/qFs02VWq/Logo-Van-Van-1.png" type="image/gif" sizes="16x16">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Battambang', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .admin-header {
            background-color: #007bff;
            color: white;
            padding: 15px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .user-info img {
            border: 2px solid white;
            border-radius: 50%;
        }
        .table-responsive {
            margin-top: 20px;
        }
        .btn-sm {
            transition: all 0.3s ease;
        }
        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .form-container {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .message {
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1 class="mb-0">គ្រប់គ្រងអ្នកប្រើ</h1>
            <div class="user-info d-flex align-items-center">
                <img src="images/profiles/<?php echo htmlspecialchars($admin_profile_pic); ?>" 
                     alt="Profile" 
                     class="me-2" 
                     style="width: 40px; height: 40px; object-fit: cover;">
                <span class="me-3"><?php echo htmlspecialchars($admin_username); ?></span>
                <a href="admin_logout.php" class="btn btn-light btn-sm">
                    <i class="fa-solid fa-right-from-bracket"></i> ចាកចេញ
                </a>
                <a href="Panel.php" class="btn btn-light btn-sm ms-2">
                    <i class="fa-solid fa-arrow-left"></i> ត្រឡប់ទៅផ្ទាំងគ្រប់គ្រង
                </a>
            </div>
        </div>

        <?php if (isset($message)): ?>
            <div class="message <?php echo strpos($message, 'ជោគជ័យ') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Create User Form -->
        <div class="form-container">
            <h3>បង្កើតអ្នកប្រើថ្មី</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="create_user" value="1">
                <div class="mb-3">
                    <label class="form-label">ឈ្មោះអ្នកប្រើ</label>
                    <input type="text" name="new_username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">ពាក្យសម្ងាត់</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">ប្រភេទអ្នកប្រើ</label>
                    <select name="user_type" class="form-select">
                        <option value="admin">Admin</option>
                        <option value="sub_user" selected>Sub-User</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">សាខា (សម្រាប់ Sub-User)</label>
                    <select name="branch" class="form-select">
                        <option value="">-- ជ្រើសរើសសាខា --</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo htmlspecialchars($branch); ?>"><?php echo htmlspecialchars($branch); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">រូបភាពប្រវត្តិរូប</label>
                    <input type="file" name="profile_pic" class="form-control" accept="image/*">
                    <small class="form-text text-muted">ទទួលយក JPG, PNG, GIF (អតិបរមា 2MB)</small>
                </div>
                <button type="submit" class="btn btn-primary">បង្កើតអ្នកប្រើ</button>
            </form>
        </div>

        <!-- User List -->
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="table-primary">
                    <tr>
                        <th>ID</th>
                        <th>ឈ្មោះអ្នកប្រើ</th>
                        <th>ប្រភេទអ្នកប្រើ</th>
                        <th>សាខា</th>
                        <th>រូបភាព</th>
                        <th>សកម្មភាព</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_users)): ?>
                        <tr>
                            <td colspan="6" class="text-center">មិនមានអ្នកប្រើទេ!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($all_users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['user_type'] === 'admin' ? 'Admin' : 'Sub-User'); ?></td>
                                <td><?php echo htmlspecialchars($user['branch'] ?? 'N/A'); ?></td>
                                <td>
                                    <img src="images/profiles/<?php echo htmlspecialchars($user['profile_pic']); ?>" 
                                         alt="Profile" 
                                         style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%;">
                                </td>
                                <td>
                                    <a href="edit_user.php?id=<?php echo htmlspecialchars($user['id']); ?>&type=<?php echo htmlspecialchars($user['user_type']); ?>" 
                                       class="btn btn-warning btn-sm">
                                        <i class="fa-solid fa-edit"></i> កែ
                                    </a>
                                    <a href="manage_users.php?delete_id=<?php echo htmlspecialchars($user['id']); ?>&type=<?php echo htmlspecialchars($user['user_type']); ?>" 
                                       class="btn btn-danger btn-sm" 
                                       onclick="return confirm('តើអ្នកប្រាកដជាចង់លុបអ្នកប្រើនេះមែនទេ?')">
                                        <i class="fa-solid fa-trash"></i> លុប
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>