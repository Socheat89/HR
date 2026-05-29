<?php
session_start();

// Determine current page for active navigation
$current_page = basename($_SERVER['PHP_SELF']);

// Load Theme Config
$themeConfigPath = __DIR__ . '/../admin/includes/theme_config.json';
$currentTheme = 'default';
$customImage = '';
if (file_exists($themeConfigPath)) {
    $configData = json_decode(file_get_contents($themeConfigPath), true);
    $currentTheme = $configData['theme'] ?? 'default';
    $customImage = $configData['custom_image'] ?? '';
}

// Default Background Images for each theme
$themeBackgrounds = [   
    'kny'  => 'https://i.ibb.co/RKMS4tb/khmer-new-year-bg-1770518313913.jpg',
    'pb'   => 'https://i.ibb.co/S4dYb35p/khmer-new-year-bg-1770518389358.jpg',
    'cny'  => 'https://i.ibb.co/4462998/khmer-new-year-bg-1770518448823.jpg',
    'wf'   => 'https://i.ibb.co/2611144/khmer-new-year-bg-1770518505378.jpg',
    'kb'   => 'https://images.unsplash.com/photo-1596701062351-be5f6a200a45?q=80&w=1600',
    'indy' => 'https://images.unsplash.com/photo-1629813289069-7c8704204d60?q=80&w=1600'
];

// Determine which image to use
$bgImage = !empty($customImage) ? $customImage : ($themeBackgrounds[$currentTheme] ?? '');


// Set time zone
date_default_timezone_set('Asia/Phnom_Penh');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'សូមចូលគណនីសិន!';
    header("Location: ../auth/login.php");
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

// Include database configuration
require_once '../system/config.php';

// Database connection
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $_SESSION['error'] = "ការតភ្ជាប់មូលដ្ឋានទិន្នន័យបរាជ័យ: " . $e->getMessage();
    header("Location: ../reports/daily_reports.php");
    exit();
}

