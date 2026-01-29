<?php
// admin_panel.php
header('Content-Type: text/html; charset=utf-8');

// Database configuration
$dbHost = 'localhost';
$dbUser = 'samann1_daily_report_db';
$dbPass = 'samann1_daily_report_db';
$dbName = 'samann1_daily_report_db';

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>list_daily_report</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="style1.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css"/>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
  <script src="https://unpkg.com/scrollreveal"></script>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&icon_names=fact_check" />
  <link rel="stylesheet" href="/node_modules/bootstrap-icons/icons/">
  <link
  rel="stylesheet"
  href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
  
   .folder-wrapper {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background-color: #fff;
        }

        .main-folder {
            cursor: pointer;
            padding: 30px 15px;
            background-color: #dbdbdb;
             color: goldenrod;
            border-radius: 4px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: 600;
            font-size: 16px;
        }
                .main-folder span{
            font-family: Kh Muol;
            font-weight: bold;
            font-size: 25px;
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
    @media (max-width: 768px) {
      .menu-card{
        position: relative;
        top: -5rem;
      }
      .main-menu{
        position: relative;
        top: -4rem;
      }
      .navbar-brand img{
        text-align: center;
        position: relative;
        left: 40%;
        align-items: center;
      }

      .mes{
        width: 5%;
        position: relative;
        left: 0;
        top: 5rem;
      }
    }
    /* computer */
    .main-menu{
      top: 3rem;
      position: relative;
    }
    .navbar-custom {
      background-color: #050049;
      position: fixed;
    }

    .menu-icon {
      font-size: 1.5rem;
      color: white;
    }

    .bottom-nav {
      position: fixed;
      background-color: #ffffff;
      border: 1px solid #ddd;
      height: 80px;
      bottom: 0;
      width: 100%;
    }
    .navbar-brand img{
      text-decoration: none;
      width: 200px;
      text-align: center;
      align-items: center;
      position: relative;
    }
    .navbar-toggler{
      background: none;
    }

    .bottom-menu a {
      color: #007bff;
      text-decoration: none;
      font-size: 1.5rem;
    }
    .btn{
      color: white;
    }
    .main-cart{
      position: relative;
      align-items: center;
      text-align: center;
      top: 5rem;
    }
    .card-footer{
      position: relative;
      justify-content: center;
      text-align: center;
      align-items: center;
    }
    .sort-list{
      position: relative;
      top: -1rem;
    }
    .form-label{
      position: relative;
      justify-content: start;
    }
    .cart-box {
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 16px;
      text-align: center;
      background: #f9f9f9;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease-in-out;
    }
    .cart-box:hover {
      transform: translateY(-5px);
    }
    .cart-image {
      width: 100%;
      height: 200px;
      object-fit: cover;
      border-radius: 8px;
    }
    .cart-title{
      font-size: 1.1rem;
      margin: 12px 0;
      color: blue;
      font-family: Kh Muol;
    }
    .text-title{
      font-family: Kh Muol;
      color: blue;
      font-size: 20px;
      position: relative;
    }
    .text-h5 a{
      text-decoration: none;
    }
    .main-content{
      position: relative;
      top: 3rem;
    }
    .check{
      font-size: 100px;
    }
    .page{
      display: none;
    }
    .bg-color-iframe{
      background-color: rgb(240, 240, 240);
      position: relative;
      top: 34.6rem;
      height: 7vh;
      padding: 15px;
    }
    .page{
      position: relative;
      top: -2rem;
    }
    .page h1{
      font-size: 20px;
      position: relative;
      top: 2rem;
      text-decoration: underline;
      font-family: Kh Muol;
      color: blue;
    }
  </style>
</head>
<body>
  <!-- Navbar -->
  <div class="main-header">
  <nav class="navbar navbar-expand-lg navbar-custom shadow-lg mb-5">
    <div class="container-fluid text-decoration-none">
      <a href="index1.html"><span class="navbar-brand text-white text-decoration-none"><img src="https://i.ibb.co/HTksMQd/Logo-Van-Van-2.png" alt=""></span></a>
    </button>
    <a href="index1.html"><button id="logout"><i class="fa-solid fa-right-from-bracket"></i></button></a>
    </div>
  </nav>
  <div class="container py-4 main-cart">
    <!-- <div class="mb-3 mt-6 sort-list">
      <label for="sortBy" class="form-label">Sort By</label>
      <select class="form-select" id="sortBy" onchange="sortMeetings()">
        <option value="date-asc">A-Z</option>
        <option value="date-desc">Z-A</option>
      </select>
    </div> -->
  </div>
  <div class="container py-5 mt-6 main-content">
    <h2 class="text-center mb-4 text-title" id="text">បញ្ជីតារាងរបាយការណ៍ប្រចាំថ្ងៃ</h2>
    
      <!-- Box List -->
      <div class="row g-4 mt-3  justify-content-center">
          <!-- Cart 1 -->
          <div class="col-12 col-md-6 col-lg-4 text-h5 ani-1" id="box1">
              <a href="index4.html" data-page="1"><div class="cart-box">
                  <img src="	https://cdn-icons-png.flaticon.com/512/11924/11924569.png" alt="" width="100px">
                  <h5 class="cart-title mt-6">ធ្វើរបាយការណ៍ប្រចាំថ្ងៃ</h5>
                </div></a>
            </div>
            <div class="col-12 col-md-6 col-lg-4 text-h5 ani-2">
            <div id="main" class="main-page">
          <a href="#" data-page="2"><div class="cart-box">
            <img src="	https://cdn-icons-png.flaticon.com/512/4414/4414640.png" alt="" width="100px">
            <h5 class="cart-title mt-6">បញ្ជីតារាងរបាយការណ៍ប្រចាំថ្ងៃបុគ្គលិក</h5>
          </div></a>
        </div>
      </div>
    </div>
    </div>
    </div>
    <div id="page-2" class="page">
    <div class="container">
        <div class="folder-wrapper">
      <!-- Main Folder -->
            <div class="main-folder" onclick="toggleFolder('main-reports')">
                <span>របាយការណ៍ប្រចាំថ្ងៃ</span>
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
  
  
        function showPage(pageId) {
        document.getElementById('main').style.display = 'none'; // Hide main page
        document.getElementById('text').style.display = 'none'; // Hide text page
        document.getElementById('box1').style.display = 'none'; // Hide box1 page
        document.getElementById('page-' + pageId).style.display = 'block'; // Show the selected page
    }

    // Function to go back to the main page
    function goBack() {
        const pages = document.querySelectorAll('.page');
        pages.forEach(page => page.style.display = 'none'); // Hide all pages
        document.getElementById('main').style.display = 'block'; // Show the main page
    }

    // Add event listeners to page links
    document.querySelectorAll('.main-page a').forEach(link => {
        link.addEventListener('click', function (event) {
            event.preventDefault(); // Prevent default link behavior
            const pageId = this.getAttribute('data-page'); // Get the page ID
            showPage(pageId); // Show the selected page
        });
    });


    const scrollRevealOption = {
  origin: "bottom",
  distance: "10px",
  duration: 1000,
};

ScrollReveal().reveal(".ani-1", {
  ...scrollRevealOption,
  origin: "bottom",
  distance:"200px",
});
ScrollReveal().reveal(".ani-2", {
  ...scrollRevealOption,
  delay: 100,
  distance:"200px",
});
ScrollReveal().reveal(".ani-3", {
  ...scrollRevealOption,
  delay: 200,
  distance:"200px",
});
ScrollReveal().reveal(".ani-4", {
  ...scrollRevealOption,
  delay: 300,
  distance:"200px",
});
ScrollReveal().reveal(".ani-5", {
  ...scrollRevealOption,
  delay: 400,
  distance:"200px",
});
ScrollReveal().reveal(".ani-6", {
  ...scrollRevealOption,
  delay: 500,
  distance:"200px",
});
const textareas = document.querySelectorAll('textarea');

textareas.forEach((textarea) => {
  textarea.addEventListener('input', () => {
    textarea.style.height = 'auto'; // Reset height
    textarea.style.height = textarea.scrollHeight + 'px'; // Adjust height
  });
});
var telegram_bot_id = "7680707479:AAG38M8FpFbuVfqWCLwxUKo7l7iKJCXOEz8";
//chat id
var chat_id = -1002496391098;
var numberofdays, date, namerequest,Positions,Branch,reason;
var ready = function () {
namerequest = document.getElementById("namerequest").value;
date = document.getElementById("date").value;
numberofdays = document.getElementById("numberofdays").value;
typeofrequest = document.getElementById("typeofrequest").value;
Positions = document.getElementById("Positions").value;
Branch = document.getElementById("Branch").value;
reason = document.getElementById("reason").value;
  message = "\n- អ្នកស្នើរសុំ៖ " + namerequest + "\n- មុខដំណែង៖  " + Positions + "\n- សាខា៖  " + Branch + "\n- ថ្ងៃខែឆ្នាំ៖  " + date + "\n- ចំនួនថ្ងៃ៖  " + numberofdays + "\n- ប្រភេទនៃការស្នើរសុំ​៖  " + typeofrequest  + "\n- មូលហេតុ៖  " + reason;
};
var sender = function () {
  ready();
  var settings = {
      "async": true,
      "crossDomain": true,
      "url": "https://api.telegram.org/bot" + telegram_bot_id + "/sendMessage",
      "method": "POST",
      "headers": {
          "Content-Type": "application/json",
          "cache-control": "no-cache"
      },
      "data": JSON.stringify({
          "chat_id": chat_id,
          "text": message
      })
  };
  $.ajax(settings).done(function (response) {
      console.log(response);
  });
  document.getElementById("name").value = "";
  document.getElementById("date").value = "";
  document.getElementById("message").value = "";
  return false;
};

document.getElementById("form").addEventListener("submit", function (e) {
      e.preventDefault(); // Prevent the default form submission
      document.getElementById("message").textContent = "កំពុងបញ្ជូន";
      document.getElementById("message").style.display = "block";
      document.getElementById("submit-button").disabled = true;

      // Collect the form data
      var formData = new FormData(this);
      var keyValuePairs = [];
      for (var pair of formData.entries()) {
        keyValuePairs.push(pair[0] + "=" + pair[1]);
      }

      var formDataString = keyValuePairs.join("&");

      // Send a POST request to your Google Apps Script
      fetch(
        "https://script.google.com/macros/s/AKfycbzGaGmkgIpxi2c_2D3NMYgv5CNx0HdGgE3JuZhcvrwYADYAB8iENs_yCaIiVcbvHQ5-/exec",
        
        {
          redirect: "follow",
          method: "POST",
          body: formDataString,
          headers: {
            "Content-Type": "text/plain;charset=utf-8",
          },
        }
      )
        .then(function (response) {
          // Check if the request was successful
          if (response) {
            return response; // Assuming your script returns JSON response
          } else {
            throw new Error("Failed to submit the form.");
          }
        })
        .then(function (data) {
          // Display a success message
          document.getElementById("message").textContent =
            "បានបញ្ជូនរួចរាល់";
          document.getElementById("message").style.display = "block";
          document.getElementById("message").style.backgroundColor = "green";
          document.getElementById("message").style.color = "beige";
          document.getElementById("submit-button").disabled = false;
          document.getElementById("form").reset();

          setTimeout(function () {
            document.getElementById("message").textContent = "";
            document.getElementById("message").style.display = "none";
          }, 2600);
        })
        .catch(function (error) {
          // Handle errors, you can display an error message here
          console.error(error);
          document.getElementById("message").textContent =
            "An error occurred while submitting the form.";
          document.getElementById("message").style.display = "block";
        });
    });


  </script>
</body>
</html>