<?php
include 'includes/auth.php';
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'អ្នកមិនមានសិទ្ធិចូលទំព័រនេះទេ។';
    header("Location: dashboard.php");
    exit();
}

include 'includes/db.php';
$conn = include 'includes/db.php';
date_default_timezone_set('Asia/Phnom_Penh');

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');

// If no user_id provided, fallback to the first active employee to avoid redirect loops
if ($user_id <= 0) {
    try {
        $stmt_first = $conn->query("SELECT id FROM users WHERE status = 'active' ORDER BY full_name ASC LIMIT 1");
        $first_id = (int)$stmt_first->fetchColumn();
        if ($first_id > 0) {
            $user_id = $first_id;
        } else {
            $_SESSION['error'] = 'មិនមានបុគ្គលិកសកម្មសម្រាប់បង្ហាញលម្អិត។';
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'បញ្ហាពេលស្វែងរកបុគ្គលិកដើម: ' . $e->getMessage();
    }
}

try {
    $stmt_user = $conn->prepare("SELECT id, full_name, base_salary, department, role, bank_name, bank_account_number, bank_qr_code_url FROM users WHERE id = ?");
    $stmt_user->execute([$user_id]);
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $_SESSION['error'] = 'រកមិនឃើញទិន្នន័យបុគ្គលិក';
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'បញ្ហាពេលទាញយកទិន្នន័យបុគ្គលិក: ' . $e->getMessage();
}

$page_title = 'លម្អិតប្រាក់ខែ' . (!empty($user['full_name']) ? ' — ' . htmlspecialchars($user['full_name']) : '');

$bonus_details = [];
$deduction_details = [];
$base_salary = (float)$user['base_salary'];
$department = $user['department'];
$daily_rate_worker = $base_salary > 0 ? $base_salary / 28 : 0;
$daily_rate_staff = $base_salary > 0 ? $base_salary / 26 : 0;

$ot_bonus_total = 0.0;
$deduction_total = 0.0;

// Approved requests in the month
try {
    $sql_requests = "SELECT request_type, reason, request_date FROM requests WHERE status = 'approved' AND requester_name = (SELECT full_name FROM users WHERE id = :uid) AND YEAR(request_date) = :year AND MONTH(request_date) = :month";
    $stmt_requests = $conn->prepare($sql_requests);
    $stmt_requests->execute([':uid' => $user_id, ':year' => $current_year, ':month' => $current_month]);
    $requests = $stmt_requests->fetchAll(PDO::FETCH_ASSOC);

    foreach ($requests as $request) {
        switch ($request['request_type']) {
            case 'ថែមម៉ោង (OT)':
                $ot_amount_base = ($department === 'Worker') ? $daily_rate_worker : $daily_rate_staff;
                $ot_amount = $ot_amount_base * 0.5; // Half-day OT
                $ot_bonus_total += $ot_amount;
                $bonus_details[] = "OT 0.5 ថ្ងៃ (តាមសំណុំ) — " . date('d-M', strtotime($request['request_date'])) . ": $" . number_format($ot_amount, 2);
                break;
            case 'ភ្លេចស្កេនមេដៃ (Forgot FP)':
                $deduction_total += 1.00;
                $deduction_details[] = "Forgot FP — " . date('d-M', strtotime($request['request_date'])) . ": -$1.00";
                break;
            case 'សម្រាកប្រចាំឆ្នាំ (Annual Leave)':
                if ($department === 'Worker') {
                    $ded_amount = $daily_rate_worker;
                    $deduction_total += $ded_amount;
                    $deduction_details[] = "Annual Leave — " . date('d-M', strtotime($request['request_date'])) . ": -$" . number_format($ded_amount, 2);
                }
                break;
        }
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'បញ្ហាពេលទាញ Requests: ' . $e->getMessage();
}

// Manual OT bonuses for the month
try {
    $ot_month_str = $current_year . '-' . str_pad($current_month, 2, '0', STR_PAD_LEFT);
    $stmt_ot = $conn->prepare("SELECT ot_amount, reason FROM ot_bonuses WHERE user_id = :uid AND ot_month = :m");
    $stmt_ot->execute([':uid' => $user_id, ':m' => $ot_month_str]);
    $ots = $stmt_ot->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ots as $ot) {
        $amt = (float)$ot['ot_amount'];
        $ot_bonus_total += $amt;
        $text = "OT (បញ្ចូលដោយដៃ): $" . number_format($amt, 2);
        if (!empty($ot['reason'])) $text .= " (" . htmlspecialchars($ot['reason']) . ")";
        $bonus_details[] = $text;
    }
} catch (PDOException $e) {
    // non-blocking
}

// Other deductions in the month
try {
    $stmt_od = $conn->prepare("SELECT amount, reason, deduction_date FROM other_deductions WHERE user_id = :uid AND YEAR(deduction_date) = :year AND MONTH(deduction_date) = :month ORDER BY deduction_date ASC");
    $stmt_od->execute([':uid' => $user_id, ':year' => $current_year, ':month' => $current_month]);
    $ods = $stmt_od->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ods as $od) {
        $amt = (float)$od['amount'];
        $deduction_total += $amt;
        $deduction_details[] = htmlspecialchars($od['reason']) . " — " . date('d-M', strtotime($od['deduction_date'])) . ": -$" . number_format($amt, 2);
    }
} catch (PDOException $e) {
    // non-blocking
}