// --- AJAX Action: Get Latest Report ---
if (isset($_GET['action']) && $_GET['action'] === 'get_latest_report') {
    header('Content-Type: application/json');
    try {
        $stmt = $db->prepare("SELECT content FROM daily_reports WHERE user_id = :user_id ORDER BY report_date DESC LIMIT 1");
        $stmt->execute(['user_id' => $_SESSION['user_id']]);
        $latestReport = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'content' => $latestReport['content'] ?? '']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Fetch user data
$user = ['full_name' => '', 'email' => '', 'position' => '']; 
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $db->prepare("SELECT full_name, email, position FROM users WHERE id = :user_id");
        $stmt->execute(['user_id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $_SESSION['error'] = 'គណនីមិនត្រឹមត្រូវ';
            header("Location: ../auth/login.php");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
    }
}

// --- START OF DYNAMIC CONFIGURATION & DATA LOADING FROM DB ---

// Fetch the bot token
$telegramBotToken = '';
try {
    $stmt = $db->query("SELECT bot_token FROM telegram_settings WHERE id = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $telegramBotToken = $result['bot_token'] ?? '';
} catch (PDOException $e) {
    error_log("Failed to load Telegram bot token from DB: " . $e->getMessage());
    $_SESSION['error'] = 'កំហុសក្នុងការផ្ទុក Bot Token ពីមូលដ្ឋានទិន្នន័យ។';
    // Continue execution but Telegram sending will be skipped if token is empty.
}


// Fetch Telegram groups and their threads from the database
$telegramGroups = []; // This will be the array matching the structure daily_reports expects
try {
    $groupStmt = $db->query("SELECT group_id, name, chat_id, report_format FROM telegram_groups ORDER BY group_id ASC");
    while ($groupData = $groupStmt->fetch(PDO::FETCH_ASSOC)) {
        $transformedThreadIds = [];
        // Fetch category as well, though it's not directly used for sending here,
        // it's good to keep track if the structure becomes more complex.
        $threadStmt = $db->prepare("SELECT position, category, thread_id FROM telegram_group_threads WHERE group_id = :group_id ORDER BY category ASC, position ASC");
        $threadStmt->execute(['group_id' => $groupData['group_id']]);
        while ($threadItem = $threadStmt->fetch(PDO::FETCH_ASSOC)) {
            // Transform to associative array for easy lookup by position name
            $transformedThreadIds[$threadItem['position']] = (int) $threadItem['thread_id'];
        }

        $telegramGroups[] = [
            'chat_id' => $groupData['chat_id'],
            'report_format' => $groupData['report_format'],
            'thread_ids' => $transformedThreadIds // This structure matches the original array format
        ];
    }
} catch (PDOException $e) {
    error_log("Failed to load Telegram groups from DB: " . $e->getMessage());
    $_SESSION['error'] = 'កំហុសក្នុងការផ្ទុកក្រុម Telegram ពីមូលដ្ឋានទិន្នន័យ។';
    // Continue execution but Telegram sending will be skipped if groups are empty.
}

// Fetch all positions WITH their categories for the dropdown
$categorizedPositions = [];
try {
    // Select DISTINCT position and category, order by category and then position
    $stmt = $db->query("SELECT DISTINCT position, category FROM telegram_group_threads ORDER BY category ASC, position ASC");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        $category = $row['category'] ?? 'ផ្សេងៗ'; // Use category from DB, default to 'ផ្សេងៗ' if category is null/empty
        if (!isset($categorizedPositions[$category])) {
            $categorizedPositions[$category] = [];
        }
        $categorizedPositions[$category][] = $row['position'];
    }

    // --- Apply Custom Category Order for Display ---
    // This defines the specific order in which optgroups will appear.
    $customCategoryOrder = [
        'ផ្នែកការិយាល័យ',
        'ហាង និងផ្នែកលក់ (318)',
        'ហាង SK Cosmetics(ឈូកមាស)',
        'ហាង SK Cosmetics(ផ្លូវជាតិលេខ៣)',
        'គ្រប់គ្រងស្តុក និងឃ្លាំង',
        'ផ្សេងៗ', // Ensure 'ផ្សេងៗ' is last
    ];

    $orderedCategorizedPositions = [];
    foreach ($customCategoryOrder as $catName) {
        if (isset($categorizedPositions[$catName])) {
            // Positions within each category are already sorted by the SQL query (ORDER BY position ASC)
            $orderedCategorizedPositions[$catName] = $categorizedPositions[$catName];
        }
    }
    // Handle any categories from DB that are NOT in the custom order,
    // merge them into 'ផ្សេងៗ' or add them at the end. For simplicity, merge to 'ផ្សេងៗ'.
    foreach ($categorizedPositions as $catName => $positions) {
        if (!in_array($catName, $customCategoryOrder) && !empty($positions)) {
            if (!isset($orderedCategorizedPositions['ផ្សេងៗ'])) {
                $orderedCategorizedPositions['ផ្សេងៗ'] = [];
            }
            $orderedCategorizedPositions['ផ្សេងៗ'] = array_merge($orderedCategorizedPositions['ផ្សេងៗ'], $positions);
            sort($orderedCategorizedPositions['ផ្សេងៗ']); // Re-sort positions within 'ផ្សេងៗ' category alphabetically
        }
    }
    // Remove any categories that ended up empty after merging or filtering, except potentially 'ផ្សេងៗ'
    foreach ($orderedCategorizedPositions as $catName => $positions) {
        if (empty($positions) && $catName !== 'ផ្សេងៗ') { // Keep 'ផ្សេងៗ' even if empty as a fallback option
            unset($orderedCategorizedPositions[$catName]);
        }
    }
    $categorizedPositions = $orderedCategorizedPositions; // Assign the final ordered and structured array

    // --- Filter categorizedPositions to only the user's position ---
    if (!empty($user['position'])) {
        $found = false;
        foreach ($categorizedPositions as $cat => $positions) {
            if (in_array($user['position'], $positions)) {
                $categorizedPositions = [$cat => [$user['position']]];
                $found = true;
                break;
            }
        }
        // If position is not in any group, just show it as 'តួនាទីរបស់អ្នក' or similar
        if (!$found) {
            $categorizedPositions = ['តួនាទីរបស់អ្នក' => [$user['position']]];
        }
    }

} catch (PDOException $e) {
    error_log("Failed to load positions for dropdown: " . $e->getMessage());
    $_SESSION['error'] = 'កំហុសក្នុងការផ្ទុកបញ្ជីតួនាទីពីមូលដ្ឋានទិន្នន័យ។';
    // The dropdown will just be empty or show default option.
}


// --- END OF DYNAMIC CONFIGURATION & DATA LOADING FROM DB ---


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = filter_var($_POST['Email'], FILTER_SANITIZE_EMAIL);
        $name = filter_var($_POST['Name'], FILTER_SANITIZE_STRING);
        $position = filter_var($_POST['Position'], FILTER_SANITIZE_STRING);
        $report_date = filter_var($_POST['Date'], FILTER_SANITIZE_STRING);
        $content = filter_var($_POST['Content'], FILTER_SANITIZE_STRING);
        // $user_id = (int)$_SESSION['user_id']; // Uncomment if using user login

        // Validate inputs
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("អ៊ីមែលមិនត្រឹមត្រូវ");
        }
        if (empty($name) || empty($position) || empty($report_date) || empty($content)) {
            throw new Exception("សូមបំពេញគ្រប់វាល");
        }
        if (strlen($content) < 1) { // Changed from < 10 for more flexibility, adjust as needed
            throw new Exception("របាយការណ៍ត្រូវមានយ៉ាងតិច ១ តួអក្សរ");
        }

        // Validate and format date-time
        $dateObj = DateTime::createFromFormat('Y-m-d\TH:i', $report_date);
        if (!$dateObj || $dateObj->format('Y-m-d\TH:i') !== $report_date) {
            throw new Exception("ទម្រង់ថ្ងៃខែឆ្នាំ និងម៉ោងមិនត្រឹមត្រូវ");
        }
        $formattedDate = $dateObj->format('Y-m-d H:i:s'); // For database
        $telegramDate = $dateObj->format('d-m-Y H:i'); // For Telegram, e.g., 05-06-2025 16:52

        // Insert into database
        $stmt = $db->prepare("
            INSERT INTO daily_reports (user_id, email, name, position, report_date, content)
            VALUES (:user_id, :email, :name, :position, :report_date, :content)
        ");
        // If user_id is uncommented and used, add it here too:
        // INSERT INTO daily_reports (user_id, email, name, position, report_date, content)
        // Values: 'user_id' => $user_id, ...
        $stmt->execute([
            'user_id' => $_SESSION['user_id'], // Save the actual user ID instead of 0
            'email' => $email,
            'name' => $name,
            'position' => $position,
            'report_date' => $formattedDate,
            'content' => $content
        ]);

        // Get the inserted report ID for the link
        $report_id = $db->lastInsertId();
        $reportLink = "https://" . $_SERVER['HTTP_HOST'] . "/view_report.php?id=" . $report_id;

        // --- START OF TELEGRAM SENDING LOGIC ---
        $telegramHtml = function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        // Only attempt to send if a bot token is available and there are configured groups
        if (!empty($telegramBotToken) && !empty($telegramGroups)) {
            // Loop through each configured group and send a message
            foreach ($telegramGroups as $group) {
                // Prepare message header (common for both formats)
                $messageHeader = "📝 <b>របាយការណ៍ប្រចាំថ្ងៃ</b>\n"
                    . "ឈ្មោះ ៖ " . $telegramHtml($name) . "\n"
                    . "តួនាទី ៖ " . $telegramHtml($position) . "\n"
                    . "ថ្ងៃខែឆ្នាំ និងម៉ោង ៖ " . $telegramHtml($telegramDate) . "\n";
                if (!empty($email)) {
                    $messageHeader .= "អ៊ីមែល ៖ " . $telegramHtml($email) . "\n";
                }

                // Prepare the final message based on the group's report format
                $telegramMessage = '';
                if ($group['report_format'] === 'full') {
                    // Group with 'full' content format
                    $telegramMessage = $messageHeader
                        . "--------------------------------------\n"
                        . "<b>របាយការណ៍ពេលល្ងាច</b>:\n"
                        . $telegramHtml($content);
                } else {
                    // Group with 'link' format
                    $telegramMessage = $messageHeader;
                }

                // Get thread ID for this specific group based on position
                // Note: $position must exactly match a 'position' in telegram_group_threads for a thread_id to be found.
                $threadId = isset($group['thread_ids'][$position]) ? $group['thread_ids'][$position] : null;

                // Prepare Telegram data packet
                $telegramUrl = "https://api.telegram.org/bot$telegramBotToken/sendMessage";
                $telegramData = [
                    'chat_id' => $group['chat_id'],
                    'text' => $telegramMessage,
                    'parse_mode' => 'HTML',
                ];
                if ($threadId) {
                    $telegramData['message_thread_id'] = $threadId;
                }

                // --- Handle Photo Sensing if Image is provided ---
                if (!empty($_POST['image']) && $group['report_format'] === 'full') {
                    $imageData = explode(',', $_POST['image'])[1];
                    $decodedImage = base64_decode($imageData);
                    $tempPhotoPath = tempnam(sys_get_temp_dir(), 'report_') . '.png';
                    file_put_contents($tempPhotoPath, $decodedImage);

                    $photoPayload = [
                        'chat_id' => $group['chat_id'],
                        'photo' => new CURLFile($tempPhotoPath, 'image/png', 'report.png'),
                        'caption' => $telegramMessage,
                        'parse_mode' => 'HTML'
                    ];
                    if ($threadId) {
                        $photoPayload['message_thread_id'] = $threadId;
                    }

                    $ch = curl_init("https://api.telegram.org/bot$telegramBotToken/sendPhoto");
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $photoPayload);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $telegramResponse = curl_exec($ch);
                    curl_close($ch);
                    
                    if (file_exists($tempPhotoPath)) unlink($tempPhotoPath);
                } else {
                    // Send as text only
                    $ch = curl_init($telegramUrl);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($telegramData));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $telegramResponse = curl_exec($ch);
                    curl_close($ch);
                }

                // Check response for this specific group
                $telegramResult = json_decode($telegramResponse, true);
                if (!$telegramResult || !$telegramResult['ok']) {
                    // Log the error but continue trying to send to other groups.
                    // For immediate user feedback, you can aggregate errors or show the first one.
                    error_log("Telegram send error (Chat ID: {$group['chat_id']}): " . ($telegramResult['description'] ?? 'Unknown error') . " Message: " . $telegramMessage);
                    // Decide if you want to throw an exception here to stop all sending for the user,
                    // or just log and let other groups send. For this example, we'll throw to inform the user.
                    throw new Exception("កំហុសក្នុងការផ្ញើទៅ Telegram (Chat ID: {$group['chat_id']}): " . ($telegramResult['description'] ?? 'មិនស្គាល់'));
                }
            }
        } else {
            error_log("Telegram sending skipped: Bot token or groups not configured.");
            // Optionally, inform the user that Telegram notification was skipped but report was saved.
            // $_SESSION['info'] = "របាយការណ៍ត្រូវបានរក្សាទុក ប៉ុន្តែការជូនដំណឹង Telegram ត្រូវបានរំលង (ការកំណត់មិនពេញលេញ)។";
        }

        $_SESSION['success'] = "បានបញ្ជូនរបាយការណ៍ទៅកាន់គ្រប់ក្រុមដោយជោគជ័យ";
        header("Location: ../reports/daily_reports.php?success=1");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: ../reports/daily_reports.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>របាយការណ៍ប្រចាំថ្ងៃ - HR App</title>

    <!-- Frameworks & Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

    <!-- Favicon & Theme Color -->
    <link rel="icon" type="image/png" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
    <meta name="theme-color" content="#6366f1">

    <style>
        /* === SHARED DESIGN SYSTEM === */
        :root {
            --primary: #6366f1;
            --primary-light: #8b5cf6;
            --primary-dark: #4f46e5;
            --secondary: #06b6d4;
            --accent: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --dark: #0f172a;
            --light: #f8fafc;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-500: #64748b;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --border-radius-xl: 20px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Kantumruy Pro', sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
            color: var(--gray-800);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        /* Prevent horizontal scrolling */
        html,
        body {
            overflow-x: hidden;
            width: 100%;
        }

        .app-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 24px;
            padding-bottom: 90px;
        }

        /* === HEADER STYLES === */
        .app-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius-xl);
            padding: 16px 24px;
            margin-bottom: 32px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 16px;
            text-decoration: none;
        }

        .logo-img {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius);
            object-fit: cover;
            box-shadow: var(--shadow);
        }

        .app-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .back-btn {
            color: var(--gray-500);
            font-size: 1.1rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            transition: color 0.2s;
        }

        .back-btn:hover {
            color: var(--primary);
        }

        /* === FORM CARD === */
        .report-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 32px;
            box-shadow: var(--shadow-lg);
            animation: fadeInUp 0.5s ease-out;
        }

        /* Season/Festival Theme Overrides */
        <?php if ($currentTheme === 'kny'): ?>
        :root { --primary: #f59e0b; --primary-light: #fbbf24; --primary-dark: #d97706; --secondary: #ec4899; }
        .app-header { border-color: rgba(245, 158, 11, 0.3) !important; }
        .app-title { background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; }
        .back-btn { color: var(--primary-dark) !important; }
        
        /* Fireworks Overlay for KNY */
        body::after {
            content: "";
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('https://media.tenor.com/XesYJjyNYgAAAAAi/fireworks-putukan.gif');
            background-size: cover; background-repeat: no-repeat;
            pointer-events: none; z-index: -1; opacity: 0.35; mix-blend-mode: screen;
        }
        
        <?php elseif ($currentTheme === 'pb'): ?>
        :root { --primary: #ea580c; --primary-light: #fdba74; --primary-dark: #c2410c; --secondary: #4b5563; }
        .app-header { border-color: rgba(234, 88, 12, 0.3) !important; }
        .app-title { background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; }
        .back-btn { color: var(--primary-dark) !important; }

        <?php elseif ($currentTheme === 'cny'): ?>
        :root { --primary: #dc2626; --primary-light: #f87171; --primary-dark: #b91c1c; --secondary: #fbbf24; }
        .app-header { border-color: rgba(220, 38, 38, 0.3) !important; }
        .app-title { background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; }
        .back-btn { color: var(--primary-dark) !important; }

        <?php elseif ($currentTheme === 'wf'): ?>
        :root { --primary: #0284c7; --primary-light: #38bdf8; --primary-dark: #0369a1; --secondary: #0ea5e9; }
        .app-header { border-color: rgba(2, 132, 199, 0.3) !important; }
        .app-title { background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important; -webkit-background-clip: text !important; -webkit-text-fill-color: transparent !important; }
        .back-btn { color: var(--primary-dark) !important; }
        <?php endif; ?>

        /* Apply Theme Background Image */
        <?php if (!empty($bgImage)): ?>
        body {
            background-image: url('<?php echo $bgImage; ?>') !important;
            background-size: cover !important;
            background-position: center !important;
            background-attachment: fixed !important;
            background-repeat: no-repeat !important;
            animation: bgZoom 20s ease-in-out infinite alternate;
        }
        @keyframes bgZoom {
            from { background-size: 100% 100%; }
            to { background-size: 110% 110%; }
        }

        /* Overlay to ensure readability */
        body::before {
            content: "";
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(2px);
            z-index: -2;
        }
        <?php endif; ?>


        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--gray-900);
            display: block;
        }

        .form-control,
        .form-select {
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-300);
            padding: 0.75rem 1rem;
            transition: all 0.2s;
            font-size: 0.95rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
            outline: none;
        }

        optgroup {
            font-weight: bold;
            color: var(--primary);
            background-color: var(--gray-50);
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border: none;
            color: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
            box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.4);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.4);
        }

        .btn-submit:disabled {
            background: var(--gray-300);
            transform: none;
            box-shadow: none;
        }

        /* === BOTTOM NAV === */
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            justify-content: space-around;
            padding: 8px 0;
            box-shadow: 0 -2px 16px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            border-top-left-radius: var(--border-radius-lg);
            border-top-right-radius: var(--border-radius-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
            min-height: 64px;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: var(--gray-500);
            font-size: 0.75rem;
            font-weight: 600;
            flex: 1;
            padding: 6px;
        }

        .nav-item.active {
            color: var(--primary);
        }

        .nav-icon {
            font-size: 1.3rem;
            margin-bottom: 2px;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .app-container {
                padding: 12px;
                padding-bottom: 90px;
            }

            .app-header {
                padding: 12px 16px;
                margin-bottom: 20px;
            }

            .logo-img {
                width: 40px;
                height: 40px;
            }

            .app-title {
                font-size: 1.25rem;
            }

            .report-card {
                padding: 20px;
            }

            .bottom-nav {
                display: flex;
            }
        }

        /* Screenshot Mode Styles */
        .screenshot-mode {
            background: #ffffff !important;
            padding: 20px !important;
            border: none !important;
            box-shadow: none !important;
        }
        
        .screenshot-mode .app-header,
        .screenshot-mode .btn-submit,
        .screenshot-mode .bottom-nav,
        .screenshot-mode .alert,
        .screenshot-mode #pull-previous-report {
            display: none !important;
        }

        .screenshot-mode .report-card {
            background: #ffffff !important;
            box-shadow: none !important;
            border: none !important;
            padding: 25px !important; /* Added safe padding for the whole card */
        }

        /* Force EVERYTHING inside screenshot mode to have solid black text */
        .screenshot-mode *,
        .screenshot-mode .form-label,
        .screenshot-mode .form-control,
        .screenshot-mode .form-select,
        .screenshot-mode textarea,
        .screenshot-mode input {
            color: #000000 !important;
            -webkit-text-fill-color: #000000 !important;
            opacity: 1 !important;
            filter: none !important;
            text-shadow: none !important;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .screenshot-mode .form-label {
            font-weight: 800 !important;
            margin-bottom: 8px !important;
            font-size: 1.1rem !important;
        }

        /* Improvement: Hide actual inputs/selects in screenshot and show plain text for better rendering */
        .screenshot-mode .form-control,
        .screenshot-mode .form-select,
        .screenshot-mode textarea,
        .screenshot-mode input {
            display: none !important;
        }

        .screenshot-value {
            display: none;
            word-wrap: break-word;
            white-space: pre-wrap;
        }

        .screenshot-mode .screenshot-value {
            display: block !important;
            border: 1px solid #a3a3a3ff !important; /* Thinner and softer dark border */
            background: #ffffff !important;
            color: #000000 !important;
            font-weight: 700 !important;
            padding: 12px 15px !important;
            border-radius: 8px !important;
            font-size: 1.1rem !important;
            min-height: 50px;
            width: 100%;
        }

        /* Large display for report content */
        .screenshot-mode .screenshot-value.content-display {
            min-height: 200px;
        }

        /* Preview Modal Styles */
        #previewModal .modal-body {
            background: #f1f5f9;
            text-align: center;
        }
        #previewImage {
            max-width: 100%;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: 1px solid #ddd;
        }
    </style>
