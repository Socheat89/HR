<?php
include 'includes/auth.php';
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'អ្នកមិនមានសិទ្ធិចូលទំព័រនេះទេ!';
    header("Location: index.php");
    exit();
}

include 'includes/db.php';
$conn = include 'includes/db.php';

// Set time zone
date_default_timezone_set('Asia/Phnom_Penh');

$page_title = 'ដំណើរការអនុម័តបៀវត្ស';
$user_id = $_SESSION['user_id'];

// Handle POST actions for approving or rejecting payroll
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_id'])) {
    $run_id = (int)$_POST['run_id'];
    $action = $_POST['action'] ?? '';

    try {
        $conn->beginTransaction();

        if ($action === 'approve') {
            $stmt = $conn->prepare(
                "UPDATE payroll_runs 
                 SET status = 'approved', approved_by_id = :user_id, approved_at = NOW() 
                 WHERE id = :run_id AND status = 'calculated'"
            );
            $stmt->execute([':user_id' => $user_id, ':run_id' => $run_id]);
            $_SESSION['success'] = "បញ្ជីបើកប្រាក់បៀវត្ស (ID: $run_id) ត្រូវបានអនុម័តដោយជោគជ័យ!";

        } elseif ($action === 'reject') {
            $reason = trim($_POST['rejection_reason'] ?? '');
            if (empty($reason)) {
                throw new Exception("សូមបញ្ចូលហេតុផលនៃការបដិសេធ។");
            }
            $stmt = $conn->prepare(
                "UPDATE payroll_runs 
                 SET status = 'rejected', approved_by_id = :user_id, approved_at = NOW(), notes = :reason 
                 WHERE id = :run_id AND status = 'calculated'"
            );
            $stmt->execute([':user_id' => $user_id, ':reason' => $reason, ':run_id' => $run_id]);
            $_SESSION['success'] = "បញ្ជីបើកប្រាក់បៀវត្ស (ID: $run_id) ត្រូវបានបដិសេធ។";
        }

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "មានបញ្ហា៖ " . $e->getMessage();
    }

    header("Location: payroll_approval.php");
    exit();
}


// Fetch payroll runs waiting for approval
try {
    $stmt_pending = $conn->prepare(
        "SELECT pr.*, u.full_name as creator_name 
         FROM payroll_runs pr
         JOIN users u ON pr.created_by_id = u.id
         WHERE pr.status = 'calculated'
         ORDER BY pr.month DESC"
    );
    $stmt_pending->execute();
    $pending_runs = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching pending payrolls: " . $e->getMessage();
    $pending_runs = [];
}


// Fetch payroll run history (approved or rejected)
try {
    $stmt_history = $conn->prepare(
        "SELECT pr.*, creator.full_name as creator_name, approver.full_name as approver_name
         FROM payroll_runs pr
         JOIN users creator ON pr.created_by_id = creator.id
         LEFT JOIN users approver ON pr.approved_by_id = approver.id
         WHERE pr.status IN ('approved', 'rejected')
         ORDER BY pr.approved_at DESC
         LIMIT 20"
    );
    $stmt_history->execute();
    $history_runs = $stmt_history->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching payroll history: " . $e->getMessage();
    $history_runs = [];
}

