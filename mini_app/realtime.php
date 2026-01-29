<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable buffering in some servers like Nginx

$host = 'localhost';
$dbname = 'samann1_mini_app_db';
$username = 'samann1_mini_app_db';
$password = 'samann1_mini_app_db@2025';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage(), 3, 'errors.log');
    echo "event: error\n";
    echo "data: Database connection failed\n\n";
    ob_flush();
    flush();
    exit;
}

// Store last known IDs or timestamps to detect new records
$last_check = [
    'users' => ['id' => 0, 'timestamp' => '1970-01-01 00:00:00'],
    'products' => ['id' => 0, 'timestamp' => '1970-01-01 00:00:00'],
    'cart' => ['id' => 0, 'timestamp' => '1970-01-01 00:00:00'],
    'orders' => ['id' => 0, 'timestamp' => '1970-01-01 00:00:00'],
    'rewards' => ['id' => 0, 'timestamp' => '1970-01-01 00:00:00'],
];

// Helper function to format time for Khmer AM/PM
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

// Keep the connection alive and check for new data every 5 seconds
while (true) {
    try {
        // Check users
        $stmt = $pdo->query("SELECT telegram_id, full_name, points, current_discount, updated_at 
                             FROM users 
                             WHERE updated_at > '{$last_check['users']['timestamp']}' 
                             ORDER BY updated_at DESC LIMIT 10");
        $new_users = $stmt->fetchAll();
        if ($new_users) {
            $last_check['users']['timestamp'] = $new_users[0]['updated_at'];
            echo "event: new_users\n";
            echo "data: " . json_encode($new_users) . "\n\n";
        }

        // Check products
        $stmt = $pdo->query("SELECT p.id, p.name, p.price, p.image, p.category, p.subcategory_id, p.points, p.label, 
                                    s.name AS subcategory_name, p.updated_at 
                             FROM products p 
                             LEFT JOIN subcategories s ON p.subcategory_id = s.id 
                             WHERE p.updated_at > '{$last_check['products']['timestamp']}' 
                             ORDER BY p.updated_at DESC LIMIT 10");
        $new_products = $stmt->fetchAll();
        if ($new_products) {
            $last_check['products']['timestamp'] = $new_products[0]['updated_at'];
            echo "event: new_products\n";
            echo "data: " . json_encode($new_products) . "\n\n";
        }

        // Check cart
        $stmt = $pdo->query("SELECT c.id, c.user_id, c.product_id, c.quantity, c.created_at, 
                                    p.name AS product_name, u.full_name AS user_name 
                             FROM cart c 
                             JOIN products p ON c.product_id = p.id 
                             JOIN users u ON c.user_id = u.telegram_id 
                             WHERE c.created_at > '{$last_check['cart']['timestamp']}' 
                             ORDER BY c.created_at DESC LIMIT 10");
        $new_cart_items = $stmt->fetchAll();
        if ($new_cart_items) {
            $last_check['cart']['timestamp'] = $new_cart_items[0]['created_at'];
            echo "event: new_cart_items\n";
            echo "data: " . json_encode(array_map(function($item) {
                $item['created_at_formatted'] = formatTime($item['created_at']);
                return $item;
            }, $new_cart_items)) . "\n\n";
        }

        // Check orders
        $stmt = $pdo->query("SELECT o.id, o.user_id, o.total, o.points_earned, o.created_at, o.discount_applied, 
                                    u.full_name AS user_name 
                             FROM orders o 
                             JOIN users u ON o.user_id = u.telegram_id 
                             WHERE o.created_at > '{$last_check['orders']['timestamp']}' 
                             ORDER BY o.created_at DESC LIMIT 10");
        $new_orders = $stmt->fetchAll();
        if ($new_orders) {
            $last_check['orders']['timestamp'] = $new_orders[0]['created_at'];
            foreach ($new_orders as &$order) {
                $stmt = $pdo->prepare("SELECT oi.product_id, p.name AS product_name 
                                       FROM order_items oi 
                                       JOIN products p ON oi.product_id = p.id 
                                       WHERE oi.order_id = ?");
                $stmt->execute([$order['id']]);
                $order['items'] = $stmt->fetchAll();
                $order['created_at_formatted'] = formatTime($order['created_at']);
            }
            echo "event: new_orders\n";
            echo "data: " . json_encode($new_orders) . "\n\n";
        }

        // Check rewards
        $stmt = $pdo->query("SELECT id, name, points_required, image, updated_at 
                             FROM rewards 
                             WHERE updated_at > '{$last_check['rewards']['timestamp']}' 
                             ORDER BY updated_at DESC LIMIT 10");
        $new_rewards = $stmt->fetchAll();
        if ($new_rewards) {
            $last_check['rewards']['timestamp'] = $new_rewards[0]['updated_at'];
            echo "event: new_rewards\n";
            echo "data: " . json_encode($new_rewards) . "\n\n";
        }

        // Send a ping to keep the connection alive
        echo "event: ping\n";
        echo "data: {}\n\n";

    } catch (PDOException $e) {
        error_log('SSE error: ' . $e->getMessage(), 3, 'errors.log');
        echo "event: error\n";
        echo "data: Database error\n\n";
    }

    ob_flush();
    flush();

    // Check if the client has disconnected
    if (connection_aborted()) {
        exit;
    }

    // Sleep for 5 seconds before the next check
    sleep(5);
}
?>