<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $password);
    
    if ($stmt->execute()) {
        header("Location: login.php");
        exit();
    } else {
        $error = "មានបញ្ហាក្នុងការចុះឈ្មោះ!";
    }
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ចុះឈ្មោះ</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">
    <div class="container mx-auto px-4 py-8 max-w-md">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">ចុះឈ្មោះ</h2>
        <?php if (isset($error)) { ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4"><?php echo $error; ?></div>
        <?php } ?>
        <form method="POST">
            <div class="mb-4">
                <label for="username" class="block text-gray-700 font-semibold mb-2">ឈ្មោះអ្នកប្រើ</label>
                <input type="text" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" id="username" name="username" required>
            </div>
            <div class="mb-4">
                <label for="password" class="block text-gray-700 font-semibold mb-2">លេខសម្ងាត់</label>
                <input type="password" class="w-full p-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" id="password" name="password" required>
            </div>
            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg transition duration-300 w-full">ចុះឈ្មោះ</button>
            <a href="login.php" class="block text-center text-blue-500 hover:underline mt-4">ចូលប្រព័ន្ធ</a>
        </form>
    </div>
</body>
</html>