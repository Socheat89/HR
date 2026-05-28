<?php
// Start session with security enhancements
session_start();
session_regenerate_id(true);

// --- CONFIGURATION ---
$dbHost = 'localhost';
$dbName = 'samann1_admin_panel';
$dbUser = 'samann1_admin_panel';
$dbPass = '';
define('BASE_URL', $_SERVER['PHP_SELF']);

// --- DATABASE CONNECTION ---
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (PDOException $e) {
    die("בבב ב»בבבבב»בבב¶בבבבב¶בבבב¼בבבבב¶בבב·בבבבבבב");
}

// --- GET CURRENT USER INFO ---
$currentUserFullName = 'ב¢בבבבבבב¾בב·בבבבב¶בב';
$currentUsername = null;
$annualLeaveBalance = 9;
if (isset($_SESSION['username'])) {
    $currentUsername = $_SESSION['username'];
    $stmtUser = $pdo->prepare("SELECT full_name, annual_leave_balance FROM users WHERE username = ? LIMIT 1");
    $stmtUser->execute([$currentUsername]);
    $user = $stmtUser->fetch();
    if ($user) {
        $currentUserFullName = $user['full_name'];
        $annualLeaveBalance = $user['annual_leave_balance'];
    }
}

// --- CHECK ADMIN STATUS ---
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// --- GET USER TO ANALYZE ---
$targetUsername = null;
$targetFullName = null;
$targetAnnualLeaveBalance = 9;
$requestSummary = [];
$error = null;
$dataWarning = null;

if ($isAdmin && isset($_GET['username'])) {
    $targetUsername = filter_var($_GET['username'], FILTER_SANITIZE_STRING);
    if (empty($targetUsername)) {
        $error = "בבבבבב¢בבבבבבב¾בב·בבבבב¹בבבבב¼בב";
    } else {
        $stmtUser = $pdo->prepare("SELECT full_name, annual_leave_balance FROM users WHERE username = ? LIMIT 1");
        $stmtUser->execute([$targetUsername]);
        $user = $stmtUser->fetch();
        if ($user) {
            $targetFullName = $user['full_name'];
            $targetAnnualLeaveBalance = $user['annual_leave_balance'];
        } else {
            $error = "ב¢בבבבבבב¾בבבב¶בבבב·בבבבב¼בבב¶בבבבב¾בב";
        }
    }
} elseif (!$isAdmin) {
    $targetUsername = $currentUsername;
    $targetFullName = $currentUserFullName;
    $targetAnnualLeaveBalance = $annualLeaveBalance;
} else {
    $error = "בב¼בבבבבב¼בבבבבבב¢בבבבבבב¾ב";
}

