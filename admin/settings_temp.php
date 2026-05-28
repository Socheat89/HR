<?php
// Start session - This must be at the very beginning
session_start();

// Set UTF-8 encoding for both sections
header('Content-Type: text/html; charset=UTF-8');

// Set time zone for Telegram Settings part
date_default_timezone_set('Asia/Phnom_Penh');

// Unified Login Check (from settings_control.php, assuming this is the main dashboard's auth)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php'); // Redirect to login page
    exit;
}

// --- Unified Database Connection Details ---
// Using details from settings_control.php as primary.
// If your Telegram settings database is different, you'll need to adjust this
// to either connect to multiple databases or merge your tables into one.
$host = 'localhost';
$dbname = 'samann1_admin_panel'; // Assuming Telegram tables are also in this DB
$user = 'samann1_admin_panel';
$password = '';

$pdo = null;
$error_message = ''; // For general errors
$success_message = ''; // For general success messages

// Initialize session messages for display
$session_success_message = $_SESSION['success'] ?? '';
$session_error_message = $_SESSION['error'] ?? '';
unset($_SESSION['success']);
unset($_SESSION['error']);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET NAMES 'utf8mb4'");

    // =================================================================================
    // Telegram Settings Functions (Adapted to use $pdo)
    // =================================================================================

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

    // Define the static categories for Telegram dropdowns
    $telegramAllCategories = [
        'ááááááá¶áá·áá¶ááá',
        'á á¶á áá·ááááááááá (318)',
        'á á¶á SK Cosmetics(áá¼ááá¶á)',
        'á á¶á SK Cosmetics(áááá¼ááá¶áá·áááá£)',
        'ááááááááááááá»á áá·ááááá¶áá',
        'áááááá' // Always include a default 'Other' category
    ];


    // =================================================================================
    // Categories Control Definitions
    // =================================================================================
    $category_types = ['department', 'position', 'branch'];
    $khmer_names = ['department' => 'ááááá', 'position' => 'áá»áááááá', 'branch' => 'áá¶áá¶'];


    // =================================================================================
    // HANDLE ALL FORM SUBMISSIONS (ADD/EDIT/DELETE for both sections)
    // =================================================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        try {
            switch ($action) {
                // --- Telegram Settings Actions ---
                case 'update_bot_token':
                    $new_token = filter_var($_POST['bot_token'], FILTER_SANITIZE_STRING);
                    if (empty($new_token)) {
                        throw new Exception("Telegram Bot Token áá·áá¢á¶ááááááá¶áááá");
                    }
                    $stmt = $pdo->prepare("INSERT INTO telegram_settings (id, bot_token) VALUES (1, :bot_token) ON DUPLICATE KEY UPDATE bot_token = :bot_token");
                    $stmt->execute(['bot_token' => $new_token]);
                    $_SESSION['success'] = "áá¶áááááá¶áá»á Bot Token áááááááááá";
                    break;

                case 'add_group':
                    $new_chat_id = filter_var($_POST['new_group_chat_id'], FILTER_SANITIZE_STRING);
                    $new_report_format = filter_var($_POST['new_group_report_format'], FILTER_SANITIZE_STRING);
                    $new_group_name = filter_var($_POST['new_group_name'], FILTER_SANITIZE_STRING);

                    if (empty($new_chat_id) || empty($new_report_format) || empty($new_group_name)) {
                        throw new Exception("áá¼áááááá Chat ID, Report Format áá·áááááááááá»á ááááá¶áááááá»ááááá¸á");
                    }
                    $stmt = $pdo->prepare("INSERT INTO telegram_groups (name, chat_id, report_format) VALUES (:name, :chat_id, :report_format)");
                    $stmt->execute([
                        'name' => $new_group_name,
                        'chat_id' => $new_chat_id,
                        'report_format' => $new_report_format
                    ]);
                    $_SESSION['success'] = "áá¶ááááááááááá»á Telegram áááá¸áááááááááá";
                    break;

                case 'update_group':
                    $groupId = filter_var($_POST['group_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);
                    if (empty($groupId)) {
                        throw new Exception("Group ID áá·ááááá¹ááááá¼áá");
                    }
                    $new_group_name = filter_var($_POST['group_name'], FILTER_SANITIZE_STRING);
                    $new_chat_id = filter_var($_POST['chat_id'], FILTER_SANITIZE_STRING);
                    $new_report_format = filter_var($_POST['report_format'], FILTER_SANITIZE_STRING);

                    if (empty($new_group_name) || empty($new_chat_id) || empty($new_report_format)) {
                        throw new Exception("áá¼áááááá Chat ID, Report Format áá·áááááááááá»á ááááá¶áááááá»á ID: $groupIdá");
                    }

                    $stmt = $pdo->prepare("UPDATE telegram_groups SET name = :name, chat_id = :chat_id, report_format = :report_format WHERE group_id = :group_id");
                    $stmt->execute([
                        'name' => $new_group_name,
                        'chat_id' => $new_chat_id,
                        'report_format' => $new_report_format,
                        'group_id' => $groupId
                    ]);

                    $_SESSION['success'] = "áá¶ááááááááááá»á Telegram áááááááááá";
                    break;

                case 'delete_group':
                    $groupId = filter_var($_POST['group_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);
                    if (empty($groupId)) {
                        throw new Exception("Group ID áá·ááááá¹ááááá¼áá");
                    }
                    $stmt = $pdo->prepare("DELETE FROM telegram_groups WHERE group_id = :group_id");
                    $stmt->execute(['group_id' => $groupId]);
                    $_SESSION['success'] = "áá¶ááá»ááááá»á Telegram áááááááááá";
                    break;

                case 'add_thread_to_group':
                    $groupId = filter_var($_POST['group_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);
                    if (empty($groupId)) {
                        throw new Exception("Group ID áá·ááááá¹ááááá¼á áá¾áááá¸áááááááá½áá¶áá¸á");
                    }
                    $new_position_name = filter_var($_POST['new_position_name'], FILTER_SANITIZE_STRING);
                    $new_position_category = filter_var($_POST['new_position_category'], FILTER_SANITIZE_STRING);
                    $new_position_thread_id = filter_var($_POST['new_position_thread_id'], FILTER_SANITIZE_NUMBER_INT);

                    if (empty($new_position_name) || empty($new_position_category) || !is_numeric($new_position_thread_id)) {
                        throw new Exception("áá¼ááááááááááááá½áá¶áá¸, Category áá·á Thread ID ááááá¶áááá½áá¶áá¸áááá¸á");
                    }

                    $stmt = $pdo->prepare("INSERT INTO telegram_group_threads (group_id, position, category, thread_id) VALUES (:group_id, :position, :category, :thread_id)");
                    $stmt->execute([
                        'group_id' => $groupId,
                        'position' => $new_position_name,
                        'category' => $new_position_category,
                        'thread_id' => (int)$new_position_thread_id
                    ]);
                    $_SESSION['success'] = "áá¶ááááááááá½áá¶áá¸áááááá»ááááááááááá";
                    break;

                case 'update_thread':
                    $threadMapId = filter_var($_POST['thread_map_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);
                    if (empty($threadMapId)) {
                        throw new Exception("Thread Map ID áá·ááááá¹ááááá¼áá");
                    }
                    $position_name = filter_var($_POST['position_name'], FILTER_SANITIZE_STRING);
                    $category_name = filter_var($_POST['category_name'], FILTER_SANITIZE_STRING);
                    $thread_id_val = filter_var($_POST['thread_id_val'], FILTER_SANITIZE_NUMBER_INT);

                    if (empty($position_name) || empty($category_name) || !is_numeric($thread_id_val)) {
                        throw new Exception("áá¼ááááááááááááá½áá¶áá¸, Category áá·á Thread ID ááááá¶áááá½áá¶áá¸á");
                    }
                    $stmt = $pdo->prepare("UPDATE telegram_group_threads SET position = :position, category = :category, thread_id = :thread_id WHERE thread_map_id = :thread_map_id");
                    $stmt->execute([
                        'position' => $position_name,
                        'category' => $category_name,
                        'thread_id' => (int)$thread_id_val,
                        'thread_map_id' => $threadMapId
                    ]);
                    $_SESSION['success'] = "áá¶ááááááááá½áá¶áá¸, Category áá·á Thread ID áááááááááá";
                    break;

                case 'delete_thread':
                    $threadMapId = filter_var($_POST['thread_map_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);
                    if (empty($threadMapId)) {
                        throw new Exception("Thread Map ID áá·ááááá¹ááááá¼áá");
                    }
                    $stmt = $pdo->prepare("DELETE FROM telegram_group_threads WHERE thread_map_id = :thread_map_id");
                    $stmt->execute(['thread_map_id' => $threadMapId]);
                    $_SESSION['success'] = "áá¶ááá»ááá½áá¶áá¸áá·á Thread ID áááááááááá";
                    break;

                // --- Categories Control Actions ---
                case 'add':
                    $type = trim($_POST['category_type'] ?? '');
                    $name = trim($_POST['category_name'] ?? '');

                    if (empty($type) || empty($name) || !in_array($type, $category_types)) {
                        throw new Exception("áá¼áááááááááááá áá·ááááááá±áááá¶ááááá¹ááááá¼áá");
                    }

                    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE type = ? AND name = ?");
                    $stmt_check->execute([$type, $name]);
                    if ($stmt_check->fetchColumn() > 0) {
                        throw new Exception("ááááá **" . htmlspecialchars($name) . "** áá¶ááááááá»áááááá **" . htmlspecialchars($type) . "** áá½áá á¾áá");
                    }

                    $stmt = $pdo->prepare("INSERT INTO categories (type, name) VALUES (?, ?)");
                    $stmt->execute([$type, $name]);
                    $_SESSION['success'] = "áá¶áááááá¼á **" . htmlspecialchars($name) . "** áááááá»áááááá **" . htmlspecialchars($khmer_names[$type]) . "** ááááááááá! â";
                    break;

                case 'edit':
                    $id = (int)($_POST['category_id'] ?? 0);
                    $newName = trim($_POST['new_category_name'] ?? '');
                    $newType = trim($_POST['new_category_type'] ?? '');

                    if ($id <= 0 || empty($newName) || empty($newType) || !in_array($newType, $category_types)) {
                           throw new Exception("áá·ááááááááááá¶áááááááááá·ááááááá á¬áá·ááááá¹ááááá¼áá");
                    }

                    $stmt_check = $pdo->prepare("SELECT id FROM categories WHERE type = ? AND name = ? AND id != ?");
                    $stmt_check->execute([$newType, $newName, $id]);
                    if ($stmt_check->fetch()) {
                        throw new Exception("ááááááááá¸ **" . htmlspecialchars($newName) . "** áá¶ááááááá»ááááááá **" . htmlspecialchars($khmer_names[$newType]) . "** áá½áá á¾áá");
                    }

                    $stmt = $pdo->prepare("UPDATE categories SET type = ?, name = ? WHERE id = ?");
                    $stmt->execute([$newType, $newName, $id]);
                    $_SESSION['success'] = "áá¶ááááááááá·áááááá (ID: $id) áááá¶ **" . htmlspecialchars($newName) . "** ááááááááá! âï¸";
                    break;

                case 'delete':
                    $id = (int)($_POST['category_id'] ?? 0);
                    if ($id <= 0) {
                        throw new Exception("áááááááá¶áááá·ááááá¹ááááá¼áááááá¶áááá»áá");
                    }

                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    $_SESSION['success'] = "áá¶ááá»ááá·ááááááááááááááá! ðï¸";
                    break;

                default:
                    throw new Exception("ááááááá¶ááá·ááááá¹ááááá¼áá");
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // SQLSTATE for integrity constraint violation
                $_SESSION['error'] = "ááá á»ááá¼ááááá¶ááá·áááááá: áá·ááááááááááá¶ááá½áá á¾á á¬áá¶ááááá á¶áá¶áá½á Relationshipá";
            } else {
                $_SESSION['error'] = "ááá á»ááá¼ááááá¶ááá·áááááá: " . $e->getMessage();
            }
            error_log("DB Error on settings_control.php: " . $e->getMessage());
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            error_log("Application Error on settings_control.php: " . $e->getMessage());
        }
        header("Location: settings_control.php"); // Redirect to self
        exit();
    }

    // Check for success message from redirect (settings_control.php style)
    if (isset($_GET['success'])) {
        $success_message = htmlspecialchars($_GET['success']);
    }


    // =================================================================================
    // LOAD ALL DATA FOR DISPLAY (GET REQUEST)
    // =================================================================================

    // Load Telegram configuration
    $telegramBotToken = loadBotToken($pdo);
    $telegramGroups = loadTelegramGroups($pdo);

    // Load Category data for display
    $stmt_cats = $pdo->query("SELECT id, type, name FROM categories ORDER BY type, name");
    $all_cats = $stmt_cats->fetchAll(PDO::FETCH_ASSOC);
    $dynamic_categories = []; // Reset for current load
    foreach ($all_cats as $cat) {
        $dynamic_categories[$cat['type']][] = $cat;
    }

} catch (Exception $e) {
    // Catch-all for database connection or initial load errors
    $error_message = "ááá á»ááááá»ááá¶áááááá¶áá á¬áááá¾ááá¶ááá¼ááááá¶ááá·áááááá: " . $e->getMessage();
    error_log("Critical Error on settings_control.php: " . $e->getMessage());
}

// Check if sidebar includes exist (from settings_control.php)
$sidebar_path = 'includes/sidebar.php';
$sidebar_exists = file_exists($sidebar_path);

?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>áá¶áááááááá¶ááá¢áá</title>
    <!-- Tailwind CSS (from settings_control.php) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Bootstrap CSS (from settings_control.php) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome (used by both) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    <!-- Animate.css (from settings_control.php) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <!-- Khmer Font (from settings_control.php) -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- Favicon and Theme Color (from settings_control.php) -->
    <link rel="icon" type="image/png" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
    <meta name="theme-color" content="#4f46e5">

    <style>
        /* --- Unified CSS Variables (Merging from both, prioritizing settings_control.php's dark theme) --- */
        :root {
            --primary-bg: #161b22;       /* áááááá¶ááááááááááá - áááááááá¶á (from settings_control) */
            --secondary-bg: #0d1117;       /* áááááá¶áááááááá¸áá¸á - áááááá·á (from settings_control) */
            --card-bg: rgba(22, 27, 34, 0.9); /* áááááá¶áááááááá¶á (áá¶áááááá¶áá¶áááááá·á) (from settings_control) */
            --border-color: rgba(255, 255, 255, 0.1); /* áááááá - áááá¾á (from settings_control) */
            --accent-color: #ffd700;       /* áááááááááááááá - áá¶ááá»ááá (from settings_control) */
            --accent-hover: #ffea70;       /* áááááááááááá Hover (from settings_control) */
            --text-primary: #f0f6fc;       /* áááá¢ááááááááá - áááááº (from settings_control) */
            --text-secondary: #ffffff;      /* áááá¢áááááá¸áá¸á - áááááááááá¶á (from settings_control) */
            --success: #2ea043;          /* ááááááááá - áááá (from settings_control) */
            --danger: #da3633;          /* áááááááááááá¶áá - áááá á (from settings_control) */
            --warning: #ffd700;          /* áááááááá¶á - áá¶á */

            /* Telegram specific colors - adapting to dark theme */
            --telegram-primary-color: #4f46e5; /* Indigo */
            --telegram-primary-light: #6366f1;
            --telegram-text-color: #f0f6fc; /* Adapting to dark theme */
            --telegram-border-color: rgba(255, 255, 255, 0.15);
            --telegram-success-color: #10b981;
            --telegram-danger-color: #ef4444;
            --telegram-section-bg: rgba(79, 70, 229, 0.08); /* Light indigo for section separation */
            --telegram-sub-block-bg: rgba(255, 255, 255, 0.05); /* Lighter gray for sub-blocks */
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideIn { from { transform: translateX(-100%); } to { transform: translateX(0); } }

        body {
            background-color: var(--primary-bg);
            background-image: linear-gradient(135deg, var(--secondary-bg) 0%, var(--primary-bg) 100%);
            font-family: 'Noto Sans Khmer', sans-serif;
            color: var(--text-primary);
            font-size: 1.05rem;
            line-height: 1.6;
            min-height: 100vh;
            display: flex; /* For sidebar layout */
            margin: 0;
            padding: 0;
        }
       /* --- Sidebar Navigation (ááá¶ááá»áááááá áá) --- */
        aside {
            background-color: var(--secondary-bg);
            border-right: 1px solid var(--border-color);
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.4); 
            animation: slideIn 0.5s ease-out;
        }
        aside h2 {
            color: var(--accent-hover);
            text-shadow: 0 0 12px rgba(255, 215, 0, 0.7); 
            font-size: 2rem;
            font-weight: 700;
        }
        aside a, aside button {
            color: var(--text-secondary);
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
            padding: 14px 12px;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
        }
        aside a:hover, aside button:hover {
            color: var(--accent-hover);
            background-color: var(--primary-bg);
            border-left-color: var(--accent-hover);
            transform: translateX(5px);
        }
        aside a.active, aside button.active {
            color: var(--accent-hover);
            font-weight: 700;
            background-color: var(--primary-bg);
            border-left-color: var(--accent-hover);
        }
        main {
            flex-grow: 1;
            padding: 2rem;
            overflow-y: auto;
            animation: fadeIn 0.6s ease-out;
            padding-bottom: 70px; /* Space for fixed bottom nav on mobile */
        }

        .main-content-wrapper { /* General container for content within main */
            max-width: 1000px;
            margin: auto;
        }

        /* --- Unified Card & Form Elements --- */
        .card-base {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.3);
            padding: 1.5rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 2rem; /* Consistent spacing */
        }
        .form-label { font-weight: 600; color: var(--text-primary); margin-bottom: 0.5rem; display: block;}
        .form-input, .form-select, .form-control {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 1rem;
            width: 100%;
        }
        .form-input:focus, .form-select:focus, .form-control:focus {
            outline: none;
            border-color: var(--accent-hover);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.4);
        }
        /* Bootstrap overrides for form-select background on dark theme */
        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffea70' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
            background-color: var(--secondary-bg);
            color: var(--text-primary);
            background-repeat: no-repeat; /* <<< FIX: Prevent repeating 'V' characters */
            background-position: right 0.75rem center;
            background-size: 16px 12px; /* ~1em 0.75em */
        }
        .form-select option {
            background-color: var(--secondary-bg); /* Ensure options are dark */
            color: var(--text-primary);
        }

        .btn-base {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 700;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            font-size: 0.95rem;
        }
        .btn-primary {
            background: linear-gradient(90deg, var(--accent-color), var(--accent-hover));
            color: var(--secondary-bg);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        .btn-danger:hover {
            background-color: #c02927; /* Slightly darker red */
            transform: translateY(-1px);
        }
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        .btn-success:hover {
            background-color: #268a3a; /* Slightly darker green */
            transform: translateY(-1px);
        }

        .alert { border-radius: 10px; padding: 1rem; margin-bottom: 1.5rem; font-weight: 500; font-size: 1.05rem; }
        .alert-danger { background-color: rgba(218, 54, 51, 0.2); color: var(--danger); border: 1px solid var(--danger); }
        .alert-success { background-color: rgba(46, 160, 67, 0.2); color: var(--success); border: 1px solid var(--success); }
        .alert-info { background-color: rgba(52, 152, 219, 0.2); color: #3498db; border: 1px solid #3498db; }


        /* --- Categories Control Specific Styles --- */
        .category-list { list-style: none; padding: 0; max-height: 300px; overflow-y: auto; }
        .category-item {
            display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; margin-bottom: 8px;
            background: rgba(255, 255, 255, 0.05); /* Lighter background */
            border-radius: 8px; border-left: 5px solid var(--accent-color);
            transition: background 0.2s, border-color 0.2s;
            color: var(--text-primary);
        }
        .category-item:hover { background: rgba(255, 255, 255, 0.1); }
        .item-actions button { margin-left: 10px; background: none; border: none; cursor: pointer; padding: 0; transition: color 0.2s, transform 0.2s; font-size: 1.2em; }
        .edit-btn { color: #3498db; }
        .delete-btn { color: var(--danger); }

        /* --- Telegram Settings Specific Styles (Adapted for dark theme) --- */
        .telegram-settings-card {
            background-color: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            margin-bottom: 2rem;
        }

        .telegram-section-title {
            font-weight: 600;
            color: var(--telegram-primary-color); /* Use Telegram's primary color for sections */
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--telegram-primary-light);
            padding-bottom: 0.5rem;
            font-size: 1.5rem;
        }
        .telegram-group-item {
            border: 1px solid var(--telegram-border-color);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background-color: var(--telegram-sub-block-bg);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .telegram-group-item h5 {
            color: var(--text-primary); /* Use main text color */
            font-weight: 600;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px dashed var(--telegram-border-color);
        }

        .thread-ids-table th, .thread-ids-table td {
            vertical-align: middle;
            padding: 0.75rem 0.5rem;
            color: var(--text-primary); /* Ensure text is visible */
        }
        .thread-ids-table thead th {
            background-color: var(--telegram-primary-color);
            color: white;
            border-color: var(--telegram-primary-color);
            font-weight: 600;
        }
        .thread-ids-table tbody tr:nth-child(odd) {
            background-color: rgba(255, 255, 255, 0.03); /* Darker alternating row */
        }
        .thread-ids-table tbody tr:nth-child(even) {
            background-color: var(--telegram-sub-block-bg);
        }
        .thread-ids-table td input.form-control-sm,
        .thread-ids-table td select.form-control-sm {
            padding: 0.5rem 0.75rem;
            background-color: var(--secondary-bg); /* Dark background for inputs */
            border-color: var(--border-color);
            color: var(--text-primary);
        }
        .thread-ids-table td input.form-control-sm:focus,
        .thread-ids-table td select.form-control-sm:focus {
            border-color: var(--telegram-primary-light);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }
        .thread-ids-table td select.form-control-sm {
             background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23f0f6fc' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e"); /* White arrow for dark select */
             background-repeat: no-repeat; /* <<< FIX: Prevent repeating 'V' characters for small selects */
             background-position: right 0.75rem center;
             background-size: 16px 12px; /* ~1em 0.75em */
        }
        .thread-ids-table td select.form-control-sm option {
            background-color: var(--secondary-bg); /* Dark background for options */
            color: var(--text-primary);
        }

        /* --- Tab Specific Styles --- */
        .nav-tabs {
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }
        .nav-tabs .nav-link {
            border: 1px solid transparent;
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
            color: var(--text-secondary); /* Default tab text color */
            background-color: var(--primary-bg); /* Default tab background */
            margin-bottom: -1px; /* Overlap border */
            transition: all 0.2s ease;
            padding: 0.75rem 1.25rem; /* Adjust padding */
        }
        .nav-tabs .nav-link:hover {
            color: var(--accent-hover); /* Hover text color */
            border-color: var(--border-color) var(--border-color) var(--primary-bg); /* Border on hover */
            background-color: var(--secondary-bg); /* Slightly darker background on hover */
        }
        .nav-tabs .nav-link.active {
            color: var(--accent-color); /* Active tab text color */
            background-color: var(--card-bg); /* Active tab background matches card */
            border-color: var(--border-color) var(--border-color) var(--card-bg); /* Active tab border */
            font-weight: 600;
        }
        .tab-content {
            background-color: var(--card-bg); /* Content background matches card */
            border: 1px solid var(--border-color);
            border-top: none; /* No top border, tabs handle that */
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.3);
        }

        /* --- Bottom Navigation for Mobile (from settings_control.php) --- */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--secondary-bg); /* Dark background for nav */
            display: flex;
            justify-content: space-around;
            padding: 10px 0;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.5); /* Stronger shadow */
            z-index: 1000;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--text-secondary); /* Light grey text */
            font-size: 0.75rem;
            padding: 5px 0;
            width: 25%; /* Distribute space evenly */
        }
        .nav-item.active {
            color: var(--accent-color); /* Highlight active item with accent color */
            font-weight: 600;
        }
        .nav-icon {
            font-size: 1.4rem;
            margin-bottom: 4px;
        }
        .nav-item:hover {
            color: var(--accent-hover);
        }


        /* --- Responsive Adjustments --- */
        /* Hide bottom nav on larger screens */
        @media (min-width: 768px) {
            .bottom-nav {
                display: none;
            }
            body { padding-bottom: 0; } /* Remove extra padding for non-mobile */
            aside.hidden { /* Override hidden for larger screens if toggle was used */
                display: block !important;
            }
            .settings-header { justify-content: space-between !important; }
            .tab-content {
                 border-top-left-radius: 0; /* Align with first tab */
                 border-top-right-radius: 0; /* Align with last tab */
            }
        }

        /* Mobile specific styles */
        @media (max-width: 768px) {
            aside {
                position: fixed;
                top: 0;
                left: 0;
                height: 100%;
                width: 250px;
                transform: translateX(-100%);
                transition: transform 0.3s ease-out;
                z-index: 1000;
            }
            aside.is-open {
                transform: translateX(0);
            }
            aside.hidden { /* Initial state for mobile */
                display: none;
            }
            main {
                padding: 1rem;
                margin-top: 4rem; /* Space for header if it's not fixed */
            }
            .settings-header {
                justify-content: start !important;
                gap: 1rem;
                padding-bottom: 1rem;
                border-bottom: 1px solid var(--border-color);
            }
            .main-content-wrapper {
                margin: 0; /* Remove auto margin for small screens */
            }
            .telegram-settings-card, .card-base {
                padding: 1.5rem;
            }
            .telegram-group-item {
                padding: 1rem;
            }
            .telegram-section-title {
                font-size: 1.3rem;
            }
            h1 { font-size: 2.5rem !important; }
            h2 { font-size: 2rem !important; }
            .btn {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
            }
            .btn-danger, .btn-success {
                padding: 0.4rem 0.8rem;
            }
            /* Adjust tabs for smaller screens */
            .nav-tabs .nav-link {
                padding: 0.5rem 0.75rem;
                font-size: 0.9rem;
            }
        }
        @media (max-width: 576px) {
            h1 { font-size: 2rem !important; }
            h2 { font-size: 1.8rem !important; }
            .telegram-section-title { font-size: 1.2rem; }
            .nav-item { font-size: 0.65rem; }
            .nav-icon { font-size: 1.2rem; }
        }

        /* Modal styling adaptation */
        .modal-content {
            background-color: var(--card-bg) !important;
            border: 1px solid var(--border-color) !important;
        }
        .modal-header {
            background-color: var(--primary-bg) !important;
            border-bottom: 1px solid var(--border-color) !important;
        }
        .modal-title {
            color: var(--accent-hover) !important;
        }
        .modal-body {
            color: var(--text-primary);
        }
        .modal-footer {
            border-top: 1px solid var(--border-color) !important;
        }
        .modal .btn-close {
             filter: invert(1) grayscale(100%) brightness(150%) !important; /* White X icon */
        }
    </style>
</head>
<body>

    <?php
    // Include the sidebar only if the file exists
    if ($sidebar_exists) {
        // You might need to mock or define $pendingRequestsCount if used in sidebar.php
        $pendingRequestsCount = 0;
        include $sidebar_path;
    }
    ?>

    <main class="flex-1 overflow-y-auto">
        <header class="mb-8">
            <div class="flex settings-header justify-between items-center mb-4">
                <div class="flex items-center gap-5">
                    <?php if ($sidebar_exists): ?>
                    <button id="menu-toggle" class="md:hidden text-accent-hover text-3xl focus:outline-none hover:text-accent-color transition-colors">
                        <i class="fas fa-bars"></i>
                    </button>
                    <?php endif; ?>
                    <div>
                        <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight text-accent-hover drop-shadow-sm">
                             <i class="fas fa-cogs mr-2 text-accent-color"></i> áá¶áááááááá¶ááá¢ááá
                        </h1>
                        <p class="text-text-secondary mt-2 text-lg font-medium italic">
                            Telegram Bot, ááááá, áá»áááááá, áá¶áá¶
                        </p>
                    </div>
                </div>
                <a href="dashboard.php" class="btn-base btn-primary text-sm hidden sm:flex">
                     <i class="fas fa-arrow-left"></i>áááá¡áááááááá¶ááááááááááá
                </a>
            </div>
        </header>

        <div class="main-content-wrapper">
            <?php if ($error_message): // For initial DB connection/load error ?>
                <div class="alert alert-danger animate__animated animate__fadeInDown" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($session_error_message): // For POST request errors ?>
                <div class="alert alert-danger animate__animated animate__fadeInDown" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($session_error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($session_success_message): // For POST request success messages ?>
                <div class="alert alert-success animate__animated animate__fadeInDown" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($session_success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): // For GET redirect success (from settings_control.php original) ?>
                <div class="alert alert-success animate__animated animate__fadeInDown" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>

            <!-- Tabs Navigation -->
            <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="telegram-tab" data-bs-toggle="tab" data-bs-target="#telegramSettings" type="button" role="tab" aria-controls="telegramSettings" aria-selected="true">
                        <i class="fab fa-telegram-plane me-2"></i>áá¶áááááá Telegram Bot
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categoriesControl" type="button" role="tab" aria-controls="categoriesControl" aria-selected="false">
                        <i class="fas fa-sitemap me-2"></i>ááááá, áá»áááááá, áá¶áá¶
                    </button>
                </li>
            </ul>

            <!-- Tabs Content -->
            <div class="tab-content" id="settingsTabsContent">
                <!-- ================================================================================= -->
                <!-- TELEGRAM SETTINGS SECTION - Tab Pane -->
                <!-- ================================================================================= -->
                <div class="tab-pane fade show active" id="telegramSettings" role="tabpanel" aria-labelledby="telegram-tab">
                    <!-- Bot Token Settings Block -->
                    <div class="telegram-settings-card animate__animated animate__fadeInUp">
                        <h4 class="telegram-section-title"><i class="fab fa-telegram-plane me-2"></i> Telegram Bot Token</h4>
                        <form method="POST" action="settings_control.php">
                            <input type="hidden" name="action" value="update_bot_token">
                            <div class="mb-4">
                                <label for="botTokenInput" class="form-label">Bot Token:</label>
                                <input type="text" class="form-control" id="botTokenInput" name="bot_token" value="<?php echo htmlspecialchars($telegramBotToken); ?>" required>
                                <small class="form-text text-text-secondary mt-2 d-block">
                                    áá·ááááá¶áá Telegram Bot Token ááááá¢ááááááá¸áááá áá¶áá¶ááááááá¶áááááá¾áá¶áá
                                </small>
                            </div>
                            <button type="submit" class="btn-base btn-primary w-100"><i class="fas fa-save me-2"></i> ááááá¶áá»á Bot Token</button>
                        </form>
                    </div>

                    <!-- Existing Telegram Groups Block -->
                    <div class="telegram-settings-card animate__animated animate__fadeInUp">
                        <h4 class="telegram-section-title"><i class="fas fa-users-cog me-2"></i> áááá»á Telegram ááááá¶ááááá¶áá</h4>
                        <?php if (empty($telegramGroups)): ?>
                            <div class="alert alert-info text-center py-4">
                                <i class="fas fa-info-circle me-2"></i> áá·ááá¶áááá¶ááááá»á Telegram áá¶áá½ááááá¼ááá¶áááááááááá¶ááááááááááá¡á¾áááá
                                <p class="mt-2 mb-0">áá¼ááááá¾áááááááá¶áááááááá¾áááá¸áááááááááá»ááááá¸á</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($telegramGroups as $group): ?>
                                <div class="telegram-group-item mb-4" id="group-<?php echo htmlspecialchars($group['group_id']); ?>">
                                    <!-- Group details form -->
                                    <form method="POST" action="settings_control.php" class="mb-4">
                                        <input type="hidden" name="action" value="update_group">
                                        <input type="hidden" name="group_id" value="<?php echo htmlspecialchars($group['group_id']); ?>">

                                        <div class="row g-3 mb-3">
                                            <div class="col-md-6">
                                                <label for="groupName_<?php echo htmlspecialchars($group['group_id']); ?>" class="form-label">ááááááááá»á:</label>
                                                <input type="text" class="form-control" id="groupName_<?php echo htmlspecialchars($group['group_id']); ?>" name="group_name" value="<?php echo htmlspecialchars($group['name']); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="chatId_<?php echo htmlspecialchars($group['group_id']); ?>" class="form-label">Chat ID:</label>
                                                <input type="text" class="form-control" id="chatId_<?php echo htmlspecialchars($group['group_id']); ?>" name="chat_id" value="<?php echo htmlspecialchars($group['chat_id']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <label for="reportFormat_<?php echo htmlspecialchars($group['group_id']); ?>" class="form-label">ááááááááá¶ááá¶ááá:</label>
                                            <select class="form-select" id="reportFormat_<?php echo htmlspecialchars($group['group_id']); ?>" name="report_format" required>
                                                <option value="link" <?php echo ($group['report_format'] === 'link') ? 'selected' : ''; ?>>áááá á¶ááá Link</option>
                                                <option value="full" <?php echo ($group['report_format'] === 'full') ? 'selected' : ''; ?>>áááá á¶ááá¶áá·áá¶ááá</option>
                                            </select>
                                        </div>

                                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 mt-4 pt-3 border-top border-telegram-border-color">
                                            <button type="submit" class="btn-base btn-primary flex-grow-1"><i class="fas fa-save me-2"></i> ááááá¶áá»ááá¶ááááá¶áááááá¼ááááá»á</button>
                                            <button type="button" class="btn-base btn-danger flex-grow-1" onclick="confirmDeleteGroup('<?php echo htmlspecialchars($group['group_id']); ?>')"><i class="fas fa-trash-alt me-2"></i> áá»ááááá»á</button>
                                        </div>
                                    </form>

                                    <div class="sub-block mt-4 pt-3">
                                        <h5><i class="fas fa-thumbtack me-2"></i>áá¶áááááá Thread IDs (áá½áá¶áá¸) ááááá¶áááááá»áááá:</h5>
                                        <?php if (empty($group['thread_ids'])): ?>
                                            <p class="text-text-secondary text-center py-2 opacity-75">áá·ááá¶áááá¶ááá½áá¶áá¸áááá¼ááá¶ááááááááááá¶áááááá»ááááááá¡á¾áááá</p>
                                        <?php else: ?>
                                            <div class="table-responsive mb-3">
                                                <table class="table table-hover thread-ids-table">
                                                    <thead>
                                                        <tr>
                                                            <th class="text-white" style="width: 35%;">áá½áá¶áá¸</th>
                                                            <th class="text-white" style="width: 35%;">áááááá</th>
                                                            <th class="text-white" style="width: 15%;">Thread ID</th>
                                                            <th class="text-white" style="width: 15%;">ááááááá¶á</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($group['thread_ids'] as $thread_item): ?>
                                                            <tr>
                                                                <form method="POST" action="settings_control.php" class="d-inline">
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
                                                                            <?php foreach ($telegramAllCategories as $cat): ?>
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
                                                                        <button type="submit" class="btn-base btn-primary btn-sm me-1" title="ááááá¶áá»á Thread">
                                                                            <i class="fas fa-save"></i>
                                                                        </button>
                                                                </form>
                                                                <form method="POST" action="settings_control.php" class="d-inline" onsubmit="return confirm('áá¾á¢ááááá·ááá¶ááááá»ááá½áá¶áá¸áááááááá?');">
                                                                    <input type="hidden" name="action" value="delete_thread">
                                                                    <input type="hidden" name="thread_map_id" value="<?php echo htmlspecialchars($thread_item['thread_map_id']); ?>">
                                                                        <button type="submit" class="btn-base btn-danger btn-sm" title="áá»á Thread">
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
                                        <h5 class="mt-4"><i class="fas fa-plus-circle me-2"></i>áááááááá½áá¶áá¸áááá¸áááááá»áááá:</h5>
                                        <form method="POST" action="settings_control.php">
                                            <input type="hidden" name="action" value="add_thread_to_group">
                                            <input type="hidden" name="group_id" value="<?php echo htmlspecialchars($group['group_id']); ?>">
                                            <div class="row g-2 align-items-end">
                                                <div class="col-md-4">
                                                    <label for="newPositionName_<?php echo htmlspecialchars($group['group_id']); ?>" class="form-label">áá½áá¶áá¸:</label>
                                                    <input type="text" class="form-control form-control-sm" id="newPositionName_<?php echo htmlspecialchars($group['group_id']); ?>" name="new_position_name" placeholder="ááááááá½áá¶áá¸" required>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="newPositionCategory_<?php echo htmlspecialchars($group['group_id']); ?>" class="form-label">áááááá:</label>
                                                    <select class="form-select form-control-sm" id="newPositionCategory_<?php echo htmlspecialchars($group['group_id']); ?>" name="new_position_category" required>
                                                        <?php foreach ($telegramAllCategories as $cat): ?>
                                                            <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <label for="newPositionThreadId_<?php echo htmlspecialchars($group['group_id']); ?>" class="form-label">Thread ID:</label>
                                                    <input type="number" class="form-control form-control-sm" id="newPositionThreadId_<?php echo htmlspecialchars($group['group_id']); ?>" name="new_position_thread_id" placeholder="ID" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="submit" class="btn-base btn-success btn-sm w-100"><i class="fas fa-plus me-2"></i> áááááá</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div> <!-- End sub-block -->
                                </div> <!-- End group-item -->
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div> <!-- End settings-card for existing groups -->

                    <!-- Add New Telegram Group Block -->
                    <div class="telegram-settings-card animate__animated animate__fadeInUp">
                        <h4 class="telegram-section-title"><i class="fas fa-plus-square me-2"></i> áááááááááá»á Telegram áááá¸</h4>
                        <form method="POST" action="settings_control.php">
                            <input type="hidden" name="action" value="add_group">
                            <div class="mb-3">
                                <label for="newGroupName" class="form-label">ááááááááá»á:</label>
                                <input type="text" class="form-control" id="newGroupName" name="new_group_name" placeholder="á§áá¶á ááá: ááá¶ááá¶áááááááá IT" required>
                            </div>
                            <div class="mb-3">
                                <label for="newGroupChatId" class="form-label">Chat ID:</label>
                                <input type="text" class="form-control" id="newGroupChatId" name="new_group_chat_id" placeholder="á§áá¶á ááá: -1001234567890" required>
                            </div>
                            <div class="mb-4">
                                <label for="newGroupReportFormat" class="form-label">ááááááááá¶ááá¶ááá:</label>
                                <select class="form-select" id="newGroupReportFormat" name="new_group_report_format" required>
                                    <option value="link">áááá á¶ááá Link</option>
                                    <option value="full">áááá á¶ááá¶áá·áá¶ááá</option>
                                </select>
                            </div>
                            <button type="submit" class="btn-base btn-primary w-100"><i class="fas fa-plus-circle me-2"></i> áááááááááá»á</button>
                        </form>
                    </div>
                </div> <!-- End telegramSettings tab-pane -->

                <!-- ================================================================================= -->
                <!-- CATEGORIES CONTROL SECTION - Tab Pane -->
                <!-- ================================================================================= -->
                <div class="tab-pane fade" id="categoriesControl" role="tabpanel" aria-labelledby="categories-tab">
                    <div class="card-base mb-8 shadow-lg">
                        <div class="mb-4">
                            <h5 class="text-xl font-bold text-accent-hover"><i class="fas fa-plus-circle me-2"></i>ááááááááááá¾ááááá¸</h5>
                        </div>
                        <form method="POST" action="settings_control.php" class="row g-3">
                            <input type="hidden" name="action" value="add">
                            <div class="col-md-4 mb-3">
                                <label for="category_type" class="form-label">áááá¾ááá¾ááááááá:</label>
                                <select id="category_type" name="category_type" class="form-select" required>
                                    <option value="" class="bg-secondary">-- áááá¾ááá¾á --</option>
                                    <?php foreach ($category_types as $type): ?>
                                        <option value="<?php echo $type; ?>" class="bg-secondary"><?php echo htmlspecialchars(ucfirst($khmer_names[$type]) . " (" . ucfirst($type) . ")"); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5 mb-3">
                                <label for="category_name" class="form-label">áááááááááá¾á (ááááá/á¢ááááááá):</label>
                                <input type="text" id="category_name" name="category_name" class="form-control" placeholder="ááááá¼áááááááááá¸" required>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn-base btn-primary w-full"><i class="fas fa-save me-1"></i>ááááá¶áá»á</button>
                            </div>
                        </form>
                    </div>

                    <div class="row">
                        <?php foreach ($category_types as $type): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card-base h-full p-3">
                                <div class="mb-3 border-b border-border-color pb-2">
                                    <h5 class="text-lg font-bold text-accent-color text-capitalize">
                                        <?php echo htmlspecialchars($khmer_names[$type]) . " (" . ucfirst($type) . ")"; ?>
                                    </h5>
                                </div>
                                <ul class="category-list">
                                    <?php if (isset($dynamic_categories[$type]) && !empty($dynamic_categories[$type])): ?>
                                        <?php foreach ($dynamic_categories[$type] as $category): ?>
                                            <li class="category-item"
                                                data-id="<?php echo (int)$category['id']; ?>"
                                                data-type="<?php echo htmlspecialchars($category['type']); ?>"
                                                data-name="<?php echo htmlspecialchars($category['name']); ?>">

                                                <span class="category-name"><?php echo htmlspecialchars($category['name']); ?></span>

                                                <div class="item-actions">
                                                    <button type="button" class="edit-btn" title="áááááá" onclick="openEditModal(this)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>

                                                    <form method="POST" action="settings_control.php" onsubmit="return confirm('áá¾á¢ááááá·ááá¶ááááá»áááááá¾á Â«<?php echo htmlspecialchars($category['name']); ?>Â» áááááááá?');" class="d-inline">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="category_id" value="<?php echo (int)$category['id']; ?>">
                                                        <button type="submit" class="delete-btn" title="áá»á"><i class="fas fa-times-circle"></i></button>
                                                    </form>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="p-3 text-text-secondary text-center opacity-75">áá·ááá¶áááá¶áááááá¾áááá</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div> <!-- End categoriesControl tab-pane -->
            </div> <!-- End tab-content -->


            <div class="text-center mt-4 hidden sm:hidden md:block">
                <a href="dashboard.php" class="btn-base btn-primary">
                    <i class="fas fa-arrow-left me-2"></i>áááá¡áááááááá¶ááááááááááá
                </a>
            </div>

        </div> <!-- End main-content-wrapper -->
    </main>

    <!-- Bottom Navigation Bar for Mobile (from settings_control.php) -->
    <nav class="bottom-nav md:hidden">
        <a href="../homes.php" class="nav-item">
            <i class="fas fa-home nav-icon"></i>
            <span>ááááááá¾á</span>
        </a>
        <a href="../reports/daily_reports.php" class="nav-item">
            <i class="fas fa-edit nav-icon"></i>
            <span>ááá¶ááá¶ááá</span>
        </a>
        <a href="list_reports.php" class="nav-item">
            <i class="fas fa-list-alt nav-icon"></i>
            <span>ááááá¸</span>
        </a>
        <a href="settings_control.php" class="nav-item active">
            <i class="fas fa-cog nav-icon"></i>
            <span>ááááá</span>
        </a>
    </nav>

    <!-- Edit Modal for Categories Control -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST" action="settings_control.php">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="category_id" id="edit-category-id">

            <div class="modal-header">
              <h5 class="modal-title" id="editModalLabel"><i class="fas fa-edit me-2"></i>ááááááááááá¾á</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label for="edit-category-type" class="form-label">áááááá (Category Type)</label>
                <select id="edit-category-type" name="new_category_type" class="form-select" required>
                    <?php foreach ($category_types as $type): ?>
                        <option value="<?php echo $type; ?>"><?php echo htmlspecialchars(ucfirst($khmer_names[$type]) . " (" . ucfirst($type) . ")"); ?></option>
                    <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label for="edit-category-name" class="form-label">ááááááááá¸ (New Name)</label>
                <input type="text" id="edit-category-name" name="new_category_name" class="form-control" required>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn-base bg-secondary text-text-secondary" data-bs-dismiss="modal">áááááá</button>
              <button type="submit" class="btn-base btn-primary"><i class="fas fa-save me-1"></i>ááááá¶áá»ááá¶ááááááá</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Bootstrap JS (used by both, ensure it's loaded once) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Mobile Menu Toggle Logic (from settings_control.php)
            const menuToggle = document.getElementById('menu-toggle');
            const sidebar = document.querySelector('aside');
            if (menuToggle && sidebar) {
                menuToggle.addEventListener('click', () => {
                    sidebar.classList.toggle('is-open');
                    // For the initial state on mobile, sidebar is 'hidden' by default
                    // We remove 'hidden' when 'is-open'
                    if (sidebar.classList.contains('is-open')) {
                        sidebar.classList.remove('hidden');
                    } else {
                        // Optionally hide it again if desired, but 'is-open' CSS handles transform
                        // sidebar.classList.add('hidden');
                    }
                });

                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', (event) => {
                    if (window.innerWidth <= 768 && sidebar.classList.contains('is-open') && !sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                        sidebar.classList.remove('is-open');
                        // sidebar.classList.add('hidden'); // Optional: re-hide visually
                    }
                });
            }

            // Function to confirm Telegram group deletion (from settings_control.php)
            window.confirmDeleteGroup = function(groupId) {
                if (confirm('áá¾á¢ááááá·ááá¶ááááá»ááááá»á Telegram áááááááá? ááááááá¶áááááá·áá¢á¶ááááá¡áááá·ááá¶áááá áá¶áá¹ááá»ááá½áá¶áá¸áá¶ááá¢ááááááá¶ááááááááááá¶áááááá»áááááááááá')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'settings_control.php';

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

            // Function to open Edit Modal for Categories (from settings_control.php)
            const editModalElement = document.getElementById('editModal');
            const editModal = new bootstrap.Modal(editModalElement);

            window.openEditModal = function(button) {
                const listItem = button.closest('.category-item');
                const id = listItem.dataset.id;
                const type = listItem.dataset.type;
                const name = listItem.dataset.name;

                document.getElementById('edit-category-id').value = id;
                document.getElementById('edit-category-name').value = name;
                document.getElementById('edit-category-type').value = type;

                editModal.show();
            };
        });
    </script>
</body>
</html>
