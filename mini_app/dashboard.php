<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

date_default_timezone_set('Asia/Phnom_Penh');

$host = 'localhost';
$dbname = 'samann1_mini_app_db';
$username = 'samann1_mini_app_db';
$password = 'samann1_mini_app_db@2025';

// Odoo Configuration
$odoo_url = "https://www.vvc.asia";
$odoo_db = "zender";
$odoo_username = "samannoeun@gmail.com";
$odoo_api_key = "59cb5114faa709a3977586dca546b838b39110cc";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES 'utf8mb4'");
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage(), 3, 'errors.log');
    die('ការតភ្ជាប់ទិន្នន័យបរាជ័យ: ' . htmlspecialchars($e->getMessage()));
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

    $ch = curl_init($url . '/jsonrpc');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log("cURL error during Odoo authentication: $curl_error", 3, 'errors.log');
        throw new Exception("cURL error: $curl_error");
    }

    if ($http_code !== 200) {
        error_log("Odoo authentication failed with HTTP code $http_code", 3, 'errors.log');
        throw new Exception("Odoo authentication failed with HTTP code $http_code");
    }

    $result = json_decode($response, true);
    if (isset($result['error'])) {
        $error_msg = $result['error']['data']['message'] ?? $result['error']['message'];
        error_log("Odoo authentication error: $error_msg", 3, 'errors.log');
        throw new Exception("Authentication error: $error_msg");
    }

    if (!$result['result']) {
        error_log("Odoo authentication returned no user ID", 3, 'errors.log');
        throw new Exception("Invalid credentials or no user ID returned");
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

   $ch = curl_init($url . '/jsonrpc');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
   curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        error_log("cURL error during Odoo API call: $curl_error", 3, 'errors.log');
        throw new Exception("cURL error: $curl_error");
    }

    if ($http_code !== 200) {
        error_log("Odoo API request failed with HTTP code $http_code", 3, 'errors.log');
        throw new Exception("API request failed with HTTP code $http_code");
    }

    $result = json_decode($response, true);
    if (isset($result['error'])) {
        $error_msg = $result['error']['data']['message'] ?? $result['error']['message'];
        error_log("Odoo API error: $error_msg", 3, 'errors.log');
        throw new Exception("API error: $error_msg");
    }

    return $result['result'];
}

// Function to fetch and insert products from Odoo
function fetch_odoo_products($pdo, $odoo_url, $odoo_db, $odoo_username, $odoo_api_key) {
    try {
        // Authenticate with Odoo
        $uid = authenticate_odoo($odoo_url, $odoo_db, $odoo_username, $odoo_api_key);

        // Fetch products from Odoo
        $products = call_odoo_api($odoo_url, $odoo_db, $uid, $odoo_api_key, 'product.template', 'search_read', [
            [], // Domain (fetch all)
            ['id', 'name', 'list_price', 'categ_id', 'image_1920', 'qty_available'] // Fields to fetch
        ]);

        $pdo->beginTransaction();

        // Step 1: Clear all existing products for a fresh sync
        $pdo->exec("DELETE FROM products");

        $inserted = 0;
        foreach ($products as $product) {
            $odoo_id = intval($product['id']);
            $name = filter_var($product['name'], FILTER_SANITIZE_SPECIAL_CHARS);
            $price = floatval($product['list_price']);
            // Odoo returns category as an array [id, 'name']
            $category = is_array($product['categ_id']) ? filter_var($product['categ_id'][1], FILTER_SANITIZE_SPECIAL_CHARS) : 'Uncategorized';
            $stock = intval($product['qty_available'] ?? 0);
            
            // Construct a public URL for the image
            $image = !empty($product['image_1920'])
                ? "{$odoo_url}/web/image/product.template/{$product['id']}/image_1920"
                : 'https://via.placeholder.com/150'; // A better placeholder

            if (empty($name) || $price <= 0) {
                error_log("Skipping invalid product data from Odoo: ID=$odoo_id, Name=$name, Price=$price", 3, 'errors.log');
                continue; // Skip invalid products
            }

            $stmt = $pdo->prepare(
                "INSERT INTO products (odoo_id, name, price, category, image, stock, points, discount, label) 
                 VALUES (?, ?, ?, ?, ?, ?, 0, 0, '')"
            );
            $stmt->execute([$odoo_id, $name, $price, $category, $image, $stock]);
            $inserted++;
        }

        // Set the fetched flag in settings
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('odoo_products_fetched', '1') 
                               ON DUPLICATE KEY UPDATE setting_value = '1'");
        $stmt->execute();
        
        $pdo->commit();

        $_SESSION['table_counts']['products'] = $inserted;
        $_SESSION['table_counts']['timestamp'] = time();

        return "បានធ្វើសមកាលកម្មផលិតផល $inserted ដោយជោគជ័យពី Odoo!";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Odoo product sync failed: ' . $e->getMessage(), 3, 'errors.log');
        // Give a more user-friendly message
        throw new Exception("កំហុសក្នុងការទាញផលិតផលពី Odoo: " . htmlspecialchars($e->getMessage()));
    }
}

// Handle manual fetching products from Odoo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'fetch_odoo_products') {
    try {
        $success_message = fetch_odoo_products($pdo, $odoo_url, $odoo_db, $odoo_username, $odoo_api_key);
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Automatic fetching when accessing Products Section
if ($active_section === 'products') {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'odoo_products_fetched'");
        $stmt->execute();
        $result = $stmt->fetch();
        $products_fetched = $result && $result['setting_value'] == '1';

        if (!$products_fetched) {
            $success_message = fetch_odoo_products($pdo, $odoo_url, $odoo_db, $odoo_username, $odoo_api_key);
        }
    } catch (PDOException $e) {
        error_log('Check odoo_products_fetched failed: ' . $e->getMessage(), 3, 'errors.log');
        $error_message = "កំហុសក្នុងការពិនិត្យស្ថានភាពនាំចូលផលិតផល: " . htmlspecialchars($e->getMessage());
    }
}

// Cache table counts
if (!isset($_SESSION['table_counts']) || (time() - $_SESSION['table_counts']['timestamp']) > 300) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM users");
        $users_count = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM products");
        $products_count = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM cart");
        $cart_count = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM orders");
        $orders_count = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM order_history");
        $order_history_count = $stmt->fetchColumn();

        $_SESSION['table_counts'] = [
            'users' => $users_count,
            'products' => $products_count,
            'cart' => $cart_count,
            'orders' => $orders_count,
            'order_history' => $order_history_count,
            'timestamp' => time()
        ];
    } catch (PDOException $e) {
        error_log('Error counting records: ' . $e->getMessage(), 3, 'errors.log');
        $error_message = "កំហុសក្នុងការរាប់ទិន្នន័យ: " . htmlspecialchars($e->getMessage());
        $users_count = $products_count = $cart_count = $orders_count = $order_history_count = 0;
    }
} else {
    $users_count = $_SESSION['table_counts']['users'];
    $products_count = $_SESSION['table_counts']['products'];
    $cart_count = $_SESSION['table_counts']['cart'];
    $orders_count = $_SESSION['table_counts']['orders'];
    $order_history_count = $_SESSION['table_counts']['order_history'];
}

// Helper function to format datetime to date and 12-hour format with Khmer AM/PM
function formatTime($datetime) {
    if (empty($datetime)) {
        return 'N/A';
    }
    try {
        $date = new DateTime($datetime, new DateTimeZone('Asia/Phnom_Penh'));
        $ampm = $date->format('A') === 'AM' ? 'ព្រឹក' : 'ល្ងាច';
        return $date->format('Y-m-d h:i') . ' ' . $ampm;
    } catch (Exception $e) {
        error_log('Time format error: ' . $e->getMessage(), 3, 'errors.log');
        return 'N/A';
    }
}

// Initialize messages
$success_message = '';
$error_message = '';

// Handle points configuration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_points_config') {
    $points_per_dollar = filter_input(INPUT_POST, 'points_per_dollar', FILTER_VALIDATE_FLOAT);
    if ($points_per_dollar !== false && $points_per_dollar >= 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('points_per_dollar', ?) 
                                   ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$points_per_dollar, $points_per_dollar]);
            $success_message = "បានកំណត់ពិន្ទុក្នុងមួយដុល្លារដោយជោគជ័យ!";
        } catch (PDOException $e) {
            error_log('Points config update failed: ' . $e->getMessage(), 3, 'errors.log');
            $error_message = "កំហុសក្នុងការកំណត់ពិន្ទុ: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error_message = "សូមបញ្ចូលតម្លៃពិន្ទុត្រឹមត្រូវ (លេខវិជ្ជមាន)!";
    }
}

