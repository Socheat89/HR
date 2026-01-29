<?php
session_start();

// Set time zone
date_default_timezone_set('Asia/Phnom_Penh');

// // Check if user is logged in - IMPORTANT for production!
// // For demonstration, this is commented out. In a real application,
// // this page should only be accessible to authorized users.
// if (!isset($_SESSION['user_id'])) {
//     $_SESSION['error'] = 'សូមចូលគណនីសិន!';
//     header("Location: login.php");
//     exit();
// }

// Include database configuration
require_once 'config.php'; // Assumes config.php provides DB_HOST, DB_NAME, DB_USER, DB_PASS

// Database connection
try {
    // If $db is not already set from a shared config or connection file
    if (!isset($db) || !$db instanceof PDO) {
        $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "ការតភ្ជាប់មូលដ្ឋានទិន្នន័យបរាជ័យ: " . $e->getMessage();
    header("Location: setting.php");
    exit();
}

/**
 * Loads the Telegram Bot Token from the database.
 * @param PDO $db The database connection.
 * @return string The bot token or empty string if not found.
 */
function loadBotToken(PDO $db): string {
    try {
        $stmt = $db->query("SELECT bot_token FROM telegram_settings WHERE id = 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['bot_token'] ?? '';
    } catch (PDOException $e) {
        error_log("Database error loading bot token: " . $e->getMessage());
        return ''; // Return empty string on error
    }
}

/**
 * Loads all Telegram groups and their associated threads from the database.
 * @param PDO $db The database connection.
 * @return array An array of group configurations, each with its 'thread_ids'.
 */
function loadTelegramGroups(PDO $db): array {
    $groups = [];
    try {
        $stmt = $db->query("SELECT group_id, name, chat_id, report_format FROM telegram_groups ORDER BY group_id ASC");
        while ($group = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $group['thread_ids'] = []; // Initialize
            // Fetch category as well
            $threadStmt = $db->prepare("SELECT thread_map_id, position, category, thread_id FROM telegram_group_threads WHERE group_id = :group_id ORDER BY category ASC, position ASC");
            $threadStmt->execute(['group_id' => $group['group_id']]);
            $group['thread_ids'] = $threadStmt->fetchAll(PDO::FETCH_ASSOC);
            $groups[] = $group;
        }
    } catch (PDOException $e) {
        error_log("Database error loading Telegram groups: " . $e->getMessage());
    }
    return $groups;
}

// Define the static categories for the dropdowns
// This list should ideally be managed from a dedicated categories table if more dynamic control is needed.
$allCategories = [
    'ផ្នែកការិយាល័យ',
    'ហាង និងផ្នែកលក់ (318)',
    'ហាង SK Cosmetics(ឈូកមាស)',
    'ហាង SK Cosmetics(ផ្លូវជាតិលេខ៣)',
    'គ្រប់គ្រងស្តុក និងឃ្លាំង',
    'ផ្សេងៗ' // Always include a default 'Other' category
];

// Handle POST requests for configuration updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // Sanitize common IDs once
    $groupId = filter_var($_POST['group_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);
    $threadMapId = filter_var($_POST['thread_map_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);

    try {
        switch ($action) {
            case 'update_bot_token':
                $new_token = filter_var($_POST['bot_token'], FILTER_SANITIZE_STRING);
                if (empty($new_token)) {
                    throw new Exception("Telegram Bot Token មិនអាចទទេរបានទេ។");
                }
                // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both first time insert and subsequent updates
                $stmt = $db->prepare("INSERT INTO telegram_settings (id, bot_token) VALUES (1, :bot_token) ON DUPLICATE KEY UPDATE bot_token = :bot_token");
                $stmt->execute(['bot_token' => $new_token]);
                $_SESSION['success'] = "បានរក្សាទុក Bot Token ដោយជោគជ័យ។";
                break;

            case 'add_group':
                $new_chat_id = filter_var($_POST['new_group_chat_id'], FILTER_SANITIZE_STRING);
                $new_report_format = filter_var($_POST['new_group_report_format'], FILTER_SANITIZE_STRING);
                $new_group_name = filter_var($_POST['new_group_name'], FILTER_SANITIZE_STRING);

                if (empty($new_chat_id) || empty($new_report_format) || empty($new_group_name)) {
                    throw new Exception("សូមបំពេញ Chat ID, Report Format និងឈ្មោះក្រុម សម្រាប់ក្រុមថ្មី។");
                }
                $stmt = $db->prepare("INSERT INTO telegram_groups (name, chat_id, report_format) VALUES (:name, :chat_id, :report_format)");
                $stmt->execute([
                    'name' => $new_group_name,
                    'chat_id' => $new_chat_id,
                    'report_format' => $new_report_format
                ]);
                $_SESSION['success'] = "បានបន្ថែមក្រុម Telegram ថ្មីដោយជោគជ័យ។";
                break;

            case 'update_group':
                if (empty($groupId)) {
                    throw new Exception("Group ID មិនត្រឹមត្រូវ។");
                }
                $new_group_name = filter_var($_POST['group_name'], FILTER_SANITIZE_STRING);
                $new_chat_id = filter_var($_POST['chat_id'], FILTER_SANITIZE_STRING);
                $new_report_format = filter_var($_POST['report_format'], FILTER_SANITIZE_STRING);

                if (empty($new_group_name) || empty($new_chat_id) || empty($new_report_format)) {
                    throw new Exception("សូមបំពេញ Chat ID, Report Format និងឈ្មោះក្រុម សម្រាប់ក្រុម ID: $groupId។");
                }

                $stmt = $db->prepare("UPDATE telegram_groups SET name = :name, chat_id = :chat_id, report_format = :report_format WHERE group_id = :group_id");
                $stmt->execute([
                    'name' => $new_group_name,
                    'chat_id' => $new_chat_id,
                    'report_format' => $new_report_format,
                    'group_id' => $groupId
                ]);

                $_SESSION['success'] = "បានកែប្រែក្រុម Telegram ដោយជោគជ័យ។";
                break;

            case 'delete_group':
                if (empty($groupId)) {
                    throw new Exception("Group ID មិនត្រឹមត្រូវ។");
                }
                // Due to ON DELETE CASCADE on telegram_group_threads, child records will be deleted automatically
                $stmt = $db->prepare("DELETE FROM telegram_groups WHERE group_id = :group_id");
                $stmt->execute(['group_id' => $groupId]);
                $_SESSION['success'] = "បានលុបក្រុម Telegram ដោយជោគជ័យ។";
                break;

            case 'add_thread_to_group':
                if (empty($groupId)) {
                    throw new Exception("Group ID មិនត្រឹមត្រូវ ដើម្បីបន្ថែមតួនាទី។");
                }
                $new_position_name = filter_var($_POST['new_position_name'], FILTER_SANITIZE_STRING);
                $new_position_category = filter_var($_POST['new_position_category'], FILTER_SANITIZE_STRING); // Fetch new category
                $new_position_thread_id = filter_var($_POST['new_position_thread_id'], FILTER_SANITIZE_NUMBER_INT);

                if (empty($new_position_name) || empty($new_position_category) || !is_numeric($new_position_thread_id)) {
                    throw new Exception("សូមបំពេញឈ្មោះតួនាទី, Category និង Thread ID សម្រាប់តួនាទីថ្មី។");
                }

                $stmt = $db->prepare("INSERT INTO telegram_group_threads (group_id, position, category, thread_id) VALUES (:group_id, :position, :category, :thread_id)");
                $stmt->execute([
                    'group_id' => $groupId,
                    'position' => $new_position_name,
                    'category' => $new_position_category, // Insert category
                    'thread_id' => (int)$new_position_thread_id
                ]);
                $_SESSION['success'] = "បានបន្ថែមតួនាទីទៅក្រុមដោយជោគជ័យ។";
                break;

            case 'update_thread':
                if (empty($threadMapId)) {
                    throw new Exception("Thread Map ID មិនត្រឹមត្រូវ។");
                }
                $position_name = filter_var($_POST['position_name'], FILTER_SANITIZE_STRING);
                $category_name = filter_var($_POST['category_name'], FILTER_SANITIZE_STRING); // Fetch category for update
                $thread_id_val = filter_var($_POST['thread_id_val'], FILTER_SANITIZE_NUMBER_INT);

                if (empty($position_name) || empty($category_name) || !is_numeric($thread_id_val)) {
                    throw new Exception("សូមបំពេញឈ្មោះតួនាទី, Category និង Thread ID សម្រាប់តួនាទី។");
                }
                $stmt = $db->prepare("UPDATE telegram_group_threads SET position = :position, category = :category, thread_id = :thread_id WHERE thread_map_id = :thread_map_id");
                $stmt->execute([
                    'position' => $position_name,
                    'category' => $category_name, // Update category
                    'thread_id' => (int)$thread_id_val,
                    'thread_map_id' => $threadMapId
                ]);
                $_SESSION['success'] = "បានកែប្រែតួនាទី, Category និង Thread ID ដោយជោគជ័យ។";
                break;

            case 'delete_thread':
                if (empty($threadMapId)) {
                    throw new Exception("Thread Map ID មិនត្រឹមត្រូវ។");
                }
                $stmt = $db->prepare("DELETE FROM telegram_group_threads WHERE thread_map_id = :thread_map_id");
                $stmt->execute(['thread_map_id' => $threadMapId]);
                $_SESSION['success'] = "បានលុបតួនាទីនិង Thread ID ដោយជោគជ័យ។";
                break;

            default:
                throw new Exception("សកម្មភាពមិនត្រឹមត្រូវ។");
        }

    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // SQLSTATE for integrity constraint violation (e.g., duplicate position)
            $_SESSION['error'] = "កំហុសមូលដ្ឋានទិន្នន័យ: តួនាទីនេះមានរួចហើយនៅក្នុងក្រុមនេះ។";
        } else {
            $_SESSION['error'] = "កំហុសមូលដ្ឋានទិន្នន័យ: " . $e->getMessage();
        }
        error_log("DB Error on settings page: " . $e->getMessage());
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        error_log("Application Error on settings page: " . $e->getMessage());
    }
    header("Location: setting.php"); // Corrected action URL
    exit();
}

// Load current configuration for display
$telegramBotToken = loadBotToken($db);
$telegramGroups = loadTelegramGroups($db);

?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telegram Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="icon" type="image/png" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
    <meta name="theme-color" content="#4f46e5">
    <style>
        /* Define a modern color palette */
        :root {
            --primary-color: #4f46e5; /* Indigo */
            --primary-light: #6366f1;
            --secondary-color: #111827; /* Dark Gray */
            --light-gray: #f3f4f6;
            --text-color: #374151;
            --border-color: #d1d5db;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --section-bg: #eef2ff; /* Light indigo for section separation */
            --sub-block-bg: #f9fafb; /* Lighter gray for sub-blocks */
        }

        body {
            font-family: 'Kantumruy Pro', sans-serif; /* A good Khmer font */
            background-color: var(--light-gray);
            color: var(--text-color);
            padding-bottom: 70px; /* For fixed bottom nav */
        }

        .main-container {
            max-width: 900px; /* Wider for settings */
            margin: 2rem auto;
            padding: 1rem;
        }

        .settings-card {
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); /* Stronger shadow for depth */
            margin-bottom: 2rem; /* More space between main blocks */
        }

        .card-header h2 {
            font-weight: 700;
            color: var(--secondary-color);
            text-align: center;
            margin-bottom: 2rem; /* More space below main title */
            font-size: 2.2rem; /* Larger main title */
        }
        
        .section-title {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 0.5rem;
            font-size: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            background-color: #ffffff;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
            outline: none;
            background-color: #ffffff;
        }

        .btn { /* General button styling */
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        .btn-primary:hover {
            background-color: var(--primary-light);
            border-color: var(--primary-light);
            transform: translateY(-1px);
        }

        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
            color: white;
            padding: 0.7rem 1.2rem; /* Slightly smaller for delete */
        }
        .btn-danger:hover {
            background-color: #dc2626;
            border-color: #dc2626;
            transform: translateY(-1px);
        }
        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
            color: white;
            padding: 0.7rem 1.2rem; /* Slightly smaller for add */
        }
        .btn-success:hover {
            background-color: #059669;
            border-color: #059669;
            transform: translateY(-1px);
        }

        .group-item {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem; /* Space between individual groups */
            background-color: var(--sub-block-bg); /* Lighter background for group items */
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05); /* Subtle shadow for group items */
        }

        .group-item h5 {
            color: var(--secondary-color);
            font-weight: 600;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px dashed var(--border-color); /* Dotted separator */
        }

        .thread-ids-table th, .thread-ids-table td {
            vertical-align: middle;
            padding: 0.75rem 0.5rem; /* More padding */
        }

        .thread-ids-table thead th {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            font-weight: 600;
        }
        .thread-ids-table tbody tr:nth-child(odd) {
            background-color: #ffffff; /* Alternating row colors */
        }
        .thread-ids-table tbody tr:nth-child(even) {
            background-color: var(--sub-block-bg);
        }
        .thread-ids-table td input.form-control-sm,
        .thread-ids-table td select.form-control-sm { /* Added select for consistency */
            padding: 0.5rem 0.75rem; /* Adjust smaller input padding */
        }

        .alert {
            border-radius: 8px;
            font-weight: 500;
            margin-bottom: 2rem;
        }

        /* Bottom Navigation for Mobile */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.1); /* Stronger shadow for nav */
            z-index: 1000;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #6b7280;
            font-size: 0.75rem;
            padding: 5px 0;
            width: 25%; /* Distribute space evenly */
        }
        .nav-item.active {
            color: var(--primary-color);
            font-weight: 600;
        }
        .nav-icon {
            font-size: 1.4rem; /* Slightly larger icons */
            margin-bottom: 4px;
        }
        .nav-item:hover {
            color: var(--primary-light);
        }


        /* Hide bottom nav on larger screens */
        @media (min-width: 768px) {
            .bottom-nav {
                display: none;
            }
            .main-container {
                padding-bottom: 1rem; /* No need for extra padding on larger screens */
            }
        }
        @media (max-width: 576px) {
            .settings-card {
                padding: 1.5rem;
            }
            .group-item {
                padding: 1rem;
            }
            .main-container {
                margin: 1rem auto 6rem auto; /* Add margin-bottom to avoid overlap with nav */
            }
            .card-header h2 {
                font-size: 1.8rem;
                margin-bottom: 1.5rem;
            }
            .section-title {
                font-size: 1.3rem;
                margin-bottom: 1rem;
            }
            .btn {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
            .btn-danger, .btn-success {
                padding: 0.4rem 0.8rem;
            }
            .nav-item {
                font-size: 0.7rem;
            }
            .nav-icon {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>

    <div class="main-container">

        <div class="card-header">
            <h2><i class="fas fa-cogs me-3"></i>ការកំណត់ Telegram</h2>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success animate__animated animate__fadeInDown" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger animate__animated animate__fadeInDown" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Bot Token Settings Block -->
        <div class="settings-card animate__animated animate__fadeInUp">
            <h4 class="section-title"><i class="fab fa-telegram-plane me-2"></i> Telegram Bot Token</h4>
            <form method="POST">
                <input type="hidden" name="action" value="update_bot_token">
                <div class="mb-4">
                    <label for="botTokenInput" class="form-label">Bot Token:</label>
                    <input type="text" class="form-control" id="botTokenInput" name="bot_token" value="<?php echo htmlspecialchars($telegramBotToken); ?>" required>
                    <small class="form-text text-muted mt-2 d-block">
                        បិទភ្ជាប់ Telegram Bot Token របស់អ្នកនៅទីនេះ។ វាជាសោសម្រាប់ផ្ញើសារ។
                    </small>
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i> រក្សាទុក Bot Token</button>
            </form>
        </div>


        <!-- Existing Telegram Groups Block -->
        <div class="settings-card animate__animated animate__fadeInUp">
            <h4 class="section-title"><i class="fas fa-users-cog me-2"></i> ក្រុម Telegram ដែលមានស្រាប់</h4>
            <?php if (empty($telegramGroups)): ?>
                <div class="alert alert-info text-center py-4">
                    <i class="fas fa-info-circle me-2"></i> មិនទាន់មានក្រុម Telegram ណាមួយត្រូវបានកំណត់រចនាសម្ព័ន្ធនៅឡើយទេ។
                    <p class="mt-2 mb-0">សូមប្រើទម្រង់ខាងក្រោមដើម្បីបន្ថែមក្រុមថ្មី។</p>
                </div>
            <?php else: ?>
                <?php foreach ($telegramGroups as $group): ?>
                    <div class="group-item mb-4" id="group-<?php echo htmlspecialchars($group['group_id']); ?>">
                        <!-- Group details form -->
                        <form method="POST" class="mb-4">
                            <input type="hidden" name="action" value="update_group">
                            <input type="hidden" name="group_id" value="<?php echo htmlspecialchars($group['group_id']); ?>">

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="groupName_<?php echo htmlspecialchars($group['group_id']); ?>" class="form-label">ឈ្មោះក្រុម:</label>
                                    <input type="text" class="form-control" id="groupName_<?php echo htmlspecialchars($group['group_id']); ?>" name="group_name" value="<?php echo htmlspecialchars($group['name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="chatId_<?php echo htmlspecialchars($group['group_id']); ?>" class="form-label">Chat ID:</label>
                                    <input type="text" class="form-control" id="chatId_<?php echo htmlspecialchars($group['group_id']); ?>" name="chat_id" value="<?php echo htmlspecialchars($group['chat_id']); ?>" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="reportFormat_<?php echo htmlspecialchars($group['group_id']); ?>" class="form-label">ទម្រង់របាយការណ៍:</label>
                                <select class="form-select" id="reportFormat_<?php echo htmlspecialchars($group['group_id']); ?>" name="report_format" required>
                                    <option value="link" <?php echo ($group['report_format'] === 'link') ? 'selected' : ''; ?>>បង្ហាញតែ Link</option>
                                    <option value="full" <?php echo ($group['report_format'] === 'full') ? 'selected' : ''; ?>>បង្ហាញមាតិកាពេញ</option>
                                </select>
                            </div>

                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 mt-4 pt-3 border-top">
                                <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-save me-2"></i> រក្សាទុកការផ្លាស់ប្ដូរក្រុម</button>
                                <button type="button" class="btn btn-danger flex-grow-1" onclick="confirmDeleteGroup('<?php echo htmlspecialchars($group['group_id']); ?>')"><i class="fas fa-trash-alt me-2"></i> លុបក្រុម</button>
                            </div>
                        </form>

                        <div class="sub-block mt-4 pt-3">
                            <h5><i class="fas fa-thumbtack me-2"></i>ការកំណត់ Thread IDs (តួនាទី) សម្រាប់ក្រុមនេះ:</h5>
                            <?php if (empty($group['thread_ids'])): ?>
                                <p class="text-muted text-center py-2">មិនទាន់មានតួនាទីត្រូវបានកំណត់សម្រាប់ក្រុមនេះនៅឡើយទេ។</p>
                            <?php else: ?>
                                <div class="table-responsive mb-3">
                                    <table class="table table-hover thread-ids-table">
                                        <thead>
                                            <tr>
                                                <th class="text-white" style="width: 35%;">តួនាទី</th>
                                                <th class="text-white" style="width: 35%;">ប្រភេទ</th>
                                                <th class="text-white" style="width: 15%;">Thread ID</th>
                                                <th class="text-white" style="width: 15%;">សកម្មភាព</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($group['thread_ids'] as $thread_item): ?>
                                                <tr>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="update_thread">
                                                        <input type="hidden" name="group_id" value="<?php echo htmlspecialchars($group['group_id']); ?>">
                                                        <input type="hidden" name="thread_map_id" value="<?php echo htmlspecialchars($thread_item['thread_map_id']); ?>">
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm"
                                                                   name="position_name"
                                                                   value="<?php echo htmlspecialchars($thread_item['position']); ?>" required>
                                                        </td>
                                                        <td>
                                                            <select class="form-select form-control-sm" name="category_name" required>
                                                                <?php foreach ($allCategories as $cat): ?>
                                                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($thread_item['category'] === $cat) ? 'selected' : ''; ?>>
                                                                        <?php echo htmlspecialchars($cat); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </td>
                                                        <td>
                                                            <input type="number" class="form-control form-control-sm"
                                                                   name="thread_id_val"
                                                                   value="<?php echo htmlspecialchars($thread_item['thread_id']); ?>" required>
                                                        </td>
                                                        <td class="d-flex align-items-center justify-content-evenly">
                                                            <button type="submit" class="btn btn-primary btn-sm me-1" title="រក្សាទុក Thread">
                                                                <i class="fas fa-save"></i>
                                                            </button>
                                                    </form>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('តើអ្នកពិតជាចង់លុបតួនាទីនេះមែនទេ?');">
                                                        <input type="hidden" name="action" value="delete_thread">
                                                        <input type="hidden" name="thread_map_id" value="<?php echo htmlspecialchars($thread_item['thread_map_id']); ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm" title="លុប Thread">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                    </form>
                                                        </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <!-- Add New Position to this group form -->
                            <h5 class="mt-4"><i class="fas fa-plus-circle me-2"></i>បន្ថែមតួនាទីថ្មីទៅក្រុមនេះ:</h5>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_thread_to_group">
                                <input type="hidden" name="group_id" value="<?php echo htmlspecialchars($group['group_id']); ?>">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-4">
                                        <label for="newPositionName_<?php echo htmlspecialchars($group['group_id']); ?>" class="form-label">តួនាទី:</label>
                                        <input type="text" class="form-control form-control-sm" id="newPositionName_<?php echo htmlspecialchars($group['group_id']); ?>" name="new_position_name" placeholder="ឈ្មោះតួនាទី" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="newPositionCategory_<?php echo htmlspecialchars($group['group_id']); ?>" class="form-label">ប្រភេទ:</label>
                                        <select class="form-select form-control-sm" id="newPositionCategory_<?php echo htmlspecialchars($group['group_id']); ?>" name="new_position_category" required>
                                            <?php foreach ($allCategories as $cat): ?>
                                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label for="newPositionThreadId_<?php echo htmlspecialchars($group['group_id']); ?>" class="form-label">Thread ID:</label>
                                        <input type="number" class="form-control form-control-sm" id="newPositionThreadId_<?php echo htmlspecialchars($group['group_id']); ?>" name="new_position_thread_id" placeholder="ID" required>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" class="btn btn-success btn-sm w-100"><i class="fas fa-plus me-2"></i> បន្ថែម</button>
                                    </div>
                                </div>
                            </form>
                        </div> <!-- End sub-block -->
                    </div> <!-- End group-item -->
                <?php endforeach; ?>
            <?php endif; ?>
        </div> <!-- End settings-card for existing groups -->

        <!-- Add New Telegram Group Block -->
        <div class="settings-card animate__animated animate__fadeInUp">
            <h4 class="section-title"><i class="fas fa-plus-square me-2"></i> បន្ថែមក្រុម Telegram ថ្មី</h4>
            <form method="POST">
                <input type="hidden" name="action" value="add_group">
                <div class="mb-3">
                    <label for="newGroupName" class="form-label">ឈ្មោះក្រុម:</label>
                    <input type="text" class="form-control" id="newGroupName" name="new_group_name" placeholder="ឧទាហរណ៍: របាយការណ៍ផ្នែក IT" required>
                </div>
                <div class="mb-3">
                    <label for="newGroupChatId" class="form-label">Chat ID:</label>
                    <input type="text" class="form-control" id="newGroupChatId" name="new_group_chat_id" placeholder="ឧទាហរណ៍: -1001234567890" required>
                </div>
                <div class="mb-4">
                    <label for="newGroupReportFormat" class="form-label">ទម្រង់របាយការណ៍:</label>
                    <select class="form-select" id="newGroupReportFormat" name="new_group_report_format" required>
                        <option value="link">បង្ហាញតែ Link</option>
                        <option value="full">បង្ហាញមាតិកាពេញ</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus-circle me-2"></i> បន្ថែមក្រុម</button>
            </form>
        </div>
    </div>

    <!-- Bottom Navigation Bar for Mobile -->
    <nav class="bottom-nav">
        <a href="homes.php" class="nav-item">
            <i class="fas fa-home nav-icon"></i>
            <span>ទំព័រដើម</span>
        </a>
        <a href="daily_reports.php" class="nav-item">
            <i class="fas fa-edit nav-icon"></i>
            <span>របាយការណ៍</span>
        </a>
        <a href="list_reports.php" class="nav-item">
            <i class="fas fa-list-alt nav-icon"></i>
            <span>បញ្ជី</span>
        </a>
        <a href="setting.php" class="nav-item active">
            <i class="fas fa-cog nav-icon"></i>
            <span>កំណត់</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to confirm group deletion
            window.confirmDeleteGroup = function(groupId) {
                if (confirm('តើអ្នកពិតជាចង់លុបក្រុម Telegram នេះមែនទេ? សកម្មភាពនេះមិនអាចត្រឡប់វិញបានទេ។ វានឹងលុបតួនាទីទាំងអស់ដែលបានកំណត់សម្រាប់ក្រុមនេះផងដែរ។')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'setting.php'; // Corrected action URL

                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete_group';
                    form.appendChild(actionInput);

                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'group_id';
                    idInput.value = groupId;
                    form.appendChild(idInput);

                    document.body.appendChild(form);
                    form.submit();
                }
            };
        });
    </script>
</body>
</html>