<?php
date_default_timezone_set('Asia/Phnom_Penh');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-Telegram-Init-Data');

$host = 'localhost';
$dbname = 'samann1_mini_app_db';
$username = 'samann1_mini_app_db';
$password = 'samann1_mini_app_db@2025';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES 'utf8mb4'");
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'ការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យបានបរាជ័យ']);
    exit();
}

// Sanitize and validate inputs
$telegram_id = filter_input(INPUT_POST, 'telegram_id', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^[0-9]+$/']])
    ?? filter_input(INPUT_GET, 'telegram_id', FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/^[0-9]+$/']])
    ?? null;
$first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_SPECIAL_CHARS)
    ?? filter_input(INPUT_GET, 'first_name', FILTER_SANITIZE_SPECIAL_CHARS)
    ?? 'Unknown';
$last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_SPECIAL_CHARS)
    ?? filter_input(INPUT_GET, 'last_name', FILTER_SANITIZE_SPECIAL_CHARS)
    ?? '';
$full_name = trim("$first_name $last_name");

// Optional: Validate Telegram initData for security
$init_data = $_SERVER['HTTP_X_TELEGRAM_INIT_DATA'] ?? '';
if ($init_data) {
    // TODO: Implement Telegram initData validation
}

function checkUser($pdo, $telegram_id, $full_name) {
    if (!$telegram_id) {
        return false;
    }
    try {
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegram_id]);
        $user = $stmt->fetch();

        if (!$user) {
            $stmt = $pdo->prepare("INSERT INTO users (telegram_id, full_name, points) VALUES (?, ?, 0)");
            $stmt->execute([$telegram_id, $full_name]);
        } elseif ($user['full_name'] !== $full_name) {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ? WHERE telegram_id = ?");
            $stmt->execute([$full_name, $telegram_id]);
        }
        return true;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'ការត្រួតពិនិត្យអ្នកប្រើបានបរាជ័យ']);
        exit();
    }
}

function calculatePoints($pdo, $price, $product_points = null) {
    if ($product_points !== null) {
        return (int)$product_points;
    }
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'points_per_dollar'");
    $stmt->execute();
    $config = $stmt->fetch();
    $points_per_dollar = $config['setting_value'] ?? 1.0;
    return floor($price * $points_per_dollar);
}

$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';