// Fetch pending requests count for the sidebar badge
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

        body { background-color: var(--primary-bg); background-image: linear-gradient(135deg, var(--secondary-bg) 0%, var(--primary-bg) 100%); font-family: 'Noto Sans Khmer', 'Poppins', sans-serif; color: var(--text-primary); }
        aside { background-color: var(--secondary-bg); border-right: 1px solid var(--border-color); }
        aside h2 { color: var(--accent-hover); text-shadow: 0 0 10px rgba(255, 215, 0, 0.5); font-size: 1.75rem; }
        aside a, aside button { color: var(--text-secondary); transition: all 0.2s ease; border-left: 4px solid transparent; padding: 14px 12px; font-size: 1.05rem; display: flex; align-items: center; }
        aside a:hover, aside button:hover { color: var(--accent-hover); background-color: var(--primary-bg); border-left-color: var(--accent-hover); transform: translateX(5px); }
        aside a.active, aside button.active { color: var(--accent-hover); font-weight: 700; background-color: var(--primary-bg); border-left-color: var(--accent-hover); }
        .card-base { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; backdrop-filter: blur(12px); box-shadow: 0 6px 25px rgba(0, 0, 0, 0.2); }
        .table-container thead { background: linear-gradient(90deg, var(--accent-color), var(--accent-hover)); color: var(--secondary-bg); }
        .table-container th, .table-container td { border-bottom: 1px solid var(--border-color); padding: 1rem 1.25rem; vertical-align: middle; }
        .table-container tbody tr:hover { background-color: rgba(240, 196, 25, 0.07); }
        .form-label { font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; display: inline-block; }
        .form-input, .form-select, .form-textarea { background: var(--primary-bg); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 10px; transition: border-color 0.2s, box-shadow 0.2s; padding: 12px 16px; font-size: 1rem; width: 100%; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: var(--accent-hover); box-shadow: 0 0 0 4px rgba(240, 196, 25, 0.25); }
        .btn-base { padding: 12px 24px; border-radius: 10px; font-weight: 700; font-size: 1rem; transition: all 0.2s ease; cursor: pointer; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 0.6rem; }
        .btn-base:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3); }
        .btn-primary { background: linear-gradient(90deg, var(--accent-color), var(--accent-hover)); color: var(--secondary-bg); }
        .btn-success { background-color: var(--success); color: white; }
        .btn-danger { background-color: var(--danger); color: white; }
        .btn-secondary { background-color: var(--text-secondary); color: var(--secondary-bg); }
        .status-badge { padding: 6px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; }
        .status-calculated { background-color: rgba(240, 196, 25, 0.2); color: var(--warning); border: 1px solid var(--warning); }
        .status-approved { background-color: rgba(46, 160, 67, 0.2); color: var(--success); border: 1px solid var(--success); }
        .status-rejected { background-color: rgba(218, 54, 51, 0.2); color: var(--danger); border: 1px solid var(--danger); }
        .alert-message { text-align: center; padding: 1rem; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background-color: rgba(46, 160, 67, 0.2); color: var(--success); border: 1px solid var(--success); }
        .alert-error { background-color: rgba(218, 54, 51, 0.2); color: var(--danger); border: 1px solid var(--danger); }
        .modal-content { background-color: var(--primary-bg); border: 1px solid var(--border-color); border-radius: 16px; }
        .modal-header, .modal-footer { border-color: var(--border-color); padding: 1.5rem; }
        .modal-header .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
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
        
        .notification-badge { background-color: var(--danger); color: white; border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; border: 2px solid var(--secondary-bg); }
    </style>
