<?php
include 'includes/auth.php';
if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}
include 'includes/db.php';
$conn = include 'includes/db.php';

// Initialize variables
$title = $date = $description = $file_url = '';
$photo_urls = [];
$video_urls = [];
$error_message = $success_message = '';

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
            $stmt = $conn->prepare("
                INSERT INTO lessons (title, lesson_date, description, file_url) 
                VALUES (:title, :lesson_date, :description, :file_url)
            ");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':lesson_date', $date);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':file_url', $file_url);
            $stmt->execute();

            $lesson_id = $conn->lastInsertId();

            if (!empty($photo_urls)) {
                $stmt = $conn->prepare("
                    INSERT INTO lesson_photos (lesson_id, photo_url, video_url) 
                    VALUES (:lesson_id, :photo_url, :video_url)
                ");
                foreach ($photo_urls as $index => $photo_url) {
                    if (!empty($photo_url) && !filter_var($photo_url, FILTER_VALIDATE_URL)) {
                        throw new Exception("Invalid photo URL: $photo_url");
                    }
                    $stmt->bindParam(':lesson_id', $lesson_id, PDO::PARAM_INT);
                    $stmt->bindParam(':photo_url', $photo_url);
                    $video_url = $video_urls[$index] ?? null;
                    $stmt->bindParam(':video_url', $video_url);
                    $stmt->execute();
                }
            }

            $conn->commit();
            $success_message = "Lesson posted successfully!";
            header("Refresh: 2; url=lessons.php");
            exit();
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Error: " . $e->getMessage());
            $error_message = "An error occurred while posting the lesson: " . $e->getMessage();
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Lesson - HR Studio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #e0e0e0;
        }
        .sidebar {
            background: linear-gradient(to bottom, #2a2a2a, #3a3a3a);
            box-shadow: 2px 0 12px rgba(0, 0, 0, 0.3);
        }
        .input-field {
            background: #333;
            border: 1px solid #444;
            color: #e0e0e0;
            transition: all 0.3s ease;
        }
        .input-field:focus {
            border-color: #ff9500;
            box-shadow: 0 0 0 3px rgba(255, 149, 0, 0.3);
        }
        .btn-premiere {
            background: linear-gradient(to right, #ff6d00, #ff9500);
            transition: all 0.3s ease;
        }
        .btn-premiere:hover {
            background: linear-gradient(to right, #ff9500, #ffaa00);
            transform: scale(1.05);
        }
        .form-container {
            background: #252525;
            border: 1px solid #333;
            transition: all 0.3s ease;
        }
        .form-container:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.4);
        }
        .label-premiere {
            color: #ff9500;
            font-weight: 500;
        }
    </style>
</head>
<body class="min-h-screen font-sans antialiased flex">
    <!-- Sidebar -->
    <aside class="sidebar w-64 text-white p-6 hidden md:block fixed h-full">
        <h2 class="text-2xl font-bold mb-8 tracking-tight">HR Studio</h2>
        <ul class="space-y-6">
            <li>
                <a href="dashboard.php" class="flex items-center space-x-3 hover:text-orange-300 transition duration-200">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <li>
                    <a href="add_user.php" class="flex items-center space-x-3 hover:text-orange-300 transition duration-200">
                        <i class="fas fa-user-plus"></i>
                        <span>Add User</span>
                    </a>
                </li>
                <li>
                    <a href="post_meeting.php" class="flex items-center space-x-3 hover:text-orange-300 transition duration-200">
                        <i class="fas fa-calendar-plus"></i>
                        <span>Post Meeting</span>
                    </a>
                </li>
                <li>
                    <a href="post_lesson.php" class="flex items-center space-x-3 hover:text-orange-300 transition duration-200">
                        <i class="fas fa-book"></i>
                        <span>Post Lesson</span>
                    </a>
                </li>
                <li>
                    <a href="lessons.php" class="flex items-center space-x-3 hover:text-orange-300 transition duration-200">
                        <i class="fas fa-list"></i>
                        <span>View Lessons</span>
                    </a>
                </li>
            <?php endif; ?>
            <li>
                <a href="logout.php" class="flex items-center space-x-3 hover:text-orange-300 transition duration-200">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-8 overflow-y-auto md:ml-64">
        <!-- Header -->
        <header class="mb-8">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-extrabold text-white tracking-wide">Post a New Lesson</h1>
                <button class="md:hidden text-orange-400 hover:text-orange-300 focus:outline-none transition duration-200">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </header>

        <!-- Messages -->
        <?php if ($error_message): ?>
            <div class="bg-red-900 border-l-4 border-red-500 text-red-200 p-4 mb-8 rounded-md shadow-sm">
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="bg-green-900 border-l-4 border-green-500 text-green-200 p-4 mb-8 rounded-md shadow-sm">
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
        <?php endif; ?>

        <!-- Form Section -->
        <div class="form-container rounded-lg p-8">
            <form method="POST" action="" class="space-y-6">
                <div>
                    <label for="title" class="label-premiere block text-sm">Lesson Title</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($title); ?>" required 
                           class="input-field mt-1 block w-full px-4 py-3 rounded-md focus:outline-none sm:text-sm">
                </div>
                <div>
                    <label for="date" class="label-premiere block text-sm">Lesson Date</label>
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date); ?>" required 
                           class="input-field mt-1 block w-full px-4 py-3 rounded-md focus:outline-none sm:text-sm">
                </div>
                <div>
                    <label for="description" class="label-premiere block text-sm">Description</label>
                    <textarea id="description" name="description" rows="4" required 
                              class="input-field mt-1 block w-full px-4 py-3 rounded-md focus:outline-none sm:text-sm"><?php echo htmlspecialchars($description); ?></textarea>
                </div>
                <div>
                    <label for="file_url" class="label-premiere block text-sm">File URL (e.g., PDF or Video)</label>
                    <input type="url" id="file_url" name="file_url" value="<?php echo htmlspecialchars($file_url); ?>" 
                           class="input-field mt-1 block w-full px-4 py-3 rounded-md focus:outline-none sm:text-sm">
                </div>
                <div>
                    <label for="photos" class="label-premiere block text-sm">Photo URLs (One per line)</label>
                    <textarea id="photos" name="photos" rows="4" 
                              class="input-field mt-1 block w-full px-4 py-3 rounded-md focus:outline-none sm:text-sm"><?php echo htmlspecialchars(implode("\n", $photo_urls)); ?></textarea>
                    <small class="text-gray-400 mt-1 block">Enter each photo URL on a new line.</small>
                </div>
                <div>
                    <label for="videos" class="label-premiere block text-sm">Video URLs (One per line)</label>
                    <textarea id="videos" name="videos" rows="4" 
                              class="input-field mt-1 block w-full px-4 py-3 rounded-md focus:outline-none sm:text-sm"><?php echo htmlspecialchars(implode("\n", $video_urls)); ?></textarea>
                    <small class="text-gray-400 mt-1 block">Enter each video URL on a new line, matching photo order.</small>
                </div>
                <button type="submit" class="btn-premiere inline-flex items-center justify-center py-3 px-6 rounded-md text-white font-medium w-full">
                    <i class="fas fa-upload mr-2"></i> Post Lesson
                </button>
            </form>
        </div>
    </main>
</body>
</html>