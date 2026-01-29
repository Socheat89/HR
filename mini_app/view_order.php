<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Database connection
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
    die('ការតភ្ជាប់ទិន្នន័យបរាជ័យ។ សូមទាក់ទងអ្នកគ្រប់គ្រង។');
}

// Initialize messages
$error_message = '';

// Get order ID from URL
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$order_id) {
    $error_message = "លេខសម្គាល់ការបញ្ជាទិញមិនត្រឹមត្រូវ!";
}

// Fetch order details
$order = null;
$order_items = [];
if (!$error_message) {
    try {
        // Fetch order
        $stmt = $pdo->prepare("SELECT o.id, o.user_id, o.total, o.points_earned, o.created_at, o.discount_applied, u.full_name AS user_name 
                               FROM orders o 
                               JOIN users u ON o.user_id = u.telegram_id 
                               WHERE o.id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();

        if (!$order) {
            $error_message = "រកមិនឃើញការបញ្ជាទិញ!";
        } else {
            // Fetch order items
            $stmt = $pdo->prepare("SELECT oi.product_id, p.name AS product_name, p.price, p.image 
                                   FROM order_items oi 
                                   JOIN products p ON oi.product_id = p.id 
                                   WHERE oi.order_id = ?");
            $stmt->execute([$order_id]);
            $order_items = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log('Order fetch failed: ' . $e->getMessage(), 3, 'errors.log');
        $error_message = "កំហុសក្នុងការទាញយកការបញ្ជាទិញ។ សូមទាក់ទងអ្នកគ្រប់គ្រង។";
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>មើលលម្អិតការបញ្ជាទិញ - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            font-family: 'Noto Sans Khmer', Arial, sans-serif;
            background-color: #f3f4f6;
        }
        .content {
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
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #ffffff;
        }
        th, td {
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            text-align: left;
        }
        th {
            background-color: #f9fafb;
            font-weight: 600;
        }
        tr:hover {
            background-color: #f3f4f6;
        }
    </style>
</head>
<body>
    <div class="content">
        <h1 class="text-3xl font-bold text-gray-800 mb-6"><i class="fas fa-file-invoice mr-2 text-teal-500"></i> មើលលម្អិតការបញ្ជាទិញ</h1>

        <?php if ($error_message): ?>
            <div class="card bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error_message); ?></p>
            </div>
            <a href="?section=orders" class="bg-teal-500 text-white font-semibold py-2 px-4 rounded-lg hover:bg-teal-600 transition duration-300 inline-flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> ត្រឡប់ទៅការបញ្ជាទិញ
            </a>
        <?php else: ?>
            <!-- Order Details -->
            <div class="card">
                <h2 class="text-xl font-semibold text-gray-700 mb-4"><i class="fas fa-info-circle"></i> ព័ត៌មានការបញ្ជាទិញ</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p><strong>លេខសម្គាល់ការបញ្ជាទិញ:</strong> <?php echo htmlspecialchars($order['id']); ?></p>
                        <p><strong>អ្នកប្រើ:</strong> <?php echo htmlspecialchars($order['user_name']); ?> (ID: <?php echo htmlspecialchars($order['user_id']); ?>)</p>
                        <p><strong>សរុប:</strong> $<?php echo number_format($order['total'], 2); ?></p>
                    </div>
                    <div>
                        <p><strong>បញ្ចុះតម្លៃ:</strong> <?php echo number_format($order['discount_applied'], 2); ?>%</p>
                        <p><strong>ពិន្ទុទទួលបាន:</strong> <?php echo htmlspecialchars($order['points_earned']); ?></p>
                        <?php
                        // Format created_at to 12-hour format with Khmer AM/PM
                        $date = new DateTime($order['created_at']);
                        $formatted_date = $date->format('Y-m-d h:i:s A');
                        $am_pm = $date->format('A') === 'AM' ? 'ព្រឹក' : 'ល្ងាច';
                        $formatted_date_khmer = str_replace(['AM', 'PM'], ['ព្រឹក', 'ល្ងាច'], $formatted_date);
                        ?>
                        <p><strong>ថ្ងៃបញ្ជាទិញ:</strong> <?php echo htmlspecialchars($formatted_date_khmer); ?></p>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card">
                <h2 class="text-xl font-semibold text-gray-700 mb-4"><i class="fas fa-box"></i> ផលិតផលក្នុងការបញ្ជាទិញ</h2>
                <?php if (empty($order_items)): ?>
                    <p class="text-gray-600">គ្មានផលិតផលក្នុងការបញ្ជាទិញនេះ។</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>លេខសម្គាល់ផលិតផល</th>
                                <th>ឈ្មោះផលិតផល</th>
                                <th>តម្លៃ</th>
                                <th>រូបភាព</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['product_id']); ?></td>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td><?php echo number_format($item['price'], 2); ?></td>
                                    <td>
                                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>" class="w-16 h-16 object-cover rounded-md" onerror="this.src='https://via.placeholder.com/150'">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Buttons -->
            <div class="buttons mt-4">
                <a href="print_receipt.php?id=<?php echo htmlspecialchars($order_id); ?>" class="bg-blue-500 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-600 transition duration-300 inline-flex items-center mr-2">
                    <i class="fas fa-print mr-2"></i> បោះពុម្ពបុង
                </a>
                <a href="?section=orders" class="bg-teal-500 text-white font-semibold py-2 px-4 rounded-lg hover:bg-teal-600 transition duration-300 inline-flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> ត្រឡប់ទៅការបញ្ជាទិញ
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>