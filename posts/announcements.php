<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'សូមចូលគណនីសិន!';
    header("Location: ../auth/login.php");
    exit();
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}



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

// Database connection
try {
    $db = new PDO("mysql:host=localhost;dbname=samann1_admin_panel;charset=utf8mb4", 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("បរាជ័យក្នុងការតភ្ជាប់មូលដ្ឋានទិន្នន័យ: " . $e->getMessage());
}

// Fetch announcements
$current_user_id = (int) $_SESSION['user_id'];
try {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        // Admins see all announcements
        $stmt = $db->prepare("
            SELECT a.id, a.title, a.date, a.text, a.created_at
            FROM announcements a
            ORDER BY a.created_at DESC
        ");
        $stmt->execute();
    } else {
        // Non-admins see only their announcements
        $stmt = $db->prepare("
            SELECT a.id, a.title, a.date, a.text, a.created_at
            FROM announcements a
            JOIN announcement_users au ON a.id = au.announcement_id
            WHERE au.user_id = :user_id
            ORDER BY a.created_at DESC
        ");
        $stmt->execute(['user_id' => $current_user_id]);
    }
    $filtered_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("មានបញ្ហាក្នុងការទាញទិន្នន័យ: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ដំណឹង - HR App</title>

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

            /* Season/Festival Theme Overrides */
            <?php if ($currentTheme === 'kny'): ?>
            --primary: #f59e0b; --primary-light: #fbbf24; --primary-dark: #d97706; --secondary: #ec4899;
            <?php elseif ($currentTheme === 'pb'): ?>
            --primary: #ea580c; --primary-light: #fdba74; --primary-dark: #c2410c; --secondary: #4b5563;
            <?php elseif ($currentTheme === 'cny'): ?>
            --primary: #dc2626; --primary-light: #f87171; --primary-dark: #b91c1c; --secondary: #fbbf24;
            <?php elseif ($currentTheme === 'wf'): ?>
            --primary: #0284c7; --primary-light: #38bdf8; --primary-dark: #0369a1; --secondary: #0ea5e9;
            <?php elseif ($currentTheme === 'kb'): ?>
            --primary: #d97706; --primary-light: #fbbf24; --primary-dark: #b45309; --secondary: #1e3a8a;
            <?php elseif ($currentTheme === 'indy'): ?>
            --primary: #7e22ce; --primary-light: #a855f7; --primary-dark: #581c87; --secondary: #1d4ed8;
            <?php endif; ?>
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
            <?php if (!empty($bgImage)): ?>
            background-image: url('<?php echo $bgImage; ?>') !important;
            background-size: cover !important;
            background-position: center !important;
            background-attachment: fixed !important;
            background-repeat: no-repeat !important;
            <?php endif; ?>
            color: var(--gray-800);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            animation: fadeIn 0.5s ease-out;
        }

        <?php if (!empty($bgImage)): ?>
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(5px);
            z-index: -1;
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

        /* === ANNOUNCEMENT CARDS === */
        .announcements-wrapper {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .announcement-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            animation: fadeInUp 0.5s ease-out backwards;
            position: relative;
            overflow: hidden;
        }

        .announcement-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, var(--primary), var(--primary-light));
        }

        .announcement-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }

        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .announcement-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .announcement-title i {
            color: var(--primary);
            font-size: 1.1rem;
        }

        .announcement-date {
            font-size: 0.85rem;
            color: var(--gray-500);
            font-weight: 600;
            background: var(--gray-100);
            padding: 4px 12px;
            border-radius: 20px;
        }

        .announcement-body {
            font-size: 1rem;
            color: var(--gray-700);
            line-height: 1.8;
            white-space: pre-wrap;
        }

        /* === EMPTY STATE === */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: var(--border-radius-lg);
            border: 2px dashed var(--gray-200);
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
            height: 60px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            justify-content: space-around;
            align-items: center;
            padding: 0;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
            z-index: 1000;
            border-top: 1px solid rgba(0,0,0,0.05);
        }

        .nav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--gray-500);
            text-decoration: none;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.2s ease;
            border-radius: 0;
            margin: 0;
            padding: 0;
        }

        .nav-item.active {
            color: var(--primary);
            background: transparent;
            transform: none;
        }

        .nav-item.active .nav-icon {
            transform: translateY(-2px);
        }

        .nav-icon {
            font-size: 1.4rem;
            margin-bottom: 4px;
            transition: transform 0.2s ease;
        }
        
        .nav-item:active {
            background-color: rgba(0,0,0,0.02);
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
                <h1 class="app-title">ដំណឹង</h1>
            </a>
            <div class="user-profile">
                <a href="https://app.vvc.asia/admin/profile.php"
                    class="btn btn-outline-primary btn-sm rounded-pill px-3">
                    <i class="fas fa-user me-1"></i> 
                </a>
            </div>
        </header>

        <!-- Announcements List -->
        <div class="announcements-wrapper">
            <?php if (empty($filtered_announcements)): ?>
                <div class="empty-state animate__animated animate__fadeIn">
                    <i class="fas fa-bullhorn empty-icon"></i>
                    <h3 class="text-secondary">មិនទាន់មានដំណឹង</h3>
                    <p class="text-muted">សូមត្រលប់មកពិនិត្យម្តងទៀតពេលក្រោយ</p>
                </div>
            <?php else: ?>
                <?php foreach ($filtered_announcements as $index => $announcement): ?>
                    <div class="announcement-card" style="animation-delay: <?= $index * 0.1 ?>s">
                        <div class="announcement-header">
                            <h2 class="announcement-title">
                                <i class="fas fa-info-circle"></i>
                                <?php echo htmlspecialchars($announcement['title']); ?>
                            </h2>
                            <span class="announcement-date">
                                <i class="far fa-calendar-alt me-1"></i>
                                <?php echo htmlspecialchars($announcement['date']); ?>
                            </span>
                        </div>
                        <div class="announcement-body">
                            <?php echo nl2br(htmlspecialchars($announcement['text'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
        <a href="announcements.php" class="nav-item active">
            <i class="fas fa-bell nav-icon"></i>
            <span>ដំណឹង</span>
        </a>
        <a href="../admin/profile.php" class="nav-item">
            <i class="fas fa-user nav-icon"></i>
            <span>គណនី</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth reveal animation
        document.addEventListener('DOMContentLoaded', function () {
            const cards = document.querySelectorAll('.announcement-card');
            cards.forEach((card, index) => {
                card.style.opacity = '1';
            });
        });
    </script>
</body>

</html>
