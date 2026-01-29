<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

try {
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $usernames = $_GET['usernames'] ? explode(',', $_GET['usernames']) : [];
    $page = $_GET['page'] ?? 1;

    $limit = 20;
    $offset = ($page - 1) * $limit;

    $pdo = new PDO("mysql:host=localhost;dbname=samann1_Fingerprint", 'samann1_fingerprint_db', 'Fingerprint@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    // Main logs query
    $sql = "SELECT id, username, user_id, action, timestamp, latitude, longitude, status, address FROM scan_logs WHERE 1=1";
    $params = [];

    if ($startDate) {
        $sql .= " AND DATE(timestamp) >= ?";
        $params[] = $startDate;
    }
    if ($endDate) {
        $sql .= " AND DATE(timestamp) <= ?";
        $params[] = $endDate;
    }
    if (!empty($usernames)) {
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $sql .= " AND username IN ($placeholders)";
        $params = array_merge($params, $usernames);
    }
    $sql .= " ORDER BY username ASC, timestamp DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    foreach ($params as $index => $value) {
        if ($index === count($params) - 2) { // Limit
            $stmt->bindValue($index + 1, $value, PDO::PARAM_INT);
        } elseif ($index === count($params) - 1) { // Offset
            $stmt->bindValue($index + 1, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($index + 1, $value);
        }
    }
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($logs as &$log) {
        $log['date'] = date('m/d/Y', strtotime($log['timestamp']));
        $log['time'] = date('h:i:s A', strtotime($log['timestamp']));
    }
    unset($log);

    // Analytics query
    $analyticsSql = "SELECT 
        COUNT(*) as total_scans, 
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late_scans, 
        SUM(CASE WHEN status = 'Good' THEN 1 ELSE 0 END) as good_scans 
        FROM scan_logs WHERE 1=1";
    $analyticsParams = [];
    if ($startDate) {
        $analyticsSql .= " AND DATE(timestamp) >= ?";
        $analyticsParams[] = $startDate;
    }
    if ($endDate) {
        $analyticsSql .= " AND DATE(timestamp) <= ?";
        $analyticsParams[] = $endDate;
    }
    if (!empty($usernames)) {
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $analyticsSql .= " AND username IN ($placeholders)";
        $analyticsParams = array_merge($analyticsParams, $usernames);
    }
    $analyticsStmt = $pdo->prepare($analyticsSql);
    foreach ($analyticsParams as $index => $value) {
        $analyticsStmt->bindValue($index + 1, $value);
    }
    $analyticsStmt->execute();
    $analytics = $analyticsStmt->fetch(PDO::FETCH_ASSOC);

    // Generate logs HTML
    $logs_html = '<table class="table table-striped table-bordered"><thead class="table-dark"><tr><th>User ID</th><th>ឈ្មោះ</th><th>ប្រភេទស្កេន</th><th>ថ្ងៃខែឆ្នាំ</th><th>ម៉ោង</th><th>ទីតាំង</th><th>ស្ថានភាព</th><th>អាសយដ្ឋាន</th><th>សកម្មភាព</th></tr></thead><tbody>';
    if (empty($logs)) {
        $logs_html .= '<tr><td colspan="9" class="text-center">មិនមានទិន្នន័យប្រវត្តិសម្រាប់ការស្វែងរកនេះ!</td></tr>';
    } else {
        foreach ($logs as $log) {
            $location = isset($log['latitude']) && isset($log['longitude']) && $log['latitude'] && $log['longitude']
                ? "<a href='https://www.google.com/maps?q=" . urlencode($log['latitude'] . ',' . $log['longitude']) . "' target='_blank'><i class='fas fa-map-marker-alt location-icon'></i> " . htmlspecialchars($log['latitude'] . ', ' . $log['longitude']) . "</a>"
                : 'មិនមានទីតាំង';
            $logs_html .= "<tr><td>" . htmlspecialchars($log['user_id'] ?? 'មិនមាន') . "</td><td>" . htmlspecialchars($log['username'] ?? 'មិនមាន') . "</td><td>" . htmlspecialchars($log['action'] ?? 'មិនមាន') . "</td><td>" . htmlspecialchars($log['date'] ?? 'មិនមាន') . "</td><td>" . htmlspecialchars($log['time'] ?? 'មិនមាន') . "</td><td>$location</td><td>" . htmlspecialchars($log['status'] ?? 'មិនមាន') . "</td><td>" . htmlspecialchars($log['address'] ?? 'មិនមាន') . "</td><td><a href='edit_log.php?id=" . htmlspecialchars($log['id']) . "' class='btn btn-warning btn-sm'><i class='fa-solid fa-edit'></i> កែ</a> <a href='delete_log.php?id=" . htmlspecialchars($log['id']) . "' class='btn btn-danger btn-sm' onclick='return confirm(\"តើអ្នកប្រាកដជាចង់លុបមែនទេ?\")'><i class='fa-solid fa-trash'></i> លុប</a></td></tr>";
        }
    }
    $logs_html .= '</tbody></table>';

    // Pagination
    $totalSql = "SELECT COUNT(*) FROM scan_logs WHERE 1=1";
    $totalParams = [];
    if ($startDate) {
        $totalSql .= " AND DATE(timestamp) >= ?";
        $totalParams[] = $startDate;
    }
    if ($endDate) {
        $totalSql .= " AND DATE(timestamp) <= ?";
        $totalParams[] = $endDate;
    }
    if (!empty($usernames)) {
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $totalSql .= " AND username IN ($placeholders)";
        $totalParams = array_merge($totalParams, $usernames);
    }
    $totalStmt = $pdo->prepare($totalSql);
    foreach ($totalParams as $index => $value) {
        $totalStmt->bindValue($index + 1, $value);
    }
    $totalStmt->execute();
    $totalLogs = $totalStmt->fetchColumn();
    $totalPages = ceil($totalLogs / $limit);

    $pagination_html = '<nav><ul class="pagination">';
    for ($i = 1; $i <= $totalPages; $i++) {
        $pagination_html .= "<li class='page-item " . ($i == $page ? 'active' : '') . "'><a class='page-link' href='?page=$i'>$i</a></li>";
    }
    $pagination_html .= '</ul></nav>';

    // JSON response with analytics
    echo json_encode([
        'logs_html' => $logs_html,
        'pagination_html' => $pagination_html,
        'total_scans' => $analytics['total_scans'],
        'late_scans' => $analytics['late_scans'],
        'good_scans' => $analytics['good_scans']
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    exit;
}
?>