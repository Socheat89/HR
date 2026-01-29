<?php
// get_transfer_history.php
header('Content-Type: application/json; charset=utf-8');

require_once 'db_connect.php'; 

$response = [
    'success' => false,
    'message' => 'Invalid request. Item ID is required.',
    'data' => []
];

if (!isset($_GET['item_id']) || !is_numeric($_GET['item_id'])) {
    echo json_encode($response);
    exit;
}

$itemId = (int)$_GET['item_id'];
$year = $_GET['year'] ?? null;
$month = $_GET['month'] ?? null;
$day = $_GET['day'] ?? null;

try {
    // START: កែប្រែ SQL Query ដើម្បីទាញយកឈ្មោះអ្នកទទួល (អ្នកស្នើសុំ)
    // យើងនឹង JOIN ទៅកាន់ stock_request រួច JOIN ទៅកាន់ users ម្តងទៀតដើម្បីយកឈ្មោះអ្នកស្នើសុំ
    $sql = "SELECT 
                st.to_location, 
                st.quantity_transferred, 
                st.transfer_date, 
                COALESCE(u_requester.full_name, 'ផ្ទេរដោយផ្ទាល់') AS receiver_name
            FROM stock_transfers st
            LEFT JOIN stock_request sr ON st.stock_request_id = sr.id
            LEFT JOIN users u_requester ON sr.user_id = u_requester.id
            WHERE st.stock_item_id = ?";
    // END: បញ្ចប់ការកែប្រែ SQL Query
    
    $params = [$itemId];

    // បន្ថែមលក្ខខណ្ឌតម្រង (filter conditions)
    if (!empty($year) && is_numeric($year)) {
        $sql .= " AND YEAR(st.transfer_date) = ?";
        $params[] = (int)$year;
    }
    if (!empty($month) && is_numeric($month)) {
        $sql .= " AND MONTH(st.transfer_date) = ?";
        $params[] = (int)$month;
    }
    if (!empty($day) && is_numeric($day)) {
        $sql .= " AND DAY(st.transfer_date) = ?";
        $params[] = (int)$day;
    }
    
    $sql .= " ORDER BY st.transfer_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $history_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($history_data) {
        $response['success'] = true;
        $response['message'] = 'History data found.';
        $response['data'] = $history_data;
    } else {
        $response['success'] = true; 
        $response['message'] = 'No transfer history found for the selected criteria.';
    }

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);