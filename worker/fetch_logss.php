<?php
// fetch_logs.php (FULL CODE - 500 ERROR FIXED & TIME FILTER ADDED)

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

// ==================================================================================================
// === START: Logic to handle sub-user permissions (Correct) ===
// ==================================================================================================
$allowed_username_filter = null;
if ($is_sub_user && isset($_SESSION['allowed_username'])) {
    $allowed_usernames_from_session = $_SESSION['allowed_username'];
    $decoded_usernames = null;

    if (is_string($allowed_usernames_from_session)) {
        $decoded_usernames = json_decode($allowed_usernames_from_session, true);
    } elseif (is_array($allowed_usernames_from_session)) {
        $decoded_usernames = $allowed_usernames_from_session;
    }

    if (is_array($decoded_usernames) && !empty($decoded_usernames)) {
        $allowed_username_filter = $decoded_usernames;
    }
}
// ==================================================================================================
// === END: Sub-user permission logic ===
// ==================================================================================================

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

    // Extract parameters from original code
    $startDate = isset($input['start_date']) ? trim($input['start_date']) : '';
    $endDate = isset($input['end_date']) ? trim($input['end_date']) : '';
    
    // === START: NEW CODE TO GET TIME FILTERS ===
    $startTime = isset($input['start_time']) && !empty($input['start_time']) ? trim($input['start_time']) : '';
    $endTime = isset($input['end_time']) && !empty($input['end_time']) ? trim($input['end_time']) : '';
    // === END: NEW CODE TO GET TIME FILTERS ===

    $usernames = $is_admin && isset($input['usernames']) && is_array($input['usernames']) ? array_map('trim', $input['usernames']) : [];
    $branches = $is_admin && isset($input['branches']) && is_array($input['branches']) ? array_map('trim', $input['branches']) : [];
    $filterUsername = $is_admin && isset($input['filter_username']) ? trim($input['filter_username']) : '';
    $filterBranch = $is_admin && isset($input['filter_branch']) ? trim($input['filter_branch']) : '';
    $filterDate = isset($input['filter_date']) ? trim($input['filter_date']) : '';
    $page = isset($input['page']) && is_numeric($input['page']) ? max(1, (int)$input['page']) : 1;
    $sort = isset($input['sort']) && in_array($input['sort'], ['timestamp_asc', 'timestamp_desc']) ? $input['sort'] : 'timestamp_desc';
    
    // Validate dates and times
    if ($startDate && !DateTime::createFromFormat('Y-m-d', $startDate)) { throw new Exception('Invalid start date format'); }
    if ($endDate && !DateTime::createFromFormat('Y-m-d', $endDate)) { throw new Exception('Invalid end date format'); }
    if ($filterDate && !DateTime::createFromFormat('m/d/Y', $filterDate)) { throw new Exception('Invalid filter date format'); }
    
    // === START: NEW CODE TO VALIDATE TIME FORMAT ===
    if ($startTime && !DateTime::createFromFormat('H:i', $startTime) && !DateTime::createfromformat('H:i:s', $startTime)) { throw new Exception('Invalid start time format'); }
    if ($endTime && !DateTime::createFromFormat('H:i', $endTime) && !DateTime::createfromformat('H:i:s', $endTime)) { throw new Exception('Invalid end time format'); }
    // === END: NEW CODE TO VALIDATE TIME FORMAT ===

    $limit = 500; // Align with panel.php
    $offset = ($page - 1) * $limit;

    // Database connection
    $pdo = new PDO(
        "mysql:host=localhost;dbname=samann1_scan_logs_worker_db;charset=utf8mb4",
        'samann1_scan_logs_worker_db',
        'scan_logs_worker_db@2025',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Build query - Kept your original logic
    $timestampOrder = ($sort === 'timestamp_asc') ? 'ASC' : 'DESC';
    $orderBy = "ORDER BY username ASC, timestamp ASC";
    $sql = "SELECT id, username, branch, folder, user_id, action, timestamp, latitude, longitude, status, address, noted, early_reason 
            FROM scan_logs WHERE 1=1";
    $params = [];

    // --- START: Filter Conditions (Main Query) ---
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
    
    if ($startDate) { $sql .= " AND DATE(timestamp) >= ?"; $params[] = $startDate; }
    if ($endDate) { $sql .= " AND DATE(timestamp) <= ?"; $params[] = $endDate; }
    
    // === START: NEW CODE TO ADD TIME CONDITION TO MAIN QUERY ===
    if ($startTime) { $sql .= " AND TIME(timestamp) >= ?"; $params[] = $startTime; }
    if ($endTime) { $sql .= " AND TIME(timestamp) <= ?"; $params[] = $endTime; }
    // === END: NEW CODE TO ADD TIME CONDITION TO MAIN QUERY ===

    if ($is_admin && !empty($usernames)) {
        $sql .= " AND username IN (" . implode(',', array_fill(0, count($usernames), '?')) . ")";
        $params = array_merge($params, $usernames);
    }
    if ($is_admin && !empty($filterUsername)) { $sql .= " AND username = ?"; $params[] = $filterUsername; }
    if ($is_admin && !empty($filterBranch)) { $sql .= " AND branch = ?"; $params[] = $filterBranch; }
    if (!empty($filterDate)) { $sql .= " AND DATE_FORMAT(timestamp, '%m/%d/%Y') = ?"; $params[] = $filterDate; }
    
    $sql .= " $orderBy LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    foreach ($params as $index => $value) {
        $paramType = ($index >= count($params) - 2) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($index + 1, $value, $paramType);
    }
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Status counts
    $statusSql = "SELECT TRIM(LOWER(status)) as status, COUNT(*) as count FROM scan_logs WHERE 1=1";
    $statusParams = [];
    
    // --- START: Status Filter Conditions ---
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
    
    if ($startDate) { $statusSql .= " AND DATE(timestamp) >= ?"; $statusParams[] = $startDate; }
    if ($endDate) { $statusSql .= " AND DATE(timestamp) <= ?"; $statusParams[] = $endDate; }
    
    // === START: NEW CODE TO ADD TIME CONDITION TO STATUS QUERY ===
    if ($startTime) { $statusSql .= " AND TIME(timestamp) >= ?"; $statusParams[] = $startTime; }
    if ($endTime) { $statusSql .= " AND TIME(timestamp) <= ?"; $statusParams[] = $endTime; }
    // === END: NEW CODE TO ADD TIME CONDITION TO STATUS QUERY ===

    if ($is_admin && !empty($usernames)) {
        $statusSql .= " AND username IN (" . implode(',', array_fill(0, count($usernames), '?')) . ")";
        $statusParams = array_merge($statusParams, $usernames);
    }
    if ($is_admin && !empty($filterUsername)) { $statusSql .= " AND username = ?"; $statusParams[] = $filterUsername; }
    if ($is_admin && !empty($filterBranch)) { $statusSql .= " AND branch = ?"; $statusParams[] = $filterBranch; }
    if (!empty($filterDate)) { $statusSql .= " AND DATE_FORMAT(timestamp, '%m/%d/%Y') = ?"; $statusParams[] = $filterDate; }
    
    $statusSql .= " GROUP BY TRIM(LOWER(status))";
    $statusStmt = $pdo->prepare($statusSql);
    foreach ($statusParams as $index => $value) {
        $statusStmt->bindValue($index + 1, $value, PDO::PARAM_STR);
    }
    $statusStmt->execute();
    $statusData = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

    $statusCounts = [];
    $totalStatusCount = 0;
    foreach ($statusData as $data) {
        $status = $data['status'] ?: 'មិនមានស្ថានភាព';
        if (strpos($status, 'good') !== false) {
            $statusCounts['🔵Good'] = ($statusCounts['🔵Good'] ?? 0) + (int)$data['count'];
        } elseif (strpos($status, 'late') !== false) {
            $statusCounts['🔴Late'] = ($statusCounts['🔴Late'] ?? 0) + (int)$data['count'];
        } else {
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + (int)$data['count'];
        }
        $totalStatusCount += (int)$data['count'];
    }

    // Generate table HTML
    $logs_html = '<table class="table table-striped table-bordered table-hover" id="scan-logs-table">';
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

    // Total logs count
    $totalSql = "SELECT COUNT(*) FROM scan_logs WHERE 1=1";
    $totalParams = [];
    
    // --- START: Total Count Filter Conditions ---
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
    
    if ($startDate) { $totalSql .= " AND DATE(timestamp) >= ?"; $totalParams[] = $startDate; }
    if ($endDate) { $totalSql .= " AND DATE(timestamp) <= ?"; $totalParams[] = $endDate; }
    
    // === START: NEW CODE TO ADD TIME CONDITION TO TOTAL COUNT QUERY ===
    if ($startTime) { $totalSql .= " AND TIME(timestamp) >= ?"; $totalParams[] = $startTime; }
    if ($endTime) { $totalSql .= " AND TIME(timestamp) <= ?"; $totalParams[] = $endTime; }
    // === END: NEW CODE TO ADD TIME CONDITION TO TOTAL COUNT QUERY ===

    if ($is_admin && !empty($usernames)) {
        $totalSql .= " AND username IN (" . implode(',', array_fill(0, count($usernames), '?')) . ")";
        $totalParams = array_merge($totalParams, $usernames);
    }
    if ($is_admin && !empty($filterUsername)) { $totalSql .= " AND username = ?"; $totalParams[] = $filterUsername; }
    if ($is_admin && !empty($filterBranch)) { $totalSql .= " AND branch = ?"; $totalParams[] = $filterBranch; }
    if (!empty($filterDate)) { $totalSql .= " AND DATE_FORMAT(timestamp, '%m/%d/%Y') = ?"; $totalParams[] = $filterDate; }
    
    $totalStmt = $pdo->prepare($totalSql);
    foreach ($totalParams as $index => $value) {
        $totalStmt->bindValue($index + 1, $value, PDO::PARAM_STR);
    }
    $totalStmt->execute();
    $totalLogs = (int)$totalStmt->fetchColumn();
    $totalPages = ceil($totalLogs / $limit);
    
    function generatePagination($currentPage, $totalPages) {
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


    echo json_encode([
        'logs_html' => $logs_html,
        'pagination_html' => $pagination_html,
        'total_logs' => $totalLogs,
        'status_counts' => $statusCounts
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>