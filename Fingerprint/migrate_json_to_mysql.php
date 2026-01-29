<?php
// Database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=samann1_fingerprint_db', 'samann1_Fingerprint', 'Fingerprint@2025', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Load JSON data
$dataFile = 'data-2.json';
if (!file_exists($dataFile)) {
    die("JSON file not found!");
}

$data = json_decode(file_get_contents($dataFile), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Invalid JSON data: " . json_last_error_msg());
}

// Migrate folders
try {
    $pdo->beginTransaction();
    foreach ($data['folders'] as $folder) {
        $stmt = $pdo->prepare("INSERT INTO folders (id, name) VALUES (?, ?)");
        $stmt->execute([$folder['id'], $folder['name']]);
    }

    // Migrate users
    foreach ($data['users'] as $user) {
        $stmt = $pdo->prepare("INSERT INTO users (id, username, department, position, branch, workplace, folder, time_settings) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user['id'],
            $user['username'],
            $user['department'] ?? null,
            $user['position'] ?? null,
            $user['branch'] ?? null,
            $user['workplace'] ?? null,
            $user['folder'] ?? null,
            json_encode($user['time_settings'] ?? [])
        ]);
    }

    // Migrate allowed locations
    foreach ($data['allowedLocations'] as $location) {
        $stmt = $pdo->prepare("INSERT INTO allowed_locations (id, name, branch, latitude, longitude, users) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $location['id'],
            $location['name'],
            $location['branch'] ?? null,
            $location['latitude'] ?? null,
            $location['longitude'] ?? null,
            json_encode($location['users'] ?? [])
        ]);
    }

    // Migrate active users
    foreach ($data['activeUsers'] as $activeUser) {
        $stmt = $pdo->prepare("INSERT INTO active_users (token, user_id, login_time) VALUES (?, ?, ?)");
        $stmt->execute([
            $activeUser['token'],
            $activeUser['user_id'],
            $activeUser['login_time']
        ]);
    }

    // Migrate settings
    if (isset($data['settings']['maxTokensPerUser'])) {
        $stmt = $pdo->prepare("INSERT INTO settings (key_name, value) VALUES (?, ?)");
        $stmt->execute(['maxTokensPerUser', json_encode(['value' => $data['settings']['maxTokensPerUser']])]);
    }

    $pdo->commit();
    echo "Data migration completed successfully!";
} catch (Exception $e) {
    $pdo->rollBack();
    die("Migration failed: " . $e->getMessage());
}
?>