<?php

// Odoo Configuration
$odoo_url = "https://odoo-test-api.odoo.com/jsonrpc";
$odoo_db = "odoo-test-api";
$odoo_username = "samannoeun@gmail.com";
$odoo_api_key = "09aba2b77691143490e2dc32b8f6e9ef8a6a2925";

// Database Configuration
$db_host = 'localhost';
$db_name = 'samann1_mini_app_db';
$db_user = 'samann1_mini_app_db';
$db_pass = 'samann1_mini_app_db@2025';

// Function to make JSON-RPC request to Odoo
function odoo_jsonrpc($url, $method, $params, $id = 0) {
    $data = [
        'jsonrpc' => '2.0',
        'method' => $method,
        'params' => $params,
        'id' => $id
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Step 1: Authenticate with Odoo
$auth_response = odoo_jsonrpc($odoo_url, 'call', [
    'service' => 'common',
    'method' => 'login',
    'args' => [$odoo_db, $odoo_username, $odoo_api_key]
]);

if (!isset($auth_response['result']) || !$auth_response['result']) {
    die("Authentication failed: " . json_encode($auth_response));
}

$uid = $auth_response['result'];

// Step 2: Fetch products from Odoo
$fields = ['id', 'name', 'list_price', 'categ_id', 'description', 'qty_available', 'discount', 'standard_price'];
$product_response = odoo_jsonrpc($odoo_url, 'call', [
    'service' => 'object',
    'method' => 'execute_kw',
    'args' => [
        $odoo_db,
        $uid,
        $odoo_api_key,
        'product.template',
        'search_read',
        [[]], // No domain filter to fetch all products
        ['fields' => $fields]
    ]
]);

if (!isset($product_response['result'])) {
    die("Failed to fetch products: " . json_encode($product_response));
}

$products = $product_response['result'];

// Step 3: Connect to the local database
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Step 4: Create or ensure the products table exists
$create_table_sql = "
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    odoo_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) DEFAULT 0.00,
    category INT DEFAULT NULL,
    subcategory_id INT DEFAULT NULL,
    image MEDIUMBLOB DEFAULT NULL,
    points DECIMAL(10,2) DEFAULT 0.00,
    label VARCHAR(255) DEFAULT NULL,
    cart_id INT DEFAULT NULL,
    discount DECIMAL(5,2) DEFAULT 0.00,
    description TEXT,
    stock INT DEFAULT 0,
    original_price DECIMAL(10,2) DEFAULT 0.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

try {
    $pdo->exec($create_table_sql);
} catch (PDOException $e) {
    die("Failed to create table: " . $e->getMessage());
}

// Step 5: Clear old data (using DELETE instead of TRUNCATE)
try {
    $pdo->exec("DELETE FROM cart"); // Remove this if you want to keep cart data
    $pdo->exec("DELETE FROM products");
} catch (PDOException $e) {
    die("Failed to clear old data: " . $e->getMessage());
}

// Step 6: Insert new product data
$insert_sql = "INSERT INTO products (odoo_id, name, price, category, subcategory_id, image, points, label, cart_id, discount, description, stock, original_price) VALUES (:odoo_id, :name, :price, :category, :subcategory_id, :image, :points, :label, :cart_id, :discount, :description, :stock, :original_price)";
$stmt = $pdo->prepare($insert_sql);

try {
    $pdo->beginTransaction();
    foreach ($products as $product) {
        $stmt->execute([
            ':odoo_id' => $product['id'],
            ':name' => $product['name'],
            ':price' => $product['list_price'] ?? 0.00,
            ':category' => $product['categ_id'][0] ?? null, // Assuming categ_id is a many2one relation
            ':subcategory_id' => null, // Requires custom mapping or Odoo field
            ':image' => null, // Removed image_medium, set to null
            ':points' => 0.00, // Requires custom field or logic
            ':label' => null, // Requires custom field or logic
            ':cart_id' => null, // Requires custom field or logic
            ':discount' => $product['discount'] ?? 0.00,
            ':description' => $product['description'] ?? null,
            ':stock' => $product['qty_available'] ?? 0,
            ':original_price' => $product['standard_price'] ?? 0.00
        ]);
    }
    $pdo->commit();
    echo "Products successfully synchronized. Total products: " . count($products);
} catch (PDOException $e) {
    $pdo->rollBack();
    die("Failed to insert products: " . $e->getMessage());
}

?>