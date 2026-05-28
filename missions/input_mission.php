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
    'cny'  => 'https://img.freepik.com/premium-photo/copyspace-chinese-new-year-background-with-oriental-fans-chinese-lanterns-red-gold_780838-15759.jpg',
    'wf'   => 'https://i.ibb.co/2611144/khmer-new-year-bg-1770518505378.jpg',
    'kb'   => 'https://images.unsplash.com/photo-1596701062351-be5f6a200a45?q=80&w=1600',
    'indy' => 'https://images.unsplash.com/photo-1629813289069-7c8704204d60?q=80&w=1600'
];

// Determine which image to use
$bgImage = !empty($customImage) ? $customImage : ($themeBackgrounds[$currentTheme] ?? '');

// Enable error reporting for debugging (disable in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Centralized Database Connection
require_once __DIR__ . '/../db_connection.php';

try {
    $conn = getPDO();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $base_columns = ['location', 'purpose', 'start_date', 'start_time', 'end_date', 'end_time', 'transport', 'materials', 'date_khmer'];
        $params = [];

        // Build base parameters
        foreach ($base_columns as $col) {
            if ($col === 'date_khmer') {
                $date_khmer_part1 = trim($_POST['date_khmer_part1'] ?? '');
                $date_khmer_part2 = trim($_POST['date_khmer_part2'] ?? '');
                $params[':date_khmer'] = $date_khmer_part1 . 'br' . $date_khmer_part2;
            } else {
                $params[':' . $col] = trim($_POST[$col] ?? '');
            }
        }

        // Dynamically add personnel
        $personnel_columns = [];
        // Assuming a max of 10 people for safety. Adjust if needed.
        for ($i = 1; $i <= 10; $i++) {
            if (isset($_POST["person$i"]) && !empty(trim($_POST["person$i"]))) {
                $personnel_columns[] = "person$i";
                $personnel_columns[] = "role$i";
                $params[":person$i"] = trim($_POST["person$i"]);
                $params[":role$i"] = trim($_POST["role$i"] ?? '');
            }
        }

        $all_columns = array_merge($base_columns, $personnel_columns);
        $sql_columns = implode(', ', $all_columns);
        $sql_placeholders = implode(', ', array_keys($params));

        $sql = "INSERT INTO mission_letters ($sql_columns) VALUES ($sql_placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Data inserted successfully.']);
        exit;
    }

} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database operation failed: ' . $e->getMessage()]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>បញ្ចូលលិខិតបេសកកម្ម - HR App</title>

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

        /* Animated Background Container */
        .bg-animate-container {
            position: fixed;
            top: -5%;
            left: -5%;
            width: 110%;
            height: 110%;
            z-index: -10;
            background-image: url('<?php echo $bgImage; ?>');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            animation: bgFloat 30s ease-in-out infinite alternate;
            will-change: transform;
        }
        
        @keyframes bgFloat {
            0% { transform: scale(1) translate(0, 0) rotate(0deg); }
            25% { transform: scale(1.02) translate(-1%, 0.5%) rotate(0.5deg); }
            50% { transform: scale(1.05) translate(1%, -0.5%) rotate(-0.5deg); }
            75% { transform: scale(1.03) translate(-0.5%, 1%) rotate(0.2deg); }
            100% { transform: scale(1.06) translate(0.5%, -1%) rotate(-0.2deg); }
        }

        /* Season/Festival Theme Overrides */
        <?php if ($currentTheme === 'kny'): ?>
        :root { --primary: #f59e0b; --primary-light: #fbbf24; --primary-dark: #d97706; }
        .app-header { background: rgba(250, 204, 21, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
        .app-title { background: none !important; -webkit-text-fill-color: white !important; color: white !important; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-outline-primary { color: white !important; border-color: white !important; }
        .btn-outline-primary:hover { background: white !important; color: #f59e0b !important; }
        .content-card::after { 
            content: ""; position: absolute; bottom: 10px; right: 10px; width: 50px; height: 50px;
            background-image: url('https://i.ibb.co/qFRZ8SCK/khmer-new-year.png');
            background-size: contain; background-repeat: no-repeat;
            opacity: 0.12; animation: floatUpDown 6s ease-in-out infinite;
        }
        
        <?php elseif ($currentTheme === 'pb'): ?>
        :root { --primary: #ea580c; --primary-light: #fdba74; --primary-dark: #c2410c; }
        .app-header { background: rgba(234, 88, 12, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
        .app-title { background: none !important; -webkit-text-fill-color: white !important; color: white !important; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-outline-primary { color: white !important; border-color: white !important; }
        .btn-outline-primary:hover { background: white !important; color: #ea580c !important; }
        .content-card::after { 
            content: "\f67f"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
            position: absolute; bottom: 5px; right: 5px; font-size: 40px;
            opacity: 0.1; color: #ea580c; animation: floatUpDown 6s ease-in-out infinite;
        }

        <?php elseif ($currentTheme === 'cny'): ?>
        :root { --primary: #dc2626; --primary-light: #f87171; --primary-dark: #b91c1c; }
        .app-header { background: rgba(220, 38, 38, 0.95) !important; border-color: rgba(255,255,255,0.3) !important; }
        .app-title { background: none !important; -webkit-text-fill-color: white !important; color: white !important; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn-outline-primary { color: white !important; border-color: white !important; }
        .btn-outline-primary:hover { background: white !important; color: #dc2626 !important; }
        .content-card::after { 
            content: ""; position: absolute; bottom: 10px; right: 10px; width: 50px; height: 50px;
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
        .content-card::after { 
            content: "\f773"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
            position: absolute; bottom: 5px; right: 5px; font-size: 45px;
            opacity: 0.1; color: #0284c7; animation: floatUpDown 6s ease-in-out infinite;
        }
        <?php endif; ?>

        /* Overlay to ensure readability */
        body::before {
            content: "";
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(2px);
            z-index: -2;
        }

        /* Prevent horizontal scrolling */
        html,
        body {
            overflow-x: hidden;
            width: 100%;
        }

        .app-container {
            max-width: 900px;
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

        /* === FORM CARDS === */
        .content-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            border-radius: var(--border-radius-lg);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.4);
            animation: fadeInUp 0.5s ease-out backwards;
            position: relative;
            overflow: hidden;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 2px solid var(--gray-100);
            padding-bottom: 12px;
        }

        .section-title i {
            color: var(--primary);
        }

        /* === FORM CONTROLS === */
        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--gray-700);
            margin-bottom: 6px;
            display: block;
        }

        .input-group-modern {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group-modern i {
            position: absolute;
            left: 16px;
            color: var(--gray-400);
            z-index: 10;
        }

        .form-control-modern {
            padding: 12px 16px 12px 48px;
            border-radius: 12px;
            border: 1.5px solid var(--gray-200);
            background: var(--gray-50);
            transition: all 0.3s;
            width: 100%;
            font-size: 0.95rem;
        }

        .form-control-modern:focus {
            background: white;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        .btn-modern {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-800);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
            transform: translateY(-2px);
        }

        .btn-add {
            background: var(--success);
            color: white;
            padding: 8px 16px;
            font-size: 0.85rem;
        }

        .btn-add:hover {
            filter: brightness(1.1);
        }

        .btn-remove {
            background: var(--danger);
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 32px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-remove:hover {
            transform: scale(1.1);
            filter: brightness(1.1);
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

            .content-card {
                padding: 16px;
            }

            .btn-remove {
                margin-top: 0;
                margin-bottom: 12px;
            }
        }

        /* Loading Overlay */
        #loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 3000;
            color: white;
            flex-direction: column;
            gap: 20px;
        }
    </style>
</head>

<body>

    <?php if (!empty($bgImage)): ?>
    <div class="bg-animate-container"></div>
    <?php endif; ?>

    <div class="app-container">
        <!-- Modern Header -->
        <header class="app-header animate__animated animate__fadeInDown">
            <a href="../homes.php" class="logo-container">
                <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="Logo" class="logo-img">
                <h1 class="app-title">លិខិតបេសកកម្ម</h1>
            </a>
            <a href="../missions/mission.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                <i class="fas fa-list me-1"></i> បញ្ជី
            </a>
        </header>

        <form id="mission-form" method="POST">
            <!-- Basic Info Card -->
            <div class="content-card">
                <h3 class="section-title"><i class="fas fa-info-circle"></i> ព័ត៌មានបេសកកម្ម</h3>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">ទីតាំង</label>
                        <div class="input-group-modern">
                            <i class="fas fa-map-marker-alt"></i>
                            <input type="text" name="location" class="form-control-modern" placeholder="បញ្ជាក់ទីតាំង..."
                                required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">គោលបំណង</label>
                        <div class="input-group-modern">
                            <i class="fas fa-bullseye"></i>
                            <input type="text" name="purpose" class="form-control-modern" placeholder="បញ្ជាក់គោលបំណង..."
                                required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Personnel Card -->
            <div class="content-card">
                <h3 class="section-title"><i class="fas fa-users"></i> បុគ្គលិកបេសកកម្ម</h3>

                <div id="personnel-list">
                    <!-- Default 3 persons -->
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <div class="row g-3 mb-3">
                            <div class="col-6 col-md-6">
                                <label class="form-label">គោត្តនាម-នាម (<?= $i ?>)</label>
                                <div class="input-group-modern">
                                    <i class="fas fa-user"></i>
                                    <input type="text" name="person<?= $i ?>" class="form-control-modern"
                                        placeholder="ឈ្មោះ..." <?= $i == 1 ? 'required' : '' ?>>
                                </div>
                            </div>
                            <div class="col-6 col-md-6">
                                <label class="form-label">តួនាទី (<?= $i ?>)</label>
                                <div class="input-group-modern">
                                    <i class="fas fa-briefcase"></i>
                                    <input type="text" name="role<?= $i ?>" class="form-control-modern"
                                        placeholder="តួនាទី..." <?= $i == 1 ? 'required' : '' ?>>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="text-center mt-3">
                    <button type="button" id="add-person-btn" class="btn-modern btn-add">
                        <i class="fas fa-plus"></i> បន្ថែមបុគ្គលិក
                    </button>
                </div>
            </div>

            <!-- Schedule Card -->
            <div class="content-card">
                <h3 class="section-title"><i class="fas fa-calendar-alt"></i> កាលវិភាគ</h3>
                <div class="row g-3 mb-3">
                    <div class="col-md-3 col-6">
                        <label class="form-label">ថ្ងៃចេញដំណើរ</label>
                        <div class="input-group-modern">
                            <i class="fas fa-calendar-day"></i>
                            <input type="date" name="start_date" class="form-control-modern" required>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label">ម៉ោងចេញ</label>
                        <div class="input-group-modern">
                            <i class="fas fa-clock"></i>
                            <input type="time" name="start_time" class="form-control-modern" required>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label">ថ្ងៃត្រឡប់</label>
                        <div class="input-group-modern">
                            <i class="fas fa-calendar-check"></i>
                            <input type="date" name="end_date" class="form-control-modern" required>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label">ម៉ោងត្រឡប់</label>
                        <div class="input-group-modern">
                            <i class="fas fa-clock"></i>
                            <input type="time" name="end_time" class="form-control-modern" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transport & Other Card -->
            <div class="content-card">
                <h3 class="section-title"><i class="fas fa-car-side"></i> មធ្យោបាយ និងសម្ភារៈ</h3>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">មធ្យោបាយធ្វើដំណើរ</label>
                        <div class="input-group-modern">
                            <i class="fas fa-car"></i>
                            <input type="text" name="transport" class="form-control-modern"
                                placeholder="ឧ. ឡានក្រុមហ៊ុន..." required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">សម្ភារៈយកតាមខ្លួន</label>
                        <div class="input-group-modern">
                            <i class="fas fa-box-open"></i>
                            <input type="text" name="materials" class="form-control-modern"
                                placeholder="ឧ. ឯកសារ, ប្រាក់...">
                        </div>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">កាលបរិច្ឆេទខ្មែរ (បន្ទាត់ទី១)</label>
                        <div class="input-group-modern">
                            <i class="fas fa-pen-nib"></i>
                            <input type="text" name="date_khmer_part1" class="form-control-modern"
                                placeholder="ឧ. ថ្ងៃសីល ពេញបូណ៌មី..." required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">កាលបរិច្ឆេទខ្មែរ (បន្ទាត់ទី២)</label>
                        <div class="input-group-modern">
                            <i class="fas fa-map-pin"></i>
                            <input type="text" name="date_khmer_part2" class="form-control-modern"
                                value="រាជធានីភ្នំពេញ, ថ្ងៃទី  ខែ  ឆ្នាំ២០២៥" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4 mb-5">
                <button type="submit" class="btn-modern btn-primary w-100 py-3 rounded-pill">
                    <i class="fas fa-save"></i> រក្សទុកទិន្នន័យ
                </button>
            </div>
        </form>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="../homes.php" class="nav-item">
            <i class="fas fa-home nav-icon"></i>
            <span>ទំព័រដើម</span>
        </a>
        <a href="../requests/requests_menu.php" class="nav-item">
            <i class="fas fa-clipboard-list nav-icon"></i>
            <span>ច្បាប់</span>
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

    <!-- Loading Overlay -->
    <div id="loading-overlay">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
        <p class="h5">កំពុងរក្សាទុកទិន្នន័យ...</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const addPersonBtn = document.getElementById('add-person-btn');
            const personnelList = document.getElementById('personnel-list');
            let personCount = 3;

            addPersonBtn.addEventListener('click', function () {
                if (personCount >= 10) {
                    alert('អ្នកអាចបន្ថែមបានត្រឹម ១០ នាក់ប៉ុណ្ណោះ');
                    return;
                }
                personCount++;

                const div = document.createElement('div');
                div.className = 'row g-3 mb-3 animate__animated animate__fadeIn';
                div.innerHTML = `
                    <div class="col-5 col-md-5">
                        <label class="form-label">គោត្តនាម-នាម (${personCount})</label>
                        <div class="input-group-modern">
                            <i class="fas fa-user"></i>
                            <input type="text" name="person${personCount}" class="form-control-modern" placeholder="ឈ្មោះ...">
                        </div>
                    </div>
                    <div class="col-5 col-md-5">
                        <label class="form-label">តួនាទី (${personCount})</label>
                        <div class="input-group-modern">
                            <i class="fas fa-briefcase"></i>
                            <input type="text" name="role${personCount}" class="form-control-modern" placeholder="តួនាទី...">
                        </div>
                    </div>
                    <div class="col-2 col-md-2">
                        <button type="button" class="btn-remove" onclick="this.closest('.row').remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                personnelList.appendChild(div);
            });

            const form = document.getElementById('mission-form');
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                document.getElementById('loading-overlay').style.display = 'flex';

                const formData = new FormData(form);
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            window.location.href = '../missions/mission.php';
                        } else {
                            alert(data.message);
                            document.getElementById('loading-overlay').style.display = 'none';
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        alert('មានបញ្ហាក្នុងការរក្សាទុកទិន្នន័យ');
                        document.getElementById('loading-overlay').style.display = 'none';
                    });
            });
        });
    </script>
</body>

</html>
