<?php
session_start();

// Validate admin session
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

// Database credentials (consider moving to config file)
const DB_CONFIG = [
    'host' => 'localhost',
    'user' => 'samann1_scan_logs_worker_db',
    'pass' => 'scan_logs_worker_db@2025',
    'name' => 'samann1_scan_logs_worker_db'
];

// Status mapping
const STATUS_MAP = [
    '✅' => 'ជោគជ័យ',
    '✔️' => 'ជោគជ័យ',
    '❌' => 'បរាជ័យ',
    '✖️' => 'បរាជ័យ',
    '⏳' => 'កំពុងរង់ចាំ',
    '⚠️' => 'ការព្រមាន',
    '🚫' => 'ហាមឃាត់',
    '🔄' => 'កំពុងដំណើរការ',
    'Good' => '✅ល្អ',
    'Late' => '❌យឺត'
];

function mapStatusToText($status, $format = 'csv') {
    if (empty($status)) return 'មិនមានស្ថានភាព';

    $text = STATUS_MAP[$status] ?? $status;

    if ($format === 'html') {
        $colorMap = [
            'Late' => 'red',
            'Good' => 'blue',
            '❌' => 'red',
            '✖️' => 'red',
            '✅' => 'green',
            '✔️' => 'green',
            '⏳' => 'gray',
            '⚠️' => 'orange',
            '🚫' => 'darkred',
            '🔄' => 'blue'
        ];

        $iconMap = [
            'Good' => '<span class="status-icon good-icon"></span>',
            'Late' => '<span class="status-icon late-icon"></span>',
            '✅' => '<span class="status-icon success-icon"></span>',
            '✔️' => '<span class="status-icon success-icon"></span>',
            '❌' => '<span class="status-icon fail-icon"></span>',
            '✖️' => '<span class="status-icon fail-icon"></span>',
            '⏳' => '<span class="status-icon pending-icon"></span>',
            '⚠️' => '<span class="status-icon warning-icon"></span>',
            '🚫' => '<span class="status-icon forbidden-icon"></span>',
            '🔄' => '<span class="status-icon progress-icon"></span>'
        ];

        $color = 'black';
        $icon = '';
        foreach ($colorMap as $key => $val) {
            if (str_contains($status, $key) || $status === $key) {
                $color = $val;
                break;
            }
        }
        foreach ($iconMap as $key => $val) {
            if (str_contains($status, $key) || $status === $key) {
                $icon = $val;
                break;
            }
        }

        return "$icon <span style=\"color: $color;\">$text</span>";
    }
    return $text;
}

try {
    // Connect to database
    $pdo = new PDO(
        "mysql:host=" . DB_CONFIG['host'] . ";dbname=" . DB_CONFIG['name'] . ";charset=utf8mb4",
        DB_CONFIG['user'],
        DB_CONFIG['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );

    // Sanitize inputs
    $start_date = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING);
    $end_date = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING);
    $status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);
    $username = filter_input(INPUT_GET, 'username', FILTER_SANITIZE_STRING); 
    $usernames = $_GET['usernames'] ? explode(',', $_GET['usernames']) : []; 
    $format = filter_input(INPUT_GET, 'format', FILTER_SANITIZE_STRING) ?? 'csv';

    // Prepare SQL query
    $sql = "SELECT username, action, timestamp, status FROM scan_logs WHERE 1=1";
    $params = [];

    // Date range filter
    if ($start_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
        $sql .= " AND DATE(timestamp) >= ?";
        $params[] = $start_date;
    }
    if ($end_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        $sql .= " AND DATE(timestamp) <= ?";
        $params[] = $end_date;
    }

    // Status filter
    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }

    // Single username filter
    if ($username) {
        $sql .= " AND username LIKE ?";
        $params[] = '%' . $username . '%';
    }

    // Multiple usernames filter
    if (!empty($usernames)) {
        $placeholders = implode(',', array_fill(0, count($usernames), '?'));
        $sql .= " AND username IN ($placeholders)";
        $params = array_merge($params, $usernames);
    }

    // Modified ORDER BY to group by username first, then timestamp
    $sql .= " ORDER BY username ASC, timestamp DESC LIMIT 1000";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $index => $value) {
        $stmt->bindValue($index + 1, $value);
    }
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Output based on format
    if ($format === 'html') {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Scan Logs</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .status-icon {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 5px;
            vertical-align: middle;
        }
        .good-icon { background: radial-gradient(circle, #ccc 0%, #fff 70%); }
        .late-icon { background: repeating-linear-gradient(45deg, #ccc, #ccc 2px, #fff 2px, #fff 4px); }
        .success-icon { background-color: green; }
        .fail-icon { background-color: red; }
        .pending-icon { background-color: gray; }
        .warning-icon { background-color: orange; }
        .forbidden-icon { background-color: darkred; }
        .progress-icon { background-color: blue; }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <th>ឈ្មោះ</th>
                <th>ប្រភេទស្កេន</th>
                <th>ថ្ងៃខែឆ្នាំ</th>
                <th>ម៉ោង</th>
                <th>ស្ថានភាព</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($logs as $log) {
            $timestamp = $log['timestamp'] ?? '';
            $date = $time = '';
            if ($timestamp) {
                $datetime = new DateTime($timestamp);
                $date = $datetime->format('Y-m-d');
                $time = $datetime->format('H:i:s');
            }

            echo "<tr>
                <td>" . htmlspecialchars($log['username'] ?? '') . "</td>
                <td>" . htmlspecialchars($log['action'] ?? '') . "</td>
                <td>" . htmlspecialchars($date) . "</td>
                <td>" . htmlspecialchars($time) . "</td>
                <td>" . mapStatusToText($log['status'] ?? '', 'html') . "</td>
            </tr>";
        }

        echo '</tbody></table></body></html>';
    } else {
        // CSV output
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="ទិន្នន័យស្កនវត្តមាន_' . date('d-m-Y') . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        if ($output === false) {
            throw new Exception("មិនអាចបើក output stream បានទេ");
        }

        fwrite($output, "\xEF\xBB\xBF"); // UTF-8 BOM
        fputcsv($output, ['ឈ្មោះ', 'ប្រភេទស្កេន', 'ថ្ងៃខែឆ្នាំ', 'ម៉ោង', 'ស្ថានភាព']);

        foreach ($logs as $log) {
            $timestamp = $log['timestamp'] ?? '';
            $date = $time = '';
            if ($timestamp) {
                $datetime = new DateTime($timestamp);
                $date = $datetime->format('Y-m-d');
                $time = $datetime->format('H:i:s');
            }

            fputcsv($output, [
                $log['username'] ?? '',
                $log['action'] ?? '',
                $date,
                $time,
                mapStatusToText($log['status'] ?? '', 'csv')
            ]);
        }

        fclose($output);
    }
    exit;

} catch (PDOException $e) {
    $error_message = "កំហុសទិន្នន័យ: " . $e->getMessage() . 
                    " | កូដ: " . $e->getCode() . 
                    " | បន្ទាត់: " . $e->getLine();
    error_log($error_message);
    header('Content-Type: text/html; charset=UTF-8');
    die("មានបញ្ហាក្នុងការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ: " . htmlspecialchars($e->getMessage()));
} catch (Exception $e) {
    error_log("កំហុសទូទៅ: " . $e->getMessage());
    header('Content-Type: text/html; charset=UTF-8');
    die("កំហុស: " . htmlspecialchars($e->getMessage()));
}
?>