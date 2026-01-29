<?php
session_start();
// Debugging: Temporarily uncomment these lines to see immediate PHP errors on the browser if any.
// REMEMBER TO COMMENT THEM OUT FOR PRODUCTION.
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once 'log.php'; // Ensure log.php exists and is correctly configured

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = 'សូមចូលគណនីសិន!';
    header("Location: login.php");
    exit();
}

// Database connection
try {
    $db = new PDO("mysql:host=localhost;dbname=samann1_admin_panel;charset=utf8mb4", "samann1_admin_panel", "admin_panel@2025");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Corrected PDO::EXCEPTION to PDO::ERRMODE_EXCEPTION

    // --- AUTO-FIX DATABASE SCHEMA ---
    try {
        // Ensure vote_count column exists
        $check_col = $db->query("SHOW COLUMNS FROM peer_votes LIKE 'vote_count'");
        if ($check_col && $check_col->rowCount() == 0) {
            $db->exec("ALTER TABLE peer_votes ADD COLUMN vote_count INT DEFAULT 1");
        }

        // Ensure allowed_usernames column exists in polls
        $check_col_polls = $db->query("SHOW COLUMNS FROM polls LIKE 'allowed_usernames'");
        if ($check_col_polls && $check_col_polls->rowCount() == 0) {
            $db->exec("ALTER TABLE polls ADD COLUMN allowed_usernames TEXT DEFAULT NULL");
        }

        // Ensure exempted_usernames column exists in polls
        $check_col_exempt = $db->query("SHOW COLUMNS FROM polls LIKE 'exempted_usernames'");
        if ($check_col_exempt && $check_col_exempt->rowCount() == 0) {
            $db->exec("ALTER TABLE polls ADD COLUMN exempted_usernames TEXT DEFAULT NULL");
        }

        // Migrate data from excluded_usernames to exempted_usernames if needed
        try {
            $db->exec("UPDATE polls SET exempted_usernames = excluded_usernames WHERE exempted_usernames IS NULL AND excluded_usernames IS NOT NULL");
        } catch (Exception $e) {
            // Ignore if excluded_usernames doesn't exist
        }

        // Ensure correct unique index on (poll_id, voter_user_id, voted_for_user_id)
        $idx_stmt = $db->query("SHOW INDEX FROM peer_votes");
        $indexes = $idx_stmt ? $idx_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        // Group indexes by name -> ordered column list
        $idx_by_name = [];
        foreach ($indexes as $row) {
            $k = $row['Key_name'];
            if (!isset($idx_by_name[$k])) { $idx_by_name[$k] = []; }
            $idx_by_name[$k][(int)$row['Seq_in_index']] = $row['Column_name'];
        }
        foreach ($idx_by_name as $k => $cols) {
            ksort($cols); // order by sequence
            $cols = array_values($cols);
            $idx_by_name[$k] = $cols;
        }

        // Drop any legacy unique index that blocks multi-candidate votes (unique on poll_id or poll_id+voter_user_id)
        foreach ($indexes as $row) {
            if ((int)$row['Non_unique'] === 0) { // unique index
                $k = $row['Key_name'];
                $cols = $idx_by_name[$k] ?? [];
                if ($cols === ['poll_id'] || $cols === ['poll_id', 'voter_user_id']) {
                    try { $db->exec("ALTER TABLE peer_votes DROP INDEX `" . str_replace('`','', $k) . "`"); } catch (Exception $ex) { /* ignore */ }
                }
            }
        }

        // Re-check if correct unique index exists
        $idx_stmt2 = $db->query("SHOW INDEX FROM peer_votes WHERE Key_name = 'unique_vote'");
        $idx_rows2 = $idx_stmt2 ? $idx_stmt2->fetchAll(PDO::FETCH_ASSOC) : [];
        $has_correct_unique = false;
        if ($idx_rows2) {
            $cols = array_column($idx_rows2, 'Column_name');
            if ($cols === ['poll_id', 'voter_user_id', 'voted_for_user_id']) {
                $has_correct_unique = true;
            }
        }
        if (!$has_correct_unique) {
            try { $db->exec("ALTER TABLE peer_votes DROP INDEX unique_vote"); } catch (Exception $ex) { /* ignore */ }
            try {
                $db->exec("ALTER TABLE peer_votes ADD UNIQUE KEY `unique_vote` (`poll_id`, `voter_user_id`, `voted_for_user_id`)");
            } catch (Exception $ex) { /* ignore if already added */ }
        }
    } catch (Exception $e) {
    }
    // --------------------------------
} catch (PDOException $e) {
    $_SESSION['error'] = 'មានបញ្ហាក្នុងការតភ្ជាប់ទិន្នន័យ។ សូមព្យាយាមម្តងទៀត។';
    error_log("DB Connection Error: " . $e->getMessage());
    header("Location: error.php");
    exit();
}

