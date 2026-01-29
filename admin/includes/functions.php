function notify_all_staff($conn, $message) {
    // Get all staff users (excluding admin)
    $stmt = $conn->query("SELECT id FROM users WHERE role = 'staff'");
    $staff_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Insert notification for each staff
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (:user_id, :message)");
    foreach ($staff_ids as $user_id) {
        $stmt->execute([
            ':user_id' => $user_id,
            ':message' => $message
        ]);
    }
}
