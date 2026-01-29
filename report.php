<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// Database configuration
$dbHost = 'localhost';
$dbUser = 'samann1_daily_report_db';
$dbPass = 'samann1_daily_report_db';
$dbName = 'samann1_daily_report_db';

if (!isset($_GET['id']) || !isset($_GET['token'])) {
    die("Invalid report link.");
}

$reportId = $_GET['id'];
$token = $_GET['token'];

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch report details including all tasks
    $reportStmt = $pdo->prepare("
        SELECT dr.*, GROUP_CONCAT(
            CONCAT(
                COALESCE(rt.time, ''), '||',
                COALESCE(rt.task, ''), '||',
                COALESCE(rt.status, ''), '||',
                COALESCE(rt.due_date, ''), '||',
                COALESCE(rt.description, ''), '||',
                COALESCE(rt.problem, ''), '||',
                COALESCE(rt.solution, ''), '||',
                COALESCE(rt.no, '')
            ) SEPARATOR ';;;'
        ) as tasks
        FROM daily_reports dr
        LEFT JOIN report_tasks rt ON dr.id = rt.report_id
        WHERE dr.id = :id AND dr.report_link LIKE :report_link
        GROUP BY dr.id
    ");
    $reportStmt->execute([
        ':id' => $reportId,
        ':report_link' => "%$token%"
    ]);
    $report = $reportStmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        die("Report not found.");
    }

    // Parse tasks into an array
    $tasks = [];
    if ($report['tasks']) {
        $taskRows = explode(';;;', $report['tasks']);
        foreach ($taskRows as $taskRow) {
            $fields = explode('||', $taskRow);
            $tasks[] = [
                'time' => $fields[0] ?: null,
                'task' => $fields[1] ?: null,
                'status' => $fields[2] ?: null,
                'due_date' => $fields[3] ?: null,
                'description' => $fields[4] ?: null,
                'problem' => $fields[5] ?: null,
                'solution' => $fields[6] ?: null,
                'no' => $fields[7] ?: null
            ];
        }
    }

    // Check password if submitted
    if (isset($_POST['password'])) {
        if (password_verify($_POST['password'], $report['view_password'])) {
            $_SESSION['report_access_' . $reportId] = true;
        } else {
            $error = "លេខសម្ងាត់មិនត្រឹមត្រូវ!";
        }
    }

    // Check if access granted
    if (!isset($_SESSION['report_access_' . $reportId])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>បញ្ចូលលេខសម្ងាត់</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: 'Noto Sans Khmer', Arial, sans-serif;
                    background: linear-gradient(135deg, #e0e7ff, #f3f4f6);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    padding: 15px;
                }
                .popup {
                    background: #ffffff;
                    padding: 25px;
                    border-radius: 12px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                    text-align: center;
                    width: 100%;
                    max-width: 400px;
                    animation: fadeIn 0.3s ease-in-out;
                }
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(-20px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                h2 { color: #1e3a8a; font-size: 1.5rem; margin-bottom: 20px; }
                .error { color: #dc2626; font-size: 0.9rem; margin-bottom: 15px; }
                input[type="password"] {
                    width: 100%; padding: 12px; font-size: 1rem;
                    border: 1px solid #d1d5db; border-radius: 8px;
                    margin-bottom: 15px; outline: none;
                    transition: border-color 0.3s;
                }
                input[type="password"]:focus {
                    border-color: #3b82f6;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
                }
                button {
                    width: 100%; padding: 12px; background: #3b82f6;
                    color: #ffffff; border: none; border-radius: 8px;
                    font-size: 1rem; cursor: pointer;
                    transition: background 0.3s;
                }
                button:hover { background: #2563eb; }
                @media (max-width: 480px) {
                    .popup { padding: 20px; max-width: 90%; }
                    h2 { font-size: 1.3rem; }
                    input[type="password"], button { font-size: 0.9rem; padding: 10px; }
                }
            </style>
        </head>
        <body>
            <div class="popup">
                <h2>បញ្ចូលលេខសម្ងាត់</h2>
                <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
                <form method="POST">
                    <input type="password" name="password" required placeholder="លេខសម្ងាត់">
                    <button type="submit">បើករបាយការណ៍</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    // Display report
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>របាយការណ៍ប្រចាំថ្ងៃ</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Noto Sans Khmer', Arial, sans-serif;
                background: linear-gradient(135deg, #e0e7ff, #f3f4f6);
                padding: 20px;
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .report-container {
                background: #ffffff;
                padding: 25px;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
                width: 100%;
                max-width: 700px;
                animation: fadeIn 0.3s ease-in-out;
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            h1 {
                color: #1e3a8a;
                font-size: 1.8rem;
                margin-bottom: 20px;
                text-align: center;
                border-bottom: 2px solid #e5e7eb;
                padding-bottom: 10px;
            }
            .report-item {
                margin-bottom: 15px;
                font-size: 1rem;
                line-height: 1.6;
            }
            .report-item strong {
                color: #3b82f6;
                display: inline-block;
                width: 120px;
            }
            .report-item span { color: #4b5563; }
            .task-content {
                background: #f9fafb;
                padding: 15px;
                border-radius: 8px;
                border-left: 4px solid #3b82f6;
                margin-top: 10px;
                white-space: pre-wrap;
            }
            .task-item { margin-bottom: 10px; }
            .task-item:last-child { margin-bottom: 0; }
            @media (max-width: 480px) {
                .report-container { padding: 15px; max-width: 90%; }
                h1 { font-size: 1.5rem; }
                .report-item { font-size: 0.9rem; }
                .report-item strong { width: 100px; }
                .task-content { padding: 10px; }
            }
        </style>
    </head>
    <body>
        <div class="report-container">
            <h1>របាយការណ៍ប្រចាំថ្ងៃ</h1>
            <div class="report-item">
                <strong>ឈ្មោះ:</strong>
                <span><?php echo htmlspecialchars($report['name']); ?></span>
            </div>
            <div class="report-item">
                <strong>តួនាទី:</strong>
                <span><?php echo htmlspecialchars($report['position']); ?></span>
            </div>
            <?php if ($report['department']) { ?>
            <div class="report-item">
                <strong>ផ្នែក:</strong>
                <span><?php echo htmlspecialchars($report['department']); ?></span>
            </div>
            <?php } ?>
            <div class="report-item">
                <strong>ថ្ងៃខែឆ្នាំ:</strong>
                <span><?php echo htmlspecialchars($report['report_date']); ?></span>
            </div>
            <div class="report-item">
                <strong>របាយការណ៍:</strong>
                <div class="task-content">
                    <?php 
                    foreach ($tasks as $index => $task) {
                        echo "<div class='task-item'>";
                        echo "<strong>ការពិពណ៌នាការងារ " . ($index + 1) . ":</strong><br>";
                        if ($task['time']) {
                            $time = DateTime::createFromFormat('H:i:s', $task['time']);
                            if ($time) {
                                echo "⏰ ម៉ោង: " . $time->format('g:i A') . "<br>";
                            } else {
                                echo "⏰ ម៉ោង: " . htmlspecialchars($task['time']) . "<br>";
                            }
                        }
                        if ($task['task']) echo "" . htmlspecialchars($task['task']) . "<br>";
                        if ($task['status']) echo "ស្ថានភាព: " . htmlspecialchars($task['status']) . "<br>";
                        if ($task['due_date']) echo "កាលបរិច្ឆេទកំណត់: " . htmlspecialchars($task['due_date']) . "<br>";
                        if ($task['description']) echo "ការពិពណ៌នា: " . htmlspecialchars($task['description']) . "<br>";
                        if ($task['problem']) echo "បញ្ហា: " . htmlspecialchars($task['problem']) . "<br>";
                        if ($task['solution']) echo "ដំណោះស្រាយ: " . htmlspecialchars($task['solution']) . "<br>";
                        if ($task['no']) echo "លេខ: " . htmlspecialchars($task['no']) . "<br>";
                        echo "</div>";
                    }
                    ?>
                </div>
            </div>
            <?php if ($report['next_plan_date'] || $report['next_plan_details']) { ?>
            <div class="report-item">
                <strong>ផែនការថ្ងៃបន្ទាប់:</strong>
                <div class="task-content">
                    <?php
                    if ($report['next_plan_date']) echo "កាលបរិច្ឆេទ:" . htmlspecialchars($report['next_plan_date']) . "<br>";
                    if ($report['next_plan_details']) echo "ព័ត៌មានលម្អិត: <br>" . nl2br(htmlspecialchars($report['next_plan_details']));
                    ?>
                </div>
            </div>
            <?php } ?>
        </div>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>