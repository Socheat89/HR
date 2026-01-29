<?php
session_start();

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

$admin_username = 'admin'; // Username សម្រាប់ Admin
$admin_password = 'admin@2025'; // Password សម្រាប់ Admin (ផ្លាស់ប្តូរនៅពេលប្រើជាក់ស្តែង)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === $admin_username && $password === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: dashboard.php');
        exit();
    } else {
        $error = 'ឈ្មោះអ្នកប្រើ ឬលេខសម្ងាត់មិនត្រឹមត្រូវ!';
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ចូលប្រើ Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans Khmer', Arial, sans-serif;
            background-color: #f0f2f5;
        }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <h1 class="text-2xl font-bold text-center text-teal-600 mb-6">ចូលប្រើ Admin Panel</h1>
        <?php if (isset($error)): ?>
            <p class="text-red-500 text-center mb-4"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <div>
                <label for="username" class="block text-gray-700">ឈ្មោះអ្នកប្រើ</label>
                <input type="text" id="username" name="username" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500" required>
            </div>
            <div>
                <label for="password" class="block text-gray-700">លេខសម្ងាត់</label>
                <input type="password" id="password" name="password" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500" required>
            </div>
            <button type="submit" class="w-full bg-teal-500 text-white font-semibold py-2 rounded-lg hover:bg-teal-600 transition duration-300">ចូលប្រើ</button>
        </form>
    </div>
</body>
</html>