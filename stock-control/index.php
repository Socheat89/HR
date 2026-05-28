<?php
// =================================================================
// ផ្នែកទី១៖ ការរៀបចំ និងការកំណត់ (SETUP AND CONFIGURATION)
// =================================================================
session_start();
header('Content-Type: text/html; charset=utf-8');

// ហៅ Logic រួមសម្រាប់រាប់จำนวน Notification និងเชื่อมต่อ DB
require_once 'nav_logic.php';

// បង្កើត CSRF token បើមិនទាន់មាន
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ទាញយក និងលុបข้อความแสดงข้อผิดพลาด/ความสำเร็จออกจาก session
$error_message = $_SESSION['error_message'] ?? '';
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message'], $_SESSION['success_message']);

// =================================================================
// ផ្នែកទី២៖ ฟังก์ชันជំនួយ (HELPER FUNCTIONS)
// =================================================================

/**
 * បង្ហាប់រូបភាពให้มีขนาดไม่เกินที่กำหนด.
 * @param string $source ទីតាំងไฟล์ដើម
 * @param string $destination ទីតាំងไฟล์ដែលត្រូវរក្សាទុក
 * @param int $maxSizeBytes ขนาดสูงสุดเป็น bytes (default 1MB)
 * @param int $maxWidth ความกว้างสูงสุด (default 1024px)
 * @return bool
 * @throws Exception
 */
function compressImage(string $source, string $destination, int $maxSizeBytes = 1048576, int $maxWidth = 1024): bool
{
    $info = getimagesize($source);
    if (!$info) {
        throw new Exception("ไฟล์រូបភាពមិនត្រឹមត្រូវ។");
    }

    $mime = $info['mime'];
    $quality = 75;
    $scale = 1.0;

    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;
        default:
            throw new Exception("ប្រភេទរូបភាពមិនត្រូវបានគាំទ្រ។");
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $newWidth = $width;
    $newHeight = $height;

    $tempFile = tempnam(sys_get_temp_dir(), 'img');

    do {
        $newImage = imagecreatetruecolor($newWidth, $newHeight);

        if ($mime == 'image/png' || $mime == 'image/gif') {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
            imagefill($newImage, 0, 0, $transparent);
        }

        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        if ($mime == 'image/jpeg') {
            imagejpeg($newImage, $tempFile, $quality);
        } elseif ($mime == 'image/png') {
            imagepng($newImage, $tempFile, 9);
        } elseif ($mime == 'image/gif') {
            imagegif($newImage, $tempFile);
        }

        $fileSize = filesize($tempFile);
        imagedestroy($newImage);

        if ($fileSize > $maxSizeBytes) {
            if ($mime == 'image/jpeg') {
                $quality -= 10;
            } else {
                $scale *= 0.9;
                $newWidth = (int)($width * $scale);
                $newHeight = (int)($height * $scale);
            }
        }
    } while ($fileSize > $maxSizeBytes && ($quality > 10 || $scale > 0.5));

    if ($fileSize <= $maxSizeBytes) {
        copy($tempFile, $destination);
    } else {
        copy($source, $destination);
        throw new Exception("មិនអាចបង្ហាប់រូបភាពឱ្យតិចជាង 1MB បានទេ។");
    }

    unlink($tempFile);
    imagedestroy($image);
    return true;
}

/**
 * លុបទំនិញ និងទិន្នន័យដែលពាក់ព័ន្ធ (รวม logic สำหรับ form และ AJAX)
 * @param PDO $pdo Object การเชื่อมต่อฐานข้อมูล
 * @param int $id ID ของទំនិញ
 * @throws Exception
 */
