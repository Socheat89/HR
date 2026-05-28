<?php
session_start();

// Load Theme Config
$themeConfigPath = __DIR__ . '/../admin/includes/theme_config.json';
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


// Prevent caching - ការពារ browser cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Cache-Control: s-maxage=0, proxy-revalidate", false);
header("Surrogate-Control: no-store");
header("X-Accel-Expires: 0");
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header('Clear-Site-Data: "cache"');

// Auto cache-buster: ensure first normal GET load always uses a unique URL.
// This prevents sticky browser/proxy caches without requiring Ctrl+F5.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
    && !isset($_GET['t'])
    && !isset($_GET['fresh'])
    && !isset($_GET['clear_cache'])
    && !isset($_GET['logout'])) {
    $path = strtok($_SERVER['REQUEST_URI'] ?? '../auth/login.php', '?');
    parse_str($_SERVER['QUERY_STRING'] ?? '', $qs);
    $qs['t'] = (string) time();
    header('Location: ' . $path . '?' . http_build_query($qs));
    exit();
}

// Force-clear browser cache/storage if requested (safe: affects only the current browser).
// Use: ../auth/login.php?fresh=1
if (isset($_GET['fresh'])) {
    header('Clear-Site-Data: "cache", "cookies", "storage"');
    header("Location: ../auth/login.php?t=" . time());
    exit();
}

// Clear server-side caches (OPcache/APCu) + browser cache/storage (localhost only).
// Use: ../auth/login.php?clear_cache=1 (must be called from the server itself)
if (isset($_GET['clear_cache']) && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)) {
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    if (function_exists('apcu_clear_cache')) {
        @apcu_clear_cache();
    }
    header('Clear-Site-Data: "cache", "cookies", "storage"');
    header("Location: ../auth/login.php?t=" . time());
    exit();
}

require_once '../admin/includes/db.php';

// TOTP Functions
function normalize_totp_secret($secret) {
    return strtoupper(preg_replace('/\s+/', '', $secret));
}

function base32_decode_custom($data) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    $data = normalize_totp_secret($data);

    for ($i = 0; $i < strlen($data); $i++) {
        $char = $data[$i];
        $value = strpos($alphabet, $char);
        if ($value === false) {
            return false;
        }
        $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
    }

    $binary = '';
    for ($i = 0, $len = strlen($bits); $i + 8 <= $len; $i += 8) {
        $binary .= chr(bindec(substr($bits, $i, 8)));
    }

    return $binary;
}

function generate_totp_secret($length = 16) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $secret;
}

function build_otpauth_uri($issuer, $account, $secret) {
    $label = rawurlencode($issuer . ':' . $account);
    $issuer_param = rawurlencode($issuer);
    return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer_param}&digits=6&period=30";
}

function build_qr_url($otpauth_uri) {
    $encoded = urlencode($otpauth_uri);
    return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={$encoded}";
}

function calculate_totp($secret, $timeSlice = null, $digits = 6, $period = 30) {
    $secret = normalize_totp_secret($secret);
    if ($timeSlice === null) {
        $timeSlice = floor(time() / $period);
    }

    $secretKey = base32_decode_custom($secret);
    if ($secretKey === false) {
        return null;
    }

    $time = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $time, $secretKey, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $truncatedHash = unpack('N', substr($hash, $offset, 4))[1];
    $truncatedHash = $truncatedHash & 0x7FFFFFFF;
    $code = $truncatedHash % pow(10, $digits);

    return str_pad($code, $digits, '0', STR_PAD_LEFT);
}

function verify_totp_code($secret, $code, $window = 1, $period = 30) {
    $secret = normalize_totp_secret($secret);
    if ($secret === '') {
        return false;
    }

    $code = trim($code);
    if ($code === '' || strlen($code) < 6) {
        return false;
    }

    $timeSlice = floor(time() / $period);
    for ($i = -$window; $i <= $window; $i++) {
        $calculated = calculate_totp($secret, $timeSlice + $i, 6, $period);
        if ($calculated !== null && hash_equals($calculated, $code)) {
            return true;
        }
    }

    return false;
}

function check_totp_columns(PDO $conn) {
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'totp_secret'");
        $secretExists = (bool) $stmt->fetch();
        $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'totp_enabled'");
        $enabledExists = (bool) $stmt->fetch();
        return $secretExists && $enabledExists;
    } catch (PDOException $e) {
        error_log('Check TOTP columns failed: ' . $e->getMessage());
        return false;
    }
}

// Logout - Clear all session and cookies
if (isset($_GET['logout'])) {
    // Clear session data
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
    
    // Clear any other cookies
    if (isset($_COOKIE['PHPSESSID'])) {
        setcookie('PHPSESSID', '', time() - 3600, '/');
    }
    
    // Redirect with cache-busting parameter
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
    header('Clear-Site-Data: "cache", "cookies", "storage"');
    header("Location: ../auth/login.php?t=" . time());
    exit();
}