</head>
<body class="flex h-screen">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 p-6 lg:p-8 overflow-y-auto">
        <header class="mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-accent-hover">
                <?php echo $page_title; ?>
            </h1>
            <p class="text-text-secondary mt-1">ពិនិត្យ និងអនុម័តបញ្ជីបើកប្រាក់បៀវត្សដែលបានគណនា។</p>
        </header>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert-message alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert-message alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <section class="mb-8">
            <h2 class="text-2xl font-bold text-accent-hover mb-4">រង់ចាំការអនុម័ត</h2>
            <?php if (empty($pending_runs)): ?>
                <div class="card-base p-8 text-center text-text-secondary">
                    <i class="fas fa-check-circle fa-3x mb-4"></i>
                    <p class="text-lg">គ្មានបញ្ជីបៀវត្សរង់ចាំការអនុម័តទេ។</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($pending_runs as $run): ?>
                        <div class="card-base p-6 flex flex-col justify-between">
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <h3 class="text-xl font-bold">ប្រាក់ខែ: <?php echo date('F Y', strtotime($run['month'])); ?></h3>
                                    <span class="status-badge status-calculated">រង់ចាំ</span>
                                </div>
                                <p class="text-text-secondary">រៀបចំដោយ: <?php echo htmlspecialchars($run['creator_name']); ?></p>
                                <p class="text-text-secondary">កាលបរិច្ឆេទ: <?php echo date('d-M-Y H:i', strtotime($run['created_at'])); ?></p>
                                <hr class="border-gray-700 my-4">
                                <div class="space-y-2">
                                    <div class="flex justify-between"><span>ប្រាក់ខែសរុប (Gross):</span> <span class="font-semibold">$<?php echo number_format($run['total_gross_salary'], 2); ?></span></div>
                                    <div class="flex justify-between"><span>ការកាត់ទុកសរុប:</span> <span class="font-semibold">$<?php echo number_format($run['total_deductions'], 2); ?></span></div>
                                    <div class="flex justify-between text-accent-hover text-lg"><strong>ប្រាក់ខែត្រូវបើក (Net):</strong> <strong class="font-bold">$<?php echo number_format($run['total_net_salary'], 2); ?></strong></div>
                                </div>
                            </div>
                            <div class="flex justify-end gap-3 mt-6">
                                <a href="payroll_run_details.php?id=<?php echo $run['id']; ?>" class="btn-base btn-secondary text-sm">មើលលម្អិត</a>
                                <button type="button" class="btn-base btn-danger text-sm" data-bs-toggle="modal" data-bs-target="#rejectModal" data-run-id="<?php echo $run['id']; ?>" data-run-month="<?php echo date('F Y', strtotime($run['month'])); ?>">បដិសេធ</button>
                                <button type="button" class="btn-base btn-success text-sm" data-bs-toggle="modal" data-bs-target="#approveModal" data-run-id="<?php echo $run['id']; ?>" data-run-month="<?php echo date('F Y', strtotime($run['month'])); ?>">អនុម័ត</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card-base p-0">
            <div class="p-6">
                <h2 class="text-2xl font-bold text-accent-hover">ប្រវត្តិការអនុម័ត</h2>
            </div>
            <div class="overflow-x-auto table-container">
                <table class="min-w-full">
                    <thead>
                        <tr>
                            <th class="text-left">ខែ</th>
                            <th class="text-center">ស្ថានភាព</th>
                            <th class="text-left">អ្នកដំណើរការ</th>
                            <th class="text-left">កាលបរិច្ឆេទ</th>
                            <th class="text-left">កំណត់ចំណាំ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($history_runs)): ?>
                        <tr><td colspan="5" class="text-center py-8 text-text-secondary">គ្មានប្រវត្តិទេ។</td></tr>
                    <?php else: ?>
                        <?php foreach ($history_runs as $run): ?>
                        <tr>
                            <td class="font-semibold"><?php echo date('F Y', strtotime($run['month'])); ?></td>
                            <td class="text-center">
                                <span class="status-badge status-<?php echo htmlspecialchars($run['status']); ?>">
                                    <?php echo htmlspecialchars($run['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($run['approver_name'] ?? 'N/A'); ?></td>
                            <td><?php echo date('d-M-Y H:i', strtotime($run['approved_at'])); ?></td>
                            <td class="text-text-secondary"><?php echo htmlspecialchars($run['notes'] ?? '---'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="payroll_approval.php">
                    <div class="modal-header">
                        <h5 class="modal-title text-accent-hover" id="approveModalLabel">បញ្ជាក់ការអនុម័ត</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="run_id" id="approveRunId">
                        <p>តើអ្នកពិតជាចង់អនុម័តបញ្ជីបើកប្រាក់បៀវត្សសម្រាប់ខែ <strong id="approveRunMonth"></strong> មែនទេ?</p>
                        <p class="text-text-secondary mt-3">សកម្មភាពនេះមិនអាចមិនធ្វើវិញបានទេ។</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-base btn-secondary" data-bs-dismiss="modal">បោះបង់</button>
                        <button type="submit" class="btn-base btn-success">យល់ព្រម អនុម័ត</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="payroll_approval.php">
                    <div class="modal-header">
                        <h5 class="modal-title text-accent-hover" id="rejectModalLabel">បដិសេធបញ្ជីបើកប្រាក់បៀវត្ស</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="run_id" id="rejectRunId">
                        <p class="mb-3">អ្នកកំពុងបដិសេធបញ្ជីបើកប្រាក់បៀវត្សសម្រាប់ខែ <strong id="rejectRunMonth"></strong>។</p>
                        <div class="form-group">
                            <label for="rejection_reason" class="form-label">សូមបញ្ចូលហេតុផល (តម្រូវឲ្យបំពេញ)</label>
                            <textarea name="rejection_reason" id="rejection_reason" rows="4" class="form-textarea" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-base btn-secondary" data-bs-dismiss="modal">បោះបង់</button>
                        <button type="submit" class="btn-base btn-danger">យល់ព្រម បដិសេធ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const approveModal = document.getElementById('approveModal');
            if (approveModal) {
                approveModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const runId = button.getAttribute('data-run-id');
                    const runMonth = button.getAttribute('data-run-month');
                    
                    approveModal.querySelector('#approveRunId').value = runId;
                    approveModal.querySelector('#approveRunMonth').textContent = runMonth;
                });
            }
            
            const rejectModal = document.getElementById('rejectModal');
            if (rejectModal) {
                rejectModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const runId = button.getAttribute('data-run-id');
                    const runMonth = button.getAttribute('data-run-month');
                    
                    rejectModal.querySelector('#rejectRunId').value = runId;
                    rejectModal.querySelector('#rejectRunMonth').textContent = runMonth;
                    rejectModal.querySelector('#rejection_reason').value = ''; 
                });
            }

            const payrollToggle = document.getElementById('payroll-toggle');
            const payrollSubmenu = document.getElementById('payroll-submenu');
            const payrollArrow = document.getElementById('payroll-arrow');

            if (payrollToggle && payrollSubmenu && payrollArrow) {
                // Check if the current page is a payroll page to decide if the menu should be open
                const isPayrollPage = window.location.pathname.includes('payroll_');
                if (isPayrollPage) {
                    payrollSubmenu.classList.remove('hidden');
                    payrollArrow.classList.add('rotate-180');
                }

                payrollToggle.addEventListener('click', (event) => {
                    event.preventDefault(); 
                    payrollSubmenu.classList.toggle('hidden');
                    payrollArrow.classList.toggle('rotate-180');
                });
            }
        });
    </script>
</body>
</html>