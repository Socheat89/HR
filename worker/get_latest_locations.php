<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    http_response_code(401);
    echo json_encode(['error' => 'Session expired']);
    exit;
}
$_SESSION['last_activity'] = time();

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=" . getenv('DB_NAME') . ";charset=utf8mb4",
        getenv('DB_USER'),
        getenv('DB_PASS')
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Optional: Fetch locations since lastTimestamp
    $lastTimestamp = isset($_GET['lastTimestamp']) ? $_GET['lastTimestamp'] : date('Y-m-d H:i:s', strtotime('-5 minutes'));
    
    // Use ROW_NUMBER to select the most recent record per coordinate pair
    $query = "
        SELECT id, username, branch, folder, 
               DATE_FORMAT(timestamp, '%Y-%m-%d %H:%i:%s') AS timestamp, 
               latitude, longitude, status, address
        FROM (
            SELECT id, username, branch, folder, timestamp, latitude, longitude, status, address,
                   ROW_NUMBER() OVER (PARTITION BY latitude, longitude ORDER BY timestamp DESC) AS rn
            FROM scan_logs
            WHERE latitude IS NOT NULL 
                  AND longitude IS NOT NULL 
                  AND timestamp > :lastTimestamp
        ) t
        WHERE rn = 1
        ORDER BY timestamp DESC
        LIMIT 20";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':lastTimestamp', $lastTimestamp);
    $stmt->execute();
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Validate coordinates
    $locations = array_filter($locations, function($loc) {
        return is_numeric($loc['latitude']) && is_numeric($loc['longitude']) &&
               $loc['latitude'] >= -90 && $loc['latitude'] <= 90 &&
               $loc['longitude'] >= -180 && $loc['longitude'] <= 180;
    });

    echo json_encode(array_values($locations), JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log("Database error in get_latest_locations.php: " . $e->getMessage() . " | IP: " . $_SERVER['REMOTE_ADDR']);
    http_response_code(500);
    echo json_encode(['error' => 'មានបញ្ហាក្នុងការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ។']);
} catch (Exception $e) {
    error_log("General error in get_latest_locations.php: " . $e->getMessage() . " | IP: " . $_SERVER['REMOTE_ADDR']);
    http_response_code(500);
    echo json_encode(['error' => 'មានបញ្ហាទូទៅ។']);
}
?>