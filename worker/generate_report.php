<?php
session_start();

// Start output buffering to prevent stray output
ob_start();

// Disable display errors and log to file
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
error_reporting(E_ALL);

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'មានបញ្ហាក្នុងការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ']);
    ob_end_flush();
    exit;
}

// Check authentication
$logged_in = false;
$user_type = 'admin';
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $logged_in = true;
} elseif (isset($_SESSION['sub_user_logged_in']) && $_SESSION['sub_user_logged_in'] === true) {
    $logged_in = true;
    $user_type = 'sub_user';
}

if (!$logged_in) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'មិនមានសិទ្ធិចូលប្រើ']);
    ob_end_flush();
    exit;
}

// Get POST data
$report_type = $_POST['report_type'] ?? '';
$date_value = $_POST['date_value'] ?? '';
$usernames = isset($_POST['usernames']) && !empty($_POST['usernames']) ? explode(',', $_POST['usernames']) : [];
$branches = isset($_POST['branches']) && !empty($_POST['branches']) ? explode(',', $_POST['branches']) : [];

// Validate inputs
$valid_report_types = ['daily', 'weekly', 'monthly', 'yearly'];
if (!in_array($report_type, $valid_report_types) || empty($date_value)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ប្រភេទរបាយការណ៍ ឬ កាលបរិច្ឆេទមិនត្រឹមត្រូវ']);
    ob_end_flush();
    exit;
}

