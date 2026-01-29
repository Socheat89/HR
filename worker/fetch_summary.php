<?php
session_start();

// Enable error logging
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/path/to/php-error.log'); // Adjust path as needed

// CSRF token validation
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get POST data
$rawInput = file_get_contents('php://input');
error_log("Raw input: " . $rawInput); // Log raw input for debugging
$input = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    http_response_code(500);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

$start_date = $input['start_date'] ?? '';
$end_date = $input['end_date'] ?? '';
$usernames = $input['usernames'] ?? [];
$branches = $input['branches'] ?? [];
$filter_username = $input['filter_username'] ?? '';
$filter_branch = $input['filter_branch'] ?? '';
$user_type = $_SESSION['user_type'] ?? 'admin';

// Build WHERE clause
$whereClauses = [];
$params = [];

if ($user_type === 'sub_user' && isset($_SESSION['branch'])) {
    $whereClauses[] = "sl.branch = :branch";
    $params[':branch'] = $_SESSION['branch'];
}

if (!empty($start_date)) {
    $whereClauses[] = "DATE(sl.scan_time) >= :start_date";
    $params[':start_date'] = $start_date;
}

if (!empty($end_date)) {
    $whereClauses[] = "DATE(sl.scan_time) <= :end_date";
    $params[':end_date'] = $end_date;
}

if (!empty($usernames)) {
    $placeholders = implode(',', array_fill(0, count($usernames), ':username' . count($params)));
    $whereClauses[] = "sl.username IN ($placeholders)";
    foreach ($usernames as $index => $username) {
        $params[":username$index"] = $username;
    }
}

if (!empty($branches)) {
    $placeholders = implode(',', array_fill(0, count($branches), ':branch' . count($params)));
    $whereClauses[] = "sl.branch IN ($placeholders)";
    foreach ($branches as $index => $branch) {
        $params[":branch" . (count($params) + $index)] = $branch;
    }
}

if (!empty($filter_username)) {
    $whereClauses[] = "sl.username = :filter_username";
    $params[':filter_username'] = $filter_username;
}

if (!empty($filter_branch)) {
    $whereClauses[] = "sl.branch = :filter_branch";
    $params[':filter_branch'] = $filter_branch;
}

$whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Check if users table exists
$usersTableExists = $pdo->query("SHOW TABLES LIKE 'users'")->rowCount() > 0;

