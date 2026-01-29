<?php
// ====================================================================
// NEW: ส่วนពិនិត្យการ Login (Security Check)
// កូដនេះត្រូវតែនៅខាងលើสุดជានិច្ច
// ====================================================================
session_start(); // ចាប់ផ្តើម Session

// ពិនិត្យមើលថា Admin បាន Login ហើយឬនៅ
// ប្រសិនបើមិនទាន់ Login, បញ្ជូនទៅកាន់หน้า admin_login.php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit; // សំខាន់ណាស់! ត្រូវតែមាន exit បន្ទាប់ពី header
}


// ====================================================================
// កូដដើមរបស់អ្នកចាប់ផ្តើមពីទីនេះ (Your Original Code Starts Here)
// ====================================================================

// Database connection details
$db_host = 'localhost';
$db_name = 'samann1_scan_logs_worker_db';
$db_user = 'samann1_scan_logs_worker_db';
$db_pass = 'scan_logs_worker_db@2025';

// Helper function to safely display data.
function displayValue($data): string
{
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}


// ====================================================================
// AJAX HANDLERS - ផ្នែកដោះស្រាយ AJAX Request
// ====================================================================

// Refactored function to get log data to avoid code duplication
function getLogData(PDO $pdo, array $request_data) {
    // Initialize variables
    $logs = [];
    $late_summary_data = [];
    $total_records = 0;
    $total_pages = 0;
    $current_page = 1;
    $chart_data = ['Good' => 0, 'Late' => 0];

    $selected_date = $request_data['selected_date'] ?? '';
    $selected_staff_type = $request_data['staff_type'] ?? 'professional';

    $displayColumns = ['id', 'user_id', 'username', 'branch', 'action', 'timestamp', 'status', 'early_reason', 'noted', 'folder'];
    $columnsString = implode(', ', $displayColumns);

    $whereClauses = [];
    $params = [];

    if (!empty($selected_date)) {
        $whereClauses[] = "DATE(timestamp) = ?";
        $params[] = $selected_date;
    }

    if ($selected_staff_type === 'professional') {
        $professional_folders = ['ជំនាញ', 'ហាងទំនិញ៣១៨', 'SK Chuk Meas', 'ឃ្លាំង', 'SK Cosmetic'];
        $placeholders = implode(',', array_fill(0, count($professional_folders), '?'));
        $whereClauses[] = "folder IN ($placeholders)";
        $params = array_merge($params, $professional_folders);
    } elseif ($selected_staff_type === 'worker') {
        $whereClauses[] = "folder = ?";
        $params[] = 'កម្មករ';
    }

    $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    // --- Get total records for pagination ---
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM scan_logs $whereClause");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();

    // --- Get data for the chart ---
    $chart_params = $params;
    $chart_where_clause = $whereClause . (!empty($whereClause) ? ' AND ' : 'WHERE ') . "status IN ('Good', 'Late')";
    $chart_stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM scan_logs $chart_where_clause GROUP BY status");
    $chart_stmt->execute($chart_params);
    $status_counts = $chart_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (isset($status_counts['Good'])) $chart_data['Good'] = (int)$status_counts['Good'];
    if (isset($status_counts['Late'])) $chart_data['Late'] = (int)$status_counts['Late'];

    // --- NEW: Get data for the late summary report ---
    if (!empty($selected_date)) {
        $summary_params = $params;
        $summary_where_clause = $whereClause . (!empty($whereClause) ? ' AND ' : 'WHERE ') . "status = 'Late'";
        $summary_stmt = $pdo->prepare("SELECT DISTINCT username FROM scan_logs $summary_where_clause ORDER BY username ASC");
        $summary_stmt->execute($summary_params);
        $late_summary_data = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- Pagination Logic ---
    $records_per_page = 500;
    $total_pages = ceil($total_records / $records_per_page);
    $current_page = isset($request_data['page']) && is_numeric($request_data['page']) ? (int)$request_data['page'] : 1;

    if ($current_page < 1) $current_page = 1;
    if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

    $offset = ($current_page - 1) * $records_per_page;

    // --- Get paginated log data for the table ---
    $orderBy = "ORDER BY username ASC, timestamp ASC";
    $query = "SELECT $columnsString FROM scan_logs $whereClause $orderBy LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($query);

    $param_index = 1;
    foreach ($params as $param) {
        $stmt->bindValue($param_index++, is_int($param) ? $param : (string)$param);
    }
    $stmt->bindValue(count($params) + 1, $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);

    $stmt->execute();
    $logs = $stmt->fetchAll();

    return compact('logs', 'total_records', 'total_pages', 'current_page', 'chart_data', 'late_summary_data');
}


// Helper function to generate table rows HTML
function generateTableRowsHTML(array $logs): string {
    if (empty($logs)) {
        return '<tr><td colspan="10" class="text-center" style="padding: 4rem;"><i class="fas fa-box-open fa-3x" style="color:#aaa;"></i><h5 style="margin-top: 1.5rem; margin-bottom: 0.5rem;">មិនមានទិន្នន័យត្រូវបង្ហាញទេ!</h5><p style="color:var(--text-muted);">សូមសាកល្បងត្រងទិន្នន័យម្តងទៀត ឬប្តូរប្រភេទបុគ្គលិក។</p></td></tr>';
    }

    ob_start();
    foreach ($logs as $log) { ?>
        <tr data-id="<?= displayValue($log['id']) ?>">
            <td><?= displayValue($log['user_id']) ?></td>
            <td style="color: #05165e; font-weight: bold;"><?= displayValue($log['username']) ?></td>
            <td><?= displayValue($log['branch']) ?></td>
            <td><?= displayValue($log['action']) ?></td>
            <td style="color: #05165e; font-weight: bold;"><?= date('d/m/Y', strtotime($log['timestamp'])) ?></td>
            <td style="color: #05165e; font-weight: bold;"><?= date('h:i:s A', strtotime($log['timestamp'])) ?></td>
            <td>
                <?php
                $status = displayValue($log['status']);
                $status_lower = strtolower($status);
                $icon_class = ''; $text_class = '';
                if ($status_lower === 'success') { $icon_class = 'fa-check-circle'; $text_class = 'status-success'; }
                elseif ($status_lower === 'failed') { $icon_class = 'fa-times-circle'; $text_class = 'status-failed'; }
                elseif ($status_lower === 'good') { $icon_class = 'fa-circle'; $text_class = 'status-good'; }
                elseif ($status_lower === 'late') { $icon_class = 'fa-circle'; $text_class = 'status-late'; }
                else { $icon_class = 'fa-circle-question'; $text_class = 'status-unknown'; }
                echo "<span class='$text_class'><i class='fas $icon_class status-icon'></i> $status</span>";
                ?>
            </td>
            <td><?= displayValue($log['early_reason']) ?></td>
            <td class="editable-note" data-column="noted"><?= displayValue($log['noted']) ?></td>
            <td class="action-buttons action-column">
                <button class="btn btn-action btn-edit" data-id="<?= displayValue($log['id']) ?>" title="កែសម្រួល"><i class="fas fa-pencil-alt"></i></button>
                <button class="btn btn-action btn-duplicate" data-id="<?= displayValue($log['id']) ?>" title="ចម្លង"><i class="fas fa-copy"></i></button>
                <button class="btn btn-action btn-delete" data-id="<?= displayValue($log['id']) ?>" title="លុប"><i class="fas fa-trash-alt"></i></button>
            </td>
        </tr>
    <?php }
    return ob_get_clean();
}

// Helper function to generate the late summary HTML
function generateLateSummaryHTML(array $late_staff): string {
    if (empty($late_staff)) {
        return '<p class="text-center text-muted p-4">មិនមានបុគ្គលិកមកយឺតសម្រាប់ថ្ងៃដែលបានជ្រើសរើសទេ។</p>';
    }
    ob_start();
    echo '<ul class="late-summary-list">';
    foreach ($late_staff as $staff) {
        echo '<li><i class="fas fa-user-clock icon-late"></i> ' . displayValue($staff['username']) . '</li>';
    }
    echo '</ul>';
    return ob_get_clean();
}


// Helper function to generate pagination HTML
function generatePaginationHTML(int $current_page, int $total_pages, array $query_params): string {
    if ($total_pages <= 1) return '';
    ob_start(); ?>
    <nav aria-label="Page navigation">
        <ul class="pagination">
            <?php
            // Previous button
            if ($current_page > 1) {
                $query_params['page'] = $current_page - 1;
                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">&laquo;</a></li>';
            } else { echo '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>'; }
            // Page numbers
            $num_links = 2;
            if ($current_page > ($num_links + 1)) {
                $query_params['page'] = 1;
                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">1</a></li>';
                if ($current_page > ($num_links + 2)) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
            }
            for ($i = max(1, $current_page - $num_links); $i <= min($total_pages, $current_page + $num_links); $i++) {
                $query_params['page'] = $i;
                if ($i == $current_page) { echo '<li class="page-item active" aria-current="page"><span class="page-link">' . $i . '</span></li>'; }
                else { echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">' . $i . '</a></li>'; }
            }
            if ($current_page < ($total_pages - $num_links)) {
                if ($current_page < ($total_pages - $num_links - 1)) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; }
                $query_params['page'] = $total_pages;
                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">' . $total_pages . '</a></li>';
            }
            // Next button
            if ($current_page < $total_pages) {
                $query_params['page'] = $current_page + 1;
                echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">&raquo;</a></li>';
            } else { echo '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>'; }
            ?>
        </ul>
    </nav>
    <?php return ob_get_clean();
}


// AJAX handler to PREVIEW excess logs
if (isset($_POST['action']) && $_POST['action'] === 'preview_excess_logs') {
    header('Content-Type: application/json');
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->query("SELECT username, DATE(timestamp) as scan_date, COUNT(id) as total_scans FROM scan_logs GROUP BY username, DATE(timestamp) HAVING COUNT(id) > 4 ORDER BY scan_date DESC, username ASC");
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        http_response_code(500); error_log("Preview excess logs error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// AJAX handler for cleaning up excess scan logs
if (isset($_POST['action']) && $_POST['action'] === 'auto_delete_excess') {
    header('Content-Type: application/json');
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->beginTransaction();

        $stmt_groups = $pdo->query("SELECT username, DATE(timestamp) as scan_date FROM scan_logs GROUP BY username, DATE(timestamp) HAVING COUNT(id) > 4");
        $groups_to_clean = $stmt_groups->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($groups_to_clean)) {
            $pdo->commit();
            echo json_encode(['status' => 'success', 'message' => 'មិនមានកំណត់ត្រាលើសដែលត្រូវលុបទេ។', 'moved_count' => 0]);
            exit;
        }

        $cols_res = $pdo->query("SHOW COLUMNS FROM scan_logs");
        $columns_string = '`' . implode('`, `', $cols_res->fetchAll(PDO::FETCH_COLUMN)) . '`';
        $total_moved_records = 0;

        foreach ($groups_to_clean as $group) {
            $stmt_ids = $pdo->prepare("SELECT id FROM scan_logs WHERE username = ? AND DATE(timestamp) = ? ORDER BY timestamp ASC LIMIT 4, 18446744073709551615");
            $stmt_ids->execute([$group['username'], $group['scan_date']]);
            $ids_to_move = $stmt_ids->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($ids_to_move)) {
                $placeholders = implode(',', array_fill(0, count($ids_to_move), '?'));
                $pdo->prepare("INSERT INTO deleted_logs ($columns_string) SELECT $columns_string FROM scan_logs WHERE id IN ($placeholders)")->execute($ids_to_move);
                $pdo->prepare("DELETE FROM scan_logs WHERE id IN ($placeholders)")->execute($ids_to_move);
                $total_moved_records += count($ids_to_move);
            }
        }
        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => "បានរក្សាទុក និងលុបកំណត់ត្រាដែលលើសចំនួន $total_moved_records ដោយជោគជ័យ។", 'moved_count' => $total_moved_records]);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        http_response_code(500); error_log("Auto-delete error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// AJAX handler for updating a note
if (isset($_POST['action']) && $_POST['action'] === 'update_note') {
    header('Content-Type: application/json');
    if (!isset($_POST['id']) || !isset($_POST['noted'])) {
        http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'Invalid data.']); exit;
    }
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare("UPDATE scan_logs SET noted = ? WHERE id = ?");
        $stmt->execute([$_POST['noted'], $_POST['id']]);
        if ($stmt->rowCount() > 0) { echo json_encode(['status' => 'success', 'message' => 'Note updated successfully.']); }
        else { echo json_encode(['status' => 'error', 'message' => 'No record found or no changes made.']); }
    } catch (PDOException $e) {
        http_response_code(500); error_log("Database error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// AJAX handler for filtering and pagination
if (isset($_GET['action']) && $_GET['action'] === 'filter_logs') {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'An unknown error occurred.'];
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $data = getLogData($pdo, $_GET);
        $response = [
            'status' => 'success',
            'table_html' => generateTableRowsHTML($data['logs']),
            'pagination_html' => generatePaginationHTML($data['current_page'], $data['total_pages'], $_GET),
            'late_summary_html' => generateLateSummaryHTML($data['late_summary_data']),
            'total_records' => $data['total_records'],
            'current_page' => $data['current_page'],
            'total_pages' => $data['total_pages'],
            'chart_data' => $data['chart_data']
        ];
    } catch (PDOException $e) {
        http_response_code(500); error_log("Database error: " . $e->getMessage());
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
    echo json_encode($response);
    exit;
}

// AJAX handler to GET a single log's details
if (isset($_POST['action']) && $_POST['action'] === 'get_log_details') {
    header('Content-Type: application/json');
    if (!isset($_POST['id'])) { http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'ID is missing.']); exit; }
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare("SELECT * FROM scan_logs WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($log) { echo json_encode(['status' => 'success', 'data' => $log]); }
        else { http_response_code(404); echo json_encode(['status' => 'error', 'message' => 'Log not found.']); }
    } catch (PDOException $e) {
        http_response_code(500); error_log("Get log details error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error.']);
    }
    exit;
}

