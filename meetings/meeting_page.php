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

include '../admin/includes/db.php'; // Database connection
// $conn = include '../admin/includes/db.php'; // This line might be redundant

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$groupedMeetings = []; // Initialize an array to hold meetings grouped by category

try {
    // MODIFIED: Select the new 'category' column and order by category first
    $stmt = $conn->prepare("
        SELECT id, title, meeting_date, category 
        FROM meetings 
        ORDER BY category, meeting_date DESC
    ");
    $stmt->execute();
    $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // NEW: Group the results by category
    foreach ($meetings as $meeting) {
        $category = !empty($meeting['category']) ? $meeting['category'] : 'General';
        $groupedMeetings[$category][] = $meeting;
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("An error occurred while fetching the meetings.");
}
?>

<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>បញ្ជីកិច្ចប្រជុំ - HR App</title>

    <!-- Frameworks & Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- Favicon & Theme Color -->
    <link rel="icon" type="image/png" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
    <meta name="theme-color" content="#6366f1">

    <style>
        /* === SHARED DESIGN SYSTEM === */
        :root {
            --primary: #6366f1;
            --primary-light: #8b5cf6;
            --primary-dark: #4f46e5;
            --secondary: #06b6d4;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --dark: #0f172a;
            --light: #f8fafc;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-500: #64748b;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --border-radius-xl: 20px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Kantumruy Pro', sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            color: var(--gray-800);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            min-height: 100vh;
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
        .app-header { background: rgba(250, 204, 21, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
        .app-title { background: none !important; -webkit-text-fill-color: white !important; color: white !important; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-outline-primary { color: white !important; border-color: white !important; }
        .btn-outline-primary:hover { background: white !important; color: #f59e0b !important; }
        .meeting-card::after { 
            content: ""; position: absolute; bottom: -5px; right: -5px; width: 40px; height: 40px;
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
        .app-header { background: rgba(234, 88, 12, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
        .app-title { background: none !important; -webkit-text-fill-color: white !important; color: white !important; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-outline-primary { color: white !important; border-color: white !important; }
        .btn-outline-primary:hover { background: white !important; color: #ea580c !important; }
        .meeting-card::after { 
            content: "\f67f"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
            position: absolute; bottom: -2px; right: -2px; font-size: 30px;
            opacity: 0.1; color: #ea580c; animation: floatUpDown 6s ease-in-out infinite;
        }

        <?php elseif ($currentTheme === 'cny'): ?>
        :root { --primary: #dc2626; --primary-light: #f87171; --primary-dark: #b91c1c; }
        .app-header { background: rgba(220, 38, 38, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
        .app-title { background: none !important; -webkit-text-fill-color: white !important; color: white !important; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-outline-primary { color: white !important; border-color: white !important; }
        .btn-outline-primary:hover { background: white !important; color: #dc2626 !important; }
        .meeting-card::after { 
            content: ""; position: absolute; bottom: -5px; right: -5px; width: 40px; height: 40px;
            background-image: url('https://i.ibb.co/G4K8Mv36/chinese-new-year.png');
            background-size: contain; background-repeat: no-repeat;
            opacity: 0.12; animation: floatUpDown 6s ease-in-out infinite;
        }

        <?php elseif ($currentTheme === 'wf'): ?>
        :root { --primary: #0284c7; --primary-light: #38bdf8; --primary-dark: #0369a1; }
        .app-header { background: rgba(2, 132, 199, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
        .app-title { background: none !important; -webkit-text-fill-color: white !important; color: white !important; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-outline-primary { color: white !important; border-color: white !important; }
        .btn-outline-primary:hover { background: white !important; color: #0284c7 !important; }
        .meeting-card::after { 
            content: "\f773"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
            position: absolute; bottom: -2px; right: -2px; font-size: 35px;
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
            animation: bgZoom 20s ease-in-out infinite alternate;
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

        /* Prevent horizontal scrolling */
        html,
        body {
            overflow-x: hidden;
            width: 100%;
        }

        .app-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 24px;
            padding-bottom: 90px;
        }

        /* === HEADER STYLES === */
        .app-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius-xl);
            padding: 16px 24px;
            margin-bottom: 32px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 16px;
            text-decoration: none;
        }

        .logo-img {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius);
            object-fit: cover;
            box-shadow: var(--shadow);
        }

        .app-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* === CATEGORY SECTIONS === */
        .category-section {
            margin-bottom: 32px;
            animation: fadeInUp 0.5s ease-out backwards;
        }

        .category-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-left: 8px;
            border-left: 4px solid var(--primary);
        }

        /* === MEETING CARDS === */
        .meeting-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .meeting-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-lg);
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.4);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .meeting-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
            background: var(--light);
        }

        .meeting-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .meeting-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .meeting-date {
            font-size: 0.85rem;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-view {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            transition: all 0.2s;
        }

        .meeting-card:hover .btn-view {
            background: var(--primary);
            color: white;
        }

        /* === EMPTY STATE === */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 20px;
        }

        /* === BOTTOM NAV === */
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            justify-content: space-around;
            padding: 8px 0;
            box-shadow: 0 -2px 16px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            border-top-left-radius: var(--border-radius-lg);
            border-top-right-radius: var(--border-radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
            min-height: 64px;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--gray-500);
            font-size: 0.75rem;
            font-weight: 600;
            flex: 1;
            padding: 6px;
        }

        .nav-item.active {
            color: var(--primary);
        }

        .nav-icon {
            font-size: 1.3rem;
            margin-bottom: 2px;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .app-container {
                padding: 12px;
                padding-bottom: 90px;
            }

            .app-header {
                padding: 12px 16px;
                margin-bottom: 20px;
            }

            .logo-img {
                width: 40px;
                height: 40px;
            }

            .app-title {
                font-size: 1.25rem;
            }

            .bottom-nav {
                display: flex;
            }
        }
    </style>
</head>

<body>

    <div class="app-container">
        <!-- Modern Header -->
        <header class="app-header animate__animated animate__fadeInDown">
            <a href="../homes.php" class="logo-container">
                <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="Logo" class="logo-img">
                <h1 class="app-title">បញ្ជីកិច្ចប្រជុំ</h1>
            </a>
            <a href="../homes.php" class="btn btn-outline-primary btn-sm rounded-pill px-3 no-print">
                <i class="fas fa-arrow-left me-1"></i> ថយក្រោយ
            </a>
        </header>

        <!-- Meeting List -->
        <?php if (empty($groupedMeetings)): ?>
            <div class="empty-state animate__animated animate__fadeIn">
                <i class="fas fa-video-slash empty-icon"></i>
                <h3 class="text-secondary">មិនមានកិច្ចប្រជុំនៅឡើយទេ</h3>
                <p class="text-muted">រាល់កិច្ចប្រជុំដែលបានកត់ត្រានឹងបង្ហាញនៅទីនេះ</p>
            </div>
        <?php else: ?>
            <?php foreach ($groupedMeetings as $category => $meetings): ?>
                <div class="category-section">
                    <h2 class="category-title">
                        <i class="fas fa-folder-open"></i>
                        <?php echo htmlspecialchars($category); ?>
                    </h2>
                    <div class="meeting-grid">
                        <?php foreach ($meetings as $meeting): ?>
                            <a href="../meetings/view_meeting_page.php?id=<?php echo $meeting['id']; ?>" class="meeting-card">
                                <div class="meeting-info">
                                    <span class="meeting-name"><?php echo htmlspecialchars($meeting['title']); ?></span>
                                    <span class="meeting-date">
                                        <i class="far fa-calendar-alt"></i>
                                        <?php echo htmlspecialchars(date('d F Y', strtotime($meeting['meeting_date']))); ?>
                                    </span>
                                </div>
                                <div class="btn-view">
                                    <i class="fas fa-chevron-right"></i>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="../homes.php" class="nav-item">
            <i class="fas fa-home nav-icon"></i>
            <span>ទំព័រដើម</span>
        </a>
        <a href="../requests/requests_menu.php" class="nav-item">
            <i class="fas fa-clipboard-list nav-icon"></i>
            <span>សំណើ</span>
        </a>
        <a href="../system/checklist.php" class="nav-item">
            <i class="fas fa-tasks nav-icon"></i>
            <span>ការងារ</span>
        </a>
        <a href="https://app.vvc.asia/admin/profile.php" class="nav-item">
            <i class="fas fa-user nav-icon"></i>
            <span>គណនី</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    meetingGroups.forEach(group => {
    const meetingsInGroup = group.querySelectorAll('.meeting-item');
    let visibleMeetingsCount = 0;

    meetingsInGroup.forEach(item => {
    const title = item.getAttribute('data-title');
    if (title.includes(searchTerm)) {
    item.style.display = 'flex';
    visibleMeetingsCount++;
    } else {
    item.style.display = 'none';
    }
    });

    if (visibleMeetingsCount > 0) {
    group.style.display = 'block';
    } else {
    group.style.display = 'none';
    }
    });
    });
    });
    </script>
</body>

</html>
