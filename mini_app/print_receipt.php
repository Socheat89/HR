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
            $stmt = $pdo->prepare("SELECT oi.product_id, p.name AS product_name, p.price 
                                   FROM order_items oi 
                                   JOIN products p ON oi.product_id = p.id 
                                   WHERE oi.order_id = ?");
            $stmt->execute([$order_id]);
            $order_items = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log('Order fetch failed: ' . $e->getMessage(), 3, 'errors.log');
        $error_message = "កំហុសក្នុងការទាញយកការបញ្ជាទិញ�। សូមទាក់ទងអ្នកគ្រប់គ្រង។";
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>បោះពុម្ពបុង - Admin Panel</title>
    <link href="https://fonts.googleapis.comcss2?family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLg0DA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            font-family: 'Noto Sans Khmer', Arial, sans-serif;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }
        .receipt-container {
            width: 80mm; /* Standard width for thermal printers */
            max-width: 300px; /* For A4 or browser display */
            margin: 20px auto;
            padding: 10px;
            background-color: #ffffff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        .receipt-header h2 {
            font-size: 18px;
            margin: 0;
        }
        .receipt-details p {
            margin: 5px 0;
            font-size: 14px;
        }
        .receipt-details strong {
            display: inline-block;
            width: 100px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 5px;
            font-size: 12px;
            text-align: left;
        }
        .items-table th {
            background-color: #f9fafb;
        }
        .receipt-footer {
            text-align: center;
            border-top: 2px dashed #000;
            padding-top: 10px;
            margin-top: 10px;
            font-size: 12px;
        }
        .buttons {
            text-align: center;
            margin-top: 20px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-print {
            background-color: #10b981;
            color: #ffffff;
        }
        .btn-back {
            background-color: #6b7280;
            color: #ffffff;
            margin-left: 10px;
        }
        .error-message {
            background-color: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 10px;
            margin: 20px auto;
            max-width: 300px;
            font-size: 14px;
        }
        @media print {
            .buttons {
                display: none;
            }
            .receipt-container {
                box-shadow: none;
                border: none;
                margin: 0;
                width: 100%;
            }
            body {
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <?php if ($error_message): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error_message); ?>
        </div>
        <div class="buttons">
            <a href="view_order.php?id=<?php echo htmlspecialchars($order_id); ?>" class="btn btn-back">
                <i class="fas fa-arrow-left mr-2"></i> ត្រឡប់ទៅព័ត៌មានការបញ្ជាទイイ
            </a>
        </div>
    <?php else: ?>
        <div class="receipt-container">
            <!-- Receipt Header -->
            <div class="receipt-header">
                <h2>បុងទទួលប្រាក់</h2>
                <p>ហាងរបស់អ្នក</p>
                <p>ទូរស័ព្ទ: 012 345 678</p>
            </div>

            <!-- Receipt Details -->
            <div class="receipt-details">
                <p><strong>លេខបុង:</strong> <?php echo htmlspecialchars($order['id']); ?></p>
                <p><strong>អតិជន:</strong> <?php echo htmlspecialchars($order['user_name']); ?></p>
                <p><strong>លេខអតិថជន:</strong> <?php echo htmlspecialchars($order['user_id']); ?></p>
                <?php
                // Format created_at to 12-hour format with Khmer AM/PM
                try {
                    $date = new DateTime($order['created_at'], new DateTimeZone('Asia/Phnom_Penh'));
                    $formatted_date = $date->format('Y-m-d h:i:s A');
                    $am_pm = $date->format('A') === 'AM' ? 'ព្រឹក' : 'ល្ងាច';
                    $formatted_date_khmer = str_replace(['AM', 'PM'], ['ព្រឹក', 'ល្ងាច'], $formatted_date);
                } catch (Exception $e) {
                    $formatted_date_khmer = 'មិនអាចបង្ហាញកាលបរិច្ឆេទ';
                }
                ?>
                <p><strong>កាលបរិច្ឆេទ:</strong> <?php echo htmlspecialchars($formatted_date_khmer); ?></p>
            </div>

            <!-- Order Items -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th>ផលិតផល</th>
                        <th>តម្លៃ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td>$<?php echo number_format($item['price'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Totals -->
            <div class="receipt-details">
                <p><strong>សរុប:</strong> $<?php echo number_format($order['total'], 2); ?></p>
                <p><strong>បញ្ចុះតម្លៃ:</strong> <?php echo number_format($order['discount_applied'], 2); ?>%</p>
                <p><strong>ពិន្ទុទទួលបាន:</strong> <?php echo htmlspecialchars($order['points_earned']); ?></p>
            </div>

            <!-- Footer -->
            <div class="receipt-footer">
                <p>សូមអរគុណសម្រាប់ការទិញ!</p>
                <p>សូមមកម្តងទៀត</p>
            </div>
        </div>

        <!-- Buttons -->
        <div class="buttons">
            <button class="btn btn-print" onclick="window.print()">
                <i class="fas fa-print mr-2"></i> បោះពុម្ព
            </button>
            <a href="view_order.php?id=<?php echo htmlspecialchars($order_id); ?>" class="btn btn-back">
                <i class="fas fa-arrow-left mr-2"></i> ត្រឡប់
            </a>
        </div>
    <?php endif; ?>
</body>
</html>