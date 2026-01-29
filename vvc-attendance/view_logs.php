<?php
session_start();

// ===============================================
//            PART 1: CONFIGURATION
// ===============================================

// ENHANCEMENT: Activate error reporting for debugging
date_default_timezone_set('Asia/Phnom_Penh');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Force UTF-8 everywhere so Khmer text renders correctly
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) { mb_internal_encoding('UTF-8'); }
if (function_exists('mb_http_output')) { mb_http_output('UTF-8'); }
// Send explicit charset header for browsers
header('Content-Type: text/html; charset=UTF-8');

// !! ត្រូវប្រាកដថាអ្នកបានប្តូរតម្លៃ DB_PASSWORD, TELEGRAM_BOT_TOKEN, និង TELEGRAM_CHAT_ID ឱ្យបានត្រឹមត្រូវ !!
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'samann1_attendance_db');
define('DB_PASSWORD', 'attendance@2025'); // <-- ត្រូវប្តូរ
define('DB_NAME', 'samann1_attendance_db');

// Check if user is logged in
if (!isset($_SESSION['employee_id'])) {
    header("location: scan.php");
    exit;
}

$employee_id = $_SESSION['employee_id'];
$log_data = [];
$error_message = '';
$user_name = '';

// Determine the date to display (Default to today)
$selected_date = date('Y-m-d');
if (isset($_POST['selected_date']) && !empty($_POST['selected_date'])) {
    // Validate date format before setting
    if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $_POST['selected_date'])) {
        $selected_date = $_POST['selected_date'];
    }
}

// ===============================================
//            PART 2: DATABASE CONNECTION
// ===============================================

$mysqli = @new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_error) {
    die("Database Connection Failed: Please check configuration. Error: " . $mysqli->connect_error); 
}

// IMPORTANT: ensure connection speaks utf8mb4 for Khmer
@$mysqli->set_charset('utf8mb4');
@$mysqli->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

// ===============================================
//            PART 3: FETCH USER DATA AND LOGS
// ===============================================

// 1. Fetch user data for display (Name) - ធានាថាបង្ហាញតែឈ្មោះបុគ្គលិកផ្ទាល់ខ្លួន
$name_sql = "SELECT name FROM users WHERE employee_id = ?";
if ($stmt_name = $mysqli->prepare($name_sql)) {
    $stmt_name->bind_param("s", $employee_id);
    $stmt_name->execute();
    $result_name = $stmt_name->get_result();
    if ($result_name->num_rows == 1) {
        $user_data = $result_name->fetch_assoc();
        $user_name = $user_data['name'];
    }
    $stmt_name->close();
}


// 2. Fetch Attendance Logs filtered by employee_id AND selected_date
// ប្រើ DATE(log_datetime) = ? ដើម្បី Filter តាមថ្ងៃ
$sql = "SELECT log_datetime, action_type, location_name, status 
        FROM checkin_logs 
        WHERE employee_id = ? AND DATE(log_datetime) = ?
        ORDER BY log_datetime DESC";

