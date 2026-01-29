<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'សូមចូលគណនីសិន!';
    header("Location: login.php"); // Changed from logintest.php
    exit();
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
// Database connection
try {
    $db = new PDO("mysql:host=localhost;dbname=samann1_admin_panel;charset=utf8mb4", "samann1_admin_panel", "admin_panel@2025");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("ការតភ្ជាប់មូលដ្ឋានទិន្នន័យបរាជ័យ: " . $e->getMessage());
}

// Fetch announcements
$current_user_id = (int)$_SESSION['user_id'];
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
    die("កំហុសក្នុងការទាញទិន្នន័យ: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4f46e5">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2/Logo-Van-Van-1.png">
    <title>HRM Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="manifest" href="manifest.json">
    <style>
        /* Your existing CSS remains unchanged */
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --secondary: #4338ca;
            --accent: #06b6d4;
            --dark: #1e293b;
            --light: #f8fafc;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray: #64748b;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        .app-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            padding-bottom: 100px;
        }
        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            margin-bottom: 30px;
            position: relative;
        }
        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .logo-img {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            object-fit: cover;
        }
        .app-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .user-avatar:hover {
            transform: scale(1.1);
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .action-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: none;
            cursor: pointer;
            text-align: center;
        }
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .action-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            box-shadow: 0 5px 15px rgba(79, 70, 229, 0.3);
        }
        .action-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark);
        }
        .action-desc {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 0;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .dashboard-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: none;
            cursor: pointer;
        }
        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--primary), var(--accent));
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        .card-icon {
            font-size: 2.2rem;
            margin-bottom: 20px;
            color: var(--primary);
        }
        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }
        .card-desc {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 0;
        }
        .announcements {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--dark);
        }
        .section-title i {
            color: var(--primary);
        }
        .announcement-item {
            padding: 15px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
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
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            display: flex;
            justify-content: space-around;
            padding: 15px 0;
            box-shadow: 0 -5px 15px rgba(0, 0, 0, 0.05);
            z-index: 1000;
        }
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--gray);
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        .nav-item.active {
            color: var(--primary);
            transform: translateY(-5px);
        }
        .nav-icon {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        @media (max-width: 768px) {
            .app-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .user-profile {
                width: 100%;
                justify-content: flex-end;
            }
            .quick-actions {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
            }
            .dashboard-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 480px) {
            .quick-actions {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
            }
            .dashboard-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-card {
            animation: fadeIn 0.5s ease-out forwards;
            opacity: 0;
        }
        a {
            text-decoration: none;
        }
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --secondary: #4338ca;
            --accent: #06b6d4;
            --dark: #1e293b;
            --light: #f8fafc;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray: #64748b;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        .app-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            padding-bottom: 100px;
        }
        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            margin-bottom: 30px;
            position: relative;
        }
        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .logo-img {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            object-fit: cover;
        }
        .app-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
        }
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        .user-avatar:hover {
            transform: scale(1.1);
        }
        .announcements {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--dark);
        }
        .section-title i {
            color: var(--primary);
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
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            display: flex;
            justify-content: space-around;
            padding: 15px 0;
            box-shadow: 0 -5px 15px rgba(0, 0, 0, 0.05);
            z-index: 1000;
        }
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--gray);
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        .nav-item.active {
            color: var(--primary);
            transform: translateY(-5px);
        }
        .nav-icon {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        @media (max-width: 768px) {
            .app-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .user-profile {
                width: 100%;
                justify-content: flex-end;
            }
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate__animated {
            animation: fadeIn 0.5s ease-out forwards;
        }
        a {
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Modern App Header -->
        <header class="app-header animate__animated animate__fadeIn">
             <a href="homes.php">
            <div class="logo-container">
                <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="HRM Logo" class="logo-img">
                <h1 class="app-title">HR App</h1>
            </div>
            </a>
            <div class="user-profile">
                <div class="user-avatar">
                    <a href="https://app.vvc.asia/admin/profile.php"><i class="fas fa-user"></i></a>
                </div>
                <a href="?logout=true" class="btn btn-danger btn-sm">ចាកចេញ</a>
            </div>
        </header>
        
        <!-- Quick Actions Section -->
        <div class="quick-actions">
            <a href="https://app.vvc.asia/admin/submit_request.php" class="dashboard-card animate-card" style="animation-delay: 0.1s">
            <div class="action-card animate-card" style="animation-delay: 0.1s">
                <div class="action-icon">
                    <i class="fas fa-plus"></i>
                </div>
                <h3 class="action-title">ស្នើសុំថ្មី</h3>
                <p class="action-desc">បង្កើតសំណើរសុំថ្មី</p>
            </div>
            </a>
            <a href="https://app.vvc.asia/request_analysis.php"  class="dashboard-card animate-card" style="animation-delay: 0.1s">
           <div class="action-card animate-card" style="animation-delay: 0.2s">
                <div class="action-icon" style="background: linear-gradient(135deg, var(--success), #34d399);">
                    <i class="fas fa-calendar-check"></i>
          
                </div>
                <h3 class="action-title">មើលការស្នើរសុំផ្សេងៗ</h3>
                <p class="action-desc">មើលរបាយការណ៍ស្នើរសុំផ្សេងៗរបស់ខ្លួន</p>
           </div>
           </a>
            <a href="#"  class="dashboard-card animate-card" style="animation-delay: 0.1s">
            <div class="action-card animate-card" style="animation-delay: 0.3s">
                <div class="action-icon" style="background: linear-gradient(135deg, var(--warning), #fbbf24);">
                    <i class="fas fa-file-upload"></i>
                </div>
                <h3 class="action-title">ដាក់ឯកសារ</h3>
                <p class="action-desc">ដាក់ឯកសារថ្មី</p>
            </div>
            </a>
            <a href="announcements.php"  class="dashboard-card animate-card" style="animation-delay: 0.1s">
            <div class="action-card animate-card" style="animation-delay: 0.4s">
                <div class="action-icon" style="background: linear-gradient(135deg, var(--danger), #f87171);">
                    <i class="fas fa-bell"></i>
                </div>
                <h3 class="action-title">ការជូនដំណឹង</h3>
                <p class="action-desc">មើលការជូនដំណឹងថ្មីៗ</p>
            </div>
              </a>
        </div>
        
        <!-- Main Dashboard Grid -->
        <div class="dashboard-grid">
            <a href="index27.php" class="dashboard-card animate-card" style="animation-delay: 0.1s">
                <i class="fas fa-file-alt card-icon"></i>
                <h3 class="card-title">របាយការណ៍ប្រចាំថ្ងៃ</h3>
                <p class="card-desc">បំពេញរបាយការណ៍ការងារប្រចាំថ្ងៃរបស់អ្នក</p>
            </a>
            <a href="requests_menu.php" class="dashboard-card animate-card" style="animation-delay: 0.2s">
                <i class="fas fa-paper-plane card-icon" style="color: #ef4444;"></i>
                <h3 class="card-title">ការស្នើរសុំផ្សេងៗ</h3>
                <p class="card-desc">ដាក់សំណើរសុំចំពោះការងារផ្សេងៗ</p>
            </a>
            <a href="index5.html" class="dashboard-card animate-card" style="animation-delay: 0.3s">
                <i class="fas fa-shopping-cart card-icon" style="color: #06b6d4;"></i>
                <h3 class="card-title">ស្នើរសុំទិញសម្ភារៈ</h3>
                <p class="card-desc">ដាក់សំណើរសុំទិញសម្ភារៈផ្សេងៗ</p>
            </a>
            <a href="index20.html" class="dashboard-card animate-card" style="animation-delay: 0.4s">
                <i class="fas fa-users card-icon" style="color: #10b981;"></i>
                <h3 class="card-title">ចុះឈ្មោះប្រជុំ</h3>
                <p class="card-desc">ចុះឈ្មោះចូលរួមការប្រជុំ</p>
            </a>
            <a href="meeting_page.php" class="dashboard-card animate-card" style="animation-delay: 0.5s">
                <i class="fas fa-video card-icon" style="color: #8b5cf6;"></i>
                <h3 class="card-title">ស្ដាប់ការប្រជុំ</h3>
                <p class="card-desc">ចូលរួមស្ដាប់ការប្រជុំពីចម្ងាយ</p>
            </a>
            <a href="admin/employee_view.php" class="dashboard-card animate-card" style="animation-delay: 0.6s">
                <i class="fas fa-address-card card-icon" style="color: #f59e0b;"></i>
                <h3 class="card-title">ព័ត៌មានបុគ្គលិក</h3>
                <p class="card-desc">មើលព័ត៌មានលម្អិតអំពីបុគ្គលិក</p>
            </a>
            <a href="admin/print_content.php" class="dashboard-card animate-card" style="animation-delay: 0.7s">
                <i class="fas fa-print card-icon" style="color: #64748b;"></i>
                <h3 class="card-title">ព្រីនឯកសារ</h3>
                <p class="card-desc">បោះពុម្ពឯកសារផ្សេងៗ</p>
            </a>
            <a href="survey.php" class="dashboard-card animate-card" style="animation-delay: 0.8s">
                <i class="fas fa-poll card-icon" style="color: #ec4899;"></i>
                <h3 class="card-title">ការស្រង់មតិ</h3>
                <p class="card-desc">ចូលរួមការស្រង់មតិផ្សេងៗ</p>
            </a>
        </div>
        
       
    <!-- Bottom Navigation -->
    <nav class="bottom-nav d-lg-none">
        <a href="#" class="nav-item active">
            <i class="fas fa-home nav-icon"></i>
            <span>ទំព័រដើម</span>
        </a>
        <a href="#" class="nav-item">
            <i class="fas fa-calendar nav-icon"></i>
            <span>កាលវិភាគ</span>
        </a>
        <a href="#" class="nav-item">
            <i class="fas fa-tasks nav-icon"></i>
            <span>ការងារ</span>
        </a>
        <a href="https://app.vvc.asia/admin/profile.php" class="nav-item">
            <i class="fas fa-user nav-icon"></i>
            <span>គណនី</span>
        </a>
    </nav>

    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('service-worker.js')
                    .then(registration => {
                        console.log('ServiceWorker registration successful');
                    })
                    .catch(err => {
                        console.log('ServiceWorker registration failed: ', err);
                    });
            });
        }
        document.addEventListener('DOMContentLoaded', () => {
            const animateCards = document.querySelectorAll('.animate-card');
            animateCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>