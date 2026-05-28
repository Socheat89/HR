<?php
header('Content-Type: text/html; charset=utf-8');

// Include database configuration
require_once '../system/config.php';

$dbHost = DB_HOST;
$dbUser = DB_USER;
$dbPass = DB_PASS;
$dbName = DB_NAME;

// Khmer months array
$khmerMonths = [
    1 => 'មករា',
    2 => 'កុម្ភៈ',
    3 => 'មីនា',
    4 => 'មេសា',
    5 => 'ឧសភា',
    6 => 'មិថុនា',
    7 => 'កក្កដា',
    8 => 'សីហា',
    9 => 'កញ្ញា',
    10 => 'តុលា',
    11 => 'វិច្ឆិកា',
    12 => 'ធ្នូ'
];

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8mb4'");

    // Fetch all report data
    $stmt = $pdo->prepare("
        SELECT dr.*, GROUP_CONCAT(rt.task) as tasks 
        FROM daily_reports dr
        LEFT JOIN report_tasks rt ON dr.id = rt.report_id
        GROUP BY dr.id
        ORDER BY dr.report_date DESC, dr.name
    ");
    $stmt->execute();
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group reports by year, month, and name
    $groupedReportsByYearMonth = [];
    foreach ($reports as $report) {
        $year = date('Y', strtotime($report['report_date']));
        $month = (int)date('m', strtotime($report['report_date']));
        $name = htmlspecialchars($report['name']);
        
        if (!isset($groupedReportsByYearMonth[$year])) {
            $groupedReportsByYearMonth[$year] = [];
        }
        if (!isset($groupedReportsByYearMonth[$year][$month])) {
            $groupedReportsByYearMonth[$year][$month] = [];
        }
        if (!isset($groupedReportsByYearMonth[$year][$month][$name])) {
            $groupedReportsByYearMonth[$year][$month][$name] = [];
        }
        $groupedReportsByYearMonth[$year][$month][$name][] = $report;
    }
} catch (Exception $e) {
    die("កំហុសក្នុងការតភ្ជាប់: " . $e->getMessage());
}

// Format time to 12-hour with AM/PM
function formatTimeTo12Hour($time) {
    if (empty($time)) return '-';
    $dateTime = DateTime::createFromFormat('H:i:s', $time);
    if ($dateTime === false) {
        $dateTime = DateTime::createFromFormat('H:i', $time);
    }
    return $dateTime ? $dateTime->format('h:i A') : '-';
}

// Parse and format next plan details
function formatNextPlanDetails($nextPlanDetails) {
    if (empty($nextPlanDetails)) return ['-'];
    $lines = array_map('trim', explode('-', $nextPlanDetails));
    $result = [];
    foreach ($lines as $line) {
        if (!empty($line)) {
            $result[] = htmlspecialchars($line);
        }
    }
    return $result ?: ['-'];
}

// Export to CSV function
function exportToCSV($reports) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="daily_reports_' . date('Ymd_His') . '.csv"');
    
    // Add BOM for proper UTF-8 support in Excel
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    fputcsv($output, [
        'កាលបរិច្ឆេទ',
        'ឈ្មោះ',
        'តួនាទី',
        'ផ្នែក',
        'ផែនការបន្ទាប់',
        'កាលបរិច្ឆេទផែនការបន្ទាប់',
        'កិច្ចការ',
        'ម៉ោង',
        'ស្ថានភាព',
        'កាលបរិច្ឆេទកំណត់',
        'ពិពណ៌នា',
        'បញ្ហា',
        'ដំណោះស្រាយ',
        'លេខ'
    ]);
    
    // CSV Data
    foreach ($reports as $report) {
        $taskStmt = $GLOBALS['pdo']->prepare("SELECT * FROM report_tasks WHERE report_id = ?");
        $taskStmt->execute([$report['id']]);
        $tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($tasks)) {
            fputcsv($output, [
                date('d/m/Y', strtotime($report['report_date'])),
                htmlspecialchars($report['name']),
                htmlspecialchars($report['position']),
                htmlspecialchars($report['department'] ?? '-'),
                implode('; ', formatNextPlanDetails($report['next_plan_details'])),
                $report['next_plan_date'] ? date('d/m/Y', strtotime($report['next_plan_date'])) : '-',
                '-', '-', '-', '-', '-', '-', '-', '-'
            ]);
        } else {
            foreach ($tasks as $task) {
                fputcsv($output, [
                    date('d/m/Y', strtotime($report['report_date'])),
                    htmlspecialchars($report['name']),
                    htmlspecialchars($report['position']),
                    htmlspecialchars($report['department'] ?? '-'),
                    implode('; ', formatNextPlanDetails($report['next_plan_details'])),
                    $report['next_plan_date'] ? date('d/m/Y', strtotime($report['next_plan_date'])) : '-',
                    htmlspecialchars($task['task'] ?? '-'),
                    formatTimeTo12Hour($task['time'] ?? '-'),
                    htmlspecialchars($task['status'] ?? '-'),
                    $task['due_date'] ? date('d/m/Y', strtotime($task['due_date'])) : '-',
                    htmlspecialchars($task['description'] ?? '-'),
                    htmlspecialchars($task['problem'] ?? '-'),
                    htmlspecialchars($task['solution'] ?? '-'),
                    htmlspecialchars($task['no'] ?? '-')
                ]);
            }
        }
    }
    
    fclose($output);
    exit();
}

