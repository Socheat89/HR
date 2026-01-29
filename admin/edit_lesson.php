<?php
include 'includes/auth.php';
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}
include 'includes/db.php';
$conn = include 'includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: lessons.php");
    exit();
}

$error_message = $success_message = '';
$lesson_id = $_GET['id'];

// Fetch lesson
$stmt = $conn->prepare("SELECT * FROM lessons WHERE id = :id");
$stmt->bindParam(':id', $lesson_id, PDO::PARAM_INT);
$stmt->execute();
$lesson = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lesson) {
    header("Location: lessons.php");
    exit();
}

// Fetch media
$stmt = $conn->prepare("SELECT * FROM lesson_photos WHERE lesson_id = :id");
$stmt->bindParam(':id', $lesson_id, PDO::PARAM_INT);
$stmt->execute();
$media = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $date = trim($_POST['date']);
    $description = trim($_POST['description']);
    $file_url = trim($_POST['file_url']);
    $photo_urls = isset($_POST['photos']) ? array_map('trim', explode("\n", $_POST['photos'])) : [];
    $video_urls = isset($_POST['videos']) ? array_map('trim', explode("\n", $_POST['videos'])) : [];

    if (!empty($title) && !empty($date) && !empty($description)) {
        try {
            $conn->beginTransaction();

            // Update lesson
            $stmt = $conn->prepare("UPDATE lessons SET title = :title, lesson_date = :date, description = :description, file_url = :file_url WHERE id = :id");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':file_url', $file_url);
            $stmt->bindParam(':id', $lesson_id, PDO::PARAM_INT);
            $stmt->execute();

            // Delete existing media
            $stmt = $conn->prepare("DELETE FROM lesson_photos WHERE lesson_id = :id");
            $stmt->bindParam(':id', $lesson_id, PDO::PARAM_INT);
            $stmt->execute();

            // Insert new media
            if (!empty($photo_urls)) {
                $stmt = $conn->prepare("INSERT INTO lesson_photos (lesson_id, photo_url, video_url) VALUES (:id, :photo_url, :video_url)");
                foreach ($photo_urls as $index => $photo_url) {
                    if (!empty($photo_url) && !filter_var($photo_url, FILTER_VALIDATE_URL)) {
                        throw new Exception("Invalid photo URL: $photo_url");
                    }
                    $stmt->bindParam(':id', $lesson_id, PDO::PARAM_INT);
                    $stmt->bindParam(':photo_url', $photo_url);
                    $video_url = $video_urls[$index] ?? null;
                    $stmt->bindParam(':video_url', $video_url);
                    $stmt->execute();
                }
            }

            $conn->commit();
            $success_message = "Lesson updated successfully!";
            header("Refresh: 2; url=lessons.php");
        } catch (Exception $e) {
            $conn->rollBack();
            $error_message = "Error updating lesson: " . $e->getMessage();
        }
    } else {
        $error_message = "All required fields must be filled.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Lesson</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-50">
    <main class="max-w-4xl mx-auto p-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Edit Lesson</h1>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-md"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-6 bg-white p-6 rounded-lg shadow-md">
            <div>
                <label class="block text-sm font-medium text-gray-700">Title</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($lesson['title']); ?>" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Date</label>
                <input type="date" name="date" value="<?php echo htmlspecialchars($lesson['lesson_date']); ?>" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Description</label>
                <textarea name="description" rows="4" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md"><?php echo htmlspecialchars($lesson['description']); ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">File URL</label>
                <input type="url" name="file_url" value="<?php echo htmlspecialchars($lesson['file_url']); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Photo URLs (One per line)</label>
                <textarea name="photos" rows="4" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md"><?php echo htmlspecialchars(implode("\n", array_column($media, 'photo_url'))); ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Video URLs (One per line)</label>
                <textarea name="videos" rows="4" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md"><?php echo htmlspecialchars(implode("\n", array_column($media, 'video_url'))); ?></textarea>
            </div>
            <div class="flex space-x-4">
                <button type="submit" class="flex-1 py-2 px-4 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Update Lesson</button>
                <a href="lessons.php" class="flex-1 py-2 px-4 bg-gray-600 text-white rounded-md hover:bg-gray-700 text-center">Cancel</a>
            </div>
        </form>
    </main>
</body>
</html>