<?php
session_start();

// Set time zone
date_default_timezone_set('Asia/Phnom_Penh');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'សូមចូលគណនីសិន!';
    header("Location: login.php");
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Include database configuration
require_once 'config.php';

// Database connection
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $_SESSION['error'] = "ការតភ្ជាប់មូលដ្ឋានទិន្នន័យបរាជ័យ: " . $e->getMessage();
    header("Location: menu.php");
    exit();
}

// Fetch user data
try {
    $stmt = $db->prepare("SELECT name FROM users WHERE id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $_SESSION['error'] = 'គណនីមិនត្រឹមត្រូវ';
        header("Location: login.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "កំហុសមូលដ្ឋានទិន្នន័យ: " . $e->getMessage();
    header("Location: menu.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4f46e5">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
    <title>HRM Menu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script src="https://unpkg.com/scrollreveal"></script>
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
            width: 40px;
            height: 40px;
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
        .dashboard-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: none;
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
        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--dark);
        }
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .menu-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        .menu-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
        }
        .menu-item:hover .menu-icon {
            color: white;
        }
        .menu-item:hover .menu-title {
            color: white;
        }
        .menu-icon {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 10px;
            transition: color 0.3s ease;
        }
        .menu-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 10px;
            transition: color 0.3s ease;
        }
        .menu-button {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .menu-button:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
        }
        .form-message {
            padding: 12px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 20px;
        }
        .form-message.error {
            background: var(--danger);
            color: white;
        }
        .modal-content {
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.3s ease-out;
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border-bottom: none;
            padding: 15px 20px;
        }
        .modal-title {
            font-size: 1.2rem;
            font-weight: 600;
        }
        .modal-body {
            padding: 20px;
            background: var(--light);
            text-align: center;
        }
        .modal-dialog-centered {
            display: flex;
            align-items: center;
            min-height: calc(100% - 1rem);
        }
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
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
            .app-container {
                padding: 15px;
            }
            .menu-grid {
                grid-template-columns: 1fr;
            }
            .menu-item {
                padding: 15px;
            }
            .menu-icon {
                font-size: 2rem;
            }
            .menu-button {
                font-size: 0.9rem;
                padding: 8px 16px;
            }
        }
        @media (max-width: 480px) {
            .dashboard-card {
                padding: 15px;
            }
            .menu-title {
                font-size: 1rem;
            }
            .menu-button {
                font-size: 0.85rem;
                padding: 6px 12px;
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
    </style>
</head>
<body>
    <div class="app-container">
        <header class="app-header animate__animated animate__fadeIn">
            <a href="homes.php">
                <div class="logo-container">
                    <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="Logo" class="logo-img">
                    <h1 class="app-title"><?php echo htmlspecialchars($user['name']); ?></h1>
                </div>
            </a>
            <!-- Uncomment if you want to re-enable user profile section -->
            <!--
            <div class="user-profile">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <a href="?logout=true" class="btn btn-danger btn-sm">ចាកចេញ</a>
            </div>
            -->
        </header>

        <!-- Success Modal -->
        <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="successModalLabel">ជោគជ័យ</h5>
                    </div>
                    <div class="modal-body">
                        <i class="fas fa-check-circle" style="color: var(--success); font-size: 2.5rem; margin-bottom: 15px;"></i>
                        <p>បានបញ្ជូនរួចរាល់</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="form-message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="dashboard-card animate-card" style="animation-delay: 0.1s">
            <h3 class="card-title">ម៉ឺនុយ</h3>
            <div class="menu-grid">
                <div class="menu-item ani-1">
                    <i class="fas fa-file-alt menu-icon"></i>
                    <div class="menu-title">បញ្ជូនរបាយការណ៍ប្រចាំថ្ងៃ</div>
                    <a href="daily_reports.php" class="menu-button">ចូលទៅកាន់ទំព័រ</a>
                </div>
                <div class="menu-item ani-2">
                    <i class="fas fa-eye menu-icon"></i>
                    <div class="menu-title">មើលរបាយការណ៍</div>
                    <a href="view_report.php" class="menu-button">ចូលទៅកាន់ទំព័រ</a>
                </div>
            </div>
        </div>

          <!-- Bottom Navigation -->
    <nav class="bottom-nav d-lg-none">
        <a href="homes.php" class="nav-item active">
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
    </div>

    <script>
        const scrollRevealOption = {
            origin: "bottom",
            distance: "10px",
            duration: 1000,
        };
        ScrollReveal().reveal(".ani-1", { ...scrollRevealOption, delay: 100, distance: "200px" });
        ScrollReveal().reveal(".ani-2", { ...scrollRevealOption, delay: 200, distance: "200px" });

        // Handle success modal
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('success') === '1') {
            const successModal = new bootstrap.Modal(document.getElementById('successModal'), {
                backdrop: 'static',
                keyboard: false
            });
            successModal.show();

            // Auto-close modal and redirect after 2 seconds
            setTimeout(() => {
                successModal.hide();
                window.history.replaceState({}, document.title, window.location.pathname);
                window.location.href = window.location.pathname;
            }, 2000);

            <?php unset($_SESSION['success']); ?>
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>