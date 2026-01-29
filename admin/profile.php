<?php
//======================================================================
// BLOCK 1: PHP LOGIC (ការគ្រប់គ្រង PHP ទាំងអស់)
//======================================================================

// ចាប់ផ្តើម Session
session_start();

// 1.1 ភ្ជាប់ទៅកាន់ DATABASE
require 'includes/db.php';

// 1.2 ប្រកាសអថេរដំបូង
$login_error = '';
$user_data = null;
$requests_data = [];
$total_requests = 0;
$chart_labels = [];
$chart_data_values = [];
$unwanted_fields = ['username', 'password', 'name', 'last_leave_reset_year', 'gender', 'allowed_menus', 'base_salary']; // Fields ដែលមិនចង់បង្ហាញ
$unwanted_fields[] = 'totp_secret';
$unwanted_fields[] = 'totp_enabled';

$totp_message = '';
$totp_error = '';
$totp_setup_secret = $_SESSION['totp_setup_secret'] ?? null;
$totp_setup_uri = '';
$totp_setup_qr = '';
$totp_enabled = false;
$totp_secret = null;
$has_totp_columns = false;

// 1.3 ការចាកចេញពីប្រព័ន្ធ (Logout)
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_unset();
    session_destroy();
    header("Location: profile.php");
    exit();
}

// 1.4 ការចូលสู่ប្រព័ន្ធ (Login)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username_login'])) {
    try {
        $username = $_POST['username_login'];
        $password = $_POST['password_login'];

        $sql = "SELECT id, username, password, full_name, position, image_url FROM users WHERE username = :username";
        $stmt = $conn->prepare($sql);
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        // សុវត្ថិភាព៖ នៅក្នុងប្រព័ន្ធពិតប្រាកដ គួរតែប្រើ password_verify() ជំនួសឱ្យការប្រៀបធៀបដោយផ្ទាល់
        // ឧទាហរណ៍៖ if ($user && password_verify($password, $user['password']))
        if ($user && $password === $user['password']) { // ការប្រើ === ល្អជាង ==
            $_SESSION['user_id'] = $user['id'];
            header("Location: profile.php");
            exit();
        } else {
            $login_error = "ឈ្មោះអ្នកប្រើប្រាស់ ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវទេ!";
        }
    } catch (PDOException $e) {
        $login_error = "មានបញ្ហាក្នុងការតភ្ជាប់ សូមព្យាយាមម្តងទៀត";
        error_log("Login Error: " . $e->getMessage()); // កត់ត្រាបញ្ហាសម្រាប់អ្នកអភិវឌ្ឍន៍
    }
}

