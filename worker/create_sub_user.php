<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

$db_host = 'localhost';
$db_user = 'samann1_scan_logs_worker_db';
$db_pass = 'scan_logs_worker_db@2025';
$db_name = 'samann1_scan_logs_worker_db';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $branch = filter_input(INPUT_POST, 'branch', FILTER_SANITIZE_STRING) ?: null;
    $permissions = filter_input(INPUT_POST, 'permissions', FILTER_SANITIZE_STRING);
    $admin_id = $_SESSION['admin_id'];

    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sub_users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        if ($stmt->fetchColumn() > 0) {
            $error = "ឈ្មោះអ្នកប្រើនេះត្រូវបានប្រើរួចហើយ";
        } else {
            $stmt = $pdo->prepare("INSERT INTO sub_users (admin_id, username, password, branch, permissions) 
                                   VALUES (:admin_id, :username, :password, :branch, :permissions)");
            $stmt->execute([
                ':admin_id' => $admin_id,
                ':username' => $username,
                ':password' => $password,
                ':branch' => $branch,
                ':permissions' => $permissions
            ]);
            header("Location: manage_sub_users.php?success=Sub-user created");
            exit;
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>បង្កើត Sub-User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Battambang&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Battambang', sans-serif; background-color: #f8f9fa; }
        .container { max-width: 500px; margin: 20px auto; }
    </style>
</head>
<body>
    <div class="container">
        <h2>បង្កើត Sub-User ថ្មី</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label>ឈ្មោះអ្នកប្រើ</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>លេខសម្ងាត់</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label>សាខា (ស្រេចចិត្ត)</label>
                <input type="text" name="branch" class="form-control">
            </div>
            <div class="mb-3">
                <label>សិទ្ធិ</label>
                <select name="permissions" class="form-control">
                    <option value="read">អានតែប៉ុណ្ណោះ</option>
                    <option value="read_write">អាន និងកែ</option>
                    <option value="full">សិទ្ធិពេញលេញ</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">បង្កើត</button>
            <a href="manage_sub_users.php" class="btn btn-secondary">ត្រលប់</a>
        </form>
    </div>
</body>
</html>