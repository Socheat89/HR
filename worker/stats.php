<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) && !isset($_SESSION['sub_user_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$total_scans = $pdo->query("SELECT COUNT(*) FROM scan_logs")->fetchColumn();
$late_scans = $pdo->query("SELECT COUNT(*) FROM scan_logs WHERE status = 'Late'")->fetchColumn();
$late_percentage = $total_scans > 0 ? ($late_scans / $total_scans) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ស្ថិតិស្កេនទាំងអស់</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .sidebar { padding: 15px; background-color: #f8f9fa; border-radius: 5px; }
        .folder-items { padding-left: 15px; }
        .folder-item { display: block; }
        .nav-link { color: #333; }
        .nav-link:hover { background-color: #e9ecef; }
        .nav-link.active { background-color: #007bff; color: white !important; }
        .stats-container { display: flex; gap: 15px; margin-bottom: 20px; }
        .stat-box { background-color: #f1f1f1; padding: 10px; border-radius: 5px; text-align: center; }
        .stat-number { display: block; font-size: 1.5em; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Navigation Menu -->
            <div class="col-md-3">
                <div class="sidebar">
                    <div class="search-folder mb-3">
                        <input type="text" id="folder_search" class="form-control form-control-sm" placeholder="ស្វែងរកថត...">
                    </div>
                    <ul class="nav nav-pills flex-column" id="folder_menu">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">ផ្ទាំង</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="data.php">ទិន្នន័យ</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="report.php">របាយការណ៍</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="stats.php">ស្ថិតិស្កេនទាំងអស់</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_folders.php">គ្រប់គ្រងថត</a>
                        </li>
                        <li class="nav-item folder-list">
                            <h6>ថតឯកសារ</h6>
                            <ul class="nav flex-column folder-items">
                                <?php
                                $folders = $pdo->query("SELECT DISTINCT folder FROM scan_logs")->fetchAll(PDO::FETCH_COLUMN);
                                foreach ($folders as $folder) {
                                    echo "<li class='nav-item folder-item'>
                                            <a class='nav-link' href='index.php?folder=" . urlencode($folder) . "'>" . htmlspecialchars($folder) . "</a>
                                          </li>";
                                }
                                ?>
                            </ul>
                        </li>
                        <li class="nav-item mt-3">
                            <a class="nav-link text-danger" href="logout.php">ចាកចេញ</a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <h1>ស្ថិតិស្កេនទាំងអស់</h1>
                <div class="stats-container">
                    <div class="stat-box">
                        <strong>សរុបស្កេន</strong>
                        <span class="stat-number"><?php echo $total_scans; ?></span>
                    </div>
                    <div class="stat-box">
                        <strong>ចំនួនយឺត</strong>
                        <span class="stat-number"><?php echo $late_scans; ?></span>
                    </div>
                    <div class="stat-box">
                        <strong>ភាគរយនៃការយឺត</strong>
                        <span class="stat-number"><?php echo round($late_percentage, 2); ?>%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('folder_search').addEventListener('input', function() {
        const searchTerm = this.value.trim().toLowerCase();
        const folderItems = document.querySelectorAll('.folder-item');

        folderItems.forEach(item => {
            const folderName = item.textContent.trim().toLowerCase();
            if (folderName.includes(searchTerm)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>