// AJAX handler to UPDATE a log
if (isset($_POST['action']) && $_POST['action'] === 'update_log') {
    header('Content-Type: application/json');
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $sql = "UPDATE scan_logs SET user_id=?, username=?, branch=?, action=?, `timestamp`=?, status=?, early_reason=?, noted=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['user_id'],
            $_POST['username'],
            $_POST['branch'],
            $_POST['action_type'],
            $_POST['timestamp'],
            $_POST['status'],
            $_POST['early_reason'],
            $_POST['noted'],
            $_POST['log_id']
        ]);
        echo json_encode(['status' => 'success', 'message' => 'កំណត់ត្រាត្រូវបានធ្វើបច្ចុប្បន្នភាពដោយជោគជ័យ។']);
    } catch (PDOException $e) {
        http_response_code(500); error_log("Update log error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// AJAX handler to DUPLICATE a log
if (isset($_POST['action']) && $_POST['action'] === 'duplicate_log') {
    header('Content-Type: application/json');
    if (!isset($_POST['id'])) { http_response_code(400); echo json_encode(['status' => 'error', 'message' => 'ID is missing.']); exit; }
    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt_select = $pdo->prepare("SELECT * FROM scan_logs WHERE id = ?");
        $stmt_select->execute([$_POST['id']]);
        $original_log = $stmt_select->fetch(PDO::FETCH_ASSOC);

        if (!$original_log) { http_response_code(404); echo json_encode(['status' => 'error', 'message' => 'Original log not found.']); exit; }
        
        unset($original_log['id']);
        $original_log['noted'] = trim('ច្បាប់ចម្លងពី ID ' . $_POST['id'] . '. ' . $original_log['noted']);
        
        $original_log['timestamp'] = date('Y-m-d', strtotime($original_log['timestamp'])) . ' ' . date('H:i:s');

        $columns = array_keys($original_log);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = sprintf('INSERT INTO scan_logs (`%s`) VALUES (%s)', implode('`, `', $columns), implode(', ', $placeholders));
        $stmt_insert = $pdo->prepare($sql);
        $stmt_insert->execute(array_values($original_log));

        echo json_encode(['status' => 'success', 'message' => 'កំណត់ត្រាត្រូវបានចម្លងដោយជោគជ័យ។']);
    } catch (PDOException $e) {
        http_response_code(500); error_log("Duplicate log error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// AJAX handler to DELETE a log with full audit info
if (isset($_POST['action']) && $_POST['action'] === 'delete_log') {
    header('Content-Type: application/json');
    if (!isset($_POST['id'])) { 
        http_response_code(400); 
        echo json_encode(['status' => 'error', 'message' => 'ID is missing.']); 
        exit; 
    }

    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get the log data before deleting
        $stmt_select = $pdo->prepare("SELECT * FROM scan_logs WHERE id = ?");
        $stmt_select->execute([$_POST['id']]);
        $log = $stmt_select->fetch(PDO::FETCH_ASSOC);

        if (!$log) {
            http_response_code(404); 
            echo json_encode(['status' => 'error', 'message' => 'Log not found.']); 
            exit;
        }

        // --- Start: Retrieve admin info from sub_users table ---
        $deleted_by_name = 'unknown';
        $deleted_by_id = null;

        if (isset($_SESSION['admin_id'])) {
            $deleted_by_id = $_SESSION['admin_id'];
            // Assume 'sub_users' is the table for administrators/sub-users
            // and it has an 'id' column and a 'username' column for the display name
            $stmt_admin = $pdo->prepare("SELECT username FROM sub_users WHERE id = ?");
            $stmt_admin->execute([$deleted_by_id]);
            $admin_user = $stmt_admin->fetch(PDO::FETCH_ASSOC);

            if ($admin_user && isset($admin_user['username'])) {
                $deleted_by_name = $admin_user['username'];
            } else {
                // Fallback if ID is in session but not found in sub_users, or username column is different
                error_log("Admin ID " . $deleted_by_id . " not found in sub_users table or 'username' column missing. Falling back to session username if available.");
                $deleted_by_name = $_SESSION['admin_username'] ?? 'unknown (ID not found or sub_users issue)'; // Use session username as a fallback if available
            }
        } elseif (isset($_SESSION['admin_username'])) {
            // If admin_id is not set, but username is, use it as a best effort
            $deleted_by_name = $_SESSION['admin_username'];
        }
        // --- End: Retrieve admin info from sub_users table ---


        // Insert into deleted_logs
        $cols_res = $pdo->query("SHOW COLUMNS FROM scan_logs");
        $columns_string = '`' . implode('`, `', $cols_res->fetchAll(PDO::FETCH_COLUMN)) . '`';
        $pdo->prepare("INSERT INTO deleted_logs ($columns_string) SELECT $columns_string FROM scan_logs WHERE id = ?")->execute([$_POST['id']]);

        // Insert into audit_log
        $audit_stmt = $pdo->prepare("INSERT INTO audit_log (user, action, table_name, record_id, app_user_id, app_user_name) VALUES (?, 'DELETE', 'scan_logs', ?, ?, ?)");
        $audit_stmt->execute([
            $deleted_by_name, // Use the name retrieved from sub_users
            $log['id'],
            $deleted_by_id,   // Use the ID from session, linked to sub_users
            $deleted_by_name  // Use the name retrieved from sub_users
        ]);

        // Delete from scan_logs
        $stmt = $pdo->prepare("DELETE FROM scan_logs WHERE id = ?");
        $stmt->execute([$_POST['id']]);

        // Send Telegram notification
        $message = "✅ <b>Scan Log Deleted</b>\n";
        $message .= "ID: " . $log['id'] . "\n";
        $message .= "Username: " . $log['username'] . "\n";
        $message .= "Action: " . $log['action'] . "\n";
        $message .= "Deleted by: " . $deleted_by_name . "\n"; // Use the name retrieved from sub_users
        $message .= "Time: " . date('Y-m-d H:i:s');

        // Telegram function with added debugging
        function sendTelegramNotification($message) {
            $bot_token = '7680086124:AAHrvdz-mOx3pO1Ijqvh7BHTeGh2JB5JuwQ'; // ពិនិត្យមើលម្ដងទៀត
            $chat_id = '-1002802610249'; // ពិនិត្យមើលម្ដងទៀត និងត្រូវប្រាកដថា bot ជា admin ក្នុង group

            // Log the message being sent
            error_log("Attempting to send Telegram notification: " . $message);
            
            $url = "https://api.telegram.org/bot$bot_token/sendMessage";
            $data = ['chat_id' => $chat_id, 'text' => $message, 'parse_mode' => 'HTML'];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Get response as a string
            curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Set a timeout for the request

            $response = curl_exec($ch); // Execute the cURL request
            $error = curl_error($ch);   // Get cURL error message
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get HTTP status code

            curl_close($ch);

            // Log cURL details for debugging
            error_log("Telegram API URL: " . $url);
            error_log("Telegram Data (sent): " . print_r($data, true));
            error_log("Telegram API Response: " . ($response ? $response : "No response / Empty response"));
            error_log("Telegram cURL Error: " . ($error ? $error : "No cURL error"));
            error_log("Telegram HTTP Code: " . $http_code);

            // Decode response and check for Telegram API specific errors
            $decoded_response = json_decode($response, true);
            if ($decoded_response && isset($decoded_response['ok']) && $decoded_response['ok'] === false) {
                error_log("Telegram API Error Details: " . ($decoded_response['description'] ?? 'Unknown Telegram API error'));
            } elseif ($decoded_response && isset($decoded_response['ok']) && $decoded_response['ok'] === true) {
                error_log("Telegram notification sent successfully.");
            } else {
                error_log("Telegram API did not return a valid JSON response or 'ok' status was missing.");
            }
        }

        sendTelegramNotification($message);

        echo json_encode(['status' => 'success', 'message' => 'កំណត់ត្រាត្រូវបានលុបដោយជោគជ័យ និង Telegram បានផ្ញើ notification។']);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        http_response_code(500); 
        error_log("Delete log error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    } catch (Exception $e) {
        // Catch any other general exceptions
        http_response_code(500); 
        error_log("General error in delete_log: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'An unexpected error occurred: ' . $e->getMessage()]);
    }
    exit;
}

// ====================================================================
// Main Page Logic (runs on initial page load)
// ====================================================================
$logs = [];
$available_dates = [];
$error_message = '';
$selected_date = '';
$selected_staff_type = '';
$total_records = 0;
$total_pages = 1;
$current_page = 1;
$chart_data_json = '{"Good": 0, "Late": 0}';
$late_summary_data = [];

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $date_stmt = $pdo->query("SELECT DISTINCT DATE(timestamp) as date_only FROM scan_logs ORDER BY date_only DESC");
    $available_dates = $date_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $initial_data = getLogData($pdo, $_GET);
    $logs = $initial_data['logs'];
    $total_records = $initial_data['total_records'];
    $total_pages = $initial_data['total_pages'];
    $current_page = $initial_data['current_page'];
    $chart_data_json = json_encode($initial_data['chart_data']);
    $late_summary_data = $initial_data['late_summary_data'];

    $selected_date = $_GET['selected_date'] ?? '';
    $selected_staff_type = $_GET['staff_type'] ?? 'professional';

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error_message = "មានបញ្ហាក្នុងការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ផ្ទាំងគ្រប់គ្រងវត្តមាន</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&display=swap" rel="stylesheet">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <style>
    :root {
        --primary-color: #05165e; --primary-color-dark: #031044; --secondary-color: #6c757d;
        --info-color: #0dcaf0; --success-color: #198754; --danger-color: #dc3545; --warning-color: #ffc107;
        --light-bg: #f8f9fa; --border-color: #dee2e6; --text-color: #212529; --text-muted: #6c757d;
        --highlight-color: #fff3cd; --border-radius: 0.5rem; --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        --box-shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    * { box-sizing: border-box; }
    body { font-family: 'Kantumruy Pro', sans-serif; background-color: var(--light-bg); margin: 0; color: var(--text-color); line-height: 1.6; }
    h1, h5 { font-weight: 700; }
    .container { max-width: 1600px; margin: 2rem auto; padding: 0 1.5rem; }
    .main-header { padding: 1.5rem 2rem; margin-bottom: 2rem; background: linear-gradient(135deg, var(--primary-color-dark), var(--primary-color)); color: white; border-radius: var(--border-radius); box-shadow: var(--box-shadow); display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem; }
    .main-header .title { display: flex; align-items: center; gap: 1rem; font-size: 1.6rem; margin: 0; }
    .main-header .user-info { display: flex; align-items: center; gap: 1rem; }
    .btn { display: inline-block; padding: 0.6rem 1.2rem; font-size: 1rem; font-weight: 500; text-align: center; text-decoration: none; vertical-align: middle; cursor: pointer; border: 1px solid transparent; border-radius: var(--border-radius); transition: all 0.2s ease-in-out; }
    .btn-primary { color: #fff; background-color: var(--primary-color); border-color: var(--primary-color); }
    .btn-primary:hover { background-color: var(--primary-color-dark); border-color: var(--primary-color-dark); transform: translateY(-2px); box-shadow: var(--box-shadow-sm); }
    .btn-outline-light { color: #f8f9fa; border-color: rgba(255, 255, 255, 0.5); }
    .btn-outline-light:hover { color: var(--primary-color); background-color: #f8f9fa; }
    .btn-outline-secondary { color: var(--secondary-color); border-color: var(--border-color); background-color: transparent; }
    .btn-outline-secondary:hover { color: #fff; background-color: var(--secondary-color); }
    .btn-danger { color: #fff; background-color: var(--danger-color); border-color: var(--danger-color); }
    .btn-danger:hover { background-color: #bb2d3b; border-color: #b02a37; }
    .btn-warning { color: #000; background-color: var(--warning-color); border-color: var(--warning-color); }
    .btn-warning:hover { color: #000; background-color: #ffca2c; border-color: #ffc720; }
    .btn i { margin-right: 0.5rem; }
    .btn-group { display: flex; border-radius: var(--border-radius); overflow: hidden; box-shadow: var(--box-shadow-sm); }
    .btn-group .btn { border-radius: 0; background-color: #fff; border: 1px solid var(--border-color); color: var(--text-muted); padding: 0.5rem 1rem; }
    .btn-group .btn:not(:last-child) { border-right: none; }
    .btn-group .btn:hover { background-color: #e9ecef; }
    .btn-group .btn.active { background-color: var(--primary-color); border-color: var(--primary-color); color: #fff; box-shadow: inset 0 3px 5px rgba(0,0,0,0.1); }
    .card { border: none; box-shadow: var(--box-shadow); border-radius: var(--border-radius); margin-bottom: 2rem; background-color: #fff; overflow: hidden; }
    .card-header { padding: 1.25rem 1.5rem; background-color: #fff; border-bottom: 1px solid var(--border-color); }
    .card-footer { padding: 1rem 1.5rem; background-color: var(--light-bg); border-top: 1px solid var(--border-color); }
    .card-header h5 { margin: 0; font-weight: 600; font-size: 1.2rem; }
    .card-header i { margin-right: 0.75rem; color: var(--primary-color); }
    .card-body { padding: 1.5rem; }
    .card-body.p-0 { padding: 0; }
    .card-header-flex { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; }
    .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
    .form-control, .form-select { display: block; width: 100%; padding: 0.6rem 1.2rem; font-size: 1rem; line-height: 1.6; color: var(--text-color); background-color: #fff; border: 1px solid var(--border-color); border-radius: var(--border-radius); transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out; }
    .form-control:focus, .form-select:focus { border-color: var(--primary-color); outline: 0; box-shadow: 0 0 0 0.25rem rgba(5, 22, 94, 0.25); }
    .form-select { appearance: none; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%233a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right 0.75rem center; background-size: 16px 12px; }
    .table-container { overflow-x: auto; max-height: 70vh; overflow-y: auto; }
    .table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
    .table th, .table td { padding: 1rem; vertical-align: middle; border-top: 1px solid var(--border-color); white-space: nowrap; }
    .table th { font-weight: 600; }
    .table thead.table-header-custom th { background-color: var(--primary-color); color: white; border-color: var(--primary-color-dark); text-align: left; position: sticky; top: 0; z-index: 1; }
    .table tbody tr:nth-of-type(odd) { background-color: #fff; }
    .table tbody tr:nth-of-type(even) { background-color: var(--light-bg); }
    .table tbody tr:hover { background-color: #e9ecef; }
    .table tbody tr.row-highlight { background-color: var(--highlight-color) !important; font-weight: bold; }
    .table td.editable-note, .table td:nth-child(8) { white-space: normal; min-width: 180px; }
    .text-center { text-align: center; }
    #date-filter-form { margin: 0; }
    .date-filter-select { background-color: var(--primary-color); color: white; border: 1px solid rgba(255, 255, 255, 0.4); border-radius: var(--border-radius); padding: 0.3rem 2rem 0.3rem 0.6rem; font-size: 0.9em; cursor: pointer; -webkit-appearance: none; appearance: none; background-repeat: no-repeat; background-position: right 8px center; background-size: 12px 10px; background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e"); transition: border-color 0.15s ease-in-out; }
    .date-filter-select:hover { border-color: rgba(255, 255, 255, 0.8); }
    .date-filter-select option { background-color: #fff; color: var(--text-color); }
    .badge { display: inline-block; padding: 0.4em 0.8em; font-size: .85em; font-weight: 600; line-height: 1; color: var(--text-color); text-align: center; border-radius: var(--border-radius); }
    .badge-info { background-color: #cfe2ff; color: #084298; }
    .badge-success { background-color: #d1e7dd; color: #0f5132;}
    .badge-group { display: flex; align-items: center; flex-wrap: wrap; gap: 0.75rem;}
    .pagination-wrapper { display: flex; justify-content: center; padding-top: 1rem; }
    .pagination { display: flex; padding-left: 0; list-style: none; margin: 0; }
    .page-item .page-link { position: relative; display: block; padding: 0.6rem 0.9rem; color: var(--primary-color); text-decoration: none; background-color: #fff; border: 1px solid var(--border-color); transition: all .2s ease-in-out; margin-left: -1px; }
    .page-item:first-child .page-link { border-top-left-radius: var(--border-radius); border-bottom-left-radius: var(--border-radius); }
    .page-item:last-child .page-link { border-top-right-radius: var(--border-radius); border-bottom-right-radius: var(--border-radius); }
    .page-item:hover .page-link { z-index: 2; color: var(--primary-color-dark); background-color: #e9ecef; }
    .page-item.active .page-link { z-index: 3; color: #fff; background-color: var(--primary-color); border-color: var(--primary-color); }
    .page-item.disabled .page-link { color: var(--text-muted); pointer-events: none; background-color: #fff; border-color: var(--border-color); }
    #table-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.8); z-index: 10; display: flex; align-items: center; justify-content: center; transition: opacity 0.2s ease-in-out; opacity: 0; visibility: hidden; }
    #table-overlay.show { opacity: 1; visibility: visible; }
    .loader { width: 50px; height: 50px; border-radius: 50%; border: 5px solid #e0e0e0; border-top-color: var(--primary-color); animation: spin 1s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .card-body.p-0 { position: relative; }
    .status-icon { font-size: 1.1em; vertical-align: middle; margin-right: 8px; }
    .status-success { color: var(--success-color); } .status-failed { color: var(--danger-color); } .status-good { color: #0d6efd; } .status-late { color: var(--danger-color); } .status-unknown { color: var(--secondary-color); }
    .editable-note { cursor: pointer; word-wrap: break-word; transition: background-color 0.2s ease; }
    .editable-note:hover, .editable-note:focus-within { background-color: #e9ecef; }
    .editable-note textarea { border: 1px solid #ced4da; width: 100%; height: auto; min-height: 60px; resize: vertical; padding: 8px; font-family: inherit; font-size: inherit; border-radius: calc(var(--border-radius) - 2px); }
    .editable-note a { word-break: break-all; }
    .late-summary-list { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem; }
    .late-summary-list li { background-color: #fff7f7; padding: 0.75rem 1.25rem; border-radius: var(--border-radius); border-left: 5px solid var(--danger-color); display: flex; align-items: center; font-weight: 500; color: #58151c; }
    .late-summary-list .icon-late { color: var(--danger-color); margin-right: 0.75rem; font-size: 1.2em; }
    .text-muted.p-4 { padding: 1rem; }
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.6); z-index: 1000; display: none; align-items: center; justify-content: center; }
    .modal-overlay.show { display: flex; }
    .modal-content { background-color: #fff; border-radius: var(--border-radius); box-shadow: 0 5px 15px rgba(0,0,0,0.3); width: 90%; max-width: 700px; display: flex; flex-direction: column; }
    .modal-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
    .modal-header h5 { margin: 0; font-size: 1.25rem; }
    .modal-close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
    .modal-body { padding: 1.5rem; max-height: 70vh; overflow-y: auto; }
    .modal-body .table { margin-bottom: 0; }
    .modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; background-color: var(--light-bg); }
    .page-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-bottom: 2rem; }
    .action-buttons { display: flex; gap: 0.5rem; align-items: center; }
    .btn-action { padding: 0.3rem 0.6rem; font-size: 0.9rem; line-height: 1.2; border-radius: 0.3rem; }
    .btn-action i { margin: 0; }
    .btn-edit { background-color: #e9ecef; color: var(--primary-color); border: 1px solid #ced4da; }
    .btn-edit:hover { background-color: var(--primary-color); color: #fff; }
    .btn-duplicate { background-color: #e9ecef; color: var(--secondary-color); border: 1px solid #ced4da; }
    .btn-duplicate:hover { background-color: var(--secondary-color); color: #fff; }
    .btn-delete { background-color: #f8d7da; color: var(--danger-color); border: 1px solid #f5c2c7; }
    .btn-delete:hover { background-color: var(--danger-color); color: #fff; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .action-column { display: none; }
    th.action-column-header { display: table-cell; width: 60px; text-align: center; transition: width 0.3s ease-in-out; }
    .btn-header-toggle { background: none; border: none; color: white; padding: 0; font-weight: 600; font-family: inherit; font-size: inherit; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
    .btn-header-toggle:hover { opacity: 0.8; }
    .btn-header-toggle .toggle-text, .btn-header-toggle .icon-hide { display: none; }
    .btn-header-toggle .icon-show { display: inline-block; margin-right: 0; }
    body.show-actions .action-column { display: table-cell; text-align: center; }
    body.show-actions th.action-column-header { width: 140px; }
    body.show-actions .btn-header-toggle .toggle-text, body.show-actions .btn-header-toggle .icon-hide { display: inline-block; }
    body.show-actions .btn-header-toggle .icon-show { display: none; }
    .card-header-title-group { display: flex; align-items: center; gap: 0.75rem; }
    #fullscreen-btn { background-color: transparent; border: none; font-size: 1.1rem; color: var(--text-muted); cursor: pointer; padding: 0.2rem 0.5rem; transition: color 0.2s ease; }
    #fullscreen-btn:hover { color: var(--primary-color); }
/* NEW Corrected CSS for fullscreen scrolling */
#attendance-card:fullscreen {
    display: flex;
    flex-direction: column; /* Stacks header, body, footer vertically */
    width: 100vw;
    height: 100vh;
    margin: 0;
    border-radius: 0;
    box-shadow: none;
    background-color: #fff; /* Ensure white background */
}
#attendance-card:fullscreen .card-header,
#attendance-card:fullscreen .card-footer {
    flex-shrink: 0; /* Header and footer will not shrink */
}
#attendance-card:fullscreen .card-body {
    flex-grow: 1; /* Body takes all available space */
    padding: 0; /* Remove padding for edge-to-edge table */
    display: flex; /* Needed for child element to grow */
    overflow: hidden; /* Prevent body itself from scrolling */
}
#attendance-card:fullscreen .table-container {
    flex-grow: 1; /* Table container fills the body */
    overflow-y: auto; /* THIS IS THE FIX: Enable vertical scrolling */
    max-height: 100%; /* Ensure it does not overflow its parent */
}
  </style>
</head>
<body>

<div class="container">
    <header class="main-header">
        <h1 class="title" style="display: flex; align-items: center;"><img src="https://i.ibb.co/0RQjvn4M/Logo-Van-Van-2.png" alt="" width="200px" style="margin-right: 1rem;"></h1>
        <span style=" text-align: center; font-size: 28px; font-weight: bold;">ផ្ទាំងគ្រប់គ្រងវត្តមាន</span>
        <div class="user-info">
            <span><i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
            <a href="logout.php" class="btn btn-outline-light"><i class="fas fa-sign-out-alt"></i> ចាកចេញ</a>
        </div>
    </header>
    <div class="page-actions">
        <button id="auto-delete-btn" class="btn btn-warning"><i class="fas fa-magic"></i> សម្អាតកំណត់ត្រាលើស</button>
        <a href="draft.php" class="btn btn-outline-secondary"><i class="fas fa-trash-restore"></i> ទិន្នន័យបានលុប</a>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?= displayValue($error_message) ?></div>
    <?php else: ?>
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-chart-pie"></i> សេចក្តីសង្ខេបស្ថានភាព</h5></div>
            <div class="card-body"><div id="chartContainer" style="height: 300px; width: 100%;"></div></div>
        </div>
        <div class="card" id="late-summary-card" style="<?= empty($selected_date) ? 'display:none;' : '' ?>">
            <div class="card-header"><h5><i class="fas fa-user-clock" style="color: var(--danger-color);"></i> បុគ្គលិកដែលមកយឺត (សម្រាប់ថ្ងៃ <?= !empty($selected_date) ? date('d/m/Y', strtotime($selected_date)) : '' ?>)</h5></div>
            <div class="card-body" id="late-summary-container"><?= generateLateSummaryHTML($late_summary_data) ?></div>
        </div>
        <div class="card" id="attendance-card">
            <div class="card-header card-header-flex">
                <div class="card-header-title-group">
                    <h5><i class="fas fa-list-ul"></i> លទ្ធផលស្កេនវត្តមាន</h5>
                    <button id="fullscreen-btn" title="ពេញអេក្រង់ / ចេញពីពេញអេក្រង់">
                        <i class="fas fa-expand"></i>
                    </button>
                </div>
                <div class="btn-group" id="staff-type-group">
                    <?php $query_params_prof = $_GET; $query_params_prof['staff_type'] = 'professional'; unset($query_params_prof['page']);
                          $query_params_worker = $_GET; $query_params_worker['staff_type'] = 'worker'; unset($query_params_worker['page']); ?>
                    <a href="?<?= http_build_query($query_params_prof) ?>" data-type="professional" class="btn <?= ($selected_staff_type === 'professional') ? 'active' : '' ?>">បុគ្គលិកជំនាញ</a>
                    <a href="?<?= http_build_query($query_params_worker) ?>" data-type="worker" class="btn <?= ($selected_staff_type === 'worker') ? 'active' : '' ?>">បុគ្គលិកកម្មករ</a>
                </div>
                <div class="badge-group">
                   <span class="badge badge-info" id="page-info">ទំព័រ <?= $current_page ?> / <?= $total_pages > 0 ? $total_pages : 1 ?></span>
                   <span class="badge badge-success" id="record-count">សរុប <?= $total_records ?> កំណត់ត្រា</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div id="table-overlay"><div class="loader"></div></div>
                <div class="table-container">
                    <table class="table">
                        <thead class="table-header-custom">
                            <tr>
                                <th>អត្តលេខ</th><th>ឈ្មោះ</th><th>សាខា</th><th>សកម្មភាព</th>  
                                <th>
                                    <div style="display: flex; flex-direction: column; align-items: center;">
                                        <span style="margin-bottom: 4px;">កាលបរិច្ឆេទ</span>
                                        <form method="GET" id="date-filter-form" style="width: 100%;">
                                            <select class="date-filter-select" name="selected_date" style="font-family: 'Kantumruy Pro', Arial, sans-serif; font-size: 1em; width: 100%;">
                                                <option value="">ទាំងអស់</option>
                                                <?php foreach ($available_dates as $date): ?>
                                                    <option value="<?= htmlspecialchars($date) ?>" <?= ($date === $selected_date) ? 'selected' : '' ?> style="padding: 8px 0;"><?= date('d/m/Y', strtotime($date)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </div>
                                </th>
                                <th>ពេលវេលា</th><th>ស្ថានភាព</th><th>មូលហេតុ</th><th>ចំណាំ</th>
                                <th class="action-column-header">
                                    <button id="toggle-actions-btn" class="btn-header-toggle" title="បង្ហាញ/លាក់សកម្មភាព">
                                        <span class="toggle-text">សកម្មភាព</span>
                                        <i class="fas fa-eye-slash icon-hide"></i>
                                        <i class="fas fa-eye icon-show"></i>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="log-table-body"><?= generateTableRowsHTML($logs) ?></tbody>
                    </table>
                </div>
                <div class="card-footer pagination-wrapper" id="pagination-container"><?= generatePaginationHTML($current_page, $total_pages, $_GET) ?></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="modal-overlay" id="preview-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5><i class="fas fa-eye" style="color:var(--primary-color)"></i> មើលជាមុននូវកំណត់ត្រាដែលនឹងត្រូវសម្អាត</h5>
            <button class="modal-close-btn" data-close-preview>&times;</button>
        </div>
        <div class="modal-body" id="preview-modal-body"></div>
        <div class="modal-footer">
            <button class="btn btn-outline-secondary" data-close-preview>បោះបង់</button>
            <button class="btn btn-danger" id="confirm-delete-btn"><i class="fas fa-check"></i> បញ្ជាក់ការសម្អាត</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="alert-modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h5 id="alert-modal-title"><i class="fas fa-info-circle"></i> ការជូនដំណឹង</h5>
            <button class="modal-close-btn" data-close-alert>&times;</button>
        </div>
        <div class="modal-body" id="alert-modal-body" style="text-align: center; font-size: 1.1rem; padding: 2rem 1.5rem;"></div>
        <div class="modal-footer" style="justify-content: center;"><button class="btn btn-primary" data-close-alert>យល់ព្រម</button></div>
    </div>
</div>

<div class="modal-overlay" id="edit-log-modal">
    <div class="modal-content">
        <form id="edit-log-form">
            <div class="modal-header">
                <h5><i class="fas fa-pencil-alt" style="color:var(--primary-color)"></i> កែសម្រួលកំណត់ត្រា</h5>
                <button type="button" class="modal-close-btn" data-close-edit>&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="log_id" id="edit-log-id">
                <input type="hidden" name="action" value="update_log">
                <div class="form-grid">
                    <div>
                        <label for="edit-user-id" class="form-label">អត្តលេខ</label>
                        <input type="text" class="form-control" id="edit-user-id" name="user_id">
                    </div>
                    <div>
                        <label for="edit-username" class="form-label">ឈ្មោះ</label>
                        <input type="text" class="form-control" id="edit-username" name="username">
                    </div>
                     <div>
                        <label for="edit-branch" class="form-label">សាខា</label>
                        <input type="text" class="form-control" id="edit-branch" name="branch">
                    </div>
                     <div>
                        <label for="edit-action" class="form-label">សកម្មភាព</label>
                        <select class="form-select" id="edit-action" name="action_type">
                            <option value="Check-In">Check-In</option>
                            <option value="Check-Out">Check-Out</option>
                        </select>
                    </div>
                     <div>
                        <label for="edit-status" class="form-label">ស្ថានភាព</label>
                        <select class="form-select" id="edit-status" name="status">
                            <option value="Good">Good</option>
                            <option value="Late">Late</option>
                            <option value="Success">Success</option>
                            <option value="Failed">Failed</option>
                        </select>
                    </div>
                    <div>
                        <label for="edit-timestamp" class="form-label">ពេលវេលា</label>
                        <input type="datetime-local" class="form-control" id="edit-timestamp" name="timestamp">
                    </div>
                </div>
                <div style="margin-top: 1rem;">
                    <label for="edit-early-reason" class="form-label">មូលហេតុ</label>
                    <textarea class="form-control" id="edit-early-reason" name="early_reason" rows="2"></textarea>
                </div>
                <div style="margin-top: 1rem;">
                    <label for="edit-noted" class="form-label">ចំណាំ</label>
                    <textarea class="form-control" id="edit-noted" name="noted" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-close-edit>បោះបង់</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> រក្សាទុកការផ្លាស់ប្តូរ</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.canvasjs.com/canvasjs.min.js"></script>

<script>
$(document).ready(function() {

    const scriptUrl = '<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>';
    
    // ===================================================================
    // Custom Alert Modal Functionality
    // ===================================================================
    const $alertModal = $('#alert-modal');
    function showAlert(message, title = 'ការជូនដំណឹង', type = 'info') {
        let icon = '<i class="fas fa-info-circle"></i>';
        if (type === 'success') icon = '<i class="fas fa-check-circle" style="color:var(--success-color);"></i>';
        if (type === 'error') icon = '<i class="fas fa-times-circle" style="color:var(--danger-color);"></i>';
        if (type === 'warning') icon = '<i class="fas fa-exclamation-triangle" style="color:var(--warning-color);"></i>';
        
        $alertModal.find('#alert-modal-title').html(`${icon} ${title}`);
        $alertModal.find('#alert-modal-body').html(message);
        $alertModal.addClass('show');
    }
    $alertModal.on('click', '[data-close-alert], .modal-overlay', function(e) {
        if (e.target !== this) return;
        $alertModal.removeClass('show');
    });

    // ===================================================================
    // Chart Rendering functionality
    // ===================================================================
    let chart;
    function renderChart(goodCount, lateCount) {
        if (chart) chart.destroy();
        CanvasJS.addCultureInfo("km", { days: ["អាទិត្យ", "ចន្ទ", "អង្គារ", "ពុធ", "ព្រហស្បតិ៍", "សុក្រ", "សៅរ៍"] });
        chart = new CanvasJS.Chart("chartContainer", {
            animationEnabled: true, theme: "light2", culture: "km",
            title: { text: "សរុបស្ថានភាព៖ ល្អ និង យឺត", fontFamily: "'Kantumruy Pro', sans-serif" },
            axisY: { title: "ចំនួន", titleFontFamily: "'Kantumruy Pro', sans-serif", labelFontFamily: "'Kantumruy Pro', sans-serif", gridThickness: 0.5 },
            axisX: { labelFontFamily: "'Kantumruy Pro', sans-serif" },
            dataPointWidth: 60,
            data: [{
                type: "column", showInLegend: true, legendText: "{label}", indexLabelFontFamily: "'Kantumruy Pro', sans-serif",
                indexLabelFontSize: 16, indexLabel: "{y}", indexLabelPlacement: "inside", indexLabelFontColor: "white",
                toolTipContent: "<b>{label}:</b> {y}",
                dataPoints: [ { y: goodCount, label: "ល្អ (Good)", color: "#05165e" }, { y: lateCount, label: "យឺត (Late)", color: "#dc3545" } ]
            }]
        });
        chart.render();
    }
    const initialChartData = <?= $chart_data_json ?>;
    renderChart(initialChartData.Good || 0, initialChartData.Late || 0);

    // ===================================================================
    // Editable Note functionality
    // ===================================================================
    function linkify(text) {
        if (!text) return '';
        const urlRegex = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])|(\bwww\.[\-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
        return text.replace(urlRegex, url => `<a href="${!url.startsWith('http') ? 'https://' + url : url}" target="_blank" rel="noopener noreferrer">${url}</a>`);
    }

    function applyLinkifyToAllNotes() {
        $('#log-table-body .editable-note').each(function() { $(this).html(linkify($(this).text().trim())); });
    }
    applyLinkifyToAllNotes();

    $('#log-table-body').on('click', '.editable-note', function(e) {
        if ($(this).find('textarea').length || $(e.target).is('a')) return;
        const cell = $(this); const originalText = cell.text().trim();
        const input = $('<textarea>').val(originalText);
        cell.html(input); input.focus();
        const saveChanges = () => {
            const newValue = input.val().trim(); const logId = cell.closest('tr').data('id');
            if (newValue === originalText) { cell.html(linkify(originalText)); return; }
            $.ajax({
                url: scriptUrl, type: 'POST', data: { action: 'update_note', id: logId, noted: newValue }, dataType: 'json',
                success: response => { cell.html(linkify(response.status === 'success' ? newValue : originalText)); },
                error: () => { cell.html(linkify(originalText)); showAlert('An error occurred.', 'Error', 'error'); }
            });
        };
        input.on('blur', saveChanges).on('keypress', e => { if (e.which === 13 && !e.shiftKey) { e.preventDefault(); input.blur(); } });
    });

    $('#log-table-body').on('click', 'tr[data-id]', function(event) {
        if ($(event.target).closest('.editable-note, .action-buttons').length) return;
        $(this).toggleClass('row-highlight');
    });

    // ===================================================================
    // AJAX for Filtering and Pagination
    // ===================================================================
    const $tableOverlay = $('#table-overlay');
    function fetchLogData(params = {}) {
        const currentParams = {
            selected_date: $('#date-filter-form select[name="selected_date"]').val(),
            staff_type: $('#staff-type-group .btn.active').data('type') || 'professional',
            page: params.page || new URL(window.location.href).searchParams.get('page') || 1,
            action: 'filter_logs'
        };
        const queryString = $.param(currentParams, true);
        const newUrl = scriptUrl + '?' + queryString.replace(/&?action=filter_logs/, '');
        $tableOverlay.addClass('show');
        $.ajax({
            url: scriptUrl, type: 'GET', data: queryString, dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    $('#log-table-body').html(response.table_html);
                    $('#pagination-container').html(response.pagination_html);
                    $('#page-info').text(`ទំព័រ ${response.current_page} / ${response.total_pages > 0 ? response.total_pages : 1}`);
                    $('#record-count').text(`សរុប ${response.total_records} កំណត់ត្រា`);
                    applyLinkifyToAllNotes();
                    history.pushState({path: newUrl}, '', newUrl);
                    if(response.chart_data) { renderChart(response.chart_data.Good || 0, response.chart_data.Late || 0); }
                    const $summaryCard = $('#late-summary-card');
                    if (currentParams.selected_date) {
                        $('#late-summary-container').html(response.late_summary_html);
                        const dateObj = new Date(currentParams.selected_date + 'T00:00:00');
                        const formattedDate = ('0' + dateObj.getDate()).slice(-2) + '/' + ('0' + (dateObj.getMonth() + 1)).slice(-2) + '/' + dateObj.getFullYear();
                        $summaryCard.find('h5').html(`<i class="fas fa-user-clock" style="color: var(--danger-color);"></i> បុគ្គលិកដែលមកយឺត (សម្រាប់ថ្ងៃ ${formattedDate})`);
                        $summaryCard.show();
                    } else { $summaryCard.hide(); }
                } else { showAlert('Error loading data: ' + response.message, 'Error', 'error'); }
            },
            error: xhr => { showAlert('An error occurred. Check console.', 'Error', 'error'); console.error('AJAX Error:', xhr.responseText); },
            complete: () => { $tableOverlay.removeClass('show'); }
        });
    }

    $('#date-filter-form select').on('change', () => fetchLogData({ page: 1 }));
    $('#staff-type-group').on('click', '.btn', function(e) {
        e.preventDefault(); if ($(this).hasClass('active')) return;
        $('#staff-type-group .btn').removeClass('active'); $(this).addClass('active');
        fetchLogData({ page: 1 });
    });
    $('#pagination-container').on('click', 'a.page-link', function(e) {
        e.preventDefault();
        fetchLogData({ page: new URL($(this).attr('href'), window.location.origin).searchParams.get('page') });
    });
    
    // ===================================================================
    // Auto-Delete with Preview Modal Functionality
    // ===================================================================
    const $previewModal = $('#preview-modal');
    $('#auto-delete-btn').on('click', function() {
        const $button = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> កំពុង​ពិនិត្យ...');
        $.ajax({
            url: scriptUrl, type: 'POST', data: { action: 'preview_excess_logs' }, dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    if (response.data.length === 0) { showAlert('មិនមានកំណត់ត្រាលើសដែលត្រូវលុបទេ។ ប្រព័ន្ធស្អាតហើយ។', 'ស្ថានភាពល្អ', 'success'); return; }
                    let tableHtml = '<p>បញ្ជីបុគ្គលិកដែលមានការស្កេនលើសពី ៤ ដង។ កំណត់ត្រាដែលលើសនឹងត្រូវបានផ្លាស់ទី។</p>';
                    tableHtml += '<table class="table"><thead><tr><th>ឈ្មោះ</th><th>កាលបរិច្ឆេទ</th><th>ចំនួនស្កេន</th><th>នឹងត្រូវលុប</th></tr></thead><tbody>';
                    response.data.forEach(item => {
                        const dateObj = new Date(item.scan_date + 'T00:00:00');
                        const formattedDate = ('0' + dateObj.getDate()).slice(-2) + '/' + ('0' + (dateObj.getMonth() + 1)).slice(-2) + '/' + dateObj.getFullYear();
                        tableHtml += `<tr><td>${item.username}</td><td>${formattedDate}</td><td>${item.total_scans}</td><td style="color:var(--danger-color); font-weight:bold;">${item.total_scans - 4}</td></tr>`;
                    });
                    tableHtml += '</tbody></table>';
                    $('#preview-modal-body').html(tableHtml);
                    $previewModal.addClass('show');
                } else { showAlert('Error: ' + response.message, 'Error', 'error'); }
            },
            error: () => showAlert('An error occurred while fetching preview data.', 'Error', 'error'),
            complete: () => $button.prop('disabled', false).html('<i class="fas fa-magic"></i> សម្អាតកំណត់ត្រាលើស')
        });
    });

    $('#confirm-delete-btn').on('click', function() {
        const $button = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> កំពុងសម្អាត...');
        $.ajax({
            url: scriptUrl, type: 'POST', data: { action: 'auto_delete_excess' }, dataType: 'json',
            success: function(response) {
                $previewModal.removeClass('show');
                if (response.status === 'success') {
                    showAlert(response.message, 'ជោគជ័យ', 'success');
                    fetchLogData();
                } else { showAlert('Error: ' + response.message, 'Error', 'error'); }
            },
            error: () => { $previewModal.removeClass('show'); showAlert('An error occurred during cleanup.', 'Error', 'error'); },
            complete: () => $button.prop('disabled', false).html('<i class="fas fa-check"></i> បញ្ជាក់ការសម្អាត')
        });
    });
    $previewModal.on('click', '[data-close-preview], .modal-overlay', function(e) { if (e.target !== this) return; $previewModal.removeClass('show'); });


    // ===================================================================
    // Edit, Duplicate, Delete Functionality
    // ===================================================================
    const $editModal = $('#edit-log-modal');

    $('#log-table-body').on('click', '.btn-edit', function() {
        const logId = $(this).data('id');
        $.ajax({
            url: scriptUrl, type: 'POST', data: { action: 'get_log_details', id: logId }, dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const log = response.data;
                    $('#edit-log-id').val(log.id);
                    $('#edit-user-id').val(log.user_id);
                    $('#edit-username').val(log.username);
                    $('#edit-branch').val(log.branch);
                    $('#edit-action').val(log.action);
                    $('#edit-status').val(log.status);
                    
                    const timestamp = new Date(log.timestamp.replace(' ', 'T'));
                    timestamp.setMinutes(timestamp.getMinutes() - timestamp.getTimezoneOffset());
                    const formattedTimestamp = timestamp.toISOString().slice(0, 16);
                    $('#edit-timestamp').val(formattedTimestamp);
                    
                    $('#edit-early-reason').val(log.early_reason);
                    $('#edit-noted').val(log.noted);
                    $editModal.addClass('show');
                } else {
                    showAlert(response.message, 'Error', 'error');
                }
            },
            error: () => showAlert('Could not fetch log details.', 'Error', 'error')
        });
    });

    $('#edit-log-form').on('submit', function(e) {
        e.preventDefault();
        const $button = $(this).find('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> កំពុងរក្សាទុក...');
        const formData = $(this).serialize();
        $.ajax({
            url: scriptUrl, type: 'POST', data: formData, dataType: 'json',
            success: function(response) {
                $editModal.removeClass('show');
                if (response.status === 'success') {
                    showAlert(response.message, 'ជោគជ័យ', 'success');
                    fetchLogData();
                } else {
                    showAlert(response.message, 'Error', 'error');
                }
            },
            error: () => showAlert('An error occurred while updating.', 'Error', 'error'),
            complete: () => $button.prop('disabled', false).html('<i class="fas fa-save"></i> រក្សាទុកការផ្លាស់ប្តូរ')
        });
    });

    $('#log-table-body').on('click', '.btn-duplicate', function() {
        const logId = $(this).data('id');
        if (confirm('តើអ្នកពិតជាចង់ចម្លងកំណត់ត្រានេះមែនទេ?')) {
            $.ajax({
                url: scriptUrl, type: 'POST', data: { action: 'duplicate_log', id: logId }, dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        showAlert(response.message, 'ជោគជ័យ', 'success');
                        fetchLogData();
                    } else {
                        showAlert(response.message, 'Error', 'error');
                    }
                },
                error: () => showAlert('An error occurred during duplication.', 'Error', 'error')
            });
        }
    });

    $('#log-table-body').on('click', '.btn-delete', function() {
        const logId = $(this).data('id');
        if (confirm('តើអ្នកពិតជាចង់លុបកំណត់ត្រានេះមែនទេ? សកម្មភាពនេះនឹងផ្លាស់ទីវាទៅក្នុងធុងសំរាម។')) {
            $.ajax({
                url: scriptUrl, type: 'POST', data: { action: 'delete_log', id: logId }, dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        showAlert(response.message, 'ជោគជ័យ', 'success');
                        fetchLogData();
                    } else {
                        showAlert(response.message, 'Error', 'error');
                    }
                },
                error: () => showAlert('An error occurred during deletion.', 'Error', 'error')
            });
        }
    });

    $editModal.on('click', '[data-close-edit], .modal-overlay', function(e) { if (e.target !== this) return; $editModal.removeClass('show'); });

    $('.table-header-custom').on('click', '#toggle-actions-btn', function() {
        $('body').toggleClass('show-actions');
    });

    // ===================================================================
    // Fullscreen functionality
    // ===================================================================
    const attendanceCard = document.getElementById('attendance-card');
    const fullscreenBtn = document.getElementById('fullscreen-btn');

    if (attendanceCard && fullscreenBtn) {
        fullscreenBtn.addEventListener('click', () => {
            if (!document.fullscreenElement) {
                attendanceCard.requestFullscreen().catch(err => {
                    alert(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`);
                });
            } else {
                document.exitFullscreen();
            }
        });

        document.addEventListener('fullscreenchange', () => {
            const icon = fullscreenBtn.querySelector('i');
            if (document.fullscreenElement === attendanceCard) {
                icon.classList.remove('fa-expand');
                icon.classList.add('fa-compress');
            } else {
                icon.classList.remove('fa-compress');
                icon.classList.add('fa-expand');
            }
        });
    }
});
</script>

</body>
</html>