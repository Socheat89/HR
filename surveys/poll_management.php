<?php
session_start();
require_once '../system/log.php';

// ONLY ADMIN CAN ACCESS THIS PAGE
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'ב¢בבבבבבב¶בבב·בבבב·בב¼בבבבבבבבבבבב';
    header("Location: ../auth/login.php"); // Redirect to login or home page
    exit();
}

// Database connection
try {
    $db = new PDO("mysql:host=localhost;dbname=samann1_admin_panel;charset=utf8mb4", 'root', '');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- AUTO-FIX DATABASE SCHEMA ---
    try {
        // Ensure allowed_usernames column exists in polls
        $check_col = $db->query("SHOW COLUMNS FROM polls LIKE 'allowed_usernames'");
        if ($check_col && $check_col->rowCount() == 0) {
            $db->exec("ALTER TABLE polls ADD COLUMN allowed_usernames TEXT DEFAULT NULL");
        }
        // Ensure exempted_usernames column exists in polls
        $check_col_exempt = $db->query("SHOW COLUMNS FROM polls LIKE 'exempted_usernames'");
        if ($check_col_exempt && $check_col_exempt->rowCount() == 0) {
            $db->exec("ALTER TABLE polls ADD COLUMN exempted_usernames TEXT DEFAULT NULL");
        }
    } catch (Exception $e) {
        error_log("Auto-migration warning: " . $e->getMessage());
    }
    // --------------------------------
} catch (PDOException $e) {
    if (isset($_GET['action']) && $_GET['action'] === 'get_poll_results') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'בב¶בבבבב ב¶בבבב»בבב¶בבבבב¶בבבב Server ב']);
        exit();
    } else {
        $_SESSION['error'] = 'בב¶בבבבב ב¶בבבב»בבב¶בבבבבב¶בבבב·בבבבבבב בב¼בבבבב¶בב¶בבבבבבבבב';
        error_log("DB Connection Error: " . $e->getMessage());
        header("Location: error.php");
        exit();
    }
}

// Function to convert quarter code to full text
function getQuarterText($quarter) {
    $quarters = [
        'ב¡' => 'בבבב¸בב¶בבב¸ ב¡',
        'ב¢' => 'בבבב¸בב¶בבב¸ ב¢',
        'ב£' => 'בבבב¸בב¶בבב¸ ב£',
        'ב₪' => 'בבבב¸בב¶בבב¸ ב₪'
    ];
    return $quarters[$quarter] ?? $quarter;
}

$message = '';
$message_type = '';

// --- AJAX Endpoint for fetching poll details and results with key verification ---
if (isset($_GET['action']) && $_GET['action'] === 'get_poll_results' && isset($_GET['poll_id'])) {
    $poll_id = filter_input(INPUT_GET, 'poll_id', FILTER_VALIDATE_INT);
    $access_key_input = $_GET['access_key'] ?? ''; // Get key from AJAX request

    if ($poll_id) {
        try {
            // First, get the stored key for this poll
            $stmt_key = $db->prepare("SELECT results_access_key FROM polls WHERE id = :poll_id");
            $stmt_key->execute(['poll_id' => $poll_id]);
            $poll_data = $stmt_key->fetch(PDO::FETCH_ASSOC);

            // Check if a key is required for this poll and if the provided key is valid
            if ($poll_data && !empty($poll_data['results_access_key'])) { // Key is required if results_access_key is not empty
                if (!password_verify($access_key_input, $poll_data['results_access_key'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'בבבבבבבב¶בבבב·בבבבב¹בבבבב¼בב']);
                    exit();
                }
            }
            // If results_access_key is NULL or empty, no key is required. Proceed.

            // Fetch aggregated results
            $stmt_results = $db->prepare("
                SELECT
                    u.id AS user_id,
                    COALESCE(u.full_name, u.username) AS employee_name,
                    SUM(COALESCE(pv.vote_count, 1)) AS total_votes
                FROM
                    peer_votes pv
                JOIN
                    users u ON pv.voted_for_user_id = u.id
                WHERE
                    pv.poll_id = :poll_id
                GROUP BY
                    u.id, employee_name
                ORDER BY
                    total_votes DESC
            ");
            $stmt_results->execute(['poll_id' => $poll_id]);
            $aggregated_results = $stmt_results->fetchAll(PDO::FETCH_ASSOC);
            $total_votes_overall = array_sum(array_column($aggregated_results, 'total_votes'));

            // Fetch individual votes
            $stmt_individual_votes = $db->prepare("
                SELECT
                    COALESCE(u_voter.full_name, u_voter.username) AS voter_name,
                    COALESCE(u_voted_for.full_name, u_voted_for.username) AS voted_for_name,
                    COALESCE(pv.vote_count, 1) AS vote_count,
                    pv.voted_at
                FROM
                    peer_votes pv
                JOIN
                    users u_voter ON pv.voter_user_id = u_voter.id
                JOIN
                    users u_voted_for ON pv.voted_for_user_id = u_voted_for.id
                WHERE
                    pv.poll_id = :poll_id
                ORDER BY
                    pv.voted_at DESC
            ");
            $stmt_individual_votes->execute(['poll_id' => $poll_id]);
            $individual_votes = $stmt_individual_votes->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'aggregated_results' => $aggregated_results,
                'total_votes_overall' => $total_votes_overall,
                'individual_votes' => $individual_votes
            ]);
            exit();

        } catch (PDOException $e) {
            error_log("AJAX Fetch Poll Results Error: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'בב¶בבבבב ב¶בבבב»בבב¶בבב¶בבבבבבבבבבב¶בבבבבבבבבב']);
            exit();
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'בבבבבבבב¶בבבב¶בבבבבבבבבבב·בבבבב¹בבבבב¼בב']);
        exit();
    }
}

