    <?php
    header('Content-Type: application/json');
    require_once 'db.php'; // Your DB connection file

    $id = isset($_GET['id']) ? trim($_GET['id']) : '';
    if (!$id) {
        echo json_encode(['status' => 'error', 'message' => 'Missing user ID']);
        exit;
    }

    // Example: fetch face descriptor from database (stored as JSON array)
    $stmt = $pdo->prepare("SELECT face_descriptor FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['face_descriptor']) {
        $descriptor = json_decode($row['face_descriptor'], true);
        if (is_array($descriptor)) {
            echo json_encode(['status' => 'success', 'descriptor' => $descriptor]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid descriptor']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No face data found']);
    }