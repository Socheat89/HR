<?php
// fetch_logs.php (UPDATED WITH STAFF TYPE FILTER AND FIXED PAGINATION)

session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in']) && !isset($_SESSION['sub_user_logged_in'])) {
    error_log('Session invalid in fetch_logs.php: ' . print_r($_SESSION, true));
    http_response_code(401);
    exit(json_encode(['error' => 'Session expired'], JSON_UNESCAPED_UNICODE));
}

// Validate CSRF token
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || !hash_equals($_SESSION['csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE));
}

// Close session early to prevent locking
session_write_close();

$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$is_sub_user = isset($_SESSION['sub_user_logged_in']) && $_SESSION['sub_user_logged_in'] === true;
$branch_filter = $_SESSION['branch'] ?? null;

// Logic to handle sub-user permissions
$allowed_username_filter = null;
if ($is_sub_user && isset($_SESSION['allowed_username'])) {
    $allowed_usernames_from_session = $_SESSION['allowed_username'];
    $decoded_usernames = is_string($allowed_usernames_from_session) ? json_decode($allowed_usernames_from_session, true) : $allowed_usernames_from_session;
    if (is_array($decoded_usernames) && !empty($decoded_usernames)) {
        $allowed_username_filter = $decoded_usernames;
    }
}

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    // Read JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input');
    }

    // Extract parameters
    $startDate = $input['start_date'] ?? '';
    $endDate = $input['end_date'] ?? '';
    $startTime = $input['start_time'] ?? '';
    $endTime = $input['end_time'] ?? '';
    $usernames = ($is_admin && isset($input['usernames']) && is_array($input['usernames'])) ? $input['usernames'] : [];
    $branches = ($is_admin && isset($input['branches']) && is_array($input['branches'])) ? $input['branches'] : [];
    $filterUsername = ($is_admin && isset($input['filter_username'])) ? $input['filter_username'] : '';
    $filterBranch = ($is_admin && isset($input['filter_branch'])) ? $input['filter_branch'] : '';
    $filterDate = $input['filter_date'] ?? '';
    $page = isset($input['page']) && is_numeric($input['page']) ? max(1, (int)$input['page']) : 1;

    // === START: NEW CODE - ទទួល Parameter ប្រភេទបុគ្គលិក ===
    $staff_type = $input['staff_type'] ?? 'skilled'; // កំណត់ 'skilled' ជា Default នៅฝั่ง Server
    // === END: NEW CODE ===

    $limit = 1000;
    $offset = ($page - 1) * $limit;

    // Database connection
    $pdo = new PDO(
        "mysql:host=localhost;dbname=samann1_scan_logs_worker_db;charset=utf8mb4",
        'samann1_scan_logs_worker_db',
        'scan_logs_worker_db@2025',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // --- កំណត់ Folder សម្រាប់ប្រភេទបុគ្គលិក ---
    $skilled_folders = ['ជំនាញ', 'ឃ្លាំង', 'ហាងទំនិញ៣១៨', 'SK Chuk Meas', 'SK Cosmetic'];
    $worker_folders = ['កម្មករ'];


    // ===================================================================
    // === 1. Build MAIN QUERY for fetching logs ===
    // ===================================================================
    $sql = "SELECT id, username, branch, folder, user_id, action, timestamp, latitude, longitude, status, address, noted, early_reason 
             FROM scan_logs WHERE 1=1";
    $params = [];
    $orderBy = "ORDER BY username ASC, timestamp ASC";

    // --- START: Filter Conditions (Main Query) ---
    // Sub-user filter
    if ($is_sub_user) {
        if ($branch_filter) { $sql .= " AND branch = ?"; $params[] = $branch_filter; }
        if ($allowed_username_filter) {
            $sql .= " AND username IN (" . implode(',', array_fill(0, count($allowed_username_filter), '?')) . ")";
            $params = array_merge($params, $allowed_username_filter);
        } else { $sql .= " AND 1=0"; }
    } elseif ($is_admin && !empty($branches)) {
        $sql .= " AND branch IN (" . implode(',', array_fill(0, count($branches), '?')) . ")";
        $params = array_merge($params, $branches);
    }

// === START: NEW CODE - បន្ថែមលក្ខខណ្ឌ Staff Type ទៅ Main Query ===
if (!empty($staff_type) && $staff_type !== 'all') { // បន្ថែមលក្ខខណ្ឌ 'all'
    if ($staff_type === 'skilled') {
        $placeholders = implode(',', array_fill(0, count($skilled_folders), '?'));
        $sql .= " AND folder IN ($placeholders)";
        $params = array_merge($params, $skilled_folders);
    } elseif ($staff_type === 'worker') {
        $placeholders = implode(',', array_fill(0, count($worker_folders), '?'));
        $sql .= " AND folder IN ($placeholders)";
        $params = array_merge($params, $worker_folders);
    }
}
// === END: NEW CODE ===

    // Other filters
    if ($startDate) { $sql .= " AND DATE(timestamp) >= ?"; $params[] = $startDate; }
    if ($endDate) { $sql .= " AND DATE(timestamp) <= ?"; $params[] = $endDate; }
    if ($startTime) { $sql .= " AND TIME(timestamp) >= ?"; $params[] = $startTime; }
    if ($endTime) { $sql .= " AND TIME(timestamp) <= ?"; $params[] = $endTime; }

    if ($is_admin && !empty($usernames)) {
        $sql .= " AND username IN (" . implode(',', array_fill(0, count($usernames), '?')) . ")";
        $params = array_merge($params, $usernames);
    }
    if ($is_admin && !empty($filterUsername)) { $sql .= " AND username = ?"; $params[] = $filterUsername; }
    if ($is_admin && !empty($filterBranch)) { $sql .= " AND branch = ?"; $params[] = $filterBranch; }
    if (!empty($filterDate)) { $sql .= " AND DATE_FORMAT(timestamp, '%m/%d/%Y') = ?"; $params[] = $filterDate; }
    
    $sql .= " $orderBy LIMIT ? OFFSET ?";

    // === START: FIXED CODE FOR PAGINATION ===
    $stmt = $pdo->prepare($sql);
    
    $paramIndex = 1;
    foreach ($params as $value) {
        $stmt->bindValue($paramIndex++, $value, PDO::PARAM_STR);
    }
    
    $stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($paramIndex, $offset, PDO::PARAM_INT);
    // === END: FIXED CODE FOR PAGINATION ===

    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // ===================================================================
    // === 2. Build STATUS COUNT QUERY ===
    // ===================================================================
    $statusSql = "SELECT TRIM(LOWER(status)) as status, COUNT(*) as count FROM scan_logs WHERE 1=1";
    $statusParams = [];
    
    // --- START: Status Filter Conditions ---
    // Sub-user filter
    if ($is_sub_user) {
        if ($branch_filter) { $statusSql .= " AND branch = ?"; $statusParams[] = $branch_filter; }
        if ($allowed_username_filter) {
            $statusSql .= " AND username IN (" . implode(',', array_fill(0, count($allowed_username_filter), '?')) . ")";
            $statusParams = array_merge($statusParams, $allowed_username_filter);
        } else { $statusSql .= " AND 1=0"; }
    } elseif ($is_admin && !empty($branches)) {
        $statusSql .= " AND branch IN (" . implode(',', array_fill(0, count($branches), '?')) . ")";
        $statusParams = array_merge($statusParams, $branches);
    }
    
    // === START: NEW CODE - បន្ថែមលក្ខខណ្ឌ Staff Type ទៅ Status Query ===
    if ($staff_type === 'skilled') {
        $placeholders = implode(',', array_fill(0, count($skilled_folders), '?'));
        $statusSql .= " AND folder IN ($placeholders)";
        $statusParams = array_merge($statusParams, $skilled_folders);
    } elseif ($staff_type === 'worker') {
        $placeholders = implode(',', array_fill(0, count($worker_folders), '?'));
        $statusSql .= " AND folder IN ($placeholders)";
        $statusParams = array_merge($statusParams, $worker_folders);
    }
    // === END: NEW CODE ===

    // Other filters
    if ($startDate) { $statusSql .= " AND DATE(timestamp) >= ?"; $statusParams[] = $startDate; }
    if ($endDate) { $statusSql .= " AND DATE(timestamp) <= ?"; $statusParams[] = $endDate; }
    if ($startTime) { $statusSql .= " AND TIME(timestamp) >= ?"; $statusParams[] = $startTime; }
    if ($endTime) { $statusSql .= " AND TIME(timestamp) <= ?"; $statusParams[] = $endTime; }

    if ($is_admin && !empty($usernames)) {
        $statusSql .= " AND username IN (" . implode(',', array_fill(0, count($usernames), '?')) . ")";
        $statusParams = array_merge($statusParams, $usernames);
    }
    if ($is_admin && !empty($filterUsername)) { $statusSql .= " AND username = ?"; $statusParams[] = $filterUsername; }
    if ($is_admin && !empty($filterBranch)) { $statusSql .= " AND branch = ?"; $statusParams[] = $filterBranch; }
    if (!empty($filterDate)) { $statusSql .= " AND DATE_FORMAT(timestamp, '%m/%d/%Y') = ?"; $statusParams[] = $filterDate; }
    
    $statusSql .= " GROUP BY TRIM(LOWER(status))";
    $statusStmt = $pdo->prepare($statusSql);
    $statusStmt->execute($statusParams);
    $statusData = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

    $statusCounts = [];
    foreach ($statusData as $data) {
        $status = $data['status'] ?: 'មិនមានស្ថានភាព';
        $count = (int)$data['count'];
        if (strpos($status, 'good') !== false) {
            $statusCounts['🔵 Good'] = ($statusCounts['🔵 Good'] ?? 0) + $count;
        } elseif (strpos($status, 'late') !== false) {
            $statusCounts['🔴 Late'] = ($statusCounts['🔴 Late'] ?? 0) + $count;
        } else {
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + $count;
        }
    }


    // ===================================================================
    // === 3. Build TOTAL COUNT QUERY for Pagination ===
    // ===================================================================
    $totalSql = "SELECT COUNT(*) FROM scan_logs WHERE 1=1";
    $totalParams = [];
    
    // --- START: Total Count Filter Conditions ---
    // Sub-user filter
    if ($is_sub_user) {
        if ($branch_filter) { $totalSql .= " AND branch = ?"; $totalParams[] = $branch_filter; }
        if ($allowed_username_filter) {
            $totalSql .= " AND username IN (" . implode(',', array_fill(0, count($allowed_username_filter), '?')) . ")";
            $totalParams = array_merge($totalParams, $allowed_username_filter);
        } else { $totalSql .= " AND 1=0"; }
    } elseif ($is_admin && !empty($branches)) {
        $totalSql .= " AND branch IN (" . implode(',', array_fill(0, count($branches), '?')) . ")";
        $totalParams = array_merge($totalParams, $branches);
    }
    
    // === START: NEW CODE - បន្ថែមលក្ខខណ្ឌ Staff Type ទៅ Total Count Query ===
    if ($staff_type === 'skilled') {
        $placeholders = implode(',', array_fill(0, count($skilled_folders), '?'));
        $totalSql .= " AND folder IN ($placeholders)";
        $totalParams = array_merge($totalParams, $skilled_folders);
    } elseif ($staff_type === 'worker') {
        $placeholders = implode(',', array_fill(0, count($worker_folders), '?'));
        $totalSql .= " AND folder IN ($placeholders)";
        $totalParams = array_merge($totalParams, $worker_folders);
    }
    // === END: NEW CODE ===

    // Other filters
    if ($startDate) { $totalSql .= " AND DATE(timestamp) >= ?"; $totalParams[] = $startDate; }
    if ($endDate) { $totalSql .= " AND DATE(timestamp) <= ?"; $totalParams[] = $endDate; }
    if ($startTime) { $totalSql .= " AND TIME(timestamp) >= ?"; $totalParams[] = $startTime; }
    if ($endTime) { $totalSql .= " AND TIME(timestamp) <= ?"; $totalParams[] = $endTime; }

    if ($is_admin && !empty($usernames)) {
        $totalSql .= " AND username IN (" . implode(',', array_fill(0, count($usernames), '?')) . ")";
        $totalParams = array_merge($totalParams, $usernames);
    }
    if ($is_admin && !empty($filterUsername)) { $totalSql .= " AND username = ?"; $totalParams[] = $filterUsername; }
    if ($is_admin && !empty($filterBranch)) { $totalSql .= " AND branch = ?"; $totalParams[] = $filterBranch; }
    if (!empty($filterDate)) { $totalSql .= " AND DATE_FORMAT(timestamp, '%m/%d/%Y') = ?"; $totalParams[] = $filterDate; }
    
    $totalStmt = $pdo->prepare($totalSql);
    $totalStmt->execute($totalParams);
    $totalLogs = (int)$totalStmt->fetchColumn();
    $totalPages = ceil($totalLogs / $limit);
    

    // ===================================================================
    // === Generate HTML Response (No changes needed here) ===
    // ===================================================================
    // Generate table HTML
    $logs_html = '<table class="table table-striped table-bordered table-hover" id="scan-logs-table">';
    // ... (Your HTML table header code)
    $logs_html .= '<thead class="table-primary"><tr>';
    $logs_html .= '<th>អត្តលេខ</th>';
    $logs_html .= '<th>ឈ្មោះ</th>';
    $logs_html .= '<th>សាខា</th>';
    $logs_html .= '<th>ប្រភេទស្កេន</th>';
    $logs_html .= '<th>ថ្ងៃខែឆ្នាំ<select id="filter_date" class="form-select form-select-sm" aria-label="Filter by date">';
    $logs_html .= '<option value="">-- ទាំងអស់ --</option>';
    $uniqueDatesStmt = $pdo->query("SELECT DISTINCT DATE_FORMAT(timestamp, '%m/%d/%Y') AS formatted_date FROM scan_logs ORDER BY timestamp DESC");
    $uniqueDates = $uniqueDatesStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($uniqueDates as $date) {
        $selected = ($filterDate === $date) ? ' selected' : '';
        $logs_html .= '<option value="' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $logs_html .= '</select></th>';
    $logs_html .= '<th>ម៉ោង</th>';
    $logs_html .= '<th>ស្ថានភាព</th>';
    $logs_html .= '<th>មូលហេតុស្កេនមុនម៉ោង</th>';
    $logs_html .= '<th>ទីតាំង</th>';
    $logs_html .= '<th>ចំណាំ</th>';
    $logs_html .= '<th>សកម្មភាព</th>';
    $logs_html .= '</tr></thead><tbody>';
    
    if (empty($logs)) {
        $logs_html .= '<tr><td colspan="11" class="text-center">មិនមានទិន្នន័យប្រវត្តិសម្រាប់ការស្វែងរកនេះ!</td></tr>';
    } else {
        foreach ($logs as $log) {
            $log['id'] = (int)$log['id'];
            $location = isset($log['latitude'], $log['longitude']) && is_numeric($log['latitude']) && is_numeric($log['longitude'])
                ? "<a href='https://www.google.com/maps?q=" . urlencode($log['latitude'] . ',' . $log['longitude']) . "' target='_blank'><i class='fas fa-map-marker-alt location-icon'></i> " . htmlspecialchars($log['latitude'] . ', ' . $log['longitude'], ENT_QUOTES, 'UTF-8') . "</a>"
                : 'មិនមានទីតាំង';
            
            $statusIcon = '';
            if (isset($log['status'])) {
                $normalizedStatus = strtolower(trim($log['status']));
                if (strpos($normalizedStatus, 'good') !== false) {
                    $statusIcon = '🔵';
                } elseif (strpos($normalizedStatus, 'late') !== false) {
                    $statusIcon = '🔴';
                }
            }
            $statusDisplay = $statusIcon ? "$statusIcon " . htmlspecialchars($log['status'] ?? 'មិនមាន', ENT_QUOTES, 'UTF-8') : htmlspecialchars($log['status'] ?? 'មិនមាន', ENT_QUOTES, 'UTF-8');
            
            $scan_datetime = new DateTime($log['timestamp']);
            $scan_date_for_display = $scan_datetime->format('d/m/Y');
            $scan_time_for_input = $scan_datetime->format('h:i:s A');
            
            $logs_html .= "<tr data-id='" . htmlspecialchars($log['id'], ENT_QUOTES, 'UTF-8') . "' data-timestamp='" . htmlspecialchars($log['timestamp'], ENT_QUOTES, 'UTF-8') . "'>";
            $logs_html .= "<td data-field='user_id'>" . htmlspecialchars($log['user_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</td>";
            $logs_html .= "<td data-field='username'>" . htmlspecialchars($log['username'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</td>";
            $logs_html .= "<td data-field='branch'>" . htmlspecialchars($log['branch'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</td>";
            $logs_html .= "<td data-field='action'>" . htmlspecialchars($log['action'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</td>";
            $logs_html .= "<td data-field='scan_date'>" . htmlspecialchars($scan_date_for_display, ENT_QUOTES, 'UTF-8') . "</td>";
            $logs_html .= "<td data-field='scan_time'>" . htmlspecialchars($scan_time_for_input, ENT_QUOTES, 'UTF-8') . "</td>";
            $logs_html .= "<td data-field='status'>" . $statusDisplay . "</td>";
            $logs_html .= "<td data-field='early_reason'>" . htmlspecialchars($log['early_reason'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . "</td>";
            $logs_html .= "<td>" . $location . "</td>";
            $logs_html .= "<td data-field='noted'>";
            $note_text = $log['noted'] ?? '';
            if (filter_var($note_text, FILTER_VALIDATE_URL)) {
                $logs_html .= '<a href="' . htmlspecialchars($note_text, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($note_text, ENT_QUOTES, 'UTF-8') . '</a>';
            } else {
                $logs_html .= htmlspecialchars($note_text, ENT_QUOTES, 'UTF-8');
            }
            $logs_html .= "</td>";
            $logs_html .= "<td class='action-buttons'>";
            $logs_html .= "<button class='btn btn-info btn-sm edit-log-btn me-1' title='កែសម្រួល'><i class='fa-solid fa-edit'></i></button>";
            $logs_html .= "<button class='btn btn-success btn-sm save-log-btn me-1' title='រក្សាទុក' style='display:none;'><i class='fa-solid fa-save'></i></button>";
            $logs_html .= "<button class='btn btn-secondary btn-sm cancel-log-btn me-1' title='បោះបង់' style='display:none;'><i class='fa-solid fa-times'></i></button>";
            $logs_html .= "<button class='btn btn-warning btn-sm duplicate-log-btn me-1' title='ចម្លង'><i class='fa-solid fa-copy'></i></button>";
            $logs_html .= "<button class='btn btn-danger btn-sm delete-log-btn' title='លុប'><i class='fa-solid fa-trash-alt'></i></button>";
            $logs_html .= "</td>";
            $logs_html .= "</tr>";
        }
    }
    $logs_html .= '</tbody></table>';

    function generatePagination($currentPage, $totalPages) {
        if ($totalPages <= 1) return '';
        $range = 2;
        $html = '<nav><ul class="pagination">';
        $prevPage = $currentPage > 1 ? $currentPage - 1 : 1;
        $html .= '<li class="page-item' . ($currentPage == 1 ? ' disabled' : '') . '"><a class="page-link" href="#" data-page="' . $prevPage . '">« មុន</a></li>';
        $start = max(1, $currentPage - $range);
        $end = min($totalPages, $currentPage + $range);
        if ($start > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
            if ($start > 2) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        for ($i = $start; $i <= $end; $i++) {
            $html .= '<li class="page-item' . ($i == $currentPage ? ' active' : '') . '"><a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
        }
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            $html .= '<li class="page-item"><a class="page-link" href="#" data-page="' . $totalPages . '">' . $totalPages . '</a></li>';
        }
        $nextPage = $currentPage < $totalPages ? $currentPage + 1 : $totalPages;
        $html .= '<li class="page-item' . ($currentPage == $totalPages ? ' disabled' : '') . '"><a class="page-link" href="#" data-page="' . $nextPage . '">បន្ទាប់ »</a></li>';
        $html .= '</ul></nav>';
        return $html;
    }

    $pagination_html = generatePagination($page, $totalPages);

    // Final JSON response
    echo json_encode([
        'logs_html' => $logs_html,
        'pagination_html' => $pagination_html,
        'total_logs' => $totalLogs,
        'status_counts' => $statusCounts
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Fetch Logs Error: " . $e->getMessage());
    echo json_encode(['error' => "Server Error: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>