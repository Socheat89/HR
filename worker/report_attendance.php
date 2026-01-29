<?php
// --- START: CONFIGURATION & DATA FETCHING ---
define('BYPASS_AUTH', true); 
session_start();

// Determine which report to display
$report_type = $_GET['report_type'] ?? 'late'; // 'late' or 'forget'

// Common filters from URL
$startDate = $_GET['startDate'] ?? date('Y-m-01');
$endDate = $_GET['endDate'] ?? date('Y-m-t');
$employeeType = $_GET['employee_type'] ?? 'all';

function validateDate($date, $format = 'Y-m-d') { 
    $d = DateTime::createFromFormat($format, $date); 
    return $d && $d->format($format) === $date; 
}

if (!validateDate($startDate) || !validateDate($endDate)) { 
    $startDate = date('Y-m-01'); 
    $endDate = date('Y-m-t'); 
}

$is_sub_user = !BYPASS_AUTH && isset($_SESSION['sub_user_logged_in']) && $_SESSION['sub_user_logged_in'];
$branch_filter = $is_sub_user ? ($_SESSION['branch'] ?? null) : null;
session_write_close();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// --- START: SECURE TELEGRAM SENDING LOGIC (MODIFIED FOR DYNAMIC GROUPS) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_telegram_group') {
    header('Content-Type: application/json');
    try {
        // --- START: MODIFICATION 1 ---
        // For best practice, move these credentials to a secure configuration file
        $botToken = '8099151515:AAH8QLSSdnPJKFq-nLuR1zIH-JfYpzirsag';
        
        // Define Chat IDs for your two groups
        // ** សូមប្តូរលេខ ID ខាងក្រោមនេះ ទៅតាម Group ជាក់ស្តែងរបស់អ្នក **
        $chatIdSkilled = '-1001167276739'; // Group ទី១ សម្រាប់ "បុគ្គលិកជំនាញ"
        $chatIdWorker  = '-1001714010520'; // Group ទី២ សម្រាប់ "បុគ្គលិកកម្មករ" (សូមដាក់ ID ពិត)

        // Get the employee type sent from the JavaScript
        $selectedEmployeeType = $_POST['employee_type'] ?? 'all';

        // Determine the correct Chat ID based on the selection
        $chatId = null;
        if ($selectedEmployeeType === 'skilled') {
            $chatId = $chatIdSkilled;
        } elseif ($selectedEmployeeType === 'worker') {
            $chatId = $chatIdWorker;
        }

        // If no specific group is selected, stop and return an error
        if ($chatId === null) {
            throw new Exception('សូមជ្រើសរើសប្រភេទបុគ្គលិក (ជំនាញ ឬ កម្មករ) ដើម្បីផ្ញើរបាយការណ៍។');
        }
        // --- END: MODIFICATION 1 ---

        if (!isset($_FILES['photos']) || !isset($_POST['caption'])) {
            throw new Exception('Missing photos or caption.');
        }

        $caption = $_POST['caption'];
        $files = $_FILES['photos'];
        
        $media = [];
        // Use the dynamic $chatId determined above
        $post_fields = ['chat_id' => $chatId]; 
        
        // Prepare media array and attach files for cURL
        for ($i = 0; $i < count($files['tmp_name']); $i++) {
            $unique_key = 'photo' . $i;
            $media_item = [
                'type' => 'photo',
                'media' => 'attach://' . $unique_key,
            ];
            // Add caption to the first photo only
            if ($i === 0) {
                $media_item['caption'] = $caption;
            }
            $media[] = $media_item;
            
            $post_fields[$unique_key] = new CURLFile($files['tmp_name'][$i], $files['type'][$i], $files['name'][$i]);
        }
        
        $post_fields['media'] = json_encode($media);
        
        $url = "https://api.telegram.org/bot{$botToken}/sendMediaGroup";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: multipart/form-data"]);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        
        $output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpcode !== 200) {
            $decoded_output = json_decode($output, true);
            $error_message = $decoded_output['description'] ?? $error ?: 'Unknown API error';
            throw new Exception("Telegram API Error (HTTP {$httpcode}): {$error_message}");
        }

        echo $output;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'description' => $e->getMessage()]);
    }
    exit;
}
// --- END: SECURE TELEGRAM SENDING LOGIC ---

// --- DATABASE CONNECTION & DATA FETCHING FOR BOTH REPORTS ---
// ... (The rest of your PHP code for database fetching remains unchanged) ...
try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db;charset=utf8mb4", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

  // --- 1. DATA FOR LATE REPORT (កូដកែសម្រួលថ្មី និងត្រឹមត្រូវ) ---
