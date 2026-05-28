<?php
session_start(); // Start session for login checking

// Load Theme Config
$themeConfigPath = __DIR__ . '/includes/theme_config.json';
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


// Redirect to login page if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: https://app.vvc.asia/login.php");
    exit();
}

include 'includes/db.php';
$conn = include 'includes/db.php';

// Pagination variables
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10; // Number of lessons per page
$offset = ($page - 1) * $limit;

// Fetch total number of lessons
try {
    $stmt = $conn->query("SELECT COUNT(*) FROM lessons");
    $totalLessons = $stmt->fetchColumn();

    // Calculate total pages
    $totalPages = ceil($totalLessons / $limit);

    // Fetch lessons for the current page
    $stmt = $conn->prepare("
        SELECT id, title, lesson_date, description 
        FROM lessons 
        ORDER BY lesson_date DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $lessons = [];
    $totalLessons = 0;
    $totalPages = 1;
}
?>


<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>មេរៀន - HR App</title>

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
        .lesson-card::after { 
            content: ""; position: absolute; bottom: 10px; right: 10px; width: 50px; height: 50px;
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
        .lesson-card::after { 
            content: "\f67f"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
            position: absolute; bottom: 5px; right: 5px; font-size: 40px;
            opacity: 0.1; color: #ea580c; animation: floatUpDown 6s ease-in-out infinite;
        }

        <?php elseif ($currentTheme === 'cny'): ?>
        :root { --primary: #dc2626; --primary-light: #f87171; --primary-dark: #b91c1c; }
        .app-header { background: rgba(220, 38, 38, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
        .app-title { background: none !important; -webkit-text-fill-color: white !important; color: white !important; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .lesson-card::after { 
            content: ""; position: absolute; bottom: 10px; right: 10px; width: 50px; height: 50px;
            background-image: url('https://i.ibb.co/G4K8Mv36/chinese-new-year.png');
            background-size: contain; background-repeat: no-repeat;
            opacity: 0.12; animation: floatUpDown 6s ease-in-out infinite;
        }

        <?php elseif ($currentTheme === 'wf'): ?>
        :root { --primary: #0284c7; --primary-light: #38bdf8; --primary-dark: #0369a1; }
        .app-header { background: rgba(2, 132, 199, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
        .app-title { background: none !important; -webkit-text-fill-color: white !important; color: white !important; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .lesson-card::after { 
            content: "\f773"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
            position: absolute; bottom: 5px; right: 5px; font-size: 45px;
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
            max-width: 1000px;
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

        .btn-action {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            text-decoration: none;
            box-shadow: var(--shadow);
        }

        .btn-add {
            background: var(--primary);
            color: white;
        }

        .btn-add:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-2px);
        }

        .btn-logout {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .btn-logout:hover {
            background: var(--danger);
            color: white;
        }

        /* === LESSON CARDS === */
        .lesson-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }

        .lesson-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            border-radius: var(--border-radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.4);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
            position: relative;
        }

        .lesson-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border-color: var(--primary-light);
        }

        .card-body {
            padding: 24px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .lesson-date {
            font-size: 0.85rem;
            color: var(--gray-500);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .lesson-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--gray-900);
            line-height: 1.4;
        }

        .lesson-desc {
            color: var(--gray-600);
            font-size: 0.95rem;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            flex: 1;
        }

        .card-footer {
            padding: 16px 24px;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-100);
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn-view {
            padding: 8px 16px;
            border-radius: var(--border-radius);
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            flex: 1;
            text-align: center;
            box-shadow: var(--shadow);
            transition: all 0.2s;
        }

        .btn-view:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-1px);
            color: white;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-edit {
            background: rgba(245, 158, 11, 0.1);
            color: var(--accent);
        }

        .btn-edit:hover {
            background: var(--accent);
            color: white;
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .btn-delete:hover {
            background: var(--danger);
            color: white;
        }

        /* === PAGINATION === */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 40px;
        }

        .page-link {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--border-radius);
            background: white;
            border: 1px solid var(--gray-200);
            color: var(--gray-600);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .page-link:hover,
        .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
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

        /* Animation */
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

        .animate-delay-1 {
            animation-delay: 0.1s;
        }

        .animate-delay-2 {
            animation-delay: 0.2s;
        }

        .animate-delay-3 {
            animation-delay: 0.3s;
        }

        /* Responsive */
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

            .lesson-grid {
                grid-template-columns: 1fr;
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
                <h1 class="app-title">មេរៀន</h1>
            </a>
            <div class="d-flex gap-2">
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="../posts/post_lesson.php" class="btn-action btn-add" title="Add Lesson">
                        <i class="fas fa-plus"></i>
                    </a>
                <?php endif; ?>
                <a href="../logout.php" class="btn-action btn-logout" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <main>
            <?php if (empty($lessons)): ?>
                <div class="text-center py-5 bg-white bg-opacity-75 rounded-4 shadow-sm animate__animated animate__fadeInUp" style="backdrop-filter: blur(10px);">
                    <img src="https://cdni.iconscout.com/illustration/premium/thumb/empty-folder-3374345-2810771.png"
                        alt="No Lessons" style="width: 150px; opacity: 0.8;">
                    <h5 class="mt-3 text-secondary">មិនទាន់មានមេរៀននៅឡើយទេ</h5>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="../posts/post_lesson.php" class="btn btn-primary mt-3 rounded-pill px-4">
                            <i class="fas fa-plus me-2"></i> បង្កើតមេរៀនថ្មី
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="lesson-grid">
                    <?php foreach ($lessons as $index => $lesson): ?>
                        <div class="lesson-card animate__animated animate__fadeInUp"
                            style="animation-delay: <?php echo ($index * 0.1); ?>s">
                            <div class="card-body">
                                <div class="lesson-date">
                                    <i class="far fa-calendar-alt"></i>
                                    <?php echo htmlspecialchars($lesson['lesson_date']); ?>
                                </div>
                                <h3 class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></h3>
                                <div class="lesson-desc">
                                    <?php echo nl2br(htmlspecialchars($lesson['description'])); ?>
                                </div>
                            </div>
                            <div class="card-footer">
                                <a href="view_lesson.php?id=<?php echo $lesson['id']; ?>" class="btn-view">
                                    <i class="fas fa-eye me-1"></i> មើល
                                </a>
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                    <a href="edit_lesson.php?id=<?php echo $lesson['id']; ?>" class="btn-icon btn-edit"
                                        title="កែប្រែ">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $lesson['id']; ?>"
                                        onclick="return confirm('តើអ្នកពិតជាចង់លុបមេរៀននេះមែនទេ?');" class="btn-icon btn-delete"
                                        title="លុប">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="../homes.php" class="nav-item">
            <i class="fas fa-home nav-icon"></i>
            <span>ទំព័រដើម</span>
        </a>
        <a href="../requests_menu.php" class="nav-item">
            <i class="fas fa-clipboard-list nav-icon"></i>
            <span>សំណើ</span>
        </a>
        <a href="../checklist.php" class="nav-item">
            <i class="fas fa-tasks nav-icon"></i>
            <span>ការងារ</span>
        </a>
        <a href="../profile.php" class="nav-item">
            <i class="fas fa-user nav-icon"></i>
            <span>គណនី</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>