// --- FETCH REQUESTS FOR SELECTED MONTH ---
if ($targetFullName && !$error) {
    try {
        // Define request types
        $requestTypes = [
            'Annual Leave' => 'בבבבב¶בבבבבב¶בבבבב¶ב (Annual Leave)',
            'Sick Leave' => 'בבבבב¶בבבבבבבב÷ (Sick Leave)',
            'Forgot FP' => 'בבבבבבבבבבבבבב (Forgot FP)',
            'Maternity Leave' => 'בבבבב¶בבבב בבב¶בב»בב¶ב (Maternity Leave)',
            'OT' => 'בבבבבבב (OT)',
            'Early' => 'בבבבב»בבבבב (Early)',
            'Changing day off' => 'בבבב¼בבבבבבבבבב¶ב (Changing day off)',
            'Special Leave' => 'בבבבב¶בבב·בבב (Special Leave)',
            'Late' => 'בבבב÷ב (Late)'
        ];

        // Initialize summary array
        $requestSummary = array_fill_keys(array_values($requestTypes), 0);

        // Get selected month and year (default to current month/year if not set)
        $selectedMonth = isset($_GET['month']) ? filter_var($_GET['month'], FILTER_VALIDATE_REGEXP, [
            'options' => ['regexp' => '/^(0[1-9]|1[0-2])$/']
        ]) : date('m');
        $selectedMonth = $selectedMonth ?: date('m'); // Fallback to current month if invalid
        $currentYear = date('Y'); // Extend to allow year selection if needed

        // Query requests for selected month
        $sql = "SELECT request_type, COUNT(*) as count, SUM(COALESCE(number_of_days, 0)) as total_days 
                FROM requests 
                WHERE requester_name = ? 
                AND MONTH(created_at) = ? AND YEAR(created_at) = ?
                GROUP BY request_type";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$targetFullName, $selectedMonth, $currentYear]);
        $results = $stmt->fetchAll();

        // Process results
        $totalAnnualLeaveDays = 0;
        foreach ($results as $row) {
            $types = array_map('trim', explode(',', $row['request_type']));
            foreach ($types as $type) {
                foreach ($requestTypes as $key => $definedType) {
                    if ($type === $key || $type === $definedType) {
                        if ($key === 'Annual Leave') {
                            if ($row['total_days'] == 0) {
                                $dataWarning = "בבבבבב¶בבבבב½בבבבבבבבבב¶בבבבבב¶בבבבב¶בבב¶בבבבב ב¶ב";
                            } else {
                                $requestSummary[$definedType] = floatval($row['total_days']);
                                $totalAnnualLeaveDays += floatval($row['total_days']);
                            }
                        } else {
                            $requestSummary[$definedType] += $row['count'];
                        }
                        break;
                    }
                }
            }
        }

        // Calculate remaining balance
        $calculatedBalance = max(0, $targetAnnualLeaveBalance - $totalAnnualLeaveDays);

        // Log calculation
        error_log("User: $targetFullName, Month: $selectedMonth/$currentYear, Total Annual Leave Days: $totalAnnualLeaveDays, Initial Balance: $targetAnnualLeaveBalance, Calculated Balance: $calculatedBalance", 3, 'leave_calc.log');

    } catch (PDOException $e) {
        $error = "בבב ב»בבבבבב¶בבב·בבבבבבב";
        error_log("Database error: " . $e->getMessage(), 3, 'leave_calc.log');
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2/Logo-Van-Van-1.png">
    <title>בב·בב¶בבבבב¾בב¶בבבבבבב - בבבבב</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap');
        body {
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            font-family: 'Noto Sans Khmer', 'Segoe UI', sans-serif;
            min-height: 100vh;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .report-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            max-width: 800px;
            width: 100%;
        }
        .report-title {
            color: #2c3e50;
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
        }
        .user-info, .leave-balance {
            font-size: 1.2rem;
            color: #34495e;
            text-align: center;
            margin-bottom: 1rem;
        }
        .leave-balance.warning {
            color: #e74c3c;
            font-weight: bold;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e6f0;
        }
        th {
            background-color: #3498db;
            color: white;
            font-weight: 600;
        }
        tr:hover {
            background-color: #f5f7fa;
        }
        .btn-back {
            background-color: #6c757d;
            border: none;
            padding: 10px 20px;
            font-size: 1rem;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: background-color 0.3s ease, transform 0.2s ease;
            margin-top: 1rem;
        }
        .btn-back:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
            color: white;
        }
        .btn-back i {
            margin-right: 8px;
        }
        .alert {
            margin-bottom: 1rem;
        }
        .chart-container {
            position: relative;
            height: 400px;
            margin-top: 2rem;
        }
        @media (max-width: 768px) {
            .report-container { padding: 1rem; }
            .report-title { font-size: 1.5rem; }
            th, td { font-size: 0.9rem; padding: 8px; }
            .btn-back { font-size: 0.9rem; padding: 8px 15px; }
            .chart-container { height: 300px; }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <h2 class="report-title">בב·בב¶בבבבב¾בב¶בבבבבבב - בב <?php echo htmlspecialchars($selectedMonth); ?></h2>

        <form method="GET" action="<?php echo htmlspecialchars(BASE_URL); ?>" class="mb-4">
            <div class="row g-3 align-items-center justify-content-center">
                <div class="col-auto">
                    <label for="month" class="col-form-label">בבבב¾בבב¾בבב:</label>
                </div>
                <div class="col-auto">
                    <select name="month" id="month" class="form-select" onchange="this.form.submit()">
                        <option value="">-- בבבב¾בבב¾בבב --</option>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo sprintf('%02d', $m); ?>" <?php echo (isset($_GET['month']) && $_GET['month'] == sprintf('%02d', $m)) ? 'selected' : ''; ?>>
                                <?php echo sprintf('%02d', $m); ?> - <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <?php if ($isAdmin && isset($_GET['username'])): ?>
                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($targetUsername); ?>">
                <?php endif; ?>
            </div>
        </form>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($dataWarning): ?>
            <div class="alert alert-warning" role="alert"><?php echo htmlspecialchars($dataWarning); ?></div>
        <?php elseif ($targetFullName): ?>
            <div class="user-info">
                <strong>ב¢בבבבבבב¾:</strong> <?php echo htmlspecialchars($targetFullName); ?>
            </div>
            <div class="leave-balance <?php echo $calculatedBalance <= 3 ? 'warning' : ''; ?>">
                <strong>בבבבב¶בבבבבב¶בבבבב¶בבבבבב:</strong> <?php echo number_format($calculatedBalance, 2); ?> בבבב
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover" role="grid" aria-describedby="requestSummaryInfo">
                    <caption id="requestSummaryInfo" class="visually-hidden">בבבבבבבבבב¾בב <?php echo htmlspecialchars($selectedMonth); ?></caption>
                    <thead>
                        <tr>
                            <th scope="col">בבבבבבבבבב¾</th>
                            <th scope="col"><?php echo $requestTypes['Annual Leave'] === 'בבבבב¶בבבבבב¶בבבבב¶ב (Annual Leave)' ? 'בבבב½בבבבב' : 'בבבב½בבבבב¾'; ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requestSummary as $type => $value): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($type); ?></td>
                                <td><?php echo $type === $requestTypes['Annual Leave'] ? number_format($value, 2) : $value; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if (array_sum($requestSummary) > 0): ?>
                <div class="chart-container">
                    <canvas id="requestChart"></canvas>
                </div>
            <?php else: ?>
                <p class="text-center">בב·בבב¶בבבבב¾בבבבב¶בבבבבבבב</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="text-center">בב·בבב¶בבב·בבבבבבב¢בבבבבבב¾ב</p>
        <?php endif; ?>

        <div class="text-center">
            <a href="https://app.vvc.asia/requests_menu.php" class="btn-back" role="button" aria-label="בבבב¡בבבבבבב÷בב»ב">
                <i class="fas fa-arrow-left"></i> בבבב¡בבבב Menu
            </a>
        </div>
    </div>

    <?php if ($targetFullName && array_sum($requestSummary) > 0): ?>
        <script>
            const ctx = document.getElementById('requestChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_values($requestTypes)); ?>,
                    datasets: [{
                        label: 'בבבב½בבבבב¾/בבבב',
                        data: <?php echo json_encode(array_values($requestSummary)); ?>,
                        backgroundColor: ['#3498db', '#e74c3c', '#2ecc71', '#f1c40f', '#9b59b6', '#e67e22', '#1abc9c', '#34495e', '#d35400'],
                        borderColor: ['#2980b9', '#c0392b', '#27ae60', '#f39c12', '#8e44ad', '#d35400', '#16a085', '#2c3e50', '#b34900'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'בבבב½בבבבב¾/בבבב' } },
                        x: { title: { display: true, text: 'בבבבבבבבבב¾' } }
                    },
                    plugins: {
                        legend: { display: false },
                        title: { 
                            display: true, 
                            text: 'בבבב¾בב¶בבבבבבב - בב <?php echo htmlspecialchars($selectedMonth); ?>', 
                            font: { size: 16 } 
                        },
                        tooltip: {
                            enabled: true,
                            callbacks: {
                                label: function(context) {
                                    const label = context.dataset.label;
                                    const value = context.raw;
                                    const index = context.dataIndex;
                                    const type = <?php echo json_encode(array_values($requestTypes)); ?>[index];
                                    return type === '<?php echo $requestTypes['Annual Leave']; ?>' ? `${label}: ${value} בבבב` : `${label}: ${value} בבבב¾`;
                                }
                            }
                        }
                    }
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>
