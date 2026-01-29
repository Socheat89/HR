<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connect.php';

date_default_timezone_set('Asia/Phnom_Penh');

// Set headers for JSON response and CORS
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *'); // Adjust for production

// Check database connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Get and sanitize input parameters
$user_name = isset($_GET['user_name']) ? $conn->real_escape_string($_GET['user_name']) : '';
$start_date = isset($_GET['start_date']) ? $conn->real_escape_string($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? $conn->real_escape_string($_GET['end_date']) : '';

// Build the SQL query
$sql = "SELECT id, user_name, scan_type, 
        DATE_FORMAT(scan_time, '%Y-%m-%d %H:%i:%s') AS scan_time, 
        latitude, longitude 
        FROM scan_logs 
        WHERE 1=1";

if (!empty($user_name)) {
    $sql .= " AND user_name = '$user_name'";
}

if (!empty($start_date) && !empty($end_date)) {
    $sql .= " AND scan_time BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
}

$sql .= " ORDER BY scan_time DESC";

// Execute query
$result = $conn->query($sql);
$logs = [];

if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed: ' . $conn->error]);
    $conn->close();
    exit;
}

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = [
            'id' => (int) $row['id'],
            'user_name' => $row['user_name'],
            'scan_type' => $row['scan_type'],
            'scan_time' => $row['scan_time'],
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude']
        ];
    }
}

$conn->close();
echo json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>