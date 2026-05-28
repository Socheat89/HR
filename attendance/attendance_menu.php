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
?>
<!DOCTYPE html>

<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ម៉ឺនុយរបាយការណ៍វត្តមានបុគ្គលិក | Premium HRM</title>
    <!-- Google Fonts: Kantumruy Pro & Bayon -->
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;600;700&family=Bayon&display=swap" rel="stylesheet">
    <!-- Font Awesome 6.x -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --accent: #f59e0b;
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: rgba(255, 255, 255, 0.4);
            --text-main: #1e293b;
            --text-muted: #64748b;
            --shadow-lg: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Kantumruy Pro', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 50%, #dbeafe 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            position: relative;
            overflow-x: hidden;
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
        :root { --primary: #f59e0b; --primary-light: #fbbf24; --primary-dark: #d97706; --accent: #fbbf24; }
        .header-content h1 { color: var(--primary-dark) !important; text-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .back-btn { background: rgba(245, 158, 11, 0.15) !important; color: var(--primary-dark) !important; }
        .back-btn:hover { background: var(--primary-dark) !important; color: white !important; }
        .menu-card::after { 
            content: ""; position: absolute; bottom: 10px; right: 10px; width: 60px; height: 60px;
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
        :root { --primary: #ea580c; --primary-light: #fdba74; --primary-dark: #c2410c; --accent: #fdba74; }
        .header-content h1 { color: var(--primary-dark) !important; text-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .back-btn { background: rgba(234, 88, 12, 0.15) !important; color: var(--primary-dark) !important; }
        .back-btn:hover { background: var(--primary-dark) !important; color: white !important; }
        .menu-card::after { 
            content: "\f67f"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
            position: absolute; bottom: 5px; right: 5px; font-size: 50px;
            opacity: 0.1; color: #ea580c; animation: floatUpDown 6s ease-in-out infinite;
        }

        <?php elseif ($currentTheme === 'cny'): ?>
        :root { --primary: #dc2626; --primary-light: #f87171; --primary-dark: #b91c1c; --accent: #f87171; }
        .header-content h1 { color: var(--primary-dark) !important; text-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .back-btn { background: rgba(220, 38, 38, 0.15) !important; color: var(--primary-dark) !important; }
        .back-btn:hover { background: var(--primary-dark) !important; color: white !important; }
        .menu-card::after { 
            content: ""; position: absolute; bottom: 10px; right: 10px; width: 60px; height: 60px;
            background-image: url('https://i.ibb.co/G4K8Mv36/chinese-new-year.png');
            background-size: contain; background-repeat: no-repeat;
            opacity: 0.12; animation: floatUpDown 6s ease-in-out infinite;
        }

        <?php elseif ($currentTheme === 'wf'): ?>
        :root { --primary: #0284c7; --primary-light: #38bdf8; --primary-dark: #0369a1; --accent: #38bdf8; }
        .header-content h1 { color: var(--primary-dark) !important; text-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .back-btn { background: rgba(2, 132, 199, 0.15) !important; color: var(--primary-dark) !important; }
        .back-btn:hover { background: var(--primary-dark) !important; color: white !important; }
        .menu-card::after { 
            content: "\f773"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
            position: absolute; bottom: 5px; right: 5px; font-size: 60px;
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

        /* Ambient background circles */
        .ambient-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .circle {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            animation: move 20s infinite alternate ease-in-out;
        }

        .circle-1 {
            width: 400px;
            height: 400px;
            background: var(--primary-light);
            top: -100px;
            left: -100px;
        }

        .circle-2 {
            width: 300px;
            height: 300px;
            background: var(--accent);
            bottom: -50px;
            right: -50px;
            animation-duration: 15s;
        }

        @keyframes move {
            from { transform: translate(0, 0) scale(1); }
            to { transform: translate(50px, 50px) scale(1.1); }
        }

        .container {
            width: 100%;
            max-width: 900px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 32px;
            padding: 3rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            z-index: 10;
        }

        .nav-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: var(--primary-dark);
            font-weight: 700;
            padding: 0.75rem 1.25rem;
            border-radius: 16px;
            background: rgba(99, 102, 241, 0.1);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid transparent;
        }

        .back-btn:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateX(-5px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .header-content {
            text-align: center;
            margin-bottom: 4rem;
        }

        .header-content h1 {
            font-family: 'Bayon', cursive;
            font-size: 3rem;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
            letter-spacing: 2px;
            /* Gradient removed for clarity as per user request */
        }

        .header-content p {
            color: var(--text-muted);
            font-size: 1.1rem;
            font-weight: 400;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
        }

        .menu-card {
            text-decoration: none;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 2.5rem 1.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .menu-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            transform: scaleX(0);
            transition: transform 0.4s ease;
            transform-origin: left;
        }

        .menu-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border-color: var(--primary-light);
        }

        .menu-card:hover::after {
            transform: scaleX(1);
        }

        .icon-box {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(99, 102, 241, 0.05));
            border-radius: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 1.5rem;
            color: var(--primary-dark);
            font-size: 2.2rem;
            transition: all 0.4s ease;
        }

        .menu-card:hover .icon-box {
            background: var(--primary-dark);
            color: white;
            transform: scale(1.1) rotate(8deg);
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4);
        }

        .card-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0.75rem;
        }

        .card-desc {
            font-size: 0.95rem;
            color: var(--text-muted);
            margin-bottom: 2rem;
            line-height: 1.5;
        }

        .card-action {
            font-weight: 700;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            margin-top: auto;
        }

        .menu-card:hover .card-action i {
            transform: translateX(5px);
        }

        .card-action i {
            transition: transform 0.3s ease;
        }

        /* Footer Info */
        .footer-info {
            margin-top: 4rem;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        /* Responsive */
        @media (max-width: 640px) {
            body { padding: 1rem; }
            .container { padding: 2rem 1.5rem; border-radius: 24px; }
            .header-content h1 { font-size: 2.2rem; }
            .menu-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <div class="ambient-bg">
        <div class="circle circle-1"></div>
        <div class="circle circle-2"></div>
    </div>

    <div class="container animate__animated animate__fadeInUp">
        <div class="nav-header">
            <a href="../index.php" class="back-btn">
                <i class="fas fa-chevron-left"></i>
                ត្រឡប់ទៅទំព័រដើម
            </a>
            <!-- Optional: User status indicator can go here -->
        </div>

        <div class="header-content">
            <h1>របាយការណ៍វត្តមានបុគ្គលិក</h1>
            <p>សូមជ្រើសរើសទីតាំងបុគ្គលិកដែលលោកអ្នកចង់គ្រប់គ្រង</p>
        </div>

        <div class="menu-grid">
            <!-- 318 -->
            <a href="view_by_date.php" class="menu-card animate__animated animate__fadeIn" style="animation-delay: 0.1s;">
                <div class="icon-box">
                    <i class="fas fa-store"></i>
                </div>
                <div class="card-title">៣១៨</div>
                <div class="card-desc">គ្រប់គ្រង និងពិនិត្យវត្តមានបុគ្គលិកនៅទីតាំងហាង ៣១៨</div>
                <div class="card-action">
                    ចូលទៅកាន់របាយការណ៍ <i class="fas fa-arrow-right"></i>
                </div>
            </a>

            <!-- PSP -->
            <a href="attendance_PSP.php" class="menu-card animate__animated animate__fadeIn" style="animation-delay: 0.2s;">
                <div class="icon-box">
                    <i class="fas fa-warehouse"></i>
                </div>
                <div class="card-title">PSP</div>
                <div class="card-desc">គ្រប់គ្រង និងពិនិត្យវត្តមានបុគ្គលិកនៅទីតាំងឃ្លាំង PSP</div>
                <div class="card-action">
                    ចូលទៅកាន់របាយការណ៍ <i class="fas fa-arrow-right"></i>
                </div>
            </a>

            <!-- CKD -->
            <a href="attendance.php" class="menu-card animate__animated animate__fadeIn" style="animation-delay: 0.3s;">
                <div class="icon-box">
                    <i class="fas fa-truck-ramp-box"></i>
                </div>
                <div class="card-title">CKD</div>
                <div class="card-desc">គ្រប់គ្រង និងពិនិត្យវត្តមានបុគ្គលិកនៅទីតាំងឃ្លាំង CKD</div>
                <div class="card-action">
                    ចូលទៅកាន់របាយការណ៍ <i class="fas fa-arrow-right"></i>
                </div>
            </a>
        </div>

        <div class="footer-info">
            &copy; <?php echo date('Y'); ?> Premium HRM System - Developed with <i class="fas fa-heart text-danger"></i>
        </div>
    </div>

</body>
</html>