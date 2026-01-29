<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || $_SESSION['user_type'] !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
$type = $_GET['type'] ?? '';
$table = $type === 'admin' ? 'admins' : 'sub_users';

if (!$id || !in_array($type, ['admin', 'sub_user'])) {
    header("Location: manage_users.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM $table WHERE id = :id");
$stmt->execute(['id' => $id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: manage_users.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password'] ?? '';
    $branch = $type === 'sub_user' ? ($_POST['branch'] ?? null) : null;
    $profile_pic = $user['profile_pic'];

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
            $newFileName = $username . '_' . time() . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $profile_pic = $newFileName;
            }
        }
    }

    if ($password) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE $table SET username = :username, password = :password, profile_pic = :profile_pic" . ($type === 'sub_user' ? ', branch = :branch' : '') . " WHERE id = :id");
        $params = ['username' => $username, 'password' => $hashed_password, 'profile_pic' => $profile_pic, 'id' => $id];
        if ($type === 'sub_user') $params['branch'] = $branch;
    } else {
        $stmt = $pdo->prepare("UPDATE $table SET username = :username, profile_pic = :profile_pic" . ($type === 'sub_user' ? ', branch = :branch' : '') . " WHERE id = :id");
        $params = ['username' => $username, 'profile_pic' => $profile_pic, 'id' => $id];
        if ($type === 'sub_user') $params['branch'] = $branch;
    }
    $stmt->execute($params);

    header("Location: manage_users.php");
    exit;
}

$branches = $pdo->query("SELECT DISTINCT branch FROM scan_logs WHERE branch IS NOT NULL AND branch != '' ORDER BY branch")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>កែអ្នកប្រើ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Battambang&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Battambang', sans-serif; background-color: #f8f9fa; padding: 20px; }
        .container { max-width: 600px; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container">
        <h2>កែអ្នកប្រើ: <?php echo htmlspecialchars($user['username']); ?></h2>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">ឈ្មោះអ្នកប្រើ</label>
                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">ពាក្យសម្ងាត់ថ្មី (ទុកចោលបើមិនចង់ផ្លាស់ប្តូរ)</label>
                <input type="password" name="password" class="form-control">
            </div>
            <?php if ($type === 'sub_user'): ?>
                <div class="mb-3">
                    <label class="form-label">សាខា</label>
                    <select name="branch" class="form-select">
                        <option value="">-- ជ្រើសរើសសាខា --</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo htmlspecialchars($branch); ?>" <?php echo $user['branch'] === $branch ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label">រូបភាពប្រវត្តិរូប</label>
                <input type="file" name="profile_pic" class="form-control" accept="image/*">
                <img src="images/profiles/<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Current Profile" style="width: 50px; margin-top: 10px;">
            </div>
            <button type="submit" class="btn btn-primary">រក្សាទុក</button>
            <a href="manage_users.php" class="btn btn-secondary">ត្រឡប់ក្រោយ</a>
        </form>
    </div>
</body>
</html>