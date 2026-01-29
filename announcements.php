<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'សូមចូលគណនីសិន!';
    header("Location: login.php");
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
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
    <title>ការជូនដំណឹង | HRM Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="manifest" href="manifest.json">
    <style>
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
                <h1 class="app-title">ការជូនដំណឹង</h1>
            </div>
            </a>
            <div class="user-profile">
                <div class="user-avatar">
                    <a href="https://app.vvc.asia/admin/profile.php"><i class="fas fa-user"></i></a>
                </div>
                <a href="?logout=true" class="btn btn-danger btn-sm">ចាកចេញ</a>
            </div>
        </header>
        
        <!-- Announcements Section -->
        <section class="announcements animate__animated animate__fadeIn">
            <h2 class="section-title">
                <i class="fas fa-bullhorn"></i>
                ការជូនដំណឹងផ្ទាល់ខ្លួន
            </h2>
            <?php if (empty($filtered_announcements)): ?>
                <p class="text-center text-muted">មិនទាន់មានការជូនដំណឹងសម្រាប់អ្នក។</p>
            <?php else: ?>
                <?php foreach ($filtered_announcements as $announcement): ?>
                    <div class="announcement-item">
                        <h3 class="announcement-title">
                            <i class="fas fa-circle"></i>
                            <?php echo htmlspecialchars($announcement['title']); ?>
                        </h3>
                        <div class="announcement-date"><?php echo htmlspecialchars($announcement['date']); ?></div>
                        <p class="announcement-text"><?php echo htmlspecialchars($announcement['text']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
    
    <!-- Bottom Navigation -->
    <nav class="bottom-nav d-lg-none">
       <a href="homes.php" class="nav-item">
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
    </script>
</body>
</html>