    <?php
    header('Content-Type: application/json');

    // Simple authentication (optional, adjust as needed)
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        exit;
    }

    $userId = isset($_GET['id']) ? trim($_GET['id']) : '';
    if (!$userId) {
        echo json_encode(['status' => 'error', 'message' => 'Missing user id']);
        exit;
    }

    // Path to face descriptors storage (adjust as needed)
    $storageDir = __DIR__ . '/face_descriptors';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0777, true);
    }

    $file = $storageDir . '/' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $userId) . '.json';

    if (!file_exists($file)) {
        echo json_encode(['status' => 'error', 'message' => 'No face data found']);
        exit;
    }

    $data = json_decode(file_get_contents($file), true);
    if (!$data || !isset($data['descriptor']) || !is_array($data['descriptor'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid face data']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'descriptor' => $data['descriptor']
    ]);
    exit;
    ?>
