<?php
include '../admin/includes/auth.php';

// Determine current page for active navigation
$current_page = basename($_SERVER['PHP_SELF']);

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


// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// User role check if needed, otherwise rely on ../auth/auth.php or session
?>

<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#4f46e5">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
    <title>Requests Menu - HR App</title>

    <!-- Fonts & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        /* === SHARED DESIGN SYSTEM (From ../homes.php) === */
        :root {
            --primary: #6366f1;
            --primary-light: #8b5cf6;
            --primary-dark: #4f46e5;
            --secondary: #06b6d4;
            --accent: #f59e0b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #0f172a;
            --light: #f8fafc;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --border-radius-xl: 20px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Kantumruy Pro', 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            color: var(--gray-800);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        /* Prevent horizontal scrolling on mobile */
        html,
        body {
            overflow-x: hidden;
            width: 100%;
        }

        .app-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
            box-sizing: border-box;
            padding-bottom: 80px;
            /* Space for bottom nav */
        }

        /* === HEADER STYLES === */
        .app-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius-xl);
            padding: 20px 24px;
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
            width: 56px;
            height: 56px;
            border-radius: var(--border-radius);
            object-fit: cover;
            box-shadow: var(--shadow-md);
        }

        .app-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .back-btn {
            color: var(--gray-600);
            font-size: 1.1rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: color 0.2s;
        }

        .back-btn:hover {
            color: var(--primary);
        }

        /* === MENU GRID === */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-top: 40px;
        }

        .menu-card {
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(12px);
            border-radius: var(--border-radius-lg);
            padding: 32px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.4);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .menu-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }

        .menu-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-light);
        }

        .menu-card:hover::before {
            transform: scaleX(1);
        }

        /* Decorative Background Pattern for Theme Integration */
        .menu-card::after {
            content: '';
            position: absolute;
            bottom: -20px;
            right: -20px;
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            font-size: 80px;
            opacity: 0.03;
            color: var(--primary);
            transition: all 0.5s ease;
            transform: rotate(-15deg);
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

        /* Wiggle/Shake Animation for Hover */
        @keyframes iconWiggle {
            0% { transform: scale(1.2) rotate(0deg); }
            25% { transform: scale(1.2) rotate(8deg); }
            50% { transform: scale(1.2) rotate(-8deg); }
            75% { transform: scale(1.2) rotate(4deg); }
            100% { transform: scale(1.2) rotate(0deg); }
        }

        /* Season/Festival Theme Overrides */
        <?php if ($currentTheme === 'kny'): ?>
        :root { --primary: #f59e0b; --primary-light: #fbbf24; --primary-dark: #d97706; --secondary: #ec4899; }
        .app-header { border-color: rgba(245, 158, 11, 0.3) !important; }
        .app-title { background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; }
        .back-btn { color: var(--primary-dark) !important; }
        .menu-card::after { 
            content: ""; 
            background-image: url('https://i.ibb.co/qFRZ8SCK/khmer-new-year.png');
            background-size: contain; background-repeat: no-repeat;
            width: 100px; height: 100px; bottom: -10px; right: -10px; 
            opacity: 0.18; filter: drop-shadow(0 5px 8px rgba(0,0,0,0.1));
            animation: floatUpDown 5s ease-in-out infinite;
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
        :root { --primary: #ea580c; --primary-light: #fdba74; --primary-dark: #c2410c; --secondary: #4b5563; }
        .app-header { border-color: rgba(234, 88, 12, 0.3) !important; }
        .app-title { background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; }
        .back-btn { color: var(--primary-dark) !important; }
        .menu-card::after { content: "\f67f"; opacity: 0.15; filter: drop-shadow(0 5px 8px rgba(0,0,0,0.1)); animation: floatUpDown 5s ease-in-out infinite; }

        <?php elseif ($currentTheme === 'cny'): ?>
        :root { --primary: #dc2626; --primary-light: #f87171; --primary-dark: #b91c1c; --secondary: #fbbf24; }
        .app-header { border-color: rgba(220, 38, 38, 0.3) !important; }
        .app-title { background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; }
        .back-btn { color: var(--primary-dark) !important; }
        .menu-card::after { 
            content: ""; background-image: url('https://i.ibb.co/G4K8Mv36/chinese-new-year.png');
            background-size: contain; background-repeat: no-repeat;
            width: 100px; height: 100px; bottom: -10px; right: -10px;
            opacity: 0.15; filter: drop-shadow(0 5px 8px rgba(0,0,0,0.1));
            animation: floatUpDown 5s ease-in-out infinite;
        }

        <?php elseif ($currentTheme === 'wf'): ?>
        :root { --primary: #0284c7; --primary-light: #38bdf8; --primary-dark: #0369a1; --secondary: #0ea5e9; }
        .app-header { border-color: rgba(2, 132, 199, 0.3) !important; }
        .app-title { background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; }
        .back-btn { color: var(--primary-dark) !important; }
        .menu-card::after { content: "\f773"; opacity: 0.15; filter: drop-shadow(0 5px 8px rgba(0,0,0,0.1)); animation: floatUpDown 5s ease-in-out infinite; }
        <?php endif; ?>

        .menu-card:hover::after {
            opacity: 0.25;
            bottom: -10px;
            right: -10px;
            animation: iconWiggle 0.5s ease-in-out infinite;
        }

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
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(2px);
            z-index: -2;
        }
        <?php endif; ?>

        .menu-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        /* Specific Colors */
        .icon-blue {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .icon-green {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .icon-purple {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }

        .menu-card:hover .menu-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .menu-title {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 8px;
        }

        .menu-desc {
            font-size: 1rem;
            color: var(--gray-500);
            line-height: 1.5;
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
            transition: all 0.2s ease;
            padding: 6px;
            flex: 1;
        }

        .nav-item.active {
            color: var(--primary);
        }

        .nav-icon {
            font-size: 1.3rem;
            margin-bottom: 2px;
        }

        /* === MOBILE RESPONSIVE === */
        @media (max-width: 768px) {
            .app-container {
                padding: 12px;
                padding-bottom: 90px;
            }

            .app-header {
                padding: 12px 16px;
                border-radius: var(--border-radius-lg);
                margin-bottom: 24px;
            }

            .logo-img {
                width: 40px;
                height: 40px;
            }

            .app-title {
                font-size: 1.5rem;
            }

            .menu-grid {
                grid-template-columns: 1fr;
                gap: 16px;
                margin-top: 20px;
            }

            .menu-card {
                padding: 24px;
                flex-direction: row;
                text-align: left;
                align-items: center;
                gap: 20px;
            }

            .menu-icon {
                width: 60px;
                height: 60px;
                font-size: 1.8rem;
                margin-bottom: 0;
                flex-shrink: 0;
            }

            .bottom-nav {
                display: flex;
            }
        }
    </style>
</head>

<body>

    <div class="app-container">
        <!-- Header -->
        <header class="app-header animate__animated animate__fadeInDown">
            <a href="../homes.php" class="logo-container">
                <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="HRM Logo" class="logo-img">
                <h1 class="app-title">ម៉ឺនុយស្នើសុំ</h1>
            </a>
            <a href="../homes.php" class="back-btn d-none d-md-flex">
                <i class="fas fa-arrow-left"></i> ត្រឡប់ក្រោយ
            </a>
        </header>

        <!-- Menu Grid -->
        <div class="menu-grid">
            <!-- New Request -->
            <a href="../admin/submit_request.php" class="menu-card animate__animated animate__fadeInUp"
                style="animation-delay: 0.1s">
                <div class="menu-icon icon-blue">
                    <i class="fas fa-plus"></i>
                </div>
                <div>
                    <h3 class="menu-title">ដាក់ស្នើសុំថ្មី</h3>
                    <p class="menu-desc">បង្កើតសំណើរសុំថ្មីសម្រាប់ឈប់សម្រាក ឬផ្សេងៗ</p>
                </div>
            </a>

            <!-- Requests Table -->
            <a href="../admin/table_report.php" class="menu-card animate__animated animate__fadeInUp"
                style="animation-delay: 0.2s">
                <div class="menu-icon icon-green">
                    <i class="fas fa-table"></i>
                </div>
                <div>
                    <h3 class="menu-title">តារាងស្នើសុំ</h3>
                    <p class="menu-desc">ពិនិត្យមើលប្រវត្តិនិងស្ថានភាពសំណើរបស់អ្នក</p>
                </div>
            </a>

            <!-- Analyze Requests -->
            <a href="../admin/analyze_requests.php" class="menu-card animate__animated animate__fadeInUp"
                style="animation-delay: 0.3s">
                <div class="menu-icon icon-purple">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div>
                    <h3 class="menu-title">វិភាគស្នើសុំ</h3>
                    <p class="menu-desc">មើលស្ថិតិនិងទិន្នន័យនៃសំណើរសុំ</p>
                </div>
            </a>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="../homes.php" class="nav-item">
            <i class="fas fa-home nav-icon"></i>
            <span>ទំព័រដើម</span>
        </a>
        <a href="../admin/submit_request.php" class="nav-item">
            <i class="fas fa-plus-circle nav-icon"></i>
            <span>ស្នើសុំ</span>
        </a>
        <a href="../admin/table_report.php" class="nav-item">
            <i class="fas fa-list-alt nav-icon"></i>
            <span>បញ្ជី</span>
        </a>
        <a href="?logout=true" class="nav-item" onclick="return confirm('តើអ្នកប្រាកដជាចង់ចាកចេញមែនទេ?');">
            <i class="fas fa-sign-out-alt nav-icon"></i>
            <span>ចាកចេញ</span>
        </a>
    </nav>

</body>

</html>
