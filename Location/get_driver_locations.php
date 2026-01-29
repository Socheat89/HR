<?php
$conn = new mysqli("localhost", "samann1_location_db", "samann1_location_db", "samann1_location_db");
$sql = "SELECT id, user_id, name, latitude, longitude, accuracy, timestamp FROM locations ORDER BY timestamp DESC";
$result = $conn->query($sql);
$locations = [];
while ($row = $result->fetch_assoc()) {
    $locations[] = $row;
}
echo json_encode($locations);
$conn->close();
?>