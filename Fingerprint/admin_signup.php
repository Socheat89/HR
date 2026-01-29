<?php
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'samann1_Fingerprint', 'Fingerprint@2025', 'samann1_fingerprint_db');
$data = json_decode(file_get_contents('php://input'), true);

$username = $data['username'];
$password = password_hash($data['password'], PASSWORD_DEFAULT); // Hash the password

// Check if username already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
  echo json_encode(['status' => 'error', 'message' => 'ឈ្មោះអ្នកប្រើនេះមានរួចហើយ!']);
} else {
  $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
  $stmt->bind_param("ss", $username, $password);
  if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'មានបញ្ហាក្នុងការចុះឈ្មោះ!']);
  }
}

$stmt->close();
$conn->close();
?>