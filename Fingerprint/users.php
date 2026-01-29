<?php
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'samann1_Fingerprint', 'Fingerprint@2025', 'samann1_fingerprint_db');

if (isset($_GET['id'])) {
  $id = $_GET['id'];
  $result = $conn->query("SELECT * FROM users WHERE id = $id");
  echo json_encode($result->fetch_assoc());
} else {
  $result = $conn->query("SELECT * FROM users");
  $users = [];
  while ($row = $result->fetch_assoc()) {
    $users[] = $row;
  }
  echo json_encode($users);
}
$conn->close();
?>