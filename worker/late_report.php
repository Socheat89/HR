<?php
// Start session with secure settings
ini_set('session.gc_maxlifetime', 3600); // 1 hour session timeout
session_set_cookie_params(3600);
session_start();

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Redirect to login if not authenticated
$logged_in = false;
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $logged_in = true;
    $user_type = $_SESSION['user_type'] ?? 'admin';
} elseif (isset($_SESSION['sub_user_logged_in']) && $_SESSION['sub_user_logged_in'] === true) {
    $logged_in = true;
    $user_type = $_SESSION['user_type'] ?? 'sub_user';
}

if (!$logged_in) {
    header("Location: admin_login.php");
    exit;
}

// User info from session
$admin_username = $_SESSION['admin_username'] ?? 'User';
$admin_profile_pic = $_SESSION['admin_profile_pic'] ?? 'default_profile.png';

// Close session to prevent locking during AJAX requests
session_write_close();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Database connection (credentials should be in .env or config file)
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    // Adjust query for sub-users if applicable
    $whereClause = "";
    $params = [];
    if ($user_type === 'sub_user' && isset($_SESSION['branch'])) {
        $whereClause = " WHERE branch = :branch";
        $params[':branch'] = $_SESSION['branch'];
    }

    // Pagination setup
    $limit = 100;
    $page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
    $offset = ($page - 1) * $limit;

    // Total logs count
    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM scan_logs" . $whereClause);
    $totalStmt->execute($params);
    $totalLogs = $totalStmt->fetchColumn();
    $totalPages = ceil($totalLogs / $limit);

    // Status counts for statistics
    $statusStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM scan_logs" . $whereClause . " GROUP BY status");
    $statusStmt->execute($params);
    $statusCounts = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Fetch distinct folders
    $folderStmt = $pdo->prepare("SELECT DISTINCT folder FROM scan_logs" . $whereClause . " WHERE folder IS NOT NULL AND folder != '' ORDER BY folder");
    $folderStmt->execute($params);
    $folders = $folderStmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch user status counts
    $userStatusStmt = $pdo->prepare("SELECT username, status, COUNT(*) as count 
                                   FROM scan_logs" . $whereClause . " 
                                   WHERE username IS NOT NULL 
                                   GROUP BY username, status");
    $userStatusStmt->execute($params);
    $userStatuses = [];
    while ($row = $userStatusStmt->fetch(PDO::FETCH_ASSOC)) {
        $username = $row['username'];
        $status = $row['status'];
        if (!isset($userStatuses[$username])) {
            $userStatuses[$username] = ['Good' => 0, 'Late' => 0];
        }
        $userStatuses[$username][$status] = $row['count'];
    }

    // Fetch branches and usernames by folder
    $branchesByFolder = [];
    $usernamesByBranchFolder = [];
    foreach ($folders as $folder) {
        $branchQuery = "SELECT DISTINCT branch FROM scan_logs 
                       WHERE folder = :folder AND branch IS NOT NULL AND branch != ''";
        if ($user_type === 'sub_user' && isset($_SESSION['branch'])) {
            $branchQuery .= " AND branch = :branch";
        }
        $branchQuery .= " ORDER BY branch";
        $branchStmt = $pdo->prepare($branchQuery);
        $branchStmt->bindValue(':folder', $folder, PDO::PARAM_STR);
        if ($user_type === 'sub_user' && isset($_SESSION['branch'])) {
            $branchStmt->bindValue(':branch', $_SESSION['branch'], PDO::PARAM_STR);
        }
        $branchStmt->execute();
        $branchesByFolder[$folder] = $branchStmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($branchesByFolder[$folder] as $branch) {
            $usernameQuery = "SELECT DISTINCT username FROM scan_logs 
                            WHERE folder = :folder AND branch = :branch 
                            AND username IS NOT NULL AND username != '' 
                            ORDER BY username";
            $usernameStmt = $pdo->prepare($usernameQuery);
            $usernameStmt->bindValue(':folder', $folder, PDO::PARAM_STR);
            $usernameStmt->bindValue(':branch', $branch, PDO::PARAM_STR);
            $usernameStmt->execute();
            $usernamesByBranchFolder[$folder][$branch] = $usernameStmt->fetchAll(PDO::FETCH_COLUMN);
        }
    }

    // Pagination function
    function generatePagination($currentPage, $totalPages, $baseUrl) {
        $range = 2;
        $html = '<nav><ul class="pagination">';
        
        $prevPage = $currentPage > 1 ? $currentPage - 1 : 1;
        $html .= '<li class="page-item' . ($currentPage == 1 ? ' disabled' : '') . '">';
        $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . $prevPage . '" data-page="' . $prevPage . '">« មុន</a></li>';

        $start = max(1, $currentPage - $range);
        $end = min($totalPages, $currentPage + $range);

        if ($start > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=1" data-page="1">1</a></li>';
            if ($start > 2) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }

        for ($i = $start; $i <= $end; $i++) {
            $html .= '<li class="page-item' . ($i == $currentPage ? ' active' : '') . '">';
            $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . $i . '" data-page="' . $i . '">' . $i . '</a></li>';
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $totalPages . '" data-page="' . $totalPages . '">' . $totalPages . '</a></li>';
        }

        $nextPage = $currentPage < $totalPages ? $currentPage + 1 : $totalPages;
        $html .= '<li class="page-item' . ($currentPage == $totalPages ? ' disabled' : '') . '">';
        $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . $nextPage . '" data-page="' . $nextPage . '">បន្ទាប់ »</a></li>';

        $html .= '</ul></nav>';
        return $html;
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("មានបញ្ហាក្នុងការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ផ្ទាំងគ្រប់គ្រង - ប្រវត្តិស្កេន</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Battambang&display=swap" rel="stylesheet" id="googleFontCDN">
    <link rel="icon" href="https://i.ibb.co/qFs02VWq/Logo-Van-Van-1.png" type="image/gif" sizes="16x16">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="Panel.css">
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&family=Koulen&family=Noto+Sans+Khmer:wght@100..900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        if (!document.getElementById('googleFontCDN').sheet) {
            document.write('<link rel="stylesheet" href="/local/battambang.css">');
            console.warn('Falling back to local Battambang font.');
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet" id="fontAwesomeCDN">
    <script>
        if (!document.getElementById('fontAwesomeCDN').sheet) {
            document.write('<link rel="stylesheet" href="/local/font-awesome.min.css">');
            console.warn('Falling back to local Font Awesome.');
        }
        if (typeof Chart === 'undefined') {
            document.write('<script src="/local/chart.min.js"><\/script>');
            console.warn('Falling back to local Chart.js');
        }
    </script>
</head>
<body>
    <div id="notification MariaDB [10.2.34] started
notification-container" class="notification-container"></div>
    <div class="container">
        <!-- Admin Header -->
        <div class="admin-header shadow-sm">
            <div class="d-flex align-items-center gap-3">
                <img src="images/profiles/<?php echo htmlspecialchars($admin_profile_pic); ?>"
                     alt="Profile"
                     class="user-profile-pic rounded-circle"
                     style="width: 48px; height: 48px; object-fit: cover; border: 2.5px solid #fff;">
                <div>
                    <div class="user-name" style="font-size: 1.15rem; font-weight: 600;">
                        <?php echo htmlspecialchars($admin_username); ?>
                    </div>
                    <div style="font-size: 0.95rem; color: #e0e0e0;">
                        <?php echo $user_type === 'admin' ? 'អ្នកគ្រប់គ្រង' : 'អ្នកប្រើរង'; ?>
                    </div>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="admin_logout.php" class="btn btn-outline-light btn-sm fw-bold px-3" style="border-radius: 7px;">
                    <i class="fa-solid fa-right-from-bracket me-1"></i> ចាកចេញ
                </a>
            </div>
        </div>

        <!-- Statistics Dashboard -->
        <div class="stats-container" id="stats_dashboard" style="background: #f8faff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); padding: 25px 20px 20px 20px; margin-bottom: 30px;">
            <h2 style="font-size: 1.35rem; font-weight: bold; color: #007bff; margin-bottom: 18px; letter-spacing: 0.5px;">
                <i class="fa-solid fa-chart-column me-2"></i>
                ស្ថិតិទិន្នន័យស្កេន
            </h2>
            <div class="stats-grid" style="gap: 18px;">
                <div class="stat-box" style="background: #e3f0ff; border: 1.5px solid #b6d4fe; border-radius: 8px;">
                    <strong style="color: #0056b3;">សរុបស្កេន</strong>
                    <span class="stat-number" style="font-size: 1.5rem; color: #007bff;"><?php echo $totalLogs; ?></span>
                </div>
                <?php foreach ($statusCounts as $status => $count): ?>
                    <div class="stat-box" style="background: #f6f8fa; border: 1.5px solid #e0e0; border-radius: 8px;">
                        <strong style="color: #495057;"><?php echo htmlspecialchars($status ?: 'មិនមានស្ថានភាព'); ?></strong>
                        <span class="stat-number" style="font-size: 1.3rem; color: #007bff;"><?php echo $count; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="chart-container" style="max-width: 350px; margin: 25px auto 0 auto; background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); padding: 18px;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <!-- Export and Map Buttons -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <button id="export_csv" class="btn btn-success btn-sm">
                    <i class="fa-solid fa-download"></i> ទាញយក Excel
                </button>
                <a href="view_map.php" class="btn btn-primary btn-sm ms-2" target="_blank">
                    <i class="fa-solid fa-map"></i> មើលផែនទី
                </a>
                <a href="late_report.php" class="btn btn-warning btn-sm ms-2" target="_blank">
                    <i class="fa-solid fa-table-list"></i> មើលតារាងមកយឺត
                </a>
                <a href="employee_late_report.php" class="btn btn-info btn-sm ms-2" target="_blank">
                    <i class="fa-solid fa-users"></i> មើលរបាយការណ៍បុគ្គលិកមកយឺត
                </a>
            </div>
        </div>

        <!-- Search Form (Accordion for Folder/Branch/Username) -->
        <div class="search-form d-none">
            <div class="row g-3 align-items-end">
                <div class="col-md-12">
                    <label class="form-label fw-bold" style="color: #007bff; font-size: 1.1rem;">ជ្រើសរើសប្រភេទជំនាញ</label>
                    <div class="accordion" id="folderAccordion" style="margin-top: 15px; border: none; background: transparent;">
                        <?php foreach ($folders as $folderIndex => $folder): ?>
                            <div class="accordion-item shadow-sm" style="margin-bottom: 12px; border-radius: 8px; border: none; overflow: hidden;">
                                <h2 class="accordion-header" id="headingFolder<?php echo $folderIndex; ?>">
                                    <button class="accordion-button <?php echo $folderIndex === 0 ? '' : 'collapsed'; ?>" 
                                            type="button" 
                                            data-bs-toggle="collapse" 
                                            data-bs-target="#collapseFolder<?php echo $folderIndex; ?>" 
                                            aria-expanded="<?php echo $folderIndex === 0 ? 'true' : 'false'; ?>" 
                                            aria-controls="collapseFolder<?php echo $folderIndex; ?>"
                                            style="background: linear-gradient(90deg, #007bff 70%, #0056b3 100%); color: #fff; font-weight: bold; font-size: 1.05rem;">
                                        <i class="fa-solid fa-folder-open me-2"></i>
                                        <?php echo htmlspecialchars($folder); ?>
                                    </button>
                                </h2>
                                <div id="collapseFolder<?php echo $folderIndex; ?>" 
                                     class="accordion-collapse collapse <?php echo $folderIndex === 0 ? 'show' : ''; ?>" 
                                     aria-labelledby="headingFolder<?php echo $folderIndex; ?>" 
                                     data-bs-parent="#folderAccordion">
                                    <div class="accordion-body" style="background: #f8faff; border-radius: 0 0 8px 8px; padding: 18px 18px 10px 18px;">
                                        <div class="accordion" id="branchAccordion<?php echo $folderIndex; ?>">
                                            <?php foreach ($branchesByFolder[$folder] as $branchIndex => $branch): ?>
                                                <div class="accordion-item" style="margin-bottom: 10px; border-radius: 7px; border: none; overflow: hidden;">
                                                    <h2 class="accordion-header" id="headingBranch<?php echo $folderIndex . $branchIndex; ?>">
                                                        <button class="accordion-button branch-toggle <?php echo $branchIndex === 0 ? '' : 'collapsed'; ?>" 
                                                                type="button" 
                                                                data-bs-toggle="collapse" 
                                                                data-bs-target="#collapseBranch<?php echo $folderIndex . $branchIndex; ?>" 
                                                                aria-expanded="<?php echo $branchIndex === 0 ? 'true' : 'false'; ?>" 
                                                                aria-controls="collapseBranch<?php echo $folderIndex . $branchIndex; ?>" 
                                                                data-folder="<?php echo htmlspecialchars($folder); ?>" 
                                                                data-branch="<?php echo htmlspecialchars($branch); ?>"
                                                                style="background: linear-gradient(90deg, #6c757d 70%, #495057 100%); color: #fff; font-weight: 500; font-size: 1rem;">
                                                            <input type="checkbox" class="branch-checkbox" checked style="margin-right: 7px; accent-color: #007bff;">
                                                            <i class="fa-solid fa-code-branch me-2"></i>
                                                            <?php echo htmlspecialchars($branch); ?>
                                                        </button>
                                                    </h2>
                                                    <div id="collapseBranch<?php echo $folderIndex . $branchIndex; ?>" 
                                                         class="accordion-collapse collapse <?php echo $branchIndex === 0 ? 'show' : ''; ?>" 
                                                         aria-labelledby="headingBranch<?php echo $folderIndex . $branchIndex; ?>" 
                                                         data-bs-parent="#branchAccordion<?php echo $folderIndex; ?>">
                                                        <div class="accordion-body username-checkboxes" style="background: #f4f6fb; border-radius: 0 0 7px 7px; padding: 12px 15px;">
                                                            <label class="select-all" style="font-weight: bold; color: #007bff; cursor: pointer; margin-bottom: 8px;">
                                                                <input type="checkbox" class="selectBranchAll" 
                                                                       data-folder="<?php echo htmlspecialchars($folder); ?>"
                                                                       data-branch="<?php echo htmlspecialchars($branch); ?>" checked style="accent-color: #007bff;">
                                                                <i class="fa-solid fa-users me-1"></i>
                                                                -- ជ្រើសរើសទាំងអស់នៅ <?php echo htmlspecialchars($branch); ?> --
                                                            </label>
                                                            <div class="username-search-container" style="margin: 10px 0 12px 0;">
                                                                <input type="text" class="form-control username-search" 
                                                                       placeholder="ស្វែងរកឈ្មោះ..." 
                                                                       data-folder="<?php echo htmlspecialchars($folder); ?>"
                                                                       data-branch="<?php echo htmlspecialchars($branch); ?>"
                                                                       style="border: 1.5px solid #007bff; border-radius: 6px; padding: 6px 10px; font-size: 0.97rem;">
                                                            </div>
                                                            <div class="username-list" style="max-height: 180px; overflow-y: auto;">
                                                                <?php foreach ($usernamesByBranchFolder[$folder][$branch] as $username): ?>
                                                                    <label class="username-item" style="display: block; margin-bottom: 6px; cursor: pointer; font-size: 0.97rem;">
                                                                        <input type="checkbox" name="username_filter[]" 
                                                                               value="<?php echo htmlspecialchars($username); ?>" 
                                                                               data-folder="<?php echo htmlspecialchars($folder); ?>"
                                                                               data-branch="<?php echo htmlspecialchars($branch); ?>" 
                                                                               checked style="accent-color: #007bff;">
                                                                        <i class="fa-regular fa-user me-1"></i>
                                                                        <?php echo htmlspecialchars($username); ?>
                                                                    </label>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Date Range Filter -->
        <div class="row g-3 align-items-end mb-3" style="margin-top: 10px;">
            <div class="col-md-3">
                <label class="form-label fw-bold" style="color: #007bff;">ចាប់ផ្តើមពីថ្ងៃ</label>
                <input type="date" id="start_date" class="form-control shadow-sm" style="border-radius: 8px; border: 1.5px solid #007bff;">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold" style="color: #007bff;">រហូតដល់ថ្ងៃ</label>
                <input type="date" id="end_date" class="form-control shadow-sm" style="border-radius: 8px; border: 1.5px solid #007bff;">
            </div>
            <div class="col-md-2 d-flex gap-2 align-items-end">
                <button id="search_button" class="btn btn-primary fw-bold px-4 shadow-sm" style="border-radius: 8px;">
                    <i class="fa fa-search me-1"></i> ស្វែងរក
                </button>
                <button id="clear_filter" class="btn btn-secondary fw-bold px-4 shadow-sm" style="border-radius: 8px;">
                    <i class="fa fa-eraser me-1"></i> សម្អាត
                </button>
            </div>
        </div>

        <!-- Folder and Username Filters -->
        <div class="row mb-3">
            <div class="col-md-3">
                <label for="filter_folder" class迄今

System: <xaiArtifact artifact_id="e1cec75c-4e46-4497-8427-23329e6121ed" artifact_version_id="db795e54-28d0-49c4-bdce-8b562a3752ea" title="late_report.php" contentType="text/php">
<?php
// Start session with secure settings
ini_set('session.gc_maxlifetime', 3600);
session_set_cookie_params(3600);
session_start();

// Redirect to login if not authenticated
$logged_in = false;
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $logged_in = true;
    $user_type = $_SESSION['user_type'] ?? 'admin';
} elseif (isset($_SESSION['sub_user_logged_in']) && $_SESSION['sub_user_logged_in'] === true) {
    $logged_in = true;
    $user_type = $_SESSION['user_type'] ?? 'sub_user';
}

if (!$logged_in) {
    header("Location: admin_login.php");
    exit;
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    // Adjust query for sub-users
    $whereClause = "";
    $params = [];
    if ($user_type === 'sub_user' && isset($_SESSION['branch'])) {
        $whereClause = " WHERE branch = :branch";
        $params[':branch'] = $_SESSION['branch'];
    }

    // Fetch employee data and late statistics
    $query = "SELECT DISTINCT username, gender, role FROM scan_logs" . $whereClause;
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch late statistics
    $lateStats = [];
    foreach ($employees as $employee) {
        $username = $employee['username'];
        $lateQuery = "SELECT 
            SUM(CASE WHEN status = 'Late' AND TIMESTAMPDIFF(MINUTE, expected_time, scan_time) < 15 THEN 1 ELSE 0 END) AS under_15,
            SUM(CASE WHEN status = 'Late' AND TIMESTAMPDIFF(MINUTE, expected_time, scan_time) BETWEEN 15 AND 60 THEN 1 ELSE 0 END) AS between_15_60,
            SUM(CASE WHEN status = 'Late' AND TIMESTAMPDIFF(MINUTE, expected_time, scan_time) > 60 THEN 1 ELSE 0 END) AS over_60
            FROM scan_logs 
            WHERE username = :username" . $whereClause;
        $stmt = $pdo->prepare($lateQuery);
        $stmt->bindValue(':username', $username, PDO::PARAM_STR);
        $stmt->execute($params);
        $lateStats[$username] = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Calculate totals
    $totalUnder15 = array_sum(array_column($lateStats, 'under_15'));
    $totalBetween15_60 = array_sum(array_column($lateStats, 'between_15_60'));
    $totalOver60 = array_sum(array_column($lateStats, 'over_60'));
    $totalLate = $totalUnder15 + $totalBetween15_60 + $totalOver60;
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("មានបញ្ហាក្នុងការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ: " . htmlspecialchars($e->getMessage()));
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>របាយការណ៍បុគ្គលិកមកយឺត</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Battambang&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Battambang', sans-serif;
        }
        .table-responsive {
            margin-top: 20px;
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .summary-table {
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h2 class="mb-4">របាយការណ៍បុគ្គលិកមកយឺត</h2>

        <!-- Employee Details Table -->
        <div class="table-responsive">
            <table class="table table-striped table-bordered">
                <thead class="table-primary">
                    <tr>
                        <th>អត្តលេខ</th>
                        <th>ឈ្មោះ</th>
                        <th>ភេទ</th>
                        <th>តួនាទី</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $index => $employee): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($employee['username']); ?></td>
                            <td><?php echo htmlspecialchars($employee['gender']); ?></td>
                            <td><?php echo htmlspecialchars($employee['role']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Late Statistics Table -->
        <div class="table-responsive">
            <h3 class="mt-4">ស្ថិតិមកយឺត</h3>
            <table class="table table-striped table-bordered">
                <thead class="table-warning">
                    <tr>
                        <th>ឈ្មោះ</th>
                        <th>ក្រោម ១៥ នាទី</th>
                        <th>ចាប់ពី ១៥ នាទី ដល់ ១ ម៉ោង</th>
                        <th>លើសពី ១ ម៉ោង</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lateStats as $username => $stats): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($username); ?></td>
                            <td><?php echo $stats['under_15']; ?></td>
                            <td><?php echo $stats['between_15_60']; ?></td>
                            <td><?php echo $stats['over_60']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Summary Table -->
        <div class="table-responsive summary-table">
            <h3 class="mt-4">សរុបទិន្ន័យ</h3>
            <table class="table table-striped table-bordered">
                <thead class="table-info">
                    <tr>
                        <th>ប្រភេទ</th>
                        <th>ចំនួន</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>ក្រោម ១៥ នាទី</td>
                        <td><?php echo $totalUnder15; ?></td>
                    </tr>
                    <tr>
                        <td>ចាប់ពី ១៥ នាទី ដល់ ១ ម៉ោង</td>
                        <td><?php echo $totalBetween15_60; ?></td>
                    </tr>
                    <tr>
                        <td>លើសពី ១ ម៉ោង</td>
                        <td><?php echo $totalOver60; ?></td>
                    </tr>
                    <tr>
                        <td>សរុបមកយឺត</td>
                        <td><?php echo $totalLate; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>