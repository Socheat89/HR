<?php
// FILE: manage_deductions.php
include 'includes/auth.php';
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'អ្នកមិនមានសិទ្ធិចូលទំព័រនេះទេ។';
    header("Location: dashboard.php");
    exit();
}

include 'includes/db.php';
$conn = include 'includes/db.php';
date_default_timezone_set('Asia/Phnom_Penh');
$page_title = 'គ្រប់គ្រងការកាត់ប្រាក់ & ប្រាក់ OT';

// Determine the active tab for display
$active_tab = $_GET['tab'] ?? 'deductions'; // Default to deductions
$current_action_file = 'manage_deductions.php';

// --- HANDLE POST REQUESTS ---

// 1. Handle Deduction Logic (Existing Logic, slightly updated redirect)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_deduction'])) {
    $user_id = $_POST['user_id'];
    $amount = $_POST['amount'];
    $reason = trim($_POST['reason']);
    $deduction_date = $_POST['deduction_date'];

    if (empty($user_id) || empty($amount) || empty($reason) || empty($deduction_date)) {
        $_SESSION['error'] = 'សូមបំពេញព័ត៌មានឲ្យបានគ្រប់គ្រាន់សម្រាប់ការកាត់ប្រាក់។';
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $_SESSION['error'] = 'ចំនួនទឹកប្រាក់កាត់ត្រូវតែជាលេខវិជ្ជមាន។';
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO other_deductions (user_id, amount, reason, deduction_date, created_by_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $amount, $reason, $deduction_date, $_SESSION['user_id']]);
            $_SESSION['success'] = 'ការកាត់ប្រាក់ត្រូវបានបន្ថែមដោយជោគជ័យ។';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'មានបញ្ហាក្នុងការបន្ថែមទិន្នន័យ៖ ' . $e->getMessage();
        }
    }
    header("Location: {$current_action_file}?tab=deductions");
    exit();
}

// Handle Delete Deduction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_deduction'])) {
    $deduction_id = $_POST['deduction_id'];
    try {
        $stmt = $conn->prepare("DELETE FROM other_deductions WHERE id = ?");
        $stmt->execute([$deduction_id]);
        $_SESSION['success'] = 'ការកាត់ប្រាក់ត្រូវបានលុបចោលដោយជោគជ័យ។';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'មានបញ្ហាក្នុងការលុបទិន្នន័យ៖ ' . $e->getMessage();
    }
    header("Location: {$current_action_file}?tab=deductions");
    exit();
}


// 2. Handle OT Bonus Logic (NEW Logic)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ot_bonus'])) {
    $user_id = $_POST['ot_user_id'];
    $ot_amount = filter_var($_POST['ot_amount'], FILTER_VALIDATE_FLOAT);
    $reason = trim($_POST['ot_reason']);
    $ot_month = $_POST['ot_month']; // Format YYYY-MM

    if (empty($user_id) || empty($ot_month) || $ot_amount === false) {
        $_SESSION['error'] = 'សូមបំពេញព័ត៌មាន OT ឲ្យបានគ្រប់គ្រាន់។';
    } elseif ($ot_amount < 0) {
        $_SESSION['error'] = 'ចំនួនប្រាក់ OT ត្រូវតែជាលេខវិជ្ជមាន ឬសូន្យ។';
    } else {
        $current_user_id = $_SESSION['user_id'];

        try {
            // Check if OT for this employee/month already exists
            $checkStmt = $conn->prepare("SELECT id FROM ot_bonuses WHERE user_id = ? AND ot_month = ?");
            $checkStmt->execute([$user_id, $ot_month]);
            $existing_ot = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_ot) {
                // Update existing record
                $stmt = $conn->prepare(
                    "UPDATE ot_bonuses SET ot_amount = ?, reason = ?, recorded_by_id = ? WHERE id = ?"
                );
                $stmt->execute([$ot_amount, $reason, $current_user_id, $existing_ot['id']]);
                $_SESSION['success'] = 'ប្រាក់ OT ត្រូវបានកែប្រែដោយជោគជ័យសម្រាប់ខែ ' . $ot_month . '។';
            } else {
                // Insert new record
                $stmt = $conn->prepare(
                    "INSERT INTO ot_bonuses (user_id, ot_month, ot_amount, reason, recorded_by_id)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([$user_id, $ot_month, $ot_amount, $reason, $current_user_id]);
                $_SESSION['success'] = 'ប្រាក់ OT ត្រូវបានបញ្ចូលដោយជោគជ័យ។';
            }

        } catch (PDOException $e) {
            $_SESSION['error'] = 'មានបញ្ហាក្នុងការរក្សាទុកទិន្នន័យ OT៖ ' . $e->getMessage();
        }
    }
    header("Location: {$current_action_file}?tab=ot_bonus&month=" . urlencode($ot_month));
    exit();
}

