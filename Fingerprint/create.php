<?php
require 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_name = $_POST['user_name'] ?? '';
    $employee_id = $_POST['employee_id'] ?? '';
    $department = $_POST['department'] ?? '';
    $position = $_POST['position'] ?? '';
    $branch = $_POST['branch'] ?? '';

    if (empty($user_name) || empty($employee_id) || empty($department) || empty($position) || empty($branch)) {
        $error = "សូមបំពេញគ្រប់វាលទាំងអស់!";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (user_name, employee_id, department, position, branch) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_name, $employee_id, $department, $position, $branch]);
            header("Location: index.php?success=បានបន្ថែមអ្នកប្រើប្រាស់ជោគជ័យ");
            exit;
        } catch (PDOException $e) {
            $error = "កំហុស: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>បន្ថែមអ្នកប្រើប្រាស់</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container mt-5">
        <h1 class="text-center mb-4">បន្ថែមអ្នកប្រើប្រាស់ថ្មី</h1>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label for="user_name" class="form-label">ឈ្មោះ</label>
                <input type="text" class="form-control" id="user_name" name="user_name" required>
            </div>
            <div class="mb-3">
                <label for="employee_id" class="form-label">លេខសម្គាល់</label>
                <input type="text" class="form-control" id="employee_id" name="employee_id" required>
            </div>
            <div class="mb-3">
                <label for="department" class="form-label">នាយកដ្ឋាន</label>
                <input type="text" class="form-control" id="department" name="department" required>
            </div>
            <div class="mb-3">
                <label for="position" class="form-label">តួនាទី</label>
                <input type="text" class="form-control" id="position" name="position" required>
            </div>
            <div class="mb-3">
                <label for="branch" class="form-label">សាខា</label>
                <input type="text" class="form-control" id="branch" name="branch" required>
            </div>
            <button type="submit" class="btn btn-success">បន្ថែម</button>
            <a href="index.php" class="btn btn-secondary">ត្រឡប់ក្រោយ</a>
        </form>
    </div>
</body>
</html>