// Already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../homes.php");
    exit();
}

$error = '';
$show_totp_form = false;

// Check if TOTP columns exist
$has_totp_columns = check_totp_columns($conn);

// Login form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$conn) {
        $error = 'មិនអាចតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យបានទេ។';
    } else {
        // Handle TOTP verification if pending
        if (isset($_POST['totp_code']) && isset($_SESSION['totp_pending'])) {
            $totp_code = trim($_POST['totp_code']);
            $user_data = $_SESSION['totp_pending'];
            
            if ($has_totp_columns && !empty($user_data['totp_secret']) && verify_totp_code($user_data['totp_secret'], $totp_code)) {
                // TOTP verified, complete login
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user_data['id'];
                $_SESSION['username'] = $user_data['username'];
                $_SESSION['role'] = $user_data['role'];
                $_SESSION['last_login'] = date('Y-m-d H:i:s');
                unset($_SESSION['totp_pending']);
                
                header("Location: ../homes.php");
                exit();
            } else {
                $error = 'លេខកូដ 2FA មិនត្រឹមត្រូវទេ។ សូមព្យាយាមម្តងទៀត។';
                $show_totp_form = true;
            }
        } 
        // Handle username/password login
        elseif (isset($_POST['username']) && isset($_POST['password'])) {
            $username = trim($_POST['username'] ?? '');
            $password = trim($_POST['password'] ?? '');

            try {
                $stmt = $conn->prepare("SELECT id, username, password, role, status" . ($has_totp_columns ? ", totp_secret, totp_enabled" : "") . " FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status'] === 'active') {
                        $totp_enabled = $has_totp_columns && !empty($user['totp_enabled']);
                        
                        if ($totp_enabled) {
                            // TOTP enabled, require verification
                            $_SESSION['totp_pending'] = $user;
                            $show_totp_form = true;
                        } else {
                            // No TOTP, direct login
                            session_regenerate_id(true);
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['role'] = $user['role'];
                            $_SESSION['last_login'] = date('Y-m-d H:i:s');

                            header("Location: ../homes.php");
                            exit();
                        }
                    } else {
                        $error = 'គណនីរបស់អ្នកត្រូវបានបិទ។ សូមទាក់ទងអ្នកគ្រប់គ្រង។';
                    }
                } else {
                    $error = 'ឈ្មោះអ្នកប្រើ ឬ ពាក្យសម្ងាត់មិនត្រឹមត្រូវទេ!';
                }
            } catch (PDOException $e) {
                error_log('Login Page DB Error: ' . $e->getMessage());
                $error = 'មានបញ្ហាប្រព័ន្ធ។ សូមព្យាយាមម្តងទៀត។';
            }
        }
    }
}

