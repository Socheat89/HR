<?php

session_start();

// Prevent caching - ការពារ browser cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

require_once 'system/log.php';
// Debug: Log session state
error_log("Session ID: " . session_id() . ", shown_announcement_ids: " . (isset($_SESSION['shown_announcement_ids']) ? json_encode($_SESSION['shown_announcement_ids']) : '[]'));

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'សូមចូលគណនីសិន!';
    header("Location: auth/login.php");
    exit();
}

if (isset($_GET['logout'])) {
    // Clear session data
    $_SESSION = array();

    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    // Clear cache and redirect
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Location: auth/login.php?t=" . time());
    exit();
}

// Initialize shown announcement IDs array if not set
if (!isset($_SESSION['shown_announcement_ids'])) {
    $_SESSION['shown_announcement_ids'] = [];
}

// Handle popup_shown query parameter
if (isset($_GET['popup_shown']) && $_GET['popup_shown'] == '1' && !empty($_GET['announcement_ids'])) {
    // Add announcement IDs to session array
    $ids = explode(',', $_GET['announcement_ids']);
    $ids = array_map('intval', $ids); // Sanitize IDs
    $_SESSION['shown_announcement_ids'] = array_unique(array_merge($_SESSION['shown_announcement_ids'], $ids));
    error_log("Updated shown_announcement_ids: " . json_encode($_SESSION['shown_announcement_ids']));
    // Redirect to clean URL
    header("Location: homes.php");
    exit();
}

// Centralized Database Connection
require_once __DIR__ . '/db_connection.php';

// Get PDO Connection
try {
    $db = getPDO();
} catch (Exception $e) {
    $_SESSION['error'] = 'មានបញ្ហាក្នុងការតភ្ជាប់ទិន្នន័យ។ សូមព្យាយាមម្តងទៀត។';
    error_log("DB Connection Error: " . $e->getMessage());
    header("Location: error.php");
    exit();
}

// Fetch new announcements not yet shown
$current_user_id = (int) $_SESSION['user_id'];
$new_announcements = [];
try {
    // Assume last_login is stored in session or default to last 24 hours
    $last_login = isset($_SESSION['last_login']) ? $_SESSION['last_login'] : date('Y-m-d H:i:s', strtotime('-24 hours'));

    // Prepare query to exclude shown announcements
    $shown_ids = !empty($_SESSION['shown_announcement_ids']) ? implode(',', array_map('intval', $_SESSION['shown_announcement_ids'])) : '0';

    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $stmt = $db->prepare("
            SELECT a.id, a.title, a.date, a.text, a.created_at
            FROM announcements a
            WHERE a.created_at > :last_login AND a.id NOT IN ($shown_ids)
            ORDER BY a.created_at DESC
            LIMIT 3
        ");
        $stmt->execute(['last_login' => $last_login]);
    } else {
        $stmt = $db->prepare("
            SELECT a.id, a.title, a.date, a.text, a.created_at
            FROM announcements a
            JOIN announcement_users au ON a.id = au.announcement_id
            WHERE au.user_id = :user_id AND a.created_at > :last_login AND a.id NOT IN ($shown_ids)
            ORDER BY a.created_at DESC
            LIMIT 3
        ");
        $stmt->execute(['user_id' => $current_user_id, 'last_login' => $last_login]);
    }
    $new_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Query Error: " . $e->getMessage());
    header("Location: error.php");
    exit();
}

// Fetch User Info (Image, Full Name, Position)
$userImage = '';
$userFullName = $_SESSION['username'];
$userPosition = $_SESSION['role'];

try {
    $userStmt = $db->prepare("SELECT image_url, full_name, position FROM users WHERE id = ?");
    $userStmt->execute([$current_user_id]);
    $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($userInfo) {
        $userImage = $userInfo['image_url'];
        // Adjust image path for root directory if it's relative
        if (!empty($userImage) && !filter_var($userImage, FILTER_VALIDATE_URL)) {
             $userImage = 'admin/' . $userImage;
        }
        
        if (!empty($userInfo['full_name'])) $userFullName = $userInfo['full_name'];
        if (!empty($userInfo['position'])) $userPosition = $userInfo['position'];
    }
} catch (PDOException $e) {
    // create log if needed, otherwise fallback to session defaults
}

// Fetch stats for dashboard - OPTIMIZED: Single query instead of multiple
// ទាញយកស្ថិតិសម្រាប់ dashboard - ធ្វើឱ្យប្រសើរ: query តែមួយជំនួសឱ្យច្រើន
$username = $_SESSION['username'];
$today_work = 0;
$requests_count = 0;
$announcements_count = 0;
$annual_leave_remaining = 9;

try {
    // Combined query for all stats in one database call (faster!)
    // រួមបញ្ចូល query សម្រាប់ស្ថិតិទាំងអស់ក្នុងការហៅមូលដ្ឋានទិន្នន័យតែមួយ (លឿនជាង!)
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $statsQuery = "
            SELECT 
                (SELECT COUNT(*) FROM work_checklist WHERE user_id = :user_id1 AND due_date = CURDATE()) as today_work,
                (SELECT COUNT(*) FROM requests WHERE user_id = :user_id2) as requests_count,
                (SELECT COUNT(*) FROM announcements) as announcements_count,
                (SELECT remaining_days FROM requests 
                 WHERE user_id = :user_id3 
                 AND (request_type LIKE '%សម្រាកប្រចាំឆ្នាំ%' OR request_type LIKE '%annual%') 
                 AND status = 'approved' 
                 ORDER BY request_date DESC LIMIT 1) as annual_leave
        ";
    } else {
        $statsQuery = "
            SELECT 
                (SELECT COUNT(*) FROM work_checklist WHERE user_id = :user_id1 AND due_date = CURDATE()) as today_work,
                (SELECT COUNT(*) FROM requests WHERE user_id = :user_id2) as requests_count,
                (SELECT COUNT(*) FROM announcements a 
                 JOIN announcement_users au ON a.id = au.announcement_id 
                 WHERE au.user_id = :user_id4) as announcements_count,
                (SELECT remaining_days FROM requests 
                 WHERE user_id = :user_id3 
                 AND (request_type LIKE '%សម្រាកប្រចាំឆ្នាំ%' OR request_type LIKE '%annual%') 
                 AND status = 'approved' 
                 ORDER BY request_date DESC LIMIT 1) as annual_leave
        ";
    }

    $stmt = $db->prepare($statsQuery);

    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        $stmt->execute([
            'user_id1' => $current_user_id,
            'user_id2' => $current_user_id,
            'user_id3' => $current_user_id
        ]);
    } else {
        $stmt->execute([
            'user_id1' => $current_user_id,
            'user_id2' => $current_user_id,
            'user_id3' => $current_user_id,
            'user_id4' => $current_user_id
        ]);
    }

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $today_work = (int) ($stats['today_work'] ?? 0);
    $requests_count = (int) ($stats['requests_count'] ?? 0);
    $announcements_count = (int) ($stats['announcements_count'] ?? 0);
    $annual_leave_remaining = $stats['annual_leave'] !== null ? (int) $stats['annual_leave'] : 9;

} catch (PDOException $e) {
    error_log("Dashboard stats query error: " . $e->getMessage());
    // Use default values on error
}

