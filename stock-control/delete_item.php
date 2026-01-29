<?php
session_start();
require_once 'db_connect.php';

// Ensure UTF-8 encoding for PHP output
header('Content-Type: text/html; charset=utf-8');

// Initialize error and success messages
$error_message = '';
$success_message = '';

// Enable error logging
ini_set('display_errors', 0); // Set to 1 for debugging on screen
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_log("Starting deletion process in delete.php");

// Handle deletion
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $id = $_GET['id'];
    error_log("Attempting to delete item_id: $id");

    try {
        $pdo->beginTransaction();

        // Get item details for image deletion and name
        $stmt = $pdo->prepare("SELECT image_path, item_name FROM stock_items WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            error_log("Item not found for item_id: $id");
            throw new Exception("រកមិនឃើញទំនិញ។");
        }
        error_log("Found item: {$item['item_name']}, image_path: " . ($item['image_path'] ?? 'none'));

        // Delete related records from stock_request_items
        $stmt = $pdo->prepare("DELETE FROM stock_request_items WHERE item_id = ?");
        $stmt->execute([$id]);
        $deleted_rows = $stmt->rowCount();
        error_log("Deleted $deleted_rows rows from stock_request_items for item_id: $id");

        // Check for other related tables (add more as needed based on schema)
        // Example: $stmt = $pdo->prepare("DELETE FROM stock_history WHERE item_id = ?");
        // $stmt->execute([$id]);
        // error_log("Deleted rows from stock_history for item_id: $id");

        // Delete image if exists
        if (!empty($item['image_path']) && file_exists($item['image_path'])) {
            if (!is_writable($item['image_path'])) {
                error_log("Image not writable: " . $item['image_path']);
                throw new Exception("មិនអាចលុបរូបភាពបានទេ ដោយសារបញ្ហាសិទ្ធិឯកសារ។");
            }
            if (!unlink($item['image_path'])) {
                error_log("Failed to delete image: " . $item['image_path']);
                throw new Exception("បរាជ័យក្នុងការលុបរូបភាព។");
            }
            error_log("Deleted image: " . $item['image_path']);
        }

        // Delete item from stock_items
        $stmt = $pdo->prepare("DELETE FROM stock_items WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() === 0) {
            error_log("No rows deleted from stock_items for item_id: $id");
            throw new Exception("មិនអាចលុបទំនិញបានទេ។");
        }

        $pdo->commit();
        error_log("Successfully deleted item_id: $id");
        $_SESSION['success_message'] = "ទំនិញ '" . htmlspecialchars($item['item_name']) . "' ត្រូវបានលុបដោយជោគជ័យ!";
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("PDO Error during deletion: " . $e->getMessage() . " | Code: " . $e->getCode());
        $error_message = "កំហុសក្នុងការលុបទំនិញ: " . ($e->getCode() == 23000 ? "ទំនិញនេះកំពុងត្រូវបានប្រើប្រាស់ក្នុងសំណើរស្តុក ឬទិន្នន័យផ្សេងទៀត។" : $e->getMessage());
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("General Error during deletion: " . $e->getMessage());
        $error_message = "កំហុសក្នុងការលុបទំនិញ: " . $e->getMessage();
    }
} else {
    error_log("Invalid or missing item_id in GET request");
    $error_message = "លេខសម្គាល់ទំនិញមិនត្រឹមត្រូវ។";
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>លុបទំនិញ</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Noto Sans Khmer', 'Poppins', sans-serif;
        }

        body {
            background: #f5f7fa;
            color: #2c3e50;
            line-height: 1.5;
            overflow-x: hidden;
        }

        @keyframes fadeInScale {
            from { opacity: 0; transform: scale(0.8) translateY(20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        @keyframes fadeOutSlide {
            from { opacity: 1; transform: scale(1) translateY(0); }
            to { opacity: 0; transform: scale(0.8) translateY(20px); }
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            opacity: 1;
        }

        .modal-content {
            background: #fff;
            margin: 10% auto;
            padding: 1.5rem;
            width: 90%;
            max-width: 400px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            transform: scale(0.8) translateY(20px);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }

        .modal-content.show {
            transform: scale(1) translateY(0);
            opacity: 1;
            animation: fadeInScale 0.3s ease-out forwards;
        }

        .modal-content.hide {
            animation: fadeOutSlide 0.3s ease-out forwards;
        }

        .modal-content .close {
            float: right;
            font-size: 1.5rem;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .modal-content .close:hover {
            transform: rotate(90deg);
        }

        .modal-content h3 {
            margin-bottom: 1rem;
            color: #2c3e50;
        }

        .modal-content p {
            margin-bottom: 0.75rem;
            color: #64748b;
        }

        .container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            max-width: calc(100% - 2rem);
            margin: 0 auto;
        }

        .sidebar {
            width: 250px;
            flex-shrink: 0;
            background: #fff;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            padding: 2rem 1rem;
            display: none;
            animation: slideIn 0.5s ease-out;
        }

        .sidebar .nav-item {
            display: block;
            padding: 1rem;
            color: #7f8c8d;
            text-decoration: none;
            font-size: 1rem;
            transition: color 0.2s ease, background 0.2s ease, transform 0.2s ease;
        }

        .sidebar .nav-item:hover {
            background: #ecf0f1;
            transform: translateX(5px);
        }

        .sidebar .nav-item.active {
            color: #00b4db;
            background: #e6f3f8;
        }

        .sidebar .nav-item span {
            margin-right: 0.5rem;
        }

        .main-content {
            flex: 1;
            padding: 1rem;
            width: 100%;
        }

        .header {
            background: linear-gradient(135deg, #00b4db, #0083b0);
            color: #fff;
            padding: 1.5rem 1rem;
            border-radius: 0 0 20px 20px;
            text-align: center;
            margin-bottom: 1.5rem;
            animation: slideIn 0.5s ease-out;
        }

        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .error-message {
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 0.85rem;
            background: #ffebee;
            color: #c0392b;
            animation: fadeInScale 0.3s ease-out;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .form-group button {
            padding: 10px;
            border: none;
            border-radius: 6px;
            font-size: 0.9rem;
            width: 100%;
            cursor: pointer;
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .form-group .btn-back {
            background: #00b4db;
            color: #fff;
        }

        .form-group .btn-back:hover {
            background: #0083b0;
            transform: scale(1.05);
        }

        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-around;
            padding: 0.5rem 0;
            border-radius: 20px 20px 0 0;
            animation: slideIn 0.5s ease-out;
        }

        .bottom-nav .nav-item {
            text-align: center;
            padding: 0.5rem;
            color: #7f8c8d;
            text-decoration: none;
            font-size: 0.75rem;
            flex: 1;
            transition: transform 0.2s ease;
        }

        .bottom-nav .nav-item:hover {
            transform: scale(1.1);
        }

        .bottom-nav .nav-item.active {
            color: #00b4db;
        }

        .bottom-nav .nav-item span {
            display: block;
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }

        @media (min-width: 769px) {
            .container {
                flex-direction: row;
            }

            .sidebar {
                display: block;
                width: 250px;
            }

            .main-content {
                padding: 2rem;
            }

            .header {
                border-radius: 12px;
                padding: 2rem;
            }

            .bottom-nav {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }

            .main-content {
                padding: 1rem 0.5rem 5rem;
            }
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;600&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation for PC -->
        <nav class="sidebar">
            <a href="dashboard.php" class="nav-item"> <span>🏠</span> ផ្ទាំងគ្រប់គ្រង </a>
            <a href="index.php" class="nav-item active"> <span>📦</span> ទំនិញ </a>
            <a href="reports.php" class="nav-item"> <span>📊</span> របាយការណ៍ </a>
            <a href="stock_counting.php" class="nav-item"> <span>🔢</span> ការរាប់ស្តុក </a>
            <a href="review_requests.php" class="nav-item"> <span>🔍</span> ពិនិត្យសំណើរ </a>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>លុបទំនិញ</h1>
            </div>

            <?php if ($error_message): ?>
                <div id="errorModal" class="modal show">
                    <div class="modal-content show">
                        <span class="close" onclick="hideErrorModal()">×</span>
                        <h3>កំហុស</h3>
                        <p><?php echo htmlspecialchars($error_message); ?></p>
                        <div class="form-group">
                            <button onclick="hideErrorModal()">យល់ព្រម</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <button class="btn-back" onclick="window.location.href='index.php'">ត្រឡប់ក្រោយ</button>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation for Mobile -->
    <div class="bottom-nav">
        <a href="dashboard.php" class="nav-item"> <span>🏠</span> ផ្ទាំងគ្រប់គ្រង </a>
        <a href="index.php" class="nav-item active"> <span>📦</span> ទំនិញ </a>
        <a href="reports.php" class="nav-item"> <span>📊</span> របាយការណ៍ </a>
        <a href="stock_counting.php" class="nav-item"> <span>🔢</span> ការរាប់ស្តុក </a>
    </div>

    <script>
        function hideErrorModal() {
            const modal = document.getElementById('errorModal');
            modal.classList.add('hide');
            modal.querySelector('.modal-content').classList.add('hide');
            setTimeout(() => {
                modal.classList.remove('show', 'hide');
                modal.querySelector('.modal-content').classList.remove('show', 'hide');
                modal.style.display = 'none';
                window.location.href = 'index.php';
            }, 300);
        }

        window.onclick = function(event) {
            const errorModal = document.getElementById('errorModal');
            if (event.target == errorModal) {
                hideErrorModal();
            }
        }

        window.hideErrorModal = hideErrorModal;
    </script>
</body>
</html>