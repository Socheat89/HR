<?php
//--- បង្ហាញកំហុស (Error Reporting) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//--- Admin Authentication Check ---
// Allow POST requests for admin actions (like logout) even when logged in
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    session_start();
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: admin_login.php');
        exit;
    }
} else {
    session_start();
    // For POST requests, we still need session but don't redirect if not logged in
    // The action handlers will check authentication as needed
}

require 'db.php'; // <-- ត្រូវប្រាកដថា db.php នៅក្នុង Folder ដូចគ្នា

$create_user_message = '';
$trips = [];

// --- ADMIN LOGIC (BACKEND) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        // JSON input from AJAX
        header('Content-Type: application/json');
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        $action = $data['action'] ?? null;
    } else {
        // Form data from HTML forms
        $data = $_POST;
        $action = $_POST['action'] ?? null;
    }

    // --- CREATE USER ACTION ---
    if ($action === 'create_user') {
        $username = $data['username'];
        $login_id = $data['login_id']; // <-- Field ថ្មី
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        $role = $data['role']; // 'user' or 'admin'

        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, login_id, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$username, $login_id, $password, $role]);
            $create_user_message = "User '{$username}' (ID: {$login_id}) created successfully!";
        } catch (PDOException $e) {
            $create_user_message = 'Error creating user: ' . $e->getMessage();
        }
    }

    // --- GET USER ACTION ---
    if ($action === 'get_user') {
        // Check admin authentication
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            echo json_encode(['success' => false, 'message' => 'Admin authentication required.']);
            exit;
        }

        $user_id = $data['user_id'];

        try {
            $stmt = $pdo->prepare("SELECT id, username, login_id, role FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            if ($user) {
                echo json_encode(['success' => true, 'user' => $user]);
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found.']);
            }
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error fetching user: ' . $e->getMessage()]);
            exit;
        }
    }

    // --- EDIT USER ACTION ---
    if ($action === 'edit_user') {
        // Check admin authentication
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            echo json_encode(['success' => false, 'message' => 'Admin authentication required.']);
            exit;
        }

        $user_id = $data['user_id'];
        $username = $data['username'];
        $login_id = $data['login_id'];
        $role = $data['role'];

        try {
            $stmt = $pdo->prepare("UPDATE users SET username = ?, login_id = ?, role = ? WHERE id = ?");
            $stmt->execute([$username, $login_id, $role, $user_id]);
            echo json_encode(['success' => true, 'message' => 'User updated successfully!']);
            exit;
        } catch (PDOException $e) {
            if ($e->errorInfo[1] == 1062) {
                echo json_encode(['success' => false, 'message' => 'Login ID or username already exists.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating user: ' . $e->getMessage()]);
            }
            exit;
        }
    }

    // --- RESET USER PASSWORD ACTION ---
    if ($action === 'reset_user_password') {
        // Check admin authentication
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            echo json_encode(['success' => false, 'message' => 'Admin authentication required.']);
            exit;
        }

        $user_id = $data['user_id'];

        // Generate a new random password
        $new_password = bin2hex(random_bytes(8)); // 16 character random password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            echo json_encode(['success' => true, 'new_password' => $new_password, 'message' => 'Password reset successfully!']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error resetting password: ' . $e->getMessage()]);
            exit;
        }
    }

    // --- DELETE USER ACTION ---
    if ($action === 'delete_user') {
        // Check admin authentication
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            echo json_encode(['success' => false, 'message' => 'Admin authentication required.']);
            exit;
        }

        $user_id = $data['user_id'];

        // Prevent deleting admin users for safety
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user && $user['role'] === 'admin') {
            echo json_encode(['success' => false, 'message' => 'Cannot delete admin users.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            echo json_encode(['success' => true, 'message' => 'User deleted successfully!']);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error deleting user: ' . $e->getMessage()]);
            exit;
        }
    }

    // --- ADMIN LOGOUT ACTION ---
    if ($action === 'admin_logout') {
        // Check admin authentication
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            echo json_encode(['success' => false, 'message' => 'Not logged in.']);
            exit;
        }

        // Clear admin session
        session_unset();
        session_destroy();

        echo json_encode(['success' => true, 'message' => 'Logged out successfully!']);
        exit;
    }

    // --- LIST CUSTOMERS ACTION ---
    if ($action === 'list_customers') {
        // Check admin authentication
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            echo json_encode(['success' => false, 'message' => 'Admin authentication required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT id, name, lat, lng, map_link FROM customers ORDER BY id DESC");
            $stmt->execute();
            $rows = $stmt->fetchAll();
            echo json_encode(['success' => true, 'data' => $rows]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error fetching customers: ' . $e->getMessage()]);
            exit;
        }
    }

    // --- ADD CUSTOMER ACTION ---
    if ($action === 'add_customer') {
        // Check admin authentication
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            echo json_encode(['success' => false, 'message' => 'Admin authentication required.']);
            exit;
        }

        $name = $data['name'] ?? '';
        $lat = isset($data['lat']) ? floatval($data['lat']) : null;
        $lng = isset($data['lng']) ? floatval($data['lng']) : null;
        $map_link = $data['map_link'] ?? '';

        if (empty($name) || $lat === null || $lng === null) {
            echo json_encode(['success' => false, 'message' => 'Missing name or coordinates']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO customers (name, lat, lng, map_link, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $lat, $lng, $map_link]);
            $cust_id = $pdo->lastInsertId();
            echo json_encode(['success' => true, 'customer_id' => $cust_id]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error adding customer: ' . $e->getMessage()]);
            exit;
        }
    }

    // --- FETCH ROUTE ACTION (OSRM with server-side caching) ---
    if ($action === 'fetch_route') {
        // Check admin authentication
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            echo json_encode(['success' => false, 'message' => 'Admin authentication required.']);
            exit;
        }

        $s_lat = isset($data['start_lat']) ? floatval($data['start_lat']) : null;
        $s_lng = isset($data['start_lng']) ? floatval($data['start_lng']) : null;
        $e_lat = isset($data['end_lat']) ? floatval($data['end_lat']) : null;
        $e_lng = isset($data['end_lng']) ? floatval($data['end_lng']) : null;

        if ($s_lat === null || $s_lng === null || $e_lat === null || $e_lng === null) {
            echo json_encode(['success' => false, 'message' => 'Missing coordinates']);
            exit;
        }

        // Cache directory
        $cacheDir = __DIR__ . '/cache/osrm';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        // Use rounded coordinates to reduce cache granularity
        $key = md5(sprintf('%.6f,%.6f:%.6f,%.6f', $s_lat, $s_lng, $e_lat, $e_lng));
        $cacheFile = $cacheDir . '/' . $key . '.json';
        $ttl = 60 * 60 * 24; // 24 hours

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
            $json = file_get_contents($cacheFile);
            if ($json !== false) {
                echo $json;
                exit;
            }
        }

        // Fetch from OSRM
        $osrmUrl = sprintf('https://router.project-osrm.org/route/v1/driving/%.6f,%.6f;%.6f,%.6f?overview=full&geometries=geojson', $s_lng, $s_lat, $e_lng, $e_lat);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $osrmUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'TrackerAdmin/1.0');
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $httpCode !== 200) {
            // don't cache failed responses; return error so client falls back
            echo json_encode(['success' => false, 'message' => 'Routing service error', 'http' => $httpCode, 'curl_error' => $curlErr]);
            exit;
        }

        $j = json_decode($resp, true);
        if (!$j || !isset($j['routes'][0]['geometry']['coordinates'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid routing response']);
            exit;
        }

        // Convert coordinates to [lat,lng]
        $coords = array_map(function($c){ return [floatval($c[1]), floatval($c[0])]; }, $j['routes'][0]['geometry']['coordinates']);
        $distance = isset($j['routes'][0]['distance']) ? floatval($j['routes'][0]['distance']) : null;

        $out = json_encode(['success' => true, 'coords' => $coords, 'distance' => $distance]);
        // Save cache
        @file_put_contents($cacheFile, $out);
        echo $out;
        exit;
    }

    // --- GET USER LOCATION ACTION ---
    if ($action === 'get_user_location') {
        // Check admin authentication
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            echo json_encode(['success' => false, 'message' => 'Admin authentication required.']);
            exit;
        }

        $user_id = $data['user_id'];

        try {
            // Get the latest active trip's current location, or start location if no current
            $stmt = $pdo->prepare("SELECT start_lat, start_lng, current_lat, current_lng FROM trips WHERE user_id = ? AND (status IS NULL OR status != 'completed') ORDER BY id DESC LIMIT 1");
            $stmt->execute([$user_id]);
            $trip = $stmt->fetch();

            if ($trip) {
                // Use current location if available, otherwise start location
                $lat = $trip['current_lat'] ?? $trip['start_lat'];
                $lng = $trip['current_lng'] ?? $trip['start_lng'];
                if ($lat && $lng) {
                    echo json_encode(['success' => true, 'location' => ['lat' => floatval($lat), 'lng' => floatval($lng)]]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'No location data available for this user.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'No active trip found for this user.']);
            }
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error fetching user location: ' . $e->getMessage()]);
            exit;
        }
    }

    // --- GET USER TRIPS ACTION ---
    if ($action === 'get_user_trips') {
        // Check admin authentication
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            echo json_encode(['success' => false, 'message' => 'Admin authentication required.']);
            exit;
        }

        $user_id = $data['user_id'];

        try {
            $stmt = $pdo->prepare("SELECT id, start_time, end_time, distance_km, status, start_lat, start_lng, end_lat, end_lng FROM trips WHERE user_id = ? ORDER BY start_time DESC");
            $stmt->execute([$user_id]);
            $trips = $stmt->fetchAll();
            echo json_encode(['success' => true, 'trips' => $trips]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error fetching user trips: ' . $e->getMessage()]);
            exit;
        }
    }

    // --- GET USER STATUSES ACTION ---
    if ($action === 'get_user_statuses') {
        // Check admin authentication
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            echo json_encode(['success' => false, 'message' => 'Admin authentication required.']);
            exit;
        }

        try {
            // Get all users with their travel status
            $stmt = $pdo->query("
                SELECT u.id, u.username, 
                       CASE WHEN t.id IS NOT NULL THEN 1 ELSE 0 END as is_traveling
                FROM users u
                LEFT JOIN trips t ON u.id = t.user_id AND (t.status IS NULL OR t.status != 'completed')
                ORDER BY u.id DESC
            ");
            $users = $stmt->fetchAll();
            echo json_encode(['success' => true, 'users' => $users]);
            exit;
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Error fetching user statuses: ' . $e->getMessage()]);
            exit;
        }
    }
}

// --- LOAD DATA FOR DASHBOARD ---
$stmt = $pdo->query("
    SELECT trips.*, users.username 
    FROM trips 
    JOIN users ON trips.user_id = users.id 
    WHERE trips.status = 'completed'
    ORDER BY trips.end_time DESC
");
$trips = $stmt->fetchAll();

// --- LOAD USERS FOR DASHBOARD ---
$stmt = $pdo->query("
    SELECT id, username, login_id, role
    FROM users
    ORDER BY id DESC
");
$users = $stmt->fetchAll();?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
        <style>
            :root {
                --primary: #3b82f6;
                --primary-dark: #2563eb;
                --secondary: #06b6d4;
                --accent: #8b5cf6;
                --success: #10b981;
                --warning: #f59e0b;
                --error: #ef4444;
                --gray-50: #f9fafb;
                --gray-100: #f3f4f6;
                --gray-200: #e5e7eb;
                --gray-300: #d1d5db;
                --gray-400: #9ca3af;
                --gray-500: #6b7280;
                --gray-600: #4b5563;
                --gray-700: #374151;
                --gray-800: #1f2937;
                --gray-900: #111827;
                --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
                --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
                --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                --border-radius: 12px;
                --border-radius-lg: 16px;
            }

            * {
                box-sizing: border-box;
            }

            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                min-height: 100vh;
                margin: 0;
                color: var(--gray-800);
                line-height: 1.6;
            }

            /* Layout */
            .flex {
                display: flex;
            }

            .min-h-screen {
                min-height: 100vh;
            }

            .flex-1 {
                flex: 1 1 0%;
            }

            .w-64 {
                width: 16rem;
            }

            .grid {
                display: grid;
            }

            .grid-cols-1 {
                grid-template-columns: repeat(1, minmax(0, 1fr));
            }

            @media (min-width: 768px) {
                .md\\:grid-cols-2 {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            .gap-3 {
                gap: 0.75rem;
            }

            .gap-4 {
                gap: 1rem;
            }

            .mb-4 {
                margin-bottom: 1rem;
            }

            .mb-6 {
                margin-bottom: 1.5rem;
            }

            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 0 1rem;
            }

            /* Top Navigation */
            .top-nav {
                background: white;
                border-bottom: 1px solid var(--gray-200);
                box-shadow: var(--shadow-sm);
                position: sticky;
                top: 0;
                z-index: 10;
            }

            .nav-tab {
                color: var(--gray-600);
                text-decoration: none;
                transition: all 0.2s ease;
                border-radius: var(--border-radius);
                margin: 0 0.25rem;
            }

            .nav-tab:hover {
                background: var(--gray-50);
                color: var(--gray-900);
            }

            .nav-tab.active {
                background: linear-gradient(135deg, var(--primary), var(--primary-dark));
                color: white;
                box-shadow: var(--shadow-md);
            }

            .nav-tab.active span svg {
                color: white;
            }

            .nav-tab span {
                display: flex;
                align-items: center;
                font-weight: 500;
            }

            .nav-tab svg {
                width: 1rem;
                height: 1rem;
                margin-right: 0.5rem;
                transition: all 0.2s ease;
            }

            /* Main Content */
            .main-content {
                padding: 2rem;
                background: transparent;
            }

            .page-title {
                font-size: 2.5rem;
                font-weight: 800;
                color: var(--gray-900);
                margin-bottom: 2rem;
                background: linear-gradient(135deg, var(--gray-900), var(--gray-600));
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                letter-spacing: -0.025em;
            }

            /* Cards */
            .card {
                background: white;
                border-radius: var(--border-radius-lg);
                box-shadow: var(--shadow);
                padding: 2rem;
                border: 1px solid var(--gray-100);
                transition: all 0.3s ease;
                margin-bottom: 2rem;
            }

            .card:hover {
                box-shadow: var(--shadow-lg);
                transform: translateY(-2px);
            }

            .card-header {
                margin-bottom: 1.5rem;
                padding-bottom: 1rem;
                border-bottom: 2px solid var(--gray-100);
            }

            .card-title {
                font-size: 1.5rem;
                font-weight: 700;
                color: var(--gray-900);
                margin: 0;
            }

            .card-subtitle {
                font-size: 0.875rem;
                color: var(--gray-500);
                margin: 0.5rem 0 0 0;
            }

            /* Forms */
            .form-group {
                margin-bottom: 1.5rem;
            }

            .form-label {
                display: block;
                font-size: 0.875rem;
                font-weight: 600;
                color: var(--gray-700);
                margin-bottom: 0.5rem;
            }

            .form-input {
                width: 100%;
                padding: 0.75rem 1rem;
                border: 2px solid var(--gray-200);
                border-radius: var(--border-radius);
                font-size: 1rem;
                transition: all 0.2s ease;
                background: white;
            }

            .form-input:focus {
                outline: none;
                border-color: var(--primary);
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }

            .form-select {
                width: 100%;
                padding: 0.75rem 1rem;
                border: 2px solid var(--gray-200);
                border-radius: var(--border-radius);
                font-size: 1rem;
                background: white;
                cursor: pointer;
            }

            .form-select:focus {
                outline: none;
                border-color: var(--primary);
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }

            /* Buttons */
            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                padding: 0.75rem 1.5rem;
                border-radius: var(--border-radius);
                font-size: 0.875rem;
                font-weight: 600;
                text-decoration: none;
                border: none;
                cursor: pointer;
                transition: all 0.2s ease;
                position: relative;
                overflow: hidden;
            }

            .btn::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
                transition: left 0.5s;
            }

            .btn:hover::before {
                left: 100%;
            }

            .btn-primary {
                background: linear-gradient(135deg, var(--primary), var(--primary-dark));
                color: white;
                box-shadow: var(--shadow);
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: var(--shadow-lg);
            }

            .btn-secondary {
                background: var(--gray-100);
                color: var(--gray-700);
                box-shadow: var(--shadow-sm);
            }

            .btn-secondary:hover {
                background: var(--gray-200);
                transform: translateY(-1px);
                box-shadow: var(--shadow);
            }

            .btn-success {
                background: linear-gradient(135deg, var(--success), #059669);
                color: white;
                box-shadow: var(--shadow);
            }

            .btn-success:hover {
                transform: translateY(-2px);
                box-shadow: var(--shadow-lg);
            }

            .btn-sm {
                padding: 0.5rem 1rem;
                font-size: 0.75rem;
            }

            /* Tables */
            .table-container {
                background: white;
                border-radius: var(--border-radius-lg);
                box-shadow: var(--shadow);
                overflow: hidden;
                border: 1px solid var(--gray-100);
            }

            .table {
                width: 100%;
                border-collapse: collapse;
            }

            .table thead th {
                background: linear-gradient(135deg, var(--gray-50), var(--gray-100));
                padding: 1rem;
                text-align: left;
                font-weight: 600;
                color: var(--gray-700);
                border-bottom: 2px solid var(--gray-200);
                font-size: 0.875rem;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }

            .table tbody td {
                padding: 1rem;
                border-bottom: 1px solid var(--gray-100);
                color: var(--gray-700);
                vertical-align: middle;
            }

            .table tbody tr:hover {
                background: var(--gray-50);
            }

            .table tbody tr:last-child td {
                border-bottom: none;
            }

            /* Map */
            #map {
                border-radius: var(--border-radius-lg);
                border: 1px solid var(--gray-200);
                box-shadow: var(--shadow);
                height: 400px;
                position: relative;
                overflow: hidden;
            }

            #map::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(45deg, #f8fafc 25%, transparent 25%),
                            linear-gradient(-45deg, #f8fafc 25%, transparent 25%),
                            linear-gradient(45deg, transparent 75%, #f8fafc 75%),
                            linear-gradient(-45deg, transparent 75%, #f8fafc 75%);
                background-size: 20px 20px;
                background-position: 0 0, 0 10px, 10px -10px, -10px 0px;
                z-index: -1;
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            #map.loading::before {
                opacity: 0.1;
            }

            /* Alerts */
            .alert {
                padding: 1rem 1.5rem;
                border-radius: var(--border-radius);
                margin-bottom: 1rem;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                font-weight: 500;
            }

            .alert-success {
                background: rgba(16, 185, 129, 0.1);
                color: var(--success);
                border: 1px solid rgba(16, 185, 129, 0.2);
            }

            .alert-error {
                background: rgba(239, 68, 68, 0.1);
                color: var(--error);
                border: 1px solid rgba(239, 68, 68, 0.2);
            }

            /* Modal */
            .fixed {
                position: fixed;
            }

            .inset-0 {
                top: 0;
                right: 0;
                bottom: 0;
                left: 0;
            }

            .z-50 {
                z-index: 50;
            }

            .hidden {
                display: none;
            }

            /* Responsive */
            @media (max-width: 768px) {
                .flex {
                    flex-direction: column;
                }

                .w-64 {
                    width: 100%;
                    height: auto;
                }

                .sidebar {
                    height: auto;
                    position: static;
                }

                .main-content {
                    padding: 1rem;
                }

                .page-title {
                    font-size: 2rem;
                }

                .card {
                    padding: 1.5rem;
                }
            }

            /* Animations */
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .fade-in {
                animation: fadeIn 0.3s ease-out;
            }

            /* Custom scrollbar */
            .sidebar::-webkit-scrollbar {
                width: 6px;
            }

            .sidebar::-webkit-scrollbar-track {
                background: var(--gray-100);
            }

            .sidebar::-webkit-scrollbar-thumb {
                background: var(--gray-300);
                border-radius: 3px;
            }

            .sidebar::-webkit-scrollbar-thumb:hover {
                background: var(--gray-400);
            }

            /* Leaflet custom styles */
            .route-arrow {
                font-weight: bold;
                color: #4285F4;
                text-shadow: 1px 1px 1px rgba(255,255,255,0.7);
            }
            .custom-start-marker, .custom-end-marker, .custom-customer-marker {
                border: none !important;
            }

            /* Custom trip route markers */
            .custom-marker-start, .custom-marker-end {
                background: transparent !important;
                border: none !important;
            }
            .custom-marker-start div, .custom-marker-end div {
                pointer-events: none;
            }
        </style>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- Leaflet JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

</head>
<body class="bg-gray-100">

    <div class="min-h-screen">
        <!-- Top Navigation Tabs -->
        <div class="top-nav bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center space-x-8">
                        <h2 class="text-xl font-bold text-gray-900">Admin Dashboard</h2>
                        <nav class="flex space-x-1">
                            <a class="nav-tab px-4 py-2 text-sm font-medium rounded-md transition-colors duration-200" href="#trips" data-target="trips">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                    Trips
                                </span>
                            </a>
                            <a class="nav-tab px-4 py-2 text-sm font-medium rounded-md transition-colors duration-200" href="#customers" data-target="customers">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                    Customers
                                </span>
                            </a>
                            <a class="nav-tab px-4 py-2 text-sm font-medium rounded-md transition-colors duration-200" href="#userList" data-target="userList">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                    </svg>
                                    Users
                                </span>
                            </a>
                            <a class="nav-tab px-4 py-2 text-sm font-medium rounded-md transition-colors duration-200" href="#transport" data-target="transport">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                    </svg>
                                    Transportation
                                </span>
                            </a>
                        </nav>
                    </div>
                    <button onclick="adminLogout()" class="btn btn-secondary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Logout
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="container">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="page-title">Welcome Back</h1>
                        <p class="text-gray-600 mt-2">Logged in as: <span class="font-semibold text-blue-600"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span></p>
                    </div>
                </div>

    <div id="createUser" class="card fade-in">
        <div class="card-header">
            <h2 class="card-title">Create New User</h2>
            <p class="card-subtitle">Add a new user to the system with appropriate permissions</p>
        </div>
        <?php if ($create_user_message): ?>
            <div class="alert <?php echo strpos($create_user_message, 'Error') === 0 ? 'alert-error' : 'alert-success'; ?>">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <?php echo $create_user_message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin.php">
            <input type="hidden" name="action" value="create_user">

            <div class="form-group">
                <label class="form-label" for="username">Display Name</label>
                <input class="form-input" id="username" name="username" type="text" placeholder="Enter display name" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="login_id">Login ID</label>
                <input class="form-input" id="login_id" name="login_id" type="text" placeholder="Enter login ID" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input class="form-input" id="password" name="password" type="password" placeholder="Enter password" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="role">User Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="user">Regular User</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>

            <button class="btn btn-primary" type="submit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Create User
            </button>
        </form>
    </div>

    <div id="userList" class="card fade-in">
        <div class="card-header">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="card-title">User Management</h2>
                    <p class="card-subtitle">View and manage all registered users</p>
                </div>
                <button class="btn btn-primary" onclick="setActiveTabById('createUser')">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Create User
                </button>
            </div>
        </div>

        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Display Name</th>
                        <th>Login ID</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>Actions
                            <td><?php echo htmlspecialchars($user['id']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['login_id']); ?></td>
                            <td>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $user['role'] === 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                    <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($user['id']); ?> (ID-based)</td>
                            <td>
                                <div class="flex gap-2">
                                    <button class="btn btn-secondary btn-sm" onclick="editUser(<?php echo $user['id']; ?>)">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                        </svg>
                                        Edit
                                    </button>
                                    <button class="btn btn-secondary btn-sm" onclick="resetPassword(<?php echo $user['id']; ?>)">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                        </svg>
                                        Reset Password
                                    </button>
                                    <?php if ($user['role'] !== 'admin'): ?>
                                    <button class="btn btn-secondary btn-sm" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Delete
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-8 text-gray-500">
                                <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                                No users found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="transport" class="fade-in">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Transportation Operations</h2>
                <p class="card-subtitle">Monitor user locations and travel activities</p>
            </div>

            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Fetch active trips for status
                        $activeTrips = [];
                        $stmt = $pdo->query("SELECT user_id FROM trips WHERE status IS NULL OR status != 'completed'");
                        foreach ($stmt->fetchAll() as $trip) {
                            $activeTrips[$trip['user_id']] = true;
                        }
                        foreach ($users as $user): 
                            $isTraveling = isset($activeTrips[$user['id']]);
                        ?>
                        <tr id="user-row-<?php echo $user['id']; ?>">
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td>
                                <span id="status-<?php echo $user['id']; ?>" class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $isTraveling ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                                    <?php echo $isTraveling ? 'កំពុងចេញដំណើរ' : 'មិនទាន់ចេញដំណើរ'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm" onclick="viewUserLocation(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                    View Location
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="3" class="text-center py-8 text-gray-500">
                                No users found.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- User Location Modal -->
        <div id="userLocationModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
            <div class="flex items-center justify-center min-h-screen p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-screen overflow-y-auto">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900" id="userLocationTitle">User Location</h3>
                            <div class="flex items-center space-x-2">
                                <button onclick="clearUserRoute()" class="px-3 py-1 text-sm bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors">
                                    Clear Route
                                </button>
                                <button onclick="closeUserLocationModal()" class="text-gray-400 hover:text-gray-600">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div id="userMap" class="w-full h-96 mb-4"></div>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Distance</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="userTripsTable">
                                    <!-- Trips will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Edit User</h3>
                        <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <form id="editUserForm">
                        <input type="hidden" id="editUserId" name="user_id">

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Display Name</label>
                            <input type="text" id="editUsername" name="username" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Login ID</label>
                            <input type="text" id="editLoginId" name="login_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">User Role</label>
                            <select id="editRole" name="role" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="user">Regular User</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Update User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="trips" class="fade-in">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">All Completed Trips</h2>
                <p class="card-subtitle">View and manage all completed trip records</p>
            </div>

            <div class="mb-4 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <input id="tripSearch" type="text" class="form-input" placeholder="Search trips by user, date, distance..." style="min-width:280px;">
                    <select id="tripPerPage" class="form-select" style="width:120px;">
                        <option value="0">All</option>
                        <option value="10">10 / page</option>
                        <option value="25">25 / page</option>
                    </select>
                </div>
                <div class="text-sm text-gray-600">Total: <span id="tripsCount"><?php echo count($trips); ?></span></div>
            </div>

            <div class="table-container">
                <table id="tripsTable" class="table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Distance</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trips as $trip): 
                            // Prepare safe and formatted fields
                            $username = htmlspecialchars($trip['username']);
                            $distance = is_numeric($trip['distance_km']) ? number_format((float)$trip['distance_km'], 1) : htmlspecialchars($trip['distance_km']);
                            $start_time = !empty($trip['start_time']) ? date('Y-m-d H:i', strtotime($trip['start_time'])) : '';
                            $end_time = !empty($trip['end_time']) ? date('Y-m-d H:i', strtotime($trip['end_time'])) : '';
                            $start_lat = isset($trip['start_lat']) && is_numeric($trip['start_lat']) ? $trip['start_lat'] : '';
                            $start_lng = isset($trip['start_lng']) && is_numeric($trip['start_lng']) ? $trip['start_lng'] : '';
                            $end_lat = isset($trip['end_lat']) && is_numeric($trip['end_lat']) ? $trip['end_lat'] : '';
                            $end_lng = isset($trip['end_lng']) && is_numeric($trip['end_lng']) ? $trip['end_lng'] : '';
                            $start_coords = htmlspecialchars($start_lat . ',' . $start_lng);
                            $end_coords = htmlspecialchars($end_lat . ',' . $end_lng);
                            $map_link = "https://www.google.com/maps/dir/{$start_coords}/{$end_coords}";
                        ?>
                        <tr class="trip-row" data-username="<?php echo $username; ?>" data-distance="<?php echo $distance; ?>" data-start="<?php echo $start_time; ?>" data-end="<?php echo $end_time; ?>" data-start-lat="<?php echo $start_lat; ?>" data-start-lng="<?php echo $start_lng; ?>" data-end-lat="<?php echo $end_lat; ?>" data-end-lng="<?php echo $end_lng; ?>">
                            <td><?php echo $username; ?></td>
                            <td><?php echo $distance; ?> km</td>
                            <td><?php echo $start_time; ?></td>
                            <td><?php echo $end_time; ?></td>
                            <td><?php echo htmlspecialchars($start_lat . ', ' . $start_lng); ?></td>
                            <td><?php echo htmlspecialchars($end_lat . ', ' . $end_lng); ?></td>
                            <td>
                                <div class="flex gap-2">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="previewTripRoute(this)">Preview</button>
                                    <a href="<?php echo $map_link; ?>" target="_blank" class="btn btn-secondary btn-sm">Open in Maps</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($trips)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-8 text-gray-500">
                                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                    No completed trips found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Trips Map</h2>
                <p class="card-subtitle">Visual representation of all trip routes</p>
            </div>
            <div id="map" class="loading"></div>
        </div>
    </div>
        
        <div id="customers" class="card fade-in">
            <div class="card-header">
                <h2 class="card-title">Customers & Destinations</h2>
                <p class="card-subtitle">Manage customer locations and destinations</p>
            </div>

            <div id="custMsg"></div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="form-group">
                    <label class="form-label" for="adminCustName">Customer Name</label>
                    <input id="adminCustName" class="form-input" type="text" placeholder="Enter customer name">
                </div>
                <div class="form-group">
                    <label class="form-label" for="adminCustLink">Google Maps Link</label>
                    <input id="adminCustLink" class="form-input" type="text" placeholder="https://www.google.com/maps/...">
                </div>
            </div>

            <div class="flex gap-3 mb-6">
                <button id="adminAddCust" class="btn btn-success">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Add Customer
                </button>
                <button id="adminReloadCust" class="btn btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Reload List
                </button>
            </div>

            <div>
                <h3 class="text-lg font-semibold mb-4 text-gray-800">Existing Customers</h3>
                <div class="table-container">
                    <table id="customersTable" class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Coordinates</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="customersTBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
    </div>

    <script>
    // អថេរ map ត្រូវបានប្រកាសនៅខាងក្រៅ ដើម្បីអាចហៅប្រើបានគ្រប់ទីកន្លែង
    let map;
    // រក្សាទុក Markers របស់ Customers ដើម្បីងាយស្រួលលុបចេញពេល reload
    let customerMarkers = [];

    // Function to animate route drawing with progressive tracing
    function animateRouteDrawing(routePath, startPos, endPos, username, distance) {
        // Validate input parameters
        if (!routePath || !Array.isArray(routePath) || routePath.length < 2) {
            console.error('Invalid routePath provided to animateRouteDrawing');
            return;
        }

        if (!startPos || !endPos || typeof startPos.lat !== 'number' || typeof startPos.lng !== 'number' ||
            typeof endPos.lat !== 'number' || typeof endPos.lng !== 'number') {
            console.error('Invalid startPos or endPos provided to animateRouteDrawing');
            return;
        }

        let currentIndex = 0;
        const totalPoints = routePath.length;
        const animationSpeed = Math.max(1, Math.floor(totalPoints / 100)); // Adjust speed based on route length
        const drawnPath = [];
        let mainRoute, innerRoute;
        let arrowMarkers = [];
        let routeInfo;

        // Create initial empty polylines using Leaflet
        if (!map) {
            console.error('Map object not available for animateRouteDrawing');
            return;
        }

        mainRoute = L.polyline([], {
            color: '#4285F4',
            weight: 6,
            opacity: 0.9
        }).addTo(map);

        innerRoute = L.polyline([], {
            color: '#FFFFFF',
            weight: 2,
            opacity: 0.9
        }).addTo(map);

        // Animation function
        function animate() {
            // Add points to the path progressively
            for (let i = 0; i < animationSpeed && currentIndex < totalPoints; i++) {
                drawnPath.push([routePath[currentIndex][0], routePath[currentIndex][1]]);
                currentIndex++;
            }

            // Update polylines
            mainRoute.setLatLngs(drawnPath);
            innerRoute.setLatLngs(drawnPath);

            // Add arrows at intervals during animation
            if (currentIndex % Math.floor(totalPoints / 8) === 0 && currentIndex > 0 && currentIndex < totalPoints) {
                const arrowIndex = Math.max(0, currentIndex - 1);
                if (arrowIndex < routePath.length) {
                    const arrow = L.marker([routePath[arrowIndex][0], routePath[arrowIndex][1]], {
                        icon: L.divIcon({
                            html: '▶',
                            className: 'route-arrow',
                            iconSize: [12, 12],
                            iconAnchor: [6, 6]
                        })
                    }).addTo(map);
                    arrowMarkers.push(arrow);
                }
            }

            // Continue animation or finish
            if (currentIndex < totalPoints) {
                requestAnimationFrame(animate);
            } else {
                // Animation complete - add final info popup
                routeInfo = L.popup()
                    .setLatLng([endPos.lat, endPos.lng])
                    .setContent(`
                        <div style="font-family: Arial, sans-serif; padding: 8px; max-width: 250px;">
                            <strong style="color: #4285F4;">${username}</strong><br>
                            <span style="color: #666;">🚗 Start: ${startPos.lat.toFixed(4)}, ${startPos.lng.toFixed(4)}</span><br>
                            <span style="color: #666;">🏁 End: ${endPos.lat.toFixed(4)}, ${endPos.lng.toFixed(4)}</span><br>
                            <span style="color: #666;">🛣️ Route: ${distance} via roads</span><br>
                            <span style="color: #666;">✨ Animated route complete!</span>
                        </div>
                    `);

                // Add click listeners
                mainRoute.on('click', function(e) {
                    routeInfo.setLatLng(e.latlng).openOn(map);
                });

                innerRoute.on('click', function(e) {
                    routeInfo.setLatLng(e.latlng).openOn(map);
                });

                // Add hover effects
                mainRoute.on('mouseover', function() {
                    mainRoute.setStyle({ weight: 8, opacity: 1 });
                    innerRoute.setStyle({ weight: 3 });
                });

                mainRoute.on('mouseout', function() {
                    mainRoute.setStyle({ weight: 6, opacity: 0.9 });
                    innerRoute.setStyle({ weight: 2 });
                });

                innerRoute.on('mouseover', function() {
                    mainRoute.setStyle({ weight: 8, opacity: 1 });
                    innerRoute.setStyle({ weight: 3 });
                });

                innerRoute.on('mouseout', function() {
                    mainRoute.setStyle({ weight: 6, opacity: 0.9 });
                    innerRoute.setStyle({ weight: 2 });
                });
            }
        }

        // Start animation
        animate();
    }

    // Function to show map error
    function showMapError(message) {
        const mapElement = document.getElementById("map");
        if (mapElement) {
            mapElement.innerHTML = `
                <div style="padding: 20px; text-align: center; color: red; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px;">
                    <h3 style="margin: 0 0 10px 0; color: #dc3545;">Map Error</h3>
                    <p style="margin: 0; color: #6c757d;">${message}</p>
                    <button onclick="location.reload()" style="margin-top: 10px; padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Retry</button>
                </div>
            `;
            mapElement.classList.remove('loading');
        }
    }

    async function initMap() {
        // Prevent multiple initializations
        if (window.mapInitialized) {
            console.log('Map already initialized, skipping...');
            return;
        }

        try {
            // Check if map container exists
            const mapElement = document.getElementById("map");
            if (!mapElement) {
                console.error('Map container element not found');
                return;
            }

            const defaultLocation = [11.5564, 104.9282]; // Phnom Penh [lat, lng]

            // Initialize Leaflet map with OpenStreetMap
            map = L.map('map').setView(defaultLocation, 12);

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);

            // Mark as initialized
            window.mapInitialized = true;

            // Remove loading state
            mapElement.classList.remove('loading');
            console.log('OpenStreetMap loaded successfully');

            //--- ផ្នែកបង្ហាញ Trips នៅលើផែនទី ---
            <?php foreach ($trips as $trip):
                // Validate coordinates before generating JavaScript
                $start_lat = isset($trip['start_lat']) && is_numeric($trip['start_lat']) ? floatval($trip['start_lat']) : null;
                $start_lng = isset($trip['start_lng']) && is_numeric($trip['start_lng']) ? floatval($trip['start_lng']) : null;
                $end_lat = isset($trip['end_lat']) && is_numeric($trip['end_lat']) ? floatval($trip['end_lat']) : null;
                $end_lng = isset($trip['end_lng']) && is_numeric($trip['end_lng']) ? floatval($trip['end_lng']) : null;

                // Only generate JavaScript if all coordinates are valid
                if ($start_lat !== null && $start_lng !== null && $end_lat !== null && $end_lng !== null):
            ?>
            {
                const startPos = { lat: <?php echo $start_lat; ?>, lng: <?php echo $start_lng; ?> };
                const endPos = { lat: <?php echo $end_lat; ?>, lng: <?php echo $end_lng; ?> };

                // Marker សម្រាប់ចំណុចចាប់ផ្តើម - Leaflet style
                const startIcon = L.divIcon({
                    html: '<div style="background: #EA4335; border: 2px solid #FFFFFF; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #FFFFFF; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">🚗</div>',
                    className: 'custom-start-marker',
                    iconSize: [24, 24],
                    iconAnchor: [12, 12]
                });
                const startMarker = L.marker([startPos.lat, startPos.lng], { icon: startIcon })
                    .addTo(map)
                    .bindPopup('Start: <?php echo htmlspecialchars($trip['username']); ?>');

                // Marker សម្រាប់ចំណុចបញ្ចប់ - Leaflet style
                const endIcon = L.divIcon({
                    html: '<div style="background: #34A853; border: 2px solid #FFFFFF; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #FFFFFF; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">🏁</div>',
                    className: 'custom-end-marker',
                    iconSize: [24, 24],
                    iconAnchor: [12, 12]
                });
                const endMarker = L.marker([endPos.lat, endPos.lng], { icon: endIcon })
                    .addTo(map)
                    .bindPopup('Stop: <?php echo htmlspecialchars($trip['username']); ?> (<?php echo htmlspecialchars($trip['distance_km']); ?> KM)');

                // Request a driving route (road-following) from OSRM, fall back to straight line
                (async function(){
                    // OSRM expects lon,lat pairs
                    async function fetchRouteFromOSRM(sPos, ePos) {
                        try {
                            const resp = await postToAdmin('fetch_route', { start_lat: sPos.lat, start_lng: sPos.lng, end_lat: ePos.lat, end_lng: ePos.lng });
                            if (!resp || !resp.success) return null;
                            return { coords: resp.coords, distance: resp.distance };
                        } catch (err) {
                            console.error('fetchRouteFromOSRM error', err);
                            return null;
                        }
                    }

                    let routeInfo = await fetchRouteFromOSRM(startPos, endPos);

                    if (routeInfo && Array.isArray(routeInfo.coords) && routeInfo.coords.length > 1) {
                        // Use routed polyline
                        const routedPath = routeInfo.coords; // array of [lat, lng]
                        const routeDistanceKm = (routeInfo.distance / 1000).toFixed(1);
                        animateRouteDrawing(routedPath, startPos, endPos, <?php echo json_encode(htmlspecialchars($trip['username'])); ?>, routeDistanceKm + ' km');
                    } else {
                        // Fallback: straight line
                        const straight = [[startPos.lat, startPos.lng], [endPos.lat, endPos.lng]];
                        const routeDistance = map.distance([startPos.lat, startPos.lng], [endPos.lat, endPos.lng]);
                        const routeDistanceKm = (routeDistance / 1000).toFixed(1);
                        animateRouteDrawing(straight, startPos, endPos, <?php echo json_encode(htmlspecialchars($trip['username'])); ?>, routeDistanceKm + ' km');
                    }
                })();
            }
            <?php endif; endforeach; ?>

            // បន្ទាប់ពីផែនទី និង Trips បានបង្ហាញរួចរាល់, ចាប់ផ្តើមទាញយក និងបង្ហាញ Customers
            loadAndDrawCustomers();
        } catch (err) {
            console.error('initMap error', err);
            const mapElement = document.getElementById("map");
            if (mapElement) {
                showMapError('Map initialization error: ' + (err && err.message ? err.message : 'Unknown error'));
                mapElement.classList.remove('loading');
            }
        }
    }

    // Function សម្រាប់ទាញយក និងគូសបង្ហាញ Customers នៅលើផែនទី
    async function loadAndDrawCustomers() {
        if (!map) {
            console.error("Map object is not initialized yet.");
            return;
        }

        // មុននឹងគូសថ្មី, លុប Markers ចាស់ៗចេញពីផែនទីทั้งหมด
        customerMarkers.forEach(marker => map.removeLayer(marker));
        customerMarkers = []; // សម្អាត array

        const resp = await postToAdmin('list_customers', {});

        if (!resp.success || !Array.isArray(resp.data)) {
             console.error('Failed to load customers for map:', resp.message);
             // Don't return, continue with empty customer list
             resp.data = [];
        }

        // គូស Marker សម្រាប់ Customer ម្នាក់ៗ - Leaflet style
        resp.data.forEach(customer => {
            const position = [parseFloat(customer.lat), parseFloat(customer.lng)];

            // Create custom icon for customer marker
            const customerIcon = L.divIcon({
                html: '<div style="background: #FBBC04; border: 2px solid #FFFFFF; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #000000; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">📍</div>',
                className: 'custom-customer-marker',
                iconSize: [24, 24],
                iconAnchor: [12, 12]
            });

            const customerMarker = L.marker(position, { icon: customerIcon })
                .addTo(map)
                .bindPopup(`<strong>Customer: ${customer.name}</strong><br>Lat: ${customer.lat}<br>Lng: ${customer.lng}`);

            // រក្សាទុក marker ទុកសម្រាប់ពេលលុប
            customerMarkers.push(customerMarker);
        });
    }

    // --- កូដសម្រាប់จัดการ Customer ក្នុងតារាង ---
    async function postToTracker(action, payload) {
        try {
            // We fetch the tracker.php file you created
            const res = await fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, ...payload })
            });

            const text = await res.text();
            try {
                const json = JSON.parse(text);
                if (!res.ok && !json.message) {
                     return { success: false, message: `Server error (status ${res.status})`, raw: text, status: res.status };
                }
                return json;
            } catch (e) {
                console.error('postToTracker: non-JSON response (status=' + res.status + ')', text);
                return { success: false, message: 'Non-JSON response from server. Check PHP error log.', raw: text, status: res.status };
            }
        } catch (err) {
            console.error('postToTracker fetch error', err);
            return { success: false, message: err.message };
        }
    }

    // --- Function for Admin Actions (posts to admin.php) ---
    async function postToAdmin(action, payload) {
        try {
            const res = await fetch('admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, ...payload })
            });

            const text = await res.text();
            try {
                const json = JSON.parse(text);
                if (!res.ok && !json.message) {
                     return { success: false, message: `Server error (status ${res.status})`, raw: text, status: res.status };
                }
                return json;
            } catch (e) {
                console.error('postToAdmin: non-JSON response (status=' + res.status + ')', text);
                return { success: false, message: 'Non-JSON response from server. Check PHP error log.', raw: text, status: res.status };
            }
        } catch (err) {
            console.error('postToAdmin fetch error', err);
            return { success: false, message: err.message };
        }
    }

    function parseGoogleMapsLink(url) {
        try {
            // More robust regex to catch different Google Maps URL formats
            let m = url.match(/@([\-0-9\.]+),([\-0-9\.]+)/); // Format: .../@11.5564,104.9282...
            if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
            
            m = url.match(/[?&]q=([\-0-9\.]+),([\-0-9\.]+)/); // Format: ...?q=11.5564,104.9282...
            if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };

            m = url.match(/maps\/place\/[^\/]+\/([\-0-9\.]+),([\-0-9\.]+)/); // Format: /maps/place/Name/@11.5564,104.9282...
             if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
            
            return null;
        } catch(e) { return null; }
    }

    async function loadCustomersAdminForTable() {
        const resp = await postToAdmin('list_customers', {});
        const tbody = document.getElementById('customersTBody');
        tbody.innerHTML = ''; // Clear existing list
        
        if (!resp.success || !Array.isArray(resp.data)) {
             console.error('Failed to load customers:', resp.message);
             tbody.innerHTML = '<tr><td colspan="3" class="px-4 py-2 text-center text-red-500">Failed to load customers. Please try again.</td></tr>';
             return;
        }

        if (resp.data.length === 0) {
             tbody.innerHTML = '<tr><td colspan="3" class="px-4 py-2 text-center text-gray-500">No customers found.</td></tr>';
             return;
        }

        resp.data.forEach(c => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td class="px-4 py-2">${escapeHtml(c.name)}</td>
                            <td class="px-4 py-2">${escapeHtml(c.lat)}, ${escapeHtml(c.lng)}</td>
                            <td class="px-4 py-2"><a href="${escapeHtml(c.map_link)}" target="_blank" rel="noopener noreferrer" class="text-blue-500">Open</a></td>`;
            tbody.appendChild(tr);
        });
    }

    // Helper function to prevent XSS
    function escapeHtml(s){ 
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"']/g, function(t){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[t]; }); 
    }

    document.getElementById('adminAddCust').addEventListener('click', async function(e){
        e.preventDefault();
        const name = document.getElementById('adminCustName').value.trim();
        const link = document.getElementById('adminCustLink').value.trim();
        const msg = document.getElementById('custMsg'); 
        msg.textContent = '';
        
        if (!name || !link) { 
            msg.style.color = 'red';
            msg.textContent = 'Name and link required'; 
            return; 
        }
        
        const coords = parseGoogleMapsLink(link);
        if (!coords) { 
            msg.style.color = 'red';
            msg.textContent = 'Cannot parse coordinates from link. Please use a valid Google Maps link.'; 
            return; 
        }
        
        const resp = await postToAdmin('add_customer', { name, lat: coords.lat, lng: coords.lng, map_link: link });
        
        if (resp.success) { 
            msg.style.color = 'green'; 
            msg.textContent = 'Customer added successfully'; 
            document.getElementById('adminCustName').value=''; 
            document.getElementById('adminCustLink').value=''; 
            loadCustomersAdminForTable(); // Reload the list in table
            
            // Reload markers on the map
            loadAndDrawCustomers(); 
        } else { 
            msg.style.color = 'red'; 
            msg.textContent = resp.message || 'An unknown error occurred.'; 
        }
    });

    document.getElementById('adminReloadCust').addEventListener('click', async function(e){ 
        e.preventDefault(); 
        loadCustomersAdminForTable();
        // Reload markers on the map
        loadAndDrawCustomers();
    });

    // Load on start
    document.addEventListener('DOMContentLoaded', () => {
         loadCustomersAdminForTable();

         // Nav tabs behavior: show only the active section and hide others
         const tabs = document.querySelectorAll('.nav-tab');
         const sections = ['trips','customers','createUser','userList','transport'];
         window.setActiveTabById = function(id) {
             tabs.forEach(t => t.classList.toggle('active', t.dataset.target === id));
             sections.forEach(sid => {
                 const el = document.getElementById(sid);
                 if (!el) return;
                 el.style.display = (sid === id) ? '' : 'none';
             });
             // update hash without jumping
             history.replaceState(null, '', '#' + id);

             // Initialize map when trips tab becomes active
             if (id === 'trips' && !window.mapInitialized) {
                 setTimeout(() => initMap(), 100); // Small delay to ensure DOM is ready
             }

             // Start/stop status refresh for transport tab
             if (id === 'transport') {
                 startStatusRefresh();
             } else {
                 stopStatusRefresh();
             }
         };

         tabs.forEach(t => {
             t.addEventListener('click', function(e){
                 e.preventDefault();
                 const targetId = this.dataset.target;
                 window.setActiveTabById(targetId);
                 // smooth scroll to top of container
                 document.querySelector('.container').scrollIntoView({ behavior: 'smooth', block: 'start' });
             });
         });

         // Default to trips if no hash
         const initialHash = location.hash.replace('#','') || 'trips';
         window.setActiveTabById(initialHash);

         // Map initialization is handled in setActiveTabById when trips tab becomes active
    });

    // User Management Functions
    async function editUser(userId) {
        try {
            // Fetch user data
            const resp = await postToAdmin('get_user', { user_id: userId });
            if (resp.success) {
                // Populate modal with user data
                document.getElementById('editUserId').value = resp.user.id;
                document.getElementById('editUsername').value = resp.user.username;
                document.getElementById('editLoginId').value = resp.user.login_id;
                document.getElementById('editRole').value = resp.user.role;

                // Show modal
                document.getElementById('editUserModal').classList.remove('hidden');
            } else {
                alert('Error loading user data: ' + resp.message);
            }
        } catch (error) {
            alert('Error loading user data: ' + error.message);
        }
    }

    function closeEditModal() {
        document.getElementById('editUserModal').classList.add('hidden');
    }

    // Handle edit user form submission
    document.getElementById('editUserForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const userData = {
            action: 'edit_user',
            user_id: formData.get('user_id'),
            username: formData.get('username'),
            login_id: formData.get('login_id'),
            role: formData.get('role')
        };

        try {
            const resp = await postToAdmin('edit_user', userData);
            if (resp.success) {
                alert('User updated successfully!');
                closeEditModal();
                location.reload(); // Reload to update the user list
            } else {
                alert('Error updating user: ' + resp.message);
            }
        } catch (error) {
            alert('Error updating user: ' + error.message);
        }
    });

    async function resetPassword(userId) {
        if (confirm('Are you sure you want to reset this user\'s password? A new temporary password will be generated.')) {
            try {
                const resp = await postToAdmin('reset_user_password', { user_id: userId });
                if (resp.success) {
                    alert('Password reset successfully! New password: ' + resp.new_password);
                } else {
                    alert('Error resetting password: ' + resp.message);
                }
            } catch (error) {
                alert('Error resetting password: ' + error.message);
            }
        }
    }

    async function deleteUser(userId, username) {
        if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
            try {
                const resp = await postToAdmin('delete_user', { user_id: userId });
                if (resp.success) {
                    alert('User deleted successfully!');
                    location.reload(); // Reload to update the user list
                } else {
                    alert('Error deleting user: ' + resp.message);
                }
            } catch (error) {
                alert('Error deleting user: ' + error.message);
            }
        }
    }

    // Admin Logout Function
    async function adminLogout() {
        if (confirm('Are you sure you want to logout from the admin panel?')) {
            try {
                const resp = await postToAdmin('admin_logout', {});
                if (resp.success) {
                    window.location.href = 'admin_login.php';
                } else {
                    alert('Error logging out: ' + resp.message);
                }
            } catch (error) {
                alert('Error logging out: ' + error.message);
            }
        }
    }

    // Transportation Operations Functions
    let userMap = null;
    let userMapMarker = null;

    async function viewUserLocation(userId, username) {
        document.getElementById('userLocationTitle').textContent = `Location of ${username}`;
        document.getElementById('userLocationModal').classList.remove('hidden');

        // Initialize map if not already
        if (!userMap) {
            userMap = L.map('userMap').setView([11.5564, 104.9282], 10); // Default to Phnom Penh
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(userMap);
        }

        // Fetch user's active trip or last location
        try {
            const resp = await postToAdmin('get_user_location', { user_id: userId });
            if (resp.success && resp.location) {
                const { lat, lng } = resp.location;
                if (userMapMarker) {
                    userMap.removeLayer(userMapMarker);
                }
                userMapMarker = L.marker([lat, lng]).addTo(userMap)
                    .bindPopup(`${username}'s Location`);
                userMap.setView([lat, lng], 15);
            } else {
                alert('No location data available for this user.');
            }
        } catch (error) {
            alert('Error fetching user location: ' + error.message);
        }

        // Load user trips
        loadUserTrips(userId);
    }

    function closeUserLocationModal() {
        document.getElementById('userLocationModal').classList.add('hidden');
    }

    async function loadUserTrips(userId) {
        try {
            const resp = await postToAdmin('get_user_trips', { user_id: userId });
            const tbody = document.getElementById('userTripsTable');
            tbody.innerHTML = '';
            if (resp.success && resp.trips.length > 0) {
                resp.trips.forEach(trip => {
                    const previewButton = trip.status === 'completed' && trip.start_lat && trip.start_lng && trip.end_lat && trip.end_lng 
                        ? `<button class="btn btn-secondary btn-sm" onclick="previewUserTripRoute(${trip.id}, ${trip.start_lat}, ${trip.start_lng}, ${trip.end_lat}, ${trip.end_lng})">Preview Route</button>`
                        : '';
                    const row = `
                        <tr>
                            <td>${trip.start_time || 'N/A'}</td>
                            <td>${trip.end_time || 'Ongoing'}</td>
                            <td>${trip.distance_km ? trip.distance_km + ' km' : 'N/A'}</td>
                            <td>${trip.status === 'completed' ? 'Completed' : 'Ongoing'}</td>
                            <td>${previewButton}</td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4">No trips found.</td></tr>';
            }
        } catch (error) {
            console.error('Error loading user trips:', error);
        }
    }

    // Preview trip route on user map
    async function previewUserTripRoute(tripId, startLat, startLng, endLat, endLng) {
        if (!userMap) {
            alert('Map is not initialized yet.');
            return;
        }

        // Remove previous route and markers if any
        if (window._userTripRouteLayer) {
            try { userMap.removeLayer(window._userTripRouteLayer); } catch(e){}
            window._userTripRouteLayer = null;
        }
        if (window._userTripStartMarker) {
            try { userMap.removeLayer(window._userTripStartMarker); } catch(e){}
            window._userTripStartMarker = null;
        }
        if (window._userTripEndMarker) {
            try { userMap.removeLayer(window._userTripEndMarker); } catch(e){}
            window._userTripEndMarker = null;
        }

        const points = [[startLat, startLng], [endLat, endLng]];

        // Try to get OSRM route
        try {
            const resp = await postToAdmin('fetch_route', { start_lat: startLat, start_lng: startLng, end_lat: endLat, end_lng: endLng });
            if (resp && resp.success && Array.isArray(resp.coords) && resp.coords.length > 0) {
                // Create route with improved styling
                window._userTripRouteLayer = L.polyline(resp.coords, {
                    color: '#2563eb', // Blue color for better visibility
                    weight: 8, // Thicker line
                    opacity: 0.8,
                    dashArray: null // Solid line instead of dashed
                }).addTo(userMap);

                // Add start marker
                window._userTripStartMarker = L.marker([startLat, startLng], {
                    icon: L.divIcon({
                        className: 'custom-marker-start',
                        html: '<div style="background-color: #10b981; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
                        iconSize: [20, 20],
                        iconAnchor: [10, 10]
                    })
                }).addTo(userMap).bindPopup('<b>ចាប់ផ្តើម</b><br>Start Location');

                // Add end marker
                window._userTripEndMarker = L.marker([endLat, endLng], {
                    icon: L.divIcon({
                        className: 'custom-marker-end',
                        html: '<div style="background-color: #ef4444; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
                        iconSize: [20, 20],
                        iconAnchor: [10, 10]
                    })
                }).addTo(userMap).bindPopup('<b>បញ្ចប់</b><br>End Location');

                userMap.fitBounds(window._userTripRouteLayer.getBounds(), { padding: [50,50] });
                return;
            }
        } catch (err) {
            console.warn('OSRM route failed, using straight line:', err);
        }

        // Fallback to straight line with same styling
        window._userTripRouteLayer = L.polyline(points, {
            color: '#2563eb',
            weight: 8,
            opacity: 0.8
        }).addTo(userMap);

        // Add markers for straight line fallback
        window._userTripStartMarker = L.marker([startLat, startLng], {
            icon: L.divIcon({
                className: 'custom-marker-start',
                html: '<div style="background-color: #10b981; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            })
        }).addTo(userMap).bindPopup('<b>ចាប់ផ្តើម</b><br>Start Location');

        window._userTripEndMarker = L.marker([endLat, endLng], {
            icon: L.divIcon({
                className: 'custom-marker-end',
                html: '<div style="background-color: #ef4444; width: 20px; height: 20px; border-radius: 50%; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
                iconSize: [20, 20],
                iconAnchor: [10, 10]
            })
        }).addTo(userMap).bindPopup('<b>បញ្ចប់</b><br>End Location');

        userMap.fitBounds(window._userTripRouteLayer.getBounds(), { padding: [50,50] });
    }

    // Clear user route manually
    function clearUserRoute() {
        // Clear route layer
        if (window._userTripRouteLayer) {
            try { userMap.removeLayer(window._userTripRouteLayer); } catch(e){}
            window._userTripRouteLayer = null;
        }
        if (window._userTripStartMarker) {
            try { userMap.removeLayer(window._userTripStartMarker); } catch(e){}
            window._userTripStartMarker = null;
        }
        if (window._userTripEndMarker) {
            try { userMap.removeLayer(window._userTripEndMarker); } catch(e){}
            window._userTripEndMarker = null;
        }
    }

    // Refresh user statuses periodically
    let statusRefreshInterval = null;

    function startStatusRefresh() {
        if (statusRefreshInterval) clearInterval(statusRefreshInterval);
        statusRefreshInterval = setInterval(refreshUserStatuses, 10000); // Refresh every 10 seconds
    }

    function stopStatusRefresh() {
        if (statusRefreshInterval) {
            clearInterval(statusRefreshInterval);
            statusRefreshInterval = null;
        }
    }

    async function refreshUserStatuses() {
        try {
            const resp = await postToAdmin('get_user_statuses', {});
            if (resp.success) {
                resp.users.forEach(user => {
                    const statusEl = document.getElementById(`status-${user.id}`);
                    if (statusEl) {
                        const isTraveling = user.is_traveling == 1;
                        statusEl.textContent = isTraveling ? 'កំពុងចេញដំណើរ' : 'មិនទាន់ចេញដំណើរ';
                        statusEl.className = `px-2 py-1 text-xs font-semibold rounded-full ${isTraveling ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`;
                    }
                });
            }
        } catch (error) {
            console.error('Error refreshing user statuses:', error);
        }
    }

    // Refresh location if modal is open
    let locationRefreshInterval = null;
    let currentUserId = null;

    function startLocationRefresh(userId) {
        currentUserId = userId;
        if (locationRefreshInterval) clearInterval(locationRefreshInterval);
        locationRefreshInterval = setInterval(() => refreshUserLocation(userId), 10000);
    }

    function stopLocationRefresh() {
        if (locationRefreshInterval) {
            clearInterval(locationRefreshInterval);
            locationRefreshInterval = null;
        }
        currentUserId = null;
    }

    async function refreshUserLocation(userId) {
        try {
            const resp = await postToAdmin('get_user_location', { user_id: userId });
            if (resp.success && resp.location && userMap && userMapMarker) {
                const { lat, lng } = resp.location;
                userMapMarker.setLatLng([lat, lng]);
                userMap.setView([lat, lng], userMap.getZoom());
            }
            // Also refresh trips
            loadUserTrips(userId);
            // Note: Route layers are now persistent and only cleared when explicitly requested
        } catch (error) {
            console.error('Error refreshing user location:', error);
        }
    }

    // Modified viewUserLocation to start refresh
    async function viewUserLocation(userId, username) {
        document.getElementById('userLocationTitle').textContent = `Location of ${username}`;
        document.getElementById('userLocationModal').classList.remove('hidden');

        // Initialize map if not already
        if (!userMap) {
            userMap = L.map('userMap').setView([11.5564, 104.9282], 10); // Default to Phnom Penh
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(userMap);
        }

        // Load initial data
        await loadUserLocation(userId, username);
        loadUserTrips(userId);

        // Start refreshing
        startLocationRefresh(userId);
    }

    // Modified closeUserLocationModal to stop refresh
    function closeUserLocationModal() {
        document.getElementById('userLocationModal').classList.add('hidden');
        stopLocationRefresh();
        // Clear route layer
        if (window._userTripRouteLayer) {
            try { userMap.removeLayer(window._userTripRouteLayer); } catch(e){}
            window._userTripRouteLayer = null;
        }
        if (window._userTripStartMarker) {
            try { userMap.removeLayer(window._userTripStartMarker); } catch(e){}
            window._userTripStartMarker = null;
        }
        if (window._userTripEndMarker) {
            try { userMap.removeLayer(window._userTripEndMarker); } catch(e){}
            window._userTripEndMarker = null;
        }
    }

    // Separate function for initial load
    async function loadUserLocation(userId, username) {
        try {
            const resp = await postToAdmin('get_user_location', { user_id: userId });
            if (resp.success && resp.location) {
                const { lat, lng } = resp.location;
                if (userMapMarker) {
                    userMap.removeLayer(userMapMarker);
                }
                userMapMarker = L.marker([lat, lng]).addTo(userMap)
                    .bindPopup(`${username}'s Location`);
                userMap.setView([lat, lng], 15);
            } else {
                alert('No location data available for this user.');
            }
        } catch (error) {
            alert('Error fetching user location: ' + error.message);
        }
    }

    // Trip table utilities: filtering and preview
    (function(){
        function filterTrips() {
            const q = document.getElementById('tripSearch').value.trim().toLowerCase();
            const rows = document.querySelectorAll('#tripsTable tbody .trip-row');
            let visible = 0;
            rows.forEach(r => {
                const uname = (r.dataset.username || '').toLowerCase();
                const dist = (r.dataset.distance || '').toLowerCase();
                const start = (r.dataset.start || '').toLowerCase();
                const end = (r.dataset.end || '').toLowerCase();
                const match = !q || uname.includes(q) || dist.includes(q) || start.includes(q) || end.includes(q);
                r.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            document.getElementById('tripsCount').textContent = visible;
        }

        document.addEventListener('DOMContentLoaded', function(){
            const search = document.getElementById('tripSearch');
            if (search) search.addEventListener('input', filterTrips);
        });

        // Expose preview function globally so inline onclick can call it
        window.previewTripRoute = function(btn) {
            if (!map) {
                alert('Map is not initialized yet. Open the Trips tab first.');
                return;
            }
            const tr = btn.closest('tr');
            if (!tr) return;
            const sLat = parseFloat(tr.dataset.startLat || tr.dataset.startLat === '' ? tr.dataset.startLat : tr.getAttribute('data-start-lat')) || parseFloat(tr.dataset.startLat || tr.getAttribute('data-start-lat'));
            const sLng = parseFloat(tr.dataset.startLng || tr.getAttribute('data-start-lng'));
            const eLat = parseFloat(tr.dataset.endLat || tr.getAttribute('data-end-lat'));
            const eLng = parseFloat(tr.dataset.endLng || tr.getAttribute('data-end-lng'));

            // prefer dataset attributes, fallback to getAttribute
            const startLat = parseFloat(tr.getAttribute('data-start-lat'));
            const startLng = parseFloat(tr.getAttribute('data-start-lng'));
            const endLat = parseFloat(tr.getAttribute('data-end-lat'));
            const endLng = parseFloat(tr.getAttribute('data-end-lng'));

            if (isNaN(startLat) || isNaN(startLng) || isNaN(endLat) || isNaN(endLng)) {
                alert('Invalid coordinates for this trip.');
                return;
            }

            // remove previous preview layer if any
            if (window._tripPreviewLayer) {
                try { map.removeLayer(window._tripPreviewLayer); } catch(e){}
                window._tripPreviewLayer = null;
            }

            const points = [[startLat, startLng], [endLat, endLng]];
            // try to request OSRM route for preview (no heavy animations)
            (async function(){
                try {
                    const resp = await postToAdmin('fetch_route', { start_lat: startLat, start_lng: startLng, end_lat: endLat, end_lng: endLng });
                    if (resp && resp.success && Array.isArray(resp.coords) && resp.coords.length > 0) {
                        window._tripPreviewLayer = L.polyline(resp.coords, { color: '#ff8c00', weight: 5, dashArray: '6 6' }).addTo(map);
                        map.fitBounds(window._tripPreviewLayer.getBounds(), { padding: [40,40] });
                        return;
                    }
                } catch (err) {
                    console.warn('Preview OSRM failed', err);
                }
                // fallback to straight line preview
                window._tripPreviewLayer = L.polyline(points, { color: '#ff8c00', weight: 4, dashArray: '6 6' }).addTo(map);
                map.fitBounds(window._tripPreviewLayer.getBounds(), { padding: [40,40] });
            })();
        };
    })();
    </script>
</body>
</html>