// Determine current page for active navigation
$current_page = basename($_SERVER['PHP_SELF']);

// Load Theme Config
$themeConfigPath = __DIR__ . '/admin/includes/theme_config.json';
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
?>

<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#4f46e5">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
    <title>HRM Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">

    <link rel="manifest" href="manifest.json">
    <link rel="preload" href="assets/sound/chinese effect.mp3" as="audio">
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

        /* Season/Festival Theme Overrides (Highest Priority) */
        <?php if ($currentTheme === 'kny'): ?>
        :root { --primary: #f59e0b; --primary-light: #fbbf24; --primary-dark: #d97706; --secondary: #ec4899; }
        .welcome-section { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%) !important; position: relative; }
        .welcome-section::after { 
            content: ""; 
            position: absolute; right: 20px; bottom: -20px; 
            width: 250px; height: 250px;
            background-image: url('https://i.ibb.co/qFRZ8SCK/khmer-new-year.png');
            background-size: contain; background-repeat: no-repeat;
            opacity: 0.4; 
            z-index: 0; 
            filter: drop-shadow(0 10px 15px rgba(0,0,0,0.1));
            animation: floatUpDown 6s ease-in-out infinite;
        }
        /* Add traditional Khmer pattern as background overlay */
        .welcome-section::before { 
            content: ""; position: absolute; inset: 0; opacity: 0.1; 
            background-image: url('https://www.transparenttextures.com/patterns/natural-paper.png'); 
            mask-image: linear-gradient(to bottom, black, transparent);
        }
        .action-card { border-left: 4px solid var(--primary) !important; background: rgba(255, 255, 255, 0.85) !important; }
        .action-card:hover { transform: translateY(-5px) scale(1.02); box-shadow: 0 15px 30px rgba(245, 158, 11, 0.2); border-color: var(--primary) !important; }
        
        /* Fireworks Overlay for KNY */
        body::after {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('https://media.tenor.com/XesYJjyNYgAAAAAi/fireworks-putukan.gif');
            background-size: cover;
            background-repeat: no-repeat;
            pointer-events: none;
            z-index: -1;
            opacity: 0.35;
            mix-blend-mode: screen;
        }
        
        <?php elseif ($currentTheme === 'pb'): ?>
        :root { --primary: #ea580c; --primary-light: #fdba74; --primary-dark: #c2410c; --secondary: #4b5563; }
        .welcome-section { background: linear-gradient(135deg, #ea580c 0%, #c2410c 100%) !important; }
        .welcome-section::after { 
            content: "\f67f"; 
            font-family: "Font Awesome 6 Free"; 
            font-weight: 900; 
            position: absolute; right: 30px; bottom: -10px; 
            font-size: 180px; opacity: 0.25; color: white; 
            filter: drop-shadow(0 10px 15px rgba(0,0,0,0.1));
            animation: floatUpDown 6s ease-in-out infinite;
        }
        .action-card { border-left: 4px solid var(--primary) !important; background: rgba(255, 255, 255, 0.88) !important; }
        .action-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(234, 88, 12, 0.2); }

        <?php elseif ($currentTheme === 'cny'): ?>
        :root { --primary: #dc2626; --primary-light: #f87171; --primary-dark: #b91c1c; --secondary: #fbbf24; }
        .welcome-section { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%) !important; }
        .welcome-section::after { 
            content: ""; 
            position: absolute; right: 20px; bottom: -10px; 
            width: 280px; height: 280px;
            background-image: url('https://i.ibb.co/G4K8Mv36/chinese-new-year.png');
            background-size: contain; background-repeat: no-repeat;
            opacity: 0.3; 
            filter: drop-shadow(0 10px 15px rgba(0,0,0,0.2));
            animation: floatUpDown 6s ease-in-out infinite;
        }
        .action-card { border-left: 4px solid var(--primary) !important; }
        .action-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(220, 38, 38, 0.25); border-color: var(--secondary) !important; }

        <?php elseif ($currentTheme === 'wf'): ?>
        :root { --primary: #0284c7; --primary-light: #38bdf8; --primary-dark: #0369a1; --secondary: #0ea5e9; }
        .welcome-section { background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%) !important; }
        .welcome-section::after { 
            content: "\f773"; 
            font-family: "Font Awesome 6 Free"; 
            font-weight: 900; 
            position: absolute; right: 10px; bottom: -20px; 
            font-size: 220px; opacity: 0.3; color: white; 
            filter: drop-shadow(0 10px 15px rgba(0,0,0,0.1));
            animation: floatUpDown 6s ease-in-out infinite;
        }
        .action-card { border-left: 4px solid var(--primary) !important; }
        .action-card:hover { transform: translateY(-5px); box-shadow: 0 15px 30px rgba(2, 132, 199, 0.2); }

        <?php elseif ($currentTheme === 'kb'): ?>
        :root { --primary: #d97706; --primary-light: #fbbf24; --primary-dark: #b45309; --secondary: #1e3a8a; }
        .welcome-section { background: linear-gradient(135deg, #d97706 0%, #b45309 100%) !important; }
        .welcome-section::after { content: "\f521"; font-family: "Font Awesome 6 Free"; font-weight: 900; position: absolute; right: 30px; bottom: -10px; font-size: 180px; opacity: 0.15; color: white; }
        .action-card { border-left: 4px solid var(--primary) !important; border-top: 1px solid rgba(217, 119, 6, 0.1) !important; }

        <?php elseif ($currentTheme === 'indy'): ?>
        :root { --primary: #7e22ce; --primary-light: #a855f7; --primary-dark: #581c87; --secondary: #1d4ed8; }
        .welcome-section { background: linear-gradient(135deg, #7e22ce 0%, #581c87 100%) !important; }
        .welcome-section::after { content: "\f3ff"; font-family: "Font Awesome 6 Free"; font-weight: 900; position: absolute; right: 40px; bottom: 0px; font-size: 170px; opacity: 0.12; color: white; }
        .action-card { border-left: 4px solid var(--primary) !important; }
        <?php endif; ?>

        /* Apply Theme Background Image */
        <?php if (!empty($bgImage)): ?>
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
            will-change: transform; /* Performance optimization */
        }
        
        /* Floating/Shaking Animation */
        @keyframes bgFloat {
            0% { transform: scale(1) translate(0, 0) rotate(0deg); }
            25% { transform: scale(1.02) translate(-1%, 0.5%) rotate(0.5deg); }
            50% { transform: scale(1.05) translate(1%, -0.5%) rotate(-0.5deg); }
            75% { transform: scale(1.03) translate(-0.5%, 1%) rotate(0.2deg); }
            100% { transform: scale(1.06) translate(0.5%, -1%) rotate(-0.2deg); }
        }

        body {
            /* Background moved to .bg-animate-container */
            font-family: 'Kantumruy Pro', sans-serif;
            background-color: transparent; /* Changed from #f3f4f6 */
            margin: 0;
            min-height: 100vh;
        }

        /* Floating Animation for Theme Icons */
        @keyframes floatUpDown {
            0% { transform: translateY(0) rotate(-15deg); }
            50% { transform: translateY(-15px) rotate(-10deg); }
            100% { transform: translateY(0) rotate(-15deg); }
        }

        @keyframes floatUpDownSimplified {
            0% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0); }
        }

        /* Wiggle/Shake Animation for Hover */
        @keyframes iconWiggle {
            0% { transform: scale(1.2) rotate(0deg); }
            25% { transform: scale(1.2) rotate(8deg); }
            50% { transform: scale(1.2) rotate(-8deg); }
            75% { transform: scale(1.2) rotate(4deg); }
            100% { transform: scale(1.2) rotate(0deg); }
        }

        /* Overlay to ensure readability */
        body::before {
            content: "";
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(2px);
            z-index: -2;
        }
        <?php endif; ?>

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

        /* Loading Animation */
        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .app-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px;
            box-sizing: border-box;
        }

        /* === HEADER STYLES === */
        .app-header {
            background: rgba(255, 255, 255, <?php echo !empty($bgImage) ? '0.7' : '0.95'; ?>);
            backdrop-filter: blur(25px);
            border-radius: var(--border-radius-xl);
            padding: 16px 24px;
            margin-bottom: 32px;
            box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.5);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
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
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .user-name {
            font-weight: 600;
            color: var(--gray-900);
            font-size: 1.1rem;
        }

        .user-role {
            font-size: 0.85rem;
            color: var(--gray-500);
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
        }

        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-lg);
        }

        .logout-btn {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: var(--border-radius);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .logout-btn i {
            font-size: 0.8rem;
        }

        .logout-text {
            display: inline;
        }

        /* === WELCOME SECTION === */
        .welcome-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: var(--border-radius-xl);
            padding: 40px;
            margin-bottom: 40px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-xl);
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translate(-50%, -50%) rotate(0deg);
            }

            50% {
                transform: translate(-50%, -50%) rotate(180deg);
            }
        }

        .welcome-content {
            position: relative;
            z-index: 1;
        }

        .welcome-greeting {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 8px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .welcome-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 24px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-lg);
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            transition: all 0.6s ease;
        }

        .stat-card:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-3px);
        }

        .stat-card:hover::after {
            left: 100%;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* === QUICK ACTIONS === */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
        }

        .section-subtitle {
            color: var(--gray-500);
            font-size: 1rem;
            margin: 0;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 48px;
        }

        .action-card {
            background: rgba(255, 255, 255, 0.82);
            backdrop-filter: blur(12px);
            border-radius: var(--border-radius-lg);
            padding: 24px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.4);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* Decorative Background Pattern for Theme Integration */
        .action-card::after {
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
        /* Set theme-specific background icons for cards */
        <?php if ($currentTheme === 'kny'): ?> 
            .action-card::after { 
                content: ""; 
                background-image: url('https://i.ibb.co/qFRZ8SCK/khmer-new-year.png');
                background-size: contain; background-repeat: no-repeat;
                width: 100px; height: 100px; bottom: -10px; right: -10px; 
                opacity: 0.18;
                filter: drop-shadow(0 5px 8px rgba(0,0,0,0.1));
                animation: floatUpDown 5s ease-in-out infinite;
            } 
        <?php elseif ($currentTheme === 'pb'): ?> 
            .action-card::after { 
                content: "\f67f"; 
                opacity: 0.15;
                filter: drop-shadow(0 5px 8px rgba(0,0,0,0.1));
                animation: floatUpDown 5s ease-in-out infinite;
            }
        <?php elseif ($currentTheme === 'cny'): ?> 
            .action-card::after { 
                content: ""; 
                background-image: url('https://i.ibb.co/G4K8Mv36/chinese-new-year.png');
                background-size: contain; background-repeat: no-repeat;
                width: 100px; height: 100px; bottom: -10px; right: -10px;
                opacity: 0.15;
                filter: drop-shadow(0 5px 8px rgba(0,0,0,0.1));
                animation: floatUpDown 5s ease-in-out infinite;
            }
        <?php elseif ($currentTheme === 'wf'): ?> 
            .action-card::after { 
                content: "\f773"; 
                animation: floatUpDown 5s ease-in-out infinite;
            }
        <?php elseif ($currentTheme === 'kb'): ?> 
            .action-card::after { 
                content: "\f521"; 
                animation: floatUpDown 5s ease-in-out infinite;
            }
        <?php elseif ($currentTheme === 'indy'): ?> 
            .action-card::after { 
                content: "\f3ff"; 
                animation: floatUpDown 5s ease-in-out infinite;
            }
        <?php endif; ?>

        .action-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.95);
            border-color: var(--primary);
        }

        .action-card:hover::after {
            opacity: 0.18;
            bottom: -10px;
            right: -10px;
            animation: iconWiggle 0.5s ease-in-out infinite;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            transform: scaleX(0);
            transition: transform 0.3s ease;
            z-index: 2;
        }

        .action-card:hover::before {
            transform: scaleX(1);
        }

        .action-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--border-radius);
            background: var(--gray-100);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }

        .action-card:hover .action-icon {
            background: var(--primary);
            color: white;
            transform: rotate(10deg);
        }

        .action-content {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .action-icon {
            width: 64px;
            height: 64px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            box-shadow: var(--shadow-md);
            flex-shrink: 0;
        }

        .action-text {
            flex: 1;
        }

        .action-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 4px;
            color: var(--gray-900);
        }

        .action-desc {
            font-size: 0.9rem;
            color: var(--gray-500);
            margin: 0;
        }

        /* === DASHBOARD GRID === */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 40px;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }

        .dashboard-card {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: var(--border-radius-lg);
            padding: 32px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.18);
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
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            opacity: 0.8;
            z-index: 2;
        }

        /* Removed simple overlay ::after as it's replaced by theme decorative icon */

        .dashboard-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.35);
        }

        .dashboard-card:hover::before {
            opacity: 1;
        }

        .card-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .card-icon {
            font-size: 3rem;
            margin-bottom: 16px;
            color: var(--primary);
            opacity: 0.9;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--gray-900);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .card-desc {
            font-size: 0.95rem;
            color: var(--gray-600);
            line-height: 1.4;
        }

        /* Decorative Background Pattern for Theme Integration in Dashboard Cards */
        .dashboard-card::after {
            content: '';
            position: absolute;
            bottom: -25px;
            right: -25px;
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            font-size: 100px;
            opacity: 0.03;
            color: var(--primary);
            transition: all 0.5s ease;
            transform: rotate(-15deg);
            z-index: 0;
            pointer-events: none;
        }

        /* Set theme-specific background icons for dashboard cards */
        <?php if ($currentTheme === 'kny'): ?> 
            .dashboard-card::after { 
                content: ""; 
                background-image: url('https://i.ibb.co/qFRZ8SCK/khmer-new-year.png');
                background-size: contain; background-repeat: no-repeat;
                width: 120px; height: 120px; bottom: -15px; right: -15px; 
                opacity: 0.15;
                filter: drop-shadow(0 5px 10px rgba(0,0,0,0.15));
                animation: floatUpDown 7s ease-in-out infinite;
            } 
        <?php elseif ($currentTheme === 'pb'): ?> 
            .dashboard-card::after { 
                content: "\f67f"; 
                opacity: 0.12;
                filter: drop-shadow(0 5px 10px rgba(0,0,0,0.1));
                animation: floatUpDown 7s ease-in-out infinite;
            }
        <?php elseif ($currentTheme === 'cny'): ?> 
            .dashboard-card::after { 
                content: ""; 
                background-image: url('https://i.ibb.co/G4K8Mv36/chinese-new-year.png');
                background-size: contain; background-repeat: no-repeat;
                width: 120px; height: 120px; bottom: -15px; right: -15px;
                opacity: 0.12;
                filter: drop-shadow(0 5px 10px rgba(0,0,0,0.1));
                animation: floatUpDown 7s ease-in-out infinite;
            }
        <?php elseif ($currentTheme === 'wf'): ?> 
            .dashboard-card::after { 
                content: "\f773"; 
                animation: floatUpDown 7s ease-in-out infinite;
            }
        <?php elseif ($currentTheme === 'kb'): ?> 
            .dashboard-card::after { 
                content: "\f521"; 
                animation: floatUpDown 7s ease-in-out infinite;
            }
        <?php elseif ($currentTheme === 'indy'): ?> 
            .dashboard-card::after { 
                content: "\f3ff"; 
                animation: floatUpDown 7s ease-in-out infinite;
            }
        .dashboard-card:hover::after {
            opacity: 0.18;
            bottom: -10px;
            right: -10px;
            animation: iconWiggle 0.5s ease-in-out infinite;
        }
