<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT keyword, product_name, price, product_image FROM auto_replies WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Error: Rule not found.");
}
$rule = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>Edit Product</title>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kantumruy Pro', sans-serif; background-color: #f4f7f9; padding: 20px; }
        .container { max-width: 900px; margin: auto; }
        .card { background: #fff; border-radius: 8px; padding: 25px; }
        form label { display: block; margin-bottom: 8px; font-weight: 600; }
        form input[type="text"], form input[type="number"], form input[type="file"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; box-sizing: border-box; margin-bottom: 15px; }
        .current-image-preview { max-width: 150px; margin-top: 10px; border-radius: 5px; display: block; }
        .btn { display: inline-block; text-decoration: none; padding: 12px 20px; border-radius: 5px; border: none; cursor: pointer; color: white; margin-right: 10px; }
        .btn-primary { background-color: #007BFF; }
        .btn-secondary { background-color: #6c757d; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>កែប្រែផលិតផល (ID: <?php echo $id; ?>)</h1>
            <form action="dashboard.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($rule['product_image']); ?>">

                <label for="keyword">Keyword:</label>
                <input type="text" id="keyword" name="keyword" required value="<?php echo htmlspecialchars($rule['keyword']); ?>">
                
                <label for="product_name">Product Name:</label>
                <input type="text" id="product_name" name="product_name" required value="<?php echo htmlspecialchars($rule['product_name']); ?>">
                
                <label for="price">Price (USD):</label>
                <input type="number" id="price" name="price" required step="0.01" min="0" value="<?php echo htmlspecialchars($rule['price']); ?>">
                
                <label for="product_image">Change Product Image (optional):</label>
                <input type="file" id="product_image" name="product_image" accept="image/*">
                
                <?php if (!empty($rule['product_image'])): ?>
                    <label>Current Image:</label>
                    <img src="uploads/<?php echo htmlspecialchars($rule['product_image']); ?>" alt="Current Image" class="current-image-preview">
                <?php endif; ?>
                
                <br><br>
                <button type="submit" class="btn btn-primary">រក្សាទុកការកែប្រែ</button>
                <a href="dashboard.php" class="btn btn-secondary">ត្រឡប់ក្រោយ</a>
            </form>
        </div>
    </div>
</body>
</html>
<?php
$conn->close();
?>