<?php
session_start();
session_regenerate_id(true); // Prevent Session Fixation

// Set UTF-8 encoding
header('Content-Type: text/html; charset=UTF-8');

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_fingerprint_db;charset=utf8mb4", "samann1_Fingerprint", "Fingerprint@2025");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    header('Location: error.php?message=' . urlencode('មានបញ្ហាក្នុងការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ! សូមទាក់ទងអ្នកគ្រប់គ្រង។'));
    exit;
}

// Load data from MySQL
function loadData($file = null) {
    global $pdo;
    try {
        // Fetch folders
        $stmt = $pdo->query("SELECT id, name FROM folders");
        $folders = $stmt->fetchAll();
        if (empty($folders)) {
            error_log("No folders found in the database.");
        }

        // Fetch users with pagination support
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = 10; // Adjust as needed
        $offset = ($page - 1) * $perPage;
        $stmt = $pdo->prepare("SELECT id, username, department, position, branch, workplace, folder, timeSettings FROM users LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll();
        if (empty($users)) {
            error_log("No users found in the database for page $page.");
        }
        foreach ($users as &$user) {
            $user['timeSettings'] = json_decode($user['timeSettings'], true) ?? ['check_in_ranges' => [], 'check_out_ranges' => []];
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON decode error for user {$user['id']}: " . json_last_error_msg());
                $user['timeSettings'] = ['check_in_ranges' => [], 'check_out_ranges' => []];
            }
        }

        // Count total users for pagination
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $totalUsers = $stmt->fetchColumn();
        $totalPages = ceil($totalUsers / $perPage);

        return [
            'folders' => $folders,
            'users' => $users,
            'totalPages' => $totalPages,
            'currentPage' => $page
        ];
    } catch (PDOException $e) {
        error_log("Database query failed: " . $e->getMessage());
        header('Location: error.php?message=' . urlencode('បរាជ័យក្នុងការផ្ទុកទិន្នន័យពីមូលដ្ឋានទិន្នន័យ!'));
        exit;
    }
}

// Session Timeout
$loggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if ($loggedIn) {
    $timeout = 30 * 60; // 30 minutes
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_destroy();
        header('Location: admin.php?logout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// Load data only if logged in
$data = $loggedIn ? loadData() : ['folders' => [], 'users' => [], 'totalPages' => 1, 'currentPage' => 1];

// Handle CSRF for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Location: error.php?message=' . urlencode('Invalid CSRF token!'));
        exit;
    }
}

// Handle Registration
$registerError = '';
$registerSuccess = '';
if (isset($_POST['register'])) {
    $username = filter_var($_POST['username'] ?? '', FILTER_SANITIZE_STRING);
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($password) || empty($confirm_password)) {
        $registerError = 'ឈ្មោះអ្នកប្រើ និងលេខសម្ងាត់ទាំងពីរត្រូវបានទាមទារ!';
    } elseif (strlen($username) < 3 || strlen($password) < 8) {
        $registerError = 'ឈ្មោះអ្នកប្រើត្រូវមានយ៉ាងហោចណាស់ ៣ តួអក្សរ និងលេខសម្ងាត់ ៨ តួអក្សរ!';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
        $registerError = 'លេខសម្ងាត់ត្រូវមានយ៉ាងហោចណាស់ ៨ តួអក្សរ រួមមានអក្សរធំ អក្សរតូច និងលេខ!';
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
$loginError = '';
if (!$loggedIn && isset($_POST['login'])) {
    $username = filter_var($_POST['username'] ?? '', FILTER_SANITIZE_STRING);
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $loginError = 'ឈ្មោះអ្នកប្រើ និងលេខសម្ងាត់ត្រូវបានទាមទារ!';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['last_activity'] = time();

            $stmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = :id");
            $stmt->execute([':id' => $admin['id']]);

            header('Location: admin.php');
            exit;
        } else {
            $loginError = 'ឈ្មោះអ្នកប្រើ ឬលេខសម្ងាត់មិនត្រឹមត្រូវ!';
        }
    }
}