// Handle subcategory creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_subcategory') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS);
    
    try {
        $stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND TRIM(category) != ''");
        $valid_categories = array_column($stmt->fetchAll(), 'category');
    } catch (PDOException $e) {
        error_log('Categories fetch failed: ' . $e->getMessage(), 3, 'errors.log');
        $error_message = "កំហុសក្នុងការទាញយកប្រភេទ: " . htmlspecialchars($e->getMessage());
        $valid_categories = [];
    }
    
    if ($name && $category && in_array($category, $valid_categories)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO subcategories (name, category) VALUES (?, ?)");
            $stmt->execute([$name, $category]);
            $success_message = "បានបន្ថែមប្រភេទរងដោយជោគជ័យ!";
        } catch (PDOException $e) {
            error_log('Subcategory creation failed: ' . $e->getMessage(), 3, 'errors.log');
            $error_message = "កំហុសក្នុងការបន្ថែមប្រភេទរង: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error_message = "សូមបញ្ចូលឈ្មោះប្រភេទរង និងជ្រើសរើសប្រភេទត្រឹមត្រូវ!";
    }
}

// Handle subcategory update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_subcategory') {
    $subcategory_id = filter_input(INPUT_POST, 'subcategory_id', FILTER_VALIDATE_INT);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS);

    try {
        $stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND TRIM(category) != ''");
        $valid_categories = array_column($stmt->fetchAll(), 'category');
    } catch (PDOException $e) {
        error_log('Categories fetch failed: ' . $e->getMessage(), 3, 'errors.log');
        $error_message = "កំហុសក្នុងការទាញយកប្រភេទ: " . htmlspecialchars($e->getMessage());
        $valid_categories = [];
    }

    if ($subcategory_id && $name && $category && in_array($category, $valid_categories)) {
        try {
            $stmt = $pdo->prepare("UPDATE subcategories SET name = ?, category = ? WHERE id = ?");
            $stmt->execute([$name, $category, $subcategory_id]);
            $success_message = "បានកែប្រែប្រភេទរងដោយជោគជ័យ!";
        } catch (PDOException $e) {
            error_log('Subcategory update failed: ' . $e->getMessage(), 3, 'errors.log');
            $error_message = "កំហុសក្នុងការកែប្រែប្រភេទរង: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error_message = "សូមបញ្ចូលព័ត៌មានឱ្យត្រឹមត្រូវ (ឈ្មោះ និងប្រភេទ)!";
    }
}

// Handle subcategory deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete_subcategory' && isset($_GET['id'])) {
    $subcategory_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($subcategory_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM subcategories WHERE id = ?");
            $stmt->execute([$subcategory_id]);
            $success_message = "បានលុបប្រភេទរងដោយជោគជ័យ!";
        } catch (PDOException $e) {
            error_log('Subcategory deletion failed: ' . $e->getMessage(), 3, 'errors.log');
            $error_message = "កំហុសក្នុងការលុបប្រភេទរង: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error_message = "លេខសម្គាល់ប្រភេទរងមិនត្រឹមត្រូវ!";
    }
}

// Handle product update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_product') {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS);
    $subcategory_id = filter_input(INPUT_POST, 'subcategory_id', FILTER_VALIDATE_INT);
    $image = filter_input(INPUT_POST, 'image', FILTER_SANITIZE_URL);
    $points = filter_input(INPUT_POST, 'points', FILTER_VALIDATE_INT);
    $label = filter_input(INPUT_POST, 'label', FILTER_SANITIZE_SPECIAL_CHARS);
    $discount = filter_input(INPUT_POST, 'discount', FILTER_VALIDATE_FLOAT);
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);

    $valid_labels = ['new', 'low_stock', 'sold_out', ''];
    
    $subcategory_valid = true;
    if ($subcategory_id) {
        $stmt = $pdo->prepare("SELECT id FROM subcategories WHERE id = ?");
        $stmt->execute([$subcategory_id]);
        $subcategory = $stmt->fetch();
        if (!$subcategory) {
            $subcategory_valid = false;
        }
    }

    if (strlen($category) > 100) {
        $error_message = "ប្រភេទមិនអាចវែងជាង ១០០ តួអក្សរ!";
    } elseif ($product_id && $name && $price !== false && $price > 0 && !empty(trim($category)) && 
        $subcategory_valid && $image && filter_var($image, FILTER_VALIDATE_URL) && 
        $points !== false && $points >= 0 && in_array($label, $valid_labels) &&
        $discount !== false && $discount >= 0 && $discount <= 100 &&
        $stock !== false && $stock >= 0) {
        try {
            $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, category = ?, subcategory_id = ?, 
                                  image = ?, points = ?, label = ?, discount = ?, stock = ? WHERE id = ?");
            $stmt->execute([$name, $price, $category, $subcategory_id ?: null, $image, $points, $label ?: null, $discount, $stock, $product_id]);
            $success_message = "បានធ្វើបច្ចុប្បន្នភាពផលិតផលដោយជោគជ័យ!";
        } catch (PDOException $e) {
            error_log('Product update failed: ' . $e->getMessage(), 3, 'errors.log');
            $error_message = "កំហុសក្នុងការធ្វើបច្ចុប្បន្នភាពផលិតផល: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error_message = "សូមបំពេញព័ត៌មានឱ្យគ្រប់ និងត្រឹមត្រូវ (ឈ្មោះ, តម្លៃជាលេខវិជ្ជមាន, ប្រភេទមិនអាចទទេ, ប្រភេទរងត្រឹមត្រូវ, URL រូបភាពត្រឹមត្រូវ, ពិន្ទុជាលេខវិជ្ជមាន, ស្លាកត្រឹមត្រូវ, បញ្ចុះតម្លៃជាលេខ ០ ឬវិជ្ជមាន និងមិនលើសពី ១៦៦%, ស្តុកជាលេខ ៦ ឬវិជ្ជមាន)!";
    }
}

// Handle application/x-www-form-urlencoded
if ($_SERVER['CONTENT_TYPE'] === 'application/x-www-form-urlencoded' && isset($_POST['action']) && $_POST['action'] === 'update_product') {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_SPECIAL_CHARS);
    $subcategory_id = filter_input(INPUT_POST, 'subcategory_id', FILTER_VALIDATE_INT);
    $image = filter_input(INPUT_POST, 'image', FILTER_SANITIZE_URL);
    $points = filter_input(INPUT_POST, 'points', FILTER_VALIDATE_INT);
    $label = filter_input(INPUT_POST, 'label', FILTER_SANITIZE_SPECIAL_CHARS);
    $discount = filter_input(INPUT_POST, 'discount', FILTER_VALIDATE_FLOAT);
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);

    $valid_labels = ['new', 'low_stock', 'sold_out', ''];
    
    $subcategory_valid = true;
    if ($subcategory_id) {
        $stmt = $pdo->prepare("SELECT id FROM subcategories WHERE id = ?");
        $stmt->execute([$subcategory_id]);
        $subcategory = $stmt->fetch();
        if (!$subcategory) {
            $subcategory_valid = false;
        }
    }

    if (strlen($category) > 100) {
        $error_message = "ប្រភេទមិនអាចវែងជាង ១៦៦ តួអក្សរ!";
    } elseif ($product_id && $name && $price !== false && $price > 0 && !empty(trim($category)) && 
        $subcategory_valid && $image && filter_var($image, FILTER_VALIDATE_URL) && 
        $points !== false && $points >= 0 && in_array($label, $valid_labels) &&
        $discount !== false && $discount >= 0 && $discount <= 100 &&
        $stock !== false && $stock >= 0) {
        try {
            $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, category = ?, subcategory_id = ?, 
                                  image = ?, points = ?, label = ?, discount = ?, stock = ? WHERE id = ?");
            $stmt->execute([$name, $price, $category, $subcategory_id ?: null, $image, $points, $label ?: null, $discount, $stock, $product_id]);
            $success_message = "បានធ្វើបច្ចុប្បន្នភាពផលិតផលដោយជោគជ័យ!";
        } catch (PDOException $e) {
            error_log('Product update failed: ' . $e->getMessage(), 3, 'errors.log');
            $error_message = "កំហុសក្នុងការធ្វើបច្ចុប្បន្នភាពផលិតផល: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error_message = "សូមបំពេញព័ត៌មានឱ្យគ្រប់ និងត្រឹមត្រូវ!";
    }
}

// Handle product deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete_product' && isset($_GET['id'])) {
    $product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($product_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $success_message = "បានលុបផលិតផលដោយជោគជ័យ!";
        } catch (PDOException $e) {
            error_log('Product deletion failed: ' . $e->getMessage(), 3, 'errors.log');
            $error_message = "កំហុសក្នុងការលុបផលិតផល: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error_message = "លេខសម្គាល់ផលិតផលមិនត្រឹមត្រូវ!";
    }
}

// Handle product-specific points
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_product_points') {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $points = filter_input(INPUT_POST, 'points', FILTER_VALIDATE_INT);
    if ($product_id && $points !== false && $points >= 0) {
        try {
            $stmt = $pdo->prepare("UPDATE products SET points = ? WHERE id = ?");
            $stmt->execute([$points, $product_id]);
            $success_message = "បានធ្វើបច្ចុប្បន្នភាពពិន្ទុផលិតផលដោយជោគជ័យ!";
        } catch (PDOException $e) {
            error_log('Product points update failed: ' . $e->getMessage(), 3, 'errors.log');
            $error_message = "កំហុសក្នុងការធ្វើបច្ចុប្បន្នភាពពិន្ទុផលិតផល: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error_message = "សូមបញ្ចូលព័ត៌មានផលិតផលនិងពិន្ទុឱ្យត្រឹមត្រូវ (លេខវិជ្ជមាន)!";
    }
}

