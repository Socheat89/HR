<?php
// File: shorten.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow CORS for testing; restrict in production
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database configuration
$host = 'localhost';
$dbname = 'samann1_facebook-bot';
$username = 'samann1_facebook-bot'; // Replace with your database username
$password = 'facebook-bot!@#'; // Replace with your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Function to generate a random short code
function generateShortCode($length = 6) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $shortCode = '';
    for ($i = 0; $i < $length; $i++) {
        $shortCode .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $shortCode;
}

// Function to validate URL
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// Function to validate custom alias
function isValidAlias($alias) {
    return empty($alias) || preg_match('/^[a-zA-Z0-9\-]+$/', $alias);
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $longUrl = isset($input['long_url']) ? trim($input['long_url']) : '';
    $alias = isset($input['alias']) ? trim($input['alias']) : '';

    // Validate inputs
    if (empty($longUrl)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Long URL is required']);
        exit;
    }

    if (!isValidUrl($longUrl)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid URL format']);
        exit;
    }

    if (!isValidAlias($alias)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Custom alias can only contain letters, numbers, and hyphens']);
        exit;
    }

    // Determine short code
    $shortCode = $alias ?: generateShortCode();

    try {
        // Check if the short code or alias already exists
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM urls WHERE short_code = ?');
        $stmt->execute([$shortCode]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            if ($alias) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Custom alias already in use']);
                exit;
            } else {
                // Retry generating a new short code if randomly generated one exists
                $attempts = 0;
                $maxAttempts = 5;
                while ($exists && $attempts < $maxAttempts) {
                    $shortCode = generateShortCode();
                    $stmt->execute([$shortCode]);
                    $exists = $stmt->fetchColumn();
                    $attempts++;
                }
                if ($exists) {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Unable to generate unique short code']);
                    exit;
                }
            }
        }

        // Insert into database
        $stmt = $pdo->prepare('INSERT INTO urls (long_url, short_code) VALUES (?, ?)');
        $stmt->execute([$longUrl, $shortCode]);

        // Construct short URL
        $baseUrl = 'https://app.vvc.asia/WIFI/'; // Replace with your actual domain
        $shortUrl = $baseUrl . $shortCode;

        // Return success response
        echo json_encode([
            'success' => true,
            'short_url' => $shortUrl,
            'message' => 'Short URL created successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>