<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) && !isset($_SESSION['sub_user_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// បន្ថែម Folder
if (isset($_POST['add_folder'])) {
    $folder_name = trim($_POST['folder_name']);
    if (!empty($folder_name)) {
        $stmt = $pdo->prepare("INSERT INTO folders (folder_name) VALUES (:folder_name)");
        $stmt->execute([':folder_name' => $folder_name]);
    }
}

// លុប Folder
if (isset($_GET['delete_folder'])) {
    $folder_name = $_GET['delete_folder'];
    $stmt = $pdo->prepare("DELETE FROM folders WHERE folder_name = :folder_name");
    $stmt->execute([':folder_name' => $folder_name]);
    $stmt = $pdo->prepare("UPDATE scan_logs SET folder = 'default' WHERE folder = :folder");
    $stmt->execute([':folder' => $folder_name]);
}

// ស្រង់ Folder ទាំងអស់
$folders = $pdo->query("SELECT DISTINCT folder_name FROM folders")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>គ្រប់គ្រងថត</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>គ្រប់គ្រងថតឯកសារ</h1>
        <form method="POST" class="mb-3">
            <div class="input-group">
                <input type="text" name="folder_name" class="form-control" placeholder="បញ្ចូលឈ្មោះថត" required>
                <button type="submit" name="add_folder" class="btn btn-primary">បន្ថែម</button>
            </div>
        </form>
        <table class="table">
            <thead>
                <tr>
                    <th>ឈ្មោះថត</th>
                    <th>សកម្មភាព</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($folders as $folder): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($folder); ?></td>
                        <td>
                            <a href="manage_folders.php?delete_folder=<?php echo urlencode($folder); ?>" 
                               class="btn btn-danger btn-sm" 
                               onclick="return confirm('ប្រាកដជាលុបថតនេះមែនទេ?')">លុប</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>