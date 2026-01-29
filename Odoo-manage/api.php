<?php
// Set headers for CORS
header('Content-Type: application/json');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Configuration Section ---
$dbHost = 'localhost';
$dbName = 'samann1_scan_logs_worker_db';
$dbUser = 'samann1_scan_logs_worker_db';
$dbPass = 'scan_logs_worker_db@2025';

$uploadDir = 'uploads/';
$baseURL = sprintf(
    "%s://%s%s",
    (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https' : 'http',
    $_SERVER['SERVER_NAME'],
    rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/'
);
$imageBaseURL = $baseURL . $uploadDir;

// --- Database Connection ---
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// --- Helper Functions (UPDATED) ---
function isUrl($string) {
    return filter_var($string, FILTER_VALIDATE_URL) !== false;
}

function deleteImageFile($imagePath, $uploadDir) {
    // Only delete if it's NOT a URL and the file exists
    if (empty($imagePath) || isUrl($imagePath)) {
        return;
    }
    $filePath = $uploadDir . basename($imagePath);
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
}

// --- API Routing ---
$method = $_SERVER['REQUEST_METHOD'];
$path_str = $_SERVER['PATH_INFO'] ?? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = explode('/', trim(str_replace(dirname($_SERVER['SCRIPT_NAME']), '', $path_str), '/'));
$id = $path[0] ?? null;

switch ($method) {
    case 'GET':
        try {
            if ($id && is_numeric($id)) {
                $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
                $stmt->execute([$id]);
                $product = $stmt->fetch();

                if ($product) {
                    // UPDATED: Conditionally build the image URL
                    if (!empty($product['image']) && !isUrl($product['image'])) {
                        $product['image'] = $imageBaseURL . $product['image'];
                    }
                    echo json_encode($product);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Product not found']);
                }
            } else {
                $stmt = $pdo->query('SELECT * FROM products ORDER BY id DESC');
                $products = $stmt->fetchAll();
                // UPDATED: Conditionally build the image URL for all items
                foreach ($products as &$product) {
                    if (!empty($product['image']) && !isUrl($product['image'])) {
                        $product['image'] = $imageBaseURL . $product['image'];
                    }
                }
                echo json_encode($products);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error fetching products: ' . $e->getMessage()]);
        }
        break;

    case 'POST': // UPDATED: Handles Create/Update with both Upload and URL
        if (!isset($_POST['price'], $_POST['rating'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing price or rating fields']);
            exit;
        }

        $price = $_POST['price'];
        $rating = $_POST['rating'];
        $productId = !empty($_POST['id']) ? $_POST['id'] : null;
        $imageDbValue = null; // Final value for the 'image' column

        // UPDATED: Priority logic for image source
        // 1. Check for file upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileTmpPath = $_FILES['image']['tmp_name'];
            $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $newFileName = uniqid('img_', true) . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $imageDbValue = $newFileName;
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to move uploaded file.']);
                exit;
            }
        } 
        // 2. If no file, check for URL input
        else if (isset($_POST['image_url']) && !empty($_POST['image_url'])) {
            $imageUrl = $_POST['image_url'];
            if (isUrl($imageUrl)) {
                $imageDbValue = $imageUrl;
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid image URL format.']);
                exit;
            }
        }

        try {
            if ($productId) { // --- UPDATE ---
                $stmt = $pdo->prepare('SELECT image FROM products WHERE id = ?');
                $stmt->execute([$productId]);
                $oldImage = $stmt->fetchColumn();

                if ($imageDbValue === null && isset($_POST['existingImage'])) {
                    // Keep existing image if no new one is provided
                    $imageDbValue = isUrl($_POST['existingImage']) ? $_POST['existingImage'] : basename($_POST['existingImage']);
                } else if ($imageDbValue !== null) {
                    // If new image provided, delete old one (if it's a local file)
                    deleteImageFile($oldImage, $uploadDir);
                } else {
                    $imageDbValue = $oldImage; // Fallback
                }
                
                $stmt = $pdo->prepare('UPDATE products SET price = ?, rating = ?, image = ? WHERE id = ?');
                $stmt->execute([$price, $rating, $imageDbValue, $productId]);
                echo json_encode(['message' => 'Product updated successfully']);

            } else { // --- CREATE ---
                if ($imageDbValue === null) {
                    http_response_code(400);
                    echo json_encode(['error' => 'An image (upload or URL) is required.']);
                    exit;
                }
                $stmt = $pdo->prepare('INSERT INTO products (price, rating, image) VALUES (?, ?, ?)');
                $stmt->execute([$price, $rating, $imageDbValue]);
                $newId = $pdo->lastInsertId();
                http_response_code(201);
                $newImageURL = isUrl($imageDbValue) ? $imageDbValue : $imageBaseURL . $imageDbValue;
                echo json_encode(['id' => $newId, 'price' => $price, 'rating' => $rating, 'image' => $newImageURL]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            if (isset($newFileName)) {
                 deleteImageFile($newFileName, $uploadDir);
            }
            echo json_encode(['error' => 'Database operation failed: ' . $e->getMessage()]);
        }
        break;

    case 'DELETE':
        if (!$id || !is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid Product ID required']);
            exit;
        }
        try {
            // UPDATED: Get image identifier before deleting record
            $stmt = $pdo->prepare('SELECT image FROM products WHERE id = ?');
            $stmt->execute([$id]);
            $imageToDelete = $stmt->fetchColumn();

            $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                // Now delete the associated file (if it's not a URL)
                deleteImageFile($imageToDelete, $uploadDir);
                echo json_encode(['message' => 'Product deleted successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Product not found']);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error deleting product: ' . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>