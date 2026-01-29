<?php
session_start();

// Define admin status (modify based on your auth system)
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=samann1_admin_panel', 'samann1_admin_panel', 'admin_panel@2025', [
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
        'shift_changes' => "SELECT requester_name, COUNT(*) as count FROM requests $whereClause AND (request_type LIKE '%Changing day off%' OR request_type LIKE '%ប្តូរថ្ងៃសម្រាក%') GROUP BY requester_name ORDER BY count DESC LIMIT 5",
        'forgot_scan' => "SELECT requester_name, COUNT(*) as count FROM requests $whereClause AND (forgot_scan_in != '' OR forgot_scan_out != '') GROUP BY requester_name ORDER BY count DESC LIMIT 5",
        'leave_requests' => "SELECT requester_name, COUNT(*) as count FROM requests $whereClause AND (request_type LIKE '%Leave%' OR request_type LIKE '%សម្រាក%') GROUP BY requester_name ORDER BY count DESC LIMIT 5",
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
    $error = "កំហុសមូលដ្ឋានទិន្នន័យ: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2x/Logo-Van-Van-1.png">
    <title>ការវិភាគសំណើរ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap');

        :root {
            --primary: #3498db;
            --secondary: #2c3e50;
            --background: #e9ecef;
            --card-bg: rgba(255, 255, 255, 0.9);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Noto Sans Khmer', sans-serif;
            background: linear-gradient(135deg, #e9ecef, #d1d9e6);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Main Content */
        .main-content {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: fadeIn 0.5s ease;
        }
        .header h1 {
            font-size: 2rem;
            color: var(--secondary);
            margin: 0;
            font-weight: 700;
        }
        .header .back-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            transition: color 0.3s;
        }
        .header .back-link:hover {
            color: #2980b9;
        }
        .header .back-link i {
            margin-right: 8px;
        }
        .filter-form {
            display: flex;
            gap: 15px;
        }
        .filter-form select {
            padding: 10px 15px;
            border-radius: 8px;
            border: none;
            background: rgba(255, 255, 255, 0.8);
            box-shadow: inset 2px 2px 5px rgba(0, 0, 0, 0.1);
            font-size: 1rem;
            transition: box-shadow 0.3s;
        }
        .filter-form select:focus {
            box-shadow: 0 0 10px rgba(52, 152, 219, 0.5);
            outline: none;
        }

        /* Cards */
        .card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: slideUp 0.5s ease;
        }
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }
        .card-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .card-header i {
            font-size: 1.8rem;
            color: var(--primary);
            margin-right: 15px;
        }
        .card-header h3 {
            font-size: 1.4rem;
            color: var(--secondary);
            margin: 0;
            font-weight: 600;
        }
        .chart-container {
            height: 280px;
            margin-bottom: 20px;
        }
        .card-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }
        .card-table th, .card-table td {
            padding: 12px;
            text-align: left;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 8px;
        }
        .card-table th {
            background: var(--primary);
            color: white;
            font-weight: 600;
        }
        .card-table td {
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .card-table tr {
            transition: background 0.3s;
        }
        .card-table tr:hover td {
            background: rgba(240, 248, 255, 0.9);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Responsive */
        @media (max-width: 992px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            .filter-form {
                flex-direction: column;
                width: 100%;
            }
            .filter-form select {
                width: 100%;
            }
            .chart-container {
                height: 220px;
            }
        }
        @media (max-width: 576px) {
            body {
                padding: 10px;
            }
            .header h1 {
                font-size: 1.5rem;
            }
            .header .back-link {
                font-size: 1rem;
            }
            .card-header h3 {
                font-size: 1.2rem;
            }
            .card-header i {
                font-size: 1.4rem;
            }
            .card-table th, .card-table td {
                padding: 8px;
                font-size: 0.9rem;
            }
            .chart-container {
                height: 180px;
            }
        }
    </style>
</head>
<body>
    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>ការវិភាគសំណើរ</h1>
            <div class="filter-form">
                <form method="GET" action="">
                    <select name="year" id="year" onchange="this.form.submit()">
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo $year['year']; ?>" <?php echo $year['year'] == $selectedYear ? 'selected' : ''; ?>>
                                <?php echo $year['year']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="month" id="month" onchange="this.form.submit()">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo sprintf('%02d', $m); ?>" <?php echo $m == $selectedMonth ? 'selected' : ''; ?>>
                                <?php echo sprintf('%02d', $m); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </form>
            </div>
            <a href="https://app.vvc.asia/requests_menu.php" class="back-link"><i class="fas fa-arrow-left"></i> ត្រឡប់ក្រោយ</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php else: ?>
            <!-- Sum of All Requests per Month -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-bar"></i>
                    <h3>សរុបសំណើរសម្រាប់មួយខែៗ</h3>
                </div>
                <div class="chart-container">
                    <canvas id="sumRequestsChart"></canvas>
                </div>
                <table class="card-table">
                    <thead>
                        <tr>
                            <th>ឆ្នាំ</th>
                            <th>ខែ</th>
                            <th>ចំនួនសំណើសរុប</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sumRequests as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['year']); ?></td>
                                <td><?php echo htmlspecialchars(sprintf('%02d', $row['month'])); ?></td>
                                <td><?php echo htmlspecialchars($row['count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Total Requests -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list"></i>
                    <h3>អ្នកមានសំណើច្រើនជាងគេ</h3>
                </div>
                <div class="chart-container">
                    <canvas id="totalRequestsChart"></canvas>
                </div>
                <table class="card-table">
                    <thead>
                        <tr>
                            <th>ឈ្មោះ</th>
                            <th>ចំនួនសំណើ</th>
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
                    <i class="fas fa-calendar-times"></i>
                    <h3>អ្នកមានច្បាប់ឈប់ច្រើនជាងគេ</h3>
                </div>
                <div class="chart-container">
                    <canvas id="leaveRequestsChart"></canvas>
                </div>
                <table class="card-table">
                    <thead>
                        <tr>
                            <th>ឈ្មោះ</th>
                            <th>ចំនួនច្បាប់ឈប់</th>
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
                    <i class="fas fa-exchange-alt"></i>
                    <h3>អ្នកប្តូរដេអូសច្រើនជាងគេ</h3>
                </div>
                <div class="chart-container">
                    <canvas id="shiftChangesChart"></canvas>
                </div>
                <table class="card-table">
                    <thead>
                        <tr>
                            <th>ឈ្មោះ</th>
                            <th>ចំនួនប្តូរដេអូស</th>
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
                    <h3>អ្នកធ្វើ OT ច្រើនជាងគេ</h3>
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
                            <th>ចំនួនភ្លេចស្កេន</th>
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
                        label: 'ចំនួនសំណើសរុប',
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
                            title: { display: true, text: 'ឆ្នាំ-ខែ', font: { size: 14, family: 'Noto Sans Khmer' } },
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
                    datasets: [{ label: 'ចំនួនសំណើ', data: <?php echo json_encode(array_column($results['total_requests'], 'count')); ?>, backgroundColor: 'rgba(54, 162, 235, 0.8)', borderColor: 'rgba(54, 162, 235, 1)', borderWidth: 1 }]
                },
                options: chartOptions
            });

            // Leave Requests Chart
            new Chart(document.getElementById('leaveRequestsChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($results['leave_requests'], 'requester_name')); ?>,
                    datasets: [{ label: 'ចំនួនច្បាប់ឈប់', data: <?php echo json_encode(array_column($results['leave_requests'], 'count')); ?>, backgroundColor: 'rgba(255, 99, 132, 0.8)', borderColor: 'rgba(255, 99, 132, 1)', borderWidth: 1 }]
                },
                options: chartOptions
            });

            // Shift Changes Chart
            new Chart(document.getElementById('shiftChangesChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($results['shift_changes'], 'requester_name')); ?>,
                    datasets: [{ label: 'ចំនួនប្តូរដេអូស', data: <?php echo json_encode(array_column($results['shift_changes'], 'count')); ?>, backgroundColor: 'rgba(75, 192, 192, 0.8)', borderColor: 'rgba(75, 192, 192, 1)', borderWidth: 1 }]
                },
                options: chartOptions
            });

            // OT Requests Chart
            new Chart(document.getElementById('otRequestsChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($results['ot_requests'], 'requester_name')); ?>,
                    datasets: [{ label: 'ចំនួន OT', data: <?php echo json_encode(array_column($results['ot_requests'], 'count')); ?>, backgroundColor: 'rgba(255, 159, 64, 0.8)', borderColor: 'rgba(255, 159, 64, 1)', borderWidth: 1 }]
                },
                options: chartOptions
            });

            // Forgot Scan Chart
            new Chart(document.getElementById('forgotScanChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($results['forgot_scan'], 'requester_name')); ?>,
                    datasets: [{ label: 'ចំនួនភ្លេចស្កេន', data: <?php echo json_encode(array_column($results['forgot_scan'], 'count')); ?>, backgroundColor: 'rgba(153, 102, 255, 0.8)', borderColor: 'rgba(153, 102, 255, 1)', borderWidth: 1 }]
                },
                options: chartOptions
            });
        });
    </script>
</body>
</html>