<?php
// Configuration, Session, Filters, etc. remain the same
define('BYPASS_AUTH', true);
session_start();
$startDate = $_GET['startDate'] ?? date('Y-m-01');
$endDate = $_GET['endDate'] ?? date('Y-m-t');
$employeeType = $_GET['employee_type'] ?? 'all';
function validateDate($date, $format = 'Y-m-d')
{
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

try {
    // Database connection
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db;charset=utf8mb4", 'samann1_scan_logs_worker_db', 'scan_logs_worker_db@2025', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    // The SQL structure remains the same
    $sql = "
        SELECT
            AggregatedResults.*,
            u.gender,
            u.position
        FROM (
            WITH DailyScanCounts AS (
                SELECT
                    user_id,
                    DATE(timestamp) AS scan_date,
                    MIN(username) AS username,
                    MIN(folder) AS folder,
                    MIN(branch) AS branch,
                    SUM(CASE WHEN action = 'Check-In' THEN 1 ELSE 0 END) AS total_in,
                    SUM(CASE WHEN action = 'Check-Out' THEN 1 ELSE 0 END) AS total_out
                FROM scan_logs
                WHERE 
                    timestamp BETWEEN ? AND ?
                    AND action IN ('Check-In', 'Check-Out')
                GROUP BY user_id, DATE(timestamp)
            )
            SELECT
                dsc.user_id,
                dsc.username,
                MIN(dsc.folder) AS folder, 
                MIN(dsc.branch) AS branch,
                SUM(
                    IF( (dsc.total_in + dsc.total_out) >= 4, 0, GREATEST(0, 2 - dsc.total_in) )
                ) AS forgot_check_in,
                SUM(
                    IF( (dsc.total_in + dsc.total_out) >= 4, 0, GREATEST(0, 2 - dsc.total_out) )
                ) AS forgot_check_out
            FROM DailyScanCounts AS dsc
            GROUP BY dsc.user_id, dsc.username
        ) AS AggregatedResults
        LEFT JOIN users AS u ON AggregatedResults.user_id = u.user_id
    ";

    $params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];

    $filter_conditions = [];

    if ($employeeType === 'skilled') {
        $skilledFolders = ['ឃ្លាំង', 'ជំនាញ', 'ហាងទំនិញ៣១៨', 'SK Chuk Meas'];
        $placeholders = implode(',', array_fill(0, count($skilledFolders), '?'));
        $filter_conditions[] = "AggregatedResults.folder IN ($placeholders)";
        $params = array_merge($params, $skilledFolders);
    } elseif ($employeeType === 'worker') {
        $filter_conditions[] = "AggregatedResults.folder = ?";
        $params[] = 'កម្មករ';
    }
    if ($is_sub_user && $branch_filter) {
        $filter_conditions[] = "AggregatedResults.branch = ?";
        $params[] = $branch_filter;
    }

    if (!empty($filter_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $filter_conditions);
    }

    $sql .= " ORDER BY (AggregatedResults.forgot_check_in + AggregatedResults.forgot_check_out) DESC, AggregatedResults.username ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $sumForgotIn = 0;
    $sumForgotOut = 0;
    foreach ($users as &$user) {
        $user['total'] = ($user['forgot_check_in'] ?? 0) + ($user['forgot_check_out'] ?? 0);
        $sumForgotIn += $user['forgot_check_in'] ?? 0;
        $sumForgotOut += $user['forgot_check_out'] ?? 0;
    }
    unset($user);
    $sumTotal = $sumForgotIn + $sumForgotOut;
} catch (PDOException $e) {
    exit('Database Error: ' . $e->getMessage());
} catch (Exception $e) {
    exit('General Error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>របាយការណ៍ភ្លេចស្កេន</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.7/js/bootstrap.min.js" integrity="sha512-zKeerWHHuP3ar7kX2WKBSENzb+GJytFSBL6HrR2nPSR1kOX1qjm+oHooQtbDpDBSITgyl7QXZApvDfDWvKjkUw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        /* All existing CSS remains the same */
        body {
            font-family: "kh battambang", "Battambang", sans-serif;
            font-size: 12pt;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
        }

        .report-page {
            width: 310mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 0 auto 20px auto;
            background: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .report-header img {
            max-width: 500px;
            margin-top: -2rem;
            display: block;
            margin: auto;
        }

        .report-title {
            background-color: #192c4f;
            color: white;
            padding-top: 10px;
            padding: 1px 0;
            text-align: center;
            margin-top: -1rem;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .report-title h2 {
            font-family: Khmer OS Muol Light;
            font-size: 28px;
            color: #f1c011;
            font-weight: 200;
        }

        .report-title h4 {
            font-family: Khmer OS Muol Light;
            color: #f1c011;
            margin-top: -0.5rem;
            font-size: 14px;
            font-weight: 200;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: -1.2rem;
        }

        .report-table th,
        .report-table td {
            border: 1px solid black;
            font-size: 13pt;
            text-align: center;
            border-top: none;
            padding: 4px;
        }

        .report-table th {
            background-color: #f1c011;
            color: black;
            font-weight: bold;
        }

        .report-table tbody tr:nth-child(even) td {
            background-color: #f9f9f9;
        }

        .highlight-gold td {
            background-color: #fdea0f !important;
            font-weight: bold;
        }

        .highlight-red td {
            background-color: red !important;
            font-weight: bold;
            color: white !important;
        }

        .report-footer {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            position: relative;
            margin-top: 2rem;
        }

        .report-footer p {
            text-align: center;
            width: 300px;
            margin: 4px 0;
            font-size: 12px;
        }

        .filter-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .filter-container form {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            align-items: flex-end;
            justify-content: center;
        }

        .filter-container label {
            font-family: 'Noto Sans Khmer', sans-serif;
            font-size: 1rem;
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
            display: block;
        }

        .filter-container .form-control,
        .filter-container .form-select {
            border: 1px solid #ced4da;
            border-radius: 6px;
            padding: 10px;
            font-size: 1rem;
            font-family: 'Noto Sans Khmer', sans-serif;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .filter-container .form-control:focus,
        .filter-container .form-select:focus {
            border-color: #007bff;
            box-shadow: 0 0 5px rgba(0, 123, 255, 0.3);
            outline: none;
        }
        
        .filter-container div {
            flex: 1;
            min-width: 200px;
        }

        @media (max-width: 768px) {
            .filter-container form {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-container div {
                min-width: 100%;
            }
        }

        @media print {
            @page { size: A4 portrait; margin: 0; }
            body { background: none; }
            .filter-container, .no-print, .custom-popup-wrapper, .loading-overlay { display: none; }
            .report-page { width: 210mm; min-height: 297mm; padding: 20mm; margin: 0; box-shadow: none; background: white; }
            .report-title { background-color: #192c4f !important; color: white !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>

<body>
    <div class="filter-container">
        <form method="GET" action="" class="d-flex align-items-end gap-3" style="width: 100%;">
            <div> <label for="startDate">ពីថ្ងៃទី:</label> <input type="date" id="startDate" name="startDate" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>" onchange="this.form.submit()"> </div>
            <div> <label for="endDate">ដល់ថ្ងៃទី:</label> <input type="date" id="endDate" name="endDate" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>" onchange="this.form.submit()"> </div>
            <div> <label for="employee_type">ប្រភេទបុគ្គលិក:</label> <select id="employee_type" name="employee_type" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?php if ($employeeType === 'all') echo 'selected'; ?>>ទាំងអស់</option>
                    <option value="skilled" <?php if ($employeeType === 'skilled') echo 'selected'; ?>>បុគ្គលិកជំនាញ</option>
                    <option value="worker" <?php if ($employeeType === 'worker') echo 'selected'; ?>>បុគ្គលិកកម្មករ</option>
                </select> </div>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Create and Inject CSS for the Modal & Popups ---
            const customStyles = `
                /* --- Modern & Clean Modal Style --- */
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

                /* --- Animated Alert (Toast/Snackbar) Style --- */
                .custom-popup-wrapper { position: fixed; top: 20px; left: 50%; z-index: 10000; display: flex; align-items: center; background-color: #fff; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.15); padding: 14px 20px; min-width: 320px; max-width: 90%; font-family: 'kh battambang', sans-serif; animation: slideDown 0.4s ease forwards; }
                .custom-popup-wrapper.hide { animation: slideUp 0.4s ease forwards; }
                .popup-icon { flex-shrink: 0; width: 24px; height: 24px; margin-right: 12px; }
                .popup-message { font-size: 15px; font-weight: 500; color: #333; }
                .popup-close { position: absolute; top: 8px; right: 8px; cursor: pointer; color: #aaa; background: none; border: none; font-size: 20px; line-height: 1; }
                .custom-popup-wrapper.success { border-left: 5px solid #28a745; }
                .custom-popup-wrapper.error { border-left: 5px solid #dc3545; }
                @keyframes slideDown { from { opacity: 0; transform: translate(-50%, -20px); } to { opacity: 1; transform: translate(-50%, 0); } }
                @keyframes slideUp { from { opacity: 1; transform: translate(-50%, 0); } to { opacity: 0; transform: translate(-50%, -20px); } }

                /* --- Loading Overlay Style --- */
                .loading-overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); z-index: 99999; display: flex; align-items: center; justify-content: center; }
                .loading-box { background: white; color: #192c4f; padding: 25px 40px; border-radius: 12px; text-align: center; box-shadow: 0 5px 20px rgba(0,0,0,0.2); }
                .loading-box .spinner { width: 40px; height: 40px; border: 4px solid rgba(0, 0, 0, 0.1); border-left-color: #2563eb; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 15px auto; }
                .loading-box p { font-size: 16px; font-weight: bold; margin: 0; font-family: 'kh battambang', sans-serif; }
                @keyframes spin { to { transform: rotate(360deg); } }
            `;
            const styleSheet = document.createElement("style");
            styleSheet.innerText = customStyles;
            document.head.appendChild(styleSheet);


            // --- Modal HTML ---
            const modalHtml = `
            <div id="captionModal" class="tg-modal">
              <div class="tg-modal-dialog">
                <div class="tg-modal-header">
                  <h5 class="tg-modal-title">បញ្ចូល Caption</h5>
                  <button type="button" class="tg-modal-close-btn" id="closeModalBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                  </button>
                </div>
                <div class="tg-modal-body">
                  <label for="captionInput">សូមបញ្ចូល Caption ដែលត្រូវភ្ជាប់ជាមួយរូបភាព:</label>
                  <textarea id="captionInput" rows="4"></textarea>
                </div>
                <div class="tg-modal-footer">
                  <button type="button" class="tg-btn tg-btn-secondary" id="cancelModalBtn">បោះបង់</button>
                  <button type="button" class="tg-btn tg-btn-primary" id="okModalBtn">យល់ព្រម</button>
                </div>
              </div>
            </div>`;
            document.body.insertAdjacentHTML('beforeend', modalHtml);


            // Create the Telegram button
            const btn = document.createElement('button');
            btn.textContent = 'ផ្ញើរបាយការណ៍ទៅ Telegram';
            btn.type = 'button';
            btn.className = 'no-print tg-btn tg-btn-primary';
            btn.style.display = 'block';
            btn.style.margin = '24px auto';
            btn.style.fontWeight = 'bold';
            btn.style.fontSize = '17px';
            btn.style.padding = '12px 32px';

            const filterContainer = document.querySelector('.filter-container');
            filterContainer.parentNode.insertBefore(btn, filterContainer.nextSibling);

            // --- Reusable Functions for Popups and Modals ---
            function showModal(defaultCaption) {
                return new Promise((resolve) => {
                    const modal = document.getElementById('captionModal');
                    const input = document.getElementById('captionInput');
                    const okBtn = document.getElementById('okModalBtn');
                    const cancelBtn = document.getElementById('cancelModalBtn');
                    const closeBtn = document.getElementById('closeModalBtn');

                    input.value = defaultCaption || '';
                    requestAnimationFrame(() => {
                        modal.classList.add('visible');
                        input.focus();
                        input.select();
                    });

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
                wrapper.innerHTML = `
                    ${icons[type]}
                    <span class="popup-message">${message}</span>
                    <button class="popup-close">×</button>
                `;
                document.body.appendChild(wrapper);
                currentPopup = wrapper;

                const closePopup = () => {
                    wrapper.classList.add('hide');
                    wrapper.addEventListener('animationend', () => { 
                        if (wrapper.parentElement) wrapper.remove();
                        if (currentPopup === wrapper) currentPopup = null;
                    });
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

            // --- Main Button Click Event ---
            btn.addEventListener('click', async function() {
                const defaultCaption = `របាយការណ៍បុគ្គលិកភ្លេចស្កេន\nគិតពីថ្ងៃទី <?php echo date('d-m-Y', strtotime($startDate)); ?> ដល់ <?php echo date('d-m-Y', strtotime($endDate)); ?>`;
                const caption = await showModal(defaultCaption);
                if (caption === null) return; 

                btn.disabled = true;
                const loadingOverlay = showLoading('កំពុងបង្កើតរូបភាព...');
                
                if (typeof html2canvas === 'undefined') {
                    const script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js';
                    document.body.appendChild(script);
                    await new Promise(resolve => { script.onload = resolve; });
                }

                try {
                    const reportPage = document.querySelector('.report-page');
                    btn.style.display = 'none';
                    window.scrollTo(0, 0);

                    const canvas = await html2canvas(reportPage, { scale: 2, useCORS: true, backgroundColor: '#ffffff' });
                    
                    btn.style.display = 'block';
                    loadingOverlay.querySelector('p').textContent = 'កំពុងផ្ញើទៅ Telegram...';

                    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
                    if (!blob) throw new Error('បរាជ័យក្នុងការបង្កើតរូបភាព។');

                    const BOT_TOKEN = '8099151515:AAH8QLSSdnPJKFq-nLuR1zIH-JfYpzirsag';
                    const CHAT_ID = '-1001167276739';
                    const THREAD_ID = ' 191829'; // <-- បញ្ចូល thread id នៅទីនេះ

                    const formData = new FormData();
                    formData.append('chat_id', CHAT_ID);
                    formData.append('caption', caption);
                    formData.append('photo', blob, 'report.png');
                    formData.append('message_thread_id', THREAD_ID); // បន្ថែម thread id

                    const response = await fetch(`https://api.telegram.org/bot${BOT_TOKEN}/sendPhoto`, {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    
                    if (result.ok) {
                        showCustomAlert('success', 'ផ្ញើរបានជោគជ័យ!');
                    } else {
                        throw new Error(result.description || 'Unknown Telegram error');
                    }
                } catch (e) {
                    showCustomAlert('error', 'បរាជ័យ: ' + e.message);
                } finally {
                    loadingOverlay.remove();
                    btn.disabled = false;
                }
            });
        });
    </script>

    <div class="report-page">
        <div class="report-header text-center"> <img src="https://i.ibb.co/7x90kJJk/Logo-Van-Van-2.png" alt="logo" /> </div>
        <div class="report-title">
            <h2>របាយការណ៍បុគ្គលិកភ្លេចស្កេន</h2>
            <h4>សម្រាប់បុគ្គលិកជំនាញៗ និងតាមឃ្លាំង</h4>
            <h4 style="font-family: kh battambang;"> គិតចាប់ពីថ្ងៃទី <?php echo date('d-m-Y', strtotime($startDate)); ?> ដល់ថ្ងៃទី <?php echo date('d-m-Y', strtotime($endDate)); ?> </h4>
        </div>

        <table class="report-table">
            <thead>
                <tr>
                    <th rowspan="2">ល.រ</th>
                    <th rowspan="2">អត្តលេខ</th>
                    <th rowspan="2">ឈ្មោះ</th>
                    <th rowspan="2">ភេទ</th>
                    <th rowspan="2">តួនាទី</th>
                    <th colspan="2">ភ្លេចស្កេនមេដៃ</th>
                    <th rowspan="2">សរុប</th>
                </tr>
                <tr>
                    <th style="background-color: #192c4f; color: white;">ចូល</th>
                    <th style="background-color: #192c4f; color: white;">ចេញ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if (empty($users)) {
                    echo '<tr><td colspan="8" style="text-align: center;">មិនមានទិន្នន័យសម្រាប់បង្ហាញទេ។</td></tr>';
                } else {
                    $index = 1;
                    foreach ($users as $user) {
                        $row_class = '';
                        if (($user['total'] ?? 0) >= 5) {
                            $row_class = 'highlight-red';
                        } elseif (($user['total'] ?? 0) >= 2) {
                            $row_class = 'highlight-gold';
                        }
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
                <tr id="summary-row">
                    <th colspan="5" style="text-align: center; background-color: #f1c011;">សរុប</th>
                    <th id="sum-forgot-in"><?php echo htmlspecialchars($sumForgotIn); ?></th>
                    <th id="sum-forgot-out"><?php echo htmlspecialchars($sumForgotOut); ?></th>
                    <th id="sum-total"><?php echo htmlspecialchars($sumTotal); ?></th>
                </tr>
            </tbody>
        </table>

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
</body>
</html>