// --- Handle Form Submissions (Create/Edit) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create New Poll
    if (isset($_POST['create_poll_submit'])) {
        $question = filter_input(INPUT_POST, 'question', FILTER_SANITIZE_STRING);
        $quarter = filter_input(INPUT_POST, 'quarter', FILTER_SANITIZE_STRING);
        $warehouse = filter_input(INPUT_POST, 'warehouse', FILTER_SANITIZE_STRING);
        $start_date = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING);
        $end_date = filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $results_access_key = filter_input(INPUT_POST, 'results_access_key', FILTER_SANITIZE_STRING);
        $restrict_users = isset($_POST['restrict_users']) ? 1 : 0;
        $allowed_usernames = $restrict_users && isset($_POST['allowed_users']) ? implode(',', array_map('trim', $_POST['allowed_users'])) : null;
        $exempted_usernames = isset($_POST['exempted_users']) ? implode(',', array_map('trim', $_POST['exempted_users'])) : null;
        $hashed_key = ($results_access_key) ? password_hash($results_access_key, PASSWORD_DEFAULT) : NULL; // Hash the key

        if ($question && $quarter && $warehouse && $start_date && $end_date) {
            try {
                $stmt = $db->prepare("INSERT INTO polls (question, quarter, warehouse, start_date, end_date, is_active, results_access_key, allowed_usernames, exempted_usernames) VALUES (:question, :quarter, :warehouse, :start_date, :end_date, :is_active, :results_access_key, :allowed_usernames, :exempted_usernames)");
                $stmt->execute([
                    'question' => $question,
                    'quarter' => $quarter,
                    'warehouse' => $warehouse,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'is_active' => $is_active,
                    'results_access_key' => $hashed_key, // Store hashed key
                    'allowed_usernames' => $allowed_usernames,
                    'exempted_usernames' => $exempted_usernames
                ]);
                $message = 'בב¶בבבבבבבבבבבבב¸בבבב¼בבב¶בבבבבב¾בבבבבבבבבבב';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'בב¶בבבבב ב¶בבבב»בבב¶בבבבבב¾בבב¶בבבבבבבבבב';
                $message_type = 'danger';
                error_log("Create Poll Error: " . $e->getMessage());
            }
        } else {
            $message = 'בב¼בבבבבבבבבבבב¶בב±בבבב¶בבבבבבבב';
            $message_type = 'danger';
        }
    }

    // Edit Existing Poll
    if (isset($_POST['edit_poll_submit'])) {
        $poll_id = filter_input(INPUT_POST, 'poll_id', FILTER_VALIDATE_INT);
        $question = filter_input(INPUT_POST, 'question', FILTER_SANITIZE_STRING);
        $quarter = filter_input(INPUT_POST, 'quarter', FILTER_SANITIZE_STRING);
        $warehouse = filter_input(INPUT_POST, 'warehouse', FILTER_SANITIZE_STRING);
        $start_date = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING);
        $end_date = filter_input(INPUT_POST, 'end_date', FILTER_SANITIZE_STRING);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $results_access_key_new = filter_input(INPUT_POST, 'results_access_key', FILTER_SANITIZE_STRING);
        $restrict_users = isset($_POST['restrict_users']) ? 1 : 0;
        $allowed_usernames = $restrict_users && isset($_POST['allowed_users']) ? implode(',', array_map('trim', $_POST['allowed_users'])) : null;
        $exempted_usernames = isset($_POST['exempted_users']) ? implode(',', array_map('trim', $_POST['exempted_users'])) : null;
        $hashed_key_to_store = NULL;

        if ($poll_id && $question && $quarter && $warehouse && $start_date && $end_date) {
            try {
                // If a new key is provided, hash it.
                if (!empty($results_access_key_new)) {
                    $hashed_key_to_store = password_hash($results_access_key_new, PASSWORD_DEFAULT);
                } else {
                    // If the key field is left empty, fetch the existing key from DB and retain it.
                    // This prevents accidentally clearing a set key.
                    $stmt_old_key = $db->prepare("SELECT results_access_key FROM polls WHERE id = :id");
                    $stmt_old_key->execute(['id' => $poll_id]);
                    $old_poll_data = $stmt_old_key->fetch(PDO::FETCH_ASSOC);
                    if ($old_poll_data) {
                        $hashed_key_to_store = $old_poll_data['results_access_key'];
                    }
                }

                $sql_update = "UPDATE polls SET question = :question, quarter = :quarter, warehouse = :warehouse, start_date = :start_date, end_date = :end_date, is_active = :is_active, results_access_key = :results_access_key, allowed_usernames = :allowed_usernames, exempted_usernames = :exempted_usernames WHERE id = :id";
                $stmt = $db->prepare($sql_update);
                $stmt->execute([
                    'question' => $question,
                    'quarter' => $quarter,
                    'warehouse' => $warehouse,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'is_active' => $is_active,
                    'results_access_key' => $hashed_key_to_store, // Use the determined key
                    'allowed_usernames' => $allowed_usernames,
                    'exempted_usernames' => $exempted_usernames,
                    'id' => $poll_id
                ]);
                $message = 'בב¶בבבבבבבבבבבבב¼בבב¶בבבבבבבבבבבבבבבבב';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'בב¶בבבבב ב¶בבבב»בבב¶בבבבבבבבב¶בבבבבבבבבב';
                $message_type = 'danger';
                error_log("Edit Poll Error: " . $e->getMessage());
            }
        } else {
            $message = 'בב¼בבבבבבבבבבבב¶בב±בבבב¶בבבבבבבב';
            $message_type = 'danger';
        }
    }
}

