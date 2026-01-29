<?php
session_start();

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
    header("Location: index.php");
    exit();
}

// Fetch user data and role
try {
    $stmt = $db->prepare("SELECT name, role FROM users WHERE id = :user_id");
    $stmt->execute(['user_id' => $_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $_SESSION['error'] = 'គណនីមិនត្រឹមត្រូវ';
        header("Location: login.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "កំហុសមូលដ្ឋានទិន្នន័យ: " . $e->getMessage();
    header("Location: index.php");
    exit();
}

// Fetch reports for table
$reports = [];
try {
    if ($user['role'] === 'admin') {
        $stmt = $db->prepare("SELECT id, user_id, email, name, position, report_date, content FROM daily_reports ORDER BY report_date DESC");
        $stmt->execute();
    } else {
        $stmt = $db->prepare("SELECT id, user_id, email, name, position, report_date, content FROM daily_reports WHERE user_id = :user_id ORDER BY report_date DESC");
        $stmt->execute(['user_id' => $_SESSION['user_id']]);
    }
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "កំហុសក្នុងការទាញរបាយការណ៍: " . $e->getMessage();
    header("Location: index.php");
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
    <title>HRM Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="manifest" href="manifest.json">
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
        .table-responsive {
            margin-top: 20px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td {
            padding: 12px;
            vertical-align: middle;
            border: 1px solid rgba(0, 0, 0, 0.1);
        }
        .table th {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            font-weight: 600;
        }
        .table tr:hover {
            background-color: rgba(79, 70, 229, 0.05);
        }
        .excel-table {
            width: 100%;
            border-collapse: collapse;
            font-family: 'Courier New', monospace;
            font-size: 0.95rem;
        }
        .excel-table th, .excel-table td {
            border: 1px solid #d0d0d0;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }
        .excel-table th {
            background-color: #e0e0e0;
            font-weight: 600;
        }
        .excel-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .excel-table tr:hover {
            background-color: #f0f0f0;
        }
        .form-message {
            padding: 12px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 20px;
        }
        .form-message.success {
            background: var(--success);
            color: white;
        }
        .form-message.error {
            background: var(--danger);
            color: white;
        }
        .content-preview {
            max-width: 100px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border-bottom: none;
        }
        .modal-title {
            font-weight: 600;
        }
        .modal-body {
            padding: 20px;
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
                height : 100vh;
            }
            .table th, .table td {
                font-size: 0.9rem;
                padding: 8px;
            }
            .excel-table th, .excel-table td {
                font-size: 0.85rem;
                padding: 8px;
            }
            .content-preview {
                max-width: 80px;
            }
        }
        @media (max-width: 480px) {
            .dashboard-card {
                padding: 15px;
            }
            .table th, .table td {
                font-size: 0.85rem;
                padding: 6px;
            }
            .excel-table th, .excel-table td {
                font-size: 0.8rem;
                padding: 6px;
            }
            .content-preview {
                max-width: 60px;
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
                    <h1 class="app-title"><?php echo htmlspecialchars($user['name'] ?? 'HR App'); ?></h1>
                </div>
            </a>
            <!--<div class="user-profile">-->
            <!--    <div class="user-avatar">-->
            <!--        <i class="fas fa-user"></i>-->
            <!--    </div>-->
            <!--    <a href="?logout=true" class="btn btn-danger btn-sm">ចាកចេញ</a>-->
            <!--</div>-->
        </header>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="form-message success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="form-message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="dashboard-card animate-card" style="animation-delay: 0.1s">
            <h3 class="card-title">របាយការណ៍ប្រចាំថ្ងៃ</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>លេខសម្គាល់</th>
                            <th>ឈ្មោះ</th>
                            <th>តួនាទី</th>
                            <th>ថ្ងៃខែឆ្នាំ</th>
                            <th>អ៊ីមែល</th>
                            <th>មាតិកា</th>
                            <th>សកម្មភាព</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reports)): ?>
                            <tr>
                                <td colspan="7" class="text-center">មិនមានរបាយការណ៍</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($reports as $index => $report): ?>
                                <tr class="ani-<?php echo $index + 1; ?>">
                                    <td><?php echo htmlspecialchars($report['id']); ?></td>
                                    <td><?php echo htmlspecialchars($report['name']); ?></td>
                                    <td><?php echo htmlspecialchars($report['position']); ?></td>
                                    <td><?php echo htmlspecialchars($report['report_date']); ?></td>
                                    <td><?php echo htmlspecialchars($report['email']); ?></td>
                                    <td class="content-preview"><?php echo htmlspecialchars(substr($report['content'], 0, 100)) . (strlen($report['content']) > 100 ? '...' : ''); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary view-detail-btn" 
                                                data-content="<?php echo htmlspecialchars($report['content'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#reportModal">
                                            <i class="fas fa-eye"></i> មើលលម្អិត
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="reportModalLabel">មាតិការបាយការណ៍</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="excel-table" id="modalContentTable">
                                <thead>
                                    <tr>
                                     
                                        <th>មាតិកា</th>
                                    </tr>
                                </thead>
                                <tbody id="modalContentBody"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">បិទ</button>
                    </div>
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
        <?php foreach ($reports as $index => $report): ?>
            ScrollReveal().reveal(".ani-<?php echo $index + 1; ?>", { ...scrollRevealOption, delay: <?php echo ($index + 1) * 100; ?>, distance: "200px" });
        <?php endforeach; ?>

        // Handle modal content population
        document.querySelectorAll('.view-detail-btn').forEach(button => {
            button.addEventListener('click', function() {
                const content = this.getAttribute('data-content');
                const lines = content.split('\n');
                const tbody = document.getElementById('modalContentBody');
                tbody.innerHTML = '';

                lines.forEach((line, index) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                       
                        <td>${line.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;')}</td>
                    `;
                    tbody.appendChild(row);
                });
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>