<?php
session_start();

// Load Theme Config
$themeConfigPath = __DIR__ . '/includes/theme_config.json';
$currentTheme = 'default';
$customImage = '';
if (file_exists($themeConfigPath)) {
    $configData = json_decode(file_get_contents($themeConfigPath), true);
    $currentTheme = $configData['theme'] ?? 'default';
    $customImage = $configData['custom_image'] ?? '';
}

// Default Background Images for each theme
$themeBackgrounds = [   
    'kny'  => 'https://i.ibb.co/RKMS4tb/khmer-new-year-bg-1770518313913.jpg',
    'pb'   => 'https://i.ibb.co/S4dYb35p/khmer-new-year-bg-1770518389358.jpg',
    'cny'  => 'https://i.ibb.co/4462998/khmer-new-year-bg-1770518448823.jpg',
    'wf'   => 'https://i.ibb.co/2611144/khmer-new-year-bg-1770518505378.jpg',
    'kb'   => 'https://images.unsplash.com/photo-1596701062351-be5f6a200a45?q=80&w=1600',
    'indy' => 'https://images.unsplash.com/photo-1629813289069-7c8704204d60?q=80&w=1600'
];

// Determine which image to use
$bgImage = !empty($customImage) ? $customImage : ($themeBackgrounds[$currentTheme] ?? '');