function deleteStockItem(PDO $pdo, int $id): void {
    if ($id <= 0) {
        throw new Exception("លេខសម្គាល់ទំនិញមិនត្រឹមត្រូវ។");
    }

    $pdo->beginTransaction();

    try {
        // ពិនិត្យមើលទំនិញ និងเอารูปภาพ path
        $stmt = $pdo->prepare("SELECT image_path FROM stock_items WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            throw new Exception("រកមិនឃើញទំនិញ។");
        }
        
        // លុបទិន្នន័យពី `stock_request_items` ជាមុនសិន
        $stmt = $pdo->prepare("DELETE FROM stock_request_items WHERE item_id = ?");
        $stmt->execute([$id]);
        error_log("Deleted request items for item_id: $id, rows: " . $stmt->rowCount());

        // លុបទិន្នន័យពាក់ព័ន្ធក្នុង stock_count_history
        $stmt = $pdo->prepare("DELETE FROM stock_count_history WHERE item_id = ?");
        $stmt->execute([$id]);
        error_log("Deleted history for item_id: $id, rows: " . $stmt->rowCount());

        // លុបរូបភាពออกจาก server
        if (!empty($item['image_path']) && file_exists($item['image_path'])) {
            if (!unlink($item['image_path'])) {
                error_log("Failed to delete image: " . $item['image_path']);
            }
        }

        // លុបទំនិញออกจาก stock_items (លុបចុងក្រោយគេ)
        $stmt = $pdo->prepare("DELETE FROM stock_items WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("ការលុបទំនិញបរាជ័យ។");
        }

        $pdo->commit();

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}


// =================================================================
// ផ្នែកទី៣៖ ការគ្រប់គ្រង REQUEST (POST REQUEST HANDLING)
// =================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = "កំហុសសុវត្ថិភាព: CSRF token មិនត្រឹមត្រូវ។";
        if (isset($_POST['ajax_delete'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $errorMessage]);
            exit;
        }
        $_SESSION['error_message'] = $errorMessage;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    try {
        if (isset($_POST['add'])) {
            $item_name = $_POST['item_name'];
            $quantity = $_POST['quantity'];
            $price = $_POST['price'];
            $category = $_POST['category'];
            $compress_image = isset($_POST['compress_image']) && $_POST['compress_image'] == '1';
            $image_path = '';

            if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {
                $upload_dir = 'Uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $image_name = uniqid() . '_' . basename($_FILES['item_image']['name']);
                $image_path = $upload_dir . $image_name;
                if ($compress_image) {
                    compressImage($_FILES['item_image']['tmp_name'], $image_path);
                } else {
                    if (!move_uploaded_file($_FILES['item_image']['tmp_name'], $image_path)) {
                        throw new Exception("បរាជ័យក្នុងការផ្ទុករូបភាព។");
                    }
                }
            }
            
            $stmt = $pdo->prepare("INSERT INTO stock_items (item_name, quantity, price, category, image_path) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$item_name, $quantity, $price, $category, $image_path]);
            $_SESSION['success_message'] = "ទំនិញត្រូវបានបន្ថែមដោយជោគជ័យ!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        elseif (isset($_POST['deduct'])) {
            $id = filter_var($_POST['item_id'], FILTER_VALIDATE_INT);
            $deduct_quantity = filter_var($_POST['deduct_quantity'], FILTER_VALIDATE_INT);

            if (!$id || $id <=0) throw new Exception("លេខសម្គាល់ទំនិញមិនត្រឹមត្រូវ។");
            if (!$deduct_quantity || $deduct_quantity <= 0) throw new Exception("បរិមាណកាត់បន្ថយមិនត្រឹមត្រូវ។");
            
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT quantity FROM stock_items WHERE id = ? FOR UPDATE");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) throw new Exception("រកមិនឃើញទំនិញ។");
            if ($item['quantity'] < $deduct_quantity) throw new Exception("ស្តុកមិនគ្រប់គ្រាន់សម្រាប់កាត់បន្ថយ។");

            $stmt = $pdo->prepare("UPDATE stock_items SET quantity = quantity - ? WHERE id = ?");
            $stmt->execute([$deduct_quantity, $id]);
            $pdo->commit();
            $_SESSION['success_message'] = "ស្តុកត្រូវបានកាត់បន្ថយដោយជោគជ័យ!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        elseif (isset($_POST['delete'])) {
            $id = filter_var($_POST['item_id'], FILTER_VALIDATE_INT);
            deleteStockItem($pdo, $id);
            $_SESSION['success_message'] = "ទំនិញត្រូវបានលុបដោយជោគជ័យ!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
        
        elseif (isset($_POST['ajax_delete'])) {
            $id = filter_var($_POST['item_id'], FILTER_VALIDATE_INT);
            deleteStockItem($pdo, $id);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'ទំនិញត្រូវបានលុបដោយជោគជ័យ!']);
            exit;
        }
        
    } catch (Exception $e) {
        if(isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Request Handling Error: " . $e->getMessage());
        
        if (isset($_POST['ajax_delete'])) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'កំហុស: ' . $e->getMessage()]);
            exit;
        }
        $_SESSION['error_message'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}


