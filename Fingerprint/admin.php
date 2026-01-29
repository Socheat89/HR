<?php
session_start();
session_regenerate_id(true); // Prevent Session Fixation

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_fingerprint_db", "samann1_Fingerprint", "Fingerprint@2025");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    header('Location: error.php?message=' . urlencode('មានបញ្ហាក្នុងការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ! សូមទាក់ទងអ្នកគ្រប់គ្រង។'));
    exit;
}

$loggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Handle Registration
if (isset($_POST['register'])) {
    $username = filter_var($_POST['username'] ?? '', FILTER_SANITIZE_STRING);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($password) || empty($confirm_password)) {
        $registerError = 'ឈ្មោះអ្នកប្រើ និងលេខសម្ងាត់ទាំងពីរត្រូវបានទាមទារ!';
    } elseif (strlen($username) < 3 || strlen($password) < 8) {
        $registerError = 'ឈ្មោះអ្នកប្រើត្រូវមានយ៉ាងហោចណាស់ ៣ តួអក្សរ និងលេខសម្ងាត់ ៨ តួអក្សរ!';
    } elseif ($password !== $confirm_password) {
        $registerError = 'លេខសម្ងាត់មិនត្រូវគ្នា!';
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = :username");
        $stmt->execute([':username' => $username]);
        if ($stmt->fetchColumn() > 0) {
            $registerError = 'ឈ្មោះអ្នកប្រើនេះមានរួចហើយ!';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash)");
            $stmt->execute([':username' => $username, ':password_hash' => $password_hash]);
            $registerSuccess = 'ការចុះឈ្មោះជោគជ័យ! សូមចូលគណនី។';
        }
    }
}

