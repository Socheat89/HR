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

// Main router: Check if a specific run_id is requested
$run_id = isset($_GET['run_id']) ? (int)$_GET['run_id'] : 0;


// =============================================================================
// === LOGIC FOR DETAILED PAYSLIP VIEW (if run_id is provided) ===
// =============================================================================
if ($run_id > 0) {
  // Get the name filter from the query string (ADDED)
  $name_filter = isset($_GET['name_filter']) ? trim($_GET['name_filter']) : '';
  $sql_where_clause = '';
  $sql_params = [$run_id];

  if (!empty($name_filter)) {
    // Add WHERE clause for name filter
    $sql_where_clause = " AND prd.full_name LIKE ?";
    // Add the search term to parameters
    $sql_params[] = '%' . $name_filter . '%';
  }
   
  // Fetch main run info
  $run_info_stmt = $conn->prepare("SELECT * FROM payroll_runs WHERE id = ? AND status IN ('approved', 'paid')");
  $run_info_stmt->execute([$run_id]);
  $run_info = $run_info_stmt->fetch(PDO::FETCH_ASSOC);

  if (!$run_info) {
    $_SESSION['error'] = 'រកមិនឃើញបញ្ជីបើកប្រាក់បៀវត្សដែលបានស្នើសុំទេ';
    header("Location: payroll_payslip.php");
    exit();
  }

  // 1. Fetch main payslip details (MODIFIED QUERY AND EXECUTION)
  $payslips_stmt = $conn->prepare(
    "SELECT prd.*, u.department, u.role, u.nssf_id, u.bank_name, u.bank_account_number, u.bank_qr_code_url
    FROM payroll_run_details prd
    JOIN users u ON prd.user_id = u.id
    WHERE prd.payroll_run_id = ?"
    . $sql_where_clause . // ADDED: Name filter where clause
    " ORDER BY prd.full_name ASC"
  );
  $payslips_stmt->execute($sql_params); // MODIFIED: Use dynamic parameters
  $payslips = $payslips_stmt->fetchAll(PDO::FETCH_ASSOC);

  $payslip_user_ids = array_column($payslips, 'user_id');
   
  // Find the first and last day of the payroll month to filter details
  $pay_month_str = date('Y-m', strtotime($run_info['month'])); // For ot_bonuses table
  $start_of_month = date('Y-m-01', strtotime($run_info['month'])); // For other_deductions table
  $end_of_month = date('Y-m-t', strtotime($run_info['month'])); // For other_deductions table

  $detailed_deductions = [];
    $detailed_ot_bonuses = []; // NEW ARRAY FOR OT DETAILS

  if (!empty($payslip_user_ids)) {
    // Prepare IN clause for multiple user IDs
    $in_clause = implode(',', array_fill(0, count($payslip_user_ids), '?'));

    // 2. Fetch Detailed Deductions from 'other_deductions' table
    $deductions_stmt = $conn->prepare(
      "SELECT user_id, reason, amount 
       FROM other_deductions 
       WHERE user_id IN ($in_clause) 
       AND deduction_date BETWEEN ? AND ?
       ORDER BY deduction_date ASC"
    );
    $params = array_merge($payslip_user_ids, [$start_of_month, $end_of_month]);
    $deductions_stmt->execute($params);
    $deductions_raw = $deductions_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group deductions by user_id
    foreach ($deductions_raw as $deduction) {
      $user_id = $deduction['user_id'];
      if (!isset($detailed_deductions[$user_id])) {
        $detailed_deductions[$user_id] = [];
      }
      $detailed_deductions[$user_id][] = [
        'reason' => $deduction['reason'],
        'amount' => $deduction['amount']
      ];
    }
        
        // 3. Fetch Detailed OT Bonuses from 'ot_bonuses' table (NEW LOGIC)
        $ot_bonuses_stmt = $conn->prepare(
            "SELECT user_id, reason, ot_amount AS amount
             FROM ot_bonuses
             WHERE user_id IN ($in_clause)
             AND ot_month = ?
             ORDER BY recorded_at ASC"
        );
        $ot_params = array_merge($payslip_user_ids, [$pay_month_str]);
        $ot_bonuses_stmt->execute($ot_params);
        $ot_bonuses_raw = $ot_bonuses_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group OT Bonuses by user_id
        foreach ($ot_bonuses_raw as $ot_bonus) {
            $user_id = $ot_bonus['user_id'];
            if (!isset($detailed_ot_bonuses[$user_id])) {
                $detailed_ot_bonuses[$user_id] = [];
            }
            $detailed_ot_bonuses[$user_id][] = [
                'reason' => $ot_bonus['reason'],
                'amount' => $ot_bonus['amount']
            ];
        }
  }

  $page_title = 'Payslip ខែ ' . date('F Y', strtotime($run_info['month']));

// =============================================================================
// === LOGIC FOR MAIN LISTING VIEW (if no run_id is provided) ===
// =============================================================================
} else {
    // ... (Existing listing logic remains unchanged) ...
    
  $page_title = 'បង្កើត Payslip';

  // Handle marking a run as 'paid'
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_paid') {
    $posted_run_id = (int)$_POST['run_id'];

    try {
      $stmt = $conn->prepare("UPDATE payroll_runs SET status = 'paid' WHERE id = :run_id AND status = 'approved'");
      $stmt->execute([':run_id' => $posted_run_id]);

      if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "បញ្ជីបើកប្រាក់បៀវត្ស (ID: $posted_run_id) ត្រូវបានសម្គាល់ថាបានទូទាត់រួចរាល់!";
      } else {
        $_SESSION['error'] = 'មិនអាចដំណើរការបានទេ។ បញ្ជីនេះប្រហែលមិនស្ថិតក្នុងស្ថានភាព "approved" ទេ។';
      }
    } catch (PDOException $e) {
      $_SESSION['error'] = 'មានបញ្ហា៖ ' . $e->getMessage();
    }
    header("Location: payroll_payslip.php");
    exit();
  }

  // Fetch 'approved' payroll runs
  try {
    $stmt_approved = $conn->prepare(
      "SELECT pr.*, u.full_name as approver_name 
       FROM payroll_runs pr
       JOIN users u ON pr.approved_by_id = u.id
       WHERE pr.status = 'approved'
       ORDER BY pr.month DESC"
    );
    $stmt_approved->execute();
    $approved_runs = $stmt_approved->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    $approved_runs = [];
  }

  // Fetch 'paid' payroll runs for history
  try {
    $stmt_paid = $conn->prepare(
      "SELECT pr.*, u.full_name as approver_name 
       FROM payroll_runs pr
       JOIN users u ON pr.approved_by_id = u.id
       WHERE pr.status = 'paid'
       ORDER BY pr.month DESC
       LIMIT 20"
    );
    $stmt_paid->execute();
    $paid_runs = $stmt_paid->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    $paid_runs = [];
  }
}

