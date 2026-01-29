<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
include 'db.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$action = $_GET['action'] ?? '';

function validateInput($data, $requiredFields) {
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && empty(trim($data[$field])))) {
            return false;
        }
    }
    return true;
}

function errorResponse($message) {
    return json_encode(['success' => false, 'error' => $message]);
}

// Public Endpoints (No authentication required)
if ($action === 'get_products') {
    try {
        $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name 
                             FROM products p 
                             LEFT JOIN categories c ON p.category_id = c.id 
                             WHERE p.active = 1");
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $products]);
    } catch (PDOException $e) {
        echo errorResponse('Database error: ' . $e->getMessage());
    }
    exit;
}

if ($action === 'get_products_by_category') {
    $category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
    if (!$category_id) {
        echo errorResponse('Invalid or missing category ID');
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name 
                             FROM products p 
                             LEFT JOIN categories c ON p.category_id = c.id 
                             WHERE p.category_id = ? AND p.active = 1");
        $stmt->execute([$category_id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        echo errorResponse('Database error: ' . $e->getMessage());
    }
    exit;
}

if ($action === 'search_products') {
    $query = filter_input(INPUT_GET, 'query', FILTER_SANITIZE_STRING);
    if (empty($query)) {
        echo errorResponse('Search query is required');
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name 
                             FROM products p 
                             LEFT JOIN categories c ON p.category_id = c.id 
                             WHERE (p.name LIKE ? OR p.description LIKE ?) AND p.active = 1");
        $searchTerm = "%$query%";
        $stmt->execute([$searchTerm, $searchTerm]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        echo errorResponse('Database error: ' . $e->getMessage());
    }
    exit;
}

if ($action === 'get_about_us') {
    try {
        $stmt = $pdo->query("SELECT * FROM about_us LIMIT 1");
        $about = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($about && !empty($about['image_urls'])) {
            $about['image_urls'] = json_decode($about['image_urls'], true);
        }
        echo json_encode(['success' => true, 'data' => $about ?: []]);
    } catch (PDOException $e) {
        echo errorResponse('Database error: ' . $e->getMessage());
    }
    exit;
}

// Authentication check for all other endpoints
if (!isset($_SESSION['user_id'])) {
    echo errorResponse('Unauthorized access');
    exit;
}

try {
    switch ($action) {
        // Existing Endpoints (unchanged)
        case 'get_main_categories':
            $stmt = $pdo->query("SELECT * FROM main_categories");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_categories':
            $stmt = $pdo->query("SELECT c.*, mc.name AS main_category_name 
                               FROM categories c 
                               LEFT JOIN main_categories mc ON c.main_category_id = mc.id");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_users':
            $stmt = $pdo->query("SELECT id, username, confirmed, role FROM users");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'confirm_user':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) echo errorResponse('Invalid user ID');
            else {
                $stmt = $pdo->prepare("UPDATE users SET confirmed = 1 WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true]);
            }
            break;

        case 'create_user':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!validateInput($data, ['username', 'password'])) {
                echo errorResponse('Missing required fields');
                break;
            }
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$data['username']]);
            if ($stmt->fetchColumn() > 0) {
                echo errorResponse('Username already exists');
            } else {
                $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
                $confirmed = isset($data['confirmed']) ? (int)$data['confirmed'] : 0;
                $stmt = $pdo->prepare("INSERT INTO users (username, password, confirmed, role) 
                                     VALUES (?, ?, ?, ?)");
                $stmt->execute([$data['username'], $hashed_password, $confirmed, $data['role'] ?? 'user']);
                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            }
            break;

        case 'edit_user':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!validateInput($data, ['id', 'username'])) {
                echo errorResponse('Missing required fields');
                break;
            }
            $sql = "UPDATE users SET username = ?, confirmed = ?, role = ?" . 
                   ($data['password'] ? ", password = ?" : "") . 
                   " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $params = [$data['username'], $data['confirmed'], $data['role']];
            if ($data['password']) $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            $params[] = $data['id'];
            $stmt->execute($params);
            echo json_encode(['success' => true]);
            break;

        case 'delete_user':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                echo errorResponse('Invalid user ID');
            } else if ($id == $_SESSION['user_id']) {
                echo errorResponse('Cannot delete your own account');
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true]);
            }
            break;

        case 'bulk_delete_users':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
                echo errorResponse('No user IDs provided');
                break;
            }
            $ids = array_filter($data['ids'], 'is_numeric');
            if (in_array($_SESSION['user_id'], $ids)) {
                echo errorResponse('Cannot delete your own account');
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
            break;

        case 'add_main_category':
            ob_start();
            try {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('Method Not Allowed', 405);
                }
                if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || !isset($_SESSION['csrf_token']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
                    throw new Exception('Invalid CSRF token', 403);
                }
                $input = file_get_contents('php://input');
                $data = json_decode($input, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                    throw new Exception('Invalid JSON input: ' . json_last_error_msg(), 400);
                }
                if (!isset($data['name']) || empty(trim($data['name']))) {
                    throw new Exception('Missing required field: name', 400);
                }
                $name = filter_var(trim($data['name']), FILTER_SANITIZE_STRING);
                $description = isset($data['description']) ? filter_var(trim($data['description']), FILTER_SANITIZE_STRING) : null;
                if (empty($name)) {
                    throw new Exception('Folder name cannot be empty', 400);
                }
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM main_categories WHERE name = ?");
                $checkStmt->execute([$name]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception('Folder name already exists', 409);
                }
                $stmt = $pdo->prepare("INSERT INTO main_categories (name, description) VALUES (?, ?)");
                $success = $stmt->execute([$name, $description]);
                if (!$success) {
                    throw new Exception('Failed to create folder', 500);
                }
                $newId = $pdo->lastInsertId();
                ob_end_clean();
                echo json_encode([
                    'success' => true,
                    'id' => $newId,
                    'data' => [
                        'name' => $name,
                        'description' => $description
                    ]
                ]);
            } catch (Exception $e) {
                ob_end_clean();
                http_response_code($e->getCode() ?: 500);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode() ?: 500
                ]);
            }
            exit;
            break;

        case 'edit_main_category':
            ob_start();
            try {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('Method Not Allowed', 405);
                }
                if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || !isset($_SESSION['csrf_token']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
                    throw new Exception('Invalid CSRF token', 403);
                }
                $input = file_get_contents('php://input');
                $data = json_decode($input, true);
                if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                    throw new Exception('Invalid JSON input: ' . json_last_error_msg(), 400);
                }
                if (!isset($data['id']) || !isset($data['name']) || empty(trim($data['name']))) {
                    throw new Exception('Missing required fields: id or name', 400);
                }
                $id = filter_var($data['id'], FILTER_VALIDATE_INT);
                $name = filter_var(trim($data['name']), FILTER_SANITIZE_STRING);
                $description = isset($data['description']) ? filter_var(trim($data['description']), FILTER_SANITIZE_STRING) : null;
                if ($id === false || empty($name)) {
                    throw new Exception('Invalid folder ID or name', 400);
                }
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM main_categories WHERE name = ? AND id != ?");
                $checkStmt->execute([$name, $id]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception('Folder name already exists', 409);
                }
                $stmt = $pdo->prepare("UPDATE main_categories SET name = ?, description = ? WHERE id = ?");
                $success = $stmt->execute([$name, $description, $id]);
                if (!$success) {
                    throw new Exception('Failed to update folder', 500);
                }
                ob_end_clean();
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                ob_end_clean();
                http_response_code($e->getCode() ?: 500);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode() ?: 500
                ]);
            }
            exit;
            break;

        case 'add_category':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!validateInput($data, ['name'])) echo errorResponse('Missing required fields');
            else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, main_category_id, description) VALUES (?, ?, ?)");
                $stmt->execute([$data['name'], $data['main_category_id'] ?? null, $data['description'] ?? null]);
                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            }
            break;

       case 'add_product':
            $data = json_decode(file_get_contents('php://input'), true);
            $name = isset($data['name']) ? trim($data['name']) : null;
            if (!$name) {
                echo errorResponse('Product name is required');
                break;
            }
            if (isset($data['type'])) {
                $validTypes = ['title1', 'title2', 'title3', 'product'];
                if (!in_array($data['type'], $validTypes)) {
                    echo errorResponse('Invalid type value. Must be one of: ' . implode(', ', $validTypes));
                    break;
                }
                if ($data['section'] !== 'enhance_creation') {
                    echo errorResponse('Type field is only allowed for the "enhance_creation" section');
                    break;
                }
            }
            $stmt = $pdo->prepare("INSERT INTO products (name, name_km, category_id, image_url, section, type, description, description_km, recipe, recipe_km, scale, scale_km, active) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $name,
                $data['name_km'] ?? null,
                $data['category_id'] ?? null,
                $data['image_url'] ?? null,
                $data['section'] ?? null,
                $data['type'] ?? null,
                $data['description'] ?? null,
                $data['description_km'] ?? null,
                $data['recipe'] ?? null,
                $data['recipe_km'] ?? null,
                $data['scale'] ?? null,
                $data['scale_km'] ?? null,
                1
            ]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'edit_category':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!validateInput($data, ['id', 'name'])) echo errorResponse('Missing required fields');
            else {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, main_category_id = ?, description = ? WHERE id = ?");
                $stmt->execute([$data['name'], $data['main_category_id'] ?? null, $data['description'] ?? null, $data['id']]);
                echo json_encode(['success' => true]);
            }
            break;

     case 'edit_product':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            $name = isset($data['name']) ? trim($data['name']) : null;
            if (!$id) {
                echo errorResponse('Product ID is required');
                break;
            }
            if ($name === null) {
                echo errorResponse('Product name cannot be empty');
                break;
            }
            if (isset($data['type'])) {
                $validTypes = ['title1', 'title2', 'title3', 'product'];
                if (!in_array($data['type'], $validTypes)) {
                    echo errorResponse('Invalid type value. Must be one of: ' . implode(', ', $validTypes));
                    break;
                }
                if ($data['section'] !== 'enhance_creation') {
                    echo errorResponse('Type field is only allowed for the "enhance_creation" section');
                    break;
                }
            }
            $stmt = $pdo->prepare("UPDATE products SET 
                                 name = COALESCE(?, name),
                                 name_km = COALESCE(?, name_km),
                                 category_id = ?,
                                 image_url = COALESCE(?, image_url),
                                 section = COALESCE(?, section),
                                 type = COALESCE(?, type),
                                 description = COALESCE(?, description),
                                 description_km = COALESCE(?, description_km),
                                 recipe = COALESCE(?, recipe),
                                 recipe_km = COALESCE(?, recipe_km),
                                 scale = COALESCE(?, scale),
                                 scale_km = COALESCE(?, scale_km),
                                 active = COALESCE(?, active)
                                 WHERE id = ?");
            $stmt->execute([
                $name,
                $data['name_km'] ?? null,
                $data['category_id'] ?? null,
                $data['image_url'] ?? null,
                $data['section'] ?? null,
                $data['type'] ?? null,
                $data['description'] ?? null,
                $data['description_km'] ?? null,
                $data['recipe'] ?? null,
                $data['recipe_km'] ?? null,
                $data['scale'] ?? null,
                $data['scale_km'] ?? null,
                isset($data['active']) ? (int)$data['active'] : null,
                $id
            ]);
            echo json_encode(['success' => true]);
            break;

        case 'delete_main_category':
        case 'delete_category':
        case 'delete_product':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) echo errorResponse('Invalid ID');
            else {
                $tableMap = [
                    'delete_main_category' => 'main_categories',
                    'delete_category' => 'categories',
                    'delete_product' => 'products'
                ];
                $table = $tableMap[$action];
                
                if ($action === 'delete_category') {
                    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
                    $checkStmt->execute([$id]);
                    if ($checkStmt->fetchColumn() > 0) {
                        echo errorResponse('Cannot delete category: Products are still assigned to it');
                        break;
                    }
                }
                
                $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true]);
            }
            break;

        case 'bulk_delete_categories':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
                echo errorResponse('No category IDs provided');
                break;
            }
            $ids = array_filter($data['ids'], 'is_numeric');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id IN ($placeholders)");
            $checkStmt->execute($ids);
            if ($checkStmt->fetchColumn() > 0) {
                echo errorResponse('Cannot delete: Some categories have assigned products');
                break;
            }
            
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
            break;

        case 'bulk_delete_products':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!isset($data['ids']) || !is_array($data['ids']) || empty($data['ids'])) {
                echo errorResponse('No product IDs provided');
                break;
            }
            $ids = array_filter($data['ids'], 'is_numeric');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            echo json_encode(['success' => true, 'deleted' => $stmt->rowCount()]);
            break;

        case 'get_files':
            $stmt = $pdo->query("SELECT * FROM files ORDER BY upload_date DESC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'upload_files':
            $uploadDir = 'uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $response = ['success' => true, 'files' => []];
            
            if (!isset($_FILES['files']) || empty($_FILES['files']['name'])) {
                echo errorResponse('No files uploaded');
                break;
            }

            foreach ($_FILES['files']['name'] as $key => $name) {
                $tmpName = $_FILES['files']['tmp_name'][$key];
                $size = $_FILES['files']['size'][$key];
                $type = $_FILES['files']['type'][$key];
                $error = $_FILES['files']['error'][$key];

                if ($error !== UPLOAD_ERR_OK) {
                    echo errorResponse('Upload error for file: ' . $name);
                    exit;
                }

                if ($size > $maxFileSize) {
                    echo errorResponse('File too large: ' . $name);
                    exit;
                }

                if (!in_array($type, $allowedTypes)) {
                    echo errorResponse('Invalid file type: ' . $name);
                    exit;
                }

                $filename = uniqid() . '_' . basename($name);
                $destination = $uploadDir . $filename;
                $url = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $destination;

                if (move_uploaded_file($tmpName, $destination)) {
                    $stmt = $pdo->prepare("INSERT INTO files (filename, url, size, upload_date) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$filename, $url, $size]);
                    
                    $response['files'][] = [
                        'id' => $pdo->lastInsertId(),
                        'filename' => $filename,
                        'url' => $url,
                        'size' => $size,
                        'upload_date' => date('Y-m-d H:i:s')
                    ];
                } else {
                    echo errorResponse('Failed to move uploaded file: ' . $name);
                    exit;
                }
            }
            echo json_encode($response);
            break;

        case 'delete_file':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                echo errorResponse('Invalid file ID');
                break;
            }
            
            $stmt = $pdo->prepare("SELECT url FROM files WHERE id = ?");
            $stmt->execute([$id]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$file) {
                echo errorResponse('File not found');
                break;
            }
            
            $filePath = str_replace('http://' . $_SERVER['HTTP_HOST'] . '/', '', $file['url']);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'update_about_us':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!validateInput($data, ['title', 'description'])) {
                echo errorResponse('Missing required fields');
                break;
            }
            $image_urls = isset($data['image_urls']) && is_array($data['image_urls']) ? json_encode($data['image_urls']) : null;
            $stmt = $pdo->prepare("INSERT INTO about_us (title, description, image_urls) 
                                 VALUES (?, ?, ?) 
                                 ON DUPLICATE KEY UPDATE title = ?, description = ?, image_urls = ?");
            $stmt->execute([
                $data['title'], $data['description'], $image_urls,
                $data['title'], $data['description'], $image_urls
            ]);
            echo json_encode(['success' => true]);
            break;

        case 'get_analytics':
            $stats = [];
            $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $stats['total_categories'] = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
            $stats['total_products'] = $pdo->query("SELECT COUNT(*) FROM products WHERE active = 1")->fetchColumn();
            $stats['unconfirmed_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE confirmed = 0")->fetchColumn();
            $stats['total_files'] = $pdo->query("SELECT COUNT(*) FROM files")->fetchColumn();
            echo json_encode(['success' => true, 'data' => $stats]);
            break;

        case 'toggle_product_status':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            $active = filter_input(INPUT_GET, 'active', FILTER_VALIDATE_INT);
            if (!$id || !in_array($active, [0, 1])) {
                echo errorResponse('Invalid product ID or status');
                break;
            }
            $stmt = $pdo->prepare("UPDATE products SET active = ? WHERE id = ?");
            $stmt->execute([$active, $id]);
            echo json_encode(['success' => true]);
            break;

        case 'get_product_details':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                echo errorResponse('Invalid product ID');
                break;
            }
            try {
                $stmt = $pdo->prepare("SELECT p.*, c.name AS category_name 
                                     FROM products p 
                                     LEFT JOIN categories c ON p.category_id = c.id 
                                     WHERE p.id = ?");
                $stmt->execute([$id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $product ?: []]);
            } catch (PDOException $e) {
                echo errorResponse('Database error: ' . $e->getMessage());
            }
            break;

        case 'add_event':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!validateInput($data, ['title', 'date'])) {
                echo errorResponse('Missing required fields');
                break;
            }
            $stmt = $pdo->prepare("INSERT INTO events (title, description, date, location) 
                                 VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['title'],
                $data['description'] ?? null,
                $data['date'],
                $data['location'] ?? null
            ]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'get_events':
            $stmt = $pdo->query("SELECT * FROM events ORDER BY date DESC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'delete_event':
            $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                echo errorResponse('Invalid event ID');
                break;
            }
            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo errorResponse('Invalid action');
    }
} catch (PDOException $e) {
    echo errorResponse('Database error: ' . $e->getMessage());
}

?>