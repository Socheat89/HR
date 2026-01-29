<?php
// ===================================================================
// 1. DATABASE CONFIGURATION & CONNECTION (ការកំណត់រចនាសម្ព័ន្ធ និងការតភ្ជាប់មូលដ្ឋានទិន្នន័យ)
// ===================================================================

$dsn = "mysql:host=localhost;dbname=samann1_scan_logs_worker_db;charset=utf8mb4";
$db_username = 'samann1_scan_logs_worker_db'; 
$password = 'scan_logs_worker_db@2025';
$table_name = 'users';

try {
    $pdo = new PDO($dsn, $db_username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("ការតភ្ជាប់មូលដ្ឋានទិន្នន័យមានបញ្ហា៖ " . $e->getMessage());
}

// ===================================================================
// 2. LOGIC FOR HANDLING FORM SUBMISSIONS (តក្កវិជ្ជាសម្រាប់គ្រប់គ្រងទិន្នន័យពី Form)
// ===================================================================

$user_to_edit = null;
$edit_mode = false;
$error_message = '';
$success_message = '';

// --- គ្រប់គ្រងការលុប (DELETE) ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $id_to_delete = $_GET['id'];
    $sql = "DELETE FROM {$table_name} WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_to_delete]);
    // បង្កើត session message ដើម្បីបង្ហាញពីភាពជោគជ័យ
    session_start();
    $_SESSION['success_message'] = "អ្នកប្រើប្រាស់ត្រូវបានលុបដោយជោគជ័យ។";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- គ្រប់គ្រងការបង្កើត (CREATE) និងកែសម្រួល (UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    $user_id = $_POST['user_id'];
    $full_name = $_POST['full_name'];
    $gender = $_POST['gender'];
    $position = $_POST['position'];

    try {
        if (isset($_POST['id']) && !empty($_POST['id'])) {
            // --- កែសម្រួល (UPDATE) ---
            $id_to_update = $_POST['id'];
            $sql = "UPDATE {$table_name} SET user_id = ?, full_name = ?, gender = ?, position = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $full_name, $gender, $position, $id_to_update]);
            $_SESSION['success_message'] = "ព័ត៌មានអ្នកប្រើប្រាស់ត្រូវបានកែសម្រួលដោយជោគជ័យ។";
        } else {
            // --- បង្កើត (CREATE) ---
            $username = $user_id; 
            $email = $user_id . '@example.com';
            $password_hashed = password_hash('default_password', PASSWORD_DEFAULT);

            $sql = "INSERT INTO {$table_name} (user_id, full_name, gender, position, username, email, password) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $full_name, $gender, $position, $username, $email, $password_hashed]);
            $_SESSION['success_message'] = "អ្នកប្រើប្រាស់ថ្មីត្រូវបានបន្ថែមដោយជោគជ័យ។";
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error_message = "មានបញ្ហា៖ លេខសម្គាល់ (User ID) នេះមានគេប្រើរួចហើយ។ សូមជ្រើសរើសលេខផ្សេង។";
        } else {
            $error_message = "មានបញ្ហាក្នុងការបញ្ជូនទិន្នន័យ៖ " . $e->getMessage();
        }
        $user_to_edit = $_POST;
        if(isset($_POST['id']) && !empty($_POST['id'])) {
            $edit_mode = true;
        }
    }
}

// --- គ្រប់គ្រងការស្នើសុំកែសម្រួល (EDIT) ---
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    if (!$error_message) {
        $edit_mode = true;
        $id_to_edit = $_GET['id'];
        $sql = "SELECT id, user_id, full_name, gender, position FROM {$table_name} WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_to_edit]);
        $user_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// --- ទាញយកសារពី Session ---
session_start();
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // លុបសារចេញបន្ទាប់ពីបង្ហាញ
}

// ===================================================================
// 3. READ ALL USERS FROM DATABASE (អានទិន្នន័យអ្នកប្រើប្រាស់ទាំងអស់)
// ===================================================================
$stmt = $pdo->query("SELECT id, user_id, full_name, gender, position FROM {$table_name} ORDER BY id DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ប្រព័ន្ធគ្រប់គ្រងអ្នកប្រើប្រាស់</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Koulen&family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans Khmer', sans-serif;
        }
        h1, h2, h3 {
             font-family: 'Koulen', cursive;
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

<div class="container mx-auto p-4 md:p-8">
    <header class="text-center mb-10">
        <h1 class="text-4xl md:text-5xl text-blue-700">ប្រព័ន្ធគ្រប់គ្រងអ្នកប្រើប្រាស់</h1>
    </header>

    <!-- បង្ហាញសារជូនដំណឹង (Notifications) -->
    <?php if ($error_message): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md" role="alert">
            <p class="font-bold">មានបញ្ហា!</p>
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md" role="alert">
            <p class="font-bold">ជោគជ័យ!</p>
            <p><?php echo htmlspecialchars($success_message); ?></p>
        </div>
    <?php endif; ?>

    <!-- Form សម្រាប់បន្ថែម និងកែសម្រួល -->
    <div class="bg-white p-6 rounded-lg shadow-lg mb-10">
        <h2 class="text-2xl mb-6 border-b pb-4"><?php echo $edit_mode ? 'កែសម្រួលព័ត៌មានអ្នកប្រើប្រាស់' : 'បន្ថែមអ្នកប្រើប្រាស់ថ្មី'; ?></h2>
        
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" class="space-y-6">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($user_to_edit['id'] ?? ''); ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">លេខសម្គាល់ (User ID)</label>
                    <input type="text" id="user_id" name="user_id" value="<?php echo htmlspecialchars($user_to_edit['user_id'] ?? ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">ឈ្មោះពេញ</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user_to_edit['full_name'] ?? ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
                 <div>
                    <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">ភេទ</label>
                    <select id="gender" name="gender" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">-- សូមជ្រើសរើសភេទ --</option>
                        <option value="ប្រុស" <?php echo (isset($user_to_edit['gender']) && $user_to_edit['gender'] == 'ប្រុស') ? 'selected' : ''; ?>>ប្រុស</option>
                        <option value="ស្រី" <?php echo (isset($user_to_edit['gender']) && $user_to_edit['gender'] == 'ស្រី') ? 'selected' : ''; ?>>ស្រី</option>
                    </select>
                </div>
                <div>
                    <label for="position" class="block text-sm font-medium text-gray-700 mb-1">តួនាទី</label>
                    <input type="text" id="position" name="position" value="<?php echo htmlspecialchars($user_to_edit['position'] ?? ''); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <div class="flex items-center justify-end space-x-4 pt-4">
                <?php if ($edit_mode): ?>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="px-6 py-2 bg-gray-600 text-white font-semibold rounded-md hover:bg-gray-700 transition duration-200">បោះបង់</a>
                <?php endif; ?>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white font-semibold rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-200">
                    <?php echo $edit_mode ? 'រក្សាទុកការផ្លាស់ប្តូរ' : 'បន្ថែមអ្នកប្រើប្រាស់'; ?>
                </button>
            </div>
        </form>
    </div>

    <!-- តារាងបង្ហាញបញ្ជីអ្នកប្រើប្រាស់ -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
         <h2 class="text-2xl p-6">បញ្ជីឈ្មោះអ្នកប្រើប្រាស់</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-600">
                <thead class="text-xs text-gray-700 uppercase bg-gray-200">
                    <tr>
                        <th scope="col" class="px-6 py-3">#</th>
                        <th scope="col" class="px-6 py-3">លេខសម្គាល់</th>
                        <th scope="col" class="px-6 py-3">ឈ្មោះពេញ</th>
                        <th scope="col" class="px-6 py-3">ភេទ</th>
                        <th scope="col" class="px-6 py-3">តួនាទី</th>
                        <th scope="col" class="px-6 py-3 text-center">សកម្មភាព</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr class="bg-white border-b">
                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">មិនទាន់មានទិន្នន័យនៅឡើយទេ។</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $index => $user): ?>
                            <tr class="bg-white border-b hover:bg-gray-50">
                                <td class="px-6 py-4"><?php echo $index + 1; ?></td>
                                <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($user['user_id']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($user['gender']); ?></td>
                                <td class="px-6 py-4"><?php echo htmlspecialchars($user['position']); ?></td>
                                <td class="px-6 py-4 text-center space-x-2">
                                    <a href="?action=edit&id=<?php echo $user['id']; ?>" class="inline-block px-3 py-1 text-sm font-medium text-white bg-green-500 rounded-md hover:bg-green-600 transition">កែសម្រួល</a>
                                    <a href="?action=delete&id=<?php echo $user['id']; ?>" class="inline-block px-3 py-1 text-sm font-medium text-white bg-red-500 rounded-md hover:bg-red-600 transition" onclick="return confirm('តើអ្នកពិតជាចង់លុបអ្នកប្រើប្រាស់នេះមែនទេ?');">លុប</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <footer class="text-center text-gray-500 mt-10 text-sm">
        <p>© <?php echo date('Y'); ?> ប្រព័ន្ធគ្រប់គ្រងអ្នកប្រើប្រាស់ | រចនាដោយ AI</p>
    </footer>

</div>

</body>
</html>