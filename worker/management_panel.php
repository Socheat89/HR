<?php
session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_scan_logs_worker_db", 
                  'samann1_scan_logs_worker_db', 
                  'scan_logs_worker_db@2025');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    // Get table schema (all columns)
    $schemaStmt = $pdo->query("SHOW COLUMNS FROM scan_logs");
    $columns = $schemaStmt->fetchAll(PDO::FETCH_ASSOC);
    $editableColumns = array_filter($columns, function($col) {
        return !in_array($col['Field'], ['id', 'timestamp']); // Exclude non-editable fields
    });
    $columnNames = array_column($editableColumns, 'Field');

    // Get distinct branches for filtering
    $branchStmt = $pdo->query("SELECT DISTINCT branch FROM scan_logs 
                              WHERE branch IS NOT NULL AND branch != '' 
                              ORDER BY branch");
    $branches = $branchStmt->fetchAll(PDO::FETCH_COLUMN);

    // Get usernames by branch
    $usernamesByBranch = [];
    foreach ($branches as $branch) {
        $usernameStmt = $pdo->prepare("SELECT DISTINCT username FROM scan_logs 
                                      WHERE branch = :branch 
                                      AND username IS NOT NULL AND username != '' 
                                      ORDER BY username");
        $usernameStmt->execute([':branch' => $branch]);
        $usernamesByBranch[$branch] = $usernameStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // CRUD Operations
    $message = '';
    
    // Create
    if (isset($_POST['create'])) {
        $values = [];
        $placeholders = [];
        $params = [];
        $hasData = false; // Track if any field has data

        foreach ($columnNames as $col) {
            $value = trim($_POST["new_$col"] ?? '');
            if ($value !== '') { // Only include non-empty fields
                $placeholders[] = ":$col";
                $params[":$col"] = $value;
                $values[] = $col;
                $hasData = true;
            }
        }

        // Optionally, enforce specific required fields (e.g., username)
        if (!isset($params[':username']) || empty($params[':username'])) {
            $message = "សូមបំពេញឈ្មោះអ្នកប្រើប្រាស់!";
        } elseif (!$hasData) {
            $message = "សូមបំពេញយ៉ាងហោចណាស់មួយទិន្នន័យ!";
        } else {
            $columnsStr = implode(', ', $values);
            $placeholdersStr = implode(', ', $placeholders);
            $query = "INSERT INTO scan_logs ($columnsStr, timestamp) VALUES ($placeholdersStr, NOW())";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $message = "បានបន្ថែមទិន្នន័យថ្មីដោយជោគជ័យ!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }
    }

    // Read (with filtering and searching)
    $selected_users = $_POST['selected_users'] ?? [];
    $search_term = trim($_POST['search_username'] ?? '');
    $whereClause = '';
    $conditions = [];
    $params = [];

    if (!empty($selected_users)) {
        $placeholders = implode(',', array_fill(0, count($selected_users), '?'));
        $conditions[] = "username IN ($placeholders)";
        $params = array_merge($params, $selected_users);
    }

    if (!empty($search_term)) {
        $conditions[] = "username LIKE ?";
        $params[] = "%$search_term%";
    }

    if (!empty($conditions)) {
        $whereClause = "WHERE " . implode(" AND ", $conditions);
    }

    $stmt = $pdo->prepare("SELECT * FROM scan_logs $whereClause ORDER BY timestamp DESC");
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Update
    if (isset($_POST['update'])) {
        $id = $_POST['edit_id'] ?? 0;
        $updates = [];
        $params = [':id' => $id];
        $hasData = false; // Track if any field has data

        foreach ($columnNames as $col) {
            $value = trim($_POST["edit_$col"] ?? '');
            if ($value !== '') { // Only include non-empty fields
                $updates[] = "$col = :$col";
                $params[":$col"] = $value;
                $hasData = true;
            }
        }

        // Optionally, enforce specific required fields (e.g., username)
        if (!isset($params[':username']) || empty($params[':username'])) {
            $message = "សូមបំពេញឈ្មោះអ្នកប្រើប្រាស់!";
        } elseif (!$hasData) {
            $message = "សូមបំពេញយ៉ាងហោចណាស់មួយទិន្នន័យ!";
        } elseif ($id) {
            $updatesStr = implode(', ', $updates);
            $query = "UPDATE scan_logs SET $updatesStr WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $message = "បានកែប្រែទិន្នន័យដោយជោគជ័យ!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $message = "សូមជ្រើសរើសទិន្នន័យដើម្បីកែប្រែ!";
        }
    }

    // Delete (Single)
    if (isset($_GET['delete'])) {
        $id = $_GET['delete'];
        $stmt = $pdo->prepare("DELETE FROM scan_logs WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $message = "បានលុបទិន្នន័យដោយជោគជ័យ!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Bulk Delete
    if (isset($_POST['bulk_delete']) && !empty($_POST['delete_ids'])) {
        $ids = $_POST['delete_ids'];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM scan_logs WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $message = "បានលុបទិន្នន័យច្រើនដោយជោគជ័យ!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

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
    <title>ផ្ទាំងគ្រប់គ្រង - CRUD ទិន្នន័យទាំងអស់</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Battambang&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Battambang', sans-serif;
            background-color: #f8f9fa;
            margin: 0; /* Remove default margin for full-screen */
            padding: 0; /* Remove default padding */
            min-height: 100vh; /* Full viewport height */
            display: flex;
            flex-direction: column;
        }

        .container {
            flex: 1; /* Expand to fill available space */
            width: 100%; /* Full width */
            max-width: 100%; /* Remove max-width constraint */
            margin: 0; /* No margins */
            padding: 1rem; /* Reduced padding for more content space */
            background: white;
            border-radius: 0; /* Remove border radius for edge-to-edge */
            box-shadow: none; /* Remove shadow for cleaner full-screen look */
            overflow-x: auto; /* Allow horizontal scroll for wide content */
        }

        .header {
            background-color: #007bff;
            color: white;
            padding: 1rem;
            border-radius: 0; /* Align with full-screen container */
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .accordion-button {
            font-family: 'Battambang', sans-serif;
            background-color: #f8f9fa;
        }

        .username-checkboxes {
            max-height: 60vh; /* Increased height to show more usernames */
            overflow-y: auto;
            padding: 0.5rem;
            background-color: #fff;
        }

        .select-all {
            font-weight: bold;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 0.5rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .username-checkboxes label {
            display: block;
            margin-bottom: 0.3rem;
            cursor: pointer;
        }

        .btn-submit {
            margin-top: 1rem;
        }

        .selected-count {
            margin-top: 0.75rem;
            font-style: italic;
            color: #666;
        }

        .table-responsive {
            margin-top: 1rem;
            max-height: 70vh; /* Limit table height to fit screen */
            overflow-y: auto; /* Vertical scroll for large datasets */
            width: 100%; /* Full width */
        }

        .table {
            width: 100%; /* Ensure table uses full container width */
            min-width: 1000px; /* Prevent column squeezing on smaller screens */
        }

        .message {
            margin-bottom: 1rem;
            padding: 0.75rem;
            border-radius: 0.25rem;
            transition: opacity 0.3s ease; /* Smooth fade-in/out for toast */
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
        }

        .message.success, .message.error {
            min-width: 200px; /* Ensure toast is readable */
        }

        .bulk-actions {
            margin-top: 0.75rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.5rem;
        }

        .copy-btn {
            margin-left: 0.25rem; /* Space between buttons */
            position: relative; /* For click feedback */
        }

        /* NEW: Prevent multiple toasts */
        .message.toast {
            opacity: 0.9;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 0.9; }
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 0.5rem;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
                padding: 0.75rem;
            }

            .form-grid {
                grid-template-columns: 1fr; /* Stack inputs on smaller screens */
            }

            .username-checkboxes {
                max-height: 50vh; /* Slightly smaller for tablets */
            }

            .table-responsive {
                max-height: 60vh; /* Adjust table height for tablets */
            }
        }

        @media (max-width: 576px) {
            .container {
                padding: 0.25rem;
            }

            .header {
                padding: 0.5rem;
            }

            .accordion-button {
                padding: 0.5rem;
            }

            .username-checkboxes {
                max-height: 40vh; /* Smaller for mobile */
            }

            .table-responsive {
                max-height: 50vh; /* Smaller table height for mobile */
            }

            .table {
                min-width: 800px; /* Slightly smaller min-width for mobile */
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2 class="mb-0">ផ្ទាំងគ្រប់គ្រង - CRUD ទិន្នន័យទាំងអស់</h2>
            <a href="admin_logout.php" class="btn btn-light btn-sm">
                <i class="fa-solid fa-right-from-bracket"></i> ចាកចេញ
            </a>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php else: ?>
            <?php if ($message): ?>
                <div class="message <?php echo strpos($message, 'ជោគជ័យ') !== false ? 'success' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Create Form -->
            <h4>បន្ថែមទិន្នន័យថ្មី</h4>
            <form method="POST" class="mb-4">
                <div class="form-grid">
                    <?php foreach ($columnNames as $col): ?>
                        <div>
                            <input type="text" name="new_<?php echo $col; ?>" class="form-control" 
                                   placeholder="<?php echo htmlspecialchars(ucfirst($col)); ?>" 
                                   <?php echo $col === 'username' ? 'required' : ''; ?>>
                        </div>
                    <?php endforeach; ?>
                    <div>
                        <button type="submit" name="create" class="btn btn-success btn-submit w-100">
                            <i class="fa-solid fa-plus"></i> បន្ថែម
                        </button>
                    </div>
                </div>
            </form>

            <!-- Filter Form -->
            <h4>ជ្រើសរើសដើម្បីមើល</h4>
            <form id="selectionForm" method="POST">
                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <input type="text" name="search_username" class="form-control" 
                               placeholder="ស្វែករកឈ្មោះអ្នកប្រើប្រាស់" 
                               value="<?php echo htmlspecialchars($search_term ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" name="search" class="btn btn-info w-100">
                            <i class="fa-solid fa-magnifying-glass"></i> ស្វែករកឈ្មោះអ្នកប្រើប្រាស់
                        </button>
                    </div>
                </div>

                <div class="accordion" id="branchAccordion">
                    <?php foreach ($branches as $branchIndex => $branch): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingBranch<?php echo $branchIndex; ?>">
                                <button class="accordion-button <?php echo $branchIndex === 0 ? '' : 'collapsed'; ?>" 
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#collapseBranch<?php echo $branchIndex; ?>" 
                                        aria-expanded="<?php echo $branchIndex === 0 ? 'true' : 'false'; ?>" 
                                        aria-controls="collapseBranch<?php echo $branchIndex; ?>">
                                    <?php echo htmlspecialchars($branch); ?>
                                </button>
                            </h2>
                            <div id="collapseBranch<?php echo $branchIndex; ?>" 
                                 class="accordion-collapse collapse <?php echo $branchIndex === 0 ? 'show' : ''; ?>" 
                                 aria-labelledby="headingBranch<?php echo $branchIndex; ?>" 
                                 data-bs-parent="#branchAccordion">
                                <div class="accordion-body username-checkboxes">
                                    <label class="select-all">
                                        <input type="checkbox" class="selectBranchAll" 
                                               data-branch="<?php echo htmlspecialchars($branch); ?>" checked> 
                                        -- ជ្រើសរើសទាំងអស់នៅ <?php echo htmlspecialchars($branch); ?> --
                                    </label>
                                    <?php foreach ($usernamesByBranch[$branch] as $username): ?>
                                        <label>
                                            <input type="checkbox" name="selected_users[]" 
                                                   value="<?php echo htmlspecialchars($username); ?>" 
                                                   data-branch="<?php echo htmlspecialchars($branch); ?>" 
                                                   <?php echo in_array($username, $selected_users) ? 'checked' : ''; ?>>
                                            <?php echo htmlspecialchars($username); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="selected-count" id="selectedCount">
                    បានជ្រើសរើស: <span id="count"><?php echo count($selected_users); ?></span> នាក់
                </div>

                <button type="submit" name="filter" class="btn btn-primary btn-submit">
                    <i class="fa-solid fa-filter"></i> តម្រង
                </button>
            </form>

            <!-- Read/Update/Delete Table with Bulk Delete -->
            <form method="POST" id="bulkDeleteForm">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead class="table-primary">
                            <tr>
                                <th><input type="checkbox" id="selectAllDelete"></th>
                                <?php foreach ($columns as $col): ?>
                                    <th><?php echo htmlspecialchars(ucfirst($col['Field'])); ?></th>
                                <?php endforeach; ?>
                                <th>សកម្មភាព</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="<?php echo count($columns) + 2; ?>" class="text-center">មិនមានទិន្នន័យ!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="delete_ids[]" 
                                                   value="<?php echo $log['id']; ?>" 
                                                   class="deleteCheckbox">
                                        </td>
                                        <?php if (isset($_GET['edit']) && $_GET['edit'] == $log['id']): ?>
                                            <form method="POST">
                                                <?php foreach ($columns as $col): ?>
                                                    <?php $field = $col['Field']; ?>
                                                    <td>
                                                        <?php if ($field === 'id'): ?>
                                                            <?php echo $log['id']; ?>
                                                            <input type="hidden" name="edit_id" value="<?php echo $log['id']; ?>">
                                                        <?php elseif ($field === 'timestamp'): ?>
                                                            <?php echo date('d/m/Y H:i', strtotime($log['timestamp'])); ?>
                                                        <?php else: ?>
                                                            <input type="text" name="edit_<?php echo $field; ?>" 
                                                                   value="<?php echo htmlspecialchars($log[$field]); ?>" 
                                                                   class="form-control" 
                                                                   <?php echo $field === 'username' ? 'required' : ''; ?>>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                                <td>
                                                    <button type="submit" name="update" class="btn btn-success btn-sm">
                                                        <i class="fa-solid fa-save"></i> រក្សាទុក
                                                    </button>
                                                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary btn-sm">
                                                        <i class="fa-solid fa-times"></i> បោះបង់
                                                    </a>
                                                </td>
                                            </form>
                                        <?php else: ?>
                                            <?php foreach ($columns as $col): ?>
                                                <?php $field = $col['Field']; ?>
                                                <td>
                                                    <?php 
                                                    if ($field === 'timestamp') {
                                                        echo date('d/m/Y H:i', strtotime($log['timestamp']));
                                                    } else {
                                                        echo htmlspecialchars($log[$field]);
                                                    }
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                            <td>
                                                <a href="?edit=<?php echo $log['id']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fa-solid fa-edit"></i> កែ
                                                </a>
                                                <a href="?delete=<?php echo $log['id']; ?>" class="btn btn-danger btn-sm" 
                                                   onclick="return confirm('តើអ្នកប្រាកដជាចង់លុបមែនទេ?')">
                                                    <i class="fa-solid fa-trash"></i> លុប
                                                </a>
                                                <!-- UPDATED: Copy button with explicit fields -->
                                                <button class="btn btn-info btn-sm copy-btn" 
                                                        data-row='<?php 
                                                            $copyData = array_intersect_key($log, array_flip($columnNames));
                                                            echo htmlspecialchars(json_encode($copyData, JSON_UNESCAPED_UNICODE));
                                                        ?>' 
                                                        onclick="copyRowData(this)" 
                                                        aria-label="ចម្លងទិន្នន័យ">
                                                    <i class="fa-solid fa-copy"></i> ចម្លង
                                                </button>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="bulk-actions">
                    <button type="submit" name="bulk_delete" class="btn btn-danger" 
                            onclick="return confirm('តើអ្នកប្រាកដជាចង់លុបទិន្នន័យដែលបានជ្រើសរើសទាំងអស់មែនទេ?')">
                        <i class="fa-solid fa-trash"></i> លុបទាំងអស់ដែលជ្រើសរើស
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter form checkboxes
            const branchAllCheckboxes = document.querySelectorAll('.selectBranchAll');
            const userCheckboxes = document.querySelectorAll('input[name="selected_users[]"]');
            const countElement = document.getElementById('count');

            // Bulk delete checkboxes
            const selectAllDelete = document.getElementById('selectAllDelete');
            const deleteCheckboxes = document.querySelectorAll('.deleteCheckbox');

            // Update selected filter count
            function updateFilterCount() {
                const checkedCount = document.querySelectorAll('input[name="selected_users[]"]:checked').length;
                countElement.textContent = checkedCount;
            }

            // Filter form: Select/deselect all in branch
            branchAllCheckboxes.forEach(branchCheckbox => {
                branchCheckbox.addEventListener('change', function() {
                    const branch = this.dataset.branch;
                    document.querySelectorAll(`input[name="selected_users[]"][data-branch="${branch}"]`)
                        .forEach(checkbox => {
                            checkbox.checked = this.checked;
                        });
                    updateFilterCount();
                });
            });

            // Filter form: Individual checkbox handling
            userCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const branch = this.dataset.branch;
                    const branchCheckboxes = document.querySelectorAll(`input[name="selected_users[]"][data-branch="${branch}"]`);
                    const branchAll = document.querySelector(`.selectBranchAll[data-branch="${branch}"]`);
                    const allChecked = Array.from(branchCheckboxes).every(cb => cb.checked);
                    const noneChecked = Array.from(branchCheckboxes).every(cb => !cb.checked);
                    branchAll.checked = allChecked;
                    branchAll.indeterminate = !allChecked && !noneChecked;
                    updateFilterCount();
                });
            });

            // Bulk delete: Select/deselect all
            selectAllDelete.addEventListener('change', function() {
                deleteCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });

            // Bulk delete: Individual checkbox handling
            deleteCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const allChecked = Array.from(deleteCheckboxes).every(cb => cb.checked);
                    const noneChecked = Array.from(deleteCheckboxes).every(cb => !cb.checked);
                    selectAllDelete.checked = allChecked;
                    selectAllDelete.indeterminate = !allChecked && !noneChecked;
                });
            });

            // Initial filter count update
            updateFilterCount();

            // UPDATED: Copy row data to clipboard
            let isCopying = false; // Prevent multiple copy operations
            window.copyRowData = function(button) {
                if (isCopying) return; // Block concurrent clicks
                isCopying = true;

                try {
                    const rowData = JSON.parse(button.getAttribute('data-row'));
                    // Ensure only intended fields are included
                    const allowedFields = <?php echo json_encode($columnNames); ?>;
                    const filteredData = Object.keys(rowData)
                        .filter(key => allowedFields.includes(key))
                        .reduce((obj, key) => {
                            obj[key] = rowData[key];
                            return obj;
                        }, {});
                    
                    // Format data as a string (key-value pairs)
                    const formattedData = Object.entries(filteredData)
                        .map(([key, value]) => `${key}: ${value}`)
                        .join('\n');
                    
                    // Copy to clipboard
                    navigator.clipboard.writeText(formattedData)
                        .then(() => {
                            // Show success toast
                            showToast('បានចម្លងទិន្នន័យដោយជោគជ័យ!', 'success');
                        })
                        .catch(() => {
                            // Show error toast
                            showToast('មានបញ្ហាក្នុងការចម្លងទិន្នន័យ!', 'error');
                        })
                        .finally(() => {
                            isCopying = false; // Reset lock
                        });
                } catch (e) {
                    showToast('កំហុសក្នុងការចម្លងទិន្នន័យ!', 'error');
                    isCopying = false;
                }
            };

            // UPDATED: Function to show toast notification
            window.showToast = function(message, type) {
                // Remove existing toasts to prevent overlap
                document.querySelectorAll('.message.toast').forEach(toast => toast.remove());
                
                const toast = document.createElement('div');
                toast.className = `message ${type} toast`;
                toast.style.position = 'fixed';
                toast.style.bottom = '20px';
                toast.style.right = '20px';
                toast.style.zIndex = '1000';
                toast.style.padding = '1rem';
                toast.style.borderRadius = '6px';
                toast.textContent = message;
                document.body.appendChild(toast);

                // Auto-remove after 3 seconds
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            };
        });
    </script>
</body>
</html>