</head>

<body>

    <div class="app-container">

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success animate__animated animate__fadeInDown" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success'];
                unset($_SESSION['success']); ?>
            </div>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger animate__animated animate__fadeInDown" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $_SESSION['error'];
                unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Modern Header -->
        <header class="app-header animate__animated animate__fadeInDown">
            <a href="../homes.php" class="logo-container">
                <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="Logo" class="logo-img">
                <h1 class="app-title">របាយការណ៍ប្រចាំថ្ងៃ</h1>
            </a>
            <a href="../homes.php" class="back-btn d-none d-md-flex">
                <i class="fas fa-arrow-left"></i> ត្រឡប់ក្រោយ
            </a>
        </header>

        <div class="report-card">
            <form id="reportForm" method="POST" action="../reports/daily_reports.php">
                <input type="hidden" name="image" id="reportImage">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="Email" class="form-label">អ៊ីមែល</label>
                        <input class="form-control" type="email" id="Email" placeholder="example@email.com" name="Email"
                            value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly required />
                        <div class="screenshot-value" id="val-Email"></div>
                    </div>
                    <div class="col-md-6">
                        <label for="Name" class="form-label">ឈ្មោះ</label>
                        <input class="form-control" type="text" id="Name" placeholder="ឈ្មោះពេញ" name="Name"
                            value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" readonly required />
                        <div class="screenshot-value" id="val-Name"></div>
                    </div>
                </div>

                <div class="mt-3">
                    <label for="Positions" class="form-label">តួនាទី</label>
                    <select class="form-select" name="Position" id="Positions" required>
                        <?php if (empty($user['position'])): ?>
                            <option value="" disabled selected>-- សូមជ្រើសរើសតួនាទី --</option>
                        <?php endif; ?>
                        <?php
                        foreach ($categorizedPositions as $categoryName => $positions):
                            if (!empty($positions)):
                                ?>
                                <optgroup label="■ <?php echo htmlspecialchars($categoryName); ?>">
                                    <?php foreach ($positions as $pos): ?>
                                        <option value="<?php echo htmlspecialchars($pos); ?>" <?php echo ($pos === $user['position']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($pos); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php
                            endif;
                        endforeach;
                        ?>
                    </select>
                    <div class="screenshot-value" id="val-Positions"></div>
                </div>

                <div class="mt-3">
                    <label for="Date" class="form-label">ថ្ងៃខែឆ្នាំ និងម៉ោង</label>
                    <input class="form-control" type="datetime-local" id="Date" name="Date" 
                        value="<?php echo date('Y-m-d\TH:i'); ?>" required />
                    <div class="screenshot-value" id="val-Date"></div>
                </div>

                <div class="mt-3">
                    <label for="reportContent" class="form-label d-flex justify-content-between align-items-center">
                        <span>របាយការណ៍ប្រចាំថ្ងៃ</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="pull-previous-report" style="font-size: 0.8rem; border-radius: 20px;">
                            <i class="fas fa-history me-1"></i> របាយការណ៍ធ្វើពីម្សិលមិញ
                        </button>
                    </label>
                    <textarea class="form-control" id="reportContent" placeholder="សរសេររបាយការណ៍របស់អ្នកនៅទីនេះ..."
                        name="Content" rows="6" required></textarea>
                    <div class="screenshot-value content-display" id="val-reportContent"></div>
                </div>

                <div class="mt-4">
                    <button class="btn btn-submit" type="submit" id="submit-button">
                        <i class="fas fa-paper-plane me-2"></i> បញ្ជូនរបាយការណ៍
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="../homes.php" class="nav-item">
            <i class="fas fa-home nav-icon"></i>
            <span>ទំព័រដើម</span>
        </a>
        <a href="../requests/requests_menu.php" class="nav-item">
            <i class="fas fa-clipboard-list nav-icon"></i>
            <span>សំណើ</span>
        </a>
        <a href="../system/checklist.php" class="nav-item">
            <i class="fas fa-tasks nav-icon"></i>
            <span>ការងារ</span>
        </a>
        <a href="https://app.vvc.asia/admin/profile.php" class="nav-item">
            <i class="fas fa-user nav-icon"></i>
            <span>គណនី</span>
        </a>
    </nav>

    <!-- Loading Modal - Removed fade for reliability -->
    <div class="modal" id="loadingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: transparent; border: none;">
                <div class="modal-body text-center">
                    <div class="spinner-border text-light" role="status" style="width: 3.5rem; height: 3.5rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-white fw-bold fs-5">កំពុងថតរូបភាព... សូមរង់ចាំ</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-eye me-2"></i> ពិនិត្យមើលរូបភាពរបាយការណ៍បច្ចុប្បន្ន</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <img id="previewImage" src="" alt="Report Preview">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">កែប្រែឡើងវិញ</button>
                    <button type="button" class="btn btn-primary" id="confirmSendBtn">
                        <i class="fas fa-paper-plane me-2"></i> បញ្ជូនទៅ Telegram
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const reportForm = document.getElementById('reportForm');
            const submitButton = document.getElementById('submit-button');
            const loadingModalEl = document.getElementById('loadingModal');
            const loadingModal = new bootstrap.Modal(loadingModalEl);
            const textarea = document.getElementById('reportContent');
            const dateInput = document.getElementById('Date');

            // --- Auto-Save and Restore Textarea Content ---
            const savedContent = localStorage.getItem('reportContent');
            if (savedContent) {
                textarea.value = savedContent;
            } else {
                textarea.value = '- '; // Default starting bullet
            }

            textarea.addEventListener('input', () => {
                localStorage.setItem('reportContent', textarea.value);
            });

            // --- Auto-bulleting Logic ---
            textarea.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    const cursorPosition = this.selectionStart;
                    const textBefore = this.value.substring(0, cursorPosition);
                    const textAfter = this.value.substring(cursorPosition);
                    const lines = textBefore.split('\n');
                    const lastLine = lines[lines.length - 1];

                    if (lastLine.trim().startsWith('-')) {
                        if (lastLine.trim() !== '-' && lastLine.trim() !== '- ') {
                            e.preventDefault();
                            const bullet = lastLine.startsWith('- ') ? '\n- ' : '\n-';
                            this.value = textBefore + bullet + textAfter;
                            this.selectionStart = this.selectionEnd = cursorPosition + bullet.length;
                        } else {
                            e.preventDefault();
                            const newTextBefore = textBefore.substring(0, textBefore.length - lastLine.length);
                            this.value = newTextBefore + '\n' + textAfter;
                            this.selectionStart = this.selectionEnd = newTextBefore.length + 1;
                        }
                        localStorage.setItem('reportContent', this.value);
                    }
                }
            });

            // --- Set current date and time by default if not set by PHP ---
            if (!dateInput.value) {
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                dateInput.value = `${year}-${month}-${day}T${hours}:${minutes}`;
            }

            // --- Pull Previous Report Logic ---
            const pullPrevBtn = document.getElementById('pull-previous-report');
            if (pullPrevBtn) {
                pullPrevBtn.addEventListener('click', async function () {
                    const originalText = pullPrevBtn.innerHTML;
                    pullPrevBtn.disabled = true;
                    pullPrevBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> កំពុងទាញ...';

                    try {
                        const response = await fetch('?action=get_latest_report');
                        const result = await response.json();

                        if (result.success && result.content) {
                            if (textarea.value.trim() !== '' && !confirm('តើអ្នកចង់ជំនួសរបាយការណ៍បច្ចុប្បន្នដោយរបាយការណ៍ចាស់មែនទេ?')) {
                                return;
                            }
                            textarea.value = result.content;
                            // Trigger auto-save
                            localStorage.setItem('reportContent', result.content);
                            // Visual feedback
                            textarea.classList.add('animate__animated', 'animate__pulse');
                            setTimeout(() => textarea.classList.remove('animate__animated', 'animate__pulse'), 1000);
                        } else {
                            alert(result.content === '' ? 'មិនមានរបាយការណ៍ពីមុនដើម្បីទាញយកទេ។' : 'កំហុស៖ ' + (result.message || 'មិនអាចទាញទិន្នន័យបាន'));
                        }
                    } catch (error) {
                        console.error('Error fetching latest report:', error);
                        alert('មានបញ្ហាក្នុងការភ្ជាប់ទៅកាន់ម៉ាស៊ីនមេ។');
                    } finally {
                        pullPrevBtn.disabled = false;
                        pullPrevBtn.innerHTML = originalText;
                    }
                });
            }

            // --- Handle Form Submission with Preview ---
            const previewModalEl = document.getElementById('previewModal');
            const previewModal = new bootstrap.Modal(previewModalEl);
            const previewImage = document.getElementById('previewImage');
            const confirmSendBtn = document.getElementById('confirmSendBtn');
            let isCapturing = false;

            reportForm.addEventListener('submit', async function (event) {
                event.preventDefault();

                if (isCapturing || !this.checkValidity()) {
                    return;
                }

                isCapturing = true;
                loadingModal.show();
                
                // --- Preparing Screenshot Values for a Cleaner Look ---
                const ids = ['Email', 'Name', 'Positions', 'Date', 'reportContent'];
                ids.forEach(id => {
                    const inputEl = document.getElementById(id);
                    const displayEl = document.getElementById('val-' + id);
                    if (inputEl && displayEl) {
                        let val = inputEl.value;
                        if (id === 'Date') {
                            const d = new Date(val);
                            // Format: DD/MM/YYYY hh:mm AM/PM
                            const day = String(d.getDate()).padStart(2, '0');
                            const month = String(d.getMonth() + 1).padStart(2, '0');
                            const year = d.getFullYear();
                            let hours = d.getHours();
                            const minutes = String(d.getMinutes()).padStart(2, '0');
                            const ampm = hours >= 12 ? 'PM' : 'AM';
                            hours = hours % 12;
                            hours = hours ? hours : 12; // the hour '0' should be '12'
                            val = `${day}/${month}/${year} ${hours}:${minutes} ${ampm}`;
                        } else if (id === 'Positions' && inputEl.options) {
                            val = inputEl.options[inputEl.selectedIndex].text;
                        }
                        displayEl.innerText = val || '-';
                    }
                });

                // Capture Screenshot for Preview
                const container = document.querySelector('.report-card');
                const appContainer = document.querySelector('.app-container');
                appContainer.classList.add('screenshot-mode');

                try {
                    const canvas = await html2canvas(container, {
                        scale: 3,
                        useCORS: true,
                        backgroundColor: '#ffffff',
                        logging: false,
                        imageSmoothingEnabled: true
                    });
                    
                    const imgData = canvas.toDataURL('image/png', 1.0);
                    document.getElementById('reportImage').value = imgData;
                    previewImage.src = imgData;
                    
                    // Small delay to ensure browser processed the capture before switching modals
                    setTimeout(() => {
                        loadingModal.hide();
                        // Additional safety to remove static backdrop if stuck
                        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                        document.body.classList.remove('modal-open');
                        document.body.style.overflow = '';
                        document.body.style.paddingRight = '';
                        
                        // Show preview after loading is cleared
                        setTimeout(() => {
                            previewModal.show();
                        }, 100);
                    }, 500);

                } catch (error) {
                    console.error('Screenshot error:', error);
                    loadingModal.hide();
                    alert('មិនអាចថតរូបភាពបានទេ!');
                } finally {
                    appContainer.classList.remove('screenshot-mode');
                    isCapturing = false;
                }
            });

            // Actual submission when user confirms
            confirmSendBtn.addEventListener('click', function() {
                confirmSendBtn.disabled = true;
                confirmSendBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> កំពុងផ្ញើ...';
                
                localStorage.removeItem('reportContent');
                reportForm.submit();
            });

            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                localStorage.removeItem('reportContent');
                window.history.replaceState(null, null, window.location.pathname);
            }
        });
    </script>
</body>

</html>
