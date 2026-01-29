<?php
$pdo = new PDO("mysql:host=localhost;dbname=samann1_admin_panel", "samann1_admin_panel", "admin_panel@2025");

// ទាញយកទិន្នន័យពីtable employee_locations
$stmt = $pdo->query("SELECT phone, latitude, longitude FROM employee_locations");
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// កំណត់ header ដើម្បីទទួលទិន្នន័យ JSON
header('Content-Type: application/json');

// ផ្ញើទិន្នន័យនៅក្នុងទ្រង់ទ្រាយ JSON
echo json_encode($locations);
?>