// Define admin status (modify based on your auth system)
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=samann1_admin_panel', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true,
    ]);
    $pdo->exec("SET NAMES 'utf8mb4'");

    // Get current year and month as default
    $selectedYear = $_GET['year'] ?? date('Y');
    $selectedMonth = $_GET['month'] ?? date('m');

    // Base WHERE clause for monthly filter
    $whereClause = " WHERE YEAR(request_date) = :year AND MONTH(request_date) = :month";

    // Queries to analyze data with monthly filter
    $queries = [
        'total_requests' => "SELECT requester_name, COUNT(*) as count FROM requests $whereClause GROUP BY requester_name ORDER BY count DESC LIMIT 5",
        'ot_requests' => "SELECT requester_name, COUNT(*) as count FROM requests $whereClause AND (request_type LIKE '%OT%' OR request_type LIKE '%ថែមម៉ោង%') GROUP BY requester_name ORDER BY count DESC LIMIT 5",
        'shift_changes' => "SELECT requester_name, COUNT(*) as count FROM requests $whereClause AND (request_type LIKE '%Changing day off%' OR request_type LIKE '%ប្តូរថ្ងៃឈប់សម្រាក%') GROUP BY requester_name ORDER BY count DESC LIMIT 5",     
        'forgot_scan' => "SELECT requester_name, COUNT(*) as count FROM requests $whereClause AND (forgot_scan_in != '' OR forgot_scan_out != '') GROUP BY requester_name ORDER BY count DESC LIMIT 5",
        'leave_requests' => "SELECT requester_name, COUNT(*) as count FROM requests $whereClause AND (request_type LIKE '%Leave%' OR request_type LIKE '%ច្បាប់%') GROUP BY requester_name ORDER BY count DESC LIMIT 5",
    ];

    $results = [];
    foreach ($queries as $key => $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['year' => $selectedYear, 'month' => $selectedMonth]);
        $results[$key] = $stmt->fetchAll();
    }

    // Query for sum of all requests per month across all years
    $sumRequestsStmt = $pdo->query("SELECT YEAR(request_date) as year, MONTH(request_date) as month, COUNT(*) as count FROM requests GROUP BY YEAR(request_date), MONTH(request_date) ORDER BY year, month");
    $sumRequests = $sumRequestsStmt->fetchAll();

    // Get available years for the filter
    $yearStmt = $pdo->query("SELECT DISTINCT YEAR(request_date) as year FROM requests ORDER BY year DESC");
    $years = $yearStmt->fetchAll();
} catch (PDOException $e) {
    $error = "ការតភ្ជាប់មូលដ្ឋានទិន្នន័យបរាជ័យ: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>វិភាគសំណើ</title>
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);
            --border-radius: 16px;
            --font-family-khmer: 'Noto Sans Khmer', sans-serif;
        }

        body {
            font-family: var(--font-family-khmer);
            background-color: #f3f4f6;
            color: #2c3e50;
            padding-bottom: 2rem;
            min-height: 100vh;
        }

        @keyframes bgZoom {
            from { background-size: 100% 100%; }
            to { background-size: 110% 110%; }
        }

        /* Floating Animation for Theme Icons */
        @keyframes floatUpDown {
            0% { transform: translateY(0) rotate(-15deg); }
            50% { transform: translateY(-15px) rotate(-10deg); }
            100% { transform: translateY(0) rotate(-15deg); }
        }

        /* Season/Festival Theme Overrides */
        <?php if ($currentTheme === 'kny'): ?>
        :root { --primary-btn: #f59e0b; --primary-btn-hover: #d97706; }
        .header-section { border-color: rgba(245, 158, 11, 0.3) !important; }
        .header-title h1 { background: linear-gradient(135deg, #f59e0b, #d97706) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; }
        .card::after { 
            content: ""; position: absolute; bottom: -10px; right: -10px; width: 80px; height: 80px;
            background-image: url('https://i.ibb.co/qFRZ8SCK/khmer-new-year.png');
            background-size: contain; background-repeat: no-repeat;
            opacity: 0.12; filter: drop-shadow(0 5px 8px rgba(0,0,0,0.1));
            animation: floatUpDown 6s ease-in-out infinite;
        }
        /* Fireworks Overlay for KNY */
        body::after {
            content: "";
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('https://media.tenor.com/XesYJjyNYgAAAAAi/fireworks-putukan.gif');
            background-size: cover; background-repeat: no-repeat;
            pointer-events: none; z-index: -1; opacity: 0.35; mix-blend-mode: screen;
        }
        
        <?php elseif ($currentTheme === 'pb'): ?>
        :root { --primary-btn: #ea580c; --primary-btn-hover: #c2410c; }
        .header-section { border-color: rgba(234, 88, 12, 0.3) !important; }
        .header-title h1 { background: linear-gradient(135deg, #ea580c, #c2410c) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; }
        .card::after { 
            content: "\f67f"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
            position: absolute; bottom: -5px; right: -5px; font-size: 60px;
            opacity: 0.1; color: #ea580c; animation: floatUpDown 6s ease-in-out infinite;
        }

        <?php elseif ($currentTheme === 'cny'): ?>
        :root { --primary-btn: #dc2626; --primary-btn-hover: #b91c1c; }
        .header-section { border-color: rgba(220, 38, 38, 0.3) !important; }
        .header-title h1 { background: linear-gradient(135deg, #dc2626, #b91c1c) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; }
        .card::after { 
            content: ""; position: absolute; bottom: -10px; right: -10px; width: 80px; height: 80px;
            background-image: url('https://i.ibb.co/G4K8Mv36/chinese-new-year.png');
            background-size: contain; background-repeat: no-repeat;
            opacity: 0.12; filter: drop-shadow(0 5px 8px rgba(0,0,0,0.1));
            animation: floatUpDown 6s ease-in-out infinite;
        }

        <?php elseif ($currentTheme === 'wf'): ?>
        :root { --primary-btn: #0284c7; --primary-btn-hover: #0369a1; }
        .header-section { border-color: rgba(2, 132, 199, 0.3) !important; }
        .header-title h1 { background: linear-gradient(135deg, #0284c7, #0369a1) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; }
        .card::after { 
            content: "\f773"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
            position: absolute; bottom: -5px; right: -5px; font-size: 70px;
            opacity: 0.1; color: #0284c7; animation: floatUpDown 6s ease-in-out infinite;
        }
        <?php endif; ?>

        /* Apply Theme Background Image */
        <?php if (!empty($bgImage)): ?>
        body {
            background-image: url('<?php echo $bgImage; ?>') !important;
            background-size: cover !important;
            background-position: center !important;
            background-attachment: fixed !important;
            background-repeat: no-repeat !important;
            animation: bgZoom 20s ease-in-out infinite alternate !important;
        }

        /* Overlay to ensure readability */
        body::before {
            content: "";
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.7);
            z-index: -2;
        }
        <?php endif; ?>

        .container {
            max-width: 1400px;
            padding: 2rem 1rem;
        }

        .header-section {
            background: var(--glass-bg);
            backdrop-filter: blur(8px);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--glass-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-title {
            font-family: var(--font-family-khmer);
            font-weight: 700;
            color: #2c3e50;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .form-select {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 0.5rem 2rem 0.5rem 1rem;
            font-family: var(--font-family-khmer);
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        /* Specific layout for the big chart */
        .full-width-card {
            grid-column: 1 / -1;
        }

        .card {
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: var(--border-radius);
            box-shadow: var(--glass-shadow);
            padding: 1.5rem;
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            background: none;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding-bottom: 1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h3 {
            font-family: var(--font-family-khmer);
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            color: #2c3e50;
        }

        .card-header i {
            font-size: 1.5rem;
            color: #667eea;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        .card-table {
            width: 100%;
            margin-top: 1rem;
            border-collapse: separate;
            border-spacing: 0;
        }

        .card-table th, .card-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid #edf2f7;
            font-family: var(--font-family-khmer);
        }

        .card-table th {
            font-weight: 600;
            color: #718096;
            background-color: #f7fafc;
        }

        .card-table tr:last-child td {
            border-bottom: none;
        }

        .alert-error {
            background-color: #ffe5e5;
            color: #c53030;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #c53030;
        }

        .btn-back {
            text-decoration: none;
            color: #667eea;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: color 0.2s;
        }
        
        .btn-back:hover {
            color: #764ba2;
        }

        @media (max-width: 768px) {
            .card-grid {
                grid-template-columns: 1fr;
            }
            .header-section {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Error Message -->
        <?php if (isset($error)): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="header-section">
            <div class="header-title">
                <i class="fas fa-chart-pie fa-lg"></i>
                <h1>វិភាគសំណើ</h1>
            </div>
            <div class="filter-form">
                <form method="GET" class="d-flex gap-2">
                    <select name="year" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y['year']; ?>" <?php echo $y['year'] == $selectedYear ? 'selected' : ''; ?>>
                                <?php echo $y['year']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="month" class="form-select" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $selectedMonth ? 'selected' : ''; ?>>
                                <?php echo sprintf('%02d', $m); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </form>
                <a href="../homes.php" class="btn-back"><i class="fas fa-arrow-left"></i> ត្រឡប់ក្រោយ</a>
            </div>
        </div>

        <?php if (!isset($error)): ?>
            <div class="card-grid">
                <!-- Sum of All Requests (Full Width) -->
                <div class="card full-width-card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar"></i>
                        <h3>សំណើសរុបប្រចាំខែ (គ្រប់ឆ្នាំ)</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="sumRequestsChart"></canvas>
                    </div>
                </div>

                <!-- Total Requests by Person -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-users"></i>
                        <h3>អ្នកស្នើសុំច្រើនជាងគេ (សរុប)</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="totalRequestsChart"></canvas>
                    </div>
                    <table class="card-table">
                        <thead>
                            <tr>
                                <th>ឈ្មោះ</th>
                                <th>ចំនួន</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['total_requests'] as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['requester_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Leave Requests -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-envelope-open-text"></i>
                        <h3>អ្នកស្នើសុំច្បាប់ច្រើនជាងគេ</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="leaveRequestsChart"></canvas>
                    </div>
                    <table class="card-table">
                        <thead>
                            <tr>
                                <th>ឈ្មោះ</th>
                                <th>ចំនួន</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['leave_requests'] as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['requester_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Shift Changes -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-sync-alt"></i>
                        <h3>អ្នកសុំប្តូរថ្ងៃឈប់ច្រើនជាងគេ</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="shiftChangesChart"></canvas>
                    </div>
                    <table class="card-table">
                        <thead>
                            <tr>
                                <th>ឈ្មោះ</th>
                                <th>ចំនួន</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['shift_changes'] as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['requester_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- OT Requests -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-clock"></i>
                        <h3>អ្នកសុំថែមម៉ោងច្រើនជាងគេ</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="otRequestsChart"></canvas>
                    </div>
                    <table class="card-table">
                        <thead>
                            <tr>
                                <th>ឈ្មោះ</th>
                                <th>ចំនួន OT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['ot_requests'] as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['requester_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Forgot Scan -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-fingerprint"></i>
                        <h3>អ្នកភ្លេចស្កេនច្រើនជាងគេ</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="forgotScanChart"></canvas>
                    </div>
                    <table class="card-table">
                        <thead>
                            <tr>
                                <th>ឈ្មោះ</th>
                                <th>ចំនួន</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['forgot_scan'] as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['requester_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['count']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Chart.js Configuration
            const chartOptions = {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'ចំនួន', font: { size: 14, family: 'Noto Sans Khmer' } },
                        grid: { color: 'rgba(0, 0, 0, 0.05)' }
                    },
                    x: {
                        title: { display: true, text: 'ឈ្មោះ', font: { size: 14, family: 'Noto Sans Khmer' } },
                        ticks: { font: { family: 'Noto Sans Khmer' } }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(44, 62, 80, 0.9)',
                        titleFont: { size: 14, family: 'Noto Sans Khmer' },
                        bodyFont: { size: 12, family: 'Noto Sans Khmer' }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                }
            };

            // Sum of All Requests per Month Chart
            new Chart(document.getElementById('sumRequestsChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function($row) { return $row['year'] . '-' . sprintf('%02d', $row['month']); }, $sumRequests)); ?>,
                    datasets: [{
                        label: 'សំណើសរុប',
                        data: <?php echo json_encode(array_column($sumRequests, 'count')); ?>,
                        backgroundColor: 'rgba(46, 204, 113, 0.8)', // Emerald green
                        borderColor: 'rgba(46, 204, 113, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    ...chartOptions,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'ចំនួនសំណើ', font: { size: 14, family: 'Noto Sans Khmer' } },
                            grid: { color: 'rgba(0, 0, 0, 0.05)' }
                        },
                        x: {
                            title: { display: true, text: 'ខែ-ឆ្នាំ', font: { size: 14, family: 'Noto Sans Khmer' } },
                            ticks: { font: { family: 'Noto Sans Khmer' } }
                        }
                    }
                }
            });

            // Total Requests Chart
            new Chart(document.getElementById('totalRequestsChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($results['total_requests'], 'requester_name')); ?>,
                    datasets: [{ label: 'សំណើសរុប', data: <?php echo json_encode(array_column($results['total_requests'], 'count')); ?>, backgroundColor: 'rgba(54, 162, 235, 0.8)', borderColor: 'rgba(54, 162, 235, 1)', borderWidth: 1 }]
                },
                options: chartOptions
            });

            // Leave Requests Chart
            new Chart(document.getElementById('leaveRequestsChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($results['leave_requests'], 'requester_name')); ?>,
                    datasets: [{ label: 'ច្បាប់', data: <?php echo json_encode(array_column($results['leave_requests'], 'count')); ?>, backgroundColor: 'rgba(255, 99, 132, 0.8)', borderColor: 'rgba(255, 99, 132, 1)', borderWidth: 1 }]
                },
                options: chartOptions
            });

            // Shift Changes Chart
            new Chart(document.getElementById('shiftChangesChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($results['shift_changes'], 'requester_name')); ?>,
                    datasets: [{ label: 'ប្តូរថ្ងៃឈប់', data: <?php echo json_encode(array_column($results['shift_changes'], 'count')); ?>, backgroundColor: 'rgba(75, 192, 192, 0.8)', borderColor: 'rgba(75, 192, 192, 1)', borderWidth: 1 }]
                },
                options: chartOptions
            });

            // OT Requests Chart
            new Chart(document.getElementById('otRequestsChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($results['ot_requests'], 'requester_name')); ?>,
                    datasets: [{ label: 'OT', data: <?php echo json_encode(array_column($results['ot_requests'], 'count')); ?>, backgroundColor: 'rgba(255, 159, 64, 0.8)', borderColor: 'rgba(255, 159, 64, 1)', borderWidth: 1 }]
                },
                options: chartOptions
            });

            // Forgot Scan Chart
            new Chart(document.getElementById('forgotScanChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($results['forgot_scan'], 'requester_name')); ?>,
                    datasets: [{ label: 'ភ្លេចស្កេន', data: <?php echo json_encode(array_column($results['forgot_scan'], 'count')); ?>, backgroundColor: 'rgba(153, 102, 255, 0.8)', borderColor: 'rgba(153, 102, 255, 1)', borderWidth: 1 }]
                },
                options: chartOptions
            });
        });
    </script>
</body>
</html>
