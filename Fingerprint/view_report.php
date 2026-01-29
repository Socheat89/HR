<?php
// Database configuration
$dbHost = 'localhost';
$dbUser = 'samann1_daily_report_db';
$dbPass = 'daily_report_db';
$dbName = 'samann1_daily_report_db';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get report ID from URL
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        throw new Exception("មិនបានបញ្ជាក់ ID របាយការណ៍");
    }

    // Fetch report details
    $stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ?");
    $stmt->execute([$id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        throw new Exception("រកមិនឃើញរបាយការណ៍");
    }

    // Fetch report tasks
    $taskStmt = $pdo->prepare("SELECT * FROM report_tasks WHERE report_id = ? ORDER BY task_number");
    $taskStmt->execute([$id]);
    $tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "កំហុស: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>មើលរបាយការណ៍ - ID: <?php echo htmlspecialchars($id); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            background-color: #333;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .report-details, .task-list {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .detail-item {
            margin: 10px 0;
        }
        .task-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .error {
            color: red;
            padding: 10px;
            background-color: #ffebee;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: #2196F3;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>ព័ត៌មានលម្អិតរបាយការណ៍</h2>
        </div>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
            <a href="admin_panel.php" class="back-link">ត្រឡប់ទៅកាន់ Admin Panel</a>
        <?php else: ?>

            <div class="report-details">
                <h3>ព័ត៌មានទូទៅ</h3>
                <div class="detail-item"><strong>ID:</strong> <?php echo htmlspecialchars($report['id']); ?></div>
                <div class="detail-item"><strong>កាលបរិច្ឆេទ:</strong> <?php echo htmlspecialchars($report['report_date']); ?></div>
                <div class="detail-item"><strong>ឈ្មោះ:</strong> <?php echo htmlspecialchars($report['name']); ?></div>
                <div class="detail-item"><strong>តួនាទី:</strong> <?php echo htmlspecialchars($report['position']); ?></div>
                <div class="detail-item"><strong>ផ្នែក:</strong> <?php echo htmlspecialchars($report['department'] ?? 'មិនមាន'); ?></div>
                <div class="detail-item"><strong>បង្កើតនៅ:</strong> <?php echo htmlspecialchars($report['created_at']); ?></div>
            </div>

            <div class="task-list">
                <h3>កិច្ចការប្រចាំថ្ងៃ</h3>
                <?php if (empty($tasks)): ?>
                    <p>មិនមានកិច្ចការត្រូវបានកត់ត្រា</p>
                <?php else: ?>
                    <?php foreach ($tasks as $index => $task): ?>
                        <div class="task-item">
                            <h4>កិច្ចការ <?php echo ($index + 1); ?></h4>
                            <?php if ($task['task_number']): ?>
                                <div><strong>លេខកិច្ចការ:</strong> <?php echo htmlspecialchars($task['task_number']); ?></div>
                            <?php endif; ?>
                            <?php if ($task['time']): ?>
                                <div><strong>ម៉ោង:</strong> <?php echo htmlspecialchars(date('h:i A', strtotime($task['time']))); ?></div>
                            <?php endif; ?>
                            <?php if ($task['task']): ?>
                                <div><strong>កិច្ចការ:</strong> <?php echo htmlspecialchars($task['task']); ?></div>
                            <?php endif; ?>
                            <?php if ($task['status']): ?>
                                <div><strong>ស្ថានភាព:</strong> <?php echo htmlspecialchars($task['status']); ?></div>
                            <?php endif; ?>
                            <?php if ($task['due_date']): ?>
                                <div><strong>កាលបរិច្ឆេទកំណត់:</strong> <?php echo htmlspecialchars($task['due_date']); ?></div>
                            <?php endif; ?>
                            <?php if ($task['description']): ?>
                                <div><strong>ការពិពណ៌នា:</strong> <?php echo htmlspecialchars($task['description']); ?></div>
                            <?php endif; ?>
                            <?php if ($task['problem']): ?>
                                <div><strong>បញ្ហា:</strong> <?php echo htmlspecialchars($task['problem']); ?></div>
                            <?php endif; ?>
                            <?php if ($task['solution']): ?>
                                <div><strong>ដំណោះស្រាយ:</strong> <?php echo htmlspecialchars($task['solution']); ?></div>
                            <?php endif; ?>
                            <?php if ($task['plan_date'] || $task['plan']): ?>
                                <div><strong>ផែនការថ្ងៃបន្ទាប់:</strong></div>
                                <?php if ($task['plan_date']): ?>
                                    <div>&nbsp;&nbsp;• កាលបរិច្ឆេទ: <?php echo htmlspecialchars($task['plan_date']); ?></div>
                                <?php endif; ?>
                                <?php if ($task['plan']): ?>
                                    <div>&nbsp;&nbsp;• ព័ត៌មានលម្អិត: <?php echo htmlspecialchars($task['plan']); ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <a href="Admin_report.php" class="back-link">ត្រឡប់ទៅកាន់ Admin Panel</a>
        <?php endif; ?>
    </div>
</body>
</html>