$sql_late = "
    WITH RankedScans AS (
        SELECT *, ROW_NUMBER() OVER (PARTITION BY user_id, DATE(timestamp) ORDER BY timestamp ASC) AS scan_rank
        FROM scan_logs WHERE timestamp BETWEEN ? AND ?
    )
    SELECT 
        rs.user_id, 
        rs.username, 
        u.gender, 
        u.position,
        
        -- Late under 15 minutes (Morning OR Afternoon OR Evening)
        COUNT(CASE 
            WHEN LOWER(rs.status) LIKE '%late%' AND (
                -- Morning Late: Before 12:00 PM
                (
                    TIME(rs.timestamp) < '12:00:00' AND
                    TIME(rs.timestamp) > (CASE WHEN rs.folder IN ('កម្មករ', 'ហាងទំនិញ៣១៨', 'ឃ្លាំង', 'SK Chuk Meas') THEN '07:30:00' ELSE '08:00:00' END) AND
                    TIME(rs.timestamp) <= ADDTIME((CASE WHEN rs.folder IN ('កម្មករ', 'ហាងទំនិញ៣១៨', 'ឃ្លាំង', 'SK Chuk Meas') THEN '07:30:00' ELSE '08:00:00' END), '00:15:00')
                )
                OR
                -- Afternoon Late (1 PM Shift): After 12:00 PM but before 4:00 PM
                (
                    TIME(rs.timestamp) >= '12:00:00' AND TIME(rs.timestamp) < '16:00:00' AND
                    TIME(rs.timestamp) > '13:00:00' AND 
                    TIME(rs.timestamp) <= ADDTIME('13:00:00', '00:15:00')
                )
                -- <<<<<<<<<<<<<<<<< START: កែសម្រួលលក្ខខណ្ឌវេនល្ងាច >>>>>>>>>>>>>>>>>
                OR
                -- Evening Late (Lateness starts AFTER 5:15 PM)
                (
                    TIME(rs.timestamp) >= '16:00:00' AND -- Check scans within the evening window
                    TIME(rs.timestamp) > '17:15:00' AND -- Lateness starts from 17:15:01
                    TIME(rs.timestamp) <= ADDTIME('17:15:00', '00:15:00') -- Late for up to 15 mins (i.e., until 17:30:00)
                )
                -- <<<<<<<<<<<<<<<<< END: កែសម្រួលលក្ខខណ្ឌវេនល្ងាច >>>>>>>>>>>>>>>>>
            ) THEN 1 
        END) AS late_under_15,

        -- Late 15+ minutes (Morning OR Afternoon OR Evening)
        COUNT(CASE 
            WHEN LOWER(rs.status) LIKE '%late%' AND (
                -- Morning Late
                (
                    TIME(rs.timestamp) < '12:00:00' AND
                    TIME(rs.timestamp) > ADDTIME((CASE WHEN rs.folder IN ('កម្មករ', 'ហាងទំនិញ៣១៨', 'ឃ្លាំង', 'SK Chuk Meas') THEN '07:30:00' ELSE '08:00:00' END), '00:15:00') AND
                    TIME(rs.timestamp) < ADDTIME((CASE WHEN rs.folder IN ('កម្មករ', 'ហាងទំនិញ៣១៨', 'ឃ្លាំង', 'SK Chuk Meas') THEN '07:30:00' ELSE '08:00:00' END), '01:00:00')
                )
                OR
                -- Afternoon Late
                (
                    TIME(rs.timestamp) >= '12:00:00' AND TIME(rs.timestamp) < '16:00:00' AND
                    TIME(rs.timestamp) > ADDTIME('13:00:00', '00:15:00') AND 
                    TIME(rs.timestamp) < ADDTIME('13:00:00', '01:00:00')
                )
                -- <<<<<<<<<<<<<<<<< START: កែសម្រួលលក្ខខណ្ឌវេនល្ងាច >>>>>>>>>>>>>>>>>
                OR
                -- Evening Late
                (
                    TIME(rs.timestamp) >= '16:00:00' AND
                    TIME(rs.timestamp) > ADDTIME('17:15:00', '00:15:00') AND -- Late more than 15 mins (after 17:30:00)
                    TIME(rs.timestamp) < ADDTIME('17:15:00', '01:00:00')  -- But less than 1 hour (before 18:15:00)
                )
                -- <<<<<<<<<<<<<<<<< END: កែសម្រួលលក្ខខណ្ឌវេនល្ងាច >>>>>>>>>>>>>>>>>
            ) THEN 1 
        END) AS late_15_plus,

        -- Late 1+ hour (Morning OR Afternoon OR Evening)
        COUNT(CASE 
            WHEN LOWER(rs.status) LIKE '%late%' AND (
                -- Morning Late
                (
                    TIME(rs.timestamp) < '12:00:00' AND
                    TIME(rs.timestamp) >= ADDTIME((CASE WHEN rs.folder IN ('កម្មករ', 'ហាងទំនិញ៣១៨', 'ឃ្លាំង', 'SK Chuk Meas') THEN '07:30:00' ELSE '08:00:00' END), '01:00:00')
                )
                OR
                -- Afternoon Late
                (
                    TIME(rs.timestamp) >= '12:00:00' AND TIME(rs.timestamp) < '16:00:00' AND
                    TIME(rs.timestamp) >= ADDTIME('13:00:00', '01:00:00')
                )
                -- <<<<<<<<<<<<<<<<< START: កែសម្រួលលក្ខខណ្ឌវេនល្ងាច >>>>>>>>>>>>>>>>>
                OR
                -- Evening Late
                (
                    TIME(rs.timestamp) >= '16:00:00' AND
                    TIME(rs.timestamp) >= ADDTIME('17:15:00', '01:00:00') -- Late for 1 hour or more (from 18:15:00 onwards)
                )
                -- <<<<<<<<<<<<<<<<< END: កែសម្រួលលក្ខខណ្ឌវេនល្ងាច >>>>>>>>>>>>>>>>>
            ) THEN 1 
        END) AS late_1_hour

    FROM RankedScans AS rs
    LEFT JOIN users AS u ON rs.user_id = u.user_id
    WHERE rs.scan_rank <= 5
