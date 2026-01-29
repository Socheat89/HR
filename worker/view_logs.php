<?php
session_start();

// ====================================================
// Security check: only admin can access
// ====================================================
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Database connection
$db_host = 'localhost';
$db_name = 'samann1_scan_logs_worker_db';
$db_user = 'samann1_scan_logs_worker_db';
$db_pass = 'scan_logs_worker_db@2025';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Filter by date
$filter_date = isset($_GET['filter_date']) && !empty($_GET['filter_date']) ? $_GET['filter_date'] : '';

// Pagination setup
$records_per_page = 50;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $records_per_page;

// Count total records with optional date filter
$count_sql = "SELECT COUNT(*) FROM audit_log";
$params = [];
if ($filter_date) {
    $count_sql .= " WHERE DATE(deleted_at) = ?";
    $params[] = $filter_date;
}
$total_stmt = $pdo->prepare($count_sql);
$total_stmt->execute($params);
$total_records = $total_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Fetch audit log records with optional date filter
$sql = "SELECT * FROM audit_log";
if ($filter_date) {
    $sql .= " WHERE DATE(deleted_at) = ?";
}
$sql .= " ORDER BY deleted_at DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);

$bind_index = 1;
if ($filter_date) {
    $stmt->bindValue($bind_index++, $filter_date);
}
$stmt->bindValue($bind_index++, $records_per_page, PDO::PARAM_INT);
$stmt->bindValue($bind_index++, $offset, PDO::PARAM_INT);

$stmt->execute();
$logs = $stmt->fetchAll();

function displayValue($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>View Audit Log</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
table th, table td { font-size: 14px; }
</style>
</head>
<body class="p-4">

<div class="container">
    <h2 class="mb-4">Audit Log</h2>

    <!-- Filter by Date -->
    <form method="get" class="mb-3 row g-2 align-items-center">
        <div class="col-auto">
            <label for="filter_date" class="col-form-label">Filter by Date:</label>
        </div>
        <div class="col-auto">
            <input type="date" id="filter_date" name="filter_date" class="form-control" value="<?= displayValue($filter_date) ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="view_audit_log.php" class="btn btn-secondary">Reset</a>
        </div>
    </form>

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Action</th>
                <th>Table Name</th>
                <th>Record ID</th>
                <th>Admin ID</th>
                <th>Admin Name</th>
                <th>Deleted At</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="8" class="text-center">No audit logs found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= displayValue($log['id']) ?></td>
                        <td><?= displayValue($log['user']) ?></td>
                        <td><?= displayValue($log['action']) ?></td>
                        <td><?= displayValue($log['table_name']) ?></td>
                        <td><?= displayValue($log['record_id']) ?></td>
                        <td><?= displayValue($log['app_user_id'] ?? '') ?></td>
                        <td><?= displayValue($log['app_user_name'] ?? '') ?></td>
                        <td><?= displayValue($log['deleted_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav>
            <ul class="pagination">
                <?php for ($i=1; $i<=$total_pages; $i++): ?>
                    <li class="page-item <?= ($i==$current_page) ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?><?= $filter_date ? '&filter_date=' . $filter_date : '' ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

</body>
</html>
