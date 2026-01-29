<?php
require_once 'db_connect.php';

// Ensure UTF-8 encoding for PHP output
header('Content-Type: text/html; charset=utf-8');

// Determine current page
$current_page = basename($_SERVER['PHP_SELF']);

// Initialize message variables
$error = '';
$success = '';

// Handle deletion request
if (isset($_GET['id'])) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    
    if ($id === false || $id <= 0) {
        $error = "លេខសម្គាល់ទំនិញមិនត្រឹមត្រូវ។";
    } else {
        try {
            // Get item details for confirmation
            $stmt = $pdo->prepare("SELECT item_name, image_path FROM stock_items WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                $error = "រកមិនឃើញទំនិញ។";
            }
        } catch (PDOException $e) {
            $error = "កំហុសក្នុងការទាញយកទិន្នន័យ: " . $e->getMessage();
        }
    }
}

// Process confirmed deletion
if (isset($_POST['confirm_delete']) && isset($_POST['id'])) {
    $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    
    if ($id === false || $id <= 0) {
        $error = "លេខសម្គាល់ទំនិញមិនត្រឹមត្រូវ។";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Get image path for deletion
            $stmt = $pdo->prepare("SELECT image_path FROM stock_items WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete image if exists
            if ($item && !empty($item['image_path']) && file_exists($item['image_path'])) {
                unlink($item['image_path']);
            }
            
            // Delete related history (assuming stock_history table exists)
            $stmt = $pdo->prepare("DELETE FROM stock_history WHERE item_id = ?");
            $stmt->execute([$id]);
            
            // Delete item from database
            $stmt = $pdo->prepare("DELETE FROM stock_items WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            $success = "ទំនិញត្រូវបានលុបដោយជោគជ័យ!";
            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "កំហុសក្នុងការលុបទំនិញ: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>លុបទំនិញស្តុក</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Noto Sans Khmer', 'Poppins', sans-serif;
        }

        body {
            background: #f4f7fa;
            color: #1e293b;
            line-height: 1.6;
            font-size: 16px;
        }

        .dashboard {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #3b82f6, #1e40af);
            color: #ffffff;
            padding: 30px 20px;
            position: fixed;
            height: 100%;
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        }

        .sidebar h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 2.5rem;
            letter-spacing: 1px;
        }

        .sidebar a {
            color: #e2e8f0;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 14px 20px;
            margin-bottom: 12px;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: rgba(255,255,255,0.2);
            color: #ffffff;
            transform: translateX(5px);
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 40px;
        }

        .header {
            background: #ffffff;
            padding: 25px 35px;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .header h1 {
            color: #1e293b;
            font-size: 2rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .card {
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 6px 12px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .card h2 {
            color: #1e293b;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .confirm-container {
            max-width: 500px;
            margin: 0 auto;
        }

        .confirm-details {
            margin-bottom: 20px;
        }

        .confirm-details p {
            margin: 10px 0;
            color: #64748b;
        }

        .confirm-details p strong {
            color: #1e293b;
        }

        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-delete {
            background: #ef4444;
            color: white;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .btn-cancel {
            background: #e2e8f0;
            color: #1e293b;
        }

        .btn-cancel:hover {
            background: #d1d5db;
        }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .success-message {
            background: #d1fae5;
            color: #059669;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        @media (max-width: 1024px) {
            .sidebar { width: 240px; }
            .main-content { margin-left: 240px; padding: 30px; }
        }

        @media (max-width: 768px) {
            .sidebar { width: 200px; }
            .main-content { margin-left: 200px; }
        }

        @media (max-width: 576px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 20px;
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .header h1 { font-size: 1.5rem; }
            .button-group { flex-direction: column; }
            .btn { width: 100%; }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;600&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <h2>ផ្ទាំងគ្រប់គ្រងស្តុក</h2>
            <a href="dashboard.php" <?php echo $current_page === 'dashboard.php' ? 'class="active"' : ''; ?>>ផ្ទាំងគ្រប់គ្រង</a>
            <a href="index.php" <?php echo $current_page === 'index.php' ? 'class="active"' : ''; ?>>ទំនិញស្តុក</a>
            <a href="reports.php" <?php echo $current_page === 'reports.php' ? 'class="active"' : ''; ?>>របាយការណ៍</a>
            <a href="stock_counting.php" <?php echo $current_page === 'stock_counting.php' ? 'class="active"' : ''; ?>>ការរាប់ស្តុក</a>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>លុបទំនិញស្តុក</h1>
            </div>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif ($success): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="card confirm-container">
                <h2>បញ្ជាក់ការលុប</h2>
                <?php if (isset($item) && !$error): ?>
                    <div class="confirm-details">
                        <p>អ្នកហៀបនឹងលុបទំនិញដូចខាងក្រោម:</p>
                        <p><strong>ឈ្មោះទំនិញ:</strong> <?php echo htmlspecialchars($item['item_name']); ?></p>
                        <?php if ($item['image_path'] && file_exists($item['image_path'])): ?>
                            <p><strong>រូបភាព:</strong></p>
                            <img src="<?php echo $item['image_path']; ?>" alt="រូបភាពទំនិញ" style="max-width: 150px; border-radius: 6px; margin-top: 10px;">
                        <?php endif; ?>
                        <p style="color: #dc2626; margin-top: 15px;">សកម្មភាពនេះមិនអាចស្តារបានទេ។</p>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <div class="button-group">
                            <button type="submit" name="confirm_delete" class="btn btn-delete">លុបទំនិញ</button>
                            <a href="index.php" class="btn btn-cancel">បោះបង់</a>
                        </div>
                    </form>
                <?php else: ?>
                    <p>គ្មានទំនិញត្រូវបានជ្រើសរើសសម្រាប់លុប។ សូមត្រឡប់ទៅទំព័រទំនិញស្តុក។</p>
                    <div class="button-group">
                        <a href="index.php" class="btn btn-cancel">ត្រឡប់ទៅទំនិញស្តុក</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>