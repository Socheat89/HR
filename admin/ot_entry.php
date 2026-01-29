<?php
$page_title = 'បញ្ចូលប្រាក់ថែមម៉ោង (OT)';
include 'includes/auth.php';

// --------------------------------------------------------------------------------
// 1. Authorization Check (Admin Only)
// --------------------------------------------------------------------------------
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'អ្នកមិនមានសិទ្ធិចូលទំព័រនេះទេ។';
    header("Location: dashboard.php");
    exit();
}

include 'includes/db.php';
$conn = include 'includes/db.php';
date_default_timezone_set('Asia/Phnom_Penh');

// --------------------------------------------------------------------------------
// 2. Fetch Employee List for Dropdown
// --------------------------------------------------------------------------------
$employees = [];
try {
    $stmt = $conn->query("SELECT id, full_name, base_salary, department FROM users WHERE status = 'active' ORDER BY full_name ASC");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching employees: " . $e->getMessage();
}

// --------------------------------------------------------------------------------
// 3. Handle POST Request to Save OT Entry
// --------------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ot'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $ot_month = $_POST['ot_month'] ?? ''; // Format: YYYY-MM
    $ot_amount = filter_var($_POST['ot_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
    $reason = trim($_POST['reason'] ?? '');

    // Basic Validation
    if ($user_id <= 0 || empty($ot_month) || $ot_amount <= 0) {
        $_SESSION['error'] = 'សូមបញ្ចូលទិន្នន័យចាំបាច់ទាំងអស់ (បុគ្គលិក ខែ និងចំនួនប្រាក់ OT)។';
    } else {
        $ot_date_for_db = $ot_month . '-01'; // Use the 1st of the month for unique tracking
        $current_user_id = $_SESSION['user_id'];

        try {
            // Check if OT for this employee/month already exists
            $checkStmt = $conn->prepare("SELECT id FROM ot_bonuses WHERE user_id = ? AND ot_month = ?");
            $checkStmt->execute([$user_id, $ot_month]);
            $existing_ot = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_ot) {
                // Update existing record
                $stmt = $conn->prepare(
                    "UPDATE ot_bonuses SET ot_amount = ?, reason = ?, recorded_by_id = ?, updated_at = NOW() WHERE id = ?"
                );
                $stmt->execute([$ot_amount, $reason, $current_user_id, $existing_ot['id']]);
                $_SESSION['success'] = 'ប្រាក់ថែមម៉ោង (OT) ត្រូវបានកែប្រែដោយជោគជ័យសម្រាប់ខែ ' . $ot_month . '។';
            } else {
                // Insert new record
                $stmt = $conn->prepare(
                    "INSERT INTO ot_bonuses (user_id, ot_month, ot_amount, reason, recorded_by_id)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([$user_id, $ot_month, $ot_amount, $reason, $current_user_id]);
                $_SESSION['success'] = 'ប្រាក់ថែមម៉ោង (OT) ត្រូវបានបញ្ចូលដោយជោគជ័យសម្រាប់ខែ ' . $ot_month . '។';
            }

        } catch (PDOException $e) {
            $_SESSION['error'] = 'មានបញ្ហាពេលរក្សាទុកទិន្នន័យ OT: ' . $e->getMessage();
        }
    }
    header('Location: ot_entry.php');
    exit();
}


// --------------------------------------------------------------------------------
// 4. Fetch Existing OT Entries for Display/Editing (Optional, but useful)
// --------------------------------------------------------------------------------
$current_ot_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$ot_entries = [];

try {
    $sql_ot = "SELECT u.full_name, ob.ot_amount, ob.reason, ob.ot_month
               FROM ot_bonuses ob
               JOIN users u ON ob.user_id = u.id
               WHERE ob.ot_month = :month
               ORDER BY u.full_name ASC";
    $stmt_ot = $conn->prepare($sql_ot);
    $stmt_ot->execute([':month' => $current_ot_month]);
    $ot_entries = $stmt_ot->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Error is logged, but not displayed as a critical error
}

// Get pending request count for sidebar notification
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
            --primary-bg: #161b22; --secondary-bg: #0d1117; --card-bg: rgba(22, 27, 34, 0.75);
            --border-color: rgba(240, 196, 25, 0.25); --accent-color: #f0c419; --accent-hover: #ffd700;
            --text-primary: #f0f6fc; --text-secondary: #8b949e; --success: #2ea043; --danger: #da3633;
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
        .form-select, .form-control { background: var(--primary-bg); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 10px; padding: 12px 16px; width: 100%; }
        .btn-base { padding: 12px 24px; border-radius: 10px; font-weight: 700; transition: all 0.2s ease; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 0.6rem; }
        .btn-primary { background: linear-gradient(90deg, var(--accent-color), var(--accent-hover)); color: var(--secondary-bg); }
        .btn-success { background-color: var(--success); color: white; }
        .alert-message { text-align: center; padding: 1rem; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background-color: rgba(46, 160, 67, 0.2); color: var(--success); border: 1px solid var(--success); }
        .alert-error { background-color: rgba(218, 54, 51, 0.2); color: var(--danger); border: 1px solid var(--danger); }
        .notification-badge { background-color: var(--danger); color: white; border-radius: 50%; font-size: 12px; font-weight: 700; height: 22px; min-width: 22px; display: inline-flex; align-items: center; justify-content: center; line-height: 1; }
    </style>
</head>
<body class="flex h-screen">
    
    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 p-6 lg:p-8 overflow-y-auto">
        <header class="flex justify-between items-center mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-accent-hover"><?php echo $page_title; ?></h1>
        </header>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert-message alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-message alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <section class="card-base p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4 border-b pb-2 border-border-color">បញ្ចូលប្រាក់ថែមម៉ោងថ្មី</h2>
            <form method="POST" action="ot_entry.php" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="md:col-span-1">
                    <label for="user_id" class="form-label">បុគ្គលិក</label>
                    <select name="user_id" id="user_id" class="form-select" required>
                        <option value="">-- ជ្រើសរើសបុគ្គលិក --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-1">
                    <label for="ot_month" class="form-label">សម្រាប់ខែ</label>
                    <input type="month" name="ot_month" id="ot_month" class="form-control" value="<?php echo date('Y-m'); ?>" required>
                </div>

                <div class="md:col-span-1">
                    <label for="ot_amount" class="form-label">ចំនួនប្រាក់ OT ($)</label>
                    <input type="number" step="0.01" min="0.01" name="ot_amount" id="ot_amount" class="form-control" placeholder="ឧទាហរណ៍៖ 50.00" required>
                </div>

                <div class="md:col-span-1">
                    <label for="reason" class="form-label">មូលហេតុ/កំណត់សម្គាល់</label>
                    <input type="text" name="reason" id="reason" class="form-control" placeholder="OT ចំនួន ៥ ថ្ងៃ (ស្រេចចិត្ត)">
                </div>

                <div class="lg:col-span-4 flex justify-end">
                    <button type="submit" name="submit_ot" class="btn-base btn-primary text-lg mt-4">
                        <i class="fas fa-plus-circle"></i> រក្សាទុកប្រាក់ OT
                    </button>
                </div>
            </form>
        </section>

        <section class="card-base p-6">
            <h2 class="text-xl font-semibold mb-4 border-b pb-2 border-border-color">បញ្ជីប្រាក់ថែមម៉ោងដែលបានបញ្ចូល</h2>
            
            <form method="GET" action="ot_entry.php" class="flex flex-wrap items-end gap-4 mb-4">
                <div>
                    <label for="filter_month" class="form-label">មើលសម្រាប់ខែ</label>
                    <input type="month" name="month" id="filter_month" class="form-control" value="<?php echo htmlspecialchars($current_ot_month); ?>" onchange="this.form.submit()">
                </div>
            </form>

            <div class="overflow-x-auto table-container">
                <table class="min-w-full">
                    <thead>
                        <tr>
                            <th class="py-3 px-4 text-left">បុគ្គលិក</th>
                            <th class="py-3 px-4 text-left">ខែ</th>
                            <th class="py-3 px-4 text-right">ចំនួនទឹកប្រាក់ OT ($)</th>
                            <th class="py-3 px-4 text-left">មូលហេតុ/កំណត់សម្គាល់</th>
                            <th class="py-3 px-4 text-center">សកម្មភាព</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ot_entries)): ?>
                            <tr><td colspan="5" class="text-center py-8 text-text-secondary">មិនមានទិន្នន័យប្រាក់ OT សម្រាប់ខែនេះទេ។</td></tr>
                        <?php else: ?>
                            <?php foreach ($ot_entries as $entry): ?>
                                <tr>
                                    <td class="py-3 px-4 font-semibold"><?php echo htmlspecialchars($entry['full_name']); ?></td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($entry['ot_month']); ?></td>
                                    <td class="py-3 px-4 text-right text-green-400">$<?php echo number_format($entry['ot_amount'], 2); ?></td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($entry['reason'] ?: 'N/A'); ?></td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="text-text-secondary text-sm">បានបញ្ចូល</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // This script is for the sidebar dropdown menu (copied from your original)
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