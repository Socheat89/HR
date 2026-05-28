<?php
session_start();
// Include authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $theme = isset($_POST['theme']) ? $_POST['theme'] : 'default';
    $custom_image = isset($_POST['custom_image']) ? $_POST['custom_image'] : '';

    $config = [
        'theme' => $theme,
        'custom_image' => $custom_image
    ];

    $file = __DIR__ . '/includes/theme_config.json';
    
    // Load existing config to preserve history
    $existingConfig = [];
    if (file_exists($file)) {
        $existingConfig = json_decode(file_get_contents($file), true) ?? [];
    }

    $history = $existingConfig['history'] ?? [];

    // Add new image to history if provided and unique
    if (!empty($custom_image)) {
        // Remove if exists to move to top
        $key = array_search($custom_image, $history);
        if ($key !== false) {
            unset($history[$key]);
        }
        // Add to front
        array_unshift($history, $custom_image);
        // Keep only top 10
        $history = array_slice($history, 0, 10);
    }
    
    // Re-index array
    $history = array_values($history);

    $config = [
        'theme' => $theme,
        'custom_image' => $custom_image,
        'history' => $history
    ];
    
    if (file_put_contents($file, json_encode($config))) {
        $_SESSION['success'] = 'Theme updated successfully!';
    } else {
        $_SESSION['error'] = 'Failed to update theme!';
    }
    
    // Redirect back
    header('Location: dashboard.php?view=settings');
    exit();
}
?>
