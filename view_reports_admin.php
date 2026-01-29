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
    header("Location: view_reports_admin.php");
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
    header("Location: view_reports_admin.php");
    exit();
}

// Fetch unique positions for filter dropdown
try {
    $stmt = $db->query("SELECT DISTINCT position FROM daily_reports ORDER BY position");
    $positions = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $_SESSION['error'] = "កំហុសក្នុងការទាញតួនាទី: " . $e->getMessage();
    $positions = [];
}

// Handle filter form submission
$filters = [
    'start_date' => isset($_GET['start_date']) ? filter_var($_GET['start_date'], FILTER_SANITIZE_STRING) : '',
    'end_date' => isset($_GET['end_date']) ? filter_var($_GET['end_date'], FILTER_SANITIZE_STRING) : '',
    'position' => isset($_GET['position']) ? filter_var($_GET['position'], FILTER_SANITIZE_STRING) : ''
];

// Build query for reports
$query = "SELECT * FROM daily_reports WHERE 1=1";
$params = [];

if (!empty($filters['start_date'])) {
    $query .= " AND report_date >= :start_date";
    $params['start_date'] = $filters['start_date'];
}
if (!empty($filters['end_date'])) {
    $query .= " AND report_date <= :end_date";
    $params['end_date'] = $filters['end_date'] . ' 23:59:59';
}
if (!empty($filters['position'])) {
    $query .= " AND position = :position";
    $params['position'] = $filters['position'];
}

