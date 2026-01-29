```php
<?php
session_start();

// Restrict access to logged-in admins
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

date_default_timezone_set('Asia/Phnom_Penh');

// Database Configuration
$host = 'localhost';
$dbname = 'samann1_mini_app_db';
$username = 'samann1_mini_app_db';
$password = 'samann1_mini_app_db@2025';

// Odoo Configuration
$odoo_url = "https://odoo-test-api.odoo.com/jsonrpc";
$odoo_db = "odoo-test-api";
$odoo_username = "samannoeun@gmail.com";
$odoo_api_key = "09aba2b77691143490e2dc32b8f6e9ef8a6a2925";

// Connect to database
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES 'utf8mb4'");
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage(), 3, 'errors.log');
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Function to authenticate with Odoo
function authenticate_odoo($url, $db, $username, $api_key) {
    $payload = json_encode([
        'jsonrpc' => '2.0',
        'method' => 'call',
        'params' => [
            'service' => 'common',
            'method' => 'authenticate',
            'args' => [$db, $username, $api_key, []]
        ],
        'id' => rand(1, 1000)
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error || $http_code !== 200 || !$response) {
        error_log("Odoo authentication failed: $curl_error, HTTP $http_code", 3, 'errors.log');
        throw new Exception('Authentication failed');
    }

    $result = json_decode($response, true);
    if (isset($result['error']) || !$result['result']) {
        error_log("Odoo authentication error: " . ($result['error']['message'] ?? 'No user ID'), 3, 'errors.log');
        throw new Exception('Authentication error');
    }

    return $result['result'];
}

// Function to call Odoo JSON-RPC API
function call_odoo_api($url, $db, $uid, $api_key, $model, $method, $args) {
    $payload = json_encode([
        'jsonrpc' => '2.0',
        'method' => 'call',
        'params' => [
            'service' => 'object',
            'method' => 'execute_kw',
            'args' => [$db, $uid, $api_key, $model, $method, $args]
        ],
        'id' => rand(1, 1000)
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error || $http_code !== 200 || !$response) {
        error_log("Odoo API call failed: $curl_error, HTTP $http_code", []);
        throw new Exception('API call failed');
    }

    $result = json_decode($response, true);
    if (isset($result['error'])) {
        error_log("Odoo API error: " . $result['error']['message'], 3, 'errors.log');
        throw new Exception('API error');
    }

    return $result['result'];
}

// Fetch and sync products
try {
    $uid = authenticate_odoo($odoo_url, $odoo_db, $odoo_username, $odoo_api_key);
    $products = call_odoo_api($odoo_url, $odoo_db, $uid, $odoo_api_key, 'product.template', 'search_read', [
        [],
        ['id', 'name', 'list_price', 'categ_id', 'image_1920', 'qty_available']
    ]);

    $synced_products = [];
    foreach ($products as $product) {
        $name = filter_var($product['name'], FILTER_SANITIZE_SPECIAL_CHARS);
        $price = floatval($product['list_price']);
        $category = is_array($product['categ_id']) ? filter_var($product['categ_id'][1], FILTER_SANITIZE_SPECIAL_CHARS) : 'Default';
        $stock = intval($product['qty_available'] ?? 0);
        $image = !empty($product['image_1920'])
            ? "https://odoo-test-api.odoo.com/web/image/product.template/{$product['id']}/image_1920"
            : 'https://example.com/placeholder.jpg';
        $points = 0;
        $discount = 0;
        $label = '';

        if (empty($name) || $price <= 0 || strlen($category) > 100) {
            continue;
        }

        // Check for existing product by odoo_id
        $stmt = $pdo->prepare("SELECT id, name, price, discount, points, stock, image, category, subcategory_id, label FROM products WHERE odoo_id = ?");
        $stmt->execute([$product['id']]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update existing product
            $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, category = ?, image = ?, stock = ? WHERE odoo_id = ?");
            $stmt->execute([$name, $price, $category, $image, $stock, $product['id']]);
        } else {
            // Insert new product
            $stmt = $pdo->prepare("INSERT INTO products (odoo_id, name, price, category, image, points, label, discount, stock) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$product['id'], $name, $price, $category, $image, $points, $label, $discount, $stock]);
        }

        // Fetch updated product data
        $stmt = $pdo->prepare("SELECT p.id, p.name, p.price, p.discount, p.points, p.stock, p.image, p.category, p.subcategory_id, p.label, s.name AS subcategory_name 
                              FROM products p 
                              LEFT JOIN subcategories s ON p.subcategory_id = s.id 
                              WHERE p.odoo_id = ?");
        $stmt->execute([$product['id']]);
        $synced_product = $stmt->fetch();
        if ($synced_product) {
            $synced_products[] = $synced_product;
        }
    }

    // Fetch total product count
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $products_count = $stmt->fetchColumn();

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'products' => $synced_products,
        'products_count' => $products_count
    ]);
} catch (Exception $e) {
    error_log('Odoo sync failed: ' . $e->getMessage(), 3, 'errors.log');
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to fetch products: ' . $e->getMessage()]);
}
?>