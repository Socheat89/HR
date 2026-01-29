<?php
$conn = new mysqli('localhost', 'samann1_admin_panel', 'admin_panel@2025', 'samann1_admin_panel');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$id = intval($_GET['id'] ?? 0);
$lang = $_GET['lang'] ?? 'km';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $title_en = trim($_POST['title_en'] ?? '');
    $content = $_POST['content'] ?? '';
    $content_en = $_POST['content_en'] ?? '';

    if ($title === '') {
        $error = $lang === 'km' ? "ចំណងជើងមិនអាចទទេបានទេ។" : "Title cannot be empty.";
    } else {
        $stmt = $conn->prepare("UPDATE posts SET title=?, title_en=?, content=?, content_en=? WHERE id=?");
        $stmt->bind_param('ssssi', $title, $title_en, $content, $content_en, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: view_posts.php?lang=" . urlencode($lang) . "&message=updated");
        exit;
    }
}

$stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$post = $result->fetch_assoc();
$stmt->close();

if (!$post) {
    die($lang === 'km' ? "រកមិនឃើញអត្ថបទ។" : "Post not found.");
}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8" />
    <title><?= $lang === 'km' ? 'កែសម្រួលអត្ថបទ' : 'Edit Post' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
</head>
<body class="p-3">
    <div class="container" style="max-width:600px;">
        <h1><?= $lang === 'km' ? 'កែសម្រួលអត្ថបទ' : 'Edit Post' ?></h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="POST" action="?id=<?= $id ?>&lang=<?= htmlspecialchars($lang) ?>">
            <div class="mb-3">
                <label class="form-label"><?= $lang === 'km' ? 'ចំណងជើង' : 'Title' ?></label>
                <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label"><?= $lang === 'km' ? 'ចំណងជើងបកប្រែ (អង់គ្លេស)' : 'Title Translation (English)' ?></label>
                <input type="text" name="title_en" class="form-control" value="<?= htmlspecialchars($post['title_en'], ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label"><?= $lang === 'km' ? 'ខ្លឹមសារ' : 'Content' ?></label>
                <textarea name="content" class="form-control" rows="6"><?= htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= $lang === 'km' ? 'បកប្រែខ្លឹមសារ (អង់គ្លេស)' : 'Content Translation (English)' ?></label>
                <textarea name="content_en" class="form-control" rows="6"><?= htmlspecialchars($post['content_en'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary"><?= $lang === 'km' ? 'រក្សាទុក' : 'Save' ?></button>
            <a href="view_posts.php?lang=<?= htmlspecialchars($lang) ?>" class="btn btn-secondary ms-2"><?= $lang === 'km' ? 'ត្រឡប់' : 'Back' ?></a>
        </form>
    </div>
</body>
</html>