// User Details Table
try {
    if ($usersTableExists) {
        $userQuery = "SELECT DISTINCT u.id, u.username, u.gender, u.role 
                      FROM users u 
                      JOIN scan_logs sl ON u.username = sl.username 
                      $whereClause 
                      ORDER BY u.id";
    } else {
        $userQuery = "SELECT DISTINCT id, username, gender, role 
                      FROM scan_logs 
                      $whereClause 
                      WHERE username IS NOT NULL 
                      ORDER BY id";
    }
    $userStmt = $pdo->prepare($userQuery);
    foreach ($params as $key => $value) {
        $userStmt->bindValue($key, $value);
    }
    $userStmt->execute();
    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("User query error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'User query failed: ' . $e->getMessage()]);
    exit;
}

// Late Arrivals Table
try {
    $lateQuery = "SELECT sl.username, 
                         SUM(CASE WHEN TIME(sl.scan_time) > '08:00:00' AND TIME(sl.scan_time) <= '08:15:00' THEN 1 ELSE 0 END) AS late_under_15,
                         SUM(CASE WHEN TIME(sl.scan_time) > '08:15:00' AND TIME(sl.scan_time) <= '09:00:00' THEN 1 ELSE 0 END) AS late_15_to_60,
                         SUM(CASE WHEN TIME(sl.scan_time) > '09:00:00' THEN 1 ELSE 0 END) AS late_over_60
                  FROM scan_logs sl 
                  $whereClause 
                  GROUP BY sl.username";
    $lateStmt = $pdo->prepare($lateQuery);
    foreach ($params as $key => $value) {
        $lateStmt->bindValue($key, $value);
    }
    $lateStmt->execute();
    $lateData = $lateStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Late query error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Late query failed: ' . $e->getMessage()]);
    exit;
}

// Total Summary Table
try {
    $totalQuery = "SELECT COUNT(DISTINCT sl.username) AS total_users, 
                          COUNT(*) AS total_scans,
                          SUM(CASE WHEN TIME(sl.scan_time) > '08:00:00' THEN 1 ELSE 0 END) AS total_late,
                          SUM(CASE WHEN TIME(sl.scan_time) > '08:00:00' AND TIME(sl.scan_time) <= '08:15:00' THEN 1 ELSE 0 END) AS total_under_15,
                          SUM(CASE WHEN TIME(sl.scan_time) > '08:15:00' AND TIME(sl.scan_time) <= '09:00:00' THEN 1 ELSE 0 END) AS total_15_to_60,
                          SUM(CASE WHEN TIME(sl.scan_time) > '09:00:00' THEN 1 ELSE 0 END) AS total_over_60
                   FROM scan_logs sl 
                   $whereClause";
    $totalStmt = $pdo->prepare($totalQuery);
    foreach ($params as $key => $value) {
        $totalStmt->bindValue($key, $value);
    }
    $totalStmt->execute();
    $totalData = $totalStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Total query error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Total query failed: ' . $e->getMessage()]);
    exit;
}

// Generate HTML for tables
$summary_html = '<h6 class="mb-3">តារ័ងព័ត៌មានបុគ្គលិក</h6>';
$summary_html .= '<div class="table-responsive mb-4">';
$summary_html .= '<table class="table table-bordered summary-table">';
$summary_html .= '<thead><tr><th>អគ្គលេខ</th><th>ឈ្មោះ</th><th>ភេទ</th><th>តួនាទី</th></tr></thead>';
$summary_html .= '<tbody>';
foreach ($users as $user) {
    $summary_html .= '<tr>';
    $summary_html .= '<td>' . htmlspecialchars($user['id'] ?? 'N/A') . '</td>';
    $summary_html .= '<td>' . htmlspecialchars($user['username']) . '</td>';
    $summary_html .= '<td>' . htmlspecialchars($user['gender'] ?? 'មិនបញ្ជាក់') . '</td>';
    $summary_html .= '<td>' . htmlspecialchars($user['role'] ?? 'មិនបញ្ជាក់') . '</td>';
    $summary_html .= '</tr>';
}
$summary_html .= '</tbody></table></div>';

$summary_html .= '<h6 class="mb-3">តារ័ងមកយឺត</h6>';
$summary_html .= '<div class="table-responsive mb-4">';
$summary_html .= '<table class="table table-bordered summary-table">';
$summary_html .= '<thead><tr><th>ឈ្មោះ</th><th>ក្រោម ១៥ នាទី</th><th>ចាប់ពី ១៥ នាទី</th><th>ចាប់ពី ១ ម៉ោង</th></tr></thead>';
$summary_html .= '<tbody>';
foreach ($lateData as $row) {
    $summary_html .= '<tr>';
    $summary_html .= '<td>' . htmlspecialchars($row['username']) . '</td>';
    $summary_html .= '<td>' . htmlspecialchars($row['late_under_15']) . '</td>';
    $summary_html .= '<td>' . htmlspecialchars($row['late_15_to_60']) . '</td>';
    $summary_html .= '<td>' . htmlspecialchars($row['late_over_60']) . '</td>';
    $summary_html .= '</tr>';
}
$summary_html .= '</tbody></table></div>';

$summary_html .= '<h6 class="mb-3">សរុបទិន្ន័យទាំងអស់</h6>';
$summary_html .= '<div class="table-responsive">';
$summary_html .= '<table class="table table-bordered summary-table">';
$summary_html .= '<thead><tr><th>ចំនួនបុគ្គលិក</th><th>ចំនួនស្កេនសរុប</th><th>ចំនួនមកយឺតសរុប</th><th>ក្រោម ១៥ នាទី</th><th>ចាប់ពី ១៥ នាទី</th><th>ចាប់ពី ១ ម៉ោង</th></tr></thead>';
$summary_html .= '<tbody>';
$summary_html .= '<tr>';
$summary_html .= '<td>' . htmlspecialchars($totalData['total_users'] ?? 0) . '</td>';
$summary_html .= '<td>' . htmlspecialchars($totalData['total_scans'] ?? 0) . '</td>';
$summary_html .= '<td>' . htmlspecialchars($totalData['total_late'] ?? 0) . '</td>';
$summary_html .= '<td>' . htmlspecialchars($totalData['total_under_15'] ?? 0) . '</td>';
$summary_html .= '<td>' . htmlspecialchars($totalData['total_15_to_60'] ?? 0) . '</td>';
$summary_html .= '<td>' . htmlspecialchars($totalData['total_over_60'] ?? 0) . '</td>';
$summary_html .= '</tr>';
$summary_html .= '</tbody></table></div>';

header('Content-Type: application/json');
echo json_encode(['summary_html' => $summary_html]);
?>