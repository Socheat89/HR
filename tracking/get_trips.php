<?php
header('Content-Type: application/json');
$host = 'localhost'; $dbname = 'samann1_admin_panel'; $username = 'root'; $password = '';

$conn = new mysqli($host, $username, $password, $dbname);
$conn->set_charset("utf8mb4");

// בבבבבבבבבבבבבבבבבבב¶בב session בב¶בבבבב¶בבבבבבבבב¶בבבבבבבבבבבבב (UTC+7)
$conn->query("SET time_zone = '+07:00'");

if ($conn->connect_error) { 
    die(json_encode([])); 
}

$trips = [];

// MODIFIED SQL QUERY
// We use LEFT JOIN to include trips even if the driver's location is not yet available.
// We select lat/lng from the locations table and alias them as current_lat/current_lng.
$sql = "SELECT 
            at.id, 
            at.start_lat, 
            at.start_lng, 
            at.end_lat, 
            at.end_lng, 
            at.customer_name, 
            at.start_time,
            dl.lat AS current_lat,
            dl.lng AS current_lng
        FROM 
            active_trips AS at
        LEFT JOIN 
            driver_locations AS dl ON at.driver_id = dl.driver_id
        ORDER BY 
            at.start_time DESC";

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Convert null values from the DB to actual null in JSON for easier JS handling
        $row['current_lat'] = $row['current_lat'] === null ? null : (float)$row['current_lat'];
        $row['current_lng'] = $row['current_lng'] === null ? null : (float)$row['current_lng'];
        $trips[] = $row;
    }
}

echo json_encode($trips);
$conn->close();
?>
