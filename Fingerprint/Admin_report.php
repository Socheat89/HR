<?php
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Database configuration
$dbHost = 'localhost';
$dbUser = 'samann1_daily_report_db';
$dbPass = 'daily_report_db';
$dbName = 'samann1_daily_report_db';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle filters
$whereClause = [];
$params = [];
$dateFilter = isset($_GET['date']) ? $_GET['date'] : '';
$positionFilter = isset($_GET['position']) ? $_GET['position'] : '';
$nameFilter = isset($_GET['name']) ? $_GET['name'] : '';

if ($dateFilter) {
    $whereClause[] = "r.report_date = :date";
    $params[':date'] = date('m/d/Y', strtotime($dateFilter));
}
if ($positionFilter) {
    $whereClause[] = "r.position = :position";
    $params[':position'] = $positionFilter;
}
if ($nameFilter) {
    $whereClause[] = "r.name LIKE :name";
    $params[':name'] = "%$nameFilter%";
}

$sql = "SELECT r.*, COUNT(rt.id) as task_count 
        FROM reports r 
        LEFT JOIN report_tasks rt ON r.id = rt.report_id";
if (!empty($whereClause)) {
    $sql .= " WHERE " . implode(" AND ", $whereClause);
}
$sql .= " GROUP BY r.id ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Daily Reports</title>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --background-color: #ecf0f1;
            --text-color: #333;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        h1 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 2rem;
        }

        .logout-btn {
            float: right;
            background-color: var(--accent-color);
            color: white;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .logout-btn:hover {
            background-color: #c0392b;
        }

        .filter-form {
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .filter-form form {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-form label {
            display: flex;
            flex-direction: column;
            font-size: 0.9rem;
            color: var(--primary-color);
        }

        .filter-form input, 
        .filter-form select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            margin-top: 5px;
        }

        .filter-form button {
            padding: 10px 20px;
            background-color: var(--secondary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .filter-form button:hover {
            background-color: #2980b9;
        }

        .reports-table {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
        }

        th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
        }

        tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        tr:hover {
            background-color: #f1f3f5;
        }

        .actions a {
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 3px;
            margin-right: 5px;
        }

        .actions a:first-child {
            background-color: var(--secondary-color);
            color: white;
        }

        .actions a:last-child {
            background-color: var(--accent-color);
            color: white;
        }

        .actions a:hover {
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .filter-form form {
                flex-direction: column;
            }
            
            .filter-form label {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Panel - Daily Reports</h1>
        <a href="logout.php" class="logout-btn">Logout</a>

        <!-- Filter Form -->
        <div class="filter-form">
            <form method="GET">
                <label>
                    Date
                    <input type="date" name="date" value="<?php echo htmlspecialchars($dateFilter); ?>">
                </label>
                <label>
                    Position
                    <select name="position">
                        <option value="">All</option>
                        <option value="IT SUPPORT" <?php echo $positionFilter === 'IT SUPPORT' ? 'selected' : ''; ?>>IT SUPPORT</option>
                        <option value="IT MANAGER" <?php echo $positionFilter === 'IT MANAGER' ? 'selected' : ''; ?>>IT MANAGER</option>
                        <option value="Administration" <?php echo $positionFilter === 'Administration' ? 'selected' : ''; ?>>Administration</option>
                    </select>
                </label>
                <label>
                    Name
                    <input type="text" name="name" value="<?php echo htmlspecialchars($nameFilter); ?>">
                </label>
                <button type="submit">Filter</button>
            </form>
        </div>

        <!-- Reports Table -->
        <div class="reports-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Department</th>
                        <th>Tasks</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($report['id']); ?></td>
                            <td><?php echo htmlspecialchars($report['report_date']); ?></td>
                            <td><?php echo htmlspecialchars($report['name']); ?></td>
                            <td><?php echo htmlspecialchars($report['position']); ?></td>
                            <td><?php echo htmlspecialchars($report['department'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($report['task_count']); ?></td>
                            <td><?php echo htmlspecialchars($report['created_at']); ?></td>
                            <td class="actions">
                                <a href="view_report.php?id=<?php echo $report['id']; ?>">View</a>
                                <a href="delete_report.php?id=<?php echo $report['id']; ?>" 
                                   onclick="return confirm('Are you sure?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>