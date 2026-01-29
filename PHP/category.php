<?php
include("db.php");

// Get the category ID from the URL
$category_id = isset($_GET['category_id']) ? $_GET['category_id'] : '';

if (!$category_id) {
    echo "Category not found!";
    exit;
}

// Get category name from the database for the page title
$category_sql = "SELECT * FROM categories WHERE id = $category_id";
$category_result = $conn->query($category_sql);
$category = $category_result->fetch_assoc();

if (!$category) {
    echo "Category not found!";
    exit;
}

// Fetch products for the selected category
$sql = "SELECT products.*, categories.name AS category_name 
        FROM products 
        LEFT JOIN categories ON products.category_id = categories.id
        WHERE products.category_id = $category_id";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= $category['name'] ?> - Product List</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2><?= $category['name'] ?> - Product List</h2>
        <a href="add_product.php" class="btn btn-success mb-3">Add New Product</a>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Image</th>
                    <th>Category</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row["id"] ?></td>
                    <td><?= $row["name"] ?></td>
                    <td><?= $row["description"] ?></td>
                    <td>$<?= $row["price"] ?></td>
                    <td><img src="<?= $row["image_url"] ?>" width="50"></td>
                    <td><?= $row["category_name"] ?></td>
                    <td>
                        <a href="delete_product.php?id=<?= $row["id"] ?>" class="btn btn-danger btn-sm">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
