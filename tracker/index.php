<?php
//--- បង្ហាញកំហុស (Error Reporting) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require 'db.php'; // <-- ត្រូវប្រាកដថា db.php នៅក្នុង Folder ដូចគ្នា

// Database setup for live tracking
try {
    // Check and add columns for live tracking
    $result = $pdo->query("SHOW COLUMNS FROM trips LIKE 'current_lat'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE trips ADD COLUMN current_lat DECIMAL(10,8) NULL");
    }
    $result = $pdo->query("SHOW COLUMNS FROM trips LIKE 'current_lng'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE trips ADD COLUMN current_lng DECIMAL(11,8) NULL");
    }
    $result = $pdo->query("SHOW COLUMNS FROM trips LIKE 'last_update'");
    if ($result->rowCount() == 0) {
        $pdo->exec("ALTER TABLE trips ADD COLUMN last_update TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }
} catch (Exception $e) {
    // Ignore errors if table doesn't exist or other issues
    error_log("Database setup error: " . $e->getMessage());
}

// --- [ផ្នែកបន្ថែម] ចាប់ផ្តើមមុខងារ "Remember Me" ---
// ពិនិត្យមើល Cookie ប្រសិនបើ User មិនទាន់មាន Session
if (empty($_SESSION['user_id']) && !empty($_COOKIE['remember_me'])) {
    // បំបែក selector និង validator ចេញពី Cookie
    list($selector, $validator) = explode(':', $_COOKIE['remember_me'], 2);

    if ($selector && $validator) {
        // ស្វែងរក selector នៅក្នុង Database ដែលមិនទាន់หมดอายุ
        $stmt = $pdo->prepare("SELECT * FROM auth_tokens WHERE selector = ? AND expires >= NOW()");
        $stmt->execute([$selector]);
        $token = $stmt->fetch();

        if ($token) {
            // ផ្ទៀងផ្ទាត់ validator ជាមួយនឹង hashed_validator ក្នុង Database
            if (hash_equals($token['hashed_validator'], hash('sha256', $validator))) {
                // Token ត្រឹមត្រូវ, បង្កើត Session ឡើងវិញ
                $stmt_user = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
                $stmt_user->execute([$token['user_id']]);
                $user = $stmt_user->fetch();
                if ($user) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                }
            }
        }
    }
}
// --- [ផ្នែកបន្ថែម] បញ្ចប់មុខងារ "Remember Me" ---


// Register shutdown handler to catch fatal errors and return JSON when possible (helps debug 500s)
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        // Log full error for server-side debugging
        error_log("Fatal error: " . $err['message'] . " in " . $err['file'] . " on line " . $err['line']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Try to return JSON to the client so fetch callers can see the error HTML/text
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Fatal error on server', 'detail' => $err['message']]);
        }
    }
});