";
    
    
    $params_late = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];
    // Apply filters for late report
    $filter_conditions_late = [];
    if ($employeeType === 'skilled') {
        $skilledFolders = ['ឃ្លាំង', 'ជំនាញ', 'ហាងទំនិញ៣១៨', 'SK Chuk Meas'];
        $placeholders = implode(',', array_fill(0, count($skilledFolders), '?'));
        $filter_conditions_late[] = "rs.folder IN ($placeholders)";
        $params_late = array_merge($params_late, $skilledFolders);
    } elseif ($employeeType === 'worker') {
        $filter_conditions_late[] = "rs.folder = ?";
        $params_late[] = 'កម្មករ';
    }
    if ($is_sub_user && $branch_filter) {
        $filter_conditions_late[] = "rs.branch = ?";
        $params_late[] = $branch_filter;
    }
    if (!empty($filter_conditions_late)) { $sql_late .= " AND " . implode(" AND ", $filter_conditions_late); }
    $sql_late .= " GROUP BY rs.user_id, rs.username, u.gender, u.position ORDER BY rs.username ASC";
    $stmt_late = $pdo->prepare($sql_late);
    $stmt_late->execute($params_late);
    $late_users = $stmt_late->fetchAll(PDO::FETCH_ASSOC);

    // Process late report data
    $sumUnder15 = 0; $sum15plus = 0; $sum1hour = 0;
    foreach ($late_users as &$user) {
        $user['total'] = ($user['late_under_15'] ?? 0) + ($user['late_15_plus'] ?? 0) + ($user['late_1_hour'] ?? 0);
        $sumUnder15 += $user['late_under_15'] ?? 0;
        $sum15plus += $user['late_15_plus'] ?? 0;
        $sum1hour += $user['late_1_hour'] ?? 0;
    }
    unset($user);
    $late_sumTotal = $sumUnder15 + $sum15plus + $sum1hour;
    usort($late_users, fn($a, $b) => ($b['total'] <=> $a['total']) ?: strcmp($a['username'], $b['username']));

    // --- 2. DATA FOR FORGET SCAN REPORT ---
    $sql_forget = "
        SELECT AggregatedResults.*, u.gender, u.position
        FROM (
            WITH DailyScanCounts AS (
                SELECT user_id, DATE(timestamp) AS scan_date, MIN(username) AS username, MIN(folder) AS folder, MIN(branch) AS branch,
                    SUM(CASE WHEN action = 'Check-In' THEN 1 ELSE 0 END) AS total_in,
                    SUM(CASE WHEN action = 'Check-Out' THEN 1 ELSE 0 END) AS total_out
                FROM scan_logs
                WHERE timestamp BETWEEN ? AND ? AND action IN ('Check-In', 'Check-Out')
                GROUP BY user_id, DATE(timestamp)
            )
             SELECT dsc.user_id, dsc.username, MIN(dsc.folder) AS folder, MIN(dsc.branch) AS branch,
                /* --- START: NEW FORGET SCAN LOGIC --- */
                /* រាប់ចំនួនភ្លេចស្កេនចូល ដោយយកចំនួនចេញដកចំនួនចូល (បើលទ្ធផល > 0) */
                SUM(GREATEST(0, dsc.total_out - dsc.total_in)) AS forgot_check_in,
                /* រាប់ចំនួនភ្លេចស្កេនចេញ ដោយយកចំនួនចូលដកចំនួនចេញ (បើលទ្ធផល > 0) */
                SUM(GREATEST(0, dsc.total_in - dsc.total_out)) AS forgot_check_out
                /* --- END: NEW FORGET SCAN LOGIC --- */
            FROM DailyScanCounts AS dsc GROUP BY dsc.user_id, dsc.username
        ) AS AggregatedResults
        LEFT JOIN users AS u ON AggregatedResults.user_id = u.user_id
    ";
    
    $params_forget = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];
    // Apply filters for forget report
    $filter_conditions_forget = [];
    if ($employeeType === 'skilled') {
        $skilledFolders = ['ឃ្លាំង', 'ជំនាញ', 'ហាងទំនិញ៣១៨', 'SK Chuk Meas'];
        $placeholders = implode(',', array_fill(0, count($skilledFolders), '?'));
        $filter_conditions_forget[] = "AggregatedResults.folder IN ($placeholders)";
        $params_forget = array_merge($params_forget, $skilledFolders);
    } elseif ($employeeType === 'worker') {
        $filter_conditions_forget[] = "AggregatedResults.folder = ?";
        $params_forget[] = 'កម្មករ';
    }
    if ($is_sub_user && $branch_filter) {
        $filter_conditions_forget[] = "AggregatedResults.branch = ?";
        $params_forget[] = $branch_filter;
    }
    if (!empty($filter_conditions_forget)) { $sql_forget .= " WHERE " . implode(" AND ", $filter_conditions_forget); }
    $sql_forget .= " ORDER BY (AggregatedResults.forgot_check_in + AggregatedResults.forgot_check_out) DESC, AggregatedResults.username ASC";
    $stmt_forget = $pdo->prepare($sql_forget);
    $stmt_forget->execute($params_forget);
    $forget_users = $stmt_forget->fetchAll(PDO::FETCH_ASSOC);

    // Process forget report data
    $sumForgotIn = 0; $sumForgotOut = 0;
    foreach ($forget_users as &$user) {
        $user['total'] = ($user['forgot_check_in'] ?? 0) + ($user['forgot_check_out'] ?? 0);
        $sumForgotIn += $user['forgot_check_in'] ?? 0;
        $sumForgotOut += $user['forgot_check_out'] ?? 0;
    }
    unset($user);
    $forget_sumTotal = $sumForgotIn + $sumForgotOut;

} catch (PDOException $e) { exit('Database Error: ' . $e->getMessage()); } catch (Exception $e) { exit('General Error: ' . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="km">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>របាយការណ៍ប្រចាំថ្ងៃ</title>
  <link rel="stylesheet" href="assets/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    /* ... (All your CSS styles remain unchanged) ... */
    body { font-family: "kh battambang", "Battambang", sans-serif; font-size: 12pt; margin: 0; padding: 0; background: #f5f5f5; }
    .report-page { width: 310mm; min-height: 297mm; padding: 20mm; margin: 0 auto 20px auto; background: white; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
    .report-header img { max-width: 500px; margin-top: -2rem; display: block; margin: auto; }
    .report-title { background-color: #192c4f; color: white; padding: 1px 0; padding-top:10px; text-align: center; margin-top: -1rem; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    .report-title h2 { font-family: Khmer OS Muol Light; font-size: 28px; color: #f1c011; font-weight: 200; }
    .report-title h4 { font-family: Khmer OS Muol Light; color: #f1c011; margin-top: -0.5rem; font-size: 14px; font-weight: 200; }
    .report-table { width: 100%; border-collapse: collapse; margin-top: -1.2rem; }
    .report-table th, .report-table td { border: 1px solid black; font-size: 12pt; text-align: center; border-top: none; padding: 4px; }
    .report-table th { background-color: #f1c011; color: black; font-weight: bold; }
    .highlight-gold td { background-color: #fdea0f !important; font-weight: bold; }
    .highlight-red td { background-color: red !important; font-weight: bold; color: white !important; }
    .report-footer { display: grid; grid-template-columns: repeat(3, 1fr); position: relative; margin-top: 2rem; }
    .report-footer p { text-align: center; width: 300px; margin: 4px 0; font-size: 12px; }
    .filter-container { max-width: 1100px; margin: 28px auto 18px auto; padding: 28px 36px 20px 36px; background: #fff; border-radius: 14px; box-shadow: 0 4px 18px rgba(25,44,79,0.10); border: 1.5px solid #e5e7eb; }
    .filter-container form { display: flex; flex-wrap: wrap; gap: 2rem 1.5rem; align-items: flex-end; justify-content: center; }
    .filter-container label { font-family: 'Khmer OS Muol Light', 'Noto Sans Khmer', sans-serif; font-size: 1.08rem; font-weight: 600; color: #192c4f; margin-bottom: 8px; display: block; }
    .filter-container .form-control, .filter-container .form-select { border: 1.5px solid #cbd5e1; border-radius: 9px; padding: 11px 16px; font-size: 1.01rem; font-family: 'kh battambang', 'Noto Sans Khmer', sans-serif; background: #f8fafc; }
    .filter-container div { flex: 1 1 200px; min-width: 200px; margin-bottom: 0; }
    .report-toggle-buttons { display: flex; gap: 1rem; justify-content: center; margin-bottom: 1rem; }
    .report-toggle-buttons .tg-btn { font-weight: bold; font-size: 14px; padding: 12px 32px; display: inline-block; border-radius: 9px; border: 1.5px solid #d1d5db; background: #fff; color: #192c4f; text-decoration: none; }
    .report-toggle-buttons .tg-btn.active { background:rgb(0, 0, 0); color: #f1c011; border-color:rgb(255, 255, 255); }
    @media print {
      @page { size: A4 portrait; margin: 0; }
      body { background: none; }
      .filter-container, .no-print, .custom-popup-wrapper, .loading-overlay { display: none; }
      .report-page { display: block !important; margin: 0; padding: 20mm; box-shadow: none; }
      .report-title { background-color: #192c4f !important; -webkit-print-color-adjust: exact !important; }
    }
  </style>
</head>
<body>
  <div class="filter-container no-print">
    <form method="GET" action="">
      <!-- Hidden input to keep track of the current report type -->
      <input type="hidden" name="report_type" value="<?php echo htmlspecialchars($report_type); ?>">
      
      <div style="width: 100%; display: flex; flex-wrap: wrap; gap: 1.5rem; justify-content: center; align-items: flex-end;">
          <div>
            <label for="startDate">ពីថ្ងៃទី:</label>
            <input type="date" id="startDate" name="startDate" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>" onchange="this.form.submit()">
          </div>
          <div>
            <label for="endDate">ដល់ថ្ងៃទី:</label>
            <input type="date" id="endDate" name="endDate" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>" onchange="this.form.submit()">
          </div>
          <div>
            <label for="employee_type">ប្រភេទបុគ្គលិក:</label>
            <select id="employee_type" name="employee_type" class="form-select" onchange="this.form.submit()">
              <option value="all" <?php if ($employeeType==='all' ) echo 'selected' ; ?>>ទាំងអស់</option>
              <option value="skilled" <?php if ($employeeType==='skilled' ) echo 'selected' ; ?>>បុគ្គលិកជំនាញ</option>
              <option value="worker" <?php if ($employeeType==='worker' ) echo 'selected' ; ?>>បុគ្គលិកកម្មករ</option>
            </select>
          </div>
      </div>
    </form>
    
    <!-- Report Toggling Buttons -->
    <div class="report-toggle-buttons" style="margin-top: 2rem;">
        <?php
            $queryString = http_build_query([
                'startDate' => $startDate,
                'endDate' => $endDate,
                'employee_type' => $employeeType
            ]);
        ?>
        <style>
            .tg-btn { display: flex; gap: 15px; align-items: center; }
            .tg-btn img { vertical-align: middle; }
            .tg-btn.active { background-color:rgb(255, 255, 255); color: white; border-color: #2563eb; }
            .tg-btn:hover { background-color:rgb(4, 24, 80); color: white; transform: scale(1.05); }
        </style>
        <a href="?report_type=late&<?php echo $queryString; ?>"  class="tg-btn <?php echo ($report_type === 'late') ? 'active' : ''; ?>" style="align-items: center;">
          <img src="https://cdn-icons-png.flaticon.com/512/5295/5295358.png" alt="" width="20px">
            របាយការណ៍មកយឺត
        </a>
        <a href="?report_type=forget&<?php echo $queryString; ?>" class="tg-btn <?php echo ($report_type === 'forget') ? 'active' : ''; ?>">
            <img src="https://cdn-icons-png.flaticon.com/512/12962/12962551.png" alt="" width="20px">
            របាយការណ៍ភ្លេចស្កេន
        </a>
    </div>
  </div>

  <!-- This button will trigger sending BOTH reports -->
  <button id="send-telegram-btn" type="button" class="no-print tg-btn tg-btn-primary" style="display: block; margin: 24px auto; font-weight: bold; font-size: 17px; padding: 12px 32px; background-color: #2563eb; color:white;">
      <i class="fa-brands fa-telegram"></i>
    ផ្ញើរបាយការណ៍ទៅ Telegram
  </button>
  
  <!-- JavaScript for popups, modals, and sending logic -->
  <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Inject CSS for Modal, Popups, and Loading Overlay ---
        // (This is the same advanced CSS you had before, no changes needed)
        const customStyles = `
            .tg-modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100vw; height: 100vh; background: rgba(30, 41, 59, 0.5); align-items: center; justify-content: center; opacity: 0; transition: opacity 0.3s ease; font-family: 'kh battambang', sans-serif; }
            .tg-modal.visible { display: flex; opacity: 1; }
            .tg-modal-dialog { background: #fff; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); width: 95%; max-width: 440px; transform: scale(0.95); transition: transform 0.3s ease; }
            .tg-modal.visible .tg-modal-dialog { transform: scale(1); }
            .tg-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #e5e7eb; }
            .tg-modal-title { font-family: 'Khmer OS Muol Light', sans-serif; font-size: 18px; color: #111827; font-weight: 600; margin: 0; }
            .tg-modal-close-btn { background: none; border: none; cursor: pointer; color: #6b7280; padding: 4px; border-radius: 50%; }
            .tg-modal-close-btn:hover { background-color: #f3f4f6; }
            .tg-modal-close-btn svg { width: 20px; height: 20px; display: block; }
            .tg-modal-body { padding: 24px; background: #f9fafb; }
            .tg-modal-body label { font-size: 15px; display: block; margin-bottom: 8px; font-weight: 500; }
            .tg-modal-body textarea { width: 100%; box-sizing: border-box; border-radius: 8px; border: 1px solid #d1d5db; padding: 10px 14px; font-size: 15px; resize: vertical; min-height: 80px; font-family: 'kh battambang', sans-serif;}
            .tg-modal-body textarea:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25); }
            .tg-modal-footer { display: flex; justify-content: flex-end; gap: 12px; padding: 16px 24px; background: #fff; border-top: 1px solid #e5e7eb; }
            .tg-btn { font-family: 'kh battambang', sans-serif; font-weight: 600; font-size: 15px; padding: 9px 20px; border-radius: 8px; border: 1px solid transparent; cursor: pointer; transition: all 0.2s ease; }
            .tg-btn-secondary { background-color: #fff; color: #374151; border-color: #d1d5db; }
            .tg-btn-secondary:hover { background-color: #f9fafb; }
            .tg-btn-primary { background-color: #2563eb; color: white; }
            .tg-btn-primary:hover { background-color: #1d4ed8; }
            .custom-popup-wrapper { position: fixed; top: 20px; left: 50%; z-index: 10000; display: flex; align-items: center; background-color: #fff; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); padding: 14px 20px; min-width: 320px; max-width: 90%; font-family: 'kh battambang', sans-serif; animation: slideDown 0.4s ease forwards; }
            .custom-popup-wrapper.hide { animation: slideUp 0.4s ease forwards; }
            .popup-icon { flex-shrink: 0; width: 24px; height: 24px; margin-right: 12px; }
            .popup-message { font-size: 15px; font-weight: 500; color: #333; }
            .popup-close { position: absolute; top: 8px; right: 8px; cursor: pointer; color: #aaa; background: none; border: none; font-size: 20px; line-height: 1; }
            .custom-popup-wrapper.success { border-left: 5px solid #28a745; }
            .custom-popup-wrapper.error { border-left: 5px solid #dc3545; }
            @keyframes slideDown { from { opacity: 0; transform: translate(-50%, -20px); } to { opacity: 1; transform: translate(-50%, 0); } }
            @keyframes slideUp { from { opacity: 1; transform: translate(-50%, 0); } to { opacity: 0; transform: translate(-50%, -20px); } }
            .loading-overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); z-index: 99999; display: flex; align-items: center; justify-content: center; }
            .loading-box { background: white; color: #192c4f; padding: 25px 40px; border-radius: 12px; text-align: center; box-shadow: 0 5px 20px rgba(0,0,0,0.2); }
            .loading-box .spinner { width: 40px; height: 40px; border: 4px solid rgba(0, 0, 0, 0.1); border-left-color: #2563eb; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 15px auto; }
            .loading-box p { font-size: 16px; font-weight: bold; margin: 0; font-family: 'kh battambang', sans-serif; }
            @keyframes spin { to { transform: rotate(360deg); } }
        `;
        const styleSheet = document.createElement("style");
        styleSheet.innerText = customStyles;
        document.head.appendChild(styleSheet);
        
        // --- Insert Modal HTML into the page ---
        const modalHtml = `
            <div id="captionModal" class="tg-modal">
              <div class="tg-modal-dialog">
                <div class="tg-modal-header"><h5 class="tg-modal-title">បញ្ចូល Caption</h5><button type="button" class="tg-modal-close-btn" id="closeModalBtn"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg></button></div>
                <div class="tg-modal-body"><label for="captionInput">សូមបញ្ចូល Caption ដែលត្រូវភ្ជាប់ជាមួយរូបភាព:</label><textarea id="captionInput" rows="4"></textarea></div>
                <div class="tg-modal-footer"><button type="button" class="tg-btn tg-btn-secondary" id="cancelModalBtn">បោះបង់</button><button type="button" class="tg-btn tg-btn-primary" id="okModalBtn">យល់ព្រម</button></div>
              </div>
            </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // --- Reusable Functions for UI (Modal, Alerts, Loading) ---
        // (These are the same advanced functions you had, no changes needed)
        function showModal(defaultCaption) {
            return new Promise((resolve) => {
                const modal = document.getElementById('captionModal');
                const input = document.getElementById('captionInput');
                const okBtn = document.getElementById('okModalBtn');
                const cancelBtn = document.getElementById('cancelModalBtn');
                const closeBtn = document.getElementById('closeModalBtn');
                input.value = defaultCaption || '';
                requestAnimationFrame(() => { modal.classList.add('visible'); input.focus(); input.select(); });
                const cleanupAndResolve = (value) => {
                    modal.classList.remove('visible');
                    okBtn.removeEventListener('click', onOk);
                    cancelBtn.removeEventListener('click', onCancel);
                    closeBtn.removeEventListener('click', onCancel);
                    input.removeEventListener('keydown', onKeydown);
                    resolve(value);
                };
                const onOk = () => cleanupAndResolve(input.value.trim());
                const onCancel = () => cleanupAndResolve(null);
                const onKeydown = (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); onOk(); }
                    if (e.key === 'Escape') { e.preventDefault(); onCancel(); }
                };
                okBtn.addEventListener('click', onOk);
                cancelBtn.addEventListener('click', onCancel);
                closeBtn.addEventListener('click', onCancel);
                input.addEventListener('keydown', onKeydown);
            });
        }
        let currentPopup = null;
        function showCustomAlert(type, message) {
            if (currentPopup) { currentPopup.remove(); } 
            const icons = {
                success: `<svg class="popup-icon" style="color: #28a745;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`,
                error: `<svg class="popup-icon" style="color: #dc3545;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`
            };
            const wrapper = document.createElement('div');
            wrapper.className = `custom-popup-wrapper ${type}`;
            wrapper.innerHTML = `${icons[type]}<span class="popup-message">${message}</span><button class="popup-close">×</button>`;
            document.body.appendChild(wrapper);
            currentPopup = wrapper;
            const closePopup = () => {
                wrapper.classList.add('hide');
                wrapper.addEventListener('animationend', () => { if (wrapper.parentElement) wrapper.remove(); if (currentPopup === wrapper) currentPopup = null; });
            };
            wrapper.querySelector('.popup-close').onclick = closePopup;
            setTimeout(closePopup, 5000);
        }
        function showLoading(message) {
            const overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = `<div class="loading-box"><div class="spinner"></div><p>${message}</p></div>`;
            document.body.appendChild(overlay);
            return overlay;
        }

        // --- START: MODIFICATION 2 ---
        const sendBtn = document.getElementById('send-telegram-btn');
        const employeeTypeSelect = document.getElementById('employee_type');

        // Function to disable the send button if "All" is selected
        function toggleSendButton() {
            if (employeeTypeSelect.value === 'all') {
                sendBtn.disabled = true;
                sendBtn.style.opacity = '0.6';
                sendBtn.style.cursor = 'not-allowed';
                sendBtn.title = 'សូមជ្រើសរើសប្រភេទបុគ្គលិក (ជំនាញ ឬ កម្មករ) ជាមុនសិន';
            } else {
                sendBtn.disabled = false;
                sendBtn.style.opacity = '1';
                sendBtn.style.cursor = 'pointer';
                sendBtn.title = 'ផ្ញើរបាយការណ៍ទៅ Telegram';
            }
        }

        // Check on page load
        toggleSendButton();
        
        // The page reloads when the select changes, so the check above is enough.
        // No need for an extra event listener here.

        // --- MAIN SEND BUTTON LOGIC (MODIFIED TO SEND EMPLOYEE TYPE) ---
        sendBtn.addEventListener('click', async function() {
            const defaultCaption = `របាយការណ៍សរុប\nគិតពីថ្ងៃទី <?php echo date('d-m-Y', strtotime($startDate)); ?> ដល់ <?php echo date('d-m-Y', strtotime($endDate)); ?>`;
            const caption = await showModal(defaultCaption);
            if (caption === null) return; // User cancelled

            sendBtn.disabled = true;
            const loadingOverlay = showLoading('កំពុងបង្កើតរូបភាព...');

            try {
                const lateReportDiv = document.getElementById('report-page-late');
                const forgetReportDiv = document.getElementById('report-page-forget');
                const currentView = '<?php echo $report_type; ?>';

                // Temporarily ensure both divs are display:block to generate canvas
                lateReportDiv.style.display = 'block';
                forgetReportDiv.style.display = 'block';

                loadingOverlay.querySelector('p').textContent = 'កំពុងបង្កើតរូបភាពទី១ (មកយឺត)...';
                window.scrollTo(0, 0);
                const canvasLate = await html2canvas(lateReportDiv, { scale: 2, useCORS: true, backgroundColor: '#ffffff' });
                const blobLate = await new Promise(resolve => canvasLate.toBlob(resolve, 'image/png'));
                if (!blobLate) throw new Error('បរាជ័យក្នុងការបង្កើតរូបភាពទី១។');
                
                loadingOverlay.querySelector('p').textContent = 'កំពុងបង្កើតរូបភាពទី២ (ភ្លេចស្កេន)...';
                window.scrollTo(0, 0);
                const canvasForget = await html2canvas(forgetReportDiv, { scale: 2, useCORS: true, backgroundColor: '#ffffff' });
                const blobForget = await new Promise(resolve => canvasForget.toBlob(resolve, 'image/png'));
                if (!blobForget) throw new Error('បរាជ័យក្នុងការបង្កើតរូបភាពទី២។');

                // Restore original view
                lateReportDiv.style.display = (currentView === 'late') ? 'block' : 'none';
                forgetReportDiv.style.display = (currentView === 'forget') ? 'block' : 'none';
                
                loadingOverlay.querySelector('p').textContent = 'កំពុងផ្ញើទៅ Telegram...';
                
                // --- Prepare FormData for PHP endpoint ---
                const formData = new FormData();
                formData.append('action', 'send_telegram_group');
                formData.append('caption', caption);
                // **Add the selected employee type to the form data**
                formData.append('employee_type', employeeTypeSelect.value); 
                formData.append('photos[]', blobLate, 'report_late.png');
                formData.append('photos[]', blobForget, 'report_forget.png');

                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (response.ok && result.ok) {
                    showCustomAlert('success', 'ផ្ញើរបានជោគជ័យ!');
                } else {
                    throw new Error(result.description || 'Unknown Telegram error occurred.');
                }
            } catch (e) {
                showCustomAlert('error', 'បរាជ័យ: ' + e.message);
            } finally {
                loadingOverlay.remove();
                // We re-enable the button by calling the check function again
                toggleSendButton(); 
            }
        });
        // --- END: MODIFICATION 2 ---
    });
  </script>

  <!-- LATE REPORT BLOCK -->
  <!-- ... (The rest of your HTML code for the reports remains unchanged) ... -->
  <div class="report-page" id="report-page-late" style="<?php echo ($report_type === 'late') ? 'display:block;' : 'display:none;'; ?>">
    <div class="report-header text-center"> <img src="https://i.ibb.co/7x90kJJk/Logo-Van-Van-2.png" alt="logo" /> </div>
    <div class="report-title">
      <h2>របាយការណ៍បុគ្គលិកមកយឺត</h2>
      <h4>សម្រាប់បុគ្គលិកជំនាញៗ និងតាមឃ្លាំង</h4>
      <h4 style="font-family: kh battambang;"> គិតចាប់ពីថ្ងៃទី <?php echo date('d-m-Y', strtotime($startDate)); ?> ដល់ថ្ងៃទី <?php echo date('d-m-Y', strtotime($endDate)); ?></h4>
    </div>
    <table class="report-table">
      <thead>
        <tr>
          <th rowspan="2">ល.រ</th> <th rowspan="2">អត្តលេខ</th> <th rowspan="2">ឈ្មោះ</th> <th rowspan="2">ភេទ</th> <th rowspan="2">តួនាទី</th> <th colspan="3">មកយឺត</th> <th rowspan="2">សរុប</th>
        </tr>
        <tr>
          <th style="background-color: #192c4f; color: white;">ក្រោម ១៥ នាទី</th> <th style="background-color: #192c4f; color: white;">ចាប់ពី ១៥ នាទី</th> <th style="background-color: #192c4f; color: white;">ចាប់ពី ១ ម៉ោង</th>
        </tr>
      </thead>
      <tbody>
        <?php
            if (empty($late_users)) {
                echo '<tr><td colspan="9" style="text-align: center;">មិនមានទិន្នន័យទេ។</td></tr>';
            } else {
                $index = 1;
                foreach ($late_users as $user) {
                    $row_class = '';
                    if ($user['total'] >= 5) { $row_class = 'highlight-red'; } 
                    elseif ($user['total'] >= 2) { $row_class = 'highlight-gold'; }
                    echo '<tr class="' . $row_class . '">';
                    echo '<td>' . $index++ . '</td>';
                    echo '<td>' . htmlspecialchars($user['user_id'] ?? 'N/A') . '</td>';
                    echo '<td style="text-align: left;">' . htmlspecialchars($user['username'] ?? 'N/A') . '</td>';
                    echo '<td style="text-align: left;">' . htmlspecialchars($user['gender'] ?? 'N/A') . '</td>';
                    echo '<td style="text-align: left;">' . htmlspecialchars($user['position'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($user['late_under_15'] ?? 0) . '</td>';
                    echo '<td>' . htmlspecialchars($user['late_15_plus'] ?? 0) . '</td>';
                    echo '<td>' . htmlspecialchars($user['late_1_hour'] ?? 0) . '</td>';
                    echo '<td style="color: red; font-weight: bold;">' . htmlspecialchars($user['total'] ?? 0) . '</td>';
                    echo '</tr>';
                }
            }
        ?>
        <tr id="summary-row-late">
          <th colspan="5" style="text-align: center; background-color: #f1c011;">សរុប</th>
          <th><?php echo htmlspecialchars($sumUnder15); ?></th>
          <th><?php echo htmlspecialchars($sum15plus); ?></th>
          <th><?php echo htmlspecialchars($sum1hour); ?></th>
          <th><?php echo htmlspecialchars($late_sumTotal); ?></th>
        </tr>
      </tbody>
    </table>
    <!-- Common Footer -->
 <div class="report-footer">
            <div>
                <p style="font-family: Khmer OS Muol Light;">ប្រធាននាយកដ្ឋានធនធានមនុស្ស និងរដ្ឋបាល</p>
                <div style="margin-top: 4rem;">
                    <p>____________________</p>
                    <p style="font-family: Khmer OS Muol Light;">លោក ផល ស៊ាងឡេង</p>
                </div>
            </div>
            <div>
                <p style="font-family: Khmer OS Muol Light;">ត្រួតពិនិត្យដោយ</p>
                <div style="margin-top: 4.2rem;">
                    <p>____________________</p>
                    <p style="font-family: Khmer OS Muol Light;">វិជ្ជា វាអ៊ី</p>
                </div>
            </div>
            <div>
                <p id="khmer-lunar-date" class="editable-text">ថ្ងៃអង្គារ ៦កើត ខែអាសាឍ ព.ស.២៥៦៨</p>
                <p id="khmer-gregorian-date" class="editable-text">រាជធានីភ្នំពេញ, ០១ កក្កដា ២៥៦៨</p>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        document.querySelectorAll('.editable-text').forEach(function(el) {
                            const key = 'editable_' + (el.id || Math.random());
                            const saved = localStorage.getItem(key);
                            if (saved !== null) el.textContent = saved;
                            el.style.cursor = 'pointer';
                            el.title = 'Click to edit';
                            el.addEventListener('click', function() {
                                if (el.querySelector('input')) return;
                                const oldText = el.textContent;
                                const input = document.createElement('input');
                                input.type = 'text';
                                input.value = oldText;
                                input.style.width = '100%';
                                input.style.fontFamily = 'inherit';
                                input.style.fontSize = 'inherit';
                                input.style.border = '1px solid #ccc';
                                input.style.padding = '2px 6px';
                                el.textContent = '';
                                el.appendChild(input);
                                input.focus();
                                const finishEdit = (save) => {
                                    const newValue = input.value;
                                    el.textContent = save ? newValue : oldText;
                                    if (save) localStorage.setItem(key, newValue);
                                };
                                input.addEventListener('blur', () => finishEdit(true));
                                input.addEventListener('keydown', (e) => {
                                    if (e.key === 'Enter') finishEdit(true);
                                    if (e.key === 'Escape') finishEdit(false);
                                });
                            });
                        });
                    });
                </script>
                <p style="font-family: Khmer OS Muol Light;">រៀបចំដោយ</p>
                <div style="margin-top: 4.2rem;">
                    <p>____________________</p>
                    <p style="font-family: Khmer OS Muol Light;">សៀង សារុន</p>
                </div>
            </div>
        </div>
  </div>

  <!-- FORGET SCAN REPORT BLOCK -->
  <div class="report-page" id="report-page-forget" style="<?php echo ($report_type === 'forget') ? 'display:block;' : 'display:none;'; ?>">
    <div class="report-header text-center"> <img src="https://i.ibb.co/7x90kJJk/Logo-Van-Van-2.png" alt="logo" /> </div>
    <div class="report-title">
      <h2>របាយការណ៍បុគ្គលិកភ្លេចស្កេន</h2>
      <h4>សម្រាប់បុគ្គលិកជំនាញៗ និងតាមឃ្លាំង</h4>
      <h4 style="font-family: kh battambang;"> គិតចាប់ពីថ្ងៃទី <?php echo date('d-m-Y', strtotime($startDate)); ?> ដល់ថ្ងៃទី <?php echo date('d-m-Y', strtotime($endDate)); ?></h4>
    </div>
    <table class="report-table">
      <thead>
        <tr>
          <th rowspan="2">ល.រ</th> <th rowspan="2">អត្តលេខ</th> <th rowspan="2">ឈ្មោះ</th> <th rowspan="2">ភេទ</th> <th rowspan="2">តួនាទី</th> <th colspan="2">ភ្លេចស្កេនមេដៃ</th> <th rowspan="2">សរុប</th>
        </tr>
        <tr>
          <th style="background-color: #192c4f; color: white;">ចូល</th> <th style="background-color: #192c4f; color: white;">ចេញ</th>
        </tr>
      </thead>
      <tbody>
        <?php
            if (empty($forget_users)) {
                echo '<tr><td colspan="8" style="text-align: center;">មិនមានទិន្នន័យទេ។</td></tr>';
            } else {
                $index = 1;
                foreach ($forget_users as $user) {
                    $row_class = '';
                    if (($user['total'] ?? 0) >= 5) { $row_class = 'highlight-red'; } 
                    elseif (($user['total'] ?? 0) >= 2) { $row_class = 'highlight-gold'; }
                    echo '<tr class="' . $row_class . '">';
                    echo '<td>' . $index++ . '</td>';
                    echo '<td>' . htmlspecialchars($user['user_id'] ?? 'N/A') . '</td>';
                    echo '<td style="text-align: left;">' . htmlspecialchars($user['username'] ?? 'N/A') . '</td>';
                    echo '<td style="text-align: left;">' . htmlspecialchars($user['gender'] ?? 'N/A') . '</td>';
                    echo '<td style="text-align: left;">' . htmlspecialchars($user['position'] ?? 'N/A') . '</td>';
                    echo '<td>' . htmlspecialchars($user['forgot_check_in'] ?? 0) . '</td>';
                    echo '<td>' . htmlspecialchars($user['forgot_check_out'] ?? 0) . '</td>';
                    echo '<td style="color: red; font-weight: bold;">' . htmlspecialchars($user['total'] ?? 0) . '</td>';
                    echo '</tr>';
                }
            }
        ?>
        <tr id="summary-row-forget">
          <th colspan="5" style="text-align: center; background-color: #f1c011;">សរុប</th>
          <th><?php echo htmlspecialchars($sumForgotIn); ?></th>
          <th><?php echo htmlspecialchars($sumForgotOut); ?></th>
          <th><?php echo htmlspecialchars($forget_sumTotal); ?></th>
        </tr>
      </tbody>
    </table>
    <!-- Common Footer -->
    <div class="report-footer">
       <!-- Footer content from your original file... -->
       <!-- Note: The inline editable date script will work for both as it's common -->
       <div>
            <p style="font-family: Khmer OS Muol Light;">ប្រធាននាយកដ្ឋានធនធានមនុស្ស និងរដ្ឋបាល</p>
            <div style="margin-top: 4rem;"><p>____________________</p><p style="font-family: Khmer OS Muol Light;">លោក ផល ស៊ាងឡេង</p></div>
       </div>
       <div>
            <p style="font-family: Khmer OS Muol Light;">ត្រួតពិនិត្យដោយ</p>
            <div style="margin-top: 4.2rem;"><p>____________________</p><p style="font-family: Khmer OS Muol Light;">វិជ្ជា វាអ៊ី</p></div>
       </div>
       <div>
            <p id="khmer-lunar-date" class="editable-text">ថ្ងៃអង្គារ ៦កើត ខែអាសាឍ ព.ស.២៥៦៨</p>
            <p id="khmer-gregorian-date" class="editable-text">រាជធានីភ្នំពេញ, ០១ កក្កដា ២៥៦៨</p>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.editable-text').forEach(function (el) {
                const key = 'editable_' + (el.id || Math.random());
                const saved = localStorage.getItem(key);
                if (saved !== null) el.textContent = saved;
                el.style.cursor = 'pointer'; el.title = 'Click to edit';
                el.addEventListener('click', function () {
                    if (el.querySelector('input')) return;
                    const oldText = el.textContent;
                    const input = document.createElement('input');
                    input.type = 'text'; input.value = oldText; input.style.width = '100%'; input.style.fontFamily = 'inherit'; input.style.fontSize = 'inherit'; input.style.border = '1px solid #ccc'; input.style.padding = '2px 6px';
                    el.textContent = ''; el.appendChild(input); input.focus();
                    const finishEdit = (save) => {
                    const newValue = input.value;
                    el.textContent = save ? newValue : oldText;
                    if (save) localStorage.setItem(key, newValue);
                    };
                    input.addEventListener('blur', () => finishEdit(true));
                    input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') finishEdit(true);
                    if (e.key === 'Escape') finishEdit(false);
                    });
                });
                });
            });
            </script>
            <p style="font-family: Khmer OS Muol Light;">រៀបចំដោយ</p>
            <div style="margin-top: 4.2rem;"><p>____________________</p><p style="font-family: Khmer OS Muol Light;">សៀង សារុន</p></div>
       </div>
    </div>
  </div>
</body>
</html>