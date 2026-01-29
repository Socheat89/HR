<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$host = 'localhost';
$dbname = 'samann1_mini_app_db';
$username = 'samann1_mini_app_db';
$password = 'samann1_mini_app_db@2025';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// បន្ថែមផលិតផល
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = $_POST['name'];
    $price = $_POST['price'];
    $image = $_POST['image'];
    $category = $_POST['category'];
    $stock = $_POST['stock'];

    $stmt = $pdo->prepare("INSERT INTO products (name, price, image, category, stock) VALUES (:name, :price, :image, :category, :stock)");
    $stmt->execute([
        ':name' => $name,
        ':price' => $price,
        ':image' => $image,
        ':category' => $category,
        ':stock' => $stock
    ]);
    header('Location: products.php');
    exit();
}

// កែផលិតផល
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $price = $_POST['price'];
    $image = $_POST['image'];
    $category = $_POST['category'];
    $stock = $_POST['stock'];

    $stmt = $pdo->prepare("UPDATE products SET name = :name, price = :price, image = :image, category = :category, stock = :stock WHERE id = :id");
    $stmt->execute([
        ':id' => $id,
        ':name' => $name,
        ':price' => $price,
        ':image' => $image,
        ':category' => $category,
        ':stock' => $stock
    ]);
    header('Location: products.php');
    exit();
}

// លុបផលិតផល
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
    $stmt->execute([':id' => $id]);
    header('Location: products.php');
    exit();
}

// ទាញទិន្នន័យផលិតផល
$stmt = $pdo->query("SELECT * FROM products");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - គ្រប់គ្រងផលិតផល</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Noto Sans Khmer', Arial, sans-serif;
            background-color: #f0f2f5;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }
        th, td {
            padding: 0.75rem;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f4f4f4;
        }
    </style>
</head>
<body class="bg-gray-100 p-6">
    <div class="container mx-auto max-w-5xl">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-teal-600">Admin Panel - គ្រប់គ្រងផលិតផល</h1>
            <a href="dashboard.php" class="bg-teal-500 text-white font-semibold py-2 px-4 rounded-lg hover:bg-teal-600 transition duration-300">ត្រឡប់ទៅ Dashboard</a>
        </div>

        <!-- ទម្រង់បន្ថែមផលិតផល -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">បន្ថែមផលិតផលថ្មី</h2>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="name" class="block text-gray-700">ឈ្មោះផលិតផល</label>
                        <input type="text" id="name" name="name" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500" required>
                    </div>
                    <div>
                        <label for="price" class="block text-gray-700">តម្លៃ</label>
                        <input type="number" step="0.01" id="price" name="price" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500" required>
                    </div>
                    <div>
                        <label for="image" class="block text-gray-700">URL រូបភាព</label>
                        <input type="text" id="image" name="image" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500" required>
                    </div>
                    <div>
                        <label for="category" class="block text-gray-700">ប្រភេទ</label>
                        <input type="text" id="category" name="category" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500" required>
                    </div>
                    <div>
                        <label for="stock" class="block text-gray-700">ស្តុក</label>
                        <input type="number" id="stock" name="stock" class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500" required>
                    </div>
                </div>
                <button type="submit" class="w-full bg-teal-500 text-white font-semibold py-2 rounded-lg hover:bg-teal-600 transition duration-300">បន្ថែមផលិតផល</button>
            </form>
        </div>

        <!-- បញ្ជីផលិតផល -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">បញ្ជីផលិតផល</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ឈ្មោះ</th>
                        <th>តម្លៃ</th>
                        <th>រូបភាព</th>
                        <th>ប្រភេទ</th>
                        <th>ស្តុក</th>
                        <th>សកម្មភាព</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo $product['id']; ?></td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td>$<?php echo $product['price']; ?></td>
                            <td><img src="<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-16 h-16 object-cover rounded-md"></td>
                            <td><?php echo htmlspecialchars($product['category']); ?></td>
                            <td><?php echo $product['stock']; ?></td>
                            <td>
                                <!-- ទម្រង់កែសម្រួល -->
                                <form method="POST" class="inline-block">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" class="px-2 py-1 border rounded-lg" required>
                                    <input type="number" step="0.01" name="price" value="<?php echo $product['price']; ?>" class="px-2 py-1 border rounded-lg" required>
                                    <input type="text" name="image" value="<?php echo $product['image']; ?>" class="px-2 py-1 border rounded-lg" required>
                                    <input type="text" name="category" value="<?php echo htmlspecialchars($product['category']); ?>" class="px-2 py-1 border rounded-lg" required>
                                    <input type="number" name="stock" value="<?php echo $product['stock']; ?>" class="px-2 py-1 border rounded-lg" required>
                                    <button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded-lg hover:bg-blue-600">កែ</button>
                                </form>
                                <a href="products.php?delete=<?php echo $product['id']; ?>" class="bg-red-500 text-white px-3 py-1 rounded-lg hover:bg-red-600 ml-2" onclick="return confirm('តើអ្នកប្រាកដជាចង់លុបផលិតផលនេះមែនទេ?')">លុប</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>