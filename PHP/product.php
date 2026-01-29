<?php
include("db.php");

// Fetch categories for the category filter dropdown
$category_sql = "SELECT * FROM categories";
$category_result = $conn->query($category_sql);

// Check if a category is selected
$category_filter = isset($_GET['category_id']) ? $_GET['category_id'] : '';

// Fetch products based on the selected category
$sql = "SELECT * FROM products";
if ($category_filter) {
    $sql .= " WHERE category_id = $category_filter";
}

$product_result = $conn->query($sql);
// Fetch categories to populate the category filter
$sql = "SELECT * FROM categories";
$category_result = $conn->query($sql);

// Get the selected category filter (if any)
$category_filter = isset($_GET['category_id']) ? $_GET['category_id'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #faf3e3;
        }
        .container {
            margin-top: 50px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .card img {
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .card-body {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center">Our Products</h2>

        <!-- Category Filter Menu -->
<div class="mb-4">
    <form action="index.php" method="GET">
        <div class="btn-group" role="group" aria-label="Category Filter">
            <a href="index.php" class="btn btn-link <?= empty($category_filter) ? 'active' : '' ?>">All Categories</a>
            <?php while ($category = $category_result->fetch_assoc()): ?>
                <a href="index.php?category_id=<?= $category['id'] ?>" class="btn btn-link <?= $category['id'] == $category_filter ? 'active' : '' ?>">
                    <?= $category['name'] ?>
                </a>
            <?php endwhile; ?>
        </div>
    </form>
</div>          
        <!-- Product Listing -->
        <div class="row">
            <?php while ($row = $product_result->fetch_assoc()): ?>
            <div class="col-md-4">
                <div class="card mb-4">
                    <img src="<?= $row["image_url"] ?>" class="card-img-top" alt="<?= $row["name"] ?>">
                    <div class="card-body">
                        <h5 class="card-title"><?= $row["name"] ?></h5>
                        <p class="card-text"><?= $row["description"] ?></p>
                        <p><strong>$<?= $row["price"] ?></strong></p>
                        <a href="product_details.php?id=<?= $row['id'] ?>" class="btn btn-primary">View Details</a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</body>
</html>