// Handle reward creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_reward') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $points_required = filter_input(INPUT_POST, 'points_required', FILTER_VALIDATE_INT);
    $image = filter_input(INPUT_POST, 'image', FILTER_SANITIZE_URL);

    if ($name && $points_required && $points_required > 0 && $image && filter_var($image, FILTER_VALIDATE_URL)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO rewards (name, points_required, image) VALUES (?, ?, ?)");
            $stmt->execute([$name, $points_required, $image]);
            $success_message = "បានបន្ថែមរង្វាន់ដោយជោគជ័យ!";
        } catch (PDOException $e) {
            error_log('Reward creation failed: ' . $e->getMessage(), 3, 'errors.log');
            $error_message = "កំហុសក្នុងការបន្ថែមរង្វាន់: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error_message = "សូមបំពេញព័ត៌មានឱ្យគ្រប់ និងត្រឹមត្រូវ (ឈ្មោះ, ពិន្ទុជាលេខវិជ្ជមាន, URL រូបភាពត្រឹមត្រូវ)!";
    }
}

// Handle reward deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete_reward' && isset($_GET['id'])) {
    $reward_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($reward_id) {
        try {
            $stmt = $pdo->prepare("DELETE FROM rewards WHERE id = ?");
            $stmt->execute([$reward_id]);
            $success_message = "បានលុបរង្វាន់ដោយជោគជ័យ!";
        } catch (PDOException $e) {
            error_log('Reward deletion failed: ' . $e->getMessage(), 3, 'errors.log');
            $error_message = "កំហុសក្នុងការលុបរង្វាន់: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error_message = "លេខសម្គាល់រង្វាន់មិនត្រឹមត្រូវ!";
    }
}

// Handle points update for orders
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_order') {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $new_points = filter_input(INPUT_POST, 'points_earned', FILTER_VALIDATE_INT);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_SPECIAL_CHARS);
    $shipping_address = filter_input(INPUT_POST, 'shipping_address', FILTER_SANITIZE_SPECIAL_CHARS);
    $shipping_latitude = filter_input(INPUT_POST, 'shipping_latitude', FILTER_VALIDATE_FLOAT);
    $shipping_longitude = filter_input(INPUT_POST, 'shipping_longitude', FILTER_VALIDATE_FLOAT);
    $shippingPhone = filter_input(INPUT_POST, 'shippingPhone', FILTER_SANITIZE_SPECIAL_CHARS);
    $mapLink = filter_input(INPUT_POST, 'mapLink', FILTER_SANITIZE_URL);

    if ($order_id && $new_points !== false && $new_points >= 0) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT user_id, points_earned FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();

            if ($order) {
                $stmt = $pdo->prepare("UPDATE orders SET points_earned = ?, payment_method = ?, shipping_address = ?, 
                                      shipping_latitude = ?, shipping_longitude = ?, shippingPhone = ?, mapLink = ? 
                                      WHERE id = ?");
                $stmt->execute([$new_points, $payment_method, $shipping_address, $shipping_latitude, 
                               $shipping_longitude, $shippingPhone, $mapLink, $order_id]);

                $points_diff = $new_points - $order['points_earned'];
                $stmt = $pdo->prepare("UPDATE users SET points = GREATEST(points + ?, 0) WHERE telegram_id = ?");
                $stmt->execute([$points_diff, $order['user_id']]);

                $pdo->commit();
                $success_message = "បានធ្វើបច្ចុប្បន្នភាពការបញ្ជាទិញដោយជោគជ័យ!";
            } else {
                $pdo->rollBack();
                $error_message = "រកមិនឃើញការបញ្ជាទិញ!";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Order update failed: ' . $e->getMessage(), 3, 'errors.log');
            $error_message = "កំហុសក្នុងការធ្វើបច្ចុប្បន្នភាពការបញ្ជាទិញ: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error_message = "សូមបញ្ចូលព័ត៌មានឱ្យត្រឹមត្រូវ (លេខសម្គាល់ និងពិន្ទុវិជ្ជមាន)!";
    }
}

// Handle deletion of multiple order history records
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_selected_order_history') {
    if (!empty($_POST['selected_orders'])) {
        $selected_ids = array_filter($_POST['selected_orders'], 'is_numeric');
        if (!empty($selected_ids)) {
            try {
                $pdo->beginTransaction();
                $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
                
                $stmt = $pdo->prepare("DELETE FROM order_history_items WHERE order_history_id IN ($placeholders)");
                $stmt->execute($selected_ids);
                
                $stmt = $pdo->prepare("DELETE FROM order_history WHERE id IN ($placeholders)");
                $stmt->execute($selected_ids);
                
                $pdo->commit();
                
                $_SESSION['table_counts']['order_history'] -= count($selected_ids);
                $_SESSION['table_counts']['timestamp'] = time();
                
                $success_message = "បានលុបប្រវត្តិការបញ្ជាទិញចំនួន " . count($selected_ids) . " ដោយជោគជ័យ!";
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log('Multiple order history deletion failed: ' . $e->getMessage(), 3, 'errors.log');
                $error_message = "កំហុសក្នុងការលុបប្រវត្តិការបញ្ជាទិញ: " . htmlspecialchars($e->getMessage());
            }
        } else {
            $error_message = "សូមជ្រើសរើសប្រវត្តិការបញ្ជាទិញដែលត្រឹមត្រូវ!";
        }
    } else {
        $error_message = "សូមជ្រើសរើសយ៉ាងហោចណាស់ប្រវត្តិការបញ្ជាទិញមួយ!";
    }
}

// Handle order confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'confirm_order') {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);

    if ($order_id) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();

            if ($order) {
                $stmt = $pdo->prepare("
                    INSERT INTO order_history (
                        user_id, total, points_earned, discount_applied, payment_method,
                        shipping_address, shipping_latitude, shipping_longitude, shippingPhone, mapLink
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $order['user_id'],
                    $order['total'],
                    $order['points_earned'],
                    $order['discount_applied'],
                    $order['payment_method'],
                    $order['shipping_address'],
                    $order['shipping_latitude'],
                    $order['shipping_longitude'],
                    $order['shippingPhone'],
                    $order['mapLink']
                ]);

                $order_history_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                $stmt->execute([$order_id]);
                $order_items = $stmt->fetchAll();

                foreach ($order_items as $item) {
                    $stmt = $pdo->prepare("
                        INSERT INTO order_history_items (order_history_id, product_id, quantity)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$order_history_id, $item['product_id'], $item['quantity']]);
                }

                $stmt = $pdo->prepare("UPDATE users SET points = GREATEST(points + ?, 0) WHERE telegram_id = ?");
                $stmt->execute([$order['points_earned'], $order['user_id']]);

                foreach ($order_items as $item) {
                    $stmt = $pdo->prepare("UPDATE products SET stock = GREATEST(stock - ?, 0) WHERE id = ?");
                    $stmt->execute([$item['quantity'], $item['product_id']]);
                }

                $stmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
                $stmt->execute([$order_id]);

                $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
                $stmt->execute([$order_id]);

                $pdo->commit();
                $success_message = "បានបញ្ជាក់ការបញ្ជាទិញ និងរក្សាទុកក្នុងប្រវត្តិដោយជោគជ័យ!";
            } else {
                $pdo->rollBack();
                $error_message = "រកមិនឃើញការបញ្ជាទិញ!";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log('Order confirmation failed: ' . $e->getMessage(), 3, 'errors.log');
            $error_message = "កំហុសក្នុងការបញ្ជាក់ការបញ្ជាទិញ: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error_message = "លេខសម្គាល់ការបញ្ជាទិញមិនត្រឹមត្រូវ!";
    }
}

// Fetch points configuration
$points_per_dollar = 1.0;
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'points_per_dollar'");
    $stmt->execute();
    $result = $stmt->fetch();
    if ($result) {
        $points_per_dollar = floatval($result['setting_value']);
    }
} catch (PDOException $e) {
    error_log('Points config fetch failed: ' . $e->getMessage(), 3, 'errors.log');
    $error_message = "កំហុសក្នុងការទាញយកការកំណត់ពិន្ទុ: " . htmlspecialchars($e->getMessage());
}

// Fetch subcategories
try {
    $stmt = $pdo->query("SELECT id, name, category FROM subcategories ORDER BY category, name LIMIT 100");
    $subcategories = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Subcategories fetch failed: ' . $e->getMessage(), 3, 'errors.log');
    $error_message = "កំហុសក្នុងការទាញយកប្រភេទរង: " . htmlspecialchars($e->getMessage());
    $subcategories = [];
}

// Fetch unique categories from products
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND TRIM(category) != '' ORDER BY category");
    $product_categories = array_column($stmt->fetchAll(), 'category');
} catch (PDOException $e) {
    error_log('Product categories fetch failed: ' . $e->getMessage(), 3, 'errors.log');
    $error_message = "កំហុសក្នុងការទាញយកប្រភេទផលិតផល: " . htmlspecialchars($e->getMessage());
    $product_categories = [];
}