// Handle Logout
if ($loggedIn && isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Load data for use in the script
$data = loadData();

// API URL
$apiUrl = 'api-2.php';
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

        /* Navbar */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #3182ce;
            border-radius: 0 0 30px 30px;
            padding: 10px 20px;
            color: white;
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .navbar.scrolled {
            padding: 5px 20px;
            transition: all 0.3s ease;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .navbar-links {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .navbar-links a,
        .navbar-links button {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .navbar-links .btn-light:hover {
            background: #e2e8f0;
            color: #3182ce;
        }

        /* Main container with full width */
        .container {
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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
            width: 120%;
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
            .container {
                max-width: 1800px;
                /* កំណត់ទទឹងអតិបរមា */
                padding: 30px;
                /* បន្ថែម padding សម្រាប់ភាពស្រួលមើល */
            }

            /* Navbar កែសម្រួលឱ្យប្រើទទឹងពេញ */
            .navbar {
                padding: 10px 40px;
                /* បន្ថែម padding សម្រាប់អេក្រង់ធំ */
            }

            .navbar-brand img {
                width: 180px;
                /* បង្កើនទំហំ logo */
            }

            .navbar-links a,
            .navbar-links button {
                padding: 10px 25px;
                /* បន្ថែម padding សម្រាប់ប៊ូតុង */
                font-size: 1.1rem;
                /* បង្កើនទំហំអក្សរ */
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
            h2 {
                font-size: 2.5rem;
                /* បង្កើនទំហំអក្សរ */
            }

            h3 {
                font-size: 1.5rem;
                /* បង្កើនទំហំអក្សរ */
            }

            /* Table កែសម្រួលទំហំនិងគម្លាត */
            .table {
                width: 100%;
                /* កែទៅប្រើទទឹង 100% ជំនួស 120% */
                font-size: 1rem;
                /* បង្កើនទំហំអក្សរ */
            }

            .table th {
                padding: 20px 25px;
                /* បន្ថែម padding */
            }

            .table td {
                padding: 20px 25px;
                /* បន្ថែម padding */
            }

            /* Buttons កែសម្រួលទំហំ */
            .btn {
                padding: 12px 25px;
                /* បន្ថែម padding */
                font-size: 1.1rem;
                /* បង្កើនទំហំអក្សរ */
            }

            .btn-sm {
                padding: 8px 15px;
                /* កែសម្រួលប៊ូតុងតូច */
                font-size: 0.95rem;
            }

            /* Form elements កែសម្រួលទំហំ */
            .form-control {
                padding: 14px 20px;
                /* បន្ថែម padding */
                font-size: 1.1rem;
                /* បង្កើនទំហំអក្សរ */
            }

            /* Modal កែសម្រួលទំហំ */
            #addUserModal .modal-dialog,
            #editUserModal .modal-dialog {
                max-width: 1100px;
                /* បង្កើនទទឹង modal */
            }

            #addLocationModal .modal-dialog,
            #editLocationModal .modal-dialog {
                max-width: 1100px;
                /* បង្កើនទទឹង modal */
            }

            /* Map កែសម្រួលទំហំ */
            #addMap,
            #editMap {
                height: 400px;
                /* បង្កើនកម្ពស់ map */
            }

            /* Pagination controls */
            .pagination-controls {
                font-size: 1.1rem;
                /* បង្កើនទំហំអក្សរ */
            }

            /* Time range */
            .time-range {
                padding: 15px 20px;
                /* បន្ថែម padding */
                font-size: 1.1rem;
                /* បង្កើនទំហំអក្សរ */
            }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
            }

            .navbar-links {
                flex-direction: column;
                width: 100%;
            }

            .navbar-links a,
            .navbar-links button {
                padding: 8px 15px;
                border-radius: 20px;
                font-size: 0.95rem;
                transition: all 0.3s ease;
            }

            .card-body {
                padding: 20px;
            }

            h2 {
                font-size: 1.5rem;
            }

            h3 {
                font-size: 1.3rem;
            }

            .table th,
            .table td {
                padding: 12px;
                font-size: 0.85rem;
            }

            .btn {
                padding: 8px 16px;
                font-size: 0.9rem;
            }

            .modal-dialog {
                margin: 10px;
                max-width: 95%;
            }
        }

        @media (max-width: 576px) {
            h2 {
                font-size: 1.2rem;
            }

            h3 {
                font-size: 1.1rem;
            }

            .btn-sm {
                padding: 5px 10px;
            }
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
            /* Hidden by default */
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

        /* Responsive adjustments */
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
    <?php if ($loggedIn): ?>
    <nav class="navbar shadow-sm" style="background: linear-gradient(90deg, #3182ce 0%, #2b6cb0 100%); border-radius: 0 0 30px 30px; padding: 12px 32px;">
        <div class="navbar-brand d-flex align-items-center gap-2">
            <img src="https://i.ibb.co/0RjV6FpX/Logo-Van-Van-2.png" alt="Logo" style="width: 150px; height: 54px; border-radius: 12px; object-fit: cover; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <span style="font-size: 1.7rem; font-weight: 700; color: #fff; letter-spacing: 1px;">IT</span>
        </div>
        <div class="navbar-links d-flex gap-2 align-items-center">
            <a href="#users" class="btn btn-light d-flex align-items-center gap-2 shadow-sm" style="font-weight:600;">
                <i class="fas fa-users"></i> អ្នកប្រើ
            </a>
            <a href="#Folder" class="btn btn-light d-flex align-items-center gap-2 shadow-sm" style="font-weight:600;">
                <i class="fas fa-folder"></i> Folder
            </a>
            <a href="#locations" class="btn btn-light d-flex align-items-center gap-2 shadow-sm" style="font-weight:600;">
                <i class="fas fa-map-marker-alt"></i> ទីតាំង
            </a>
            <a href="#tokens" class="btn btn-light d-flex align-items-center gap-2 shadow-sm" style="font-weight:600;">
                <i class="fas fa-key"></i> Token
            </a>
            <a href="?logout=true" class="btn btn-danger d-flex align-items-center gap-2 shadow-sm" style="font-weight:600;">
                <i class="fas fa-sign-out-alt"></i> ចាកចេញ
            </a>
        </div>
    </nav>
    <?php endif; ?>

    <div class="container">
        <?php if (!$loggedIn): ?>
        <div class="row justify-content-center mt-5">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h2><i class="fas fa-sign-in-alt"></i> ចូលគណនី Admin</h2>
                        <?php if (isset($loginError)): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($loginError); ?>
                        </div>
                        <?php endif; ?>
                        <?php if (isset($registerSuccess)): ?>
                        <div class="alert alert-success">
                            <?php echo htmlspecialchars($registerSuccess); ?>
                        </div>
                        <?php endif; ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="mb-3">
                                <label for="login_username" class="form-label">ឈ្មោះអ្នកប្រើ</label>
                                <input type="text" class="form-control" id="login_username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="login_password" class="form-label">លេខសម្ងាត់</label>
                                <input type="password" class="form-control" id="login_password" name="password"
                                    required>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary w-100"><i
                                    class="fas fa-sign-in-alt"></i> ចូល</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h2 class="text-center mb-4">ចុះឈ្មោះ Admin</h2>
                        <?php if (isset($registerError)): ?>
                        <div class="alert alert-danger">
                            <?php echo htmlspecialchars($registerError); ?>
                        </div>
                        <?php endif; ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="mb-3">
                                <label for="register_username" class="form-label">ឈ្មោះអ្នកប្រើ</label>
                                <input type="text" class="form-control" id="register_username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="register_password" class="form-label">លេខសម្ងាត់</label>
                                <input type="password" class="form-control" id="register_password" name="password"
                                    required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">បញ្ជាក់លេខសម្ងាត់</label>
                                <input type="password" class="form-control" id="confirm_password"
                                    name="confirm_password" required>
                            </div>
                            <button type="submit" name="register" class="btn btn-success w-100">ចុះឈ្មោះ</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-body">
                <h2><i class="fas fa-tachometer-alt"></i> Admin Panel</h2>
                <p>ស្វាគមន៍,
                    <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                </p>
            </div>
        </div>


        <!-- Edit Folder Modal -->
        <div class="modal fade" id="editFolderModal" tabindex="-1" aria-labelledby="editFolderModalLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editFolderModalLabel">កែសម្រួល Folder</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editFolderForm">
                            <input type="hidden" id="editFolderIndex">
                            <div class="mb-3">
                                <label for="editFolderName" class="form-label">ឈ្មោះ Folder</label>
                                <input type="text" class="form-control" id="editFolderName" required>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                                class="fas fa-times"></i> បិទ</button>
                        <button type="button" class="btn btn-primary" id="updateFolder"><i class="fas fa-save"></i>
                            ធ្វើបច្ចុប្បន្នភាព</button>
                    </div>
                </div>
            </div>
        </div>


        <div class="card" id="users">
            <div class="card-body">
            <h3><i class="fas fa-users"></i> គ្រប់គ្រងអ្នកប្រើប្រាស់</h3>
            <div class="row mb-3">
                <div class="col-md-4">
                <input type="text" id="searchUsers" class="form-control" placeholder="ស្វែងរកអ្នកប្រើប្រាស់">
                </div>
                <div class="col-md-4">
                <select id="filterFolder" class="form-control">
                    <option value="">តម្រងតាម Folder (ទាំងអស់)</option>
                    <?php foreach ($data['folders'] as $folder): ?>
                    <option value="<?php echo htmlspecialchars($folder['name']); ?>">
                    <?php echo htmlspecialchars($folder['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                </div>
                <div class="col-md-4">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFolderModal">
                    <i class="fas fa-folder-plus"></i> បន្ថែម Folder
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus"></i> បន្ថែមអ្នកប្រើប្រាស់
                </button>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                <select id="filterBranch" class="form-control">
                    <option value="">តម្រងតាមទីតាំងស្កេន (ទាំងអស់)</option>
                    <option value="Head Office">ការិយាល័យកណ្តាល</option>
                    <option value="Warehouse CH1">ឃ្លាំង CH1</option>
                    <option value="318 Shop">ហាងទំនិញ៣១៨</option>
                    <option value="Warehouse CKD">ឃ្លាំងចំការដូង</option>
                    <option value="Warehouse ST1">ឃ្លាំងស្ទឹងមានជ័យ១</option>
                    <option value="Warehouse PSP">ឃ្លាំងព្រៃស្ពឺ</option>
                </select>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped">
                <thead>
                    <tr>
                    <th><i class="fas fa-user"></i> ឈ្មោះ</th>
                    <th><i class="fas fa-id-card"></i> ID</th>
                    <th><i class="fas fa-building"></i> នាយកដ្ឋាន</th>
                    <th><i class="fas fa-briefcase"></i> តួនាទី</th>
                    <th><i class="fas fa-map-marker-alt"></i> ទីតាំងស្កេន</th>
                    <th><i class="fas fa-location-arrow"></i> កន្លែងធ្វើការ</th>
                    <th><i class="fas fa-folder"></i> ប្រភេទ</th>
                    <th><i class="fas fa-clock"></i> ម៉ោងស្កេនចូល</th>
                    <th><i class="fas fa-clock"></i> ម៉ោងស្កេនចេញ</th>
                    <th><i class="fas fa-cogs"></i> សកម្មភាព</th>
                    </tr>
                </thead>
                <tbody id="usersTable">
                    <?php foreach ($data['users'] as $index => $user): ?>
                    <tr>
                    <td>
                        <?php echo htmlspecialchars($user['username']); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($user['id']); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($user['branch'] ?? 'N/A'); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($user['workplace'] ?? 'N/A'); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($user['folder'] ?? 'N/A'); ?>
                    </td>
                    <td>
                        <?php
                            if (!empty($user['timeSettings']['check_in_ranges'])) {
                            foreach ($user['timeSettings']['check_in_ranges'] as $range) {
                                $start = sprintf("%02d:%02d", floor($range['start'] / 60), $range['start'] % 60);
                                $end = sprintf("%02d:%02d", floor($range['end'] / 60), $range['end'] % 60);
                                $status = htmlspecialchars($range['status']);
                                echo "<div class='time-range $status'>" . htmlspecialchars("$start - $end") . " <i class='status-icon'></i>" . htmlspecialchars($status) . "</div>";
                            }
                            } else {
                            echo 'N/A';
                            }
                            ?>
                    </td>
                    <td>
                        <?php
                            if (!empty($user['timeSettings']['check_out_ranges'])) {
                            foreach ($user['timeSettings']['check_out_ranges'] as $range) {
                                $start = sprintf("%02d:%02d", floor($range['start'] / 60), $range['start'] % 60);
                                $end = sprintf("%02d:%02d", floor($range['end'] / 60), $range['end'] % 60);
                                $status = htmlspecialchars($range['status']);
                                echo "<div class='time-range $status'>" . htmlspecialchars("$start - $end") . " <i class='status-icon'></i>" . htmlspecialchars($status) . "</div>";
                            }
                            } else {
                            echo 'N/A';
                            }
                            ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                        <button class="btn btn-success btn-sm duplicate-user"
                            data-index="<?php echo $index; ?>"><i class="fas fa-copy"></i>
                            ចម្លង</button>
                        <button class="btn btn-warning btn-sm edit-user"
                            data-index="<?php echo $index; ?>" data-bs-toggle="modal"
                            data-bs-target="#editUserModal"><i class="fas fa-edit"></i> កែ</button>
                        <button class="btn btn-danger btn-sm delete-user"
                            data-index="<?php echo $index; ?>"><i class="fas fa-trash"></i> លុប</button>
                        </div>
                    </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                </table>
            </div>
            <div class="pagination-controls d-flex justify-content-between align-items-center mt-3">
                <button class="btn btn-secondary" id="prevPage" disabled><i class="fas fa-arrow-left"></i>
                មុន</button>
                <div>
                ទំព័រ <span id="currentPage">1</span> នៃ <span id="totalPages">1</span>
                </div>
                <button class="btn btn-secondary" id="nextPage"><i class="fas fa-arrow-right"></i> បន្ទាប់</button>
            </div>
            </div>
        </div>
        <script>
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

            document.getElementById('currentPage').textContent = page;
            document.getElementById('totalPages').textContent = Math.max(1, Math.ceil(allRows.length / rowsPerPage));
            document.getElementById('prevPage').disabled = page === 1;
            document.getElementById('nextPage').disabled = page === Math.ceil(allRows.length / rowsPerPage) || allRows.length === 0;
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
                const folder = tds[6] ? tds[6].textContent.trim() : '';
                const branch = tds[4] ? tds[4].textContent.trim() : '';
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
        </script>

        <!-- Inside the #users card, after the existing table -->
        <div class="card" id="Folder">
            <div class="card-body">
                <h3><i class="fas fa-folder"></i> គ្រប់គ្រង Folders</h3>
                <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addFolderModal">
                    <i class="fas fa-folder-plus"></i> បន្ថែម Folder
                </button>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th><i class="fas fa-folder"></i> ឈ្មោះ Folder</th>
                                <th><i class="fas fa-users"></i> ចំនួនអ្នកប្រើ</th>
                                <th><i class="fas fa-cogs"></i> សកម្មភាព</th>
                            </tr>
                        </thead>
                        <tbody id="foldersTable">
                            <?php foreach ($data['folders'] as $index => $folder): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($folder['name']); ?>
                                </td>
                                <td>
                                    <?php 
                            $userCount = count(array_filter($data['users'], fn($u) => ($u['folder'] ?? '') === $folder['name']));
                            echo $userCount;
                            ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-warning btn-sm edit-folder"
                                            data-index="<?php echo $index; ?>" data-bs-toggle="modal"
                                            data-bs-target="#editFolderModal">
                                            <i class="fas fa-edit"></i> កែ
                                        </button>
                                        <button class="btn btn-danger btn-sm delete-folder"
                                            data-index="<?php echo $index; ?>">
                                            <i class="fas fa-trash"></i> លុប
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Add Folder Modal -->
        <div class="modal fade" id="addFolderModal" tabindex="-1" aria-labelledby="addFolderModalLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addFolderModalLabel">បន្ថែម Folder</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addFolderForm">
                            <div class="mb-3">
                                <label for="folderName" class="form-label">ឈ្មោះ Folder</label>
                                <input type="text" class="form-control" id="folderName" required>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                                class="fas fa-times"></i> បិទ</button>
                        <button type="button" class="btn btn-primary" id="saveFolder"><i class="fas fa-save"></i>
                            រក្សាទុក</button>
                    </div>
                </div>
            </div>
        </div>



        <!-- Add User Modal -->
        <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addUserModalLabel">បន្ថែមអ្នកប្រើប្រាស់</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addUserForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">ឈ្មោះ</label>
                                    <input type="text" class="form-control" id="username" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="id" class="form-label">ID</label>
                                    <input type="text" class="form-control" id="id" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="department" class="form-label">នាយកដ្ឋាន</label>
                                    <input type="text" class="form-control" id="department" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="position" class="form-label">តួនាទី</label>
                                    <input type="text" class="form-control" id="position" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="branch" class="form-label">ទីតាំងស្កេន</label>
                                    <select class="form-control" id="branch" required>
                                        <option value="" disabled selected>ជ្រើសរើសសាខា</option>
                                        <option value="Head Office">ការិយាល័យកណ្តាល</option>
                                        <option value="Warehouse CH1">ឃ្លាំង CH1</option>
                                        <option value="318 Shop">ហាងទំនិញ៣១៨</option>
                                        <option value="Warehouse CKD">ឃ្លាំងចំការដូង</option>
                                        <option value="Warehouse ST1">ឃ្លាំងស្ទឹងមានជ័យ១</option>
                                        <option value="Warehouse PSP">ឃ្លាំងព្រៃស្ពឺ</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="workplace" class="form-label">កន្លែងធ្វើការ</label>
                                    <input type="text" class="form-control" id="workplace" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="folder" class="form-label">ប្រភេទ</label>
                                    <select class="form-control" id="folder" required>
                                        <option value="" disabled selected>ជ្រើសរើស ប្រភេទ</option>
                                        <?php foreach ($data['folders'] as $folder): ?>
                                        <option value="<?php echo htmlspecialchars($folder['name']); ?>">
                                            <?php echo htmlspecialchars($folder['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <h5>កំណត់ម៉ោងស្កេន</h5>
                            <h6>ស្កេនចូល</h6>
                            <div id="checkInRanges"></div>
                            <button type="button" class="btn btn-success mb-3" id="addCheckInRange"><i
                                    class="fas fa-plus"></i> បន្ថែមម៉ោងស្កេនចូល</button>
                            <h6>ស្កេនចេញ</h6>
                            <div id="checkOutRanges"></div>
                            <button type="button" class="btn btn-success mb-3" id="addCheckOutRange"><i
                                    class="fas fa-plus"></i> បន្ថែមម៉ោងស្កេនចេញ</button>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                                class="fas fa-times"></i> បិទ</button>
                        <button type="button" class="btn btn-primary" id="saveUser"><i class="fas fa-save"></i>
                            រក្សាទុក</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit User Modal -->
        <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editUserModalLabel">កែសម្រួលអ្នកប្រើប្រាស់</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editUserForm">
                            <input type="hidden" id="editIndex">
                            <div class="mb-3">
                                <label for="editUsername" class="form-label">ឈ្មោះ</label>
                                <input type="text" class="form-control" id="editUsername" required>
                            </div>
                            <div class="mb-3">
                                <label for="editId" class="form-label">ID</label>
                                <input type="text" class="form-control" id="editId" required>
                            </div>
                            <div class="mb-3">
                                <label for="editDepartment" class="form-label">នាយកដ្ឋាន</label>
                                <input type="text" class="form-control" id="editDepartment" required>
                            </div>
                            <div class="mb-3">
                                <label for="editPosition" class="form-label">តួនាទី</label>
                                <input type="text" class="form-control" id="editPosition" required>
                            </div>
                            <div class="mb-3">
                                <label for="editBranch" class="form-label">ទីតាំងស្កេន</label>
                                <select class="form-control" id="editBranch" required>
                                    <option value="" disabled>ជ្រើសរើសសាខា</option>
                                    <option value="Head Office">ការិយាល័យកណ្តាល</option>
                                    <option value="Warehouse CH1">ឃ្លាំង CH1</option>
                                    <option value="318 Shop">ហាងទំនិញ៣១៨</option>
                                    <option value="Warehouse CKD">ឃ្លាំងចំការដូង</option>
                                    <option value="Warehouse ST1">ឃ្លាំងស្ទឹងមានជ័យ១</option>
                                    <option value="Warehouse PSP">ឃ្លាំងព្រៃស្ពឺ</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="editWorkplace" class="form-label">កន្លែងធ្វើការ</label>
                                <input type="text" class="form-control" id="editWorkplace" required>
                            </div>
                            <div class="mb-3">
                                <label for="editFolder" class="form-label">ប្រភេទ</label>
                                <select class="form-control" id="editFolder" required>
                                    <option value="" disabled>ជ្រើសរើស ប្រភេទ</option>
                                    <?php foreach ($data['folders'] as $folder): ?>
                                    <option value="<?php echo htmlspecialchars($folder['name']); ?>">
                                        <?php echo htmlspecialchars($folder['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <h5>កំណត់ម៉ោងស្កេន</h5>
                            <h6>ស្កេនចូល</h6>
                            <div id="editCheckInRanges"></div>
                            <button type="button" class="btn btn-success mb-3" id="editAddCheckInRange"><i
                                    class="fas fa-plus"></i> បន្ថែមម៉ោងស្កេនចូល</button>
                            <h6>ស្កេនចេញ</h6>
                            <div id="editCheckOutRanges"></div>
                            <button type="button" class="btn btn-success mb-3" id="editAddCheckOutRange"><i
                                    class="fas fa-plus"></i> បន្ថែមម៉ោងស្កេនចេញ</button>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                                class="fas fa-times"></i> បិទ</button>
                        <button type="button" class="btn btn-primary" id="updateUser"><i class="fas fa-save"></i>
                            ធ្វើបច្ចុប្បន្នភាព</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rest of the existing HTML (locations, tokens, etc.) remains unchanged -->
      
<div class="card" id="locations">
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
                        // Generate QR code with geo: format, high quality (SVG, 500x500)
                        $qrData = "geo:{$lat},{$lng}";
                        $qrUrlSvg = "https://api.qrserver.com/v1/create-qr-code/?size=500x500&format=svg&data=" . urlencode($qrData);
                        $qrUrlPng = "https://api.qrserver.com/v1/create-qr-code/?size=500x500&format=png&data=" . urlencode($qrData);
                        $qrFileNameSvg = "QR_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $loc['name'] ?? 'location') . ".svg";
                        $qrFileNamePng = "QR_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $loc['name'] ?? 'location') . ".png";
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($loc['name'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($loc['branch'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(number_format($lat, 5)); ?></td>
                            <td><?php echo htmlspecialchars(number_format($lng, 5)); ?></td>
                            <td>
                                <div style="text-align:center;">
                                    <img src="<?php echo htmlspecialchars($qrUrlSvg); ?>" alt="QR Code for <?php echo htmlspecialchars($loc['name']); ?>" width="100" height="100" loading="lazy" style="display:block;margin:auto;">
                                    <a href="<?php echo htmlspecialchars($qrUrlSvg); ?>" download="<?php echo htmlspecialchars($qrFileNameSvg); ?>" class="btn btn-outline-primary btn-sm mt-2" title="ទាញយក QR Code SVG">
                                        <i class="fas fa-download"></i> ទាញយក SVG
                                    </a>
                                    <a href="<?php echo htmlspecialchars($qrUrlPng); ?>" download="<?php echo htmlspecialchars($qrFileNamePng); ?>" class="btn btn-outline-success btn-sm mt-2" title="ទាញយក QR Code PNG">
                                        <i class="fas fa-download"></i> ទាញយក PNG
                                    </a>
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
                                    <button type="button" class="btn btn-success btn-sm duplicate-location" data-index="<?php echo $index; ?>">
                                        <i class="fas fa-copy"></i> ចម្លង
                                    </button>
                                    <button type="button" class="btn btn-warning btn-sm edit-location" data-index="<?php echo $index; ?>" data-bs-toggle="modal" data-bs-target="#editLocationModal">
                                        <i class="fas fa-edit"></i> កែ
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm delete-location" data-index="<?php echo $index; ?>">
                                        <i class="fas fa-trash"></i> លុប
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

        <div class="card" id="tokens">
            <div class="card-body">
            <h3><i class="fas fa-key"></i> គ្រប់គ្រង Token</h3>
            <div class="max-tokens-form">
            <label for="maxTokensPerUser" class="form-label">ចំនួន Token អតិបរមាក្នុងម្នាក់:</label>
            <input type="number" id="maxTokensPerUser" class="form-control"
            value="<?php echo htmlspecialchars($data['settings']['maxTokensPerUser'] ?? 3); ?>" min="1">
            <button type="button" class="btn btn-primary mt-2" id="saveMaxTokens"><i class="fas fa-save"></i>
            រក្សាទុក</button>
            <p class="current-max-tokens mt-2">បច្ចុប្បន្ន:
            <?php echo htmlspecialchars($data['settings']['maxTokensPerUser'] ?? 3); ?> Tokens
            </p>
            </div>
            <div class="alert alert-info mb-3">
            អ្នកប្រើដែលមាន Token នៅក្នុងតារាងនេះ គឺ <strong>នៅសកម្ម</strong>។
            </div>
            <div class="mb-3">
            <span>ជ្រើសរើសបង្ហាញ icon:</span>
            <button type="button" class="btn btn-outline-primary btn-sm" id="showOnline"><i class="fas fa-circle text-primary"></i> បង្ហាញខៀវ</button>
            <button type="button" class="btn btn-outline-danger btn-sm" id="showOffline"><i class="fas fa-circle text-danger"></i> បង្ហាញក្រហម</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="showAll"><i class="fas fa-circle"></i> បង្ហាញទាំងអស់</button>
            </div>
            <div class="table-responsive">
            <table class="table table-striped">
            <thead>
                <tr>
                <th><i class="fas fa-user"></i> ឈ្មោះ</th>
                <th><i class="fas fa-id-card"></i> ID</th>
                <th><i class="fas fa-key"></i> Token</th>
                <th><i class="fas fa-clock"></i> ពេលវេលាចូល</th>
                <th><i class="fas fa-cogs"></i> សកម្មភាព</th>
                </tr>
            </thead>
            <tbody id="tokensTable">
                <?php
                foreach ($data['users'] as $user) {
                // Find all tokens for this user
                $userTokens = [];
                foreach ($data['activeUsers'] as $token => $userData) {
                if ($userData['id'] === $user['id']) {
                $userTokens[] = [
                    'token' => $token,
                    'loginTime' => $userData['loginTime']
                ];
                }
                }
                if (empty($userTokens)) {
                // No active token: show offline (red icon)
                echo '<tr class="offline-row">
                <td>
                    <i class="fas fa-circle text-danger" title="Offline" style="font-size: 0.9em; margin-right: 5px;"></i>'
                    . htmlspecialchars($user['username']) .
                '</td>
                <td>' . htmlspecialchars($user['id']) . '</td>
                <td>-</td>
                <td>-</td>
                <td>
                    <span class="text-muted">មិនមាន Token</span>
                </td>
                </tr>';
                } else {
                // Show all active tokens for this user (green icon)
                foreach ($userTokens as $tk) {
                echo '<tr class="online-row">
                    <td>
                    <i class="fas fa-circle text-primary" title="Online" style="font-size: 0.9em; margin-right: 5px;"></i>'
                    . htmlspecialchars($user['username']) .
                    '</td>
                    <td>' . htmlspecialchars($user['id']) . '</td>
                    <td>' . htmlspecialchars($tk['token']) . '</td>
                    <td>' . htmlspecialchars($tk['loginTime']) . '</td>
                    <td>
                    <div class="action-buttons">
                    <button class="btn btn-danger btn-sm revoke-token"
                    data-token="' . htmlspecialchars($tk['token']) . '"><i
                    class="fas fa-trash"></i> លុប</button>
                    </div>
                    </td>
                </tr>';
                }
                }
                }
                ?>
            </tbody>
            </table>
            </div>
            </div>
        </div>
        <script>
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
            document.getElementById('showAll').classList.toggle('active', filter === 'all');
        }
        document.getElementById('showOnline').onclick = function() { setTokenFilter('online'); };
        document.getElementById('showOffline').onclick = function() { setTokenFilter('offline'); };
        document.getElementById('showAll').onclick = function() { setTokenFilter('all'); };
        // On page load, restore filter
        document.addEventListener('DOMContentLoaded', function() {
            const filter = localStorage.getItem('tokenIconFilter') || 'all';
            applyTokenFilter(filter);
        });
        </script>

        <!-- Add Location Modal -->
        <div class="modal fade" id="addLocationModal" tabindex="-1" aria-labelledby="addLocationModalLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addLocationModalLabel">បន្ថែមទីតាំង</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="addMap" style="width: 100%; height: 300px; margin-bottom: 20px;"></div>
                        <form id="addLocationForm">
                            <input type="hidden" id="locationId" value="">
                            <div class="mb-3">
                                <label for="locationName" class="form-label">ឈ្មោះទីតាំង</label>
                                <input type="text" class="form-control" id="locationName" required>
                            </div>
                            <div class="mb-3">
                                <label for="locationBranch" class="form-label">ទីតាំងស្កេន</label>
                                <select class="form-control" id="locationBranch" required>
                                    <option value="" disabled selected>ជ្រើសរើសសាខា</option>
                                    <option value="Head Office">ការិយាល័យកណ្តាល</option>
                                    <option value="Warehouse CH1">ឃ្លាំង CH1</option>
                                    <option value="318 Shop">ហាងទំនិញ៣១៨</option>
                                    <option value="Warehouse CKD">ឃ្លាំងចំការដូង</option>
                                    <option value="Warehouse ST1">ឃ្លាំងស្ទឹងមានជ័យ១</option>
                                    <option value="Warehouse PSP">ឃ្លាំងព្រៃស្ពឺ</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="latitude" class="form-label">Latitude</label>
                                <input type="number" step="0.00001" class="form-control" id="latitude" required>
                            </div>
                            <div class="mb-3">
                                <label for="longitude" class="form-label">Longitude</label>
                                <input type="number" step="0.00001" class="form-control" id="longitude" required>
                            </div>
                            <h5>អ្នកប្រើប្រាស់ដែលអនុញ្ញាត</h5>
                            <div id="allowedUsersContainer">
                                <div class="user-entry mb-3">
                                    <div class="row">
                                        <div class="col-md-5">
                                            <select class="form-control user-select" required>
                                                <option value="">ជ្រើសរើសអ្នកប្រើប្រាស់</option>
                                                <?php foreach ($data['users'] as $user): ?>
                                                <option value="<?php echo htmlspecialchars($user['id']); ?>">
                                                    <?php echo htmlspecialchars($user['username'] . ' (' . $user['workplace'] . ' - ' . $user['id'] . ')'); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="input-group">
                                                <input type="number" step="0.00001" class="form-control user-tolerance"
                                                    placeholder="Tolerance" value="0.001" required>
                                                <span class="input-group-text tolerance-meters"
                                                    style="min-width:90px;">≈ 111m</span>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-danger remove-user-entry"><i
                                                    class="fas fa-trash"></i> លុប</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-success mb-3" id="addUserEntry"><i
                                    class="fas fa-plus"></i> បន្ថែមអ្នកប្រើប្រាស់</button>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                                class="fas fa-times"></i> បិទ</button>
                        <button type="button" class="btn btn-primary" id="saveLocation"><i class="fas fa-save"></i>
                            រក្សាទុក</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            // Auto update meters when tolerance changes (Add Location Modal)
            function updateToleranceMeters(input) {
                const val = parseFloat(input.value);
                // 1 degree ≈ 111,000 meters (for latitude/longitude)
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
                setTimeout(() => {
                    document.querySelectorAll('#allowedUsersContainer .user-tolerance').forEach(updateToleranceMeters);
                }, 10);
            });
        </script>

        <!-- Edit Location Modal -->
        <div class="modal fade" id="editLocationModal" tabindex="-1" aria-labelledby="editLocationModalLabel"
            aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editLocationModalLabel">កែសម្រួលទីតាំង</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="editMap" style="width: 100%; height: 300px; margin-bottom: 20px;"></div>
                        <form id="editLocationForm">
                            <input type="hidden" id="editLocationIndex">
                            <input type="hidden" id="editLocationId">
                            <div class="mb-3">
                                <label for="editLocationName" class="form-label">ឈ្មោះទីតាំង</label>
                                <input type="text" class="form-control" id="editLocationName" required>
                            </div>
                            <div class="mb-3">
                                <label for="editLocationBranch" class="form-label">ទីតាំងស្កេន</label>
                                <select class="form-control" id="editLocationBranch" required>
                                    <option value="" disabled>ជ្រើសរើសសាខា</option>
                                    <option value="Head Office">ការិយាល័យកណ្តាល</option>
                                    <option value="Warehouse CH1">ឃ្លាំង CH1</option>
                                    <option value="318 Shop">ហាងទំនិញ៣១៨</option>
                                    <option value="Warehouse CKD">ឃ្លាំងចំការដូង</option>
                                    <option value="Warehouse ST1">ឃ្លាំងស្ទឹងមានជ័យ១</option>
                                    <option value="Warehouse PSP">ឃ្លាំងព្រៃស្ពឺ</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="editLatitude" class="form-label">Latitude</label>
                                <input type="number" step="0.00001" class="form-control" id="editLatitude" required>
                            </div>
                            <div class="mb-3">
                                <label for="editLongitude" class="form-label">Longitude</label>
                                <input type="number" step="0.00001" class="form-control" id="editLongitude" required>
                            </div>
                            <h5>អ្នកប្រើប្រាស់ដែលអនុញ្ញាត</h5>
                            <div id="editAllowedUsersContainer">
                                <!-- User entries will be injected here by JS -->
                            </div>
                            <button type="button" class="btn btn-success mb-3" id="editAddUserEntry"><i
                                    class="fas fa-plus"></i>បន្ថែមអ្នកប្រើប្រាស់</button>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                                class="fas fa-times"></i> បិទ</button>
                        <button type="button" class="btn btn-primary" id="updateLocation"><i class="fas fa-save"></i>
                            ធ្វើបច្ចុប្បន្នភាព</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            // Show meters for Tolerance in edit modal
            function updateEditToleranceMeters(input) {
                const val = parseFloat(input.value);
                const meters = isNaN(val) ? 0 : Math.round(val * 111000);
                const span = input.closest('.input-group').querySelector('.tolerance-meters');
                if (span) span.textContent = `≈ ${meters}m`;
            }
            document.addEventListener('input', function (e) {
                if (e.target.closest('#editAllowedUsersContainer') && e.target.classList.contains('user-tolerance')) {
                    updateEditToleranceMeters(e.target);
                }
            });
            document.querySelectorAll('#editAllowedUsersContainer .user-tolerance').forEach(updateEditToleranceMeters);

            // When adding new user entry in edit modal, also add the meter display
            document.getElementById('editAddUserEntry').addEventListener('click', function () {
                setTimeout(() => {
                    document.querySelectorAll('#editAllowedUsersContainer .user-tolerance').forEach(updateEditToleranceMeters);
                }, 10);
            });
        </script>

        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
    <script>





        // Script to resize navbar on scroll
        document.addEventListener('DOMContentLoaded', () => {
            const navbar = document.querySelector('.navbar');
            let lastScrollTop = 0;

            window.addEventListener('scroll', () => {
                const currentScroll = window.pageYOffset || document.documentElement.scrollTop;
                if (currentScroll > lastScrollTop) {
                    navbar.style.padding = '5px 20px';
                    navbar.style.transition = 'all 0.3s ease';
                } else {
                    navbar.style.padding = '10px 20px';
                    navbar.style.transition = 'all 0.3s ease';
                }
                lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
            });
        });

        // Pagination Variables
        const rowsPerPage = 50;
        let currentPage = 1;
        let filteredRows = [];

        function displayPage(page) {
            const start = (page - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            const allRows = filteredRows.length ? filteredRows : document.querySelectorAll('#usersTable tr');

            document.querySelectorAll('#usersTable tr').forEach(row => row.style.display = 'none');
            allRows.forEach((row, index) => {
                if (index >= start && index < end) row.style.display = '';
            });

            document.getElementById('currentPage').textContent = page;
            document.getElementById('totalPages').textContent = Math.ceil(allRows.length / rowsPerPage);
            document.getElementById('prevPage').disabled = page === 1;
            document.getElementById('nextPage').disabled = page === Math.ceil(allRows.length / rowsPerPage);
        }

        function filterUsers() {
            const searchTerm = document.getElementById('searchUsers').value.toLowerCase();
            const filterFolder = document.getElementById('filterFolder').value;
            const filterBranch = document.getElementById('filterBranch').value;
            const rows = document.querySelectorAll('#usersTable tr');
            filteredRows = [];

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const folder = row.querySelector('td:nth-child(7)').textContent;
                const branch = row.querySelector('td:nth-child(5)').textContent;
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
            if (currentPage < Math.ceil((filteredRows.length || document.querySelectorAll('#usersTable tr').length) / rowsPerPage)) {
                currentPage++;
                displayPage(currentPage);
            }
        });

        document.addEventListener('DOMContentLoaded', () => displayPage(currentPage));
        document.getElementById('searchUsers').addEventListener('input', filterUsers);
        document.getElementById('filterFolder').addEventListener('change', filterUsers);
        document.getElementById('filterBranch').addEventListener('change', filterUsers);

        <?php if ($loggedIn): ?>
        const users = <?php echo json_encode($data['users']); ?>;
        const activeUsers = <?php echo json_encode($data['activeUsers']); ?>;
        const allowedLocations = <?php echo json_encode($data['allowedLocations']); ?>;
        const folders = <?php echo json_encode($data['folders']); ?>;
        const apiUrl = '<?php echo $apiUrl; ?>';

        async function sendRequest(action, data) {
            if (!apiUrl) {
                throw new Error('API URL មិនត្រូវបានកំណត់!');
            }

            data.csrf_token = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';

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
                console.log(`Response from ${action}:`, result); // Debug response
                return result; // Always return the result, whether success or error
            } catch (error) {
                console.error(`Error in sendRequest (${action}):`, error);
                throw error; // Throw error to be handled by the caller
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
            const [hours, minutes] = time.split(':').map(Number);
            return hours * 60 + minutes;
        }

        function minutesToTime(minutes) {
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`;
        }

        function addTimeRange(containerId, prefix, range = { start: '', end: '', status: 'Good' }) {
            const index = document.querySelectorAll(`#${containerId} .time-range`).length;
            const html = `
                    <div class="time-range mb-3">
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-label">ចាប់ផ្តើម</label>
                                <input type="time" class="form-control start-time" value="${range.start ? minutesToTime(range.start) : ''}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">បញ្ចប់</label>
                                <input type="time" class="form-control end-time" value="${range.end ? minutesToTime(range.end) : ''}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">ស្ថានភាព</label>
                                <select class="form-control status" required>
                                    <option value="Good" ${range.status === 'Good' ? 'selected' : ''}>Good</option>
                                    <option value="Late" ${range.status === 'Late' ? 'selected' : ''}>Late</option>
                                </select>
                            </div>
                            <div class="col-md-1 align-self-end">
                                <button type="button" class="btn btn-danger remove-range">លុប</button>
                            </div>
                        </div>
                    </div>
                `;
            document.getElementById(containerId).insertAdjacentHTML('beforeend', html);
            document.querySelectorAll(`#${containerId} .remove-range`).forEach(button => {
                button.addEventListener('click', () => button.closest('.time-range').remove());
            });
        }

        addTimeRange('checkInRanges', 'checkIn');
        addTimeRange('checkOutRanges', 'checkOut');

        document.getElementById('addCheckInRange').addEventListener('click', () => addTimeRange('checkInRanges', 'checkIn'));
        document.getElementById('addCheckOutRange').addEventListener('click', () => addTimeRange('checkOutRanges', 'checkOut'));

        document.getElementById('saveUser').addEventListener('click', async () => {
            const checkInRanges = [];
            document.querySelectorAll('#checkInRanges .time-range').forEach(range => {
                const start = timeToMinutes(range.querySelector('.start-time').value);
                const end = timeToMinutes(range.querySelector('.end-time').value);
                const status = range.querySelector('.status').value;
                if (isNaN(start) || isNaN(end) || start >= end) return;
                checkInRanges.push({ start, end, status });
            });

            const checkOutRanges = [];
            document.querySelectorAll('#checkOutRanges .time-range').forEach(range => {
                const start = timeToMinutes(range.querySelector('.start-time').value);
                const end = timeToMinutes(range.querySelector('.end-time').value);
                const status = range.querySelector('.status').value;
                if (isNaN(start) || isNaN(end) || start >= end) return;
                checkOutRanges.push({ start, end, status });
            });

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
                (user.timeSettings?.check_in_ranges || []).forEach(range => addTimeRange('editCheckInRanges', 'editCheckIn', range));
                (user.timeSettings?.check_out_ranges || []).forEach(range => addTimeRange('editCheckOutRanges', 'editCheckOut', range));
            });
        });

        document.getElementById('editAddCheckInRange').addEventListener('click', () => addTimeRange('editCheckInRanges', 'editCheckIn'));
        document.getElementById('editAddCheckOutRange').addEventListener('click', () => addTimeRange('editCheckOutRanges', 'editCheckOut'));

        document.getElementById('updateUser').addEventListener('click', async () => {
            const checkInRanges = [];
            document.querySelectorAll('#editCheckInRanges .time-range').forEach(range => {
                const start = timeToMinutes(range.querySelector('.start-time').value);
                const end = timeToMinutes(range.querySelector('.end-time').value);
                const status = range.querySelector('.status').value;
                if (!isNaN(start) && !isNaN(end) && start < end) checkInRanges.push({ start, end, status });
            });

            const checkOutRanges = [];
            document.querySelectorAll('#editCheckOutRanges .time-range').forEach(range => {
                const start = timeToMinutes(range.querySelector('.start-time').value);
                const end = timeToMinutes(range.querySelector('.end-time').value);
                const status = range.querySelector('.status').value;
                if (!isNaN(start) && !isNaN(end) && start < end) checkOutRanges.push({ start, end, status });
            });

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
            }
        });

        document.querySelectorAll('.delete-user').forEach(button => {
            button.addEventListener('click', () => {
                Swal.fire({
                    title: 'តើអ្នកប្រាកដទេ?',
                    text: 'តើអ្នកប្រាកដទេថាចង់លុបអ្នកប្រើប្រាស់នេះ?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'បាទ/ចាស លុប!',
                    cancelButtonText: 'ទេ បោះបង់'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        const response = await sendRequest('delete_user', { index: button.dataset.index });
                        if (response && response.status === 'success') {
                            Swal.fire('បានលុប!', 'អ្នកប្រើប្រាស់ត្រូវបានលុបដោយជោគជ័យ។', 'success').then(() => location.reload());
                        }
                    }
                });
            });
        });

        document.querySelectorAll('.duplicate-user').forEach(button => {
            button.addEventListener('click', () => {
                Swal.fire({
                    title: 'តើអ្នកប្រាកដទេ?',
                    text: 'តើអ្នកប្រាកដទេថាចង់ចម្លងអ្នកប្រើប្រាស់នេះ?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'បាទ/ចាស ចម្លង!',
                    cancelButtonText: 'ទេ បោះបង់'
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
                        user.username += ` (ចម្លង ${suffix - 1 || ''})`;
                        const response = await sendRequest('add_user', user);
                        if (response && response.status === 'success') {
                            Swal.fire('បានចម្លង!', 'អ្នកប្រើប្រាស់ត្រូវបានចម្លងដោយជោគជ័យ។', 'success').then(() => location.reload());
                        }
                    }
                });
            });
        });

        document.getElementById('saveFolder').addEventListener('click', async () => {
            const folderName = document.getElementById('folderName').value.trim();
            if (!folderName) {
                Swal.fire('កំហុស!', 'សូមបញ្ចូលឈ្មោះ Folder!', 'error');
                return;
            }

            const folder = {
                id: Date.now().toString(), // Simple unique ID
                name: folderName
            };

            try {
                const response = await sendRequest('add_folder', folder);
                if (response && response.status === 'success') {
                    Swal.fire('ជោគជ័យ!', 'Folder ត្រូវបានបន្ថែម!', 'success')
                        .then(() => location.reload());
                } else {
                    throw new Error(response.message || 'មានបញ្ហាក្នុងការរក្សាទុក Folder');
                }
            } catch (error) {
                Swal.fire('កំហុស!', error.message, 'error');
            }
        });

        document.querySelectorAll('.revoke-token').forEach(button => {
            button.addEventListener('click', () => {
                Swal.fire({
                    title: 'តើអ្នកប្រាកដទេ?',
                    text: 'តើអ្នកប្រាកដទេថាចង់លុប Token នេះ?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'បាទ/ចាស លុប!',
                    cancelButtonText: 'ទេ បោះបង់'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        const response = await sendRequest('revoke_token', { token: button.dataset.token });
                        if (response && response.status === 'success') {
                            Swal.fire('បានលុប', 'Token ត្រូវបានលុបដោយជោគជ័យ។', 'success').then(() => location.reload());
                        }
                    }
                });
            });
        });

        function addUserEntry(containerId, userId = '', tolerance = 0.001) {
            const html = `
                    <div class="user-entry mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <select class="form-control user-select" required>
                                    <option value="">ជ្រើសរើសអ្នកប្រើប្រាស់</option>
                                    ${users.map(user => `
                                        <option value="${user.id}" ${user.id === userId ? 'selected' : ''}>
                                            ${user.username} (${user.id})
                                        </option>
                                    `).join('')}
                                </select>
                            </div>
                            <div class="col-md-4">
                                <input type="number" step="0.00001" class="form-control user-tolerance" 
                                       placeholder="Tolerance" value="${tolerance}" required>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-danger remove-user-entry"><i class="fas fa-trash"></i> លុប</button>
                            </div>
                        </div>
                    </div>
                `;
            document.getElementById(containerId).insertAdjacentHTML('beforeend', html);
            document.querySelectorAll(`#${containerId} .remove-user-entry`).forEach(btn => {
                btn.addEventListener('click', () => btn.closest('.user-entry').remove());
            });
        }

        document.getElementById('addUserEntry').addEventListener('click', () => addUserEntry('allowedUsersContainer'));
        document.getElementById('editAddUserEntry').addEventListener('click', () => addUserEntry('editAllowedUsersContainer'));

        document.getElementById('saveLocation').addEventListener('click', async () => {
            const allowedUsers = [];
            document.querySelectorAll('#allowedUsersContainer .user-entry').forEach(entry => {
                const userId = entry.querySelector('.user-select').value;
                const tolerance = parseFloat(entry.querySelector('.user-tolerance').value);
                if (userId && !isNaN(tolerance)) allowedUsers.push({ user_id: userId, tolerance });
            });

            const location = {
                id: document.getElementById('locationId').value || Date.now().toString(), // Temporary ID, will be overwritten by backend
                name: document.getElementById('locationName').value.trim(),
                branch: document.getElementById('locationBranch').value,
                latitude: parseFloat(document.getElementById('latitude').value),
                longitude: parseFloat(document.getElementById('longitude').value),
                users: allowedUsers
            };

            if (!location.name || !location.branch || isNaN(location.latitude) || isNaN(location.longitude)) {
                Swal.fire('កំហុស!', 'សូមបញ្ចូលទិន្នន័យឱ្យគ្រប់គ្រាន់!', 'error');
                return;
            }

            try {
                const response = await sendRequest('add_location', location);
                if (response && response.status === 'success') {
                    Swal.fire('ជោគជ័យ!', 'ទីតាំងត្រូវបានបន្ថែម!', 'success').then(() => location.reload());
                } else {
                    Swal.fire('កំហុស!', response.message || 'មានបញ្ហាក្នុងការបន្ថែមទីតាំង', 'error');
                }
            } catch (error) {
                Swal.fire('កំហុស!', error.message, 'error');
            }
        });

        document.querySelectorAll('.delete-location').forEach(button => {
            button.addEventListener('click', () => {
                const index = button.dataset.index;
                const locationName = allowedLocations[index]?.name || '';
                Swal.fire({
                    title: 'តើអ្នកប្រាកដទេ?',
                    text: `តើអ្នកប្រាកដទេថាចង់លុបទីតាំង "${locationName}" នេះ?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'បាទ/ចាស លុប!',
                    cancelButtonText: 'ទេ បោះបង់'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                            const response = await sendRequest('delete_location', { index });
                            if (response && response.status === 'success') {
                                Swal.fire('បានលុប!', 'ទីតាំងត្រូវបានលុបដោយជោគជ័យ។', 'success')
                                    .then(() => location.reload());
                            } else {
                                throw new Error(response.message || 'មានបញ្ហាក្នុងការលុបទីតាំង');
                            }
                        } catch (error) {
                            Swal.fire('កំហុស!', error.message, 'error');
                        }
                    }
                });
            });
        });




        document.querySelectorAll('.duplicate-location').forEach(button => {
            button.addEventListener('click', () => {
                Swal.fire({
                    title: 'តើអ្នកប្រាកដទេ?',
                    text: 'តើអ្នកប្រាកដទេថាចង់ចម្លងទីតាំងនេះ?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'បាទ/ចាស ចម្លង!',
                    cancelButtonText: 'ទេ បោះបង់'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        const index = button.dataset.index;
                        const location = { ...allowedLocations[index] }; // Create a copy of the location
                        let newName = `${location.name}-copy`;
                        let suffix = 1;

                        // Ensure the new name is unique
                        while (allowedLocations.some(loc => loc.name === newName)) {
                            newName = `${location.name}-copy${suffix++}`;
                        }

                        location.name = newName; // Update the name to avoid conflicts

                        try {
                            const response = await sendRequest('add_location', location);
                            if (response && response.status === 'success') {
                                Swal.fire('បានចម្លង!', 'ទីតាំងត្រូវបានចម្លងដោយជោគជ័យ។', 'success').then(() => location.reload());
                            } else {
                                throw new Error(response.message || 'មានបញ្ហាក្នុងការចម្លងទីតាំង');
                            }
                        } catch (error) {
                            Swal.fire('កំហុស!', error.message, 'error');
                        }
                    }
                });
            });
        });


        // --- Optimized Edit Location Modal User Filtering ---

        // Efficiently update user selects to prevent duplicates
        function updateUserSelectOptions(container) {
            const selects = Array.from(container.querySelectorAll('.user-select'));
            const chosen = selects.map(s => s.value).filter(Boolean);
            selects.forEach(sel => {
            const currentValue = sel.value;
            Array.from(sel.options).forEach(opt => {
                if (!opt.value) return;
                opt.disabled = (opt.value !== currentValue && chosen.includes(opt.value));
            });
            });
        }

        // Optimized addUserEntry for both add/edit modals
        function addUserEntry(containerId, userId = '', tolerance = 0.001) {
            const container = document.getElementById(containerId);
            // Only collect selected user ids in this container
            const selectedUserIds = Array.from(container.querySelectorAll('.user-select')).map(sel => sel.value).filter(Boolean);
            // If editing, allow the current userId to be shown (for editing existing entry)
            const availableUsers = users.filter(user => userId ? (user.id === userId || !selectedUserIds.includes(user.id)) : !selectedUserIds.includes(user.id));
            // Build options HTML once
            const optionsHtml = availableUsers.map(user =>
            `<option value="${user.id}"${user.id === userId ? ' selected' : ''}>${user.username} (${user.id})</option>`
            ).join('');
            const html = `
            <div class="user-entry mb-3">
                <div class="row">
                <div class="col-md-6">
                    <select class="form-control user-select" required>
                    <option value="">ជ្រើសរើសអ្នកប្រើប្រាស់</option>
                    ${optionsHtml}
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="number" step="0.00001" class="form-control user-tolerance" 
                    placeholder="Tolerance" value="${tolerance}" required>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-danger remove-user-entry"><i class="fas fa-trash"></i> លុប</button>
                </div>
                </div>
            </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            // Remove entry event
            container.lastElementChild.querySelector('.remove-user-entry').onclick = function () {
            this.closest('.user-entry').remove();
            updateUserSelectOptions(container);
            };
            // Update disables on change
            container.lastElementChild.querySelector('.user-select').onchange = function () {
            updateUserSelectOptions(container);
            };
            // Initial disables
            updateUserSelectOptions(container);
        }

        // Edit Location Modal: populate fields and user list
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
            (loc.users || []).forEach(user => addUserEntry('editAllowedUsersContainer', user.user_id, user.tolerance));
            document.getElementById('editLocationModal').addEventListener('shown.bs.modal', () => setupEditMap(loc.latitude, loc.longitude), { once: true });
            });
        });

        document.getElementById('addUserEntry').addEventListener('click', () => addUserEntry('allowedUsersContainer'));
        document.getElementById('editAddUserEntry').addEventListener('click', () => addUserEntry('editAllowedUsersContainer'));

        // Edit Location Save
        document.getElementById('updateLocation').addEventListener('click', async () => {
            const allowedUsers = [];
            document.querySelectorAll('#editAllowedUsersContainer .user-entry').forEach(entry => {
            const userId = entry.querySelector('.user-select').value;
            const tolerance = parseFloat(entry.querySelector('.user-tolerance').value);
            if (userId && !isNaN(tolerance)) allowedUsers.push({ user_id: userId, tolerance });
            });
            const location = {
            index: parseInt(document.getElementById('editLocationIndex').value),
            id: document.getElementById('editLocationId').value,
            name: document.getElementById('editLocationName').value.trim(),
            branch: document.getElementById('editLocationBranch').value,
            latitude: parseFloat(document.getElementById('editLatitude').value),
            longitude: parseFloat(document.getElementById('editLongitude').value),
            users: allowedUsers
            };
            if (!location.name || !location.branch || isNaN(location.latitude) || isNaN(location.longitude)) {
            Swal.fire('កំហុស!', 'សូមបញ្ចូលទិន្នន័យឱ្យគ្រប់គ្រាន់!', 'error');
            return;
            }
            try {
            const response = await sendRequest('edit_location', location);
            if (response && response.status === 'success') {
                const modal = bootstrap.Modal.getInstance(document.getElementById('editLocationModal'));
                modal.hide();
                await Swal.fire('ជោគជ័យ!', 'ទីតាំងត្រូវបានធ្វើបច្ចុប្បន្នភាព!', 'success');
                window.location.reload();
            } else {
                Swal.fire('កំហុស!', response.message || 'មានបញ្ហាក្នុងការធ្វើបច្ចុប្បន្នភាពទីតាំង', 'error');
            }
            } catch (error) {
            Swal.fire('កំហុស!', error.message, 'error');
            }
        });

        // Duplicate Location (no change, already fast)
        document.querySelectorAll('.duplicate-location').forEach(button => {
            button.addEventListener('click', () => {
            Swal.fire({
                title: 'តើអ្នកប្រាកដទេ?',
                text: 'តើអ្នកប្រាកដទេថាចង់ចម្លងទីតាំងនេះ?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'បាទ/ចាស ចម្លង!',
                cancelButtonText: 'ទេ បោះបង់'
            }).then(async (result) => {
                if (result.isConfirmed) {
                const index = button.dataset.index;
                const location = { ...allowedLocations[index] };
                let newName = `${location.name}-copy`;
                let suffix = 1;
                while (allowedLocations.some(loc => loc.name === newName)) {
                    newName = `${location.name}-copy${suffix++}`;
                }
                location.name = newName;
                location.id = Date.now().toString();
                try {
                    const response = await sendRequest('add_location', location);
                    if (response && response.status === 'success') {
                    Swal.fire('បានចម្លង!', 'ទីតាំងត្រូវបានចម្លងដោយជោគជ័យ។', 'success').then(() => location.reload());
                    } else {
                    Swal.fire('កំហុស!', response.message || 'មានបញ្ហាក្នុងការចម្លងទីតាំង', 'error');
                    }
                } catch (error) {
                    Swal.fire('កំហុស!', error.message, 'error');
                }
                }
            });
            });
        });

        // --- Map setup (unchanged, already efficient) ---
        let addMap, editMap, addMarker, editMarker;
        function setupAddMap() {
            const defaultLatLng = [11.562108, 104.916009];
            addMap = L.map('addMap').setView(defaultLatLng, 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(addMap);
            addMarker = L.marker(defaultLatLng, { draggable: true }).addTo(addMap);
            addMap.on('click', updateMarker);
            addMarker.on('dragend', updateMarker);
            function updateMarker(e) {
            const lat = e.latlng ? e.latlng.lat : e.target.getLatLng().lat;
            const lng = e.latlng ? e.latlng.lng : e.target.getLatLng().lng;
            addMarker.setLatLng([lat, lng]);
            document.getElementById('latitude').value = lat.toFixed(5);
            document.getElementById('longitude').value = lng.toFixed(5);
            }
            const observer = new MutationObserver(() => {
            if (document.getElementById('addLocationModal').classList.contains('show')) {
                addMap.invalidateSize();
            }
            });
            observer.observe(document.getElementById('addLocationModal'), { attributes: true });
        }
        function setupEditMap(lat, lng) {
            editMap = L.map('editMap').setView([parseFloat(lat), parseFloat(lng)], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(editMap);
            editMarker = L.marker([parseFloat(lat), parseFloat(lng)], { draggable: true }).addTo(editMap);
            editMap.on('click', function (e) {
            const lat = e.latlng.lat;
            const lng = e.latlng.lng;
            editMarker.setLatLng([lat, lng]);
            document.getElementById('editLatitude').value = lat.toFixed(5);
            document.getElementById('editLongitude').value = lng.toFixed(5);
            });
            editMarker.on('dragend', function (e) {
            const lat = e.target.getLatLng().lat;
            const lng = e.target.getLatLng().lng;
            document.getElementById('editLatitude').value = lat.toFixed(5);
            document.getElementById('editLongitude').value = lng.toFixed(5);
            });
            setTimeout(() => editMap.invalidateSize(), 100);
        }


        // Edit Folder
        document.querySelectorAll('.edit-folder').forEach(button => {
            button.addEventListener('click', () => {
                const index = button.dataset.index;
                const folder = folders[index];
                document.getElementById('editFolderIndex').value = index;
                document.getElementById('editFolderName').value = folder.name;
            });
        });

        document.getElementById('updateFolder').addEventListener('click', async () => {
            const folderName = document.getElementById('editFolderName').value.trim();
            const index = document.getElementById('editFolderIndex').value;

            if (!folderName) {
                Swal.fire('កំហុស!', 'សូមបញ្ចូលឈ្មោះ Folder!', 'error');
                return;
            }

            const folder = {
                index: parseInt(index),
                id: folders[index].id,
                name: folderName
            };

            try {
                const response = await sendRequest('edit_folder', folder);
                if (response && response.status === 'success') {
                    // Update users with old folder name
                    const oldFolderName = folders[index].name;
                    const updatedUsers = users.map(user => {
                        if (user.folder === oldFolderName) {
                            return { ...user, folder: folderName };
                        }
                        return user;
                    });

                    await sendRequest('update_users_folder', { users: updatedUsers });

                    Swal.fire('ជោគជ័យ!', 'Folder ត្រូវបានធ្វើបច្ចុប្បន្នភាព!', 'success')
                        .then(() => location.reload());
                } else {
                    throw new Error(response.message || 'មានបញ្ហាក្នុងការធ្វើបច្ចុប្បន្នភាព Folder');
                }
            } catch (error) {
                Swal.fire('កំហុស!', error.message, 'error');
            }
        });

        // Delete Folder
        document.querySelectorAll('.delete-folder').forEach(button => {
            button.addEventListener('click', () => {
                const index = button.dataset.index;
                const folderName = folders[index].name;
                const userCount = users.filter(u => u.folder === folderName).length;

                Swal.fire({
                    title: 'តើអ្នកប្រាកដទេ?',
                    text: userCount > 0
                        ? `Folder នេះមានអ្នកប្រើ ${userCount} នាក់។ ការលុបនឹងផ្លាស់ប្តូរអ្នកប្រើទាំងនេះទៅ "N/A"។ តើអ្នកប្រាកដទេ?`
                        : 'តើអ្នកប្រាកដទេថាចង់លុប Folder នេះ?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'បាទ/ចាស លុប!',
                    cancelButtonText: 'ទេ បោះបង់'
                }).then(async (result) => {
                    if (result.isConfirmed) {
                        try {
                            // Update users to remove folder reference
                            if (userCount > 0) {
                                const updatedUsers = users.map(user => {
                                    if (user.folder === folderName) {
                                        return { ...user, folder: 'N/A' };
                                    }
                                    return user;
                                });
                                await sendRequest('update_users_folder', { users: updatedUsers });
                            }

                            const response = await sendRequest('delete_folder', { index });
                            if (response && response.status === 'success') {
                                Swal.fire('បានលុប!', 'Folder ត្រូវបានលុបដោយជោគជ័យ។', 'success')
                                    .then(() => location.reload());
                            } else {
                                throw new Error(response.message || 'មានបញ្ហាក្នុងការលុប Folder');
                            }
                        } catch (error) {
                            Swal.fire('កំហុស!', error.message, 'error');
                        }
                    }
                });
            });
        });


        document.getElementById('addLocationModal').addEventListener('shown.bs.modal', setupAddMap);
        document.querySelectorAll('.edit-location').forEach(button => {
            button.addEventListener('click', () => {
                const index = button.dataset.index;
                const loc = allowedLocations[index];
                document.getElementById('editLocationIndex').value = index;
                document.getElementById('editLocationName').value = loc.name || '';
                document.getElementById('editLocationBranch').value = loc.branch || '';
                document.getElementById('editLatitude').value = loc.latitude;
                document.getElementById('editLongitude').value = loc.longitude;

                const container = document.getElementById('editAllowedUsersContainer');
                container.innerHTML = '';
                (loc.users || []).forEach(user => addUserEntry('editAllowedUsersContainer', user.user_id, user.tolerance));

                document.getElementById('editLocationModal').addEventListener('shown.bs.modal', () => setupEditMap(loc.latitude, loc.longitude), { once: true });
            });
        });
        <?php endif; ?>
    </script>
</body>

</html>