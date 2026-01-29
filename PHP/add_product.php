<?php
include("db.php");

// Fetch categories to populate the category dropdown in the form
$sql = "SELECT * FROM categories";
$category_result = $conn->query($sql);

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $recipe = $_POST['recipe'];
    $scale = $_POST['scale'];
    $category_id = $_POST['category_id'];
    $image_url = $_POST['image_url']; // Get the URL input from the form

    // Insert the product into the database
    $insert_sql = "INSERT INTO products (name, description, price, category_id, image_url, recipe, scale) 
                   VALUES ('$name', '$description', '$price', '$category_id', '$image_url', '$recipe', '$scale')";
    if ($conn->query($insert_sql) === TRUE) {
        echo "New product added successfully!";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Add New Product</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Add New Product</h2>
        <form action="add_product.php" method="POST">
            <div class="mb-3">
                <label for="name" class="form-label">Product Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea name="description" class="form-control" required></textarea>
            </div>
            <div class="mb-3">
                <label for="price" class="form-label">Price</label>
                <input type="text" name="price" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="recipe" class="form-label">Recipe</label>
                <textarea type="text" name="recipe" class="form-control" required></textarea>
            </div>
            <div class="mb-3">
                <label for="scale" class="form-label">Scale</label>
                <textarea type="text" name="scale" class="form-control" required></textarea>
            </div>
            <div class="mb-3">
                <label for="category" class="form-label">Category</label>
                <select name="category_id" class="form-control" required>
                    <?php while ($row = $category_result->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>"><?= $row['name'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="image_url" class="form-label">Image URL</label>
                <input type="text" name="image_url" class="form-control" placeholder="Enter image URL" required>
            </div>
            <button type="submit" class="btn btn-primary">Add Product</button>
        </form>
    </div>
</body>
</html>