// Fetch users data
try {
    $stmt = $pdo->query("SELECT telegram_id, full_name, points, current_discount FROM users ORDER BY telegram_id DESC LIMIT 100");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Users fetch failed: ' . $e->getMessage(), 3, 'errors.log');
    $error_message = "កំហុសក្នុងការទាញយកអ្នកប្រើ: " . htmlspecialchars($e->getMessage());
    $users = [];
}

// Fetch products data
try {
    $stmt = $pdo->query("SELECT p.id, p.name, p.price, p.image, p.category, p.subcategory_id, p.points, p.label, p.discount, p.stock, s.name AS subcategory_name 
                         FROM products p 
                         LEFT JOIN subcategories s ON p.subcategory_id = s.id 
                         ORDER BY p.id DESC LIMIT 100");
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Products fetch failed: ' . $e->getMessage(), 3, 'errors.log');
    $error_message = "កំហុសក្នុងការទាញយកផលិតផល: " . htmlspecialchars($e->getMessage());
    $products = [];
}

// Fetch cart data
try {
    $stmt = $pdo->query("SELECT c.id, c.user_id, c.product_id, c.quantity, c.created_at, p.name AS product_name, u.full_name AS user_name 
                         FROM cart c 
                         JOIN products p ON c.product_id = p.id 
                         JOIN users u ON c.user_id = u.telegram_id 
                         ORDER BY c.created_at DESC LIMIT 100");
    $cart_items = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Cart fetch failed: ' . $e->getMessage(), 3, 'errors.log');
    $error_message = "កំហុសក្នុងការទាញយកកន្ត្រក: " . htmlspecialchars($e->getMessage());
    $cart_items = [];
}

// Fetch order history data
try {
    $stmt = $pdo->query("
        SELECT oh.id, oh.user_id, oh.total, oh.points_earned, oh.confirmed_at, oh.discount_applied,
               oh.payment_method, oh.shipping_address, oh.shipping_latitude, oh.shipping_longitude,
               oh.shippingPhone, oh.mapLink, u.full_name AS user_name
        FROM order_history oh
        JOIN users u ON oh.user_id = u.telegram_id
        ORDER BY oh.confirmed_at DESC LIMIT 100
    ");
    $order_history = $stmt->fetchAll();

    foreach ($order_history as &$history) {
        $stmt = $pdo->prepare("
            SELECT ohi.product_id, p.name AS product_name
            FROM order_history_items ohi
            JOIN products p ON ohi.product_id = p.id
            WHERE ohi.order_history_id = ?
        ");
        $stmt->execute([$history['id']]);
        $history['items'] = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log('Order history fetch failed: ' . $e->getMessage(), 3, 'errors.log');
    $error_message = "កំហុសក្នុងការទាញយកប្រវត្តិការបញ្ជាទិញ: " . htmlspecialchars($e->getMessage());
    $order_history = [];
}

// Fetch orders data
try {
    $stmt = $pdo->query("
        SELECT o.id, o.user_id, o.total, o.points_earned, o.created_at, o.discount_applied,
               o.payment_method, o.shipping_address, o.shipping_latitude, o.shipping_longitude,
               o.shippingPhone, o.mapLink, u.full_name AS user_name
        FROM orders o
        JOIN users u ON o.user_id = u.telegram_id
        ORDER BY o.created_at DESC LIMIT 100
    ");
    $orders = $stmt->fetchAll();

    foreach ($orders as &$order) {
        $stmt = $pdo->prepare("
            SELECT oi.product_id, oi.quantity, p.name AS product_name
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order['id']]);
        $order['items'] = $stmt->fetchAll();
    }

    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $orders_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('Orders fetch failed: ' . $e->getMessage(), 3, 'errors.log');
    $error_message = "កំហុសក្នុងការទាញយកការបញ្ជាទិញ: " . htmlspecialchars($e->getMessage());
    $orders = [];
    $orders_count = 0;
}

// Fetch rewards data
try {
    $stmt = $pdo->query("SELECT id, name, points_required, image FROM rewards ORDER BY points_required ASC LIMIT 100");
    $rewards = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Rewards fetch failed: ' . $e->getMessage(), 3, 'errors.log');
    $error_message = "កំហុសក្នុងការទាញយករង្វាន់: " . htmlspecialchars($e->getMessage());
    $rewards = [];
}

// Determine active section
$active_section = isset($_GET['section']) ? filter_input(INPUT_GET, 'section', FILTER_SANITIZE_SPECIAL_CHARS) : 'dashboard';

// Label display mapping
$label_display = [
    'new' => 'ថ្មី',
    'low_stock' => 'ជិតអស់ស្តុក',
    'sold_out' => 'លក់អស់',
    '' => 'គ្មានស្លាក'
];
?>

<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
        integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&family=Koulen&family=Noto+Sans+Khmer:wght@100..900&display=swap" rel="stylesheet">
    <style>
body {
    font-family: 'Kantumruy Pro', Arial, sans-serif;
    background-color: #f3f4f6;
    color: #374151;
}

.sidebar {
    background-color: #1f2937;
    height: 100vh;
    width: 250px;
    position: fixed;
    top: 0;
    left: 0;
    overflow-y: auto;
    transition: transform 0.3s ease-in-out;
}

.sidebar a {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    color: #d1d5db;
    font-weight: 500;
    transition: background-color 0.2s;
}

.sidebar a i {
    margin-right: 0.75rem;
    font-size: 1.25rem;
}

.sidebar a:hover {
    background-color: #374151;
}

.sidebar a.active {
    background-color: #10b981;
    color: #ffffff;
}

.content {
    margin-left: 250px;
    padding: 2rem;
}

.card {
    background-color: #ffffff;
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    padding: 1.5rem;
    margin-bottom: 2rem;
}

.card h2 i {
    margin-right: 0.5rem;
    color: #10b981;
}

/* Wrapper for the table to enable horizontal scrolling */
.table-container {
    overflow-x: auto;
    max-width: 100%;
    margin-bottom: 1.5rem;
    border-radius: 0.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

/* Table styling */
table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background-color: #ffffff;
    font-size: 16px;
    border-radius: 0.5rem;
    overflow: hidden;
    font-weight: 500;
}

/* Sticky headers */
th {
    background-color: #f1f5f9;
    font-family: Koulen;
    color: darkblue;
    font-weight: 100;
    position: sticky;
    top: 0;
    z-index: 10;
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 2px solid #e5e7eb;
    white-space: nowrap;
    text-transform: uppercase;
    font-size: 1rem;
    letter-spacing: 0.05em;
}

/* Table cells */
td {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f1f5f9;
    text-align: left;
    vertical-align: middle;
    max-width: 250px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: normal;
    word-wrap: break-word;
    color: #374151;
}

/* Alternating row colors */
tbody tr:nth-child(odd) {
    background-color: #fafafa;
}

/* Hover effect on rows */
tbody tr:hover {
    background-color: #f1f5f9;
    transition: background-color 0.2s ease-in-out;
}

/* Highlight active row */
tbody tr.active {
    background-color: #e6f3fa;
}

/* Specific column widths */
th:nth-child(1), td:nth-child(1) { /* ID, Name, User */
    min-width: 120px;
    max-width: 200px;
}

th:nth-child(2), td:nth-child(2) { /* Name, Price, Total */
    min-width: 150px;
}

th:nth-child(3), td:nth-child(3) { /* Price, Points, Discount */
    min-width: 100px;
}

th:nth-child(4), td:nth-child(4) { /* Points, Category, Quantity */
    min-width: 100px;
}

th:nth-child(5), td:nth-child(5) { /* Image, Category, Payment Method */
    min-width: 80px;
}

th:nth-child(6), td:nth-child(6) { /* Category, Subcategory, Shipping Address */
    min-width: 120px;
}

th:nth-child(7), td:nth-child(7) { /* Subcategory, Label, Phone */
    min-width: 120px;
}

th:nth-child(8), td:nth-child(8) { /* Label, Actions, Map Link */
    min-width: 150px;
}

th:nth-child(9), td:nth-child(9) { /* Actions, Products */
    min-width: 150px;
}

th:nth-child(10), td:nth-child(10) { /* Order Date, Map Link */
    min-width: 120px;
}

th:nth-child(11), td:nth-child(11) { /* Products, Actions */
    min-width: 150px;
}

/* Image styling */
td img {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 0.25rem;
    transition: transform 0.2s;
}

td img:hover {
    transform: scale(1.1);
}

/* Action buttons */
td a, td button {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    transition: background-color 0.2s;
}

td a.text-blue-500:hover, td button.text-blue-500:hover {
    background-color: #dbeafe;
}

td a.text-red-500:hover, td button.text-red-500:hover {
    background-color: #fee2e2;
}

td a i, td button i {
    margin-right: 0.25rem;
}

/* Input fields in tables */
td input[type="number"],
td input[type="text"] {
    padding: 0.25rem;
    border: 1px solid #d1d5db;
    border-radius: 0.25rem;
    width: 60px;
    font-size: 0.75rem;
}

td input:focus {
    outline: none;
    border-color: #10b981;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
}

/* Modal styling */
.modal {
    display: none;
    position: fixed;
    top: -10rem;
    left: 0;
    width: 100%;
    height: 150%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 50;
}

.modal-content {
    background-color: #ffffff;
    margin: 10% auto;
    padding: 2rem;
    border-radius: 0.5rem;
    width: 90%;
    max-width: 600px;
    position: relative;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    font-size: 1.5rem;
    cursor: pointer;
    color: #6b7280;
    transition: color 0.2s;
}

.close:hover {
    color: #374151;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: 200px;
    }

    .sidebar.open {
        transform: translateX(0);
    }

    .content {
        margin-left: 0;
    }

    .menu-toggle {
        display: block;
    }

    table {
        font-size: 0.75rem;
    }

    th, td {
        padding: 0.5rem 0.75rem;
    }

    th:nth-child(n), td:nth-child(n) {
        min-width: 80px;
    }

    /* Hide less critical columns on mobile */
    th:nth-child(5), td:nth-child(5),
    th:nth-child(6), td:nth-child(6),
    th:nth-child(7), td:nth-child(7),
    th:nth-child(8), td:nth-child(8),
    th:nth-child(10), td:nth-child(10) {
        display: none;
    }

    /* Ensure action column is always visible */
    th:nth-child(9), td:nth-child(9),
    th:nth-child(11), td:nth-child(11) {
        display: table-cell;
        min-width: 100px;
    }

    td:nth-child(6) {
        max-height: 3rem;
        overflow-y: auto;
    }
}

/* Accessibility */
th:focus, td:focus {
    outline: none;
    background-color: #e6f3fa;
}

td a:focus, td button:focus {
    outline: none;
    box-shadow: 0 0 0 2px #10b981;
}

 /* Checkbox styling */
        input[type="checkbox"] {
            accent-color: #10b981;
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        /* Select All checkbox */
        #selectAll {
            margin-left: 8px;
        }

        /* Delete Selected button */
        .delete-selected-btn {
            background-color: #ef4444;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            font-weight: 500;
            transition: background-color 0.2s;
        }

        .delete-selected-btn:hover {
            background-color: #dc2626;
        }

        .delete-selected-btn:disabled {
            background-color: #d1d5db;
            cursor: not-allowed;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-4">
            <h1 class="text-xl font-bold text-white"><i class="fas fa-cog mr-2"></i> Admin Panel</h1>
        </div>
        <nav>
            <a href="?section=dashboard" class="<?php echo $active_section === 'dashboard' ? 'active' : ''; ?>"><i
                    class="fas fa-tachometer-alt"></i> ផ្ទាំងគ្រប់គ្រង</a>
            <a href="?section=points" class="<?php echo $active_section === 'points' ? 'active' : ''; ?>"><i
                    class="fas fa-star"></i> កំណត់ពិន្ទុ</a>
            <a href="?section=subcategories"
                class="<?php echo $active_section === 'subcategories' ? 'active' : ''; ?>"><i
                    class="fas fa-list-ul"></i> ប្រភេទរង</a>
            <a href="?section=rewards" class="<?php echo $active_section === 'rewards' ? 'active' : ''; ?>"><i
                    class="fas fa-gift"></i> គ្រប់គ្រងរង្វាន់</a>
            <a href="?section=users" class="<?php echo $active_section === 'users' ? 'active' : ''; ?>"><i
                    class="fas fa-users"></i> អ្នកប្រើ</a>
            <a href="?section=products" class="<?php echo $active_section === 'products' ? 'active' : ''; ?>"><i
                    class="fas fa-box"></i> ផលិតផល</a>
            <a href="?section=cart" class="<?php echo $active_section === 'cart' ? 'active' : ''; ?>"><i
                    class="fas fa-shopping-cart"></i> កន្ត្រកទំនិញ</a>
            <a href="?section=orders" class="<?php echo $active_section === 'orders' ? 'active' : ''; ?>"><i
                    class="fas fa-file-invoice"></i> ការបញ្ជាទិញ</a>
                    <a href="?section=order_history" class="<?php echo $active_section === 'order_history' ? 'active' : ''; ?>"><i class="fas fa-history"></i> ប្រវត្តិការបញ្ជាទិញ</a>
            <a href="logout.php"
                class="bg-red-500 text-white mt-4 mx-4 rounded-lg text-center flex items-center justify-center"><i
                    class="fas fa-sign-out-alt mr-2"></i> ចាកចេញ</a>
        </nav>
    </div>

    <!-- Content -->
    <div class="content">
        <button class="menu-toggle hidden fixed top-4 left-4 z-50 p-2 bg-gray-800 text-white rounded-lg md:hidden"
            onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>

        <h1 class="text-3xl font-bold text-gray-800 mb-6"><i class="fas fa-tachometer-alt mr-2 text-teal-500"></i>
            ផ្ទាំងគ្រប់គ្រង</h1>

        <?php if ($success_message): ?>
        <div class="card bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </p>
        </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
        <div class="card bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><i class="fas fa-exclamation-circle mr-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </p>
        </div>
        <?php endif; ?>

        <?php if ($active_section === 'dashboard' || $active_section === 'points'): ?>
        <!-- Points Configuration Section -->
        <div class="card">
            <h2 class="text-xl font-semibold text-gray-700 mb-4"><i class="fas fa-star"></i> កំណត់ពិន្ទុ</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_points_config">
                <div>
                    <label for="points_per_dollar" class="block text-gray-700">ពិន្ទុក្នុងមួយដុល្លារ:</label>
                    <input type="number" step="0.1" name="points_per_dollar" id="points_per_dollar"
                        value="<?php echo htmlspecialchars($points_per_dollar); ?>"
                        class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500" min="0" required>
                    <p class="text-sm text-gray-500 mt-1">បញ្ចូលតម្លៃពិន្ទុសម្រាប់ការចំណាយ 1 ដុល្លារ (ឧ. 1.0 = 1
                        ពិន្ទុក្នុង 1 ដុល្លារ)។</p>
                </div>
                <button type="submit"
                    class="bg-teal-500 text-white font-semibold py-2 px-4 rounded-lg hover:bg-teal-600 transition duration-300"><i
                        class="fas fa-save mr-2"></i> រក្សាទុកការកំណត់</button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($active_section === 'dashboard' || $active_section === 'subcategories'): ?>
        <!-- Subcategories Management Section -->
        <div class="card">
            <h2 class="text-xl font-semibold text-gray-700 mb-4"><i class="fas fa-list-ul"></i> គ្រប់គ្រងប្រភេទរង</h2>
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-600 mb-2"><i class="fas fa-plus-circle mr-2"></i>
                    បន្ថែមប្រភេទរងថ្មី</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_subcategory">
                    <div>
                        <label for="subcategory_name" class="block text-gray-700">ឈ្មោះប្រភេទរង:</label>
                        <input type="text" name="name" id="subcategory_name"
                            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500" required>
                    </div>
                    <div>
                        <label for="subcategory_category" class="block text-gray-700">ប្រភេទ:</label>
                        <select name="category" id="subcategory_category"
                            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500" required>
                            <option value="">ជ្រើសរើសប្រភេទ</option>
                            <?php foreach ($product_categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>">
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit"
                        class="bg-teal-500 text-white font-semibold py-2 px-4 rounded-lg hover:bg-teal-600 transition duration-300"><i
                            class="fas fa-plus mr-2"></i> បន្ថែមប្រភេទរង</button>
                </form>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ឈ្មោះ</th>
                        <th>ប្រភេទ</th>
                        <th>សកម្មភាព</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subcategories as $subcategory): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($subcategory['name']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($subcategory['category']); ?>
                        </td>
                        <td>
                            <button
                                onclick="openEditSubcategoryModal(<?php echo htmlspecialchars(json_encode($subcategory)); ?>)"
                                class="text-blue-500 hover:underline mr-2"><i class="fas fa-edit mr-1"></i>
                                កែសម្រួល</button>
                            <a href="?action=delete_subcategory&id=<?php echo htmlspecialchars($subcategory['id']); ?>"
                                class="text-red-500 hover:underline"
                                onclick="return confirm('តើអ្នកប្រាកដជាចង់លុបប្រភេទរងនេះមែនទេ?')"><i
                                    class="fas fa-trash mr-1"></i> លុប</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Edit Subcategory Modal -->
        <div id="editSubcategoryModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeEditSubcategoryModal()">×</span>
                <h3 class="text-lg font-semibold text-gray-600 mb-4"><i class="fas fa-edit mr-2"></i> កែសម្រួលប្រភេទរង
                </h3>
                <form method="POST" class="space-y-4" id="editSubcategoryForm">
                    <input type="hidden" name="action" value="update_subcategory">
                    <input type="hidden" name="subcategory_id" id="edit_subcategory_id">
                    <div>
                        <label for="edit_subcategory_name" class="block text-gray-700">ឈ្មោះប្រភេទរង:</label>
                        <input type="text" name="name" id="edit_subcategory_name"
                            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500" required>
                    </div>
                    <div>
                        <label for="edit_subcategory_category" class="block text-gray-700">ប្រភេទ:</label>
                        <select name="category" id="edit_subcategory_category"
                            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500" required>
                            <option value="">ជ្រើសរើសប្រភេទ</option>
                            <?php foreach ($product_categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category); ?>">
                                <?php echo htmlspecialchars($category); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit"
                        class="bg-teal-500 text-white font-semibold py-2 px-4 rounded-lg hover:bg-teal-600 transition duration-300"><i
                            class="fas fa-save mr-2"></i> រក្សាទុកការកែសម្រួល</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($active_section === 'dashboard' || $active_section === 'rewards'): ?>
        <!-- Rewards Management Section -->
        <div class="card">
            <h2 class="text-xl font-semibold text-gray-700 mb-4"><i class="fas fa-gift"></i> គ្រប់គ្រងរង្វាន់</h2>
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-600 mb-2"><i class="fas fa-plus-circle mr-2"></i>
                    បន្ថែមរង្វាន់ថ្មី</h3>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_reward">
                    <div>
                        <label for="name" class="block text-gray-700">ឈ្មោះរង្វាន់:</label>
                        <input type="text" name="name" id="name"
                            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500" required>
                    </div>
                    <div>
                        <label for="points_required" class="block text-gray-700">ពិន្ទុដែលត្រូវការ:</label>
                        <input type="number" name="points_required" id="points_required"
                            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500" min="1" required>
                    </div>
                    <div>
                        <label for="image" class="block text-gray-700">URL រូបភាព:</label>
                        <input type="url" name="image" id="image"
                            class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500" required>
                    </div>
                    <button type="submit"
                        class="bg-teal-500 text-white font-semibold py-2 px-4 rounded-lg hover:bg-teal-600 transition duration-300"><i
                            class="fas fa-plus mr-2"></i> បន្ថែមរង្វាន់</button>
                </form>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ឈ្មោះ</th>
                        <th>ពិន្ទុដែលត្រូវការ</th>
                        <th>រូបភាព</th>
                        <th>សកម្មភាព</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rewards as $reward): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($reward['name']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($reward['points_required']); ?>
                        </td>
                        <td><img src="<?php echo htmlspecialchars($reward['image']); ?>"
                                alt="<?php echo htmlspecialchars($reward['name']); ?>"
                                class="w-16 h-16 object-cover rounded-md"
                                onerror="this.src='https://via.placeholder.com/150'"></td>
                        <td>
                            <a href="edit_reward.php?id=<?php echo htmlspecialchars($reward['id']); ?>"
                                class="text-blue-500 hover:underline"><i class="fas fa-edit mr-1"></i> កែសម្រួល</a>
                            <a href="?action=delete_reward&id=<?php echo htmlspecialchars($reward['id']); ?>"
                                class="text-red-500 hover:underline ml-2"
                                onclick="return confirm('តើអ្នកប្រាកដជាចង់លុបរង្វាន់នេះមែនទេ?')"><i
                                    class="fas fa-trash mr-1"></i> លុប</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($active_section === 'dashboard' || $active_section === 'users'): ?>
        <!-- Users Section -->
        <div class="card">
            <h2 class="text-xl font-semibold text-gray-700 mb-4"><i class="fas fa-users"></i> អ្នកប្រើ (សរុប:
                <?php echo htmlspecialchars(number_format($users_count)); ?>)
            </h2>
            <table>
                <thead>
                    <tr>
                        <th>Telegram ID</th>
                        <th>ឈ្មោះពេញ</th>
                        <th>ពិន្ទុ</th>
                        <th>បញ្ចុះតម្លៃបច្ចុប្បន្ន (%)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($user['telegram_id']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($user['points']); ?>
                        </td>
                        <td>
                            <?php echo number_format($user['current_discount'], 2); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($active_section === 'dashboard' || $active_section === 'products'): ?>
        <!-- Products Section -->
     
<!-- Products Section -->
<div id="products" class="tab-content <?php echo $active_section === 'products' ? '' : 'hidden'; ?>">
    <h2 class="text-2xl font-bold text-gray-800 mb-4"><i class="fas fa-box mr-2"></i> ផលិតផល</h2>

    <?php if ($success_message): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
        <p><?php echo htmlspecialchars($success_message); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
        <p><?php echo htmlspecialchars($error_message); ?></p>
    </div>
    <?php endif; ?>

    <!-- Manual Odoo Fetch Form -->
    <form method="POST" id="fetchOdooForm" class="mb-4">
        <input type="hidden" name="action" value="fetch_odoo_products">
        <button id="odooImportBtn" type="submit" class="bg-teal-500 text-white font-semibold py-2 px-4 rounded-lg hover:bg-teal-600 transition duration-300">
            <i class="fas fa-sync mr-2"></i> ទាញយកផលិតផលពី Odoo (លុបទាំងអស់/បញ្ចូលថ្មី)
        </button>
        <span id="loadingIndicator" class="ml-2 hidden"><i class="fas fa-spinner fa-spin"></i> កំពុងទាញ...</span>
    </form>

    <script>
    // Auto click the Odoo import button when products tab is active
    document.addEventListener('DOMContentLoaded', function () {
        if (<?php echo json_encode($active_section === 'products'); ?>) {
            setTimeout(function () {
                var btn = document.getElementById('odooImportBtn');
                if (btn && !btn.disabled) {
                    btn.click();
                }
            }, 50000); // delay to allow DOM/render
        }
    });
    </script>

    <!-- Display sync history -->
    <h3 class="text-lg font-semibold text-gray-600 mb-2">ប្រវត្តិនៃការធ្វើសមកាលកម្ម</h3>
    <div class="bg-white shadow rounded-lg p-4 mb-4">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-gray-200">
                    <th class="p-2">លេខសម្គាល់ Odoo</th>
                    <th class="p-2">ឈ្មោះផលិតផល</th>
                    <th class="p-2">សកម្មភាព</th>
                    <th class="p-2">ព័ត៌មានលម្អិត</th>
                    <th class="p-2">ពេលវេលា</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    $stmt = $pdo->query("SELECT * FROM product_sync_history ORDER BY sync_time DESC LIMIT 50");
                    $sync_history = $stmt->fetchAll();
                    foreach ($sync_history as $history): ?>
                        <tr>
                            <td class="p-2"><?php echo htmlspecialchars($history['odoo_id']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($history['product_name']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($history['action']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($history['details']); ?></td>
                            <td class="p-2"><?php echo htmlspecialchars($history['sync_time']); ?></td>
                        </tr>
                <?php endforeach;
                } catch (Exception $e) {
                    echo '<tr><td colspan="5" class="p-2 text-red-500">មិនអាចទាញប្រវត្តិបានទេ</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Product Table -->
    <div class="bg-white shadow rounded-lg p-4">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-gray-200">
                    <th class="p-2">លេខសម្គាល់</th>
                    <th class="p-2">ឈ្មោះ</th>
                    <th class="p-2">តម្លៃ ($)</th>
                    <th class="p-2">ប្រភេទ</th>
                    <th class="p-2">ប្រភេទរង</th>
                    <th class="p-2">រូបភាព</th>
                    <th class="p-2">ពិន្ទុ</th>
                    <th class="p-2">ស្លាក</th>
                    <th class="p-2">បញ្ចុះតម្លៃ (%)</th>
                    <th class="p-2">ស្តុក</th>
                    <th class="p-2">សកម្មភាព</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td class="p-2"><?php echo htmlspecialchars($product['id']); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($product['name']); ?></td>
                    <td class="p-2"><?php echo number_format($product['price'], 2); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($product['category']); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($product['subcategory_name'] ?? 'គ្មាន'); ?></td>
                    <td class="p-2">
                        <?php if ($product['image']): ?>
                        <img src="<?php echo htmlspecialchars($product['image']); ?>" alt="Product Image" class="w-12 h-12 object-cover">
                        <?php else: ?>
                        គ្មាន
                        <?php endif; ?>
                    </td>
                    <td class="p-2"><?php echo htmlspecialchars($product['points']); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($product['label'] ?? 'គ្មាន'); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($product['discount']); ?>%</td>
                    <td class="p-2"><?php echo htmlspecialchars($product['stock']); ?></td>
                    <td class="p-2">
                        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($product)); ?>)" class="text-blue-500 hover:underline"><i class="fas fa-edit"></i> កែ</button>
                        <a href="?action=delete_product&id=<?php echo htmlspecialchars($product['id']); ?>" onclick="return confirm('តើអ្នកប្រាកដទេថាចង់លុបផលិតផលនេះ?')" class="text-red-500 hover:underline ml-2"><i class="fas fa-trash"></i> លុប</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Manual Odoo Sync: Delete all products and insert new data from Odoo
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'fetch_odoo_products'
) {
    try {
        // Authenticate and fetch products from Odoo
        $uid = authenticate_odoo($odoo_url, $odoo_db, $odoo_username, $odoo_api_key);
        $odoo_products = call_odoo_api($odoo_url, $odoo_db, $uid, $odoo_api_key, 'product.template', 'search_read', [
            [],
            ['id', 'name', 'list_price', 'categ_id', 'image_1920', 'qty_available']
        ]);

        // Delete all products
        $pdo->exec("DELETE FROM products");

        // Insert new products
        $inserted = 0;
        foreach ($odoo_products as $odoo_product) {
            $name = filter_var($odoo_product['name'], FILTER_SANITIZE_SPECIAL_CHARS);
            $price = floatval($odoo_product['list_price']);
            $category = is_array($odoo_product['categ_id']) ? filter_var($odoo_product['categ_id'][1], FILTER_SANITIZE_SPECIAL_CHARS) : 'Default';
            $stock = intval($odoo_product['qty_available'] ?? 0);
            $image = !empty($odoo_product['image_1920'])
                ? "https://odoo-test-api.odoo.com/web/image/product.template/{$odoo_product['id']}/image_1920"
                : 'https://example.com/placeholder.jpg';
            $points = 0;
            $discount = 0;
            $label = '';

            $stmt = $pdo->prepare("INSERT INTO products (name, price, category, image, points, label, discount, stock) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $price, $category, $image, $points, $label, $discount, $stock]);
            $inserted++;

            // Log sync history
            $stmtLog = $pdo->prepare("INSERT INTO product_sync_history (odoo_id, product_name, action, details, sync_time) VALUES (?, ?, ?, ?, NOW())");
            $stmtLog->execute([$odoo_product['id'], $name, 'insert', 'Inserted from Odoo']);
        }

        $success_message = "បានលុបទិន្នន័យចាស់ និងបញ្ចូលទិន្នន័យថ្មីពី Odoo ដោយជោគជ័យ! ចំនួនផលិតផល: $inserted";
    } catch (Exception $e) {
        $error_message = "កំហុសក្នុងការធ្វើសមកាលកម្ម Odoo: " . htmlspecialchars($e->getMessage());
    }
}
?>


<!-- Edit Product Modal -->
<div id="editProductModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">×</span>
        <h3 class="text-lg font-semibold text-gray-600 mb-4"><i class="fas fa-edit mr-2"></i> កែសម្រួលផលិតផល</h3>
        <form method="POST" class="space-y-4" id="editProductForm">
            <input type="hidden" name="action" value="update_product">
            <input type="hidden" name="product_id" id="edit_product_id">
            <div>
                <label for="edit_name" class="block text-gray-700">ឈ្មោះផលិតផល:</label>
                <input type="text" name="name" id="edit_name" class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500" required>
            </div>
            <div>
                <label for="edit_price" class="block text-gray-700">តម្លៃ (ដុល្លារ):</label>
                <input type="number" step="0.01" name="price" id="edit_price" class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500" min="0.01" required>
            </div>
            <div>
                <label for="edit_category" class="block text-gray-700">ប្រភេទ:</label>
                <input type="text" name="category" id="edit_category" class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500" required>
            </div>
            <div>
                <label for="edit_subcategory_id" class="block text-gray-700">ប្រភេទរង:</label>
                <select name="subcategory_id" id="edit_subcategory_id" class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                    <option value="">គ្មានប្រភេទរង</option>
                    <?php foreach ($subcategories as $subcategory): ?>
                    <option value="<?php echo htmlspecialchars($subcategory['id']); ?>" data-category="<?php echo htmlspecialchars($subcategory['category']); ?>">
                        <?php echo htmlspecialchars($subcategory['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="edit_image" class="block text-gray-700">URL រូបភាព:</label>
                <input type="url" name="image" id="edit_image" class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500" required>
            </div>
            <div>
                <label for="edit_points" class="block text-gray-700">ពិន្ទុ:</label>
                <input type="number" name="points" id="edit_points" class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500" min="0" required>
            </div>
            <div>
                <label for="edit_discount" class="block text-gray-700">បញ្ចុះតម្លៃ (%):</label>
                <input type="number" step="0.01" name="discount" id="edit_discount" class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500" min="0" max="100" required>
                <p class="text-sm text-gray-500 mt-1">បញ្ចូលបញ្ចុះតម្លៃជាភាគរយ (ឧ. 10 សម្រាប់ 10%)</p>
            </div>
            <div>
                <label for="edit_stock" class="block text-gray-700">ស្តុក:</label>
                <input type="number" name="stock" id="edit_stock" class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500" min="0" required>
                <p class="text-sm text-gray-500 mt-1">បញ្ចូលចំนួនស្តុកផលិតផល (ឧ. 100)</p>
            </div>
            <div>
                <label for="edit_label" class="block text-gray-700">ស្លាក:</label>
                <select name="label" id="edit_label" class="w-full p-2 border rounded-lg focus:ring-2 focus:ring-teal-500">
                    <option value="">គ្មានស្លាក</option>
                    <option value="new">ថ្មី</option>
                    <option value="low_stock">ជិតអស់ស្តុក</option>
                    <option value="sold_out">លក់អស់</option>
                </select>
            </div>
            <button type="submit" class="bg-teal-500 text-white font-semibold py-2 px-4 rounded-lg hover:bg-teal-600 transition duration-300"><i class="fas fa-save mr-2"></i> រក្សាទុកការកែសម្រួល</button>
        </form>
    </div>
</div>
        <?php endif; ?>

        <?php if ($active_section === 'dashboard' || $active_section === 'cart'): ?>
        <!-- Cart Section -->
        <div class="card">
            <h2 class="text-xl font-semibold text-gray-700 mb-4"><i class="fas fa-shopping-cart"></i> កន្ត្រកទំនិញ
                (សរុប:
                <?php echo htmlspecialchars(number_format($cart_count)); ?>)
            </h2>
            <table>
                <thead>
                    <tr>
                        <th>អ្នកប្រើប្រាស់</th>
                        <th>ផលិតផល</th>
                        <th>ចំនួន</th>
                        <th>ថ្ងៃបន្ថែម</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_items as $item): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($item['user_name']); ?> (ID:
                            <?php echo htmlspecialchars($item['user_id']); ?>)
                        </td>
                        <td>
                            <?php echo htmlspecialchars($item['product_name']); ?> (ID:
                            <?php echo htmlspecialchars($item['product_id']); ?>)
                        </td>
                        <td>
                            <?php echo htmlspecialchars($item['quantity']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars(formatTime($item['created_at'])); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($active_section === 'dashboard' || $active_section === 'orders'): ?>
        <!-- Orders Section -->
       <div class="card table-container">
    <h2 class="text-xl font-semibold text-gray-700 mb-4"><i class="fas fa-file-invoice"></i> ការបញ្ជាទិញ (សរុប:
        <?php echo htmlspecialchars(number_format($orders_count)); ?>)
    </h2>
    <table>
        <thead>
            <tr>
                <th>អ្នកប្រើ</th>
                <th>សរុប</th>
                <th>បញ្ចុះតម្លៃ ($)</th>
                <th>ពិន្ទុទទួលបាន</th>
                <th>វិធីបង់ប្រាក់</th>
                <th>អាសយដ្ឋានដឹកជញ្ជូន</th>
                <th>ទូរស័ព្ទ</th>
                <th>ផ្ទាំងផែនទី</th>
                <th>ផលិតផល</th>
                <th>ថ្ងៃបញ្ជាទិញ</th>
                <th>សកម្មភាព</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
            <?php
                // Calculate discount in dollars
                $discount_in_dollars = ($order['discount_applied'] / 100) * $order['total'];
            ?>
            <tr>
                <td>
                    <?php echo htmlspecialchars($order['user_name']); ?> (ID:
                    <?php echo htmlspecialchars($order['user_id']); ?>)
                </td>
                <td>$
                    <?php echo number_format($order['total'], 2); ?>
                </td>
                <td>
                    $<?php echo number_format($discount_in_dollars, 2); ?>
                </td>
                <td>
                    <?php echo htmlspecialchars($order['points_earned']); ?>
                </td>
                <td>
                    <?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?>
                </td>
                <td>
                    <?php echo htmlspecialchars($order['shipping_address'] ?? 'N/A'); ?>
                </td>
                <td>
                    <?php echo htmlspecialchars($order['shippingPhone'] ?? 'N/A'); ?>
                </td>
                <td>
                    <?php if (!empty($order['mapLink'])): ?>
                    <a href="<?php echo htmlspecialchars($order['mapLink']); ?>" target="_blank"
                        class="map-icon-link">
                        <i class="fas fa-map-marker-alt"></i>
                    </a>
                    <?php else: ?>
                    <span>N/A</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    if (!empty($order['items'])) {
                        $product_names = array_map(function($item) { return htmlspecialchars($item['product_name']); }, $order['items']);
                        echo implode(', ', $product_names);
                    } else {
                        echo 'គ្មានផលិតផល';
                    }
                    ?>
                </td>
                <td>
                    <?php echo htmlspecialchars(formatTime($order['created_at'])); ?>
                </td>
                <td>
                    <a href="view_order.php?id=<?php echo htmlspecialchars($order['id']); ?>"
                        class="text-blue-500 hover:underline mr-2"><i class="fas fa-eye mr-1"></i> មើលលម្អិត</a>
                    <!-- Confirm Order Button triggers popup -->
                    <button type="button" class="text-green-500 hover:underline" onclick="openConfirmOrderModal(<?php echo htmlspecialchars($order['id']); ?>)">
                        <i class="fas fa-check mr-1"></i> បញ្ជាក់
                    </button>

                    <!-- Confirm Order Popup Modal -->
                    <div id="confirmOrderModal_<?php echo htmlspecialchars($order['id']); ?>" class="modal" style="display:none;">
                        <div class="modal-content" style="max-width:400px;">
                            <span class="close" onclick="closeConfirmOrderModal(<?php echo htmlspecialchars($order['id']); ?>)">×</span>
                            <div class="mb-4 text-center">
                                <i class="fas fa-exclamation-triangle text-yellow-500 text-3xl mb-2"></i>
                                <p class="text-lg font-semibold text-gray-800 mb-2">តើអ្នកប្រាកដជាចង់បញ្ជាក់ការបញ្ជាទិញនេះមែនទេ?</p>
                                <p class="text-gray-600 text-sm">វានឹងលុបចេញពីតារាងនេះ និងរក្សាទុកក្នុងប្រវត្តិ។</p>
                            </div>
                            <form method="POST" onsubmit="closeConfirmOrderModal(<?php echo htmlspecialchars($order['id']); ?>)">
                                <input type="hidden" name="action" value="confirm_order">
                                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                                <div class="flex justify-end space-x-2 mt-6">
                                    <button type="button" class="bg-gray-300 text-gray-800 px-4 py-2 rounded hover:bg-gray-400" onclick="closeConfirmOrderModal(<?php echo htmlspecialchars($order['id']); ?>)">បោះបង់</button>
                                    <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600"><i class="fas fa-check mr-1"></i> បញ្ជាក់</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <script>
                    function openConfirmOrderModal(orderId) {
                        document.getElementById('confirmOrderModal_' + orderId).style.display = 'block';
                    }
                    function closeConfirmOrderModal(orderId) {
                        document.getElementById('confirmOrderModal_' + orderId).style.display = 'none';
                    }
                    </script>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
        <?php endif; ?>
    
    
    
    <?php if ($active_section === 'dashboard' || $active_section === 'order_history'): ?>
<!-- Order History Section -->
 <div class="card table-container">
            <h2 class="text-xl font-semibold text-gray-700 mb-4"><i class="fas fa-history"></i> ប្រវត្តិការបញ្ជាទិញ</h2>
            <form method="POST" id="deleteOrderHistoryForm">
                <input type="hidden" name="action" value="delete_selected_order_history">
                <div class="mb-4">
                    <button type="submit" class="delete-selected-btn" id="deleteSelectedBtn" disabled>
                        <i class="fas fa-trash mr-2"></i> លុបជម្រើស
                    </button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll" onclick="toggleSelectAll()"></th>
                            <th>អ្នកប្រើ</th>
                            <th>សរុប</th>
                            <th>បញ្ចុះតម្លៃ ($)</th>
                            <th>ពិន្ទុទទួលបាន</th>
                            <th>វិធីបង់ប្រាក់</th>
                            <th>អាសយដ្ឋានដឹកជញ្ជូន</th>
                            <th>ទូរស័ព្ទ</th>
                            <th>ផ្ទាំងផែនទី</th>
                            <th>ផលិតផល</th>
                            <th>ថ្ងៃបញ្ជាក់</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_history as $history): ?>
                        <?php
                            $discount_in_dollars = ($history['discount_applied'] / 100) * $history['total'];
                        ?>
                        <tr>
                            <td><input type="checkbox" name="selected_orders[]" value="<?php echo htmlspecialchars($history['id']); ?>" class="order-checkbox" onclick="updateDeleteButtonState()"></td>
                            <td><?php echo htmlspecialchars($history['user_name']); ?> (ID: <?php echo htmlspecialchars($history['user_id']); ?>)</td>
                            <td>$<?php echo number_format($history['total'], 2); ?></td>
                            <td>$<?php echo number_format($discount_in_dollars, 2); ?></td>
                            <td><?php echo htmlspecialchars($history['points_earned']); ?></td>
                            <td><?php echo htmlspecialchars($history['payment_method'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($history['shipping_address'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($history['shippingPhone'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if (!empty($history['mapLink'])): ?>
                                <a href="<?php echo htmlspecialchars($history['mapLink']); ?>" target="_blank" class="map-icon-link">
                                    <i class="fas fa-map-marker-alt"></i>
                                </a>
                                <?php else: ?>
                                <span>N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                if (!empty($history['items'])) {
                                    $product_names = array_map(function($item) { return htmlspecialchars($item['product_name']); }, $history['items']);
                                    echo implode(', ', $product_names);
                                } else {
                                    echo 'គ្មានផលិតផល';
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars(formatTime($history['confirmed_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        </div>
<?php endif; ?>
</div>

    <script>
    
    document.addEventListener('DOMContentLoaded', function () {
    // Show loading indicator if automatic fetch is happening
    <?php if ($active_section === 'products' && isset($success_message) && strpos($success_message, 'បាននាំចូលផលិតផល') !== false): ?>
        document.getElementById('loadingIndicator').classList.remove('hidden');
        setTimeout(() => {
            document.getElementById('loadingIndicator').classList.add('hidden');
        }, 2000); // Hide after 2 seconds, assuming fetch is complete
    <?php endif; ?>

    // Handle manual fetch button click
    document.getElementById('fetchOdooForm').addEventListener('submit', function () {
        const btn = document.getElementById('odooImportBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> កំពុងទាញ...';
        document.getElementById('loadingIndicator').classList.remove('hidden');
    });
});
    
  function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.order-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
            updateDeleteButtonState();
        }

        function updateDeleteButtonState() {
            const checkboxes = document.querySelectorAll('.order-checkbox');
            const deleteButton = document.getElementById('deleteSelectedBtn');
            const anyChecked = Array.from(checkboxes).some(checkbox => checkbox.checked);
            deleteButton.disabled = !anyChecked;
        }

 document.getElementById('deleteOrderHistoryForm').addEventListener('submit', function(e) {
            const checkboxes = document.querySelectorAll('.order-checkbox:checked');
            if (checkboxes.length === 0) {
                e.preventDefault();
                alert('សូមជ្រើសរើសយ៉ាងហោចណាស់ប្រវត្តិការបញ្ជាទិញមួយ!');
                return;
            }
            if (!confirm('តើអ្នកប្រាកដជាចង់លុបប្រវត្តិការបញ្ជាទិញដែលបានជ្រើសរើសទាំងនេះមែនទេ?')) {
                e.preventDefault();
            }
        });




        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }
        function openEditOrderModal(order) {
            document.getElementById('edit_order_id').value = order.id;
            document.getElementById('edit_points_earned').value = order.points_earned;
            document.getElementById('edit_payment_method').value = order.payment_method || '';
            document.getElementById('edit_shipping_address').value = order.shipping_address || '';
            document.getElementById('edit_shipping_latitude').value = order.shipping_latitude || '';
            document.getElementById('edit_shipping_longitude').value = order.shipping_longitude || '';
            document.getElementById('edit_shippingPhone').value = order.shippingPhone || '';
            document.getElementById('edit_mapLink').value = order.mapLink || '';
            document.getElementById('editOrderModal').style.display = 'block';
        }


        function closeEditOrderModal() {
            document.getElementById('editOrderForm').reset();
            document.getElementById('editOrderModal').style.display = 'none';
        }

        window.onclick = function (event) {
            const orderModal = document.getElementById('editOrderModal');
            if (event.target === orderModal) {
                closeEditOrderModal();
            }
        };


function openEditModal(product) {
    document.getElementById('edit_product_id').value = product.id;
    document.getElementById('edit_name').value = product.name;
    document.getElementById('edit_price').value = product.price;
    document.getElementById('edit_category').value = product.category;
    document.getElementById('edit_subcategory_id').value = product.subcategory_id || '';
    document.getElementById('edit_image').value = product.image;
    document.getElementById('edit_points').value = product.points;
    document.getElementById('edit_label').value = product.label || '';
    document.getElementById('edit_discount').value = product.discount || 0;
    document.getElementById('edit_stock').value = product.stock || 0;
    document.getElementById('editProductModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editProductForm').reset();
    document.getElementById('editProductModal').style.display = 'none';
}

function openEditSubcategoryModal(subcategory) {
    document.getElementById('edit_subcategory_id').value = subcategory.id;
    document.getElementById('edit_subcategory_name').value = subcategory.name;
    document.getElementById('edit_subcategory_category').value = subcategory.category;
    document.getElementById('editSubcategoryModal').style.display = 'block';
}

function closeEditSubcategoryModal() {
    document.getElementById('editSubcategoryForm').reset();
    document.getElementById('editSubcategoryModal').style.display = 'none';
}


window.onclick = function (event) {
    const productModal = document.getElementById('editProductModal');
    const subcategoryModal = document.getElementById('editSubcategoryModal');
    const orderModal = document.getElementById('editOrderModal');
    if (event.target === productModal) {
        closeEditModal();
    } else if (event.target === subcategoryModal) {
        closeEditSubcategoryModal();
    } else if (event.target === orderModal) {
        closeEditOrderModal();
    }
};
    </script>
</body>

</html>