$current_user_id = (int)$_SESSION['user_id'];
$message = '';
$message_type = ''; // success or danger

// Poll question constants used in multiple sections
$poll_question_office = 'បុគ្គលិកឆ្នើមការិយាល័យកណ្តាល ប្រចាំត្រីមាសទី៤ នៃឆ្នាំ២០២៥';
$poll_question_wh_legacy = 'បុគ្គលិកឆ្នើមខាងឃ្លាំងប្រចាំត្រីមាសទី៤ នៃឆ្នាំ២០២៥';
$poll_question_psp = 'បុគ្គលិកឆ្នើមខាងឃ្លាំងPSP ប្រចាំត្រីមាសទី៤ នៃឆ្នាំ២០២៥';
$poll_question_ckd = 'បុគ្គលិកឆ្នើមខាងឃ្លាំងCKD ប្រចាំត្រីមាសទី៤ នៃឆ្នាំ២០២៥';

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vote_submit'])) {
    $poll_id = filter_input(INPUT_POST, 'poll_id', FILTER_VALIDATE_INT);

    // Determine current user's department for POST handling
    $current_user_department_post = '';
    try {
        $stmt_dep = $db->prepare("SELECT LOWER(department) AS department FROM users WHERE id = :uid LIMIT 1");
        $stmt_dep->execute(['uid' => $current_user_id]);
        $row_dep = $stmt_dep->fetch(PDO::FETCH_ASSOC);
        if ($row_dep && !empty($row_dep['department'])) {
            $current_user_department_post = $row_dep['department'];
        }
    } catch (PDOException $e) {
    }

    $eligible_wh_depts_post = ['assistant-wh','manager-wh','assistant-wh-ckd','assistant-wh-psp','manager-wh-ckd','wh-manager-psp','manager-store-318','assistant-general-manager-stock','general-manager-stock'];

    // If eligible WH dept: accept quantity inputs per candidate via votes[user_id]
    $votes_map = [];
    if (isset($_POST['votes']) && is_array($_POST['votes']) && in_array($current_user_department_post, $eligible_wh_depts_post, true)) {
        foreach ($_POST['votes'] as $uid => $qty) {
            $uid = (int)$uid;
            $qty = (int)$qty;
            if ($uid > 0 && $qty > 0) {
                $votes_map[$uid] = $qty;
            }
        }
    }

    // Also support legacy inputs (radio/checkbox) for non-eligible departments
    $selected_ids = [];
    if (empty($votes_map) && isset($_POST['voted_for_user_id'])) {
        if (is_array($_POST['voted_for_user_id'])) {
            $selected_ids = array_filter(array_map('intval', $_POST['voted_for_user_id']));
        } else {
            $single = filter_input(INPUT_POST, 'voted_for_user_id', FILTER_VALIDATE_INT);
            if ($single) { $selected_ids = [$single]; }
        }
    }

    if ($poll_id && (!empty($votes_map) || !empty($selected_ids))) {
        // Fetch poll question to apply special per-poll rules
        $single_vote_polls = [
            $poll_question_office,
            $poll_question_wh_legacy
        ];
        $poll_question = '';
        try {
            $stmt_q = $db->prepare("SELECT question FROM polls WHERE id = :pid LIMIT 1");
            $stmt_q->execute(['pid' => $poll_id]);
            $poll_question = (string)($stmt_q->fetchColumn() ?: '');
        } catch (PDOException $e) { }
        // Vote limit resolution: default 3; eligible WH depts fixed at 3; specific polls fixed at 1
        $vote_limit = 3;
        try {
            $stmt_limit = $db->prepare("SELECT COALESCE(vote_limit, 3) AS vote_limit FROM users WHERE id = :uid");
            $stmt_limit->execute(['uid' => $current_user_id]);
            $row_limit = $stmt_limit->fetch(PDO::FETCH_ASSOC);
            if ($row_limit && isset($row_limit['vote_limit'])) {
                $vote_limit = (int)$row_limit['vote_limit'];
            }
        } catch (PDOException $e) {
        }
        if (in_array($current_user_department_post, $eligible_wh_depts_post, true)) {
            $vote_limit = 3; // enforce 3 for eligible WH departments
        }
        if (in_array($poll_question, $single_vote_polls, true)) {
            $vote_limit = 1; // enforce single vote for specified polls
        }

        try {
            // Current votes already cast in this poll by this user
            $stmt_count = $db->prepare("SELECT SUM(COALESCE(vote_count, 1)) FROM peer_votes WHERE poll_id = :poll AND voter_user_id = :voter");
            $stmt_count->execute(['poll' => $poll_id, 'voter' => $current_user_id]);
            $already = (int)$stmt_count->fetchColumn();
            $remaining = max(0, $vote_limit - $already);

            if (!empty($votes_map)) {
                // If this is a single-vote poll, collapse to legacy single selection
                if ($vote_limit === 1) {
                    // pick first positive quantity candidate
                    $first = null;
                    foreach ($votes_map as $uid => $qty) { if ($qty > 0) { $first = $uid; break; } }
                    $votes_map = [];
                    $selected_ids = $first ? [$first] : [];
                }
                // Enforce per-person cap of 3
                foreach ($votes_map as $k => $v) { $votes_map[$k] = max(0, min(3, (int)$v)); }
                $total_requested = array_sum($votes_map);
                if ($total_requested <= 0) {
                    $message = 'សូមបញ្ចូលចំនួនបោះឆ្នោតយ៉ាងហោចណាស់ 1។';
                    $message_type = 'danger';
                } else if ($total_requested > $vote_limit) {
                    $message = 'អ្នកអាចបោះឆ្នោតបានត្រឹមតែ ' . $vote_limit . ' ដងសម្រាប់ការបោះឆ្នោតនេះ។';
                    $message_type = 'danger';
                } else if ($already >= $vote_limit) {
                    $message = 'អ្នកបានប្រើសិទ្ធិបោះឆ្នោតគ្រប់ ' . $vote_limit . ' ដងរួចហើយសម្រាប់ការបោះឆ្នោតនេះ។';
                    $message_type = 'danger';
                } else if ($total_requested > $remaining) {
                    $message = 'សរុបចំនួនបោះឆ្នោតលើសសិទ្ធិដែលនៅសល់ (' . $remaining . ')។';
                    $message_type = 'danger';
                } else {
                    $db->beginTransaction();
                    // Upsert to keep cumulative quantities if the same voter votes again for the same person
                    $stmt_ins = $db->prepare(
                        "INSERT INTO peer_votes (poll_id, voter_user_id, voted_for_user_id, vote_count)
                         VALUES (:poll_id, :voter_user_id, :voted_for_user_id, :vote_count)
                         ON DUPLICATE KEY UPDATE vote_count = LEAST(3, vote_count + VALUES(vote_count))"
                    );

                    $inserted = 0;
                    foreach ($votes_map as $uid => $qty) {
                        try {
                            $stmt_ins->execute([
                                'poll_id' => $poll_id,
                                'voter_user_id' => $current_user_id,
                                'voted_for_user_id' => $uid,
                                'vote_count' => $qty
                            ]);
                            $inserted += $qty;
                        } catch (PDOException $e) {
                            // Gracefully skip duplicate/constraint errors but log for diagnostics
                            $code = $e->getCode();
                            if ($code === '23000') { // integrity constraint violation
                                continue;
                            }
                            throw $e; // rethrow unexpected errors
                        }
                    }
                    $db->commit();
                    $message = 'ការបោះឆ្នោតរបស់អ្នកត្រូវបានកត់ត្រា ' . $inserted . ' ដង។';
                    $message_type = 'success';
                }
            } else {
                // Legacy path: single selection or multi without quantities
                $selected_ids = array_values(array_unique($selected_ids));
                if (empty($selected_ids)) {
                    $message = 'សូមជ្រើសរើសបុគ្គលិកយ៉ាងហោចណាស់ ១ នាក់។';
                    $message_type = 'danger';
                } else if (count($selected_ids) > $remaining) {
                    $message = 'អ្នកអាចជ្រើសរើសបានច្រើនបំផុត ' . $remaining . ' នាក់សម្រាប់ការបោះឆ្នោតនេះ។';
                    $message_type = 'danger';
                } else {
                    $db->beginTransaction();
                    $stmt_ins = $db->prepare("INSERT INTO peer_votes (poll_id, voter_user_id, voted_for_user_id, vote_count) VALUES (:poll_id, :voter_user_id, :voted_for_user_id, 1)");
                    $inserted = 0;
                    foreach ($selected_ids as $vid) {
                        try {
                            $stmt_ins->execute(['poll_id' => $poll_id, 'voter_user_id' => $current_user_id, 'voted_for_user_id' => $vid]);
                            $inserted++;
                        } catch (PDOException $e) {
                            $code = $e->getCode();
                            if ($code === '23000') {
                                continue;
                            }
                            throw $e;
                        }
                    }
                    $db->commit();
                    $message = 'ការបោះឆ្នោតរបស់អ្នកត្រូវបានកត់ត្រា ' . $inserted . ' ដង។';
                    $message_type = 'success';
                }
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            $message = 'មានបញ្ហាក្នុងការកត់ត្រាការបោះឆ្នោតរបស់អ្នក។ សូមព្យាយាមម្តងទៀត។ Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    } else {
        $message = 'ការបោះឆ្នោតមិនត្រឹមត្រូវ។';
        $message_type = 'danger';
    }
}

// Fetch active polls
$polls = [];
try {
    // Get the current user's department for filtering
    $current_user_department = '';
    $current_user_name = $_SESSION['username'] ?? 'Unknown User';
    
    $stmt_user_info = $db->prepare("SELECT full_name, department, username FROM users WHERE id = :user_id LIMIT 1");
    $stmt_user_info->execute(['user_id' => $current_user_id]);
    $user_info = $stmt_user_info->fetch(PDO::FETCH_ASSOC);
    if ($user_info) {
        if (!empty($user_info['full_name'])) {
            $current_user_name = $user_info['full_name'];
        }
        $current_user_department = strtolower($user_info['department'] ?? '');
        $current_username = strtolower($user_info['username'] ?? '');
    }
    
    // DEBUG: Log current user's department
    
    // Define allowed departments for Worker to vote for (warehouse managers)
    $worker_allowed_vote_departments = [
        'manager-wh-ckd',
        'assistant-wh-ckd',
        'assistant-general-manager-stock',
        'general-manager-stock',
        'assistant-wh-psp',
        'manager-store-318'
    ];
    
    // Legacy single warehouse poll (kept for non-eligible depts if present)
    $worker_allowed_poll = $poll_question_wh_legacy;
    
    // Fetch active polls - filter based on user's department
    // Determine eligible departments for special warehouse voting
    $eligible_wh_depts = [
        'assistant-wh','manager-wh','assistant-wh-ckd','assistant-wh-psp','manager-wh-ckd','wh-manager-psp','manager-store-318','assistant-general-manager-stock','general-manager-stock'
    ];

    // Fetch all active polls for all users; visibility and candidate logic will handle restrictions
    $stmt = $db->prepare("SELECT id, question, is_active, exempted_usernames, allowed_usernames FROM polls WHERE start_date <= NOW() AND end_date >= NOW() AND is_active = 1 ORDER BY created_at DESC");
    $stmt->execute();
    $active_polls = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // DEBUG: Log active polls count and questions

    foreach ($active_polls as $poll) {
        $poll_id = $poll['id'];
        $poll_data = $poll;
        $poll_data['user_voted'] = false;
        $poll_data['employees'] = [];

        // Check visibility: if allowed_usernames is set, only allow those users
        $allowed_usernames = [];
        if (!empty($poll['allowed_usernames'])) {
            $parts = array_map('trim', explode(',', strtolower($poll['allowed_usernames'])));
            $allowed_usernames = array_filter($parts);
        }
        if (!empty($allowed_usernames) && !in_array($current_username, $allowed_usernames, true)) {
            continue; // Skip this poll if user is not allowed
        }

        $base_excluded_usernames = ['admin','adminbt','bt123','it','test'];
        $extra_excluded = [];
        if (!empty($poll['exempted_usernames'])) {
            $parts = array_map('trim', explode(',', strtolower($poll['exempted_usernames'])));
            $extra_excluded = array_filter($parts);
        }
        $excluded_usernames = array_values(array_unique(array_merge($base_excluded_usernames, $extra_excluded)));
        
        // Check if user has voted for this poll
        $stmt_voted = $db->prepare("SELECT id FROM peer_votes WHERE poll_id = :poll_id AND voter_user_id = :user_id");
        $stmt_voted->execute(['poll_id' => $poll_id, 'user_id' => $current_user_id]);
        if ($stmt_voted->fetch()) {
            $poll_data['user_voted'] = true;
        }

        // Fetch employees to vote for based on poll question first, then fall back to department rules
        $is_psp_poll = ($poll['question'] === $poll_question_psp);
        $is_ckd_poll = ($poll['question'] === $poll_question_ckd);
        $is_wh_legacy_poll = ($poll['question'] === $poll_question_wh_legacy);
        $is_office_poll = ($poll['question'] === $poll_question_office);
        $is_single_vote_poll = ($is_wh_legacy_poll || $is_office_poll);

        if ($is_psp_poll || $is_ckd_poll) {
            // PSP/CKD polls: only workers are candidates
            $stmt_employees = $db->prepare("SELECT id, full_name, username FROM users WHERE LOWER(department) = 'worker' AND (status = 'active' OR status IS NULL) AND LOWER(username) NOT IN ('admin', 'adminbt', 'bt123', 'it', 'test') ORDER BY full_name ASC, username ASC");
            $stmt_employees->execute();
        } else if ($is_single_vote_poll) {
            // Single-vote polls: constrain candidates only when voter is worker on warehouse poll
            if ($is_wh_legacy_poll && $current_user_department === 'worker') {
                $allowed_depts = [
                    'manager-wh-ckd',
                    'assistant-wh-ckd',
                    'assistant-general-manager-stock',
                    'general-manager-stock',
                    'assistant-wh-psp',
                    'manager-store-318'
                ];
                $allowed_depts = array_values(array_unique(array_map('strtolower', $allowed_depts)));
                $dept_placeholders = implode(',', array_fill(0, count($allowed_depts), '?'));
                $sql = "SELECT id, full_name, username FROM users WHERE LOWER(department) IN ($dept_placeholders) AND (status = 'active' OR status IS NULL) AND LOWER(username) NOT IN ('admin', 'adminbt', 'bt123', 'it', 'test') ORDER BY full_name ASC, username ASC";
                $params = $allowed_depts;
                $stmt_employees = $db->prepare($sql);
                $stmt_employees->execute($params);
            } else if ($is_office_poll) {
                // Central Office poll: show all employees
                $stmt_employees = $db->prepare("SELECT id, full_name, username FROM users WHERE (status = 'active' OR status IS NULL) AND LOWER(username) NOT IN ('admin', 'adminbt', 'bt123', 'it', 'test') ORDER BY full_name ASC, username ASC");
                $stmt_employees->execute();
            } else {
                // Other users on warehouse poll: show all (excluding admin/test accounts)
                $stmt_employees = $db->prepare("SELECT id, full_name, username FROM users WHERE (status = 'active' OR status IS NULL) AND LOWER(username) NOT IN ('admin', 'adminbt', 'bt123', 'it', 'test','ly') ORDER BY full_name ASC, username ASC");
                $stmt_employees->execute();
            }
        } else if ($current_user_department === 'worker') {
            // Worker can only vote for specific warehouse manager departments (legacy rule)
            $dept_placeholders = implode(',', array_fill(0, count($worker_allowed_vote_departments), '?'));
            $sql = "SELECT id, full_name, username FROM users WHERE LOWER(department) IN ($dept_placeholders) AND (status = 'active' OR status IS NULL) AND LOWER(username) NOT IN ('admin', 'adminbt', 'bt123', 'it', 'test') ORDER BY full_name ASC, username ASC";
            $params = $worker_allowed_vote_departments;
            $stmt_employees = $db->prepare($sql);
            $stmt_employees->execute($params);
        } else if (in_array($current_user_department, ['assistant-general-manager-stock', 'general-manager-stock'], true)) {
            // Specific managers vote for workers
            $stmt_employees = $db->prepare("SELECT id, full_name, username FROM users WHERE LOWER(department) = 'worker' AND (status = 'active' OR status IS NULL) AND LOWER(username) NOT IN ('admin', 'adminbt', 'bt123', 'it', 'test') ORDER BY full_name ASC, username ASC");
            $stmt_employees->execute();
        } else if (in_array($current_user_department, $eligible_wh_depts, true)) {
            // Eligible WH users: default to all employees for other polls
            $stmt_employees = $db->prepare("SELECT id, full_name, username FROM users WHERE (status = 'active' OR status IS NULL) AND LOWER(username) NOT IN ('admin', 'adminbt', 'bt123', 'it', 'test') ORDER BY full_name ASC, username ASC");
            $stmt_employees->execute();
        } else {
            // Other users can vote for all employees (excluding admin and specific usernames)
            $stmt_employees = $db->prepare("SELECT id, full_name, username FROM users WHERE (role != 'admin' OR role IS NULL) AND (status = 'active' OR status IS NULL) AND LOWER(username) NOT IN ('admin', 'adminbt', 'bt123', 'it', 'test') ORDER BY full_name ASC, username ASC");
            $stmt_employees->execute();
        }
        $poll_data['employees'] = $stmt_employees->fetchAll(PDO::FETCH_ASSOC);

        // Apply per-poll excluded usernames to filter out exempted users from the candidates list
        if (!empty($poll_data['employees'])) {
            $poll_data['employees'] = array_values(array_filter($poll_data['employees'], function($emp) use ($excluded_usernames) {
                $uname = strtolower($emp['username'] ?? '');
                return $uname !== '' && !in_array($uname, $excluded_usernames, true);
            }));
        }

        // --- DEBUGGING: Logs fetched employees to your server error log ---
        // --- END DEBUGGING ---

        // For office poll, get list of employees who haven't voted
        if ($is_office_poll) {
            // Get eligible usernames: all users except workers, not in excluded
            $excluded_list = [];
            if (!empty($poll['exempted_usernames'])) {
                $excluded_list = array_map('trim', explode(',', strtolower($poll['exempted_usernames'])));
            }
            $stmt_eligible = $db->prepare("SELECT username, full_name FROM users WHERE LOWER(department) != 'worker' AND LOWER(username) NOT IN ('admin', 'adminbt', 'bt123', 'it', 'test') ORDER BY full_name ASC, username ASC");
            $stmt_eligible->execute();
            $eligible_users = $stmt_eligible->fetchAll(PDO::FETCH_ASSOC);
            $eligible_usernames = array_map('strtolower', array_column($eligible_users, 'username'));
            $eligible_usernames = array_diff($eligible_usernames, $excluded_list);
            
            // Get voted usernames
            $stmt_voted = $db->prepare("SELECT DISTINCT LOWER(u.username) AS username FROM peer_votes pv JOIN users u ON pv.voter_user_id = u.id WHERE pv.poll_id = :poll_id");
            $stmt_voted->execute(['poll_id' => $poll_id]);
            $voted_usernames = $stmt_voted->fetchAll(PDO::FETCH_COLUMN);
            
            // Non-voters
            $non_voted_usernames = array_diff($eligible_usernames, $voted_usernames);
            $non_voters = array_filter($eligible_users, function($user) use ($non_voted_usernames) {
                return in_array(strtolower($user['username']), $non_voted_usernames);
            });
            $poll_data['non_voters'] = array_values($non_voters);
        }

        $polls[] = $poll_data;
    }

} catch (PDOException $e) {
    $_SESSION['error'] = 'មានបញ្ហាក្នុងការទាញទិន្នន័យការបោះឆ្នោត។ សូមព្យាយាមម្តងទៀត។';
    header("Location: error.php");
    exit();
}

$current_page = 'employee_voting.php'; // For bottom navigation active state
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#4f46e5">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png">
    <title>Employee Voting - HRM Dashboard</title>
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
            max-width: 800px;
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
        .form-select {
            margin-bottom: 15px;
            border-radius: 8px;
            border-color: #ced4da;
            padding: 0.75rem 1.25rem;
            font-size: 1rem;
            color: var(--dark);
        }
        .form-select:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 0.25rem rgba(79, 70, 229, 0.25);
        }
        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
        }
        .btn-primary:hover {
            background-color: var(--primary-light);
            border-color: var(--primary-light);
        }
        .employee-name-text {
            font-weight: 500;
            color: var(--dark);
        }

        /* Employee Checkbox List Styles */
        .employee-checkbox-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            max-height: 400px;
            overflow-y: auto;
            padding: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
        }
        .employee-checkbox-list .form-check {
            margin: 0;
            padding: 12px 15px;
            background: white;
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            transition: all 0.2s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .employee-checkbox-list .form-check:hover {
            border-color: var(--primary-light);
            background: #f0f0ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.15);
        }
        .employee-checkbox-list .form-check-input {
            width: 20px;
            height: 20px;
            margin: 0;
            cursor: pointer;
            border: 2px solid var(--gray);
        }
        .employee-checkbox-list .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        .employee-checkbox-list .form-check-input:focus {
            box-shadow: 0 0 0 0.25rem rgba(79, 70, 229, 0.25);
        }
        .employee-checkbox-list .form-check-label {
            cursor: pointer;
            font-weight: 500;
            color: var(--dark);
            font-size: 0.95rem;
            margin: 0;
            padding: 0;
        }
        .employee-checkbox-list .form-check:has(.form-check-input:checked) {
            border-color: var(--primary);
            background: linear-gradient(135deg, #eef2ff, #e0e7ff);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }

        @media (max-width: 768px) {
            .employee-checkbox-list {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
                padding: 10px;
            }
            .employee-checkbox-list .form-check {
                padding: 10px 12px;
            }
            .employee-checkbox-list .form-check-label {
                font-size: 0.85rem;
            }
            .employee-checkbox-list .form-check-input {
                width: 18px;
                height: 18px;
            }
        }

        @media (max-width: 480px) {
            .employee-checkbox-list {
                grid-template-columns: 1fr 1fr;
            }
        }

        /* Bottom Navigation - Copied from homes.php for consistency */
        .bottom-nav {
            display: flex;
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
            .form-select {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
            .btn-primary {
                padding: 0.6rem 1.2rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <header class="app-header">
            <a href="homes.php">
                <div class="logo-container">
                    <img src="https://i.ibb.co/k6ysLFZd/Logo-Van-Van-1.png" alt="HRM Logo" class="logo-img">
                    <h1 class="app-title">HR App</h1>
                </div>
            </a>
            <div class="user-profile">
                <span class="user-name" style="font-weight: 600; color: var(--dark);">
                    <?php echo htmlspecialchars($current_user_name); // Display the fetched full name or username ?>
                </span>
                <a href="https://app.vvc.asia/admin/profile.php" class="user-avatar" title="Profile">
                    <i class="fas fa-user"></i>
                </a>
                <a href="?logout=true" class="btn btn-danger btn-sm">ចាកចេញ</a>
            </div>
        </header>

        <h2 class="mb-4" style="font-weight: 600; color: var(--dark);">ការបោះឆ្នោតបុគ្គលិក</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($polls)): ?>
            <div class="alert alert-info text-center">
                មិនទាន់មានការបោះឆ្នោតសកម្មណាមួយសម្រាប់បុគ្គលិកបោះឆ្នោតនៅពេលនេះទេ។
            </div>
        <?php else: ?>
            <?php foreach ($polls as $poll): ?>
                <?php if (!$poll['is_active']) continue; ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <?php echo htmlspecialchars($poll['question']); ?>
                    </div>
                    <div class="card-body">
                        <?php if ($poll['user_voted']): ?>
                            <p class="text-success fw-bold">អ្នកបានបោះឆ្នោតរួចហើយសម្រាប់ការបោះឆ្នោតនេះ។ សូមអរគុណ!</p>
                            <!-- Results are intentionally hidden as per requirement -->
                        <?php else: ?>
                            <?php if (empty($poll['employees'])): ?>
                                <div class="alert alert-warning">
                                    មិនមានបុគ្គលិកផ្សេងទៀតដើម្បីបោះឆ្នោតឱ្យទេ។ (សូមប្រាកដថាមានបុគ្គលិកច្រើនជាងម្នាក់នៅក្នុងប្រព័ន្ធ និងមានឈ្មោះត្រឹមត្រូវ)
                                </div>
                            <?php else: ?>
                                <?php
                                    $single_vote_polls = [
                                        'បុគ្គលិកឆ្នើមការិយាល័យកណ្តាល ប្រចាំត្រីមាសទី៤ នៃឆ្នាំ២០២៥',
                                        'បុគ្គលិកឆ្នើមខាងឃ្លាំងប្រចាំត្រីមាសទី៤ នៃឆ្នាំ២០២៥'
                                    ];
                                    $is_single_vote_poll = in_array($poll['question'], $single_vote_polls, true);
                                ?>
                                <form action="employee_voting.php" method="POST">
                                    <input type="hidden" name="poll_id" value="<?php echo $poll['id']; ?>">
                                    <?php if (!$is_single_vote_poll) { ?>
                                        <?php if (in_array($current_user_department, ['assistant-wh','manager-wh','assistant-wh-ckd','assistant-wh-psp','manager-wh-ckd','wh-manager-psp','general-manager-stock','assistant-general-manager-stock','manager-store-318'], true)) { ?>
                                            <input type="hidden" name="eligible_wh" value="1">
                                        <?php } ?>
                                        <input type="hidden" name="eligible_wh" value="1">
                                    <?php } ?>
                                    <p class="mb-3">អ្នកបោះឆ្នោត: <span class="fw-bold"><?php echo htmlspecialchars($current_user_name); ?></span></p>
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <?php
                                                $limit_hint = 1;
                                                if ($is_single_vote_poll) {
                                                    echo 'សូមជ្រើសរើសបុគ្គលិកម្នាក់តែប៉ុណ្ណោះ៖ ';
                                                } else if (in_array($current_user_department, ['assistant-wh','manager-wh','assistant-wh-ckd','assistant-wh-psp','manager-wh-ckd','wh-manager-psp','general-manager-stock','assistant-general-manager-stock','manager-store-318'], true)) {
                                                    $limit_hint = 3; // fixed 3 total for eligible WH departments
                                                    echo 'សូមជ្រើសរើសឈ្មោះ និងដាក់ចំនួនសរុបមិនលើស ' . $limit_hint . ' ដង (មនុស្សម្នាក់អាចដាក់បានច្រើនបំផុត 3 ដង)';
                                                } else {
                                                    echo 'សូមជ្រើសរើសបុគ្គលិកម្នាក់ដើម្បីបោះឆ្នោតឱ្យ:';
                                                }
                                            ?>
                                        </label>
                                        <div class="employee-checkbox-list">
                                            <?php foreach ($poll['employees'] as $employee): ?>
                                                <div class="form-check">
                                                    <?php if (!$is_single_vote_poll) { ?>
                                                        <input class="form-check-input" type="checkbox" name="voted_for_user_id[]" id="employee_<?php echo $poll['id']; ?>_<?php echo $employee['id']; ?>" value="<?php echo $employee['id']; ?>">
                                                        <label class="form-check-label" for="employee_<?php echo $poll['id']; ?>_<?php echo $employee['id']; ?>">
                                                            <?php echo htmlspecialchars($employee['full_name'] ?? $employee['username']); ?>
                                                        </label>
                                                        <?php if (in_array($current_user_department, ['assistant-wh','manager-wh','assistant-wh-ckd','assistant-wh-psp','manager-wh-ckd','wh-manager-psp','general-manager-stock','assistant-general-manager-stock','manager-store-318'], true)) { ?>
                                                            <input style="margin-left:auto; width:42px; padding-right:6px;" class="form-control form-control-sm qty-input" type="number" min="0" max="3" step="1" inputmode="numeric" name="votes[<?php echo $employee['id']; ?>]" value="0" disabled>
                                                        <?php } ?>
                                                    <?php } else { ?>
                                                        <input class="form-check-input" type="radio" name="voted_for_user_id" id="employee_<?php echo $poll['id']; ?>_<?php echo $employee['id']; ?>" value="<?php echo $employee['id']; ?>" required>
                                                        <label class="form-check-label" for="employee_<?php echo $poll['id']; ?>_<?php echo $employee['id']; ?>">
                                                            <?php echo htmlspecialchars($employee['full_name'] ?? $employee['username']); ?>
                                                        </label>
                                                    <?php } ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <button type="submit" name="vote_submit" class="btn btn-primary mt-3">បោះឆ្នោត</button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav">
        <a href="homes.php" class="nav-item <?php echo $current_page === 'homes.php' ? 'active' : ''; ?>">
            <i class="fas fa-home nav-icon"></i>
            <span>ទំព័រដើម</span>
        </a>
        <a href="checklist.php" class="nav-item <?php echo $current_page === 'checklist.php' ? 'active' : ''; ?>">
            <i class="fas fa-tasks nav-icon"></i>
            <span>ការងារ</span>
        </a>
        <a href="announcements.php" class="nav-item <?php echo $current_page === 'announcements.php' ? 'active' : ''; ?>">
            <i class="fas fa-bell nav-icon"></i>
            <span>ដំណឹង</span>
        </a>
        <a href="https://app.vvc.asia/admin/profile.php" class="nav-item <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user nav-icon"></i>
            <span>គណនី</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            if (!form.querySelector('input[name="eligible_wh"][value="1"]')) return;
            const LIMIT = 3; // total per poll
            const PER_PERSON_LIMIT = 3; // per candidate
            const checkboxes = form.querySelectorAll('input.form-check-input[type="checkbox"][name="voted_for_user_id[]"]');
            const qtyInputs = form.querySelectorAll('input.qty-input[name^="votes["]');
            const submitBtn = form.querySelector('button[type="submit"]');

            const sumQty = () => {
                let s = 0;
                qtyInputs.forEach(inp => { s += parseInt(inp.value || '0', 10) || 0; });
                return s;
            };
            const updateState = () => {
                const total = sumQty();
                if (submitBtn) submitBtn.disabled = total === 0 || total > LIMIT;
            };

            checkboxes.forEach(cb => {
                const id = cb.value;
                const qty = form.querySelector(`input.qty-input[name="votes[${id}]"]`);
                cb.addEventListener('change', () => {
                    if (!qty) return;
                    if (cb.checked) {
                        if ((parseInt(qty.value || '0', 10) || 0) === 0) qty.value = '1';
                        qty.disabled = false;
                    } else {
                        qty.value = '0';
                        qty.disabled = true;
                    }
                    updateState();
                });
            });

            qtyInputs.forEach(inp => {
                inp.addEventListener('input', () => {
                    let val = parseInt(inp.value || '0', 10) || 0;
                    if (val < 0) val = 0;
                    if (val > PER_PERSON_LIMIT) val = PER_PERSON_LIMIT;
                    inp.value = String(val);
                    // Sync checkbox
                    const m = inp.name.match(/votes\[(\d+)\]/);
                    if (m) {
                        const cb = form.querySelector(`input.form-check-input[type="checkbox"][name="voted_for_user_id[]"][value="${m[1]}"]`);
                        if (cb) {
                            cb.checked = val > 0;
                        }
                    }
                    // Enforce total limit by capping current input
                    let total = sumQty();
                    if (total > LIMIT) {
                        const over = total - LIMIT;
                        const newVal = Math.max(0, val - over);
                        inp.value = String(newVal);
                        // Recalculate
                    }
                    updateState();
                });
            });

            // Initialize state
            updateState();
        });
    });
    </script>
</body>
</html>