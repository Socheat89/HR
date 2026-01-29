<?php
// Start session for user tracking
session_start();

// Include the telegram.php file
require_once __DIR__ . '/includes/telegram.php'; // Ensure this path is correct

// Set UTF-8 encoding for the script
header('Content-Type: text/html; charset=UTF-8');

// Check login status
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirect to login page
    exit;
}

// Configuration
$dbHost = 'localhost';
$dbName = 'samann1_admin_panel';
$dbUser = 'samann1_admin_panel';
$dbPass = 'admin_panel@2025';
$telegramChatId = '-1002496391098';
define('BASE_URL', $_SERVER['PHP_SELF']);

// Database connection with MySQL and UTF-8 support
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => true,
    ]);
    $pdo->exec("SET NAMES 'utf8mb4'");
} catch (Exception $e) {
    die("កំហុសក្នុងការតភ្ជាប់ទៅប្រព័ន្ធមូលដ្ឋានទិន្នន័យ: " . $e->getMessage());
}

// Initialize variables
$success = '';
$errors = [];

// Handle status update (approve/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
    try {
        $request_id = (int)$_POST['request_id'];
        $action = $_POST['action'];
        $status = ($action === 'approve') ? 'approved' : 'rejected';

        // Update request status
        $stmt = $pdo->prepare("UPDATE requests SET status = ? WHERE id = ?");
        $stmt->execute([$status, $request_id]);

        // Fetch request details for Telegram notification
        $stmt = $pdo->prepare("SELECT * FROM requests WHERE id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();

        if ($request) {
            $message = "សំណើបានធ្វើបច្ចុប្បន្នភាព:\n" .
                       "- លេខសម្គាល់: {$request['id']}\n" .
                       "- ប្រភេទ: {$request['request_type']}\n" .
                       "- ឈ្មោះ: {$request['requester_name']}\n" .
                       "- ស្ថានភាព: " . ($status === 'approved' ? 'បានអនុម័ត' : 'បានបដិសេធ') . "\n" .
                       "- កាលបរិច្ឆេទ: " . date('Y-m-d H:i:s');
            if (!sendTelegramMessage($telegramChatId, $message)) {
                error_log("Failed to send Telegram message for request ID: $request_id");
            }
            $success = "សំណើ (ID: $request_id) ត្រូវបានធ្វើបច្ចុប្បន្នភាពជោគជ័យ!";
        }
    } catch (Exception $e) {
        $errors[] = "កំហុស: " . $e->getMessage();
        error_log("Error updating request: " . $e->getMessage());
    }
}

// Initialize filter and sort variables
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$filter_date = isset($_GET['request_date']) ? trim($_GET['request_date']) : '';
$filter_type = isset($_GET['request_type']) ? trim($_GET['request_type']) : '';
$sort_by = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'created_at';
$sort_order = isset($_GET['sort_order']) && in_array($_GET['sort_order'], ['ASC', 'DESC']) ? $_GET['sort_order'] : 'DESC';

// Validate sort_by to prevent SQL injection
$valid_sort_columns = ['id', 'request_type', 'request_date', 'created_at', 'status'];
if (!in_array($sort_by, $valid_sort_columns)) {
οντας: $sort_by = 'created_at';
}

// Build the SQL query with filters
$sql = "SELECT * FROM requests WHERE 1=1";
$params = [];

if ($filter_status) {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
}

if ($filter_date) {
    $sql .= " AND request_date = ?";
    $params[] = $filter_date;
}

if ($filter_type) {
    $sql .= " AND request_type LIKE ?";
    $params[] = "%$filter_type%";
}