// Check if we need to show TOTP form (either from POST or session)
if (isset($_SESSION['totp_pending']) && !$show_totp_form && empty($error)) {
    $show_totp_form = true;
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
<meta charset="utf-8">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ចូលប្រព័ន្ធ - HR Management</title>
<link rel="preload" href="../assets/sound/chinese effect.mp3" as="audio">

<!-- Lordicon -->
<script src="https://cdn.lordicon.com/lordicon.js"></script>

<!-- Khmer font -->
<link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;600&display=swap" rel="stylesheet">

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
    :root{
        --accent-1: #ff7eb3;
        --accent-2: #ff5ab3;
        --accent-3: #ff9ed6;
        --glass-bg: rgba(255,255,255,0.28);
    }

    html,body { height:100%; margin:0; padding:0; }

    body{
        display:flex;
        align-items:center;
        justify-content:center;
        font-family: 'Kantumruy Pro', 'Noto Sans Khmer', sans-serif;
        background: linear-gradient(-45deg, #d4a373, #c77dff, #ff7eb3, #74b9ff, #a29bfe);
        background-size: 400% 400%;
        animation: bgMove 15s ease infinite;
        -webkit-font-smoothing:antialiased;
        -moz-osx-font-smoothing:grayscale;
        overflow: hidden;
    }

    @keyframes bgMove {
        0% { background-position: 0% 50%; }
        25% { background-position: 100% 50%; }
        50% { background-position: 100% 100%; }
        75% { background-position: 0% 100%; }
        100% { background-position: 0% 50%; }
    }

    @keyframes bgZoom {
        from { background-size: 100% 100%; }
        to { background-size: 110% 110%; }
    }

    /* Season/Festival Theme Overrides */
    <?php if ($currentTheme === 'kny'): ?>
    :root { --accent-1: #f59e0b; --accent-2: #d97706; --accent-3: #fbbf24; }
    .title { color: #d97706 !important; }
    /* Fireworks Overlay for KNY */
    body::after {
        content: "";
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background-image: url('https://media.tenor.com/XesYJjyNYgAAAAAi/fireworks-putukan.gif');
        background-size: cover; background-repeat: no-repeat;
        pointer-events: none; z-index: -1; opacity: 0.35; mix-blend-mode: screen;
    }
    
    <?php elseif ($currentTheme === 'pb'): ?>
    :root { --accent-1: #ea580c; --accent-2: #c2410c; --accent-3: #fdba74; }
    .title { color: #c2410c !important; }

    <?php elseif ($currentTheme === 'cny'): ?>
    :root { --accent-1: #dc2626; --accent-2: #b91c1c; --accent-3: #f87171; }
    .title { color: #b91c1c !important; }

    <?php elseif ($currentTheme === 'wf'): ?>
    :root { --accent-1: #0284c7; --accent-2: #0369a1; --accent-3: #38bdf8; }
    .title { color: #0369a1 !important; }
    <?php endif; ?>

    /* Apply Theme Background Image */
    <?php if (!empty($bgImage)): ?>
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
        will-change: transform;
    }
    
    @keyframes bgFloat {
        0% { transform: scale(1) translate(0, 0) rotate(0deg); }
        25% { transform: scale(1.02) translate(-1%, 0.5%) rotate(0.5deg); }
        50% { transform: scale(1.05) translate(1%, -0.5%) rotate(-0.5deg); }
        75% { transform: scale(1.03) translate(-0.5%, 1%) rotate(0.2deg); }
        100% { transform: scale(1.06) translate(0.5%, -1%) rotate(-0.2deg); }
    }
    
    body {
        /* Background managed by container */
        background-color: transparent !important;
    }

    /* Overlay to ensure readability */
    body::before {
        content: "";
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(255, 255, 255, 0.4);
        backdrop-filter: blur(2px);
        z-index: -2;
    }
    <?php endif; ?>

    /* Login card (glass) */
    .login-card {
        width: 420px;
        max-width: calc(100% - 40px);
        padding:28px 28px;
        border-radius:20px;
        background: rgba(255, 255, 255, 0.75);
        backdrop-filter: blur(14px) saturate(140%);
        -webkit-backdrop-filter: blur(14px) saturate(140%);
        border: 1px solid rgba(255,255,255,0.45);
        box-shadow: 0 12px 40px rgba(0,0,0,0.12);
        text-align:center;
        position:relative;
        animation: cardFloat 6s ease-in-out infinite;
    }

    @keyframes cardFloat {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-5px); }
    }

    .brand-icon {
        margin: 4px auto 8px;
    }

    .title {
        color: #c01f7a;
        font-size:22px;
        font-weight:600;
        margin:8px 0 18px;
    }

    .field {
        width:100%;
        display:block;
        margin:10px 0;
        position: relative;
    }

    .password-container {
        position: relative;
    }

    .password-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #666;
        cursor: pointer;
        padding: 4px;
        border-radius: 4px;
        transition: color 0.2s ease;
    }

    .password-toggle:hover {
        color: var(--accent-2);
    }

    .password-toggle i {
        font-size: 16px;
    }

    input[type="text"],
    input[type="password"]{
        width:100%;
        padding:12px 14px;
        border-radius:12px;
        border: 1px solid rgba(255,150,190,0.5);
        background: rgba(255,255,255,0.85);
        font-size:15px;
        outline:none;
        box-sizing:border-box;
        transition: box-shadow .18s ease, transform .12s ease;
    }
    input:focus{
        box-shadow: 0 6px 18px rgba(255,107,159,0.18);
        transform: translateY(-1px);
        border-color: var(--accent-2);
    }

    .btn {
        width:100%;
        padding:12px 14px;
        border-radius:12px;
        border:none;
        font-weight:700;
        font-size:16px;
        color:#fff;
        background: linear-gradient(135deg,var(--accent-1),var(--accent-2));
        cursor:pointer;
        transition: transform .12s ease, box-shadow .12s ease, background .3s ease;
    }
    .btn:hover {
        background: linear-gradient(135deg,var(--accent-2),var(--accent-1));
        box-shadow: 0 8px 25px rgba(255,122,179,0.3);
    }
    .btn:active{ transform: translateY(1px); }
    .btn:disabled{ opacity:.6; cursor:not-allowed; }

    .code-display {
        margin: 15px 0;
        padding: 15px;
        background: rgba(255,255,255,0.9);
        border: 2px dashed var(--accent-2);
        border-radius: 12px;
        text-align: center;
        display: none;
        position: relative;
    }

    .code-display.show {
        display: block;
        animation: fadeIn 0.3s ease;
    }

    .code-text {
        font-family: 'Courier New', monospace;
        font-size: 24px;
        font-weight: bold;
        color: var(--accent-2);
        letter-spacing: 2px;
        margin: 10px 0;
        user-select: all;
        transition: all 0.3s ease;
    }

    .code-text.hidden {
        color: transparent;
        text-shadow: 0 0 8px rgba(255, 107, 159, 0.5);
        -webkit-text-stroke: 1px var(--accent-2);
    }

    .code-label {
        font-size: 14px;
        color: #666;
        margin-bottom: 10px;
    }

    .code-eye-toggle {
        position: absolute;
        top: 10px;
        right: 10px;
        background: none;
        border: none;
        color: var(--accent-2);
        cursor: pointer;
        padding: 5px;
        border-radius: 50%;
        transition: all 0.2s ease;
        font-size: 16px;
    }

    .code-eye-toggle:hover {
        background: rgba(255, 107, 159, 0.1);
        transform: scale(1.1);
    }

    .show-code-btn {
        background: none;
        border: none;
        color: var(--accent-2);
        text-decoration: underline;
        cursor: pointer;
        font-size: 14px;
        margin-top: 10px;
        transition: color 0.2s ease;
    }

    .show-code-btn:hover {
        color: var(--accent-1);
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* floating decorative elements */
    .decor {
        position: absolute;
        pointer-events:none;
        inset: -30px -30px auto auto;
        width:100%;
        height:100%;
    }

    .floating-element{
        position:absolute;
        opacity:.7;
        filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
        animation: floatUp linear infinite;
    }

    .heart{
        width:24px;
        height:24px;
        background-image: url('https://i.ibb.co/2vHTkK3/heart.png');
        background-size:cover;
    }

    .star{
        width:20px;
        height:20px;
        background: radial-gradient(circle, #ffd700 0%, #ffed4e 50%, transparent 70%);
        border-radius: 50%;
        box-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
    }

    .circle{
        width:16px;
        height:16px;
        background: radial-gradient(circle, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0.3) 70%, transparent 100%);
        border-radius: 50%;
    }

    /* Position floating elements */
    .heart.h1{ left:6%; top:70%; animation-duration:9s; animation-delay: 0s; }
    .heart.h2{ left:28%; top:80%; animation-duration:11s; animation-delay: 2s; }
    .heart.h3{ left:50%; top:72%; animation-duration:8s; animation-delay: 4s; }
    .heart.h4{ left:72%; top:78%; animation-duration:10s; animation-delay: 1s; }
    .heart.h5{ left:88%; top:68%; animation-duration:7.5s; animation-delay: 3s; }

    .star.s1{ left:15%; top:60%; animation-duration:12s; animation-delay: 1.5s; }
    .star.s2{ left:65%; top:85%; animation-duration:14s; animation-delay: 4.5s; }
    .star.s3{ left:35%; top:55%; animation-duration:10s; animation-delay: 2.5s; }

    .circle.c1{ left:80%; top:50%; animation-duration:13s; animation-delay: 0.5s; }
    .circle.c2{ left:20%; top:90%; animation-duration:16s; animation-delay: 3.5s; }
    .circle.c3{ left:55%; top:45%; animation-duration:11s; animation-delay: 5.5s; }

    @keyframes floatUp {
        0%{ transform: translateY(0) rotate(0deg) scale(1); opacity:0; }
        10%{ opacity:0.7; }
        90%{ opacity:0.7; }
        100%{ transform: translateY(-160px) rotate(360deg) scale(1.2); opacity:0; }
    }

    /* Loading overlay (full-page) */
    #page-loading {
        position: fixed;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
        background-size: 200% 200%;
        animation: gradientShift 3s ease infinite;
        z-index: 99999;
        visibility: hidden;
        opacity: 0;
        transition: opacity .3s ease, visibility .3s ease;
        overflow: hidden;
    }

    @keyframes gradientShift {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    #page-loading.show {
        visibility: visible;
        opacity: 1;
    }

    /* Loading container */
    .loading-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px;
        border-radius: 30px;
        background: rgba(255, 255, 255, 0.15);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        animation: containerPulse 2s ease-in-out infinite;
    }

    @keyframes containerPulse {
        0%, 100% { transform: scale(1); box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15); }
        50% { transform: scale(1.02); box-shadow: 0 30px 60px rgba(0, 0, 0, 0.2); }
    }

    /* Animated loader ring */
    .loader-ring {
        position: relative;
        width: 120px;
        height: 120px;
        margin-bottom: 20px;
    }

    .loader-ring::before,
    .loader-ring::after {
        content: '';
        position: absolute;
        border-radius: 50%;
    }

    .loader-ring::before {
        inset: 0;
        border: 4px solid rgba(255, 255, 255, 0.2);
    }

    .loader-ring::after {
        inset: 0;
        border: 4px solid transparent;
        border-top-color: #fff;
        border-right-color: #fff;
        animation: ringRotate 1s linear infinite;
    }

    @keyframes ringRotate {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Center icon wrapper */
    .loader-icon-wrapper {
        position: absolute;
        inset: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 255, 255, 0.25);
        border-radius: 50%;
        animation: iconPulse 1.5s ease-in-out infinite;
    }

    @keyframes iconPulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }

    #page-loading .msg {
        margin-top: 15px;
        font-size: 22px;
        color: #fff;
        font-weight: 600;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        animation: textPulse 1.5s ease-in-out infinite;
    }

    @keyframes textPulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }

    /* Loading dots animation */
    .loading-dots {
        display: flex;
        gap: 8px;
        margin-top: 15px;
    }

    .loading-dots span {
        width: 12px;
        height: 12px;
        background: #fff;
        border-radius: 50%;
        animation: dotBounce 1.4s ease-in-out infinite;
    }

    .loading-dots span:nth-child(1) { animation-delay: 0s; }
    .loading-dots span:nth-child(2) { animation-delay: 0.2s; }
    .loading-dots span:nth-child(3) { animation-delay: 0.4s; }

    @keyframes dotBounce {
        0%, 80%, 100% { transform: translateY(0); opacity: 0.5; }
        40% { transform: translateY(-15px); opacity: 1; }
    }

    /* Floating particles in loader */
    .loader-particles {
        position: absolute;
        inset: 0;
        overflow: hidden;
        pointer-events: none;
    }

    .particle {
        position: absolute;
        width: 10px;
        height: 10px;
        background: rgba(255, 255, 255, 0.6);
        border-radius: 50%;
        animation: particleFloat 4s ease-in-out infinite;
    }

    .particle:nth-child(1) { left: 10%; top: 20%; animation-delay: 0s; width: 8px; height: 8px; }
    .particle:nth-child(2) { left: 20%; top: 80%; animation-delay: 1s; width: 6px; height: 6px; }
    .particle:nth-child(3) { left: 80%; top: 30%; animation-delay: 2s; width: 12px; height: 12px; }
    .particle:nth-child(4) { left: 70%; top: 70%; animation-delay: 0.5s; width: 7px; height: 7px; }
    .particle:nth-child(5) { left: 40%; top: 10%; animation-delay: 1.5s; width: 9px; height: 9px; }
    .particle:nth-child(6) { left: 90%; top: 50%; animation-delay: 2.5s; width: 5px; height: 5px; }

    @keyframes particleFloat {
        0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.6; }
        50% { transform: translateY(-30px) rotate(180deg); opacity: 1; }
    }

    /* Remember Me Checkbox (Font Awesome Styled) */
    .remember-me {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        margin: 15px 0 25px;
        font-size: 15px;
        color: #555;
        position: relative;
    }
    
    /* Hide default checkbox but keep it accessible/focusable */
    .remember-me input[type="checkbox"] {
        opacity: 0;
        position: absolute;
        width: 0;
        height: 0;
        margin: 0;
    }
    
    .remember-me label {
        cursor: pointer;
        user-select: none;
        display: flex;
        align-items: center;
        gap: 10px;
        font-weight: 500;
        transition: color 0.2s;
        color: #666;
    }

    .remember-me label:hover {
        color: var(--accent-2);
    }
    
    /* Custom Checkbox Box (Unchecked) */
    .remember-me label::before {
        content: '\f0c8'; /* fa-square */
        font-family: 'Font Awesome 6 Free';
        font-weight: 400; /* Regular style */
        font-size: 20px;
        color: #bbb;
        transition: all 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    /* Hover effect on the box */
    .remember-me label:hover::before {
        color: var(--accent-1);
        transform: scale(1.1);
    }

    /* Checked State */
    .remember-me input[type="checkbox"]:checked + label::before {
        content: '\f14a'; /* fa-check-square */
        font-weight: 900; /* Solid style */
        color: var(--accent-2);
        animation: checkPop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    
    /* Checked Label Text Color */
    .remember-me input[type="checkbox"]:checked + label {
        color: var(--accent-2);
        font-weight: 600;
    }

    @keyframes checkPop {
        0% { transform: scale(0.8); }
        50% { transform: scale(1.15); }
        100% { transform: scale(1); }
    }

    /* ensure lord-icon sizes nicely in loader */
    #page-loading lord-icon {
        width: 80px;
        height: 80px;
    }

    /* small responsive adjustments */
    @media (max-width:420px){
        .login-card{ padding:20px; border-radius:18px; }
        #page-loading lord-icon{ width:60px; height:60px; }
        .loading-container { padding: 30px; border-radius: 25px; }
        .loader-ring { width: 100px; height: 100px; }
        #page-loading .msg { font-size: 18px; }
        .loading-dots span { width: 10px; height: 10px; }
    }
</style>
</head>
<body>
    <?php if (!empty($bgImage)): ?>
    <div class="bg-animate-container"></div>
    <?php endif; ?>

<!-- Loading overlay (Modern animated design) -->
<div id="page-loading" role="status" aria-hidden="true">
    <!-- Floating particles background -->
    <div class="loader-particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <!-- Loading container with glass effect -->
    <div class="loading-container">
        <!-- Animated ring with icon -->
        <div class="loader-ring">
            <div class="loader-icon-wrapper">
                <lord-icon
                    src="https://cdn.lordicon.com/ktsahwvc.json"
                    trigger="loop"
                    colors="primary:#ffffff,secondary:#ffd6e7"
                    style="width:80px;height:80px">
                </lord-icon>
            </div>
        </div>

        <div class="msg">កំពុងចូល សូមរង់ចាំបន្តិច</div>

        <!-- Bouncing dots -->
        <div class="loading-dots">
            <span></span>
            <span></span>
            <span></span>
        </div>
    </div>
</div>

<!-- Login card -->
<div class="login-card" role="main" aria-labelledby="loginTitle">
   
    <div class="brand-icon">
        <!-- decorative lordicon (logo/office) -->
        <lord-icon
            src="https://cdn.lordicon.com/dxjqoygy.json"
            trigger="hover"
            colors="primary:#ff4fa8,secondary:#ff8ac6"
            style="width:110px;height:110px">
        </lord-icon>
    </div>

    <div id="loginTitle" class="title"><?php echo $show_totp_form ? 'បញ្ជាក់ 2FA' : 'ចូលប្រព័ន្ធ'; ?></div>

    <?php if (!empty($error)): ?>
        <div class="error" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- keep action empty (submit to same file) -->
    <form id="loginForm" method="POST" novalidate>
        <?php if ($show_totp_form): ?>
            <!-- TOTP Verification Form -->
            <div class="field">
                <input class="field" type="text" name="totp_code" placeholder="លេខកូដ 6 ខ្ទង់ពី Google Authenticator" autocomplete="one-time-code" required maxlength="6" pattern="[0-9]{6}" />
            </div>
            <button id="submitBtn" class="btn" type="submit">បញ្ជាក់</button>
            <button type="button" class="btn" style="background: #6c757d; margin-top: 10px;" onclick="window.location.reload()">ត្រឡប់</button>
        <?php else: ?>
            <!-- Username/Password Form -->
            <input class="field" type="text" name="username" id="username" placeholder="ឈ្មោះអ្នកប្រើ" autocomplete="username" required />
            <div class="field password-container">
                <input type="password" name="password" id="password" placeholder="ពាក្យសម្ងាត់" autocomplete="current-password" required />
                <button type="button" class="password-toggle" id="passwordToggle" title="បង្ហាញ/លាក់ពាក្យសម្ងាត់">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            
            <!-- Remember Me Checkbox -->
            <div class="remember-me">
                <input type="checkbox" id="rememberMe">
                <label for="rememberMe">ចងចាំខ្ញុំ</label>
            </div>

            <!-- Code Display Section -->
            <div class="code-display" id="codeDisplay">
                <button type="button" class="code-eye-toggle" id="codeEyeToggle" title="បង្ហាញ/លាក់លេខកូដ">
                    <i class="fas fa-eye"></i>
                </button>
                <div class="code-label">លេខកូដសម្រាប់ចូលប្រព័ន្ធ</div>
                <div class="code-text" id="verificationCode">
                    <?php
                    // Generate a random 6-digit code for demonstration
                    echo sprintf("%06d", rand(0, 999999));
                    ?>
                </div>
                <div style="font-size: 12px; color: #888; margin-top: 5px;">
                    ចុចលើលេខកូដដើម្បីចម្លង
                </div>
                <button type="button" class="show-code-btn" id="showCodeBtn" style="margin-top: 10px; padding: 5px 15px; font-size: 12px;">
                    <i class="fas fa-qrcode"></i> ចុចមើលលេខកូដ
                </button>
                <button type="button" class="btn btn-sm" id="refreshCodeBtn" style="margin-top: 10px; padding: 5px 15px; font-size: 12px;">
                    <i class="fas fa-refresh"></i> ប្តូរលេខកូដថ្មី
                </button>
            </div>

            <button id="submitBtn" class="btn" type="submit">ចូល</button>
        <?php endif; ?>
    </form>
</div>

<script>
(function(){
    const form = document.getElementById('loginForm');
    const loader = document.getElementById('page-loading');
    const submitBtn = document.getElementById('submitBtn');

    // Password visibility toggle (only if password field exists)
    const passwordToggle = document.getElementById('passwordToggle');
    const passwordInput = document.querySelector('input[name="password"]');

    if (passwordToggle && passwordInput) {
        passwordToggle.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            // Toggle icon
            const icon = this.querySelector('i');
            icon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
        });
    }
 
    // Code display toggle (only if code display exists)
    const showCodeBtn = document.getElementById('showCodeBtn');
    const codeDisplay = document.getElementById('codeDisplay');
    const verificationCode = document.getElementById('verificationCode');
    const codeEyeToggle = document.getElementById('codeEyeToggle');
    const codeEyeIcon = codeEyeToggle ? codeEyeToggle.querySelector('i') : null;

    if (showCodeBtn && codeDisplay) {
        showCodeBtn.addEventListener('click', function() {
            if (codeDisplay.classList.contains('show')) {
                codeDisplay.classList.remove('show');
                this.innerHTML = '<i class="fas fa-qrcode"></i> ចុចមើលលេខកូដ';
            } else {
                codeDisplay.classList.add('show');
                this.innerHTML = '<i class="fas fa-times"></i> លាក់លេខកូដ';
            }
        });
    }

    // Code visibility toggle (eye icon)
    if (codeEyeToggle && verificationCode && codeEyeIcon) {
        let codeVisible = true; // Start with code visible
        codeEyeToggle.addEventListener('click', function() {
            codeVisible = !codeVisible;

            if (codeVisible) {
                verificationCode.classList.remove('hidden');
                codeEyeIcon.className = 'fas fa-eye';
                this.title = 'លាក់លេខកូដ';
            } else {
                verificationCode.classList.add('hidden');
                codeEyeIcon.className = 'fas fa-eye-slash';
                this.title = 'បង្ហាញលេខកូដ';
            }
        });
    }

    // Remember Me Logic
    const usernameInput = document.getElementById('username');
    const rememberMeCheckbox = document.getElementById('rememberMe');
    
    if (usernameInput && passwordInput && rememberMeCheckbox) {
        // Load saved credentials
        if (localStorage.getItem('hrm_remember_me') === 'true') {
            usernameInput.value = localStorage.getItem('hrm_username') || '';
            passwordInput.value = localStorage.getItem('hrm_password') || '';
            rememberMeCheckbox.checked = true;
        }
        
        // Save credentials on submit
        form.addEventListener('submit', function() {
            if (rememberMeCheckbox.checked) {
                localStorage.setItem('hrm_remember_me', 'true');
                localStorage.setItem('hrm_username', usernameInput.value);
                localStorage.setItem('hrm_password', passwordInput.value);
            } else {
                localStorage.removeItem('hrm_remember_me');
                localStorage.removeItem('hrm_username');
                localStorage.removeItem('hrm_password');
            }
        });
    }

    // Copy code on click (only if verificationCode exists)
    if (verificationCode) {
        verificationCode.addEventListener('click', function() {
            // Get the actual code value, not the display text
            const code = this.textContent.trim().replace(/\s+/g, ''); // Remove any extra spaces

            navigator.clipboard.writeText(code).then(function() {
                // Show temporary feedback
                const originalText = verificationCode.textContent;
                const wasHidden = verificationCode.classList.contains('hidden');

                if (!wasHidden) {
                    verificationCode.textContent = 'ចម្លងរួច!';
                    verificationCode.style.color = '#28a745';
                }

                setTimeout(() => {
                    verificationCode.textContent = originalText;
                    if (!wasHidden) {
                        verificationCode.style.color = 'var(--accent-2)';
                    }
                }, 1000);
            }).catch(function(err) {
                console.error('Failed to copy: ', err);
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = code;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);

                const originalText = verificationCode.textContent;
                const wasHidden = verificationCode.classList.contains('hidden');

                if (!wasHidden) {
                    verificationCode.textContent = 'ចម្លងរួច!';
                    verificationCode.style.color = '#28a745';
                }

                setTimeout(() => {
                    verificationCode.textContent = originalText;
                    if (!wasHidden) {
                        verificationCode.style.color = 'var(--accent-2)';
                    }
                }, 1000);
            });
        });
    }

    // Refresh code button (only if exists)
    const refreshCodeBtn = document.getElementById('refreshCodeBtn');
    if (refreshCodeBtn && verificationCode) {
        refreshCodeBtn.addEventListener('click', function() {
            // Generate new random code
            const newCode = Math.floor(100000 + Math.random() * 900000);
            const formattedCode = newCode.toString().padStart(6, '0');
            verificationCode.textContent = formattedCode;

            // Add refresh animation
            verificationCode.style.transform = 'scale(1.1)';
            setTimeout(() => {
                verificationCode.style.transform = 'scale(1)';
            }, 200);

            // Reset to visible state if it was hidden
            if (!codeVisible) {
                codeVisible = true;
                verificationCode.classList.remove('hidden');
                if (codeEyeIcon) codeEyeIcon.className = 'fas fa-eye';
                if (codeEyeToggle) codeEyeToggle.title = 'លាក់លេខកូដ';
            }
        });
    }

    form.addEventListener('submit', function(e){
        // Basic client-side validation (let server still validate)
        const user = form.username.value.trim();
        const pass = form.password.value.trim();

        if (!user || !pass) {
            // allow browser-native validation to show; prevent showing loader
            return;
        }

        // Show loader & disable button to prevent double submit
        loader.classList.add('show');
        loader.setAttribute('aria-hidden','false');
        submitBtn.disabled = true;

        // Let the form submit normally to server (no e.preventDefault())
        // But to make sure loader shows instantly on slow connections, we flush changes
        // (No additional action here; server will redirect after processing)
    });

    // Hide loader on navigation/back-forward; and if restored from bfcache, force a real refresh
    window.addEventListener('pageshow', function(event){
        loader.classList.remove('show');
        loader.setAttribute('aria-hidden','true');
        submitBtn.disabled = false;

        if (event && event.persisted) {
            const url = new URL(window.location.href);
            url.searchParams.set('t', Date.now().toString());
            window.location.replace(url.toString());
        }
    });

    // Forgot Password functionality
    const forgotPasswordLink = document.getElementById('forgotPasswordLink');
    const forgotPasswordModal = new bootstrap.Modal(document.getElementById('forgotPasswordModal'));
    const forgotPasswordForm = document.getElementById('forgotPasswordForm');
    const resetCodeSection = document.getElementById('resetCodeSection');
    const forgotPasswordMessage = document.getElementById('forgotPasswordMessage');
    const newPasswordToggle = document.getElementById('newPasswordToggle');
    const newPasswordInput = document.getElementById('newPassword');

    forgotPasswordLink.addEventListener('click', function(e) {
        e.preventDefault();
        forgotPasswordModal.show();
    });

    // New password visibility toggle
    newPasswordToggle.addEventListener('click', function() {
        const type = newPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        newPasswordInput.setAttribute('type', type);

        const icon = this.querySelector('i');
        icon.className = type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
    });

    // Handle forgot password form submission
    forgotPasswordForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const username = document.getElementById('resetUsername').value.trim();
        const email = document.getElementById('resetEmail').value.trim();

        if (!username) {
            showForgotPasswordMessage('សូមបញ្ចូលឈ្មោះអ្នកប្រើ', 'danger');
            return;
        }

        // Show loading state
        const resetBtn = document.getElementById('resetPasswordBtn');
        const originalText = resetBtn.innerHTML;
        resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> កំពុងផ្ញើ...';
        resetBtn.disabled = true;

        // Simulate sending reset code (replace with actual API call)
        setTimeout(() => {
            // Generate a random 6-digit reset code
            const resetCode = Math.floor(100000 + Math.random() * 900000);

            // Store the code temporarily (in a real app, this would be sent via email/SMS)
            sessionStorage.setItem('resetCode', resetCode.toString());
            sessionStorage.setItem('resetUsername', username);

            showForgotPasswordMessage('លេខកូដកំណត់ឡើងវិញត្រូវបានផ្ញើទៅកាន់អ៊ីមែលរបស់អ្នក (សម្រាប់សាកល្បង: ' + resetCode + ')', 'success');

            // Show the code entry section
            resetCodeSection.style.display = 'block';
            resetBtn.innerHTML = originalText;
            resetBtn.disabled = false;

            // Scroll to code section
            resetCodeSection.scrollIntoView({ behavior: 'smooth' });
        }, 2000);
    });

    // Handle password reset confirmation
    document.getElementById('confirmResetBtn').addEventListener('click', function() {
        const enteredCode = document.getElementById('resetCode').value.trim();
        const newPassword = document.getElementById('newPassword').value.trim();
        const storedCode = sessionStorage.getItem('resetCode');
        const storedUsername = sessionStorage.getItem('resetUsername');

        if (!enteredCode || !newPassword) {
            showForgotPasswordMessage('សូមបំពេញលេខកូដ និងពាក្យសម្ងាត់ថ្មី', 'danger');
            return;
        }

        if (enteredCode !== storedCode) {
            showForgotPasswordMessage('លេខកូដមិនត្រឹមត្រូវទេ', 'danger');
            return;
        }

        if (newPassword.length < 6) {
            showForgotPasswordMessage('ពាក្យសម្ងាត់ត្រូវមានយ៉ាងហោច 6 តួអក្សរ', 'danger');
            return;
        }

        // Show loading state
        const confirmBtn = document.getElementById('confirmResetBtn');
        const originalText = confirmBtn.innerHTML;
        confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> កំពុងកំណត់ឡើងវិញ...';
        confirmBtn.disabled = true;

        // Simulate password reset (replace with actual API call)
        setTimeout(() => {
            showForgotPasswordMessage('ពាក្យសម្ងាត់ត្រូវបានកំណត់ឡើងវិញដោយជោគជ័យ! អ្នកអាចចូលប្រព័ន្ធបានឥឡូវនេះ។', 'success');

            // Clear stored data
            sessionStorage.removeItem('resetCode');
            sessionStorage.removeItem('resetUsername');

            // Close modal after 3 seconds
            setTimeout(() => {
                forgotPasswordModal.hide();
                // Reset form
                forgotPasswordForm.reset();
                resetCodeSection.style.display = 'none';
                forgotPasswordMessage.style.display = 'none';
            }, 3000);

            confirmBtn.innerHTML = originalText;
            confirmBtn.disabled = false;
        }, 2000);
    });

    function showForgotPasswordMessage(message, type) {
        forgotPasswordMessage.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
        forgotPasswordMessage.style.display = 'block';
    }
})();
</script>


</body>
</html>

