<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $image_url = $_POST['image_url'];
    $link = $_POST['link'];
    $category = $_POST['category'];

    $stmt = $conn->prepare("INSERT INTO products (name, image_url, link, category) VALUES (:name, :image_url, :link, :category)");
    $stmt->execute([
        'name' => $name,
        'image_url' => $image_url,
        'link' => $link,
        'category' => $category
    ]);

    header("Location: admin.php");
    exit();
}

$category = $_GET['category'] ?? '';
?>

<!-- HTML form for adding product -->
<form method="POST">
    <input type="text" name="name" placeholder="Product Name" required>
    <input type="text" name="image_url" placeholder="Image URL" required>
    <input type="text" name="link" placeholder="Product Link" required>
    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
    <button type="submit">Add Product</button>
</form>