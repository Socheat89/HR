<?php
session_start();
require_once 'db_connect.php';

// Ensure UTF-8 encoding for PHP output
header('Content-Type: text/html; charset=utf-8');

// Function to compress image (reusing the same function from index.php)
function compressImage($source, $destination, $maxSizeBytes = 1048576, $maxWidth = 1024) {
    $info = getimagesize($source);
    if (!$info) {
        throw new Exception("រូបភាពមិនត្រឹមត្រូវ។");
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
        throw new Exception("មិនអាចបង្ហាប់រូបភាពឱ្យតិចជាង 1MB បានទេ។ បានរក្សាទុករូបភាពដើម។");
    }

    unlink($tempFile);
    imagedestroy($image);
    return true;
}

// Fetch item details
try {
    if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
        throw new Exception("លេខសម្គាល់ទំនិញមិនត្រឹមត្រូវ។");
    }

    $item_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM stock_items WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception("រកមិនឃើញទំនិញ។");
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = "កំហុស: " . $e->getMessage();
    header("Location: index.php");
    exit;
}

// Handle form submission for editing
if (isset($_POST['update'])) {
    try {
        $item_name = $_POST['item_name'];
        $quantity = $_POST['quantity'];
        $price = $_POST['price'];
        $category = $_POST['category'];
        $compress_image = isset($_POST['compress_image']) && $_POST['compress_image'] == '1';
        $image_path = $item['image_path'];

        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {
            $upload_dir = 'Uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $image_name = uniqid() . '_' . $_FILES['item_image']['name'];
            $new_image_path = $upload_dir . $image_name;

            if ($compress_image) {
                compressImage($_FILES['item_image']['tmp_name'], $new_image_path);
            } else {
                if (!move_uploaded_file($_FILES['item_image']['tmp_name'], $new_image_path)) {
                    throw new Exception("បរាជ័យក្នុងការផ្ទុករូបភាព។");
                }
            }

            // Delete old image if it exists
            if (!empty($item['image_path']) && file_exists($item['image_path'])) {
                unlink($item['image_path']);
            }

            $image_path = $new_image_path;
        }

        $stmt = $pdo->prepare("UPDATE stock_items SET item_name = ?, quantity = ?, price = ?, category = ?, image_path = ? WHERE id = ?");
        $stmt->execute([$item_name, $quantity, $price, $category, $image_path, $item_id]);

        $_SESSION['success_message'] = "ទំនិញត្រូវបានកែសម្រួលដោយជោគជ័យ!";
        header("Location: index.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['error_message'] = "កំហុសក្នុងការកែសម្រួលទំនិញ: " . $e->getMessage();
        header("Location: edit.php?id=$item_id");
        exit;
    }
}

// Error and success messages
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['error_message']);
unset($_SESSION['success_message']);
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>កែសម្រួលទំនិញ</title>
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
            padding: 1rem;
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

        .form-group .btn-cancel {
            background: #e74c3c;
        }

        .form-group .btn-cancel:hover {
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

        .item-image {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            border-radius: 4px;
            margin: 1rem 0;
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

            .form-group {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1rem;
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

            .form-group {
                display: flex;
                flex-direction: column;
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
                <h1>កែសម្រួលទំនិញ</h1>
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

            <!-- Edit Item Card -->
            <div class="card">
                <h2>កែសម្រួលទំនិញ</h2>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <input type="text" name="item_name" placeholder="ឈ្មោះទំនិញ" value="<?php echo htmlspecialchars($item['item_name']); ?>" required>
                        <input type="number" name="quantity" placeholder="បរិមាណ" value="<?php echo $item['quantity']; ?>" required>
                        <input type="number" step="0.01" name="price" placeholder="តម្លៃ ($)" value="<?php echo $item['price']; ?>" required>
                        <input type="text" name="category" placeholder="ប្រភេទ" value="<?php echo htmlspecialchars($item['category'] ?? ''); ?>">
                        <div>
                            <p>រូបភាពបច្ចុប្បន្ន:</p>
                            <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                                <img src="<?php echo $item['image_path']; ?>" class="item-image" alt="រូបភាពទំនិញ">
                            <?php else: ?>
                                <p>គ្មានរូបភាព</p>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="item_image" accept="image/*">
                        <label>
                            <input type="checkbox" name="compress_image" value="1" checked> បង្ហាប់រូបភាព (ទំហំតិចជាង 1MB)
                        </label>
                        <button type="submit" name="update">កែសម្រួលទំនិញ</button>
                        <button type="button" class="btn-cancel" onclick="window.location.href='index.php'">បោះបង់</button>
                    </div>
                </form>
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
        function hideSuccessModal() {
            const modal = document.getElementById('successModal');
            modal.classList.add('hide');
            modal.querySelector('.modal-content').classList.add('hide');
            setTimeout(() => {
                modal.classList.remove('show', 'hide');
                modal.querySelector('.modal-content').classList.remove('show', 'hide');
                modal.style.display = 'none';
                window.location.href = 'index.php';
            }, 300);
        }

        function hideErrorModal() {
            const modal = document.getElementById('errorModal');
            modal.classList.add('hide');
            modal.querySelector('.modal-content').classList.add('hide');
            setTimeout(() => {
                modal.classList.remove('show', 'hide');
                modal.querySelector('.modal-content').classList.remove('show', 'hide');
                modal.style.display = 'none';
                window.location.href = 'edit.php?id=<?php echo $item_id; ?>';
            }, 300);
        }

        window.onclick = function(event) {
            const successModal = document.getElementById('successModal');
            const errorModal = document.getElementById('errorModal');
            if (event.target == successModal) {
                hideSuccessModal();
            }
            if (event.target == errorModal) {
                hideErrorModal();
            }
        }

        window.hideSuccessModal = hideSuccessModal;
        window.hideErrorModal = hideErrorModal;
    </script>
</body>
</html>