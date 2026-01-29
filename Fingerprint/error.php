<!-- error.php -->
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>កំហុស</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-6">
        <div class="bg-white p-6 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold mb-4 text-red-500">កំហុស</h2>
            <p><?php echo htmlspecialchars($_GET['message'] ?? 'មានបញ្ហាមិនស្គាល់។ សូមទាក់ទងអ្នកគ្រប់គ្រង។'); ?></p>
            <a href="admin-2.php" class="mt-4 inline-block bg-blue-500 text-white py-2 px-4 rounded-md">ត្រលប់ក្រោយ</a>
        </div>
    </div>
</body>
</html>