switch ($action) {
    case 'get_points_config':
        try {
            $stmt = $pdo->prepare("SELECT setting_value AS points_per_dollar FROM settings WHERE setting_key = 'points_per_dollar'");
            $stmt->execute();
            $config = $stmt->fetch();
            echo json_encode(['points_per_dollar' => $config['points_per_dollar'] ?? 1.0]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'មិនអាចទាញការកំណត់ពិន្ទុបាន']);
        }
        break;

    case 'get_categories':
        try {
            $stmt = $pdo->prepare("SELECT DISTINCT category FROM products ORDER BY category");
            $stmt->execute();
            $categories = array_merge(['ទាំងអស់'], array_column($stmt->fetchAll(), 'category'));
            echo json_encode($categories);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'មិនអាចទាញប្រភេទផលិតផលបាន']);
        }
        break;

    case 'get_subcategories':
        try {
            $stmt = $pdo->prepare("SELECT category, id, name FROM subcategories ORDER BY category, name");
            $stmt->execute();
            $subcategories = $stmt->fetchAll();

            $categories = [];
            foreach ($subcategories as $sub) {
                $catIndex = array_search($sub['category'], array_column($categories, 'category'));
                if ($catIndex === false) {
                    $categories[] = [
                        'category' => $sub['category'],
                        'subcategories' => [
                            ['id' => (int)$sub['id'], 'name' => $sub['name']]
                        ]
                    ];
                } else {
                    $categories[$catIndex]['subcategories'][] = [
                        'id' => (int)$sub['id'],
                        'name' => $sub['name']
                    ];
                }
            }

            echo json_encode($categories);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'មិនអាចទាញប្រភេទរងបាន']);
        }
        break;

    case 'get_products':
        $category = filter_input(INPUT_GET, 'category', FILTER_SANITIZE_SPECIAL_CHARS);
        $subcategory = filter_input(INPUT_GET, 'subcategory', FILTER_SANITIZE_SPECIAL_CHARS);
        
        try {
            $query = "SELECT p.id, p.name, p.price, p.category, p.image, p.points, p.label, p.discount, p.stock 
                      FROM products p 
                      LEFT JOIN subcategories s ON p.subcategory_id = s.id";
            $params = [];
            
            if ($category && $category !== 'ទាំងអស់') {
                $query .= " WHERE p.category = ?";
                $params[] = $category;
                if ($subcategory) {
                    $query .= " AND s.name = ?";
                    $params[] = $subcategory;
                }
            }
            
            $query .= " ORDER BY p.id DESC";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $products = $stmt->fetchAll();
            
            foreach ($products as &$product) {
                $product['image'] = $product['image'] ?? 'https://via.placeholder.com/150';
                $product['label'] = $product['label'] ?? '';
                $product['discount'] = (float)($product['discount'] ?? 0);
                $product['stock'] = (int)($product['stock'] ?? 0); // Include stock
            }
            
            echo json_encode($products);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'មិនអាចទាញផលិតផលបាន']);
        }
        break;

    case 'get_cart':
        if (!$telegram_id || !checkUser($pdo, $telegram_id, $full_name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Telegram ID មិនត្រឹមត្រូវ']);
            break;
        }
        try {
            $stmt = $pdo->prepare("SELECT c.id, c.product_id, c.quantity, p.name, p.price, p.image, p.points, p.label, p.discount, p.stock 
                                   FROM cart c JOIN products p ON c.product_id = p.id 
                                   WHERE c.user_id = ?");
            $stmt->execute([$telegram_id]);
            $cart = $stmt->fetchAll();
            foreach ($cart as &$item) {
                $item['image'] = $item['image'] ?? 'https://via.placeholder.com/48';
                $item['label'] = $item['label'] ?? '';
                $item['discount'] = (float)($item['discount'] ?? 0);
                $item['stock'] = (int)($item['stock'] ?? 0); // Include stock
            }
            echo json_encode($cart);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'មិនអាចទាញកន្ត្រកទំនិញបាន']);
        }
        break;

    case 'add_to_cart':
        $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        if (!$telegram_id || !$product_id || !checkUser($pdo, $telegram_id, $full_name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Telegram ID ឬ Product ID មិនត្រឹមត្រូវ']);
            break;
        }
        try {
            $stmt = $pdo->prepare("SELECT id, label, stock FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            if (!$product) {
                http_response_code(400);
                echo json_encode(['error' => 'រកផលិតផលមិនឃើញ']);
                break;
            }
            if ($product['label'] === 'sold_out' || $product['stock'] <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'ផលិតផលនេះលក់អស់ហើយ']);
                break;
            }

            $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$telegram_id, $product_id]);
            $existingItem = $stmt->fetch();

            $new_quantity = $existingItem ? $existingItem['quantity'] + 1 : 1;
            if ($new_quantity > $product['stock']) {
                http_response_code(400);
                echo json_encode(['error' => 'ស្តុកផលិតផលមិនគ្រប់គ្រាន់']);
                break;
            }

            if ($existingItem) {
                $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                $stmt->execute([$new_quantity, $existingItem['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
                $stmt->execute([$telegram_id, $product_id]);
            }
            echo json_encode(['message' => 'បានបន្ថែមផលិតផលទៅកន្ត្រក']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'មិនអាចបន្ថែមទៅកន្ត្រកបាន']);
        }
        break;

    case 'remove_from_cart':
        $cart_id = filter_input(INPUT_POST, 'cart_id', FILTER_VALIDATE_INT);
        if (!$telegram_id || !$cart_id || !checkUser($pdo, $telegram_id, $full_name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Telegram ID ឬ Cart ID មិនត្រឹមត្រូវ']);
            break;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
            $stmt->execute([$cart_id, $telegram_id]);
            echo json_encode(['message' => 'បានលុបផលិតផលចេញពីកន្ត្រក']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'មិនអាចលុបផលិតផលចេញពីកន្ត្រកបាន']);
        }
        break;

    case 'update_cart_quantity':
        $cart_id = filter_input(INPUT_POST, 'cart_id', FILTER_VALIDATE_INT);
        $quantity_change = filter_input(INPUT_POST, 'quantity_change', FILTER_VALIDATE_INT);
        if (!$telegram_id || !$cart_id || !isset($_POST['quantity_change']) || !checkUser($pdo, $telegram_id, $full_name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Telegram ID, Cart ID, ឬការផ្លាស់ប្តូរបរិមាណមិនត្រឹមត្រូវ']);
            break;
        }
        try {
            $stmt = $pdo->prepare("SELECT c.quantity, c.product_id, p.stock 
                                   FROM cart c 
                                   JOIN products p ON c.product_id = p.id 
                                   WHERE c.id = ? AND c.user_id = ?");
            $stmt->execute([$cart_id, $telegram_id]);
            $cart_item = $stmt->fetch();

            if (!$cart_item) {
                http_response_code(404);
                echo json_encode(['error' => 'រកទំនិញក្នុងកន្ត្រកមិនឃើញ']);
                break;
            }

            $new_quantity = $cart_item['quantity'] + $quantity_change;
            if ($new_quantity <= 0) {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $stmt->execute([$cart_id, $telegram_id]);
                echo json_encode(['message' => 'បានលុបទំនិញចេញពីកន្ត្រក', 'new_quantity' => 0]);
            } else {
                if ($new_quantity > $cart_item['stock']) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ស្តុកផលិតផលមិនគ្រប់គ្រាន់']);
                    break;
                }
                $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
                $stmt->execute([$new_quantity, $cart_id, $telegram_id]);
                echo json_encode(['message' => 'បានធ្វើបច្ចុប្បន្នភាពបរិមាណកន្ត្រក', 'new_quantity' => $new_quantity]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'មិនអាចធ្វើបច្ចុប្បន្នភាពបរិមាណកន្ត្រកបាន']);
        }
        break;

    case 'checkout':
        if (!$telegram_id || !checkUser($pdo, $telegram_id, $full_name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Telegram ID មិនត្រឹមត្រូវ']);
            break;
        }

        // Validate and sanitize shipping inputs
        $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_SPECIAL_CHARS);
        $shipping_address = filter_input(INPUT_POST, 'shipping_address', FILTER_SANITIZE_SPECIAL_CHARS);
        $shipping_latitude = filter_input(INPUT_POST, 'shipping_latitude', FILTER_VALIDATE_FLOAT);
        $shipping_longitude = filter_input(INPUT_POST, 'shipping_longitude', FILTER_VALIDATE_FLOAT);
        $shippingPhone = filter_input(INPUT_POST, 'shippingPhone', FILTER_SANITIZE_SPECIAL_CHARS);
        $mapLink = filter_input(INPUT_POST, 'mapLink', FILTER_SANITIZE_URL);

        // Validate required fields
        if (!$shipping_address) {
            http_response_code(400);
            echo json_encode(['error' => 'អាសយដ្ឋានដឹកជញ្ជូនត្រូវការ']);
            break;
        }
        if (!$shippingPhone || !preg_match('/^0[1-9][0-9]{7,8}$/', $shippingPhone)) {
            http_response_code(400);
            echo json_encode(['error' => 'លេខទូរស័ព្ទមិនត្រឹមត្រូវ']);
            break;
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT c.id, c.product_id, c.quantity, p.price, p.points, p.label, p.discount, p.stock 
                                   FROM cart c JOIN products p ON c.product_id = p.id 
                                   WHERE c.user_id = ?");
            $stmt->execute([$telegram_id]);
            $cart = $stmt->fetchAll();

            if (empty($cart)) {
                http_response_code(400);
                echo json_encode(['error' => 'កន្ត្រកទទេ']);
                $pdo->rollBack();
                break;
            }

            // Check for sold-out products or insufficient stock
            foreach ($cart as $item) {
                if ($item['label'] === 'sold_out' || $item['stock'] <= 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'មានផលិតផលលក់អស់នៅក្នុងកន្ត្រក']);
                    $pdo->rollBack();
                    break 2;
                }
                if ($item['quantity'] > $item['stock']) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ស្តុកផលិតផលមិនគ្រប់គ្រាន់សម្រាប់ការបញ្ជាទិញ']);
                    $pdo->rollBack();
                    break 2;
                }
            }

            $total = 0;
            $points_earned = 0;
            $total_discount = 0;
            foreach ($cart as $item) {
                $discounted_price = $item['price'] * (1 - ($item['discount'] / 100));
                $item_total = $discounted_price * $item['quantity'];
                $total += $item_total;
                $points_earned += calculatePoints($pdo, $discounted_price, $item['points']) * $item['quantity'];
                $total_discount += ($item['discount'] * $item['price'] * $item['quantity'] / 100);
            }
            $weighted_discount = $total > 0 ? ($total_discount / array_sum(array_map(function($item) { return $item['price'] * $item['quantity']; }, $cart))) * 100 : 0;

            // Insert order with shipping details
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, total, points_earned, status, discount_applied, payment_method, shipping_address, shipping_latitude, shipping_longitude, shippingPhone, mapLink) 
                                   VALUES (?, ?, ?, 'confirmed', ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $telegram_id,
                $total,
                $points_earned,
                $weighted_discount,
                $payment_method ?: null,
                $shipping_address,
                $shipping_latitude !== false ? $shipping_latitude : null,
                $shipping_longitude !== false ? $shipping_longitude : null,
                $shippingPhone,
                $mapLink ?: null
            ]);
            $order_id = $pdo->lastInsertId();

            // Insert order items and update stock
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) 
                                   VALUES (?, ?, ?, ?)");
            $stockStmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            foreach ($cart as $item) {
                $discounted_price = $item['price'] * (1 - ($item['discount'] / 100));
                $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $discounted_price]);
                $stockStmt->execute([$item['quantity'], $item['product_id']]);
            }

            // Update user points
            $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE telegram_id = ?");
            $stmt->execute([$points_earned, $telegram_id]);

            // Clear cart
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$telegram_id]);

            $pdo->commit();
            echo json_encode([
                'message' => 'ការបញ្ជាទិញជោគជ័យ',
                'order_id' => $order_id,
                'points_earned' => $points_earned
            ]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'ការបញ្ជាទិញបានបរាជ័យ']);
        }
        break;

    case 'get_points':
        if (!$telegram_id || !checkUser($pdo, $telegram_id, $full_name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Telegram ID មិនត្រឹមត្រូវ']);
            break;
        }
        try {
            $stmt = $pdo->prepare("SELECT points FROM users WHERE telegram_id = ?");
            $stmt->execute([$telegram_id]);
            $user = $stmt->fetch();
            echo json_encode(['points' => (int)$user['points']]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'មិនអាចទាញពិន្ទុបាន']);
        }
        break;

    case 'get_orders':
        if (!$telegram_id || !checkUser($pdo, $telegram_id, $full_name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Telegram ID មិនត្រឹមត្រូវ']);
            break;
        }
        try {
            $stmt = $pdo->prepare("SELECT id, total, status, points_earned, created_at, discount_applied, payment_method, shipping_address, shipping_latitude, shipping_longitude, shippingPhone, mapLink 
                                   FROM orders WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$telegram_id]);
            $orders = $stmt->fetchAll();

            foreach ($orders as &$order) {
                $order['created_at'] = (new DateTime($order['created_at']))->format('d/m/Y H:i:s');
            }

            $stmt = $pdo->prepare("SELECT oi.order_id, oi.quantity, oi.price, p.name AS product_name, p.discount 
                                   FROM order_items oi JOIN products p ON oi.product_id = p.id 
                                   WHERE oi.order_id = ?");
            foreach ($orders as &$order) {
                $stmt->execute([$order['id']]);
                $items = $stmt->fetchAll();
                foreach ($items as &$item) {
                    $item['discount'] = (float)($item['discount'] ?? 0);
                }
                $order['items'] = $items;
            }
            echo json_encode($orders);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'មិនអាចទាញការបញ្ជាទិញបាន']);
        }
        break;

    case 'get_rewards':
        try {
            $stmt = $pdo->prepare("SELECT id, name, points_required, image FROM rewards ORDER BY points_required ASC");
            $stmt->execute();
            $rewards = $stmt->fetchAll();
            foreach ($rewards as &$reward) {
                $reward['image'] = $reward['image'] ?? 'https://via.placeholder.com/48';
            }
            echo json_encode($rewards);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'មិនអាចទាញរង្វាន់បាន']);
        }
        break;

    case 'get_reward_redemptions':
        if (!$telegram_id || !checkUser($pdo, $telegram_id, $full_name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Telegram ID មិនត្រឹមត្រូវ']);
            break;
        }
        try {
            $stmt = $pdo->prepare("SELECT rr.id, rr.reward_id, rr.points_required, rr.redeemed_at, r.name AS reward_name 
                                   FROM reward_redemptions rr JOIN rewards r ON rr.reward_id = r.id 
                                   WHERE rr.user_id = ? ORDER BY rr.redeemed_at DESC");
            $stmt->execute([$telegram_id]);
            $redemptions = $stmt->fetchAll();
            foreach ($redemptions as &$redemption) {
                $redemption['redeemed_at'] = (new DateTime($redemption['redeemed_at']))->format('d/m/Y H:i:s');
            }
            echo json_encode($redemptions);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'មិនអាចទាញប្រវត្តិប្តូររង្វាន់បាន']);
        }
        break;

    case 'redeem_reward':
        $reward_id = filter_input(INPUT_POST, 'reward_id', FILTER_VALIDATE_INT);
        if (!$telegram_id || !$reward_id || !checkUser($pdo, $telegram_id, $full_name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Telegram ID ឬ Reward ID មិនត្រឹមត្រូវ']);
            break;
        }
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT name, points_required FROM rewards WHERE id = ?");
            $stmt->execute([$reward_id]);
            $reward = $stmt->fetch();
            if (!$reward) {
                http_response_code(404);
                echo json_encode(['error' => 'រករង្វាន់មិនឃើញ']);
                break;
            }

            $stmt = $pdo->prepare("SELECT points FROM users WHERE telegram_id = ?");
            $stmt->execute([$telegram_id]);
            $user = $stmt->fetch();
            if ($user['points'] < $reward['points_required']) {
                http_response_code(400);
                echo json_encode(['error' => 'ពិន្ទុមិនគ្រប់គ្រាន់']);
                break;
            }

            $stmt = $pdo->prepare("UPDATE users SET points = points - ? WHERE telegram_id = ?");
            $stmt->execute([$reward['points_required'], $telegram_id]);

            $stmt = $pdo->prepare("INSERT INTO reward_redemptions (user_id, reward_id, points_required) VALUES (?, ?, ?)");
            $stmt->execute([$telegram_id, $reward_id, $reward['points_required']]);
            $redemption_time = (new DateTime())->format('d/m/Y H:i:s');

            $stmt = $pdo->prepare("SELECT points FROM users WHERE telegram_id = ?");
            $stmt->execute([$telegram_id]);
            $new_points = (int)$stmt->fetchColumn();

            $pdo->commit();
            echo json_encode([
                'message' => 'បានប្តូររង្វាន់ជោគជ័យ',
                'points' => $new_points,
                'redeemed_at' => $redemption_time
            ]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'មិនអាចប្តូររង្វាន់បាន']);
        }
        break;

    case 'get_product_ratings':
        $product_ids = filter_input(INPUT_GET, 'product_ids', FILTER_SANITIZE_SPECIAL_CHARS);
        if (!$product_ids) {
            http_response_code(400);
            echo json_encode(['error' => 'Product IDs ត្រូវការ']);
            break;
        }
        try {
            $product_id_array = array_filter(array_map('intval', explode(',', $product_ids)));
            if (empty($product_id_array)) {
                http_response_code(400);
                echo json_encode(['error' => 'Product IDs មិនត្រឹមត្រូវ']);
                break;
            }
            $placeholders = implode(',', array_fill(0, count($product_id_array), '?'));
            $stmt = $pdo->prepare("
                SELECT product_id, AVG(rating) as average_rating, COUNT(rating) as rating_count
                FROM product_ratings
                WHERE product_id IN ($placeholders)
                GROUP BY product_id
            ");
            $stmt->execute($product_id_array);
            $ratings = $stmt->fetchAll();

            $result = [];
            foreach ($ratings as $rating) {
                $result[$rating['product_id']] = [
                    'average_rating' => round((float)$rating['average_rating'], 1),
                    'rating_count' => (int)$rating['rating_count']
                ];
            }
            // Include products with no ratings
            foreach ($product_id_array as $id) {
                if (!isset($result[$id])) {
                    $result[$id] = [
                        'average_rating' => 0.0,
                        'rating_count' => 0
                    ];
                }
            }
            echo json_encode($result);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'មិនអាចទាញការវាយតម្លៃផលិតផលបាន']);
        }
        break;

    case 'submit_product_rating':
        $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
        if (!$telegram_id || !$product_id || !$rating || !checkUser($pdo, $telegram_id, $full_name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Telegram ID, Product ID, ឬការវាយតម្លៃមិនត្រឹមត្រូវ']);
            break;
        }
        if ($rating < 1 || $rating > 5) {
            http_response_code(400);
            echo json_encode(['error' => 'ការវាឯុតម្លៃត្រូវនៅចន្លោះ ១ ដល់ ៥']);
            break;
        }
        try {
            // Check if product exists
            $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'រកផលិតផលមិនឃើញ']);
                break;
            }
            // Check if rating exists
            $stmt = $pdo->prepare("SELECT id FROM product_ratings WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$telegram_id, $product_id]);
            $existingRating = $stmt->fetch();

            if ($existingRating) {
                // Update existing rating
                $stmt = $pdo->prepare("UPDATE product_ratings SET rating = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$rating, $existingRating['id']]);
            } else {
                // Insert new rating
                $stmt = $pdo->prepare("INSERT INTO product_ratings (user_id, product_id, rating) VALUES (?, ?, ?)");
                $stmt->execute([$telegram_id, $product_id, $rating]);
            }
            echo json_encode(['message' => 'ការវាយតម្លៃបានជោគជ័យ']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'មិនអាចដាក់ការវាយតម្លៃបាន']);
        }
        break;

    case 'check_user_rating':
        $product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
        if (!$telegram_id || !$product_id || !checkUser($pdo, $telegram_id, $full_name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Telegram ID ឬ Product ID មិនត្រឹមត្រូវ']);
            break;
        }
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_ratings WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$telegram_id, $product_id]);
            $has_rated = $stmt->fetchColumn() > 0;
            echo json_encode(['has_rated' => $has_rated]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'មិនអាចត្រួតពិនិត្យការវាឯុតម្លៃបាន']);
        }
        break;

    case 'delete_product_rating':
        $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        if (!$telegram_id || !$product_id || !checkUser($pdo, $telegram_id, $full_name)) {
            http_response_code(400);
            echo json_encode(['error' => 'Telegram ID ឬ Product ID មិនត្រឹមត្រូវ']);
            break;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM product_ratings WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$telegram_id, $product_id]);
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'រកការវាឯុតម្លៃមិនឃើញ']);
                break;
            }
            echo json_encode(['message' => 'ការវាឯុតម្លៃបានលុបជោគជ័យ']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'មិនអាចលុបការវាឯុតម្លៃបាន']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'សកម្មភាពមិនត្រឹមត្រូវ']);
        break;
}
?>