// --- Handle GET Actions (Toggle Active/Delete) ---
if (isset($_GET['action']) && ($_GET['action'] === 'toggle_active' || $_GET['action'] === 'delete')) {
    $poll_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($poll_id) {
        if ($_GET['action'] === 'toggle_active') {
            try {
                // Get current status
                $stmt_current = $db->prepare("SELECT is_active, exempted_usernames FROM polls WHERE id = :id");
                $stmt_current->execute(['id' => $poll_id]);
                $current_poll = $stmt_current->fetch(PDO::FETCH_ASSOC);
                if (!$current_poll) {
                    throw new Exception('Poll not found');
                }
                $new_active = 1 - $current_poll['is_active'];
                $update_excluded = $current_poll['excluded_usernames'];

                // If activating the poll, append exempted to excluded
                if ($current_poll['is_active'] == 0 && $new_active == 1 && !empty($current_poll['exempted_usernames'])) {
                    $exempted = array_map('trim', explode(',', strtolower($current_poll['exempted_usernames'])));
                    $excluded = !empty($current_poll['excluded_usernames']) ? array_map('trim', explode(',', strtolower($current_poll['excluded_usernames']))) : [];
                    $combined = array_unique(array_merge($excluded, $exempted));
                    $update_excluded = implode(',', $combined);
                }

                $stmt = $db->prepare("UPDATE polls SET is_active = :active, excluded_usernames = :excluded WHERE id = :id");
                $stmt->execute(['active' => $new_active, 'excluded' => $update_excluded, 'id' => $poll_id]);
                $message = 'בבבב¶בבב¶בבב¶בבבבבבבבבבבבב¼בבב¶בבבבב¶בבבבבב¼בבבבבבבבבבב';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'בב¶בבבבב ב¶בבבב»בבב¶בבבבב¶בבבבבב¼בבבבב¶בבב¶בבב¶בבבבבבבבבב';
                $message_type = 'danger';
                error_log("Toggle Active Error: " . $e->getMessage());
            }
        } elseif ($_GET['action'] === 'delete') {
            try {
                // Deleting from 'polls' should cascade delete from 'peer_votes' due to ON DELETE CASCADE
                $stmt = $db->prepare("DELETE FROM polls WHERE id = :id");
                $stmt->execute(['id' => $poll_id]);
                $message = 'בב¶בבבבבבבבבבב·בבבב¡בבבבבבבבבבבב¶בבבבבבבבבבב¼בבב¶בבב»בבבבבבבבבבב';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'בב¶בבבבב ב¶בבבב»בבב¶בבב»בבב¶בבבבבבבבבב';
                $message_type = 'danger';
                error_log("Delete Poll Error: " . $e->getMessage());
            }
        }
    } else {
        $message = 'ID בב¶בבבבבבבבבבב·בבבבב¹בבבבב¼בב';
        $message_type = 'danger';
    }
    // Redirect to clean URL after action
    header("Location: ../surveys/poll_management.php");
    exit();
}


// Fetch all polls for display (without pre-fetching results)
$all_polls = [];
$all_users = [];
try {
    // Select results_access_key to determine if a key is required
    $stmt = $db->query("SELECT id, question, quarter, warehouse, start_date, end_date, is_active, results_access_key, allowed_usernames, exempted_usernames FROM polls ORDER BY created_at DESC");
    $all_polls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add a flag to indicate if a key is required for the frontend
    foreach ($all_polls as $key => $poll) {
        $all_polls[$key]['key_required'] = !empty($poll['results_access_key']);
        unset($all_polls[$key]['results_access_key']); // Remove sensitive data
    }

    // Fetch all users for the user selection
    $stmt_users = $db->query("SELECT id, username, full_name FROM users ORDER BY username ASC");
    $all_users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $_SESSION['error'] = 'בב¶בבבבב ב¶בבבב»בבב¶בבב¶בבב·בבבבבבבב¶בבבבבבבבבב';
    error_log("Fetch All Polls Error: " . $e->getMessage());
    header("Location: error.php");
    exit();
}

