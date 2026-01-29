<?php
include 'db.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
  $id = intval($_GET['id']);
  $result = $conn->query("SELECT status FROM tasks WHERE id = $id LIMIT 1");
  if ($result && $row = $result->fetch_assoc()) {
    $new_status = ($row['status'] == 'completed') ? 'pending' : 'completed';
    $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $new_status, $id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    exit;
  }
}

echo json_encode(['success' => false]);
?>