if ($stmt = $mysqli->prepare($sql)) {
    // ត្រូវ Bind ទាំង employee_id និង selected_date
    $stmt->bind_param("ss", $employee_id, $selected_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $log_data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $error_message = "Database Query Error: " . $mysqli->error;
    error_log("VIEW_LOGS DB ERROR: " . $mysqli->error);
}

$mysqli->close();
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>កំណត់ត្រាវត្តមាន - <?php echo htmlspecialchars($user_name ?: $employee_id); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        :root {
            --primary-color: #007aff; --primary-color-dark: #005ecb; 
            --secondary-color: #f2f2f7; --success-color: #34c759; 
            --error-color: #ff3b30; --warning-color: #ff9500; 
            --background-color: #f2f2f7; --surface-color: #ffffff;
            --text-primary: #1c1c1e; --text-secondary: #8a8a8e; 
            --border-radius-s: 8px; --border-radius-m: 16px; --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.06);
        }
        * { box-sizing: border-box; }
        body { font-family: 'Kantumruy Pro', sans-serif; margin: 0; padding: 0; background-color: var(--background-color); color: var(--text-primary); }
        .mobile-body { max-width: 500px; margin: 0 auto; min-height: 100vh; background-color: var(--surface-color); box-shadow: 0 0 40px rgba(0, 0, 0, 0.08); padding-bottom: 20px; }
        .app-header { background-color: var(--surface-color); padding: 16px 20px; border-bottom: 1px solid #e5e5ea; position: sticky; top: 0; z-index: 10; }
        .header-content { display: flex; justify-content: space-between; align-items: center; }
        .header-title { margin: 0; font-size: 1.15em; font-weight: 600; }
        .back-button { color: var(--primary-color); text-decoration: none; font-size: 1em; font-weight: 500; }
        .app-main { padding: 20px; }
        h2 { font-size: 1.8em; font-weight: 700; margin: 0 0 10px 0; }
        .info-card { background-color: var(--secondary-color); padding: 15px; border-radius: var(--border-radius-m); margin-bottom: 20px; font-size: 0.9em; }
        .info-card p { margin: 5px 0; }
        .info-card strong { color: var(--primary-color); }
        
        /* Date Picker Styling */
        .date-filter-form { display: flex; gap: 10px; margin-bottom: 20px; }
        .date-filter-form .mobile-input { flex-grow: 1; height: 48px; padding: 0 10px; border: 1px solid #e0e0e0; border-radius: var(--border-radius-s); font-family: 'Kantumruy Pro', sans-serif; line-height: 46px; }
        .date-filter-form button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius-s);
            padding: 0 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .date-filter-form button:active { background-color: var(--primary-color-dark); }

        .log-table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .log-table { width: 100%; border-collapse: collapse; min-width: 350px; } 
        .log-table th, .log-table td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid #e5e5ea;
            font-size: 0.85em;
        }
        .log-table th { background-color: var(--secondary-color); font-weight: 600; color: var(--text-primary); }
        .log-table tbody tr:last-child td { border-bottom: none; }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
        }
        .status-Good { background-color: #d1e7dd; color: var(--success-color); }
        .status-Late { background-color: #f8d7da; color: var(--error-color); }
        .status-Absent { background-color: #fff3cd; color: var(--warning-color); }
        .status-Failed { background-color: #e9e9e9; color: var(--text-secondary); }
    </style>
</head>
<body class="mobile-body">
    <header class="app-header">
        <div class="header-content">
            <a href="scan.php" class="back-button"><i class="fas fa-arrow-left"></i> ត្រឡប់ក្រោយ</a>
            <h1 class="header-title">កំណត់ត្រាវត្តមាន</h1>
            <a href="scan.php?logout=true" style="color: var(--error-color); font-size: 0.9em; text-decoration: none;"><i class="fas fa-sign-out-alt"></i> ចេញ</a>
        </div>
    </header>
    <main class="app-main">
        <h2>កំណត់ត្រាវត្តមានបុគ្គលិក</h2>
        <div class="info-card">
            <p>ឈ្មោះ៖ <strong><?php echo htmlspecialchars($user_name ?: 'N/A'); ?></strong></p>
            <p>អត្តលេខ៖ <strong><?php echo htmlspecialchars($employee_id); ?></strong></p>
            <p>កំណត់ត្រាសម្រាប់ថ្ងៃ៖ <strong><?php echo date('d-M-Y', strtotime($selected_date)); ?></strong></p>
        </div>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="date-filter-form">
            <input 
                type="date" 
                name="selected_date" 
                class="mobile-input" 
                value="<?php echo htmlspecialchars($selected_date); ?>" 
                required
            >
            <button type="submit"><i class="fas fa-search"></i> មើល</button>
        </form>

        <?php if ($error_message): ?>
            <div style="padding: 15px; background-color: var(--error-color); color: white; border-radius: var(--border-radius-m); margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> កំហុស: <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($log_data)): ?>
            <div style="padding: 30px; text-align: center; color: var(--text-secondary); background-color: var(--secondary-color); border-radius: var(--border-radius-m);">
                <i class="fas fa-calendar-times" style="font-size: 2em; margin-bottom: 10px; display: block;"></i>
                គ្មានកំណត់ត្រាវត្តមានសម្រាប់ថ្ងៃនេះទេ។
            </div>
        <?php else: ?>
            <div class="log-table-container">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>ម៉ោង</th>
                            <th>សកម្មភាព</th>
                            <th>ទីតាំង</th>
                            <th>ស្ថានភាព</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($log_data as $log): ?>
                            <?php 
                                $status = htmlspecialchars($log['status'] ?? 'Failed');
                                $status_class = 'status-' . str_replace(['/', ' '], ['-', ''], $status);
                            ?>
                            <tr>
                                <td><?php echo date('h:i A', strtotime($log['log_datetime'])); ?></td>
                                <td><?php echo htmlspecialchars($log['action_type']); ?></td>
                                <td><?php echo htmlspecialchars($log['location_name'] ?? 'N/A'); ?></td>
                                <td><span class="status-badge <?php echo $status_class; ?>"><?php echo $status; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>