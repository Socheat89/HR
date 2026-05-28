<?php
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
?>
<!DOCTYPE html>

<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#4f46e5">
    <title>ចុះឈ្មោះប្រជុំ - Meeting Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* === MODERN DASHBOARD STYLES === */
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

        @keyframes bgZoom {
            from { background-size: 100% 100%; }
            to { background-size: 110% 110%; }
        }

        /* Season/Festival Theme Overrides */
        <?php if ($currentTheme === 'kny'): ?>
        :root { --primary: #f59e0b; --primary-light: #fbbf24; --primary-dark: #d97706; --secondary: #ec4899; }
        .app-header { background: rgba(250, 204, 21, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
        .app-title { background: none !important; -webkit-text-fill-color: white !important; color: white !important; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .back-btn { color: white !important; }
        .welcome-section { background: linear-gradient(135deg, #f59e0b, #d97706) !important; }
        .dashboard-card::after { 
            content: ""; position: absolute; bottom: -10px; right: -10px; width: 60px; height: 60px;
            background-image: url('https://i.ibb.co/qFRZ8SCK/khmer-new-year.png');
            background-size: contain; background-repeat: no-repeat;
            opacity: 0.12; filter: drop-shadow(0 5px 8px rgba(0,0,0,0.1));
            animation: float 6s ease-in-out infinite;
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
        :root { --primary: #ea580c; --primary-light: #fdba74; --primary-dark: #c2410c; --secondary: #f43f5e; }
        .app-header { background: rgba(234, 88, 12, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
        .app-title { background: none !important; -webkit-text-fill-color: white !important; color: white !important; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .back-btn { color: white !important; }
        .welcome-section { background: linear-gradient(135deg, #ea580c, #c2410c) !important; }
        .dashboard-card::after { 
            content: "\f67f"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
            position: absolute; bottom: -5px; right: -5px; font-size: 50px;
            opacity: 0.1; color: #ea580c; animation: float 6s ease-in-out infinite;
        }

        <?php elseif ($currentTheme === 'cny'): ?>
        :root { --primary: #dc2626; --primary-light: #f87171; --primary-dark: #b91c1c; --secondary: #facc15; }
        .app-header { background: rgba(220, 38, 38, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
        .app-title { background: none !important; -webkit-text-fill-color: white !important; color: white !important; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .back-btn { color: white !important; }
        .welcome-section { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; }
        .dashboard-card::after { 
            content: ""; position: absolute; bottom: -10px; right: -10px; width: 60px; height: 60px;
            background-image: url('https://i.ibb.co/G4K8Mv36/chinese-new-year.png');
            background-size: contain; background-repeat: no-repeat;
            opacity: 0.12; filter: drop-shadow(0 5px 8px rgba(0,0,0,0.1));
            animation: float 6s ease-in-out infinite;
        }

        <?php elseif ($currentTheme === 'wf'): ?>
        :root { --primary: #0284c7; --primary-light: #38bdf8; --primary-dark: #0369a1; --secondary: #22d3ee; }
        .app-header { background: rgba(2, 132, 199, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
        .app-title { background: none !important; -webkit-text-fill-color: white !important; color: white !important; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .back-btn { color: white !important; }
        .welcome-section { background: linear-gradient(135deg, #0284c7, #0369a1) !important; }
        .dashboard-card::after { 
            content: "\f773"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
            position: absolute; bottom: -5px; right: -5px; font-size: 60px;
            opacity: 0.1; color: #0284c7; animation: float 6s ease-in-out infinite;
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
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(2px);
            z-index: -2;
        }
        <?php endif; ?>

        html, body {
            overflow-x: hidden;
            width: 100%;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .app-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 24px;
            box-sizing: border-box;
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
        }

        .logo-img {
            width: 56px;
            height: 56px;
            border-radius: var(--border-radius);
            object-fit: cover;
            box-shadow: var(--shadow-md);
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

        .back-btn {
            background: linear-gradient(135deg, var(--gray-600), var(--gray-700));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
        }

        /* === WELCOME SECTION === */
        .welcome-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: var(--border-radius-xl);
            padding: 40px;
            margin-bottom: 32px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            text-align: center;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translate(-50%, -50%) rotate(0deg); }
            50% { transform: translate(-50%, -50%) rotate(180deg); }
        }

        .welcome-content {
            position: relative;
            z-index: 1;
        }

        .welcome-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem;
        }

        .welcome-greeting {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .welcome-subtitle {
            font-size: 1rem;
            opacity: 0.9;
        }

        /* === DASHBOARD GRID === */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 20px;
            margin-bottom: 40px;
        }

        .dashboard-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border-radius: var(--border-radius-lg);
            padding: 28px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.4);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            opacity: 0.8;
        }

        .dashboard-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
            pointer-events: none;
        }

        .dashboard-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.4);
        }

        .dashboard-card:hover::before {
            opacity: 1;
        }

        .card-content {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            z-index: 1;
        }

        .card-icon {
            width: 64px;
            height: 64px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            flex-shrink: 0;
            box-shadow: var(--shadow-md);
        }

        .card-icon.green {
            background: linear-gradient(135deg, var(--success), #34d399);
        }

        .card-icon.red {
            background: linear-gradient(135deg, var(--danger), #f87171);
        }

        .card-text {
            flex: 1;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--gray-900);
        }

        .card-desc {
            font-size: 0.9rem;
            color: var(--gray-600);
            margin: 0;
        }

        .card-arrow {
            font-size: 1.5rem;
            color: var(--primary);
            transition: transform 0.3s ease;
        }

        .dashboard-card:hover .card-arrow {
            transform: translateX(5px);
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
            -webkit-backdrop-filter: blur(20px);
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
            padding: 6px 8px;
            border-radius: var(--border-radius);
            min-width: 60px;
            min-height: 48px;
            flex: 1;
            max-width: 80px;
        }

        .nav-item.active {
            color: var(--primary);
            background: rgba(99, 102, 241, 0.1);
            transform: scale(1.05);
        }

        .nav-item:hover {
            color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }

        .nav-icon {
            font-size: 1.3rem;
            margin-bottom: 2px;
        }

        a {
            text-decoration: none;
        }

        /* === ANIMATIONS === */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out forwards;
        }
        /* === MOBILE RESPONSIVE === */
        @media (max-width: 768px) {
            body {
                padding-bottom: 100px;
            }

            .app-container {
                padding: 12px;
            }

            .app-header {
                padding: 12px 16px;
                margin-bottom: 20px;
            }

            .logo-img {
                width: 44px;
                height: 44px;
            }

            .app-title {
                font-size: 1.2rem;
            }

            .back-btn {
                padding: 8px 14px;
                font-size: 0.85rem;
            }

            .back-btn span {
                display: none;
            }

            .welcome-section {
                padding: 24px 20px;
                margin-bottom: 24px;
            }

            .welcome-icon {
                width: 60px;
                height: 60px;
                font-size: 1.8rem;
            }

            .welcome-greeting {
                font-size: 1.4rem;
            }

            .welcome-subtitle {
                font-size: 0.9rem;
            }

            .dashboard-card {
                padding: 20px;
            }

            .card-icon {
                width: 52px;
                height: 52px;
                font-size: 1.4rem;
            }

            .card-title {
                font-size: 1rem;
            }

            .card-desc {
                font-size: 0.85rem;
            }

            .bottom-nav {
                display: flex;
            }

            .nav-item {
                font-size: 0.75rem;
                padding: 6px 8px;
                min-height: 48px;
            }

            .nav-icon {
                font-size: 1.3rem;
                margin-bottom: 4px;
            }

            /* Touch feedback for mobile */
            .nav-item:active {
                transform: scale(0.95);
                transition: transform 0.1s ease;
            }
        }

        @media (max-width: 480px) {
            .app-container {
                padding: 8px;
            }

            .welcome-section {
                padding: 20px 16px;
            }

            .welcome-greeting {
                font-size: 1.2rem;
            }

            .card-content {
                gap: 14px;
            }

            .card-icon {
                width: 48px;
                height: 48px;
                font-size: 1.2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div id="loading-overlay" style="
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        transition: opacity 0.5s ease-out;
    ">
        <div style="text-align: center; color: var(--gray-800);">
            <div style="
                width: 50px;
                height: 50px;
                border: 4px solid var(--gray-300);
                border-top: 4px solid var(--primary);
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto 16px;
            "></div>
            <p style="font-family: 'Kantumruy Pro', sans-serif; font-size: 1rem; opacity: 0.8; margin: 0;">កំពុងផ្ទុក...</p>
        </div>
    </div>

    <div class="app-container">
        <!-- Modern App Header -->
        <header class="app-header animate__animated animate__fadeIn">
            <div class="logo-container">
                <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="Logo" class="logo-img">
                <h1 class="app-title">ចុះឈ្មោះប្រជុំ</h1>
            </div>
            <a href="../homes.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                <span>ត្រឡប់ក្រោយ</span>
            </a>
        </header>

        <!-- Welcome Section -->
        <section class="welcome-section animate-fade-in-up">
            <div class="welcome-content">
                <div class="welcome-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h2 class="welcome-greeting">បញ្ជីចុះឈ្មោះចូលរួមប្រជុំ</h2>
                <p class="welcome-subtitle">សូមជ្រើសរើសប្រភេទចុះឈ្មោះខាងក្រោម</p>
            </div>
        </section>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <a href="../meetings/meeting_list.php" class="dashboard-card animate-fade-in-up" style="animation-delay: 0.1s">
                <div class="card-content">
                    <div class="card-icon green">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="card-text">
                        <h3 class="card-title">ចុះឈ្មោះប្រជុំបុគ្គលិកជំនាញ</h3>
                        <p class="card-desc">សូមបំពេញទិន្នន័យចុះឈ្មោះចូលរួមប្រជុំឱ្យបានត្រឹមត្រូវ</p>
                    </div>
                    <div class="card-arrow">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </div>
            </a>

            <a href="../meetings/meeting_list_admin.php" class="dashboard-card animate-fade-in-up" style="animation-delay: 0.2s">
                <div class="card-content">
                    <div class="card-icon red">
                        <i class="fas fa-user-xmark"></i>
                    </div>
                    <div class="card-text">
                        <h3 class="card-title">មិនបានចូលរួមប្រជុំបុគ្គលិកជំនាញ</h3>
                        <p class="card-desc">សូមបំពេញទិន្នន័យមិនបានចូលរួមប្រជុំឱ្យបានត្រឹមត្រូវ</p>
                    </div>
                    <div class="card-arrow">
                        <i class="fas fa-chevron-right"></i>
                    </div>
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
        <a href="../system/checklist.php" class="nav-item">
            <i class="fas fa-tasks nav-icon"></i>
            <span>ការងារ</span>
        </a>
        <a href="../posts/announcements.php" class="nav-item">
            <i class="fas fa-bell nav-icon"></i>
            <span>ដំណឹង</span>
        </a>
        <a href="https://app.vvc.asia/admin/profile.php" class="nav-item">
            <i class="fas fa-user nav-icon"></i>
            <span>គណនី</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Animation for cards
        document.addEventListener('DOMContentLoaded', () => {
            const animateCards = document.querySelectorAll('.animate-fade-in-up');
            animateCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });

        // Hide loading overlay when page is fully loaded
        window.addEventListener('load', function() {
            const loadingOverlay = document.getElementById('loading-overlay');
            if (loadingOverlay) {
                loadingOverlay.style.opacity = '0';
                setTimeout(() => {
                    loadingOverlay.style.display = 'none';
                }, 500);
            }
        });
    </script>
</body>
</html>