// Needed for the sidebar notification badge
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
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;700&family=Kantumruy+Pro:wght@400;500;700&family=Moul&display=swap" rel="stylesheet">
  <link rel="icon" type="image/x-icon" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
   
  <style>
    /* === BASE STYLES FOR DASHBOARD & FRAME === */
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
    body { background-color: var(--primary-bg); font-family: 'Kantumruy Pro', 'Noto Sans Khmer', sans-serif; color: var(--text-primary); }
    aside { background-color: var(--secondary-bg); border-right: 1px solid var(--border-color); }
    aside h2 { color: var(--accent-hover); }
    aside a, aside button { color: var(--text-secondary); transition: all 0.2s ease; border-left: 4px solid transparent; padding: 14px 12px; font-size: 1.05rem; display: flex; align-items: center; }
    aside a:hover, aside button:hover { color: var(--accent-hover); background-color: var(--primary-bg); border-left-color: var(--accent-hover); transform: translateX(5px); }
    aside a.active, button.active { color: var(--accent-hover); font-weight: 700; background-color: var(--primary-bg); border-left-color: var(--accent-hover); }
    .card-base { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; backdrop-filter: blur(12px); }
    .btn-base { padding: 10px 20px; border-radius: 10px; font-weight: 700; transition: all 0.2s ease; border: none; display: inline-flex; align-items: center; justify-content: center; gap: 0.6rem; cursor: pointer; }
    .btn-primary { background: linear-gradient(90deg, var(--accent-color), var(--accent-hover)); color: var(--secondary-bg); }
    .btn-success { background-color: var(--success); color: white; }
    .btn-secondary { background-color: var(--text-secondary); color: var(--secondary-bg); }
    .alert-message { text-align: center; padding: 1rem; border-radius: 10px; margin-bottom: 20px; font-weight: 500; }
    .alert-success { background-color: rgba(46, 160, 67, 0.2); color: var(--success); border: 1px solid var(--success); }
    .alert-error { background-color: rgba(218, 54, 51, 0.2); color: var(--danger); border: 1px solid var(--danger); }
    .payslip-page-container { display: flex; justify-content: center; }
    .payslip-wrapper { width: 180mm; margin: 20px 0; }
     
    /* === V3 STYLES FOR TABLE-BASED PAYSLIP (Matches Image) === */
    .v3-payslip-container {
      background-color: #ffffff;
      color: #000000;
      font-family: 'Kantumruy Pro', 'Noto Sans Khmer', sans-serif;
      border: 1px solid #000;
      padding: 1rem;
      font-size: 11pt;
      margin-bottom: 2rem;
    }
    .v3-header {
      text-align: center;
      padding-bottom: 1rem;
      border-bottom: 1px solid #000;
      margin-bottom: 1rem;
      position: relative;
    }
    .v3-header img.logo {
      position: absolute;
      left: 0;
      top: 0;
      max-height: 100px;
    }
    .v3-header h1 {
      font-family: 'Moul', cursive;
      font-size: 1.5rem;
      font-weight: bold;
      margin: 0;
    }
    .v3-header h2 {
      font-size: 1.2rem;
      font-weight: bold;
      margin-top: 0.5rem;
    }
    .v3-info-section {
      display: flex;
      justify-content: space-between;
      margin-bottom: 1rem;
    }
    .v3-info-grid {
      display: grid;
      grid-template-columns: 100px auto;
      gap: 5px;
    }
    .v3-info-grid .label { font-weight: bold; }
    .v3-qr-code img {
      width: 100px;
      height: 100px;
      object-fit: contain; /* Make sure QR is not stretched */
    }
    .v3-main-table {
      width: 100%;
      border-collapse: collapse;
      border: 1px solid #000;
    }
    .v3-main-table td {
      padding: 6px 8px;
      border-right: 1px solid #000;
    }
    .v3-main-table td:last-child {
      border-right: none;
    }
    .v3-main-table tr {
      border-bottom: 1px solid #000;
    }
    .v3-main-table tr:last-child {
      border-bottom: none;
    }
    .v3-table-header, .v3-table-footer {
      background-color: #e3e3e3;
      font-weight: bold;
    }
    .v3-amount {
      text-align: right;
      font-family: 'Arial', 'Helvetica', sans-serif; /* Changed for clearer numbers */
      font-weight: bold; /* Added bold for clarity */
    }
    .v3-footer {
      margin-top: 1rem;
    }
    .v3-footer-date {
      text-align: center;
      font-size: 10pt;
      margin-bottom: 2rem;
    }
    .v3-signature-area {
      display: flex;
      justify-content: space-between;
      text-align: center;
    }
    .v3-signature-block {
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      width: 30%;
      height: 80px;
    }
    .v3-signature-block .label { font-weight: bold; }

    /* === PRINT STYLES === */
    @page { size: A5 portrait; margin: 0mm; }
    @media print {
         body { 
            background-color: #FFF !important; 
            -webkit-print-color-adjust: exact; 
            print-color-adjust: exact; 
         }
         .no-print { 
            display: none !important; /* <--- បន្ថែម !important ត្រង់នេះ */
         }
         .payslip-page-container { display: block; }
         .payslip-wrapper { width: 98%; margin: 5px; padding: 0; }
         .v3-payslip-container { border: 1px solid #000; margin: 0; box-shadow: none; page-break-after: always; }
         .v3-payslip-container:last-child { page-break-after: auto; }
      }
  </style>
</head>
<body class="<?php if($run_id > 0) echo 'bg-gray-200'; else echo 'flex h-screen'; ?>">

  <?php if ($run_id == 0): // Only show sidebar on the main listing page ?>
    <?php include 'includes/sidebar.php'; ?>
  <?php endif; ?>

  <main class="flex-1 <?php if($run_id == 0) echo 'p-6 lg:p-8'; ?> overflow-y-auto">

  <?php if ($run_id > 0): ?>
     
    <div class="no-print my-4 text-center">
      <a href="payroll_payslip.php" class="inline-block bg-gray-500 text-white font-bold py-2 px-4 rounded hover:bg-gray-600">
        <i class="fas fa-arrow-left"></i> ត្រឡប់ក្រោយ
      </a>
      <button onclick="window.print()" class="bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700">
        <i class="fas fa-print"></i> បោះពុម្ព Payslip ទាំងអស់
      </button>
    </div>
        <div class="no-print my-4 flex justify-center">
      <form method="GET" action="payroll_payslip.php" class="flex gap-2">
        <input type="hidden" name="run_id" value="<?php echo $run_id; ?>">
        <input
          type="text"
          name="name_filter"
          placeholder="ច្រោះតាមឈ្មោះ..."
          value="<?php echo htmlspecialchars($name_filter); ?>"
          class="py-2 px-4 rounded border border-gray-300 text-gray-800 focus:ring-accent-color focus:border-accent-color"
        >
        <button type="submit" class="bg-accent-color text-secondary-bg font-bold py-2 px-4 rounded hover:bg-accent-hover">
          <i class="fas fa-filter"></i> ច្រោះ
        </button>
        <?php if (!empty($name_filter)): ?>
          <a href="payroll_payslip.php?run_id=<?php echo $run_id; ?>" class="bg-red-500 text-white font-bold py-2 px-4 rounded hover:bg-red-600">
            <i class="fas fa-times"></i> លុបច្រោះ
          </a>
        <?php endif; ?>
      </form>
    </div>
        <div class="payslip-page-container">
      <div class="payslip-wrapper">
        <?php if (empty($payslips)): ?>
          <p class="text-center text-xl text-text-secondary mt-8">
            គ្មានទិន្នន័យបុគ្គលិកសម្រាប់បញ្ជីបើកប្រាក់បៀវត្សនេះទេ
            <?php if (!empty($name_filter)) echo 'សម្រាប់ឈ្មោះ៖ ' . htmlspecialchars($name_filter); ?>
          </p>
        <?php else: ?>
          <?php 
            // Calculate pay period dates
            $pay_period_date = new DateTime($run_info['month']);
            $start_date = $pay_period_date->format('Y-m-01');
            $end_date = $pay_period_date->format('Y-m-t');
          ?>
          <?php foreach ($payslips as $slip): ?>
           
          <div class="v3-payslip-container">
            <div class="v3-header">
              <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="Company Logo" class="logo">
              <h1>វណ្ណ វណ្ណ ខេមបូឌា</h1>
              <span>VAN VAN CAMBODIA</span>
              <h2>ប័ណ្ណបើកប្រាក់បៀវត្ស</h2>
            </div>

            <div class="v3-info-section">
              <div class="v3-info-grid">
                <span class="label">ឈ្មោះ:</span> <span class="value"><?php echo htmlspecialchars($slip['full_name']); ?></span>
                <span class="label">តួនាទី:</span> <span class="value"><?php echo htmlspecialchars($slip['role']); ?></span>
                <span class="label">គិតចាប់ពី:</span> <span class="value"><?php echo date('d-m-Y', strtotime($start_date)); ?></span>
                <span class="label">ដល់:</span> <span class="value"><?php echo date('d-m-Y', strtotime($end_date)); ?></span>
              </div>
              <div class="v3-qr-code">
                <?php if (!empty($slip['bank_qr_code_url']) && file_exists($slip['bank_qr_code_url'])): ?>
                  <img src="<?php echo htmlspecialchars($slip['bank_qr_code_url']); ?>" alt="Bank QR Code">
                <?php else: ?>
                  <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=No-QR-Available" alt="QR Code">
                <?php endif; ?>
              </div>
            </div>

            <table class="v3-main-table">
              <tbody>
                <tr class="v3-table-header">
                  <td colspan="2">
                    <?php 
                      echo htmlspecialchars($slip['bank_account_number'] ?: 'N/A') . ' ( ' . htmlspecialchars($slip['bank_name'] ?: 'N/A') . ')';
                    ?>
                  </td>
                </tr>
                <tr>
                  <td>ប្រាក់ខែសរុប (Base Salary)</td>
                   
                  <td class="v3-amount">$ <?php echo number_format($slip['base_salary'], 2); ?></td>
                   
                </tr>
                 
                                <?php
                                    // NEW: Loop and display detailed OT bonuses
                                    $current_user_ot = $detailed_ot_bonuses[$slip['user_id']] ?? [];
                                    $total_custom_ot = 0;
                                    
                                    if (!empty($current_user_ot)) {
                                        foreach ($current_user_ot as $ot_item) {
                                            $reason = htmlspecialchars($ot_item['reason'] ?: 'OT Bonus');
                                            $amount = $ot_item['amount'] ?? 0;
                                            $total_custom_ot += $amount;
                                            
                                            if ($amount > 0) {
                                                echo '<tr>';
                                                echo '<td style="padding-left: 20px;">' . $reason . '</td>'; 
                                                echo '<td class="v3-amount" style="color: #2ea043;">+$ ' . number_format($amount, 2) . '</td>';
                                                echo '</tr>';
                                            }
                                        }
                                    }
                                    
                                    // Fallback for OT (if total bonuses > custom OT, assume the difference is old OT/other bonuses)
                                    $remaining_bonus = $slip['bonuses'] - $total_custom_ot;
                                    if ($remaining_bonus > 0.01) { 
                                        echo '<tr>';
                                        echo '<td>ប្រាក់បន្ថែមម៉ោង</td>';
                                        echo '<td class="v3-amount" style="color: #2ea043;">+$ ' . number_format($remaining_bonus, 2) . '</td>';
                                        echo '</tr>';
                                    } elseif ($slip['bonuses'] > 0 && empty($current_user_ot)) {
                                        // Fallback if total bonuses > 0 but no details were fetched
                                        echo '<tr>';
                                        echo '<td>ប្រាក់ OT បន្ថែមម៉ោងសរុប (គ្មានលម្អិត)</td>';
                                        echo '<td class="v3-amount" style="color: #2ea043;">+$ ' . number_format($slip['bonuses'], 2) . '</td>';
                                        echo '</tr>';
                                    }
                                ?>
                 
                <?php 
                  // 1. ទាញយកការកាត់ប្រាក់លម្អិតសម្រាប់បុគ្គលិកបច្ចុប្បន្ន
                  $current_user_deductions = $detailed_deductions[$slip['user_id']] ?? []; 
                   
                  $total_custom_deductions = 0;
                  $has_custom_deductions = !empty($current_user_deductions);

                  // 2. Loop លើធាតុដែលត្រូវកាត់នីមួយៗ
                  if ($has_custom_deductions) {
                    foreach ($current_user_deductions as $deduction_item) {
                      $reason = htmlspecialchars($deduction_item['reason'] ?? 'មូលហេតុមិនស្គាល់');
                      $amount = $deduction_item['amount'] ?? 0;
                      $total_custom_deductions += $amount;
                       
                      if ($amount > 0) {
                        // បង្ហាញមូលហេតុ និងចំនួនទឹកប្រាក់ដែលកាត់
                        echo '<tr>';
                        echo '<td style="padding-left: 20px;"> ' . $reason . '</td>'; 
                        echo '<td class="v3-amount" style="color: #da3633;">-$ ' . number_format($amount, 2) . '</td>';
                        echo '</tr>';
                      }
                    }
                  }

                  // 3. បង្ហាញបន្ទាត់សរុបប្រសិនបើ deductions របស់ Payslip មានតម្លៃ តែយើងមិនបានទាញយកលម្អិត ឬតម្លៃសរុបមិនត្រូវគ្នា
                  $remaining_deduction = $slip['deductions'] - $total_custom_deductions;
                   
                  if ($remaining_deduction > 0.01) { // ប្រើ 0.01 ដើម្បីជៀសវាងបញ្ហាទសភាគ
                     echo '<tr>';
                     echo '<td> ភ្លេចស្កេន</td>';
                     echo '<td class="v3-amount" style="color: #da3633;">-$ ' . number_format($remaining_deduction, 2) . '</td>';
                     echo '</tr>';
                  } elseif ($slip['deductions'] > 0 && !$has_custom_deductions) {
                    // Fallback: If total deductions is > 0 but we couldn't fetch details (Error in logic/data)
                    echo '<tr>';
                    echo '<td>កាត់ប្រាក់សរុប (គ្មានលម្អិត)</td>';
                    echo '<td class="v3-amount" style="color: #da3633;">-$ ' . number_format($slip['deductions'], 2) . '</td>';
                    echo '</tr>';
                  }

                ?>
                <tr class="v3-table-footer">
                  <td>ប្រាក់ខែសរុបដែលទទួលបាន</td>
                  <td class="v3-amount">$ <?php echo number_format($slip['net_salary'], 2); ?></td>
                </tr>
              </tbody>
            </table>
             
            <div class="v3-footer">
              <div class="v3-footer-date">
                <span><?php echo $dynamic_date_line ?? ''; ?></span>
              </div>
              <div class="v3-signature-area">
                <div class="v3-signature-block">
                  <span class="label">អ្នកទទួល</span><br><br>
                  <span><?php echo htmlspecialchars($slip['full_name']); ?></span>
                 
                </div>
                <div class="v3-signature-block">
                  <span class="label">អ្នកប្រគល់</span>
                  <span class="label">គណនេយ្យករ</span><br><br>
                 
                  <span>លី សាំងអុី</span>
                 
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
   
   
  <?php else: ?>

    <header class="mb-8">
      <h1 class="text-3xl md:text-4xl font-bold text-accent-hover"><?php echo $page_title; ?></h1>
      <p class="text-text-secondary mt-1">បង្កើត និងមើល Payslip សម្រាប់បញ្ជីដែលត្រូវបានអនុម័ត។</p>
    </header>

    <?php if (isset($_SESSION['success'])): ?>
      <div class="alert-message alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
      <div class="alert-message alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <section class="mb-8">
      <h2 class="text-2xl font-bold text-accent-hover mb-4">បញ្ជីដែលបានអនុម័ត (ត្រៀមសម្រាប់បង្កើត Payslip)</h2>
      <?php if (empty($approved_runs)): ?>
        <div class="card-base p-8 text-center text-text-secondary">
          <i class="fas fa-inbox fa-3x mb-4"></i>
          <p class="text-lg">គ្មានបញ្ជីដែលបានអនុម័តកំពុងរង់ចាំដំណើរការទេ។</p>
        </div>
      <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($approved_runs as $run): ?>
            <div class="card-base p-6 flex flex-col justify-between">
              <div>
                <h3 class="text-xl font-bold">ប្រាក់ខែ: <?php echo date('F Y', strtotime($run['month'])); ?></h3>
                <p class="text-text-secondary">អនុម័តដោយ: <?php echo htmlspecialchars($run['approver_name']); ?></p>
                <p class="text-text-secondary">កាលបរិច្ឆេទអនុម័ត: <?php echo date('d-M-Y H:i', strtotime($run['approved_at'])); ?></p>
                <strong class="text-lg text-accent-hover mt-3 block">ប្រាក់ត្រូវបើកសរុប: $<?php echo number_format($run['total_net_salary'], 2); ?></strong>
              </div>
              <div class="flex justify-end gap-3 mt-6">
                <button type="button" class="btn-base btn-secondary open-modal-btn" data-run-id="<?php echo $run['id']; ?>" data-run-month="<?php echo date('F Y', strtotime($run['month'])); ?>">
                  <i class="fas fa-check-double"></i> សម្គាល់ថាបានទូទាត់
                </button>
                <a href="payroll_payslip.php?run_id=<?php echo $run['id']; ?>" class="btn-base btn-primary">
                  <i class="fas fa-file-invoice-dollar"></i> បង្កើត/មើល Payslip
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="card-base p-0">
       <div class="p-6">
         <h2 class="text-2xl font-bold text-accent-hover">ប្រវត្តិការទូទាត់ប្រាក់បៀវត្ស</h2>
       </div>
      <div class="overflow-x-auto table-container">
        <table class="min-w-full">
          <thead>
            <tr><th>ខែ</th><th>ស្ថានភាព</th><th>អនុម័តដោយ</th><th>ប្រាក់ត្រូវបើកសរុប</th><th>សកម្មភាព</th></tr>
          </thead>
          <tbody>
          <?php if (empty($paid_runs)): ?>
            <tr><td colspan="5" class="text-center py-8 text-text-secondary">គ្មានប្រវត្តិទេ។</td></tr>
          <?php else: ?>
            <?php foreach ($paid_runs as $run): ?>
            <tr>
              <td class="font-semibold"><?php echo date('F Y', strtotime($run['month'])); ?></td>
              <td><span class="status-badge status-paid">បានទូទាត់</span></td>
              <td><?php echo htmlspecialchars($run['approver_name']); ?></td>
              <td>$<?php echo number_format($run['total_net_salary'], 2); ?></td>
              <td>
                <a href="payroll_payslip.php?run_id=<?php echo $run['id']; ?>" class="text-accent-hover hover:underline">
                  <i class="fas fa-eye"></i> មើល Payslips
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

  <?php endif; ?>

  </main>
   
  <?php if ($run_id == 0): ?>
  <div id="markPaidModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center p-4 z-50 hidden">
    <div class="card-base w-full max-w-md p-0 mx-auto rounded-lg shadow-xl">
      <form method="POST" action="payroll_payslip.php">
        <div class="p-6 border-b" style="border-color: var(--border-color);">
          <h5 class="text-xl font-bold text-accent-hover">បញ្ជាក់ការសម្គាល់ថាបានទូទាត់</h5>
        </div>
        <div class="p-6">
          <input type="hidden" name="action" value="mark_paid">
          <input type="hidden" name="run_id" id="paidRunId">
          <p>តើអ្នកពិតជាចង់សម្គាល់ថាបញ្ជីបើកប្រាក់បៀវត្សសម្រាប់ខែ <strong id="paidRunMonth"></strong> បានទូទាត់ហើយមែនទេ?</p>
          <p class="text-text-secondary mt-2">សកម្មភាពនេះនឹងផ្លាស់ទីបញ្ជីទៅក្នុងប្រវត្តិ និងមិនអាចត្រឡប់វិញបានទេ។</p>
        </div>
        <div class="p-4 flex justify-end gap-3 bg-opacity-50" style="background-color: var(--secondary-bg);">
          <button type="button" class="btn-base btn-secondary" id="closeModalBtn">បោះបង់</button>
          <button type="submit" class="btn-base btn-success">យល់ព្រម</button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>


  <script>
  document.addEventListener('DOMContentLoaded', function() {
     
    // === 1. JAVASCRIPT FOR SIDEBAR DROPDOWN ===
    const payrollToggle = document.getElementById('payroll-toggle');
    const payrollSubmenu = document.getElementById('payroll-submenu');
    const payrollArrow = document.getElementById('payroll-arrow');

    if (payrollToggle && payrollSubmenu && payrollArrow) {
      // Check initial state on page load
      // If the main toggle button is active, it means we are on a payroll page, so show the submenu.
      if (payrollToggle.classList.contains('active')) {
        payrollSubmenu.classList.remove('hidden');
        payrollArrow.classList.add('rotate-180');
      }

      // Add click event listener to the toggle button
      payrollToggle.addEventListener('click', function() {
        payrollSubmenu.classList.toggle('hidden');
        payrollArrow.classList.toggle('rotate-180');
      });
    }

    // === 2. JAVASCRIPT FOR "MARK AS PAID" MODAL ===
    const markPaidModal = document.getElementById('markPaidModal');
    const openModalButtons = document.querySelectorAll('.open-modal-btn');
    const closeModalBtn = document.getElementById('closeModalBtn');
     
    if (markPaidModal) {
      const openModal = (event) => {
        const button = event.currentTarget;
        const runId = button.getAttribute('data-run-id');
        const runMonth = button.getAttribute('data-run-month');
         
        markPaidModal.querySelector('#paidRunId').value = runId;
        markPaidModal.querySelector('#paidRunMonth').textContent = runMonth;
         
        markPaidModal.classList.remove('hidden');
      };

      const closeModal = () => {
        markPaidModal.classList.add('hidden');
      };

      openModalButtons.forEach(button => {
        button.addEventListener('click', openModal);
      });

      if(closeModalBtn) {
         closeModalBtn.addEventListener('click', closeModal);
      }
       
      markPaidModal.addEventListener('click', function(event) {
        if (event.target === markPaidModal) {
          closeModal();
        }
      });
    }
  });
  </script>
</body>
</html>