// Check for export request
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    exportToCSV($reports);
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - របាយការណ៍ប្រចាំថ្ងៃ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Khmer', 'Noto Sans Khmer', sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            overflow: hidden;
        }

        .container {
            height: 100vh;
            padding: 0;
            display: flex;
            flex-direction: column;
            background-color: #fff;
        }

        .folder-wrapper {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background-color: #fff;
        }

        .main-folder {
            cursor: pointer;
            padding: 10px 15px;
            background-color: #4682b4;
            color: #fff;
            border-radius: 4px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 600;
            font-size: 16px;
        }

        .main-folder::before {
            content: '📁';
            margin-right: 5px;
            font-size: 18px;
        }

        .main-folder.open::before {
            content: '📂';
        }

        .year-folder {
            cursor: pointer;
            padding: 8px 20px;
            background-color: #f0f4f8;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 15px;
            font-weight: 600;
            color: #2c3e50;
        }

        .year-folder:hover {
            background-color: #e6ecf2;
        }

        .year-folder::before {
            content: '📅';
            margin-right: 5px;
            font-size: 16px;
            color: #e67e22;
        }

        .month-folder {
            cursor: pointer;
            padding: 8px 25px;
            background-color: #f9f9f9;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            color: #333;
        }

        .month-folder:hover {
            background-color: #f0f0f0;
        }

        .month-folder::before {
            content: '📆';
            margin-right: 5px;
            font-size: 16px;
            color: #3498db;
        }

        .employee-folder {
            cursor: pointer;
            padding: 8px 30px;
            background-color: #fff;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            color: #333;
        }

        .employee-folder:hover {
            background-color: #f5f5f5;
        }

        .employee-folder::before {
            content: '📁';
            margin-right: 5px;
            font-size: 16px;
            color: #d4a017;
        }

        .date-folder {
            cursor: pointer;
            padding: 5px 40px;
            background-color: #fff;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            color: #333;
        }

        .date-folder:hover {
            background-color: #f5f5f5;
        }

        .date-folder::before {
            content: '📁';
            margin-right: 5px;
            font-size: 16px;
            color: #d4a017;
        }

        .folder-content {
            display: none;
            padding-left: 10px;
        }

        .folder-content.open {
            display: block;
        }

        .details-content {
            display: none;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            margin: 15px 40px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
        }

        .details-content.open {
            display: block;
        }

        .details-content h6 {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 15px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 8px;
        }

        .details-content p {
            display: flex;
            align-items: flex-start;
            margin: 10px 0;
            font-size: 15px;
            color: #34495e;
            line-height: 1.6;
        }

        .details-content p strong {
            flex: 0 0 130px;
            font-weight: 600;
            color: #1a252f;
        }

        .details-content .tasks-list, .details-content .next-plan-list {
            flex: 1;
            padding-left: 0;
            list-style: none;
        }

        .details-content .tasks-list li, .details-content .next-plan-list li {
            position: relative;
            margin: 8px 0;
            padding-left: 20px;
            color: #34495e;
            line-height: 1.6;
        }

        .details-content .tasks-list li::before, .details-content .next-plan-list li::before {
            content: '•';
            position: absolute;
            left: 0;
            color: #3498db;
            font-size: 18px;
        }

        .details-content ul {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }

        .details-content ul li {
            background-color: #f9fbfd;
            padding: 15px;
            margin-bottom: 12px;
            border-radius: 6px;
            border-left: 4px solid #3498db;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .details-content ul li strong {
            display: inline-block;
            width: 110px;
            font-weight: 600;
            color: #1a252f;
        }

        .search-container {
            padding: 5px;
        }

        .form-control {
            font-family: 'Noto Sans Khmer', sans-serif;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        }

        /* Media Queries for Mobile */
        @media screen and (max-width: 768px) {
            .folder-wrapper {
                padding: 10px;
            }

            .main-folder, .year-folder, .month-folder, .employee-folder, .date-folder {
                font-size: 13px;
                padding: 6px 10px;
            }

            .details-content {
                margin: 10px 15px;
                padding: 15px;
            }

            .details-content p {
                font-size: 13px;
                flex-direction: column;
            }

            .details-content p strong {
                flex: none;
                width: 100%;
                margin-bottom: 5px;
            }
        }

        @media screen and (max-width: 480px) {
            .main-folder, .year-folder, .month-folder, .employee-folder, .date-folder {
                font-size: 12px;
                padding: 5px 8px;
            }

            .details-content {
                margin: 5px 10px;
                padding: 10px;
            }

            .details-content p {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="folder-wrapper">
            <!-- Global Search and Export -->
            <div class="search-container mb-3">
                <div class="d-flex gap-2">
                    <input type="text" 
                           class="form-control" 
                           id="global-search" 
                           placeholder="ស្វែងរករបាយការណ៍ទាំងអស់..." 
                           onkeyup="searchGlobal()">
                    <button onclick="exportCSV()" 
                            class="btn btn-success d-flex align-items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-download" viewBox="0 0 16 16">
                            <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                            <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
                        </svg>
                        នាំចេញជា CSV
                    </button>
                </div>
            </div>

            <!-- Main Folder -->
            <div class="main-folder" onclick="toggleFolder('main-reports')">
                របាយការណ៍ប្រចាំថ្ងៃ
            </div>
            <div class="folder-content" id="main-reports">
                <!-- Year Search -->
                <div class="search-container ms-3 mb-2">
                    <input type="text" 
                           class="form-control" 
                           id="year-search" 
                           placeholder="ស្វែងរកឆ្នាំ..." 
                           onkeyup="searchYears()">
                </div>

                <?php foreach ($groupedReportsByYearMonth as $year => $yearReports): ?>
                    <div class="year-folder" onclick="toggleFolder('year-<?= $year ?>')">
                        ឆ្នាំ <?= $year ?>
                    </div>
                    <div class="folder-content" id="year-<?= $year ?>">
                        <!-- Month Search -->
                        <div class="search-container ms-3 mb-2">
                            <input type="text" 
                                   class="form-control" 
                                   id="month-search-<?= $year ?>" 
                                   placeholder="ស្វែងរកខែ..." 
                                   onkeyup="searchMonths('<?= $year ?>')">
                        </div>

                        <?php foreach ($yearReports as $month => $monthReports): ?>
                            <div class="month-folder" onclick="toggleFolder('month-<?= $year ?>-<?= $month ?>')">
                                ខែ<?= $khmerMonths[$month] ?>
                            </div>
                            <div class="folder-content" id="month-<?= $year ?>-<?= $month ?>">
                                <!-- Employee Search -->
                                <div class="search-container ms-3 mb-2">
                                    <input type="text" 
                                           class="form-control" 
                                           id="employee-search-<?= $year ?>-<?= $month ?>" 
                                           placeholder="ស្វែងរកឈ្មោះបុគ្គលិក..." 
                                           onkeyup="searchEmployees('<?= $year ?>', '<?= $month ?>')">
                                </div>

                                <?php foreach ($monthReports as $name => $employeeReports): ?>
                                    <div class="employee-folder" onclick="toggleFolder('employee-<?= md5($year . $month . $name) ?>')">
                                        <?= $name ?>
                                    </div>
                                    <div class="folder-content" id="employee-<?= md5($year . $month . $name) ?>">
                                        <!-- Date Search -->
                                        <div class="search-container ms-3 mb-2">
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="date-search-<?= md5($year . $month . $name) ?>" 
                                                   placeholder="ស្វែងរកកាលបរិច្ឆេទ..." 
                                                   onkeyup="searchDates('<?= md5($year . $month . $name) ?>')">
                                        </div>

                                        <?php foreach ($employeeReports as $report): ?>
                                            <div class="date-folder" onclick="toggleDetails('details-<?= $report['id'] ?>')">
                                                <?= date('d/m/Y', strtotime($report['report_date'])) ?>
                                            </div>
                                            <div class="details-content" id="details-<?= $report['id'] ?>">
                                                <p><strong>កាលបរិច្ឆេទ:</strong> <?= date('d/m/Y', strtotime($report['report_date'])) ?></p>
                                                <p><strong>ឈ្មោះ:</strong> <?= htmlspecialchars($report['name']) ?></p>
                                                <p><strong>តួនាទី:</strong> <?= htmlspecialchars($report['position']) ?></p>
                                                <p><strong>ផ្នែក:</strong> <?= htmlspecialchars($report['department'] ?? '-') ?></p>
                                                <p><strong>ផែនការបន្ទាប់:</strong>
                                                    <?php if ($report['next_plan_date']): ?>
                                                        <ul class="next-plan-list">
                                                            <?php foreach (formatNextPlanDetails($report['next_plan_details']) as $detail): ?>
                                                                <li><?= $detail ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <span class="next-plan-list">-</span>
                                                    <?php endif; ?>
                                                </p>
                                                <h6>កិច្ចការ:</h6>
                                                <?php
                                                $taskStmt = $pdo->prepare("SELECT * FROM report_tasks WHERE report_id = ?");
                                                $taskStmt->execute([$report['id']]);
                                                $tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);
                                                ?>
                                                <?php if ($tasks): ?>
                                                    <ul>
                                                        <?php foreach ($tasks as $task): ?>
                                                            <li>
                                                                <strong>ម៉ោង:</strong> <?= formatTimeTo12Hour($task['time'] ?? '-') ?><br>
                                                                <strong>កិច្ចការ:</strong>
                                                                <ul>
                                                                    <?php foreach (explode("\n", htmlspecialchars($task['task'] ?? '-')) as $detail): ?>
                                                                        <li><?= $detail ?></li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                                <strong>ស្ថានភាព:</strong> <?= htmlspecialchars($task['status'] ?? '-') ?><br>
                                                                <strong>កាលបរិច្ឆេទកំណត់:</strong> 
                                                                <?= $task['due_date'] ? date('d/m/Y', strtotime($task['due_date'])) : '-' ?><br>
                                                                <strong>ពិពណ៌នា:</strong> <?= htmlspecialchars($task['description'] ?? '-') ?><br>
                                                                <strong>បញ្ហា:</strong> <?= htmlspecialchars($task['problem'] ?? '-') ?><br>
                                                                <strong>ដំណោះស្រាយ:</strong> <?= htmlspecialchars($task['solution'] ?? '-') ?><br>
                                                                <strong>លេខ:</strong> <?= htmlspecialchars($task['no'] ?? '-') ?>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <p>មិនមានកិច្ចការ</p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        // Global Search
        function searchGlobal() {
            let input = document.getElementById('global-search').value.toLowerCase();
            let allFolders = document.querySelectorAll('.year-folder, .month-folder, .employee-folder, .date-folder');
            
            allFolders.forEach(folder => {
                let text = folder.textContent.toLowerCase();
                folder.style.display = text.includes(input) ? '' : 'none';
                
                if (text.includes(input)) {
                    let parent = folder.parentElement;
                    while (parent && parent.classList.contains('folder-content')) {
                        parent.previousElementSibling.style.display = '';
                        parent = parent.parentElement;
                    }
                }
            });
        }

        // Search Years
        function searchYears() {
            let input = document.getElementById('year-search').value.toLowerCase();
            let yearFolders = document.getElementsByClassName('year-folder');
            
            for (let folder of yearFolders) {
                let text = folder.textContent.toLowerCase();
                folder.style.display = text.includes(input) ? '' : 'none';
            }
        }

        // Search Months
        function searchMonths(year) {
            let input = document.getElementById(`month-search-${year}`).value.toLowerCase();
            let monthFolders = document.querySelectorAll(`#year-${year} .month-folder`);
            
            for (let folder of monthFolders) {
                let text = folder.textContent.toLowerCase();
                folder.style.display = text.includes(input) ? '' : 'none';
            }
        }

        // Search Employees
        function searchEmployees(year, month) {
            let input = document.getElementById(`employee-search-${year}-${month}`).value.toLowerCase();
            let employeeFolders = document.querySelectorAll(`#month-${year}-${month} .employee-folder`);
            
            for (let folder of employeeFolders) {
                let text = folder.textContent.toLowerCase();
                folder.style.display = text.includes(input) ? '' : 'none';
            }
        }

        // Search Dates
        function searchDates(employeeHash) {
            let input = document.getElementById(`date-search-${employeeHash}`).value.toLowerCase();
            let dateFolders = document.querySelectorAll(`#employee-${employeeHash} .date-folder`);
            
            for (let folder of dateFolders) {
                let text = folder.textContent.toLowerCase();
                folder.style.display = text.includes(input) ? '' : 'none';
            }
        }

        // Toggle Folder
        function toggleFolder(folderId) {
            const folderContent = document.getElementById(folderId);
            const folder = folderContent.previousElementSibling;
            
            folderContent.classList.toggle('open');
            folder.classList.toggle('open');
        }

        // Toggle Details
        function toggleDetails(detailsId) {
            const detailsContent = document.getElementById(detailsId);
            detailsContent.classList.toggle('open');
        }

        // Export to CSV
        function exportCSV() {
            if (confirm('តើអ្នកចង់នាំចេញរបាយការណ៍ទាំងអស់ទៅជា CSV មែនទេ?')) {
                window.location.href = window.location.pathname + '?export=csv';
            }
        }
    </script>
</body>
</html>