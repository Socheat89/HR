<?php
session_start();

// Set time zone
date_default_timezone_set('Asia/Phnom_Penh');

// // Check if user is logged in
// if (!isset($_SESSION['user_id'])) {
//     $_SESSION['error'] = 'សូមចូលគណនីសិន!';
//     header("Location: login.php");
//     exit();
// }

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Include database configuration
require_once 'config.php';

// Database connection
try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $_SESSION['error'] = "ការតភ្ជាប់មូលដ្ឋានទិន្នន័យបរាជ័យ: " . $e->getMessage();
    header("Location: daily_reports.php");
    exit();
}

// // Fetch user data (uncomment if you need user data, e.g., for pre-filling name)
// $user = ['name' => '']; // Default empty name if not logged in. Remove this line if uncommenting user login.
// if (isset($_SESSION['user_id'])) {
//     try {
//         $stmt = $db->prepare("SELECT name FROM users WHERE id = :user_id");
//         $stmt->execute(['user_id' => $_SESSION['user_id']]);
//         $user = $stmt->fetch(PDO::FETCH_ASSOC);
//         if (!$user) {
//             $_SESSION['error'] = 'គណនីមិនត្រឹមត្រូវ';
//             header("Location: login.php");
//             exit();
//         }
//     } catch (PDOException $e) {
//         $_SESSION['error'] = "កំហុសមូលដ្ឋានទិន្នន័យ: " . $e->getMessage();
//         header("Location: daily_reports.php");
//         exit();
//     }
// }

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
            $transformedThreadIds[$threadItem['position']] = (int)$threadItem['thread_id'];
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
            'user_id' => 0, // Default user_id for anonymous submissions
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
        // Only attempt to send if a bot token is available and there are configured groups
        if (!empty($telegramBotToken) && !empty($telegramGroups)) {
            // Loop through each configured group and send a message
            foreach ($telegramGroups as $group) {
                // Prepare message header (common for both formats)
                $messageHeader = "📝 *របាយការណ៍ប្រចាំថ្ងៃ*\n"
                               . "ឈ្មោះ ៖ " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "\n"
                               . "តួនាទី ៖ " . htmlspecialchars($position, ENT_QUOTES, 'UTF-8') . "\n"
                               . "ថ្ងៃខែឆ្នាំ និងម៉ោង ៖ " . htmlspecialchars($telegramDate, ENT_QUOTES, 'UTF-8') . "\n";
                if (!empty($email)) {
                    $messageHeader .= "អ៊ីមែល ៖ " . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "\n";
                }

                // Prepare the final message based on the group's report format
                $telegramMessage = '';
                if ($group['report_format'] === 'full') {
                    // Group with 'full' content format
                    $telegramMessage = $messageHeader
                                     . "--------------------------------------\n"
                                     . "*របាយការណ៍ពេលល្ងាច*:\n"
                                     . htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
                } else {
                    // Group with 'link' format
                    $telegramMessage = $messageHeader
                                     . "[ចុចទីនេះដើម្បីមើលរបាយការណ៍លម្អិត](" . $reportLink . ")";
                }

                // Get thread ID for this specific group based on position
                // Note: $position must exactly match a 'position' in telegram_group_threads for a thread_id to be found.
                $threadId = isset($group['thread_ids'][$position]) ? $group['thread_ids'][$position] : null;

                // Prepare Telegram data packet
                $telegramUrl = "https://api.telegram.org/bot$telegramBotToken/sendMessage";
                $telegramData = [
                    'chat_id' => $group['chat_id'],
                    'text' => $telegramMessage,
                    'parse_mode' => 'Markdown',
                ];
                if ($threadId) {
                    $telegramData['message_thread_id'] = $threadId;
                }

                // Send the message using cURL
                $ch = curl_init($telegramUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($telegramData));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $telegramResponse = curl_exec($ch);
                curl_close($ch);

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
        header("Location: daily_reports.php?success=1");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: daily_reports.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Report Form</title>
    
    <!-- Frameworks & Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <!-- Favicon & Theme Color for Mobile Browsers -->
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
        }

        /* Use a clean, modern font */
        body {
            font-family: 'Kantumruy Pro', sans-serif; /* A good Khmer font */
            background-color: var(--light-gray);
            color: var(--text-color);
            padding-bottom: 70px; /* For fixed bottom nav */
        }

        .main-container {
            max-width: 700px; /* Optimal width for a form */
            margin: 2rem auto;
            padding: 1rem;
        }

        .report-card {
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 2rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }
        
        .card-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .card-header .logo {
            width: 60px;
            height: 60px;
            margin-bottom: 0.5rem;
        }

        .card-header h2 {
            font-weight: 700;
            color: var(--secondary-color);
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
            outline: none;
        }
        
        /* Style for optgroup to make it stand out */
        optgroup {
            font-weight: bold;
            color: var(--primary-color);
            background-color: #f9fafb;
            padding: 8px 15px; /* Add padding to optgroup */
        }
        
        optgroup option {
            padding-left: 25px; /* Indent options within optgroup */
        }

        .btn-submit {
            background-color: var(--primary-color);
            border: none;
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            width: 100%;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }

        .btn-submit:hover {
            background-color: var(--primary-light);
            transform: translateY(-2px);
        }
        
        .btn-submit:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
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
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #6b7280;
            font-size: 0.75rem;
        }
        .nav-item.active {
            color: var(--primary-color);
        }
        .nav-icon {
            font-size: 1.25rem;
            margin-bottom: 4px;
        }

        /* Hide bottom nav on larger screens */
        @media (min-width: 768px) {
            .bottom-nav {
                display: none;
            }
            body {
                padding-bottom: 0; /* Remove extra padding on desktop */
            }
        }
        @media (max-width: 576px) {
            .report-card {
                padding: 1.5rem;
            }
             .main-container {
                margin: 1rem auto 6rem auto; /* Add margin-bottom to avoid overlap with nav */
            }
        }
    </style>
