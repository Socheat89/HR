<?php
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'samann1_Fingerprint', 'Fingerprint@2025', 'samann1_fingerprint_db');
$result = $conn->query("SELECT * FROM scan_status_ranges");
$data = [];
while ($row = $result->fetch_assoc()) $data[] = $row;
echo json_encode($data);
$conn->close();
?>