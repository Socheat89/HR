<?php
// export_logs.php (ប្តូរពី CSV ទៅជា HTML Table សម្រាប់ Excel Styling)

// ១. ចាប់ផ្ដើម Session និងពិនិត្យសុវត្ថិភាព (កូដនេះនៅដដែល)
ini_set('session.gc_maxlifetime', 3600);
session_set_cookie_params(3600);
session_start();
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die('CSRF token validation failed.');
}
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$is_sub_user = isset($_SESSION['sub_user_logged_in']) && $_SESSION['sub_user_logged_in'] === true;
if (!$is_admin && !$is_sub_user) {
    header("Location: admin_login.php");
    exit;
}

// Function build_where_clause (កូដនេះនៅដដែល)
function build_where_clause(array $conditions): string {
    if (empty($conditions)) { return ""; }
    return " WHERE " . implode(" AND ", $conditions);
}

try {
    // ២. ភ្ជាប់ទៅកាន់ Database (កូដនេះនៅដដែល)
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db;charset=utf8mb4", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    // ៣. ទទួល និងបង្កើត Filter សម្រាប់ទិន្នន័យ (កូដនេះនៅដដែល)
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $start_time = $_POST['start_time'] ?? null;
    $end_time = $_POST['end_time'] ?? null;
    $staff_type = $_POST['staff_type'] ?? 'skilled';
    $usernames = !empty($_POST['usernames']) ? array_filter(array_map('trim', explode(',', $_POST['usernames']))) : [];
    $branches = !empty($_POST['branches']) ? array_filter(array_map('trim', explode(',', $_POST['branches']))) : [];
    
    // ៤. បង្កើត SQL WHERE clause ផ្អែកលើ Filter (កូដនេះនៅដដែល)
    $conditions = [];
    $params = [];
    // ... (កូដ filter ทั้งหมดនៅដដែល) ...
    $skilled_folders = ['ជំនាញ', 'ឃ្លាំង', 'ហាងទំនិញ៣១៨', 'SK Chuk Meas', 'SK Cosmetic'];
    $worker_folders = ['កម្មករ'];
    if ($is_sub_user) {
        if (isset($_SESSION['branch'])) { $conditions[] = "branch = ?"; $params[] = $_SESSION['branch']; }
        if (isset($_SESSION['allowed_username'])) {
            $allowed_usernames_from_session = is_string($_SESSION['allowed_username']) ? json_decode($_SESSION['allowed_username'], true) : $_SESSION['allowed_username'];
            if (is_array($allowed_usernames_from_session) && !empty($allowed_usernames_from_session)) {
                 $placeholders = implode(',', array_fill(0, count($allowed_usernames_from_session), '?'));
                 $conditions[] = "username IN ($placeholders)";
                 $params = array_merge($params, $allowed_usernames_from_session);
            } else { $conditions[] = "1=0"; }
        }
    } elseif ($is_admin && !empty($branches)) {
        $placeholders = implode(',', array_fill(0, count($branches), '?'));
        $conditions[] = "branch IN ($placeholders)";
        $params = array_merge($params, $branches);
    }
    if ($staff_type === 'skilled' && !empty($skilled_folders)) {
        $placeholders = implode(',', array_fill(0, count($skilled_folders), '?'));
        $conditions[] = "folder IN ($placeholders)";
        $params = array_merge($params, $skilled_folders);
    } elseif ($staff_type === 'worker' && !empty($worker_folders)) {
        $placeholders = implode(',', array_fill(0, count($worker_folders), '?'));
        $conditions[] = "folder IN ($placeholders)";
        $params = array_merge($params, $worker_folders);
    }
    if (!empty($start_date)) { $conditions[] = "DATE(timestamp) >= ?"; $params[] = $start_date; }
    if (!empty($end_date)) { $conditions[] = "DATE(timestamp) <= ?"; $params[] = $end_date; }
    if (!empty($start_time)) { $conditions[] = "TIME(timestamp) >= ?"; $params[] = $start_time; }
    if (!empty($end_time)) { $conditions[] = "TIME(timestamp) <= ?"; $params[] = $end_time; }
    if ($is_admin && !empty($usernames)) {
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $conditions[] = "username IN ($placeholders)";
        $params = array_merge($params, $usernames);
    }
    
    $whereClause = build_where_clause($conditions);
    
    // ៥. ទាញយកទិន្នន័យពី Database (កូដនេះនៅដដែល)
    $sql = "SELECT user_id, username, branch, folder, action, timestamp, status, noted, early_reason FROM scan_logs" . $whereClause . " ORDER BY username ASC, timestamp ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ៦. បង្កើតឈ្មោះไฟล์ (កូដនេះនៅដដែល)
    $filename_part = date('Y-m-d_H-i-s');
    if (!empty($start_date) && !empty($end_date)) {
        if ($start_date === $end_date) { $filename_part = $start_date; } 
        else { $filename_part = $start_date . '_to_' . $end_date; }
    }
    $filename = "report_attendance" . $filename_part . ".xls"; // << ប្តូរកន្ទុយទៅជា .xls និងធ្វើឱ្យឈ្មោះស្អាតជាងមុន

    // ================= START: កូដកែសម្រួលនៅត្រង់នេះ =================
    // ៧. បង្កើត និងបញ្ចេញไฟล์ HTML សម្រាប់ Excel

    // ក. កំណត់ Headers ដើម្បីឱ្យ Browser ទាញយកជាไฟล์ Excel
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // ខ. ចាប់ផ្តើមបង្កើត HTML Document
    // (xmlns... គឺដើម្បីឱ្យ Excel យល់ពីទម្រង់ HTML នេះកាន់តែច្បាស់)
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta http-equiv="Content-type" content="text/html;charset=utf-8" />';
    
    // *** ចំណុចសំខាន់៖ បន្ថែម Style ដើម្បីកំណត់ Font និងរចនាបថតារាង ***
    echo '<style>
            /* កំណត់ Font គោលសម្រាប់ឯកសារទាំងមូល */
            body, table, td, th {
                font-family: "Kh Battambang", "Khmer OS Content", sans-serif;
                font-size: 11pt;
            }
            /* កំណត់ Style សម្រាប់ក្បាលតារាង (Header) */
            th {
                background-color: goldenrod; /* ពណ៌ខៀវស្រាលជាងមុន */
                color: #FFFFFF;
                font-weight: bold;
                padding: 10px;
                border: 1px solid #333333;
                text-align: center; /* ដាក់អក្សរនៅកណ្តាល */
                vertical-align: middle;
            }
            /* កំណត់ Style សម្រាប់ក្រឡាទិន្នន័យ (Data cell) */
            td {
                padding: 8px;
                border: 1px solid #CCCCCC;
                vertical-align: top; /* ដាក់ទិន្នន័យនៅផ្នែកខាងលើនៃក្រឡា */
            }
          </style>';
    echo '</head><body>';
    
    echo '<table border="1">';
    
    // គ. សរសេរក្បាលតារាង (Header Row) ជាភាសាខ្មែរ
    echo '<thead>';
    echo '<tr>';
    echo '<th style="background-color: #2023a2; color: white; font-family: Kh KoulenL; font-weight: 400;  font-size: 12pt">លេខសម្គាល់</th>';
    echo '<th style="background-color: #2023a2; color: white;font-family: Kh KoulenL; font-weight: 400;  font-size: 12pt" >ឈ្មោះបុគ្គលិក</th>';
    echo '<th style="background-color: #2023a2; color: white;font-family: Kh KoulenL; font-weight: 400;  font-size: 12pt">សាខា</th>';
    echo '<th style="background-color: #2023a2; color: white;font-family: Kh KoulenL; font-weight: 400; font-size: 12pt">ផ្នែក</th>';
    echo '<th style="background-color: #2023a2; color: white;font-family: Kh KoulenL; font-weight: 400; font-size: 12pt">ប្រភេទស្កេន</th>';
    echo '<th style="background-color: #2023a2; color: white;font-family: Kh KoulenL; font-weight: 400; font-size: 12pt">កាលបរិច្ឆេទ</th>';
    echo '<th style="background-color: #2023a2; color: white;font-family: Kh KoulenL; font-weight: 400; font-size: 12pt">ពេលវេលា</th>';
    echo '<th style="background-color: #2023a2; color: white;font-family: Kh KoulenL; font-weight: 400; font-size: 12pt">ស្ថានភាព</th>';
    echo '<th style="background-color: #2023a2; color: white;font-family: Kh KoulenL; font-weight: 400; font-size: 12pt">កំណត់ត្រា</th>';
    echo '<th style="background-color: #2023a2; color: white;font-family: Kh KoulenL; font-weight: 400; font-size: 12pt">មូលហេតុ (ស្កេនមុនម៉ោង)</th>';
    echo '</tr>';
    echo '</thead>';
    
    // ឃ. បញ្ចូលទិន្នន័យទៅក្នុងតួតារាង (Table Body)
    echo '<tbody>';
    if ($logs) {
        foreach ($logs as $log) {
            // បំប្លែង timestamp ទៅជាទម្រង់ ថ្ងៃ/ខែ/ឆ្នាំ និង ម៉ោង:នាទី:វិនាទី AM/PM
            $scan_datetime = new DateTime($log['timestamp']);
            $scan_date_display = $scan_datetime->format('d-M-Y'); // ប្រើ M ដើម្បីបានឈ្មោះខែជាអក្សរកាត់
            $scan_time_display = $scan_datetime->format('h:i:s A');

            echo '<tr>';
            echo '<td style="text-align: center;">' . htmlspecialchars($log['user_id'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($log['username'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($log['branch'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($log['folder'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($log['action'] ?? '') . '</td>';
            echo '<td>' . $scan_date_display . '</td>';
            echo '<td>' . $scan_time_display . '</td>';
            echo '<td>' . htmlspecialchars($log['status'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($log['noted'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($log['early_reason'] ?? '') . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody>';
    echo '</table>';
    
    echo '</body></html>';
    // ================= END: កូដកែសម្រួលនៅត្រង់នេះ =================
    
    exit;

} catch (PDOException $e) {
    header('Content-Type: text/plain; charset=utf-8');
    die("Database Error: " . $e->getMessage());
} catch (Exception $e) {
    header('Content-Type: text/plain; charset=utf-8');
    die("An error occurred: " . $e->getMessage());
}
?>