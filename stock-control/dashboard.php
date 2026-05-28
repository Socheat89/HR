<?php
// FILE: dashboard.php
session_start();
require_once 'nav_logic.php';

try {
    header('Content-Type: text/html; charset=utf-8');

    $current_page = basename($_SERVER['PHP_SELF']);

    // --- Dashboard Stats ---
    $total_items = $pdo->query("SELECT COUNT(*) FROM stock_items")->fetchColumn() ?: 0;
    $total_quantity = $pdo->query("SELECT SUM(quantity) FROM stock_items")->fetchColumn() ?: 0;
    $total_value = $pdo->query("SELECT SUM(quantity * price) FROM stock_items")->fetchColumn() ?: 0;
    $low_stock = $pdo->query("SELECT COUNT(*) FROM stock_items WHERE quantity < 10")->fetchColumn() ?: 0;

    // --- Location Request Stats ---
    $filter_month = $_GET['filter_month'] ?? null;

    $sql_locations = "SELECT location, COUNT(id) AS request_count 
                      FROM stock_request";
    $params = [];

    if ($filter_month && preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
        list($year, $month) = explode('-', $filter_month);
        $sql_locations .= " WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?";
        $params = [$year, $month];
    }

    $sql_locations .= " GROUP BY location ORDER BY request_count DESC";

    $stmt_locations = $pdo->prepare($sql_locations);
    $stmt_locations->execute($params);
    $location_requests = $stmt_locations->fetchAll(PDO::FETCH_ASSOC);

    // --- រៀបចំទិន្នន័យសម្រាប់ Chart ---
    $chart_labels = [];
    $chart_data = [];
    foreach ($location_requests as $request) {
        $chart_labels[] = $request['location'];
        $chart_data[] = $request['request_count'];
    }


    // --- Session Messages ---
    $error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
    $success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';

    unset($_SESSION['error_message']);
    unset($_SESSION['success_message']);

} catch (PDOException $e) {
    $error_message = "កំហុសមូលដ្ឋានទិន្នន័យ: " . $e->getMessage();
    $total_items = $total_quantity = $total_value = $low_stock = 0;
    $location_requests = [];
    $chart_labels = [];
    $chart_data = [];
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ផ្ទាំងគ្រប់គ្រងស្តុក</title>
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2/Logo-Van-Van-1.png">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;600&family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bayon&family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root { 
            --primary-color: #2575fc; 
            --primary-hover: #6a11cb; 
            --light-gray: #f5f7fa; 
            --text-color: #2c3e50; 
        }
        * {
            margin: 0; padding: 0; box-sizing: border-box;
            font-family: 'Kantumruy Pro', 'Inter', sans-serif;
        }
        body { background: var(--light-gray); color: var(--text-color); line-height: 1.5; overflow-x: hidden; }
        @keyframes fadeInScale { from { opacity: 0; transform: scale(0.8) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1050; align-items: center; justify-content: center; }
        .modal.show { display: flex; animation: fadeInScale 0.3s forwards; }
        .modal-content { background: #fff; padding: 1.5rem; width: 90%; max-width: 400px; border-radius: 12px; }
        .modal-content .close { float: right; font-size: 1.5rem; cursor: pointer; }
        
        .container { display: flex; min-height: 100vh; }

        .sidebar {
            width: 250px;
            flex-shrink: 0;
            background: #fff;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 2rem 1rem;
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            z-index: 1000;
            overflow-y: auto;
        }
        .sidebar .nav-item { display: flex; align-items: center; padding: 1rem; color: #030303; text-decoration: none; font-size: 1rem; border-radius: 8px; margin-bottom: 0.5rem; transition: all 0.2s ease; position: relative; }
        .sidebar .nav-item:hover { background: #ecf0f1; transform: translateX(5px); }
        .sidebar .nav-item.active { color: #fff; background: linear-gradient(135deg, var(--primary-hover), var(--primary-color)); box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
        .sidebar .nav-item i { margin-right: 0.85rem; font-size: 1.1rem; width: 20px; text-align: center; }

        .notification-badge {
            background-color: #e74c3c;
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: auto;
            min-width: 20px;
            height: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .main-content { flex: 1; padding: 1rem; width: 100%; }
        .dashboard-header { background: linear-gradient(135deg, var(--primary-hover), var(--primary-color)); color: #fff; padding: 1.5rem; border-radius: 0 0 20px 20px; text-align: center; margin-bottom: 1.5rem; animation: slideIn 0.5s ease-out; }
        .dashboard-header h1 { font-size: 1.75rem; font-weight: 600; }

        .stats-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: #fff; padding: 1.25rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-left: 5px solid var(--primary-color); transition: transform 0.2s ease; animation: slideIn 0.5s ease-out; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card h4 { color: #7f8c8d; font-size: 0.9rem; font-weight: 600; text-transform: uppercase; margin-bottom: 0.5rem; }
        .stat-card p { font-size: 1.5rem; font-weight: 700; }
        
        .menu-grid { display: none; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
        .menu-card { background: #fff; padding: 1rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-left: 5px solid #00b4db; display: flex; flex-direction: column; align-items: center; justify-content: center; text-decoration: none; color: var(--text-color); transition: all 0.2s ease; }
        .menu-card:hover { transform: translateY(-5px); }
        .menu-card i { font-size: 1.75rem; margin-bottom: 0.5rem; color: var(--primary-color); }
        .menu-card span.label { font-size: 0.9rem; font-weight: 500; text-align: center; }
        
        .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; background: #fff; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-around; padding: 0.5rem 0; border-radius: 20px 20px 0 0; z-index: 999; }
        .bottom-nav .nav-item { text-align: center; color: #7f8c8d; text-decoration: none; font-size: 0.75rem; flex: 1; }
        .bottom-nav .nav-item.active { color: var(--primary-color); }
        .bottom-nav .nav-item i { display: block; font-size: 1.25rem; margin-bottom: 0.25rem; }

        .data-card { background: #fff; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .data-card h3 { font-size: 1.2rem; margin-bottom: 1rem; border-bottom: 2px solid #ecf0f1; padding-bottom: 0.5rem; }
        .filter-form { display: flex; gap: 1rem; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .filter-form input[type="month"] { padding: 0.5rem; border: 1px solid #ccc; border-radius: 6px; }
        .filter-form button { padding: 0.5rem 1rem; background: var(--primary-color); color: #fff; border: none; border-radius: 6px; cursor: pointer; transition: background 0.2s; }
        .filter-form button:hover { background: var(--primary-hover); }
        .filter-form a { color: #7f8c8d; text-decoration: none; font-size: 0.9rem; }
        .table-responsive { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .data-table th, .data-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ecf0f1; }
        .data-table th { background-color: #f5f7fa; font-weight: 600; }
        .data-table tbody tr:hover { background-color: #f9f9f9; }
        .no-data-row td { text-align: center; padding: 2rem; color: #95a5a6; }
        
        #chart-container {
            position: relative;
            height: 350px;
            width: 100%;
            margin-bottom: 2rem;
        }
        
        @media (min-width: 769px) {
            .sidebar { display: block; }
            .main-content { padding: 2rem; margin-left: 250px; }
            .dashboard-header { border-radius: 12px; }
            .stats-grid { grid-template-columns: repeat(4, 1fr); }
            .bottom-nav, .menu-grid { display: none; }
        }
        @media (max-width: 768px) {
            .main-content { padding-bottom: 2rem; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .menu-grid { display: grid; }
            #chart-container { height: 250px; }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <div class="dashboard-header"><h1>ផ្ទាំងគ្រប់គ្រងស្តុក</h1></div>

            <?php if ($error_message || $success_message): ?>
            <div id="notificationModal" class="modal show">
                <div class="modal-content">
                        <span class="close" onclick="hideModal('notificationModal')">&times;</span>
                    <h3 style="color: <?php echo $error_message ? '#c0392b' : '#27ae60'; ?>;"><?php echo $error_message ? 'កំហុស' : 'ជោគជ័យ'; ?></h3>
                    <p><?php echo htmlspecialchars($error_message ?: $success_message); ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="menu-grid">
                <a href="../index.php" class="menu-card"><i class="fa-solid fa-box-archive"></i><span class="label">ទំនិញ</span></a>
                <a href="reports.php" class="menu-card"><i class="fa-solid fa-chart-simple"></i><span class="label">របាយការណ៍</span></a>
                <a href="stock_counting.php" class="menu-card"><i class="fa-solid fa-clipboard-list"></i><span class="label">រាប់ស្តុក</span></a>
                <a href="review_requests.php" class="menu-card"><i class="fa-solid fa-magnifying-glass-chart"></i><span class="label">ពិនិត្យសំណើរ</span></a>
            </div>

            <div class="stats-grid">
                <div class="stat-card"><h4>ចំនួនទំនិញ</h4><p><?php echo $total_items; ?></p></div>
                <div class="stat-card"><h4>បរិមាណសរុប</h4><p><?php echo number_format($total_quantity); ?></p></div>
                <div class="stat-card"><h4>តម្លៃសរុប</h4><p>$<?php echo number_format($total_value, 2); ?></p></div>
                <div class="stat-card"><h4>ស្តុកទាប</h4><p style="color: <?php echo $low_stock > 0 ? '#c0392b' : '#2c3e50'; ?>"><?php echo $low_stock; ?></p></div>
            </div>

            <div class="data-card animate-in">
                <h3>សរុបសំណើតាមទីតាំង</h3>
                
                <form method="GET" action="dashboard.php" class="filter-form">
                    <div>
                        <label for="filter_month" style="font-size: 0.9rem; color: #64748b; margin-right: 0.5rem;">ត្រងតាមខែ:</label>
                        <input type="month" id="filter_month" name="filter_month" value="<?php echo htmlspecialchars($filter_month ?? ''); ?>">
                    </div>
                    <button type="submit">ត្រង</button>
                    <?php if ($filter_month): ?>
                        <a href="dashboard.php">លុបការត្រង</a>
                    <?php endif; ?>
                </form>

                <?php if (!empty($location_requests)): ?>
                <div id="chart-container">
                    <canvas id="locationRequestChart"></canvas>
                </div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ទីតាំង</th>
                                <th style="text-align: right;">ចំនួនសំណើ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($location_requests)): ?>
                                <tr class="no-data-row">
                                    <td colspan="2">មិនមានទិន្នន័យសំណើ<?php echo $filter_month ? 'ក្នុងខែនេះ' : ''; ?>ទេ។</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($location_requests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['location']); ?></td>
                                        <td style="text-align: right; font-weight: bold;"><?php echo htmlspecialchars($request['request_count']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- កូដ Modal ---
        window.hideModal = function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.remove('show');
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                hideModal(event.target.id);
            }
        }
        
        // --- កូដថ្មីសម្រាប់បង្កើត Chart (បានកែសម្រួលភាសា) ---
        const chartDataExists = <?php echo !empty($chart_data) ? 'true' : 'false'; ?>;

        if (chartDataExists) {
            const ctx = document.getElementById('locationRequestChart').getContext('2d');
            
            const chartLabels = <?php echo json_encode($chart_labels); ?>;
            const chartData = <?php echo json_encode($chart_data); ?>;

            const locationRequestChart = new Chart(ctx, {
                type: 'bar', 
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'ចំនួនសំណើ', // << កែតម្រូវ
                        data: chartData,
                        backgroundColor: [ // ពណ៌សម្រាប់ Bar នីមួយៗ
                            'rgba(37, 117, 252, 0.8)',
                            'rgba(106, 17, 203, 0.8)',
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(255, 206, 86, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)',
                            'rgba(255, 159, 64, 0.8)'
                        ],
                        borderColor: [ // ពណ៌ស៊ុម
                           'rgba(37, 117, 252, 1)',
                           'rgba(106, 17, 203, 1)',
                           'rgba(255, 99, 132, 1)',
                           'rgba(54, 162, 235, 1)',
                           'rgba(255, 206, 86, 1)',
                           'rgba(75, 192, 192, 1)',
                           'rgba(153, 102, 255, 1)',
                           'rgba(255, 159, 64, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true, // ចាប់ផ្តើមអ័ក្ស Y ពីលេខ 0
                            ticks: {
                                // ធ្វើឲ្យលេខនៅលើអ័ក្ស Y ជាលេខគត់
                                stepSize: 1 
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false // លាក់ស្លាកសម្គាល់ (ព្រោះមានតែមួយชุดข้อมูล)
                        },
                        title: {
                            display: true,
                            text: 'ក្រាហ្វចំនួនសំណើតាមទីតាំង',
                            font: {
                                size: 16,
                                family: "'Noto Sans Khmer', 'Inter', sans-serif"
                            }
                        }
                    }
                }
            });
        }
    });
    </script>
</body>
</html>