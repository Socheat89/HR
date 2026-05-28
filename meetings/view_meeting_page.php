<?php
session_start();

// Load Theme Config
$themeConfigPath = __DIR__ . '/../admin/includes/theme_config.json';
$currentTheme = 'default';
$customImage = '';
if (file_exists($themeConfigPath)) {
    $configData = json_decode(file_get_contents($themeConfigPath), true);
    $currentTheme = $configData['theme'] ?? 'default';
    $customImage = $configData['custom_image'] ?? '';
}

// Default Background Images for each theme
$themeBackgrounds = [   
    'kny'  => 'https://i.ibb.co/RKMS4tb/khmer-new-year-bg-1770518313913.jpg',
    'pb'   => 'https://i.ibb.co/S4dYb35p/khmer-new-year-bg-1770518389358.jpg',
    'cny'  => 'https://i.ibb.co/4462998/khmer-new-year-bg-1770518448823.jpg',
    'wf'   => 'https://i.ibb.co/2611144/khmer-new-year-bg-1770518505378.jpg',
    'kb'   => 'https://images.unsplash.com/photo-1596701062351-be5f6a200a45?q=80&w=1600',
    'indy' => 'https://images.unsplash.com/photo-1629813289069-7c8704204d60?q=80&w=1600'
];

// Determine which image to use
$bgImage = !empty($customImage) ? $customImage : ($themeBackgrounds[$currentTheme] ?? '');
include '../admin/includes/db.php';
$conn = include '../admin/includes/db.php';

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
    $raw_photos = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Convert mp3_url and photo_urls to absolute URLs
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/";
    // Adjust base_url if it's in a subdirectory like /meetings/
    $admin_base_url = str_replace('/meetings/', '/admin/', $base_url);

    $absolute_mp3_url = !empty($meeting['mp3_url']) && !filter_var($meeting['mp3_url'], FILTER_VALIDATE_URL)
        ? $admin_base_url . ltrim($meeting['mp3_url'], '/')
        : $meeting['mp3_url'];

    $photos = [];
    foreach ($raw_photos as $p) {
        if (!empty($p) && !filter_var($p, FILTER_VALIDATE_URL)) {
            $photos[] = $admin_base_url . ltrim($p, '/');
        } else {
            $photos[] = $p;
        }
    }

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
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: #2d3748;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        @keyframes bgZoom {
            from { background-size: 100% 100%; }
            to { background-size: 110% 110%; }
        }

        /* Floating Animation for Theme Icons */
        @keyframes floatUpDown {
            0% { transform: translateY(0) rotate(-15deg); }
            50% { transform: translateY(-15px) rotate(-10deg); }
            100% { transform: translateY(0) rotate(-15deg); }
        }

        /* Season/Festival Theme Overrides */
        <?php if ($currentTheme === 'kny'): ?>
        :root { --primary: #f59e0b; --primary-light: #fbbf24; --primary-dark: #d97706; }
        .meeting-title { color: #f59e0b !important; }
        .section-title { color: #d97706 !important; }
        .main-card::after { 
            content: ""; position: absolute; bottom: 15px; right: 15px; width: 60px; height: 60px;
            background-image: url('https://i.ibb.co/qFRZ8SCK/khmer-new-year.png');
            background-size: contain; background-repeat: no-repeat;
            opacity: 0.12; animation: floatUpDown 6s ease-in-out infinite;
        }
        /* Fireworks Overlay for KNY */
        body::after {
            content: "";
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('https://media.tenor.com/XesYJjyNYgAAAAAi/fireworks-putukan.gif');
            background-size: cover; background-repeat: no-repeat;
            pointer-events: none; z-index: -1; opacity: 0.35; mix-blend-mode: screen;
        }
        
        <?php elseif ($currentTheme === 'pb'): ?>
        :root { --primary: #ea580c; --primary-light: #fdba74; --primary-dark: #c2410c; }
        .meeting-title { color: #ea580c !important; }
        .section-title { color: #c2410c !important; }
        .main-card::after { 
            content: "\f67f"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
            position: absolute; bottom: 10px; right: 10px; font-size: 50px;
            opacity: 0.1; color: #ea580c; animation: floatUpDown 6s ease-in-out infinite;
        }

        <?php elseif ($currentTheme === 'cny'): ?>
        :root { --primary: #dc2626; --primary-light: #f87171; --primary-dark: #b91c1c; }
        .meeting-title { color: #dc2626 !important; }
        .section-title { color: #b91c1c !important; }
        .main-card::after { 
            content: ""; position: absolute; bottom: 15px; right: 15px; width: 60px; height: 60px;
            background-image: url('https://i.ibb.co/G4K8Mv36/chinese-new-year.png');
            background-size: contain; background-repeat: no-repeat;
            opacity: 0.12; animation: floatUpDown 6s ease-in-out infinite;
        }

        <?php elseif ($currentTheme === 'wf'): ?>
        :root { --primary: #0284c7; --primary-light: #38bdf8; --primary-dark: #0369a1; }
        .meeting-title { color: #0284c7 !important; }
        .section-title { color: #0369a1 !important; }
        .main-card::after { 
            content: "\f773"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
            position: absolute; bottom: 10px; right: 10px; font-size: 60px;
            opacity: 0.1; color: #0284c7; animation: floatUpDown 6s ease-in-out infinite;
        }
        <?php endif; ?>

        /* Apply Theme Background Image */
        <?php if (!empty($bgImage)): ?>
        body {
            background-image: url('<?php echo $bgImage; ?>') !important;
            background-size: cover !important;
            background-position: center !important;
            background-attachment: fixed !important;
            background-repeat: no-repeat !important;
            animation: bgZoom 20s ease-in-out infinite alternate !important;
        }

        /* Overlay to ensure readability */
        body::before {
            content: "";
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(2px);
            z-index: -2;
        }
        <?php endif; ?>

        .main-card {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 1.5rem;
            box-shadow: 0 10px 32px rgba(0, 0, 0, 0.1);
            max-width: 900px;
            margin: 2rem auto;
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.3);
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
