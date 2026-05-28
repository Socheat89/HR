<?php
// Start session (ensure it's started at the very top)
session_start();

// Set UTF-8 encoding
header('Content-Type: text/html; charset=UTF-8');

// --- Includes (Ensure these paths are correct) ---
include 'includes/auth.php';

// Provide a safe fallback for isLoggedIn() in case includes/auth.php doesn't define it.
// This considers a user logged in when a session user_id exists; adjust as needed to match your auth implementation.
if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

if (!isLoggedIn()) {
    $_SESSION['error'] = 'សូមចូលគណនីសិន!';
    header("Location: index.php");
    exit();
}

require_once 'includes/telegram.php';
// Corrected database inclusion: Include once and assign the returned connection.
$conn = include 'includes/db.php'; 

// Check if the database connection was successful
if ($conn === false || !$conn instanceof PDO) {
    // Log the error for the admin, but show a generic message to the user.
    error_log("Failed to get a valid PDO connection from 'includes/db.php'.");
    exit('មានកំហុសក្នុងការតភ្ជាប់មូលដ្ឋានទិន្នន័យ សូមទាក់ទងអ្នកគ្រប់គ្រងប្រព័ន្ធ');
}

// Set time zone
date_default_timezone_set('Asia/Phnom_Penh');

// Helper: return a thumbnail-serving URL for an image path or URL
function thumb_url($imgPath, $w = 80, $h = 80) {
    $default_avatar = 'https://via.placeholder.com/40';
    if (empty($imgPath)) return $default_avatar;
    // if it's already an absolute URL, forward to thumb.php which can handle remote sources
    $q = urlencode($imgPath);
    return "thumb.php?src={$q}&w={$w}&h={$h}";
}

// Determine view mode
$view = isset($_GET['view']) ? filter_var($_GET['view'], FILTER_SANITIZE_STRING) : 'dashboard';

// +++ NEW: Check for specific payroll management permission +++
// This must be defined in your auth.php as created in Step 1
// And the permission 'manage_payroll_data' must be created in your permissions UI as in Step 2
$canManagePayrollInfo = false;
if (is_callable('hasPermission')) {
    // use call_user_func to avoid static analyzers flagging a direct call to an undefined function
    $canManagePayrollInfo = (bool) call_user_func('hasPermission', 'manage_payroll_data', $conn);
}
// +++ END NEW +++

// +++ NEW: Check current user's department for salary visibility +++
$current_user_dept = null;
$is_current_user_allowed = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT department FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user_dept = $stmt->fetchColumn();
    $allowed_departments = ['admin', 'accounting', 'hr'];
    $is_current_user_allowed = in_array(strtolower($current_user_dept ?? ''), $allowed_departments);
}
// +++ END NEW +++

// Provide a fallback requirePermission() if it's not defined by includes/auth.php
// This ensures existing calls to requirePermission() do not produce "undefined function" errors.
// The function prefers to call hasPermission() when available, otherwise falls back to admin-only access.
if (!function_exists('requirePermission')) {
    function requirePermission($permission, $conn = null) {
        // If hasPermission is available and callable, use it via call_user_func to avoid direct undefined calls
        if (is_callable('hasPermission')) {
            try {
                if (!call_user_func('hasPermission', $permission, $conn)) {
                    $_SESSION['error'] = 'មិនមានសិទ្ធិគ្រប់គ្រាន់សម្រាប់ចូលមើលទំព័រនេះ។';
                    header('Location: dashboard.php');
                    exit();
                }
            } catch (Throwable $e) {
                // If the permission function exists but throws, log it and deny access safely.
                error_log('Permission check failed: ' . $e->getMessage());
                $_SESSION['error'] = 'មិនមានសិទ្ធិគ្រប់គ្រាន់សម្រាប់ចូលមើលទំព័រនេះ។';
                header('Location: dashboard.php');
                exit();
            }
            return true;
        }

        // Fallback behaviour: only allow admin role if permission system is not loaded
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $_SESSION['error'] = 'មិនមានសិទ្ធិគ្រប់គ្រាន់សម្រាប់ចូលមើលទំព័រនេះ។';
            header('Location: dashboard.php');
            exit();
        }
        return true;
    }
}


// --- General Functions for Employee Data ---
function buildTree(array &$elements, $parentId = null) {
    $branch = array();
    foreach ($elements as &$element) {
        if ($element['manager_id'] == $parentId) {
            $children = buildTree($elements, $element['id']);
            if ($children) {
                $element['children'] = $children;
            }
            $branch[] = $element;
        }
    }
    return $branch;
}

function displayEmployees($employees, $level = 0, $parentId = null) {
    // MODIFIED: We will use the global variable $canManagePayrollInfo
    global $canManagePayrollInfo;

    // MODIFIED: Define roles that can manage employees. You can add more roles here.
    $canManageEmployees = ['admin', 'administration','accounting'];

    foreach ($employees as $employee) {
        $arrow = $level > 0 ? '↳ ' : '';
        $default_avatar = 'https://via.placeholder.com/40';
        $hasChildren = !empty($employee['children']);
        $indentPx = $level * 16;

        echo '<tr class="employee-row text-lg"'
            . ' data-id="' . htmlspecialchars($employee['id']) . '"'
            . ' data-parent-id="' . htmlspecialchars($parentId ?? '') . '"'
            . ' data-level="' . (int)$level . '"'
            . ' data-name="' . htmlspecialchars($employee['full_name'] ?: $employee['username']) . '"'
            . ' data-email="' . htmlspecialchars($employee['email']) . '"'
            . ' data-position="' . htmlspecialchars($employee['position']) . '"'
            . ' data-department="' . htmlspecialchars($employee['department'] ?? '') . '"'
            . ' data-gender="' . htmlspecialchars($employee['gender'] ?? '') . '"'
            . ' data-employee-id="' . htmlspecialchars($employee['employee_id'] ?? '') . '"'
            . ' data-latin-name="' . htmlspecialchars($employee['latin_name'] ?? '') . '"'
            . ' data-start-date="' . htmlspecialchars($employee['start_date'] ?? '') . '"'
            . ' data-marital-status="' . htmlspecialchars($employee['marital_status'] ?? '') . '"'
            . ' data-number-of-children="' . htmlspecialchars($employee['number_of_children'] ?? '') . '"'
            . ' data-current-address="' . htmlspecialchars($employee['current_address'] ?? '') . '"'
            . ' data-has-children="' . ($hasChildren ? '1' : '0') . '"'
            . ' data-collapsed="0"'
            . '>';
      $imgSrc = thumb_url($employee['image_url'] ?? '', 48, 48);
      echo '<td class="py-3 px-4"><img src="' . htmlspecialchars($imgSrc) . '" alt="Avatar" loading="lazy" class="w-12 h-12 rounded-full object-cover"></td>';
        echo '<td class="py-3 px-4 font-semibold">'
                . '<div class="flex items-center">'
                . ($hasChildren
                    ? '<button type="button" class="toggle-children mr-2 text-accent-color" title="បិទ/បើកកូន"><i class="fas fa-caret-down"></i></button>'
                    : '<span class="mr-6"></span>')
                . '<span style="margin-left:' . (int)$indentPx . 'px">' . $arrow . htmlspecialchars($employee['full_name'] ?: $employee['username']) . '</span>'
                . '</div>'
            . '</td>';
        echo '<td class="py-3 px-4 text-text-secondary hidden md:table-cell">' . htmlspecialchars($employee['email']) . '</td>';
        echo '<td class="py-3 px-4 text-text-secondary hidden sm:table-cell">' . htmlspecialchars($employee['position']) . '</td>';
        echo '<td class="py-3 px-4 text-text-secondary hidden sm:table-cell">' . htmlspecialchars($employee['department'] ?? 'N/A') . '</td>';
        echo '<td class="py-3 px-4 text-center space-x-4">';
        echo '<a href="view_employee.php?id=' . htmlspecialchars($employee['id']) . '" class="btn-action-link" target="_blank">មើល</a>';
        
        // MODIFIED: Check if the user's role is in the defined array of managers.
        if (isset($_SESSION['role']) && in_array($_SESSION['role'], $canManageEmployees)) {
            $payrollDataAttributes = '';
            // +++ MODIFIED: Only add payroll data attributes if the user has permission +++
            if ($canManagePayrollInfo) {
                $payrollDataAttributes = ' data-base-salary="' . htmlspecialchars($employee['base_salary'] ?? '') . '"'
                                       . ' data-bank-name="' . htmlspecialchars($employee['bank_name'] ?? '') . '"'
                                       . ' data-bank-account-number="' . htmlspecialchars($employee['bank_account_number'] ?? '') . '"'
                                       . ' data-bank-qr-code="' . htmlspecialchars($employee['bank_qr_code_url'] ?? '') . '"'
                                       . ' data-nssf-id="' . htmlspecialchars($employee['nssf_id'] ?? '') . '"';
            }
            
            echo '<button type="button" class="btn-action-link"'
                           . ' data-bs-toggle="modal"'
                           . ' data-bs-target="#editUserModal"'
                           . ' data-id="' . htmlspecialchars($employee['id']) . '"'
                           . ' data-name="' . htmlspecialchars($employee['full_name'] ?: $employee['username']) . '"'
                           . ' data-username="' . htmlspecialchars($employee['username']) . '"'
                           . ' data-email="' . htmlspecialchars($employee['email']) . '"'
                           . ' data-role="' . htmlspecialchars($employee['role']) . '"'
                           . ' data-position="' . htmlspecialchars($employee['position']) . '"'
                           . ' data-department="' . htmlspecialchars($employee['department'] ?? '') . '"'
                           . ' data-al="' . htmlspecialchars($employee['annual_leave_days'] ?? '') . '"'
                           . ' data-image="' . htmlspecialchars($employee['image_url'] ?? '') . '"'
                           . ' data-jd="' . htmlspecialchars($employee['jd_pdf'] ?? '') . '"'
                           . ' data-workflow="' . htmlspecialchars($employee['workflow_pdf'] ?? '') . '"'
                           . ' data-manager-id="' . htmlspecialchars($employee['manager_id'] ?? '') . '"'
                           . $payrollDataAttributes . '>' // Inject payroll attributes here
                            . 'កែ'
                        . '</button>';
            echo '<a href="deactivate_user.php?id=' . htmlspecialchars($employee['id']) . '" class="btn-action-link text-danger" onclick="return confirm(\'តើអ្នកពិតជាចង់បិទគណនីនេះមែនទេ?\')">បិទ</a>';
        }
        echo '</td>';
        echo '</tr>';
        
        if ($hasChildren) {
            displayEmployees($employee['children'], $level + 1, $employee['id']);
        }
    }
}


// =========================================================================
// --- POST ACTION HANDLERS ARE NOW MOVED TO api_handler.php ---
// =========================================================================


// =========================================================================
// --- DATA LOADING LOGIC ---
// =========================================================================

try {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
    $stmt->execute([$user_id]);
} catch (PDOException $e) {
    error_log("Failed to update last_activity: " . $e->getMessage());
}

$telegramChatId = '-1002496391098';

$totalEmployees = 0;
$activeUsers = 0;
$pendingRequestsCount = 0;

try {
    $stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role != 'admin' AND status = 'active'");
    $totalEmployees = $stmt->fetchColumn();

    $stmt = $conn->query("SELECT COUNT(*) FROM users WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND status = 'active'");
    $activeUsers = $stmt->fetchColumn();

    $stmt = $conn->query("SELECT COUNT(*) FROM requests WHERE status = 'pending'");
    $pendingRequestsCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("General data fetch error: " . $e->getMessage());
}


if ($view === 'dashboard' || $view === 'manage_employees') {
    try {
        $stmt = $conn->query("SELECT id, username, full_name, email, role AS position, department FROM users WHERE status = 'active' ORDER BY full_name");
        $employees_flat_for_dropdowns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // This initial data load for PHP is now only for things like dropdowns.
        // The main table will be loaded by AJAX.
        // We can still keep the logic for other parts of the dashboard if needed.
        if ($view === 'dashboard') {
            $stmt_pending = $conn->prepare("SELECT id, request_type, requester_name, department, reason, request_date, status FROM requests WHERE status = 'pending' ORDER BY created_at DESC");
            $stmt_pending->execute();
            $pendingRequests = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (PDOException $e) {
        error_log("Dashboard view data error: " . $e->getMessage());
        $_SESSION['error'] = 'Database Error: ' . $e->getMessage();
        $employees_flat_for_dropdowns = [];
        if ($view === 'dashboard') {
            $pendingRequests = [];
        }
    }
}


if ($view === 'inactive_users') {
    requirePermission('inactive_users', $conn);
    try {
        $stmt = $conn->query("SELECT id, username, full_name, email, role AS position, image_url FROM users WHERE status = 'inactive' ORDER BY full_name");
        $inactive_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error fetching inactive users: " . $e->getMessage());
        $inactive_employees = [];
        $_SESSION['error'] = 'មានកំហុសក្នុងការទាញបញ្ជីគណនីដែលបានបិទ។';
    }
}


if ($view === 'request_reports') {
    requirePermission('request_reports', $conn);
    $success_request = '';
    $errors_request = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
        try {
            $request_id = (int)$_POST['request_id'];
            $action = $_POST['action'];
            $status = ($action === 'approve') ? 'approved' : 'rejected';

            $stmt = $conn->prepare("UPDATE requests SET status = ? WHERE id = ?");
            $stmt->execute([$status, $request_id]);

            $stmt = $conn->prepare("SELECT * FROM requests WHERE id = ?");
            $stmt->execute([$request_id]);
            $request = $stmt->fetch();

            if ($request) {
                $message = "សំណើបានធ្វើបច្ចុប្បន្នភាព:\n" .
                           "- លេខសម្គាល់: {$request['id']}\n" .
                           "- ប្រភេទ: {$request['request_type']}\n" .
                           "- ឈ្មោះ: {$request['requester_name']}\n" .
                           "- ស្ថានភាព: " . ($status === 'approved' ? 'បានអនុម័ត' : 'បានបដិសេធ') . "\n" .
                           "- កាលបរិច្ឆេទ: " . date('Y-m-d H:i:s');

                // Use is_callable + call_user_func to avoid static analyzer warnings when the function
                // might be defined only in includes/telegram.php and to safely catch exceptions.
                if (is_callable('sendTelegramMessage')) {
                    try {
                        $sent = (bool) call_user_func('sendTelegramMessage', $telegramChatId, $message);
                        if (!$sent) {
                            error_log("Failed to send Telegram message for request ID: $request_id");
                        }
                    } catch (Throwable $e) {
                        error_log("sendTelegramMessage threw an exception: " . $e->getMessage());
                    }
                } else {
                    // Optionally log that the function is not available in this environment.
                    error_log("sendTelegramMessage() not available; telegram notifications skipped for request ID: $request_id");
                }

                $success_request = "សំណើ (ID: $request_id) ត្រូវបានធ្វើបច្ចុប្បន្នភាពជោគជ័យ!";
                header("Location: dashboard.php?view=request_reports&success_request=" . urlencode($success_request));
                exit();
            }
        } catch (PDOException $e) {
            $errors_request[] = "កំហុស: " . $e->getMessage();
            error_log("Error updating request: " . $e->getMessage());
        }
    }
    
    if (isset($_GET['success_request'])) {
        $success_request = htmlspecialchars($_GET['success_request']);
    }

    $filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
    $filter_date = isset($_GET['request_date']) ? trim($_GET['request_date']) : '';
    $filter_type = isset($_GET['request_type']) ? trim($_GET['request_type']) : '';
    $sort_by = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'created_at';
    $sort_order = isset($_GET['sort_order']) && in_array($_GET['sort_order'], ['ASC', 'DESC']) ? $_GET['sort_order'] : 'DESC';

    $valid_sort_columns = ['id', 'request_type', 'request_date', 'created_at', 'status'];
    if (!in_array($sort_by, $valid_sort_columns)) {
        $sort_by = 'created_at';
    }

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

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $requests = [];
        $_SESSION['error'] = "កំហុសក្នុងការទាញសំណើ: " . $e->getMessage();
    }
}

// +++ NEW: Meetings Logic +++
if ($view === 'meetings') {
    try {
        // Pagination setup
        $items_per_page = 9;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) $page = 1;
        $offset = ($page - 1) * $items_per_page;

        // Get total count
        $total_stmt = $conn->query("SELECT COUNT(*) FROM meetings");
        $total_items = $total_stmt->fetchColumn();
        $total_pages = ceil($total_items / $items_per_page);

        // Fetch paginated slice
        $stmt = $conn->prepare("
            SELECT m.*, GROUP_CONCAT(mp.photo_url) AS photo_urls
            FROM meetings m
            LEFT JOIN meeting_photos mp ON m.id = mp.meeting_id
            GROUP BY m.id
            ORDER BY m.meeting_date DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $meetings = [];
        $total_pages = 0;
    }
}

if ($view === 'post_meeting' || $view === 'edit_meeting') {
    $existing_categories = [];
    $editing_meeting = null;
    $editing_photos = [];

    if ($view === 'edit_meeting' && isset($_GET['id'])) {
        $mid = (int)$_GET['id'];
        try {
            $stmt = $conn->prepare("SELECT * FROM meetings WHERE id = ?");
            $stmt->execute([$mid]);
            $editing_meeting = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($editing_meeting) {
                $ps = $conn->prepare("SELECT photo_url FROM meeting_photos WHERE meeting_id = ?");
                $ps->execute([$mid]);
                $editing_photos = $ps->fetchAll(PDO::FETCH_COLUMN);
            }
        } catch (Exception $e) { error_log($e->getMessage()); }
    }

    try {
        $stmt = $conn->query("SELECT DISTINCT category FROM meetings WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
        $existing_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Could not fetch categories: " . $e->getMessage());
    }
}

if ($view === 'view_meeting') {
    $meeting_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    try {
        $stmt = $conn->prepare("SELECT * FROM meetings WHERE id = ?");
        $stmt->execute([$meeting_id]);
        $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($meeting) {
            $stmt_photos = $conn->prepare("SELECT photo_url FROM meeting_photos WHERE meeting_id = ?");
            $stmt_photos->execute([$meeting_id]);
            $meeting_photos = $stmt_photos->fetchAll(PDO::FETCH_COLUMN);
        }
    } catch (PDOException $e) {
        error_log("Meeting view error: " . $e->getMessage());
    }
}
// +++ END NEW +++

// ... (KEEP ALL YOUR OTHER VIEW LOGIC for 'reports', 'checklist', 'payroll', etc. They are not affected by this change)
// ...
// ... [Your existing code for other views remains here] ...
// ...
if ($view === 'reports') {
    requirePermission('daily_reports', $conn);
    try {
        $stmt = $conn->query("SELECT DISTINCT position FROM daily_reports ORDER BY position");
        $positions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $filters = [
            'start_date' => isset($_GET['start_date']) ? filter_var($_GET['start_date'], FILTER_SANITIZE_STRING) : '',
            'end_date' => isset($_GET['end_date']) ? filter_var($_GET['end_date'], FILTER_SANITIZE_STRING) : '',
            'position' => isset($_GET['position']) ? filter_var($_GET['position'], FILTER_SANITIZE_STRING) : ''
        ];

        $query = "SELECT * FROM daily_reports WHERE 1=1";
        $params = [];

        if (!empty($filters['start_date'])) {
            $query .= " AND report_date >= :start_date";
            $params['start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $query .= " AND report_date <= :end_date";
            $params['end_date'] = $filters['end_date'] . ' 23:59:59';
        }
        if (!empty($filters['position'])) {
            $query .= " AND position = :position";
            $params['position'] = $filters['position'];
        }

        $query .= " ORDER BY report_date DESC, position, name";

        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $structuredReports = [];
        foreach ($reports as $report) {
            $date = new DateTime($report['report_date']);
            $year = $date->format('Y');
            $monthDay = $date->format('d-M-Y');
            $position = $report['position'];
            $name = $report['name'];

            if (!isset($structuredReports[$year])) {
                $structuredReports[$year] = [];
            }
            if (!isset($structuredReports[$year][$position])) {
                $structuredReports[$year][$position] = [];
            }
            if (!isset($structuredReports[$year][$position][$name])) {
                $structuredReports[$year][$position][$name] = [];
            }
            if (!isset($structuredReports[$year][$position][$name][$monthDay])) {
                $structuredReports[$year][$position][$name][$monthDay] = [];
            }
            
            $structuredReports[$year][$position][$name][$monthDay][] = $report;
        }

    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $positions = [];
        $reports = [];
        $structuredReports = [];
        $_SESSION['error'] = "កំហុសក្នុងការទាញរបាយការណ៍: " . $e->getMessage();
    }
}


if ($view === 'settings') {
    // Check permissions
    if (function_exists('requirePermission')) {
        // Use a generic permission check or allow admin
        if ($_SESSION['role'] !== 'admin') {
             // You might want to define a specific permission like 'manage_settings'
             // For now, let's restrict to admin or 'administration'
             if (!in_array($_SESSION['role'], ['admin', 'administration'])) {
                 $_SESSION['error'] = 'គ្មានសិទ្ធិមើលទំព័រនេះទេ';
                 header("Location: dashboard.php");
                 exit();
             }
        }
    } else if ($_SESSION['role'] !== 'admin') {
         $_SESSION['error'] = 'គ្មានសិទ្ធិមើលទំព័រនេះទេ';
         header("Location: dashboard.php");
         exit();
    }

    // --- Ensure Tables Exist (Auto-Migration) ---
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value TEXT
        )");
        $conn->exec("CREATE TABLE IF NOT EXISTS telegram_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100),
            chat_id VARCHAR(50),
            report_format VARCHAR(50) DEFAULT 'text'
        )");
        $conn->exec("CREATE TABLE IF NOT EXISTS telegram_group_threads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_id INT,
            thread_id VARCHAR(50),
            category VARCHAR(100),
            position VARCHAR(100),
            FOREIGN KEY (group_id) REFERENCES telegram_groups(id) ON DELETE CASCADE
        )");
        $conn->exec("CREATE TABLE IF NOT EXISTS system_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50),
            name VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (PDOException $e) {
        // Log error but proceed
        error_log("Table creation error: " . $e->getMessage());
    }

    $category_types = ['department', 'position', 'branch'];
    $khmer_names = [
        'department' => 'ដេប៉ាតឺម៉ង់ (Departments)',
        'position' => 'តួនាទី (Positions)',
        'branch' => 'សាខា (Branches)'
    ];

    // --- Handle POST Actions ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings_action'])) {
        $action = $_POST['settings_action'];
        
        try {
            if ($action === 'update_bot_token') {
                $token = trim($_POST['bot_token']);
                $stmt = $conn->prepare("REPLACE INTO system_settings (setting_key, setting_value) VALUES ('telegram_bot_token', ?)");
                $stmt->execute([$token]);
                $_SESSION['success'] = "Telegram Bot Token ត្រូវបានរក្សាទុក!";
            }
            elseif ($action === 'add_group') {
                $stmt = $conn->prepare("INSERT INTO telegram_groups (name, chat_id, report_format) VALUES (?, ?, ?)");
                $stmt->execute([trim($_POST['group_name']), trim($_POST['chat_id']), $_POST['report_format']]);
                $_SESSION['success'] = "ក្រុមថ្មីត្រូវបានបន្ថែម!";
            }
            elseif ($action === 'update_group') {
                 $stmt = $conn->prepare("UPDATE telegram_groups SET name = ?, chat_id = ?, report_format = ? WHERE id = ?");
                 $stmt->execute([trim($_POST['group_name']), trim($_POST['chat_id']), $_POST['report_format'], $_POST['group_id']]);
                 $_SESSION['success'] = "ក្រុមត្រូវបានកែប្រែ!";
            }
            elseif ($action === 'delete_group') {
                $stmt = $conn->prepare("DELETE FROM telegram_groups WHERE id = ?");
                $stmt->execute([$_POST['group_id']]);
                $_SESSION['success'] = "ក្រុមត្រូវបានលុប!";
            }
            elseif ($action === 'add_thread') {
                $stmt = $conn->prepare("INSERT INTO telegram_group_threads (group_id, thread_id, category, position) VALUES (?, ?, ?, ?)");
                $stmt->execute([$_POST['group_id'], trim($_POST['thread_id']), $_POST['category_filter'], $_POST['position_filter']]);
                $_SESSION['success'] = "Thread ថ្មីត្រូវបានបន្ថែម!";
            }
            elseif ($action === 'update_thread') {
                 $stmt = $conn->prepare("UPDATE telegram_group_threads SET thread_id = ?, category = ?, position = ? WHERE id = ?");
                 $stmt->execute([trim($_POST['thread_id']), $_POST['category_filter'], $_POST['position_filter'], $_POST['thread_map_id']]);
                 $_SESSION['success'] = "Thread ត្រូវបានកែប្រែ!";
            }
            elseif ($action === 'delete_thread') {
                $stmt = $conn->prepare("DELETE FROM telegram_group_threads WHERE id = ?");
                $stmt->execute([$_POST['thread_map_id']]);
                $_SESSION['success'] = "Thread ត្រូវបានលុប!";
            }
            elseif ($action === 'add_category') {
                $stmt = $conn->prepare("INSERT INTO system_categories (type, name) VALUES (?, ?)");
                $stmt->execute([$_POST['category_type'], trim($_POST['category_name'])]);
                $_SESSION['success'] = "ប្រភេទថ្មីត្រូវបានបន្ថែម!";
            }
            elseif ($action === 'update_category') {
                $stmt = $conn->prepare("UPDATE system_categories SET name = ? WHERE id = ?");
                $stmt->execute([trim($_POST['category_name']), $_POST['category_id']]);
                $_SESSION['success'] = "ប្រភេទត្រូវបានកែប្រែ!";
            }
            elseif ($action === 'delete_category') {
                $stmt = $conn->prepare("DELETE FROM system_categories WHERE id = ?");
                $stmt->execute([$_POST['category_id']]);
                $_SESSION['success'] = "ប្រភេទត្រូវបានលុប!";
            }

            header("Location: dashboard.php?view=settings");
            exit();

        } catch (PDOException $e) {
            $_SESSION['error'] = "កំហុស: " . $e->getMessage();
        }
    }

    // --- Fetch Data ---
    $telegramBotToken = '';
    try {
        $stmt = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'telegram_bot_token'");
        if ($row = $stmt->fetch()) {
            $telegramBotToken = $row['setting_value'];
        }
    } catch (PDOException $e) { error_log($e->getMessage()); }

    $telegramGroups = [];
    try {
        $stmt = $conn->query("SELECT * FROM telegram_groups ORDER BY id DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $groupId = $row['id'];
            $row['group_id'] = $groupId; // Match UI variable
            
            $tStmt = $conn->prepare("SELECT id as thread_map_id, thread_id, category, position FROM telegram_group_threads WHERE group_id = ?");
            $tStmt->execute([$groupId]);
            $row['thread_ids'] = $tStmt->fetchAll(PDO::FETCH_ASSOC);
            $telegramGroups[] = $row;
        }
    } catch (PDOException $e) { error_log($e->getMessage()); }

    $dynamic_categories = [];
    foreach ($category_types as $type) {
        $dynamic_categories[$type] = [];
        try {
            $stmt = $conn->prepare("SELECT * FROM system_categories WHERE type = ? ORDER BY name");
            $stmt->execute([$type]);
            $dynamic_categories[$type] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { error_log($e->getMessage()); }
    }
}


if ($view === 'permissions') {
    if ($_SESSION['role'] !== 'admin') {
         $_SESSION['error'] = 'គ្មានសិទ្ធិមើលទំព័រនេះទេ';
         header('Location: dashboard.php');
         exit();
    }
    
    // --- Auto-Migration for Permissions ---
    // --- Auto-Migration for Permissions ---
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS admin_menus (
            id INT AUTO_INCREMENT PRIMARY KEY,
            menu_key VARCHAR(50) UNIQUE,
            menu_name VARCHAR(100),
            menu_link VARCHAR(255) DEFAULT '#',
            menu_icon VARCHAR(50) DEFAULT 'fas fa-circle',
            parent_key VARCHAR(50) DEFAULT NULL,
            menu_order INT DEFAULT 0
        )");
        
        // Ensure columns exist (for migration)
        try { $conn->exec("ALTER TABLE admin_menus ADD COLUMN menu_link VARCHAR(255) DEFAULT '#'"); } catch(Exception $e){}
        try { $conn->exec("ALTER TABLE admin_menus ADD COLUMN menu_icon VARCHAR(50) DEFAULT 'fas fa-circle'"); } catch(Exception $e){}
        
        // Comprehensive Default Menus
        $default_menus = [
            // Core
            ['dashboard', 'ផ្ទាំងគ្រប់គ្រង', 'dashboard.php', 'fas fa-home', NULL, 1],
            ['manage_employees', 'គ្រប់គ្រងបុគ្គលិក', 'dashboard.php?view=manage_employees', 'fas fa-users-cog', NULL, 2],
            ['settings', 'ការកំណត់ប្រព័ន្ធ', 'dashboard.php?view=settings', 'fas fa-cogs', NULL, 99],
            ['permissions', 'ការកំណត់សិទ្ធ', 'dashboard.php?view=permissions', 'fas fa-shield-alt', 'settings', 1],
            
            // Works
            ['checklist', 'បញ្ជីការងារ', 'dashboard.php?view=checklist', 'fas fa-tasks', NULL, 3],
            ['daily_reports', 'របាយការណ៍ប្រចាំថ្ងៃ', 'dashboard.php?view=reports', 'fas fa-file-alt', NULL, 4],
            ['request_reports', 'របាយការណ៍សំណើ', 'dashboard.php?view=request_reports', 'fas fa-list-check', NULL, 5],
            ['pending_requests', 'សំណើរង់ចាំ', 'pending_requests.php', 'fas fa-clock', NULL, 6],
            ['processed_requests', 'សំណើបានដំណើរការ', 'view_processed_requests.php', 'fas fa-check-circle', NULL, 7],
            
            // Payroll & Finance
            ['payroll_calculation', 'ការគណនាបៀវត្ស', 'dashboard.php?view=payroll_calculation', 'fas fa-calculator', NULL, 10],
            ['payroll_approval', 'អនុម័តបៀវត្ស', 'dashboard.php?view=payroll_approval', 'fas fa-file-signature', NULL, 11],
            ['payroll_payslip', 'ប័ណ្ណបើកប្រាក់', 'dashboard.php?view=payroll_payslip', 'fas fa-file-invoice-dollar', NULL, 12],
            ['deductions_bonuses', 'កាត់ប្រាក់ & OT', 'dashboard.php?view=deductions_bonuses', 'fas fa-money-bill-transfer', NULL, 13],
            
            // Others
            ['meetings', 'បញ្ជីកិច្ចប្រជុំ', 'dashboard.php?view=meetings', 'fas fa-calendar-check', NULL, 20],
            ['post_announcements', 'បង្ហោះការជូនដំណឹង', '../../posts/post_announcements.php', 'fas fa-bullhorn', NULL, 21],
            ['view_lessons', 'មើលមេរៀន', 'lessons.php', 'fas fa-graduation-cap', NULL, 22],
            ['upload_lesson_docs', 'បង្ហោះឯកសារមេរៀន', 'post_lesson_documents.php', 'fas fa-file-upload', NULL, 23],
            ['inactive_users', 'គណនីបានបិទ', 'dashboard.php?view=inactive_users', 'fas fa-user-slash', NULL, 24],
            ['print_pdf', 'បោះពុម្ព PDF', 'print_content.php', 'fas fa-print', NULL, 25],
            ['upload_pdf', 'បង្ហោះ PDF', 'store_print_pdf.php', 'fas fa-file-pdf', NULL, 26],
        ];

        // We use REPLACE INTO or ON DUPLICATE KEY UPDATE to ensure links/icons are updated
        $stmt = $conn->prepare("INSERT INTO admin_menus (menu_key, menu_name, menu_link, menu_icon, parent_key, menu_order) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE menu_link=VALUES(menu_link), menu_icon=VALUES(menu_icon), menu_name=VALUES(menu_name)");
        foreach ($default_menus as $m) { $stmt->execute($m); }
        
        $conn->exec("CREATE TABLE IF NOT EXISTS permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role VARCHAR(50),
            permission_key VARCHAR(50),
            UNIQUE KEY unique_perm (role, permission_key)
        )");

        // Insert Default Permissions if table is empty
        $chk_perm = $conn->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
        if ($chk_perm == 0) {
            // Define standard roles and their allowed keys
            $role_permissions = [
                'administration' => [
                    'dashboard', 'manage_employees', 'checklist', 'daily_reports', 'request_reports', 
                    'pending_requests', 'processed_requests', 'meetings', 'post_announcements', 
                    'view_lessons', 'upload_lesson_docs', 'inactive_users', 'print_pdf', 'upload_pdf', 'settings'
                ],
                'accounting' => [
                    'dashboard', 'manage_employees', 'payroll_calculation', 'payroll_approval', 
                    'payroll_payslip', 'deductions_bonuses', 'reports'
                ],
                'hr' => [
                    'dashboard', 'manage_employees', 'checklist', 'daily_reports', 'request_reports',
                    'pending_requests', 'processed_requests', 'meetings', 'inactive_users'
                ],
                'manager' => [
                    'dashboard', 'checklist', 'daily_reports', 'request_reports', 'meetings'
                ],
                'staff' => [
                    'dashboard', 'checklist', 'daily_reports'
                ]
            ];

            $stmt_perm = $conn->prepare("INSERT INTO permissions (role, permission_key) VALUES (?, ?)");
            foreach ($role_permissions as $role => $keys) {
                foreach ($keys as $k) {
                    $stmt_perm->execute([$role, $k]);
                }
            }
        }
    } catch (PDOException $e) {}

    // --- Helper Function ---
    if (!function_exists('render_menu_rows')) {
        function render_menu_rows($menus, $roles, $level = 0) {
            global $conn;
            static $role_perms = null;
            if ($role_perms === null) {
                // Fetch all permissions
                $stmt = $conn->query("SELECT role, permission_key FROM permissions");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $role_perms = [];
                foreach ($rows as $r) {
                    $role_perms[$r['role']][] = $r['permission_key'];
                }
            }

            foreach ($menus as $menu) {
                $padding = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
                $icon = $level > 0 ? '<i class="fas fa-angle-right text-xs mr-2"></i>' : '<i class="fas fa-folder text-accent-color mr-2"></i>';
                echo '<tr>';
                echo '<td class="text-white">' . $padding . $icon . htmlspecialchars($menu['menu_name']) . ' <small class="text-muted">('.$menu['menu_key'].')</small></td>';
                
                foreach ($roles as $role) {
                    $checked = '';
                    if (isset($role_perms[$role]) && in_array($menu['menu_key'], $role_perms[$role])) {
                        $checked = 'checked';
                    }
                    if ($role === 'admin') {
                        $checked = 'checked disabled';
                    }
                    
                    echo '<td class="text-center">';
                    echo '<input type="checkbox" name="perms['.$role.'][]" value="'.$menu['menu_key'].'" class="form-check-input" '.$checked.'>';
                    echo '</td>';
                }
                
                echo '<td class="text-end">';
                echo '<button type="button" class="btn-sm btn-outline-warning" onclick=\'openMenuModal('.json_encode($menu).')\'><i class="fas fa-edit"></i></button> ';
                echo '<button type="button" class="btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteMenuModal" data-menu-id="'.$menu['id'].'" data-menu-name="'.$menu['menu_name'].'"><i class="fas fa-trash"></i></button>';
                echo '</td>';
                echo '</tr>';
                
                if (!empty($menu['children'])) {
                    render_menu_rows($menu['children'], $roles, $level + 1);
                }
            }
        }
    }

    // --- Handle POST ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'save_permissions') {
            try {
                $conn->beginTransaction();
                $conn->exec("DELETE FROM permissions"); // Simple reset approach
                
                if (isset($_POST['perms'])) {
                    $stmt = $conn->prepare("INSERT INTO permissions (role, permission_key) VALUES (?, ?)");
                    foreach ($_POST['perms'] as $role => $keys) {
                        if ($role === 'admin') continue;
                        foreach ($keys as $key) {
                            $stmt->execute([$role, $key]);
                        }
                    }
                }
                $conn->commit();
                $_SESSION['success'] = "សិទ្ធិត្រូវបានកែប្រែ!";
            } catch (Exception $e) {
                $conn->rollBack();
                $_SESSION['error'] = "កំហុស: " . $e->getMessage();
            }
        } 
        elseif ($_POST['action'] === 'save_menu') {
            try {
                $name = trim($_POST['menu_name']);
                $key = trim($_POST['menu_key']);
                $parent = !empty($_POST['parent_key']) ? $_POST['parent_key'] : NULL;
                $order = (int)$_POST['menu_order'];
                $id = !empty($_POST['menu_id']) ? (int)$_POST['menu_id'] : null;

                if ($id) {
                    $stmt = $conn->prepare("UPDATE admin_menus SET menu_name=?, menu_key=?, parent_key=?, menu_order=? WHERE id=?");
                    $stmt->execute([$name, $key, $parent, $order, $id]);
                     $_SESSION['success'] = "ម៉ឺនុយត្រូវបានកែប្រែ!";
                } else {
                    $stmt = $conn->prepare("INSERT INTO admin_menus (menu_name, menu_key, parent_key, menu_order) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $key, $parent, $order]);
                     $_SESSION['success'] = "ម៉ឺនុយថ្មីត្រូវបានបន្ថែម!";
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "កំហុស: " . $e->getMessage();
            }
        }
        elseif ($_POST['action'] === 'delete_menu') {
             $id = (int)$_POST['menu_id'];
             $stmt = $conn->prepare("DELETE FROM admin_menus WHERE id = ?");
             $stmt->execute([$id]);
             $_SESSION['success'] = "ម៉ឺនុយត្រូវបានលុប!";
        }
        
        header('Location: dashboard.php?view=permissions');
        exit();
    }

    // --- Fetch Data ---
    $all_roles = ['admin', 'administration', 'accounting', 'hr', 'manager', 'staff'];
    
    // Fetch Menus
    $stmt = $conn->query("SELECT * FROM admin_menus ORDER BY menu_order ASC");
    $all_menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build Menu Tree
    $grouped_menus = [];
    $menu_map = [];
    foreach ($all_menus as $m) {
        $m['children'] = [];
        $menu_map[$m['menu_key']] = $m;
    }
    // Second pass to link children
    // Need to use references carefully or reconstruct
    // Simpler approach:
    $grouped_menus = []; 
    // Re-index by key for easy parent lookup
    $refs = [];
    foreach ($all_menus as $m) {
        $refs[$m['menu_key']] = $m;
        $refs[$m['menu_key']]['children'] = [];
    }
    foreach ($all_menus as $m) {
        if ($m['parent_key'] && isset($refs[$m['parent_key']])) {
            $refs[$m['parent_key']]['children'][] = &$refs[$m['menu_key']];
        } else {
            $grouped_menus[] = &$refs[$m['menu_key']];
        }
    }
}


if ($view === 'checklist') {
    requirePermission('checklist', $conn);
    $checklist_success = '';
    $checklist_errors = [];
    $user_id = $_SESSION['user_id'];

    if ($_SESSION['role'] !== 'admin') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_category'])) {
            try {
                $stmt = $conn->prepare("INSERT INTO category_list (user_id, name) VALUES (?, ?)");
                $stmt->execute([$user_id, trim($_POST['new_category'])]);
                $checklist_success = "ប្រភេទថ្មីត្រូវបានបន្ថែមជោគជ័យ!";
            } catch (PDOException $e) {
                $checklist_errors[] = "កំហុសក្នុងការបន្ថែមប្រភេទ: " . $e->getMessage();
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task'])) {
            try {
                $stmt = $conn->prepare(
                    "INSERT INTO work_checklist (user_id, task, due_date, category) VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([$user_id, trim($_POST['task']), $_POST['due_date'], $_POST['category']]);
                $checklist_success = "ការងារថ្មីត្រូវបានបន្ថែមជោគជ័យ!";
            } catch (PDOException $e) {
                $checklist_errors[] = "កំហុសក្នុងការបន្ថែមការងារ: " . $e->getMessage();
            }
        }

        if (isset($_GET['done'])) {
            try {
                $stmt = $conn->prepare("UPDATE work_checklist SET is_done = 1 WHERE id = ? AND user_id = ?");
                $stmt->execute([(int)$_GET['done'], $user_id]);
                if ($stmt->rowCount() > 0) {
                    $checklist_success = "ការងារត្រូវបានសម្គាល់ថាបានសម្រេច!";
                } else {
                    $checklist_errors[] = "អ្នកមិនមានសិទ្ធិសម្គាល់ការងារនេះទេ!";
                }
            } catch (PDOException $e) {
                $checklist_errors[] = "កំហុសក្នុងការសម្គាល់ការងារ: " . $e->getMessage();
            }
        }

        if (isset($_GET['delete'])) {
            try {
                $stmt = $conn->prepare("DELETE FROM work_checklist WHERE id = ? AND user_id = ?");
                $stmt->execute([(int)$_GET['delete'], $user_id]);
                if ($stmt->rowCount() > 0) {
                    $checklist_success = "ការងារត្រូវបានលុបជោគជ័យ!";
                } else {
                    $checklist_errors[] = "អ្នកមិនមានសិទ្ធិលុបការងារនេះទេ!";
                }
            } catch (PDOException $e) {
                $checklist_errors[] = "កំហុសក្នុងការលុបការងារ: " . $e->getMessage();
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['done']) || isset($_GET['delete'])) {
            $redirect_url = "dashboard.php?view=checklist";
            if (!empty($checklist_success)) {
                $redirect_url .= "&c_success=" . urlencode($checklist_success);
            } elseif (!empty($checklist_errors)) {
                 $redirect_url .= "&c_error=" . urlencode(implode('; ', $checklist_errors));
            }
            header("Location: " . $redirect_url);
            exit();
        }
    } 

    try {
        if ($_SESSION['role'] === 'admin') {
            $stmt = $conn->prepare("SELECT DISTINCT name FROM category_list ORDER BY name");
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("SELECT name FROM category_list WHERE user_id = ? ORDER BY name");
            $stmt->execute([$user_id]);
        }
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $categories = [];
        $checklist_errors[] = "កំហុសក្នុងការទាញប្រភេទ: " . $e->getMessage();
    }

    if ($_SESSION['role'] === 'admin') {
        try {
            $stmt = $conn->prepare("SELECT DISTINCT full_name FROM users ORDER BY full_name");
            $stmt->execute();
            $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            $names = [];
            $checklist_errors[] = "កំហុសក្នុងការទាញឈ្មោះអ្នកប្រើ: " . $e->getMessage();
        }
    } else {
        $names = [];
    }

    $category_filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
    $name_filter = isset($_GET['name']) ? trim($_GET['name']) : '';
    
    $sql = "SELECT w.*, u.full_name AS owner_name FROM work_checklist w LEFT JOIN users u ON w.user_id = u.id WHERE 1=1";
    $params = [];

    if ($_SESSION['role'] !== 'admin') {
        $sql .= " AND w.user_id = ?";
        $params[] = $user_id;
    }
    
    if ($category_filter) {
        $sql .= " AND w.category = ?";
        $params[] = $category_filter;
    }
    
    if ($_SESSION['role'] === 'admin' && $name_filter) {
        $sql .= " AND u.full_name = ?";
        $params[] = $name_filter;
    }
    
    $sql .= " ORDER BY w.id DESC";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $tasks = [];
        $checklist_errors[] = "កំហុសក្នុងការទាញការងារ: " . $e->getMessage();
    }
    
    if (isset($_GET['c_success'])) {
         $checklist_success = htmlspecialchars($_GET['c_success']);
    }
    if (isset($_GET['c_error'])) {
         $checklist_errors[] = htmlspecialchars($_GET['c_error']);
    }
}


if ($view === 'payroll_calculation') {
    requirePermission('payroll_calculation', $conn);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_payroll'])) {
        try {
            $conn->beginTransaction();
            
            // Delete payroll runs and details
            $conn->exec("DELETE FROM payroll_run_details");
            $conn->exec("DELETE FROM payroll_runs");
            
            // Reset loan balances and payments
            $conn->exec("UPDATE employee_loans SET remaining_balance = loan_amount, status = 'active'");
            $conn->exec("DELETE FROM loan_payments");
            
            // Optionally reset other deductions if needed, but for now, keep them
            
            $conn->commit();
            $_SESSION['success'] = 'ការគណនាប្រាក់ខែត្រូវបាន reset ដោយជោគជ័យ។ បំណុលទាំងអស់ត្រូវបានកំណត់ឡើងវិញទៅតម្លៃដើម។';
        } catch (PDOException $e) {
            $conn->rollBack();
            $_SESSION['error'] = 'មានបញ្ហាក្នុងការលុបទិន្នន័យ: ' . $e->getMessage();
        }
        header('Location: dashboard.php?view=payroll_calculation');
        exit();
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalize_payroll'])) {
        $payroll_data_json = $_POST['payroll_data'] ?? '';
        $payroll_month_str = $_POST['payroll_month'] ?? '';
        $employees_data = json_decode($payroll_data_json, true);

        if (empty($employees_data) || empty($payroll_month_str)) {
            $_SESSION['error'] = 'ទិន្នន័យមិនត្រឹមត្រូវ ឬគ្មានបុគ្គលិកត្រូវគណនា។';
            header('Location: dashboard.php?view=payroll_calculation');
            exit();
        }

        $checkStmt = $conn->prepare("SELECT id FROM payroll_runs WHERE month = ? AND status IN ('calculated', 'approved', 'paid')");
        $checkStmt->execute([$payroll_month_str]);
        if ($checkStmt->fetch()) {
            $_SESSION['error'] = 'បញ្ជីបើកប្រាក់បៀវត្សសម្រាប់ខែនេះត្រូវបានបង្កើតរួចហើយ។';
            header('Location: dashboard.php?view=payroll_approval');
            exit();
        }

        try {
            $conn->beginTransaction();

            $total_net = 0;
            $total_gross = 0;
            $total_deductions = 0;
            foreach ($employees_data as $emp) {
                $total_gross += $emp['base_salary'] + $emp['ot_bonus'];
                $total_deductions += $emp['deductions'];
                $total_net += $emp['net_salary'];
            }

            $stmt_run = $conn->prepare(
                "INSERT INTO payroll_runs (month, total_net_salary, total_gross_salary, total_deductions, created_by_id, status)
                 VALUES (?, ?, ?, ?, ?, 'calculated')"
            );
            $stmt_run->execute([$payroll_month_str, $total_net, $total_gross, $total_deductions, $_SESSION['user_id']]);
            $run_id = $conn->lastInsertId();

            $stmt_details = $conn->prepare(
                "INSERT INTO payroll_run_details (payroll_run_id, user_id, full_name, base_salary, bonuses, deductions, net_salary)
                 VALUES (:run_id, :user_id, :full_name, :base_salary, :bonuses, :deductions, :net_salary)"
            );

            foreach ($employees_data as $name => $data) {
                $stmt_details->execute([
                    ':run_id' => $run_id,
                    ':user_id' => $data['id'],
                    ':full_name' => $name,
                    ':base_salary' => $data['base_salary'],
                    ':bonuses' => $data['ot_bonus'],
                    ':deductions' => $data['deductions'],
                    ':net_salary' => $data['net_salary']
                ]);
            }

            // Update loans: decrease remaining_balance and record payments for this payroll month
            // Only update loans if the payroll month is current or future (to avoid affecting current data when calculating past)
            if ($payroll_month_str >= date('Y-m-01')) {
                try {
                    $loan_select = $conn->prepare(
                        "SELECT id, user_id, monthly_deduction, remaining_balance, start_month, status, duration_months
                         FROM employee_loans
                         WHERE user_id = :uid AND status = 'active' AND remaining_balance > 0"
                    );
                    $loan_update = $conn->prepare(
                        "UPDATE employee_loans SET remaining_balance = :new_balance, status = :new_status WHERE id = :loan_id"
                    );
                    $loan_payment = $conn->prepare(
                        "INSERT INTO loan_payments (loan_id, user_id, payroll_run_id, month, amount) VALUES (:loan_id, :uid, :run_id, :month, :amount)"
                    );
                    $loan_payments_select = $conn->prepare(
                        "SELECT month FROM loan_payments WHERE loan_id = :loan_id"
                    );

                    foreach ($employees_data as $name => $data) {
                        $uid = (int)$data['id'];
                        $loan_select->execute([':uid' => $uid]);
                        $loans = $loan_select->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($loans as $loan) {
                            $md = (float)$loan['monthly_deduction'];
                            $remain = (float)$loan['remaining_balance'];
                            $loan_id = $loan['id'];

                            // Get already paid months for this loan
                            $loan_payments_select->execute([':loan_id' => $loan_id]);
                            $paid_months = $loan_payments_select->fetchAll(PDO::FETCH_COLUMN);

                            // Only deduct for the current payroll month, not catching up on past
                            $month_str = $payroll_month_str;
                            if (!in_array($month_str, $paid_months) && $remain > 0) {
                                $repay_month = min($md, $remain);
                                $remain -= $repay_month;
                                $loan_payment->execute([':loan_id' => $loan_id, ':uid' => $uid, ':run_id' => $run_id, ':month' => $month_str, ':amount' => $repay_month]);
                            }

                            // Update the loan with new remaining balance
                            $new_status = ($remain <= 0.00001) ? 'closed' : 'active';
                            $loan_update->execute([':new_balance' => $remain, ':new_status' => $new_status, ':loan_id' => $loan_id]);
                        }
                    }
                } catch (PDOException $e) {
                    // If loan updates fail, rollback the whole payroll finalize for data consistency
                    throw $e;
                }
            }

            $conn->commit();
            $_SESSION['success'] = 'បញ្ជីបើកប្រាក់បៀវត្សបានបង្កើតដោយជោគជ័យ ហើយកំពុងរង់ចាំការអនុម័ត!';
            header('Location: dashboard.php?view=payroll_approval');
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['error'] = 'មានបញ្ហាពេលរក្សាទុកទិន្នន័យ៖ ' . $e->getMessage();
            header('Location: dashboard.php?view=payroll_calculation');
            exit();
        }
    }

    // --- Date navigation logic ---
    $current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
    $current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
    $payroll_month_for_db = $current_year . '-' . str_pad($current_month, 2, '0', STR_PAD_LEFT) . '-01';

    $current_date = new DateTime("$current_year-$current_month-01");
    $payroll_period_display = $current_date->format('F Y');

    $prev_date = (clone $current_date)->modify('-1 month');
    $prev_year = $prev_date->format('Y');
    $prev_month = $prev_date->format('m');

    $next_date = (clone $current_date)->modify('+1 month');
    $next_year = $next_date->format('Y');
    $next_month = $next_date->format('m');
    
    $employees = [];
    // +++ NEW (IMPROVED): បង្កើត Array សម្រាប់ផ្គូផ្គងឈ្មោះទៅ ID (មិនប្រកាន់អក្សរតូច/ធំ) +++
    $name_to_id_map = [];

    try {
        $stmt_users = $conn->query("SELECT id, full_name, base_salary, department FROM users WHERE status = 'active' AND LOWER(full_name) NOT IN ('admin','adminbt') AND LOWER(username) NOT IN ('admin','adminbt')");
        foreach ($stmt_users->fetchAll(PDO::FETCH_ASSOC) as $user) {
            // ប្រើ ID ជា Key សំខាន់សម្រាប់រក្សាទុកទិន្នន័យ
            $employees[$user['id']] = [
                'id' => $user['id'],
                'full_name' => $user['full_name'], // រក្សាទុកឈ្មោះដើមសម្រាប់បង្ហាញ
                'base_salary' => (float)$user['base_salary'],
                'department' => $user['department'],
                'ot_bonus' => 0.0,
                'deductions' => 0.0,
                'deduction_details' => [],
                'bonus_details' => []
            ];
            
            // +++ CHANGE 1: បញ្ចូលឈ្មោះទៅក្នុង Map ដោយបំប្លែងទៅជាអក្សរតូច និងកាត់ដកឃ្លា +++
            if (!empty($user['full_name'])) {
                $clean_name = strtolower(trim($user['full_name']));
                $name_to_id_map[$clean_name] = $user['id'];
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error fetching employees: " . $e->getMessage();
    }

    if (!empty($employees)) {
        try {
            $sql_requests = "SELECT requester_name, request_type, reason, request_date, number_of_days
                               FROM requests 
                               WHERE status = 'approved' 
                               AND YEAR(request_date) = :year AND MONTH(request_date) = :month";
            $stmt_requests = $conn->prepare($sql_requests);
            $stmt_requests->execute([':year' => $current_year, ':month' => $current_month]);
            $requests = $stmt_requests->fetchAll(PDO::FETCH_ASSOC);

            foreach ($requests as $request) {
                // +++ CHANGE 2: សម្អាត requester_name មុនពេលស្វែងរក +++
                $clean_requester_name = strtolower(trim($request['requester_name']));
                
                // ស្វែងរក ID ពីឈ្មោះដែលបានសម្អាត
                $user_id = $name_to_id_map[$clean_requester_name] ?? null; 

                if ($user_id && isset($employees[$user_id])) {
                    // បើរកឃើញ ID នោះកូដគណនានឹងដំណើរការ
                    $base_salary = $employees[$user_id]['base_salary'];
                    $department = $employees[$user_id]['department'];
                    $daily_rate_worker = $base_salary > 0 ? $base_salary / 28 : 0;
                    $daily_rate_staff = $base_salary > 0 ? $base_salary / 26 : 0;
                    
                    switch ($request['request_type']) {
                        case 'ថែមម៉ោង (OT)':
                            $ot_days = 1.0;
                            if (isset($request['number_of_days']) && is_numeric($request['number_of_days'])) {
                                $ot_days = max(0.0, (float)$request['number_of_days']);
                            }
                            $daily_rate = ($department === 'Worker') ? $daily_rate_worker : $daily_rate_staff;
                            $ot_amount = $daily_rate * $ot_days;
                            $employees[$user_id]['ot_bonus'] += $ot_amount;
                            $employees[$user_id]['bonus_details'][] = "OT (តាមសំណើ) on " . date('d-M', strtotime($request['request_date'])) . ": $" . number_format($ot_amount, 2) . " (ចំនួនថ្ងៃ: " . number_format($ot_days, 2) . ")";
                            break;
                        case 'ភ្លេចស្កេនមេដៃ (Forgot FP)':
                            $deduction_amount = 1.00;
                            $employees[$user_id]['deductions'] += $deduction_amount;
                            $employees[$user_id]['deduction_details'][] = "Forgot FP on " . date('d-M', strtotime($request['request_date'])) . ": -$1.00";
                            break;
                        case 'សម្រាកប្រចាំឆ្នាំ (Annual Leave)':
                            if ($department === 'Worker') {
                                $deduction_amount = $daily_rate_worker;
                                $employees[$user_id]['deductions'] += $deduction_amount;
                                $employees[$user_id]['deduction_details'][] = "Annual Leave on " . date('d-M', strtotime($request['request_date'])) . ": -$" . number_format($deduction_amount, 2);
                            }
                            break;
                    }
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database query failed for requests: " . $e->getMessage();
        }

        // --- ផ្នែកខាងក្រោមនេះប្រើ ID រួចហើយ មិនចាំបាច់កែប្រែច្រើន ---
        try {
            $ot_month_for_db_query = $current_year . '-' . str_pad($current_month, 2, '0', STR_PAD_LEFT);
            // កែ Query ឲ្យទាញយក user_id ដោយផ្ទាល់
            $sql_ot_bonuses = "SELECT user_id, ot_amount, reason
                               FROM ot_bonuses
                               WHERE ot_month = :month";
            $stmt_ot_bonuses = $conn->prepare($sql_ot_bonuses);
            $stmt_ot_bonuses->execute([':month' => $ot_month_for_db_query]);
            $monthly_ot_bonuses = $stmt_ot_bonuses->fetchAll(PDO::FETCH_ASSOC);
            foreach ($monthly_ot_bonuses as $ot) {
                $user_id = $ot['user_id'];
                if (isset($employees[$user_id])) {
                    $ot_amount = (float)$ot['ot_amount'];
                    $employees[$user_id]['ot_bonus'] += $ot_amount; 
                    $detail_text = "OT (បញ្ចូលដោយដៃ): $" . number_format($ot_amount, 2);
                    if (!empty($ot['reason'])) {
                        $detail_text .= " (" . htmlspecialchars($ot['reason']) . ")";
                    }
                    $employees[$user_id]['bonus_details'][] = $detail_text;
                }
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'ot_bonuses') === false) {
                 $_SESSION['error'] = "Database query failed for manual OT bonuses: " . $e->getMessage();
            }
        }

        try {
            $sql_other_deductions = "SELECT user_id, amount, reason, deduction_date
                                     FROM other_deductions
                                     WHERE YEAR(deduction_date) = :year AND MONTH(deduction_date) = :month";
            $stmt_other_deductions = $conn->prepare($sql_other_deductions);
            $stmt_other_deductions->execute([':year' => $current_year, ':month' => $current_month]);
            $other_deductions = $stmt_other_deductions->fetchAll(PDO::FETCH_ASSOC);
            foreach ($other_deductions as $deduction) {
                $user_id = $deduction['user_id'];
                if (isset($employees[$user_id])) {
                    $employees[$user_id]['deductions'] += (float)$deduction['amount'];
                    $detail_text = htmlspecialchars($deduction['reason']) . " on " . date('d-M', strtotime($deduction['deduction_date'])) . ": -$" . number_format($deduction['amount'], 2);
                    $employees[$user_id]['deduction_details'][] = $detail_text;
                }
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database query failed for other deductions: " . $e->getMessage();
        }

        // --- Loan monthly repayments: add to deductions ---
        try {
            $payroll_month_date = $current_year . '-' . str_pad($current_month, 2, '0', STR_PAD_LEFT) . '-01';
            $payroll_month_end = date('Y-m-t', strtotime($payroll_month_date)); // Last day of payroll month
            
            $loan_stmt = $conn->prepare(
                "SELECT id, user_id, monthly_deduction, remaining_balance, start_month, borrow_date, status, duration_months, loan_amount
                 FROM employee_loans
                 WHERE user_id = :uid AND status = 'active' AND remaining_balance > 0"
            );
            $loan_payments_stmt = $conn->prepare(
                "SELECT month FROM loan_payments WHERE loan_id = :loan_id"
            );
            foreach ($employees as $user_id => $empData) {
                // Fetch loans for this user
                $loan_stmt->execute([':uid' => $user_id]);
                $user_loans = $loan_stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($user_loans)) {
                    foreach ($user_loans as $loan) {
                        $md = (float)$loan['monthly_deduction'];
                        $remain = (float)$loan['remaining_balance'];
                        $loan_id = $loan['id'];
                        
                        // Get already paid months
                        $loan_payments_stmt->execute([':loan_id' => $loan_id]);
                        $paid_months = $loan_payments_stmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        // Only deduct for the current payroll month
                        $total_repay = 0.0;
                        $remaining_after = $remain;
                        $month_str = $payroll_month_date;
                        if (!in_array($month_str, $paid_months) && $remaining_after > 0) {
                            $repay_month = min($md, $remaining_after);
                            $total_repay += $repay_month;
                            $remaining_after -= $repay_month;
                        }
                        
                        if ($total_repay > 0.0) {
                            $employees[$user_id]['deductions'] += $total_repay;
                            $employees[$user_id]['deduction_details'][] = "Loan repayment: -$" . number_format($total_repay, 2) . " (Remaining: $" . number_format($remaining_after, 2) . ")";
                            
                            // Check if loan will be completed this month
                            if ($remaining_after <= 0.00001) {
                                $employees[$user_id]['deduction_details'][] = "Note: Loan will be completed this month";
                            } elseif ($remaining_after < $md) {
                                $employees[$user_id]['deduction_details'][] = "Note: Final payment needed next month";
                            }
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            // Log but do not block payroll view
            error_log("Loan deduction calculation error: " . $e->getMessage());
        }
    }

    $employees_for_json = [];
    foreach ($employees as $id => $data) {
        $data['net_salary'] = $data['base_salary'] + $data['ot_bonus'] - $data['deductions'];
        // ប្តូរ Key សម្រាប់ JSON ទៅជាឈ្មោះពេញ ដើម្បីឲ្យ Table បង្ហាញបានត្រឹមត្រូវដូចមុន
        $employees_for_json[$data['full_name']] = $data;
    }

    // Handle CSV export for payroll calculation
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=payroll_calculation_' . $current_year . '-' . str_pad($current_month, 2, '0', STR_PAD_LEFT) . '.csv');
        $output = fopen('php://output', 'w');
        // Add UTF-8 BOM for proper Khmer character display in Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Calculate totals for summary
        $total_base_salary = 0.0;
        $total_ot_bonuses = 0.0;
        $total_deductions = 0.0;
        $total_net_salary = 0.0;
        $employee_count = count($employees_for_json);
        
        foreach ($employees_for_json as $name => $data) {
            $total_base_salary += (float)($data['base_salary'] ?? 0);
            $total_ot_bonuses += (float)($data['ot_bonus'] ?? 0);
            $total_deductions += (float)($data['deductions'] ?? 0);
            $total_net_salary += (float)($data['net_salary'] ?? 0);
        }

        // Add title and summary
        fputcsv($output, ['ការគណនាបៀវត្ស - Payroll Calculation Report']);
        fputcsv($output, ['កាលបរិច្ឆេទទាញយក - Export Date', date('d/m/Y')]);
        fputcsv($output, ['ខែ - Month', $payroll_period_display]);
        fputcsv($output, []);
        fputcsv($output, ['សរុបប្រាក់ខែគោល - Total Base Salary', '$' . number_format($total_base_salary, 2)]);
        fputcsv($output, ['សរុបប្រាក់បន្ថែម OT - Total OT Bonuses', '$' . number_format($total_ot_bonuses, 2)]);
        fputcsv($output, ['សរុបការកាត់ប្រាក់ - Total Deductions', '$' . number_format($total_deductions, 2)]);
        fputcsv($output, ['សរុបប្រាក់ខែចុងក្រោយ - Total Net Salary', '$' . number_format($total_net_salary, 2)]);
        fputcsv($output, ['ចំនួនបុគ្គលិក - Total Employees', $employee_count]);
        fputcsv($output, []);

        // Add data headers
        fputcsv($output, ['ឈ្មោះបុគ្គលិក - Employee Name', 'ផ្នែក - Department', 'ប្រាក់ខែគោល - Base Salary', 'ប្រាក់បន្ថែម OT - OT Bonus', 'ការកាត់ប្រាក់ - Deductions', 'ប្រាក់ខែចុងក្រោយ - Net Salary']);

        // Add data rows
        $allowed_departments = ['admin', 'accounting', 'hr'];
        foreach ($employees_for_json as $name => $data) {
            $is_allowed = in_array(strtolower($data['department'] ?? ''), $allowed_departments);
            $base_salary_display = $is_allowed ? '$' . number_format($data['base_salary'], 2) : '****';
            $net_salary_display = $is_allowed ? '$' . number_format($data['net_salary'], 2) : '****';
            fputcsv($output, [
                $name,
                $data['department'] ?: 'N/A',
                $base_salary_display,
                '$' . number_format($data['ot_bonus'], 2),
                '$' . number_format($data['deductions'], 2),
                $net_salary_display
            ]);
        }
        fclose($output);
        exit();
    }
}


if ($view === 'deductions_bonuses') {
    requirePermission('deductions_bonuses', $conn);
    
    $active_tab = $_GET['tab'] ?? 'deductions';

    // Ensure loan tables exist for unified management
    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS employee_loans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            loan_amount DECIMAL(10,2) NOT NULL,
            remaining_balance DECIMAL(10,2) NOT NULL,
            monthly_deduction DECIMAL(10,2) NOT NULL,
            start_month DATE NOT NULL,
            status ENUM('active','paused','closed') DEFAULT 'active',
            note VARCHAR(255) NULL,
            created_by_id INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_employee_loans_user_month (user_id, start_month)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $conn->exec("CREATE TABLE IF NOT EXISTS loan_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            loan_id INT NOT NULL,
            user_id INT NOT NULL,
            payroll_run_id INT NULL,
            month DATE NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_loan_payments_loan_month (loan_id, month)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Safely add new columns for borrow date and duration if they don't exist
        try { $conn->exec("ALTER TABLE employee_loans ADD COLUMN borrow_date DATE NULL"); } catch (PDOException $e) { /* ignore if exists */ }
        try { $conn->exec("ALTER TABLE employee_loans ADD COLUMN duration_months INT NULL"); } catch (PDOException $e) { /* ignore if exists */ }
        try { $conn->exec("ALTER TABLE employee_loans ADD COLUMN fund_request_note TEXT NULL"); } catch (PDOException $e) { /* ignore if exists */ }
    } catch (PDOException $e) {
        error_log('Failed ensuring loan tables (deductions_bonuses): ' . $e->getMessage());
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_deduction'])) {
        $user_id = $_POST['user_id'];
        $amount = $_POST['amount'];
        $reason = trim($_POST['reason']);
        $deduction_date = $_POST['deduction_date'];

        if (empty($user_id) || empty($amount) || empty($reason) || empty($deduction_date)) {
            $_SESSION['error'] = 'សូមបំពេញព័ត៌មានឲ្យបានគ្រប់គ្រាន់សម្រាប់ការកាត់ប្រាក់។';
        } elseif (!is_numeric($amount) || $amount <= 0) {
            $_SESSION['error'] = 'ចំនួនទឹកប្រាក់កាត់ត្រូវតែជាលេខវិជ្ជមាន។';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO other_deductions (user_id, amount, reason, deduction_date, created_by_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $amount, $reason, $deduction_date, $_SESSION['user_id']]);
                $_SESSION['success'] = 'ការកាត់ប្រាក់ត្រូវបានបន្ថែមដោយជោគជ័យ។';
            } catch (PDOException $e) {
                $_SESSION['error'] = 'មានបញ្ហាក្នុងការបន្ថែមទិន្នន័យ៖ ' . $e->getMessage();
            }
        }
        header("Location: dashboard.php?view=deductions_bonuses&tab=deductions");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_deduction'])) {
        $deduction_id = $_POST['deduction_id'];
        try {
            $stmt = $conn->prepare("DELETE FROM other_deductions WHERE id = ?");
            $stmt->execute([$deduction_id]);
            $_SESSION['success'] = 'ការកាត់ប្រាក់ត្រូវបានលុបចោលដោយជោគជ័យ។';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'មានបញ្ហាក្នុងការលុបទិន្នន័យ៖ ' . $e->getMessage();
        }
        header("Location: dashboard.php?view=deductions_bonuses&tab=deductions");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_ot_bonus'])) {
        $user_id = $_POST['ot_user_id'];
        $ot_amount = filter_var($_POST['ot_amount'], FILTER_VALIDATE_FLOAT);
        $reason = trim($_POST['ot_reason']);
        $ot_month = $_POST['ot_month'];

        if (empty($user_id) || empty($ot_month) || $ot_amount === false) {
            $_SESSION['error'] = 'សូមបំពេញព័ត៌មាន OT ឲ្យបានគ្រប់គ្រាន់។';
        } elseif ($ot_amount < 0) {
            $_SESSION['error'] = 'ចំនួនប្រាក់ OT ត្រូវតែជាលេខវិជ្ជមាន ឬសូន្យ។';
        } else {
            $current_user_id = $_SESSION['user_id'];
            try {
                $checkStmt = $conn->prepare("SELECT id FROM ot_bonuses WHERE user_id = ? AND ot_month = ?");
                $checkStmt->execute([$user_id, $ot_month]);
                $existing_ot = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if ($existing_ot) {
                    $stmt = $conn->prepare("UPDATE ot_bonuses SET ot_amount = ?, reason = ?, recorded_by_id = ? WHERE id = ?");
                    $stmt->execute([$ot_amount, $reason, $current_user_id, $existing_ot['id']]);
                    $_SESSION['success'] = 'ប្រាក់ OT ត្រូវបានកែប្រែដោយជោគជ័យសម្រាប់ខែ ' . $ot_month . '។';
                } else {
                    $stmt = $conn->prepare("INSERT INTO ot_bonuses (user_id, ot_month, ot_amount, reason, recorded_by_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $ot_month, $ot_amount, $reason, $current_user_id]);
                    $_SESSION['success'] = 'ប្រាក់ OT ត្រូវបានបញ្ចូលដោយជោគជ័យ។';
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = 'មានបញ្ហាក្នុងការរក្សាទុកទិន្នន័យ OT៖ ' . $e->getMessage();
            }
        }
        header("Location: dashboard.php?view=deductions_bonuses&tab=ot_bonus&month=" . urlencode($ot_month));
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ot_bonus'])) {
        $ot_id = $_POST['ot_id'];
        $redirect_month = $_POST['redirect_month'] ?? date('Y-m');
        try {
            $stmt = $conn->prepare("DELETE FROM ot_bonuses WHERE id = ?");
            $stmt->execute([$ot_id]);
            $_SESSION['success'] = 'ប្រាក់ OT ត្រូវបានលុបចោលដោយជោគជ័យ។';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'មានបញ្ហាក្នុងការលុបទិន្នន័យ OT៖ ' . $e->getMessage();
        }
        header("Location: dashboard.php?view=deductions_bonuses&tab=ot_bonus&month=" . urlencode($redirect_month));
        exit();
    }

    // --- Loan management handlers ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_loan'])) {
        $loan_user_id = (int)($_POST['loan_user_id'] ?? 0);
        $loan_amount = filter_var($_POST['loan_amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $monthly_deduction = filter_var($_POST['monthly_deduction'] ?? null, FILTER_VALIDATE_FLOAT);
        $start_month = $_POST['start_month'] ?? '';
        $borrow_date = $_POST['borrow_date'] ?? '';
        $duration_months = isset($_POST['duration_months']) ? (int)$_POST['duration_months'] : null;
        $loan_note = trim($_POST['loan_note'] ?? '');
        $fund_request_note = trim($_POST['fund_request_note'] ?? '');

        if (!$loan_user_id || $loan_amount === false || $monthly_deduction === false || empty($start_month)) {
            $_SESSION['error'] = 'សូមបំពេញទឹកប្រាក់ ប្រាក់កាត់ប្រចាំខែ និងខែចាប់ផ្តើម។';
        } elseif ($loan_amount <= 0 || $monthly_deduction <= 0) {
            $_SESSION['error'] = 'ចំនួនទឹកប្រាក់ និងប្រាក់កាត់ប្រចាំខែ ត្រូវធំជាងសូន្យ។';
        } else {
            try {
                // Normalize inputs
                $start_month_str = substr($start_month, 0, 7);
                $borrow_date_val = !empty($borrow_date) ? $borrow_date : null;
                $duration_val = ($duration_months !== null && $duration_months > 0) ? $duration_months : null;

                $stmt = $conn->prepare("INSERT INTO employee_loans (user_id, loan_amount, remaining_balance, monthly_deduction, start_month, borrow_date, duration_months, status, note, fund_request_note, created_by_id) VALUES (?, ?, ?, ?, CONCAT(?, '-01'), ?, ?, 'active', ?, ?, ?)");
                $stmt->execute([$loan_user_id, $loan_amount, $loan_amount, $monthly_deduction, $start_month_str, $borrow_date_val, $duration_val, $loan_note, $fund_request_note, $_SESSION['user_id']]);
                $_SESSION['success'] = 'បំណុលត្រូវបានបន្ថែមដោយជោគជ័យ។';
            } catch (PDOException $e) {
                $_SESSION['error'] = 'មានបញ្ហាក្នុងការបន្ថែមបំណុល៖ ' . $e->getMessage();
            }
        }
        header("Location: dashboard.php?view=deductions_bonuses&tab=loan");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_loan'])) {
        $loan_id = (int)($_POST['loan_id'] ?? 0);
        $monthly_deduction = filter_var($_POST['monthly_deduction'] ?? null, FILTER_VALIDATE_FLOAT);
        $status = $_POST['status'] ?? '';
        $note = trim($_POST['note'] ?? '');
        $fund_request_note = trim($_POST['fund_request_note'] ?? '');
        $duration_months = isset($_POST['duration_months']) ? (int)$_POST['duration_months'] : null;
        if (!$loan_id || $monthly_deduction === false || $monthly_deduction <= 0) {
            $_SESSION['error'] = 'សូមបញ្ចូលប្រាក់កាត់ប្រចាំខែដែលត្រឹមត្រូវ។';
        } else {
            try {
                $valid_status = in_array($status, ['active','paused','closed']) ? $status : 'active';
                $stmt = $conn->prepare("UPDATE employee_loans SET monthly_deduction = ?, status = ?, note = ?, fund_request_note = ?, duration_months = ? WHERE id = ?");
                $duration_val = ($duration_months !== null && $duration_months > 0) ? $duration_months : null;
                $stmt->execute([$monthly_deduction, $valid_status, $note, $fund_request_note, $duration_val, $loan_id]);
                $_SESSION['success'] = 'បំណុលត្រូវបានកែប្រែដោយជោគជ័យ។';
            } catch (PDOException $e) {
                $_SESSION['error'] = 'មានបញ្ហាក្នុងការកែប្រែបំណុល៖ ' . $e->getMessage();
            }
        }
        header("Location: dashboard.php?view=deductions_bonuses&tab=loan");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_loan'])) {
        $loan_id = (int)($_POST['loan_id'] ?? 0);
        try {
            $stmt = $conn->prepare("DELETE FROM employee_loans WHERE id = ?");
            $stmt->execute([$loan_id]);
            $_SESSION['success'] = 'បំណុលត្រូវបានលុបដោយជោគជ័យ។';
        } catch (PDOException $e) {
            $_SESSION['error'] = 'មានបញ្ហាក្នុងការលុបបំណុល៖ ' . $e->getMessage();
        }
        header("Location: dashboard.php?view=deductions_bonuses&tab=loan");
        exit();
    }

    // Support two possible GET formats for month:
    // - Separate year and month (year=2025&month=11)
    // - Combined month input from <input type="month"> which sends month=YYYY-MM
    $current_year = (int)date('Y');
    $current_month_num = (int)date('m');
    $current_month_str = date('Y-m');

    if (isset($_GET['month'])) {
        $rawMonth = trim($_GET['month']);
        // If it's in YYYY-MM format (from <input type="month">), parse accordingly
        if (preg_match('/^\d{4}-\d{2}$/', $rawMonth)) {
            list($y, $m) = explode('-', $rawMonth);
            $current_year = (int)$y;
            $current_month_num = (int)$m;
            $current_month_str = sprintf('%04d-%02d', $current_year, $current_month_num);
        } else {
            // Fallback: treat as numeric month value
            $current_month_num = (int)$rawMonth ?: (int)date('m');
            $current_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
            $current_month_str = $current_year . '-' . str_pad($current_month_num, 2, '0', STR_PAD_LEFT);
        }
    } else {
        // If month not provided, allow separate year/month GET params
        $current_year = isset($_GET['year']) ? (int)$_GET['year'] : $current_year;
        $current_month_num = isset($_GET['month']) ? (int)$_GET['month'] : $current_month_num;
        $current_month_str = $current_year . '-' . str_pad($current_month_num, 2, '0', STR_PAD_LEFT);
    }
    
    $users_deductions_view = $conn->query("SELECT id, full_name FROM users WHERE status = 'active' AND LOWER(full_name) NOT IN ('admin','adminbt') AND LOWER(username) NOT IN ('admin','adminbt') ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    $stmt_deductions = $conn->prepare("SELECT od.*, u.full_name FROM other_deductions od JOIN users u ON od.user_id = u.id WHERE YEAR(od.deduction_date) = ? AND MONTH(od.deduction_date) = ? ORDER BY od.deduction_date DESC");
    $stmt_deductions->execute([$current_year, $current_month_num]);
    $deductions_list = $stmt_deductions->fetchAll(PDO::FETCH_ASSOC);
    // Compute total deductions amount for the selected month
    $total_deductions_amount = 0.0;
    foreach ($deductions_list as $d) {
        $total_deductions_amount += (float)($d['amount'] ?? 0);
    }
    $existing_deduction_reasons = $conn->query("SELECT DISTINCT reason FROM other_deductions WHERE reason IS NOT NULL AND reason != '' ORDER BY reason ASC")->fetchAll(PDO::FETCH_ASSOC);

    $stmt_ot = $conn->prepare("SELECT ob.*, u.full_name FROM ot_bonuses ob JOIN users u ON ob.user_id = u.id WHERE ob.ot_month = ? ORDER BY u.full_name ASC");
    $stmt_ot->execute([$current_month_str]);
    $ot_bonuses_list = $stmt_ot->fetchAll(PDO::FETCH_ASSOC);
    $existing_ot_reasons = $conn->query("SELECT DISTINCT reason FROM ot_bonuses WHERE reason IS NOT NULL AND reason != '' ORDER BY reason ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Pre-compute daily rates for OT day estimation in list display
    $ot_daily_rate_map = [];
    try {
        $users_for_ot = $conn->query("SELECT id, base_salary, department FROM users WHERE status = 'active' AND LOWER(full_name) NOT IN ('admin','adminbt') AND LOWER(username) NOT IN ('admin','adminbt')")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users_for_ot as $u) {
            $base = (float)($u['base_salary'] ?? 0);
            $dep = $u['department'] ?? '';
            $ot_daily_rate_map[$u['id']] = ($base > 0) ? (($dep === 'Worker') ? ($base / 28) : ($base / 26)) : 0;
        }
    } catch (PDOException $e) {
        // Ignore mapping errors; days will show as N/A
    }

    // Fetch loans for listing
    $show_detailed = isset($_GET['user_id']) && !empty($_GET['user_id']);
    try {
        if ($show_detailed) {
            $user_id = (int)$_GET['user_id'];
            $stmt_loans_dashboard = $conn->prepare(
                "SELECT el.*, u.full_name 
                 FROM employee_loans el 
                 JOIN users u ON el.user_id = u.id 
                 WHERE el.user_id = ?
                 ORDER BY el.status DESC, el.start_month ASC"
            );
            $stmt_loans_dashboard->execute([$user_id]);
            $loans_list = $stmt_loans_dashboard->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt_loans_dashboard = $conn->prepare(
                "SELECT u.full_name, u.id as user_id, 
                        SUM(el.loan_amount) as total_loan_amount, 
                        SUM(el.remaining_balance) as total_remaining, 
                        SUM(CASE WHEN el.status = 'active' AND el.remaining_balance > 0 THEN el.monthly_deduction ELSE 0 END) as total_monthly_deduction, 
                        MIN(COALESCE(el.borrow_date, el.start_month)) as earliest_borrow_date, 
                        GROUP_CONCAT(DISTINCT el.fund_request_note SEPARATOR '; ') as fund_requests
                 FROM employee_loans el 
                 JOIN users u ON el.user_id = u.id 
                 GROUP BY el.user_id, u.full_name 
                 ORDER BY u.full_name ASC"
            );
            $stmt_loans_dashboard->execute();
            $loans_list = $stmt_loans_dashboard->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $loans_list = [];
    }

    // Handle CSV export for loans
    if (isset($_GET['export']) && $_GET['export'] === 'csv' && $active_tab === 'loan') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=loans_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        // Add UTF-8 BOM for proper Khmer character display in Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Calculate totals for summary
        $total_loans_count = isset($loans_list) && is_array($loans_list) ? count($loans_list) : 0;
        $total_remaining_sum = 0.0;
        $total_monthly_active_sum = 0.0;
        $total_loan_amount_sum = 0.0;
        if (!empty($loans_list)) {
            foreach ($loans_list as $loan_sum) {
                $total_remaining_sum += (float)($loan_sum['total_remaining'] ?? 0);
                $total_loan_amount_sum += (float)($loan_sum['total_loan_amount'] ?? 0);
                $total_monthly_active_sum += (float)($loan_sum['total_monthly_deduction'] ?? 0);
            }
        }

        // Add title and summary
        fputcsv($output, ['បញ្ជីបំណុល - Loans Report']);
        fputcsv($output, ['កាលបរិច្ឆេទទាញយក - Export Date', date('d/m/Y')]);
        fputcsv($output, []);
        fputcsv($output, ['សរុបទឹកប្រាក់ខ្ចី - Total Loan Amount', '$' . number_format($total_loan_amount_sum, 2)]);
        fputcsv($output, ['សរុបនៅសល់ - Total Remaining', '$' . number_format($total_remaining_sum, 2)]);
        fputcsv($output, ['កាត់ប្រចាំខែសរុប (Active) - Total Monthly Deductions (Active)', '$' . number_format($total_monthly_active_sum, 2)]);
        fputcsv($output, ['ចំនួនបំណុល - Total Loans', $total_loans_count]);
        fputcsv($output, []);

        // Add data headers
        fputcsv($output, ['ឈ្មោះបុគ្គលិក - Employee Name', 'ទឹកប្រាក់ខ្ចីសរុប - Total Loan Amount', 'នៅសល់សរុប - Total Remaining', 'កាត់ប្រចាំខែសរុប - Total Monthly Deduction', 'ថ្ងៃខ្ចីដំបូង - Earliest Borrow Date', 'សំណើបងទឹកលុយ - Fund Requests']);

        // Add data rows
        foreach ($loans_list as $loan) {
            fputcsv($output, [
                $loan['full_name'],
                '$' . number_format($loan['total_loan_amount'], 2),
                '$' . number_format($loan['total_remaining'], 2),
                '$' . number_format($loan['total_monthly_deduction'], 2),
                !empty($loan['earliest_borrow_date']) ? date('d/m/Y', strtotime($loan['earliest_borrow_date'])) : 'N/A',
                $loan['fund_requests'] ?? ''
            ]);
        }
        fclose($output);
        exit();
    }

    // Handle CSV export for deductions
    if (isset($_GET['export']) && $_GET['export'] === 'csv' && $active_tab === 'deductions') {
        $export_month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
        $export_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=deductions_' . $export_year . '-' . str_pad($export_month, 2, '0', STR_PAD_LEFT) . '.csv');
        $output = fopen('php://output', 'w');
        // Add UTF-8 BOM for proper Khmer character display in Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Calculate totals for summary
        $total_deductions = 0.0;
        $total_deductions_count = isset($deductions_list) && is_array($deductions_list) ? count($deductions_list) : 0;
        if (!empty($deductions_list)) {
            foreach ($deductions_list as $deduction) {
                $total_deductions += (float)($deduction['amount'] ?? 0);
            }
        }

        // Add title and summary
        fputcsv($output, ['បញ្ជីការកាត់ប្រាក់ - Deductions Report']);
        fputcsv($output, ['កាលបរិច្ឆេទទាញយក - Export Date', date('d/m/Y')]);
        fputcsv($output, ['ខែ - Month', date('F Y', mktime(0, 0, 0, $export_month, 1, $export_year))]);
        fputcsv($output, []);
        fputcsv($output, ['សរុបការកាត់ប្រាក់ - Total Deductions', '$' . number_format($total_deductions, 2)]);
        fputcsv($output, ['ចំនួនការកាត់ - Total Deduction Records', $total_deductions_count]);
        fputcsv($output, []);

        // Add data headers
        fputcsv($output, ['ឈ្មោះបុគ្គលិក - Employee Name', 'ចំនួនទឹកប្រាក់ - Amount', 'មូលហេតុ - Reason', 'កាលបរិច្ឆេទ - Date']);

        // Add data rows
        foreach ($deductions_list as $deduction) {
            fputcsv($output, [
                $deduction['full_name'],
                '$' . number_format($deduction['amount'], 2),
                $deduction['reason'],
                date('d/m/Y', strtotime($deduction['deduction_date']))
            ]);
        }
        fclose($output);
        exit();
    }

    // Handle CSV export for OT bonuses
    if (isset($_GET['export']) && $_GET['export'] === 'csv' && $active_tab === 'ot_bonus') {
        $export_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=ot_bonuses_' . str_replace('-', '_', $export_month) . '.csv');
        $output = fopen('php://output', 'w');
        // Add UTF-8 BOM for proper Khmer character display in Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Calculate totals for summary
        $total_ot_amount = 0.0;
        $total_ot_count = isset($ot_bonuses_list) && is_array($ot_bonuses_list) ? count($ot_bonuses_list) : 0;
        if (!empty($ot_bonuses_list)) {
            foreach ($ot_bonuses_list as $ot) {
                $total_ot_amount += (float)($ot['ot_amount'] ?? 0);
            }
        }

        // Add title and summary
        fputcsv($output, ['បញ្ជីប្រាក់ថែមម៉ោង - OT Bonuses Report']);
        fputcsv($output, ['កាលបរិច្ឆេទទាញយក - Export Date', date('d/m/Y')]);
        fputcsv($output, ['ខែ - Month', date('F Y', strtotime($export_month . '-01'))]);
        fputcsv($output, []);
        fputcsv($output, ['សរុបប្រាក់ OT - Total OT Amount', '$' . number_format($total_ot_amount, 2)]);
        fputcsv($output, ['ចំនួនកំណត់ត្រា OT - Total OT Records', $total_ot_count]);
        fputcsv($output, []);

        // Add data headers
        fputcsv($output, ['ឈ្មោះបុគ្គលិក - Employee Name', 'ខែ - Month', 'ចំនួនប្រាក់ OT - OT Amount', 'ចំនួនថ្ងៃ (OT) - OT Days', 'កំណត់សម្គាល់ - Notes']);

        // Add data rows
        foreach ($ot_bonuses_list as $ot) {
            $daily_rate = $ot_daily_rate_map[$ot['user_id']] ?? 0;
            $days_display = $daily_rate > 0 ? number_format(($ot['ot_amount'] / $daily_rate), 2) : 'N/A';
            fputcsv($output, [
                $ot['full_name'],
                $ot['ot_month'],
                '$' . number_format($ot['ot_amount'], 2),
                $days_display,
                $ot['reason'] ?: 'N/A'
            ]);
        }
        fclose($output);
        exit();
    }
}


// =========================================================================
// --- NEW: PAYROLL APPROVAL VIEW LOGIC ---
// =========================================================================
if ($view === 'payroll_approval') {
    requirePermission('payroll_approval', $conn);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_id'])) {
        $run_id = (int)$_POST['run_id'];
        $action = $_POST['action'] ?? '';
        $current_user_id = $_SESSION['user_id'];

        try {
            $conn->beginTransaction();
            if ($action === 'approve') {
                $stmt = $conn->prepare(
                    "UPDATE payroll_runs 
                     SET status = 'approved', approved_by_id = :user_id, approved_at = NOW() 
                     WHERE id = :run_id AND status = 'calculated'"
                );
                $stmt->execute([':user_id' => $current_user_id, ':run_id' => $run_id]);
                $_SESSION['success'] = "បញ្ជីបើកប្រាក់បៀវត្ស (ID: $run_id) ត្រូវបានអនុម័តដោយជោគជ័យ!";

            } elseif ($action === 'reject') {
                $reason = trim($_POST['rejection_reason'] ?? '');
                if (empty($reason)) {
                    throw new Exception("សូមបញ្ចូលហេតុផលនៃការបដិសេធ។");
                }
                $stmt = $conn->prepare(
                    "UPDATE payroll_runs 
                     SET status = 'rejected', approved_by_id = :user_id, approved_at = NOW(), notes = :reason 
                     WHERE id = :run_id AND status = 'calculated'"
                );
                $stmt->execute([':user_id' => $current_user_id, ':reason' => $reason, ':run_id' => $run_id]);
                $_SESSION['success'] = "បញ្ជីបើកប្រាក់បៀវត្ស (ID: $run_id) ត្រូវបានបដិសេធ។";
            
            } elseif ($action === 'delete') {
                // First, delete the details associated with the run
                $stmt_details = $conn->prepare("DELETE FROM payroll_run_details WHERE payroll_run_id = :run_id");
                $stmt_details->execute([':run_id' => $run_id]);

                // Then, delete the main run itself, only if it's in a deletable state
                $stmt_run = $conn->prepare("DELETE FROM payroll_runs WHERE id = :run_id AND status IN ('calculated', 'rejected')");
                $stmt_run->execute([':run_id' => $run_id]);
                
                if ($stmt_run->rowCount() > 0) {
                    $_SESSION['success'] = "បញ្ជីបើកប្រាក់បៀវត្ស (ID: $run_id) ត្រូវបានលុបចោលដោយជោគជ័យ!";
                } else {
                    throw new Exception("មិនអាចលុបបានទេ ឬរកមិនឃើញ។ ប្រហែលជាវាត្រូវបានអនុម័តរួចហើយ។");
                }
            }
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['error'] = "មានបញ្ហា៖ " . $e->getMessage();
        }
        header("Location: dashboard.php?view=payroll_approval");
        exit();
    }

    try {
        $stmt_pending = $conn->prepare(
            "SELECT pr.*, u.full_name as creator_name 
             FROM payroll_runs pr
             JOIN users u ON pr.created_by_id = u.id
             WHERE pr.status = 'calculated' OR pr.status = 'rejected'
             ORDER BY pr.month DESC"
        );
        $stmt_pending->execute();
        $pending_runs = $stmt_pending->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error fetching pending payrolls: " . $e->getMessage();
        $pending_runs = [];
    }

    // --- REMOVED: History fetching logic is removed ---

}


// =========================================================================
// --- NEW: PAYROLL PAYSLIP VIEW LOGIC ---
// =========================================================================
if ($view === 'payroll_payslip') {
    requirePermission('payroll_payslip', $conn);
    
    $run_id = isset($_GET['run_id']) ? (int)$_GET['run_id'] : 0;

    if ($run_id > 0) {
      $name_filter = isset($_GET['name_filter']) ? trim($_GET['name_filter']) : '';
      $sql_where_clause = '';
      $sql_params = [$run_id];

      if (!empty($name_filter)) {
        $sql_where_clause = " AND prd.full_name LIKE ?";
        $sql_params[] = '%' . $name_filter . '%';
      }
      
      $run_info_stmt = $conn->prepare("SELECT * FROM payroll_runs WHERE id = ? AND status IN ('approved', 'paid')");
      $run_info_stmt->execute([$run_id]);
      $run_info = $run_info_stmt->fetch(PDO::FETCH_ASSOC);

      if (!$run_info) {
        $_SESSION['error'] = 'រកមិនឃើញបញ្ជីបើកប្រាក់បៀវត្សដែលបានស្នើសុំទេ';
        header("Location: dashboard.php?view=payroll_payslip");
        exit();
      }

      $payslips_stmt = $conn->prepare(
        "SELECT prd.*, u.department, u.role, u.nssf_id, u.bank_name, u.bank_account_number, u.bank_qr_code_url
        FROM payroll_run_details prd
        JOIN users u ON prd.user_id = u.id
        WHERE prd.payroll_run_id = ?"
        . $sql_where_clause .
        " ORDER BY prd.full_name ASC"
      );
      $payslips_stmt->execute($sql_params);
      // Corrected the undefined variable
      $payslips = $payslips_stmt->fetchAll(PDO::FETCH_ASSOC);

      $payslip_user_ids = array_column($payslips, 'user_id');
      $pay_month_str = date('Y-m', strtotime($run_info['month']));
      $start_of_month = date('Y-m-01', strtotime($run_info['month']));
      $end_of_month = date('Y-m-t', strtotime($run_info['month']));

      $detailed_deductions = [];
      $detailed_ot_bonuses = [];

      if (!empty($payslip_user_ids)) {
        $in_clause = implode(',', array_fill(0, count($payslip_user_ids), '?'));
        $deductions_stmt = $conn->prepare(
          "SELECT user_id, reason, amount FROM other_deductions WHERE user_id IN ($in_clause) AND deduction_date BETWEEN ? AND ? ORDER BY deduction_date ASC"
        );
        $params = array_merge($payslip_user_ids, [$start_of_month, $end_of_month]);
        $deductions_stmt->execute($params);
        $deductions_raw = $deductions_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($deductions_raw as $deduction) {
            $detailed_deductions[$deduction['user_id']][] = ['reason' => $deduction['reason'], 'amount' => $deduction['amount']];
        }
        
        $ot_bonuses_stmt = $conn->prepare("SELECT user_id, reason, ot_amount AS amount FROM ot_bonuses WHERE user_id IN ($in_clause) AND ot_month = ? ORDER BY recorded_at ASC");
        $ot_params = array_merge($payslip_user_ids, [$pay_month_str]);
        $ot_bonuses_stmt->execute($ot_params);
        $ot_bonuses_raw = $ot_bonuses_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ot_bonuses_raw as $ot_bonus) {
            $detailed_ot_bonuses[$ot_bonus['user_id']][] = ['reason' => $ot_bonus['reason'], 'amount' => $ot_bonus['amount']];
        }

        // Also include loan repayments from loan_payments for the run month
        try {
            $loan_in_clause = implode(',', array_fill(0, count($payslip_user_ids), '?'));
            $loan_stmt = $conn->prepare(
                "SELECT user_id, amount FROM loan_payments WHERE user_id IN ($loan_in_clause) AND month = ? ORDER BY created_at ASC"
            );
            $loan_params = array_merge($payslip_user_ids, [$start_of_month]);
            $loan_stmt->execute($loan_params);
            $loan_rows = $loan_stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($loan_rows as $lr) {
                $detailed_deductions[$lr['user_id']][] = ['reason' => 'Loan repayment', 'amount' => $lr['amount']];
            }
        } catch (PDOException $e) {
            error_log('Payslip loan deduction fetch failed: ' . $e->getMessage());
        }
      }
    } else {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_paid') {
            $posted_run_id = (int)$_POST['run_id'];
            try {
                $stmt = $conn->prepare("UPDATE payroll_runs SET status = 'paid' WHERE id = :run_id AND status = 'approved'");
                $stmt->execute([':run_id' => $posted_run_id]);
                if ($stmt->rowCount() > 0) {
                    $_SESSION['success'] = "បញ្ជីបើកប្រាក់បៀវត្ស (ID: $posted_run_id) ត្រូវបានសម្គាល់ថាបានទូទាត់រួចរាល់!";
                } else {
                    $_SESSION['error'] = 'មិនអាចដំណើរការបានទេ។ បញ្ជីនេះប្រហែលមិនស្ថិតក្នុងស្ថានភាព "approved" ទេ។';
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = 'មានបញ្ហា៖ ' . $e->getMessage();
            }
            header("Location: dashboard.php?view=payroll_payslip");
            exit();
        }

        $stmt_approved = $conn->prepare("SELECT pr.*, u.full_name as approver_name FROM payroll_runs pr JOIN users u ON pr.approved_by_id = u.id WHERE pr.status = 'approved' ORDER BY pr.month DESC");
        $stmt_approved->execute();
        $approved_runs = $stmt_approved->fetchAll(PDO::FETCH_ASSOC);

        $stmt_paid = $conn->prepare("SELECT pr.*, u.full_name as approver_name FROM payroll_runs pr JOIN users u ON pr.approved_by_id = u.id WHERE pr.status = 'paid' ORDER BY pr.month DESC LIMIT 20");
        $stmt_paid->execute();
        $paid_runs = $stmt_paid->fetchAll(PDO::FETCH_ASSOC);
    }
}


// =========================================================================
// --- NEW: PERMISSIONS VIEW LOGIC ---
// =========================================================================
if ($view === 'permissions') {
    requirePermission('permissions', $conn);
    $all_roles = ['admin', 'employee', 'accounting', 'hr', 'administration'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $conn->beginTransaction();
            $action = $_POST['action'] ?? '';

            if ($action === 'save_menu_item') {
                $id = $_POST['menu_id'] ?? null;
                $menu_key = trim($_POST['menu_key']);
                $menu_name = trim($_POST['menu_name']);
                $parent_key = empty($_POST['parent_key']) ? null : trim($_POST['parent_key']);
                $menu_order = $_POST['menu_order'] ?? 0;

                if ($id) {
                    $stmt = $conn->prepare("UPDATE menu_permissions SET menu_key = ?, menu_name = ?, parent_key = ?, menu_order = ? WHERE id = ?");
                    $stmt->execute([$menu_key, $menu_name, $parent_key, $menu_order, $id]);
                    $_SESSION['success'] = 'ម៉ឺនុយត្រូវបានធ្វើបច្ចុប្បន្នភាពដោយជោគជ័យ!';
                } else {
                    $stmt = $conn->prepare("INSERT INTO menu_permissions (menu_key, menu_name, parent_key, menu_order, allowed_roles) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$menu_key, $menu_name, $parent_key, $menu_order, 'admin']);
                    $_SESSION['success'] = 'ម៉ឺនុយថ្មីត្រូវបានបន្ថែមដោយជោគជ័យ!';
                }
            } elseif ($action === 'delete_menu_item') {
                $id = $_POST['menu_id'];
                $stmt_get_key = $conn->prepare("SELECT menu_key FROM menu_permissions WHERE id = ?");
                $stmt_get_key->execute([$id]);
                $menu_key_to_delete = $stmt_get_key->fetchColumn();
                
                if($menu_key_to_delete) {
                    $stmt_check_children = $conn->prepare("SELECT COUNT(*) FROM menu_permissions WHERE parent_key = ?");
                    $stmt_check_children->execute([$menu_key_to_delete]);
                    if ($stmt_check_children->fetchColumn() > 0) {
                        throw new Exception('មិនអាចលុបបានទេ ព្រោះម៉ឺនុយនេះមានម៉ឺនុយកូន។ សូមលុបម៉ឺនុយកូនជាមុនសិន។');
                    }
                }

                $stmt_delete = $conn->prepare("DELETE FROM menu_permissions WHERE id = ?");
                $stmt_delete->execute([$id]);
                $_SESSION['success'] = 'ម៉ឺនុយត្រូវបានលុបដោយជោគជ័យ!';

            } elseif ($action === 'save_permissions') {
                $stmt_get_keys = $conn->query("SELECT menu_key FROM menu_permissions");
                if ($stmt_get_keys === false) {
                    throw new Exception("Error fetching menu keys from database.");
                }
                $all_menu_keys = $stmt_get_keys->fetchAll(PDO::FETCH_COLUMN);
                
                $update_count = 0;
                
                $stmt_update = $conn->prepare("UPDATE menu_permissions SET allowed_roles = :roles WHERE menu_key = :key");
                if ($stmt_update === false) {
                    throw new Exception("Database prepare statement failed for update.");
                }

                foreach ($all_menu_keys as $menu_key) {
                    $allowed_roles = isset($_POST['permissions'][$menu_key]) && is_array($_POST['permissions'][$menu_key]) ? $_POST['permissions'][$menu_key] : [];
                    $roles_string = implode(',', $allowed_roles);

                    $success = $stmt_update->execute([
                        ':roles' => $roles_string,
                        ':key' => $menu_key
                    ]);

                    if (!$success) {
                        throw new Exception("Update failed for menu key: " . htmlspecialchars($menu_key));
                    }
                    $update_count += $stmt_update->rowCount();
                }
                $_SESSION['success'] = "ការកំណត់សិទ្ធត្រូវបានធ្វើបច្ចុប្បន្នភាពដោយជោគជ័យ! (" . $update_count . " rows affected)";
            }
            
            $conn->commit();
            header("Location: dashboard.php?view=permissions");
            exit();
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Permissions page error: " . $e->getMessage());
            $_SESSION['error'] = 'មានបញ្ហា! ' . $e->getMessage();
            header("Location: dashboard.php?view=permissions");
            exit();
        }
    }

    $stmt_menus = $conn->query("SELECT * FROM menu_permissions ORDER BY parent_key ASC, menu_order ASC, menu_name ASC");
    $all_menus_for_permissions = $stmt_menus->fetchAll(PDO::FETCH_ASSOC);
    $menu_map = [];
    foreach ($all_menus_for_permissions as $menu) {
        $menu['children'] = [];
        $menu_map[$menu['id']] = $menu;
    }
    $tree = [];
    foreach ($menu_map as $id => &$node) {
        $parent_id = null;
        if ($node['parent_key']) {
            foreach($menu_map as $potential_parent) {
                if ($potential_parent['menu_key'] === $node['parent_key']) {
                    $parent_id = $potential_parent['id'];
                    break;
                }
            }
        }
        if ($parent_id && isset($menu_map[$parent_id])) { 
            $menu_map[$parent_id]['children'][] =& $node; 
        } else { 
            $tree[] =& $node; 
        }
    }
    unset($node);
    $grouped_menus = $tree;

    function render_menu_rows($menus, $all_roles, $is_child = false) {
        foreach ($menus as $menu) {
            $current_roles = !empty($menu['allowed_roles']) ? explode(',', $menu['allowed_roles']) : [];
            $row_class = $is_child ? 'child-row' : '';
            $icon = !empty($menu['children']) ? '<i class="fas fa-folder text-accent-color me-2"></i>' : ($is_child ? '<i class="fas fa-file-alt me-3 text-secondary"></i>' : '<i class="fas fa-bars me-2"></i>');
            
            echo "<tr class='{$row_class}'>";
            echo "<td class='font-semibold align-middle'>{$icon}<span>" . htmlspecialchars($menu['menu_name']) . "</span><span class='d-block text-xs text-secondary ms-4'>(key: " . htmlspecialchars($menu['menu_key']) . ")</span></td>";
            echo "<td><div class='d-flex flex-wrap gap-4'>";
            foreach ($all_roles as $role) {
                $checked = in_array($role, $current_roles) ? 'checked' : '';
                echo "<div class='form-check'><input class='form-check-input' type='checkbox' name='permissions[".htmlspecialchars($menu['menu_key'])."][]' value='".htmlspecialchars($role)."' id='perm_".$menu['menu_key']."_".$role."' {$checked}><label class='form-check-label' for='perm_".$menu['menu_key']."_".$role."'>".ucfirst($role)."</label></div>";
            }
            echo "</div></td>";
            echo "<td class='text-end'>";
            echo "<button type='button' class='btn btn-sm btn-outline-warning me-2' onclick='openMenuModal(".json_encode($menu).")'><i class='fas fa-edit'></i> កែប្រែ</button>";
            
            echo "<button type='button' 
                            class='btn btn-sm btn-outline-danger' 
                            data-bs-toggle='modal' 
                            data-bs-target='#deleteMenuModal'
                            data-menu-id='{$menu['id']}'
                            data-menu-name='" . htmlspecialchars($menu['menu_name'], ENT_QUOTES) . "'>
                        <i class='fas fa-trash'></i> លុប
                  </button>";
            
            echo "</td>";
            echo "</tr>";
            
            if (!empty($menu['children'])) { 
                render_menu_rows($menu['children'], $all_roles, true); 
            }
        }
    }
}


try {
    $stmt = $conn->prepare("SELECT a.id, a.title, a.date, a.text, GROUP_CONCAT(u.full_name) as full_names FROM announcements a LEFT JOIN announcement_users au ON a.id = au.announcement_id LEFT JOIN users u ON au.user_id = u.id GROUP BY a.id ORDER BY a.created_at DESC LIMIT 5");
    $stmt->execute();
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch announcements error: " . $e->getMessage());
    $announcements = [];
    $_SESSION['error'] = "កំហុសក្នុងការទាញការជូនដំណឹង!";
}
// =========================================================================
// --- MESSAGE HANDLING ---
// =========================================================================
$success = '';
$error = '';
if (isset($_GET['success'])) {
    $success = match ($_GET['success']) {
        'approved' => 'សំណើត្រូវបានអនុម័តដោយជោគជ័យ!',
        'rejected' => 'សំណើត្រូវបានបដិសេធដោយជោគជ័យ!',
        'deactivated' => 'គណនីត្រូវបានបិទដោយជោគជ័យ!',
        'activated' => 'គណនីត្រូវបានបើកឡើងវិញដោយជោគជ័យ!',
        'updated' => 'ព័ត៌មានអ្នកប្រើប្រាស់ត្រូវបានធ្វើបច្ចុប្បន្នភាពដោយជោគជ័យ!',
        'user_added' => 'អ្នកប្រើប្រាស់ថ្មីត្រូវបានបន្ថែមដោយជោគជ័យ!',
        default => ''
    };
}
if (isset($_SESSION['error'])) {
     $error = htmlspecialchars($_SESSION['error']);
     unset($_SESSION['error']);
} elseif (isset($_GET['error'])) {
    $error = match ($_GET['error']) {
        'invalid_request' => 'លេខសំណើមិនត្រឹមត្រូវ។',
        'no_changes' => 'គ្មានការផ្លាស់ប្តូរ ឬសំណើមិនមែនជាការរង់ចាំ។',
        'database_error' => 'កំហុសមូលដ្ឋានទិន្នន័យបានកើតឡើង។',
        default => 'កំហុសមិនស្គាល់បានកើតឡើង។'
    };
}
if (isset($_SESSION['success'])) {
     $success = htmlspecialchars($_SESSION['success']);
     unset($_SESSION['success']);
}


// =========================================================================
// --- NEW: SETTINGS VIEW LOGIC ---
// =========================================================================
if ($view === 'settings') {
    requirePermission('permissions', $conn); // Using 'permissions' as the proxy for settings access

    // --- Settings Helper Functions ---
    if (!function_exists('loadBotToken')) {
        function loadBotToken($db): string {
            try {
                $stmt = $db->query("SELECT bot_token FROM telegram_settings WHERE id = 1");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result['bot_token'] ?? '';
            } catch (PDOException $e) { return ''; }
        }
    }

    if (!function_exists('loadTelegramGroups')) {
        function loadTelegramGroups($db): array {
            $groups = [];
            try {
                $stmt = $db->query("SELECT group_id, name, chat_id, report_format FROM telegram_groups ORDER BY group_id ASC");
                while ($group = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $threadStmt = $db->prepare("SELECT thread_map_id, position, category, thread_id FROM telegram_group_threads WHERE group_id = :group_id ORDER BY category ASC, position ASC");
                    $threadStmt->execute(['group_id' => $group['group_id']]);
                    $group['thread_ids'] = $threadStmt->fetchAll(PDO::FETCH_ASSOC);
                    $groups[] = $group;
                }
            } catch (PDOException $e) { }
            return $groups;
        }
    }

    $category_types = ['department', 'position', 'branch'];
    $khmer_names = ['department' => 'ដេប៉ាតឺម៉ង់', 'position' => 'តួនាទី បុគ្គលិក', 'branch' => 'សាខា'];
    $telegramAllCategories = ['ដេប៉ាតឺម៉ង់/ផ្នែក', 'ផ្នែក ឃ្លាំងទំនិញ (318)', 'ផ្នែក SK Cosmetics(ភ្នំពេញ)', 'ផ្នែក SK Cosmetics(តាមបណ្តាខេត្ត និង សាខា)', 'រោងចក្រផលិត និង វេចខ្ចប់', 'ផ្សេងៗ'];

    // --- Handle POST Actions for Settings ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings_action'])) {
        $action = $_POST['settings_action'];
        try {
            switch ($action) {
                case 'update_bot_token':
                    $new_token = filter_var($_POST['bot_token'] ?? '', FILTER_SANITIZE_STRING);
                    if (empty($new_token)) throw new Exception("Bot Token មិនអាចទទេបានទេ!");
                    $stmt = $conn->prepare("INSERT INTO telegram_settings (id, bot_token) VALUES (1, :bot_token) ON DUPLICATE KEY UPDATE bot_token = :bot_token");
                    $stmt->execute(['bot_token' => $new_token]);
                    $_SESSION['success'] = "ធ្វើបច្ចុប្បន្នភាព Bot Token ជោគជ័យ!";
                    break;
                case 'add_group':
                    $stmt = $conn->prepare("INSERT INTO telegram_groups (name, chat_id, report_format) VALUES (?, ?, ?)");
                    $stmt->execute([$_POST['group_name'], $_POST['chat_id'], $_POST['report_format']]);
                    $_SESSION['success'] = "បន្ថែមក្រុមថ្មីជោគជ័យ!";
                    break;
                case 'update_group':
                    $stmt = $conn->prepare("UPDATE telegram_groups SET name = ?, chat_id = ?, report_format = ? WHERE group_id = ?");
                    $stmt->execute([$_POST['group_name'], $_POST['chat_id'], $_POST['report_format'], $_POST['group_id']]);
                    $_SESSION['success'] = "កែប្រែព័ត៌មានក្រុមជោគជ័យ!";
                    break;
                case 'delete_group':
                    $stmt = $conn->prepare("DELETE FROM telegram_groups WHERE group_id = ?");
                    $stmt->execute([$_POST['group_id']]);
                    $_SESSION['success'] = "លុបក្រុមជោគជ័យ!";
                    break;
                case 'add_thread':
                    $stmt = $conn->prepare("INSERT INTO telegram_group_threads (group_id, position, category, thread_id) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$_POST['group_id'], $_POST['thread_name'], $_POST['thread_category'], $_POST['thread_id']]);
                    $_SESSION['success'] = "បន្ថែម Thread ជោគជ័យ!";
                    break;
                case 'update_thread':
                    $stmt = $conn->prepare("UPDATE telegram_group_threads SET position = ?, category = ?, thread_id = ? WHERE thread_map_id = ?");
                    $stmt->execute([$_POST['thread_name'], $_POST['thread_category'], $_POST['thread_id'], $_POST['thread_map_id']]);
                    $_SESSION['success'] = "កែប្រែ Thread ជោគជ័យ!";
                    break;
                case 'delete_thread':
                    $stmt = $conn->prepare("DELETE FROM telegram_group_threads WHERE thread_map_id = ?");
                    $stmt->execute([$_POST['thread_map_id']]);
                    $_SESSION['success'] = "លុប Thread ជោគជ័យ!";
                    break;
                case 'add_category':
                    $stmt = $conn->prepare("INSERT INTO categories (type, name) VALUES (?, ?)");
                    $stmt->execute([$_POST['category_type'], $_POST['category_name']]);
                    $_SESSION['success'] = "បន្ថែម " . $khmer_names[$_POST['category_type']] . " ជោគជ័យ!";
                    break;
                case 'edit_category':
                    $stmt = $conn->prepare("UPDATE categories SET name = ?, type = ? WHERE id = ?");
                    $stmt->execute([$_POST['category_name'], $_POST['category_type'], $_POST['category_id']]);
                    $_SESSION['success'] = "កែប្រែប្រភេទ ជោគជ័យ!";
                    break;
                case 'delete_category':
                    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([$_POST['category_id']]);
                    $_SESSION['success'] = "លុបប្រភេទ ជោគជ័យ!";
                    break;
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "កំហុស៖ " . $e->getMessage();
        }
        header("Location: dashboard.php?view=settings");
        exit();
    }

    // --- Load Data for Display ---
    $telegramBotToken = loadBotToken($conn);
    $telegramGroups = loadTelegramGroups($conn);
    $stmt_cats = $conn->query("SELECT id, type, name FROM categories ORDER BY type, name");
    $dynamic_categories = [];
    foreach ($stmt_cats->fetchAll(PDO::FETCH_ASSOC) as $cat) {
        $dynamic_categories[$cat['type']][] = $cat;
    }
}


// =========================================================================
// --- HTML RENDERING ---
// =========================================================================
$page_title = match($view) {
    'reports' => 'របាយការណ៍ប្រចាំថ្ងៃ',
    'request_reports' => 'របាយការណ៍សំណើ',
    'checklist' => 'បញ្ជីការងារ',
    'inactive_users' => 'គណនីបានបិទ',
    'payroll_calculation' => 'ការគណនាបៀវត្ស',
    'payroll_approval' => 'ដំណើរការអនុម័តបៀវត្ស',
    'payroll_payslip' => 'បង្កើត និងមើល Payslip',
    'permissions' => 'គ្រប់គ្រង Sidebar និងការកំណត់សិទ្ធ',
    'settings' => 'ការកំណត់ប្រព័ន្ធ',
    'deductions_bonuses' => 'គ្រប់គ្រងការកាត់ប្រាក់ & ប្រាក់ OT',
    'manage_employees' => 'គ្រប់គ្រងបុគ្គលិក',
    'meetings' => 'បញ្ជីកិច្ចប្រជុំ (Meetings)',
    'post_meeting' => 'បង្ហោះកិច្ចប្រជុំ (Post Meeting)',
    default => 'ផ្ទាំងគ្រប់គ្រង'
};

?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ប្រព័ន្ធគ្រប់គ្រងធនធានមនុស្ស (HRM) - <?php echo $page_title; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#6366f1',
                        'primary-light': '#8b5cf6',
                        'primary-dark': '#4f46e5',
                        accent: '#f59e0b',
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;600;700&family=Kantumruy+Pro:wght@400;500;700&family=Moul&display=swap" rel="stylesheet">
    <!-- Preconnects to speed up third-party resources -->
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <!-- Load Bootstrap CSS using preload to reduce render-blocking -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></noscript>
    <link rel="manifest" href="../manifest.json">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

    <!-- Cropper.js CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" />

    <style>
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #06b6d4;
            --bg-main: #f1f5f9;
            --bg-card: rgba(255, 255, 255, 0.8);
            --border-glass: rgba(255, 255, 255, 0.4);
            --text-main: #1e293b;
            --text-muted: #64748b;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-premium: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes scaleIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-5px); } 100% { transform: translateY(0px); } }
        
        body { 
            background-color: var(--bg-main); 
            font-family: 'Kantumruy Pro', 'Noto Sans Khmer', sans-serif; 
            color: var(--text-main);
            overflow-x: hidden;
        }

        .premium-card {
            background: var(--bg-card);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--border-glass);
            border-radius: 24px;
            box-shadow: var(--shadow-lg);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .premium-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-premium);
            border-color: rgba(99, 102, 241, 0.3);
        }

        .glass-header {
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-glass);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .nav-item-card {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            transition: all 0.4s ease;
            background: white;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .nav-item-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 4px;
            background: var(--gradient);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .nav-item-card:hover::before { opacity: 1; }

        .btn-premium {
            background: var(--btn-gradient);
            color: white;
            padding: 12px 24px;
            border-radius: 14px;
            font-weight: 700;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 14px 0 rgba(0,0,0,0.1);
        }

        .btn-premium:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
            filter: brightness(1.1);
        }

        .table-premium {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }

        .table-premium tr {
            background: white;
            box-shadow: var(--shadow-sm);
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .table-premium tr:hover {
            transform: scale(1.005);
            box-shadow: var(--shadow-md);
            background: var(--bg-main);
        }

        .table-premium td, .table-premium th {
            padding: 16px 20px;
        }

        .table-premium th {
            font-weight: 800;
            text-transform: uppercase;
            font-size: 0.75rem;
            color: var(--text-muted);
            letter-spacing: 0.05em;
        }

        .table-premium td:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
        .table-premium td:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }

        .status-pill {
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .status-pill.pending { background: #fef3c7; color: #92400e; }
        .status-pill.approved { background: #dcfce7; color: #166534; }
        .status-pill.rejected { background: #fee2e2; color: #991b1b; }

        .avatar-circle {
            width: 48px;
            height: 48px;
            border-radius: 16px;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: var(--shadow-sm);
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 12px;
            color: var(--text-muted);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .sidebar-item:hover, .sidebar-item.active {
            background: white;
            color: var(--primary);
            box-shadow: var(--shadow-sm);
        }

        /* Loading Screen Redesign */
        #loading-screen {
            background: rgba(241, 245, 249, 0.9);
            backdrop-filter: blur(10px);
        }

        .loader-glow {
            width: 80px;
            height: 80px;
            position: relative;
        }

        .loader-glow::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 4px solid var(--primary);
            border-top-color: transparent;
            animation: spin 1s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .breadcrumb-item {
            background: white;
            padding: 8px 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            box-shadow: var(--shadow-sm);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .breadcrumb-item i { color: var(--accent); }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-main); }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        
        @media (max-width: 768px) { 
            aside { position: absolute; z-index: 50; transform: translateX(-100%); } 
            aside.is-open { transform: translateX(0); } 
            .btn-base { padding: 8px 16px; font-size: 0.875rem; }
        }
        .tree, .tree ul { list-style-type: none; padding-left: 0; margin-left: 0; }
        .tree ul { margin-left: 20px; padding-left: 25px; border-left: 1px solid rgba(255, 215, 0, 0.2); position: relative; }
        .tree li { margin: 10px 0; position: relative; }
        .tree li::before { content: ''; position: absolute; top: 16px; left: -25px; width: 25px; height: 1px; background-color: rgba(255, 215, 0, 0.2); }
        .tree li:last-child > ul { border-left-color: transparent; }
        .tree .folder-toggle, .tree .file-item { cursor: pointer; display: inline-block; padding: 8px 12px; border-radius: 8px; transition: background-color 0.2s ease, color 0.2s ease; font-size: 1.05rem; }
        .tree .folder-toggle:hover, .tree .file-item:hover { background-color: var(--primary-bg); color: var(--accent-hover); }
        .tree .folder-toggle i, .tree .file-item i { margin-right: 12px; color: var(--accent-color); width: 20px; text-align: center; }
        .tree .nested { display: none; }
        .tree .open > .nested { display: block; }
        .tree .open > .folder-toggle > .fa-folder::before { content: '\f07c'; }
        .notification-badge { background-color: var(--danger); color: white; border-radius: 50%; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700; line-height: 1; flex-shrink: 0; border: 2px solid var(--secondary-bg); }
        .task-card.done { background-color: rgba(46, 160, 67, 0.1); border-color: rgba(46, 160, 67, 0.3); opacity: 0.7; }
        .task-text.done-text { text-decoration: line-through; color: var(--text-secondary); }
        .custom-scrollbar::-webkit-scrollbar { width: 0px; height: 0px; }
        .custom-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .select2-container--bootstrap-5 .select2-selection { background-color: var(--secondary-bg); border: 1px solid var(--border-color); color: var(--text-primary); border-radius: 10px; padding: 6px 12px; min-height: 50px; }
        .select2-container--bootstrap-5 .select2-dropdown { background-color: var(--secondary-bg); border: 1px solid var(--border-color); }
        .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field { background-color: var(--primary-bg); color: var(--text-primary); border-color: var(--border-color); }
        .select2-container--bootstrap-5 .select2-results__option--highlighted { background-color: var(--accent-color) !important; color: var(--secondary-bg) !important; }
        .select2-container--bootstrap-5 .select2-results__option { color: var(--text-primary); }
        .select2-container--bootstrap-5 .select2-selection__placeholder, .select2-container--bootstrap-5 .select2-selection__rendered { color: var(--text-primary) !important; opacity: 0.8; }
        .nav-tabs { border-bottom: 2px solid var(--border-color); }
        .nav-tabs .nav-link { color: var(--text-secondary); border: 1px solid transparent; border-top-left-radius: 0.5rem; border-top-right-radius: 0.5rem; padding: 0.75rem 1.5rem; background: none; }
        .nav-tabs .nav-link:hover { border-color: var(--border-color) var(--border-color) transparent; }
        .nav-tabs .nav-link.active { color: var(--accent-hover); background-color: var(--primary-bg); border-color: var(--border-color) var(--border-color) var(--primary-bg); font-weight: 700; }
        .tab-content {
            background-color: var(--primary-bg);
            border: 1px solid var(--border-color);
            border-top: none;
            padding: 1rem;
            border-bottom-left-radius: 0.5rem;
            border-bottom-right-radius: 0.5rem;
        }
        .payslip-page-container { display: flex; justify-content: center; }
        .payslip-wrapper { width: 180mm; margin: 20px 0; }
        .v3-payslip-container { background-color: #ffffff; color: #000000; font-family: 'Kantumruy Pro', 'Noto Sans Khmer', sans-serif; border: 1px solid #000; padding: 1rem; font-size: 11pt; margin-bottom: 2rem; }
        .v3-header { text-align: center; padding-bottom: 1rem; border-bottom: 1px solid #000; margin-bottom: 1rem; position: relative; }
        .v3-header img.logo { position: absolute; left: 0; top: 0; max-height: 100px; }
        .v3-header h1 { font-family: 'Moul', cursive; font-size: 1.5rem; font-weight: bold; margin: 0; }
        .v3-header h2 { font-size: 1.2rem; font-weight: bold; margin-top: 0.5rem; }
        .v3-info-section { display: flex; justify-content: space-between; margin-bottom: 1rem; }
        .v3-info-grid { display: grid; grid-template-columns: 100px auto; gap: 5px; }
        .v3-info-grid .label { font-weight: bold; }
        
        .v3-qr-code img {
            width: 125px;      
            height: 125px;     
            object-fit: contain;
            border: 1px solid #b0b0b0; 
            padding: 5px;             
            background-color: #ffffff; 
        }

        .v3-main-table { width: 100%; border-collapse: collapse; border: 1px solid #000; }
        .v3-main-table td { padding: 6px 8px; border-right: 1px solid #000; }
        .v3-main-table td:last-child { border-right: none; }
        .v3-main-table tr { border-bottom: 1px solid #000; }
        .v3-main-table tr:last-child { border-bottom: none; }
        .v3-table-header, .v3-table-footer { background-color: #e3e3e3; font-weight: bold; }
        .v3-amount { text-align: right; font-family: 'Arial', 'Helvetica', sans-serif; font-weight: bold; }
        .v3-footer { margin-top: 1rem; }
        .v3-footer-date { text-align: center; font-size: 10pt; margin-bottom: 2rem; }
        .v3-signature-area { display: flex; justify-content: space-between; text-align: center; }
        .v3-signature-block { display: flex; flex-direction: column; justify-content: flex-end; width: 30%; height: 80px; }
        .v3-signature-block .label { font-weight: bold; }
        @page { size: A5 portrait; margin: 0mm; }
        @media print { body { background-color: #FFF !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .no-print { display: none !important; } .payslip-page-container { display: block; } .payslip-wrapper { width: 98%; margin: 5px; padding: 0; } .v3-payslip-container { border: 1px solid #000; margin: 0; box-shadow: none; page-break-after: always; } .v3-payslip-container:last-child { page-break-after: auto; } }
        #permissions-view .child-row td { padding-left: 2.5rem !important; background-color: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.05); }
        #permissions-view .form-check-input:checked { background-color: var(--accent-color); border-color: var(--accent-color); }
        #permissions-view .table-dark { --bs-table-bg: transparent; }

        /* Permissions view: make menu names and related labels black for readability */
        #permissions-view, #permissions-view .table, #permissions-view th, #permissions-view td, #permissions-view .form-check-label {
            color: #000000 !important;
        }
        /* If first column holds the menu name, ensure it's black specifically */
        #permissions-view table tbody td:first-child { color: #000000 !important; }

        /* CropperJS Styles */
        #imageToCrop {
            display: block;
            max-width: 100%;
        }
        
        /* Additional classes for employee details modal */
        .text-accent-hover { color: var(--accent-hover); }
        .font-bold { font-weight: bold; }
        .text-text-secondary { color: var(--text-secondary); }
        .text-accent-color { color: var(--accent-color); }
        .text-success { color: var(--success); }
        .border-accent-color { border-color: var(--accent-color); }
        .shadow-lg { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
        
        /* Settings View Enhanced Styles */
        .settings-header {
            background: linear-gradient(135deg, #1e293b, #334155);
            color: #fff;
            padding: 1.5rem;
            border-radius: 12px 12px 0 0;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            position: relative;
            overflow: hidden;
        }
        .settings-header::after {
            content: '';
            position: absolute;
            top: 0; right: 0; bottom: 0; left: 0;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.05));
            pointer-events: none;
        }
        .settings-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 20px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.5);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .settings-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 25px 30px -5px rgba(0, 0, 0, 0.1), 0 15px 15px -5px rgba(0, 0, 0, 0.04);
            border-color: rgba(255, 255, 255, 0.8);
        }
        .group-card { border-left: 8px solid #fbbf24; }
        .thread-item {
            background: rgba(248, 250, 252, 0.6);
            border: 1px solid rgba(241, 245, 249, 0.8);
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        .thread-item:hover {
            background: #ffffff;
            border-color: #fbbf24;
            transform: translateX(8px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .cat-list-container {
            max-height: 450px;
            overflow-y: auto;
            padding: 1rem;
            background: rgba(255,255,255,0.3);
        }
        .cat-list-container::-webkit-scrollbar { width: 4px; }
        .cat-list-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .cat-item {
            padding: 12px 14px;
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 10px;
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        .cat-item:hover { 
            background: #ffffff; 
            border-color: #f1f5f9;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }
        .token-input-wrapper { position: relative; width: 100%; }
        .token-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            transition: all 0.2s;
            z-index: 10;
        }
        .token-toggle:hover { color: #1e293b; transform: translateY(-50%) scale(1.1); }
        
        /* Glass Loading Effect */
        .glass-loader {
            background: linear-gradient(110deg, #ececec 8%, #f5f5f5 18%, #ececec 33%);
            background-size: 200% 100%;
            animation: shine 1.5s linear infinite;
        }
        @keyframes shine { to { background-position-x: -200%; } }
        .glass-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }

        .animate-slide-up {
            animation: fadeIn 0.6s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
    </style>
</head>
<body class="flex h-screen <?php if($view === 'payroll_payslip' && isset($_GET['run_id'])) echo 'bg-gray-200'; ?>">
    <?php // Sidebar removed as per request ?>

    <main class="flex-1 p-6 lg:p-8 overflow-y-auto <?php if($view === 'payroll_payslip' && isset($_GET['run_id'])) echo '!p-0'; ?>">
        <?php if(!($view === 'payroll_payslip' && isset($_GET['run_id']))): ?>
        <header class="mb-10">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="flex items-center gap-6">
                    <div>
                        <h1 class="text-3xl md:text-5xl font-black text-slate-800 tracking-tight">
                            សួស្តី, <span class="bg-gradient-to-r from-amber-500 to-orange-600 bg-clip-text text-transparent"><?php echo htmlspecialchars($_SESSION['username']); ?></span>!
                        </h1>
                        <div class="flex items-center gap-3 mt-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <p class="text-slate-600 font-bold uppercase tracking-wider text-sm">
                                <?php echo $page_title; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center gap-4">
                    <!-- Notification Bell Redesigned -->
                    <div class="relative">
                        <button id="notification-bell" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-white shadow-sm border border-slate-200 text-slate-500 hover:text-amber-600 hover:border-amber-300 transition-all focus:outline-none group">
                            <i class="fas fa-bell text-xl group-hover:animate-swing"></i>
                            <?php if (!empty($announcements)): ?>
                                <span class="absolute top-0 right-0 w-5 h-5 bg-rose-500 border-2 border-white rounded-full flex items-center justify-center text-[10px] font-bold text-white"><?php echo count($announcements); ?></span>
                            <?php endif; ?>
                        </button>
                        <!-- Dropdown hidden logic remains same but needs class updates if styled -->
                        <div id="notification-dropdown" class="hidden absolute right-0 mt-4 w-96 bg-white rounded-2xl shadow-2xl border border-slate-100 z-50 overflow-hidden">
                            <div class="p-5 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                                <span class="font-black text-slate-800 uppercase tracking-tighter">ការជូនដំណឹងថ្មីៗ</span>
                                <i class="fas fa-bullhorn text-amber-500"></i>
                            </div>
                            <div class="max-h-96 overflow-y-auto custom-scrollbar">
                                <?php if (!empty($announcements)): foreach ($announcements as $a): ?>
                                    <div class="p-5 hover:bg-slate-50 transition-colors border-b border-slate-50 last:border-b-0 space-y-1">
                                        <div class="font-bold text-slate-900"><?php echo htmlspecialchars($a['title']); ?></div>
                                        <div class="text-[11px] font-bold text-slate-500"><?php echo htmlspecialchars($a['date']); ?></div>
                                        <div class="text-sm text-slate-700 leading-relaxed"><?php echo nl2br(htmlspecialchars($a['text'])); ?></div>
                                    </div>
                                <?php endforeach; else: ?>
                                    <div class="p-10 text-center space-y-3">
                                        <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto text-slate-400">
                                            <i class="fas fa-bell-slash text-2xl"></i>
                                        </div>
                                        <p class="text-slate-600 font-medium">មិនមានការជូនដំណឹងថ្មី</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- User Profile Quick View -->
                    <div class="hidden sm:flex items-center gap-3 p-1.5 pr-5 bg-white rounded-2xl shadow-sm border border-slate-200">
                        <img src="<?php echo htmlspecialchars(thumb_url($_SESSION['image_url'] ?? '', 40, 40)); ?>" class="w-10 h-10 rounded-xl object-cover" alt="User">
                        <div class="flex flex-col">
                            <span class="text-sm font-black text-slate-800 leading-none"><?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']); ?></span>
                            <span class="text-[10px] font-black text-slate-500 uppercase mt-1 tracking-wider"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <?php endif; ?>

        <?php if ($view !== 'dashboard' && !empty($view)): ?>
            <nav class="flex mb-8 items-center gap-4 animate-fade-in" aria-label="Breadcrumb">
                <a href="dashboard.php" class="breadcrumb-item hover:text-primary transition-all">
                    <i class="fas fa-home"></i>
                    <span>ផ្ទាំងគ្រប់គ្រង</span>
                </a>
                <i class="fas fa-chevron-right text-slate-300 text-[10px]"></i>
                <div class="breadcrumb-item active border-primary/20 text-primary bg-primary/5">
                    <?php echo $page_title; ?>
                </div>
            </nav>
        <?php endif; ?>

        <div id="notification-container" class="fixed top-24 right-8 z-[1060]"></div>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin' && isset($pendingRequestsCount) && $pendingRequestsCount > 0): ?>
            <div class="persistent-alert group" role="alert">
                <div class="flex items-center gap-5">
                    <div class="w-14 h-14 bg-amber-400/20 rounded-2xl flex items-center justify-center text-amber-600 group-hover:scale-110 transition-transform">
                        <i class="fas fa-shield-alt text-2xl"></i>
                    </div>
                    <div>
                        <h4 class="font-black text-amber-900 uppercase tracking-tight">សកម្មភាពរង់ចាំការអនុម័ត</h4>
                        <p class="text-amber-800 font-bold italic mt-0.5">អ្នកមានសំណើរចំនួន <strong><?php echo $pendingRequestsCount; ?></strong> ដែលកំពុងរង់ចាំការពិនិត្យ។</p>
                    </div>
                </div>
                <a href="dashboard.php?view=request_reports" class="btn-base bg-amber-500 text-white hover:bg-amber-600 px-6 py-3 rounded-xl shadow-lg shadow-amber-500/30">
                    <i class="fas fa-long-arrow-alt-right"></i><span>មើលសំណើទាំងអស់</span>
                </a>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?><div class="alert-message alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert-message alert-error"><?php echo $error; ?></div><?php endif; ?>

        <?php if ($view === 'dashboard'): ?>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <section class="mb-10 card-base overflow-hidden border-none shadow-xl bg-white animate-slide-up">
                <div class="p-8 bg-slate-900 border-b border-slate-800 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center border border-amber-500/20">
                            <i class="fas fa-clock-rotate-left text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-black text-white tracking-tight m-0">សំណើរង់ចាំ (Pending Requests)</h2>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">ត្រូវការការត្រួតពិនិត្យ និងអនុម័ត</p>
                        </div>
                    </div>
                </div>
                <?php if (empty($pendingRequests)): ?>
                    <div class="p-20 text-center space-y-4">
                        <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto text-slate-300">
                            <i class="fas fa-check-double text-3xl"></i>
                        </div>
                        <p class="text-slate-400 font-medium">គ្មានសំណើរង់ចាំអនុម័តទេ</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-100">
                                    <th class="py-4 px-8 text-left text-[11px] font-black text-slate-400 uppercase tracking-widest">ប្រភេទសំណើ</th>
                                    <th class="py-4 px-8 text-left text-[11px] font-black text-slate-400 uppercase tracking-widest">ឈ្មោះអ្នកស្នើ</th>
                                    <th class="py-4 px-8 text-left text-[11px] font-black text-slate-400 uppercase tracking-widest">មូលហេតុ</th>
                                    <th class="py-4 px-8 text-center text-[11px] font-black text-slate-400 uppercase tracking-widest">សកម្មភាព</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach (array_slice($pendingRequests, 0, 5) as $request): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors group">
                                        <td class="py-4 px-8">
                                            <span class="px-3 py-1 bg-amber-100 text-amber-600 rounded-lg text-[10px] font-black uppercase tracking-tight"><?php echo htmlspecialchars($request['request_type']); ?></span>
                                        </td>
                                        <td class="py-4 px-8">
                                            <div class="font-black text-slate-800"><?php echo htmlspecialchars($request['requester_name']); ?></div>
                                        </td>
                                        <td class="py-4 px-8">
                                            <div class="text-sm text-slate-500 truncate max-w-xs font-medium"><?php echo htmlspecialchars($request['reason']); ?></div>
                                        </td>
                                        <td class="py-4 px-8">
                                            <div class="flex items-center justify-center gap-2">
                                                <a href="approve_request.php?id=<?php echo htmlspecialchars($request['id']); ?>" class="w-9 h-9 flex items-center justify-center rounded-xl bg-emerald-50 text-emerald-500 hover:bg-emerald-500 hover:text-white transition-all shadow-sm shadow-emerald-500/10" title="អនុម័ត">
                                                    <i class="fas fa-check text-xs"></i>
                                                </a>
                                                <a href="reject_request.php?id=<?php echo htmlspecialchars($request['id']); ?>" class="w-9 h-9 flex items-center justify-center rounded-xl bg-rose-50 text-rose-400 hover:bg-rose-500 hover:text-white transition-all shadow-sm shadow-rose-500/10" title="បដិសេធ">
                                                    <i class="fas fa-times text-xs"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <!-- Quick Stats Overview -->
            <section class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
                <div class="premium-card p-8 flex items-center gap-6 group">
                    <div class="w-20 h-20 rounded-2xl bg-indigo-100 text-indigo-600 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition-all duration-500 shadow-inner">
                        <i class="fas fa-users text-3xl"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest mb-1">បុគ្គលិកសរុប</p>
                        <h3 class="text-4xl font-black text-slate-800 drop-shadow-sm"><?php echo htmlspecialchars($totalEmployees); ?></h3>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            <span class="text-[10px] font-bold text-slate-500 uppercase">បច្ចុប្បន្នភាព</span>
                        </div>
                    </div>
                </div>
                <div class="premium-card p-8 flex items-center gap-6 group">
                    <div class="w-20 h-20 rounded-2xl bg-amber-100 text-amber-600 flex items-center justify-center group-hover:bg-amber-600 group-hover:text-white transition-all duration-500 shadow-inner">
                        <i class="fas fa-user-check text-3xl"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest mb-1">អ្នកប្រើប្រាស់សកម្ម</p>
                        <h3 class="text-4xl font-black text-slate-800 drop-shadow-sm"><?php echo htmlspecialchars($activeUsers); ?></h3>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
                            <span class="text-[10px] font-bold text-slate-500 uppercase">នៅក្នុងប្រព័ន្ធ</span>
                        </div>
                    </div>
                </div>
                <div class="premium-card p-8 flex items-center gap-6 group">
                    <div class="w-20 h-20 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center group-hover:bg-rose-600 group-hover:text-white transition-all duration-500 shadow-inner">
                        <i class="fas fa-clock text-3xl"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest mb-1">សំណើរង់ចាំ</p>
                        <h3 class="text-4xl font-black text-slate-800 drop-shadow-sm"><?php echo htmlspecialchars($pendingRequestsCount ?? 0); ?></h3>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                            <span class="text-[10px] font-bold text-slate-500 uppercase">ត្រូវការពិនិត្យ</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Navigation Hub -->
            <div class="mb-10">
                <h2 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-3 mb-8">
                    <span class="w-1.5 h-8 bg-amber-500 rounded-full"></span>
                ផ្ទាំងគ្រប់គ្រងប្រព័ន្ធ
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php
                    $nav_items = [
                        ['key' => 'manage_employees', 'label' => 'គ្រប់គ្រងបុគ្គលិក', 'icon' => 'fas fa-users-cog', 'url' => 'dashboard.php?view=manage_employees', 'desc' => 'គ្រប់គ្រងព័ត៌មានបុគ្គលិក', 'color' => 'amber'],
                        ['key' => 'checklist', 'label' => 'បញ្ជីការងារ', 'icon' => 'fas fa-tasks', 'url' => 'dashboard.php?view=checklist', 'desc' => 'តាមដានភារកិច្ចប្រចាំថ្ងៃ', 'color' => 'indigo'],
                        ['key' => 'daily_reports', 'label' => 'របាយការណ៍ប្រចាំថ្ងៃ', 'icon' => 'fas fa-file-alt', 'url' => 'dashboard.php?view=reports', 'desc' => 'មើលសកម្មភាពការងារ', 'color' => 'emerald'],
                        ['key' => 'request_reports', 'label' => 'របាយការណ៍សំណើ', 'icon' => 'fas fa-list-check', 'url' => 'dashboard.php?view=request_reports', 'desc' => 'គ្រប់គ្រងសំណើរសុំផ្សេងៗ', 'color' => 'rose'],
                        
                        ['key' => 'deductions_bonuses', 'label' => 'កាត់ប្រាក់ & OT', 'icon' => 'fas fa-money-bill-transfer', 'url' => 'dashboard.php?view=deductions_bonuses', 'desc' => 'គ្រប់គ្រងប្រាក់បន្ថែម និងកាត់', 'color' => 'orange'],
                        ['key' => 'payroll_calculation', 'label' => 'ការគណនាបៀវត្ស', 'icon' => 'fas fa-calculator', 'url' => 'dashboard.php?view=payroll_calculation', 'desc' => 'គណនាប្រាក់បៀវត្សប្រចាំខែ', 'color' => 'violet'],
                        ['key' => 'payroll_approval', 'label' => 'អនុម័តបៀវត្ស', 'icon' => 'fas fa-file-signature', 'url' => 'dashboard.php?view=payroll_approval', 'desc' => 'ពិនិត្យ និងអនុម័តបញ្ជីបៀវត្ស', 'color' => 'cyan'],
                        ['key' => 'payroll_payslip', 'label' => 'ប័ណ្ណបើកប្រាក់', 'icon' => 'fas fa-file-invoice-dollar', 'url' => 'dashboard.php?view=payroll_payslip', 'desc' => 'បង្កើត និងមើល Payslip', 'color' => 'teal'],

                        ['key' => 'pending_requests', 'label' => 'សំណើរង់ចាំ', 'icon' => 'fas fa-clock', 'url' => 'pending_requests.php', 'desc' => 'ពិនិត្យសំណើបុគ្គលិក', 'color' => 'yellow'],
                        ['key' => 'processed_requests', 'label' => 'សំណើបានដំណើរការ', 'icon' => 'fas fa-check-circle', 'url' => 'view_processed_requests.php', 'desc' => 'ប្រវត្តិសំណើដែលបានសម្រេច', 'color' => 'green'],
                        ['key' => 'meetings', 'label' => 'បញ្ជីកិច្ចប្រជុំ', 'icon' => 'fas fa-calendar-check', 'url' => 'dashboard.php?view=meetings', 'desc' => 'កំណត់ត្រា និងកាលវិភាគ', 'color' => 'sky'],
                        ['key' => 'post_announcements', 'label' => 'បង្ហោះការជូនដំណឹង', 'icon' => 'fas fa-bullhorn', 'url' => '../../posts/post_announcements.php', 'desc' => 'ផ្សព្វផ្សាយដំណឹងថ្មីៗ', 'color' => 'pink'],
                        
                        ['key' => 'view_lessons', 'label' => 'មើលមេរៀន', 'icon' => 'fas fa-graduation-cap', 'url' => 'lessons.php', 'desc' => 'ឯកសារបណ្តុះបណ្តាល', 'color' => 'lime'],
                        ['key' => 'upload_lesson_docs', 'label' => 'បង្ហោះឯកសារមេរៀន', 'icon' => 'fas fa-file-upload', 'url' => 'post_lesson_documents.php', 'desc' => 'គ្រប់គ្រងឯកសារសិក្សា', 'color' => 'fuchsia'],
                        ['key' => 'inactive_users', 'label' => 'គណនីបានបិទ', 'icon' => 'fas fa-user-slash', 'url' => 'dashboard.php?view=inactive_users', 'desc' => 'គ្រប់គ្រងគណនីអសកម្ម', 'color' => 'slate'],
                        ['key' => 'print_pdf', 'label' => 'បោះពុម្ព PDF', 'icon' => 'fas fa-print', 'url' => 'print_content.php', 'desc' => 'ទាញយកឯកសារ PDF', 'color' => 'gray'],
                        
                        ['key' => 'upload_pdf', 'label' => 'បង្ហោះ PDF', 'icon' => 'fas fa-file-pdf', 'url' => 'store_print_pdf.php', 'desc' => 'រក្សាទុកឯកសារ PDF', 'color' => 'red'],
                        ['key' => 'permissions', 'label' => 'ការកំណត់សិទ្ធ', 'icon' => 'fas fa-shield-alt', 'url' => 'permissions.php', 'desc' => 'គ្រប់គ្រងសិទ្ធិប្រើប្រាស់', 'color' => 'zinc'],
                        ['key' => 'settings', 'label' => 'ការកំណត់ប្រព័ន្ធ', 'icon' => 'fas fa-cog', 'url' => 'dashboard.php?view=settings', 'desc' => 'ការកំណត់ទូទៅ', 'color' => 'slate'],
                    ];

                    foreach ($nav_items as $item):
                        if (can_view_menu($item['key'], $_SESSION['role'] ?? 'employee', $conn)):
                            $gradient = match($item['color']) {
                                'amber' => 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
                                'indigo' => 'linear-gradient(135deg, #6366f1 0%, #4338ca 100%)',
                                'emerald' => 'linear-gradient(135deg, #10b981 0%, #059669 100%)',
                                'rose' => 'linear-gradient(135deg, #f43f5e 0%, #be123c 100%)',
                                'orange' => 'linear-gradient(135deg, #f97316 0%, #ea580c 100%)',
                                'violet' => 'linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%)',
                                'cyan' => 'linear-gradient(135deg, #06b6d4 0%, #0891b2 100%)',
                                'teal' => 'linear-gradient(135deg, #14b8a6 0%, #0d9488 100%)',
                                'yellow' => 'linear-gradient(135deg, #eab308 0%, #ca8a04 100%)',
                                'green' => 'linear-gradient(135deg, #22c55e 0%, #16a34a 100%)',
                                'sky' => 'linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%)',
                                'pink' => 'linear-gradient(135deg, #ec4899 0%, #db2777 100%)',
                                'lime' => 'linear-gradient(135deg, #84cc16 0%, #65a30d 100%)',
                                'fuchsia' => 'linear-gradient(135deg, #d946ef 0%, #c026d3 100%)',
                                'slate' => 'linear-gradient(135deg, #64748b 0%, #475569 100%)',
                                'red' => 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)',
                                'zinc' => 'linear-gradient(135deg, #71717a 0%, #52525b 100%)',
                                default => 'linear-gradient(135deg, #94a3b8 0%, #64748b 100%)'
                            };
                    ?>
                        <a href="<?php echo $item['url']; ?>" class="premium-card p-6 group no-underline flex flex-col h-full" style="--gradient: <?php echo $gradient; ?>">
                            <div class="flex items-start justify-between mb-6">
                                <div class="w-14 h-14 rounded-2xl flex items-center justify-center transition-all duration-500 shadow-lg group-hover:scale-110 group-hover:rotate-3" style="background: <?php echo $gradient; ?>; color: white;">
                                    <i class="<?php echo $item['icon']; ?> text-2xl"></i>
                                </div>
                                <div class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center text-slate-300 group-hover:bg-primary/10 group-hover:text-primary transition-all duration-500">
                                    <i class="fas fa-arrow-right text-xs"></i>
                                </div>
                            </div>
                            <h3 class="text-lg font-black text-slate-800 mb-2 group-hover:text-primary transition-colors"><?php echo $item['label']; ?></h3>
                            <p class="text-xs font-semibold text-slate-500 leading-relaxed"><?php echo $item['desc']; ?></p>
                            
                            <div class="mt-auto pt-6 flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-all duration-500 transform translate-y-2 group-hover:translate-y-0">
                                <span class="text-[10px] font-black text-primary uppercase tracking-widest">ចូលទៅកាន់ផ្នែកនេះ</span>
                                <i class="fas fa-chevron-right text-[8px] text-primary"></i>
                            </div>
                        </a>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
            </div>


        
        <?php elseif ($view === 'inactive_users'): ?>
            <section class="card-base p-0">
                   <div class="p-6 flex justify-between items-center"><h2 class="text-2xl font-bold text-accent-hover">បញ្ជីគណនីដែលបានបិទ</h2><a href="dashboard.php" class="btn-base btn-secondary"><i class="fas fa-arrow-left"></i><span>ត្រឡប់ក្រោយ</span></a></div>
                <div class="overflow-x-auto table-container"><table class="min-w-full"><thead><tr><th class="py-3 px-4 text-left">#</th><th class="py-3 px-4 text-left">ឈ្មោះ</th><th class="py-3 px-4 text-left hidden md:table-cell">អ៊ីមែល</th><th class="py-3 px-4 text-left hidden sm:table-cell">តួនាទី</th><th class="py-3 px-4 text-center">សកម្មភាព</th></tr></thead><tbody>
                <?php if (empty($inactive_employees)): ?><tr><td colspan="5" class="text-center py-8 text-text-secondary text-lg">គ្មានគណនីដែលបានបិទ</td></tr><?php else: ?>
                    <?php foreach ($inactive_employees as $employee): ?><tr><td class="py-3 px-4"><img src="<?php echo htmlspecialchars(thumb_url($employee['image_url'] ?? '', 48, 48)); ?>" alt="Avatar" loading="lazy" class="w-12 h-12 rounded-full object-cover"></td><td class="py-3 px-4 font-semibold text-lg"><?php echo htmlspecialchars($employee['full_name'] ?: $employee['username']); ?></td><td class="py-3 px-4 text-text-secondary hidden md:table-cell"><?php echo htmlspecialchars($employee['email']); ?></td><td class="py-3 px-4 text-text-secondary hidden sm:table-cell"><?php echo htmlspecialchars($employee['position']); ?></td><td class="py-3 px-4 text-center"><a href="activate_user.php?id=<?php echo htmlspecialchars($employee['id']); ?>" class="btn-base btn-success" onclick="return confirm('តើអ្នកពិតជាចង់បើកគណនីនេះឡើងវិញមែនទេ?')"><i class="fas fa-user-check"></i><span>បើកគណនីវិញ</span></a></td></tr><?php endforeach; ?>
                <?php endif; ?>
                </tbody></table></div>
            </section>
            
        <!-- START: NEW HTML FOR MANAGE EMPLOYEES VIEW -->
        <?php elseif ($view === 'manage_employees'): ?>
            <section class="card-base p-0">
                <?php $departments = []; if (!empty($employees_flat_for_dropdowns)) { foreach ($employees_flat_for_dropdowns as $ef) { $dep = isset($ef['department']) ? trim($ef['department']) : ''; if ($dep !== '' && !in_array($dep, $departments, true)) { $departments[] = $dep; } } sort($departments); } ?>
                <div class="p-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <h2 class="text-2xl font-bold text-accent-hover">បញ្ជីបុគ្គលិកសកម្ម</h2>
                    <div class="flex flex-col sm:flex-row gap-2 items-stretch w-full md:w-auto">
                        <input id="employee-search" type="text" class="form-input w-full sm:w-64" placeholder="ស្វែងរកឈ្មោះ/អ៊ីមែល/តួនាទី...">
                        <select id="department-filter" class="form-select w-full sm:w-56">
                            <option value="">ដេប៉ាតឺម៉ង់ទាំងអស់</option>
                            <?php foreach ($departments as $dep): ?>
                                <option value="<?php echo htmlspecialchars($dep); ?>"><?php echo htmlspecialchars($dep); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'administration','accounting'])): ?>
                            <button type="button" class="btn-base btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i class="fas fa-plus"></i><span>បន្ថែមបុគ្គលិក</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="overflow-x-auto table-container">
                    <table class="min-w-full">
                        <thead>
                            <tr>
                                <th class="py-3 px-4 text-left">#</th>
                                <th class="py-3 px-4 text-left">ឈ្មោះ</th>
                                <th class="py-3 px-4 text-left hidden md:table-cell">អ៊ីមែល</th>
                                <th class="py-3 px-4 text-left hidden sm:table-cell">តួនាទី</th>
                                <th class="py-3 px-4 text-left hidden sm:table-cell">ដេប៉ាតឺម៉ង់</th>
                                <th class="py-3 px-4 text-center">សកម្មភាព</th>
                            </tr>
                        </thead>
                        <tbody id="employee-table-body">
                           <!-- JavaScript will populate this area -->
                           <tr><td colspan="6" class="text-center py-8 text-text-secondary text-lg"><i class="fas fa-spinner fa-spin mr-2"></i> កំពុងទាញទិន្នន័យ...</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        <!-- END: NEW HTML -->

        <?php elseif ($view === 'reports'): ?>
            <section class="card-base p-6">
                <h2 class="text-2xl font-bold text-accent-hover mb-4">តម្រងរបាយការណ៍</h2>
                <form method="GET" action="dashboard.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                    <input type="hidden" name="view" value="reports">
                    <div><label class="form-label">ចាប់ពីថ្ងៃ</label><input type="date" name="start_date" value="<?php echo htmlspecialchars($filters['start_date']); ?>" class="form-input p-2"></div>
                    <div><label class="form-label">ដល់ថ្ងៃ</label><input type="date" name="end_date" value="<?php echo htmlspecialchars($filters['end_date']); ?>" class="form-input p-2"></div>
                    <div><label class="form-label">បុគ្គលិកផ្នែក</label><select name="position" class="form-select p-2"><option value="">ទាំងអស់</option><?php foreach ($positions as $pos): ?><option value="<?php echo htmlspecialchars($pos); ?>" <?php echo $filters['position'] === $pos ? 'selected' : ''; ?>><?php echo htmlspecialchars($pos); ?></option><?php endforeach; ?></select></div>
                    <button type="submit" class="w-full btn-base btn-primary"><i class="fas fa-search"></i><span>ស្វែងរក</span></button>
                </form>
                <div class="mt-8">
                    <h3 class="text-xl font-bold text-accent-hover mb-4">ឯកសាររបាយការណ៍</h3>
                    <?php if (empty($structuredReports)): ?><p class="text-center text-text-secondary py-8 text-lg">មិនមានរបាយការណ៍</p><?php else: ?>
                        <ul class="tree"><?php foreach ($structuredReports as $year => $positions): ?><li><span class="folder-toggle text-xl font-bold"><i class="fas fa-folder"></i> <?php echo htmlspecialchars($year); ?></span><ul class="nested"><?php foreach ($positions as $position => $names): ?><li><span class="folder-toggle text-lg font-semibold"><i class="fas fa-folder"></i> <?php echo htmlspecialchars($position); ?></span><ul class="nested"><?php foreach ($names as $name => $dates): ?><li><span class="folder-toggle"><i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($name); ?></span><ul class="nested"><?php foreach ($dates as $date => $reportsOnDate): ?><li><span class="folder-toggle"><i class="fas fa-calendar-day"></i> <?php echo htmlspecialchars($date); ?></span><ul class="nested"><?php foreach ($reportsOnDate as $report): ?><li class="file-item" data-bs-toggle="modal" data-bs-target="#contentModal" data-bs-content="<?php echo htmlspecialchars($report['content'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-file-alt"></i> របាយការណ៍នៅម៉ោង <?php echo date('H:i A', strtotime($report['report_date'])); ?></li><?php endforeach; ?></ul></li><?php endforeach; ?></ul></li><?php endforeach; ?></ul></li><?php endforeach; ?></ul></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                </div>
            </section>

        <?php elseif ($view === 'request_reports'): ?>
            <section class="card-base p-6">
                <div class="flex flex-wrap justify-between items-center mb-4 gap-4"><h2 class="text-2xl font-bold text-accent-hover">របាយការណ៍សំណើ</h2><button type="button" class="btn-base btn-primary" onclick="window.location.href='submit_request.php'"><i class="fas fa-plus"></i>ស្នើសុំថ្មី</button></div>
                <form method="GET" action="dashboard.php" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 items-end mb-6">
                    <input type="hidden" name="view" value="request_reports"><select name="status" class="form-select p-2"><option value="">ស្ថានភាពទាំងអស់</option><option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>រង់ចាំ</option><option value="approved" <?php echo $filter_status === 'approved' ? 'selected' : ''; ?>>បានអនុម័ត</option><option value="rejected" <?php echo $filter_status === 'rejected' ? 'selected' : ''; ?>>បានបដិសេធ</option></select><input type="date" name="request_date" value="<?php echo htmlspecialchars($filter_date); ?>" class="form-input p-2"><input type="text" name="request_type" placeholder="ប្រភេទសំណើ" value="<?php echo htmlspecialchars($filter_type); ?>" class="form-input p-2"><select name="sort_by" class="form-select p-2"><option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>កាលបរិច្ឆេទបង្កើត</option><option value="id" <?php echo $sort_by === 'id' ? 'selected' : ''; ?>>លេខសម្គាល់</option><option value="request_type" <?php echo $sort_by === 'request_type' ? 'selected' : ''; ?>>ប្រភេទ</option><option value="request_date" <?php echo $sort_by === 'request_date' ? 'selected' : ''; ?>>ថ្ងៃស្នើសុំ</option><option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>ស្ថានភាព</option></select><button type="submit" class="btn-base btn-primary w-full justify-center"><i class="fas fa-filter"></i>តម្រង</button>
                </form>
                <div class="overflow-x-auto table-container"><table class="min-w-full"><thead><tr><th class="py-3 px-4 text-left">ប្រភេទ</th><th class="py-3 px-4 text-left">ឈ្មោះអ្នកស្នើ</th><th class="py-3 px-4 text-left">ថ្ងៃស្នើសុំ</th><th class="py-3 px-4 text-center">ស្ថានភាព</th><th class="py-3 px-4 text-center">សកម្មភាព</th></tr></thead><tbody>
                <?php if (empty($requests)): ?><tr><td colspan="5" class="text-center py-8 text-text-secondary text-lg">មិនមានសំណើណាមួយត្រូវបានរកឃើញទេ។</td></tr><?php else: ?>
                    <?php foreach ($requests as $request): ?><tr><td class="py-3 px-4"><?php echo htmlspecialchars($request['request_type']); ?></td><td class="py-3 px-4"><?php echo htmlspecialchars($request['requester_name']); ?></td><td class="py-3 px-4 text-text-secondary"><?php echo htmlspecialchars($request['request_date'] ?? 'N/A'); ?></td><td class="py-3 px-4 text-center"><span class="status-badge status-<?php echo htmlspecialchars($request['status']); ?>"><?php $status = htmlspecialchars($request['status']); echo $status === 'pending' ? 'រង់ចាំ' : ($status === 'approved' ? 'បានអនុម័ត' : 'បានបដិសេធ'); ?></span></td><td class="py-3 px-4 text-center space-x-1"><?php if ($request['status'] === 'pending'): ?><form method="POST" class="inline-block"><input type="hidden" name="request_id" value="<?php echo $request['id']; ?>"><input type="hidden" name="action" value="approve"><button type="submit" class="btn-base btn-success text-xs" title="អនុម័ត"><i class="fas fa-check"></i></button></form><form method="POST" class="inline-block"><input type="hidden" name="request_id" value="<?php echo $request['id']; ?>"><input type="hidden" name="action" value="reject"><button type="submit" class="btn-base btn-danger text-xs" title="បដិសេធ"><i class="fas fa-times"></i></button></form><?php endif; ?><a href="delete.php?id=<?php echo $request['id']; ?>" class="btn-base btn-secondary text-xs" onclick="return confirm('តើអ្នកប្រាកដទេថាចង់លុបសំណើនេះ?');" title="លុប"><i class="fas fa-trash"></i></a></td></tr><?php endforeach; ?>
                <?php endif; ?>
                </tbody></table></div>
            </section>

        <?php elseif ($view === 'checklist'): ?>
            <section class="card-base p-6">
                <div class="flex flex-wrap justify-between items-center mb-4 gap-4"><h2 class="text-2xl font-bold text-accent-hover">បញ្ជីការងារ</h2><?php if ($_SESSION['role'] !== 'admin'): ?><button type="button" class="btn-base btn-primary" data-bs-toggle="modal" data-bs-target="#taskModal"><i class="fas fa-plus"></i> បន្ថែមការងារ</button><?php endif; ?></div>
                <form method="GET" action="dashboard.php" class="flex flex-wrap gap-4 mb-6">
                    <input type="hidden" name="view" value="checklist"><select name="filter" class="form-select flex-grow" onchange="this.form.submit()"><option value="">📋 ប្រភេទទាំងអស់</option><?php foreach ($categories as $c): ?><option value="<?php echo htmlspecialchars($c); ?>" <?php echo $c === $category_filter ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?></select>
                    <?php if ($_SESSION['role'] === 'admin'): ?><select name="name" class="form-select flex-grow" onchange="this.form.submit()"><option value="">👤 អ្នកប្រើទាំងអស់</option><?php foreach ($names as $n): ?><option value="<?php echo htmlspecialchars($n); ?>" <?php echo $n === $name_filter ? 'selected' : ''; ?>><?php echo htmlspecialchars($n); ?></option><?php endforeach; ?></select><?php endif; ?>
                </form>
                <div class="space-y-4">
                <?php if (empty($tasks)): ?><div class="text-center text-text-secondary py-8 text-lg">គ្មានការងារត្រូវបានរកឃើញ។</div><?php else: ?>
                    <?php foreach ($tasks as $t): ?><div class="card-base p-4 flex justify-between items-start task-card <?php echo $t['is_done'] ? 'done' : ''; ?>"><div><p class="task-text font-semibold text-lg <?php echo $t['is_done'] ? 'done-text' : ''; ?>"><?php echo htmlspecialchars($t['task']); ?></p><div class="flex items-center gap-4 mt-2 text-text-secondary text-sm"><span><i class="fas fa-tag mr-1"></i><span class="task-category"><?php echo htmlspecialchars($t['category']); ?></span></span><span><i class="fas fa-calendar-alt mr-1"></i><?php echo htmlspecialchars($t['due_date']); ?></span><?php if ($_SESSION['role'] === 'admin'): ?><span><i class="fas fa-user mr-1"></i><?php echo htmlspecialchars($t['owner_name'] ?? 'N/A'); ?></span><?php endif; ?></div></div><?php if ($_SESSION['role'] !== 'admin'): ?><div class="flex items-center gap-2 shrink-0"><?php if (!$t['is_done']): ?><a href="dashboard.php?view=checklist&done=<?php echo htmlspecialchars($t['id']); ?>" class="btn-base btn-success text-xs" title="សម្គាល់ថាបានសម្រេច"><i class="fas fa-check"></i></a><?php endif; ?><a href="dashboard.php?view=checklist&delete=<?php echo htmlspecialchars($t['id']); ?>" class="btn-base btn-danger text-xs" onclick="return confirm('តើអ្នកប្រាកដទេ?')" title="លុប"><i class="fas fa-trash"></i></a></div><?php endif; ?></div><?php endforeach; ?>
                <?php endif; ?>
                </div>
            </section>
        
        <?php elseif ($view === 'payroll_calculation'): ?>
            <section class="card-base p-6">
                <!-- MODIFIED: New Navigation Bar -->
                <div class="flex flex-wrap justify-between items-center mb-6 gap-4 p-4 card-base" style="background-color: var(--secondary-bg);">
                    <a href="dashboard.php?view=payroll_calculation&year=<?php echo $prev_year; ?>&month=<?php echo $prev_month; ?>" class="btn-base btn-secondary">
                        <i class="fas fa-arrow-left"></i> ខែមុន
                    </a>
                    <div class="text-center">
                        <h2 class="text-2xl font-bold text-accent-hover">ការគណនាបៀវត្ស</h2>
                        <p class="text-lg font-semibold"><?php echo $payroll_period_display; ?></p>
                    </div>
                    <div class="flex gap-2">
                         <a href="dashboard.php?view=payroll_calculation&export=csv&year=<?php echo $current_year; ?>&month=<?php echo $current_month; ?>" class="btn-base btn-success">
                            <i class="fas fa-download"></i> ទាញយកជា CSV
                        </a>
                        <button type="button" class="btn-base btn-danger" data-bs-toggle="modal" data-bs-target="#resetPayrollModal">
                            <i class="fas fa-undo"></i> Reset Payroll
                        </button>
                        <button type="button" class="btn-base btn-secondary" data-bs-toggle="modal" data-bs-target="#jumpToDateModal">
                            <i class="fas fa-calendar-alt"></i> លោតទៅ...
                        </button>
                        <a href="dashboard.php?view=payroll_calculation&year=<?php echo $next_year; ?>&month=<?php echo $next_month; ?>" class="btn-base btn-secondary">
                            ខែបន្ទាប់ <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <!-- END MODIFIED -->

                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <input id="payroll-search" type="text" class="form-input w-full sm:w-64" placeholder="ស្វែងរកឈ្មោះបុគ្គលិក...">
                </div>
                <div class="overflow-x-auto table-container"><table class="min-w-full"><thead><tr><th class="py-3 px-4 text-left">បុគ្គលិក</th><th class="py-3 px-4 text-left">ផ្នែក</th><th class="py-3 px-4 text-right">ប្រាក់ខែគោល</th><th class="py-3 px-4 text-right text-green-400">ប្រាក់បន្ថែម (OT)</th><th class="py-3 px-4 text-right text-red-400">ប្រាក់កាត់</th><th class="py-3 px-4 text-right">ប្រាក់ខែចុងក្រោយ</th><th class="py-3 px-4 text-center">ព័ត៌មានលម្អិត</th></tr></thead><tbody>
                <?php if (empty($employees_for_json)): ?><tr><td colspan="7" class="text-center py-8 text-text-secondary">មិនមានទិន្នន័យបុគ្គលិកទេ។</td></tr><?php else: ?>
                    <?php foreach ($employees_for_json as $name => $data): ?>
                        <tr class="payroll-row" data-name="<?php echo htmlspecialchars(strtolower(trim($name))); ?>">
                            <td class="py-3 px-4 font-semibold"><?php echo htmlspecialchars($name); ?></td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($data['department'] ?: 'N/A'); ?></td>
                            <td class="py-3 px-4 text-right<?php echo !$is_current_user_allowed ? ' blur' : ''; ?>">$<?php echo number_format($data['base_salary'], 2); ?></td>
                            <td class="py-3 px-4 text-right text-green-400">+$<?php echo number_format($data['ot_bonus'], 2); ?></td>
                            <td class="py-3 px-4 text-right text-red-400">-$<?php echo number_format($data['deductions'], 2); ?></td>
                            <td class="py-3 px-4 text-right font-bold text-accent-hover text-lg<?php echo !$is_current_user_allowed ? ' blur' : ''; ?>">$<?php echo number_format($data['net_salary'], 2); ?></td>
                            <td class="py-3 px-4 text-center"><button type="button" class="btn-action-link" data-bs-toggle="modal" data-bs-target="#payrollDetailsModal" data-name="<?php echo htmlspecialchars($name); ?>" data-bonuses="<?php echo htmlspecialchars(implode('<br>', $data['bonus_details'])); ?>" data-deductions="<?php echo htmlspecialchars(implode('<br>', $data['deduction_details'])); ?>">មើល</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody></table></div>
                <?php if (!empty($employees_for_json)): ?><div class="mt-8 flex justify-end"><form id="finalizePayrollForm" method="POST" action="dashboard.php?view=payroll_calculation"><input type="hidden" name="payroll_data" value="<?php echo htmlspecialchars(json_encode($employees_for_json)); ?>"><input type="hidden" name="payroll_month" value="<?php echo $payroll_month_for_db; ?>"><button type="button" id="openFinalizeModalBtn" class="btn-base btn-success text-lg" data-bs-toggle="modal" data-bs-target="#confirmFinalizeModal" data-payroll-month="<?php echo date('F Y', strtotime($payroll_month_for_db)); ?>"><i class="fas fa-check-circle"></i> បញ្ចប់ការគណនា & បញ្ជូនដើម្បីអនុម័ត</button></form></div><?php endif; ?>
            </section>
        
        <?php elseif ($view === 'deductions_bonuses'): ?>
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item" role="presentation"><a class="nav-link <?php echo ($active_tab === 'deductions') ? 'active' : ''; ?>" href="dashboard.php?view=deductions_bonuses&tab=deductions" role="tab"><i class="fas fa-minus-circle mr-2 text-red-400"></i> កាត់ប្រាក់ (Deductions)</a></li>
                <li class="nav-item" role="presentation"><a class="nav-link <?php echo ($active_tab === 'ot_bonus') ? 'active' : ''; ?>" href="dashboard.php?view=deductions_bonuses&tab=ot_bonus" role="tab"><i class="fas fa-plus-circle mr-2 text-green-400"></i> ប្រាក់ថែមម៉ោង (OT Bonus)</a></li>
                <li class="nav-item" role="presentation"><a class="nav-link <?php echo ($active_tab === 'loan') ? 'active' : ''; ?>" href="dashboard.php?view=deductions_bonuses&tab=loan" role="tab"><i class="fas fa-hand-holding-usd mr-2 text-yellow-400"></i> បំណុល (Loan)</a></li>
            </ul>
            <div class="tab-content pt-4">
                <div class="tab-pane fade <?php echo ($active_tab === 'deductions') ? 'show active' : ''; ?>" id="deductions-tab" role="tabpanel">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8"><div class="lg:col-span-1"><section class="card-base p-6"><h2 class="text-xl font-bold mb-4 text-white">បន្ថែមការកាត់ប្រាក់ថ្មី</h2><form method="POST" action="dashboard.php?view=deductions_bonuses" class="space-y-4"><input type="hidden" name="add_deduction" value="1"><div><label for="deduction_user_id" class="form-label">ជ្រើសរើសបុគ្គលិក</label><select name="user_id" id="deduction_user_id" class="form-select text-white" required><option value=""></option><?php foreach ($users_deductions_view as $user): ?><option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option><?php endforeach; ?></select></div><div><label for="deduction_amount" class="form-label">ចំនួនទឹកប្រាក់ (USD)</label><input type="number" step="0.01" min="0.01" name="amount" id="deduction_amount" class="form-input" required></div><div><label for="deduction_reason" class="form-label">មូលហេតុ</label><select name="reason" id="deduction_reason" class="form-select text-white" required><option value=""></option><?php foreach ($existing_deduction_reasons as $reason_item): ?><option value="<?php echo htmlspecialchars($reason_item['reason']); ?>"><?php echo htmlspecialchars($reason_item['reason']); ?></option><?php endforeach; ?></select></div><div><label for="deduction_date" class="form-label">កាលបរិច្ឆេទកាត់ប្រាក់</label><input type="date" name="deduction_date" id="deduction_date" value="<?php echo date('Y-m-d'); ?>" class="form-input" required></div><button type="submit" class="btn-base btn-danger w-full mt-2"><i class="fas fa-minus-circle"></i> បន្ថែមការកាត់ប្រាក់</button></form></section></div>
                        <div class="lg:col-span-2"><section class="card-base p-6"><div class="flex flex-wrap justify-between items-center mb-6 gap-4"><h2 class="text-xl font-bold text-white">បញ្ជីការកាត់ប្រាក់</h2><div class="flex items-center gap-2"><a href="dashboard.php?view=deductions_bonuses&tab=deductions&export=csv&month=<?php echo $current_month_num; ?>&year=<?php echo $current_year; ?>" class="btn-base btn-success"><i class="fas fa-download"></i> ទាញយកជា CSV</a></div><form method="GET" action="dashboard.php" class="flex flex-wrap items-end gap-4"><input type="hidden" name="view" value="deductions_bonuses"><input type="hidden" name="tab" value="deductions"><div><label for="ded_month" class="form-label">ខែ</label><select name="month" id="ded_month" class="form-select" onchange="this.form.submit()"><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?php echo $m; ?>" <?php if ($m == $current_month_num) echo 'selected'; ?>><?php echo date('F', mktime(0, 0, 0, $m, 10)); ?></option><?php endfor; ?></select></div><div><label for="ded_year" class="form-label">ឆ្នាំ</label><select name="year" id="ded_year" class="form-select" onchange="this.form.submit()"><?php for ($y = date('Y') - 5; $y <= date('Y'); $y++): ?><option value="<?php echo $y; ?>" <?php if ($y == $current_year) echo 'selected'; ?>><?php echo $y; ?></option><?php endfor; ?></select></div></form></div><div class="overflow-x-auto table-container"><table class="min-w-full"><thead><tr><th>ឈ្មោះបុគ្គលិក</th><th>ចំនួនទឹកប្រាក់</th><th>មូលហេតុ</th><th>កាលបរិច្ឆេទ</th><th>សកម្មភាព</th></tr></thead><tbody><?php if (empty($deductions_list)): ?><tr><td colspan="5" class="text-center py-8 text-text-secondary">មិនមានទិន្នន័យការកាត់ប្រាក់សម្រាប់ខែនេះទេ។</td></tr><?php else: ?><?php foreach ($deductions_list as $deduction): ?><tr><td class="font-semibold"><?php echo htmlspecialchars($deduction['full_name']); ?></td><td class="text-right text-red-400">-$<?php echo number_format($deduction['amount'], 2); ?></td><td><?php echo htmlspecialchars($deduction['reason']); ?></td><td><?php echo date('d-M-Y', strtotime($deduction['deduction_date'])); ?></td><td class="text-center"><form method="POST" action="dashboard.php?view=deductions_bonuses" onsubmit="return confirm('តើអ្នកពិតជាចង់លុបមែនទេ?');"><input type="hidden" name="delete_deduction" value="1"><input type="hidden" name="deduction_id" value="<?php echo $deduction['id']; ?>"><button type="submit" class="btn-base btn-danger px-3 py-1 text-sm"><i class="fas fa-trash-alt"></i></button></form></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div>

                            <!-- Styled summary card placed under the table -->
                            <div class="mt-4">
                                <div class="card-base p-4 bg-gradient-to-r from-gray-800 to-gray-700 text-white">
                                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                                        <div>
                                            <div class="text-sm text-text-secondary">សរុបការកាត់ប្រាក់សម្រាប់: <strong><?php echo htmlspecialchars(date('F Y', strtotime("{$current_year}-{$current_month_num}-01"))); ?></strong></div>
                                            <div class="text-2xl font-bold text-red-400">-<?php echo '$' . number_format($total_deductions_amount ?? 0, 2); ?></div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm text-text-secondary">ចំនួនករណី</div>
                                            <div class="text-lg font-semibold"><?php echo isset($deductions_list) && is_array($deductions_list) ? count($deductions_list) : 0; ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </section></div>
                    </div>
                </div>
                <div class="tab-pane fade <?php echo ($active_tab === 'ot_bonus') ? 'show active' : ''; ?>" id="ot-bonus-tab" role="tabpanel">
                     <div class="grid grid-cols-1 lg:grid-cols-3 gap-8"><div class="lg:col-span-1"><section class="card-base p-6"><h2 class="text-xl font-bold mb-4 text-white">បញ្ចូល/កែប្រែប្រាក់ OT ប្រចាំខែ</h2><form method="POST" action="dashboard.php?view=deductions_bonuses" class="space-y-4"><input type="hidden" name="add_ot_bonus" value="1"><div><label for="ot_user_id" class="form-label">ជ្រើសរើសបុគ្គលិក</label><select name="ot_user_id" id="ot_user_id" class="form-select text-white" required><option value=""></option><?php foreach ($users_deductions_view as $user): ?><option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option><?php endforeach; ?></select></div><div><label for="ot_month" class="form-label">សម្រាប់ខែ</label><input type="month" name="ot_month" id="ot_month" class="form-input" value="<?php echo date('Y-m'); ?>" required></div><div><label for="ot_amount" class="form-label">ចំនួនប្រាក់ OT (USD)</label><input type="number" step="0.01" min="0" name="ot_amount" id="ot_amount" class="form-input" placeholder="0.00" required></div><div><label for="ot_reason" class="form-label">កំណត់សម្គាល់</label><select name="ot_reason" id="ot_reason" class="form-select text-white"><option value=""></option><?php foreach ($existing_ot_reasons as $reason_item): ?><option value="<?php echo htmlspecialchars($reason_item['reason']); ?>"><?php echo htmlspecialchars($reason_item['reason']); ?></option><?php endforeach; ?></select></div><button type="submit" class="btn-base btn-success w-full mt-2"><i class="fas fa-save"></i> រក្សាទុក/កែប្រែប្រាក់ OT</button></form></section></div>
                        <div class="lg:col-span-2"><section class="card-base p-6"><div class="flex flex-wrap justify-between items-center mb-6 gap-4"><h2 class="text-xl font-bold text-white">បញ្ជីប្រាក់ OT</h2><div class="flex items-center gap-2"><a href="dashboard.php?view=deductions_bonuses&tab=ot_bonus&export=csv&month=<?php echo htmlspecialchars($current_month_str); ?>" class="btn-base btn-success"><i class="fas fa-download"></i> ទាញយកជា CSV</a></div><form method="GET" action="dashboard.php" class="flex flex-wrap items-end gap-4"><input type="hidden" name="view" value="deductions_bonuses"><input type="hidden" name="tab" value="ot_bonus"><div><label for="ot_view_month" class="form-label">មើលសម្រាប់ខែ</label><input type="month" name="month" id="ot_view_month" class="form-input" value="<?php echo htmlspecialchars($current_month_str); ?>" onchange="this.form.submit()"></div></form></div><div class="overflow-x-auto table-container"><table class="min-w-full"><thead><tr><th>ឈ្មោះបុគ្គលិក</th><th>ខែ</th><th>ចំនួនប្រាក់ OT</th><th>ចំនួនថ្ងៃ (OT)</th><th>កំណត់សម្គាល់</th><th>សកម្មភាព</th></tr></thead><tbody><?php if (empty($ot_bonuses_list)): ?><tr><td colspan="6" class="text-center py-8 text-text-secondary">មិនមានទិន្នន័យប្រាក់ OT សម្រាប់ខែនេះទេ។</td></tr><?php else: ?><?php foreach ($ot_bonuses_list as $ot): ?><?php $daily_rate = $ot_daily_rate_map[$ot['user_id']] ?? 0; $days_display = $daily_rate > 0 ? number_format(($ot['ot_amount'] / $daily_rate), 2) : 'N/A'; ?><tr><td class="font-semibold"><?php echo htmlspecialchars($ot['full_name']); ?></td><td><?php echo htmlspecialchars($ot['ot_month']); ?></td><td class="text-right text-green-400">+$<?php echo number_format($ot['ot_amount'], 2); ?></td><td class="text-center"><?php echo $days_display; ?></td><td><?php echo htmlspecialchars($ot['reason'] ?: 'N/A'); ?></td><td class="text-center space-x-2"><button type="button" class="btn-base btn-primary px-3 py-1 text-sm" onclick="loadOtEditForm('<?php echo $ot['user_id']; ?>','<?php echo htmlspecialchars($ot['ot_amount']); ?>','<?php echo htmlspecialchars(addslashes($ot['reason'])); ?>','<?php echo htmlspecialchars($ot['ot_month']); ?>')"><i class="fas fa-edit"></i></button><form method="POST" action="dashboard.php?view=deductions_bonuses" onsubmit="return confirm('តើអ្នកពិតជាចង់លុបប្រាក់ OT នេះមែនទេ?');" class="inline-block"><input type="hidden" name="delete_ot_bonus" value="1"><input type="hidden" name="ot_id" value="<?php echo $ot['id']; ?>"><input type="hidden" name="redirect_month" value="<?php echo htmlspecialchars($ot['ot_month']); ?>"><button type="submit" class="btn-base btn-danger px-3 py-1 text-sm"><i class="fas fa-trash-alt"></i></button></form></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div></section></div>
                    </div>
                </div>
                <div class="tab-pane fade <?php echo ($active_tab === 'loan') ? 'show active' : ''; ?>" id="loan-tab" role="tabpanel">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-1">
                            <section class="card-base p-6">
                                <h2 class="text-xl font-bold mb-4 text-white">បន្ថែមបំណុលថ្មី</h2>
                                <form method="POST" action="dashboard.php?view=deductions_bonuses" class="space-y-4">
                                    <input type="hidden" name="add_loan" value="1">
                                    <div>
                                        <label for="loan_user_id" class="form-label">ជ្រើសរើសបុគ្គលិក</label>
                                        <select name="loan_user_id" id="loan_user_id" class="form-select text-white" required>
                                            <option value=""></option>
                                            <?php foreach ($users_deductions_view as $user): ?>
                                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['full_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="loan_amount" class="form-label">ទឹកប្រាក់ខ្ចី</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" step="0.01" min="0.01" name="loan_amount" id="loan_amount" class="form-input" placeholder="0.00" required>
                                            </div>
                                        </div>
                                        <div>
                                            <label for="monthly_deduction" class="form-label">កាត់ប្រចាំខែ</label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" step="0.01" min="0.01" name="monthly_deduction" id="monthly_deduction" class="form-input" placeholder="0.00" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="borrow_date" class="form-label">ថ្ងៃខ្ចី</label>
                                            <input type="date" name="borrow_date" id="borrow_date" class="form-input" value="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div>
                                            <label for="start_month" class="form-label">ខែចាប់ផ្តើម</label>
                                            <input type="month" name="start_month" id="start_month" class="form-input" value="<?php echo date('Y-m'); ?>" required>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="duration_months" class="form-label">រយៈពេល (ខែ)</label>
                                            <input type="number" min="1" name="duration_months" id="duration_months" class="form-input" placeholder="ឧ. 12">
                                        </div>
                                        <div>
                                            <label for="loan_note" class="form-label">កំណត់សម្គាល់</label>
                                            <input type="text" name="loan_note" id="loan_note" class="form-input" placeholder="ឧ. បំណុលឧបករណ៍...">
                                        </div>
                                    </div>
                                    <div>
                                        <label for="fund_request_note" class="form-label">សំណើបងទឹកលុយ (បើមិនគ្រប់ឬលើស)</label>
                                        <textarea name="fund_request_note" id="fund_request_note" class="form-input" rows="2" placeholder="សរសេរសំណើបងទឹកលុយបន្ថែម បើទឹកប្រាក់ខ្ចីមិនគ្រប់គ្រប់ ឬ លើសពីតម្រូវការ..."></textarea>
                                    </div>
                                    <button type="submit" class="btn-base btn-primary w-full mt-2"><i class="fas fa-save"></i> បន្ថែមបំណុល</button>
                                </form>
                            </section>
                        </div>

                        <div class="lg:col-span-2">
                            <section class="card-base p-6">
                                <div class="flex flex-wrap justify-between items-center mb-6 gap-4">
                                    <h2 class="text-xl font-bold text-white"><?php echo $show_detailed ? 'លម្អិតបំណុល' : 'បញ្ជីបំណុល'; ?></h2>
                                    <?php if ($show_detailed): ?>
                                        <a href="dashboard.php?view=deductions_bonuses&tab=loan" class="btn-base btn-secondary"><i class="fas fa-arrow-left"></i> ត្រឡប់</a>
                                    <?php else: ?>
                                        <a href="dashboard.php?view=deductions_bonuses&tab=loan&export=csv" class="btn-base btn-success"><i class="fas fa-download"></i> ទាញយកជា CSV</a>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$show_detailed): ?>
                                <?php 
                                    $total_loans_count = isset($loans_list) && is_array($loans_list) ? count($loans_list) : 0;
                                    $total_remaining_sum = 0.0; 
                                    $total_monthly_active_sum = 0.0;
                                    $total_loan_amount_sum = 0.0;
                                    if (!empty($loans_list)) {
                                        foreach ($loans_list as $loan_sum) {
                                            $total_remaining_sum += (float)($loan_sum['total_remaining'] ?? 0);
                                            $total_loan_amount_sum += (float)($loan_sum['total_loan_amount'] ?? 0);
                                            $total_monthly_active_sum += (float)($loan_sum['total_monthly_deduction'] ?? 0);
                                        }
                                    }
                                ?>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                    <div class="card-base p-4">
                                        <div class="text-text-secondary text-sm">ទឹកប្រាក់ខ្ចីសរុប</div>
                                        <div class="text-2xl font-bold text-blue-400">$<?php echo number_format($total_loan_amount_sum, 2); ?></div>
                                    </div>
                                    <div class="card-base p-4">
                                        <div class="text-text-secondary text-sm">បំណុលសរុបនៅសល់</div>
                                        <div class="text-2xl font-bold text-red-400">$<?php echo number_format($total_remaining_sum, 2); ?></div>
                                    </div>
                                    <div class="card-base p-4">
                                        <div class="text-text-secondary text-sm">កាត់ប្រចាំខែសរុប (Active)</div>
                                        <div class="text-2xl font-bold text-yellow-300">$<?php echo number_format($total_monthly_active_sum, 2); ?></div>
                                    </div>
                                    <div class="card-base p-4">
                                        <div class="text-text-secondary text-sm">ចំនួនបុគ្គលិកមានបំណុល</div>
                                        <div class="text-2xl font-bold text-accent-hover"><?php echo (int)$total_loans_count; ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
                                    <div class="flex items-center gap-2">
                                        <label for="loan-search" class="form-label mb-0">ស្វែងរក</label>
                                        <input type="text" id="loan-search" class="form-input" placeholder="ឈ្មោះបុគ្គលិក..." style="max-width: 220px;">
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <label for="loan-status-filter" class="form-label mb-0">ស្ថានភាព</label>
                                        <select id="loan-status-filter" class="form-select" style="max-width: 200px;">
                                            <option value="">ទាំងអស់</option>
                                            <option value="active">active</option>
                                            <option value="paused">paused</option>
                                            <option value="closed">closed</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="overflow-x-auto table-container">
                                    <table class="min-w-full loan-table">
                                        <thead>
                                            <tr>
                                                <?php if ($show_detailed): ?>
                                                    <th>ទឹកប្រាក់ខ្ចី</th>
                                                    <th>នៅសល់</th>
                                                    <th>កាត់ប្រចាំខែ</th>
                                                    <th>ថ្ងៃខ្ចី</th>
                                                    <th>ចាប់ផ្តើម</th>
                                                    <th>រយៈពេល(ខែ)</th>
                                                    <th>ស្ថានភាព</th>
                                                    <th>សកម្មភាព</th>
                                                <?php else: ?>
                                                    <th>ឈ្មោះបុគ្គលិក</th>
                                                    <th>ទឹកប្រាក់ខ្ចីសរុប</th>
                                                    <th>នៅសល់សរុប</th>
                                                    <th>កាត់ប្រចាំខែសរុប</th>
                                                    <th>ថ្ងៃខ្ចីដំបូង</th>
                                                    <th>សំណើបងទឹកលុយ</th>
                                                    <th>សកម្មភាព</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($loans_list)): ?>
                                                <tr><td colspan="<?php echo $show_detailed ? 8 : 7; ?>" class="text-center py-8 text-text-secondary">មិនមានទិន្នន័យបំណុលទេ។</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($loans_list as $loan): ?>
                                                    <tr>
                                                        <?php if ($show_detailed): ?>
                                                            <td>$<?php echo number_format($loan['loan_amount'], 2); ?></td>
                                                            <td class="text-red-400">$<?php echo number_format($loan['remaining_balance'], 2); ?></td>
                                                            <td class="text-yellow-300">$<?php echo number_format($loan['monthly_deduction'], 2); ?></td>
                                                            <td><?php echo !empty($loan['borrow_date']) ? date('Y-m-d', strtotime($loan['borrow_date'])) : 'N/A'; ?></td>
                                                            <td><?php echo date('Y-m', strtotime($loan['start_month'])); ?></td>
                                                            <td><?php echo isset($loan['duration_months']) ? (int)$loan['duration_months'] : 'N/A'; ?></td>
                                                            <td><span class="status-badge status-<?php echo htmlspecialchars($loan['status']); ?>"><?php echo htmlspecialchars($loan['status']); ?></span></td>
                                                            <td class="text-center space-x-2">
                                                                <button type="button" class="btn-base btn-primary px-3 py-1 text-sm" onclick="showEditLoanModal(<?php echo $loan['id']; ?>, '<?php echo htmlspecialchars($loan['monthly_deduction']); ?>', '<?php echo htmlspecialchars($loan['status']); ?>', '<?php echo htmlspecialchars($loan['duration_months'] ?? ''); ?>', '<?php echo htmlspecialchars(addslashes($loan['note'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($loan['fund_request_note'] ?? '')); ?>')"><i class="fas fa-edit"></i></button>
                                                                <form method="POST" action="dashboard.php?view=deductions_bonuses" onsubmit="return confirm('តើអ្នកពិតជាចង់លុបបំណុលនេះមែនទេ?');" class="inline-block">
                                                                    <input type="hidden" name="delete_loan" value="1">
                                                                    <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                                                    <button type="submit" class="btn-base btn-danger px-3 py-1 text-sm"><i class="fas fa-trash-alt"></i></button>
                                                                </form>
                                                            </td>
                                                        <?php else: ?>
                                                            <td class="font-semibold"><?php echo htmlspecialchars($loan['full_name']); ?></td>
                                                            <td>$<?php echo number_format($loan['total_loan_amount'], 2); ?></td>
                                                            <td class="text-red-400">$<?php echo number_format($loan['total_remaining'], 2); ?></td>
                                                            <td class="text-yellow-300">$<?php echo number_format($loan['total_monthly_deduction'], 2); ?></td>
                                                            <td><?php echo !empty($loan['earliest_borrow_date']) ? date('Y-m-d', strtotime($loan['earliest_borrow_date'])) : 'N/A'; ?></td>
                                                            <td><?php echo htmlspecialchars($loan['fund_requests'] ?? ''); ?></td>
                                                            <td class="text-center">
                                                                <a href="dashboard.php?view=deductions_bonuses&tab=loan&user_id=<?php echo $loan['user_id']; ?>" class="btn-base btn-primary px-3 py-1 text-sm"><i class="fas fa-eye"></i> មើលលម្អិត</a>
                                                            </td>
                                                        <?php endif; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                            </section>
                        </div>
                    </div>                    <!-- Edit Loan Modal -->
                    <div class="modal fade" id="editLoanModal" tabindex="-1" aria-labelledby="editLoanModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editLoanModalLabel">កែប្រែបំណុល</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="editLoanForm" method="POST" action="dashboard.php?view=deductions_bonuses">
                                        <input type="hidden" name="update_loan" value="1">
                                        <input type="hidden" name="loan_id" id="edit_loan_id">
                                        <div class="mb-3">
                                            <label for="edit_monthly_deduction" class="form-label">កាត់ប្រចាំខែ ($)</label>
                                            <input type="number" step="0.01" min="0.01" name="monthly_deduction" id="edit_monthly_deduction" class="form-control" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="edit_status" class="form-label">ស្ថានភាព</label>
                                            <select name="status" id="edit_status" class="form-select">
                                                <option value="active">active</option>
                                                <option value="paused">paused</option>
                                                <option value="closed">closed</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="edit_duration_months" class="form-label">រយៈពេល (ខែ)</label>
                                            <input type="number" min="1" name="duration_months" id="edit_duration_months" class="form-control">
                                        </div>
                                        <div class="mb-3">
                                            <label for="edit_note" class="form-label">កំណត់សម្គាល់</label>
                                            <input type="text" name="note" id="edit_note" class="form-control">
                                        </div>
                                        <div class="mb-3">
                                            <label for="edit_fund_request_note" class="form-label">សំណើបងទឹកលុយ</label>
                                            <textarea name="fund_request_note" id="edit_fund_request_note" class="form-control" rows="2"></textarea>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">បិទ</button>
                                    <button type="submit" form="editLoanForm" class="btn btn-primary">រក្សាទុក</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($view === 'payroll_approval'): ?>
            <section class="mb-8">
                <h2 class="text-2xl font-bold text-accent-hover mb-4">ការអនុម័តបៀវត្ស</h2>
                <?php if (empty($pending_runs)): ?><div class="card-base p-8 text-center text-text-secondary"><i class="fas fa-check-circle fa-3x mb-4"></i><p class="text-lg">គ្មានបញ្ជីបៀវត្សរង់ចាំការអនុម័ត ឬ ត្រូវបានបដិសេធទេ។</p></div><?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($pending_runs as $run): ?>
                        <div class="card-base p-6 flex flex-col justify-between">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="text-xl font-bold">ប្រាក់ខែ: <?php echo date('F Y', strtotime($run['month'])); ?></h3>
                                    <span class="status-badge status-<?php echo htmlspecialchars($run['status']); ?>"><?php echo htmlspecialchars($run['status']); ?></span>
                                </div>
                                <p class="text-text-secondary">រៀបចំដោយ: <?php echo htmlspecialchars($run['creator_name']); ?></p>
                                <p class="text-text-secondary">កាលបរិច្ឆេទ: <?php echo date('d-M-Y H:i', strtotime($run['created_at'])); ?></p>
                                <?php if($run['status'] === 'rejected' && !empty($run['notes'])): ?>
                                    <p class="mt-2 text-sm text-danger-400"><strong>ហេតុផលបដិសេធ:</strong> <?php echo htmlspecialchars($run['notes']); ?></p>
                                <?php endif; ?>
                                <hr class="border-gray-700 my-4">
                                <div class="space-y-2">
                                    <div class="flex justify-between"><span>ប្រាក់ខែសរុប (Gross):</span> <span class="font-semibold">$<?php echo number_format($run['total_gross_salary'], 2); ?></span></div>
                                    <div class="flex justify-between"><span>ការកាត់ទុកសរុប:</span> <span class="font-semibold">$<?php echo number_format($run['total_deductions'], 2); ?></span></div>
                                    <div class="flex justify-between text-accent-hover text-lg"><strong>ប្រាក់ខែត្រូវបើក (Net):</strong> <strong class="font-bold">$<?php echo number_format($run['total_net_salary'], 2); ?></strong></div>
                                </div>
                            </div>
                            <div class="flex justify-end gap-2 mt-6">
                                <a href="payroll_run_details.php?id=<?php echo $run['id']; ?>" class="btn-base btn-secondary text-sm" target="_blank">មើលលម្អិត</a>
                                <?php if ($run['status'] === 'calculated'): ?>
                                    <button type="button" class="btn-base btn-danger text-sm" data-bs-toggle="modal" data-bs-target="#rejectModal" data-run-id="<?php echo $run['id']; ?>" data-run-month="<?php echo date('F Y', strtotime($run['month'])); ?>">បដិសេធ</button>
                                    <button type="button" class="btn-base btn-success text-sm" data-bs-toggle="modal" data-bs-target="#approveModal" data-run-id="<?php echo $run['id']; ?>" data-run-month="<?php echo date('F Y', strtotime($run['month'])); ?>">អនុម័ត</button>
                                <?php endif; ?>
                                <button type="button" class="btn-base btn-danger text-sm" style="background-color: #581c0d; border: 1px solid var(--danger);" data-bs-toggle="modal" data-bs-target="#deleteModal" data-run-id="<?php echo $run['id']; ?>" data-run-month="<?php echo date('F Y', strtotime($run['month'])); ?>" title="លុប"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            
            <!-- REMOVED: History Section is removed from here -->
            
        <?php elseif ($view === 'payroll_payslip'): ?>
            <?php if ($run_id > 0): ?>
                <div class="no-print my-4 text-center"><a href="dashboard.php?view=payroll_payslip" class="inline-block bg-gray-500 text-white font-bold py-2 px-4 rounded hover:bg-gray-600"><i class="fas fa-arrow-left"></i> ត្រឡប់ក្រោយ</a><button onclick="window.print()" class="bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700"><i class="fas fa-print"></i> បោះពុម្ព Payslip ទាំងអស់</button></div>
                <div class="no-print my-4 flex justify-center"><form method="GET" action="dashboard.php" class="flex gap-2"><input type="hidden" name="view" value="payroll_payslip"><input type="hidden" name="run_id" value="<?php echo $run_id; ?>"><input type="text" name="name_filter" placeholder="ច្រោះតាមឈ្មោះ..." value="<?php echo htmlspecialchars($name_filter ?? ''); ?>" class="py-2 px-4 rounded border border-gray-300 text-gray-800 focus:ring-accent-color focus:border-accent-color"><button type="submit" class="bg-accent-color text-secondary-bg font-bold py-2 px-4 rounded hover:bg-accent-hover"><i class="fas fa-filter"></i> ច្រោះ</button><?php if (!empty($name_filter)): ?><a href="dashboard.php?view=payroll_payslip&run_id=<?php echo $run_id; ?>" class="bg-red-500 text-white font-bold py-2 px-4 rounded hover:bg-red-600"><i class="fas fa-times"></i> លុបច្រោះ</a><?php endif; ?></form></div>
                <div class="payslip-page-container"><div class="payslip-wrapper">
                <?php if (empty($payslips)): ?><p class="text-center text-xl text-black mt-8">គ្មានទិន្នន័យបុគ្គលិកសម្រាប់បញ្ជីបើកប្រាក់បៀវត្សនេះទេ <?php if (!empty($name_filter)) echo 'សម្រាប់ឈ្មោះ៖ ' . htmlspecialchars($name_filter); ?></p><?php else: ?>
                    <?php $pay_period_date = new DateTime($run_info['month']); $start_date = $pay_period_date->format('Y-m-01'); $end_date = $pay_period_date->format('Y-m-t'); ?>
                    <?php foreach ($payslips as $slip): ?>
                        <div class="v3-payslip-container"><div class="v3-header"><img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="Company Logo" class="logo"><h1>វណ្ណ វណ្ណ ខេមបូឌា</h1><span>VAN VAN CAMBODIA</span><h2>ប័ណ្ណបើកប្រាក់បៀវត្ស</h2></div><div class="v3-info-section"><div class="v3-info-grid"><span class="label">ឈ្មោះ:</span> <span class="value"><?php echo htmlspecialchars($slip['full_name']); ?></span><span class="label">តួនាទី:</span> <span class="value"><?php echo htmlspecialchars($slip['role']); ?></span><span class="label">គិតចាប់ពី:</span> <span class="value"><?php echo date('d-m-Y', strtotime($start_date)); ?></span><span class="label">ដល់:</span> <span class="value"><?php echo date('d-m-Y', strtotime($end_date)); ?></span></div><div class="v3-qr-code"><?php if (!empty($slip['bank_qr_code_url']) && file_exists($slip['bank_qr_code_url'])): ?><img src="<?php echo htmlspecialchars($slip['bank_qr_code_url']); ?>" alt="Bank QR Code"><?php else: ?><img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=No-QR-Available" alt="QR Code"><?php endif; ?></div></div><table class="v3-main-table"><tbody><tr class="v3-table-header"><td colspan="2"><?php echo htmlspecialchars($slip['bank_account_number'] ?: 'N/A') . ' ( ' . htmlspecialchars($slip['bank_name'] ?: 'N/A') . ')'; ?></td></tr><tr><td>ប្រាក់ខែសរុប (Base Salary)</td><td class="v3-amount<?php echo !$is_current_user_allowed ? ' blur' : ''; ?>">$ <?php echo number_format($slip['base_salary'], 2); ?></td></tr><?php $current_user_ot = $detailed_ot_bonuses[$slip['user_id']] ?? []; $total_custom_ot = 0; if (!empty($current_user_ot)) { foreach ($current_user_ot as $ot_item) { $reason = htmlspecialchars($ot_item['reason'] ?: 'OT Bonus'); $amount = $ot_item['amount'] ?? 0; $total_custom_ot += $amount; if ($amount > 0) { echo '<tr><td style="padding-left: 20px;">' . $reason . '</td><td class="v3-amount" style="color: #2ea043;">+$ ' . number_format($amount, 2) . '</td></tr>'; } } } $remaining_bonus = $slip['bonuses'] - $total_custom_ot; if ($remaining_bonus > 0.01) { echo '<tr><td>ប្រាក់បន្ថែមម៉ោង</td><td class="v3-amount" style="color: #2ea043;">+$ ' . number_format($remaining_bonus, 2) . '</td></tr>'; } elseif ($slip['bonuses'] > 0 && empty($current_user_ot)) { echo '<tr><td>ប្រាក់ OT បន្ថែមម៉ោងសរុប (គ្មានលម្អិត)</td><td class="v3-amount" style="color: #2ea043;">+$ ' . number_format($slip['bonuses'], 2) . '</td></tr>'; } ?><?php $current_user_deductions = $detailed_deductions[$slip['user_id']] ?? []; $total_custom_deductions = 0; $has_custom_deductions = !empty($current_user_deductions); if ($has_custom_deductions) { foreach ($current_user_deductions as $deduction_item) { $reason = htmlspecialchars($deduction_item['reason'] ?? 'មូលហេតុមិនស្គាល់'); $amount = $deduction_item['amount'] ?? 0; $total_custom_deductions += $amount; if ($amount > 0) { echo '<tr><td style="padding-left: 20px;"> ' . $reason . '</td><td class="v3-amount" style="color: #da3633;">-$ ' . number_format($amount, 2) . '</td></tr>'; } } } $remaining_deduction = $slip['deductions'] - $total_custom_deductions; if ($remaining_deduction > 0.01) { echo '<tr><td> ភ្លេចស្កេន</td><td class="v3-amount" style="color: #da3633;">-$ ' . number_format($remaining_deduction, 2) . '</td></tr>'; } elseif ($slip['deductions'] > 0 && !$has_custom_deductions) { echo '<tr><td>កាត់ប្រាក់សរុប (គ្មានលម្អិត)</td><td class="v3-amount" style="color: #da3633;">-$ ' . number_format($slip['deductions'], 2) . '</td></tr>'; } ?><tr class="v3-table-footer"><td>ប្រាក់ខែសរុបដែលទទួលបាន</td><td class="v3-amount<?php echo !$is_current_user_allowed ? ' blur' : ''; ?>">$ <?php echo number_format($slip['net_salary'], 2); ?></td></tr></tbody></table><div class="v3-footer"><div class="v3-footer-date"><span><?php echo $dynamic_date_line ?? ''; ?></span></div><div class="v3-signature-area"><div class="v3-signature-block"><span class="label">អ្នកទទួល</span><br><br><span><?php echo htmlspecialchars($slip['full_name']); ?></span></div><div class="v3-signature-block"><span class="label">អ្នកប្រគល់</span><span class="label">គណនេយ្យករ</span><br><br><span>លី សាំងអុី</span></div></div></div></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                </div></div>
            <?php else: ?>
                <section class="mb-8"><h2 class="text-2xl font-bold text-accent-hover mb-4">បញ្ជីដែលបានអនុម័ត (ត្រៀមសម្រាប់បង្កើត Payslip)</h2><?php if (empty($approved_runs)): ?><div class="card-base p-8 text-center text-text-secondary"><i class="fas fa-inbox fa-3x mb-4"></i><p class="text-lg">គ្មានបញ្ជីដែលបានអនុម័តកំពុងរង់ចាំដំណើរការទេ។</p></div><?php else: ?><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"><?php foreach ($approved_runs as $run): ?><div class="card-base p-6 flex flex-col justify-between"><div><h3 class="text-xl font-bold">ប្រាក់ខែ: <?php echo date('F Y', strtotime($run['month'])); ?></h3><p class="text-text-secondary">អនុម័តដោយ: <?php echo htmlspecialchars($run['approver_name']); ?></p><p class="text-text-secondary">កាលបរិច្ឆេទអនុម័ត: <?php echo date('d-M-Y H:i', strtotime($run['approved_at'])); ?></p><strong class="text-lg text-accent-hover mt-3 block">ប្រាក់ត្រូវបើកសរុប: $<?php echo number_format($run['total_net_salary'], 2); ?></strong></div><div class="flex justify-end gap-3 mt-6"><button type="button" class="btn-base btn-secondary open-modal-btn" data-run-id="<?php echo $run['id']; ?>" data-run-month="<?php echo date('F Y', strtotime($run['month'])); ?>"><i class="fas fa-check-double"></i> សម្គាល់ថាបានទូទាត់</button><a href="dashboard.php?view=payroll_payslip&run_id=<?php echo $run['id']; ?>" class="btn-base btn-primary"><i class="fas fa-file-invoice-dollar"></i> បង្កើត/មើល Payslip</a></div></div><?php endforeach; ?></div><?php endif; ?></section>
                <section class="card-base p-0"><div class="p-6"><h2 class="text-2xl font-bold text-accent-hover">ប្រវត្តិការទូទាត់ប្រាក់បៀវត្ស</h2></div><div class="overflow-x-auto table-container"><table class="min-w-full"><thead><tr><th>ខែ</th><th>ស្ថានភាព</th><th>អនុម័តដោយ</th><th>ប្រាក់ត្រូវបើកសរុប</th><th>សកម្មភាព</th></tr></thead><tbody><?php if (empty($paid_runs)): ?><tr><td colspan="5" class="text-center py-8 text-text-secondary">គ្មានប្រវត្តិទេ។</td></tr><?php else: ?><?php foreach ($paid_runs as $run): ?><tr><td class="font-semibold"><?php echo date('F Y', strtotime($run['month'])); ?></td><td><span class="status-badge status-paid">បានទូទាត់</span></td><td><?php echo htmlspecialchars($run['approver_name']); ?></td><td>$<?php echo number_format($run['total_net_salary'], 2); ?></td><td><a href="dashboard.php?view=payroll_payslip&run_id=<?php echo $run['id']; ?>" class="text-accent-hover hover:underline"><i class="fas fa-eye"></i> មើល Payslips</a></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div></section>
            <?php endif; ?>

        <?php elseif ($view === 'meetings'): ?>
            <section class="mb-10 card-base border-none shadow-xl overflow-hidden bg-white">
                <div class="p-8 bg-slate-900 border-b border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center border border-amber-500/20">
                            <i class="fas fa-calendar-check text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-black text-white tracking-tight m-0">បញ្ជីកិច្ចប្រជុំ (Meetings List)</h2>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">ព័ត៌មាន និងសកម្មភាពប្រជុំនានា</p>
                        </div>
                    </div>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="dashboard.php?view=post_meeting" class="btn-base bg-amber-500 text-white hover:bg-amber-600 px-6 py-3 rounded-xl shadow-lg shadow-amber-500/20">
                            <i class="fas fa-plus"></i><span>បង្ហោះកិច្ចប្រជុំថ្មី</span>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="p-8">
                    <?php if (empty($meetings)): ?>
                        <div class="p-20 text-center space-y-4">
                            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto text-slate-300 border-2 border-dashed border-slate-100">
                                <i class="fas fa-calendar-day text-3xl"></i>
                            </div>
                            <p class="text-slate-400 font-medium">មិនមានកិច្ចប្រជុំដែលបានបង្ហោះនៅឡើយទេ</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                            <?php foreach ($meetings as $meeting): ?>
                                <div class="group bg-white rounded-3xl border border-slate-100 overflow-hidden hover:shadow-2xl hover:shadow-slate-200/50 transition-all duration-500 flex flex-col h-full">
                                    <?php 
                                        $photos = !empty($meeting['photo_urls']) ? explode(',', $meeting['photo_urls']) : [];
                                        $first_photo = !empty($photos) ? $photos[0] : null;
                                    ?>
                                    <div class="relative h-56 overflow-hidden bg-slate-100">
                                        <?php if ($first_photo): ?>
                                            <img src="<?php echo htmlspecialchars($first_photo); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" alt="Meeting Cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex flex-col items-center justify-center text-slate-300">
                                                <i class="fas fa-camera text-4xl mb-2"></i>
                                                <span class="text-xs font-bold uppercase tracking-widest">No Image</span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="absolute top-4 left-4">
                                            <span class="px-3 py-1 bg-white/90 backdrop-blur-md text-slate-900 text-[10px] font-black uppercase tracking-widest rounded-lg shadow-sm">
                                                <i class="fas fa-folder mr-1 text-amber-500"></i><?php echo htmlspecialchars($meeting['category'] ?: 'General'); ?>
                                            </span>
                                        </div>
                                        <div class="absolute bottom-4 right-4">
                                            <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shadow-lg shadow-amber-500/30">
                                                <i class="fas fa-microphone-alt"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-6 flex-grow flex flex-col">
                                        <div class="flex items-center gap-3 text-slate-600 text-[10px] font-black uppercase tracking-widest mb-3">
                                            <i class="fas fa-clock text-amber-500"></i>
                                            <span><?php echo date('d M Y', strtotime($meeting['meeting_date'])); ?></span>
                                        </div>
                                        <h3 class="text-lg font-black text-slate-900 leading-tight mb-3 group-hover:text-amber-600 transition-colors">
                                            <?php echo htmlspecialchars($meeting['title']); ?>
                                        </h3>
                                        <p class="text-slate-800 text-sm leading-relaxed mb-6 line-clamp-3 font-medium">
                                            <?php echo htmlspecialchars($meeting['description']); ?>
                                        </p>

                                        <?php if ($meeting['mp3_url']): ?>
                                            <div class="mt-auto pt-6 border-t border-slate-50">
                                                <audio controls class="w-full audio-player-mini">
                                                    <source src="<?php echo htmlspecialchars($meeting['mp3_url']); ?>" type="audio/mpeg">
                                                </audio>
                                            </div>
                                        <?php endif; ?>

                                        <div class="flex items-center gap-2 mt-6">
                                            <a href="dashboard.php?view=view_meeting&id=<?php echo $meeting['id']; ?>" class="flex-grow btn-base bg-slate-50 text-slate-600 hover:bg-slate-900 hover:text-white group-hover:bg-amber-50 group-hover:text-amber-600 group-hover:border-amber-100 transition-all font-bold text-xs">
                                                <i class="fas fa-external-link-alt"></i><span>លម្អិត</span>
                                            </a>
                                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                                <a href="dashboard.php?view=edit_meeting&id=<?php echo $meeting['id']; ?>" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-50 text-slate-400 hover:bg-amber-100 hover:text-amber-600 transition-all font-bold" title="កែប្រែ">
                                                    <i class="fas fa-edit text-xs"></i>
                                                </a>
                                                <button type="button" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-50 text-slate-400 hover:bg-rose-100 hover:text-rose-600 transition-all" title="លុប" onclick="deleteMeeting(<?php echo $meeting['id']; ?>)">
                                                    <i class="fas fa-trash-alt text-xs"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="mt-12 flex items-center justify-center gap-2">
                                <?php if ($page > 1): ?>
                                    <a href="dashboard.php?view=meetings&page=<?php echo $page - 1; ?>" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-100 text-slate-500 hover:bg-slate-900 hover:text-white transition-all shadow-sm">
                                        <i class="fas fa-chevron-left text-xs"></i>
                                    </a>
                                <?php endif; ?>

                                <?php 
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++): 
                                ?>
                                    <a href="dashboard.php?view=meetings&page=<?php echo $i; ?>" 
                                       class="w-10 h-10 flex items-center justify-center rounded-xl font-bold text-xs transition-all shadow-sm
                                              <?php echo ($i === $page) ? 'bg-amber-500 text-white shadow-amber-500/30' : 'bg-white border border-slate-100 text-slate-500 hover:bg-slate-50'; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="dashboard.php?view=meetings&page=<?php echo $page + 1; ?>" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white border border-slate-100 text-slate-500 hover:bg-slate-900 hover:text-white transition-all shadow-sm">
                                        <i class="fas fa-chevron-right text-xs"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="text-center mt-4">
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    ទំព័រទី <?php echo $page; ?> នៃ <?php echo $total_pages; ?> (សរុប <?php echo $total_items; ?> កិច្ចប្រជុំ)
                                </span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>

            <script>
                async function deleteMeeting(id) {
                    if (!confirm('តើអ្នកប្រាកដថាចង់លុបកិច្ចប្រជុំនេះ?')) return;
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'delete_meeting');
                        formData.append('id', id);

                        const response = await fetch('api_handler.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();

                        if (result.status === 'success') {
                            showNotification(result.message, 'success');
                            location.reload(); 
                        } else {
                            showNotification(result.message, 'error');
                        }
                    } catch (error) {
                        showNotification('មានបញ្ហាក្នុងការតភ្ជាប់', 'error');
                    }
                }
            </script>

        <?php elseif ($view === 'edit_meeting' && !empty($editing_meeting)): ?>
            <section class="max-w-4xl mx-auto mb-12 animate-fade-in">
                <div class="card-base border-none shadow-2xl bg-white overflow-hidden">
                    <div class="p-8 bg-slate-900 border-b border-slate-800 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-amber-500 text-white flex items-center justify-center shadow-lg shadow-amber-500/20">
                                <i class="fas fa-edit text-xl"></i>
                            </div>
                            <div>
                                <h1 class="text-xl font-black text-white tracking-tight m-0">កែសម្រួលកិច្ចប្រជុំ (Edit Meeting)</h1>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">ធ្វើបច្ចុប្បន្នភាពព័ត៌មានកិច្ចប្រជុំ</p>
                            </div>
                        </div>
                        <a href="dashboard.php?view=meetings" class="w-10 h-10 rounded-xl bg-slate-800 text-slate-400 flex items-center justify-center hover:bg-slate-700 hover:text-white transition-all">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>

                    <form id="editMeetingForm" class="p-8 space-y-8">
                        <input type="hidden" name="id" value="<?php echo $editing_meeting['id']; ?>">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2">
                                <label class="text-[11px] font-black text-slate-900 uppercase tracking-widest ml-1">ប្រធានបទ (Meeting Title) *</label>
                                <input type="text" name="title" value="<?php echo htmlspecialchars($editing_meeting['title']); ?>" required class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-4 focus:ring-amber-500/5 focus:border-amber-500 outline-none transition-all font-medium" placeholder="បញ្ជាក់ប្រធានបទប្រជុំ...">
                            </div>

                            <div class="space-y-2">
                                <label class="text-[11px] font-black text-slate-900 uppercase tracking-widest ml-1">ផ្នែក/ប្រភេទ (Category) *</label>
                                <input type="text" name="category" list="edit-categories" value="<?php echo htmlspecialchars($editing_meeting['category']); ?>" required class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-4 focus:ring-amber-500/5 focus:border-amber-500 outline-none transition-all font-medium" placeholder="ជ្រើសរើស ឬវាយបញ្ចូលថ្មី...">
                                <datalist id="edit-categories">
                                    <?php foreach ($existing_categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[11px] font-black text-slate-900 uppercase tracking-widest ml-1">កាលបរិច្ឆេទ (Meeting Date) *</label>
                            <input type="date" name="date" value="<?php echo $editing_meeting['meeting_date']; ?>" required class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-4 focus:ring-amber-500/5 focus:border-amber-500 outline-none transition-all font-medium">
                        </div>

                        <div class="space-y-2">
                            <label class="text-[11px] font-black text-slate-900 uppercase tracking-widest ml-1">សេចក្តីរៀបរាប់ (Description) *</label>
                            <textarea name="description" rows="6" required class="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-4 focus:ring-amber-500/5 focus:border-amber-500 outline-none transition-all font-medium leading-relaxed" placeholder="រ៉ាយរ៉ាប់បន្ថែមពីកិច្ចប្រជុំ..."><?php echo htmlspecialchars($editing_meeting['description']); ?></textarea>
                        </div>

                        <!-- Audio Section -->
                        <div class="space-y-4 p-6 bg-slate-50 rounded-3xl border border-slate-100">
                            <div class="flex items-center justify-between">
                                <label class="text-[11px] font-black text-slate-900 uppercase tracking-widest ml-1 flex items-center gap-2">
                                    <i class="fas fa-microphone-alt text-amber-500"></i>
                                    <span>ឯកសារសំឡេង (Audio Recording)</span>
                                </label>
                                <div class="flex items-center gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer group">
                                        <div class="relative">
                                            <input type="checkbox" id="edit_compress_audio_toggle" checked class="sr-only peer">
                                            <div class="w-10 h-5 bg-slate-200 rounded-full peer peer-checked:bg-amber-500 transition-colors"></div>
                                            <div class="absolute left-1 top-1 w-3 h-3 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                                        </div>
                                        <span class="text-[9px] font-black text-slate-500 uppercase tracking-tighter">បង្រួមសំឡេង (Compress)</span>
                                    </label>
                                    <button type="button" onclick="document.getElementById('edit_audio_file_input').click()" class="text-amber-600 hover:text-amber-700 font-bold uppercase tracking-tighter text-[9px] bg-amber-50 px-4 py-2 rounded-full border border-amber-100 shadow-sm transition-all">
                                        <i class="fas fa-upload mr-1"></i>ប្តូរសំឡេងថ្មី
                                    </button>
                                </div>
                            </div>

                            <input type="file" id="edit_audio_file_input" accept="audio/*" class="hidden">
                            
                            <div id="edit_audio_preview_container" class="<?php echo $editing_meeting['mp3_url'] ? '' : 'hidden'; ?> space-y-4">
                                <div class="flex items-center gap-4 p-4 bg-white rounded-2xl border border-slate-100 shadow-sm animate-zoom-in">
                                    <div class="w-12 h-12 rounded-xl bg-amber-500 text-white flex items-center justify-center shadow-lg shadow-amber-500/20">
                                        <i class="fas fa-play text-sm"></i>
                                    </div>
                                    <div class="flex-grow min-w-0">
                                        <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Preview Audio</div>
                                        <div id="edit_audio_file_name" class="text-xs font-bold text-slate-700 truncate"><?php echo basename($editing_meeting['mp3_url']); ?></div>
                                    </div>
                                    <div id="edit_compression_badge" class="hidden flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-600 rounded-full border border-emerald-100">
                                        <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></div>
                                        <span class="text-[9px] font-black uppercase">បង្រួមរួចរាល់</span>
                                    </div>
                                </div>
                                <div class="text-[10px] font-bold text-amber-500 uppercase tracking-widest flex items-center gap-2" id="edit_audio_status_msg" style="display:none">
                                    <i class="fas fa-spinner fa-spin"></i>កំពុងបង្រួមសំឡេង សូមរង់ចាំ...
                                </div>
                                <div class="p-2 bg-white rounded-2xl border border-slate-100">
                                    <audio id="edit_audio_preview_player" controls class="w-full audio-player-mini">
                                        <source src="<?php echo htmlspecialchars($editing_meeting['mp3_url']); ?>" type="audio/mpeg">
                                    </audio>
                                </div>
                                <input type="hidden" name="audio_url" value="<?php echo htmlspecialchars($editing_meeting['mp3_url']); ?>">
                            </div>
                        </div>

                        <!-- Photo Section -->
                        <div class="space-y-4">
                            <label class="text-[11px] font-black text-slate-900 uppercase tracking-widest ml-1 flex items-center justify-between">
                                <span class="flex items-center gap-2"><i class="fas fa-images text-amber-500"></i>រូបភាពពាក់ព័ន្ធ (Photos Upload)</span>
                                <button type="button" onclick="document.getElementById('edit_photo_upload_input').click()" class="text-amber-600 hover:text-amber-700 font-bold uppercase tracking-tighter text-[9px] bg-amber-50 px-3 py-1 rounded-full border border-amber-100">
                                    <i class="fas fa-file-image mr-1"></i>បន្ថែមរូបភាព
                                </button>
                            </label>
                            
                            <input type="file" id="edit_photo_upload_input" multiple accept="image/*" class="hidden" onchange="handleEditPhotoSelection(this)">
                            
                            <div id="edit_photo-preview-grid" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                <?php foreach ($editing_photos as $index => $url): ?>
                                    <?php $pid = 'old_' . $index; ?>
                                    <div id="photo-preview-<?php echo $pid; ?>" class="relative aspect-square rounded-xl overflow-hidden group shadow-sm animate-zoom-in">
                                        <img src="<?php echo htmlspecialchars($url); ?>" class="w-full h-full object-cover">
                                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                            <button type="button" onclick="removeEditPhoto('<?php echo $pid; ?>')" class="w-10 h-10 bg-rose-500 text-white rounded-full shadow-lg transform translate-y-2 group-hover:translate-y-0 transition-all">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div id="edit_no_photo_msg" class="<?php echo empty($editing_photos) ? '' : 'hidden'; ?> p-10 text-center bg-slate-50 rounded-2xl border-2 border-dashed border-slate-100 text-slate-300">
                                <i class="fas fa-images text-3xl mb-2"></i>
                                <div class="text-[10px] font-bold uppercase tracking-widest">មិនទាន់មានរូបភាព</div>
                            </div>
                        </div>

                        <div id="edit_upload_progress_container" class="hidden space-y-2 mt-4 animate-slide-up">
                            <div class="flex items-center justify-between text-[10px] font-black text-slate-500 uppercase">
                                <span>កំពុងរក្សាទុកការផ្លាស់ប្តូរ (Updating...)</span>
                                <span id="edit_upload_percentage">0%</span>
                            </div>
                            <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                                <div id="edit_upload_progress_bar" class="h-full bg-amber-500 w-0 transition-all duration-300 shadow-[0_0_10px_rgba(245,158,11,0.5)]"></div>
                            </div>
                        </div>

                        <div class="pt-8 flex flex-col sm:flex-row items-center gap-4">
                            <a href="dashboard.php?view=meetings" class="w-full sm:w-auto px-8 py-4 rounded-xl border border-slate-200 text-slate-700 font-bold text-sm hover:bg-slate-50 transition-all text-center">
                                បោះបង់
                            </a>
                            <button type="submit" id="edit_meeting_submit" class="w-full sm:flex-grow py-4 bg-slate-900 text-white rounded-xl font-black text-sm hover:bg-black transition-all shadow-xl shadow-slate-200 flex items-center justify-center gap-2">
                                <i class="fas fa-save mr-2 text-amber-500"></i>រក្សាទុកការផ្លាស់ប្តូរ
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <script>
                let edit_selectedPhotos = [];
                let edit_removedPhotos = []; // Track URLs of old photos to delete
                let edit_finalAudioBlob = null;

                async function handleEditPhotoSelection(input) {
                    // ... same logic but use shared compressImage
                    const files = Array.from(input.files);
                    for (const file of files) {
                        try {
                            const compressedFile = await compressImage(file);
                            const photoId = Math.random().toString(36).substr(2, 9);
                            edit_selectedPhotos.push({ id: photoId, file: compressedFile });
                            
                            const reader = new FileReader();
                            reader.onload = (e) => renderEditPhotoPreview(photoId, e.target.result);
                            reader.readAsDataURL(compressedFile);
                            checkEditPhotoEmptyState();
                        } catch (err) {
                            console.error('Image compression failed:', err);
                        }
                    }
                    input.value = '';
                }

                function renderEditPhotoPreview(id, src) {
                    const grid = document.getElementById('edit_photo-preview-grid');
                    const div = document.createElement('div');
                    div.id = `photo-preview-${id}`;
                    div.className = 'relative aspect-square rounded-xl overflow-hidden group shadow-sm animate-zoom-in';
                    div.innerHTML = `
                        <img src="${src}" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <button type="button" onclick="removeEditPhoto('${id}')" class="w-10 h-10 bg-rose-500 text-white rounded-full shadow-lg transform translate-y-2 group-hover:translate-y-0 transition-all">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    `;
                    grid.appendChild(div);
                }

                function removeEditPhoto(id) {
                    if (id.startsWith('old_')) {
                        const img = document.querySelector(`#photo-preview-${id} img`);
                        if (img) {
                            const url = img.getAttribute('src');
                            edit_removedPhotos.push(url); // Mark for server-side deletion
                        }
                    }
                    edit_selectedPhotos = edit_selectedPhotos.filter(p => p.id !== id);
                    const el = document.getElementById(`photo-preview-${id}`);
                    if (el) el.remove();
                    checkEditPhotoEmptyState();
                }

                function checkEditPhotoEmptyState() {
                    const msg = document.getElementById('edit_no_photo_msg');
                    const grid = document.getElementById('edit_photo-preview-grid');
                    if (grid.children.length > 0) {
                        msg.classList.add('hidden');
                    } else {
                        msg.classList.remove('hidden');
                    }
                }

                document.getElementById('edit_audio_file_input').addEventListener('change', async function(e) {
                    const file = e.target.files[0];
                    if (!file) return;

                    document.getElementById('edit_audio_file_name').textContent = file.name;
                    document.getElementById('edit_audio_preview_container').classList.remove('hidden');
                    const previewPlayer = document.getElementById('edit_audio_preview_player');
                    previewPlayer.src = URL.createObjectURL(file);

                    if (document.getElementById('edit_compress_audio_toggle').checked) {
                        const statusMsg = document.getElementById('edit_audio_status_msg');
                        const badge = document.getElementById('edit_compression_badge');
                        statusMsg.style.display = 'flex';
                        badge.classList.add('hidden');

                        try {
                            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                            const arrayBuffer = await file.arrayBuffer();
                            const audioBuffer = await audioCtx.decodeAudioData(arrayBuffer);
                            const compressedSR = 8000;
                            const offlineCtx = new OfflineAudioContext(1, audioBuffer.duration * compressedSR, compressedSR);
                            const source = offlineCtx.createBufferSource();
                            source.buffer = audioBuffer;
                            source.connect(offlineCtx.destination);
                            source.start();
                            const renderedBuffer = await offlineCtx.startRendering();
                            const wavBlob = bufferToWav(renderedBuffer, compressedSR);
                            const compressedFile = new File([wavBlob], file.name.replace(/\.[^/.]+$/, "") + "_low.wav", { type: 'audio/wav' });
                            
                            if (compressedFile.size < file.size) {
                                edit_finalAudioBlob = compressedFile;
                                badge.classList.remove('hidden');
                                badge.textContent = 'បង្រួមរួច (' + (compressedFile.size / (1024*1024)).toFixed(1) + 'MB)';
                                badge.classList.add('bg-emerald-500', 'text-white');
                            } else {
                                edit_finalAudioBlob = file;
                            }
                            statusMsg.style.display = 'none';
                            previewPlayer.src = URL.createObjectURL(edit_finalAudioBlob);
                        } catch (err) {
                            console.error("Compression failed:", err);
                            statusMsg.style.display = 'none';
                            edit_finalAudioBlob = file;
                        }
                    } else {
                        edit_finalAudioBlob = file;
                    }
                });

                document.getElementById('editMeetingForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    formData.append('action', 'update_meeting');
                    
                    if (edit_finalAudioBlob) {
                        formData.append('audio_file', edit_finalAudioBlob);
                    }

                    edit_selectedPhotos.forEach(p => {
                        formData.append('meeting_photos[]', p.file);
                    });

                    // Add removed photos list
                    edit_removedPhotos.forEach(url => {
                        formData.append('removed_photos[]', url);
                    });

                    const submitBtn = document.getElementById('edit_meeting_submit');
                    const progressContainer = document.getElementById('edit_upload_progress_container');
                    const progressBar = document.getElementById('edit_upload_progress_bar');
                    const progressText = document.getElementById('edit_upload_percentage');
                    const originalText = submitBtn.innerHTML;

                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>កំពុងរៀបចំ...';

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'api_handler.php', true);
                    xhr.upload.onprogress = function(e) {
                        if (e.lengthComputable) {
                            progressContainer.classList.remove('hidden');
                            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>កំពុងរក្សាទុក...';
                            const p = Math.round((e.loaded / e.total) * 100);
                            progressBar.style.width = p + '%';
                            progressText.textContent = p + '%';
                        }
                    };

                    xhr.onload = function() {
                        try {
                            const result = JSON.parse(xhr.responseText);
                            if (result.status === 'success') {
                                showNotification(result.message, 'success');
                                setTimeout(() => location.href = 'dashboard.php?view=meetings', 1500);
                            } else {
                                showNotification(result.message, 'error');
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                                progressContainer.classList.add('hidden');
                            }
                        } catch (e) {
                            console.error('Server response:', xhr.responseText);
                            showNotification('មានបញ្ហាក្នុងការបកប្រែទិន្នន័យពី Server', 'error');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                            progressContainer.classList.add('hidden');
                        }
                    };
                    xhr.send(formData);
                });
            </script>

        <?php elseif ($view === 'post_meeting'): ?>
            <div class="max-w-4xl mx-auto mb-12">
                <div class="card-base border-none shadow-2xl overflow-hidden bg-white">
                    <div class="p-8 bg-slate-900 border-b border-slate-800 flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-amber-500/10 text-amber-500 flex items-center justify-center border border-amber-500/20 shadow-inner">
                            <i class="fas fa-calendar-plus text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-black text-white tracking-tight m-0">បង្ហោះកិច្ចប្រជុំថ្មី</h2>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">បំពេញព័ត៌មានខាងក្រោមដើម្បីផ្សព្វផ្សាយ</p>
                        </div>
                    </div>

                    <form id="postMeetingForm" class="p-10 space-y-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2">
                                <label class="text-[11px] font-black text-slate-900 uppercase tracking-widest ml-1">ប្រធានបទកិច្ចប្រជុំ <span class="text-amber-500">*</span></label>
                                <div class="relative group">
                                    <i class="fas fa-heading absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-amber-500 transition-colors"></i>
                                    <input type="text" name="title" required class="form-input pl-12 h-14 bg-slate-50 border-slate-100 focus:bg-white text-slate-900 font-bold" placeholder="ឧ. កិច្ចប្រជុំប្រចាំខែសីហា">
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="text-[11px] font-black text-slate-900 uppercase tracking-widest ml-1">ផ្នែក / ថតឯកសារ <span class="text-amber-500">*</span></label>
                                <div class="relative group">
                                    <i class="fas fa-folder-open absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-amber-500 transition-colors"></i>
                                    <input type="text" name="category" list="meeting-categories" required class="form-input pl-12 h-14 bg-slate-50 border-slate-100 focus:bg-white text-slate-900 font-bold" placeholder="ជ្រើសរើស ឬវាយបញ្ចូលថ្មី">
                                    <datalist id="meeting-categories">
                                        <?php if (isset($existing_categories)): foreach ($existing_categories as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat); ?>">
                                        <?php endforeach; endif; ?>
                                    </datalist>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <label class="text-[11px] font-black text-slate-900 uppercase tracking-widest ml-1">កាលបរិច្ឆេទ <span class="text-amber-500">*</span></label>
                                <div class="relative group">
                                    <i class="fas fa-calendar-day absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-amber-500 transition-colors"></i>
                                    <input type="date" name="date" required class="form-input pl-12 h-14 bg-slate-50 border-slate-100 focus:bg-white text-slate-900 font-bold">
                                </div>
                            </div>
                            <div class="md:col-span-2 space-y-4 pt-4 border-t border-slate-50">
                                <label class="text-[11px] font-black text-slate-900 uppercase tracking-widest ml-1 flex items-center gap-2">
                                    <i class="fas fa-microphone-alt text-amber-500"></i>ឯកសារសំឡេង (Audio Management)
                                </label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-tighter ml-1">Upload File (MP3, WAV, M4A)</label>
                                        <div class="relative group">
                                            <input type="file" name="audio_file_input" id="audio_file_input" accept="audio/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10">
                                            <div class="form-input flex items-center gap-3 bg-slate-50 border-slate-100 group-hover:bg-white group-hover:border-amber-200 transition-all h-14 overflow-hidden">
                                                <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center shrink-0">
                                                    <i class="fas fa-file-audio"></i>
                                                </div>
                                                <span id="audio_file_name" class="text-xs text-slate-400 truncate">ជ្រើសរើសឯកសារសំឡេង...</span>
                                                <div id="compression_badge" class="hidden ml-auto px-2 py-0.5 bg-green-100 text-green-700 text-[9px] font-black uppercase rounded">Compressed</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <label class="text-[10px] font-bold text-slate-500 uppercase tracking-tighter ml-1">តំណភ្ជាប់ខាងក្រៅ (External URL)</label>
                                        <div class="relative group">
                                            <i class="fas fa-link absolute left-4 top-1/2 -translate-y-1/2 text-slate-500 group-focus-within:text-amber-500 transition-colors"></i>
                                            <input type="url" name="audio_url" id="audio_url_final" class="form-input pl-12 h-14 bg-slate-50 border-slate-100 focus:bg-white text-slate-900 font-bold" placeholder="ឬបញ្ចូល Google Drive Link">
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4 mt-2 bg-amber-50/50 p-4 rounded-xl border border-amber-100/50">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" id="compress_audio_toggle" class="sr-only peer" checked>
                                        <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-500"></div>
                                        <span class="ml-3 text-[11px] font-bold text-slate-700 uppercase tracking-tight">បង្រួមទំហំសំឡេងឱ្យតូច (Compress for fast upload)</span>
                                    </label>
                                    <div id="audio_status_msg" class="text-[10px] font-medium text-amber-600 ml-auto animate-pulse hidden">កំពុងបង្រួម...</div>
                                </div>
                            </div>
                        </div>

                        <div id="audio_preview_container" class="hidden animate-slide-up">
                            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl bg-slate-900 text-white flex items-center justify-center">
                                    <i class="fas fa-play text-xs"></i>
                                </div>
                                <div class="flex-grow">
                                    <div class="text-[10px] font-black text-slate-400 uppercase mb-1">Preview Audio</div>
                                    <audio id="audio_preview_player" controls class="w-full h-8 audio-player-mini"></audio>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-[11px] font-black text-slate-900 uppercase tracking-widest ml-1">ការពិពណ៌នា <span class="text-amber-500">*</span></label>
                            <textarea name="description" rows="5" required class="form-input p-6 bg-slate-50 border-slate-100 focus:bg-white text-slate-900 leading-relaxed font-bold" placeholder="រៀបរាប់អំពីគោលបំណង ឬលទ្ធផលនៃកិច្ចប្រជុំ..."></textarea>
                        </div>

                        <div class="space-y-4">
                            <label class="text-[11px] font-black text-slate-900 uppercase tracking-widest ml-1 flex items-center justify-between">
                                <span>រូបភាពពាក់ព័ន្ធ (Photos Upload)</span>
                                <button type="button" onclick="document.getElementById('photo_upload_input').click()" class="text-amber-600 hover:text-amber-700 font-bold uppercase tracking-tighter text-[9px] bg-amber-50 px-3 py-1 rounded-full border border-amber-100">
                                    <i class="fas fa-file-image mr-1"></i>ជ្រើសរើសរូបភាព
                                </button>
                            </label>
                            
                            <input type="file" id="photo_upload_input" multiple accept="image/*" class="hidden" onchange="handlePhotoSelection(this)">
                            
                            <div id="photo-preview-grid" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                <!-- Previews will be inserted here -->
                            </div>

                            <div id="no-photo-msg" class="p-10 text-center bg-slate-50 rounded-2xl border-2 border-dashed border-slate-100 text-slate-300">
                                <i class="fas fa-images text-3xl mb-2"></i>
                                <div class="text-[10px] font-bold uppercase tracking-widest">មិនទាន់មានរូបភាព</div>
                            </div>
                        </div>

                        <div id="upload_progress_container" class="hidden space-y-2 mt-4 animate-slide-up">
                            <div class="flex items-center justify-between text-[10px] font-black text-slate-500 uppercase">
                                <span>កំពុងបង្ហោះទិន្នន័យ (Uploading...)</span>
                                <span id="upload_percentage">0%</span>
                            </div>
                            <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                                <div id="upload_progress_bar" class="h-full bg-amber-500 w-0 transition-all duration-300 shadow-[0_0_10px_rgba(245,158,11,0.5)]"></div>
                            </div>
                        </div>

                        <div class="pt-8 flex flex-col sm:flex-row items-center gap-4">
                            <a href="dashboard.php?view=meetings" class="w-full sm:w-auto px-8 py-4 rounded-xl border border-slate-200 text-slate-700 font-bold text-sm hover:bg-slate-50 transition-all text-center">
                                បោះបង់
                            </a>
                            <button type="submit" id="post_meeting_submit" class="w-full sm:flex-grow py-4 bg-slate-900 text-white rounded-xl font-black text-sm hover:bg-black transition-all shadow-xl shadow-slate-200 flex items-center justify-center gap-2">
                                <i class="fas fa-paper-plane mr-2 text-amber-500"></i>បង្ហោះកិច្ចប្រជុំ
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                let selectedPhotos = [];
                let finalAudioBlob = null;

                async function handlePhotoSelection(input) {
                    const files = Array.from(input.files);
                    for (const file of files) {
                        try {
                            const compressedFile = await compressImage(file);
                            const photoId = Math.random().toString(36).substr(2, 9);
                            selectedPhotos.push({ id: photoId, file: compressedFile });
                            
                            const reader = new FileReader();
                            reader.onload = (e) => renderPhotoPreview(photoId, e.target.result);
                            reader.readAsDataURL(compressedFile);
                            checkPhotoEmptyState();
                        } catch (err) {
                            console.error('Image compression failed:', err);
                        }
                    }
                    input.value = ''; // Reset input to allow re-selection
                }

                function renderPhotoPreview(id, src) {
                    const grid = document.getElementById('photo-preview-grid');
                    const div = document.createElement('div');
                    div.id = `photo-preview-${id}`;
                    div.className = 'relative aspect-square rounded-xl overflow-hidden group shadow-sm animate-zoom-in';
                    div.innerHTML = `
                        <img src="${src}" class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                            <button type="button" onclick="removePhoto('${id}')" class="w-10 h-10 bg-rose-500 text-white rounded-full shadow-lg transform translate-y-2 group-hover:translate-y-0 transition-all">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    `;
                    grid.appendChild(div);
                }

                function removePhoto(id) {
                    selectedPhotos = selectedPhotos.filter(p => p.id !== id);
                    document.getElementById(`photo-preview-${id}`).remove();
                    checkPhotoEmptyState();
                }

                function checkPhotoEmptyState() {
                    const msg = document.getElementById('no-photo-msg');
                    if (selectedPhotos.length > 0) {
                        msg.classList.add('hidden');
                    } else {
                        msg.classList.remove('hidden');
                    }
                }

                document.getElementById('audio_file_input').addEventListener('change', async function(e) {
                    const file = e.target.files[0];
                    if (!file) return;

                    document.getElementById('audio_file_name').textContent = file.name;
                    document.getElementById('audio_preview_container').classList.remove('hidden');
                    
                    const previewPlayer = document.getElementById('audio_preview_player');
                    previewPlayer.src = URL.createObjectURL(file);

                    if (document.getElementById('compress_audio_toggle').checked) {
                        compressAudio(file);
                    } else {
                        finalAudioBlob = file;
                        document.getElementById('compression_badge').classList.add('hidden');
                    }
                });

                async function compressAudio(file) {
                    const statusMsg = document.getElementById('audio_status_msg');
                    const badge = document.getElementById('compression_badge');
                    statusMsg.classList.remove('hidden');
                    badge.classList.add('hidden');

                    try {
                        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                        const arrayBuffer = await file.arrayBuffer();
                        const audioBuffer = await audioCtx.decodeAudioData(arrayBuffer);
                        
                        // Use 8kHz mono for maximum compression (Standard for voice/telephony)
                        // This will result in ~0.9MB per minute, which is very safe for the server.
                        const compressedSampleRate = 8000;
                        const offlineCtx = new OfflineAudioContext(1, audioBuffer.duration * compressedSampleRate, compressedSampleRate);
                        const source = offlineCtx.createBufferSource();
                        source.buffer = audioBuffer;
                        source.connect(offlineCtx.destination);
                        source.start();
                        
                        const renderedBuffer = await offlineCtx.startRendering();
                        const wavBlob = bufferToWav(renderedBuffer, compressedSampleRate);
                        
                        const compressedFile = new File([wavBlob], file.name.replace(/\.[^/.]+$/, "") + "_low.wav", { type: 'audio/wav' });
                        
                        // ONLY use compressed version if it's actually smaller than the original
                        if (compressedFile.size < file.size) {
                            finalAudioBlob = compressedFile;
                            badge.textContent = `បង្រួមរួច (${(finalAudioBlob.size/1024/1024).toFixed(2)}MB)`;
                            badge.classList.remove('bg-rose-500', 'text-white');
                            badge.classList.add('bg-emerald-500', 'text-white');
                        } else {
                            finalAudioBlob = file;
                            badge.textContent = `រក្សាទុកច្បាប់ដើម (តូចជាង)`;
                            badge.classList.remove('bg-emerald-500');
                            badge.classList.add('bg-slate-500', 'text-white');
                        }
                        
                        statusMsg.classList.add('hidden');
                        badge.classList.remove('hidden');
                        
                        // Update preview with final version
                        document.getElementById('audio_preview_player').src = URL.createObjectURL(finalAudioBlob);
                    } catch (err) {
                        console.error("Compression failed:", err);
                        statusMsg.classList.add('hidden');
                        finalAudioBlob = file;
                    }
                }

                document.getElementById('postMeetingForm').addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    formData.append('action', 'add_meeting');
                    
                    if (finalAudioBlob) {
                        formData.append('audio_file', finalAudioBlob);
                    }

                    // Append photos
                    selectedPhotos.forEach((photo, index) => {
                        formData.append(`meeting_photos[]`, photo.file);
                    });

                    const submitBtn = document.getElementById('post_meeting_submit');
                    const progressContainer = document.getElementById('upload_progress_container');
                    const progressBar = document.getElementById('upload_progress_bar');
                    const progressText = document.getElementById('upload_percentage');
                    const originalText = submitBtn.innerHTML;

                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>កំពុងរៀបចំ...';

                    // Use XMLHttpRequest to track progress
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'api_handler.php', true);

                    xhr.upload.onprogress = function(e) {
                        if (e.lengthComputable) {
                            progressContainer.classList.remove('hidden');
                            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>កំពុងបង្ហោះ...';
                            const percentComplete = Math.round((e.loaded / e.total) * 100);
                            progressBar.style.width = percentComplete + '%';
                            progressText.textContent = percentComplete + '%';
                        }
                    };

                    xhr.onload = function() {
                        try {
                            const result = JSON.parse(xhr.responseText);
                            if (result.status === 'success') {
                                showNotification(result.message, 'success');
                                setTimeout(() => location.href = 'dashboard.php?view=meetings', 1500);
                            } else {
                                showNotification(result.message, 'error');
                                submitBtn.disabled = false;
                                submitBtn.innerHTML = originalText;
                                progressContainer.classList.add('hidden');
                            }
                        } catch (e) {
                            console.error('Server response:', xhr.responseText);
                            let errorMsg = 'មានបញ្ហាក្នុងការបកប្រែទិន្នន័យពី Server (Invalid JSON)';
                            if (xhr.status === 413 || xhr.responseText.toLowerCase().includes('too large')) {
                                errorMsg = 'ឯកសារធំពេក! Server មិនអាចទទួលយកបានទេ។ សូមបន្ថយទំហំសំឡេង ឬរូបភាព។';
                            } else if (xhr.status === 500) {
                                errorMsg = 'កំហុសបច្ចេកទេសលើ Server (500 Error)។ ប្រហែលជាដោយសារទំហំឯកសារលើសការកំណត់។';
                            }
                            showNotification(errorMsg, 'error');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                            progressContainer.classList.add('hidden');
                        }
                    };

                    xhr.onerror = function() {
                        showNotification('មានបញ្ហាក្នុងការតភ្ជាប់', 'error');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                        progressContainer.classList.add('hidden');
                    };

                    xhr.send(formData);
                });
            </script>

        <?php elseif ($view === 'view_meeting' && !empty($meeting)): ?>
            <div class="max-w-5xl mx-auto mb-12">
                <div class="card-base border-none shadow-2xl overflow-hidden bg-white">
                    <!-- Header with Dynamic Background -->
                    <div class="relative h-64 bg-slate-900 overflow-hidden">
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/40 to-transparent z-10"></div>
                        <?php if (!empty($meeting_photos)): ?>
                            <img src="<?php echo htmlspecialchars($meeting_photos[0]); ?>" class="absolute inset-0 w-full h-full object-cover blur-sm opacity-40" alt="Background">
                        <?php endif; ?>
                        
                        <div class="absolute inset-x-0 bottom-0 p-10 z-20">
                            <div class="flex items-center gap-4 mb-4">
                                <span class="px-3 py-1 bg-amber-500 text-white text-[10px] font-black uppercase tracking-widest rounded-lg shadow-lg">
                                    <i class="fas fa-folder mr-1"></i><?php echo htmlspecialchars($meeting['category']); ?>
                                </span>
                                <span class="px-3 py-1 bg-white/20 backdrop-blur-md text-white text-[10px] font-black uppercase tracking-widest rounded-lg">
                                    <i class="fas fa-calendar-day mr-1 text-amber-500"></i><?php echo date('d M Y', strtotime($meeting['meeting_date'])); ?>
                                </span>
                            </div>
                            <h1 class="text-4xl font-black text-white tracking-tight leading-tight">
                                <?php echo htmlspecialchars($meeting['title']); ?>
                            </h1>
                        </div>
                    </div>

                    <div class="p-10 grid grid-cols-1 lg:grid-cols-3 gap-12">
                        <!-- Main Content -->
                        <div class="lg:col-span-2 space-y-10">
                            <div>
                                <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <i class="fas fa-align-left text-amber-500"></i>សេចក្តីលម្អិតនៃកិច្ចប្រជុំ
                                </h3>
                                <div class="text-slate-800 leading-relaxed text-lg font-medium bg-slate-50 p-8 rounded-3xl border border-slate-100 italic">
                                    <?php echo nl2br(htmlspecialchars($meeting['description'])); ?>
                                </div>
                            </div>

                            <?php if ($meeting['mp3_url']): ?>
                            <div>
                                <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <i class="fas fa-microphone-alt text-amber-500"></i>កំណត់ត្រាសំឡេង
                                </h3>
                                <div class="bg-slate-900 p-6 rounded-3xl shadow-xl border border-slate-800">
                                    <audio controls class="w-full audio-player-mini">
                                        <source src="<?php echo htmlspecialchars($meeting['mp3_url']); ?>" type="audio/mpeg">
                                    </audio>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Sidebar / Photos -->
                        <div class="space-y-8">
                            <div>
                                <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <i class="fas fa-images text-amber-500"></i>រូបភាពពាក់ព័ន្ធ
                                </h3>
                                <?php if (!empty($meeting_photos)): ?>
                                    <div class="grid grid-cols-1 gap-4">
                                        <?php foreach ($meeting_photos as $purl): ?>
                                            <div class="group relative rounded-2xl overflow-hidden border border-slate-100 shadow-sm transition-all hover:shadow-xl">
                                                <img src="<?php echo htmlspecialchars($purl); ?>" class="w-full h-48 object-cover transition-transform duration-500 group-hover:scale-110" alt="Meeting Photo">
                                                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                    <a href="<?php echo htmlspecialchars($purl); ?>" target="_blank" class="w-12 h-12 bg-white rounded-full flex items-center justify-center text-slate-900 shadow-lg translate-y-4 group-hover:translate-y-0 transition-transform">
                                                        <i class="fas fa-expand-alt"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="p-10 text-center bg-slate-50 rounded-2xl border-2 border-dashed border-slate-100 text-slate-300">
                                        <i class="fas fa-image text-3xl mb-2"></i>
                                        <div class="text-[10px] font-bold uppercase tracking-widest">No Photos</div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="pt-6 border-t border-slate-100">
                                <a href="dashboard.php?view=meetings" class="w-full py-4 bg-slate-100 text-slate-600 rounded-2xl font-black text-sm hover:bg-slate-900 hover:text-white transition-all flex items-center justify-center gap-2 grayscale hover:grayscale-0">
                                    <i class="fas fa-arrow-left"></i>ត្រឡប់ទៅបញ្ជី
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($view === 'view_meeting'): ?>
            <div class="max-w-3xl mx-auto p-20 text-center space-y-6">
                <div class="w-24 h-24 bg-rose-50 text-rose-500 rounded-full flex items-center justify-center mx-auto border border-rose-100 shadow-sm">
                    <i class="fas fa-exclamation-triangle text-4xl"></i>
                </div>
                <h2 class="text-2xl font-black text-slate-800">រកមិនឃើញកិច្ចប្រជុំ!</h2>
                <p class="text-slate-500 font-medium">កិច្ចប្រជុំដែលអ្នកកំពុងស្វែងរកប្រហែលជាត្រូវបានលុប ឬមិនត្រឹមត្រូវ។</p>
                <a href="dashboard.php?view=meetings" class="inline-block px-8 py-4 bg-slate-900 text-white rounded-2xl font-black text-sm shadow-xl">ត្រឡប់ទៅបញ្ជីវិញ</a>
            </div>

        <?php elseif ($view === 'settings'): ?>
            <!-- Refactored Settings with Tabs -->
            <div class="card-base p-6 bg-white/80 backdrop-blur-xl border-white/20 shadow-xl rounded-3xl">
                <ul class="nav nav-tabs nav-fill flex flex-col md:flex-row gap-2 md:gap-0 border-b-0 mb-8 p-1 bg-slate-100/50 rounded-2xl" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active flex items-center justify-center gap-3 px-6 py-4 rounded-xl font-bold text-slate-500 hover:text-amber-600 transition-all border-none aria-selected:bg-white aria-selected:text-amber-600 aria-selected:shadow-md" 
                            id="telegram-tab" 
                            data-bs-toggle="tab" 
                            data-bs-target="#telegram-content" 
                            type="button" 
                            role="tab" 
                            aria-selected="true">
                            <i class="fab fa-telegram text-xl"></i>
                            <span class="uppercase tracking-wider text-xs">ការកំណត់ Telegram</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link flex items-center justify-center gap-3 px-6 py-4 rounded-xl font-bold text-slate-500 hover:text-indigo-600 transition-all border-none aria-selected:bg-white aria-selected:text-indigo-600 aria-selected:shadow-md" 
                            id="categories-tab" 
                            data-bs-toggle="tab" 
                            data-bs-target="#categories-content" 
                            type="button" 
                            role="tab" 
                            aria-selected="false">
                            <i class="fas fa-tags text-xl"></i>
                            <span class="uppercase tracking-wider text-xs">ប្រភេទប្រព័ន្ធ (Categories)</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link flex items-center justify-center gap-3 px-6 py-4 rounded-xl font-bold text-slate-500 hover:text-rose-600 transition-all border-none aria-selected:bg-white aria-selected:text-rose-600 aria-selected:shadow-md" 
                            id="theme-tab" 
                            data-bs-toggle="tab" 
                            data-bs-target="#theme-content" 
                            type="button" 
                            role="tab" 
                            aria-selected="false">
                            <i class="fas fa-palette text-xl"></i>
                            <span class="uppercase tracking-wider text-xs">រូបរាង & Theme</span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="settingsTabsContent">
                    <!-- Telegram Tab -->
                    <div class="tab-pane fade show active space-y-8 animate-fade-in" id="telegram-content" role="tabpanel" tabindex="0">
                        <!-- Telegram Bot Token Setting -->
                        <section class="settings-card">
                            <div class="settings-header">
                                <div class="bg-white/20 p-2 rounded-lg"><i class="fab fa-telegram text-2xl"></i></div>
                                <div>
                                    <h2 class="text-xl font-bold m-0 text-white">ការកំណត់ Telegram Bot</h2>
                                    <p class="text-xs opacity-80 m-0 text-white">កំណត់ Bot Token ដើម្បីផ្ញើរបាយការណ៍ស្វ័យប្រវត្តិ</p>
                                </div>
                            </div>
                            <div class="p-6">
                                <form method="POST" action="dashboard.php?view=settings" class="flex flex-col md:flex-row items-end gap-6">
                                    <input type="hidden" name="settings_action" value="update_bot_token">
                                    <div class="flex-grow w-full">
                                        <label for="bot_token" class="form-label text-sm uppercase tracking-wider">Telegram Bot Token</label>
                                        <div class="token-input-wrapper">
                                            <input type="password" name="bot_token" id="bot_token" class="form-input pr-10" value="<?php echo htmlspecialchars($telegramBotToken); ?>" placeholder="ហាមឱ្យអ្នកដទៃដឹង...">
                                            <i class="fas fa-eye token-toggle" onclick="toggleToken()"></i>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn-base btn-primary w-full md:w-auto h-[46px] px-8"><i class="fas fa-save"></i> រក្សាទុកបម្រែបម្រួល</button>
                                </form>
                            </div>
                        </section>

                        <!-- Telegram Groups & Threads Management -->
                        <section>
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                                <div>
                                    <h2 class="text-2xl font-bold text-accent-hover mb-1"><i class="fas fa-users-cog mr-2"></i>គ្រប់គ្រងក្រុម Telegram</h2>
                                    <p class="text-text-secondary">គ្រប់គ្រងបណ្តាញបញ្ជូនរបាយការណ៍ទៅកាន់ Group នានា</p>
                                </div>
                                <button type="button" class="btn-base btn-success" data-bs-toggle="modal" data-bs-target="#addGroupModal"><i class="fas fa-plus"></i> បន្ថែមក្រុមថ្មី</button>
                            </div>

                            <div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
                                <?php foreach ($telegramGroups as $group): ?>
                                    <div class="settings-card group-card border-l-[6px]">
                                        <div class="p-5">
                                            <div class="flex justify-between items-start mb-6">
                                                <div class="flex items-center gap-4">
                                                    <div class="bg-accent-color/10 text-accent-color p-3 rounded-xl"><i class="fas fa-layer-group text-xl"></i></div>
                                                    <div>
                                                        <h3 class="font-bold text-xl text-gray-800"><?php echo htmlspecialchars($group['name']); ?></h3>
                                                        <div class="flex items-center gap-2 text-xs text-text-secondary">
                                                            <span class="bg-gray-100 px-2 py-0.5 rounded">ID: <?php echo htmlspecialchars($group['chat_id']); ?></span>
                                                            <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded">Format: <?php echo htmlspecialchars($group['report_format']); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex gap-1">
                                                    <button class="p-2 hover:bg-gray-100 rounded-lg text-accent-color transition-colors" title="កែប្រែ" onclick="editGroup(<?php echo htmlspecialchars(json_encode($group)); ?>)"><i class="fas fa-edit"></i></button>
                                                    <form method="POST" action="dashboard.php?view=settings" onsubmit="return confirm('តើអ្នកប្រាកដថាចង់លុបក្រុមនេះ?')" class="inline">
                                                        <input type="hidden" name="settings_action" value="delete_group">
                                                        <input type="hidden" name="group_id" value="<?php echo $group['group_id']; ?>">
                                                        <button type="submit" class="p-2 hover:bg-red-50 rounded-lg text-danger transition-colors"><i class="fas fa-trash"></i></button>
                                                    </form>
                                                </div>
                                            </div>
                                            
                                            <div class="space-y-4">
                                                <div class="flex justify-between items-center bg-gray-50 p-2 px-4 rounded-lg">
                                                    <h4 class="font-bold text-sm text-gray-600 uppercase tracking-tight m-0">Threads (ផ្នែក/តួនាទី)</h4>
                                                    <button class="text-xs font-bold text-accent-color hover:text-accent-hover" onclick="addThread(<?php echo $group['group_id']; ?>)"><i class="fas fa-plus-circle"></i> បន្ថែម</button>
                                                </div>
                                                
                                                <div class="space-y-2">
                                                    <?php if (empty($group['thread_ids'])): ?>
                                                        <div class="text-center py-6 border-2 border-dashed border-gray-100 rounded-xl">
                                                            <p class="text-xs italic text-text-secondary m-0">មិនទាន់មានការកំណត់ Thread នៅឡើយ...</p>
                                                        </div>
                                                    <?php else: ?>
                                                        <?php foreach ($group['thread_ids'] as $thread): ?>
                                                            <div class="thread-item group/item">
                                                                <div class="flex items-center gap-3">
                                                                    <div class="w-2 h-2 rounded-full bg-accent-color"></div>
                                                                    <div>
                                                                        <span class="font-bold text-sm text-gray-800"><?php echo htmlspecialchars($thread['category']); ?></span>
                                                                        <span class="mx-2 text-gray-300">|</span>
                                                                        <span class="text-xs text-text-secondary"><?php echo htmlspecialchars($thread['position']); ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="flex items-center gap-4">
                                                                    <span class="text-[10px] font-mono bg-white px-2 py-0.5 rounded border border-gray-200"><?php echo htmlspecialchars($thread['thread_id']); ?></span>
                                                                    <div class="flex gap-1">
                                                                        <button class="text-accent-color hover:scale-110 transition-transform" onclick="editThread(<?php echo htmlspecialchars(json_encode($thread)); ?>)"><i class="fas fa-pen-to-square"></i></button>
                                                                        <form method="POST" action="dashboard.php?view=settings" onsubmit="return confirm('លុប Thread នេះ?')" class="inline">
                                                                            <input type="hidden" name="settings_action" value="delete_thread">
                                                                            <input type="hidden" name="thread_map_id" value="<?php echo $thread['thread_map_id']; ?>">
                                                                            <button type="submit" class="text-danger hover:scale-110 transition-transform"><i class="fas fa-trash"></i></button>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </div>

                    <!-- Categories Tab -->
                    <div class="tab-pane fade space-y-8 animate-fade-in" id="categories-content" role="tabpanel" tabindex="0">
                        <!-- System Categories Management -->
                        <section>
                            <div class="mb-6">
                                <h2 class="text-2xl font-bold text-accent-hover mb-1"><i class="fas fa-tags mr-2"></i>គ្រប់គ្រង ប្រភេទនានា</h2>
                                <p class="text-text-secondary">គ្រប់គ្រងឈ្មោះ ដេប៉ាតឺម៉ង់ តួនាទី និងសាខា ក្នុងប្រព័ន្ធ</p>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                                <?php foreach ($category_types as $type): ?>
                                    <div class="settings-card flex flex-col h-full">
                                        <div class="bg-gradient-to-r from-gray-800 to-gray-700 p-4 flex justify-between items-center">
                                            <div class="flex items-center gap-3">
                                                <div class="text-white opacity-80"><i class="fas <?php echo $type === 'department' ? 'fa-building' : ($type === 'position' ? 'fa-user-tie' : 'fa-map-marker-alt'); ?>"></i></div>
                                                <h3 class="font-bold text-white m-0"><?php echo $khmer_names[$type]; ?></h3>
                                            </div>
                                            <button onclick="addCategory('<?php echo $type; ?>')" class="w-8 h-8 rounded-full bg-white/10 text-white hover:bg-white/30 transition-colors flex items-center justify-center"><i class="fas fa-plus"></i></button>
                                        </div>
                                        <div class="cat-list-container flex-grow">
                                            <?php if (empty($dynamic_categories[$type])): ?>
                                                <div class="flex flex-col items-center justify-center p-10 opacity-30">
                                                    <i class="fas fa-inbox text-4xl mb-2"></i>
                                                    <p class="text-sm italic m-0 text-white">គ្មានទិន្នន័យ</p>
                                                </div>
                                            <?php else: ?>
                                                <?php foreach ($dynamic_categories[$type] as $cat): ?>
                                                    <div class="cat-item">
                                                        <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($cat['name']); ?></span>
                                                        <div class="flex gap-2">
                                                            <button class="p-1.5 text-accent-color hover:bg-blue-50 rounded" onclick="editCategory(<?php echo htmlspecialchars(json_encode($cat)); ?>)"><i class="fas fa-edit"></i></button>
                                                            <form method="POST" action="dashboard.php?view=settings" onsubmit="return confirm('តើអ្នកចង់លុបប្រភេទនេះមែនទេ?')" class="inline">
                                                                <input type="hidden" name="settings_action" value="delete_category">
                                                                <input type="hidden" name="category_id" value="<?php echo $cat['id']; ?>">
                                                                <button type="submit" class="p-1.5 text-danger hover:bg-red-50 rounded"><i class="fas fa-trash"></i></button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </div>

                    <!-- Theme Tab -->
                    <div class="tab-pane fade space-y-8 animate-fade-in" id="theme-content" role="tabpanel" tabindex="0">
                        <?php include 'includes/theme_settings_ui.php'; ?>
                    </div>
                </div>
            </div>

            <!-- Modals for Settings (Group) -->
            <div class="modal fade" id="groupModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-none shadow-2xl">
                        <form method="POST" action="dashboard.php?view=settings">
                            <input type="hidden" name="settings_action" id="group_action" value="add_group">
                            <input type="hidden" name="group_id" id="group_id">
                            <div class="settings-header">
                                <i class="fas fa-layer-group text-xl"></i>
                                <h5 class="modal-title font-bold m-0 text-white" id="groupModalLabel">បន្ថែម/កែប្រែក្រុម</h5>
                                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" style="filter: brightness(0) invert(1);"></button>
                            </div>
                            <div class="modal-body p-6 space-y-5">
                                <div>
                                    <label class="form-label">ឈ្មោះក្រុម (សម្រាប់សម្គាល់)</label>
                                    <input type="text" name="group_name" id="modal_group_name" class="form-input" placeholder="ឧ. Telegram Group IT" required>
                                </div>
                                <div>
                                    <label class="form-label">Telegram Chat ID</label>
                                    <div class="flex gap-2">
                                        <input type="text" name="chat_id" id="modal_chat_id" class="form-input" placeholder="-123456789" required>
                                        <span class="btn-base btn-secondary cursor-help" title="Chat ID របស់ Group"><i class="fas fa-question-circle"></i></span>
                                    </div>
                                </div>
                                <div>
                                    <label class="form-label">ទម្រង់របាយការណ៍ (Report Format)</label>
                                    <select name="report_format" id="modal_report_format" class="form-select">
                                        <option value="text">ចំណងជើង និងអត្ថបទ (Text Only)</option>
                                        <option value="summary">តារាងសង្ខេប (Summary Table)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer bg-gray-50 rounded-b-2xl">
                                <button type="button" class="btn-base" data-bs-dismiss="modal">បោះបង់</button>
                                <button type="submit" class="btn-base btn-primary px-8">រក្សាទុកទិន្នន័យ</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modals for Settings (Thread) -->
            <div class="modal fade" id="threadModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-none shadow-2xl">
                        <form method="POST" action="dashboard.php?view=settings">
                            <input type="hidden" name="settings_action" id="thread_action" value="add_thread">
                            <input type="hidden" name="thread_map_id" id="thread_map_id">
                            <input type="hidden" name="group_id" id="thread_group_id">
                            <div class="settings-header" style="background: linear-gradient(90deg, #28a745, #1e7e34);">
                                <i class="fas fa-hashtag text-xl"></i>
                                <h5 class="modal-title font-bold m-0 text-white" id="threadModalLabel">គ្រប់គ្រង Thread</h5>
                                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" style="filter: brightness(0) invert(1);"></button>
                            </div>
                            <div class="modal-body p-6 space-y-5">
                                <div>
                                    <label class="form-label">ភ្ជាប់ជាមួយប្រភេទ (Category Match)</label>
                                    <select name="thread_category" id="modal_thread_category" class="form-select">
                                        <?php foreach ($telegramAllCategories as $tc): ?>
                                            <option value="<?php echo htmlspecialchars($tc); ?>"><?php echo htmlspecialchars($tc); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="text-[10px] text-text-secondary mt-1 italic">ប្រព័ន្ធនឹងផ្ញើទៅ Thread នេះ បើផ្នែកក្នុងរបាយការណ៍ត្រូវគ្នា</p>
                                </div>
                                <div>
                                    <label class="form-label">ឈ្មោះសម្គាល់ / តួនាទី</label>
                                    <input type="text" name="thread_name" id="modal_thread_name" class="form-input" placeholder="ឧ. IT Department" required>
                                </div>
                                <div>
                                    <label class="form-label">Message Thread ID</label>
                                    <input type="text" name="thread_id" id="modal_thread_id" class="form-input" placeholder="ឧ. 12" required>
                                </div>
                            </div>
                            <div class="modal-footer bg-gray-50 rounded-b-2xl">
                                <button type="button" class="btn-base" data-bs-dismiss="modal">បោះបង់</button>
                                <button type="submit" class="btn-base btn-success px-8">រក្សាទុក</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modals for Settings (Categories) -->
            <div class="modal fade" id="catModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-none shadow-2xl">
                        <form method="POST" action="dashboard.php?view=settings">
                            <input type="hidden" name="settings_action" id="cat_action" value="add_category">
                            <input type="hidden" name="category_id" id="cat_id">
                            <div class="settings-header" style="background: linear-gradient(90deg, #343a40, #000);">
                                <i class="fas fa-tags text-xl"></i>
                                <h5 class="modal-title font-bold m-0 text-white" id="catModalLabel">គ្រប់គ្រងប្រភេទ</h5>
                                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal" style="filter: brightness(0) invert(1);"></button>
                            </div>
                            <div class="modal-body p-6 space-y-5">
                                <div>
                                    <label class="form-label">ប្រភេទមេ (Root Type)</label>
                                    <select name="category_type" id="modal_cat_type" class="form-select">
                                        <?php foreach ($category_types as $t): ?>
                                            <option value="<?php echo $t; ?>"><?php echo $khmer_names[$t]; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">ឈ្មោះ (Title)</label>
                                    <input type="text" name="category_name" id="modal_cat_name" class="form-input" placeholder="បញ្ចូលឈ្មោះ..." required>
                                </div>
                            </div>
                            <div class="modal-footer bg-gray-50 rounded-b-2xl">
                                <button type="button" class="btn-base" data-bs-dismiss="modal">បោះបង់</button>
                                <button type="submit" class="btn-base btn-primary px-8">រក្សាទុក</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <script>
                function toggleToken() {
                    const input = document.getElementById('bot_token');
                    const icon = document.querySelector('.token-toggle');
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.replace('fa-eye', 'fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.replace('fa-eye-slash', 'fa-eye');
                    }
                }
                function editGroup(group) {
                    document.getElementById('group_action').value = 'update_group';
                    document.getElementById('group_id').value = group.group_id;
                    document.getElementById('modal_group_name').value = group.name;
                    document.getElementById('modal_chat_id').value = group.chat_id;
                    document.getElementById('modal_report_format').value = group.report_format;
                    document.getElementById('groupModalLabel').innerText = 'កែប្រែព័ត៌មានក្រុម';
                    new bootstrap.Modal(document.getElementById('groupModal')).show();
                }
                function addThread(groupId) {
                    document.getElementById('thread_action').value = 'add_thread';
                    document.getElementById('thread_group_id').value = groupId;
                    document.getElementById('modal_thread_name').value = '';
                    document.getElementById('modal_thread_id').value = '';
                    document.getElementById('threadModalLabel').innerText = 'បន្ថែម Thread ថ្មី';
                    new bootstrap.Modal(document.getElementById('threadModal')).show();
                }
                function editThread(thread) {
                    document.getElementById('thread_action').value = 'update_thread';
                    document.getElementById('thread_map_id').value = thread.thread_map_id;
                    document.getElementById('modal_thread_category').value = thread.category;
                    document.getElementById('modal_thread_name').value = thread.position;
                    document.getElementById('modal_thread_id').value = thread.thread_id;
                    document.getElementById('threadModalLabel').innerText = 'កែប្រែព័ត៌មាន Thread';
                    new bootstrap.Modal(document.getElementById('threadModal')).show();
                }
                function addCategory(type) {
                    document.getElementById('cat_action').value = 'add_category';
                    document.getElementById('modal_cat_type').value = type;
                    document.getElementById('modal_cat_name').value = '';
                    document.getElementById('catModalLabel').innerText = 'បន្ថែមប្រភេទថ្មី';
                    new bootstrap.Modal(document.getElementById('catModal')).show();
                }
                function editCategory(cat) {
                    document.getElementById('cat_action').value = 'edit_category';
                    document.getElementById('cat_id').value = cat.id;
                    document.getElementById('modal_cat_type').value = cat.type;
                    document.getElementById('modal_cat_name').value = cat.name;
                    document.getElementById('catModalLabel').innerText = 'កែប្រែព័ត៌មានប្រភេទ';
                    new bootstrap.Modal(document.getElementById('catModal')).show();
                }
                // Handle the initial Add Group button - we need a slightly different way to target it now
                document.addEventListener('DOMContentLoaded', function() {
                    const addGroupBtn = document.querySelector('[data-bs-target="#addGroupModal"]');
                    if (addGroupBtn) {
                       addGroupBtn.addEventListener('click', function(e) {
                            document.getElementById('group_action').value = 'add_group';
                            document.getElementById('modal_group_name').value = '';
                            document.getElementById('modal_chat_id').value = '';
                            document.getElementById('groupModalLabel').innerText = 'បន្ថែមក្រុមថ្មី';
                        });
                    }
                });
            </script>
        <?php elseif ($view === 'permissions'): ?>
            <div id="permissions-view">
                <div class="d-flex justify-content-between align-items-center mb-4"><h2 class="text-2xl font-bold text-accent-hover">គ្រប់គ្រង Sidebar និងការកំណត់សិទ្ធ</h2><button class="btn-base btn-success" onclick="openMenuModal()"><i class="fas fa-plus mr-2"></i> បន្ថែមម៉ឺនុយថ្មី</button></div>
                <div class="card-base p-6">
                    <form method="POST" action="dashboard.php?view=permissions">
                        <input type="hidden" name="action" value="save_permissions">
                        <div class="table-responsive"><table class="table table-dark table-hover align-middle"><thead><tr class="text-accent-color"><th style="width: 30%; color: black;">ឈ្មោះម៉ឺនុយ</th><th>តួនាទីដែលអាចមើលឃើញ</th><th class="text-end">សកម្មភាព</th></tr></thead><tbody>
                        <?php render_menu_rows($grouped_menus, $all_roles); ?>
                        </tbody></table></div>
                        <div class="mt-6 text-end"><button type="submit" class="btn-base btn-primary"><i class="fas fa-save mr-2"></i> រក្សាទុកការផ្លាស់ប្តូរសិទ្ធ</button></div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- MODALS SECTION -->
    <div class="modal fade" id="contentModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title text-accent-hover font-bold" id="contentModalLabel">ខ្លឹមសាររបាយការណ៍</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="modalContent" style="white-space: pre-wrap; max-height: 60vh; overflow-y: auto;"></div><div class="modal-footer"><button type="button" class="btn-base btn-secondary" data-bs-dismiss="modal">បិទ</button></div></div></div></div>
    <div class="modal fade" id="taskModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="dashboard.php?view=checklist"><div class="modal-header"><h5 class="modal-title text-accent-hover font-bold" id="taskModalLabel">បន្ថែមការងារថ្មី</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body space-y-4"><div><label for="task" class="form-label">ការងារ</label><input type="text" name="task" class="form-input w-full" required></div><div><label for="due_date" class="form-label">កាលបរិច្ឆេទសម្រេច</label><input type="date" name="due_date" class="form-input w-full" required></div><div><label for="category" class="form-label">ប្រភេទ</label><div class="flex gap-2"><select name="category" class="form-select w-full" required><?php foreach ($categories as $c): ?><option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?></select><button type="button" class="btn-base btn-primary p-3 !text-lg" data-bs-toggle="modal" data-bs-target="#categoryModal" title="បន្ថែមប្រភេទ">+</button></div></div></div><div class="modal-footer"><button type="button" class="btn-base " data-bs-dismiss="modal">បោះបង់</button><button type="submit" class="btn-base btn-primary">រក្សាទុក</button></div></form></div></div></div>
    <div class="modal fade" id="categoryModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="dashboard.php?view=checklist"><div class="modal-header"><h5 class="modal-title text-accent-hover font-bold" id="categoryModalLabel">បន្ថែមប្រភេទថ្មី</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><label for="new_category" class="form-label">ឈ្មោះប្រភេទ</label><input type="text" name="new_category" class="form-input w-full" required></div><div class="modal-footer"><button type="button" class="btn-base btn-secondary" data-bs-dismiss="modal">បោះបង់</button><button type="submit" class="btn-base btn-primary">បន្ថែម</button></div></form></div></div></div>
    
    <!-- ## MODIFICATION START: Edit User Modal ## -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-white overflow-hidden border-0 shadow-2xl rounded-3xl">
                <form id="editUserForm" enctype="multipart/form-data">
                    <div class="px-6 py-5 bg-slate-900 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shadow-lg shadow-amber-500/20">
                                <i class="fas fa-user-edit"></i>
                            </div>
                            <h5 class="text-lg font-black text-white tracking-tight m-0" id="editUserModalLabel">កែសម្រួលព័ត៌មានបុគ្គលិក</h5>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="w-10 h-10 rounded-xl bg-white/10 text-white hover:bg-white/20 transition-all flex items-center justify-center border border-white/10" id="editUserSettingsBtn" title="កំណត់ Field">
                                <i class="fas fa-cog text-sm"></i>
                            </button>
                            <button type="button" class="w-10 h-10 rounded-xl bg-white/10 text-white hover:bg-white/20 transition-all flex items-center justify-center border border-white/10" data-bs-dismiss="modal">
                                <i class="fas fa-times text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <div class="modal-body p-0">
                        <input type="hidden" name="user_id" id="editUserId">
                        <input type="hidden" name="existing_image_url" id="existingImageUrl">
                        <input type="hidden" name="existing_jd_url" id="existingJdUrl">
                        <input type="hidden" name="existing_workflow_url" id="existingWorkflowUrl">
                        <input type="hidden" name="existing_bank_qr_url" id="existingBankQrUrl">
                        
                        <!-- Premium Tabs -->
                        <div class="bg-slate-50 border-b border-slate-200 px-6 pt-4">
                            <ul class="nav nav-tabs border-0 flex gap-6" id="edit-user-tabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active !border-0 !bg-transparent pb-3 px-0 relative group" id="edit-account-tab" data-bs-toggle="tab" data-bs-target="#edit-account-pane" type="button" role="tab">
                                        <span class="text-xs font-black uppercase tracking-widest text-slate-600 group-[.active]:text-amber-500 transition-colors">គណនី</span>
                                        <div class="absolute bottom-0 left-0 w-0 h-1 bg-amber-500 rounded-full transition-all duration-300 group-[.active]:w-full"></div>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link !border-0 !bg-transparent pb-3 px-0 relative group" id="edit-personnel-tab" data-bs-toggle="tab" data-bs-target="#edit-personnel-pane" type="button" role="tab">
                                        <span class="text-xs font-black uppercase tracking-widest text-slate-600 group-[.active]:text-amber-500 transition-colors">ព័ត៌មានបុគ្គលិក</span>
                                        <div class="absolute bottom-0 left-0 w-0 h-1 bg-amber-500 rounded-full transition-all duration-300 group-[.active]:w-full"></div>
                                    </button>
                                </li>
                                <?php if ($canManagePayrollInfo): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link !border-0 !bg-transparent pb-3 px-0 relative group" id="edit-payroll-tab" data-bs-toggle="tab" data-bs-target="#edit-payroll-pane" type="button" role="tab">
                                        <span class="text-xs font-black uppercase tracking-widest text-slate-600 group-[.active]:text-amber-500 transition-colors">Payroll</span>
                                        <div class="absolute bottom-0 left-0 w-0 h-1 bg-amber-500 rounded-full transition-all duration-300 group-[.active]:w-full"></div>
                                    </button>
                                </li>
                                <?php endif; ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link !border-0 !bg-transparent pb-3 px-0 relative group" id="edit-documents-tab" data-bs-toggle="tab" data-bs-target="#edit-documents-pane" type="button" role="tab">
                                        <span class="text-xs font-black uppercase tracking-widest text-slate-600 group-[.active]:text-amber-500 transition-colors">ឯកសារ/រូបភាព</span>
                                        <div class="absolute bottom-0 left-0 w-0 h-1 bg-amber-500 rounded-full transition-all duration-300 group-[.active]:w-full"></div>
                                    </button>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="p-8">
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="edit-account-pane" role="tabpanel">
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="edit_employee_id" class="form-label">អត្តលេខ</label><input type="text" name="employee_id" id="edit_employee_id" class="form-input w-full"></div>
                    <div class="col-md-6 mb-3"><label for="editGender" class="form-label">ភេទ</label><select name="gender" id="editGender" class="form-select w-full"><option value="">-- ជ្រើសរើស --</option><option value="male">ប្រុស</option><option value="female">ស្រី</option></select></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="editFullName" class="form-label">ឈ្មោះពេញ<span class="text-danger">*</span></label><input type="text" name="full_name" id="editFullName" class="form-input w-full" required></div>
                    <div class="col-md-6 mb-3"><label for="edit_latin_name" class="form-label">ឈ្មោះអក្សរឡាតាំង</label><input type="text" name="latin_name" id="edit_latin_name" class="form-input w-full"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="editRole" class="form-label">តួនាទី<span class="text-danger">*</span></label><select name="role" id="editRole" class="form-select w-full" required><option value="employee">Employee</option><option value="admin">Admin</option><option value="accounting">Accounting</option><option value="hr">HR</option><option value="administration">Administration</option></select></div>
                    <div class="col-md-6 mb-3"><label for="editPosition" class="form-label">តួនាទី</label><input type="text" name="position" id="editPosition" class="form-input w-full"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="editDepartment" class="form-label">ផ្នែក</label><input type="text" name="department" id="editDepartment" class="form-input w-full"></div>
                    <div class="col-md-6 mb-3"><label for="editUsername" class="form-label">ឈ្មោះគណនី (Username)<span class="text-danger">*</span></label><input type="text" name="username" id="editUsername" class="form-input w-full" required></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="editPassword" class="form-label">លេខសម្ងាត់ថ្មី (ទុកឲ្យនៅទំនេរ បើមិនចង់ប្តូរ)</label><input type="password" name="password" id="editPassword" class="form-input w-full"></div>
                    <div class="col-md-6 mb-3"><label for="editEmail" class="form-label">អ៊ីមែល<span class="text-danger">*</span></label><input type="email" name="email" id="editEmail" class="form-input w-full" required></div>
                </div>
                <div class="row">
                    <div class="col-12 mb-3"><label for="edit_current_address" class="form-label">អាសយដ្ឋានបច្ចុបប្បន្ន</label><input type="text" name="current_address" id="edit_current_address" class="form-input w-full"></div>
                </div>
            </div>
            <div class="tab-pane fade" id="edit-personnel-pane" role="tabpanel">
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="edit_start_date" class="form-label">ថ្ងៃចូលធ្វើការ</label><input type="date" name="start_date" id="edit_start_date" class="form-input w-full"></div>
                    <div class="col-md-6 mb-3"><label for="edit_marital_status" class="form-label">ស្ថានភាពគ្រួសារ</label><select name="marital_status" id="edit_marital_status" class="form-select w-full"><option value="">-- ជ្រើសរើស --</option><option value="ប្តី/ប្រពន្ធ">ប្តី/ប្រពន្ធ</option><option value="កូន">កូន</option><option value="នៅលាវ">នៅលាវ</option></select></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3" id="edit_children_count_container" style="display: none;"><label for="edit_number_of_children" class="form-label">ចំនួនកូន</label><input type="number" name="number_of_children" id="edit_number_of_children" class="form-input w-full" min="0" placeholder="ចំនួនកូន"></div>
                    <div class="col-md-6 mb-3"><label for="edit_contract_start" class="form-label">កិច្ចសន្យាការងារ - ចាប់ផ្តើម</label><input type="date" name="contract_start" id="edit_contract_start" class="form-input w-full"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="edit_contract_end" class="form-label">កិច្ចសន្យាការងារ - បញ្ជប់</label><input type="date" name="contract_end" id="edit_contract_end" class="form-input w-full"></div>
                    <div class="col-md-6 mb-3"><label for="edit_contract_type" class="form-label">កិច្ចសន្យាការងារ - ប្រភេទ</label><input type="text" name="contract_type" id="edit_contract_type" class="form-input w-full" placeholder="e.g., Full-time, Part-time"></div>
                </div>
                <div class="row"><div class="col-12 mb-3"><label for="edit_manager_id" class="form-label">ជ្រើសរើសមេ (Manager)</label><select name="manager_id" id="edit_manager_id" class="form-select w-full"><option value="">-- គ្មានមេ --</option><?php if (!empty($employees_flat_for_dropdowns)): foreach ($employees_flat_for_dropdowns as $manager): ?><option value="<?php echo htmlspecialchars($manager['id']); ?>"><?php echo htmlspecialchars($manager['full_name']); ?></option><?php endforeach; endif; ?></select></div></div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="editAnnualLeave" class="form-label">ចំនួន AL (Annual Leave)</label><input type="number" step="0.5" name="annual_leave_days" id="editAnnualLeave" class="form-input w-full" placeholder="e.g., 12.5"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="editAnnualLeaveRemaining" class="form-label">AL នៅសល់ (Remaining)</label><input type="number" step="0.5" id="editAnnualLeaveRemaining" class="form-input w-full" readonly></div>
                </div>
            </div>
            <?php if ($canManagePayrollInfo): ?>
            <div class="tab-pane fade" id="edit-payroll-pane" role="tabpanel">
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="editBaseSalary" class="form-label">ប្រាក់ខែគោល ($)</label><input type="number" step="0.01" name="base_salary" id="editBaseSalary" class="form-input w-full"></div>
                    <div class="col-md-6 mb-3"><label for="editNssfId" class="form-label">លេខ ប.ស.ស (NSSF ID)</label><input type="text" name="nssf_id" id="editNssfId" class="form-input w-full"></div>
                </div>
                <div class="row"><div class="col-md-6 mb-3"><label for="editBankName" class="form-label">ឈ្មោះធនាគារ</label><input type="text" name="bank_name" id="editBankName" class="form-input w-full"></div><div class="col-md-6 mb-3"><label for="editBankAccountNumber" class="form-label">លេខគណនីធនាគារ</label><input type="text" name="bank_account_number" id="editBankAccountNumber" class="form-input w-full"></div></div>
                <div class="row"><div class="col-12 mb-3"><label class="form-label">Bank QR Code</label>
                    <div class="d-flex align-items-center gap-4">
                        <img id="editBankQrPreview" src="https://via.placeholder.com/128?text=Preview" alt="QR Preview" class="w-32 h-32 object-contain border-2 border-border-color bg-white p-1 rounded-lg">
                        <div class="flex-grow-1">
                            <ul class="nav nav-tabs" id="edit-qr-tabs" role="tablist">
                                <li class="nav-item" role="presentation"><button class="nav-link active" id="edit-upload-tab" data-bs-toggle="tab" data-bs-target="#edit-upload-pane" type="button" role="tab"><i class="fas fa-upload me-2"></i>Upload រូបភាព</button></li>
                                <li class="nav-item" role="presentation"><button class="nav-link" id="edit-generate-tab" data-bs-toggle="tab" data-bs-target="#edit-generate-pane" type="button" role="tab"><i class="fas fa-cogs me-2"></i>បង្កើតពីទិន្នន័យ</button></li>
                            </ul>
                            <div class="tab-content" id="edit-qr-tabs-content">
                                <div class="tab-pane fade show active" id="edit-upload-pane" role="tabpanel">
                                    <p class="text-sm mb-2 text-text-secondary">ជ្រើសរើសរូបភាព QR Code ដែលមានស្រាប់។</p>
                                    <div class="d-flex flex-column gap-2">
                                        <label for="editBankQrFile" class="btn-base btn-secondary w-full"><span><i class="fas fa-file-image"></i> ផ្លាស់ប្តូរ QR</span></label>
                                        <input type="file" id="editBankQrFile" class="d-none" accept="image/jpeg, image/png">
                                        <button type="button" id="editExistingQrButton" class="btn-base btn-primary w-full"><span><i class="fas fa-crop-alt"></i> កែសម្រួល Crop</span></button>
                                    </div>
                                </div>
                                <div class="tab-pane fade" id="edit-generate-pane" role="tabpanel">
                                    <label for="edit_qr_data_input" class="form-label text-sm">បិទភ្ជាប់ទិន្នន័យ KHQR:</label>
                                    <textarea id="edit_qr_data_input" rows="3" class="form-textarea w-full mb-2" placeholder="e.g., 000201..."></textarea>
                                    <button type="button" id="edit_generate_qr_btn" class="btn-base btn-success w-full">បង្កើត QR Code</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="tab-pane fade" id="edit-documents-pane" role="tabpanel">
                <div class="mb-3"><label class="form-label">រូបភាពបច្ចុប្បន្ន</label><img id="editImagePreview" src="" alt="Current Avatar" style="width:96px; height:96px; border-radius:50%; object-fit:cover; border:2px solid #ccc; margin-bottom:8px;"><label for="editImageFile" class="form-label">ផ្លាស់ប្តូររូបភាព (ជា File JPG, PNG, GIF)</label><input type="file" name="image_file" id="editImageFile" class="form-input w-full" accept="image/*"></div><div class="row"><div class="col-md-6 mb-3"><label for="editJdPdf" class="form-label">ឯកសារ JD (PDF - មិនតម្រូវ)</label><div id="currentJdLink" class="mb-2"></div><input type="file" name="jd_pdf" id="editJdPdf" class="form-input w-full" accept=".pdf"></div><div class="col-md-6 mb-3"><label for="editWorkflowPdf" class="form-label">ឯកសារ Workflow (PDF - មិនតម្រូវ)</label><div id="currentWorkflowLink" class="mb-2"></div><input type="file" name="workflow_pdf" id="editWorkflowPdf" class="form-input w-full" accept=".pdf"></div></div>
            </div>
        </div></div></div><div class="modal-footer"><button type="button" class="btn-base btn-secondary" data-bs-dismiss="modal">បោះបង់</button><button type="submit" class="btn-base btn-primary">រក្សាទុកការផ្លាស់ប្តូរ</button></div></form></div></div></div>
    
    <!-- ## MODIFICATION START: Add User Modal ## -->
    <div class="modal fade" id="addUserModal" tabindex="-1" style="z-index: 10555;">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-white overflow-hidden border-0 shadow-2xl rounded-3xl">
                <form id="addUserForm" enctype="multipart/form-data">
                    <div class="px-6 py-5 bg-slate-900 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shadow-lg shadow-amber-500/20">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <h5 class="text-lg font-black text-white tracking-tight m-0" id="addUserModalLabel">បន្ថែមបុគ្គលិកថ្មី</h5>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button" class="w-10 h-10 rounded-xl bg-white/10 text-white hover:bg-white/20 transition-all flex items-center justify-center border border-white/10" id="addUserSettingsBtn" title="កំណត់ Field">
                                <i class="fas fa-cog text-sm"></i>
                            </button>
                            <button type="button" class="w-10 h-10 rounded-xl bg-white/10 text-white hover:bg-white/20 transition-all flex items-center justify-center border border-white/10" data-bs-dismiss="modal">
                                <i class="fas fa-times text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <div class="modal-body p-0">
                        <!-- Premium Tabs -->
                        <div class="bg-slate-50 border-b border-slate-200 px-6 pt-4">
                            <ul class="nav nav-tabs border-0 flex gap-6" id="add-user-tabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active !border-0 !bg-transparent pb-3 px-0 relative group" id="account-tab" data-bs-toggle="tab" data-bs-target="#account-pane" type="button" role="tab">
                                        <span class="text-xs font-black uppercase tracking-widest text-slate-600 group-[.active]:text-amber-500 transition-colors">គណនី</span>
                                        <div class="absolute bottom-0 left-0 w-0 h-1 bg-amber-500 rounded-full transition-all duration-300 group-[.active]:w-full"></div>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link !border-0 !bg-transparent pb-3 px-0 relative group" id="personnel-tab" data-bs-toggle="tab" data-bs-target="#personnel-pane" type="button" role="tab">
                                        <span class="text-xs font-black uppercase tracking-widest text-slate-600 group-[.active]:text-amber-500 transition-colors">ព័ត៌មានបុគ្គលិក</span>
                                        <div class="absolute bottom-0 left-0 w-0 h-1 bg-amber-500 rounded-full transition-all duration-300 group-[.active]:w-full"></div>
                                    </button>
                                </li>
                                <?php if ($canManagePayrollInfo): ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link !border-0 !bg-transparent pb-3 px-0 relative group" id="payroll-tab" data-bs-toggle="tab" data-bs-target="#payroll-pane" type="button" role="tab">
                                        <span class="text-xs font-black uppercase tracking-widest text-slate-600 group-[.active]:text-amber-500 transition-colors">Payroll</span>
                                        <div class="absolute bottom-0 left-0 w-0 h-1 bg-amber-500 rounded-full transition-all duration-300 group-[.active]:w-full"></div>
                                    </button>
                                </li>
                                <?php endif; ?>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link !border-0 !bg-transparent pb-3 px-0 relative group" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents-pane" type="button" role="tab">
                                        <span class="text-xs font-black uppercase tracking-widest text-slate-600 group-[.active]:text-amber-500 transition-colors">ឯកសារ/រូបភាព</span>
                                        <div class="absolute bottom-0 left-0 w-0 h-1 bg-amber-500 rounded-full transition-all duration-300 group-[.active]:w-full"></div>
                                    </button>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="p-8">
                            <div class="tab-content">
                                <div class="tab-pane fade show active" id="account-pane" role="tabpanel">
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="add_employee_id" class="form-label">អត្តលេខ</label><input type="text" name="employee_id" id="add_employee_id" class="form-input w-full"></div>
                    <div class="col-md-6 mb-3"><label for="add_gender" class="form-label">ភេទ</label><select name="gender" id="add_gender" class="form-select w-full"><option value="">-- ជ្រើសរើស --</option><option value="male">ប្រុស</option><option value="female">ស្រី</option></select></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="add_full_name" class="form-label">ឈ្មោះពេញ<span class="text-danger">*</span></label><input type="text" name="full_name" id="add_full_name" class="form-input w-full" required></div>
                    <div class="col-md-6 mb-3"><label for="add_latin_name" class="form-label">ឈ្មោះអក្សរឡាតាំង</label><input type="text" name="latin_name" id="add_latin_name" class="form-input w-full"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="add_role" class="form-label">តួនាទី</label><select name="role" id="add_role" class="form-select w-full" required><option value="employee" selected>Employee</option><option value="admin">Admin</option><option value="accounting">Accounting</option><option value="hr">HR</option><option value="administration">Administration</option></select></div>
                    <div class="col-md-6 mb-3"><label for="add_position" class="form-label">តួនាទី<span class="text-danger">*</span></label><input type="text" name="position" id="add_position" class="form-input w-full" placeholder="e.g., Manager, Staff" required></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="add_department" class="form-label">ផ្នែក</label><input type="text" name="department" id="add_department" class="form-input w-full" placeholder="e.g., IT, Sales, HR"></div>
                    <div class="col-md-6 mb-3"><label for="add_username" class="form-label">ឈ្មោះគណនី (Username)<span class="text-danger">*</span></label><input type="text" name="username" id="add_username" class="form-input w-full" required></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="add_password" class="form-label">លេខសម្ងាត់<span class="text-danger">*</span></label><input type="password" name="password" id="add_password" class="form-input w-full" required></div>
                    <div class="col-md-6 mb-3"><label for="add_email" class="form-label">អ៊ីមែល<span class="text-danger">*</span></label><input type="email" name="email" id="add_email" class="form-input w-full" required></div>
                </div>
                <div class="row">
                    <div class="col-12 mb-3"><label for="add_current_address" class="form-label">អាសយដ្ឋានបច្ចុបប្បន្ន</label><input type="text" name="current_address" id="add_current_address" class="form-input w-full"></div>
                </div>
            </div>
            <div class="tab-pane fade" id="personnel-pane" role="tabpanel">
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="add_start_date" class="form-label">ថ្ងៃចូលធ្វើការ</label><input type="date" name="start_date" id="add_start_date" class="form-input w-full"></div>
                    <div class="col-md-6 mb-3"><label for="add_marital_status" class="form-label">ស្ថានភាពគ្រួសារ</label><select name="marital_status" id="add_marital_status" class="form-select w-full"><option value="">-- ជ្រើសរើស --</option><option value="ប្តី/ប្រពន្ធ">ប្តី/ប្រពន្ធ</option><option value="កូន">កូន</option><option value="នៅលាវ">នៅលាវ</option></select></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3" id="add_children_count_container" style="display: none;"><label for="add_number_of_children" class="form-label">ចំនួនកូន</label><input type="number" name="number_of_children" id="add_number_of_children" class="form-input w-full" min="0" placeholder="ចំនួនកូន"></div>
                    <div class="col-md-6 mb-3"><label for="add_contract_start" class="form-label">កិច្ចសន្យាការងារ - ចាប់ផ្តើម</label><input type="date" name="contract_start" id="add_contract_start" class="form-input w-full"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="add_contract_end" class="form-label">កិច្ចសន្យាការងារ - បញ្ជប់</label><input type="date" name="contract_end" id="add_contract_end" class="form-input w-full"></div>
                    <div class="col-md-6 mb-3"><label for="add_contract_type" class="form-label">កិច្ចសន្យាការងារ - ប្រភេទ</label><input type="text" name="contract_type" id="add_contract_type" class="form-input w-full" placeholder="e.g., Full-time, Part-time"></div>
                </div>
                <div class="row"><div class="col-12 mb-3"><label for="add_manager_id" class="form-label">ជ្រើសរើសមេ (Manager)</label><select name="manager_id" id="add_manager_id" class="form-select w-full"><option value="">-- គ្មានមេ --</option><?php if(!empty($employees_flat_for_dropdowns)): foreach ($employees_flat_for_dropdowns as $manager): ?><option value="<?php echo htmlspecialchars($manager['id']); ?>"><?php echo htmlspecialchars($manager['full_name']); ?></option><?php endforeach; endif; ?></select></div></div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="add_annual_leave_days" class="form-label">ចំនួន AL (Annual Leave)</label><input type="number" step="0.5" name="annual_leave_days" id="add_annual_leave_days" class="form-input w-full" placeholder="e.g., 12.5"></div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="addAnnualLeaveRemaining" class="form-label">AL នៅសល់ (Remaining)</label><input type="number" step="0.5" id="addAnnualLeaveRemaining" class="form-input w-full" readonly></div>
                </div>
            </div>
            <?php if ($canManagePayrollInfo): ?>
            <div class="tab-pane fade" id="payroll-pane" role="tabpanel">
                <div class="row">
                    <div class="col-md-6 mb-3"><label for="add_base_salary" class="form-label">ប្រាក់ខែគោល ($)</label><input type="number" step="0.01" name="base_salary" id="add_base_salary" class="form-input w-full" placeholder="0.00"></div>
                    <div class="col-md-6 mb-3"><label for="add_nssf_id" class="form-label">លេខ ប.ស.ស (NSSF ID)</label><input type="text" name="nssf_id" id="add_nssf_id" class="form-input w-full"></div>
                </div>
                <div class="row"><div class="col-md-6 mb-3"><label for="add_bank_name" class="form-label">ឈ្មោះធនាគារ</label><input type="text" name="bank_name" id="add_bank_name" class="form-input w-full"></div><div class="col-md-6 mb-3"><label for="add_bank_account_number" class="form-label">លេខគណនីធនាគារ</label><input type="text" name="bank_account_number" id="add_bank_account_number" class="form-input w-full"></div></div>
                <div class="row"><div class="col-12 mb-3"><label class="form-label">Bank QR Code</label>
                    <div class="d-flex align-items-center gap-4">
                        <img id="addBankQrPreview" src="https://via.placeholder.com/128?text=Preview" alt="QR Preview" class="w-32 h-32 object-contain border-2 border-border-color bg-white p-1 rounded-lg d-none">
                        <div class="flex-grow-1">
                            <ul class="nav nav-tabs" id="add-qr-tabs" role="tablist">
                                <li class="nav-item" role="presentation"><button class="nav-link active" id="add-upload-tab" data-bs-toggle="tab" data-bs-target="#add-upload-pane" type="button" role="tab"><i class="fas fa-upload me-2"></i>Upload រូបភាព</button></li>
                                <li class="nav-item" role="presentation"><button class="nav-link" id="add-generate-tab" data-bs-toggle="tab" data-bs-target="#add-generate-pane" type="button" role="tab"><i class="fas fa-cogs me-2"></i>បង្កើតពីទិន្នន័យ</button></li>
                            </ul>
                            <div class="tab-content" id="add-qr-tabs-content">
                                <div class="tab-pane fade show active" id="add-upload-pane" role="tabpanel">
                                    <p class="text-sm mb-2 text-text-secondary">ជ្រើសរើសរូបភាព QR Code ដែលមានស្រាប់។</p>
                                    <label for="add_bank_qr_file" class="btn-base btn-secondary w-full"><span><i class="fas fa-file-image"></i> ជ្រើសរើសរូបភាព QR</span></label>
                                    <input type="file" id="add_bank_qr_file" class="d-none" accept="image/jpeg, image/png">
                                </div>
                                <div class="tab-pane fade" id="add-generate-pane" role="tabpanel">
                                    <label for="add_qr_data_input" class="form-label text-sm">បិទភ្ជាប់ទិន្នន័យ KHQR:</label>
                                    <textarea id="add_qr_data_input" rows="3" class="form-textarea w-full mb-2" placeholder="e.g., 000201..."></textarea>
                                    <button type="button" id="add_generate_qr_btn" class="btn-base btn-success w-full">បង្កើត QR Code</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <div class="tab-pane fade" id="documents-pane" role="tabpanel">
                <div class="mb-3"><label for="add_profile_image" class="form-label">រូបភាព Profile<span class="text-danger">*</span></label><input type="file" name="profile_image" id="add_profile_image" class="form-input w-full" accept="image/*" required></div><div class="row"><div class="col-md-6 mb-3"><label for="add_jd_pdf" class="form-label">ឯកសារ JD (PDF - មិនតម្រូវ)</label><input type="file" name="jd_pdf" id="add_jd_pdf" class="form-input w-full" accept=".pdf"></div><div class="col-md-6 mb-3"><label for="add_workflow_pdf" class="form-label">ឯកសារ Workflow (PDF - មិនតម្រូវ)</label><input type="file" name="workflow_pdf" id="add_workflow_pdf" class="form-input w-full" accept=".pdf"></div></div>
            </div>
        </div></div></div><div class="modal-footer"><button type="button" class="btn-base btn-secondary" data-bs-dismiss="modal">បោះបង់</button><button type="submit" class="btn-base btn-primary">បន្ថែមអ្នកប្រើប្រាស់</button></div></form></div></div></div>

    <!-- Field Settings Modal for Add User -->
    <div class="modal fade" id="addUserFieldSettingsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-accent-hover font-bold"><i class="fas fa-cog mr-2"></i>កំណត់ Field ដែលទាមទារបំពេញ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-sm text-text-secondary mb-4">ជ្រើសរើស Field ដែលអ្នកចង់ឲ្យទាមទារបំពេញ៖</p>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <input type="checkbox" id="add_required_full_name" class="form-checkbox mr-3" checked>
                            <label for="add_required_full_name" class="form-label mb-0">ឈ្មោះពេញ</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="add_required_username" class="form-checkbox mr-3" checked>
                            <label for="add_required_username" class="form-label mb-0">ឈ្មោះគណនី (Username)</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="add_required_password" class="form-checkbox mr-3" checked>
                            <label for="add_required_password" class="form-label mb-0">លេខសម្ងាត់</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="add_required_email" class="form-checkbox mr-3" checked>
                            <label for="add_required_email" class="form-label mb-0">អ៊ីមែល</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="add_required_role" class="form-checkbox mr-3" checked>
                            <label for="add_required_role" class="form-label mb-0">តួនាទី</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="add_required_position" class="form-checkbox mr-3" checked>
                            <label for="add_required_position" class="form-label mb-0">តួនាទី (Position)</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-base btn-secondary" data-bs-dismiss="modal">បោះបង់</button>
                    <button type="button" class="btn-base btn-primary" id="applyAddUserSettings">អនុវត្ត</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Field Settings Modal for Edit User -->
    <div class="modal fade" id="editUserFieldSettingsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-accent-hover font-bold"><i class="fas fa-cog mr-2"></i>កំណត់ Field ដែលទាមទារបំពេញ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-sm text-text-secondary mb-4">ជ្រើសរើស Field ដែលអ្នកចង់ឲ្យទាមទារបំពេញ៖</p>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <input type="checkbox" id="edit_required_full_name" class="form-checkbox mr-3" checked>
                            <label for="edit_required_full_name" class="form-label mb-0">ឈ្មោះពេញ</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="edit_required_username" class="form-checkbox mr-3" checked>
                            <label for="edit_required_username" class="form-label mb-0">ឈ្មោះគណនី (Username)</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="edit_required_email" class="form-checkbox mr-3" checked>
                            <label for="edit_required_email" class="form-label mb-0">អ៊ីមែល</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="edit_required_role" class="form-checkbox mr-3" checked>
                            <label for="edit_required_role" class="form-label mb-0">តួនាទី</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-base btn-secondary" data-bs-dismiss="modal">បោះបង់</button>
                    <button type="button" class="btn-base btn-primary" id="applyEditUserSettings">អនុវត្ត</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="payrollDetailsModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title text-accent-hover font-bold" id="payrollDetailsModalLabel">ព័ត៌មានលម្អិត</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><h6 class="font-bold text-white mb-3" id="detailsEmployeeName"></h6><div class="mb-4"><p class="text-green-400 font-semibold">បញ្ជីប្រាក់បន្ថែម:</p><div id="detailsBonuses" class="text-text-secondary pl-4"></div></div><div><p class="text-red-400 font-semibold">បញ្ជីប្រាក់កាត់:</p><div id="detailsDeductions" class="text-text-secondary pl-4"></div></div></div></div></div></div>
    <div class="modal fade" id="confirmFinalizeModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title text-accent-hover font-bold" id="confirmFinalizeModalLabel"><i class="fas fa-exclamation-triangle mr-2 text-yellow-500"></i> បញ្ចប់ការគណនា?</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p class="text-lg text-white mb-4">តើអ្នកប្រាកដទេថាចង់ **បញ្ចប់ការគណនា** ប្រាក់បៀវត្សសម្រាប់ខែ <span class="font-bold text-accent-color" id="modalPayrollMonth"></span> នេះ?</p><div class="p-3 card-base border border-amber-500/50 rounded-lg"><p class="font-semibold text-text-secondary">សេចក្តីជូនដំណឹង:</p><ul class="list-disc list-inside text-sm text-text-secondary ml-3"><li>សកម្មភាពនេះនឹងបង្កើត **បញ្ជីបើកប្រាក់បៀវត្សថ្មី** ក្នុងប្រព័ន្ធ។</li><li>ស្ថានភាពនឹងក្លាយជា **"កំពុងរង់ចាំការអនុម័ត"**។</li><li>អ្នកនឹងមិនអាចកែសម្រួលការគណនានេះបានទៀតទេ។</li></ul></div></div><div class="modal-footer"><button type="button" class="btn-base" data-bs-dismiss="modal" style="background-color: var(--danger);"><i class="fas fa-times"></i> បោះបង់</button><button type="button" class="btn-base btn-success" id="confirmFinalizeBtn"><i class="fas fa-check-circle"></i> បញ្ជូនដើម្បីអនុម័ត</button></div></div></div></div>
    <div class="modal fade" id="approveModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="dashboard.php?view=payroll_approval"><div class="modal-header"><h5 class="modal-title text-accent-hover" id="approveModalLabel">បញ្ជាក់ការអនុម័ត</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="action" value="approve"><input type="hidden" name="run_id" id="approveRunId"><p>តើអ្នកពិតជាចង់អនុម័តបញ្ជីបើកប្រាក់បៀវត្សសម្រាប់ខែ <strong id="approveRunMonth"></strong> មែនទេ?</p><p class="text-text-secondary mt-3">សកម្មភាពនេះមិនអាចមិនធ្វើវិញបានទេ។</p></div><div class="modal-footer"><button type="button" class="btn-base btn-secondary" data-bs-dismiss="modal">បោះបង់</button><button type="submit" class="btn-base btn-success">យល់ព្រម អនុម័ត</button></div></form></div></div></div>
    <div class="modal fade" id="rejectModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="dashboard.php?view=payroll_approval"><div class="modal-header"><h5 class="modal-title text-accent-hover" id="rejectModalLabel">បដិសេធបញ្ជីបើកប្រាក់បៀវត្ស</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="action" value="reject"><input type="hidden" name="run_id" id="rejectRunId"><p class="mb-3">អ្នកកំពុងបដិសេធបញ្ជីបើកប្រាក់បៀវត្សសម្រាប់ខែ <strong id="rejectRunMonth"></strong>។</p><div class="form-group"><label for="rejection_reason" class="form-label">សូមបញ្ចូលហេតុផល (តម្រូវឲ្យបំពេញ)</label><textarea name="rejection_reason" id="rejection_reason" rows="4" class="form-textarea" required></textarea></div></div><div class="modal-footer"><button type="button" class="btn-base btn-secondary" data-bs-dismiss="modal">បោះបង់</button><button type="submit" class="btn-base btn-danger">យល់ព្រម បដិសេធ</button></div></form></div></div></div>
    <div id="markPaidModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center p-4 z-50 hidden"><div class="card-base w-full max-w-md p-0 mx-auto rounded-lg shadow-xl"><form method="POST" action="dashboard.php?view=payroll_payslip"><div class="p-6 border-b" style="border-color: var(--border-color);"><h5 class="text-xl font-bold text-accent-hover">បញ្ជាក់ការសម្គាល់ថាបានទូទាត់</h5></div><div class="p-6"><input type="hidden" name="action" value="mark_paid"><input type="hidden" name="run_id" id="paidRunId"><p>តើអ្នកពិតជាចង់សម្គាល់ថាបញ្ជីបើកប្រាក់បៀវត្សសម្រាប់ខែ <strong id="paidRunMonth"></strong> បានទូទាត់ហើយមែនទេ?</p><p class="text-text-secondary mt-2">សកម្មភាពនេះនឹងផ្លាស់ទីបញ្ជីទៅក្នុងប្រវត្តិ និងមិនអាចត្រឡប់វិញបានទេ។</p></div><div class="p-4 flex justify-end gap-3 bg-opacity-50" style="background-color: var(--secondary-bg);"><button type="button" class="btn-base btn-secondary" id="closeModalBtn">បោះបង់</button><button type="submit" class="btn-base btn-success">យល់ព្រម</button></div></form></div></div>

    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="dashboard.php?view=payroll_approval">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger font-bold" id="deleteModalLabel"><i class="fas fa-exclamation-triangle"></i> បញ្ជាក់ការលុប</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="run_id" id="deleteRunId">
                        <p>តើអ្នកពិតជាចង់លុបបញ្ជីបើកប្រាក់បៀវត្សសម្រាប់ខែ <strong id="deleteRunMonth"></strong> មែនទេ?</p>
                        <p class="text-warning small mt-3"><strong>ការព្រមាន:</strong> សកម្មភាពនេះនឹងលុបទិន្នន័យជាអចិន្ត្រៃយ៍ និងមិនអាចមិនធ្វើវិញបានទេ។</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-base btn-secondary" data-bs-dismiss="modal">បោះបង់</button>
                        <button type="submit" class="btn-base btn-danger">យល់ព្រម, លុប</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reset Payroll Modal -->
    <div class="modal fade" id="resetPayrollModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="dashboard.php?view=payroll_calculation">
                    <input type="hidden" name="reset_payroll" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger font-bold" id="resetPayrollModalLabel"><i class="fas fa-exclamation-triangle"></i> Reset Payroll Calculation</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-lg text-white mb-4">តើអ្នកប្រាកដទេថាចង់ **reset ការគណនាប្រាក់ខែ**?</p>
                        <div class="p-3 card-base border border-red-500/50 rounded-lg">
                            <p class="font-semibold text-red-400">សកម្មភាពនេះនឹង:</p>
                            <ul class="list-disc list-inside text-sm text-text-secondary ml-3">
                                <li>លុបបញ្ជីបើកប្រាក់បៀវត្សទាំងអស់</li>
                                <li>កំណត់បំណុលទាំងអស់ឡើងវិញទៅតម្លៃដើម (remaining = loan_amount)</li>
                                <li>លុបកំណត់ត្រាការទូទាត់បំណុលទាំងអស់</li>
                                <li>ស្ថានភាពបំណុលក្លាយជា 'active' វិញ</li>
                            </ul>
                            <p class="text-red-400 font-bold mt-2">សកម្មភាពនេះមិនអាចមិនធ្វើវិញបានទេ!</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-base btn-secondary" data-bs-dismiss="modal">បោះបង់</button>
                        <button type="submit" class="btn-base btn-danger">យល់ព្រម, Reset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- MODIFIED: Jump to Date Modal for Payroll Calculation -->
    <div class="modal fade" id="jumpToDateModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="GET" action="dashboard.php">
                    <input type="hidden" name="view" value="payroll_calculation">
                    <div class="modal-header">
                        <h5 class="modal-title text-accent-hover font-bold">លោតទៅកាន់ខែ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-6">
                                <label for="jump_month" class="form-label">ខែ</label>
                                <select name="month" id="jump_month" class="form-select">
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?php echo $m; ?>" <?php if ($m == $current_month) echo 'selected'; ?>><?php echo date('F', mktime(0, 0, 0, $m, 10)); ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="jump_year" class="form-label">ឆ្នាំ</label>
                                <select name="year" id="jump_year" class="form-select">
                                    <?php for ($y = date('Y') - 5; $y <= date('Y') + 1; $y++): ?>
                                        <option value="<?php echo $y; ?>" <?php if ($y == $current_year) echo 'selected'; ?>><?php echo $y; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-base btn-secondary" data-bs-dismiss="modal">បោះបង់</button>
                        <button type="submit" class="btn-base btn-primary">លោតទៅ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="menuModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="dashboard.php?view=permissions"><input type="hidden" name="action" value="save_menu_item"><input type="hidden" name="menu_id" id="menu_id"><div class="modal-header"><h5 class="modal-title" id="menuModalLabel"></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label for="menu_name" class="form-label">ឈ្មោះម៉ឺនុយ (Menu Name)</label><input type="text" class="form-control bg-dark text-white" id="menu_name" name="menu_name" required></div><div class="mb-3"><label for="menu_key" class="form-label">Key (e.g., 'dashboard')</label><input type="text" class="form-control bg-dark text-white" id="menu_key" name="menu_key" required></div><div class="mb-3"><label for="parent_key" class="form-label">ជាម៉ឺនុយកូនរបស់ (Parent Menu)</label><select class="form-select bg-dark text-white" id="parent_key" name="parent_key"><option value="">-- គ្មាន (ជាម៉ឺនុយមេ) --</option><?php if(isset($all_menus_for_permissions)) foreach ($all_menus_for_permissions as $parent_option): if (empty($parent_option['parent_key'])): ?><option value="<?php echo htmlspecialchars($parent_option['menu_key']); ?>"><?php echo htmlspecialchars($parent_option['menu_name']); ?></option><?php endif; endforeach; ?></select></div><div class="mb-3"><label for="menu_order" class="form-label">លេខរៀង (Menu Order)</label><input type="number" class="form-control bg-dark text-white" id="menu_order" name="menu_order" value="0" required></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">បោះបង់</button><button type="submit" class="btn btn-primary">រក្សាទុក</button></div></form></div></div></div>
    <div class="modal fade" id="deleteMenuModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="POST" action="dashboard.php?view=permissions"><div class="modal-header"><h5 class="modal-title text-danger font-bold"><i class="fas fa-exclamation-triangle"></i> បញ្ជាក់ការលុប</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>តើអ្នកពិតជាចង់លុបម៉ឺនុយ "<strong id="menuNameToDelete"></strong>" មែនទេ?</p><p class="text-warning small mt-3">សកម្មភាពនេះមិនអាចមិនធ្វើវិញបានទេ។</p><input type="hidden" name="action" value="delete_menu_item"><input type="hidden" name="menu_id" id="menuIdToDelete"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">បោះបង់</button><button type="submit" class="btn btn-danger">យល់ព្រម, លុប</button></div></form></div></div></div>

    <!-- NEW: Cropper Modal for QR Code -->
    <div class="modal fade" id="cropQrModal" tabindex="-1" aria-labelledby="cropQrModalLabel" aria-hidden="true"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title text-accent-hover font-bold" id="cropQrModalLabel">កាត់រូបភាព QR Code</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><div class="img-container" style="max-height: 50vh;"><img id="imageToCrop" src="" alt="Picture"></div></div><div class="modal-footer"><button type="button" class="btn-base btn-secondary" data-bs-dismiss="modal">បោះបង់</button><button type="button" class="btn-base btn-primary" id="cropAndSaveButton">កាត់ និងប្រើប្រាស់រូបភាព</button></div></div></div></div>

    <!-- Employee Details Modal -->
    <div class="modal fade" id="employeeDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ព័ត៌មានលម្អិតរបស់បុគ្គលិក</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="employeeDetailsBody">
                    <!-- content will be populated -->
                </div>
            </div>
        </div>
    </div>


    <script src="https://code.jquery.com/jquery-3.6.0.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js" defer></script>
    <!-- Cropper.js Script -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js" defer></script>
    <!-- QR Code Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>

    
    <script>
    // Define the function globally
    function showEmployeeDetails(row) {
        const data = row.dataset;
        const body = document.getElementById('employeeDetailsBody');
        const default_placeholder = 'https://via.placeholder.com/150';
        const imgUrl = data.image ? 'thumb.php?src=' + encodeURIComponent(data.image) + '&w=300&h=300' : default_placeholder;
        
        let content = `
            <div class="p-2">
                <div class="flex flex-col md:flex-row gap-8 items-start">
                    <!-- Profile Card -->
                    <div class="w-full md:w-1/3 flex flex-col items-center gap-4">
                        <div class="relative group">
                            <div class="absolute -inset-1 bg-gradient-to-tr from-amber-500 to-amber-200 rounded-3xl blur opacity-25 group-hover:opacity-50 transition duration-1000"></div>
                            <img src="${imgUrl}" alt="Avatar" class="relative w-48 h-48 rounded-3xl object-cover shadow-2xl border-4 border-white">
                            <div class="absolute -bottom-2 -right-2 bg-emerald-500 text-white w-10 h-10 rounded-2xl flex items-center justify-center border-4 border-white shadow-lg">
                                <i class="fas fa-check text-sm"></i>
                            </div>
                        </div>
                        <div class="text-center">
                            <h3 class="text-2xl font-black text-slate-800 mb-1">${data.name}</h3>
                            <p class="text-amber-500 font-bold uppercase tracking-widest text-[10px]">${data.position || 'N/A'}</p>
                            <div class="mt-4 flex flex-wrap justify-center gap-2">
                                <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-full text-[10px] font-black uppercase tracking-tighter shadow-sm border border-slate-200/50">${data.department || 'N/A'}</span>
                                <span class="px-3 py-1 bg-emerald-100 text-emerald-600 rounded-full text-[10px] font-black uppercase tracking-tighter shadow-sm border border-emerald-200/50">Active</span>
                            </div>
                        </div>
                    </div>

                    <!-- Details Grid -->
                    <div class="w-full md:w-2/3 space-y-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Basic Info Group -->
                            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100/80">
                                <label class="text-[10px] font-black text-slate-600 uppercase tracking-widest mb-3 block">ព័ត៌មានមូលដ្ឋាន</label>
                                <div class="space-y-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-amber-600 border border-slate-100"><i class="fas fa-at text-xs"></i></div>
                                        <div class="flex flex-col"><span class="text-[10px] text-slate-500 font-bold uppercase">Username</span><span class="text-sm font-black text-slate-800">${data.username || 'N/A'}</span></div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-amber-600 border border-slate-100"><i class="fas fa-envelope text-xs"></i></div>
                                        <div class="flex flex-col"><span class="text-[10px] text-slate-500 font-bold uppercase">Email</span><span class="text-sm font-black text-slate-800">${data.email || 'N/A'}</span></div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-amber-600 border border-slate-100"><i class="fas fa-venus-mars text-xs"></i></div>
                                        <div class="flex flex-col"><span class="text-[10px] text-slate-500 font-bold uppercase">Gender</span><span class="text-sm font-black text-slate-800">${data.gender === 'male' ? 'ប្រុស' : 'ស្រី'}</span></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Work Info Group -->
                            <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100/80">
                                <label class="text-[10px] font-black text-slate-600 uppercase tracking-widest mb-3 block">ព័ត៌មានការងារ</label>
                                <div class="space-y-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-amber-600 border border-slate-100"><i class="fas fa-id-badge text-xs"></i></div>
                                        <div class="flex flex-col"><span class="text-[10px] text-slate-500 font-bold uppercase">Employee ID</span><span class="text-sm font-black text-slate-800">#${data.employeeId || 'N/A'}</span></div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-amber-600 border border-slate-100"><i class="fas fa-calendar-star text-xs"></i></div>
                                        <div class="flex flex-col"><span class="text-[10px] text-slate-500 font-bold uppercase">Start Date</span><span class="text-sm font-black text-slate-800">${data.startDate || 'N/A'}</span></div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-white shadow-sm flex items-center justify-center text-amber-600 border border-slate-100"><i class="fas fa-umbrella-beach text-xs"></i></div>
                                        <div class="flex flex-col"><span class="text-[10px] text-slate-500 font-bold uppercase">Leave Bal.</span><span class="text-sm font-black text-slate-800">${data.al || '0.0'} Days</span></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Full Width Info -->
                        <div class="bg-slate-50 p-5 rounded-3xl border border-slate-100/80">
                             <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <span class="text-[10px] text-slate-500 font-black uppercase tracking-widest block mb-1">Contract End</span>
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-history text-rose-500 text-xs"></i>
                                        <span class="text-sm font-black text-slate-800">${data.contractEnd || 'មិនមានកំណត់'}</span>
                                    </div>
                                </div>
                                <div>
                                    <span class="text-[10px] text-slate-500 font-black uppercase tracking-widest block mb-1">Address</span>
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-location-dot text-slate-400 text-xs"></i>
                                        <span class="text-xs font-bold text-slate-700">${data.currentAddress || 'N/A'}</span>
                                    </div>
                                </div>
                             </div>
                        </div>

                        ${window.canManagePayrollInfo === 'true' && data.baseSalary ? `
                        <div class="bg-amber-50/50 p-6 rounded-3xl border border-amber-100/50">
                            <div class="flex items-center justify-between mb-4">
                                <label class="text-[10px] font-black text-amber-600 uppercase tracking-widest">ព័ត៌មានហិរញ្ញវត្ថុ (Payroll Information)</label>
                                <div class="px-3 py-1 bg-amber-500 text-white rounded-full text-[10px] font-black">PRIVATE</div>
                            </div>
                            <div class="grid grid-cols-2 gap-8">
                                <div class="flex flex-col gap-1">
                                    <span class="text-[10px] text-amber-600/60 font-black uppercase">Salary per month</span>
                                    <span class="text-2xl font-black text-amber-700">$${parseFloat(data.baseSalary).toLocaleString()}</span>
                                </div>
                                <div class="flex flex-col gap-1 border-l border-amber-100 pl-8">
                                    <span class="text-[10px] text-amber-600/60 font-black uppercase">Bank Info</span>
                                    <span class="text-xs font-black text-amber-700">${data.bankName || 'N/A'}</span>
                                    <span class="text-xs font-bold text-amber-600/80">${data.bankAccountNumber || ''}</span>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>`;
        
        body.innerHTML = content;
        
        // Update modal title to something modern
        const modalEl = document.getElementById('employeeDetailsModal');
        const titleEl = modalEl.querySelector('.modal-title');
        titleEl.innerHTML = `<div class="flex items-center gap-3"><div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center shadow-lg shadow-amber-500/20"><i class="fas fa-user-tie"></i></div><span class="font-black text-slate-800 tracking-tight">ព័ត៌មានបុគ្គលិកលម្អិត</span></div>`;
        
        if (typeof bootstrap !== 'undefined') {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        } else {
            console.error('Bootstrap is not loaded');
        }
    }

    function showNotification(message, type = 'success') {
        const container = document.getElementById('notification-container');
        if (!container) return;
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert-message ${type === 'success' ? 'alert-success' : 'alert-error'} animate-zoom-in`;
        alertDiv.textContent = message;
        alertDiv.style.minWidth = '300px';
        container.appendChild(alertDiv);
        setTimeout(() => {
            alertDiv.style.opacity = '0';
            alertDiv.style.transition = 'opacity 0.5s ease';
            setTimeout(() => alertDiv.remove(), 500);
        }, 5000);
    }

    document.addEventListener('DOMContentLoaded', async function() {
        // Loading screen helpers
        function showLoading() {
            // Loading screen removed
        }
        function hideLoading() {
            // Loading screen removed
        }
        // Fallback: hide after full load if nothing else hides it
        window.addEventListener('load', function() { hideLoading(); });
        // --- START AJAX REAL-TIME LOGIC ---
        const addUserForm = document.getElementById('addUserForm');
        const editUserForm = document.getElementById('editUserForm');
        const addUserModal = document.getElementById('addUserModal');
        const editUserModal = document.getElementById('editUserModal');
        const tableBodies = document.querySelectorAll('#employee-table-body');
        const canManageEmployees = <?php echo (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'administration','accounting'])) ? 'true' : 'false'; ?>;
        const canManagePayrollInfo = <?php echo $canManagePayrollInfo ? 'true' : 'false'; ?>;

        // Set global variable
        window.canManagePayrollInfo = canManagePayrollInfo;

        // --- NEW: Universal QR Code Logic ---
        const cropQrModalEl = document.getElementById('cropQrModal');
        let cropQrModal = null;
        if (cropQrModalEl && typeof bootstrap !== 'undefined') {
            cropQrModal = new bootstrap.Modal(cropQrModalEl);
        }
        const imageToCrop = document.getElementById('imageToCrop');
        const cropAndSaveButton = document.getElementById('cropAndSaveButton');
        let cropper;
        let finalQrBlob = null; // This will hold the final QR blob from any source (upload or generate)
        let activeFormPrefix = ''; // 'add' or 'edit'

        function initCropper(imageElement) {
            if (cropper) cropper.destroy();
            cropper = new Cropper(imageElement, { aspectRatio: 1, viewMode: 1, background: false });
        }
        
        // When user selects a file to upload
        function handleQrFileSelect(event, formPrefix) {
            const file = event.target.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            activeFormPrefix = formPrefix;
            finalQrBlob = null; // Clear previous blob
            const reader = new FileReader();
            reader.onload = (e) => {
                imageToCrop.src = e.target.result;
                cropQrModal.show();
            };
            reader.readAsDataURL(file);
            event.target.value = '';
        }

        // When Cropper modal is shown/hidden
        cropQrModalEl.addEventListener('shown.bs.modal', () => initCropper(imageToCrop));
        cropQrModalEl.addEventListener('hidden.bs.modal', () => {
            if (cropper) cropper.destroy();
            cropper = null;
            imageToCrop.src = '';
        });

        // When user clicks "Crop and Save" in the cropper modal
        cropAndSaveButton.addEventListener('click', () => {
            if (!cropper) return;
            cropper.getCroppedCanvas({ width: 512, height: 512, imageSmoothingQuality: 'high' })
                .toBlob((blob) => {
                    finalQrBlob = blob; // Set the final blob
                    const previewUrl = URL.createObjectURL(blob);
                    const previewImg = document.getElementById(`${activeFormPrefix}BankQrPreview`);
                    if (previewImg) {
                        previewImg.src = previewUrl;
                        previewImg.classList.remove('d-none');
                    }
                    cropQrModal.hide();
                }, 'image/png');
        });

        // When user wants to generate QR from text
        function generateQrInModal(dataInput, previewImg) {
            if (!dataInput || !previewImg) return;
            const textToEncode = dataInput.value.trim();
            if (textToEncode === "") {
                showNotification('សូមបញ្ជូលទិន្នន័យដើម្បីបង្កើត QR Code', 'error');
                return;
            }
            // Create a temporary, invisible div to hold the generated canvas
            const tempDiv = document.createElement('div');
            tempDiv.style.display = 'none';
            document.body.appendChild(tempDiv);

            new QRCode(tempDiv, {
                text: textToEncode, width: 256, height: 256,
                colorDark: "#000000", colorLight: "#ffffff", correctLevel: QRCode.CorrectLevel.H
            });
            
            // Find the canvas, convert to blob, and set as final QR
            setTimeout(() => { // Use setTimeout to ensure canvas is rendered
                const canvas = tempDiv.querySelector('canvas');
                if (canvas) {
                    canvas.toBlob((blob) => {
                        finalQrBlob = blob; // Set the final blob
                        const previewUrl = URL.createObjectURL(blob);
                        previewImg.src = previewUrl;
                        previewImg.classList.remove('d-none');
                    }, 'image/png');
                }
                document.body.removeChild(tempDiv); // Clean up
            }, 100);
        }
        
        // --- FIX: Safely attach event listeners only if elements exist ---
        if (canManagePayrollInfo) {
            const editExistingQrBtn = document.getElementById('editExistingQrButton');
            if (editExistingQrBtn) {
                editExistingQrBtn.addEventListener('click', () => {
                    const previewImage = document.getElementById('editBankQrPreview');
                    const imageUrl = previewImage.src;
                    if (imageUrl && !imageUrl.includes('via.placeholder.com')) {
                        activeFormPrefix = 'edit';
                        finalQrBlob = null;
                        imageToCrop.src = imageUrl;
                        cropQrModal.show();
                    } else {
                        showNotification('មិនមានរូបភាព QR Code ដើម្បីកែសម្រួលទេ។', 'error');
                    }
                });
            }
            
            const addBankQrFile = document.getElementById('add_bank_qr_file');
            if (addBankQrFile) addBankQrFile.addEventListener('change', (e) => handleQrFileSelect(e, 'add'));
            
            const editBankQrFile = document.getElementById('editBankQrFile');
            if (editBankQrFile) editBankQrFile.addEventListener('change', (e) => handleQrFileSelect(e, 'edit'));

            const addGenerateQrBtn = document.getElementById('add_generate_qr_btn');
            if (addGenerateQrBtn) addGenerateQrBtn.addEventListener('click', () => generateQrInModal(document.getElementById('add_qr_data_input'), document.getElementById('addBankQrPreview')));

            const editGenerateQrBtn = document.getElementById('edit_generate_qr_btn');
            if (editGenerateQrBtn) editGenerateQrBtn.addEventListener('click', () => generateQrInModal(document.getElementById('edit_qr_data_input'), document.getElementById('editBankQrPreview')));
        }
        // --- END OF FIX ---


        // Universal Form Submission
        async function handleFormSubmit(form, action) {
            const formData = new FormData(form);
            formData.append('action', action);
            
            if (finalQrBlob) {
                formData.append('bank_qr_code', finalQrBlob, 'qr_code.png');
            }
            
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> កំពុងដំណើរការ...`;
            submitButton.disabled = true;

            try {
                const response = await fetch('api_handler.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    showNotification(result.message, 'success');
                    const modalId = form.closest('.modal').id;
                    bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();
                    form.reset();
                    fetchAndDisplayEmployees();
                } else {
                    throw new Error(result.message || 'An unknown error occurred.');
                }
            } catch (error) {
                showNotification(error.message, 'error');
            } finally {
                submitButton.innerHTML = originalButtonText;
                submitButton.disabled = false;
            }
        }
        
        // Reset state when modals close
        if (addUserModal) {
            addUserModal.addEventListener('hidden.bs.modal', () => {
                finalQrBlob = null;
                const qrDataInput = document.getElementById('add_qr_data_input');
                if (qrDataInput) qrDataInput.value = '';

                const addPreview = document.getElementById('addBankQrPreview');
                if(addPreview) {
                    addPreview.src = 'https://via.placeholder.com/128?text=Preview';
                    addPreview.classList.add('d-none');
                }
            });
            // Reset field requirements to default when modal opens
            addUserModal.addEventListener('show.bs.modal', () => {
                const fields = ['full_name', 'username', 'password', 'email', 'role', 'position'];
                fields.forEach(field => {
                    const input = document.getElementById(`add_${field}`);
                    const label = input ? input.closest('.mb-3').querySelector('.form-label') : null;
                    
                    if (input && label) {
                        // Set default required state
                        const isRequired = ['full_name', 'username', 'password', 'email', 'role', 'position'].includes(field);
                        input.required = isRequired;
                        
                        // Update label
                        if (isRequired && !label.innerHTML.includes('<span class="text-danger">*</span>')) {
                            label.innerHTML += '<span class="text-danger">*</span>';
                        } else if (!isRequired) {
                            label.innerHTML = label.innerHTML.replace('<span class="text-danger">*</span>', '');
                        }
                    }
                });
                // Ensure add modal AL remaining follows the AL input
                const addAlInput = document.getElementById('add_annual_leave_days');
                const addAlRemaining = document.getElementById('addAnnualLeaveRemaining');
                if (addAlInput && addAlRemaining) {
                    // initialize
                    addAlRemaining.value = addAlInput.value || '';
                    addAlInput.addEventListener('input', () => {
                        addAlRemaining.value = addAlInput.value;
                    });
                }
            });
        }
        if (editUserModal) {
            editUserModal.addEventListener('hidden.bs.modal', () => {
                finalQrBlob = null;
                const qrDataInput = document.getElementById('edit_qr_data_input');
                if(qrDataInput) qrDataInput.value = '';
            });
            // Reset field requirements to default when modal opens
            editUserModal.addEventListener('show.bs.modal', () => {
                const fields = ['full_name', 'username', 'email', 'role'];
                const fieldIds = {
                    'full_name': 'editFullName',
                    'username': 'editUsername', 
                    'email': 'editEmail',
                    'role': 'editRole'
                };
                
                fields.forEach(field => {
                    const input = document.getElementById(fieldIds[field]);
                    const label = input ? input.closest('.mb-3').querySelector('.form-label') : null;
                    
                    if (input && label) {
                        // Set default required state (password is not required for edit)
                        const isRequired = ['full_name', 'username', 'email', 'role'].includes(field);
                        input.required = isRequired;
                        
                        // Update label
                        if (isRequired && !label.innerHTML.includes('<span class="text-danger">*</span>')) {
                            label.innerHTML += '<span class="text-danger">*</span>';
                        } else if (!isRequired) {
                            label.innerHTML = label.innerHTML.replace('<span class="text-danger">*</span>', '');
                        }
                    }
                });
            });
        }

        // Field Settings Functionality
        const addUserSettingsBtn = document.getElementById('addUserSettingsBtn');
        const editUserSettingsBtn = document.getElementById('editUserSettingsBtn');
        const addUserFieldSettingsModal = new bootstrap.Modal(document.getElementById('addUserFieldSettingsModal'));
        const editUserFieldSettingsModal = new bootstrap.Modal(document.getElementById('editUserFieldSettingsModal'));

        if (addUserSettingsBtn) {
            addUserSettingsBtn.addEventListener('click', () => {
                // Initialize checkboxes based on current required state
                const fields = ['full_name', 'username', 'password', 'email', 'role', 'position'];
                fields.forEach(field => {
                    const checkbox = document.getElementById(`add_required_${field}`);
                    const input = document.getElementById(`add_${field}`);
                    if (input && checkbox) {
                        checkbox.checked = input.required;
                    }
                });
                addUserFieldSettingsModal.show();
            });
        }

        if (editUserSettingsBtn) {
            editUserSettingsBtn.addEventListener('click', () => {
                // Initialize checkboxes based on current required state
                const fields = ['full_name', 'username', 'email', 'role'];
                const fieldIds = {
                    'full_name': 'editFullName',
                    'username': 'editUsername', 
                    'email': 'editEmail',
                    'role': 'editRole'
                };
                
                fields.forEach(field => {
                    const checkbox = document.getElementById(`edit_required_${field}`);
                    const input = document.getElementById(fieldIds[field]);
                    if (input && checkbox) {
                        checkbox.checked = input.required;
                    }
                });
                editUserFieldSettingsModal.show();
            });
        }

        // Apply settings for Add User
        document.getElementById('applyAddUserSettings').addEventListener('click', function() {
            const fields = ['full_name', 'username', 'password', 'email', 'role', 'position'];
            fields.forEach(field => {
                const checkbox = document.getElementById(`add_required_${field}`);
                const input = document.getElementById(`add_${field}`);
                const label = input.closest('.mb-3').querySelector('.form-label');
                
                if (checkbox.checked) {
                    input.required = true;
                    if (!label.innerHTML.includes('<span class="text-danger">*</span>')) {
                        label.innerHTML += '<span class="text-danger">*</span>';
                    }
                } else {
                    input.required = false;
                    label.innerHTML = label.innerHTML.replace('<span class="text-danger">*</span>', '');
                }
            });
            addUserFieldSettingsModal.hide();
        });

        // Apply settings for Edit User
        document.getElementById('applyEditUserSettings').addEventListener('click', function() {
            const fields = ['full_name', 'username', 'email', 'role'];
            const fieldIds = {
                'full_name': 'editFullName',
                'username': 'editUsername', 
                'email': 'editEmail',
                'role': 'editRole'
            };
            
            fields.forEach(field => {
                const checkbox = document.getElementById(`edit_required_${field}`);
                const input = document.getElementById(fieldIds[field]);
                const label = input.closest('.mb-3').querySelector('.form-label');
                
                if (checkbox.checked) {
                    input.required = true;
                    if (!label.innerHTML.includes('<span class="text-danger">*</span>')) {
                        label.innerHTML += '<span class="text-danger">*</span>';
                    }
                } else {
                    input.required = false;
                    label.innerHTML = label.innerHTML.replace('<span class="text-danger">*</span>', '');
                }
            });
            editUserFieldSettingsModal.hide();
        });

        function renderEmployeeTable(employees, tableBody) {
            if (!tableBody) return;
            tableBody.innerHTML = '';
            if (!employees || employees.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="5" class="text-center py-24"><div class="flex flex-col items-center gap-4 text-slate-300"><i class="fas fa-users-slash text-5xl"></i><p class="font-black uppercase tracking-widest text-xs">មិនមានបុគ្គលិកត្រូវបង្ហាញទេ</p></div></td></tr>';
                return;
            }

            function displayRows(employeeList, level = 0, parentId = null) {
                let html = '';
                const default_avatar = 'https://via.placeholder.com/40';

                employeeList.forEach(employee => {
                    const hasChildren = employee.children && employee.children.length > 0;
                    const indentPx = level * 32;
                    const imageUrl = (employee.image_url && employee.image_url.length > 0) ? `thumb.php?src=${encodeURIComponent(employee.image_url)}&w=120&h=120` : default_avatar;
                    
                    let payrollDataAttributes = '';
                    if (canManagePayrollInfo) {
                        payrollDataAttributes = `
                           data-base-salary="${employee.base_salary || ''}"
                           data-bank-name="${employee.bank_name || ''}"
                           data-bank-account-number="${employee.bank_account_number || ''}"
                           data-bank-qr-code="${employee.bank_qr_code_url || ''}"
                           data-nssf-id="${employee.nssf_id || ''}"`;
                    }

                    const allDataAttributes = `
                       data-id="${employee.id}"
                       data-name="${employee.full_name || employee.username}"
                       data-username="${employee.username}"
                       data-email="${employee.email || ''}"
                       data-role="${employee.role || ''}"
                       data-position="${employee.position || ''}"
                       data-department="${employee.department || ''}"
                       data-gender="${employee.gender || ''}"
                       data-al="${employee.annual_leave_days || ''}"
                       data-image="${employee.image_url || ''}"
                       data-jd="${employee.jd_pdf || ''}"
                       data-workflow="${employee.workflow_pdf || ''}"
                       data-manager-id="${employee.manager_id || ''}"
                       data-employee-id="${employee.employee_id || ''}"
                       data-start-date="${employee.start_date || ''}"
                       data-latin-name="${employee.latin_name || ''}"
                       data-current-address="${employee.current_address || ''}"
                       data-marital-status="${employee.marital_status || ''}"
                       data-number-of-children="${employee.number_of_children || ''}"
                       data-contract-start="${employee.contract_start || ''}"
                       data-contract-end="${employee.contract_end || ''}"
                       data-contract-type="${employee.contract_type || ''}"
                       ${payrollDataAttributes}`;

                    let actionButtons = `
                        <a href="view_employee.php?id=${employee.id}" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-50 text-slate-400 hover:bg-indigo-500 hover:text-white transition-all shadow-sm border border-slate-100" target="_blank" title="មើលព័ត៌មាន">
                            <i class="fas fa-eye text-sm"></i>
                        </a>`;
                    
                    if (canManageEmployees) {
                        actionButtons += `
                            <button type="button" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-50 text-slate-400 hover:bg-amber-500 hover:text-white transition-all shadow-sm border border-slate-100" data-bs-toggle="modal" data-bs-target="#editUserModal" ${allDataAttributes} title="កែប្រែ">
                                <i class="fas fa-edit text-sm"></i>
                            </button>
                            <a href="deactivate_user.php?id=${employee.id}" class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-50 text-rose-400 hover:bg-rose-500 hover:text-white transition-all shadow-sm border border-slate-100" onclick="return confirm('តើអ្នកពិតជាចង់បិទគណនីនេះមែនទេ?')" title="បិទគណនី">
                                <i class="fas fa-user-slash text-sm"></i>
                            </a>`;
                    }

                    html += `
                        <tr class="employee-row hover:bg-indigo-50/30 transition-all group border-b border-slate-50 last:border-0" data-parent-id="${parentId || ''}" data-level="${level}" ${allDataAttributes} onclick="showEmployeeDetails(this)" style="cursor: pointer;">
                            <td class="py-5 px-8">
                                <div class="px-3 py-1 bg-slate-100 text-slate-500 rounded-lg text-[10px] font-black w-fit">#${employee.employee_id || employee.id}</div>
                            </td>
                            <td class="py-5 px-8">
                                <div class="flex items-center gap-5" style="padding-left:${indentPx}px">
                                    <div class="flex items-center gap-4">
                                        ${hasChildren ? `
                                            <button type="button" class="toggle-children w-7 h-7 flex items-center justify-center rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-indigo-600 hover:border-indigo-200 transition-all shadow-sm" title="បិទ/បើកកូន">
                                                <i class="fas fa-plus text-[10px] group-[.expanded]:fa-minus"></i>
                                            </button>` : `<span class="w-7"></span>`}
                                        <div class="relative group/avatar">
                                            <img src="${imageUrl}" class="w-14 h-14 rounded-2xl object-cover shadow-md border-2 border-white ring-1 ring-slate-100 group-hover/avatar:ring-indigo-400 transition-all" alt="User">
                                            <span class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-emerald-500 border-4 border-white"></span>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="font-black text-slate-800 tracking-tight text-base leading-tight">${employee.full_name || employee.username}</span>
                                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mt-1">${employee.username}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-5 px-8 hidden md:table-cell">
                                <div class="flex flex-col gap-1.5">
                                    <div class="flex items-center gap-2 text-slate-600">
                                        <div class="w-5 h-5 rounded-md bg-indigo-50 text-indigo-500 flex items-center justify-center text-[10px]"><i class="fas fa-envelope"></i></div>
                                        <span class="text-sm font-bold truncate max-w-[200px]">${employee.email || '-'}</span>
                                    </div>
                                    <div class="flex items-center gap-2 text-slate-400">
                                        <div class="w-5 h-5 rounded-md bg-slate-50 text-slate-400 flex items-center justify-center text-[10px]"><i class="fas fa-phone"></i></div>
                                        <span class="text-[11px] font-black uppercase">តម្រូវឱ្យបញ្ចូល</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-5 px-8 hidden sm:table-cell">
                                <div class="flex flex-col gap-2">
                                    <span class="text-sm font-black text-slate-800">${employee.position || '-'}</span>
                                    <div class="px-3 py-1 bg-amber-50 text-amber-600 border border-amber-100 rounded-lg text-[10px] font-black uppercase tracking-widest w-fit">${employee.department || 'N/A'}</div>
                                </div>
                            </td>
                            <td class="py-5 px-8" onclick="event.stopPropagation()">
                                <div class="flex items-center justify-center gap-3">
                                    ${actionButtons}
                                </div>
                            </td>
                        </tr>`;

                    if (hasChildren) {
                        html += displayRows(employee.children, level + 1, employee.id);
                    }
                });
                return html;
            }
            tableBody.innerHTML = displayRows(employees);
        }

        async function fetchAndDisplayEmployees(timeoutMs = 8000) {
            // Use AbortController to avoid hanging fetch calls and improve perceived load-time.
            if (tableBodies.length === 0) return;
            const controller = new AbortController();
            const signal = controller.signal;
            const timer = setTimeout(() => controller.abort(), timeoutMs);
            let failed = false;

            try {
                const response = await fetch('api_handler.php?action=get_employees', { signal });
                clearTimeout(timer);
                if (!response.ok) throw new Error('Network response was not ok');

                const data = await response.json();
                if (data.status === 'success') {
                    tableBodies.forEach(body => renderEmployeeTable(data.employees, body));
                } else {
                    failed = true;
                    showNotification(data.message || 'Could not fetch employees.', 'error');
                    tableBodies.forEach(body => {
                        body.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-text-secondary text-lg">មិនអាចទាញទិន្នន័យបុគ្គលិកបាន: ${escapeHtml(data.message || 'Unknown error')}</td></tr>`;
                    });
                }
            } catch (error) {
                clearTimeout(timer);
                console.error('Fetch error:', error);
                failed = true;
                const userMsg = error.name === 'AbortError' ? 'ការទាញទិន្នន័យបានវចនាដោយសារtimeout។' : 'Error fetching employee data.';
                showNotification(userMsg, 'error');
                tableBodies.forEach(body => {
                    body.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-text-secondary text-lg">មិនអាចទាញទិន្នន័យបុគ្គលិកបាន - សូមព្យាយាមម្ដងទៀត។ <button id="retryFetchBtn" class="btn-base btn-primary ml-2" type="button">Retry</button></td></tr>';
                });

                // attach retry handler
                setTimeout(() => {
                    const retry = document.getElementById('retryFetchBtn');
                    if (retry) {
                        retry.addEventListener('click', (e) => {
                            e.preventDefault();
                            showLoading();
                            fetchAndDisplayEmployees(timeoutMs);
                        });
                    }
                }, 50);
            }
            // Helper to escape simple html in messages
            function escapeHtml(str) {
                if (!str) return '';
                return String(str).replace(/[&<>"']/g, function (s) {
                    return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[s];
                });
            }

            // If fetch failed, still ensure overlay is removed soon so user isn't stuck.
            if (failed) {
                // keep the message visible briefly then hide the global loader
                setTimeout(() => { try { hideLoading(); } catch(e){} }, 700);
            }
        }

        // Function to handle row click and open Edit User Modal
        window.showEmployeeDetails = function(element) {
            const modalEl = document.getElementById('editUserModal');
            if (modalEl) {
                 // Manually populate the form since relatedTarget will be null when called via JS
                if (window.populateEditUserForm) {
                    window.populateEditUserForm(element);
                }
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show(element);
            }
        };

        if (addUserForm) {
            addUserForm.addEventListener('submit', function(e) { e.preventDefault(); handleFormSubmit(this, 'add_user'); });
        }
        if (editUserForm) {
            editUserForm.addEventListener('submit', function(e) { e.preventDefault(); handleFormSubmit(this, 'update_user'); });
        }


        try {
            await fetchAndDisplayEmployees();
        } catch (e) {
            console.error('Error fetching employees (awaited):', e);
        } finally {
            hideLoading();
        }
        
        // --- YOUR OTHER EXISTING JAVASCRIPT ---
        window.loadOtEditForm = function(userId, amount, reason, month) { 
            $('#ot_user_id').val(userId).trigger('change'); 
            $('#ot_month').val(month); 
            $('#ot_amount').val(parseFloat(amount).toFixed(2)); 
            var otReasonSelect = $('#ot_reason'); 
            if (otReasonSelect.find("option[value='" + reason + "']").length === 0) { 
                var newOption = new Option(reason, reason, true, true); 
                otReasonSelect.append(newOption); 
            } 
            otReasonSelect.val(reason).trigger('change'); 
            otReasonSelect[0].scrollIntoView({ behavior: 'smooth' }); 
        }
    
        const bell = document.getElementById('notification-bell');
        const dropdown = document.getElementById('notification-dropdown');
        if (bell && dropdown) { 
            bell.addEventListener('click', (e) => { e.stopPropagation(); dropdown.classList.toggle('hidden'); }); 
            document.addEventListener('click', (e) => { 
                if (!dropdown.classList.contains('hidden') && !dropdown.contains(e.target)) { dropdown.classList.add('hidden'); } 
            }); 
        }

        const contentModal = document.getElementById('contentModal');
        if (contentModal) { 
            contentModal.addEventListener('show.bs.modal', (event) => { 
                document.getElementById('modalContent').textContent = event.relatedTarget.getAttribute('data-bs-content'); 
            }); 
        }
        

        if (editUserModal) { 
            // Shared function to populate the Edit User form
            window.populateEditUserForm = function(button) {
                if (!button) return;
                
                // Debug: Check what data is actually coming in
                console.log('Populating Edit Form with data:', {
                    id: button.getAttribute('data-id'),
                    employee_id: button.getAttribute('data-employee-id'),
                    latin_name: button.getAttribute('data-latin-name'),
                    gender: button.getAttribute('data-gender')
                });

                const titleEl = document.getElementById('editUserModalLabel');
                if (titleEl) {
                    titleEl.textContent = 'កែសម្រួល: ' + button.getAttribute('data-name');
                }
                document.getElementById('editUserId').value = button.getAttribute('data-id');
                
                // Populate fields
                if(document.getElementById('edit_employee_id')) document.getElementById('edit_employee_id').value = button.getAttribute('data-employee-id') || '';
                document.getElementById('editFullName').value = button.getAttribute('data-name');
                if(document.getElementById('edit_latin_name')) document.getElementById('edit_latin_name').value = button.getAttribute('data-latin-name') || '';
                document.getElementById('editUsername').value = button.getAttribute('data-username');
                document.getElementById('editEmail').value = button.getAttribute('data-email');
                document.getElementById('editPassword').value = '';
                document.getElementById('editRole').value = button.getAttribute('data-role');
                document.getElementById('editPosition').value = button.getAttribute('data-position');
                document.getElementById('editDepartment').value = button.getAttribute('data-department');
                
                // Gender: Handle potential case differences (Male vs male)
                const genderVal = button.getAttribute('data-gender');
                if(document.getElementById('editGender')) {
                    const normalizedGender = genderVal ? genderVal.toLowerCase() : '';
                    const genderSelect = document.getElementById('editGender');
                    // Try to match value
                    genderSelect.value = normalizedGender;
                    // If not found (maybe mismatch like 'ប្រុស'), try to match text or just set empty
                    if (!genderSelect.value && normalizedGender) {
                         for(let i=0; i<genderSelect.options.length; i++) {
                             if(genderSelect.options[i].text.toLowerCase() === normalizedGender || genderSelect.options[i].value.toLowerCase() === normalizedGender) {
                                 genderSelect.selectedIndex = i;
                                 break;
                             }
                         }
                    }
                }
                if(document.getElementById('edit_current_address')) document.getElementById('edit_current_address').value = button.getAttribute('data-current-address') || '';
                
                // Personnel Tab fields
                if(document.getElementById('edit_start_date')) document.getElementById('edit_start_date').value = button.getAttribute('data-start-date') || '';

                document.getElementById('existingImageUrl').value = button.getAttribute('data-image');
                document.getElementById('editImagePreview').src = button.getAttribute('data-image') || 'https://via.placeholder.com/96';
                document.getElementById('editImagePreview').onerror = function() { this.src = 'https://via.placeholder.com/96'; };
                document.getElementById('existingJdUrl').value = button.getAttribute('data-jd');
                document.getElementById('currentJdLink').innerHTML = button.getAttribute('data-jd') ? `<a href="${button.getAttribute('data-jd')}" target="_blank" class="btn-action-link"><i class="fas fa-file-pdf"></i> មើលឯកសារបច្ចុប្បន្ន</a>` : `<span class="text-text-secondary text-sm">មិនមានឯកសារ</span>`;
                document.getElementById('existingWorkflowUrl').value = button.getAttribute('data-workflow');
                document.getElementById('currentWorkflowLink').innerHTML = button.getAttribute('data-workflow') ? `<a href="${button.getAttribute('data-workflow')}" target="_blank" class="btn-action-link"><i class="fas fa-file-pdf"></i> មើលឯកសារបច្ចុប្បន្ន</a>` : `<span class="text-text-secondary text-sm">មិនមានឯកសារ</span>`;
                
                if (canManagePayrollInfo) {
                    document.getElementById('editBaseSalary').value = button.getAttribute('data-base-salary');
                    document.getElementById('editBankName').value = button.getAttribute('data-bank-name');
                    document.getElementById('editBankAccountNumber').value = button.getAttribute('data-bank-account-number');
                    document.getElementById('editNssfId').value = button.getAttribute('data-nssf-id');
                    document.getElementById('existingBankQrUrl').value = button.getAttribute('data-bank-qr-code');
                    const editPreview = document.getElementById('editBankQrPreview');
                    const qrUrl = button.getAttribute('data-bank-qr-code');
                    if (qrUrl) {
                        editPreview.src = qrUrl;
                        editPreview.classList.remove('d-none');
                    } else {
                        editPreview.src = 'https://via.placeholder.com/128?text=Preview';
                        editPreview.classList.add('d-none');
                    }
                }

                // Annual leave (AL) - populate the personnel field
                const alVal = button.getAttribute('data-al');
                const editAlInput = document.getElementById('editAnnualLeave');
                if (editAlInput) editAlInput.value = alVal !== null ? alVal : '';
                // Set the AL remaining field from data attribute (annual_leave_balance)
                const editAlRemaining = document.getElementById('editAnnualLeaveRemaining');
                if (editAlRemaining) editAlRemaining.value = alVal !== null ? alVal : '';

                // When user changes the edit AL input, reflect the value in the remaining field
                if (editAlInput && editAlRemaining) {
                    editAlInput.addEventListener('input', () => {
                        editAlRemaining.value = editAlInput.value;
                    });
                }
                
                $('#edit_manager_id').val(button.getAttribute('data-manager-id')).trigger('change');

                document.getElementById('edit_marital_status').value = button.getAttribute('data-marital-status') || '';
                document.getElementById('edit_number_of_children').value = button.getAttribute('data-number-of-children') || '';
                document.getElementById('edit_contract_start').value = button.getAttribute('data-contract-start') || '';
                document.getElementById('edit_contract_end').value = button.getAttribute('data-contract-end') || '';
                document.getElementById('edit_contract_type').value = button.getAttribute('data-contract-type') || '';
                
                // Toggle children count based on marital status
                const maritalStatus = button.getAttribute('data-marital-status');
                if (maritalStatus === 'កូន') {
                    document.getElementById('edit_children_count_container').style.display = 'block';
                } else {
                    document.getElementById('edit_children_count_container').style.display = 'none';
                }
            };
            
            // Standard event listener for button clicks
            editUserModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget; 
                // Only populate if triggered by a button (relatedTarget exists)
                // If specificed manually via JS, populate function should be called manually
                if (button) {
                    window.populateEditUserForm(button);
                }
            }); 
        }
        
        const payrollToggle = document.getElementById('payroll-toggle');
        const payrollSubmenu = document.getElementById('payroll-submenu');
        const payrollArrow = document.getElementById('payroll-arrow');

        if (payrollToggle && payrollSubmenu && payrollArrow) {
            // If the submenu is already visible (not hidden), show the arrow rotated
            if (!payrollSubmenu.classList.contains('hidden')) {
                payrollArrow.classList.add('rotate-180');
            }
            payrollToggle.addEventListener('click', (event) => {
                event.preventDefault(); payrollSubmenu.classList.toggle('hidden'); payrollArrow.classList.toggle('rotate-180');
            });
        }

        document.querySelectorAll('.tree .folder-toggle').forEach(toggle => { 
            toggle.addEventListener('click', function() { this.parentElement.classList.toggle('open'); }); 
        }); 

        document.body.addEventListener('click', (e) => {
            if (e.target.closest('.toggle-children')) {
                const toggleBtn = e.target.closest('.toggle-children');
                const row = toggleBtn.closest('tr');
                const isCollapsed = row.dataset.collapsed === '1';
                row.dataset.collapsed = isCollapsed ? '0' : '1';
                toggleBtn.innerHTML = isCollapsed ? '<i class="fas fa-caret-down"></i>' : '<i class="fas fa-caret-right"></i>';
                document.querySelectorAll(`tr[data-parent-id='${row.dataset.id}']`).forEach(child => { child.style.display = isCollapsed ? '' : 'none'; });
            }
        });

        const empSearch = document.getElementById('employee-search'); 
        const deptFilter = document.getElementById('department-filter');
        function applyEmpFilters() { 
            const q = (empSearch ? empSearch.value : '').toLowerCase().trim(); 
            const dep = (deptFilter ? deptFilter.value : ''); 
            document.querySelectorAll('tbody tr.employee-row').forEach(row => { 
                const name = (row.dataset.name || '').toLowerCase(); 
                const email = (row.dataset.email || '').toLowerCase(); 
                const pos = (row.dataset.position || '').toLowerCase(); 
                const dpt = row.dataset.department || ''; 
                row.style.display = (!q || name.includes(q) || email.includes(q) || pos.includes(q) || dpt.toLowerCase().includes(q)) && (!dep || dpt === dep) ? '' : 'none'; 
            }); 
        }
        if (empSearch) empSearch.addEventListener('input', applyEmpFilters);
        if (deptFilter) deptFilter.addEventListener('change', applyEmpFilters);

        const payrollSearch = document.getElementById('payroll-search');
        function applyPayrollFilters() {
            const q = (payrollSearch ? payrollSearch.value : '').toLowerCase().trim();
            document.querySelectorAll('tbody tr.payroll-row').forEach(row => {
                const name = (row.dataset.name || '').toLowerCase();
                row.style.display = (!q || name.includes(q)) ? '' : 'none';
            });
        }
        if (payrollSearch) payrollSearch.addEventListener('input', applyPayrollFilters);

        const payrollDetailsModal = document.getElementById('payrollDetailsModal');
        if (payrollDetailsModal) { 
            payrollDetailsModal.addEventListener('show.bs.modal', (event) => { 
                const button = event.relatedTarget; 
                payrollDetailsModal.querySelector('#detailsEmployeeName').innerText = "បុគ្គលិក: " + button.getAttribute('data-name'); 
                payrollDetailsModal.querySelector('#detailsBonuses').innerHTML = button.getAttribute('data-bonuses') || 'មិនមាន'; 
                payrollDetailsModal.querySelector('#detailsDeductions').innerHTML = button.getAttribute('data-deductions') || 'មិនមាន'; 
            }); 
        }
        
        const finalizeModal = document.getElementById('confirmFinalizeModal');
        if (finalizeModal) { 
            finalizeModal.addEventListener('show.bs.modal', (event) => { 
                finalizeModal.querySelector('#modalPayrollMonth').innerText = event.relatedTarget.getAttribute('data-payroll-month'); 
            }); 
            const finalizeBtn = document.getElementById('confirmFinalizeBtn');
            if (finalizeBtn) {
                finalizeBtn.addEventListener('click', () => { 
                    document.getElementById('finalizePayrollForm').insertAdjacentHTML('beforeend', '<input type="hidden" name="finalize_payroll" value="1">'); 
                    document.getElementById('finalizePayrollForm').submit(); 
                }); 
            }
        }
        
        if (document.getElementById('deductions-tab')) { 
            $('#deduction_user_id').select2({ theme: "bootstrap-5", placeholder: "-- ស្វែងរក ឬជ្រើសរើសបុគ្គលិក --", width: '100%', dropdownParent: $('#deductions-tab') }); 
            $('#deduction_reason').select2({ theme: "bootstrap-5", placeholder: "ស្វែងរក ឬបង្កើតមូលហេតុថ្មី...", tags: true, tokenSeparators: [','], width: '100%', dropdownParent: $('#deductions-tab') }); 
        }
        if (document.getElementById('ot-bonus-tab')) { 
            $('#ot_user_id').select2({ theme: "bootstrap-5", placeholder: "-- ស្វែងរក ឬជ្រើសរើសបុគ្គលិក --", width: '100%', dropdownParent: $('#ot-bonus-tab') }); 
            $('#ot_reason').select2({ theme: "bootstrap-5", placeholder: "ស្វែងរក ឬបង្កើតកំណត់សម្គាល់ថ្មី...", tags: true, tokenSeparators: [','], width: '100%', dropdownParent: $('#ot-bonus-tab') }); 
        }

        if (document.getElementById('loan-tab')) {
            $('#loan_user_id').select2({ theme: "bootstrap-5", placeholder: "-- ស្វែងរក ឬជ្រើសរើសបុគ្គលិក --", width: '100%', dropdownParent: $('#loan-tab') });
        }

        // Loan filters: search by name and filter by status
        const loanSearch = document.getElementById('loan-search');
        const loanStatusFilter = document.getElementById('loan-status-filter');
        function applyLoanFilters() {
            const q = (loanSearch ? loanSearch.value : '').toLowerCase().trim();
            const st = loanStatusFilter ? loanStatusFilter.value : '';
            document.querySelectorAll('#loan-tab tbody tr.loan-row').forEach(row => {
                const name = (row.dataset.loanName || '').toLowerCase();
                const status = row.dataset.loanStatus || '';
                const matchesName = !q || name.includes(q);
                const matchesStatus = !st || status === st;
                row.style.display = (matchesName && matchesStatus) ? '' : 'none';
            });
        }
        if (loanSearch) loanSearch.addEventListener('input', applyLoanFilters);
        if (loanStatusFilter) loanStatusFilter.addEventListener('change', applyLoanFilters);

        const approveModal = document.getElementById('approveModal');
        if (approveModal) { 
            approveModal.addEventListener('show.bs.modal', (event) => { 
                const button = event.relatedTarget; 
                approveModal.querySelector('#approveRunId').value = button.getAttribute('data-run-id'); 
                approveModal.querySelector('#approveRunMonth').textContent = button.getAttribute('data-run-month'); 
            }); 
        }
        const rejectModal = document.getElementById('rejectModal');
        if (rejectModal) { 
            rejectModal.addEventListener('show.bs.modal', (event) => { 
                const button = event.relatedTarget; 
                rejectModal.querySelector('#rejectRunId').value = button.getAttribute('data-run-id'); 
                rejectModal.querySelector('#rejectRunMonth').textContent = button.getAttribute('data-run-month'); 
                rejectModal.querySelector('#rejection_reason').value = ''; 
            }); 
        }
        
        const deleteModal = document.getElementById('deleteModal');
        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                deleteModal.querySelector('#deleteRunId').value = button.getAttribute('data-run-id');
                deleteModal.querySelector('#deleteRunMonth').textContent = button.getAttribute('data-run-month');
            });
        }

        const markPaidModal = document.getElementById('markPaidModal');
        if (markPaidModal) { 
            document.querySelectorAll('.open-modal-btn').forEach(button => { 
                button.addEventListener('click', (event) => { 
                    const btn = event.currentTarget; 
                    markPaidModal.querySelector('#paidRunId').value = btn.dataset.runId; 
                    markPaidModal.querySelector('#paidRunMonth').textContent = btn.dataset.runMonth; 
                    markPaidModal.classList.remove('hidden'); 
                }); 
            }); 
            const closeModalBtn = document.getElementById('closeModalBtn');
            if (closeModalBtn) closeModalBtn.addEventListener('click', () => markPaidModal.classList.add('hidden')); 
        }

        const menuModalEl = document.getElementById('menuModal');
        let menuModal = null;
        if(menuModalEl && typeof bootstrap !== 'undefined') {
            menuModal = new bootstrap.Modal(menuModalEl);
            window.openMenuModal = function(data = null) {
                const form = menuModalEl.querySelector('form');
                form.reset();
                document.getElementById('menu_id').value = '';
                const parentKeySelect = document.getElementById('parent_key');
                if (data) {
                    document.getElementById('menuModalLabel').innerText = 'កែប្រែម៉ឺនុយ';
                    document.getElementById('menu_id').value = data.id;
                    document.getElementById('menu_name').value = data.menu_name;
                    document.getElementById('menu_key').value = data.menu_key;
                    parentKeySelect.value = data.parent_key || '';
                    document.getElementById('menu_order').value = data.menu_order;
                    for (let option of parentKeySelect.options) { option.disabled = (option.value === data.menu_key); }
                } else {
                    document.getElementById('menuModalLabel').innerText = 'បន្ថែមម៉ឺនុយថ្មី';
                    for (let option of parentKeySelect.options) { option.disabled = false; }
                }
                menuModal.show();
            }
        }

        const deleteMenuModalEl = document.getElementById('deleteMenuModal');
        if (deleteMenuModalEl) {
            deleteMenuModalEl.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                const menuId = button.getAttribute('data-menu-id');
                const menuName = button.getAttribute('data-menu-name');
                deleteMenuModalEl.querySelector('#menuIdToDelete').value = menuId;
                deleteMenuModalEl.querySelector('#menuNameToDelete').textContent = menuName;
            });
        }

        // Marital status change handler for children count
        function handleMaritalStatusChange(selectId, containerId) {
            const select = document.getElementById(selectId);
            const container = document.getElementById(containerId);
            if (select && container) {
                select.addEventListener('change', function() {
                    if (this.value === 'កូន') {
                        container.style.display = 'block';
                    } else {
                        container.style.display = 'none';
                        const input = container.querySelector('input');
                        if (input) input.value = '';
                    }
                });
            }
        }

        // Global Meeting Utilities
        window.compressImage = function(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.readAsDataURL(file);
                reader.onload = event => {
                    const img = new Image();
                    img.src = event.target.result;
                    img.onload = () => {
                        const canvas = document.createElement('canvas');
                        const MAX_WIDTH = 1200;
                        const MAX_HEIGHT = 1200;
                        let width = img.width;
                        let height = img.height;
                        if (width > height) {
                            if (width > MAX_WIDTH) {
                                height *= MAX_WIDTH / width;
                                width = MAX_WIDTH;
                            }
                        } else {
                            if (height > MAX_HEIGHT) {
                                width *= MAX_HEIGHT / height;
                                height = MAX_HEIGHT;
                            }
                        }
                        canvas.width = width;
                        canvas.height = height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, width, height);
                        canvas.toBlob(blob => {
                            resolve(new File([blob], file.name, {
                                type: 'image/jpeg',
                                lastModified: Date.now()
                            }));
                        }, 'image/jpeg', 0.7);
                    };
                    img.onerror = reject;
                };
                reader.onerror = reject;
            });
        };

        window.bufferToWav = function(abuffer, offset = 0) {
            let numOfChan = abuffer.numberOfChannels,
                length = abuffer.length * numOfChan * 2 + 44,
                buffer = new ArrayBuffer(length),
                view = new DataView(buffer),
                channels = [], i, sample, pos = 0;

            const setUint16 = (data) => { view.setUint16(pos, data, true); pos += 2; };
            const setUint32 = (data) => { view.setUint32(pos, data, true); pos += 4; };

            setUint32(0x46464952); setUint32(length - 8); setUint32(0x45564157);
            setUint32(0x20746d66); setUint32(16); setUint16(1); setUint16(numOfChan);
            setUint32(abuffer.sampleRate); setUint32(abuffer.sampleRate * 2 * numOfChan);
            setUint16(numOfChan * 2); setUint16(16);
            setUint32(0x61746164); setUint32(length - pos - 4);

            for(i = 0; i < abuffer.numberOfChannels; i++) channels.push(abuffer.getChannelData(i));
            while(pos < length) {
                for(i = 0; i < numOfChan; i++) {
                    sample = Math.max(-1, Math.min(1, channels[i][offset]));
                    sample = (0.5 + sample < 0 ? sample * 32768 : sample * 32767)|0;
                    view.setInt16(pos, sample, true); pos += 2;
                }
                offset++;
            }
            return new Blob([buffer], {type: "audio/wav"});
        };

        handleMaritalStatusChange('add_marital_status', 'add_children_count_container');
        handleMaritalStatusChange('edit_marital_status', 'edit_children_count_container');

        // Also trigger on modal show for edit
        if (editUserModal) {
            editUserModal.addEventListener('shown.bs.modal', function() {
                const maritalStatus = document.getElementById('edit_marital_status').value;
                const container = document.getElementById('edit_children_count_container');
                if (maritalStatus === 'កូន') {
                    container.style.display = 'block';
                } else {
                    container.style.display = 'none';
                }
            });
        }

        window.showEditLoanModal = function(loanId, monthlyDeduction, status, durationMonths, note, fundRequestNote) {
            document.getElementById('edit_loan_id').value = loanId;
            document.getElementById('edit_monthly_deduction').value = monthlyDeduction;
            document.getElementById('edit_status').value = status;
            document.getElementById('edit_duration_months').value = durationMonths;
            document.getElementById('edit_note').value = note;
            document.getElementById('edit_fund_request_note').value = fundRequestNote || '';
            const modal = new bootstrap.Modal(document.getElementById('editLoanModal'));
            modal.show();
        };


        // FIX: Move modals to body to prevent z-index/backdrop issues
        const modalsToMove = ['addUserModal', 'editUserModal', 'contentModal', 'taskModal', 'categoryModal'];
        modalsToMove.forEach(id => {
            const el = document.getElementById(id);
            if (el) document.body.appendChild(el);
        });

    });

    </script>
</body>
</html>