// Handle Login
if (!$loggedIn) {
    if (isset($_POST['login'])) {
        $username = filter_var($_POST['username'] ?? '', FILTER_SANITIZE_STRING);
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $loginError = 'ឈ្មោះអ្នកប្រើ និងលេខសម្ងាត់ត្រូវបានទាមទារ!';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = :username LIMIT 1");
            $stmt->execute([':username' => $username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($admin && password_verify($password, $admin['password_hash'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];

                $stmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = :id");
                $stmt->execute([':id' => $admin['id']]);

                header('Location: admin.php');
                exit;
            } else {
                $loginError = 'ឈ្មោះអ្នកប្រើ ឬលេខសម្ងាត់មិនត្រឹមត្រូវ!';
            }
        }
    }
} else {
    if (isset($_GET['logout'])) {
        session_destroy();
        header('Location: admin.php');
        exit;
    }
}

// Data file handling with folder support
$dataFile = 'data.json';
try {
    // *** NEW CHANGE: Added 'branches' to the default data structure ***
    $data = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [
        'folders' => [],
        'users' => [],
        'allowedLocations' => [],
        'branches' => [], // <-- KEY ថ្មីសម្រាប់រក្សាទុក "ទីតាំងស្កេន"
        'scans' => [],
        'activeUsers' => [],
        'settings' => ['maxTokensPerUser' => 3] // Initialize with default value
    ];
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }
} catch (Exception $e) {
    error_log("Error loading data file: " . $e->getMessage());
    die("មានបញ្ហាបច្ចេកទេស។ សូមព្យាយាមម្តងទៀតនៅពេលក្រោយ។");
}

// API URL
$apiUrl = 'api.php';
?>

<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - ប្រព័ន្ធស្កេន</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Khmer&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="icon" href="https://i.ibb.co/HLzckTJ7/Logo-Van-Van-1.jpg" type="image/jpg">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Leaflet.js for OpenStreetMap -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        /********** NEW SIDEBAR STYLES (កូដ CSS ថ្មីសម្រាប់ Sidebar) **********/
        :root {
            --sidebar-width: 260px;
            --sidebar-bg: #2d3748;
            --sidebar-text: #e2e8f0;
            --sidebar-link-hover: #4a5568;
            --primary-color: #3182ce;
            --main-bg: #f5f7fa;
        }

        .page-wrapper {
            display: flex;
            min-height: 100vh;
            background-color: var(--main-bg);
        }

        #sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1001;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease-in-out;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid var(--sidebar-link-hover);
        }

        .sidebar-header img {
            max-width: 150px;
            margin-bottom: 10px;
        }

        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 20px 0;
            flex-grow: 1;
        }

        .sidebar-nav li a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            color: #a0aec0;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        
        .sidebar-nav li a .fa-fw {
            width: 20px;
            text-align: center;
        }

        .sidebar-nav li a:hover {
            background: var(--sidebar-link-hover);
            color: #ffffff;
        }

        .sidebar-nav li a.active {
            background: var(--primary-color);
            color: #ffffff;
            border-left: 4px solid #63b3ed;
            padding-left: 21px;
        }

        .sidebar-logout {
            margin-top: auto; /* Pushes to bottom */
        }
        .sidebar-logout .sidebar-nav li a {
            color: #f56565;
        }
        .sidebar-logout .sidebar-nav li a:hover {
            background: #c53030;
            color: #ffffff;
        }

        #main-content {
            margin-left: var(--sidebar-width);
            width: calc(100% - var(--sidebar-width));
            padding: 0;
            transition: margin-left 0.3s ease-in-out;
        }
        
        .main-content-inner {
            padding: 2rem;
        }

        .top-bar {
            background: #fff;
            padding: 10px 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        #sidebar-toggle {
            display: none; /* Hidden on large screens */
        }
        
        .welcome-message {
            font-weight: 600;
            color: #4a5568;
        }

        .content-section {
            display: none; /* Hidden by default */
        }

        .content-section.active {
            display: block; /* Shown when active */
        }
        
        @media (max-width: 992px) {
            #sidebar {
                transform: translateX(-100%);
            }
            #sidebar.open {
                transform: translateX(0);
                box-shadow: 5px 0 25px rgba(0,0,0,0.2);
            }
            #main-content {
                margin-left: 0;
                width: 100%;
            }
            #sidebar-toggle {
                display: block;
            }
        }
        
        /* Overlay for mobile when sidebar is open */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.4);
            z-index: 1000;
        }
        
        body.sidebar-is-open .sidebar-overlay {
            display: block;
        }


        /********** ORIGINAL CSS STYLES (កូដ CSS ដើមរបស់អ្ន) **********/

        /* Custom SweetAlert2 Styling */
        .swal2-popup {
            border-radius: 16px !important;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2) !important;
            font-family: 'Khmer', sans-serif !important;
        }

        .swal2-title {
            font-size: 1.6rem !important;
            color: #2d3748 !important;
            font-weight: 700 !important;
        }

        .swal2-content {
            font-size: 1rem !important;
            color: #4a5568 !important;
        }

        .swal2-confirm {
            background-color: #3182ce !important;
            border-radius: 25px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
        }

        .swal2-confirm:hover {
            background-color: #2b6cb0 !important;
            box-shadow: 0 5px 15px rgba(49, 130, 206, 0.4) !important;
        }

        .swal2-cancel {
            background-color: #e53e3e !important;
            border-radius: 25px !important;
            padding: 10px 20px !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
        }

        .swal2-cancel:hover {
            background-color: #c53030 !important;
            box-shadow: 0 5px 15px rgba(229, 62, 62, 0.4) !important;
        }

        /* Reset default margins and ensure full-screen */
        html,
        body {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            overflow-x: hidden;
            font-family: 'Khmer', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            transition: all 0.3s ease;
            scroll-behavior: smooth;

        }

        /* Dark Mode */
        body.dark-mode {
            background: #1a202c;
            color: #e2e8f0;
        }

        body.dark-mode .card {
            background: #2d3748;
            color: #e2e8f0;
        }

        body.dark-mode .table {
            background: #2d3748;
            color: #e2e8f0;
        }

        body.dark-mode .table th {
            background: #4a5568;
        }

        body.dark-mode .navbar {
            background: #2d3748;
        }

        /* Loading State */
        body.loading::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            z-index: 9998;
        }

        body.loading::after {
            content: 'កំពុងផ្ទុក...';
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            padding: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            z-index: 9999;
        }
        
        /* Main container with full width */
        .container {
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
            /* Padding is now handled by .main-content-inner */
        }

        /* Card styling with modern shadow and hover effects */
        .card {
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            border: none;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .card-body {
            padding: 30px;
        }

        /* Typography */
        h2 {
            font-size: 2rem;
            color: #1a202c;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        h3 {
            font-size: 1.5rem;
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 25px;
            border-bottom: 2px solid #3182ce;
            padding-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Modern table styling */
        .table {
            width: 100%; /* Change from 120% to fit new container */
            border-collapse: separate;
            border-spacing: 0;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .table th {
            background-color: #3082ce;
            color: rgb(255, 255, 255);
            font-weight: 600;
            padding: 15px 20px;
            text-transform: uppercase;
            font-size: 12.5px;
            letter-spacing: 1px;
        }

        .table td {
            padding: 15px 20px;
            color: #2d3748;
            font-size: 0.95rem;
            border-bottom: 1px solid #edf2f7;
            transition: background 0.2s ease;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background: #f9fafb;
        }

        .table-striped tbody tr:hover {
            background: #e6f0fa;
        }

        /* Modern buttons */
        .btn {
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #3182ce;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #2b6cb0;
            box-shadow: 0 5px 15px rgba(49, 130, 206, 0.4);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #e53e3e;
            color: #ffffff;
        }

        .btn-danger:hover {
            background: #c53030;
            box-shadow: 0 5px 15px rgba(229, 62, 62, 0.4);
        }

        .btn-success {
            background: #48bb78;
            color: #ffffff;
        }

        .btn-success:hover {
            background: #38a169;
            box-shadow: 0 5px 15px rgba(72, 187, 120, 0.4);
        }

        .btn-warning {
            background: #ecc94b;
            color: #744210;
        }

        .btn-warning:hover {
            background: #d69e2e;
            box-shadow: 0 5px 15px rgba(236, 201, 75, 0.4);
        }

        .btn-secondary {
            background: #718096;
            color: #ffffff;
        }

        .btn-secondary:hover {
            background: #5a7184;
            box-shadow: 0 5px 15px rgba(113, 128, 150, 0.4);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        /* Form elements */
        .form-control {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 12px 16px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #ffffff;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.03);
        }

        .form-control:focus {
            border-color: #3182ce;
            box-shadow: 0 0 0 4px rgba(49, 130, 206, 0.2);
            background: #ffffff;
        }

        .form-label {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        /* Modern modal styling */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, #3182ce 0%, #2b6cb0 100%);
            color: #ffffff;
            border-bottom: none;
            padding: 20px 30px;
        }

        .modal-title {
            font-size: 1.6rem;
            font-weight: 700;
        }

        .modal-body {
            padding: 30px;
            background: #f9fafb;
        }

        .modal-footer {
            padding: 20px 30px;
            background: #f9fafb;
            border-top: 1px solid #e2e8f0;
            justify-content: space-between;
        }

        /* Alerts */
        .alert {
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        /* Time range styling */
        .time-range {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 15px;
            margin: 8px 0;
            transition: all 0.3s ease;
        }

        .time-range:hover {
            border-color: #3182ce;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .time-range.Good {
            border-left: 4px solid rgb(0, 14, 92);
        }

        .time-range.Late {
            border-left: 4px solid rgb(172, 0, 0);
        }

        /* Full-screen table responsiveness */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 25px;
        }

        /* Modal specific styling */
        #addUserModal .modal-dialog,
        #editUserModal .modal-dialog {
            max-width: 900px;
        }

        #addUserModal .modal-body,
        #editUserModal .modal-body {
            background: #ffffff;
            border-radius: 0 0 20px 20px;
        }



        /* Media Query សម្រាប់អេក្រង់ទំហំ 1919px Full Screen */
        @media screen and (min-width: 1919px) {

            /* Container កណ្តាលអេក្រង់ */
            .main-content-inner {
                max-width: 1800px; /* កំណត់ទទឹងអតិបរមា */
                margin: 0 auto;
                padding: 30px; /* បន្ថែម padding សម្រាប់ភាពស្រួលមើល */
            }

            .top-bar {
                padding: 10px 40px; /* បន្ថែម padding សម្រាប់អេក្រង់ធំ */
            }

            /* Card កែសម្រួលទំហំនិងគម្លាត */
            .card {
                margin-bottom: 35px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.12);
                /* បង្កើន shadow */
            }

            .card-body {
                padding: 40px;
                /* បន្ថែម padding សម្រាប់អេក្រង់ធំ */
            }

            /* Typography សម្រាប់អេក្រង់ធំ */
            h2 { font-size: 2.5rem; }
            h3 { font-size: 1.8rem; } /* Increased size for clarity */

            /* Table កែសម្រួលទំហំនិងគម្លាត */
            .table {
                width: 100%;
                font-size: 1rem;
            }

            .table th { padding: 20px 25px; }
            .table td { padding: 20px 25px; }

            /* Buttons កែសម្រួលទំហំ */
            .btn {
                padding: 12px 25px;
                font-size: 1.1rem;
            }

            .btn-sm {
                padding: 8px 15px;
                font-size: 0.95rem;
            }

            /* Form elements កែសម្រួលទំហំ */
            .form-control {
                padding: 14px 20px;
                font-size: 1.1rem;
            }

            /* Modal កែសម្រួលទំហំ */
            #addUserModal .modal-dialog,
            #editUserModal .modal-dialog,
            #addLocationModal .modal-dialog,
            #editLocationModal .modal-dialog {
                max-width: 1100px; /* បង្កើនទទឹង modal */
            }

            #addMap, #editMap { height: 400px; }
            .pagination-controls { font-size: 1.1rem; }
            .time-range { padding: 15px 20px; font-size: 1.1rem; }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card-body { padding: 20px; }
            h2 { font-size: 1.5rem; }
            h3 { font-size: 1.3rem; }
            .table th, .table td { padding: 12px; font-size: 0.85rem; }
            .btn { padding: 8px 16px; font-size: 0.9rem; }
            .modal-dialog { margin: 10px; max-width: 95%; }
        }

        @media (max-width: 576px) {
            h2 { font-size: 1.2rem; }
            h3 { font-size: 1.1rem; }
            .btn-sm { padding: 5px 10px; }
            .main-content-inner { padding: 1rem; }
            .top-bar { padding: 10px 1rem; }
        }

        .admin-panel {
            margin-left: 0;
        }

        /* Back to Top Button */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: none;
            width: 50px;
            height: 50px;
            background: #3182ce;
            color: #ffffff;
            border-radius: 50%;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            font-size: 1.2rem;
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .back-to-top:hover {
            background: #2b6cb0;
            box-shadow: 0 6px 20px rgba(49, 130, 206, 0.4);
            transform: translateY(-3px);
        }

        .back-to-top i {
            font-size: 1.5rem;
        }

        .back-to-top:focus {
            outline: none;
            box-shadow: 0 0 0 4px rgba(49, 130, 206, 0.3);
        }

        /* Dark Mode */
        body.dark-mode .back-to-top {
            background: #4a5568;
            color: #e2e8f0;
        }

        body.dark-mode .back-to-top:hover {
            background: #5a7184;
            box-shadow: 0 6px 20px rgba(90, 113, 132, 0.4);
        }
        
                /********** DASHBOARD WIDGET STYLES (កូដ CSS សម្រាប់ Dashboard) **********/
        .stat-card {
            border: 1px solid #e3e6f0;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.15);
        }
        .stat-card .card-body {
            padding: 1.5rem;
        }
        .stat-card .text-xs {
            font-size: .8rem;
        }
        .stat-card .text-gray-300 {
            color: #dddfeb !important;
        }
        .stat-card .text-gray-800 {
            color: #5a5c69 !important;
        }

        .stat-card.card-primary { border-left: .25rem solid #4e73df; }
        .stat-card.card-success { border-left: .25rem solid #1cc88a; }
        .stat-card.card-info   { border-left: .25rem solid #36b9cc; }
        .stat-card.card-warning{ border-left: .25rem solid #f6c23e; }
        
        .text-primary { color: #4e73df !important; }
        .text-success { color: #1cc88a !important; }
        .text-info   { color: #36b9cc !important; }
        .text-warning{ color: #f6c23e !important; }
        
        .quick-link-btn {
            text-align: left;
            padding-top: 1rem;
            padding-bottom: 1rem;
            font-size: 1.1rem;
            transition: all 0.2s ease-in-out;
        }
        .quick-link-btn:hover {
            transform: translateX(5px);
        }
        .badge {
            font-size: 0.85em;
            padding: 0.5em 0.8em;
        }

        @media (max-width: 768px) {
            .back-to-top {
                width: 45px;
                height: 45px;
                bottom: 20px;
                right: 20px;
                font-size: 1rem;
            }
        }

        @media (max-width: 576px) {
            .back-to-top {
                width: 40px;
                height: 40px;
                bottom: 15px;
                right: 15px;
            }
        }
    </style>
</head>

<body>
    <?php if (!$loggedIn): ?>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-10 col-lg-8">
                 <div class="card shadow-lg">
                    <div class="row g-0">
                         <div class="col-lg-6">
                            <div class="card-body p-5">
                                <h2 class="mb-4"><i class="fas fa-sign-in-alt"></i> ចូលគណនី Admin</h2>
                                <?php if (isset($loginError)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($loginError); ?></div><?php endif; ?>
                                <?php if (isset($registerSuccess)): ?><div class="alert alert-success"><?php echo htmlspecialchars($registerSuccess); ?></div><?php endif; ?>
                                <form method="POST">
                                    <div class="mb-3"><label for="login_username" class="form-label">ឈ្មោះអ្នកប្រើ</label><input type="text" class="form-control" id="login_username" name="username" required></div>
                                    <div class="mb-3"><label for="login_password" class="form-label">លេខសម្ងាត់</label><input type="password" class="form-control" id="login_password" name="password" required></div>
                                    <button type="submit" name="login" class="btn btn-primary w-100"><i class="fas fa-sign-in-alt"></i> ចូល</button>
                                </form>
                            </div>
                        </div>
                        <div class="col-lg-6" style="background: #f7fafc;">
                             <div class="card-body p-5">
                                <h2 class="mb-4"><i class="fas fa-user-plus"></i> ចុះឈ្មោះ Admin</h2>
                                <?php if (isset($registerError)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($registerError); ?></div><?php endif; ?>
                                <form method="POST">
                                    <div class="mb-3"><label for="register_username" class="form-label">ឈ្មោះអ្នកប្រើ</label><input type="text" class="form-control" id="register_username" name="username" required></div>
                                    <div class="mb-3"><label for="register_password" class="form-label">លេខសម្ងាត់</label><input type="password" class="form-control" id="register_password" name="password" required></div>
                                    <div class="mb-3"><label for="confirm_password" class="form-label">បញ្ជាក់លេខសម្ងាត់</label><input type="password" class="form-control" id="confirm_password" name="confirm_password" required></div>
                                    <button type="submit" name="register" class="btn btn-success w-100"><i class="fas fa-check-circle"></i> ចុះឈ្មោះ</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
        <div class="page-wrapper">
            <div class="sidebar-overlay" id="sidebar-overlay"></div>
            <!-- Sidebar -->
            <aside id="sidebar">
                <div class="sidebar-header">
                    <img src="https://i.ibb.co/0RjV6FpX/Logo-Van-Van-2.png" alt="Logo" style="width: 150px; height: auto; border-radius: 12px; object-fit: cover; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <h4 style="font-weight: 700; color: #fff; letter-spacing: 1px; margin-top: 10px;">Admin Panel</h4>
                </div>
                <ul class="sidebar-nav">
                    <li><a href="#" class="nav-link active" data-target="dashboard"><i class="fas fa-tachometer-alt fa-fw"></i> Dashboard</a></li>
                    <li><a href="#" class="nav-link" data-target="users"><i class="fas fa-users fa-fw"></i> អ្នកប្រើប្រាស់</a></li>
                    <li><a href="#" class="nav-link" data-target="Folder"><i class="fas fa-folder fa-fw"></i> Folder</a></li>
                    <li><a href="#" class="nav-link" data-target="locations"><i class="fas fa-map-marker-alt fa-fw"></i> ទីតាំង</a></li>
                    <!-- *** NEW CHANGE: Added new sidebar link for Branches *** -->
                    <li><a href="#" class="nav-link" data-target="branches"><i class="fas fa-building fa-fw"></i> សាខា (ទីតាំងស្កេន)</a></li>
                    <li><a href="#" class="nav-link" data-target="tokens"><i class="fas fa-key fa-fw"></i> Token</a></li>
                </ul>
                <div class="sidebar-logout">
                     <ul class="sidebar-nav">
                         <li><a href="?logout=true" class="logout-link"><i class="fas fa-sign-out-alt fa-fw"></i> ចាកចេញ</a></li>
                     </ul>
                </div>
            </aside>

            <!-- Main Content -->
            <main id="main-content">
                <div class="top-bar">
                     <button id="sidebar-toggle" class="btn btn-primary btn-sm"><i class="fas fa-bars"></i></button>
                     <div class="welcome-message">ស្វាគមន៍, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</div>
                </div>

                <div class="main-content-inner">
                 <!-- Dashboard Section -->
<div id="dashboard" class="content-section active">
    <h2><i class="fas fa-tachometer-alt"></i> ផ្ទាំងគ្រប់គ្រង Dashboard</h2>
    
    <!-- Stat Cards Row -->
    <div class="row">
        <!-- Total Users Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card card-primary h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">អ្នកប្រើប្រាស់សរុប</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($data['users']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Users Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card card-success h-100">
                <div class="card-body">
                     <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">កំពុងសកម្ម (Online)</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($data['activeUsers']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-signal fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Locations Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card card-info h-100">
                <div class="card-body">
                     <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">ទីតាំងសរុប</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($data['allowedLocations']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-map-marker-alt fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Folders Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card card-warning h-100">
                <div class="card-body">
                     <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Folder សរុប</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($data['folders']); ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-folder fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Links Row -->
    <div class="row">
        <!-- Quick Links Column -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-body">
                    <h3><i class="fas fa-bolt"></i> តំណភ្ជាប់រហ័ស</h3>
                    <div class="d-grid gap-2">
                        <a href="#users" class="btn btn-outline-primary btn-lg quick-link-btn" data-target="users"><i class="fas fa-users fa-fw me-2"></i> គ្រប់គ្រងអ្នកប្រើប្រាស់</a>
                        <a href="#locations" class="btn btn-outline-info btn-lg quick-link-btn" data-target="locations"><i class="fas fa-map-marker-alt fa-fw me-2"></i> គ្រប់គ្រងទីតាំង</a>
                        <a href="#tokens" class="btn btn-outline-secondary btn-lg quick-link-btn" data-target="tokens"><i class="fas fa-key fa-fw me-2"></i> គ្រប់គ្រង Token</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
                    
                    <!-- Users Section -->
                    <div id="users" class="content-section">
                        <div class="card">
                            <div class="card-body">
                                <h3><i class="fas fa-users"></i> គ្រប់គ្រងអ្នកប្រើប្រាស់</h3>
                                <div class="row mb-3">
                                    <div class="col-md-4"><input type="text" id="searchUsers" class="form-control" placeholder="ស្វែងរកអ្នកប្រើប្រាស់"></div>
                                    <div class="col-md-4">
                                        <select id="filterFolder" class="form-control">
                                            <option value="">តម្រងតាម Folder (ទាំងអស់)</option>
                                            <?php foreach ($data['folders'] as $folder): ?><option value="<?php echo htmlspecialchars($folder['name']); ?>"><?php echo htmlspecialchars($folder['name']); ?></option><?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal"><i class="fas fa-plus"></i> បន្ថែមអ្នកប្រើប្រាស់</button>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <!-- *** NEW CHANGE: Dynamically populated Branch filter dropdown *** -->
                                        <select id="filterBranch" class="form-control">
                                            <option value="">តម្រងតាមទីតាំងស្កេន (ទាំងអស់)</option>
                                            <?php foreach ($data['branches'] as $branch): ?>
                                            <option value="<?php echo htmlspecialchars($branch['name']); ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th><i class="fas fa-user"></i> ឈ្មោះ</th><th><i class="fas fa-id-card"></i> ID</th><th><i class="fas fa-building"></i> នាយកដ្ឋាន</th><th><i class="fas fa-briefcase"></i> តួនាទី</th><th><i class="fas fa-map-marker-alt"></i> ទីតាំងស្កេន</th><th><i class="fas fa-location-arrow"></i> កន្លែងធ្វើការ</th><th><i class="fas fa-folder"></i> ប្រភេទ</th><th><i class="fas fa-clock"></i> ម៉ោងស្កេនចូល</th><th><i class="fas fa-clock"></i> ម៉ោងស្កេនចេញ</th><th><i class="fas fa-cogs"></i> សកម្មភាព</th>
                                            </tr>
                                        </thead>
                                        <tbody id="usersTable">
                                            <?php foreach ($data['users'] as $index => $user): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($user['username']); ?></td><td><?php echo htmlspecialchars($user['id']); ?></td><td><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></td><td><?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></td><td><?php echo htmlspecialchars($user['branch'] ?? 'N/A'); ?></td><td><?php echo htmlspecialchars($user['workplace'] ?? 'N/A'); ?></td><td><?php echo htmlspecialchars($user['folder'] ?? 'N/A'); ?></td>
                                                    <td><?php if (!empty($user['timeSettings']['check_in_ranges'])) { foreach ($user['timeSettings']['check_in_ranges'] as $range) { $start = sprintf("%02d:%02d", floor($range['start'] / 60), $range['start'] % 60); $end = sprintf("%02d:%02d", floor($range['end'] / 60), $range['end'] % 60); $status = htmlspecialchars($range['status']); echo "<div class='time-range $status'>" . htmlspecialchars("$start - $end") . " <i class='status-icon'></i>" . htmlspecialchars($status) . "</div>"; } } else { echo 'N/A'; } ?></td>
                                                    <td><?php if (!empty($user['timeSettings']['check_out_ranges'])) { foreach ($user['timeSettings']['check_out_ranges'] as $range) { $start = sprintf("%02d:%02d", floor($range['start'] / 60), $range['start'] % 60); $end = sprintf("%02d:%02d", floor($range['end'] / 60), $range['end'] % 60); $status = htmlspecialchars($range['status']); echo "<div class='time-range $status'>" . htmlspecialchars("$start - $end") . " <i class='status-icon'></i>" . htmlspecialchars($status) . "</div>"; } } else { echo 'N/A'; } ?></td>
                                                    <td><div class="action-buttons"><button class="btn btn-success btn-sm duplicate-user" data-index="<?php echo $index; ?>"><i class="fas fa-copy"></i></button><button class="btn btn-warning btn-sm edit-user" data-index="<?php echo $index; ?>" data-bs-toggle="modal" data-bs-target="#editUserModal"><i class="fas fa-edit"></i></button><button class="btn btn-danger btn-sm delete-user" data-index="<?php echo $index; ?>"><i class="fas fa-trash"></i></button></div></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="pagination-controls d-flex justify-content-between align-items-center mt-3">
                                    <button class="btn btn-secondary" id="prevPage" disabled><i class="fas fa-arrow-left"></i> មុន</button><div>ទំព័រ <span id="currentPage">1</span> នៃ <span id="totalPages">1</span></div><button class="btn btn-secondary" id="nextPage"><i class="fas fa-arrow-right"></i> បន្ទាប់</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Folder Section -->
                    <div id="Folder" class="content-section">
                        <div class="card">
                            <div class="card-body">
                                <h3><i class="fas fa-folder"></i> គ្រប់គ្រង Folders</h3>
                                <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addFolderModal"><i class="fas fa-folder-plus"></i> បន្ថែម Folder</button>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead><tr><th><i class="fas fa-folder"></i> ឈ្មោះ Folder</th><th><i class="fas fa-users"></i> ចំនួនអ្នកប្រើ</th><th><i class="fas fa-cogs"></i> សកម្មភាព</th></tr></thead>
                                        <tbody id="foldersTable">
                                            <?php foreach ($data['folders'] as $index => $folder): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($folder['name']); ?></td><td><?php $userCount = count(array_filter($data['users'], fn($u) => ($u['folder'] ?? '') === $folder['name'])); echo $userCount; ?></td>
                                                    <td><div class="action-buttons"><button class="btn btn-warning btn-sm edit-folder" data-index="<?php echo $index; ?>" data-bs-toggle="modal" data-bs-target="#editFolderModal"><i class="fas fa-edit"></i> កែ</button><button class="btn btn-danger btn-sm delete-folder" data-index="<?php echo $index; ?>"><i class="fas fa-trash"></i> លុប</button></div></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Locations Section -->
                    <div id="locations" class="content-section">
                        <div class="card">
                            <div class="card-body">
                                <h3><i class="fas fa-map-marker-alt"></i> គ្រប់គ្រងទីតាំង</h3>
                                <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addLocationModal"><i class="fas fa-plus"></i> បន្ថែមទីតាំង</button>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th><i class="fas fa-map-marker-alt"></i> ឈ្មោះ</th>
                                                <th><i class="fas fa-building"></i> សាខា</th>
                                                <th><i class="fas fa-globe"></i> Latitude</th>
                                                <th><i class="fas fa-globe"></i> Longitude</th>
                                                <th><i class="fas fa-qrcode"></i> QR Code</th>
                                                <th><i class="fas fa-users"></i> អ្នកប្រើប្រាស់</th>
                                                <th><i class="fas fa-cogs"></i> សកម្មភាព</th>
                                            </tr>
                                        </thead>
                                        <tbody id="locationsTable">
                                            <?php foreach ($data['allowedLocations'] as $index => $loc): ?>
                                                <?php
                                                // Validate coordinates
                                                $lat = is_numeric($loc['latitude']) ? floatval($loc['latitude']) : 0;
                                                $lng = is_numeric($loc['longitude']) ? floatval($loc['longitude']) : 0;
                                                if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                                                    $lat = 0;
                                                    $lng = 0;
                                                }
                                                // QR code data
                                                $qrData = "geo:{$lat},{$lng}";
                                                $logoUrl = "https://i.ibb.co/9HP7nkCV/Logo-Van-Van-3.png";
                                                ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($loc['name'] ?? 'Unknown'); ?></td>
                                                    <td><?php echo htmlspecialchars($loc['branch'] ?? 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars(number_format($lat, 5)); ?></td>
                                                    <td><?php echo htmlspecialchars(number_format($lng, 5)); ?></td>
                                                    <td>
                                                        <div style="text-align:center;">
                                                            <canvas id="qr-canvas-<?php echo $index; ?>" width="100" height="100" style="display:block;margin:auto;border-radius:8px;background:#fff;"></canvas>
                                                            <div class="d-flex flex-column align-items-center mt-2 gap-1">
                                                                <button type="button" class="btn btn-outline-primary btn-sm download-qr-svg" data-index="<?php echo $index; ?>" title="ទាញយក QR Code SVG"><i class="fas fa-download"></i> SVG</button>
                                                                <button type="button" class="btn btn-outline-success btn-sm download-qr-png" data-index="<?php echo $index; ?>" title="ទាញយក QR Code PNG"><i class="fas fa-download"></i> PNG</button>
                                                            </div>
                                                            <input type="hidden" class="qr-data" value="<?php echo htmlspecialchars($qrData); ?>">
                                                            <input type="hidden" class="qr-name" value="<?php echo htmlspecialchars($loc['name'] ?? 'location'); ?>">
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $usersList = [];
                                                        foreach ($loc['users'] ?? [] as $user) {
                                                            $userData = array_filter($data['users'], fn($u) => $u['id'] === $user['user_id']);
                                                            $userName = !empty($userData) ? array_values($userData)[0]['username'] : $user['user_id'];
                                                            $tolerance = is_numeric($user['tolerance']) ? number_format($user['tolerance'], 3) : 'N/A';
                                                            $usersList[] = htmlspecialchars("$userName (Tolerance: $tolerance)");
                                                        }
                                                        echo implode(', ', $usersList) ?: 'N/A';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons d-flex gap-2">
                                                            <button type="button" class="btn btn-success btn-sm duplicate-location" data-index="<?php echo $index; ?>"><i class="fas fa-copy"></i></button>
                                                            <button type="button" class="btn btn-warning btn-sm edit-location" data-index="<?php echo $index; ?>" data-bs-toggle="modal" data-bs-target="#editLocationModal"><i class="fas fa-edit"></i></button>
                                                            <button type="button" class="btn btn-danger btn-sm delete-location" data-index="<?php echo $index; ?>"><i class="fas fa-trash"></i></button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- *** NEW CHANGE: Added new section for Branch Management *** -->
                    <div id="branches" class="content-section">
                        <div class="card">
                            <div class="card-body">
                                <h3><i class="fas fa-building"></i> គ្រប់គ្រងសាខា (ទីតាំងស្កេន)</h3>
                                <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addBranchModal"><i class="fas fa-plus"></i> បន្ថែមសាខា</button>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th><i class="fas fa-building"></i> ឈ្មោះសាខា</th>
                                                <th><i class="fas fa-users"></i> ចំនួនអ្នកប្រើប្រាស់</th>
                                                <th><i class="fas fa-cogs"></i> សកម្មភាព</th>
                                            </tr>
                                        </thead>
                                        <tbody id="branchesTable">
                                            <?php foreach ($data['branches'] as $index => $branch): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($branch['name']); ?></td>
                                                    <td>
                                                        <?php 
                                                            // Count users assigned to this branch
                                                            $userCount = count(array_filter($data['users'], fn($u) => ($u['branch'] ?? '') === $branch['name']));
                                                            echo $userCount;
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <button class="btn btn-warning btn-sm edit-branch" data-index="<?php echo $index; ?>" data-bs-toggle="modal" data-bs-target="#editBranchModal"><i class="fas fa-edit"></i> កែ</button>
                                                            <button class="btn btn-danger btn-sm delete-branch" data-index="<?php echo $index; ?>"><i class="fas fa-trash"></i> លុប</button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Tokens Section -->
                    <div id="tokens" class="content-section">
                        <div class="card">
                            <div class="card-body">
                                <h3><i class="fas fa-key"></i> គ្រប់គ្រង Token</h3>
                                <div class="max-tokens-form mb-4">
                                    <label for="maxTokensPerUser" class="form-label">ចំនួន Token អតិបរមាក្នុងម្នាក់:</label>
                                    <input type="number" id="maxTokensPerUser" class="form-control" value="<?php echo htmlspecialchars($data['settings']['maxTokensPerUser'] ?? 3); ?>" min="1">
                                    <button type="button" class="btn btn-primary mt-2" id="saveMaxTokens"><i class="fas fa-save"></i> រក្សាទុក</button>
                                    <p class="current-max-tokens mt-2">បច្ចុប្បន្ន: <?php echo htmlspecialchars($data['settings']['maxTokensPerUser'] ?? 3); ?> Tokens</p>
                                </div>
                                <div class="alert alert-info mb-3">អ្នកប្រើដែលមាន Token នៅក្នុងតារាងនេះ គឺ <strong>នៅសកម្ម</strong>។</div>
                                <div class="mb-3 d-flex flex-wrap align-items-center gap-2">
                                    <span>ជ្រើសរើសបង្ហាញ icon:</span>
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="showOnline"><i class="fas fa-circle text-primary"></i> បង្ហាញខៀវ</button>
                                    <button type="button" class="btn btn-outline-danger btn-sm" id="showOffline"><i class="fas fa-circle text-danger"></i> បង្ហាញក្រហម</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="showAll"><i class="fas fa-circle"></i> បង្ហាញទាំងអស់</button>
                                    <input type="text" id="searchTokens" class="form-control form-control-sm ms-2" placeholder="ស្វែងរកឈ្មោះ ឬ ID" style="max-width:200px;">
                                    <button type="button" class="btn btn-info btn-sm" id="searchTokensBtn"><i class="fas fa-search"></i> ស្វែងរក</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead><tr><th><i class="fas fa-user"></i> ឈ្មោះ</th><th><i class="fas fa-id-card"></i> ID</th><th><i class="fas fa-key"></i> Token</th><th><i class="fas fa-clock"></i> ពេលវេលាចូល</th><th><i class="fas fa-cogs"></i> សកម្មភាព</th></tr></thead>
                                        <tbody id="tokensTable">
                                            <?php
                                            foreach ($data['users'] as $user) {
                                                $userTokens = [];
                                                foreach ($data['activeUsers'] as $token => $userData) {
                                                    if ($userData['id'] === $user['id']) {
                                                        $userTokens[] = ['token' => $token, 'loginTime' => $userData['loginTime']];
                                                    }
                                                }
                                                if (empty($userTokens)) {
                                                    echo '<tr class="offline-row"><td><i class="fas fa-circle text-danger" title="Offline" style="font-size: 0.9em; margin-right: 5px;"></i>' . htmlspecialchars($user['username']) . '</td><td>' . htmlspecialchars($user['id']) . '</td><td>-</td><td>-</td><td><span class="text-muted">មិនមាន Token</span></td></tr>';
                                                } else {
                                                    foreach ($userTokens as $tk) {
                                                        echo '<tr class="online-row"><td><i class="fas fa-circle text-primary" title="Online" style="font-size: 0.9em; margin-right: 5px;"></i>' . htmlspecialchars($user['username']) . '</td><td>' . htmlspecialchars($user['id']) . '</td><td>' . htmlspecialchars($tk['token']) . '</td><td>' . htmlspecialchars($tk['loginTime']) . '</td><td><div class="action-buttons"><button class="btn btn-danger btn-sm revoke-token" data-token="' . htmlspecialchars($tk['token']) . '"><i class="fas fa-trash"></i> លុប</button></div></td></tr>';
                                                    }
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </div> <!-- .main-content-inner -->
            </main>
        </div> <!-- .page-wrapper -->
    
        <!-- Modals: ដាក់ Modal ទាំងអស់នៅទីនេះ -->
        <!-- Add Folder Modal -->
        <div class="modal fade" id="addFolderModal" tabindex="-1" aria-labelledby="addFolderModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title" id="addFolderModalLabel">បន្ថែម Folder</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <form id="addFolderForm"><div class="mb-3"><label for="folderName" class="form-label">ឈ្មោះ Folder</label><input type="text" class="form-control" id="folderName" required></div></form>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> បិទ</button><button type="button" class="btn btn-primary" id="saveFolder"><i class="fas fa-save"></i> រក្សាទុក</button></div>
                </div>
            </div>
        </div>

        <!-- Edit Folder Modal -->
        <div class="modal fade" id="editFolderModal" tabindex="-1" aria-labelledby="editFolderModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title" id="editFolderModalLabel">កែសម្រួល Folder</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <form id="editFolderForm"><input type="hidden" id="editFolderIndex"><div class="mb-3"><label for="editFolderName" class="form-label">ឈ្មោះ Folder</label><input type="text" class="form-control" id="editFolderName" required></div></form>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> បិទ</button><button type="button" class="btn btn-primary" id="updateFolder"><i class="fas fa-save"></i> ធ្វើបច្ចុប្បន្នភាព</button></div>
                </div>
            </div>
        </div>
        
        <!-- *** NEW CHANGE: Added Modals for Branch management *** -->
        <!-- Add Branch Modal -->
        <div class="modal fade" id="addBranchModal" tabindex="-1" aria-labelledby="addBranchModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title" id="addBranchModalLabel">បន្ថែមសាខា (ទីតាំងស្កេន)</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <form id="addBranchForm">
                            <div class="mb-3">
                                <label for="branchName" class="form-label">ឈ្មោះសាខា</label>
                                <input type="text" class="form-control" id="branchName" required>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> បិទ</button>
                        <button type="button" class="btn btn-primary" id="saveBranch"><i class="fas fa-save"></i> រក្សាទុក</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Branch Modal -->
        <div class="modal fade" id="editBranchModal" tabindex="-1" aria-labelledby="editBranchModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title" id="editBranchModalLabel">កែសម្រួលសាខា (ទីតាំងស្កេន)</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <form id="editBranchForm">
                            <input type="hidden" id="editBranchIndex">
                            <div class="mb-3">
                                <label for="editBranchName" class="form-label">ឈ្មោះសាខា</label>
                                <input type="text" class="form-control" id="editBranchName" required>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> បិទ</button>
                        <button type="button" class="btn btn-primary" id="updateBranch"><i class="fas fa-save"></i> ធ្វើបច្ចុប្បន្នភាព</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add User Modal -->
        <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title" id="addUserModalLabel">បន្ថែមអ្នកប្រើប្រាស់</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <form id="addUserForm">
                            <div class="row"><div class="col-md-6 mb-3"><label for="username" class="form-label">ឈ្មោះ</label><input type="text" class="form-control" id="username" required></div><div class="col-md-6 mb-3"><label for="id" class="form-label">ID</label><input type="text" class="form-control" id="id" required></div></div>
                            <div class="row"><div class="col-md-6 mb-3"><label for="department" class="form-label">នាយកដ្ឋាន</label><input type="text" class="form-control" id="department" required></div><div class="col-md-6 mb-3"><label for="position" class="form-label">តួនាទី</label><input type="text" class="form-control" id="position" required></div></div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="branch" class="form-label">ទីតាំងស្កេន</label>
                                    <!-- *** NEW CHANGE: Dynamically populated Branch dropdown *** -->
                                    <select class="form-control" id="branch" required>
                                        <option value="" disabled selected>ជ្រើសរើសសាខា</option>
                                        <?php foreach ($data['branches'] as $branch): ?>
                                        <option value="<?php echo htmlspecialchars($branch['name']); ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3"><label for="workplace" class="form-label">កន្លែងធ្វើការ</label><input type="text" class="form-control" id="workplace" required></div>
                            </div>
                            <div class="row"><div class="col-md-6 mb-3"><label for="folder" class="form-label">ប្រភេទ</label><select class="form-control" id="folder" required><option value="" disabled selected>ជ្រើសរើស ប្រភេទ</option><?php foreach ($data['folders'] as $folder): ?><option value="<?php echo htmlspecialchars($folder['name']); ?>"><?php echo htmlspecialchars($folder['name']); ?></option><?php endforeach; ?></select></div></div>
                            <h5>កំណត់ម៉ោងស្កេន</h5><h6>ស្កេនចូល</h6><div id="checkInRanges"></div><button type="button" class="btn btn-success mb-3" id="addCheckInRange"><i class="fas fa-plus"></i> បន្ថែមម៉ោងស្កេនចូល</button>
                            <h6>ស្កេនចេញ</h6><div id="checkOutRanges"></div><button type="button" class="btn btn-success mb-3" id="addCheckOutRange"><i class="fas fa-plus"></i> បន្ថែមម៉ោងស្កេនចេញ</button>
                        </form>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> បិទ</button><button type="button" class="btn btn-primary" id="saveUser"><i class="fas fa-save"></i> រក្សាទុក</button></div>
                </div>
            </div>
        </div>

        <!-- Edit User Modal -->
        <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title" id="editUserModalLabel">កែសម្រួលអ្នកប្រើប្រាស់</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <form id="editUserForm">
                            <input type="hidden" id="editIndex">
                            <div class="mb-3"><label for="editUsername" class="form-label">ឈ្មោះ</label><input type="text" class="form-control" id="editUsername" required></div>
                            <div class="mb-3"><label for="editId" class="form-label">ID</label><input type="text" class="form-control" id="editId" required></div>
                            <div class="mb-3"><label for="editDepartment" class="form-label">នាយកដ្ឋាន</label><input type="text" class="form-control" id="editDepartment" required></div>
                            <div class="mb-3"><label for="editPosition" class="form-label">តួនាទី</label><input type="text" class="form-control" id="editPosition" required></div>
                            <div class="mb-3">
                                <label for="editBranch" class="form-label">ទីតាំងស្កេន</label>
                                <!-- *** NEW CHANGE: Dynamically populated Branch dropdown *** -->
                                <select class="form-control" id="editBranch" required>
                                    <option value="" disabled>ជ្រើសរើសសាខា</option>
                                    <?php foreach ($data['branches'] as $branch): ?>
                                    <option value="<?php echo htmlspecialchars($branch['name']); ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3"><label for="editWorkplace" class="form-label">កន្លែងធ្វើការ</label><input type="text" class="form-control" id="editWorkplace" required></div>
                            <div class="mb-3"><label for="editFolder" class="form-label">ប្រភេទ</label><select class="form-control" id="editFolder" required><option value="" disabled>ជ្រើសរើស ប្រភេទ</option><?php foreach ($data['folders'] as $folder): ?><option value="<?php echo htmlspecialchars($folder['name']); ?>"><?php echo htmlspecialchars($folder['name']); ?></option><?php endforeach; ?></select></div>
                            <h5>កំណត់ម៉ោងស្កេន</h5><h6>ស្កេនចូល</h6><div id="editCheckInRanges"></div><button type="button" class="btn btn-success mb-3" id="editAddCheckInRange"><i class="fas fa-plus"></i> បន្ថែមម៉ោងស្កេនចូល</button>
                            <h6>ស្កេនចេញ</h6><div id="editCheckOutRanges"></div><button type="button" class="btn btn-success mb-3" id="editAddCheckOutRange"><i class="fas fa-plus"></i> បន្ថែមម៉ោងស្កេនចេញ</button>
                        </form>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> បិទ</button><button type="button" class="btn btn-primary" id="updateUser"><i class="fas fa-save"></i> ធ្វើបច្ចុប្បន្នភាព</button></div>
                </div>
            </div>
        </div>

        <!-- Add Location Modal -->
        <div class="modal fade" id="addLocationModal" tabindex="-1" aria-labelledby="addLocationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title" id="addLocationModalLabel">បន្ថែមទីតាំង</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <div id="addMap" style="width: 100%; height: 300px; margin-bottom: 20px; border-radius: 12px;"></div>
                        <form id="addLocationForm">
                            <input type="hidden" id="locationId" value="">
                            <div class="mb-3"><label for="locationName" class="form-label">ឈ្មោះទីតាំង</label><input type="text" class="form-control" id="locationName" required></div>
                            <div class="mb-3">
                                <label for="locationBranch" class="form-label">ទីតាំងស្កេន</label>
                                <!-- *** NEW CHANGE: Dynamically populated Branch dropdown *** -->
                                <select class="form-control" id="locationBranch" required>
                                    <option value="" disabled selected>ជ្រើសរើសសាខា</option>
                                    <?php foreach ($data['branches'] as $branch): ?>
                                    <option value="<?php echo htmlspecialchars($branch['name']); ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3"><label for="latitude" class="form-label">Latitude</label><input type="number" step="0.00001" class="form-control" id="latitude" required></div>
                            <div class="mb-3"><label for="longitude" class="form-label">Longitude</label><input type="number" step="0.00001" class="form-control" id="longitude" required></div>
                            <h5>អ្នកប្រើប្រាស់ដែលអនុញ្ញាត</h5><div id="allowedUsersContainer"></div>
                            <button type="button" class="btn btn-success mb-3" id="addUserEntry"><i class="fas fa-plus"></i> បន្ថែមអ្នកប្រើប្រាស់</button>
                        </form>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> បិទ</button><button type="button" class="btn btn-primary" id="saveLocation"><i class="fas fa-save"></i> រក្សាទុក</button></div>
                </div>
            </div>
        </div>

        <!-- Edit Location Modal -->
        <div class="modal fade" id="editLocationModal" tabindex="-1" aria-labelledby="editLocationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title" id="editLocationModalLabel">កែសម្រួលទីតាំង</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <div id="editMap" style="width: 100%; height: 300px; margin-bottom: 20px; border-radius: 12px;"></div>
                        <form id="editLocationForm">
                            <input type="hidden" id="editLocationIndex"><input type="hidden" id="editLocationId">
                            <div class="mb-3"><label for="editLocationName" class="form-label">ឈ្មោះទីតាំង</label><input type="text" class="form-control" id="editLocationName" required></div>
                            <div class="mb-3">
                                <label for="editLocationBranch" class="form-label">ទីតាំងស្កេន</label>
                                <!-- *** NEW CHANGE: Dynamically populated Branch dropdown *** -->
                                <select class="form-control" id="editLocationBranch" required>
                                    <option value="" disabled>ជ្រើសរើសសាខា</option>
                                     <?php foreach ($data['branches'] as $branch): ?>
                                    <option value="<?php echo htmlspecialchars($branch['name']); ?>"><?php echo htmlspecialchars($branch['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3"><label for="editLatitude" class="form-label">Latitude</label><input type="number" step="0.00001" class="form-control" id="editLatitude" required></div>
                            <div class="mb-3"><label for="editLongitude" class="form-label">Longitude</label><input type="number" step="0.00001" class="form-control" id="editLongitude" required></div>
                            <h5>អ្នកប្រើប្រាស់ដែលអនុញ្ញាត</h5><div id="editAllowedUsersContainer"></div><button type="button" class="btn btn-success mb-3" id="editAddUserEntry"><i class="fas fa-plus"></i>បន្ថែមអ្នកប្រើប្រាស់</button>
                        </form>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> បិទ</button><button type="button" class="btn btn-primary" id="updateLocation"><i class="fas fa-save"></i> ធ្វើបច្ចុប្បន្នភាព</button></div>
                </div>
            </div>
        </div>
        
    <?php endif; ?>

    <button class="back-to-top" id="back-to-top" title="Go to top"><i class="fas fa-arrow-up"></i></button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <script>
        /********** NEW SIDEBAR SCRIPT (កូដ JavaScript ថ្មីសម្រាប់ Sidebar) **********/
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.sidebar-nav .nav-link, .quick-link-btn');
            const contentSections = document.querySelectorAll('.content-section');
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            const body = document.body;
            const backToTopButton = document.getElementById('back-to-top');

            function switchTab(targetId) {
                contentSections.forEach(section => section.classList.remove('active'));
                
                const targetSection = document.getElementById(targetId);
                if (targetSection) {
                    targetSection.classList.add('active');
                }
                
                document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
                    link.classList.remove('active');
                    if (link.dataset.target === targetId) {
                        link.classList.add('active');
                    }
                });

                history.replaceState(null, null, '#' + targetId);
                document.getElementById('main-content').scrollTop = 0;
            }

            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetId = this.dataset.target;
                    switchTab(targetId);
                    
                    if (body.classList.contains('sidebar-is-open')) {
                        body.classList.remove('sidebar-is-open');
                        sidebar.classList.remove('open');
                    }
                });
            });

            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('open');
                    body.classList.toggle('sidebar-is-open');
                });
            }
            
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('open');
                    body.classList.remove('sidebar-is-open');
                });
            }

            let initialTarget = window.location.hash.substring(1);
            if (!document.getElementById(initialTarget)) {
                initialTarget = 'dashboard';
            }
            switchTab(initialTarget);

            const mainContentArea = document.getElementById('main-content');
            if (mainContentArea) {
                mainContentArea.addEventListener('scroll', () => {
                     if (mainContentArea.scrollTop > 200) {
                        backToTopButton.style.display = 'flex';
                    } else {
                        backToTopButton.style.display = 'none';
                    }
                });
            }
            
            backToTopButton.addEventListener('click', () => {
                if(mainContentArea) mainContentArea.scrollTo({ top: 0, behavior: 'smooth' });
                else window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });

        /********** ORIGINAL JAVASCRIPT (កូដ JavaScript ដើមរបស់អ្នក) **********/
        <?php if ($loggedIn): ?>
        const users = <?php echo json_encode($data['users']); ?>;
        const activeUsers = <?php echo json_encode($data['activeUsers']); ?>;
        const allowedLocations = <?php echo json_encode($data['allowedLocations']); ?>;
        const folders = <?php echo json_encode($data['folders']); ?>;
        // *** NEW CHANGE: Added branches data to JavaScript ***
        const branches = <?php echo json_encode($data['branches']); ?>;
        const apiUrl = '<?php echo $apiUrl; ?>';

        // --- User filter and pagination logic ---
        document.addEventListener('DOMContentLoaded', function () {
            const rowsPerPage = 10;
            let currentPage = 1;
            let filteredRows = [];

            function displayPage(page) {
                const start = (page - 1) * rowsPerPage;
                const end = start + rowsPerPage;
                const allRows = filteredRows.length ? filteredRows : Array.from(document.querySelectorAll('#usersTable tr'));

                document.querySelectorAll('#usersTable tr').forEach(row => row.style.display = 'none');
                allRows.forEach((row, index) => {
                    if (index >= start && index < end) row.style.display = '';
                });
                
                const totalPages = Math.max(1, Math.ceil(allRows.length / rowsPerPage));
                document.getElementById('currentPage').textContent = page;
                document.getElementById('totalPages').textContent = totalPages;
                document.getElementById('prevPage').disabled = page === 1;
                document.getElementById('nextPage').disabled = page === totalPages || totalPages === 0;
            }

            function filterUsers() {
                const searchTerm = document.getElementById('searchUsers').value.toLowerCase();
                const filterFolder = document.getElementById('filterFolder').value;
                const filterBranch = document.getElementById('filterBranch').value;
                const rows = Array.from(document.querySelectorAll('#usersTable tr'));
                filteredRows = [];

                rows.forEach(row => {
                    const tds = row.querySelectorAll('td');
                    const text = row.textContent.toLowerCase();
                    const folder = tds.length > 6 ? tds[6].textContent.trim() : '';
                    const branch = tds.length > 4 ? tds[4].textContent.trim() : '';
                    const matchesSearch = text.includes(searchTerm);
                    const matchesFolder = !filterFolder || folder === filterFolder;
                    const matchesBranch = !filterBranch || branch === filterBranch;

                    if (matchesSearch && matchesFolder && matchesBranch) {
                        filteredRows.push(row);
                    }
                });

                currentPage = 1;
                displayPage(currentPage);
            }

            document.getElementById('prevPage').addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    displayPage(currentPage);
                }
            });

            document.getElementById('nextPage').addEventListener('click', () => {
                const totalRows = filteredRows.length ? filteredRows.length : document.querySelectorAll('#usersTable tr').length;
                if (currentPage < Math.ceil(totalRows / rowsPerPage)) {
                    currentPage++;
                    displayPage(currentPage);
                }
            });

            document.getElementById('searchUsers').addEventListener('input', filterUsers);
            document.getElementById('filterFolder').addEventListener('change', filterUsers);
            document.getElementById('filterBranch').addEventListener('change', filterUsers);

            // Initial display
            filterUsers();
        });
        
        // --- QR Code generation ---
        document.addEventListener('DOMContentLoaded', function () {
            const logoUrl = "https://i.ibb.co/9HP7nkCV/Logo-Van-Van-3.png";
            const bgLogoUrl = "https://i.ibb.co/HLzckTJ7/Logo-Van-Van-1.jpg";
            const whiteLogoUrl = "https://i.ibb.co/HLzckTJ7/Logo-Van-Van-1.jpg";
            const qrSize = 500;
            const logoSize = 120;
            const bgLogoSize = 320;
            const whiteLogoSize = 100;

            document.querySelectorAll('canvas[id^="qr-canvas-"]').forEach(function(canvas) {
                canvas.width = qrSize;
                canvas.height = qrSize;
                canvas.style.width = "100px";
                canvas.style.height = "100px";
                const row = canvas.closest('td');
                const qrData = row.querySelector('.qr-data').value;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = "#fff";
                ctx.fillRect(0, 0, qrSize, qrSize);

                const bgLogoImg = new Image();
                bgLogoImg.crossOrigin = "anonymous";
                bgLogoImg.onload = function () {
                    ctx.save();
                    ctx.globalAlpha = 0.13;
                    ctx.drawImage(bgLogoImg, (qrSize-bgLogoSize)/2, (qrSize-bgLogoSize)/2, bgLogoSize, bgLogoSize);
                    ctx.restore();

                    new QRious({ element: canvas, value: qrData, size: qrSize, level: 'H', background: null });

                    const whiteLogoImg = new Image();
                    whiteLogoImg.crossOrigin = "anonymous";
                    whiteLogoImg.onload = function () {
                        ctx.save();
                        ctx.beginPath();
                        ctx.arc(canvas.width/2, canvas.height/2, whiteLogoSize/2, 0, 2 * Math.PI, false);
                        ctx.closePath();
                        ctx.clip();
                        ctx.globalAlpha = 1.0;
                        ctx.drawImage(whiteLogoImg, (canvas.width-whiteLogoSize)/2, (canvas.height-whiteLogoSize)/2, whiteLogoSize, whiteLogoSize);
                        ctx.restore();

                        const logoImg = new Image();
                        logoImg.crossOrigin = "anonymous";
                        logoImg.onload = function () {
                            ctx.save();
                            ctx.beginPath();
                            ctx.arc(canvas.width/2, canvas.height/2, logoSize/2, 0, 2 * Math.PI, false);
                            ctx.closePath();
                            ctx.clip();
                            ctx.drawImage(logoImg, (canvas.width-logoSize)/2, (canvas.height-logoSize)/2, logoSize, logoSize);
                            ctx.restore();
                        };
                        logoImg.src = logoUrl;
                    };
                    whiteLogoImg.src = whiteLogoUrl;
                };
                bgLogoImg.src = bgLogoUrl;
            });

            document.querySelectorAll('.download-qr-png').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const idx = btn.dataset.index;
                    const canvas = document.getElementById('qr-canvas-' + idx);
                    const name = canvas.closest('td').querySelector('.qr-name').value || 'location';
                    const link = document.createElement('a');
                    link.href = canvas.toDataURL('image/png');
                    link.download = 'QR_' + name.replace(/[^a-zA-Z0-9_]/g, '_') + '.png';
                    link.click();
                });
            });

            document.querySelectorAll('.download-qr-svg').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const idx = btn.dataset.index;
                    const canvas = document.getElementById('qr-canvas-' + idx);
                    const name = canvas.closest('td').querySelector('.qr-name').value || 'location';
                    const pngDataUrl = canvas.toDataURL('image/png');
                    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${qrSize}" height="${qrSize}"><image href="${pngDataUrl}" x="0" y="0" width="${qrSize}" height="${qrSize}"/></svg>`;
                    const blob = new Blob([svg], {type: 'image/svg+xml'});
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'QR_' + name.replace(/[^a-zA-Z0-9_]/g, '_') + '.svg';
                    document.body.appendChild(link);
                    link.click();
                    setTimeout(() => { document.body.removeChild(link); URL.revokeObjectURL(url); }, 100);
                });
            });
        });

        // Token table search/filter
        function filterTokensTable() {
            const search = document.getElementById('searchTokens').value.trim().toLowerCase();
            document.querySelectorAll('#tokensTable tr').forEach(tr => {
            const tds = tr.querySelectorAll('td');
            const name = tds[0]?.textContent?.toLowerCase() || '';
            const id = tds[1]?.textContent?.toLowerCase() || '';
            if (!search || name.includes(search) || id.includes(search)) {
                tr.style.display = '';
            } else {
                tr.style.display = 'none';
            }
            });
        }
        document.getElementById('searchTokens').addEventListener('input', filterTokensTable);
        document.getElementById('searchTokensBtn').addEventListener('click', filterTokensTable);

        // --- Token icon filter with localStorage persistence ---
        function setTokenFilter(filter) {
            localStorage.setItem('tokenIconFilter', filter);
            applyTokenFilter(filter);
        }
        function applyTokenFilter(filter) {
            document.querySelectorAll('#tokensTable tr').forEach(tr => {
                if (filter === 'online') {
                    tr.style.display = tr.classList.contains('online-row') ? '' : 'none';
                } else if (filter === 'offline') {
                    tr.style.display = tr.classList.contains('offline-row') ? '' : 'none';
                } else {
                    tr.style.display = '';
                }
            });
            // Active button style
            document.getElementById('showOnline').classList.toggle('active', filter === 'online');
            document.getElementById('showOffline').classList.toggle('active', filter === 'offline');
            document.getElementById('showAll').classList.toggle('active', !filter || filter === 'all');
        }
        document.getElementById('showOnline').onclick = function() { setTokenFilter('online'); };
        document.getElementById('showOffline').onclick = function() { setTokenFilter('offline'); };
        document.getElementById('showAll').onclick = function() { setTokenFilter('all'); };
        // On page load, restore filter
        document.addEventListener('DOMContentLoaded', function() {
            const filter = localStorage.getItem('tokenIconFilter') || 'all';
            applyTokenFilter(filter);
        });
        
        // Auto update meters when tolerance changes
        function updateToleranceMeters(input) {
            const val = parseFloat(input.value);
            const meters = isNaN(val) ? 0 : Math.round(val * 111000);
            const span = input.closest('.input-group').querySelector('.tolerance-meters');
            if (span) span.textContent = `≈ ${meters}m`;
        }
        document.addEventListener('input', function (e) {
            if (e.target.classList.contains('user-tolerance')) {
                updateToleranceMeters(e.target);
            }
        });
        document.querySelectorAll('.user-tolerance').forEach(updateToleranceMeters);

        // When adding new user entry, also add the meter display
        document.getElementById('addUserEntry').addEventListener('click', function () {
            setTimeout(() => { document.querySelectorAll('#allowedUsersContainer .user-tolerance').forEach(updateToleranceMeters); }, 10);
        });
        document.getElementById('editAddUserEntry').addEventListener('click', function () {
             setTimeout(() => { document.querySelectorAll('#editAllowedUsersContainer .user-tolerance').forEach(updateToleranceMeters); }, 10);
        });


        async function sendRequest(action, data) {
            if (!apiUrl) {
                throw new Error('API URL មិនត្រូវបានកំណត់!');
            }

            try {
                document.body.classList.add('loading');
                const response = await fetch(`${apiUrl}?action=${action}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(data)
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP error! Status: ${response.status}, Response: ${errorText}`);
                }

                const result = await response.json();
                if (result.status !== 'success') {
                    console.error(`API Error for ${action}:`, result.message);
                }
                return result;
            } catch (error) {
                console.error(`Error in sendRequest (${action}):`, error);
                Swal.fire('កំហុសក្នុងការតភ្ជាប់!', 'មិនអាចទាក់ទងទៅម៉ាស៊ីនមេបានទេ។ សូមពិនិត្យមើលការតភ្ជាប់របស់អ្នក។', 'error');
                return { status: 'error', message: error.message };
            } finally {
                document.body.classList.remove('loading');
            }
        }
        
        // Handle maxTokensPerUser save
        document.getElementById('saveMaxTokens').addEventListener('click', async () => {
            const maxTokens = parseInt(document.getElementById('maxTokensPerUser').value);
            if (isNaN(maxTokens) || maxTokens < 1) {
                Swal.fire('កំហុស!', 'សូមបញ្ចូលចំនួន Token អតិបរមាត្រឹមត្រូវ (ចំនួនគត់ យ៉ាងហោចណាស់ ១)!', 'error');
                return;
            }

            try {
                const response = await sendRequest('set_max_tokens', { maxTokensPerUser: maxTokens });
                if (response && response.status === 'success') {
                    Swal.fire('ជោគជ័យ!', 'ចំនួន Token អតិបរមាត្រូវបានធ្វើបច្ចុប្បន្នភាព!', 'success').then(() => location.reload());
                } else {
                    throw new Error(response.message || 'មានបញ្ហាក្នុងការធ្វើបច្ចុប្បន្នភាពចំនួន Token អតិបរមា');
                }
            } catch (error) {
                Swal.fire('កំហុស!', error.message, 'error');
            }
        });

        function timeToMinutes(time) {
            if (!time) return NaN;
            const [hours, minutes] = time.split(':').map(Number);
            return hours * 60 + minutes;
        }

        function minutesToTime(minutes) {
            if (isNaN(minutes)) return '';
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;
        }

        function addTimeRange(containerId, range = { start: '', end: '', status: 'Good' }) {
            const html = `
                <div class="time-range mb-3 p-3">
                    <div class="row align-items-end">
                        <div class="col-md-4"><label class="form-label">ចាប់ផ្តើម</label><input type="time" class="form-control start-time" value="${minutesToTime(range.start)}" required></div>
                        <div class="col-md-4"><label class="form-label">បញ្ចប់</label><input type="time" class="form-control end-time" value="${minutesToTime(range.end)}" required></div>
                        <div class="col-md-3"><label class="form-label">ស្ថានភាព</label><select class="form-control status" required><option value="Good" ${range.status === 'Good' ? 'selected' : ''}>Good</option><option value="Late" ${range.status === 'Late' ? 'selected' : ''}>Late</option></select></div>
                        <div class="col-md-1"><button type="button" class="btn btn-danger btn-sm remove-range"><i class="fas fa-trash"></i></button></div>
                    </div>
                </div>`;
            document.getElementById(containerId).insertAdjacentHTML('beforeend', html);
            const newRange = document.getElementById(containerId).lastElementChild;
            newRange.querySelector('.remove-range').addEventListener('click', () => newRange.remove());
        }

        document.getElementById('addCheckInRange').addEventListener('click', () => addTimeRange('checkInRanges'));
        document.getElementById('addCheckOutRange').addEventListener('click', () => addTimeRange('checkOutRanges'));
        document.getElementById('editAddCheckInRange').addEventListener('click', () => addTimeRange('editCheckInRanges'));
        document.getElementById('editAddCheckOutRange').addEventListener('click', () => addTimeRange('editCheckOutRanges'));


        document.getElementById('saveUser').addEventListener('click', async () => {
            const checkInRanges = Array.from(document.querySelectorAll('#checkInRanges .time-range')).map(range => {
                return { start: timeToMinutes(range.querySelector('.start-time').value), end: timeToMinutes(range.querySelector('.end-time').value), status: range.querySelector('.status').value };
            }).filter(r => !isNaN(r.start) && !isNaN(r.end) && r.start < r.end);

            const checkOutRanges = Array.from(document.querySelectorAll('#checkOutRanges .time-range')).map(range => {
                return { start: timeToMinutes(range.querySelector('.start-time').value), end: timeToMinutes(range.querySelector('.end-time').value), status: range.querySelector('.status').value };
            }).filter(r => !isNaN(r.start) && !isNaN(r.end) && r.start < r.end);

            if (checkInRanges.length === 0 || checkOutRanges.length === 0) {
                Swal.fire('កំហុស!', 'ត្រូវការយ៉ាងហោចណាស់មួយជួរម៉ោងសម្រាប់ស្កេនចូល និងស្កេនចេញ!', 'error');
                return;
            }

            const user = {
                username: document.getElementById('username').value.trim(),
                id: document.getElementById('id').value.trim(),
                department: document.getElementById('department').value.trim(),
                position: document.getElementById('position').value.trim(),
                branch: document.getElementById('branch').value,
                workplace: document.getElementById('workplace').value.trim(),
                folder: document.getElementById('folder').value,
                timeSettings: { check_in_ranges: checkInRanges, check_out_ranges: checkOutRanges }
            };

            if (!user.username || !user.id || !user.department || !user.position || !user.branch || !user.workplace || !user.folder) {
                Swal.fire('កំហុស!', 'សូមបញ្ចូលទិន្នន័យឱ្យគ្រប់គ្រាន់!', 'error');
                return;
            }

            const response = await sendRequest('add_user', user);
            if (response && response.status === 'success') {
                Swal.fire('ជោគជ័យ!', 'អ្នកប្រើប្រាស់ត្រូវបានបន្ថែម!', 'success').then(() => location.reload());
            } else {
                 Swal.fire('កំហុស!', response.message || 'មានបញ្ហាក្នុងការបន្ថែមអ្នកប្រើប្រាស់', 'error');
            }
        });

        document.querySelectorAll('.edit-user').forEach(button => {
            button.addEventListener('click', () => {
                const index = button.dataset.index;
                const user = users[index];
                document.getElementById('editIndex').value = index;
                document.getElementById('editUsername').value = user.username;
                document.getElementById('editId').value = user.id;
                document.getElementById('editDepartment').value = user.department || '';
                document.getElementById('editPosition').value = user.position || '';
                document.getElementById('editBranch').value = user.branch || '';
                document.getElementById('editWorkplace').value = user.workplace || 'N/A';
                document.getElementById('editFolder').value = user.folder || '';
                document.getElementById('editCheckInRanges').innerHTML = '';
                document.getElementById('editCheckOutRanges').innerHTML = '';
                (user.timeSettings?.check_in_ranges || []).forEach(range => addTimeRange('editCheckInRanges', range));
                (user.timeSettings?.check_out_ranges || []).forEach(range => addTimeRange('editCheckOutRanges', range));
            });
        });

        document.getElementById('updateUser').addEventListener('click', async () => {
            const checkInRanges = Array.from(document.querySelectorAll('#editCheckInRanges .time-range')).map(range => {
                return { start: timeToMinutes(range.querySelector('.start-time').value), end: timeToMinutes(range.querySelector('.end-time').value), status: range.querySelector('.status').value };
            }).filter(r => !isNaN(r.start) && !isNaN(r.end) && r.start < r.end);

            const checkOutRanges = Array.from(document.querySelectorAll('#editCheckOutRanges .time-range')).map(range => {
                return { start: timeToMinutes(range.querySelector('.start-time').value), end: timeToMinutes(range.querySelector('.end-time').value), status: range.querySelector('.status').value };
            }).filter(r => !isNaN(r.start) && !isNaN(r.end) && r.start < r.end);


            if (checkInRanges.length === 0 || checkOutRanges.length === 0) {
                Swal.fire({ icon: 'error', title: 'កំហុស!', text: 'ត្រូវការយ៉ាងហោចណាស់មួយជួរម៉ោងសម្រាប់ស្កេនចូល និងស្កេនចេញ!' });
                return;
            }

            const user = {
                index: parseInt(document.getElementById('editIndex').value),
                username: document.getElementById('editUsername').value.trim(),
                id: document.getElementById('editId').value.trim(),
                department: document.getElementById('editDepartment').value.trim(),
                position: document.getElementById('editPosition').value.trim(),
                branch: document.getElementById('editBranch').value,
                workplace: document.getElementById('editWorkplace').value.trim(),
                folder: document.getElementById('editFolder').value,
                timeSettings: { check_in_ranges: checkInRanges, check_out_ranges: checkOutRanges }
            };

            if (!user.username || !user.id || !user.department || !user.position || !user.branch || !user.workplace || !user.folder) {
                Swal.fire({ icon: 'error', title: 'កំហុស!', text: 'សូមបញ្ចូលទិន្នន័យឱ្យគ្រប់គ្រាន់!' });
                return;
            }

            const response = await sendRequest('edit_user', user);
            if (response && response.status === 'success') {
                Swal.fire({ icon: 'success', title: 'ជោគជ័យ!', text: 'អ្នកប្រើប្រាស់ត្រូវបានធ្វើបច្ចុប្បន្នភាព!' }).then(() => location.reload());
            } else {
                 Swal.fire('កំហុស!', response.message || 'មានបញ្ហាក្នុងការកែសម្រួលអ្នកប្រើប្រាស់', 'error');
            }
        });

        document.querySelectorAll('.delete-user').forEach(button => {
            button.addEventListener('click', () => {
                Swal.fire({
                    title: 'តើអ្នកប្រាកដទេ?', text: 'តើអ្នកប្រាកដទេថាចង់លុបអ្នកប្រើប្រាស់នេះ?', icon: 'warning', showCancelButton: true, confirmButtonText: 'បាទ/ចាស លុប!', cancelButtonText: 'ទេ បោះបង់'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        const response = await sendRequest('delete_user', { index: button.dataset.index });
                        if (response && response.status === 'success') {
                            Swal.fire('បានលុប!', 'អ្នកប្រើប្រាស់ត្រូវបានលុបដោយជោគជ័យ។', 'success').then(() => location.reload());
                        } else {
                             Swal.fire('កំហុស!', response.message || 'មានបញ្ហាក្នុងការលុបអ្នកប្រើប្រាស់', 'error');
                        }
                    }
                });
            });
        });

        document.querySelectorAll('.duplicate-user').forEach(button => {
            button.addEventListener('click', () => {
                Swal.fire({
                    title: 'តើអ្នកប្រាកដទេ?', text: 'តើអ្នកប្រាកដទេថាចង់ចម្លងអ្នកប្រើប្រាស់នេះ?', icon: 'question', showCancelButton: true, confirmButtonText: 'បាទ/ចាស ចម្លង!', cancelButtonText: 'ទេ បោះបង់'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        const index = button.dataset.index;
                        const user = { ...users[index] };
                        let newId = `${user.id}-copy`;
                        let suffix = 1;
                        while (users.some(u => u.id === newId)) {
                            newId = `${user.id}-copy${suffix++}`;
                        }
                        user.id = newId;
                        user.username += ` (ចម្លង ${suffix > 1 ? suffix-1 : ''})`;
                        const response = await sendRequest('add_user', user);
                        if (response && response.status === 'success') {
                            Swal.fire('បានចម្លង!', 'អ្នកប្រើប្រាស់ត្រូវបានចម្លងដោយជោគជ័យ។', 'success').then(() => location.reload());
                        } else {
                             Swal.fire('កំហុស!', response.message || 'មានបញ្ហាក្នុងការចម្លងអ្នកប្រើប្រាស់', 'error');
                        }
                    }
                });
            });
        });

        // Folder management
        document.getElementById('saveFolder').addEventListener('click', async () => {
            const folderName = document.getElementById('folderName').value.trim();
            if (!folderName) { Swal.fire('កំហុស!', 'សូមបញ្ចូលឈ្មោះ Folder!', 'error'); return; }
            const response = await sendRequest('add_folder', { id: Date.now().toString(), name: folderName });
            if (response && response.status === 'success') {
                Swal.fire('ជោគជ័យ!', 'Folder ត្រូវបានបន្ថែម!', 'success').then(() => location.reload());
            } else {
                Swal.fire('កំហុស!', response.message || 'មានបញ្ហាក្នុងការរក្សាទុក Folder', 'error');
            }
        });

        document.querySelectorAll('.edit-folder').forEach(button => {
            button.addEventListener('click', () => {
                const index = button.dataset.index;
                const folder = folders[index];
                document.getElementById('editFolderIndex').value = index;
                document.getElementById('editFolderName').value = folder.name;
            });
        });

        document.getElementById('updateFolder').addEventListener('click', async () => {
            const index = document.getElementById('editFolderIndex').value;
            const folderName = document.getElementById('editFolderName').value.trim();
            if (!folderName) { Swal.fire('កំហុស!', 'សូមបញ្ចូលឈ្មោះ Folder!', 'error'); return; }
            const oldFolderName = folders[index].name;
            const response = await sendRequest('edit_folder', { index: parseInt(index), id: folders[index].id, name: folderName });
            if (response && response.status === 'success') {
                await sendRequest('update_users_folder', { oldName: oldFolderName, newName: folderName });
                Swal.fire('ជោគជ័យ!', 'Folder ត្រូវបានធ្វើបច្ចុប្បន្នភាព!', 'success').then(() => location.reload());
            } else {
                Swal.fire('កំហុស!', response.message || 'មានបញ្ហាក្នុងការធ្វើបច្ចុប្បន្នភាព Folder', 'error');
            }
        });

        document.querySelectorAll('.delete-folder').forEach(button => {
            button.addEventListener('click', () => {
                const index = button.dataset.index;
                const folderName = folders[index].name;
                const userCount = users.filter(u => u.folder === folderName).length;
                Swal.fire({
                    title: 'តើអ្នកប្រាកដទេ?', text: userCount > 0 ? `Folder នេះមានអ្នកប្រើ ${userCount} នាក់។ ការលុបនឹងផ្លាស់ប្តូរអ្នកប្រើទាំងនេះទៅជា "មិនបានបញ្ជាក់"។ តើអ្នកប្រាកដទេ?` : 'តើអ្នកប្រាកដទេថាចង់លុប Folder នេះ?', icon: 'warning', showCancelButton: true, confirmButtonText: 'បាទ/ចាស លុប!', cancelButtonText: 'ទេ បោះបង់'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        const response = await sendRequest('delete_folder', { index: parseInt(index), name: folderName });
                        if (response && response.status === 'success') {
                            Swal.fire('បានលុប!', 'Folder ត្រូវបានលុបដោយជោគជ័យ។', 'success').then(() => location.reload());
                        } else {
                            Swal.fire('កំហុស!', response.message || 'មានបញ្ហាក្នុងការលុប Folder', 'error');
                        }
                    }
                });
            });
        });

        // *** NEW CHANGE: JavaScript for Branch Management ***
        // --- START: BRANCH MANAGEMENT ---
        document.getElementById('saveBranch').addEventListener('click', async () => {
            const branchName = document.getElementById('branchName').value.trim();
            if (!branchName) {
                Swal.fire('កំហុស!', 'សូមបញ្ចូលឈ្មោះសាខា!', 'error');
                return;
            }
            const response = await sendRequest('add_branch', { id: Date.now().toString(), name: branchName });
            if (response && response.status === 'success') {
                Swal.fire('ជោគជ័យ!', 'សាខាត្រូវបានបន្ថែម!', 'success').then(() => location.reload());
            } else {
                Swal.fire('កំហុស!', response.message || 'មានបញ្ហាក្នុងការរក្សាទុកសាខា', 'error');
            }
        });

        document.querySelectorAll('.edit-branch').forEach(button => {
            button.addEventListener('click', () => {
                const index = button.dataset.index;
                const branch = branches[index];
                document.getElementById('editBranchIndex').value = index;
                document.getElementById('editBranchName').value = branch.name;
            });
        });

        document.getElementById('updateBranch').addEventListener('click', async () => {
            const index = document.getElementById('editBranchIndex').value;
            const branchName = document.getElementById('editBranchName').value.trim();
            if (!branchName) {
                Swal.fire('កំហុស!', 'សូមបញ្ចូលឈ្មោះសាខា!', 'error');
                return;
            }
            const oldBranchName = branches[index].name;
            const response = await sendRequest('edit_branch', { index: parseInt(index), id: branches[index].id, name: branchName, oldName: oldBranchName });
            if (response && response.status === 'success') {
                Swal.fire('ជោគជ័យ!', 'សាខាត្រូវបានធ្វើបច្ចុប្បន្នភាព!', 'success').then(() => location.reload());
            } else {
                Swal.fire('កំហុស!', response.message || 'មានបញ្ហាក្នុងការធ្វើបច្ចុប្បន្នភាពសាខា', 'error');
            }
        });

        document.querySelectorAll('.delete-branch').forEach(button => {
            button.addEventListener('click', () => {
                const index = button.dataset.index;
                const branchName = branches[index].name;
                const userCount = users.filter(u => u.branch === branchName).length;

                let warningText = 'តើអ្នកប្រាកដទេថាចង់លុបសាខានេះ?';
                if (userCount > 0) {
                    warningText = `សាខានេះមានអ្នកប្រើប្រាស់ ${userCount} នាក់បានកំណត់។ ការលុបសាខានេះអាចបណ្តាលឱ្យមានបញ្ហា។ តើអ្នកនៅតែចង់បន្ត?`;
                }

                Swal.fire({
                    title: 'តើអ្នកប្រាកដទេ?',
                    text: warningText,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'បាទ/ចាស លុប!',
                    cancelButtonText: 'ទេ បោះបង់'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        const response = await sendRequest('delete_branch', { index: parseInt(index) });
                        if (response && response.status === 'success') {
                            Swal.fire('បានលុប!', 'សាខាត្រូវបានលុបដោយជោគជ័យ។', 'success').then(() => location.reload());
                        } else {
                            Swal.fire('កំហុស!', response.message || 'មានបញ្ហាក្នុងការលុបសាខា', 'error');
                        }
                    }
                });
            });
        });
        // --- END: BRANCH MANAGEMENT ---

        // Token management
        document.querySelectorAll('.revoke-token').forEach(button => {
            button.addEventListener('click', () => {
                Swal.fire({
                    title: 'តើអ្នកប្រាកដទេ?', text: 'តើអ្នកប្រាកដទេថាចង់លុប Token នេះ?', icon: 'warning', showCancelButton: true, confirmButtonText: 'បាទ/ចាស លុប!', cancelButtonText: 'ទេ បោះបង់'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        const response = await sendRequest('revoke_token', { token: button.dataset.token });
                        if (response && response.status === 'success') {
                            Swal.fire('បានលុប', 'Token ត្រូវបានលុបដោយជោគជ័យ។', 'success').then(() => location.reload());
                        } else {
                            Swal.fire('កំហុស!', response.message || 'មានបញ្ហាក្នុងការលុប Token', 'error');
                        }
                    }
                });
            });
        });
        
        // --- START: LOCATION MANAGEMENT ---
        function addUserEntryToLocation(containerId, user = { user_id: '', tolerance: 0.001 }, excludedUserIds = []) {
            const container = document.getElementById(containerId);
            const userOptions = users
                .filter(u => !excludedUserIds.includes(u.id) || u.id === user.user_id)
                .map(u => `<option value="${u.id}" ${u.id === user.user_id ? 'selected' : ''}>${u.username} (${u.id})</option>`)
                .join('');

            const html = `
                <div class="user-entry mb-3 p-3" style="background: #f8f9fa; border-radius: 8px;">
                    <div class="row align-items-end">
                        <div class="col-md-5"><label class="form-label">អ្នកប្រើ</label><select class="form-control user-select" required><option value="">ជ្រើសរើស</option>${userOptions}</select></div>
                        <div class="col-md-5"><label class="form-label">Tolerance</label><div class="input-group"><input type="number" step="0.00001" class="form-control user-tolerance" value="${user.tolerance}" required><span class="input-group-text tolerance-meters">≈ ${Math.round(user.tolerance * 111000)}m</span></div></div>
                        <div class="col-md-2"><button type="button" class="btn btn-danger remove-user-entry w-100"><i class="fas fa-trash"></i></button></div>
                    </div>
                </div>`;
            container.insertAdjacentHTML('beforeend', html);
            const newEntry = container.lastElementChild;
            newEntry.querySelector('.remove-user-entry').addEventListener('click', () => newEntry.remove());
        }

        document.getElementById('addUserEntry').addEventListener('click', () => {
            const currentlySelectedIds = Array.from(document.querySelectorAll('#allowedUsersContainer .user-select'))
                .map(select => select.value)
                .filter(value => value);
            addUserEntryToLocation('allowedUsersContainer', undefined, currentlySelectedIds);
        });

        document.getElementById('editAddUserEntry').addEventListener('click', () => {
            const currentlySelectedIds = Array.from(document.querySelectorAll('#editAllowedUsersContainer .user-select'))
                .map(select => select.value)
                .filter(value => value);
            addUserEntryToLocation('editAllowedUsersContainer', undefined, currentlySelectedIds);
        });

        document.getElementById('saveLocation').addEventListener('click', async () => {
            const allowedUsers = Array.from(document.querySelectorAll('#allowedUsersContainer .user-entry')).map(entry => {
                return { user_id: entry.querySelector('.user-select').value, tolerance: parseFloat(entry.querySelector('.user-tolerance').value) };
            }).filter(u => u.user_id && !isNaN(u.tolerance));

            const location = {
                id: document.getElementById('locationId').value || Date.now().toString(),
                name: document.getElementById('locationName').value.trim(),
                branch: document.getElementById('locationBranch').value,
                latitude: parseFloat(document.getElementById('latitude').value),
                longitude: parseFloat(document.getElementById('longitude').value),
                users: allowedUsers
            };

            if (!location.name || !location.branch || isNaN(location.latitude) || isNaN(location.longitude)) {
                Swal.fire('កំហុស!', 'សូមបញ្ចូលទិន្នន័យឱ្យគ្រប់គ្រាន់!', 'error'); return;
            }
            const response = await sendRequest('add_location', location);
            if (response && response.status === 'success') {
                Swal.fire('ជោគជ័យ!', 'ទីតាំងត្រូវបានបន្ថែម!', 'success').then(() => location.reload());
            } else {
                Swal.fire('កំហុស!', response.message || 'មានបញ្ហាក្នុងការបន្ថែមទីតាំង', 'error');
            }
        });

        document.querySelectorAll('.edit-location').forEach(button => {
            button.addEventListener('click', () => {
                const index = button.dataset.index;
                const loc = allowedLocations[index];
                document.getElementById('editLocationIndex').value = index;
                document.getElementById('editLocationId').value = loc.id || '';
                document.getElementById('editLocationName').value = loc.name || '';
                document.getElementById('editLocationBranch').value = loc.branch || '';
                document.getElementById('editLatitude').value = loc.latitude;
                document.getElementById('editLongitude').value = loc.longitude;
                
                const container = document.getElementById('editAllowedUsersContainer');
                container.innerHTML = ''; 

                const assignedUserIds = (loc.users || []).map(u => u.user_id);
                
                (loc.users || []).forEach(user => {
                    addUserEntryToLocation('editAllowedUsersContainer', user, assignedUserIds);
                });
                
                const editModal = document.getElementById('editLocationModal');
                const shownHandler = () => setupEditMap(loc.latitude, loc.longitude);
                editModal.addEventListener('shown.bs.modal', shownHandler, { once: true });
                editModal.addEventListener('hidden.bs.modal', () => {
                     editModal.removeEventListener('shown.bs.modal', shownHandler);
                     if (editMap) { editMap.remove(); editMap = null; }
                }, { once: true });
            });
        });

        document.getElementById('updateLocation').addEventListener('click', async () => {
            const allowedUsers = Array.from(document.querySelectorAll('#editAllowedUsersContainer .user-entry')).map(entry => {
                return { user_id: entry.querySelector('.user-select').value, tolerance: parseFloat(entry.querySelector('.user-tolerance').value) };
            }).filter(u => u.user_id && !isNaN(u.tolerance));
            
            const locationData = {
                index: parseInt(document.getElementById('editLocationIndex').value),
                id: document.getElementById('editLocationId').value,
                name: document.getElementById('editLocationName').value.trim(),
                branch: document.getElementById('editLocationBranch').value,
                latitude: parseFloat(document.getElementById('editLatitude').value),
                longitude: parseFloat(document.getElementById('editLongitude').value),
                users: allowedUsers
            };

            if (!locationData.name || !locationData.branch || isNaN(locationData.latitude) || isNaN(locationData.longitude)) {
                Swal.fire('កំហុស!', 'សូមបញ្ចូលទិន្នន័យឱ្យគ្រប់គ្រាន់!', 'error'); return;
            }
            const response = await sendRequest('edit_location', locationData);
            if (response && response.status === 'success') {
                Swal.fire('ជោគជ័យ!', 'ទីតាំងត្រូវបានធ្វើបច្ចុប្បន្នភាព!', 'success').then(() => location.reload());
            } else {
                Swal.fire('កំហុស!', response.message || 'មានបញ្ហាក្នុងការធ្វើបច្ចុប្បន្នភាពទីតាំង', 'error');
            }
        });

        document.querySelectorAll('.delete-location').forEach(button => {
            button.addEventListener('click', () => {
                const index = button.dataset.index;
                Swal.fire({
                    title: 'តើអ្នកប្រាកដទេ?', text: `តើអ្នកប្រាកដទេថាចង់លុបទីតាំងនេះ?`, icon: 'warning', showCancelButton: true, confirmButtonText: 'បាទ/ចាស លុប!', cancelButtonText: 'ទេ បោះបង់'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        const response = await sendRequest('delete_location', { index: parseInt(index) });
                        if (response && response.status === 'success') {
                            Swal.fire('បានលុប!', 'ទីតាំងត្រូវបានលុបដោយជោគជ័យ។', 'success').then(() => location.reload());
                        } else {
                            Swal.fire('កំហុស!', response.message || 'មានបញ្ហាក្នុងការលុបទីតាំង', 'error');
                        }
                    }
                });
            });
        });

        document.querySelectorAll('.duplicate-location').forEach(button => {
            button.addEventListener('click', () => {
                Swal.fire({
                    title: 'តើអ្នកប្រាកដទេ?', text: 'តើអ្នកប្រាកដទេថាចង់ចម្លងទីតាំងនេះ?', icon: 'question', showCancelButton: true, confirmButtonText: 'បាទ/ចាស ចម្លង!', cancelButtonText: 'ទេ បោះបង់'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        const index = button.dataset.index;
                        const loc = { ...allowedLocations[index] };
                        let newName = `${loc.name}-copy`;
                        let suffix = 1;
                        while (allowedLocations.some(l => l.name === newName)) { newName = `${loc.name}-copy${suffix++}`; }
                        loc.name = newName;
                        loc.id = Date.now().toString();
                        const response = await sendRequest('add_location', loc);
                        if (response && response.status === 'success') {
                            Swal.fire('បានចម្លង!', 'ទីតាំងត្រូវបានចម្លងដោយជោគជ័យ។', 'success').then(() => location.reload());
                        } else {
                            Swal.fire('កំហុស!', response.message || 'មានបញ្ហាក្នុងការចម្លងទីតាំង', 'error');
                        }
                    }
                });
            });
        });
        // --- END: LOCATION MANAGEMENT ---


        // Map setup
        let addMap, editMap, addMarker, editMarker;
        const defaultLatLng = [11.562108, 104.916009];

        function setupAddMap() {
            if (addMap) return;
            addMap = L.map('addMap').setView(defaultLatLng, 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(addMap);
            addMarker = L.marker(defaultLatLng, { draggable: true }).addTo(addMap);
            const updateCoords = (lat, lng) => {
                document.getElementById('latitude').value = lat.toFixed(5);
                document.getElementById('longitude').value = lng.toFixed(5);
            };
            addMap.on('click', e => { addMarker.setLatLng(e.latlng); updateCoords(e.latlng.lat, e.latlng.lng); });
            addMarker.on('dragend', e => { const latlng = e.target.getLatLng(); updateCoords(latlng.lat, latlng.lng); });
            updateCoords(defaultLatLng[0], defaultLatLng[1]);
        }

        function setupEditMap(lat, lng) {
            if (editMap) { editMap.remove(); }
            editMap = L.map('editMap').setView([lat, lng], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(editMap);
            editMarker = L.marker([lat, lng], { draggable: true }).addTo(editMap);
            const updateCoords = (lat, lng) => {
                document.getElementById('editLatitude').value = lat.toFixed(5);
                document.getElementById('editLongitude').value = lng.toFixed(5);
            };
            editMap.on('click', e => { editMarker.setLatLng(e.latlng); updateCoords(e.latlng.lat, e.latlng.lng); });
            editMarker.on('dragend', e => { const latlng = e.target.getLatLng(); updateCoords(latlng.lat, latlng.lng); });
            setTimeout(() => editMap.invalidateSize(), 100);
        }

        document.getElementById('addLocationModal').addEventListener('shown.bs.modal', setupAddMap, { once: true });
        document.getElementById('addLocationModal').addEventListener('hidden.bs.modal', () => { if (addMap) { addMap.remove(); addMap = null; } }, { once: true });

        <?php endif; ?>
    </script>
</body>
</html>