// =================================================================
// ផ្នែកទី៤៖ ការទាញយកទិន្នន័យ (DATA FETCHING FOR DISPLAY)
// =================================================================

$items = [];
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
try {
    if ($search_query) {
        $stmt = $pdo->prepare("SELECT * FROM stock_items WHERE item_name LIKE ? ORDER BY id DESC");
        $stmt->execute(["%$search_query%"]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM stock_items ORDER BY id DESC");
        $stmt->execute();
    }
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch error: " . $e->getMessage());
    $error_message = "កំហុសក្នុងការទាញយកទំនិញ: " . $e->getMessage();
}

$current_page = basename($_SERVER['PHP_SELF']);

// =================================================================
// ផ្នែកទី៥៖ ការបង្ហាញ HTML (HTML VIEW)
// =================================================================
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>គ្រប់គ្រងស្តុក</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;600&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bayon&family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary-color: #00b4db; 
            --primary-hover: #0083b0; 
            --light-gray: #f5f7fa; 
            --text-color: #2c3e50; 
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Kantumruy Pro', 'Poppins', sans-serif;
        }

        body {
            background: var(--light-gray);
            color: var(--text-color);
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

        .animate-in {
            animation: slideIn 0.3s ease-out forwards;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 1050;
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

        .modal-content .item-image {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
            border-radius: 4px;
            margin: 1rem 0;
            display: block;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 250px;
            flex-shrink: 0;
            background: #fff;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 2rem 1rem;
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar .nav-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            color: #030303;
            text-decoration: none;
            font-size: 1rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: color 0.2s ease, background 0.2s ease, transform 0.2s ease;
            position: relative;
        }
        .sidebar .nav-item:hover {
            background: #ecf0f1;
            transform: translateX(5px);
        }
        .sidebar .nav-item.active {
            color: #fff;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .sidebar .nav-item i {
            margin-right: 0.85rem;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .notification-badge {
            background-color: #e74c3c;
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: auto;
            min-width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
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

        .error-message, .success-message {
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
            font-size: 0.85rem;
            animation: fadeInScale 0.3s ease-out;
        }

        .error-message {
            background: #ffebee;
            color: #c0392b;
        }

        .success-message {
            background: #e6ffe6;
            color: #2ecc71;
        }

        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
        }

        .add-header {
            padding: 1rem;
            background: #ecf0f1;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s ease;
        }

        .add-header:hover {
            background: #dfe6e9;
        }

        .add-header h2 {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .add-header .toggle-icon {
            font-size: 1.25rem;
            transition: transform 0.3s ease;
        }

        .add-header.active .toggle-icon {
            transform: rotate(180deg);
        }

        .add-content {
            display: none;
            padding: 1rem;
        }

        .add-content.active {
            display: block;
            animation: slideIn 0.3s ease-out;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .form-group input, .form-group button {
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
            width: 100%;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #00b4db;
            box-shadow: 0 0 0 2px rgba(0,180,219,0.2);
            transform: scale(1.02);
        }

        .form-group button {
            background: #00b4db;
            color: #fff;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .form-group button:hover {
            background: #0083b0;
            transform: scale(1.05);
        }

        .form-group .btn-delete {
            background: #e74c3c;
        }

        .form-group .btn-delete:hover {
            background: #c0392b;
            transform: scale(1.05);
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #2c3e50;
        }

        .form-group input[type="checkbox"] {
            width: auto;
            cursor: pointer;
        }

        .search-container {
            padding: 1rem;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            margin-bottom: 1rem;
            animation: slideIn 0.5s ease-out;
        }

        .search-container form {
            display: flex;
            gap: 0.5rem;
        }

        .search-container input[type="text"] {
            flex: 1;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: transform 0.2s ease;
        }

        .search-container input[type="text"]:focus {
            transform: scale(1.02);
        }

        .search-container button {
            padding: 10px 15px;
            background: #00b4db;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .search-container button:hover {
            transform: scale(1.05);
        }

        .table-container {
            overflow-x: auto;
            padding: 1rem;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            animation: slideIn 0.5s ease-out;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        th {
            background: #f1f5f9;
            font-weight: 600;
            text-transform: uppercase;
        }

        tr {
            transition: opacity 0.3s ease;
        }

        .item-image {
            max-width: 60px;
            max-height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .actions a, .actions button {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            color: #fff;
            font-size: 0.85rem;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.2s ease, background 0.2s ease;
        }

        .actions a:hover, .actions button:hover {
            transform: scale(1.1);
        }

        .actions .edit { background: #2ecc71; }
        .actions .delete { background: #e74c3c; }
        .actions .deduct { background: #f39c12; }
        .actions .view { background: #3498db; }
        .actions .view:hover { background: #2980b9; }

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
            z-index: 999;
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
            color: var(--primary-color);
        }

        .bottom-nav .nav-item i {
            display: block;
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }
        
        /* CSS សម្រាប់ Loading Spinner */
        .btn-loader {
            display: none;
            margin-left: 8px;
        }
        #confirmDeleteBtn.loading .btn-loader {
            display: inline-block;
        }
        #confirmDeleteBtn.loading .btn-text {
            display: none;
        }

        @media (min-width: 769px) {
            .sidebar { display: block; }
            .main-content { padding: 2rem; margin-left: 250px; }
            .header { border-radius: 12px; padding: 2rem; }
            .form-group { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
            .add-content { display: block; }
            .add-header .toggle-icon { display: none; }
            .search-container { padding: 1rem; }
            .bottom-nav { display: none; }
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { padding: 1rem 0.5rem 5rem; }
            .form-group { display: flex; flex-direction: column; }
            table { font-size: 0.75rem; }
            th, td { padding: 6px; }
            .item-image { max-width: 40px; max-height: 40px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="header">
                <h1>គ្រប់គ្រងស្តុក</h1>
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
            <?php if ($success_message): ?>
                <div id="successModal" class="modal show">
                    <div class="modal-content show">
                        <span class="close" onclick="hideSuccessModal()">×</span>
                        <h3>ជោគជ័យ</h3>
                        <p><?php echo htmlspecialchars($success_message); ?></p>
                        <div class="form-group">
                            <button onclick="hideSuccessModal()">យល់ព្រម</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="add-header">
                    <h2>បន្ថែមទំនិញថ្មី</h2>
                    <span class="toggle-icon">▼</span>
                </div>
                <div class="add-content">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <input type="text" name="item_name" placeholder="ឈ្មោះទំនិញ" required>
                            <input type="number" name="quantity" placeholder="បរិមាណ" required>
                            <input type="number" step="0.01" name="price" placeholder="តម្លៃ ($)" required>
                            <input type="text" name="category" placeholder="ប្រភេទ">
                            <input type="file" name="item_image" accept="image/*">
                            <label>
                                <input type="checkbox" name="compress_image" value="1" checked> បង្ហាប់រូបភាព (ទំហំតិចជាង 1MB)
                            </label>
                            <button type="submit" name="add">បន្ថែមទំនិញ</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="search-container">
                <form method="GET">
                    <input type="text" name="search" placeholder="ស្វែងរកតាមឈ្មោះទំនិញ..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit">ស្វែងរក</button>
                </form>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>លេខសម្គាល់</th>
                            <th>រូបភាព</th>
                            <th>ឈ្មោះ</th>
                            <th>បរិមាណ</th>
                            <th>តម្លៃ</th>
                            <th>ប្រភេទ</th>
                            <th>សកម្មភាព</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($items) > 0): ?>
                            <?php foreach ($items as $item): ?>
                            <tr data-id="<?php echo $item['id']; ?>">
                                <td><?php echo $item['id']; ?></td>
                                <td>
                                    <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                                        <img src="<?php echo $item['image_path']; ?>" class="item-image" alt="រូបភាពទំនិញ">
                                    <?php else: ?>
                                        គ្មាន
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>$<?php echo number_format($item['price'], 2); ?></td>
                                <td><?php echo htmlspecialchars($item['category'] ?? 'គ្មាន'); ?></td>
                                <td class="actions">
                                    <a href="edit.php?id=<?php echo $item['id']; ?>" class="edit">កែសម្រួល</a>
                                    <button class="delete" onclick="showDeleteModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['item_name']); ?>')">លុប</button>
                                    <button class="deduct" onclick="showDeductModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['item_name']); ?>', <?php echo $item['quantity']; ?>)">កាត់បន្ថយ</button>
                                    <button class="view" onclick="showViewModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['item_name']); ?>', <?php echo $item['quantity']; ?>, <?php echo $item['price']; ?>, '<?php echo addslashes($item['category'] ?? 'គ្មាន'); ?>', '<?php echo addslashes($item['image_path'] ?? ''); ?>')">មើលលម្អិត</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">រកមិនឃើញទំនិញ។</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="deductModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideDeductModal()">×</span>
            <h3>កាត់បន្ថយស្តុក</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <p>ទំនិញ: <span id="deductItemName"></span></p>
                <p>បរិមាណបច្ចុប្បន្ន: <span id="deductCurrentQty"></span></p>
                <input type="hidden" name="item_id" id="deductItemId">
                <div class="form-group">
                    <input type="number" name="deduct_quantity" id="deductQuantity" placeholder="បរិមាណដែលត្រូវកាត់បន្ថយ" min="1" required>
                    <button type="submit" name="deduct">បញ្ជាក់</button>
                </div>
            </form>
        </div>
    </div>

    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideDeleteModal()">×</span>
            <h3>បញ្ជាក់ការលុប</h3>
            <p>តើអ្នកប្រាកដថាចង់លុបទំនិញ "<span id="deleteItemName"></span>" មែនទេ? សកម្មភាពនេះនឹងលុបទិន្នន័យពាក់ព័ន្ធទាំងអស់នៅក្នុងប្រវត្តិការរាប់ស្តុក។</p>
            <form id="deleteForm" method="POST" style="display:none;"> <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                 <input type="hidden" name="item_id" id="deleteItemIdForm">
                 <input type="hidden" name="delete" value="delete">
            </form>
            <div class="form-group">
                <button type="button" id="confirmDeleteBtn" class="btn-delete" onclick="ajaxDeleteItem()">
                    <span class="btn-text">លុប</span>
                    <i class="fa-solid fa-spinner fa-spin btn-loader"></i>
                </button>
                <button type="button" id="cancelDeleteBtn" onclick="hideDeleteModal()">បោះបង់</button>
            </div>
        </div>
    </div>

    <div id="viewModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideViewModal()">×</span>
            <h3>លម្អិតទំនិញ</h3>
            <p><strong>លេខសម្គាល់:</strong> <span id="viewItemId"></span></p>
            <p><strong>ឈ្មោះទំនិញ:</strong> <span id="viewItemName"></span></p>
            <p><strong>បរិមាណ:</strong> <span id="viewQuantity"></span></p>
            <p><strong>តម្លៃ:</strong> <span id="viewPrice"></span></p>
            <p><strong>ប្រភេទ:</strong> <span id="viewCategory"></span></p>
            <p><strong>រូបភាព:</strong></p>
            <img id="viewImage" class="item-image" alt="រូបភាពទំនិញ" style="display: none;">
            <p id="viewNoImage" style="display: none;">គ្មានរូបភាព</p>
            <div class="form-group">
                <button onclick="hideViewModal()">បិទ</button>
            </div>
        </div>
    </div>

    <div class="bottom-nav">
        <a href="dashboard.php" class="nav-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fa-solid fa-house"></i> ផ្ទាំងគ្រប់គ្រង
        </a>
        <a href="../index.php" class="nav-item <?php echo $current_page == '../index.php' ? 'active' : ''; ?>">
            <i class="fa-solid fa-box-archive"></i> ទំនិញ
        </a>
        <a href="reports.php" class="nav-item <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
            <i class="fa-solid fa-chart-simple"></i> របាយការណ៍
        </a>
        <a href="stock_counting.php" class="nav-item <?php echo $current_page == 'stock_counting.php' ? 'active' : ''; ?>">
            <i class="fa-solid fa-clipboard-list"></i> ការរាប់ស្តុក
        </a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const addHeader = document.querySelector('.add-header');
            if(addHeader) {
                addHeader.addEventListener('click', function() {
                    const content = this.nextElementSibling;
                    this.classList.toggle('active');
                    content.classList.toggle('active');
                });
            }

            function showModal(modalId) {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.style.display = 'block';
                    setTimeout(() => {
                        modal.classList.add('show');
                        modal.querySelector('.modal-content').classList.add('show');
                    }, 10);
                }
            }

            function hideModal(modalId) {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.querySelector('.modal-content').classList.add('hide');
                    setTimeout(() => {
                        modal.style.display = 'none';
                        modal.classList.remove('show');
                        modal.querySelector('.modal-content').classList.remove('show', 'hide');
                    }, 300);
                }
            }

            window.showDeductModal = function(id, name, quantity) {
                showModal('deductModal');
                document.getElementById('deductItemId').value = id;
                document.getElementById('deductItemName').textContent = name;
                document.getElementById('deductCurrentQty').textContent = quantity;
                document.getElementById('deductQuantity').max = quantity;
            }

            window.showDeleteModal = function(id, name) {
                showModal('deleteModal');
                document.getElementById('deleteItemIdForm').value = id;
                document.getElementById('deleteItemName').textContent = name;
            }
            
            window.ajaxDeleteItem = function() {
                const itemId = document.getElementById('deleteItemIdForm').value;
                const csrfToken = document.querySelector('input[name="csrf_token"]').value;
                
                const deleteBtn = document.getElementById('confirmDeleteBtn');
                const cancelBtn = document.getElementById('cancelDeleteBtn');

                deleteBtn.classList.add('loading');
                deleteBtn.disabled = true;
                cancelBtn.disabled = true;
                
                fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `ajax_delete=1&item_id=${encodeURIComponent(itemId)}&csrf_token=${encodeURIComponent(csrfToken)}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const row = document.querySelector(`tr[data-id="${itemId}"]`);
                        if(row) {
                            row.style.opacity = '0';
                            setTimeout(() => row.remove(), 300);
                        }
                        hideModal('deleteModal');
                        location.reload(); 
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('AJAX error:', error);
                    alert('AJAX Error: ' + error.message);
                })
                .finally(() => {
                    deleteBtn.classList.remove('loading');
                    deleteBtn.disabled = false;
                    cancelBtn.disabled = false;
                });
            }


            window.showViewModal = function(id, name, quantity, price, category, image_path) {
                showModal('viewModal');
                document.getElementById('viewItemId').textContent = id;
                document.getElementById('viewItemName').textContent = name;
                document.getElementById('viewQuantity').textContent = quantity;
                document.getElementById('viewPrice').textContent = '$' + parseFloat(price).toFixed(2);
                document.getElementById('viewCategory').textContent = category;
                const viewImage = document.getElementById('viewImage');
                const viewNoImage = document.getElementById('viewNoImage');
                if (image_path && image_path !== '') {
                    viewImage.src = image_path;
                    viewImage.style.display = 'block';
                    viewNoImage.style.display = 'none';
                } else {
                    viewImage.style.display = 'none';
                    viewNoImage.style.display = 'block';
                }
            }
            
            window.hideDeductModal = () => hideModal('deductModal');
            window.hideDeleteModal = () => hideModal('deleteModal');
            window.hideViewModal = () => hideModal('viewModal');
            window.hideSuccessModal = () => hideModal('successModal');
            window.hideErrorModal = () => hideModal('errorModal');

            window.onclick = function(event) {
                if (event.target.classList.contains('modal')) {
                    hideModal(event.target.id);
                }
            }
        });
    </script>
</body>
</html>