$sql .= " ORDER BY $sort_by $sort_order";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>មើលរបាយការណ៍សំណើ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;700&display=swap');
        body {
            background: linear-gradient(120deg, #e0eafc, #cfdef3);
            font-family: 'Noto Sans Khmer', sans-serif;
            min-height: 100vh;
            padding: 20px;
        }
        .report-container {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.15);
            padding: 2.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        .form-title {
            color: #1a3c5e;
            font-size: 1.8rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 2.5rem;
            position: relative;
        }
        .form-title::after {
            content: '';
            width: 50px;
            height: 3px;
            background: #3498db;
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
        }
        .table {
            font-size: 0.9rem;
        }
        .table th, .table td {
            vertical-align: middle;
            text-align: center;
        }
        .table th {
            background: #3498db;
            color: #fff;
        }
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        .filter-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        .filter-form select, .filter-form input {
            padding: 10px;
            border-radius: 10px;
            border: 1px solid #ced4da;
            font-family: 'Noto Sans Khmer', sans-serif;
            background: #f8f9fa;
        }
        .filter-form select:focus, .filter-form input:focus {
            border-color: #3498db;
            background: #fff;
            box-shadow: 0 0 8px rgba(52, 152, 219, 0.2);
        }
        .btn-primary {
            background: linear-gradient(90deg, #3498db, #2980b9);
            border: none;
            padding: 10px;
            border-radius: 10px;
            font-family: 'Noto Sans Khmer', sans-serif;
        }
        .btn-primary:hover {
            background: linear-gradient(90deg, #2980b9, #1f6a93);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        .btn-success, .btn-danger, .btn-secondary {
            padding: 8px 12px;
            border-radius: 8px;
            font-family: 'Noto Sans Khmer', sans-serif;
        }
        .btn-success {
            background: #2d862d;
        }
        .btn-success:hover {
            background: #256d25;
        }
        .btn-danger {
            background: #cc0000;
        }
        .btn-danger:hover {
            background: #a30000;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .success, .error {
            text-align: center;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 1rem;
            font-family: 'Noto Sans Khmer', sans-serif;
        }
        .success {
            background: #e6ffe6;
            color: #2d862d;
            border: 1px solid #b3ffb3;
        }
        .error {
            background: #ffe6e6;
            color: #cc0000;
            border: 1px solid #ff9999;
        }
        .status-pending { color: #f39c12; }
        .status-approved { color: #2d862d; }
        .status-rejected { color: #cc0000; }
        @media (max-width: 768px) {
            .report-container {
                padding: 1.5rem;
            }
            .form-title {
                font-size: 1.5rem;
            }
            .table {
                font-size: 0.8rem;
            }
            .filter-form {
                flex-direction: column;
            }
            .btn-sm {
                font-size: 0.8rem;
                padding: 6px 10px;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <h2 class="form-title">របាយការណ៍សំណើ</h2>

        <?php if ($success): ?>
            <p class="success"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>

        <!-- Filter Form -->
        <form method="GET" class="filter-form">
            <select name="status">
                <option value="">ស្ថានភាពទាំងអស់</option>
                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>រង់ចាំ</option>
                <option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>បានអនុម័ត</option>
                <option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>បានបដិសេធ</option>
            </select>
            <input type="date" name="request_date" value="<?php echo htmlspecialchars($filter_date); ?>">
            <input type="text" name="request_type" placeholder="ប្រភេទសំណើ" value="<?php echo htmlspecialchars($filter_type); ?>">
            <select name="sort_by">
                <option value="id" <?php echo $sort_by === 'id' ? 'selected' : ''; ?>>លេខសម្គាល់</option>
                <option value="request_type" <?php echo $sort_by === 'request_type' ? 'selected' : ''; ?>>ប្រភេទ</option>
                <option value="request_date" <?php echo $sort_by === 'request_date' ? 'selected' : ''; ?>>ថ្ងៃស្នើសុំ</option>
                <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>កាលបរិច្ឆេទបង្កើត</option>
                <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>ស្ថានភាព</option>
            </select>
            <select name="sort_order">
                <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>ចុះ</option>
                <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>ឡើង</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-2"></i>តម្រង</button>
            <button type="button" class="btn btn-secondary" onclick="window.location.href='submit_request.php'"><i class="fas fa-plus me-2"></i>ស្នើសុំថ្មី</button>
        </form>

        <!-- Requests Table -->
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>លេខសម្គាល់</th>
                        <th>ប្រភេទ</th>
                        <th>ឈ្មោះអ្នកស្នើ</th>
                        <th>ផ្នែក</th>
                        <th>សាខា</th>
                        <th>ថ្ងៃស្នើសុំ</th>
                        <th>ចំនួនថ្ងៃ</th>
                        <th>មូលហេតុ</th>
                        <th>ស្ថានភាព</th>
                        <th>កាលបរិច្ឆេទបង្កើត</th>
                        <th>សកម្មភាព</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="11" class="text-center">មិនមានសំណើណាមួយត្រូវបានរកឃើញទេ។</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['id']); ?></td>
                                <td><?php echo htmlspecialchars($request['request_type']); ?></td>
                                <td><?php echo htmlspecialchars($request['requester_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($request['branch'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($request['request_date'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($request['number_of_days'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($request['reason'] ?? 'N/A'); ?></td>
                                <td class="status-<?php echo htmlspecialchars($request['status']); ?>">
                                    <?php
                                    $status = htmlspecialchars($request['status']);
                                    echo $status === 'pending' ? 'រង់ចាំ' : ($status === 'approved' ? 'បានអនុម័ត' : 'បានបដិសេធ');
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($request['created_at']); ?></td>
                                <td>
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> អនុម័ត</button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-times"></i> បដិសេធ</button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="delete_request.php?id=<?php echo $request['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('តើអ្នកប្រាកដទេថាចង់លុបសំណើនេះ?');"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="text-center mt-4">
            <button type="button" class="btn btn-secondary" onclick="window.location.href='https://app.vvc.asia/homes.php'">
                <i class="fas fa-arrow-left me-2"></i>ត្រឡប់ក្រោយ
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>