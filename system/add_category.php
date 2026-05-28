<?php
$host = 'localhost';
$dbname = 'samann1_admin_panel';
$username = 'root';
$password = '';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Connection error']));
}

if (isset($_POST['name'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $conn->query("INSERT INTO categories (name) VALUES ('$name')");
    $id = $conn->insert_id;
    echo json_encode(['success' => true, 'id' => $id]);
} else {
    echo json_encode(['success' => false]);
}
?>

