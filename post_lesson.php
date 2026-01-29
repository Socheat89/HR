<?php
// Database connection
$host = 'localhost';
$dbname = 'samann1_admin_panel';
$username = 'samann1_admin_panel';
$password = 'admin_panel@2025';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
header('Content-Type: text/html; charset=utf-8');

if (isset($_POST['submit_post'])) {
    $title = $conn->real_escape_string($_POST['title']);
    $category = (int)$_POST['category'];
    $content = str_replace(["\r\n", "\r", "\n"], '', $_POST['content']);
    $content = $conn->real_escape_string($content);

    // Get raw textarea input for image URLs
    $imageURLsRaw = isset($_POST['image_urls']) ? $_POST['image_urls'] : '';
    // Split by new lines to array
    $imageURLsArray = preg_split('/\r\n|\r|\n/', $imageURLsRaw);

    $validURLs = [];
    foreach ($imageURLsArray as $url) {
        $url = trim($url);
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $validURLs[] = $conn->real_escape_string($url);
        }
    }
    // Encode array to JSON string
    $imagesJson = json_encode($validURLs, JSON_UNESCAPED_SLASHES);

    $stmt = $conn->prepare("INSERT INTO posts (title, category_id, content, images) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("siss", $title, $category, $content, $imagesJson);

    if ($stmt->execute()) {
        echo "<script>alert('Post submitted successfully'); window.location.href='post_lesson.php';</script>";
    } else {
        echo "<script>alert('Failed to submit post: " . $conn->error . "');</script>";
    }
    $stmt->close();
}

if (isset($_POST['name'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    if ($stmt->execute()) {
        $id = $conn->insert_id;
        echo json_encode(['success' => true, 'id' => $id, 'name' => $name]);
    } else {
        echo json_encode(['success' => false]);
    }
    $stmt->close();
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Post Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        textarea.form-control {
            white-space: pre-wrap;
        }
    </style>
</head>
<body class="p-4">
<div class="container">
    <h2>Post Document</h2>
    <form method="POST" action="">
        <div class="mb-3">
            <label>Title</label>
            <input type="text" name="title" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Category</label>
            <div class="d-flex gap-2">
                <select name="category" class="form-select" required>
                    <option value="">Select Category</option>
                    <?php
                    $res = $conn->query("SELECT * FROM categories");
                    while ($row = $res->fetch_assoc()) {
                        echo "<option value='{$row['id']}'>" . htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') . "</option>";
                    }
                    ?>
                </select>
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">+ Add</button>
            </div>
        </div>

        <div class="mb-3">
            <label>Content</label>
            <textarea name="content" class="form-control" rows="5" required></textarea>
        </div>

        <div class="mb-3">
            <label>Image URLs (Optional, one URL per line)</label>
            <textarea name="image_urls" class="form-control" rows="4" placeholder="https://example.com/image1.jpg
https://example.com/image2.jpg"></textarea>
            <small class="text-muted">Paste multiple image URLs each in a new line</small>
        </div>

        <button type="submit" name="submit_post" class="btn btn-success">Submit</button>
    </form>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="addCategoryForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label>Category Name</label>
                    <input type="text" name="new_category" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$('#addCategoryForm').submit(function(e) {
    e.preventDefault();
    var name = $('input[name="new_category"]').val();
    $.post('post_lesson.php', {name: name}, function(data) {
        if (data.success) {
            $('select[name="category"]').append('<option value="'+data.id+'" selected>' + data.name + '</option>');
            $('#addCategoryModal').modal('hide');
            $('#addCategoryForm')[0].reset();
        } else {
            alert('Failed to add category.');
        }
    }, 'json');
});
</script>
</body>
</html>
<?php $conn->close(); ?>