$net_salary = $base_salary + $ot_bonus_total - $deduction_total;

try {
    $stmt_req_count = $conn->query("SELECT COUNT(*) FROM requests WHERE status = 'pending'");
    $pendingRequestsCount = $stmt_req_count->fetchColumn();
} catch(PDOException $e) {
    $pendingRequestsCount = 0;
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Management - <?php echo $page_title; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
    <style>
        :root {
            --primary-bg: #161b22;
            --secondary-bg: #0d1117;
            --card-bg: rgba(22, 27, 34, 0.9);
            --border-color: rgba(255, 255, 255, 0.1);
            --accent-color: #ffd700;
            --accent-hover: #ffea70;
            --text-primary: #f0f6fc;
            --text-secondary: #ffffff;
            --success: #2ea043;
            --danger: #da3633;
            --warning: #ffd700;
        }

        body { background-color: var(--primary-bg); font-family: 'Noto Sans Khmer', 'Poppins', sans-serif; color: var(--text-primary); }
        aside { background-color: var(--secondary-bg); border-right: 1px solid var(--border-color); }
        aside h2 { color: var(--accent-hover); }
        aside a, aside button { color: var(--text-secondary); transition: all 0.2s ease; border-left: 4px solid transparent; padding: 14px 12px; font-size: 1.05rem; display: flex; align-items: center; }
        aside a:hover, aside button:hover { color: var(--accent-hover); background-color: var(--primary-bg); border-left-color: var(--accent-hover); transform: translateX(5px); }
        aside a.active, button.active { color: var(--accent-hover); font-weight: 700; background-color: var(--primary-bg); border-left-color: var(--accent-hover); }
        .card-base { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; backdrop-filter: blur(12px); }
        .table-container thead { background: linear-gradient(90deg, var(--accent-color), var(--accent-hover)); color: var(--secondary-bg); }
        .table-container th, .table-container td { border-bottom: 1px solid var(--border-color); padding: 1rem 1.25rem; vertical-align: middle; }
        .form-label { font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; display: inline-block; }
        .form-select { background: var(--primary-bg); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 10px; padding: 12px 16px; }
        .btn-base { padding: 12px 24px; border-radius: 10px; font-weight: 700; transition: all 0.2s ease; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 0.6rem; }
        .btn-primary { background: linear-gradient(90deg, var(--accent-color), var(--accent-hover)); color: var(--secondary-bg); }
        .btn-success { background-color: var(--success); color: white; }
        .modal-content { background-color: var(--primary-bg); border: 1px solid var(--border-color); border-radius: 16px; }
        .modal-header .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
        .alert-message { text-align: center; padding: 1rem; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background-color: rgba(46, 160, 67, 0.2); color: var(--success); border: 1px solid var(--success); }
        .alert-error { background-color: rgba(218, 54, 51, 0.2); color: var(--danger); border: 1px solid var(--danger); }
        .notification-badge { background-color: var(--danger); color: white; border-radius: 50%; font-size: 12px; font-weight: 700; height: 22px; min-width: 22px; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
        .bg-secondary-bg { background-color: var(--secondary-bg); border-bottom-left-radius: 16px; border-bottom-right-radius: 16px; }
        .custom-scrollbar::-webkit-scrollbar { width: 0px; height: 0px; }
        .custom-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .label { color: var(--text-secondary); font-weight: 600; }
    </style>
</head>
<body class="flex h-screen">
    <?php include 'includes/sidebar.php'; ?>
    <main class="flex-1 p-6 lg:p-8 overflow-y-auto">
        <header class="flex justify-between items-center mb-6">
            <h1 class="text-3xl md:text-4xl font-bold text-accent-hover"><?php echo $page_title; ?></h1>
            <div class="flex gap-2">
                <a href="payroll_calculation.php?month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>" class="btn-base btn-secondary"><i class="fas fa-arrow-left"></i> ត្រឡប់ទៅការគណនា</a>
            </div>
        </header>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert-message alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-message alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <section class="card-base p-6 mb-6">
            <h2 class="text-2xl font-bold text-accent-hover mb-4">ព័ត៌មានបុគ្គលិក</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><span class="label">ឈ្មោះ:</span> <span><?php echo htmlspecialchars($user['full_name']); ?></span></div>
                <div><span class="label">ផ្នែក:</span> <span><?php echo htmlspecialchars($user['department'] ?: 'N/A'); ?></span></div>
                <div><span class="label">តួនាទី:</span> <span><?php echo htmlspecialchars($user['role'] ?: 'N/A'); ?></span></div>
                <div><span class="label">ឆមាសបើកប្រាក់:</span> <span><?php echo date('F Y', strtotime($current_year . '-' . str_pad($current_month,2,'0',STR_PAD_LEFT) . '-01')); ?></span></div>
                <div><span class="label">គណនីធនាគារ:</span> <span><?php echo htmlspecialchars(($user['bank_account_number'] ?: 'N/A') . ' (' . ($user['bank_name'] ?: 'N/A') . ')'); ?></span></div>
            </div>
        </section>

        <section class="card-base p-6 mb-6">
            <h2 class="text-2xl font-bold text-accent-hover mb-4">សរុបប្រាក់ខែ</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="p-4 card-base"><div class="label">ប្រាក់ខែគោល</div><div class="text-2xl font-bold">$<?php echo number_format($base_salary, 2); ?></div></div>
                <div class="p-4 card-base"><div class="label">ប្រាក់បន្ថែម (OT)</div><div class="text-2xl font-bold text-green-400">+$<?php echo number_format($ot_bonus_total, 2); ?></div></div>
                <div class="p-4 card-base"><div class="label">ប្រាក់កាត់</div><div class="text-2xl font-bold text-red-400">-$<?php echo number_format($deduction_total, 2); ?></div></div>
            </div>
            <div class="mt-4 p-4 card-base">
                <div class="label">ប្រាក់ខែចុងក្រោយ</div>
                <div class="text-3xl font-extrabold text-accent-hover">$<?php echo number_format($net_salary, 2); ?></div>
            </div>
        </section>

        <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="card-base p-6">
                <h3 class="text-xl font-bold text-green-400 mb-3">បញ្ជីប្រាក់បន្ថែម</h3>
                <?php if (empty($bonus_details)): ?>
                    <p class="text-text-secondary">មិនមានប្រាក់បន្ថែម</p>
                <?php else: ?>
                    <ul class="list-disc list-inside">
                        <?php foreach ($bonus_details as $b): ?>
                            <li><?php echo $b; ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="card-base p-6">
                <h3 class="text-xl font-bold text-red-400 mb-3">បញ្ជីប្រាក់កាត់</h3>
                <?php if (empty($deduction_details)): ?>
                    <p class="text-text-secondary">មិនមានការកាត់ប្រាក់</p>
                <?php else: ?>
                    <ul class="list-disc list-inside">
                        <?php foreach ($deduction_details as $d): ?>
                            <li><?php echo $d; ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Script for Sidebar Dropdown Menu ---
        const payrollToggle = document.getElementById('payroll-toggle');
        const payrollSubmenu = document.getElementById('payroll-submenu');
        const payrollArrow = document.getElementById('payroll-arrow');

        if (payrollToggle && payrollSubmenu && payrollArrow) {
            if (payrollToggle.classList.contains('active')) {
                payrollSubmenu.classList.remove('hidden');
                payrollArrow.classList.add('rotate-180');
            }
            payrollToggle.addEventListener('click', () => {
                payrollSubmenu.classList.toggle('hidden');
                payrollArrow.classList.toggle('rotate-180');
            });
        }
    });
    </script>
</body>
</html>