// 1.5 ទាញទិន្នន័យអ្នកប្រើប្រាស់ និងទិន្នន័យសំណើ (ប្រសិនបើបានចូល)
if (isset($_SESSION['user_id'])) {
    try {
        $user_id = $_SESSION['user_id'];
        $has_totp_columns = check_totp_columns($conn);

        // ទាញទិន្នន័យអ្នកប្រើប្រាស់
        $stmt_user = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt_user->execute([$user_id]);
        $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            $totp_enabled = $has_totp_columns && !empty($user_data['totp_enabled']);
            $totp_secret = $has_totp_columns ? ($user_data['totp_secret'] ?? null) : null;
            $totp_account_label = $user_data['email'] ?? ($user_data['full_name'] ?? ('user-' . $user_id));

            if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['totp_action'])) {
                if (!$has_totp_columns) {
                    $totp_error = 'សូមបន្ថែម columns totp_secret (VARCHAR) និង totp_enabled (TINYINT) ក្នុងតារាង users មុនបើក 2FA។';
                } else {
                    $action = $_POST['totp_action'];
                    if ($action === 'start_setup') {
                        if ($totp_enabled) {
                            $totp_error = 'អ្នកបានបើក 2FA រួចហើយ។';
                        } else {
                            $totp_setup_secret = generate_totp_secret();
                            $_SESSION['totp_setup_secret'] = $totp_setup_secret;
                            $totp_message = 'បានបង្កើតលេខសម្ងាត់សម្រាប់ 2FA សូមស្កេន QR ហើយបញ្ចូលកូដ។';
                        }
                    } elseif ($action === 'verify_setup') {
                        $code = trim($_POST['totp_code'] ?? '');
                        if (!$totp_setup_secret) {
                            $totp_error = 'មិនមានសម្ងាត់សម្រាប់បើក 2FA ទេ សូមចាប់ផ្តើមម្តងទៀត។';
                        } elseif ($code === '') {
                            $totp_error = 'សូមបញ្ចូល OTP ៦ ខ្ទង់។';
                        } elseif (!verify_totp_code($totp_setup_secret, $code)) {
                            $totp_error = 'OTP មិនត្រឹមត្រូវ។ សូមព្យាយាមម្ដងទៀត។';
                        } else {
                            $stmtUpdate = $conn->prepare("UPDATE users SET totp_secret = ?, totp_enabled = 1 WHERE id = ?");
                            $stmtUpdate->execute([$totp_setup_secret, $user_id]);
                            $totp_enabled = true;
                            $totp_secret = $totp_setup_secret;
                            unset($_SESSION['totp_setup_secret']);
                            $totp_setup_secret = null;
                            $totp_message = 'បានបើក 2FA ដោយជោគជ័យ។';
                        }
                    } elseif ($action === 'cancel_setup') {
                        unset($_SESSION['totp_setup_secret']);
                        $totp_setup_secret = null;
                        $totp_message = 'បានបោះបង់ការកំណត់ 2FA។';
                    } elseif ($action === 'disable_totp') {
                        $stmtDisable = $conn->prepare("UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?");
                        $stmtDisable->execute([$user_id]);
                        $totp_enabled = false;
                        $totp_secret = null;
                        unset($_SESSION['totp_setup_secret']);
                        $totp_setup_secret = null;
                        $totp_message = 'បានបិទ 2FA រួចរាល់។';
                    }
                }
            }

            if ($has_totp_columns && !$totp_enabled && $totp_setup_secret) {
                $issuer = 'HR Management';
                $totp_setup_uri = build_otpauth_uri($issuer, $totp_account_label, $totp_setup_secret);
                $totp_setup_qr = build_qr_url($totp_setup_uri);
            }

            // លុប Fields ដែលមិនចង់បង្ហាញ
            foreach ($unwanted_fields as $field) {
                if (array_key_exists($field, $user_data)) {
                    unset($user_data[$field]);
                }
            }

            // ទាញទិន្នន័យសំណើ
            $stmt_requests = $conn->prepare("SELECT request_type, COUNT(*) as count FROM requests WHERE user_id = ? AND created_at >= '2026-01-01' GROUP BY request_type ORDER BY count DESC");
            $stmt_requests->execute([$user_id]);
            $requests_data = $stmt_requests->fetchAll(PDO::FETCH_ASSOC);

            if ($requests_data) {
                $total_requests = array_sum(array_column($requests_data, 'count'));
                // រៀបចំទិន្នន័យសម្រាប់ Chart
                foreach ($requests_data as $row) {
                    $chart_labels[] = $row['request_type'];
                    $chart_data_values[] = (int)$row['count'];
                }
            }
        } else {
            // ប្រសិនបើ User ID នៅក្នុង Session មិនត្រឹមត្រូវ ให้ออกจากระบบ
            session_unset();
            session_destroy();
            header("Location: profile.php");
            exit();
        }
    } catch (PDOException $e) {
        // បង្ហាញ Error ដែលងាយស្រួលយល់ជាង
        die("មានបញ្ហាក្នុងការទាញទិន្នន័យអ្នកប្រើប្រាស់ សូមទាក់ទងអ្នកគ្រប់គ្រងប្រព័ន្ធ");
    }
}