$current_page = '../surveys/poll_management.php'; // For bottom navigation active state
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#4f46e5">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
    <title>Poll Management - HRM Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="manifest" href="manifest.json">
    <style>
        /* === DESKTOP STYLES (DEFAULT) === */
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --secondary: #4338ca;
            --accent: #06b6d4;
            --dark: #1e293b;
            --light: #f8fafc;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray: #64748b;
        }
        body {
            font-family: 'Kantumruy Pro', 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--light);
            color: var(--dark);
            line-height: 1.6;
            margin: 0;
            padding: 0;
            padding-bottom: 100px; /* Space for the bottom nav */
        }
        .app-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            margin-bottom: 30px;
        }
        .logo-container {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .logo-img {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            object-fit: cover;
        }
        .app-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            cursor: pointer;
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border: none;
        }
        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border-top-left-radius: 16px;
            border-top-right-radius: 16px;
            padding: 15px 20px;
            font-size: 1.2rem;
            font-weight: 600;
        }
        .card-body {
            padding: 20px;
        }
        .table {
            border-radius: 16px;
            overflow: hidden;
        }
        .table thead {
            background-color: var(--primary);
            color: white;
        }
        .table tbody tr:nth-child(even) {
            background-color: #f0f0f0;
        }
        .status-badge {
            padding: 0.3em 0.6em;
            border-radius: 0.5em;
            font-size: 0.75em;
            font-weight: 600;
        }
        .status-active {
            background-color: var(--success);
            color: white;
        }
        .status-inactive {
            background-color: var(--gray);
            color: white;
        }
        .btn-action {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.25rem;
        }
        .modal-content { border-radius: 16px; border: none; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); }
        .modal-header { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; border-top-left-radius: 16px; border-top-right-radius: 16px; padding: 15px 20px; }
        .modal-title { font-size: 1.4rem; font-weight: 600; }
        .modal-body { padding: 20px; }
        .modal-footer { border-top: none; padding: 15px 20px; }
        .btn-close { background-color: rgba(255, 255, 255, 0.2); opacity: 1; }
        .btn-close:hover { background-color: rgba(255, 255, 255, 0.3); }

        /* Results table in modal */
        .results-table th {
            background-color: var(--secondary);
            color: white;
        }
        .results-table tr:nth-child(1) { /* Highlight winner */
            background-color: #fff3cd; /* Light yellow */
            font-weight: bold;
        }
        .progress-bar-container {
            width: 100%;
            background-color: #f0f0f0;
            border-radius: 5px;
            margin-top: 5px;
            height: 15px;
        }
        .progress-bar-vote {
            height: 15px;
            background-color: var(--accent);
            border-radius: 5px;
            text-align: right;
            color: white;
            font-size: 10px;
            line-height: 15px;
            padding-right: 5px;
        }
        .winner-star {
            color: gold;
            margin-left: 5px;
        }
        
        /* Individual Votes Section */
        .individual-votes-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .individual-votes-table th {
            background-color: var(--gray);
            color: white;
        }
        .individual-votes-table tbody tr:nth-child(even) {
            background-color: #f8f8f8;
        }


        /* Bottom Navigation (optional for admin pages, but included for consistency) */
        .bottom-nav {
            display: none; /* Hide by default for desktop, show on mobile */
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            justify-content: space-around;
            padding: 10px 0;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.08);
            z-index: 1000;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
        }
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--gray);
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.2s ease;
            padding: 5px 10px;
        }
        .nav-item.active, .nav-item:hover {
            color: var(--primary);
            transform: translateY(-3px);
        }
        .nav-icon {
            font-size: 1.4rem;
            margin-bottom: 4px;
        }


        /* === MOBILE APP STYLES (Applied only on screens <= 768px) === */
        @media (max-width: 768px) {
            .app-container {
                padding: 1rem;
            }
            .app-header {
                padding: 1rem 0;
                margin-bottom: 1.5rem;
            }
            .logo-img { width: 45px; height: 45px; }
            .app-title { font-size: 1.5rem; background: none; -webkit-text-fill-color: initial; color: var(--dark); }
            .user-avatar { width: 40px; height: 40px; }
            .user-profile .user-name {
                display: none; /* Hide name on mobile for more space */
            }
            .card-header {
                font-size: 1rem;
            }
            .table-responsive {
                border-radius: 10px; /* Smaller border-radius for mobile */
            }
            .table {
                font-size: 0.85rem; /* Smaller font for tables */
            }
            .table th, .table td {
                padding: 0.5rem;
            }
            .btn-action {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
            .bottom-nav { /* Show bottom nav on mobile */
                display: flex;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Modern App Header -->
        <header class="app-header">
            <a href="homes.php">
                <div class="logo-container">
                    <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="HRM Logo" class="logo-img">
                    <h1 class="app-title">HR App</h1>
                </div>
            </a>
            <div class="user-profile">
                <span class="user-name" style="font-weight: 600; color: var(--dark);">
                    <?php echo htmlspecialchars($_SESSION['username'] ?? 'ב¢בבבבבבבבבבבב'); ?>
                </span>
                <a href="https://app.vvc.asia/admin/profile.php" class="user-avatar" title="Profile">
                    <i class="fas fa-user"></i>
                </a>
                <a href="?logout=true" class="btn btn-danger btn-sm">בב¶בבבב</a>
            </div>
        </header>

        <h2 class="mb-4" style="font-weight: 600; color: var(--dark);">בבבבבבבבבבב¶בבבבבבבבבבב»בבבבב·ב</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="mb-3 text-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPollModal">
                <i class="fas fa-plus-circle me-2"></i>בבבבב¾בבב¶בבבבבבבבבבבבב¸
            </button>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>בבבב½ב</th>
                                <th>בבבב¸בב¶בבב¸</th>
                                <th>Warehouse</th>
                                <th>בב¶בבבבבב¾ב</th>
                                <th>בבבבבב</th>
                                <th>בבבבב</th>
                                <th>בבבבבבב¶ב</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($all_polls)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">בב·בבב¶בבבב¶בבב¶בבבבבבבבבבב¶בב½בבבב</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($all_polls as $index => $poll): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($poll['question']); ?></td>
                                        <td><?php echo htmlspecialchars(getQuarterText($poll['quarter'])); ?></td>
                                        <td><?php echo htmlspecialchars($poll['warehouse']); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($poll['start_date'])); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($poll['end_date'])); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $poll['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $poll['is_active'] ? 'בבבבב' : 'ב¢בבבבב'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-info btn-sm btn-action view-results-btn" data-bs-toggle="modal" data-bs-target="#viewResultsModal" data-poll-id="<?php echo $poll['id']; ?>" data-poll-question="<?php echo htmlspecialchars($poll['question']); ?>" data-key-required="<?php echo $poll['key_required'] ? 'true' : 'false'; ?>">
                                                <i class="fas fa-eye"></i> בב¾בבבבבבב
                                                <?php if ($poll['key_required']): ?>
                                                    <i class="fas fa-lock ms-1"></i>
                                                <?php endif; ?>
                                            </button>
                                            <button class="btn btn-warning btn-sm btn-action edit-poll-btn" data-bs-toggle="modal" data-bs-target="#editPollModal" data-poll-id="<?php echo $poll['id']; ?>" data-question="<?php echo htmlspecialchars($poll['question']); ?>" data-quarter="<?php echo htmlspecialchars($poll['quarter']); ?>" data-warehouse="<?php echo htmlspecialchars($poll['warehouse']); ?>" data-start-date="<?php echo date('Y-m-d\TH:i', strtotime($poll['start_date'])); ?>" data-end-date="<?php echo date('Y-m-d\TH:i', strtotime($poll['end_date'])); ?>" data-is-active="<?php echo $poll['is_active']; ?>" data-allowed-usernames="<?php echo htmlspecialchars($poll['allowed_usernames'] ?? ''); ?>" data-exempted-usernames="<?php echo htmlspecialchars($poll['exempted_usernames'] ?? ''); ?>" data-restrict-users="<?php echo !empty($poll['allowed_usernames']) ? '1' : '0'; ?>">
                                                <i class="fas fa-edit"></i> בבבבבב
                                            </button>
                                            <a href="../surveys/poll_management.php?action=toggle_active&id=<?php echo $poll['id']; ?>" class="btn btn-secondary btn-sm btn-action">
                                                <i class="fas <?php echo $poll['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on'; ?>"></i> <?php echo $poll['is_active'] ? 'בב·ב' : 'בב¾ב'; ?>
                                            </a>
                                            <a href="../surveys/poll_management.php?action=delete&id=<?php echo $poll['id']; ?>" class="btn btn-danger btn-sm btn-action" onclick="return confirm('בב¾ב¢בבבבב·בבב¶בבבבב»בבב¶בבבבבבבבבבבב בב·בבבב¡בבבבבבבבבבבב¶בבבבבבבבב¶בבב¢בבבבבבב?');">
                                                <i class="fas fa-trash-alt"></i> בב»ב
                                            </a>
                                            <?php
                                            // The print certificate button is now managed dynamically within the View Results Modal
                                            // based on successful key entry and results display.
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modals -->

        <!-- Create Poll Modal -->
        <div class="modal fade" id="createPollModal" tabindex="-1" aria-labelledby="createPollModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="../surveys/poll_management.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createPollModalLabel">בבבבב¾בבב¶בבבבבבבבבבבבב¸</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="create_question" class="form-label">בבבב½ב</label>
                                <input type="text" class="form-control" id="create_question" name="question" required>
                            </div>
                            <div class="mb-3">
                                <label for="create_quarter" class="form-label">בבבב¸בב¶בבב¸</label>
                                <select class="form-control" id="create_quarter" name="quarter" required>
                                    <option value="">-- בבבב¾בבב¾ב --</option>
                                    <option value="ב¡">בבבב¸בב¶בבב¸ ב¡</option>
                                    <option value="ב¢">בבבב¸בב¶בבב¸ ב¢</option>
                                    <option value="ב£">בבבב¸בב¶בבב¸ ב£</option>
                                    <option value="ב₪">בבבב¸בב¶בבב¸ ב₪</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="create_warehouse" class="form-label">Warehouse</label>
                                <input type="text" class="form-control" id="create_warehouse" name="warehouse" required>
                            </div>
                            <div class="mb-3">
                                <label for="create_start_date" class="form-label">בב¶בבבב·בבבבבבב¶בבבבבב¾ב</label>
                                <input type="datetime-local" class="form-control" id="create_start_date" name="start_date" required>
                            </div>
                            <div class="mb-3">
                                <label for="create_end_date" class="form-label">בב¶בבבב·בבבבבבבבבבב</label>
                                <input type="datetime-local" class="form-control" id="create_end_date" name="end_date" required>
                            </div>
                            <div class="mb-3">
                                <label for="create_results_access_key" class="form-label">בבבבבבבב¶בבבבבבב¶בבבב¾בבבבבבב (בב»בבבבבב¾בב·בבבבב¼בבב¶ב)</label>
                                <input type="password" class="form-control" id="create_results_access_key" name="results_access_key" autocomplete="new-password">
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" value="1" id="create_restrict_users" name="restrict_users">
                                <label class="form-check-label" for="create_restrict_users">
                                    בבבבבב¢בבבבבבב¾בבבב¶בבבבבב¢בב»בבבב¶בבבבבבבבב
                                </label>
                            </div>
                            <div class="mb-3" id="create_usernames_div" style="display: none;">
                                <label class="form-label">בבבב¾בבב¾בב¢בבבבבבב¾בבבב¶בבבבבב¢בב»בבבב¶ב</label>
                                <div class="border p-3" style="max-height: 200px; overflow-y: auto;" id="create_users_list">
                                    <!-- Users will be populated by JS -->
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">ב¢בבבבבבב¾בבבב¶בבבבבבב¾בבבבבבבבב¾ב (optional)</label>
                                <div class="border p-3" style="max-height: 200px; overflow-y: auto;" id="create_exempted_users_list">
                                    <!-- Users will be populated by JS -->
                                </div>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="create_is_active" name="is_active" checked>
                                <label class="form-check-label" for="create_is_active">
                                    בבבבב
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">בבבבבב</button>
                            <button type="submit" name="create_poll_submit" class="btn btn-primary">בבבבב¾ב</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Edit Poll Modal -->
        <div class="modal fade" id="editPollModal" tabindex="-1" aria-labelledby="editPollModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="../surveys/poll_management.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editPollModalLabel">בבבבבבבב¶בבבבבבבבב</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="poll_id" id="edit_poll_id">
                            <div class="mb-3">
                                <label for="edit_question" class="form-label">בבבב½ב</label>
                                <input type="text" class="form-control" id="edit_question" name="question" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_quarter" class="form-label">בבבב¸בב¶בבב¸</label>
                                <select class="form-control" id="edit_quarter" name="quarter" required>
                                    <option value="">-- בבבב¾בבב¾ב --</option>
                                    <option value="ב¡">בבבב¸בב¶בבב¸ ב¡</option>
                                    <option value="ב¢">בבבב¸בב¶בבב¸ ב¢</option>
                                    <option value="ב£">בבבב¸בב¶בבב¸ ב£</option>
                                    <option value="ב₪">בבבב¸בב¶בבב¸ ב₪</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="edit_warehouse" class="form-label">Warehouse</label>
                                <input type="text" class="form-control" id="edit_warehouse" name="warehouse" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_start_date" class="form-label">בב¶בבבב·בבבבבבב¶בבבבבב¾ב</label>
                                <input type="datetime-local" class="form-control" id="edit_start_date" name="start_date" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_end_date" class="form-label">בב¶בבבב·בבבבבבבבבבב</label>
                                <input type="datetime-local" class="form-control" id="edit_end_date" name="end_date" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_results_access_key" class="form-label">בבבבבבבב¶בבבבבבב¶בבבב¾בבבבבבב (בב»בבבבבב¾בבבב¸בב·בבבבב¶בבבבבב¼ב)</label>
                                <input type="password" class="form-control" id="edit_results_access_key" name="results_access_key" autocomplete="off">
                                <small class="form-text text-muted">בבבבב¼בבבבבבבבב¶בבבבבב¸בב¾בבבב¸בבבב¶בבבבבב¼ב ב¬בב»בבבבבב¾בבבב¸בבבבב¶בבבבבבבב¶בבבבבבב¶בבבבב¶בבב</small>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_restrict_users" name="restrict_users">
                                <label class="form-check-label" for="edit_restrict_users">
                                    בבבבבב¢בבבבבבב¾בבבב¶בבבבבב¢בב»בבבב¶בבבבבבבבב
                                </label>
                            </div>
                            <div class="mb-3" id="edit_usernames_div" style="display: none;">
                                <label class="form-label">בבבב¾בבב¾בב¢בבבבבבב¾בבבב¶בבבבבב¢בב»בבבב¶ב</label>
                                <div class="border p-3" style="max-height: 200px; overflow-y: auto;" id="edit_users_list">
                                    <!-- Users will be populated by JS -->
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">ב¢בבבבבבב¾בבבב¶בבבבבבב¾בבבבבבבבב¾ב (optional)</label>
                                <div class="border p-3" style="max-height: 200px; overflow-y: auto;" id="edit_exempted_users_list">
                                    <!-- Users will be populated by JS -->
                                </div>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="edit_is_active" name="is_active">
                                <label class="form-check-label" for="edit_is_active">
                                    בבבבב
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">בבבבבב</button>
                            <button type="submit" name="edit_poll_submit" class="btn btn-primary">בבבבב¶בב»בבב¶בבבבבבב</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Results Modal -->
        <div class="modal fade" id="viewResultsModal" tabindex="-1" aria-labelledby="viewResultsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewResultsModalLabel">בבבבבבבב¶בבבבבבבבב: <span id="resultsPollQuestion"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="accessKeyForm" class="mb-3">
                            <p id="keyRequiredMessage" class="alert alert-warning" style="display: none;"><i class="fas fa-lock me-2"></i>בב¼בבבבבב¼בבבבבבבבב¶בבבב¾בבבב¸בב¾בבבבבבבבבבב¶בבבבבבבבבבבבב</p>
                            <div class="input-group">
                                <input type="password" class="form-control" id="resultsAccessKeyInput" placeholder="בבבבבבבב¶בב" autocomplete="off">
                                <button class="btn btn-primary" type="button" id="submitAccessKeyBtn">בבבב ב¶בבבבבבב</button>
                            </div>
                            <div id="keyErrorMessage" class="text-danger mt-2" style="display: none;"></div>
                        </div>

                        <div id="resultsContentArea" style="display: none;">
                            <p class="mb-3">בבבב½בבבב¡בבבבבבבבבב»ב: <strong id="resultsTotalVotes"></strong></p>
                            <div id="pollResultsContent">
                                <!-- Aggregated results will be loaded here by JavaScript -->
                            </div>

                            <!-- New section for Individual Votes -->
                            <div class="individual-votes-section">
                                <h5 class="mb-3">בבב¡בבבבבבבבבבב¢ב·ב:</h5>
                                <div id="individualVotesContent">
                                    <!-- Individual votes will be loaded here by AJAX -->
                                </div>
                            </div>
                            <div id="certificatePrintSection" class="mt-4 text-end" style="display: none;">
                                <a id="printCertificateBtn" href="#" target="_blank" class="btn btn-success">
                                    <i class="fas fa-award"></i> בבבבב»בבב Certificate
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">בב·ב</button>
                    </div>
                </div>
            </div>
        </div>

    </div>
    
    <!-- Bottom Navigation (Optional for admin page) -->
    <nav class="bottom-nav">
        <a href="homes.php" class="nav-item <?php echo $current_page === 'homes.php' ? 'active' : ''; ?>">
            <i class="fas fa-home nav-icon"></i>
            <span>בבבבבבב¾ב</span>
        </a>
        <a href="../system/checklist.php" class="nav-item <?php echo $current_page === '../system/checklist.php' ? 'active' : ''; ?>">
            <i class="fas fa-tasks nav-icon"></i>
            <span>בב¶בבב¶ב</span>
        </a>
        <a href="../posts/announcements.php" class="nav-item <?php echo $current_page === '../posts/announcements.php' ? 'active' : ''; ?>">
            <i class="fas fa-bell nav-icon"></i>
            <span>בבבב¹ב</span>
        </a>
        <a href="https://app.vvc.asia/admin/profile.php" class="nav-item <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user nav-icon"></i>
            <span>בבבב¸</span>
        </a>
    </nav>

    <script>
        // Pass users data to JS
        const allUsers = <?php echo json_encode($all_users); ?>;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Function to generate user checkboxes
            function generateUserCheckboxes(containerId, checkedUsernames = [], inputName = 'allowed_users[]') {
                const container = document.getElementById(containerId);
                container.innerHTML = '';
                allUsers.forEach(user => {
                    const isChecked = checkedUsernames.includes(user.username.toLowerCase());
                    const div = document.createElement('div');
                    div.className = 'form-check form-switch';
                    div.innerHTML = `
                        <input class="form-check-input" type="checkbox" value="${user.username}" id="${containerId}_user_${user.id}" name="${inputName}" ${isChecked ? 'checked' : ''}>
                        <label class="form-check-label" for="${containerId}_user_${user.id}">
                            ${user.full_name || user.username} (${user.username})
                        </label>
                    `;
                    container.appendChild(div);
                });
            }

            // Populate create users list on page load
            generateUserCheckboxes('create_users_list', [], 'allowed_users[]');
            generateUserCheckboxes('create_exempted_users_list', [], 'exempted_users[]');

            // Edit Poll Modal handler
            var editPollModal = document.getElementById('editPollModal');
            editPollModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; // Button that triggered the modal
                var pollId = button.getAttribute('data-poll-id');
                var question = button.getAttribute('data-question');
                var quarter = button.getAttribute('data-quarter');
                var warehouse = button.getAttribute('data-warehouse');
                var startDate = button.getAttribute('data-start-date');
                var endDate = button.getAttribute('data-end-date');
                var isActive = button.getAttribute('data-is-active');
                var allowedUsernames = button.getAttribute('data-allowed-usernames');
                var exemptedUsernames = button.getAttribute('data-exempted-usernames');
                var restrictUsers = button.getAttribute('data-restrict-users');

                // Store for later use
                window.currentAllowedUsernames = allowedUsernames;
                window.currentExemptedUsernames = exemptedUsernames;

                var modalTitle = editPollModal.querySelector('.modal-title');
                var modalPollId = editPollModal.querySelector('#edit_poll_id');
                var modalQuestion = editPollModal.querySelector('#edit_question');
                var modalQuarter = editPollModal.querySelector('#edit_quarter');
                var modalWarehouse = editPollModal.querySelector('#edit_warehouse');
                var modalStartDate = editPollModal.querySelector('#edit_start_date');
                var modalEndDate = editPollModal.querySelector('#edit_end_date');
                var modalIsActive = editPollModal.querySelector('#edit_is_active');
                var modalResultsAccessKey = editPollModal.querySelector('#edit_results_access_key'); // Added
                var modalRestrictUsers = editPollModal.querySelector('#edit_restrict_users');
                var modalUsernamesDiv = editPollModal.querySelector('#edit_usernames_div');

                modalTitle.textContent = 'בבבבבבבב¶בבבבבבבבב: ' + question;
                modalPollId.value = pollId;
                modalQuestion.value = question;
                modalQuarter.value = quarter;
                modalWarehouse.value = warehouse;
                modalStartDate.value = startDate;
                modalEndDate.value = endDate;
                modalIsActive.checked = (isActive == 1);
                modalResultsAccessKey.value = ''; // Always clear for security, user can re-enter or leave blank to keep existing
                if (isActive != 1) {
                    // Hide restrict users section for closed polls
                    modalRestrictUsers.closest('.form-check').style.display = 'none';
                    modalUsernamesDiv.style.display = 'none';
                } else {
                    modalRestrictUsers.closest('.form-check').style.display = 'block';
                    modalRestrictUsers.checked = (restrictUsers == 1);
                    if (restrictUsers == 1) {
                        modalUsernamesDiv.style.display = 'block';
                        const checkedUsernames = window.currentAllowedUsernames ? window.currentAllowedUsernames.split(',').map(u => u.trim().toLowerCase()) : [];
                        generateUserCheckboxes('edit_users_list', checkedUsernames, 'allowed_users[]');
                    } else {
                        modalUsernamesDiv.style.display = 'none';
                    }
                }
                // Always populate exempted users
                const exemptedChecked = window.currentExemptedUsernames ? window.currentExemptedUsernames.split(',').map(u => u.trim().toLowerCase()) : [];
                generateUserCheckboxes('edit_exempted_users_list', exemptedChecked, 'exempted_users[]');
            });

            // Toggle visibility for create modal
            document.getElementById('create_restrict_users').addEventListener('change', function() {
                var div = document.getElementById('create_usernames_div');
                if (this.checked) {
                    div.style.display = 'block';
                } else {
                    div.style.display = 'none';
                }
            });

            // Toggle visibility for edit modal
            document.getElementById('edit_restrict_users').addEventListener('change', function() {
                var div = document.getElementById('edit_usernames_div');
                if (this.checked) {
                    div.style.display = 'block';
                    // Populate the user list when enabling restrictions
                    const checkedUsernames = window.currentAllowedUsernames ? window.currentAllowedUsernames.split(',').map(u => u.trim().toLowerCase()) : [];
                    generateUserCheckboxes('edit_users_list', checkedUsernames, 'allowed_users[]');
                } else {
                    div.style.display = 'none';
                }
            });

            // Toggle visibility based on is_active
            document.getElementById('edit_is_active').addEventListener('change', function() {
                var restrictCheck = document.getElementById('edit_restrict_users').closest('.form-check');
                var usernamesDiv = document.getElementById('edit_usernames_div');
                if (this.checked) {
                    restrictCheck.style.display = 'block';
                    // Reset to current state
                    var restrictUsers = document.getElementById('edit_restrict_users');
                    if (restrictUsers.checked) {
                        usernamesDiv.style.display = 'block';
                    }
                } else {
                    restrictCheck.style.display = 'none';
                    usernamesDiv.style.display = 'none';
                }
            });

            // View Results Modal handler
            var viewResultsModal = document.getElementById('viewResultsModal');
            var resultsPollQuestion = document.getElementById('resultsPollQuestion');
            var resultsTotalVotes = document.getElementById('resultsTotalVotes');
            var pollResultsContent = document.getElementById('pollResultsContent');
            var individualVotesContent = document.getElementById('individualVotesContent');
            var accessKeyForm = document.getElementById('accessKeyForm');
            var resultsAccessKeyInput = document.getElementById('resultsAccessKeyInput');
            var submitAccessKeyBtn = document.getElementById('submitAccessKeyBtn');
            var keyRequiredMessage = document.getElementById('keyRequiredMessage');
            var keyErrorMessage = document.getElementById('keyErrorMessage');
            var resultsContentArea = document.getElementById('resultsContentArea');
            var printCertificateBtn = document.getElementById('printCertificateBtn'); // Certificate button
            var certificatePrintSection = document.getElementById('certificatePrintSection'); // Certificate section

            let currentPollId = null;
            let currentPollQuestion = '';
            let isKeyRequired = false;

            viewResultsModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; // Button that triggered the modal
                currentPollId = button.getAttribute('data-poll-id');
                currentPollQuestion = button.getAttribute('data-poll-question');
                isKeyRequired = button.getAttribute('data-key-required') === 'true';

                resultsPollQuestion.textContent = currentPollQuestion;

                // Reset modal state
                resultsAccessKeyInput.value = '';
                keyErrorMessage.style.display = 'none';
                pollResultsContent.innerHTML = '';
                individualVotesContent.innerHTML = '';
                resultsTotalVotes.textContent = '';
                certificatePrintSection.style.display = 'none'; // Hide certificate by default

                if (isKeyRequired) {
                    accessKeyForm.style.display = 'block';
                    keyRequiredMessage.style.display = 'block';
                    resultsContentArea.style.display = 'none';
                    resultsAccessKeyInput.focus();
                } else {
                    accessKeyForm.style.display = 'none';
                    keyRequiredMessage.style.display = 'none';
                    resultsContentArea.style.display = 'block';
                    fetchAndDisplayResults(currentPollId, ''); // No key needed, pass empty string
                }
            });

            submitAccessKeyBtn.addEventListener('click', function() {
                const accessKey = resultsAccessKeyInput.value.trim();
                keyErrorMessage.style.display = 'none';
                if (isKeyRequired && accessKey === '') {
                    keyErrorMessage.textContent = 'בב¼בבבבבב¼בבבבבבבבב¶בבב';
                    keyErrorMessage.style.display = 'block';
                    return;
                }
                fetchAndDisplayResults(currentPollId, accessKey);
            });

            // Allow pressing Enter key to submit
            resultsAccessKeyInput.addEventListener('keypress', function(event) {
                if (event.key === 'Enter') {
                    event.preventDefault(); // Prevent form submission
                    submitAccessKeyBtn.click();
                }
            });

            async function fetchAndDisplayResults(pollId, accessKey) {
                individualVotesContent.innerHTML = '<p class="text-center text-muted">בבבב»בבב¶בבבבב·בבבבבב...</p>';
                pollResultsContent.innerHTML = ''; // Clear aggregated results area too
                resultsTotalVotes.textContent = '';
                certificatePrintSection.style.display = 'none'; // Hide certificate until results are fetched

                try {
                    const response = await fetch(`poll_management.php?action=get_poll_results&poll_id=${pollId}&access_key=${encodeURIComponent(accessKey)}`);
                    const data = await response.json();

                    if (data.success) {
                        accessKeyForm.style.display = 'none'; // Hide key input on success
                        resultsContentArea.style.display = 'block'; // Show results area
                        keyErrorMessage.style.display = 'none'; // Hide any previous error message

                        const aggregatedResults = data.aggregated_results;
                        const totalVotesOverall = data.total_votes_overall;
                        const individualVotes = data.individual_votes;

                        resultsTotalVotes.textContent = totalVotesOverall;

                        // Display aggregated results
                        if (aggregatedResults.length === 0) {
                            pollResultsContent.innerHTML = '<p class="text-center">בב·בבב¶בבבב¶בבבב¡בבבבבבבבבבבב¶בבבב¶בבבבבבבבבבבבבבב</p>';
                        } else {
                            aggregatedResults.sort((a, b) => b.total_votes - a.total_votes);

                            let aggregatedResultsHtml = `
                                <table class="table table-bordered results-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>בב»בבבבב·ב</th>
                                            <th>בבב¡בבבבבבב</th>
                                            <th>בב¶בבב</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            `;
                            let winnerId = null;
                            if (aggregatedResults[0].total_votes > 0) {
                                winnerId = aggregatedResults[0].user_id;
                            }

                            aggregatedResults.forEach(function(result, index) {
                                var percentage = (totalVotesOverall > 0) ? ((result.total_votes / totalVotesOverall) * 100).toFixed(1) : 0;
                                aggregatedResultsHtml += `
                                        <tr>
                                            <td>${index + 1}</td>
                                            <td>
                                                ${result.employee_name}
                                                ${index === 0 && result.total_votes > 0 ? '<i class="fas fa-star winner-star" title="Winner"></i>' : ''}
                                            </td>
                                            <td>${result.total_votes}</td>
                                            <td>
                                                ${percentage}%
                                                <div class="progress-bar-container">
                                                    <div class="progress-bar-vote" style="width: ${percentage}%">${percentage > 0 ? percentage + '%' : ''}</div>
                                                </div>
                                            </td>
                                        </tr>
                                `;
                            });
                            aggregatedResultsHtml += `
                                    </tbody>
                                </table>
                            `;
                            pollResultsContent.innerHTML = aggregatedResultsHtml;

                            // Show print certificate button if there's a winner
                            if (winnerId !== null) {
                                printCertificateBtn.href = `print_certificate.php?poll_id=${pollId}&winner_id=${winnerId}`;
                                certificatePrintSection.style.display = 'block';
                            }
                        }

                        // Display individual votes
                        if (individualVotes.length === 0) {
                            individualVotesContent.innerHTML = '<p class="text-center">בב·בבב¶בבבב¶בבבב¡בבבבבבבבבבב¢ב·בבבבבב¶בבבב¶בבבבבבבבבבבבבבב</p>';
                        } else {
                            let individualVotesHtml = `
                                <table class="table table-bordered individual-votes-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>ב¢בבבבבבבבבבב</th>
                                            <th>בבבבבבבבב±בב</th>
                                            <th>בבבב½ב</th>
                                            <th>בבבב</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                            `;
                            individualVotes.forEach(function(vote, index) {
                                individualVotesHtml += `
                                        <tr>
                                            <td>${index + 1}</td>
                                            <td>${vote.voter_name}</td>
                                            <td>${vote.voted_for_name}</td>
                                            <td>${vote.vote_count}</td>
                                            <td>${vote.voted_at}</td>
                                        </tr>
                                `;
                            });
                            individualVotesHtml += `
                                    </tbody>
                                </table>
                            `;
                            individualVotesContent.innerHTML = individualVotesHtml;
                        }

                    } else {
                        // Display error message if key is wrong or other issues
                        accessKeyForm.style.display = 'block'; // Keep key input visible
                        resultsContentArea.style.display = 'none'; // Hide results
                        keyRequiredMessage.style.display = 'none'; // Hide default key prompt
                        keyErrorMessage.textContent = data.error || 'בב¶בבבבב ב¶בבבב»בבב¶בבב¶בבבבבבבבבב';
                        keyErrorMessage.style.display = 'block';
                        individualVotesContent.innerHTML = ''; // Clear loading message
                        pollResultsContent.innerHTML = '';
                        console.error('Error fetching poll results:', data.error);
                    }
                } catch (error) {
                    accessKeyForm.style.display = 'block'; // Keep key input visible
                    resultsContentArea.style.display = 'none'; // Hide results
                    keyRequiredMessage.style.display = 'none';
                    keyErrorMessage.textContent = 'בב¶בבבבב ב¶בבבב»בבב¶בבבבב¶בבבב Server ב';
                    keyErrorMessage.style.display = 'block';
                    individualVotesContent.innerHTML = ''; // Clear loading message
                    pollResultsContent.innerHTML = '';
                    console.error('Fetch error:', error);
                }
            }
        });
    </script>
</body>
</html>
