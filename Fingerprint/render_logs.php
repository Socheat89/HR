<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

$dbHost = 'localhost';
$dbUser = 'samann1_Fingerprint';
$dbPass = 'Fingerprint@2025';
$dbName = 'samann1_fingerprint_db';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    $limit = 20;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    $totalStmt = $pdo->query("SELECT COUNT(*) FROM scan_logs");
    $totalLogs = $totalStmt->fetchColumn();
    $totalPages = ceil($totalLogs / $limit);

    $stmt = $pdo->prepare("SELECT * FROM scan_logs ORDER BY timestamp DESC LIMIT :limit OFFSET :offset");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Reformat timestamp to mm/dd/yyyy
    foreach ($logs as &$log) {
        $log['timestamp'] = date('m/d/Y', strtotime($log['timestamp']));
    }
    unset($log);

    // Fetch distinct usernames for the dropdown
    $usernameStmt = $pdo->query("SELECT DISTINCT username FROM scan_logs WHERE username IS NOT NULL AND username != '' ORDER BY username");
    $usernames = $usernameStmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("មានបញ្ហាក្នុងការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ (Database connection failed)");
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ផ្ទាំងគ្រប់គ្រង - ប្រវត្តិស្កេន</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Battambang&display=swap" rel="stylesheet" id="googleFontCDN">
    <script>
        if (!document.getElementById('googleFontCDN').sheet) {
            document.write('<link rel="stylesheet" href="/local/battambang.css">');
            console.warn('Falling back to local Battambang font.');
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet" id="fontAwesomeCDN">
    <script>
        if (!document.getElementById('fontAwesomeCDN').sheet) {
            document.write('<link rel="stylesheet" href="/local/font-awesome.min.css">');
            console.warn('Falling back to local Font Awesome.');
        }
    </script>
    <style>
        body {
            font-family: 'Battambang', 'Khmer OS', 'Noto Sans Khmer', sans-serif;
            background-color: #f8f9fa;
        }
        .container {
            max-width: 95%;
            margin: 20px auto;
        }
        .admin-header {
            background-color: #007bff;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .table-responsive {
            margin-top: 20px;
            overflow-x: auto;
        }
        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
        .search-form {
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .table {
                font-size: 0.9rem;
            }
            .search-form .col {
                margin-bottom: 10px;
            }
        }
        .loading {
            text-align: center;
            padding: 20px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1 class="mb-0">ផ្ទាំងគ្រប់គ្រង - ប្រវត្តិស្កេនទាំងអស់</h1>
            <div class="mt-2">
                <a href="admin_logout.php" class="btn btn-light btn-sm">ចាកចេញ</a>
            </div>
        </div>

        <!-- Export to CSV Button -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <button id="export_csv" class="btn btn-success btn-sm">
                    <i class="fa-solid fa-download"></i> ទាញយក CSV
                </button>
            </div>
        </div>

        <!-- Date and Username Search Form -->
        <div class="search-form">
            <div class="row g-3 align-items-end">
                <div class="col">
                    <label class="form-label">ជ្រើសរើសឈ្មោះ</label>
                    <select id="username_filter" class="form-control">
                        <option value="">-- ទាំងអស់ --</option>
                        <?php foreach ($usernames as $username): ?>
                            <option value="<?php echo htmlspecialchars($username); ?>">
                                <?php echo htmlspecialchars($username); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col">
                    <label class="form-label">ចាប់ផ្តើមពីថ្ងៃ</label>
                    <input type="date" id="start_date" class="form-control">
                </div>
                <div class="col">
                    <label class="form-label">រហូតដល់ថ្ងៃ</label>
                    <input type="date" id="end_date" class="form-control">
                </div>
                <div class="col-auto">
                    <button id="search_button" class="btn btn-primary">ស្វែងរក</button>
                </div>
                <div class="col-auto">
                    <button id="clear_filter" class="btn btn-secondary">សម្អាត</button>
                </div>
            </div>
        </div>

        <div class="loading">កំពុងផ្ទុក...</div>
        <div class="table-responsive" id="logs_table">
            <table class="table table-striped table-bordered">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>ឈ្មោះ</th>
                        <th>ប្រភេទស្កេន</th>
                        <th>ថ្ងៃខែឆ្នាំ/ម៉ោង</th>
                        <th>ទីតាំង</th>
                        <th>ស្ថានភាព</th>
                        <th>អាសយដ្ឋាន</th>
                        <th>សកម្មភាព</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="8" class="text-center">មិនមានទិន្នន័យប្រវត្តិសម្រាប់ការស្វែងរកនេះ!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['id']); ?></td>
                                <td><?php echo htmlspecialchars($log['username'] ?? 'មិនមាន'); ?></td>
                                <td><?php echo htmlspecialchars($log['action'] ?? 'មិនមាន'); ?></td>
                                <td><?php echo htmlspecialchars($log['timestamp'] ?? 'មិនមាន'); ?></td>
                                <td>
                                    <?php if (isset($log['latitude']) && isset($log['longitude']) && $log['latitude'] && $log['longitude']): ?>
                                        <a href="https://www.google.com/maps?q=<?php echo urlencode($log['latitude'] . ',' . $log['longitude']); ?>" target="_blank">
                                            <?php echo htmlspecialchars($log['latitude'] . ', ' . $log['longitude']); ?>
                                        </a>
                                    <?php else: ?>
                                        មិនមានទីតាំង
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['status'] ?? 'មិនមាន'); ?></td>
                                <td><?php echo htmlspecialchars($log['address'] ?? 'មិនមាន'); ?></td>
                                <td>
                                    <a href="edit_log.php?id=<?php echo htmlspecialchars($log['id']); ?>" class="btn btn-warning btn-sm">
                                        <i class="fa-solid fa-edit"></i> កែ
                                    </a>
                                    <a href="delete_log.php?id=<?php echo htmlspecialchars($log['id']); ?>" class="btn btn-danger btn-sm" onclick="return confirm('តើអ្នកប្រាកដជាចង់លុបមែនទេ?')">
                                        <i class="fa-solid fa-trash"></i> លុប
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div id="pagination_container">
            <?php
            echo '<nav><ul class="pagination">';
            for ($i = 1; $i <= $totalPages; $i++) {
                echo "<li class='page-item " . ($i == $page ? 'active' : '') . "'><a class='page-link' href='?page=$i'>$i</a></li>";
            }
            echo '</ul></nav>';
            ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        if (typeof bootstrap === 'undefined') {
            console.warn('Bootstrap JS failed to load from CDN.');
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const exportButton = document.getElementById('export_csv');
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            const usernameFilter = document.getElementById('username_filter');
            const searchButton = document.getElementById('search_button');
            const clearButton = document.getElementById('clear_filter');
            const loading = document.querySelector('.loading');
            const logsTable = document.getElementById('logs_table');
            const pagination = document.getElementById('pagination_container');

            function fetchLogs(page) {
                const startDate = startDateInput.value;
                const endDate = endDateInput.value;
                const username = usernameFilter.value;

                loading.style.display = 'block';
                logsTable.innerHTML = '';
                pagination.innerHTML = '';

                const url = `fetch_logs.php?page=${page}&start_date=${startDate}&end_date=${endDate}&username=${encodeURIComponent(username)}`;

                fetch(url, {
                    method: 'GET'
                })
                .then(response => response.json())
                .then(data => {
                    logsTable.innerHTML = data.logs_html;
                    pagination.innerHTML = data.pagination_html;
                    loading.style.display = 'none';
                })
                .catch(error => {
                    console.error('Error:', error);
                    logsTable.innerHTML = '<p class="text-danger">មានបញ្ហាក្នុងការស្វែងរកទិន្នន័យ (Error loading data)</p>';
                    loading.style.display = 'none';
                });
            }

            searchButton.addEventListener('click', (e) => {
                e.preventDefault();
                fetchLogs(1);
            });

            exportButton.addEventListener('click', function() {
                const startDate = startDateInput.value;
                const endDate = endDateInput.value;
                const username = usernameFilter.value;
                window.location.href = `export_logs.php?start_date=${startDate}&end_date=${endDate}&username=${encodeURIComponent(username)}`;
            });

            clearButton.addEventListener('click', (e) => {
                e.preventDefault();
                startDateInput.value = '';
                endDateInput.value = '';
                usernameFilter.value = '';
                fetchLogs(1);
            });

            fetchLogs(1);
        });
    </script>
</body>
</html>