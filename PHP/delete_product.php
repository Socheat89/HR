<?php
include("db.php");

if (isset($_GET['id'])) {
    $product_id = $_GET['id'];

    // Delete product from the database
    $delete_sql = "DELETE FROM products WHERE id = $product_id";
    if ($conn->query($delete_sql) === TRUE) {
        echo "Product deleted successfully";
    } else {
        echo "Error: " . $conn->error;
    }
} else {
    echo "Invalid Product ID!";
}
?>