// Handle Delete OT Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ot_bonus'])) {
    $ot_id = $_POST['ot_id'];
    $redirect_month = $_POST['redirect_month'] ?? date('Y-m');
    
    try {
        $stmt = $conn->prepare("DELETE FROM ot_bonuses WHERE id = ?");
        $stmt->execute([$ot_id]);
        $_SESSION['success'] = 'ប្រាក់ OT ត្រូវបានលុបចោលដោយជោគជ័យ។';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'មានបញ្ហាក្នុងការលុបទិន្នន័យ OT៖ ' . $e->getMessage();
    }
    header("Location: {$current_action_file}?tab=ot_bonus&month=" . urlencode($redirect_month));
    exit();
}

// --- FETCH DATA FOR DISPLAY ---
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$current_month_num = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$current_month_str = $current_year . '-' . str_pad($current_month_num, 2, '0', STR_PAD_LEFT);


// 1. Fetch active users for the dropdown
$users = $conn->query("SELECT id, full_name FROM users WHERE status = 'active' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// 2. Fetch Deductions for the selected month
$stmt_deductions = $conn->prepare(
    "SELECT od.*, u.full_name 
     FROM other_deductions od 
     JOIN users u ON od.user_id = u.id 
     WHERE YEAR(od.deduction_date) = ? AND MONTH(od.deduction_date) = ?
     ORDER BY od.deduction_date DESC"
);
$stmt_deductions->execute([$current_year, $current_month_num]);
$deductions = $stmt_deductions->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing unique deduction reasons
$existing_deduction_reasons = $conn->query("SELECT DISTINCT reason FROM other_deductions WHERE reason IS NOT NULL AND reason != '' ORDER BY reason ASC")->fetchAll(PDO::FETCH_ASSOC);


// 3. Fetch OT Bonuses for the selected month
$stmt_ot = $conn->prepare(
    "SELECT ob.*, u.full_name 
     FROM ot_bonuses ob 
     JOIN users u ON ob.user_id = u.id 
     WHERE ob.ot_month = ?
     ORDER BY u.full_name ASC"
);
$stmt_ot->execute([$current_month_str]);
$ot_bonuses = $stmt_ot->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing unique OT reasons
$existing_ot_reasons = $conn->query("SELECT DISTINCT reason FROM ot_bonuses WHERE reason IS NOT NULL AND reason != '' ORDER BY reason ASC")->fetchAll(PDO::FETCH_ASSOC);


// GET PENDING REQUESTS COUNT FOR SIDEBAR BADGE
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

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
               :root {
            --primary-bg: #161b22;         /* ផ្ទៃខាងក្រោយចម្បង - ខ្មៅស្រាល */
            --secondary-bg: #0d1117;       /* ផ្ទៃខាងក្រោយទីពីរ - ខ្មៅដិត */
            --card-bg: rgba(22, 27, 34, 0.9); /* ផ្ទៃខាងក្រោយកាត (មានតម្លាភាពបន្តិច) */
            --border-color: rgba(255, 255, 255, 0.1); /* ពណ៌គែម - ស្តើង */
            --accent-color: #ffd700;       /* ពណ៌សង្កត់ចម្បង - មាសសុទ្ធ */
            --accent-hover: #ffea70;       /* ពណ៌សង្កត់ពេល Hover */
            --text-primary: #f0f6fc;       /* ពណ៌អក្សរចម្បង - សភ្លឺ */
            --text-secondary: #ffffff;     /* ពណ៌អក្សរទីពីរ - ប្រផេះស្រាល */
            --success: #2ea043;            /* ពណ៌ជោគជ័យ - បៃតង */
            --danger: #da3633;             /* ពណ៌គ្រោះថ្នាក់ - ក្រហម */
            --warning: #ffd700;            /* ពណ៌ព្រមាន - មាស */
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
        .form-input, .form-select { background: var(--primary-bg); border: 1px solid var(--border-color); color: var(--text-secondary); border-radius: 10px; padding: 12px 16px; width: 100%; }
        .form-input[type="month"] { padding: 12px 16px; }
        .btn-base { padding: 12px 24px; border-radius: 10px; font-weight: 700; transition: all 0.2s ease; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 0.6rem; }
        .btn-primary { background: linear-gradient(90deg, var(--accent-color), var(--accent-hover)); color: var(--secondary-bg); }
        .btn-success { background-color: var(--success); color: white; }
        .btn-danger { background-color: var(--danger); color: white; }
        .select2-container--bootstrap-5 .select2-results__option--selected {
            background-color: #2a3038;
            color: var(--text-primary) !important;
        }
        /* Select2 styles */
        .select2-container--bootstrap-5 .select2-selection {
            background-color: var(--primary-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 10px;
            padding: 6px 12px;
            min-height: 50px;
        }
        .select2-container--bootstrap-5 .select2-dropdown {
            background-color: var(--primary-bg);
            border: 1px solid var(--border-color);
        }
        .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field {
            background-color: var(--secondary-bg);
            color: var(--text-primary);
            border-color: var(--border-color);
        }
        .select2-container--bootstrap-5 .select2-results__option--highlighted {
            background-color: var(--accent-color) !important;
            color: var(--secondary-bg) !important;
        }
        .select2-container--bootstrap-5 .select2-results__option {
            color: var(--text-primary);
        }
        .select2-container--bootstrap-5 .select2-selection__placeholder,
        .select2-container--bootstrap-5 .select2-selection__rendered {
            color: var(--text-primary) !important;
            opacity: 0.8;
        }

        /* TAB STYLES */
        .nav-tabs { border-bottom: 2px solid var(--border-color); }
        .nav-tabs .nav-link { 
            color: var(--text-secondary); 
            border: 1px solid transparent; 
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
        }
        .nav-tabs .nav-link:hover { 
            border-color: var(--border-color) var(--border-color) transparent; 
        }
        .nav-tabs .nav-link.active {
            color: var(--accent-hover); 
            background-color: var(--card-bg);
            border-color: var(--border-color) var(--border-color) transparent;
            font-weight: 700;
        }

        .alert { 
            text-align: center; padding: 1rem; border-radius: 10px; margin-bottom: 20px; font-weight: 500; 
            background-color: rgba(22, 27, 34, 0.75);
            border: 1px solid var(--border-color);
        }
        .alert-success { color: var(--success); border-color: var(--success); }
        .alert-danger { color: var(--danger); border-color: var(--danger); }
        
        /* បន្ថែម CSS នេះទៅក្នុង <style> tag របស់អ្នក */
/* ... កូដ CSS ដែលមានស្រាប់ ... */

/* NEW: Scrollbar Hiding CSS */
.custom-scrollbar::-webkit-scrollbar {
    width: 0px; /* Chrome, Safari, Edge */
    height: 0px;
}

.custom-scrollbar {
    -ms-overflow-style: none;  /* IE and Edge */
    scrollbar-width: none;  /* Firefox */
}
/* END NEW CSS */

/* ... កូដ CSS ដែលនៅសល់ ... */
        
    </style>
</head>
<body class="flex h-screen">
    
    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 p-6 lg:p-8 overflow-y-auto">
        <header class="flex justify-between items-center mb-6">
            <h1 class="text-3xl md:text-4xl font-bold text-accent-hover"><?php echo $page_title; ?></h1>
        </header>

        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo ($active_tab === 'deductions') ? 'active' : ''; ?>" 
                   href="<?php echo $current_action_file; ?>?tab=deductions" 
                   role="tab">
                   <i class="fas fa-minus-circle mr-2 text-red-400"></i> ការកាត់ប្រាក់ (Deductions)
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?php echo ($active_tab === 'ot_bonus') ? 'active' : ''; ?>" 
                   href="<?php echo $current_action_file; ?>?tab=ot_bonus" 
                   role="tab">
                   <i class="fas fa-plus-circle mr-2 text-green-400"></i> ប្រាក់ថែមម៉ោង (OT Bonus)
                </a>
            </li>
        </ul>
        
        <div class="tab-content pt-4">

            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <div class="tab-pane <?php echo ($active_tab === 'deductions') ? 'active' : 'hidden'; ?>" id="deductions-tab" role="tabpanel">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-1">
                        <section class="card-base p-6">
                            <h2 class="text-xl font-bold mb-4 text-white">បន្ថែមការកាត់ប្រាក់ថ្មី</h2>
                            <form method="POST" action="<?php echo $current_action_file; ?>" class="space-y-4">
                                <div>
                                    <label for="deduction_user_id" class="form-label">ជ្រើសរើសបុគ្គលិក</label>
                                    <select name="user_id" id="deduction_user_id" class="form-select text-white" required>
                                        <option value=""></option> 
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="deduction_amount" class="form-label">ចំនួនទឹកប្រាក់ (USD)</label>
                                    <input type="number" step="0.01" min="0.01" name="amount" id="deduction_amount" class="form-input" required>
                                </div>
                                <div>
                                    <label for="deduction_reason" class="form-label">មូលហេតុ</label>
                                    <select name="reason" id="deduction_reason" class="form-select text-white" required>
                                        <option value=""></option>
                                        <?php foreach ($existing_deduction_reasons as $reason_item): ?>
                                            <option value="<?php echo htmlspecialchars($reason_item['reason']); ?>"><?php echo htmlspecialchars($reason_item['reason']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="deduction_date" class="form-label">កាលបរិច្ឆេទកាត់ប្រាក់</label>
                                    <input type="date" name="deduction_date" id="deduction_date" value="<?php echo date('Y-m-d'); ?>" class="form-input" required>
                                </div>
                                <button type="submit" name="add_deduction" class="btn-base btn-danger w-full mt-2">
                                    <i class="fas fa-minus-circle"></i> បន្ថែមការកាត់ប្រាក់
                                </button>
                            </form>
                        </section>
                    </div>

                    <div class="lg:col-span-2">
                        <section class="card-base p-6">
                            <div class="flex flex-wrap justify-between items-center mb-6 gap-4">
                                <h2 class="text-xl font-bold text-white">បញ្ជីការកាត់ប្រាក់</h2>
                                <form method="GET" action="<?php echo $current_action_file; ?>" class="flex flex-wrap items-end gap-4">
                                    <input type="hidden" name="tab" value="deductions">
                                    <div>
                                        <label for="ded_month" class="form-label">ខែ</label>
                                        <select name="month" id="ded_month" class="form-select" onchange="this.form.submit()">
                                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                                <option value="<?php echo $m; ?>" <?php if ($m == $current_month_num) echo 'selected'; ?>>
                                                    <?php echo date('F', mktime(0, 0, 0, $m, 10)); ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="ded_year" class="form-label">ឆ្នាំ</label>
                                        <select name="year" id="ded_year" class="form-select" onchange="this.form.submit()">
                                            <?php for ($y = date('Y') - 5; $y <= date('Y'); $y++): ?>
                                                <option value="<?php echo $y; ?>" <?php if ($y == $current_year) echo 'selected'; ?>><?php echo $y; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </form>
                            </div>

                            <div class="overflow-x-auto table-container">
                                <table class="min-w-full">
                                    <thead>
                                        <tr>
                                            <th>ឈ្មោះបុគ្គលិក</th>
                                            <th>ចំនួនទឹកប្រាក់</th>
                                            <th>មូលហេតុ</th>
                                            <th>កាលបរិច្ឆេទ</th>
                                            <th>សកម្មភាព</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($deductions)): ?>
                                            <tr><td colspan="5" class="text-center py-8 text-text-secondary">មិនមានទិន្នន័យការកាត់ប្រាក់សម្រាប់ខែនេះទេ។</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($deductions as $deduction): ?>
                                                <tr>
                                                    <td class="font-semibold"><?php echo htmlspecialchars($deduction['full_name']); ?></td>
                                                    <td class="text-right text-red-400">-$<?php echo number_format($deduction['amount'], 2); ?></td>
                                                    <td><?php echo htmlspecialchars($deduction['reason']); ?></td>
                                                    <td><?php echo date('d-M-Y', strtotime($deduction['deduction_date'])); ?></td>
                                                    <td class="text-center">
                                                        <form method="POST" action="<?php echo $current_action_file; ?>" onsubmit="return confirm('តើអ្នកពិតជាចង់លុបមែនទេ?');">
                                                            <input type="hidden" name="delete_deduction" value="1">
                                                            <input type="hidden" name="deduction_id" value="<?php echo $deduction['id']; ?>">
                                                            <button type="submit" name="delete_deduction" class="btn-base btn-danger px-3 py-1 text-sm">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            <div class="tab-pane <?php echo ($active_tab === 'ot_bonus') ? 'active' : 'hidden'; ?>" id="ot-bonus-tab" role="tabpanel">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-1">
                        <section class="card-base p-6">
                            <h2 class="text-xl font-bold mb-4 text-white">បញ្ចូល/កែប្រែប្រាក់ OT ប្រចាំខែ</h2>
                            <form method="POST" action="<?php echo $current_action_file; ?>" class="space-y-4">
                                <div>
                                    <label for="ot_user_id" class="form-label">ជ្រើសរើសបុគ្គលិក</label>
                                    <select name="ot_user_id" id="ot_user_id" class="form-select text-white" required>
                                        <option value=""></option> 
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="ot_month" class="form-label">សម្រាប់ខែ</label>
                                    <input type="month" name="ot_month" id="ot_month" class="form-input" value="<?php echo date('Y-m'); ?>" required>
                                </div>
                                
                                <div>
                                    <label for="ot_amount" class="form-label">ចំនួនប្រាក់ OT (USD)</label>
                                    <input type="number" step="0.01" min="0" name="ot_amount" id="ot_amount" class="form-input" placeholder="0.00" required>
                                </div>
                                
                                <div>
                                    <label for="ot_reason" class="form-label">កំណត់សម្គាល់</label>
                                    <select name="ot_reason" id="ot_reason" class="form-select text-white">
                                        <option value=""></option>
                                        <?php foreach ($existing_ot_reasons as $reason_item): ?>
                                            <option value="<?php echo htmlspecialchars($reason_item['reason']); ?>"><?php echo htmlspecialchars($reason_item['reason']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" name="add_ot_bonus" class="btn-base btn-success w-full mt-2">
                                    <i class="fas fa-save"></i> រក្សាទុក/កែប្រែប្រាក់ OT
                                </button>
                            </form>
                        </section>
                    </div>

                    <div class="lg:col-span-2">
                        <section class="card-base p-6">
                            <div class="flex flex-wrap justify-between items-center mb-6 gap-4">
                                <h2 class="text-xl font-bold text-white">បញ្ជីប្រាក់ OT</h2>
                                <form method="GET" action="<?php echo $current_action_file; ?>" class="flex flex-wrap items-end gap-4">
                                    <input type="hidden" name="tab" value="ot_bonus">
                                    <div>
                                        <label for="ot_view_month" class="form-label">មើលសម្រាប់ខែ</label>
                                        <input type="month" name="month" id="ot_view_month" class="form-input" value="<?php echo htmlspecialchars($current_month_str); ?>" onchange="this.form.submit()">
                                    </div>
                                </form>
                            </div>

                            <div class="overflow-x-auto table-container">
                                <table class="min-w-full">
                                    <thead>
                                        <tr>
                                            <th>ឈ្មោះបុគ្គលិក</th>
                                            <th>ខែ</th>
                                            <th>ចំនួនប្រាក់ OT</th>
                                            <th>កំណត់សម្គាល់</th>
                                            <th>សកម្មភាព</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($ot_bonuses)): ?>
                                            <tr><td colspan="5" class="text-center py-8 text-text-secondary">មិនមានទិន្នន័យប្រាក់ OT សម្រាប់ខែនេះទេ។</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($ot_bonuses as $ot): ?>
                                                <tr>
                                                    <td class="font-semibold"><?php echo htmlspecialchars($ot['full_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($ot['ot_month']); ?></td>
                                                    <td class="text-right text-green-400">+$<?php echo number_format($ot['ot_amount'], 2); ?></td>
                                                    <td><?php echo htmlspecialchars($ot['reason'] ?: 'N/A'); ?></td>
                                                    <td class="text-center space-x-2">
                                                        <button type="button" class="btn-base btn-primary px-3 py-1 text-sm" 
                                                            onclick="loadOtEditForm(
                                                                '<?php echo $ot['user_id']; ?>',
                                                                '<?php echo htmlspecialchars($ot['ot_amount']); ?>',
                                                                '<?php echo htmlspecialchars(addslashes($ot['reason'])); ?>',
                                                                '<?php echo htmlspecialchars($ot['ot_month']); ?>'
                                                            )">
                                                             <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="POST" action="<?php echo $current_action_file; ?>" onsubmit="return confirm('តើអ្នកពិតជាចង់លុបប្រាក់ OT នេះមែនទេ?');" class="inline-block">
                                                            <input type="hidden" name="delete_ot_bonus" value="1">
                                                            <input type="hidden" name="ot_id" value="<?php echo $ot['id']; ?>">
                                                            <input type="hidden" name="redirect_month" value="<?php echo htmlspecialchars($ot['ot_month']); ?>">
                                                            <button type="submit" class="btn-base btn-danger px-3 py-1 text-sm">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

        </div>     </main>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
    const currentTab = '<?php echo $active_tab; ?>';
    
    // Function to load OT edit form data
    function loadOtEditForm(userId, amount, reason, month) {
        // Set the hidden field 'user_id' for submission
        $('#ot_user_id').val(userId).trigger('change'); 
        $('#ot_month').val(month);
        $('#ot_amount').val(parseFloat(amount).toFixed(2));
        
        // Handle Select2 for Reason (Tags: true allows setting new values)
        var otReasonSelect = $('#ot_reason');
        var newOption = new Option(reason, reason, true, true);
        if (otReasonSelect.find("option[value='" + reason + "']").length === 0) {
            otReasonSelect.append(newOption);
        }
        otReasonSelect.val(reason).trigger('change');
        
        // Scroll to the top form
        otReasonSelect[0].scrollIntoView({ behavior: 'smooth' });
    }
    
    // Function to load Deduction edit form data (basic implementation for future use)
    /*
    function loadDedEditForm(userId, amount, reason, date) {
        $('#deduction_user_id').val(userId).trigger('change'); 
        $('#deduction_amount').val(parseFloat(amount).toFixed(2));
        $('#deduction_date').val(date);
        
        var dedReasonSelect = $('#deduction_reason');
        var newOption = new Option(reason, reason, true, true);
        if (dedReasonSelect.find("option[value='" + reason + "']").length === 0) {
            dedReasonSelect.append(newOption);
        }
        dedReasonSelect.val(reason).trigger('change');
        
        dedReasonSelect[0].scrollIntoView({ behavior: 'smooth' });
    }
    */

    document.addEventListener('DOMContentLoaded', function() {
        // --- Select2 Initialization ---
        
        // 1. Deduction User Select
        $('#deduction_user_id').select2({
            theme: "bootstrap-5",
            placeholder: "-- ស្វែងរក ឬជ្រើសរើសបុគ្គលិក --",
            width: '100%',
            dropdownParent: $('#deductions-tab') // Important for placement
        });
        
        // 2. Deduction Reason Select
        $('#deduction_reason').select2({
            theme: "bootstrap-5",
            placeholder: "ស្វែងរក ឬបង្កើតមូលហេតុថ្មី...",
            tags: true,
            tokenSeparators: [','],
            width: '100%',
            dropdownParent: $('#deductions-tab')
        });
        
        // 3. OT User Select
        $('#ot_user_id').select2({
            theme: "bootstrap-5",
            placeholder: "-- ស្វែងរក ឬជ្រើសរើសបុគ្គលិក --",
            width: '100%',
            dropdownParent: $('#ot-bonus-tab')
        });

        // 4. OT Reason Select
        $('#ot_reason').select2({
            theme: "bootstrap-5",
            placeholder: "ស្វែងរក ឬបង្កើតកំណត់សម្គាល់ថ្មី...",
            tags: true, 
            tokenSeparators: [','],
            width: '100%',
            dropdownParent: $('#ot-bonus-tab')
        });

        // --- Sidebar Code ---
        const payrollToggle = document.getElementById('payroll-toggle');
        const payrollSubmenu = document.getElementById('payroll-submenu');
        const payrollArrow = document.getElementById('payroll-arrow');

        if (payrollToggle && payrollSubmenu && payrollArrow) {
            // Force open sidebar if on this page
            if (window.location.pathname.includes('manage_deductions.php')) {
                payrollToggle.classList.add('active');
            }
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