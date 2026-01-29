<?php
// ====================================================================
// NEW: ส่วนពិនិត្យការ Login (Security Check)
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
// NEW: Handle AJAX request for updating a note
// This block runs ONLY when an AJAX POST request with 'action' is sent
// ====================================================================
if (isset($_POST['action']) && $_POST['action'] === 'update_note') {
    header('Content-Type: application/json');
    
    if (!isset($_POST['id']) || !isset($_POST['noted'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid data.']);
        exit;
    }

    $log_id = $_POST['id'];
    $noted_value = $_POST['noted'];

    try {
        $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("UPDATE scan_logs SET noted = ? WHERE id = ?");
        $stmt->execute([$noted_value, $log_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Note updated successfully.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No record found or no changes made.']);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        error_log("Database error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit; // Stop script execution after handling the AJAX request
}

// ====================================================================
// Main Page Logic (runs on initial page load or form submission)
// ====================================================================
$logs = [];
$available_users = [];
$available_dates = [];
$error_message = '';
$selected_users = [];
$selected_date = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $user_stmt = $pdo->query("SELECT DISTINCT username FROM scan_logs WHERE username IS NOT NULL AND username != '' ORDER BY username ASC");
    $available_users = $user_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $date_stmt = $pdo->query("SELECT DISTINCT DATE(timestamp) as date_only FROM scan_logs ORDER BY date_only DESC");
    $available_dates = $date_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $selected_users = $_GET['selected_users'] ?? [];
    $selected_date = $_GET['selected_date'] ?? '';

    $displayColumns = ['id', 'user_id', 'username', 'branch', 'action', 'timestamp', 'status', 'early_reason', 'noted'];
    $columnsString = implode(', ', $displayColumns);

    $whereClauses = [];
    $params = [];

    if (!empty($selected_users) && is_array($selected_users)) {
        $placeholders = implode(',', array_fill(0, count($selected_users), '?'));
        $whereClauses[] = "username IN ($placeholders)";
        $params = array_merge($params, $selected_users);
    }
    
    if (!empty($selected_date)) {
        $whereClauses[] = "DATE(timestamp) = ?";
        $params[] = $selected_date;
    }

    $whereClause = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM scan_logs $whereClause");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();

    $records_per_page = 25;
    $total_pages = ceil($total_records / $records_per_page);
    $current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

    if ($current_page < 1) {
        $current_page = 1;
    } elseif ($current_page > $total_pages && $total_pages > 0) {
        $current_page = $total_pages;
    }

    $offset = ($current_page - 1) * $records_per_page;

    $orderBy = "ORDER BY username ASC, timestamp ASC";
    $query = "SELECT $columnsString FROM scan_logs $whereClause $orderBy LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($query);

    $param_index = 1;
    foreach ($params as $param) {
        $stmt->bindValue($param_index++, $param);
    }

    $stmt->bindValue($param_index++, $records_per_page, PDO::PARAM_INT);
    $stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $logs = $stmt->fetchAll();

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
    <title>ផ្ទាំងគ្រប់គ្រងកំណត់ត្រា</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Battambang:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Battambang', sans-serif;
        }
        .container {
            max-width: 1200px;
        }
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            border-bottom: 1px solid #dee2e6;
        }
        .table-container {
            overflow-x: auto;
        }
        .table thead th {
            white-space: nowrap;
        }
        .status-icon {
            font-size: 1.2em;
            vertical-align: middle;
            margin-right: 5px;
        }
        .status-success {
            color: #198754;
        }
        .status-failed {
            color: #dc3545;
        }
        .status-good {
            color: #0d6efd;
        }
        .status-unknown {
            color: #6c757d;
        }
        .table-hover tbody tr:hover {
            background-color: #f5f5f5;
        }
        h1, h5 {
            font-weight: 700;
        }
        /* Styles for inline editing */
        .editable-note {
            cursor: pointer;
            word-wrap: break-word;
            min-width: 150px;
        }
        .editable-note:hover {
            background-color: #e9ecef;
        }
        .editable-note textarea {
            border: 1px solid #ced4da;
            width: 100%;
            height: auto;
            min-height: 50px;
            resize: vertical;
        }
        .editable-note a {
            word-break: break-all;
        }
    </style>
</head>
<body>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-primary fw-bold"><i class="fas fa-shield-alt me-2"></i> ផ្ទាំងគ្រប់គ្រងកំណត់ត្រា</h1>
        <div>
            <span class="badge bg-secondary">អ្នកប្រើប្រាស់: <?= htmlspecialchars($_SESSION['admin_username']) ?></span>
            <a href="logout.php" class="btn btn-danger btn-sm ms-2"><i class="fas fa-sign-out-alt"></i> ចាកចេញ</a>
        </div>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger" role="alert"><?= displayValue($error_message) ?></div>
    <?php else: ?>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0 fw-bold"><i class="fas fa-filter me-2"></i> តម្រងទិន្នន័យ</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" id="main-filter-form">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label for="user-select" class="form-label">ជ្រើសរើសអ្នកប្រើប្រាស់ (ចុច Ctrl ឬ Shift ឱ្យជាប់ ដើម្បីជ្រើសរើសច្រើន)</label>
                            <select multiple class="form-select" name="selected_users[]" id="user-select" onchange="this.form.submit()">
                                <?php foreach ($available_users as $user): ?>
                                    <?php $selected = in_array($user, $selected_users) ? 'selected' : ''; ?>
                                    <option value="<?= displayValue($user) ?>" <?= $selected ?>>
                                        <?= displayValue($user) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-2"></i> ស្វែងរក</button>
                                <a href="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>" class="btn btn-outline-secondary"><i class="fas fa-eraser me-2"></i> សម្អាតតម្រង</a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
               <h5 class="mb-0 fw-bold"><i class="fas fa-list-ul me-2"></i> លទ្ធផលកំណត់ត្រា</h5>
               <div>
                   <span class="badge bg-info rounded-pill">ទំព័រ <?= $current_page ?> / <?= $total_pages > 0 ? $total_pages : 1 ?></span>
                   <span class="badge bg-success rounded-pill">សរុប <?= $total_records ?> កំណត់ត្រា</span>
               </div>
            </div>
            <div class="card-body p-0">
                <div class="table-container">
                    <table class="table table-striped table-bordered table-hover mb-0">
                        <thead class="table-dark">
                             <tr>
                                 <th>ID</th>
                                 <th>ID អ្នកប្រើប្រាស់</th>
                                 <th>ឈ្មោះអ្នកប្រើប្រាស់</th>
                                 <th>សាខា</th>
                                 <th>សកម្មភាព</th>
                                 <th>
                                     <form method="GET" action="" class="d-inline" id="date-filter-form">
                                         <?php if (!empty($selected_users)): ?>
                                             <?php foreach ($selected_users as $user): ?>
                                                 <input type="hidden" name="selected_users[]" value="<?= htmlspecialchars($user) ?>">
                                             <?php endforeach; ?>
                                         <?php endif; ?>
                                         កាលបរិច្ឆេទ
                                         <select onchange="this.form.submit()" class="form-select form-select-sm" name="selected_date">
                                             <option value="">ទាំងអស់</option>
                                             <?php foreach ($available_dates as $date): ?>
                                                 <?php $selected = ($date === $selected_date) ? 'selected' : ''; ?>
                                                 <option value="<?= htmlspecialchars($date) ?>" <?= $selected ?>>
                                                     <?= date('d-m-Y', strtotime($date)) ?>
                                                 </option>
                                             <?php endforeach; ?>
                                         </select>
                                     </form>
                                 </th>
                                 <th>ពេលវេលា</th>
                                 <th>ស្ថានភាព</th>
                                 <th>មូលហេតុ</th>
                                 <th>ចំណាំ</th>
                             </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                        <h5 class="mb-0">មិនមានទិន្នន័យត្រូវបង្ហាញទេ!</h5>
                                        <p class="text-muted">សូមសាកល្បងត្រងទិន្នន័យម្តងទៀត</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr data-id="<?= displayValue($log['id']) ?>">
                                        <td><?= displayValue($log['id']) ?></td>
                                        <td><?= displayValue($log['user_id']) ?></td>
                                        <td><?= displayValue($log['username']) ?></td>
                                        <td><?= displayValue($log['branch']) ?></td>
                                        <td><?= displayValue($log['action']) ?></td>
                                        <td><?= date('d/m/Y', strtotime($log['timestamp'])) ?></td>
                                        <td><?= date('h:i:s A', strtotime($log['timestamp'])) ?></td>
                                        <td>
                                            <?php 
                                                $status = displayValue($log['status']);
                                                if (strtolower($status) === 'success') {
                                                    $icon_class = 'fa-check-circle status-success';
                                                    $text_class = 'status-success';
                                                } elseif (strtolower($status) === 'failed') {
                                                    $icon_class = 'fa-times-circle status-failed';
                                                    $text_class = 'status-failed';
                                                } elseif (strtolower($status) === 'good') {
                                                    $icon_class = 'fa-circle status-good'; 
                                                    $text_class = 'status-good';
                                                } else {
                                                    $icon_class = 'fa-circle-question status-unknown';
                                                    $text_class = 'status-unknown';
                                                }
                                                echo "<span class='$text_class'><i class='fas $icon_class status-icon'></i> $status</span>";
                                            ?>
                                        </td>
                                        <td><?= displayValue($log['early_reason']) ?></td>
                                        <td class="editable-note" data-column="noted">
                                            <?= displayValue($log['noted']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($total_pages > 1): ?>
                <div class="card-footer d-flex justify-content-center">
                    <nav aria-label="Page navigation">
                        <ul class="pagination mb-0">
                            <?php
                                $query_params = $_GET;
                                if ($current_page > 1) {
                                    $query_params['page'] = $current_page - 1;
                                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">Previous</a></li>';
                                } else {
                                    echo '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
                                }
                                $num_links = 2;
                                if ($current_page > ($num_links + 1)) {
                                    $query_params['page'] = 1;
                                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">1</a></li>';
                                    if ($current_page > ($num_links + 2)) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                for ($i = max(1, $current_page - $num_links); $i <= min($total_pages, $current_page + $num_links); $i++) {
                                    $query_params['page'] = $i;
                                    if ($i == $current_page) {
                                        echo '<li class="page-item active" aria-current="page"><span class="page-link">' . $i . '</span></li>';
                                    } else {
                                        echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">' . $i . '</a></li>';
                                    }
                                }
                                if ($current_page < ($total_pages - $num_links)) {
                                    if ($current_page < ($total_pages - $num_links - 1)) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    $query_params['page'] = $total_pages;
                                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">' . $total_pages . '</a></li>';
                                }
                                if ($current_page < $total_pages) {
                                    $query_params['page'] = $current_page + 1;
                                    echo '<li class="page-item"><a class="page-link" href="?' . http_build_query($query_params) . '">Next</a></li>';
                                } else {
                                    echo '<li class="page-item disabled"><span class="page-link">Next</span></li>';
                                }
                            ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // Function to convert URLs in a string into clickable links
    function linkify(text) {
        // Regular expression to find URLs starting with http://, https://, or www.
        const urlRegex = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])|(\bwww\.[\-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
        return text.replace(urlRegex, function(url) {
            let href = url;
            if (!href.startsWith('http')) {
                href = 'https://' + href;
            }
            return '<a href="' + href + '" target="_blank">' + url + '</a>';
        });
    }

    // Apply linkify function to all noted cells on page load
    $('.editable-note').each(function() {
        const originalText = $(this).text().trim();
        $(this).html(linkify(originalText));
    });

    // Variable to hold the original value during editing
    let originalText;

    // Handle click on editable cell
    $(document).on('click', '.editable-note', function() {
        // Prevent editing if already in editing mode
        if ($(this).find('textarea').length) {
            return;
        }

        const cell = $(this);
        // Get the plain text value from the cell
        originalText = cell.text().trim();
        const input = $('<textarea>').val(originalText);

        // Replace the cell content with the textarea
        cell.html(input);
        input.focus();
        
        // Handle saving on blur (when user clicks out)
        input.on('blur', function() {
            const newValue = $(this).val().trim();
            const logId = cell.closest('tr').data('id');

            // If value is the same, no need to update
            if (newValue === originalText) {
                cell.html(linkify(originalText));
                return;
            }

            // Perform AJAX call to update the database
            $.ajax({
                url: '<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>', // AJAX sends to the same file
                type: 'POST',
                data: {
                    action: 'update_note', // Tell the PHP script this is an update action
                    id: logId,
                    noted: newValue
                },
                success: function(response) {
                    if (response.status === 'success') {
                        // Update the cell text with new value and apply linkify
                        cell.html(linkify(newValue));
                        console.log(response.message);
                    } else {
                        // Revert to original value and show error
                        cell.html(linkify(originalText));
                        alert('Error: ' + response.message);
                        console.error('Update failed: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    // Revert to original value and show a general error
                    cell.html(linkify(originalText));
                    alert('An error occurred. Please try again.');
                    console.error('AJAX error: ' + status, error);
                }
            });
        });

        // Handle saving on Enter key press
        input.on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                e.preventDefault();
                $(this).blur(); // Trigger the blur event to save
            }
        });
    });
});
</script>

</body>
</html>