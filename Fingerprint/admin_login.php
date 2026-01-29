<?php
header('Content-Type: application/json');
$conn = new mysqli('localhost', 'samann1_Fingerprint', 'Fingerprint@2025', 'samann1_fingerprint_db');
$data = json_decode(file_get_contents('php://input'), true);

$username = $data['username'];
$password = $data['password'];

$stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
  if (password_verify($password, $row['password'])) {
    echo json_encode(['status' => 'success']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'លេខសម្ងាត់មិនត្រឹមត្រូវ!']);
  }
} else {
  echo json_encode(['status' => 'error', 'message' => 'ឈ្មោះអ្នកប្រើមិនត្រឹមត្រូវ!']);
}

$stmt->close();
$conn->close();
?>