/**
 * បកប្រែឈ្មោះ Field ជាភាសាខ្មែរ
 * @param string $field ឈ្មោះ Field ពី Database
 * @return string ឈ្មោះ Field ជាភាសាខ្មែរ
 */
function translate_field_name($field) {
    static $translations = null;
    if ($translations === null) {
        $translations = [
            'id' => 'លេខសម្គាល់',
            'email' => 'អ៊ីមែល',
            'role' => 'តួនាទី',
            'created_at' => 'កាលបរិច្ឆេទបង្កើត',
            'image_url' => 'រូបភាពប្រវត្តិរូប',
            'jd_part' => 'ផ្នែក JD',
            'rating' => 'ការវាយតម្លៃ',
            'last_activity' => 'សកម្មភាពចុងក្រោយ',
            'jd_pdf' => 'ឯកសារ JD',
            'full_name' => 'ឈ្មោះពេញ',
            'workflow_pdf' => 'ឯកសារ Workflow',
            'position' => 'តំណែង',
            'status' => 'ស្ថានភាព',
            'annual_leave_balance' => 'ច្បាប់សម្រាកប្រចាំឆ្នាំនៅសល់'
        ];
    }
    return $translations[$field] ?? ucfirst(str_replace('_', ' ', $field));
}

/**
 * បង្កើត HTML សម្រាប់បង្ហាញតម្លៃរបស់ Field នីមួយៗ
 * @param string $field ឈ្មោះ Field
 * @param mixed $value តម្លៃរបស់ Field
 * @return string HTML output
 */
function render_profile_field($field, $value) {
    $field_name_kh = translate_field_name($field);
    $output_value = '';

    if (is_null($value) || $value === '') {
        $output_value = '<span class="value-muted">(មិនមានទិន្នន័យ)</span>';
    } elseif ($field === 'status') {
        $status_class = htmlspecialchars(strtolower($value));
        $status_text = htmlspecialchars(ucfirst($value));
        $output_value = "<span class=\"status {$status_class}\">{$status_text}</span>";
    } elseif (strpos($field, '_pdf') !== false) {
        $output_value = '<a href="' . htmlspecialchars($value) . '" target="_blank" class="pdf-link">បើកមើលឯកសារ <i class="fas fa-external-link-alt"></i></a>';
    } elseif ($field === 'created_at' || $field === 'last_activity') {
        // === កន្លែងដែលបានកែសម្រួល ===
        $output_value = date("d/m/Y h:i A", strtotime($value));
    } elseif ($field === 'annual_leave_balance') {
        $output_value = htmlspecialchars($value) . ' ថ្ងៃ';
    } else {
        $output_value = htmlspecialchars($value);
    }
    
    return "<div class=\"detail-item\"><strong>{$field_name_kh}</strong><span>{$output_value}</span></div>";
}

// === 2FA (TOTP) helper functions ===
function normalize_totp_secret($secret)
{
    if (!is_string($secret)) {
        return '';
    }

    $clean = preg_replace('/[^A-Z2-7]/i', '', $secret);
    return strtoupper($clean);
}

function base32_decode_custom($b32)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper($b32);
    $b32 = rtrim($b32, '=');
    $bits = '';

    for ($i = 0, $len = strlen($b32); $i < $len; $i++) {
        $val = strpos($alphabet, $b32[$i]);
        if ($val === false) {
            return false;
        }
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }

    $binary = '';
    for ($i = 0, $len = strlen($bits); $i + 8 <= $len; $i += 8) {
        $binary .= chr(bindec(substr($bits, $i, 8)));
    }

    return $binary;
}

function generate_totp_secret($length = 16)
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $secret;
}

function build_otpauth_uri($issuer, $account, $secret)
{
    $label = rawurlencode($issuer . ':' . $account);
    $issuer_param = rawurlencode($issuer);
    return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer_param}&digits=6&period=30";
}

function build_qr_url($otpauth_uri)
{
    // Use api.qrserver.com to avoid image blocking on some networks
    $encoded = urlencode($otpauth_uri);
    return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={$encoded}";
}

