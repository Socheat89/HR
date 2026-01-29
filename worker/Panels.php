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
$user_type = '';
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $logged_in = true;
    $user_type = 'admin';
} elseif (isset($_SESSION['sub_user_logged_in']) && $_SESSION['sub_user_logged_in'] === true) {
    $logged_in = true;
    $user_type = 'sub_user';
}

if (!$logged_in) {
    header("Location: admin_login.php");
    exit;
}

// User info from session
$admin_username = $_SESSION['admin_username'] ?? 'User';
$admin_profile_pic = $_SESSION['admin_profile_pic'] ?? 'default_profile.png';

// Get allowed branches for the sub-user from the session
$allowed_branches_for_user = [];
if ($user_type === 'sub_user' && isset($_SESSION['allowed_branches'])) {
    $allowed_branches_for_user = is_array($_SESSION['allowed_branches']) ? $_SESSION['allowed_branches'] : [];
}

// Close session to prevent locking during AJAX requests
session_write_close();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to build the final WHERE clause string from an array of conditions
function build_where_clause(array $conditions): string {
    if (empty($conditions)) {
        return "";
    }
    return " WHERE " . implode(" AND ", $conditions);
}

try {
    // Database connection
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    // ===================================================================
    // === START: DYNAMIC WHERE CLAUSE BUILDER (កែសម្រួលនៅទីនេះ) ===
    // ===================================================================
    $base_conditions = [];
    $params = [];

    // If it's a sub-user, add a condition to filter by their allowed branches
    if ($user_type === 'sub_user' && !empty($allowed_branches_for_user)) {
        $placeholders = implode(',', array_fill(0, count($allowed_branches_for_user), '?'));
        $base_conditions[] = "branch IN ($placeholders)";
        foreach ($allowed_branches_for_user as $branch) {
            $params[] = $branch;
        }
    }
    // =================================================================
    // === END: DYNAMIC WHERE CLAUSE BUILDER ===
    // =================================================================


    // Pagination setup
    $limit = 100;
    $page = max(1, filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1]]));
    $offset = ($page - 1) * $limit;

    // Build the base WHERE clause for general queries
    $whereClause = build_where_clause($base_conditions);

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
    $folder_conditions = $base_conditions;
    $folder_conditions[] = "folder IS NOT NULL";
    $folder_conditions[] = "folder != ''";
    $folder_where_clause = build_where_clause($folder_conditions);
    $folderStmt = $pdo->prepare("SELECT DISTINCT folder FROM scan_logs" . $folder_where_clause . " ORDER BY folder");
    $folderStmt->execute($params);
    $folders = $folderStmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch user status counts
    $user_status_conditions = $base_conditions;
    $user_status_conditions[] = "username IS NOT NULL";
    $user_status_where_clause = build_where_clause($user_status_conditions);
    $userStatusStmt = $pdo->prepare("SELECT username, status, COUNT(*) as count 
                                   FROM scan_logs" . $user_status_where_clause . " 
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

    // Fetch branches and usernames by folder (with filtering for sub-users)
    $branchesByFolder = [];
    $usernamesByBranchFolder = [];
    foreach ($folders as $folder) {
        // Build conditions to get all branches in the current folder
        $branch_fetch_conditions = $base_conditions;
        $branch_fetch_conditions[] = "folder = ?";
        $branch_fetch_params = $params;
        $branch_fetch_params[] = $folder;

        $branch_where_clause = build_where_clause($branch_fetch_conditions);
        
        $branchStmt = $pdo->prepare("SELECT DISTINCT branch FROM scan_logs " . $branch_where_clause . " AND branch IS NOT NULL AND branch != '' ORDER BY branch");
        $branchStmt->execute($branch_fetch_params);
        $branchesByFolder[$folder] = $branchStmt->fetchAll(PDO::FETCH_COLUMN);

        // Now, get usernames for the filtered list of branches
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
        $html .= '<li class="page-item' . ($currentPage == 1 ? ' disabled' : '') . '"><a class="page-link" href="' . $baseUrl . '?page=' . $prevPage . '" data-page="' . $prevPage . '">« មុន</a></li>';
        $start = max(1, $currentPage - $range);
        $end = min($totalPages, $currentPage + $range);
        if ($start > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=1" data-page="1">1</a></li>';
            if ($start > 2) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        for ($i = $start; $i <= $end; $i++) {
            $html .= '<li class="page-item' . ($i == $currentPage ? ' active' : '') . '"><a class="page-link" href="' . $baseUrl . '?page=' . $i . '" data-page="' . $i . '">' . $i . '</a></li>';
        }
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $totalPages . '" data-page="' . $totalPages . '">' . $totalPages . '</a></li>';
        }
        $nextPage = $currentPage < $totalPages ? $currentPage + 1 : $totalPages;
        $html .= '<li class="page-item' . ($currentPage == $totalPages ? ' disabled' : '') . '"><a class="page-link" href="' . $baseUrl . '?page=' . $nextPage . '" data-page="' . $nextPage . '">បន្ទាប់ »</a></li>';
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://fonts.googleapis.com/css2?family=Battambang&display=swap" rel="stylesheet" id="googleFontCDN">
  <link rel="icon" href="https://i.ibb.co/qFs02VWq/Logo-Van-Van-1.png" type="image/gif" sizes="16x16">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="Panel.css">
  <link
    href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&family=Koulen&family=Noto+Sans+Khmer:wght@100..900&display=swap"
    rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
  <script>
    if (!document.getElementById('googleFontCDN').sheet) {
      document.write('<link rel="stylesheet" href="/local/battambang.css">');
      console.warn('Falling back to local Battambang font.');
    }
  </script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet"
    id="fontAwesomeCDN">
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
  <div id="notification-container" class="notification-container"></div>
  <div class="container">
    <!-- Admin Header -->
    <div class="admin-header shadow-sm">
      <div class="d-flex align-items-center gap-3">
        <img src="images/profiles/<?php echo htmlspecialchars($admin_profile_pic); ?>" alt="Profile"
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
    <div class="stats-container" id="stats_dashboard"
      style="background: #f8faff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); padding: 25px 20px 20px 20px; margin-bottom: 30px;">
      <h2 style="font-size: 1.35rem; font-weight: bold; color: #007bff; margin-bottom: 18px; letter-spacing: 0.5px;">
        <i class="fa-solid fa-chart-column me-2"></i>
        ស្ថិតិទិន្នន័យស្កេន
      </h2>
      <div class="stats-grid" style="gap: 18px;">
        <div class="stat-box" style="background: #e3f0ff; border: 1.5px solid #b6d4fe; border-radius: 8px;">
          <strong style="color: #0056b3;">សរុបស្កេន</strong>
          <span class="stat-number" style="font-size: 1.5rem; color: #007bff;">
            <?php echo $totalLogs; ?>
          </span>
        </div>
        <?php foreach ($statusCounts as $status => $count): ?>
        <div class="stat-box" style="background: #f6f8fa; border: 1.5px solid #e0e0; border-radius: 8px;">
          <strong style="color: #495057;">
            <?php echo htmlspecialchars($status ?: 'មិនមានស្ថានភាព'); ?>
          </strong>
          <span class="stat-number" style="font-size: 1.3rem; color: #007bff;">
            <?php echo $count; ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="chart-container"
        style="max-width: 350px; margin: 25px auto 0 auto; background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); padding: 18px;">
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
        <a href="report_attendance.php" class="btn btn-primary btn-sm ms-2" target="_blank">
          <i class="fa-solid fa-list-ul"></i> បង្កើតរបាយការណ៍វត្តមាន
        </a>
      </div>
    </div>



    <!-- ប្រើ class .custom-accordion ដើម្បីឱ្យ CSS ខាងលើដំណើរការ -->
    <div class="accordion custom-accordion" id="folderUsernameAccordion">
      <?php foreach ($folders as $folder): ?>
      <div class="accordion-item d-none">
        <h2 class="accordion-header" id="heading-<?php echo md5($folder); ?>">
          <div class="accordion-header-custom">
            <button class="accordion-button collapsed flex-grow-1" type="button" data-bs-toggle="collapse"
              data-bs-target="#collapse-<?php echo md5($folder); ?>">
              <i class="fa-solid fa-folder-open me-2"></i>
              <?php echo htmlspecialchars($folder); ?>
            </button>
            <div class="folder-select-all d-flex align-items-center" style="font-size: 18px;">
              <input type="checkbox" class="form-check-input selectFolderAll ms-2"
                id="folder-<?php echo md5($folder); ?>" data-folder="<?php echo htmlspecialchars($folder); ?>" checked>
              <label class="ms-2" for="folder-<?php echo md5($folder); ?>">ទាំងអស់</label>
            </div>
          </div>
        </h2>
        <div id="collapse-<?php echo md5($folder); ?>" class="accordion-collapse collapse"
          data-bs-parent="#folderUsernameAccordion">
          <div class="accordion-body">
            <?php if (!empty($branchesByFolder[$folder])): ?>
            <?php foreach ($branchesByFolder[$folder] as $branch): ?>
            <div class="branch-item" data-folder="<?php echo htmlspecialchars($folder); ?>"
              data-branch="<?php echo htmlspecialchars($branch); ?>">
              <div class="branch-header">
                <strong class="branch-title"><i class="fa-solid fa-store"></i>
                  <?php echo htmlspecialchars($branch); ?>
                </strong>
                <div class="form-check me-3">
                  <input type="checkbox" class="form-check-input selectBranchAll"
                    id="branch-<?php echo md5($branch); ?>" data-folder="<?php echo htmlspecialchars($folder); ?>"
                    data-branch="<?php echo htmlspecialchars($branch); ?>" checked>
                  <label class="form-check-label" for="branch-<?php echo md5($branch); ?>">ទាំងអស់</label>
                </div>
                <input type="text" class="form-control form-control-sm username-search" placeholder="ស្វែងរកឈ្មោះ..."
                  data-folder="<?php echo htmlspecialchars($folder); ?>"
                  data-branch="<?php echo htmlspecialchars($branch); ?>" style="width: 200px;">
              </div>
              <div class="username-list">
                <?php foreach ($usernamesByBranchFolder[$folder][$branch] ?? [] as $username): ?>
                <label class="username-tag">
                  <input type="checkbox" class="form-check-input" name="username_filter[]"
                    value="<?php echo htmlspecialchars($username); ?>"
                    data-folder="<?php echo htmlspecialchars($folder); ?>"
                    data-branch="<?php echo htmlspecialchars($branch); ?>" checked>
                  <span>
                    <?php echo htmlspecialchars($username); ?>
                  </span>
                </label>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="text-muted text-center p-3">មិនមានសាខាសម្រាប់បង្ហាញក្នុងប្រភេទនេះទេ។</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        const folderCheckboxes = document.querySelectorAll('.selectFolderAll');
        folderCheckboxes.forEach(folderCheckbox => {
          folderCheckbox.addEventListener('change', function () {
            const folder = this.dataset.folder;
            const branchToggles = document.querySelectorAll(`.branch-toggle[data-folder="${folder}"]`);
            const branchAllCheckboxes = document.querySelectorAll(`.selectBranchAll[data-folder="${folder}"]`);
            const usernameCheckboxes = document.querySelectorAll(`input[name="username_filter[]"][data-folder="${folder}"]`);
            if (!this.checked) {
              branchToggles.forEach(branchDiv => branchDiv.style.display = 'none');
              branchAllCheckboxes.forEach(cb => cb.checked = false);
              usernameCheckboxes.forEach(cb => cb.checked = false);
            } else {
              branchToggles.forEach(branchDiv => branchDiv.style.display = '');
              branchAllCheckboxes.forEach(cb => cb.checked = true);
              usernameCheckboxes.forEach(cb => cb.checked = true);
            }
          });
        });
      });
    </script>

    <!-- Date Range Filter -->
    <div class="row g-3 align-items-end mb-3" style="margin-top: 10px;">
      <div class="col-md-3">
        <label class="form-label fw-bold" style="color: #007bff;">ចាប់ផ្តើមពីថ្ងៃ</label>
        <input type="date" id="start_date" class="form-control shadow-sm"
          style="border-radius: 8px; border: 1.5px solid #007bff;">
      </div>
      <div class="col-md-3">
        <label class="form-label fw-bold" style="color: #007bff;">រហូតដល់ថ្ងៃ</label>
        <input type="date" id="end_date" class="form-control shadow-sm"
          style="border-radius: 8px; border: 1.5px solid #007bff;">
      </div>
      <!-- ដាក់កូដនេះនៅក្បែរ Filter ផ្សេងៗរបស់អ្នក -->

      <div class="col-md-2">
        <label for="start_time_filter" class="form-label">ចាប់ពីម៉ោង:</label>
        <input type="time" id="start_time_filter" class="form-control">
      </div>
      <div class="col-md-2">
        <label for="end_time_filter" class="form-label">ដល់ម៉ោង:</label>
        <input type="time" id="end_time_filter" class="form-control">
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


    <!-- Staff Type Filter Container -->
    <div class="staff-filter-container">
      <!-- Staff Type Filter Buttons -->
      <div class="mb-3">
        <label class="form-label fw-bold">ជ្រើសរើសប្រភេទបុគ្គលិក៖</label>
        <div id="staff_type_filter_group" class="btn-group w-100" role="group" aria-label="Staff Type Filter">
          <button type="button" class="btn btn-outline-primary active" data-staff-type="skilled">
            <i class="fa-solid fa-user-tie me-2"></i> បុគ្គលិកជំនាញ
          </button>
          <button type="button" class="btn btn-outline-primary" data-staff-type="worker">
            <i class="fa-solid fa-user-cog me-2"></i> បុគ្គលិកកម្មករ
          </button>
        </div>
      </div>
    </div>

    <style>
      /* Style for the filter container */
      .staff-filter-container {
        background-color: #f8f9fa;
        /* Light grey background */
        padding: 20px;
        border-radius: 12px;
        /* Softer rounded corners */
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.07);
        /* Subtle shadow for depth */
        border: 1px solid #e9ecef;
      }

      /* Style for the label */
      .staff-filter-container .form-label {
        color: #0d6efd;
        /* Bootstrap primary color */
        margin-bottom: 1rem;
        /* More space between label and buttons */
        font-size: 1.1rem;
      }

      /* General button styles within the group */
      .staff-filter-container .btn-group .btn {
        padding: 10px 20px;
        font-size: 1rem;
        font-weight: 500;
        border-radius: 8px !important;
        /* Rounded corners for buttons, !important to override Bootstrap */
        transition: all 0.3s ease;
        /* Smooth transition for hover and active states */
        border-width: 1.5px;
      }

      /* Style for the active button */
      .staff-filter-container .btn-group .btn.active {
        background-color: #0d6efd;
        color: white;
        box-shadow: 0 3px 8px rgba(13, 110, 253, 0.4);
        /* Glow effect for active button */
        transform: translateY(-2px);
        /* Lift effect */
      }

      /* Style for inactive buttons on hover */
      .staff-filter-container .btn-group .btn:not(.active):hover {
        background-color: #e7f1ff;
        /* Light blue background on hover */
        color: #0056b3;
        /* Darker blue text on hover */
        transform: translateY(-2px);
      }

      /* Adjust margin between buttons */
      .staff-filter-container .btn-group .btn+.btn {
        margin-left: 10px;
      }

      /* Make btn-group behave better with margin */
      #staff_type_filter_group {
        border: none;
        gap: 10px;
        /* Creates space between buttons */
      }
    </style>

    <!-- Folder and Username Filters -->
    <div class="row mb-3 d-none">
      <div class="col-md-3">
        <label for="filter_folder" class="form-label fw-bold" style="color: #007bff;">ជ្រើសរើសប្រភេទ</label>
        <select id="filter_folder" class="form-select form-select-sm">
          <option value="">-- ទាំងអស់ --</option>
          <?php
                    $selectedFolder = $_GET['folder'] ?? $_COOKIE['selected_folder'] ?? '';
                    foreach ($folders as $folderOption): ?>
          <option value="<?php echo htmlspecialchars($folderOption); ?>" <?php if ($selectedFolder===$folderOption)
            echo 'selected' ; ?>>
            <?php echo htmlspecialchars($folderOption); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label for="filter_username" class="form-label fw-bold"
          style="color: #007bff;">ជ្រើសរើសឈ្មោះបុគ្គលិកតាមប្រភេទ</label>
        <select id="filter_username" class="form-select form-select-sm">
          <option value="">-- ទាំងអស់ --</option>
          <?php
                    if ($selectedFolder) {
                        $stmt = $pdo->prepare("SELECT DISTINCT username FROM scan_logs WHERE folder = :folder AND username IS NOT NULL AND username != '' ORDER BY username");
                        $stmt->execute([':folder' => $selectedFolder]);
                    } else {
                        $stmt = $pdo->query("SELECT DISTINCT username FROM scan_logs WHERE username IS NOT NULL AND username != '' ORDER BY username");
                    }
                    $uniqueUsernames = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $selectedUsername = $_GET['username'] ?? '';
                    foreach ($uniqueUsernames as $username): ?>
          <option value="<?php echo htmlspecialchars($username); ?>" <?php if ($selectedUsername===$username)
            echo 'selected' ; ?>>
            <?php echo htmlspecialchars($username); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Logs Table (Populated via AJAX) -->
    <div class="loading">កំពុងផ្ទុក...</div>
    <div class="table-responsive" id="logs_table">
      <!-- Table will be populated by fetch_logs.php via AJAX -->
    </div>

    <!-- Pagination -->
    <div id="pagination_container">
      <?php echo generatePagination($page, $totalPages, $_SERVER['PHP_SELF']); ?>
    </div>
  </div>

  <!-- Back to Top Button -->
  <button id="back-to-top" class="btn btn-primary btn-sm back-to-top" title="ត្រឡប់ទៅកំពូល">
    <i class="fa-solid fa-arrow-up"></i>
  </button>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>
  <script>
    if (typeof bootstrap === 'undefined') {
      console.warn('Bootstrap JS failed to load from CDN. Attempting to load local fallback.');
      document.write('<script src="/local/bootstrap.bundle.min.js"><\/script>');
    }

    const csrfToken = '<?php echo htmlspecialchars($csrf_token); ?>';

    document.addEventListener('DOMContentLoaded', function () {
      const backToTopButton = document.getElementById('back-to-top');
      const exportButton = document.getElementById('export_csv');
      const startDateInput = document.getElementById('start_date');
      const endDateInput = document.getElementById('end_date');
      const usernameCheckboxes = document.querySelectorAll('input[name="username_filter[]"]');
      const branchToggles = document.querySelectorAll('.branch-toggle');
      const branchCheckboxes = document.querySelectorAll('.branch-checkbox');
      const branchAllCheckboxes = document.querySelectorAll('.selectBranchAll');
      const searchButton = document.getElementById('search_button');
      const clearButton = document.getElementById('clear_filter');
      const loading = document.querySelector('.loading');
      const logsTable = document.getElementById('logs_table');
      const pagination = document.getElementById('pagination_container');
      const statsDashboard = document.getElementById('stats_dashboard');
      let statusChart = null;
      let isFetching = false;
      // START: កូដបន្ថែមថ្មី
      // START: កូដបន្ថែមថ្មី
      const staffTypeButtonGroup = document.getElementById('staff_type_filter_group');
      let currentStaffType = 'skilled'; // កំណត់ 'skilled' ជា Default filter ថ្មី

      // បញ្ជី Folder សម្រាប់ប្រភេទនីមួយៗ
      const skilledFolders = ['ជំនាញ', 'ឃ្លាំង', 'ហាងទំនិញ៣១៨', 'SK Chuk Meas', 'SK Cosmetic'];
      const workerFolders = ['កម្មករ'];
      // END: កូដបន្ថែមថ្មី



      // START: កូដបន្ថែមថ្មី - សម្រាប់គ្រប់គ្រងការចុចប៊ូតុងប្រភេទបុគ្គលិក
      if (staffTypeButtonGroup) {
        staffTypeButtonGroup.addEventListener('click', function (e) {
          const button = e.target.closest('button');
          if (!button) return;

          // Update the current staff type
          currentStaffType = button.dataset.staffType;

          // Update active state for buttons
          staffTypeButtonGroup.querySelectorAll('button').forEach(btn => btn.classList.remove('active'));
          button.classList.add('active');

          // ធ្វើបច្ចុប្បន្នភាព Accordion ឲ្យបង្ហាញតែ Folder ដែលពាក់ព័ន្ធ
          updateAccordionVisibility();

          // Fetch data with the new filter
          debouncedFetchLogs(1);
        });
      }

      // Function ដើម្បីបង្ហាញ/លាក់ Folder នៅក្នុង Accordion
      function updateAccordionVisibility() {
        const accordionItems = document.querySelectorAll('#folderUsernameAccordion .accordion-item');
        accordionItems.forEach(item => {
          const button = item.querySelector('.accordion-button');
          if (!button) return;

          const folderName = button.textContent.trim();

          if (currentStaffType === 'all') {
            item.classList.remove('d-none'); // បង្ហាញទាំងអស់
          } else if (currentStaffType === 'skilled') {
            if (skilledFolders.includes(folderName)) {
              item.classList.remove('d-none');
            } else {
              item.classList.add('d-none');
            }
          } else if (currentStaffType === 'worker') {
            if (workerFolders.includes(folderName)) {
              item.classList.remove('d-none');
            } else {
              item.classList.add('d-none');
            }
          }
        });
      }
      // END: កូដបន្ថែមថ្មី


      // Debounce function
      function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
          const later = () => {
            clearTimeout(timeout);
            func(...args);
          };
          clearTimeout(timeout);
          timeout = setTimeout(later, wait);
        };
      }

      // Function to show notifications
      function showNotification(message, type = 'success') {
        const container = document.getElementById('notification-container');
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const notification = `
                    <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>`;
        container.innerHTML += notification;
        // Auto-remove notification after 5 seconds
        const newAlert = container.lastElementChild;
        setTimeout(() => {
          newAlert?.remove();
        }, 5000);
      }


      // Back to top button
      window.addEventListener('scroll', function () {
        if (window.scrollY > 300) {
          backToTopButton.classList.add('show');
        } else {
          backToTopButton.classList.remove('show');
        }
      });
      backToTopButton.addEventListener('click', function (e) {
        e.preventDefault();
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });

      // Branch toggle handling
      branchToggles.forEach(toggle => {
        toggle.addEventListener('click', function (e) {
          e.preventDefault();
          const checkbox = this.querySelector('.branch-checkbox');
          checkbox.checked = !checkbox.checked;
          const folder = this.dataset.folder;
          const branch = this.dataset.branch;
          const branchUsernameCheckboxes = document.querySelectorAll(`input[name="username_filter[]"][data-folder="${folder}"][data-branch="${branch}"]`);
          const branchAll = document.querySelector(`.selectBranchAll[data-folder="${folder}"][data-branch="${branch}"]`);

          if (!checkbox.checked) {
            branchUsernameCheckboxes.forEach(cb => cb.checked = false);
            branchAll.checked = false;
          } else {
            branchUsernameCheckboxes.forEach(cb => cb.checked = true);
            branchAll.checked = true;
          }
        });
      });

      branchCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
          const folder = this.closest('.branch-toggle').dataset.folder;
          const branch = this.closest('.branch-toggle').dataset.branch;
          const branchUsernameCheckboxes = document.querySelectorAll(`input[name="username_filter[]"][data-folder="${folder}"][data-branch="${branch}"]`);
          const branchAll = document.querySelector(`.selectBranchAll[data-folder="${folder}"][data-branch="${branch}"]`);

          if (!this.checked) {
            branchUsernameCheckboxes.forEach(cb => cb.checked = false);
            branchAll.checked = false;
          } else {
            branchUsernameCheckboxes.forEach(cb => cb.checked = true);
            branchAll.checked = true;
          }
        });
      });

      branchAllCheckboxes.forEach(branchAll => {
        branchAll.addEventListener('change', function () {
          const folder = this.dataset.folder;
          const branch = this.dataset.branch;
          const branchUsernameCheckboxes = document.querySelectorAll(`input[name="username_filter[]"][data-folder="${folder}"][data-branch="${branch}"]`);
          const branchCheckbox = document.querySelector(`.branch-checkbox[data-folder="${folder}"][data-branch="${branch}"]`);

          branchUsernameCheckboxes.forEach(cb => cb.checked = this.checked);
          branchCheckbox.checked = this.checked;
        });
      });

      usernameCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function () {
          const folder = this.dataset.folder;
          const branch = this.dataset.branch;
          const branchCheckboxes = document.querySelectorAll(`input[name="username_filter[]"][data-folder="${folder}"][data-branch="${branch}"]`);
          const branchAll = document.querySelector(`.selectBranchAll[data-folder="${folder}"][data-branch="${branch}"]`);
          const branchCheckbox = document.querySelector(`.branch-checkbox[data-folder="${folder}"][data-branch="${branch}"]`);
          const allChecked = Array.from(branchCheckboxes).every(cb => cb.checked);
          const noneChecked = Array.from(branchCheckboxes).every(cb => !cb.checked);

          branchAll.checked = allChecked;
          branchAll.indeterminate = !allChecked && !noneChecked;
          branchCheckbox.checked = allChecked;
        });
      });

      function setupRowHighlighting() {
        const rows = document.querySelectorAll('table tbody tr');
        let highlightedRowIds = JSON.parse(localStorage.getItem('highlightedRowIds')) || [];

        rows.forEach(row => {
          const rowId = row.dataset.id;
          if (highlightedRowIds.includes(rowId)) {
            row.classList.add('highlighted');
          }

          row.addEventListener('click', function (e) {
            // Prevent highlighting when clicking on buttons, inputs or links
            if (e.target.tagName === 'A' || e.target.tagName === 'I' || e.target.tagName === 'BUTTON' || e.target.tagName === 'INPUT') return;

            this.classList.toggle('highlighted');
            const rowId = this.dataset.id;
            if (this.classList.contains('highlighted')) {
              if (!highlightedRowIds.includes(rowId)) {
                highlightedRowIds.push(rowId);
              }
            } else {
              highlightedRowIds = highlightedRowIds.filter(id => id !== rowId);
            }
            localStorage.setItem('highlightedRowIds', JSON.stringify(highlightedRowIds));
          });
        });
      }

      function setupUsernameSearch() {
        const searchInputs = document.querySelectorAll('.username-search');
        searchInputs.forEach(input => {
          input.addEventListener('input', function () {
            const folder = this.dataset.folder;
            const branch = this.dataset.branch;
            const searchTerm = this.value.trim().toLowerCase();
            const usernameItems = document.querySelectorAll(
              `.username-checkboxes input[name="username_filter[]"][data-folder="${folder}"][data-branch="${branch}"]`
            );

            usernameItems.forEach(item => {
              const label = item.parentElement;
              const username = item.value.toLowerCase();
              if (searchTerm === '' || username.includes(searchTerm)) {
                label.classList.remove('hidden');
              } else {
                label.classList.add('hidden');
              }
            });

            const branchAll = document.querySelector(
              `.selectBranchAll[data-folder="${folder}"][data-branch="${branch}"]`
            );
            const visibleCheckboxes = Array.from(usernameItems).filter(
              item => !item.parentElement.classList.contains('hidden')
            );
            const allChecked = visibleCheckboxes.length > 0 &&
              visibleCheckboxes.every(cb => cb.checked);
            const noneChecked = visibleCheckboxes.every(cb => !cb.checked);

            branchAll.checked = allChecked;
            branchAll.indeterminate = !allChecked && !noneChecked;
          });
        });
      }

      const debouncedFetchLogs = debounce(function (page) {
        if (isFetching) return;
        isFetching = true;

        const startDate = startDateInput.value;
        const endDate = endDateInput.value;
        const startTime = document.getElementById('start_time_filter').value;
        const endTime = document.getElementById('end_time_filter').value;

        const selectedUsernames = Array.from(usernameCheckboxes)
          .filter(checkbox => checkbox.checked && !checkbox.parentElement.classList.contains('hidden'))
          .map(checkbox => checkbox.value);
        const selectedBranches = Array.from(usernameCheckboxes)
          .filter(checkbox => checkbox.checked && !checkbox.parentElement.classList.contains('hidden'))
          .map(checkbox => checkbox.dataset.branch)
          .filter((value, index, self) => self.indexOf(value) === index);
        const filterUsername = document.getElementById('filter_username').value;
        const filterBranch = document.getElementById('filter_branch')?.value || '';
        const filterDate = document.getElementById('filter_date')?.value || '';


        loading.style.display = 'block';
        logsTable.innerHTML = '';
        pagination.innerHTML = '';
        statsDashboard.style.display = 'none';

        fetch('fetch_logs.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          body: JSON.stringify({
            page,
            start_date: startDate,
            end_date: endDate,
            usernames: selectedUsernames,
            branches: selectedBranches,
            filter_username: filterUsername,
            filter_branch: filterBranch,
            filter_date: filterDate,
            // បន្ថែម Parameter ថ្មីសម្រាប់ម៉ោង
            start_time: startTime,
            end_time: endTime,
            staff_type: currentStaffType // <<<< បន្ថែម dòng នេះ
          })
        })
          .then(response => {
            if (response.status === 401) {
              showNotification('សម័យកាលផុតកំណត់។ សូមចូលម្តងទៀត។', 'danger');
              loading.style.display = 'none';
              isFetching = false;
              return;
            }
            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
            return response.json();
          })
          .then(data => {
            if (!data) return; // Exit if no data (e.g., session expired)
            if (data.error) throw new Error(data.error);
            logsTable.innerHTML = data.logs_html;
            pagination.innerHTML = data.pagination_html;

            statsDashboard.style.display = 'block';
            const statsGrid = statsDashboard.querySelector('.stats-grid');
            statsGrid.innerHTML = `
                        <div class="stat-box">
                            <strong>សរុបស្កេន</strong>
                            <span class="stat-number">${data.total_logs}</span>
                        </div>
                        ${Object.entries(data.status_counts).map(([status, count]) => `
                            <div class="stat-box">
                                <strong>${status || 'មិនមានស្ថានភាព'}</strong>
                                <span class="stat-number">${count}</span>
                            </div>
                        `).join('')}
                    `;

            const ctx = document.getElementById('statusChart').getContext('2d');
            if (statusChart instanceof Chart) {
              statusChart.destroy();
            }
            statusChart = new Chart(ctx, {
              type: 'pie',
              data: {
                labels: Object.keys(data.status_counts),
                datasets: [{
                  data: Object.values(data.status_counts),
                  backgroundColor: ['#0077d6', '#dc3545', '#6c757d'],
                  borderWidth: 1
                }]
              },
              options: {
                responsive: true,
                plugins: {
                  legend: { position: 'top' },
                  title: { display: true, text: 'Status Distribution' }
                }
              }
            });

            loading.style.display = 'none';
            window.history.pushState({ page: page }, '', `?page=${page}`);

            setupRowHighlighting();
            setupUsernameSearch();
            isFetching = false;
          })
          .catch(error => {
            console.error('Error:', error);
            logsTable.innerHTML = '<p class="text-danger">មានបញ្ហាក្នុងការស្វែងរកទិន្នន័យ: ' + error.message + '</p>';
            loading.style.display = 'none';
            isFetching = false;
          });
      }, 500);

      // --- START INLINE EDIT LOGIC (MODIFIED FOR DATE FORMAT) ---
      logsTable.addEventListener('click', function (e) {
        const target = e.target.closest('button');
        if (!target) return;

        const row = target.closest('tr');
        if (!row) return;
        const logId = row.dataset.id;

        // --- Edit Button Click ---
        if (target.classList.contains('edit-log-btn')) {
          const originalValues = {};
          row.querySelectorAll('td[data-field]').forEach(cell => {
            const field = cell.dataset.field;
            originalValues[field] = cell.innerHTML;
            const value = cell.textContent.trim();

            let inputHtml = '';

            if (field === 'scan_date') {
              const parts = value.split('/');
              let yyyy_mm_dd_value = value;
              if (parts.length === 3) {
                yyyy_mm_dd_value = `${parts[2]}-${parts[1]}-${parts[0]}`;
              }
              inputHtml = `<input type="date" class="form-control form-control-sm" name="${field}" value="${yyyy_mm_dd_value}">`;

            } else if (field === 'scan_time') {
              let time_for_input = value;
              const timeParts = value.match(/(\d{1,2}):(\d{2}):(\d{2})\s*(AM|PM)/i);
              if (timeParts) {
                let [, hours, minutes, seconds, ampm] = timeParts;
                hours = parseInt(hours, 10);
                if (ampm.toUpperCase() === 'PM' && hours < 12) {
                  hours += 12;
                }
                if (ampm.toUpperCase() === 'AM' && hours === 12) {
                  hours = 0;
                }
                time_for_input = `${String(hours).padStart(2, '0')}:${minutes}:${seconds}`;
              }
              inputHtml = `<input type="time" class="form-control form-control-sm" name="${field}" value="${time_for_input}" step="1">`;

            } else if (field === 'status') {
              inputHtml = `<input type="text" class="form-control form-control-sm" name="${field}" value="${value.replace('🔵', '').replace('🔴', '').trim()}">`;
            } else {
              inputHtml = `<input type="text" class="form-control form-control-sm" name="${field}" value="${value}">`;
            }
            cell.innerHTML = inputHtml;
          });

          row.dataset.original = JSON.stringify(originalValues);

          // Toggle buttons
          row.querySelector('.edit-log-btn').style.display = 'none';
          row.querySelector('.duplicate-log-btn').style.display = 'none';
          row.querySelector('.save-log-btn').style.display = 'inline-block';
          row.querySelector('.cancel-log-btn').style.display = 'inline-block';
          // Show delete button in edit mode as well, but keep it enabled
          if (row.querySelector('.delete-log-btn')) row.querySelector('.delete-log-btn').style.display = 'inline-block';
        }

        // --- Cancel Button Click ---
        else if (target.classList.contains('cancel-log-btn')) {
          const originalValues = JSON.parse(row.dataset.original);
          row.querySelectorAll('td[data-field]').forEach(cell => {
            const field = cell.dataset.field;
            cell.innerHTML = originalValues[field];
          });
          delete row.dataset.original;

          row.querySelector('.edit-log-btn').style.display = 'inline-block';
          row.querySelector('.duplicate-log-btn').style.display = 'inline-block';
          row.querySelector('.save-log-btn').style.display = 'none';
          row.querySelector('.cancel-log-btn').style.display = 'none';
        }

        // --- Save Button Click ---
        else if (target.classList.contains('save-log-btn')) {
          const updatedData = {};
          row.querySelectorAll('td[data-field] input').forEach(input => {
            updatedData[input.name] = input.value;
          });

          const formData = new FormData();
          formData.append('id', logId);
          formData.append('csrf_token', csrfToken);
          for (const key in updatedData) {
            formData.append(`data[${key}]`, updatedData[key]);
          }

          fetch('update_log.php', {
            method: 'POST',
            body: formData
          })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                showNotification('ទិន្នន័យត្រូវបានរក្សាទុកដោយជោគជ័យ!', 'success');

                row.querySelectorAll('td[data-field]').forEach(cell => {
                  const field = cell.dataset.field;
                  let newValue = updatedData[field] || '';

                  if (field === 'status') {
                    let statusIcon = '';
                    const normalizedStatus = newValue.toLowerCase();
                    if (normalizedStatus.includes('good')) statusIcon = '🔵 ';
                    else if (normalizedStatus.includes('late')) statusIcon = '🔴 ';
                    cell.innerHTML = statusIcon + newValue;

                  } else if (field === 'scan_date') {
                    const parts = newValue.split('-');
                    if (parts.length === 3) {
                      cell.innerHTML = `${parts[2]}/${parts[1]}/${parts[0]}`;
                    } else {
                      cell.innerHTML = newValue;
                    }

                  } else if (field === 'scan_time') {
                    let display_time = newValue;
                    const timeParts = newValue.match(/(\d{2}):(\d{2}):(\d{2})/);
                    if (timeParts) {
                      let [, hours, minutes, seconds] = timeParts;
                      hours = parseInt(hours, 10);
                      const ampm = hours >= 12 ? 'PM' : 'AM';
                      let displayHours = hours % 12;
                      displayHours = displayHours ? displayHours : 12;
                      display_time = `${String(displayHours).padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
                    }
                    cell.innerHTML = display_time;

                  } else {
                    cell.innerHTML = newValue;
                  }
                });
                delete row.dataset.original;

                row.querySelector('.edit-log-btn').style.display = 'inline-block';
                row.querySelector('.duplicate-log-btn').style.display = 'inline-block';
                row.querySelector('.save-log-btn').style.display = 'none';
                row.querySelector('.cancel-log-btn').style.display = 'none';
              } else {
                throw new Error(data.error || 'មានបញ្ហាក្នុងការរក្សាទុក');
              }
            })
            .catch(error => {
              showNotification('Error: ' + error.message, 'danger');
            });
        }

        // =============================================================
        // === START: កូដកែសម្រួលសម្រាប់ប៊ូតុងចម្លង (Duplicate) ===
        // =============================================================
        // --- Duplicate Button Click (កែសម្រួលដើម្បីយក timestamp ដើម) ---
        else if (target.classList.contains('duplicate-log-btn')) {
          if (confirm('តើអ្នកពិតជាចង់ចម្លងទិន្នន័យនេះមែនទេ?')) {
            // 1. ទាញយក timestamp ដើមពី data-attribute ដែលយើងបានបន្ថែមក្នុង fetch_logs.php
            const originalTimestamp = row.dataset.timestamp;

            // 2. ពិនិត្យមើលថា timestamp មានតម្លៃឬអត់
            if (!originalTimestamp) {
              showNotification('មានបញ្ហា៖ រកមិនឃើញ timestamp ដើមសម្រាប់ចម្លងទេ!', 'danger');
              return; // បញ្ឈប់ដំណើរការបើគ្មាន timestamp
            }

            // 3. រៀបចំទិន្នន័យដើម្បីផ្ញើទៅ Server
            const formData = new FormData();
            formData.append('id', logId); // ID របស់ record ដើម
            formData.append('csrf_token', csrfToken);

            // **ការកែប្រែសំខាន់** បន្ថែម timestamp ដើមទៅក្នុងទិន្នន័យដែលត្រូវផ្ញើ
            formData.append('timestamp', originalTimestamp);

            // 4. ធ្វើការ Fetch ទៅកាន់ script ដែលទទួលខុសត្រូវលើការ duplicate
            fetch('duplicate_log.php', {
              method: 'POST',
              body: formData
            })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  showNotification(data.message || 'ទិន្នន័យត្រូវបានចម្លងដោយជោគជ័យ!', 'success');
                  // ផ្ទុកទិន្នន័យក្នុងតារាងឡើងវិញដើម្បីបង្ហាញ record ថ្មី
                  const currentPage = document.querySelector('.pagination .page-item.active .page-link')?.dataset.page || 1;
                  debouncedFetchLogs(parseInt(currentPage));
                } else {
                  throw new Error(data.error || 'មានបញ្ហាក្នុងការចម្លង');
                }
              })
              .catch(error => {
                showNotification('Error: ' + error.message, 'danger');
              });
          }
        }
        // ===========================================================
        // === END: កូដកែសម្រួលសម្រាប់ប៊ូតុងចម្លង (Duplicate) ===
        // ===========================================================

        // --- Delete Button Click ---
        else if (target.classList.contains('delete-log-btn')) {
          if (confirm('តើអ្នកពិតជាចង់លុបទិន្នន័យនេះមែនទេ? ការលុបនេះមិនអាចយកមកវិញបានទេ។')) {
            const formData = new FormData();
            formData.append('id', logId);
            formData.append('csrf_token', csrfToken);

            fetch('delete_log.php', {
              method: 'POST',
              body: formData
            })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  showNotification('ទិន្នន័យត្រូវបានលុបដោយជោគជ័យ!', 'success');
                  // Remove the row from the table smoothly
                  row.style.transition = 'opacity 0.5s ease';
                  row.style.opacity = '0';
                  setTimeout(() => row.remove(), 500);
                } else {
                  throw new Error(data.error || 'មានបញ្ហាក្នុងការលុប');
                }
              })
              .catch(error => {
                showNotification('Error: ' + error.message, 'danger');
              });
          }
        }
      });
      // --- END INLINE EDIT LOGIC ---


      exportButton.addEventListener('click', function (e) {
        e.preventDefault();
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'export_logs.php';

        const fields = {
          start_date: startDateInput.value,
          end_date: endDateInput.value,
          usernames: Array.from(usernameCheckboxes)
            .filter(checkbox => checkbox.checked && !checkbox.parentElement.classList.contains('hidden'))
            .map(checkbox => checkbox.value).join(','),
          branches: Array.from(usernameCheckboxes)
            .filter(checkbox => checkbox.checked && !checkbox.parentElement.classList.contains('hidden'))
            .map(checkbox => checkbox.dataset.branch)
            .filter((value, index, self) => self.indexOf(value) === index).join(','),
          filter_username: document.getElementById('filter_username').value,
          filter_branch: document.getElementById('filter_branch')?.value || '',
          filter_date: document.getElementById('filter_date')?.value || '',
          csrf_token: csrfToken
        };

        for (const [name, value] of Object.entries(fields)) {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = name;
          input.value = value;
          form.appendChild(input);
        }

        document.body.appendChild(form);
        form.submit();
      });

      searchButton.addEventListener('click', (e) => {
        e.preventDefault();
        debouncedFetchLogs(1);
      });

      clearButton.addEventListener('click', (e) => {
        e.preventDefault();
        startDateInput.value = '';
        endDateInput.value = '';
        branchCheckboxes.forEach(checkbox => checkbox.checked = true);
        branchAllCheckboxes.forEach(checkbox => checkbox.checked = true);
        usernameCheckboxes.forEach(checkbox => checkbox.checked = true);
        document.getElementById('filter_username').value = '';
        const filterBranch = document.getElementById('filter_branch');
        if (filterBranch) filterBranch.value = '';
        const filterDateSelect = document.getElementById('filter_date');
        if (filterDateSelect) filterDateSelect.value = '';
        localStorage.removeItem('highlightedRowIds');
        debouncedFetchLogs(1);
      });

      pagination.addEventListener('click', function (e) {
        e.preventDefault();
        const target = e.target.closest('.page-link');
        if (target && !target.parentElement.classList.contains('disabled')) {
          const page = parseInt(target.getAttribute('data-page'));
          debouncedFetchLogs(page);
        }
      });

      // Filter dropdowns
      document.getElementById('filter_folder').addEventListener('change', function () {
        document.cookie = "selected_folder=" + encodeURIComponent(this.value) + ";path=/;max-age=31536000";
        const params = new URLSearchParams(window.location.search);
        params.set('folder', this.value);
        params.delete('username');
        params.set('page', 1);
        window.location.search = params.toString();
      });

      document.getElementById('filter_username').addEventListener('change', function () {
        const params = new URLSearchParams(window.location.search);
        params.set('username', this.value);
        params.set('page', 1);
        window.location.search = params.toString();
      });

      // Use event delegation for dynamically created filter_date dropdown
      document.addEventListener('change', function (e) {
        if (e.target.id === 'filter_date') {
          debouncedFetchLogs(1);
        }
      });

      // Initial setup
      if (document.getElementById('filter_folder') && !document.getElementById('filter_folder').value && document.cookie.includes('selected_folder=')) {
        const match = document.cookie.match(/selected_folder=([^;]+)/);
        if (match) document.getElementById('filter_folder').value = decodeURIComponent(match[1]);
      }

      debouncedFetchLogs(1);
      setupUsernameSearch();
      updateAccordionVisibility(); // <<<< បន្ថែម dòng នេះ
    });
  </script>
</body>

</html>