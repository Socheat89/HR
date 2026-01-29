<?php
// DB connection
$host = 'localhost';
$dbname = 'samann1_admin_panel';
$username = 'samann1_admin_panel';
$password = 'admin_panel@2025';

$conn = new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set UTF-8 encoding
$conn->set_charset("utf8mb4");

header('Content-Type: text/html; charset=utf-8');

// Language handling
$languages = ['km' => 'Khmer', 'en' => 'English'];
$default_lang = 'km';
$lang = isset($_GET['lang']) && array_key_exists($_GET['lang'], $languages) ? $_GET['lang'] : $default_lang;

// Translation arrays for static text
$translations = [
    // ... same as your original $translations ...
];

// Handle delete
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    header("Location: view_posts.php?lang=$lang&category=" . (isset($_GET['category']) ? intval($_GET['category']) : 0) . "&page=" . (isset($_GET['page']) ? intval($_GET['page']) : 1));
    exit();
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Category filter
$filter_category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$category_sql = "SELECT * FROM categories";
$categories = $conn->query($category_sql);

// Main query with optional category filter
$where = $filter_category ? "WHERE posts.category_id = ?" : "";
$total_sql = "SELECT COUNT(*) FROM posts " . $where;
$total_stmt = $conn->prepare($total_sql);
if ($filter_category) {
    $total_stmt->bind_param("i", $filter_category);
}
$total_stmt->execute();
$total_posts = $total_stmt->get_result()->fetch_row()[0];
$total_pages = ceil($total_posts / $limit);

$sql = "SELECT posts.*, categories.name AS category_name
        FROM posts
        LEFT JOIN categories ON posts.category_id = categories.id
        " . $where . "
        ORDER BY posts.created_at DESC
        LIMIT $limit OFFSET $offset";

if ($filter_category) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $filter_category);
} else {
    $stmt = $conn->prepare($sql);
}
$stmt->execute();
$result = $stmt->get_result();
?>


<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8" />
    <title>Show Posts</title>
</head>
<body>
    <h1>Posts List</h1>
    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>លេខ</th>
                <th>ចំណងជើង</th>
                <th>ប្រភេទ</th>
                <th>ខ្លឹមសារ</th>
                <th>កាលបរិច្ឆេទបង្កើត</th>
                <th>សកម្មភាព</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0):
                $i = 1;
                while ($row = $result->fetch_assoc()):
                    // Select title & content based on language
                    $title = ($lang === 'en' && !empty($row['title_en'])) ? $row['title_en'] : $row['title'];
                    $content = ($lang === 'en' && !empty($row['content_en'])) ? $row['content_en'] : $row['content'];
            ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($title) ?></td>
                <td><?= htmlspecialchars($row['category_name']) ?></td>
                <td>
                    <?= nl2br(htmlspecialchars($content)) ?>
                    <?php if ($lang === 'kh' && !empty($row['content_en'])): ?>
                    <hr>
                    <small><strong>បកប្រែ (English):</strong><br><?= nl2br(htmlspecialchars($row['content_en'])) ?></small>
                    <?php endif; ?>
                </td>
                <td><?= $row['created_at'] ?></td>
                <td>
                    <a href="edit_post.php?id=<?= $row['id'] ?>&lang=<?= $lang ?>">កែប្រែ / Edit</a>
                </td>
            </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="6" style="text-align:center;">គ្មានទិន្នន័យ</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
