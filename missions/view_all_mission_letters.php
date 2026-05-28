<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Centralized Database Connection
require_once __DIR__ . '/../db_connection.php';

try {
    $conn = getPDO();

    // Filters
    $filter_date = isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '';

    // Prepare SQL query
    $sql = "SELECT * FROM mission_letters";
    $params = [];
    if ($filter_date) {
        $sql .= " WHERE start_date = :date";
        $params[':date'] = $filter_date;
    }
    $sql .= " ORDER BY start_date DESC, id DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $missions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>បញ្ជីលិខិតបេសកកម្ម</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: 'Khmer OS Battambang', Arial, sans-serif;
            background-color: #f4f4f4;
        }
        .controls {
            margin-bottom: 20px;
            text-align: center;
        }
        .table-container {
            width: 100%;
            max-width: 1200px;
            margin: auto;
            background: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: center;
            border: 1px solid #ddd;
        }
        th {
            background-color: #0d6efd;
            color: white;
            font-family: 'Koulen';
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .btn-sm {
            font-size: 14px;
            margin-right: 5px;
        }
        @font-face { font-family: 'Koulen'; src: url('/font/Koulen.ttf'); }
        @font-face { font-family: 'Khmer OS Battambang'; src: url('/font/KhmerOSBattambang.ttf'); }
    </style>
</head>
<body>
    <div class="controls">
        <form method="GET" class="d-inline-block">
            <label for="filter_date">ស្វែងរកតាមកាលបរិច្ឆេទ:</label>
            <input type="date" id="filter_date" name="filter_date" value="<?= htmlspecialchars($filter_date) ?>">
            <button type="submit" class="btn btn-primary btn-sm">ស្វែងរក</button>
            <?php if ($filter_date): ?>
                <a href="../missions/view_all_mission_letters.php" class="btn btn-secondary btn-sm">បង្ហាញទាំងអស់</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-container">
        <h3 style="text-align: center; font-family: 'Koulen';">បញ្ជីលិខិតបេសកកម្ម</h3>
        <?php if (empty($missions)): ?>
            <p style="text-align: center;">មិនមានទិន្នន័យបេសកកម្មទេ</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ល.រ</th>
                        <th>ទីតាំង</th>
                        <th>គោលបំណង</th>
                        <th>ថ្ងៃចេញដំណើរ</th>
                        <th>ថ្ងៃត្រឡប់</th>
                        <th>សកម្មភាព</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($missions as $index => $mission): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($mission['location']) ?></td>
                            <td><?= htmlspecialchars($mission['purpose']) ?></td>
                            <td><?= htmlspecialchars($mission['start_date']) ?></td>
                            <td><?= htmlspecialchars($mission['end_date']) ?></td>
                            <td>
                                <a href="../missions/mission.php?id=<?= $mission['id'] ?>" class="btn btn-info btn-sm">មើល</a>
                                <a href="../missions/edit_mission.php?id=<?= $mission['id'] ?>" class="btn btn-warning btn-sm">កែសម្រួល</a>
                                <a href="../missions/mission.php?id=<?= $mission['id'] ?>&print=1" class="btn btn-success btn-sm" onclick="window.open(this.href, '_blank'); return false;">បោះពុម្ព</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