<?php endif; ?>


        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
            padding: 15px 20px;
        }

        .modal-title {
            font-size: 1.4rem;
            font-weight: 600;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            border-top: none;
            padding: 15px 20px;
        }

        .btn-close {
            background-color: rgba(255, 255, 255, 0.2);
            opacity: 1;
        }

        .btn-close:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }

        .announcement-item {
            padding: 15px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .announcement-item:hover {
            background: rgba(79, 70, 229, 0.05);
        }

        .announcement-item:last-child {
            border-bottom: none;
        }

        .announcement-title {
            font-weight: 600;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .announcement-title i {
            color: var(--primary);
            font-size: 0.9rem;
        }

        .announcement-date {
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 8px;
        }

        .announcement-text {
            font-size: 0.95rem;
            line-height: 1.6;
        }

        .bottom-nav {
            display: none;
            /* Hide by default */
        }

        a {
            text-decoration: none;
        }

        /* === MOBILE RESPONSIVE === */
        /* === TABLET STYLES (769px - 1024px) === */
        @media (max-width: 1024px) and (min-width: 769px) {
            .dashboard-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
                margin-bottom: 36px;
            }

            .dashboard-card {
                padding: 28px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-bottom: 120px;
                font-size: 14px;
                overflow-x: hidden;
            }

            .app-container {
                padding: 12px;
                max-width: 100%;
                margin: 0;
                box-sizing: border-box;
            }

            .app-header {
                padding: 12px 16px;
                margin-bottom: 20px;
                border-radius: var(--border-radius-lg);
                flex-wrap: wrap;
                gap: 12px;
            }

            .logo-container {
                flex: 1;
                min-width: 0;
            }

            .app-title {
                font-size: 1.3rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .user-profile {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-shrink: 0;
            }

            .user-info {
                display: none;
                /* Hide on mobile for space */
            }

            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }

            .logout-btn {
                padding: 6px 12px;
                font-size: 0.8rem;
                gap: 4px;
            }

            .logout-btn .logout-text {
                display: none;
            }

            .welcome-section {
                padding: 20px 16px;
                margin-bottom: 24px;
                border-radius: var(--border-radius-lg);
            }

            .welcome-greeting {
                font-size: 1.5rem;
                line-height: 1.3;
                margin-bottom: 8px;
            }

            .welcome-subtitle {
                font-size: 0.9rem;
                line-height: 1.4;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
                margin-top: 20px;
            }

            .stat-card {
                padding: 16px;
                border-radius: var(--border-radius);
                min-height: 80px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                align-items: center;
            }

            .stat-value {
                font-size: 1.8rem;
                margin-bottom: 4px;
            }

            .stat-label {
                font-size: 0.8rem;
                text-align: center;
            }

            .section-header {
                margin-bottom: 16px;
            }

            .section-title {
                font-size: 1.3rem;
                margin-bottom: 4px;
            }

            .section-subtitle {
                font-size: 0.85rem;
            }

            .quick-actions {
                grid-template-columns: 1fr;
                gap: 12px;
                margin-bottom: 24px;
            }

            .action-card {
                padding: 16px;
                min-height: 80px;
                border-radius: var(--border-radius-lg);
            }

            .action-content {
                flex-direction: row;
                align-items: center;
                gap: 12px;
            }

            .action-icon {
                width: 48px;
                height: 48px;
                font-size: 1.3rem;
                flex-shrink: 0;
            }

            .action-text {
                flex: 1;
                min-width: 0;
            }

            .action-title {
                font-size: 1rem;
                margin-bottom: 2px;
                line-height: 1.3;
            }

            .action-desc {
                font-size: 0.8rem;
                line-height: 1.3;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
                margin-bottom: 32px;
            }

            .dashboard-card {
                padding: 20px;
                border-radius: var(--border-radius-lg);
                min-height: 100px;
                background: rgba(255, 255, 255, 0.3);
                backdrop-filter: blur(15px);
                -webkit-backdrop-filter: blur(15px);
                box-shadow: 0 6px 24px rgba(0, 0, 0, 0.08);
                border: 1px solid rgba(255, 255, 255, 0.2);
            }

            .card-content {
                text-align: center;
            }

            .card-icon {
                font-size: 2rem;
                margin-bottom: 8px;
            }

            .card-title {
                font-size: 1rem;
                margin-bottom: 4px;
                line-height: 1.3;
            }

            .card-desc {
                font-size: 0.8rem;
                line-height: 1.3;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .bottom-nav {
                display: flex;
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

            /* Modal improvements for mobile */
            .modal-dialog {
                margin: 10px;
                max-width: none;
            }

            .modal-content {
                border-radius: var(--border-radius-lg);
            }

            .announcement-item {
                padding: 16px 0;
            }

            .announcement-title {
                font-size: 1rem;
                margin-bottom: 6px;
            }

            .announcement-date {
                font-size: 0.8rem;
                margin-bottom: 8px;
            }

            .announcement-text {
                font-size: 0.9rem;
                line-height: 1.5;
            }

            /* Touch feedback for mobile */
            .action-card:active,
            .dashboard-card:active,
            .nav-item:active {
                transform: scale(0.98);
                transition: transform 0.1s ease;
            }

            .user-avatar:active {
                transform: scale(0.95);
            }
        }

        /* === EXTRA SMALL PHONES (320px - 480px) === */
        @media (max-width: 480px) {
            body {
                font-size: 13px;
                overflow-x: hidden;
            }

            .app-container {
                padding: 8px;
                max-width: 100%;
                margin: 0;
                box-sizing: border-box;
            }

            .app-header {
                padding: 10px 12px;
                margin-bottom: 16px;
            }

            .logo-img {
                width: 40px;
                height: 40px;
            }

            .app-title {
                font-size: 1.2rem;
            }

            .user-avatar {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }

            .logout-btn {
                padding: 5px 10px;
                font-size: 0.75rem;
                gap: 3px;
            }

            .logout-btn .logout-text {
                display: none;
            }

            .welcome-section {
                padding: 16px 12px;
                margin-bottom: 20px;
            }

            .welcome-greeting {
                font-size: 1.3rem;
            }

            .welcome-subtitle {
                font-size: 0.85rem;
            }

            .stats-grid {
                gap: 10px;
            }

            .stat-card {
                padding: 12px;
                min-height: 70px;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            .stat-label {
                font-size: 0.75rem;
            }

            .section-title {
                font-size: 1.2rem;
            }

            .section-subtitle {
                font-size: 0.8rem;
            }

            .quick-actions {
                gap: 10px;
            }

            .action-card {
                padding: 14px;
                min-height: 70px;
            }

            .action-icon {
                width: 44px;
                height: 44px;
                font-size: 1.2rem;
            }

            .action-title {
                font-size: 0.95rem;
            }

            .action-desc {
                font-size: 0.75rem;
            }

            .dashboard-grid {
                gap: 10px;
            }

            .dashboard-card {
                padding: 16px;
                min-height: 90px;
                background: rgba(255, 255, 255, 0.35);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
                border: 1px solid rgba(255, 255, 255, 0.25);
            }

            .card-icon {
                font-size: 1.8rem;
                margin-bottom: 6px;
            }

            .card-title {
                font-size: 0.95rem;
            }

            .card-desc {
                font-size: 0.75rem;
            }

            .bottom-nav {
                padding: 0;
                height: 60px;
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(10px);
                border-top: 1px solid rgba(0,0,0,0.05);
                display: flex;
                justify-content: space-around;
                align-items: center;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
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

            .nav-icon {
                font-size: 1.4rem;
                margin-bottom: 4px;
                transition: transform 0.2s ease;
            }

            .nav-item.active {
                color: var(--primary);
                background: transparent;
                transform: none;
            }

            .nav-item.active .nav-icon {
                transform: translateY(-2px);
            }
            
            .nav-item:active {
                background-color: rgba(0,0,0,0.02);
            }

            .modal-dialog {
                margin: 5px;
            }

            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 12px 16px;
            }

            /* Touch feedback for small phones */
            .action-card:active,
            .dashboard-card:active,
            .nav-item:active {
                transform: scale(0.96);
                transition: transform 0.1s ease;
            }

            .user-avatar:active {
                transform: scale(0.93);
            }
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

        /* === ACCESSIBILITY & FOCUS STATES === */
        .action-card:focus,
        .dashboard-card:focus,
        .nav-item:focus,
        .user-avatar:focus,
        .logout-btn:focus {
            outline: 2px solid var(--primary);
            outline-offset: 2px;
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {

            .action-card,
            .dashboard-card {
                border: 2px solid var(--gray-300);
            }

            .welcome-section {
                background: var(--primary);
                color: white;
            }

            .stat-card {
                background: white;
                border: 2px solid var(--gray-300);
            }
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {

            .action-card,
            .dashboard-card,
            .user-avatar,
            .logout-btn,
            .nav-item {
                transition: none;
            }

            .animate-fade-in-up {
                animation: none;
            }

            .welcome-section::before {
                animation: none;
            }
        }
    </style>
    <script>
        // FASTEST AUDIO PLAYBACK
        (function() {
            // Create audio object immediately
            var audio = new Audio('assets/sound/chinese effect.mp3');
            audio.volume = 0.5;
            audio.preload = 'auto';
            audio.load(); // Force load

            // Attempt play as soon as possible
            var playPromise = audio.play();

            if (playPromise !== undefined) {
                playPromise.catch(function(error) {
                    // console.log('Autoplay prevented. Waiting for user interaction.');
                    // Add listeners to document to catch ANY early interaction
                    var interactHandler = function() {
                        audio.play();
                        document.removeEventListener('click', interactHandler);
                        document.removeEventListener('touchstart', interactHandler);
                        document.removeEventListener('keydown', interactHandler);
                    };
                    document.addEventListener('click', interactHandler);
                    document.addEventListener('touchstart', interactHandler);
                    document.addEventListener('keydown', interactHandler);
                });
            }
        })();
    </script>
</head>

<body>
    <?php if (!empty($bgImage)): ?>
    <div class="bg-animate-container"></div>
    <?php endif; ?>
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
        <div style="
            text-align: center;
            color: var(--gray-800);
        ">
            <div style="
                width: 60px;
                height: 60px;
                border: 4px solid var(--gray-300);
                border-top: 4px solid var(--primary);
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto 20px;
            "></div>
            <h3 style="
                font-family: 'Kantumruy Pro', sans-serif;
                font-size: 1.5rem;
                font-weight: 600;
                margin: 0 0 10px 0;
            ">កំពុងផ្ទុក...</h3>
            <p style="
                font-family: 'Kantumruy Pro', sans-serif;
                font-size: 1rem;
                opacity: 0.8;
                margin: 0;
            ">សូមរង់ចាំបន្តិច</p>
        </div>
    </div>

    <div class="app-container">
        <!-- Modern App Header -->
        <header class="app-header animate__animated animate__fadeIn">
            <a href="homes.php" class="header-logo-link">
                <div class="logo-container">
                    <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="HRM Logo" class="logo-img">
                    <h1 class="app-title">HR App</h1>
                </div>
            </a>
            <div class="user-profile">
                <div class="user-info">
                    <span class="user-name">
                        <?php echo htmlspecialchars($userFullName); ?>
                    </span>
                    <span class="user-role">
                        <?php echo htmlspecialchars($userPosition); ?>
                    </span>
                </div>
                <a href="admin/profile.php" class="user-avatar" title="Profile">
                    <?php if (!empty($userImage)): ?>
                        <img src="<?php echo htmlspecialchars($userImage); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </a>
                <button onclick="window.location.href='?logout=true'" class="logout-btn">
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                    <span class="logout-text">ចាកចេញ</span>
                </button>
            </div>
        </header>

        <!-- Welcome Section -->
        <section class="welcome-section animate-fade-in-up">
            <div class="welcome-content">
                <h2 class="welcome-greeting">
                    សូមស្វាគមន៍ <?php echo htmlspecialchars($_SESSION['username'] ?? 'អ្នកចូល'); ?>!
                </h2>
                <p class="welcome-subtitle">
                    សូមធ្វើការងាររបស់អ្នកដោយរីករាយនៅថ្ងៃនេះ
                </p>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php echo $today_work; ?>
                        </div>
                        <div class="stat-label">ការងារថ្ងៃនេះ</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php echo $requests_count; ?>
                        </div>
                        <div class="stat-label">សំណើរសុំ</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php echo $announcements_count; ?>
                        </div>
                        <div class="stat-label">ការជូនដំណឹង</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php echo $annual_leave_remaining; ?>
                        </div>
                        <div class="stat-label">សម្រាកប្រចាំឆ្នាំ</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Actions Section -->
        <div class="section-header">
            <h2 class="section-title">សកម្មភាពរហ័ស</h2>
            <p class="section-subtitle">សកម្មភាពធម្មតាដែលអ្នកប្រើប្រាស់ច្រើន</p>
        </div>
        <div class="quick-actions">
            <a href="admin/submit_request.php" class="action-card animate-fade-in-up"
                style="animation-delay: 0.1s">
                <div class="action-content">
                    <div class="action-icon">
                        <i class="fas fa-plus"></i>
                    </div>
                    <div class="action-text">
                        <h3 class="action-title">ស្នើសុំថ្មី</h3>
                        <p class="action-desc">បង្កើតសំណើរសុំថ្មី</p>
                    </div>
                </div>
            </a>
            <a href="requests/requests_menu.php" class="action-card animate-fade-in-up"
                style="animation-delay: 0.2s">
                <div class="action-content">
                    <div class="action-icon" style="background: linear-gradient(135deg, var(--success), #34d399);">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="action-text">
                        <h3 class="action-title">មើលការស្នើរសុំផ្សេងៗ</h3>
                        <p class="action-desc">មើលរបាយការណ៍ស្នើរសុំផ្សេងៗរបស់ខ្លួន</p>
                    </div>
                </div>
            </a>
            <a href="system/checklist.php" class="action-card animate-fade-in-up" style="animation-delay: 0.3s">
                <div class="action-content">
                    <div class="action-icon" style="background: linear-gradient(135deg, var(--warning), #fbbf24);">
                        <i class="fas fa-file-upload"></i>
                    </div>
                    <div class="action-text">
                        <h3 class="action-title">ការងារ</h3>
                        <p class="action-desc">តាមដានការងារខ្លួនឯង</p>
                    </div>
                </div>
            </a>
            <a href="posts/announcements.php" class="action-card animate-fade-in-up" style="animation-delay: 0.4s">
                <div class="action-content">
                    <div class="action-icon" style="background: linear-gradient(135deg, var(--danger), #f87171);">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="action-text">
                        <h3 class="action-title">ការជូនដំណឹង</h3>
                        <p class="action-desc">មើលការជូនដំណឹងថ្មីៗ</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Main Dashboard Section -->
        <div class="section-header">
            <h2 class="section-title">ផ្ទាំងគ្រប់គ្រង</h2>
            <p class="section-subtitle">ឧបករណ៍និងមុខងារសម្រាប់គ្រប់គ្រងការងារ</p>
        </div>

        <!-- Main Dashboard Grid -->
        <div class="dashboard-grid">
            <a href="reports/daily_reports.php" class="dashboard-card animate-fade-in-up" style="animation-delay: 0.1s">
                <div class="card-content">
                    <i class="fas fa-file-alt card-icon"></i>
                    <h3 class="card-title">របាយការណ៍ប្រចាំថ្ងៃ</h3>
                    <p class="card-desc">បំពេញរបាយការណ៍ការងារប្រចាំថ្ងៃរបស់អ្នក</p>
                </div>
            </a>
            <a href="requests/requests_menu.php" class="dashboard-card animate-fade-in-up" style="animation-delay: 0.2s">
                <div class="card-content">
                    <i class="fas fa-paper-plane card-icon" style="color: #ef4444;"></i>
                    <h3 class="card-title">ការស្នើរសុំផ្សេងៗ</h3>
                    <p class="card-desc">ដាក់សំណើរសុំចំពោះការងារផ្សេងៗ</p>
                </div>
            </a>
            <a href="stock-control/user_request_form.php" class="dashboard-card animate-fade-in-up"
                style="animation-delay: 0.3s">
                <div class="card-content">
                    <i class="fas fa-shopping-cart card-icon" style="color: #06b6d4;"></i>
                    <h3 class="card-title">ស្នើរសុំទិញសម្ភារៈ</h3>
                    <p class="card-desc">ដាក់សំណើរសុំទិញសម្ភារៈផ្សេងៗ</p>
                </div>
            </a>
            <a href="meetings/meeting_register.php" class="dashboard-card animate-fade-in-up" style="animation-delay: 0.4s">
                <div class="card-content">
                    <i class="fas fa-users card-icon" style="color: #10b981;"></i>
                    <h3 class="card-title">ចុះឈ្មោះប្រជុំ</h3>
                    <p class="card-desc">ចុះឈ្មោះចូលរួមការប្រជុំ</p>
                </div>
            </a>
            <a href="meetings/meeting_page.php" class="dashboard-card animate-fade-in-up" style="animation-delay: 0.5s">
                <div class="card-content">
                    <i class="fas fa-video card-icon" style="color: #8b5cf6;"></i>
                    <h3 class="card-title">ស្ដាប់ការប្រជុំ</h3>
                    <p class="card-desc">ចូលរួមស្ដាប់ការប្រជុំពីចម្ងាយ</p>
                </div>
            </a>
            <a href="admin/lessons.php" class="dashboard-card animate-fade-in-up" style="animation-delay: 0.6s">
                <div class="card-content">
                    <i class="fa-solid fa-graduation-cap card-icon" style="color: #f59e0b;"></i>
                    <h3 class="card-title">មេរៀន</h3>
                    <p class="card-desc">មេរៀនផ្សេងៗ</p>
                </div>
            </a>
            <a href="admin/print_content.php" class="dashboard-card animate-fade-in-up" style="animation-delay: 0.7s">
                <div class="card-content">
                    <i class="fas fa-print card-icon" style="color: #64748b;"></i>
                    <h3 class="card-title">ព្រីនឯកសារ</h3>
                    <p class="card-desc">បោះពុម្ពឯកសារផ្សេងៗ</p>
                </div>
            </a>
            <a href="surveys/survey.php" class="dashboard-card animate-fade-in-up" style="animation-delay: 0.8s">
                <div class="card-content">
                    <i class="fas fa-poll card-icon" style="color: #ec4899;"></i>
                    <h3 class="card-title">ការស្រង់មតិ</h3>
                    <p class="card-desc">ចូលរួមការស្រង់មតិផ្សេងៗ</p>
                </div>
            </a>
            <a href="missions/input_mission.php" class="dashboard-card animate-fade-in-up" style="animation-delay: 0.8s">
                <div class="card-content">
                    <i class="fa-solid fa-briefcase card-icon" style="color: #ec4899;"></i>
                    <h3 class="card-title">បេសកម្ម</h3>
                    <p class="card-desc">បំពេញលិខិតបេសកម្ម</p>
                </div>
            </a>
            <a href="attendance/attendance_menu.php" class="dashboard-card animate-fade-in-up" style="animation-delay: 0.8s">
                <div class="card-content">
                    <i class="fas fa-solid fa-clipboard-user card-icon" style="color: #ec4899;"></i>
                    <h3 class="card-title">របាយការណ៍វត្តមានបុគ្គលិក</h3>
                    <p class="card-desc">ចុះវត្តមានបុគ្គលិក</p>
                </div>
            </a>
            <a href="posts/lessons_documents.php" class="dashboard-card animate-fade-in-up" style="animation-delay: 0.8s">
                <div class="card-content">
                    <i class="fa-solid fa-folder card-icon" style="color: #ec4899;"></i>
                    <h3 class="card-title">ឯកសារមេរៀន Odoo</h3>
                    <p class="card-desc">មេរៀនសម្រាប់ Odoo</p>
                </div>
            </a>

            <a href="scan_attendance.php" class="dashboard-card animate-fade-in-up" style="animation-delay: 0.9s">
                <div class="card-content">
                    <i class="fas fa-qrcode card-icon" style="color: var(--primary);"></i>
                    <h3 class="card-title">ស្កេនវត្តមាន</h3>
                    <p class="card-desc">បើកកាមេរ៉ាដើម្បីស្កេន QR កូដ</p>
                </div>
            </a>
            <!-- NEW VOTING CARD START -->
            <a href="surveys/employee_voting.php" class="dashboard-card animate-fade-in-up" style="animation-delay: 1.2s">
                <div class="card-content">
                    <i class="fas fa-vote-yea card-icon" style="color: var(--secondary);"></i>
                    <h3 class="card-title">បោះឆ្នោតបុគ្គលិក</h3>
                    <p class="card-desc">ចូលរួមបោះឆ្នោតសម្រាប់បុគ្គលិក</p>
                </div>
            </a>

        </div>

        <!-- Announcements Popup Modal -->
        <?php if (!empty($new_announcements)): ?>
            <div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="announcementModalLabel">
                                <i class="fas fa-bullhorn me-2"></i>ការជូនដំណឹងថ្មី
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <?php foreach ($new_announcements as $announcement): ?>
                                <div class="announcement-item">
                                    <h3 class="announcement-title">
                                        <i class="fas fa-circle"></i>
                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                    </h3>
                                    <div class="announcement-date"><?php echo htmlspecialchars($announcement['date']); ?></div>
                                    <p class="announcement-text"><?php echo htmlspecialchars($announcement['text']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="modal-footer">
                            <a href="posts/announcements.php" class="btn btn-primary">មើលទាំងអស់</a>
                            <button type="button" class="btn btn-secondary" id="closeModalBtn"
                                data-bs-dismiss="modal">បិទ</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="homes.php" class="nav-item <?php echo $current_page === 'homes.php' ? 'active' : ''; ?>">
            <i class="fas fa-home nav-icon"></i>
            <span>ទំព័រដើម</span>
        </a>
        <a href="system/checklist.php" class="nav-item <?php echo $current_page === 'system/checklist.php' ? 'active' : ''; ?>">
            <i class="fas fa-tasks nav-icon"></i>
            <span>ការងារ</span>
        </a>
        <a href="posts/announcements.php"
            class="nav-item <?php echo $current_page === 'posts/announcements.php' ? 'active' : ''; ?>">
            <i class="fas fa-bell nav-icon"></i>
            <span>ដំណឹង</span>
        </a>
        <a href="admin/profile.php"
            class="nav-item <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user nav-icon"></i>
            <span>គណនី</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('service-worker.js')
                    .then(registration => {
                        console.log('Service Worker registered with scope:', registration.scope);
                    })
                    .catch(error => {
                        console.error('Service Worker registration failed:', error);
                    });
            });
        }

        // Animation for cards
        document.addEventListener('DOMContentLoaded', () => {
            const animateCards = document.querySelectorAll('.animate-fade-in-up');
            animateCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });

            // Show announcement modal if there are new announcements
            <?php if (!empty($new_announcements)): ?>
                console.log('Showing announcement modal with IDs: <?php echo implode(',', array_column($new_announcements, 'id')); ?>');
                var announcementModal = new bootstrap.Modal(document.getElementById('announcementModal'), {
                    keyboard: false
                });
                announcementModal.show();

                // Handle modal close to update the session
                const closeModalAndUpdate = () => {
                    if (!window.location.href.includes('popup_shown=1')) {
                        var announcementIds = '<?php echo implode(',', array_column($new_announcements, 'id')); ?>';
                        window.location.href = 'homes.php?popup_shown=1&announcement_ids=' + announcementIds;
                    }
                };

                document.getElementById('closeModalBtn').addEventListener('click', closeModalAndUpdate);
                document.getElementById('announcementModal').addEventListener('hidden.bs.modal', closeModalAndUpdate);
            <?php endif; ?>
        });

        // PWA Install Prompt
        let deferredPrompt;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            // You could show an install button here
            console.log(`'beforeinstallprompt' event was fired.`);
        });

        // Hide loading overlay when page is fully loaded
        window.addEventListener('load', function () {
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