</head>
<body>

    <div class="main-container">
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success animate__animated animate__fadeInDown" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php elseif (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger animate__animated animate__fadeInDown" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="report-card animate__animated animate__fadeInUp">
            <div class="card-header">
                <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="Logo" class="logo">
                <h2>របាយការណ៍ប្រចាំថ្ងៃ</h2>
            </div>
            
            <form id="reportForm" method="POST" action="daily_reports.php">
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="Email" class="form-label">អ៊ីមែល</label>
                        <input class="form-control" type="email" id="Email" placeholder="example@email.com" name="Email" required />
                    </div>
                    <div class="col-md-6 form-group">
                        <label for="Name" class="form-label">ឈ្មោះ</label>
                        <!-- Use $user['name'] if user login is enabled and fetched -->
                        <input class="form-control" type="text" id="Name" placeholder="ឈ្មោះពេញ" name="Name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required />
                    </div>
                </div>

                <div class="form-group">
                    <label for="Positions" class="form-label">តួនាទី</label>
                    <select class="form-select" name="Position" id="Positions" required>
                        <option value="" disabled selected>-- សូមជ្រើសរើសតួនាទី --</option>
                        <?php 
                        // Loop through categorized positions to create optgroups
                        foreach ($categorizedPositions as $categoryName => $positions):
                            if (!empty($positions)): // Only show optgroup if it has positions
                        ?>
                                <optgroup label="■ <?php echo htmlspecialchars($categoryName); ?>">
                                <?php foreach ($positions as $pos): ?>
                                    <option value="<?php echo htmlspecialchars($pos); ?>"><?php echo htmlspecialchars($pos); ?></option>
                                <?php endforeach; ?>
                                </optgroup>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="Date" class="form-label">ថ្ងៃខែឆ្នាំ និងម៉ោង</label>
                    <input class="form-control" type="datetime-local" id="Date" name="Date" required />
                </div>
                
                <div class="form-group">
                    <label for="reportContent" class="form-label">របាយការណ៍ប្រចាំថ្ងៃ</label>
                    <textarea class="form-control" id="reportContent" placeholder="សរសេររបាយការណ៍របស់អ្នកនៅទីនេះ..." name="Content" rows="6" required></textarea>
                </div>
                
                <div class="form-group mt-4">
                    <button class="btn btn-submit" type="submit" id="submit-button">
                        <i class="fas fa-paper-plane me-2"></i> បញ្ជូនរបាយការណ៍
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bottom Navigation Bar for Mobile -->
    <nav class="bottom-nav">
        <a href="homes.php" class="nav-item active">
            <i class="fas fa-home nav-icon"></i>
            <span>ទំព័រដើម</span>
        </a>
        <a href="list_reports.php" class="nav-item">
            <i class="fas fa-list-alt nav-icon"></i>
            <span>បញ្ជី</span>
        </a>
        <a href="logout.php" class="nav-item"> <!-- Assuming you have a logout script -->
            <i class="fas fa-sign-out-alt nav-icon"></i>
            <span>ចាកចេញ</span>
        </a>
        <a href="profile.php" class="nav-item"> <!-- Assuming you have a profile page -->
            <i class="fas fa-user nav-icon"></i>
            <span>គណនី</span>
        </a>
    </nav>
    
    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background: transparent; border: none;">
                <div class="modal-body text-center">
                    <div class="spinner-border text-light" role="status" style="width: 3.5rem; height: 3.5rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-white fw-bold fs-5">កំពុងបញ្ជូន... សូមរង់ចាំ</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const reportForm = document.getElementById('reportForm');
            const submitButton = document.getElementById('submit-button');
            const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
            const textarea = document.getElementById('reportContent');
            const dateInput = document.getElementById('Date');

            // --- Auto-Save and Restore Textarea Content ---
            const savedContent = localStorage.getItem('reportContent');
            if (savedContent) {
                textarea.value = savedContent;
            }
            textarea.addEventListener('input', () => {
                localStorage.setItem('reportContent', textarea.value);
            });

            // --- Set current date and time by default ---
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset()); // Adjust for local timezone
            dateInput.value = now.toISOString().slice(0, 16);

            // --- Handle Form Submission with Loading State ---
            reportForm.addEventListener('submit', function(event) {
                if (!this.checkValidity()) {
                    // Let browser handle validation feedback
                    return;
                }
                
                // Show loading state
                loadingModal.show();
                submitButton.disabled = true;
                submitButton.innerHTML = `
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    កំពុងបញ្ជូន...
                `;
                
                // Clear saved draft AFTER successful submission is initiated
                localStorage.removeItem('reportContent');
            });
            
            // --- Clear localStorage if submission was successful ---
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                localStorage.removeItem('reportContent');
                // Optional: clean the URL
                window.history.replaceState(null, null, window.location.pathname);
            }
        });
    </script>

</body>
</html>