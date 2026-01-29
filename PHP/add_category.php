<?php
include("db.php");

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category_name = $_POST['name'];

    // Validate input (basic validation)
    if (empty($category_name)) {
        echo "Category name cannot be empty.";
    } else {
        // Insert the category into the database
        $insert_sql = "INSERT INTO categories (name) VALUES ('$category_name')";
        if ($conn->query($insert_sql) === TRUE) {
            echo "New category added successfully!";
        } else {
            echo "Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Category</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Add New Category</h2>
        <form action="add_category.php" method="POST">
            <div class="mb-3">
                <label for="name" class="form-label">Category Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Add Category</button>
        </form>
        <br>
        <a href="admin.php" class="btn btn-secondary">Back to Admin Panel</a>
    </div>
</body>
</html>