// Convert uncaught exceptions to JSON responses during POST requests
set_exception_handler(function($e){
    error_log("Uncaught exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Server exception', 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    }
    exit;
});

/* * Function សម្រាប់គណនារយៈចម្ងាយ (Haversine formula) */
function getDistanceBetweenPoints($lat1, $lng1, $lat2, $lng2) {
    if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
        return 0;
    }
    $earthRadiusKm = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $lat1 = deg2rad($lat1);
    $lat2 = deg2rad($lat2);
    $a = sin($dLat/2) * sin($dLat/2) +
         sin($dLng/2) * sin($dLng/2) * cos($lat1) * cos($lat2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earthRadiusKm * $c;
}

// --- API LOGIC (BACKEND) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    // Log raw input to help debug 500s (server log)
    $rawInput = file_get_contents('php://input');
    error_log('[tracker] POST raw input: ' . $rawInput);
    try {
        $data = json_decode($rawInput, true);
        $action = $data['action'] ?? null;

    // --- LOGIN ACTION ---
    if ($action === 'login') {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE login_id = ? AND role = 'user'");
        $stmt->execute([$data['login_id']]);
        $user = $stmt->fetch();

        if ($user && password_verify($data['password'], $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username']; // រក្សាទុក username សម្រាប់បង្ហាញ

            // --- [ផ្នែកបន្ថែម] បង្កើត Token ប្រសិនបើ User ធីក "Remember Me" ---
            if (!empty($data['remember'])) {
                $selector = bin2hex(random_bytes(16));
                $validator = bin2hex(random_bytes(32));
                $hashed_validator = hash('sha256', $validator);
                $expires = new DateTime('+30 days'); // រយៈពេល 30 ថ្ងៃ

                // រក្សាទុក Token ក្នុង Database
                $stmt_token = $pdo->prepare("INSERT INTO auth_tokens (selector, hashed_validator, user_id, expires) VALUES (?, ?, ?, ?)");
                $stmt_token->execute([$selector, $hashed_validator, $user['id'], $expires->format('Y-m-d H:i:s')]);

                // កំណត់ Cookie ក្នុង Browser
                setcookie(
                    'remember_me',
                    $selector . ':' . $validator,
                    $expires->getTimestamp(),
                    '/',      // Path
                    '',       // Domain (ปล่อยឱ្យនៅទំនេរ)
                    isset($_SERVER["HTTPS"]), // Secure (true on HTTPS)
                    true      // HttpOnly
                );
            }
            // --- [ផ្នែកបន្ថែម] ចប់ផ្នែក Remember Me ---

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Login ID ឬ Password ខុស']);
        }
        exit;
    }

    // --- LOGOUT ACTION ---
    if ($action === 'logout') {
        // --- [ផ្នែកបន្ថែម] លុប Token ចេញពី Database និង Browser ---
        if (!empty($_COOKIE['remember_me'])) {
            list($selector, ) = explode(':', $_COOKIE['remember_me'], 2);
            $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE selector = ?");
            $stmt->execute([$selector]);
        }
        // លុប Cookie ដោយកំណត់ពេលវេលាឱ្យហួសសម័យ
        setcookie('remember_me', '', time() - 3600, '/');
        // --- [ផ្នែកបន្ថែម] ចប់ផ្នែកលុប Token ---

        session_destroy();
        echo json_encode(['success' => true]);
        exit;
    }

    // --- START TRIP ACTION ---
    if ($action === 'start_trip' && isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("INSERT INTO trips (user_id, start_lat, start_lng, start_time) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $data['lat'], $data['lng']]);
        $trip_id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'trip_id' => $trip_id]);
        exit;
    }

    // --- STOP TRIP ACTION ---
    if ($action === 'stop_trip' && isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT start_lat, start_lng FROM trips WHERE id = ? AND user_id = ?");
        $stmt->execute([$data['trip_id'], $_SESSION['user_id']]);
        $trip = $stmt->fetch();

        if ($trip) {
            $start_lat = $trip['start_lat'];
            $start_lng = $trip['start_lng'];
            $end_lat = $data['lat'];
            $end_lng = $data['lng'];

            $distance_km = getDistanceBetweenPoints($start_lat, $start_lng, $end_lat, $end_lng);
            $distance_m = $distance_km * 1000;

            $stmt = $pdo->prepare("UPDATE trips SET end_lat = ?, end_lng = ?, distance_km = ?, end_time = NOW(), status = 'completed' WHERE id = ?");
            $stmt->execute([$end_lat, $end_lng, $distance_km, $data['trip_id']]);

            echo json_encode(['success' => true, 'distance_km' => round($distance_km, 2), 'distance_m' => round($distance_m, 2)]);
        } else {
            echo json_encode(['success' => false, 'message' => 'រកមិនឃើញ Trip ID']);
        }
        exit;
    }

    // --- UPDATE LOCATION ACTION ---
    if ($action === 'update_location' && isset($_SESSION['user_id'])) {
        // Find the active trip for this user
        $stmt = $pdo->prepare("SELECT id FROM trips WHERE user_id = ? AND (status IS NULL OR status != 'completed') ORDER BY id DESC LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $trip = $stmt->fetch();

        if ($trip) {
            // Update current location
            $stmt = $pdo->prepare("UPDATE trips SET current_lat = ?, current_lng = ?, last_update = NOW() WHERE id = ?");
            $stmt->execute([$data['lat'], $data['lng'], $trip['id']]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No active trip found']);
        }
        exit;
    }

    // --- ADD CUSTOMER ACTION ---
    if ($action === 'add_customer' && isset($_SESSION['user_id'])) {
        $name = $data['name'] ?? '';
        $lat = isset($data['lat']) ? floatval($data['lat']) : null;
        $lng = isset($data['lng']) ? floatval($data['lng']) : null;
        $map_link = $data['map_link'] ?? '';
        if (empty($name) || $lat === null || $lng === null) {
            echo json_encode(['success' => false, 'message' => 'Missing name or coordinates']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO customers (name, lat, lng, map_link, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$name, $lat, $lng, $map_link]);
        $cust_id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'customer_id' => $cust_id]);
        exit;
    }

    // --- LIST CUSTOMERS ACTION ---
    if ($action === 'list_customers' && isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT id, name, lat, lng, map_link FROM customers ORDER BY id DESC");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $rows]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action or not logged in']);
    exit;
    } catch (Throwable $e) {
        // Log exception and return JSON so client sees error details
        error_log('[tracker] Exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Server exception', 'error' => $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>កម្មវិធីតាមដានចម្ងាយ</title>
       <!-- [PWA] Meta Tags for PWA -->
    <meta name="theme-color" content="#0ea5ff"/>
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="កម្មវិធីតាមដានចម្ងាយ">
    <meta name="application-name" content="កម្មវិធីតាមដានចម្ងាយ">
    
    <!-- [PWA] Link to Manifest -->
    <link rel="manifest" href="manifest.json">
    
    <!-- [PWA] Icons for Apple devices -->
    <link rel="apple-touch-icon" href="https://cdn-icons-png.flaticon.com/512/12601/12601802.png">
    <!-- Modern fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Heroicons for icons -->
    <link rel="stylesheet" href="https://unpkg.com/heroicons@2.0.18/24/outline/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --bg-1: linear-gradient(180deg, #f7fbff, #f3f8ff);
            --card-bg: #ffffff;
            --muted: #6b7280;
            --accent: #0ea5ff;
            --accent-hover: #0284c7;
            --accent-2: #06b6d4;
            --success: #10b981;
            --danger: #ef4444;
            --text-primary: #0f172a;
            --radius: 14px;
            --shadow-lg: 0 12px 40px rgba(2,6,23,0.08);
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'Noto Sans Khmer', 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto;
            background: var(--bg-1);
            margin: 0;
            -webkit-font-smoothing: antialiased;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        /* Cards */
        .tracker-card, .auth-card {
            background: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            padding: 28px;
            width: 100%;
            max-width: 420px;
            border: 1px solid rgba(2,6,23,0.04);
        }

        .tracker-header {
            display: flex;
            align-items: center;
            gap: 12px;
            justify-content: center;
        }

        .tracker-header .logo {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            box-shadow: 0 8px 26px rgba(14,165,255,0.12);
        }

        .tracker-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
        }

        .tracker-sub {
            font-size: 0.9rem;
            color: var(--muted);
            margin-top: 4px;
            text-align: center;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 12px;
            padding: 12px 14px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 40px rgba(2,6,23,0.12);
        }

        .btn-start {
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            color: #fff;
            box-shadow: 0 10px 30px rgba(6,95,125,0.08);
        }

        .btn-stop {
            background: linear-gradient(90deg, var(--danger), #f97316);
            color: #fff;
            box-shadow: 0 10px 30px rgba(255,80,60,0.08);
        }

        .btn-ghost {
            background: transparent;
            color: var(--muted);
        }

        /* Circular Button */
        .btn-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .btn-start-circle {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #fff;
            box-shadow: 0 12px 40px rgba(6,95,125,0.15);
        }

        .btn-start-circle:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 16px 50px rgba(6,95,125,0.2);
        }

        /* Status & Result */
        .status {
            color: var(--muted);
            margin-top: 12px;
            font-size: 0.95rem;
        }

        .result-card {
            margin-top: 16px;
            padding: 14px;
            border-radius: 12px;
            background: linear-gradient(180deg, #f0f9ff, #eef8ff);
            border: 1px solid rgba(14,165,255,0.12);
        }

        .result-card h3 {
            color: #064e3b;
            font-weight: 700;
        }

        /* Spinner */
        .spinner {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.25);
            border-top-color: rgba(255,255,255,0.9);
            animation: spin 0.9s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Animations */
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-in {
            animation: fade-in 0.3s ease-out;
        }

        /* Form Inputs */
        .form-input {
            width: 100%;
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid rgba(15,23,42,0.06);
        }

        /* Customer Sidebar */
        .customer-sidebar {
            position: fixed;
            right: 12px;
            top: 100px;
            width: 320px;
            max-width: 86vw;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 24px 80px rgba(2,6,23,0.12);
            z-index: 1600;
            overflow: hidden;
        }

        .customer-list .customer-item {
            padding: 10px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: 1px solid rgba(2,6,23,0.04);
            margin-bottom: 8px;
        }

        .customer-list .customer-item:hover {
            background: linear-gradient(90deg, rgba(79,70,229,0.04), rgba(6,182,212,0.02));
            transform: translateY(-2px);
        }

        .customer-list .customer-item.selected {
            box-shadow: 0 8px 24px rgba(16,24,40,0.06);
            border-color: rgba(79,70,229,0.12);
        }

        .customer-item .meta {
            color: var(--muted);
            font-size: 0.85rem;
        }

        /* Media Queries */
        @media (max-width: 420px) {
            body {
                padding: 12px;
            }
            .tracker-card, .auth-card {
                padding: 18px;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen py-8">

    <?php if (isset($_SESSION['user_id'])): ?>

        <div class="flex flex-col items-center space-y-8">
            <div class="tracker-card text-center">
            <div class="tracker-header">
                <div class="logo">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </div>
            </div>
            <h2 class="tracker-title mt-3">កម្មវិធីតាមដានចម្ងាយ</h2>
            <p class="tracker-sub">សូមស្វាគមន៍, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>

            <div class="space-y-4">
                <!-- Destination picker (now a sidebar list) -->
                <div class="text-left mb-2">
                    <label class="block font-semibold mb-1.5">ទីតាំងគោលដៅ (អតិថិជន)</label>
                    <div class="flex gap-2 items-center">
                        <button id="openSidebarBtn" class="btn btn-ghost" title="Show destinations">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                            ទីតាំងគោលដៅ
                        </button>
                        <div id="selectedDestDisplay" class="flex-1 text-muted">មិនទាន់បានជ្រើសរើសទីតាំង</div>
                        <button id="openRouteBtn" class="btn btn-ghost" title="Open route in Google Maps">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                            </svg>
                            ផ្លូវ
                        </button>
                    </div>
                    <small id="distanceToDest" class="block text-muted mt-1.5">មិនទាន់បានជ្រើសរើសទីតាំង</small>
                </div>

                <!-- Floating sidebar: list of customers -->
                <div id="customerSidebar" class="customer-sidebar" aria-hidden="true" style="display:none;">
                    <div class="sidebar-header flex items-center justify-between p-2.5 border-b border-gray-200">
                        <strong>ទីតាំងគោលដៅ</strong>
                        <button id="closeCustSidebar" class="btn btn-ghost p-1.5">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    <div id="customerList" class="customer-list max-h-60 overflow-auto p-2.5"></div>
                </div>

                <!-- Add customer moved to admin area (admin-traker.php) -->
                <button id="stopButton" class="btn btn-stop w-full text-lg hidden">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 10h6m-6 4h6m-6 4h6"></path>
                    </svg>
                    Stop Tracking
                </button>
            </div>
            <div id="result" class="result-card hidden animate-fade-in">
                <div class="flex items-center gap-2">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <h3 class="font-bold text-green-800">បានបញ្ចប់ការធ្វើដំណើរ!</h3>
                </div>
                <p class="text-gray-700 mt-2" id="distance-km"></p>
                <p class="text-gray-700" id="distance-m"></p>
            </div>
            <p id="status" class="status"></p>

            <!-- Circular Start Tracking Button -->
            <div class="flex justify-center mt-8">
                <button id="startButton" class="btn-circle btn-start-circle">
                    <i class="fa-solid fa-location-crosshairs text-white text-3xl"></i>
                    <span class="spinner hidden" id="startSpinner"></span>
                </button>
            </div>

            <button id="logoutButton" class="btn btn-ghost mt-6">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                ចាកចេញ
            </button>
        </div>

        </div> <!-- End flex container -->

        <?php
            // Check for an active trip for the logged-in user (not completed / no end_time)
            $activeTripId = null;
            try {
                $q = $pdo->prepare("SELECT id FROM trips WHERE user_id = ? AND (status IS NULL OR status != 'completed') AND end_time IS NULL ORDER BY id DESC LIMIT 1");
                $q->execute([$_SESSION['user_id']]);
                $activeTripId = $q->fetchColumn();
            } catch (
                Throwable $e) {
                // ignore DB errors here; default to null
            }
        ?>
        <script>
            // Expose initial active trip id (if any) so JS can restore UI after reload
            window.__initialTripId = <?php echo json_encode($activeTripId ?: null); ?>;
            document.addEventListener('DOMContentLoaded', () => {
                const startButton = document.getElementById('startButton');
                const stopButton = document.getElementById('stopButton');
                const logoutButton = document.getElementById('logoutButton');
                const resultDiv = document.getElementById('result');
                const distanceKmEl = document.getElementById('distance-km');
                const distanceMEl = document.getElementById('distance-m');
                const statusEl = document.getElementById('status');
                let currentTripId = null;
                // Restore state after reload if server indicated an active trip
                if (window.__initialTripId) {
                    currentTripId = window.__initialTripId;
                    statusEl.textContent = 'កំពុងធ្វើដំណើរ... (Trip ID: ' + currentTripId + ')';
                    startButton.classList.add('hidden');
                    stopButton.classList.remove('hidden');
                }

                startButton.addEventListener('click', startTracking);
                stopButton.addEventListener('click', stopTracking);
                logoutButton.addEventListener('click', logout);
                // customer UI elements (add-customer moved to admin area)
                const customerListEl = document.getElementById('customerList');
                const openRouteBtn = document.getElementById('openRouteBtn');
                const distanceToDestEl = document.getElementById('distanceToDest');
                const openSidebarBtn = document.getElementById('openSidebarBtn');
                const closeCustSidebar = document.getElementById('closeCustSidebar');
                const customerSidebar = document.getElementById('customerSidebar');
                const selectedDestDisplay = document.getElementById('selectedDestDisplay');
                let selectedDestination = null; // {id,name,lat,lng,map_link}
                let watchId = null;

                // load customers on init
                loadCustomers();

                // sidebar open/close handlers
                openSidebarBtn.addEventListener('click', () => {
                    customerSidebar.style.display = 'block';
                    customerSidebar.setAttribute('aria-hidden','false');
                });
                closeCustSidebar.addEventListener('click', () => {
                    customerSidebar.style.display = 'none';
                    customerSidebar.setAttribute('aria-hidden','true');
                });

                openRouteBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (!selectedDestination) return alert('សូមជ្រើសរើសទីតាំងគោលដៅ');
                    const lat = selectedDestination.lat;
                    const lng = selectedDestination.lng;
                    const url = `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}&travelmode=driving`;
                    window.open(url, '_blank');
                });

                // add-customer functionality moved to admin page; tracker only loads existing customers

                let locationUpdateInterval = null;

                function startTracking() {
                    statusEl.textContent = 'កំពុងកំណត់ទីតាំង...';
                    // Show spinner and disable start button to improve UX
                    const startSpinner = document.getElementById('startSpinner');
                    startSpinner.classList.remove('hidden');
                    startButton.disabled = true;

                    // Geolocation options with a sensible timeout
                    const geoOptions = { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 };

                    navigator.geolocation.getCurrentPosition(async (position) => {
                        const { latitude, longitude } = position.coords;
                        const response = await postData('start_trip', { lat: latitude, lng: longitude });
                        if (response.success) {
                            currentTripId = response.trip_id;
                            statusEl.textContent = 'កំពុងធ្វើដំណើរ... (Trip ID: ' + currentTripId + ')';
                            startButton.classList.add('hidden');
                            stopButton.classList.remove('hidden');
                            resultDiv.classList.add('hidden');
                            // restore spinner/label state for next start
                            startSpinner.classList.add('hidden');
                            startButton.disabled = false;
                            // Start periodic location updates
                            startLocationUpdates();
                            // if a destination is selected, start watching position to detect arrival
                                    if (selectedDestination && selectedDestination.lat && selectedDestination.lng) {
                                        startWatchForArrival(selectedDestination);
                                    }
                        } else {
                            statusEl.textContent = 'មានកំហុសក្នុងការចាប់ផ្តើមការធ្វើដំណើរ';
                            startSpinner.classList.add('hidden');
                            startButton.disabled = false;
                        }
                    }, handleError);
                }

                function startLocationUpdates() {
                    if (locationUpdateInterval) clearInterval(locationUpdateInterval);
                    locationUpdateInterval = setInterval(async () => {
                        if (currentTripId && navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition(async (position) => {
                                const { latitude, longitude } = position.coords;
                                await postData('update_location', { lat: latitude, lng: longitude });
                            }, (err) => {
                                console.warn('Location update error:', err);
                            }, { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 });
                        }
                    }, 10000); // Update every 10 seconds
                }

                function stopLocationUpdates() {
                    if (locationUpdateInterval) {
                        clearInterval(locationUpdateInterval);
                        locationUpdateInterval = null;
                    }
                }

                async function stopTracking(forcedCoords = null) {
                    if (!currentTripId) return;
                    // clear watch if running
                    if (watchId !== null) { navigator.geolocation.clearWatch(watchId); watchId = null; }
                    // Stop location updates
                    stopLocationUpdates();
                    statusEl.textContent = 'កំពុងកំណត់ទីតាំងបញ្ឈប់...';
                    stopButton.disabled = true;

                    // If coords passed (from watch), use them directly
                    if (forcedCoords && forcedCoords.latitude && forcedCoords.longitude) {
                        const latitude = forcedCoords.latitude;
                        const longitude = forcedCoords.longitude;
                        const response = await postData('stop_trip', { trip_id: currentTripId, lat: latitude, lng: longitude });
                        if (response.success) {
                            distanceKmEl.textContent = `រយៈចម្ងាយ: ${response.distance_km} គ.ម`;
                            distanceMEl.textContent = `(ស្មើនឹង ${response.distance_m} ម៉ែត្រ)`;
                            resultDiv.classList.remove('hidden');
                            statusEl.textContent = 'បានបញ្ចប់ការធ្វើដំណើរ!';
                            startButton.classList.remove('hidden');
                            stopButton.classList.add('hidden');
                            currentTripId = null;
                            stopButton.disabled = false;
                            return;
                        } else {
                            statusEl.textContent = 'មានកំហុសក្នុងការបញ្ចប់ការធ្វើដំណើរ';
                            stopButton.disabled = false;
                            return;
                        }
                    }

                    // otherwise get current position and stop
                    const geoOptions = { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 };
                    navigator.geolocation.getCurrentPosition(async (position) => {
                        const { latitude, longitude } = position.coords;
                        const response = await postData('stop_trip', { trip_id: currentTripId, lat: latitude, lng: longitude });
                        if (response.success) {
                            distanceKmEl.textContent = `រយៈចម្ងាយ: ${response.distance_km} គ.ម`;
                            distanceMEl.textContent = `(ស្មើនឹង ${response.distance_m} ម៉ែត្រ)`;
                            resultDiv.classList.remove('hidden');
                            statusEl.textContent = 'បានបញ្ចប់ការធ្វើដំណើរ!';
                            startButton.classList.remove('hidden');
                            stopButton.classList.add('hidden');
                            currentTripId = null;
                            stopButton.disabled = false;
                        } else {
                            statusEl.textContent = 'មានកំហុសក្នុងការបញ្ចប់ការធ្វើដំណើរ';
                            stopButton.disabled = false;
                        }
                    }, handleError);
                }

                async function logout() {
                    await postData('logout', {});
                    window.location.reload();
                }

                async function postData(action, data) {
                    const response = await fetch('index.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action, ...data })
                    });
                    return await response.json();
                }

                function handleError(error) {
                    let errorMsg = '';
                    switch (error.code) {
                        case error.PERMISSION_DENIED: errorMsg = "User denied Geolocation."; break;
                        case error.POSITION_UNAVAILABLE: errorMsg = "Location information is unavailable."; break;
                        case error.TIMEOUT: errorMsg = "The request to get user location timed out."; break;
                        default: errorMsg = "An unknown error occurred."; break;
                    }
                    statusEl.textContent = errorMsg;
                    // reset UI state (hide spinner, re-enable buttons)
                    try {
                        const startSpinner = document.getElementById('startSpinner');
                        if (startSpinner) startSpinner.classList.add('hidden');
                        if (startButton) startButton.disabled = false;
                        if (stopButton) stopButton.disabled = false;
                    } catch(e) { /* ignore */ }
                }

                // parseGoogleMapsLink removed from tracker (admin handles adding customers and parsing links)

                // Distance in meters (Haversine)
                function distanceMeters(lat1, lon1, lat2, lon2) {
                    const R = 6371e3; // meters
                    const toRad = x => x * Math.PI / 180;
                    const dLat = toRad(lat2 - lat1);
                    const dLon = toRad(lon2 - lon1);
                    const a = Math.sin(dLat/2) * Math.sin(dLat/2) + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLon/2) * Math.sin(dLon/2);
                    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                    return R * c;
                }

                // Start watchPosition to monitor arrival
                function startWatchForArrival(dest) {
                    if (!navigator.geolocation) return;
                    if (watchId !== null) navigator.geolocation.clearWatch(watchId);
                    const opts = { enableHighAccuracy: true, maximumAge: 0, timeout: 10000 };
                    watchId = navigator.geolocation.watchPosition((pos) => {
                        const { latitude, longitude } = pos.coords;
                        const d = distanceMeters(latitude, longitude, dest.lat, dest.lng);
                        distanceToDestEl.textContent = `Distance to ${dest.name}: ${Math.round(d)} m`;
                        // if within 15 meters, auto-stop
                        if (d <= 15) {
                            distanceToDestEl.textContent = `Arrived at ${dest.name} (≈ ${Math.round(d)} m)`;
                            // stop tracking using current coords
                            stopTracking({ latitude, longitude });
                        }
                    }, (err) => {
                        console.warn('watchPosition error', err);
                    }, opts);
                }

                // Load customers from server and populate sidebar list
                async function loadCustomers() {
                    const resp = await postData('list_customers', {});
                    customerListEl.innerHTML = '';
                    if (!resp.success || !Array.isArray(resp.data)) return;
                    resp.data.forEach(c => {
                        const item = document.createElement('div');
                        item.className = 'customer-item';
                        item.dataset.id = c.id;
                        item.dataset.lat = c.lat;
                        item.dataset.lng = c.lng;
                        item.dataset.mapLink = c.map_link;
                        item.innerHTML = `<div class="title">${escapeHtml(c.name)}</div><div class="meta">${c.lat ? Math.round(parseFloat(c.lat)*100)/100 : ''}, ${c.lng ? Math.round(parseFloat(c.lng)*100)/100 : ''}</div>`;
                        item.addEventListener('click', () => {
                            // mark selected visually
                            Array.from(customerListEl.querySelectorAll('.customer-item')).forEach(el => el.classList.remove('selected'));
                            item.classList.add('selected');
                            selectedDestination = { id: c.id, name: c.name, lat: parseFloat(c.lat), lng: parseFloat(c.lng), map_link: c.map_link };
                            selectedDestDisplay.textContent = c.name;
                            distanceToDestEl.textContent = `Selected: ${c.name}`;
                            // auto-close sidebar on selection
                            customerSidebar.style.display = 'none';
                            customerSidebar.setAttribute('aria-hidden','true');
                        });
                        customerListEl.appendChild(item);
                    });
                    if (customerListEl.children.length === 0) {
                        const el = document.createElement('div'); el.className = 'customer-item'; el.textContent = 'No customers'; customerListEl.appendChild(el);
                    }
                }

                // simple HTML escape for names
                function escapeHtml(s) {
                    return (s + '').replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; });
                }
            });
        </script>

    <?php else: ?>

        <div class="flex items-center justify-center min-h-[60vh]">
            <div class="auth-card">
            <h2 class="text-2xl font-bold mb-6 text-center">ចូល</h2>
            <form id="loginForm" class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="login_id">
                        លេខសម្គាល់ចូល
                    </label>
                    <input class="form-input" id="login_id" type="text" placeholder="លេខសម្គាល់ចូលរបស់អ្នក" aria-label="លេខសម្គាល់ចូល">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                        ពាក្យសម្ងាត់
                    </label>
                    <input class="form-input" id="password" type="password" placeholder="******************" aria-label="ពាក្យសម្ងាត់">
                </div>
                <!-- Remember Me Checkbox -->
                <div>
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" id="remember" class="form-checkbox h-4 w-4 text-cyan-500 rounded border-gray-300 focus:ring-cyan-500">
                        <span class="ml-2 text-sm text-gray-600">ចងចាំការ Login</span>
                    </label>
                </div>
                <div class="flex items-center justify-between">
                    <button class="btn btn-start w-full" type="submit">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        ចូល
                    </button>
                </div>
                <p id="error-msg" class="text-red-500 text-xs italic mt-4"></p>
            </form>
        </div>
        </div> <!-- End centering container -->

        <script>
            document.getElementById('loginForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                const login_id = document.getElementById('login_id').value;
                const password = document.getElementById('password').value;
                const remember = document.getElementById('remember').checked; // <-- [ផ្នែកបន្ថែម] យកតម្លៃពី Checkbox
                const errorMsg = document.getElementById('error-msg');

                const response = await fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'login',
                        login_id: login_id,
                        password: password,
                        remember: remember // <-- [ផ្នែកបន្ថែម] បញ្ជូនតម្លៃ remember ទៅកាន់ server
                    })
                });

                // --- ត្រួតពិនិត្យ Error 500 ---
                if (!response.ok) {
                    errorMsg.textContent = 'Server Error (Error 500 or other). Please check PHP logs.';
                    return;
                }

                try {
                    const result = await response.json();
                    if (result.success) {
                        window.location.reload();
                    } else {
                        errorMsg.textContent = result.message;
                    }
                } catch (e) {
                     errorMsg.textContent = 'Error reading server response (Not JSON). Check PHP code.';
                }
            });
        </script>

    <?php endif; ?>

</body>
</html>