// Calculate date range
$start_date = '';
$end_date = '';
try {
    switch ($report_type) {
        case 'daily':
            if (!DateTime::createFromFormat('Y-m-d', $date_value)) {
                throw new Exception('ទម្រង់ថ្ងៃមិនត្រឹមត្រូវ');
            }
            $start_date = $date_value;
            $end_date = $date_value;
            break;
        case 'weekly':
            if (!preg_match('/^\d{4}-W\d{2}$/', $date_value)) {
                throw new Exception('ទម្រង់សប្តាហ៍មិនត្រឹមត្រូវ');
            }
            $week = explode('-W', $date_value);
            $year = $week[0];
            $week_num = ltrim($week[1], '0');
            $start_date = date('Y-m-d', strtotime("{$year}-W{$week_num}-1")); // Monday
            $end_date = date('Y-m-d', strtotime("{$year}-W{$week_num}-7")); // Sunday
            break;
        case 'monthly':
            if (!DateTime::createFromFormat('Y-m', $date_value)) {
                throw new Exception('ទម្រង់ខែមិនត្រឹមត្រូវ');
            }
            $start_date = date('Y-m-01', strtotime($date_value . '-01'));
            $end_date = date('Y-m-t', strtotime($date_value . '-01'));
            break;
        case 'yearly':
            if (!preg_match('/^\d{4}$/', $date_value)) {
                throw new Exception('ទម្រង់ឆ្នាំមិនត្រឹមត្រូវ');
            }
            $start_date = $date_value . '-01-01';
            $end_date = $date_value . '-12-31';
            break;
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
    ob_end_flush();
    exit;
}

// Build query
$whereClause = "WHERE timestamp BETWEEN :start_date AND :end_date";
$params = [
    ':start_date' => $start_date . ' 00:00:00',
    ':end_date' => $end_date . ' 23:59:59'
];

if ($user_type === 'sub_user' && isset($_SESSION['branch'])) {
    $whereClause .= " AND branch = :branch";
    $params[':branch'] = $_SESSION['branch'];
}

if (!empty($usernames)) {
    $placeholders = implode(',', array_fill(0, count($usernames), ':username' . count($params)));
    $whereClause .= " AND username IN ($placeholders)";
    foreach ($usernames as $i => $username) {
        $params[':username' . ($i + count($params) - count($usernames))] = $username;
    }
}

if (!empty($branches)) {
    $placeholders = implode(',', array_fill(0, count($branches), ':branch' . count($params)));
    $whereClause .= " AND branch IN ($placeholders)";
    foreach ($branches as $i => $branch) {
        $params[':branch' . ($i + count($params) - count($branches))] = $branch;
    }
}

// Fetch report data
try {
    // Total scans
    $totalStmt = $pdo->prepare("SELECT COUNT(*) as total FROM scan_logs $whereClause");
    $totalStmt->execute($params);
    $total_logs = $totalStmt->fetchColumn();

    // Status counts
    $statusStmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM scan_logs $whereClause GROUP BY status");
    $statusStmt->execute($params);
    $status_counts = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // User activity
    $userStmt = $pdo->prepare("SELECT username, branch, COUNT(*) as scan_count, 
                              SUM(CASE WHEN status = 'Good' THEN 1 ELSE 0 END) as good_count,
                              SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_count
                              FROM scan_logs $whereClause 
                              GROUP BY username, branch 
                              ORDER BY branch, username");
    $userStmt->execute($params);
    $user_activity = $userStmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate report HTML
    $report_html = '<h3>របាយការណ៍' . htmlspecialchars($report_type === 'daily' ? 'ប្រចាំថ្ងៃ' : ($report_type === 'weekly' ? 'ប្រចាំសប្តាហ៍' : ($report_type === 'monthly' ? 'ប្រចាំខែ' : 'ប្រចាំឆ្នាំ'))) . '</h3>';
    $report_html .= '<p>កាលបរិច្ឆេទ: ' . htmlspecialchars($start_date) . ' ដល់ ' . htmlspecialchars($end_date) . '</p>';
    $report_html .= '<div class="stats-grid">';
    $report_html .= '<div class="stat-box"><strong>សរុបស្កេន</strong><span class="stat-number">' . $total_logs . '</span></div>';
    foreach ($status_counts as $status => $count) {
        $report_html .= '<div class="stat-box"><strong>' . htmlspecialchars($status ?: 'មិនមានស្ថានភាព') . '</strong><span class="stat-number">' . $count . '</span></div>';
    }
    $report_html .= '</div>';

    $report_html .= '<h4>សកម្មភាពអ្នកប្រើប្រាស់</h4>';
    $report_html .= '<table class="table table-striped table-bordered">';
    $report_html .= '<thead class="table-primary"><tr><th>ឈ្មោះ</th><th>សាខា</th><th>ចំនួនស្កេន</th><th>ស្ថានភាពល្អ</th><th>ស្ថានភាពយឺត</th></tr></thead>';
    $report_html .= '<tbody>';
    if (empty($user_activity)) {
        $report_html .= '<tr><td colspan="5" class="text-center">មិនមានទិន្នន័យសម្រាប់របាយការណ៍នេះ!</td></tr>';
    } else {
        foreach ($user_activity as $user) {
            $report_html .= '<tr>';
            $report_html .= '<td>' . htmlspecialchars($user['username'] ?? 'មិនមាន') . '</td>';
            $report_html .= '<td>' . htmlspecialchars($user['branch'] ?? 'មិនមាន') . '</td>';
            $report_html .= '<td>' . $user['scan_count'] . '</td>';
            $report_html .= '<td>' . $user['good_count'] . '</td>';
            $report_html .= '<td>' . $user['late_count'] . '</td>';
            $report_html .= '</tr>';
        }
    }
    $report_html .= '</tbody></table>';

    // Export URL
    $export_url = "export_report.php?report_type=" . urlencode($report_type) . "&date_value=" . urlencode($date_value) . 
                  "&usernames=" . urlencode(implode(',', $usernames)) . "&branches=" . urlencode(implode(',', $branches));

    // Clear output buffer and send JSON
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'report_html' => $report_html,
        'export_url' => $export_url
    ]);
} catch (Exception $e) {
    error_log("Report generation error: " . $e->getMessage());
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['error' => 'មានបញ្ហាក្នុងការបង្កើតរបាយការណ៍: ' . $e->getMessage()]);
}
exit;
?>