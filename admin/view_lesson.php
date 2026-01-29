<?php
include 'includes/auth.php';

include 'includes/db.php';
$conn = include 'includes/db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: lessons.php");
    exit();
}

$stmt = $conn->prepare("SELECT * FROM lessons WHERE id = :id");
$stmt->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
$stmt->execute();
$lesson = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lesson) {
    header("Location: lessons.php");
    exit();
}

// Fetch photos and videos
$stmt = $conn->prepare("SELECT * FROM lesson_photos WHERE lesson_id = :id");
$stmt->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
$stmt->execute();
$media = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Error message variable
$error_message = '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Lesson - <?php echo htmlspecialchars($lesson['title']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .video-player {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            background: #000;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .video-player video,
        .video-player iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .media-thumbnail {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .media-thumbnail:hover {
            transform: scale(1.05);
            opacity: 0.9;
        }
        .collapsible-content {
            transition: max-height 0.3s ease-out;
            overflow: hidden;
        }
        .shadow-hover:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .error-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            padding: 1rem;
        }
    </style>
    <script>
        function toggleDetails() {
            const content = document.getElementById('details-content');
            const button = document.getElementById('toggle-details');
            if (content.style.maxHeight) {
                content.style.maxHeight = null;
                button.innerHTML = '<i class="fas fa-chevron-down mr-2"></i> Show Details';
            } else {
                content.style.maxHeight = content.scrollHeight + 'px';
                button.innerHTML = '<i class="fas fa-chevron-up mr-2"></i> Hide Details';
            }
        }

        function setMainVideo(url, element) {
            const player = document.getElementById('main-video-player');
            player.innerHTML = ''; // Clear previous content
            const youtubePattern = /(youtube\.com|youtu\.be)/i;
            
            if (!url || !filterVar(url)) {
                player.innerHTML = '<div class="error-overlay">Invalid video URL</div>';
                return;
            }

            if (youtubePattern.test(url)) {
                let videoId = extractYouTubeId(url);
                if (videoId) {
                    player.innerHTML = `<iframe src="https://www.youtube.com/embed/${videoId}?enablejsapi=1" 
                                              frameborder="0" 
                                              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                              allowfullscreen 
                                              onerror="this.parentElement.innerHTML='<div class=\\'error-overlay\\'>Failed to load video</div>'"></iframe>`;
                } else {
                    player.innerHTML = '<div class="error-overlay">Invalid YouTube URL</div>';
                }
            } else {
                player.innerHTML = `<video controls onerror="this.nextElementSibling.style.display='flex';">
                                      <source src="${url}" type="video/mp4">
                                      <div class="error-overlay" style="display:none;">Failed to load video. Check URL or format.</div>
                                    </video>`;
            }
        }

        function extractYouTubeId(url) {
            const patterns = [
                /v=([^&]+)/,
                /youtu\.be\/([^?]+)/,
                /embed\/([^?]+)/
            ];
            for (let pattern of patterns) {
                const match = url.match(pattern);
                if (match) return match[1];
            }
            return null;
        }

        function filterVar(url) {
            return /^(https?:\/\/)?([\da-z.-]+)\.([a-z.]{2,6})([/\w .-]*)*\/?$/.test(url);
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen font-sans antialiased">
    <!-- Container -->
    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center space-x-4">
                <a href="lessons.php" class="text-indigo-600 hover:text-indigo-800 transition-colors duration-200">
                    <i class="fas fa-arrow-left text-2xl"></i>
                </a>
                <h1 class="text-3xl font-bold text-gray-900 truncate max-w-xl"><?php echo htmlspecialchars($lesson['title']); ?></h1>
            </div>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <div class="space-x-3">
                    <a href="edit_lesson.php?id=<?php echo $lesson['id']; ?>" 
                       class="inline-flex items-center px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600 transition-colors duration-200">
                        <i class="fas fa-edit mr-2"></i> Edit
                    </a>
                    <a href="lessons.php?delete=<?php echo $lesson['id']; ?>" 
                       onclick="return confirm('Are you sure you want to delete this lesson?');"
                       class="inline-flex items-center px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors duration-200">
                        <i class="fas fa-trash mr-2"></i> Delete
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Error Message -->
        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-md">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Video Player and Details -->
            <div class="lg:col-span-2">
                <!-- Video Player -->
                <div class="video-player mb-6" id="main-video-player">
                    <?php
                    $first_video = array_filter($media, function($item) { return !empty($item['video_url']); });
                    $first_video = reset($first_video);
                    if ($first_video && $first_video['video_url']) {
                        $youtube_pattern = '/(youtube\.com|youtu\.be)/i';
                        if (preg_match($youtube_pattern, $first_video['video_url'])) {
                            $video_id = '';
                            if (preg_match('/v=([^&]+)/', $first_video['video_url'], $matches)) {
                                $video_id = $matches[1];
                            } elseif (preg_match('/youtu\.be\/([^?]+)/', $first_video['video_url'], $matches)) {
                                $video_id = $matches[1];
                            }
                            if ($video_id) {
                                echo '<iframe src="https://www.youtube.com/embed/' . htmlspecialchars($video_id) . '?enablejsapi=1" 
                                        frameborder="0" 
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                        allowfullscreen 
                                        onerror="this.parentElement.innerHTML=\'<div class=\\\'error-overlay\\\'>Failed to load YouTube video</div>\'"></iframe>';
                            } else {
                                echo '<div class="error-overlay">Invalid YouTube URL</div>';
                            }
                        } else {
                            echo '<video controls onerror="this.nextElementSibling.style.display=\'flex\';">
                                    <source src="' . htmlspecialchars($first_video['video_url']) . '" type="video/mp4">
                                    <div class="error-overlay" style="display:none;">Failed to load video. Check URL or format.</div>
                                  </video>';
                        }
                    } else {
                        echo '<div class="flex items-center justify-center h-full text-gray-500">No video available</div>';
                    }
                    ?>
                </div>

                <!-- Collapsible Details -->
                <div class="bg-white rounded-xl shadow-md p-6 shadow-hover">
                    <button id="toggle-details" 
                            onclick="toggleDetails()" 
                            class="w-full text-left text-lg font-semibold text-gray-900 flex items-center justify-between">
                        <span>Lesson Details</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div id="details-content" class="collapsible-content mt-4" style="max-height: <?php echo $lesson['file_url'] || $lesson['description'] ? 'none' : '0'; ?>">
                        <?php if ($lesson['file_url']): ?>
                            <div class="mt-4">
                                <span class="block text-sm font-medium text-gray-500 uppercase">Resource</span>
                                <a href="<?php echo htmlspecialchars($lesson['file_url']); ?>" 
                                   class="text-indigo-600 hover:text-indigo-800 transition-colors duration-200" 
                                   target="_blank">
                                    <i class="fas fa-file-download mr-2"></i>Download/View
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="mt-4">
                            <span class="block text-sm font-medium text-gray-500 uppercase">Description</span>
                            <p class="mt-2 text-gray-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($lesson['description'])); ?></p>
                        </div>
                        <div class="mt-4">
                            <span class="block text-sm font-medium text-gray-500 uppercase">Date</span>
                            <p class="mt-1 text-gray-900"><?php echo htmlspecialchars($lesson['lesson_date']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Media Gallery -->
            <?php if (!empty($media)): ?>
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-md p-6 shadow-hover">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Media Gallery</h2>
                        <div class="space-y-4 max-h-[600px] overflow-y-auto">
                            <?php foreach ($media as $item): ?>
                                <div class="media-thumbnail">
                                    <?php if ($item['video_url']): ?>
                                        <div onclick="setMainVideo('<?php echo htmlspecialchars($item['video_url']); ?>', this)"
                                             class="relative cursor-pointer">
                                            <img src="https://img.youtube.com/vi/<?php 
                                                $video_id = '';
                                                if (preg_match('/v=([^&]+)/', $item['video_url'], $matches)) {
                                                    $video_id = $matches[1];
                                                } elseif (preg_match('/youtu\.be\/([^?]+)/', $item['video_url'], $matches)) {
                                                    $video_id = $matches[1];
                                                }
                                                echo $video_id ?: 'default';
                                            ?>/hqdefault.jpg" 
                                                 alt="Video thumbnail" 
                                                 class="w-full h-24 object-cover rounded-md"
                                                 onerror="this.src='https://via.placeholder.com/320x180?text=Thumbnail+Not+Available'">
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                <i class="fas fa-play text-white text-2xl opacity-75"></i>
                                            </div>
                                        </div>
                                    <?php elseif ($item['photo_url']): ?>
                                        <a href="<?php echo htmlspecialchars($item['photo_url']); ?>" target="_blank">
                                            <img src="<?php echo htmlspecialchars($item['photo_url']); ?>" 
                                                 alt="Lesson photo" 
                                                 class="w-full h-24 object-cover rounded-md"
                                                 onerror="this.src='https://via.placeholder.com/320x180?text=Image+Not+Available'">
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Back Button -->
        <div class="mt-6">
            <a href="lessons.php" 
               class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors duration-200">
                <i class="fas fa-arrow-left mr-2"></i> Back to Lessons
            </a>
        </div>
    </div>
</body>
</html>