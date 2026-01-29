<?php
// --- PHP code remains the same ---
include 'admin/includes/db.php';
$conn = include 'admin/includes/db.php';

// Fetch meeting ID from the query string
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid meeting ID.");
}

$meeting_id = $_GET['id'];

try {
    // Fetch meeting details
    $stmt = $conn->prepare("SELECT * FROM meetings WHERE id = :meeting_id");
    $stmt->bindParam(':meeting_id', $meeting_id, PDO::PARAM_INT);
    $stmt->execute();
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$meeting) {
        die("Meeting not found.");
    }

    // Fetch associated photos
    $stmt = $conn->prepare("SELECT photo_url FROM meeting_photos WHERE meeting_id = :meeting_id");
    $stmt->bindParam(':meeting_id', $meeting_id, PDO::PARAM_INT);
    $stmt->execute();
    $photos = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Convert mp3_url to absolute URL
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/";
    $absolute_mp3_url = !empty($meeting['mp3_url']) && !filter_var($meeting['mp3_url'], FILTER_VALIDATE_URL)
        ? $base_url . ltrim($meeting['mp3_url'], '/')
        : $meeting['mp3_url'];

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("An error occurred while fetching the meeting details.");
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2x/Logo-Van-Van-1.png">
    <title>ព័ត៌មានលម្អិតអំពីកិច្ចប្រជុំ</title>

    <!-- Google Fonts for Khmer -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- TailwindCSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Fancybox Library (for Lightbox Gallery) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />

    <style>
        body {
            font-family: 'Kantumruy Pro', sans-serif;
            background-color: #f7fafc;
            color: #2d3748;
        }

        .main-card {
            background-color: white;
            border-radius: 1rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            max-width: 900px;
            margin: 2rem auto;
            padding: 2.5rem;
        }

        .section {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e2e8f0;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .meeting-title {
            font-size: 2.25rem;
            font-weight: 700;
            line-height: 1.2;
            color: #1a202c;
        }

        .meeting-date {
            font-size: 1rem;
            color: #718096;
            margin-top: 0.5rem;
        }

        .description-text {
            font-size: 1.1rem;
            line-height: 1.7;
            color: #4a5568;
        }

        /* --- STYLES FOR PHOTO GALLERY --- */
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }

        .photo-item {
            display: block;
            border-radius: 0.75rem;
            overflow: hidden; /* Important for rounded corners on image */
            cursor: pointer;
        }

        .photo-item img {
            width: 100%;
            height: 100%;
            aspect-ratio: 4 / 3; /* Keeps the grid uniform */
            object-fit: cover; /* This makes the thumbnails look neat */
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .photo-item:hover img {
            transform: scale(1.05);
        }
        /* --- END OF PHOTO STYLES --- */

        .audio-player audio {
            width: 100%;
            margin-top: 0.5rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            background-color: #edf2f7;
            color: #4a5568;
            border-radius: 0.5rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            background-color: #e2e8f0;
            color: #2d3748;
        }

        .no-content {
            color: #a0aec0;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .main-card { margin: 1rem; padding: 1.5rem; }
            .meeting-title { font-size: 1.75rem; }
            .photo-grid { grid-template-columns: repeat(2, 1fr); } /* 2 columns on mobile */
        }
    </style>
</head>
<body>

    <div class="main-card">
        <!-- Header -->
        <header class="flex justify-between items-start mb-8">
            <div>
                <h1 class="meeting-title"><?php echo htmlspecialchars($meeting['title']); ?></h1>
                <p class="meeting-date"><i class="far fa-calendar-alt mr-2"></i><?php echo htmlspecialchars($meeting['meeting_date']); ?></p>
            </div>
            <a href="javascript:history.back()" class="back-btn flex-shrink-0 ml-4"><i class="fas fa-arrow-left"></i>ត្រឡប់ក្រោយ</a>
        </header>

        <!-- Description Section -->
        <div class="section">
            <h2 class="section-title"><i class="fas fa-info-circle"></i>ពិពណ៌នា</h2>
            <div class="description-text"><?php echo nl2br(htmlspecialchars($meeting['description'])); ?></div>
        </div>

        <!-- Audio Section -->
        <?php if (!empty($meeting['mp3_url'])): ?>
            <div class="section">
                <h2 class="section-title"><i class="fas fa-volume-up"></i>ស្តាប់ការថតសំឡេង</h2>
                <p class="text-gray-600 mb-3">ចុចប៊ូតុង Play (▶) នៅលើកម្មវិធីចាក់ខាងក្រោម ដើម្បីចាប់ផ្តើមស្តាប់។</p>
                
                <!-- === បានបន្ថែម Link នៅត្រង់នេះ === -->
                <div class="mt-4 mb-4">
                    <a href="<?php echo htmlspecialchars($absolute_mp3_url); ?>" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="inline-flex items-center gap-2 text-blue-600 hover:underline hover:text-blue-800 transition-colors font-medium">
                        <i class="fas fa-download"></i>
                        <span>ស្តាប់សំឡេងប្រជុំ</span>
                    </a>
                </div>
                <!-- === បញ្ចប់ការបន្ថែម Link === -->

                <div class="audio-player">
                    <audio controls preload="metadata">
                        <source src="<?php echo htmlspecialchars($absolute_mp3_url); ?>" type="audio/mpeg">
                        កម្មវិធីបើកអ៊ីនធឺណិតរបស់អ្នកមិនគាំទ្រការចាក់សំឡេងទេ។
                    </audio>
                </div>
            </div>
        <?php endif; ?>

        <!-- Photos Section -->
        <div class="section">
            <h2 class="section-title"><i class="fas fa-images"></i>រូបភាព</h2>
            <?php if (!empty($photos)): ?>
                <div class="photo-grid">
                    <?php foreach ($photos as $photo): ?>
                        <a href="<?php echo htmlspecialchars($photo); ?>" data-fancybox="gallery" class="photo-item">
                            <img src="<?php echo htmlspecialchars($photo); ?>" alt="Meeting Photo">
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-content">មិនមានរូបភាពសម្រាប់បង្ហាញទេ</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Fancybox JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>
    <script>
        // Initialize Fancybox
        Fancybox.bind('[data-fancybox="gallery"]', {
            // Optional: You can add custom options here if needed
        });
    </script>

</body>
</html>