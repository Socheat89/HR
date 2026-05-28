<?php
// Start session for consistency with other pages
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#4f46e5">
    <title>បញ្ជីប្រជុំ - Meeting List</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Scripts -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://unpkg.com/scrollreveal"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <style>
        /* === MODERN HRM DESIGN SYSTEM === */
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
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --border-radius-xl: 20px;
        }

        body {
            font-family: 'Kantumruy Pro', 'Inter', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            color: var(--gray-800);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
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
        .welcome-section { background: linear-gradient(135deg, #f59e0b, #d97706) !important; }
        .dashboard-card::after { 
            content: ""; position: absolute; bottom: -10px; right: -10px; width: 60px; height: 60px;
            background-image: url('https://i.ibb.co/qFRZ8SCK/khmer-new-year.png');
            background-size: contain; background-repeat: no-repeat;
            opacity: 0.12; filter: drop-shadow(0 5px 8px rgba(0,0,0,0.1));
            animation: floatUpDown 6s ease-in-out infinite;
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
        .welcome-section { background: linear-gradient(135deg, #ea580c, #c2410c) !important; }
        .dashboard-card::after { 
            content: "\f67f"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
            position: absolute; bottom: -5px; right: -5px; font-size: 50px;
            opacity: 0.1; color: #ea580c; animation: floatUpDown 6s ease-in-out infinite;
        }

        <?php elseif ($currentTheme === 'cny'): ?>
        :root { --primary: #dc2626; --primary-light: #f87171; --primary-dark: #b91c1c; }
        .app-header { background: rgba(220, 38, 38, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
        .app-title { background: none !important; -webkit-text-fill-color: white !important; color: white !important; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .welcome-section { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; }
        .dashboard-card::after { 
            content: ""; position: absolute; bottom: -10px; right: -10px; width: 60px; height: 60px;
            background-image: url('https://i.ibb.co/G4K8Mv36/chinese-new-year.png');
            background-size: contain; background-repeat: no-repeat;
            opacity: 0.12; filter: drop-shadow(0 5px 8px rgba(0,0,0,0.1));
            animation: floatUpDown 6s ease-in-out infinite;
        }

        <?php elseif ($currentTheme === 'wf'): ?>
        :root { --primary: #0284c7; --primary-light: #38bdf8; --primary-dark: #0369a1; }
        .app-header { background: rgba(2, 132, 199, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
        .app-title { background: none !important; -webkit-text-fill-color: white !important; color: white !important; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .welcome-section { background: linear-gradient(135deg, #0284c7, #0369a1) !important; }
        .dashboard-card::after { 
            content: "\f773"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
            position: absolute; bottom: -5px; right: -5px; font-size: 60px;
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

        .app-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 24px;
            width: 100%;
        }

        /* Header */
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
        }

        .logo-img {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius);
            object-fit: cover;
            box-shadow: var(--shadow-md);
        }

        .app-title {
            font-size: 1.3rem;
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
            padding: 8px 16px;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            color: white;
            box-shadow: var(--shadow-md);
        }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: var(--border-radius-xl);
            padding: 32px;
            margin-bottom: 32px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            text-align: center;
        }

        .welcome-icon {
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 2rem;
        }

        .welcome-greeting {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        /* Dashboard Cards */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .dashboard-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            border-radius: var(--border-radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.4);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
            overflow: hidden;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            background: white;
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-light);
        }

        .card-content {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .card-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }

        .card-icon.blue { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
        .card-icon.green { background: linear-gradient(135deg, var(--success), #34d399); }

        .card-text { flex: 1; }
        .card-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 4px; color: var(--gray-900); }
        .card-desc { font-size: 0.85rem; color: var(--gray-600); margin: 0; }
        .card-arrow { color: var(--primary); font-size: 1.2rem; }

        /* Pages */
        .page { display: none; animation: animate__fadeIn 0.5s; }
        .active-page { display: block; }

        /* Forms Styling */
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius-lg);
            padding: 30px;
            box-shadow: var(--shadow-lg);
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .form-label { font-weight: 600; color: var(--gray-700); margin-bottom: 8px; }
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px;
            border: 1px solid var(--gray-300);
            font-family: 'Kantumruy Pro', sans-serif;
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            width: 100%;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(99, 102, 241, 0.3); color: white; }

        /* Table Styling */
        .table-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .table thead { background: var(--gray-100); }
        .table th { padding: 16px; font-weight: 700; color: var(--gray-700); }
        .table td { padding: 16px; vertical-align: middle; }

        .badge-attended { background: #dcfce7; color: #15803d; padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 0.8rem; }
        .badge-absent { background: #fee2e2; color: #b91c1c; padding: 6px 12px; border-radius: 20px; font-weight: 600; font-size: 0.8rem; }

        /* Loading Overlay */
        #loading-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: white; display: flex; justify-content: center; align-items: center; z-index: 9999;
        }
        .spinner { width: 40px; height: 40px; border: 4px solid var(--gray-200); border-top: 4px solid var(--primary); border-radius: 50%; animation: spin 1s linear infinite; }

        /* Mobile Adjustments */
        @media (max-width: 768px) {
            .app-container { padding: 16px; }
            .welcome-section { padding: 24px; }
            .welcome-greeting { font-size: 1.2rem; }
            .dashboard-card { padding: 16px; }
            .card-icon { width: 48px; height: 48px; font-size: 1.2rem; }
            .table-responsive { font-size: 0.8rem; }
        }
    </style>
</head>
<body>
    <div id="loading-overlay">
        <div class="spinner"></div>
    </div>

    <div class="app-container">
        <!-- Header -->
        <header class="app-header animate__animated animate__fadeInDown">
            <div class="logo-container">
                <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="Logo" class="logo-img">
                <h1 class="app-title">បញ្ជីប្រជុំ</h1>
            </div>
            <a href="../homes.php" class="back-btn" id="header-back-btn">
                <i class="fas fa-arrow-left"></i>
                <span>ត្រឡប់ក្រោយ</span>
            </a>
        </header>

        <!-- MAIN PAGE (MENU) -->
        <div id="page-main" class="page active-page">
            <section class="welcome-section animate__animated animate__fadeInUp">
                <div class="welcome-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h2 class="welcome-greeting">សូមជ្រើសរើសផ្នែកខាងក្រោម</h2>
                <p class="welcome-subtitle">ដើម្បីគ្រប់គ្រងទិន្នន័យប្រជុំរបស់អ្នក</p>
            </section>

            <div class="dashboard-grid">
                <a href="javascript:void(0)" onclick="showPage('page-register')" class="dashboard-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
                    <div class="card-content">
                        <div class="card-icon blue">
                            <i class="fas fa-pen-to-square"></i>
                        </div>
                        <div class="card-text">
                            <h3 class="card-title">ចុះឈ្មោះចូលរួមប្រជុំ</h3>
                            <p class="card-desc">ចុះឈ្មោះដើម្បីកត់ត្រាវត្តមានរបស់អ្នក</p>
                        </div>
                        <div class="card-arrow">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </a>

                <a href="javascript:void(0)" onclick="showPage('page-list')" class="dashboard-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
                    <div class="card-content">
                        <div class="card-icon green">
                            <i class="fas fa-list-check"></i>
                        </div>
                        <div class="card-text">
                            <h3 class="card-title">បញ្ជីចុះឈ្មោះចូលរួមប្រជុំ</h3>
                            <p class="card-desc">ពិនិត្យមើលបញ្ជីឈ្មោះអ្នកដែលបានចុះឈ្មោះ</p>
                        </div>
                        <div class="card-arrow">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- PAGE 1: REGISTRATION FORM -->
        <div id="page-register" class="page">
            <div class="section-header text-center mb-4">
                <h2 class="fw-bold text-primary">ចុះឈ្មោះចូលរួមប្រជុំ</h2>
                <p class="text-muted">សូមបំពេញព័ត៌មានខាងក្រោម</p>
            </div>

            <div class="form-container">
                <form id="regForm">
                    <div class="mb-3">
                        <label class="form-label">អត្តលេខបុគ្គលិក</label>
                        <input type="text" name="អត្តលេខ" class="form-control" placeholder="បញ្ចូលអត្តលេខ" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ឈ្មោះពេញ</label>
                        <input type="text" name="ឈ្មោះ" class="form-control" placeholder="បញ្ចូលឈ្មោះ" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ភេទ</label>
                            <select name="ភេទ" class="form-select" required>
                                <option value="">ជ្រើសរើស</option>
                                <option value="ប្រុស">ប្រុស</option>
                                <option value="ស្រី">ស្រី</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ថ្ងៃខែឆ្នាំ</label>
                            <input type="date" name="ថ្ងៃខែឆ្នាំ" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ម៉ោងប្រជុំ</label>
                            <input type="time" name="ម៉ោងប្រជុំ" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ទីតាំង</label>
                            <select name="ទីតាំងប្រជុំ" class="form-select" required>
                                <option value="">ជ្រើសរើស</option>
                                <option value="ការិយាល័យកណ្តាល">ការិយាល័យកណ្តាល</option>
                                <option value="ឃ្លាំង CH1">ឃ្លាំង CH1</option>
                                <option value="ឃ្លាំង CKD">ឃ្លាំង CKD</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ប្រភេទនៃការប្រជុំ</label>
                        <select name="ប្រភេទនៃការប្រជុំ" class="form-select" required>
                            <option value="">ជ្រើសរើស</option>
                            <option value="ការប្រជុំប្រចាំខែ">ការប្រជុំប្រចាំខែ</option>
                            <option value="ការប្រជុំផ្នែកស្តុក CH1">ការប្រជុំផ្នែកស្តុក PSP</option>
                            <option value="ការប្រជុំផ្នែកស្តុក 318">ការប្រជុំផ្នែកស្តុក 318</option>
                            <option value="ការប្រជុំបុគ្គលិកផ្នែកបើក CH1">ការប្រជុំបុគ្គលិកផ្នែកបើក</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fas fa-paper-plane me-2"></i>ចុះឈ្មោះឥឡូវនេះ
                    </button>
                    <div id="formMsg" class="mt-3 text-center" style="display:none;"></div>
                </form>
            </div>
            
            <div class="text-center">
                <button class="btn btn-link text-decoration-none" onclick="showPage('page-main')">
                    <i class="fas fa-arrow-left me-2"></i>ត្រឡប់ទៅ Menu
                </button>
            </div>
        </div>

        <!-- PAGE 2: LIST VIEW -->
        <div id="page-list" class="page">
            <div class="section-header text-center mb-4">
                <h2 class="fw-bold text-success">បញ្ជីចុះឈ្មោះចូលរួមប្រជុំ</h2>
            </div>

            <!-- Filters -->
            <div class="card border-0 shadow-sm p-4 mb-4 rounded-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small">ស្វែងរក</label>
                        <input type="text" id="searchInput" class="form-control" placeholder="ឈ្មោះ ឬ អត្តលេខ..." oninput="applyFilters()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">ជ្រើសរើសថ្ងៃ</label>
                        <select id="dateFilter" class="form-select" onchange="applyFilters()">
                            <option value="">ទាំងអស់</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">ប្រភេទប្រជុំ</label>
                        <select id="typeFilter" class="form-select" onchange="applyFilters()">
                            <option value="">ទាំងអស់</option>
                            <option value="ការប្រជុំប្រចាំខែ">ការប្រជុំប្រចាំខែ</option>
                            <option value="ការប្រជុំផ្នែកស្តុក CH1">ការប្រជុំផ្នែកស្តុក PSP</option>
                            <option value="ការប្រជុំផ្នែកស្តុក 318">ការប្រជុំផ្នែកស្តុក 318</option>
                            <option value="ការប្រជុំបុគ្គលិកផ្នែកបើក CH1">ការប្រជុំបុគ្គលិកផ្នែកបើក</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">ស្ថានភាព</label>
                        <select id="statusFilter" class="form-select" onchange="applyFilters()">
                            <option value="">ទាំងអស់</option>
                            <option value="attended">ចូលរួម</option>
                            <option value="absent">អវត្តមាន</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 text-end">
                    <button class="btn btn-warning text-white btn-sm px-3 rounded-pill" onclick="downloadTableAsImage()">
                        <i class="fas fa-image me-2"></i>ទាញយករូបភាព
                    </button>
                </div>
            </div>

            <div class="table-container animate__animated animate__fadeIn">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="meetingTable">
                        <thead>
                            <tr>
                                <th>អត្តលេខ</th>
                                <th>ឈ្មោះ</th>
                                <th>ភេទ</th>
                                <th>ថ្ងៃ-ម៉ោង</th>
                                <th>ទីតាំង</th>
                                <th>ស្ថានភាព</th>
                            </tr>
                        </thead>
                        <tbody id="meetingBody">
                            <!-- Data will be loaded here -->
                        </tbody>
                    </table>
                </div>
                <div id="tableLoading" class="text-center p-5">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>

            <div class="text-center mt-4">
                <button class="btn btn-link text-decoration-none" onclick="showPage('page-main')">
                    <i class="fas fa-arrow-left me-2"></i>ត្រឡប់ទៅ Menu
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let allMeetingsData = [];

        function showPage(pageId) {
            $('.page').removeClass('active-page');
            $('#' + pageId).addClass('active-page');
            
            // Toggle back button in header
            if(pageId === 'page-main') {
                $('#header-back-btn').attr('href', '../homes.php').find('span').text('ត្រឡប់ក្រោយ');
            } else {
                $('#header-back-btn').attr('href', 'javascript:void(0)').attr('onclick', "showPage('page-main')").find('span').text('ទៅ Menu');
            }

            if(pageId === 'page-list') {
                fetchMeetings();
            }
        }

        async function fetchMeetings() {
            $('#tableLoading').show();
            $('#meetingTable').hide();
            try {
                const response = await fetch('../meetings/get_meetings.php');
                const result = await response.json();
                console.log("Meetings data received:", result);
                if (result.status === 'success') {
                    allMeetingsData = result.data;
                    renderTable(allMeetingsData);
                    populateDateFilter(allMeetingsData);
                } else {
                    console.error("API Error:", result.message);
                }
            } catch (error) {
                console.error('Error fetching data:', error);
            } finally {
                $('#tableLoading').hide();
                $('#meetingTable').show();
            }
        }

        function populateDateFilter(data) {
            const dateFilter = $('#dateFilter');
            const currentVal = dateFilter.val();
            dateFilter.empty().append('<option value="">ទាំងអស់</option>');
            
            // Get unique dates
            const uniqueDates = [...new Set(data.map(m => m.date))].sort().reverse();
            
            uniqueDates.forEach(date => {
                // Simple date formatting (e.g., 2024-02-06)
                dateFilter.append(`<option value="${date}">${date}</option>`);
            });
            
            if(currentVal) dateFilter.val(currentVal);
        }

        function renderTable(data) {
            const body = $('#meetingBody');
            body.empty();
            if (data.length === 0) {
                body.append('<tr><td colspan="6" class="text-center py-4">គ្មានទិន្នន័យ</td></tr>');
                return;
            }
            data.forEach(m => {
                const statusBadge = m.status === 'attended' 
                    ? '<span class="badge-attended">ចូលរួម</span>' 
                    : '<span class="badge-absent">អវត្តមាន</span>';
                
                body.append(`
                    <tr>
                        <td class="fw-bold">${m.id_number}</td>
                        <td>${m.name}</td>
                        <td>${m.gender}</td>
                        <td>
                            <div class="small fw-semibold">${m.date}</div>
                            <div class="text-muted" style="font-size:0.75rem">${m.time}</div>
                        </td>
                        <td><span class="small">${m.location}</span></td>
                        <td>${statusBadge}</td>
                    </tr>
                `);
            });
        }

        function applyFilters() {
            const search = $('#searchInput').val().toLowerCase();
            const date = $('#dateFilter').val();
            const type = $('#typeFilter').val();
            const status = $('#statusFilter').val();

            const filtered = allMeetingsData.filter(m => {
                const matchSearch = m.name.toLowerCase().includes(search) || m.id_number.toLowerCase().includes(search);
                const matchDate = date === '' || m.date === date;
                const matchType = type === '' || m.meeting_type === type;
                const matchStatus = status === '' || m.status === status;
                return matchSearch && matchDate && matchType && matchStatus;
            });
            renderTable(filtered);
        }

        function downloadTableAsImage() {
            const element = document.getElementById('meetingTable');
            html2canvas(element).then(canvas => {
                const link = document.createElement('a');
                link.download = 'meeting-list.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
            });
        }

        // Form Submission
        $('#regForm').on('submit', async function(e) {
            e.preventDefault();
            const btn = $('#submitBtn');
            const msg = $('#formMsg');
            
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>កំពុងបញ្ជូន...');
            
            const formData = new FormData(this);
            const dataObj = Object.fromEntries(formData);

            try {
                // Save to Database
                const response = await fetch('../meetings/save_meeting.php', {
                    method: 'POST',
                    body: JSON.stringify(dataObj),
                    headers: { 'Content-Type': 'application/json' }
                });
                
                const result = await response.json();
                if (result.status === 'success') {
                    msg.removeClass('text-danger').addClass('text-success').text('ការចុះឈ្មោះបានជោគជ័យ!').fadeIn();
                    this.reset();
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                msg.removeClass('text-success').addClass('text-danger').text('កំហុស: ' + error.message).fadeIn();
            } finally {
                btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>ចុះឈ្មោះឥឡូវនេះ');
                setTimeout(() => msg.fadeOut(), 3000);
            }
        });

        // App Init
        $(window).on('load', function() {
            $('#loading-overlay').fadeOut();
        });
    </script>
</body>
</html>
