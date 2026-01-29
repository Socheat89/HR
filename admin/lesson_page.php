<?php
include 'includes/auth.php';

include 'includes/db.php';
$conn = include 'includes/db.php';

// Initialize variables
$lesson = [];
$error_message = '';

// Get lesson ID from query string
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $lesson_id = intval($_GET['id']); // Sanitize input

    try {
        // Fetch lesson details
        $stmt = $conn->prepare("
            SELECT id, title, lesson_date, description, file_url 
            FROM lessons 
            WHERE id = :id
        ");
        $stmt->bindParam(':id', $lesson_id, PDO::PARAM_INT);
        $stmt->execute();
        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lesson) {
            $error_message = "Lesson not found.";
        }

        // Fetch associated photos and videos
        $stmt = $conn->prepare("
            SELECT photo_url, video_url 
            FROM lesson_photos 
            WHERE lesson_id = :id
        ");
        $stmt->bindParam(':id', $lesson_id, PDO::PARAM_INT);
        $stmt->execute();
        $media = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error_message = "An error occurred while fetching the lesson.";
    }
} else {
    $error_message = "Invalid request.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($lesson['title'] ?? 'View Lesson'); ?> - Lesson By VVC</title>
    <!-- Include Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="bg-gray-100 font-sans min-h-screen">

    <!-- Main Content (Removed Sidebar) -->
    <main class="container mx-auto p-6 py-12">
        <!-- Header -->
        <header class="mb-8">
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-3xl font-bold text-gray-900">
                Lesson By VVC
                </h1>
            </div>
            <a href="view_lesson_page.php" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium transition duration-200 flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Lessons
            </a>
        </header>

        <!-- Error Message -->
        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg shadow-md" role="alert">
                <p class="text-sm"><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>

        <!-- Lesson Details Card -->
        <?php if (!empty($lesson)): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 max-w-4xl mx-auto">
                <!-- Lesson Title and Date -->
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($lesson['title']); ?></h2>
                    <p class="text-sm text-gray-600">Date: <?php echo htmlspecialchars($lesson['lesson_date']); ?></p>
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <p class="text-gray-800 leading-relaxed"><?php echo nl2br(htmlspecialchars($lesson['description'])); ?></p>
                </div>

                <!-- File Download (if available) -->
                <?php if (!empty($lesson['file_url'])): ?>
                    <div class="mb-6">
                        <a href="<?php echo htmlspecialchars($lesson['file_url']); ?>" target="_blank" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition duration-200">
                            <i class="fas fa-download mr-2"></i>
                            Download File
                        </a>
                    </div>
                <?php else: ?>
                    <p class="text-gray-500 text-sm mb-6">No file attached.</p>
                <?php endif; ?>

                <!-- Video Player -->
                <div class="mb-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Video</h3>
                    <?php if (!empty($media)): ?>
                        <?php foreach ($media as $item): ?>
                            <?php if (!empty($item['video_url'])): ?>
                                <?php
                                $isGoogleDrive = strpos($item['video_url'], 'drive.google.com') !== false;
                                if ($isGoogleDrive) {
                                    $embedUrl = str_replace('/view?usp=sharing', '/preview', $item['video_url']);
                                    echo '<iframe src="' . htmlspecialchars($embedUrl) . '" class="w-full h-[400px] rounded-lg" frameborder="0" allow="autoplay"></iframe>';
                                } else {
                                    echo '<video controls class="w-full h-[400px] rounded-lg object-cover">';
                                    echo '<source src="' . htmlspecialchars($item['video_url']) . '" type="video/mp4">';
                                    echo 'Your browser does not support the video tag.';
                                    echo '</video>';
                                }
                                ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm">No video available for this lesson.</p>
                    <?php endif; ?>
                </div>

                <!-- Photos Gallery -->
                <div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Photos</h3>
                    <?php if (!empty($media)): ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($media as $item): ?>
                                <?php if (!empty($item['photo_url'])): ?>
                                    <div class="bg-gray-50 p-2 rounded-lg shadow-md hover:shadow-lg transition-shadow duration-200">
                                        <img src="<?php echo htmlspecialchars($item['photo_url']); ?>" alt="Lesson Photo" class="w-full h-48 object-cover rounded-md">
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500 text-sm">No photos available for this lesson.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>