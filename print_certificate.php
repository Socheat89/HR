<?php
session_start();
require_once 'log.php';

// ONLY ADMIN CAN ACCESS THIS PAGE
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'អ្នកគ្មានសិទ្ធិចូលទំព័រនេះទេ។';
    header("Location: login.php");
    exit();
}

// Database connection
try {
    $db = new PDO("mysql:host=localhost;dbname=samann1_admin_panel;charset=utf8mb4", "samann1_admin_panel", "admin_panel@2025");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. CREATE TABLE FOR SAVING TEXT EDITS IF NOT EXISTS
    $db->exec("CREATE TABLE IF NOT EXISTS certificate_text_edits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        poll_id INT NOT NULL,
        winner_id INT NOT NULL,
        field_key VARCHAR(50) NOT NULL,
        content TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_content (poll_id, winner_id, field_key)
    )");

} catch (PDOException $e) {
    $_SESSION['error'] = 'មានបញ្ហាក្នុងការតភ្ជាប់ទិន្នន័យ។ សូមព្យាយាមម្តងទៀត។';
    error_log("DB Connection Error: " . $e->getMessage());
    header("Location: error.php");
    exit();
}

// 2. HANDLE AJAX REQUEST TO SAVE TEXT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_inline_edit') {
    header('Content-Type: application/json');
    try {
        $p_poll_id = $_POST['poll_id'];
        $p_winner_id = $_POST['winner_id'];
        $p_field = $_POST['field'];
        $p_content = $_POST['content'];

        // Use INSERT ... ON DUPLICATE KEY UPDATE to save or update
        $stmt = $db->prepare("INSERT INTO certificate_text_edits (poll_id, winner_id, field_key, content) 
                              VALUES (:pid, :wid, :fkey, :cont) 
                              ON DUPLICATE KEY UPDATE content = :cont_update");
        $stmt->execute([
            ':pid' => $p_poll_id,
            ':wid' => $p_winner_id,
            ':fkey' => $p_field,
            ':cont' => $p_content,
            ':cont_update' => $p_content
        ]);
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit; // Stop execution here for AJAX
}

// --- HELPER FUNCTION TO GET TEXT (DB OR DEFAULT) ---
function getSavedText($db, $poll_id, $winner_id, $field_key, $default_content) {
    try {
        $stmt = $db->prepare("SELECT content FROM certificate_text_edits WHERE poll_id = :pid AND winner_id = :wid AND field_key = :fkey LIMIT 1");
        $stmt->execute([':pid' => $poll_id, ':wid' => $winner_id, ':fkey' => $field_key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row['content']; // Return saved text
        }
    } catch (Exception $e) {
        // Ignore error and return default
    }
    return $default_content; // Return generated default
}
// ---------------------------------------------------

$poll_id = filter_input(INPUT_GET, 'poll_id', FILTER_VALIDATE_INT);
$winner_id = filter_input(INPUT_GET, 'winner_id', FILTER_VALIDATE_INT);
$type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_STRING) ?? 'worker';

$company_name_khmer = "";
$company_name_eng = "";
$poll_question_display = ""; 
$warehouse = ""; 

function getQuarterText($quarter) {
    $quarters = [
        '១' => 'ត្រីមាសទី ១',
        '២' => 'ត្រីមាសទី ២',
        '៣' => 'ត្រីមាសទី ៣',
        '៤' => 'ត្រីមាសទី ៤'
    ];
    return $quarters[$quarter] ?? $quarter;
}

function convertToKhmerNumerals($num) {
    $map = [
        '0' => '០','1' => '១','2' => '២','3' => '៣','4' => '៤',
        '5' => '៥','6' => '៦','7' => '៧','8' => '៨','9' => '៩'
    ];
    $s = (string)$num;
    $out = '';
    for ($i = 0; $i < strlen($s); $i++) {
        $ch = $s[$i];
        $out .= isset($map[$ch]) ? $map[$ch] : $ch;
    }
    return $out;
}

$winner_name = "N/A";
$winner_gender_kh = ""; 
$winner_department_kh = ""; 
$current_date_for_cert = date("d F Y"); 
$winner_photo_url = ""; 
$certificate_bg_texture = "https://i.ibb.co/C0yVbL2/certificate-texture.png"; 

if ($poll_id && $winner_id) {
    try {
        // Fetch poll question
        $stmt_poll = $db->prepare("SELECT quarter, warehouse FROM polls WHERE id = :poll_id LIMIT 1");
        $stmt_poll->execute(['poll_id' => $poll_id]);
        $poll_data = $stmt_poll->fetch(PDO::FETCH_ASSOC);
        if ($poll_data) {
            $poll_question_display = getQuarterText($poll_data['quarter']);
            $warehouse = htmlspecialchars($poll_data['warehouse']);
        }

        // Fetch winner's basic data just to have fallback
        $stmt_winner = $db->prepare("
            SELECT 
                COALESCE(full_name, username) AS employee_name,
                gender,
                position,
                department,
                image_url
            FROM users 
            WHERE id = :user_id LIMIT 1
        ");
        $stmt_winner->execute(['user_id' => $winner_id]);
        $winner_data = $stmt_winner->fetch(PDO::FETCH_ASSOC);
        
        // Get top 3 winners
        $top_winners = [];
        $stmt_ranking = $db->prepare("
            SELECT 
                pv.voted_for_user_id,
                SUM(COALESCE(pv.vote_count, 1)) AS total_votes
            FROM peer_votes pv
            WHERE pv.poll_id = :poll_id
            GROUP BY pv.voted_for_user_id
        ");
        $stmt_ranking->execute(['poll_id' => $poll_id]);
        $vote_results = $stmt_ranking->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($vote_results) > 0) {
            $total_votes_overall = array_sum(array_column($vote_results, 'total_votes'));
            
            $ranked_candidates = [];
            foreach ($vote_results as $result) {
                $percentage = $total_votes_overall > 0 ? ($result['total_votes'] / $total_votes_overall) * 100 : 0;
                $ranked_candidates[] = [
                    'user_id' => $result['voted_for_user_id'],
                    'votes' => $result['total_votes'],
                    'percentage' => $percentage
                ];
            }
            
            usort($ranked_candidates, function($a, $b) {
                if ($a['percentage'] == $b['percentage']) {
                    return $b['votes'] <=> $a['votes']; 
                }
                return $b['percentage'] <=> $a['percentage']; 
            });
            
            $top_3_candidates = array_slice($ranked_candidates, 0, 3);
            
            foreach ($top_3_candidates as $index => $candidate) {
                $rank_number = $index + 1;
                $rank_text = 'លេខ ' . convertToKhmerNumerals($rank_number);
                
                $stmt_winner_data = $db->prepare("
                    SELECT 
                        COALESCE(full_name, username) AS employee_name,
                        gender,
                        position,
                        department,
                        image_url
                    FROM users 
                    WHERE id = :user_id LIMIT 1
                ");
                $stmt_winner_data->execute(['user_id' => $candidate['user_id']]);
                $w_data = $stmt_winner_data->fetch(PDO::FETCH_ASSOC);
                
                if ($w_data) {
                    $w_name = htmlspecialchars($w_data['employee_name']);
                    
                    $w_gender_kh = "";
                    if (!empty($w_data['gender'])) {
                        $gender_val = strtolower($w_data['gender']);
                        if ($gender_val === 'male' || $gender_val === 'ប្រុស') {
                            $w_gender_kh = 'ប្រុស';
                        } else if ($gender_val === 'female' || $gender_val === 'ស្រី') {
                            $w_gender_kh = 'ស្រី';
                        }
                    }
                    
                    $w_department_kh = !empty($w_data['position']) ? htmlspecialchars($w_data['position']) : "";
                    
                    $employee_type = 'specialist'; 
                    $department_lower = strtolower($w_data['department'] ?? '');
                    
                    if (strpos($department_lower, 'worker') !== false) {
                        $employee_type = 'worker';
                    }
                    
                    // Generate Default Description
                    if ($employee_type === 'specialist') {
                        $desc_default = 'បុគ្គលិកឈ្មោះ <span style="font-family: \'Khmer OS Muol Light\'; color: #2b36f3; font-weight: bold;"> ' . $w_name . ' </span> ភេទ  ' . $w_gender_kh . '  ជាបុគ្គលិកផ្នែក <span style="font-family: \'Khmer OS Muol Light\'; color: #2b36f3; font-weight: bold;"> ' . $w_department_kh . ' </span><br>
                            ដែលបានខិតខំក្នុងតួនាទីរបស់ខ្លួនបានយ៉ាងល្អក្នុងការបំពេញការងារជូនក្រុមហ៊ុន និងបានជាប់<br>
                            ជាបុគ្គលិកឆ្នើមផ្នែក <span style="font-family: \'Khmer OS Muol Light\'; color: #2b36f3; font-weight: bold;"> ' . htmlspecialchars($warehouse) . ' </span> ប្រចាំ <span style="font-family: \'Khmer OS Muol Light\'; color: #2b36f3; font-weight: bold;"> ' . htmlspecialchars($poll_question_display) . ' </span>   នៃឆ្នាំ ២០២៥ ។';
                    } else {
                        $desc_default = 'បុគ្គលិកឈ្មោះ <span style="font-family: \'Khmer OS Muol Light\'; color: #2b36f3; font-weight: bold;"> ' . $w_name . ' </span> ភេទ  ' . $w_gender_kh . '  ជាបុគ្គលិកផ្នែក <span style="font-family: \'Khmer OS Muol Light\'; color: #2b36f3; font-weight: bold;"> ' . $w_department_kh . ' </span><br>
                            ដែលបានខិតខំក្នុងតួនាទីរបស់ខ្លួនបានយ៉ាងល្អក្នុងការបំពេញការងារជូនក្រុមហ៊ុន និងបានជាប់<br>
                            ជាបុគ្គលិកឆ្នើម ' . $rank_text . ' នៅឃ្លាំង <span style="font-family: \'Khmer OS Muol Light\'; color: #2b36f3; font-weight: bold;"> ' . htmlspecialchars($warehouse) . ' </span> ប្រចាំ <span style="font-family: \'Khmer OS Muol Light\'; color: #2b36f3; font-weight: bold;"> ' . htmlspecialchars($poll_question_display) . ' </span>   នៃឆ្នាំ ២០២៥ ។';
                    }
                    
                    $w_photo_url = "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSOH2aZnIHWjMQj2lQUOWIL2f4Hljgab0ecZQ&s"; 
                    if (!empty($w_data['image_url'])) {
                        $w_photo_url = '/admin/' . htmlspecialchars($w_data['image_url']);
                    }
                    
                    // --- PREPARE DATA WITH SAVED OVERRIDES ---
                    $user_id_val = $candidate['user_id']; // Current winner ID
                    
                    // Default Date
                    $default_date = "ថ្ងៃពុធ ១រោច ខែអស្សុជ ឆ្នាំម្សាញ់ សប្ដស័ក ព.ស ២៥៦៩<br>រាជធានីភ្នំពេញ, ថ្ងៃទី១៤ ខែតុលា ឆ្នាំ២០២៥";

                    // Prepare final array
                    $top_winners[] = [
                        'user_id' => $user_id_val, // ADDED: Need this for DB saving
                        'rank' => $rank_text,
                        'image_url' => $w_photo_url,
                        'rank_num' => $rank_number,
                        'is_worker' => ($employee_type === 'worker'),
                        
                        // Fields that can be edited:
                        'title_main'    => getSavedText($db, $poll_id, $user_id_val, 'title_main', 'លិខិតសរសើរ'),
                        'title_sub1'    => getSavedText($db, $poll_id, $user_id_val, 'title_sub1', 'អគ្គនាយិកាក្រុមហ៊ុន វណ្ណ វណ្ណ ខេមបូឌា'),
                        'title_sub2'    => getSavedText($db, $poll_id, $user_id_val, 'title_sub2', 'សូមសរសើរចំពោះ'),
                        'description'   => getSavedText($db, $poll_id, $user_id_val, 'description', $desc_default),
                        'date_text'     => getSavedText($db, $poll_id, $user_id_val, 'date_text', $default_date),
                        'sig_title'     => getSavedText($db, $poll_id, $user_id_val, 'sig_title', 'អគ្គនាយិកា'),
                        'sig_name'      => getSavedText($db, $poll_id, $user_id_val, 'sig_name', 'ទាវ សុវណ្ណ'),

                        'votes' => $candidate['votes'],
                        'percentage' => round($candidate['percentage'], 1)
                    ];
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Certificate Data Fetch Error: " . $e->getMessage());
    }
}

if (!empty($top_winners)) {
    if ($type === 'worker') {
        $top_winners = array_filter($top_winners, function($w) {
            return $w['is_worker'];
        });
    } else {
        $top_winners = array_filter($top_winners, function($w) {
            return !$w['is_worker'];
        });
        $top_winners = array_slice($top_winners, 0, 2);
    }
}

// Scan available frames
$available_frames = [];
$frame_dir = __DIR__ . '/frame';
if (is_dir($frame_dir)) {
    $files = scandir($frame_dir);
    foreach ($files as $f) {
        if (in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp'])) {
            $available_frames[] = '/frame/' . $f;
        }
    }
}

$selected_bg = '/frame/frame1.jpg';
$bg_param = filter_input(INPUT_GET, 'bg', FILTER_SANITIZE_STRING);
if ($bg_param && in_array($bg_param, $available_frames, true)) {
    $selected_bg = $bg_param;
} else if (!empty($available_frames)) {
    if (!in_array($selected_bg, $available_frames, true)) {
        $selected_bg = $available_frames[0];
    }
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>លិខិតសរសើរ - Inline Edit</title>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Kh+Muol:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        /* Embed Fonts */
        @font-face { font-family: 'Kantumruy Pro'; src: url('https://fonts.gstatic.com/s/kantumruypro/v2/Lly5_p-p02_q0qVnCjK_s1d-S-d_r-T_y-.woff2') format('woff2'); font-weight: 400; }
        @font-face { font-family: 'Kantumruy Pro'; src: url('https://fonts.gstatic.com/s/kantumruypro/v2/Lly5_p-p02_q0qVnCjK_s1d-S-d_r-T_y-g.woff2') format('woff2'); font-weight: 600; }
        @font-face { font-family: 'Kantumruy Pro'; src: url('https://fonts.gstatic.com/s/kantumruypro/v2/Lly5_p-p02_q0qVnCjK_s1d-S-d_r-T_y-C.woff2') format('woff2'); font-weight: 700; }
        @font-face { font-family: 'Tacteing'; src: url('Tacteing.ttf') format('truetype'); font-weight: normal; }

        :root {
            --khmer-blue: #2c5282;
            --khmer-gold: #DAA520;
            --khmer-light-blue: #ADD8E6;
            --text-color-dark: #333;
            --text-color-light: #555;
            --main-heading-color: #1a4d8c;
            --recipient-name-color: #4f46e5;
        }

        body {
            font-family: 'Kantumruy Pro', sans-serif;
            background-color: #f0f2f5;
            display: block;
            min-height: 100vh;
            width: 100%;
            margin: 0;
            padding: 20px;
            color: var(--text-color-dark);
            font-size: 14pt;
        }

        /* --- STYLES FOR INLINE EDITING --- */
        [contenteditable="true"] {
            transition: all 0.2s;
            cursor: text;
            border-radius: 4px;
        }
        [contenteditable="true"]:hover {
            outline: 1px dashed #2934eb;
            background-color: rgba(41, 52, 235, 0.05);
        }
        [contenteditable="true"]:focus {
            outline: 2px solid #2934eb;
            background-color: #fff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 100;
        }
        /* Saving indicator */
        .save-status {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px 20px;
            border-radius: 5px;
            background: #333;
            color: white;
            opacity: 0;
            transition: opacity 0.5s;
            z-index: 9999;
        }
        .save-status.show { opacity: 1; }
        .save-status.success { background: #28a745; }
        .save-status.error { background: #dc3545; }

        /* Print Button */
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background: #2b36f3;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
        }
        .print-btn:hover { background: #1a24b0; }
        /* -------------------------------- */

        .certificate-page {
            width: 297mm;
            height: 210mm;
            background-color: white;
            border: 1px solid #ccc;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            padding: 0;
            box-sizing: border-box;
        }

        .certificate-bg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            z-index: 1;
        }

        .bg-selector {
            width: 100%;
            display: flex;
            gap: 10px;
            padding: 8px 12px;
            overflow-x: auto;
            align-items: center;
            box-sizing: border-box;
            margin-bottom: 12px;
        }
        .bg-thumb {
            width: 92px;
            height: 62px;
            object-fit: cover;
            border: 2px solid rgba(0,0,0,0.08);
            border-radius: 6px;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .bg-thumb.active {
            outline: 3px solid #2b36f3;
            transform: translateY(-2px);
        }

        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tab-link {
            padding: 10px 20px;
            background: #ddd;
            text-decoration: none;
            color: #333;
            border-radius: 5px;
        }
        .tab-link.active {
            background: #2b36f3;
            color: white;
        }

        .certificate-content {
            position: relative;
            z-index: 10;
            flex-grow: 1; 
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            width: calc(100% - 20mm);
            height: calc(100% - 20mm);
            padding: 15mm 20mm;
            box-sizing: border-box;
        }

        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            width: 100%;
            margin-bottom: 25mm;
        }

        .logo-container {
            text-align: left;
            flex: 1;
            margin-top: 1rem;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin-left: -1rem;
        }
        .company-logo {
            width: 55mm;
            height: auto;
        }
        
        .title-section {
            text-align: center;
            flex: 2;
            padding-top: 5mm;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .main-title {
            font-family: 'Khmer OS Muol Light', sans-serif;
            width: 100%;
            position: relative;
            top: 1rem;
            font-size: 36pt;
            font-weight: 700;
            color: #2934eb;
            margin: 0;
            letter-spacing: 1px;
            margin-bottom: 5mm;
        }
        .sub-title {
            font-size: 20px;
            font-family: 'Kh Muol', sans-serif;
            width: 100%;
            position: relative;
            top: -5rem;
            color: var(--text-color-dark);
            margin: 0;
            letter-spacing: 0.5px;
            display: flex;
            flex-direction: row;
        }
        .sub-title2 {
            font-size: 18pt;
            font-family: 'Kh Muol', sans-serif;
            width: 100%;
            position: relative;
            top: -8rem;
            margin: 0;
            letter-spacing: 0.5px;
            text-align: center;
        }

        .sub-title h5 {
            font-size: 20px;
            font-family: 'Khmer OS Muol Light', sans-serif;
            position: relative;
            color: var(--text-color-dark);
            margin: 0;
            letter-spacing: 0.5px;
        }

        .photo-container {
            flex: 1;
            text-align: right;
            display: flex;
            justify-content: flex-end;
            align-items: flex-start;
        }
        .recipient-photo {
            width: 35mm;
            height: 45mm;
            display: block;
            object-fit: cover;
            border-radius: 6px;
            border: 3px solid var(--khmer-gold);
            background-color: #fff;
            box-shadow: 0 8px 20px rgba(0,0,0,0.18);
            margin-top: 8mm;
            margin-right: -4mm;
            transition: transform 0.18s ease;
        }

        /* RESTORED ORIGINAL STYLE HERE */
        .body-section {
            text-align: center;
            position: relative;
            top: -5rem;
            width: 200%;
        }
        .description-text {
            font-size: 16pt;
            position: relative;
            top: -4rem;
            font-family: 'Kh Battambang';
            line-height: 1.8;
            margin: 0 auto;
            max-width: 200%;
            color: black;
        }
        /* -------------------------- */
        
        .tacteing-number {
            font-family: 'Tacteing', sans-serif;
            position: relative;
            top: -3rem;
            font-size: 90pt;
            color: #2934eb;
        }
        .seal-rank {
            font-family: 'Khmer OS Muol Light', 'Kh Muol', sans-serif;
            font-size: 28pt;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-width: 60px;
            min-height: 60px;
            aspect-ratio: 1 / 1;
            line-height: normal;
            font-weight: 700;
            color: #ffffff;
            background: rgba(41,52,235,0.85);
            padding: 0;
            border-radius: 50%;
            position: absolute;
            z-index: 12;
            top: 72.5%;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            box-sizing: border-box;
            overflow: hidden;
        }

        .footer-section {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            margin-top: auto;
            padding-right: 20mm;
            padding-left: 20mm;
            position: relative;
        }
        .footer-date {
            font-size: 12pt;
            text-align: center;
            font-family: 'Kh Battambang', sans-serif;
            line-height: 1.5;
            position: relative;
            top: -14rem;
            color: black;
        }
        .signature-block {
            width: 100%;
            flex-direction: column;
            left: -5rem;
            align-items: flex-end;
            position: relative;
            display: flex;
            top: -9rem;
            justify-content: space-between;
            gap: 20mm;
        }
        .signature-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex-basis: 35%;
            max-width: 200px;
            min-width: 120px;
        }
        .signature-title {
            font-size: 18pt;
            position: relative;
            margin-top: -4.5rem;
            text-align: right;
            font-family: 'Khmer OS Muol Light';
            color: var(--text-color-dark);
        }
        .signature-name {
            font-size: 18pt;
            width: 100%;
            font-family: 'Khmer OS Muol Light';
            text-align: right;
            position: relative;
            margin-left: 5rem;
            color: var(--text-color-dark);
        }
        .signature-img img {
            width: 150px;
            height: auto;
        }
        
        @page { size: A4 landscape; margin: 0; }
        
        @media print {
            .print-btn, .bg-selector, .nav-tabs, .save-status { display: none !important; }
            /* Hide edit outlines in print */
            [contenteditable="true"] { outline: none !important; background: none !important; }
            
            html, body { margin: 0; padding: 0; background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; font-size: 14pt; }
            .certificate-page { width: 297mm; height: 210mm; margin: 0; padding: 0; border: none; box-shadow: none; page-break-after: always; display: block !important; position: relative; }
            .certificate-wrapper { page-break-inside: avoid; margin: 0; padding: 0; background: white !important; border: none !important; display: block !important; }
            .certificate-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: contain; }
            
            /* Print Specific Positioning Matches Screen */
            .main-title { top: 1rem; left: 1.5rem;}
            .sub-title { top: -6rem; white-space: nowrap; overflow: visible; left: 1.5rem; }
            .sub-title2 { top: -9rem; left: 1.5rem;}
            
            /* RESTORED ORIGINAL PRINT STYLE */
            .body-section {
                text-align: center;
                position: relative;
                top: -5rem;
                width: 200%;
            }
            .photo-container{
                position: relative;
                left: 2rem;
            }
            .tacteing-number{
                position: relative;
                left: 1.5rem;
            }
            .description-text {
                font-size: 16pt;
                position: relative;
                left: 1.5rem;
                top: -5rem;
                font-family: 'Kh Battambang';
                line-height: 1.8;
                margin: 0 auto;
                max-width: 100%;
                color: black;
                text-align: center;
            }

            .logo-container{
                margin-left: 1rem;
            }
            /* -------------------------------- */

            .footer-date { top: -13rem; left: 4rem; position: relative; }
            .signature-block { top: -9rem; }
            .signature-title { margin-top: -3.5rem; left: 3rem; }
            .signature-name { margin-left: 11rem; margin-top: 5rem; }
            .seal-rank { left: 50.9%; top: 80.5%; }
            .signature-img img { left: 87%; position: absolute; }
        }

        @media screen {
            .certificates-container { display: block; width: 100%; }
            .certificate-wrapper { width: 297mm; max-width: 100%; display: block; margin: 0 auto 50px auto; border: 2px solid #ddd; }
        }
    </style>
</head>
<body>
    <!-- Print Button (Replaces Auto-Print) -->
    <button class="print-btn" onclick="window.print()"><i class="fa-solid fa-print"></i> បោះពុម្ព (Print)</button>
    <div id="saveStatus" class="save-status">កំពុងរក្សាទុក...</div>

    <?php if (!empty($available_frames)): ?>
        <div class="bg-selector" id="bgSelector">
            <?php foreach ($available_frames as $frame): ?>
                <img src="<?php echo htmlspecialchars($frame); ?>" data-src="<?php echo htmlspecialchars($frame); ?>" class="bg-thumb<?php echo ($frame === $selected_bg) ? ' active' : ''; ?>" alt="frame">
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="nav-tabs">
        <a href="?poll_id=<?php echo $poll_id; ?>&winner_id=<?php echo $winner_id; ?>&bg=<?php echo urlencode($selected_bg); ?>&type=worker" class="tab-link <?php echo $type === 'worker' ? 'active' : ''; ?>">កម្មករ</a>
        <a href="?poll_id=<?php echo $poll_id; ?>&winner_id=<?php echo $winner_id; ?>&bg=<?php echo urlencode($selected_bg); ?>&type=specialist" class="tab-link <?php echo $type === 'specialist' ? 'active' : ''; ?>">ជំនាញ</a>
    </div>

    <div class="certificates-container">
    <?php if (!empty($top_winners)): ?>
        <?php foreach ($top_winners as $index => $winner): 
            // Unique IDs for JS saving
            $wid = $winner['user_id'];
        ?>
    <div class="certificate-wrapper">
        <div class="certificate-page">
            <img src="<?php echo htmlspecialchars($selected_bg); ?>" alt="Background" class="certificate-bg">

            <div class="certificate-content">
            <div class="header-section">
                <div class="logo-container">
                    <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="Company Logo" class="company-logo">
                </div>
                <div class="title-section">
                    
                    <!-- EDITABLE: MAIN TITLE -->
                    <h1 class="main-title" contenteditable="true" 
                        data-winner="<?php echo $wid; ?>" data-field="title_main">
                        <?php echo $winner['title_main']; ?>
                    </h1>

                    <div class="title-combined">
                        <span class="tacteing-number">3</span>
                        <div class="sub-title">
                            <!-- EDITABLE: SUB TITLE 1 -->
                            <h4 style="width: 100%;" contenteditable="true"
                                data-winner="<?php echo $wid; ?>" data-field="title_sub1">
                                <?php echo $winner['title_sub1']; ?>
                            </h4>
                        </div>
                    <div class="sub-title2">
                        <!-- EDITABLE: SUB TITLE 2 -->
                        <h5 contenteditable="true"
                            data-winner="<?php echo $wid; ?>" data-field="title_sub2">
                            <?php echo $winner['title_sub2']; ?>
                        </h5>
                    </div>
                    </div>
                    <div class="body-section">
                        <!-- EDITABLE: DESCRIPTION (BODY) -->
                        <span class="description-text" contenteditable="true"
                              data-winner="<?php echo $wid; ?>" data-field="description">
                            <?php echo $winner['description']; ?>
                        </span>
                    </div>
                </div>
                <div class="photo-container">
                    <img src="<?php echo $winner['image_url']; ?>" alt="Recipient Photo" class="recipient-photo">
                </div>
            </div>

            <span class="seal-rank"><?php echo !$winner['is_worker'] ? '១' : convertToKhmerNumerals(isset($winner['rank_num']) ? $winner['rank_num'] : preg_replace('/\D/','',$winner['rank'])); ?></span>

            <div class="footer-section">
                <!-- EDITABLE: DATE -->
                <span class="footer-date" contenteditable="true"
                      data-winner="<?php echo $wid; ?>" data-field="date_text">
                    <?php echo $winner['date_text']; ?>
                </span>
            </div>
            <span class="signature-block">
                <span class="signature-item">
                    <!-- EDITABLE: SIGNATURE TITLE -->
                    <span class="signature-title" contenteditable="true"
                          data-winner="<?php echo $wid; ?>" data-field="sig_title">
                        <?php echo $winner['sig_title']; ?>
                    </span>
                    <span class="signature-img"><img src="/frame/sign.png" alt=""></span>
                    <!-- EDITABLE: SIGNATURE NAME -->
                    <span class="signature-name" contenteditable="true"
                          data-winner="<?php echo $wid; ?>" data-field="sig_name">
                        <?php echo $winner['sig_name']; ?>
                    </span>
                </span>
            </span>
        </div>
    </div>
    </div> 
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="certificate-page">
        <div class="certificate-content">
            <h2 style="text-align: center; color: red;">មិនមានទិន្នន័យអ្នកឈ្នះទេ</h2>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // 1. Background Logic
        (function(){
            var thumbs = document.querySelectorAll('.bg-thumb');
            if (!thumbs || thumbs.length === 0) return;
            function setBg(src){
                document.querySelectorAll('.certificate-bg').forEach(function(img){ img.src = src; });
                thumbs.forEach(function(t){ t.classList.toggle('active', t.getAttribute('data-src') === src); });
                try {
                    var url = new URL(window.location.href);
                    url.searchParams.set('bg', src);
                    window.history.replaceState({}, '', url.toString());
                } catch(e) {}
            }
            thumbs.forEach(function(t){
                t.addEventListener('click', function(){ setBg(this.getAttribute('data-src')); });
            });
        })();

        // 2. INLINE EDIT & SAVE LOGIC (AJAX)
        document.addEventListener('DOMContentLoaded', function() {
            const statusBox = document.getElementById('saveStatus');
            let timeoutId;

            // Helper to show status
            function showStatus(msg, type) {
                statusBox.textContent = msg;
                statusBox.className = 'save-status show ' + type;
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => { statusBox.className = 'save-status'; }, 2000);
            }

            // Listen for blur event (focus lost) on editable elements
            const editables = document.querySelectorAll('[contenteditable="true"]');
            
            editables.forEach(el => {
                // Prevent rich text paste mess, keep it cleaner
                el.addEventListener('paste', function(e) {
                    e.preventDefault();
                    var text = (e.originalEvent || e).clipboardData.getData('text/plain');
                    document.execCommand('insertText', false, text);
                });

                // Save on Blur
                el.addEventListener('blur', function() {
                    const content = this.innerHTML; // Save HTML to keep formatting like <br> or spans
                    const field = this.getAttribute('data-field');
                    const winnerId = this.getAttribute('data-winner');
                    const pollId = "<?php echo $poll_id; ?>"; // From PHP

                    if(!field || !winnerId) return;

                    // Send to PHP via fetch
                    const formData = new FormData();
                    formData.append('action', 'save_inline_edit');
                    formData.append('poll_id', pollId);
                    formData.append('winner_id', winnerId);
                    formData.append('field', field);
                    formData.append('content', content);

                    showStatus('កំពុងរក្សាទុក...', '');

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if(data.status === 'success') {
                            showStatus('បានរក្សាទុក!', 'success');
                        } else {
                            showStatus('បរាជ័យ: ' + (data.message || ''), 'error');
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        showStatus('Error saving', 'error');
                    });
                });
            });
        });
    </script>
</body>
</html>