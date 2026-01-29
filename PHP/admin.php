<?php
include("db.php");

// Join products with categories to display category names
$sql = "SELECT products.*, categories.name AS category_name 
        FROM products 
        LEFT JOIN categories ON products.category_id = categories.id";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #faf3e3;
        }
        .container {
            margin-top: 50px;
        }
        .btn {
            width: 50%;
        }

form .btn {
    width: 100%;
}

    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Admin Panel - Product Management</h2>
        <a href="add_product.php" class="btn btn-success mb-3">Add New Product</a>
        <a href="add_category.php" class="btn btn-primary">Add New Category</a>
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
                    <td><?= $row["category_name"] ?></td> <!-- Display category -->
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