function calculate_totp($secret, $timeSlice = null, $digits = 6, $period = 30)
{
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

function verify_totp_code($secret, $code, $window = 1, $period = 30)
{
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

function check_totp_columns(PDO $conn)
{
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

// Determine current page for active navigation
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $user_data ? 'ប្រវត្តិរូប - ' . htmlspecialchars($user_data['full_name']) : 'ចូលប្រព័ន្ធ'; ?></title>
    
    <!-- Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        /* --- ការកំណត់គ្រឹះ និងអក្សរ --- */
        @import url('https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&family=Kantumruy+Pro:wght@400;600&display=swap');
        
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --light-color: #ffffff;
            --dark-color: #333;
            --grey-color: #f4f7f9;
            --border-color: #e0e6ed;
            --font-family: 'Kantumruy Pro', 'Battambang', sans-serif;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --border-radius: 12px;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--grey-color);
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            margin: 0;
            padding: 25px;
            color: var(--dark-color);
            line-height: 1.6;
        }

        /* --- រចនាសម្ព័ន្ធរួម --- */
        .container {
            background-color: var(--light-color);
            padding: 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            width: 100%;
            max-width: 750px;
            transition: all 0.3s ease-in-out;
        }

        h1, h2, h3 {
            color: var(--secondary-color);
            font-weight: 700;
        }
        
        .block-section {
            margin-bottom: 3rem;
        }
        .block-section h3 {
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.8rem;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        /* --- ទម្រង់ចូលប្រព័ន្ធ --- */
        .login-form { text-align: center; }
        .login-form h2 { font-size: 2rem; margin-bottom: 2rem; }
        .login-form .input-group { margin-bottom: 1.5rem; text-align: left; }
        .login-form label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .login-form input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .login-form input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        .login-form .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background-image: linear-gradient(45deg, #3498db, #2980b9);
            color: var(--light-color);
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .login-form .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }
        .login-form .error {
            color: var(--danger-color);
            background-color: #fde8e6;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border: 1px solid var(--danger-color);
        }

        /* --- ទិដ្ឋភាពប្រវត្តិរូប --- */
        .profile-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .profile-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 1rem;
            border: 5px solid var(--light-color);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .profile-info h1 { margin: 0; font-size: 2.2rem; }
        .profile-info p { margin: 0.25rem 0; color: #7f8c8d; font-size: 1.2rem; }
        
        .detail-item {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 1.5rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
            font-size: 1rem;
        }
        .detail-item:last-child { border-bottom: none; }
        .detail-item strong { color: #555; font-weight: 600; }
        .detail-item span { text-align: left; text-overflow: ellipsis; white-space: nowrap; overflow: hidden; }

        .status { padding: 0.3rem 1rem; border-radius: 20px; color: white; font-size: 0.9rem; font-weight: bold; text-transform: uppercase; }
        .status.active { background-color: var(--success-color); }
        .status.inactive { background-color: var(--danger-color); }
        
        .pdf-link { color: var(--primary-color); text-decoration: none; font-weight: bold; }
        .pdf-link:hover { text-decoration: underline; }
        .value-muted { color: #999; font-style: italic; }
        
        .logout-btn {
            display: block;
            width: fit-content;
            margin: 2.5rem auto 0;
            padding: 12px 30px;
            background-color: var(--danger-color);
            color: var(--light-color);
            text-decoration: none;
            font-weight: bold;
            font-size: 1.1rem;
            border-radius: 8px;
            transition: background-color 0.3s, transform 0.2s;
        }
        .logout-btn:hover {
            background-color: #c0392b;
            transform: translateY(-2px);
        }

        /* --- 2FA card --- */
        .twofa-card {
            background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            position: relative;
            overflow: hidden;
        }

        .twofa-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--success-color));
        }

        .twofa-status {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .twofa-status-label {
            font-weight: 600;
            color: var(--secondary-color);
            font-size: 1rem;
        }

        .badge-on, .badge-off {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .badge-on {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            border: 1px solid rgba(46, 204, 113, 0.3);
        }

        .badge-on::before {
            content: '✓';
            font-size: 1rem;
        }

        .badge-off {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border: 1px solid rgba(231, 76, 60, 0.3);
        }

        .badge-off::before {
            content: '✗';
            font-size: 1rem;
        }

        .twofa-content {
            margin-bottom: 2rem;
        }

        .twofa-icon {
            display: inline-block;
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary-color), var(--success-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 16px rgba(52, 152, 219, 0.3);
        }

        .twofa-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 0.5rem;
        }

        .twofa-note {
            font-size: 0.95rem;
            color: #666;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .twofa-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 1.5rem;
            justify-content: flex-start;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            min-height: 48px;
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
            background: linear-gradient(135deg, var(--primary-color), #2980b9);
            color: white;
            box-shadow: 0 4px 16px rgba(52, 152, 219, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(52, 152, 219, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            box-shadow: 0 4px 16px rgba(231, 76, 60, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(231, 76, 60, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
            box-shadow: 0 4px 16px rgba(127, 140, 141, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(127, 140, 141, 0.4);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }

        .disable-2fa-section {
            background: linear-gradient(135deg, #fff5f5 0%, #fef2f2 100%);
            border: 2px solid #fecaca;
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            position: relative;
        }

        .disable-2fa-section::before {
            content: '⚠️';
            position: absolute;
            top: -12px;
            left: 20px;
            background: white;
            padding: 4px 8px;
            border-radius: 50%;
            border: 2px solid #fecaca;
            font-size: 1.2rem;
        }

        .disable-2fa-title {
            color: #dc2626;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .disable-2fa-note {
            color: #991b1b;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .qr-box {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 2rem;
            padding: 1.5rem;
            border: 2px dashed var(--border-color);
            border-radius: 12px;
            background: linear-gradient(135deg, #f8fbff 0%, #ffffff 100%);
            position: relative;
        }

        .qr-box::before {
            content: '📱';
            position: absolute;
            top: -15px;
            left: 20px;
            background: white;
            padding: 6px 10px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            font-size: 1.2rem;
        }

        .qr-box img {
            width: 160px;
            height: 160px;
            border-radius: 12px;
            border: 3px solid var(--border-color);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .secret-text {
            font-family: "Courier New", monospace;
            font-weight: 700;
            letter-spacing: 2px;
            color: var(--secondary-color);
            background: rgba(0, 0, 0, 0.05);
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            font-size: 1.1rem;
            word-break: break-all;
            margin: 8px 0;
        }

        .alert-info-box, .alert-error-box {
            padding: 12px 14px;
            border-radius: 10px;
            margin: 8px 0;
        }

        .alert-info-box {
            background: #eef6ff;
            border: 1px solid rgba(52, 152, 219, 0.35);
            color: #2c3e50;
        }

        .alert-error-box {
            background: #fdecea;
            border: 1px solid rgba(231, 76, 60, 0.35);
            color: #c0392b;
        }

        /* --- Card & Chart --- */
        .card-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .summary-card {
            background-color: #f8f9fa;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .summary-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
        .card-count { font-size: 2.8rem; font-weight: 700; color: var(--primary-color); }
        .card-type { font-size: 1rem; color: #555; margin-top: 5px; }
        .chart-container { width: 100%; max-width: 400px; margin: 2rem auto; }
        .no-data { text-align: center; padding: 2rem; background-color: #f9f9f9; border-radius: 8px; color: #777; font-style: italic; }

        .bottom-nav {
            display: none; /* Hide by default */
        }

        /* === MOBILE RESPONSIVE === */
        @media (max-width: 768px) {
            body {
                padding-bottom: 120px;
            }

            .bottom-nav {
                display: flex;
                position: fixed !important;
                bottom: 0 !important;
                left: 0 !important;
                right: 0 !important;
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                justify-content: space-around;
                padding: 8px 0;
                box-shadow: 0 -2px 16px rgba(0, 0, 0, 0.1);
                z-index: 1000;
                border-top-left-radius: 20px;
                border-top-right-radius: 20px;
                border: 1px solid rgba(255, 255, 255, 0.2);
                min-height: 64px;
            }

            .nav-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-decoration: none;
                color: #6b7280;
                font-size: 0.75rem;
                font-weight: 600;
                transition: all 0.2s ease;
                padding: 6px 8px;
                border-radius: 12px;
                min-width: 60px;
                min-height: 48px;
                flex: 1;
                max-width: 80px;
            }

            .nav-item.active {
                color: #6366f1;
                background: rgba(99, 102, 241, 0.1);
                transform: scale(1.05);
            }

            .nav-item:hover {
                color: #6366f1;
                background: rgba(99, 102, 241, 0.05);
            }

            .nav-icon {
                font-size: 1.3rem;
                margin-bottom: 2px;
            }

            /* 2FA Mobile Styles */
            .twofa-card {
                padding: 1.5rem;
            }

            .twofa-status {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .twofa-actions {
                flex-direction: column;
                gap: 8px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .qr-box {
                flex-direction: column;
                text-align: center;
                gap: 16px;
            }

            .qr-box img {
                width: 140px;
                height: 140px;
            }

            .disable-2fa-section {
                padding: 1rem;
            }
        }

    </style>
</head>
<body>
    <div class="container">

        <?php if ($user_data): ?>
        <!--======================================================================-->
        <!-- BLOCK 2: ការបង្ហាញប្រវត្តិរូប (បង្ហាញនៅពេលចូលរួច)                      -->
        <!--======================================================================-->
        <div class="profile-view">
            
            <div class="profile-header">
                <img src="<?php echo !empty($user_data['image_url']) ? htmlspecialchars($user_data['image_url']) : 'images/default-avatar.png'; ?>" alt="រូបភាពប្រវត្តិរូប" class="profile-img">
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($user_data['full_name'] ?? 'N/A'); ?></h1>
                    <p><?php echo htmlspecialchars($user_data['position'] ?? 'N/A'); ?></p>
                </div>
            </div>

            <div class="block-section" id="personal-info">
                <h3>ព័ត៌មានលម្អិតផ្ទាល់ខ្លួន</h3>
                <div class="profile-details">
                    <?php
                    // render_profile_field ដើម្បីឱ្យកូដស្អាត
                    foreach ($user_data as $field => $value) {
                        if (in_array($field, ['full_name', 'position', 'image_url'])) continue;
                        echo render_profile_field($field, $value);
                    }
                    ?>
                </div>
            </div>

            <div class="block-section" id="twofa-section">
                <h3>ការពារ 2FA (Google Authenticator)</h3>
                <?php if (!$has_totp_columns): ?>
                    <div class="alert-error-box">តារាង users មិនទាន់មាន column <strong>totp_secret</strong> និង <strong>totp_enabled</strong> ទេ។ សូមបន្ថែមវា (VARCHAR(64), TINYINT(1)) មុននឹងបើក 2FA។</div>
                <?php else: ?>
                    <?php if (!empty($totp_message)): ?><div class="alert-info-box"><?php echo htmlspecialchars($totp_message); ?></div><?php endif; ?>
                    <?php if (!empty($totp_error)): ?><div class="alert-error-box"><?php echo htmlspecialchars($totp_error); ?></div><?php endif; ?>

                    <div class="twofa-card">
                        <div class="twofa-status">
                            <span class="twofa-status-label">ស្ថានភាព 2FA:</span>
                            <?php if ($totp_enabled): ?>
                                <span class="badge-on">បានបើក - មានសុវត្ថិភាព</span>
                            <?php else: ?>
                                <span class="badge-off">មិនទាន់បើក - គ្មានការពារ</span>
                            <?php endif; ?>
                        </div>

                        <?php if (!$totp_enabled): ?>
                            <div class="twofa-content">
                                <div class="twofa-icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="twofa-title">បើកការពារ 2FA</div>
                                <p class="twofa-note">បន្ថែមស្រទាប់សុវត្ថិភាពបន្ថែមដោយប្រើកូដ OTP ពីកម្មវិធី Google Authenticator, Microsoft Authenticator ឬ Authy។</p>
                                <form method="post" class="twofa-actions" style="margin-top: 0;">
                                    <input type="hidden" name="totp_action" value="start_setup">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-lock"></i> ចាប់ផ្តើមបើក 2FA
                                    </button>
                                </form>
                            </div>

                            <?php if ($totp_setup_secret && $totp_setup_qr): ?>
                                <div class="qr-box">
                                    <img src="<?php echo htmlspecialchars($totp_setup_qr); ?>" alt="QR for 2FA" onerror="this.style.display='none';">
                                    <div>
                                        <div class="twofa-note" style="margin-bottom: 8px;"><strong>ជំហានទី 1:</strong> ស្កេន QR Code ដោយកម្មវិធី Authenticator</div>
                                        <div class="twofa-note" style="margin-bottom: 8px;"><strong>ជំហានទី 2:</strong> ឬបញ្ចូលសម្ងាត់ខាងក្រោមដោយដៃ:</div>
                                        <div class="secret-text"><?php echo htmlspecialchars($totp_setup_secret); ?></div>
                                        <div class="twofa-note" style="margin-top:8px;"><strong>គណនី:</strong> <?php echo htmlspecialchars($totp_account_label); ?></div>
                                    </div>
                                </div>

                                <form method="post" class="twofa-actions">
                                    <input type="text" name="totp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="បញ្ចូល OTP ៦ ខ្ទង់" required style="padding:12px;border:2px solid var(--border-color);border-radius:8px;font-size:1rem;width:200px;">
                                    <button type="submit" name="totp_action" value="verify_setup" class="btn btn-primary">
                                        <i class="fas fa-check"></i> បញ្ជាក់កូដ
                                    </button>
                                    <button type="submit" name="totp_action" value="cancel_setup" class="btn btn-secondary" formnovalidate>
                                        <i class="fas fa-times"></i> បោះបង់
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="twofa-content">
                                <div class="twofa-icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="twofa-title">2FA បានបើកដំណើរការ</div>
                                <p class="twofa-note">គណនីរបស់អ្នកត្រូវបានការពារដោយ 2FA។ អ្នកត្រូវបញ្ចូលកូដពី Google Authenticator នៅពេលចូលប្រព័ន្ធ។</p>
                            </div>

                            <div class="disable-2fa-section">
                                <div class="disable-2fa-title">បិទការពារ 2FA</div>
                                <div class="disable-2fa-note">
                                    ការបិទ 2FA នឹងធ្វើឱ្យគណនីរបស់អ្នកមានហានិភ័យកាន់តែខ្ពស់។ ត្រូវប្រាកដថាអ្នកយល់ពីផលវិបាកមុននឹងបិទ។
                                </div>
                                <form method="post" class="twofa-actions" onsubmit="return confirm('⚠️ ការព្រមានសំខាន់! ⚠️\n\nតើអ្នកពិតជាប្រាកដថាចង់បិទការពារ 2FA ទេ?\n\nការបិទ 2FA នឹងធ្វើឱ្យគណនីរបស់អ្នកងាយរងការវាយប្រហារ។ យើងខ្ញុំសូមណែនាំឱ្យរក្សាការពារ 2FA បើកដំណើរការ។');" style="margin-top: 0;">
                                    <input type="hidden" name="totp_action" value="disable_totp">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-exclamation-triangle"></i> បិទ 2FA (មិនណែនាំ)
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>


            <div class="block-section" id="requests-summary">
                <h3>សេចក្តីសង្ខេបនៃសំណើ (សរុប: <?php echo $total_requests; ?>)</h3>
                <?php if (!empty($requests_data)): ?>
                    <div class="card-container">
                        <?php foreach ($requests_data as $request): ?>
                            <div class="summary-card">
                                <div class="card-count"><?php echo htmlspecialchars($request['count']); ?></div>
                                <div class="card-type"><?php echo htmlspecialchars($request['request_type']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="chart-container">
                        <canvas id="requestsChart"></canvas>
                    </div>
                <?php else: ?>
                    <p class="no-data">មិនទាន់មានទិន្នន័យស្នើសុំនៅឡើយទេ។</p>
                <?php endif; ?>
            </div>
            
            <a href="?action=logout" class="logout-btn">ចាកចេញពីប្រព័ន្ធ</a>
        </div>

        <?php else: ?>
        <!--======================================================================-->
        <!-- BLOCK 3: ទម្រង់ចូលប្រព័ន្ធ (បង្ហាញនៅពេលមិនទាន់ចូល)                      -->
        <!--======================================================================-->
        <div class="login-form">
            <h2>ចូលទៅកាន់គណនី</h2>
            <?php if ($login_error): ?><p class="error"><?php echo $login_error; ?></p><?php endif; ?>
            <form method="post" action="profile.php" novalidate>
                <div class="input-group">
                    <label for="username_login">ឈ្មោះអ្នកប្រើប្រាស់:</label>
                    <input type="text" id="username_login" name="username_login" required>
                </div>
                <div class="input-group">
                    <label for="password_login">ពាក្យសម្ងាត់:</label>
                    <input type="password" id="password_login" name="password_login" required>
                </div>
                <button type="submit" class="btn">ចូលប្រព័ន្ធ</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($user_data && !empty($requests_data)): ?>
    <!--======================================================================-->
    <!-- BLOCK 4: JAVASCRIPT សម្រាប់ CHART (ដំណើរការនៅពេលមានទិន្នន័យ)                 -->
    <!--======================================================================-->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('requestsChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        label: 'ចំនួនសំណើ',
                        data: <?php echo json_encode($chart_data_values); ?>,
                        backgroundColor: ['#3498db', '#e74c3c', '#f1c40f', '#2ecc71', '#9b59b6', '#1abc9c', '#e67e22', '#34495e'],
                        borderColor: '#ffffff',
                        borderWidth: 3,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: {
                                    family: "'Kantumruy Pro', 'Battambang', sans-serif",
                                    size: 14
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: 'សមាមាត្រនៃប្រភេទសំណើ',
                            padding: { top: 10, bottom: 20 },
                            font: {
                                size: 18,
                                family: "'Kantumruy Pro', 'Battambang', sans-serif",
                                weight: 'bold'
                            }
                        },
                        tooltip: {
                            bodyFont: { family: "'Kantumruy Pro', 'Battambang', sans-serif" },
                            titleFont: { family: "'Kantumruy Pro', 'Battambang', sans-serif" }
                        }
                    }
                }
            });
        });
    </script>
    <?php endif; ?>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="../homes.php" class="nav-item <?php echo $current_page === '../homes.php' ? 'active' : ''; ?>">
            <i class="fas fa-home nav-icon"></i>
            <span>ទំព័រដើម</span>
        </a>
        <a href="../checklist.php" class="nav-item <?php echo $current_page === '../checklist.php' ? 'active' : ''; ?>">
            <i class="fas fa-tasks nav-icon"></i>
            <span>ការងារ</span>
        </a>
        <a href="../announcements.php" class="nav-item <?php echo $current_page === '../announcements.php' ? 'active' : ''; ?>">
            <i class="fas fa-bell nav-icon"></i>
            <span>ដំណឹង</span>
        </a>
        <a href="../profile.php" class="nav-item <?php echo $current_page === '../profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user nav-icon"></i>
            <span>គណនី</span>
        </a>
    </nav>

</body>
</html>