$query .= " ORDER BY report_date DESC";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "កំហុសក្នុងការទាញរបាយការណ៍: " . $e->getMessage();
    $reports = [];
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#4f46e5">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
    <title>មើលរបាយការណ៍ប្រចាំថ្ងៃ</title>
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
            --folder-bg: #f4f4f8;
            --folder-tab: #e0e0e8;
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
            margin-bottom: 10px;
            color: var(--dark);
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
        .form-field {
            margin-bottom: 1.5rem;
        }
        .form-label {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: block;
        }
        .form-input, .form-select {
            width: 100%;
            padding: 10px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            font-size: 0.95rem;
            color: var(--dark);
            background: #fff;
            transition: border-color 0.3s ease;
        }
        .form-input:focus, .form-select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 5px rgba(79, 70, 229, 0.2);
        }
        .form-button {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .form-button:hover {
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            transform: translateY(-2px);
        }
        .form-message {
            padding: 12px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            text-align: center;
            margin-top: 10px;
        }
        .form-message.error {
            background: var(--danger);
            color: white;
        }
        .folder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .folder-card {
            background: var(--folder-bg);
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            padding: 10px;
            overflow: hidden;
            cursor: pointer;
        }
        .folder-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .folder-tab {
            background: var(--folder-tab);
            padding: 5px 15px;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            position: relative;
            top: -10px;
            left: 10px;
            display: inline-block;
            font-weight: 600;
            color: var(--dark);
            clip-path: polygon(0 0, 85% 0, 100% 100%, 15% 100%);
        }
        .folder-content {
            padding: 15px;
        }
        .folder-icon {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.5rem;
            color: var(--primary);
            opacity: 0.3;
        }
        .folder-field {
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        .folder-field strong {
            color: var(--primary);
        }
        .folder-content-text {
            font-size: 0.85rem;
            color: var(--gray);
            max-height: 100px;
            overflow-y: auto;
        }
        .no-reports {
            text-align: center;
            font-size: 1rem;
            color: var(--gray);
            padding: 20px;
        }
        .modal-content {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            border-radius: 12px;
        }
        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
        }
        .modal-body {
            font-size: 1rem;
            color: var(--dark);
            white-space: pre-wrap;
            max-height: 60vh;
            overflow-y: auto;
        }
        .modal-footer {
            border-top: none;
        }
        @media (max-width: 768px) {
            .app-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .app-container {
                padding: 15px;
            }
            .folder-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
        @media (max-width: 480px) {
            .dashboard-card {
                padding: 20px;
            }
            .form-input, .form-select {
                font-size: 0.9rem;
            }
            .form-button {
                font-size: 0.9rem;
                padding: 10px;
            }
            .folder-card {
                padding: 8px;
            }
            .folder-content {
                padding: 10px;
            }
            .folder-field {
                font-size: 0.85rem;
            }
            .folder-content-text {
                font-size: 0.8rem;
            }
            .modal-body {
                font-size: 0.9rem;
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
                    <h1 class="app-title">HR App</h1>
                </div>
            </a>
        </header>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="form-message error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="dashboard-card animate-card" style="animation-delay: 0.1s">
            <h3 class="card-title">របាយការណ៍ប្រចាំថ្ងៃ</h3>
            <form method="GET" action="view_reports_admin.php" class="row g-3">
                <div class="col-md-4 form-field ani-1">
                    <label class="form-label">ចាប់ពីថ្ងៃ</label>
                    <input class="form-input" type="date" name="start_date" value="<?php echo htmlspecialchars($filters['start_date']); ?>">
                </div>
                <div class="col-md-4 form-field ani-2">
                    <label class="form-label">ដល់ថ្ងៃ</label>
                    <input class="form-input" type="date" name="end_date" value="<?php echo htmlspecialchars($filters['end_date']); ?>">
                </div>
                <div class="col-md-4 form-field ani-3">
                    <label class="form-label">បុគ្គលិកផ្នែក</label>
                    <select class="form-select" name="position">
                        <option value="">ទាំងអស់</option>
                        <?php foreach ($positions as $pos): ?>
                            <option value="<?php echo htmlspecialchars($pos); ?>" <?php echo $filters['position'] === $pos ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($pos); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 form-field ani-4">
                    <button class="form-button" type="submit">ស្វែងរក</button>
                </div>
            </form>

            <div class="folder-grid">
                <?php if (empty($reports)): ?>
                    <div class="no-reports">មិនមានរបាយការណ៍</div>
                <?php else: ?>
                    <?php foreach ($reports as $report): ?>
                        <div class="folder-card" data-bs-toggle="modal" data-bs-target="#contentModal" data-content="<?php echo htmlspecialchars($report['content'], ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="folder-tab"><?php echo date('d-m-Y', strtotime($report['report_date'])); ?></div>
                            <i class="fas fa-folder folder-icon"></i>
                            <div class="folder-content">
                                <div class="folder-field">
                                    <strong>ឈ្មោះ:</strong> <?php echo htmlspecialchars($report['name']); ?>
                                </div>
                                <div class="folder-field">
                                    <strong>អ៊ីមែល:</strong> <?php echo htmlspecialchars($report['email']); ?>
                                </div>
                                <div class="folder-field">
                                    <strong>តួនាទី:</strong> <?php echo htmlspecialchars($report['position']); ?>
                                </div>
                                <div class="folder-field">
                                    <strong>ម៉ោង:</strong> <?php echo date('H:i', strtotime($report['report_date'])); ?>
                                </div>
                                <div class="folder-field">
                                    <strong>ខ្លឹមសារ:</strong>
                                    <div class="folder-content-text"><?php echo nl2br(htmlspecialchars($report['content'])); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modal for viewing content -->
        <div class="modal fade" id="contentModal" tabindex="-1" aria-labelledby="contentModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="contentModalLabel">ខ្លឹមសាររបាយការណ៍</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="បិទ"></button>
                    </div>
                    <div class="modal-body" id="modalContent"></div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">បិទ</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Navigation -->
        <nav class="bottom-nav d-lg-none">
            <a href="home.php" class="nav-item">
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
        ScrollReveal().reveal(".ani-1", { ...scrollRevealOption, distance: "200px" });
        ScrollReveal().reveal(".ani-2", { ...scrollRevealOption, delay: 100, distance: "200px" });
        ScrollReveal().reveal(".ani-3", { ...scrollRevealOption, delay: 200, distance: "200px" });
        ScrollReveal().reveal(".ani-4", { ...scrollRevealOption, delay: 300, distance: "200px" });
        ScrollReveal().reveal(".folder-card", { ...scrollRevealOption, delay: 400, interval: 100 });

        // Handle modal content population
        const contentModal = document.getElementById('contentModal');
        contentModal.addEventListener('show.bs.modal', function (event) {
            const folderCard = event.relatedTarget;
            const content = folderCard.getAttribute('data-content');
            const modalContent = document.getElementById('modalContent');
            modalContent.textContent = content; // Use textContent to preserve newlines
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>