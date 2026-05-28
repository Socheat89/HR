<?php
// Start session for user tracking
session_start();

// Load Theme Config
$themeConfigPath = __DIR__ . '/includes/theme_config.json';
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


// --- CONFIGURATION ---
require_once __DIR__ . '/includes/db.php';

// --- PAGINATION SETUP ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 50; // Default 50 per page, max 100
$offset = ($page - 1) * $limit;

// --- END CONFIGURATION ---

if (!defined('BASE_URL')) {
    define('BASE_URL', 'table_report.php');
}

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
require_once __DIR__ . '/includes/telegram.php';

// Map $conn to $pdo if needed
if (isset($conn)) {
    $pdo = $conn;
}

// Get existing columns for requests table to allow conditional inserts/fields
try {
    $stmtCols = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'requests'");
    // $dbname is defined in includes/db.php
    $stmtCols->execute([$dbname]);
    $existingRequestColumns = $stmtCols->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $existingRequestColumns = [];
}

// Fetch current user details
$currentUserFullName = 'អ្នកប្រើមិនស្គាល់';
$currentUserId = null;
if (isset($_SESSION['user_id'])) {
    $currentUserId = $_SESSION['user_id'];
    $stmtUser = $pdo->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute([$currentUserId]);
    $user = $stmtUser->fetch();
    if ($user) {
        $currentUserFullName = $user['full_name'];
    }
} else {
    header("Location: login.php");
    exit;
}

$error = null;
$success = null;

// Define request fields
$requestFields = [
    'request_type', 'user_id', 'requester_name', 'number_of_days', 'remaining_days',
    'department', 'position', 'branch', 'department_head_name', 'request_date', 'return_date',
    'late_hours', 'forgot_scan_in', 'forgot_scan_out', 'time_in', 'time_out',
    'total_hours', 'repay_time_in', 'repay_time_out', 'repay_total_hours',
    'reason', 'assigned_to', 'location', 'contact_number', 'status', 'signature', 'signature_date'
];

// --- HANDLE AJAX: GET REQUEST DETAILS ---
if (isset($_GET['action']) && $_GET['action'] === 'get_request_details' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $reqId = (int)$_GET['id'];
    
    $stmt = $pdo->prepare("SELECT * FROM requests WHERE id = ?");
    $stmt->execute([$reqId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($request) {
        echo json_encode(['success' => true, 'data' => $request]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
    }
    exit;
}

// --- HANDLE AJAX: GET LATEST SIGNATURE ---
if (isset($_GET['action']) && $_GET['action'] === 'get_latest_signature' && (isset($_GET['user_id']) || isset($_GET['requester_name']))) {
    header('Content-Type: application/json');
    $uId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    $rName = $_GET['requester_name'] ?? null;
    
    if ($uId) {
        $stmt = $pdo->prepare("SELECT signature, signature_date FROM requests WHERE user_id = ? AND signature IS NOT NULL AND signature != '' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$uId]);
    } else {
        $stmt = $pdo->prepare("SELECT signature, signature_date FROM requests WHERE requester_name = ? AND signature IS NOT NULL AND signature != '' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$rName]);
    }
    
    $sig = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $sig]);
    exit;
}

// --- HANDLE AJAX: GET SIGNATURE HISTORY ---

// --- HANDLE AJAX: QUICK SIGNATURE UPLOAD ---
if (isset($_GET['action']) && $_GET['action'] === 'quick_signature_upload' && isset($_POST['request_id'])) {
    header('Content-Type: application/json');
    try {
        $requestId = (int)$_POST['request_id'];
        $signatureData = $_POST['signature_data'] ?? '';
        $isDeptHead = ($_POST['is_department_head'] ?? '0') === '1';

        if (!$requestId) throw new Exception('Request ID missing');
        if (!$signatureData) throw new Exception('Signature data missing');

        // Fetch original
        $stmt = $pdo->prepare("SELECT * FROM requests WHERE id = ?");
        $stmt->execute([$requestId]);
        $original = $stmt->fetch();
        if (!$original) throw new Exception('Request not found');

        // History
        $oldSig = $isDeptHead ? $original['department_head_signature'] : $original['signature'];
        if ($oldSig) {
            $stmtHist = $pdo->prepare("INSERT INTO signature_history (request_id, old_signature, changed_by_user_id, changed_at) VALUES (?, ?, ?, NOW())");
            $stmtHist->execute([$requestId, $oldSig, $currentUserId]);
        }

        // Update
        if ($isDeptHead) {
            $stmtUpd = $pdo->prepare("UPDATE requests SET department_head_signature = ?, department_head_signature_date = NOW() WHERE id = ?");
        } else {
            $stmtUpd = $pdo->prepare("UPDATE requests SET signature = ?, signature_date = NOW() WHERE id = ?");
        }
        $stmtUpd->execute([$signatureData, $requestId]);

        // Get new history count
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM signature_history WHERE request_id = ?");
        $stmtCount->execute([$requestId]);
        $historyCount = $stmtCount->fetchColumn();

        echo json_encode(['success' => true, 'signature' => $signatureData, 'history_count' => $historyCount]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// --- HANDLE ADD NEW REQUEST (FIXED) ---
if (isset($_POST['submit_add_request'])) {
    $newRequestData = [];
    foreach ($requestFields as $field) {
        if ($field === 'signature') continue;
        $newRequestData[$field] = $_POST[$field] ?? null;
    }

    $finalUserId = null;
    $finalRequesterName = null;

    if (!empty($newRequestData['user_id'])) {
        $finalUserId = $newRequestData['user_id'];
        $stmtUser = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
        $stmtUser->execute([$finalUserId]);
        $user = $stmtUser->fetch();
        $finalRequesterName = $user ? $user['full_name'] : 'អ្នកប្រើមិនស្គាល់';
    } else {
        $finalUserId = $currentUserId;
        $finalRequesterName = $currentUserFullName;
    }

    $newRequestData['user_id'] = $finalUserId;
    $newRequestData['requester_name'] = $finalRequesterName;
    $newRequestData['created_at'] = date('Y-m-d H:i:s');

    // --- NEW: AUTO-PULL PREVIOUS SIGNATURE ---
    // If signature is not provided in $_POST (which it usually isn't in add form), 
    // fetch the latest signature from previous requests of this user.
    if (empty($newRequestData['signature'])) {
        try {
            $stmtSig = $pdo->prepare("SELECT signature, signature_date FROM requests WHERE user_id = ? AND signature IS NOT NULL AND signature != '' ORDER BY id DESC LIMIT 1");
            $stmtSig->execute([$finalUserId]);
            $prevSig = $stmtSig->fetch();
            if ($prevSig) {
                $newRequestData['signature'] = $prevSig['signature'];
                // Only pull date if signature existed
                if ($prevSig['signature_date']) {
                    $newRequestData['signature_date'] = $prevSig['signature_date'];
                } else {
                    $newRequestData['signature_date'] = date('Y-m-d');
                }
            }
        } catch (Exception $e) {
            // Silently fail if signature pull fails
        }
    }

    if (empty($newRequestData['request_type']) || empty($newRequestData['user_id']) || empty($newRequestData['request_date'])) {
        $error = "សូមបំពេញគ្រប់ Field ដែលមានសញ្ញា (*) នៅក្នុងទម្រង់បន្ថែម។";
    } else {
        try {
            $filteredRequestData = array_filter($newRequestData, function($key) use ($requestFields, $existingRequestColumns) {
                // Only keep keys that are in our allowed requestFields AND actually exist in the DB table
                // Note: 'signature' and 'signature_date' are in $requestFields
                return ($key === 'created_at') || (in_array($key, $requestFields) && in_array($key, $existingRequestColumns));
            }, ARRAY_FILTER_USE_KEY);

            $columns = implode(', ', array_keys($filteredRequestData));
            $placeholders = implode(', ', array_fill(0, count($filteredRequestData), '?'));
            $sql = "INSERT INTO requests ($columns) VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($filteredRequestData));
            $newId = $pdo->lastInsertId();

            $message = "🆕 *សំណើថ្មីត្រូវបានបន្ថែម*\n" . "អ្នកប្រើ (អ្នកបន្ថែម): $currentUserFullName\n" . "ប្រភេទស្នើសុំ: {$newRequestData['request_type']}\n" . "អ្នកស្នើសុំ: {$newRequestData['requester_name']}\n" . "កាលបរិច្ឆេទ: " . date('Y-m-d H:i:s');
            sendTelegramMessage($telegramChatId, $message);
            $_SESSION['success_message'] = "សំណើ (ID: $newId) ត្រូវបានបន្ថែមដោយជោគជ័យ។";
            header("Location: " . BASE_URL);
            exit;
        } catch (PDOException $e) {
            $error = "កំហុសក្នុងការបន្ថែមកំណត់ត្រា: " . $e->getMessage();
        }
    }
}


// --- HANDLE EDIT REQUEST (MODIFIED FOR SIGNATURE UPLOAD & HISTORY) ---
if (isset($_POST['edit_id'])) {
    $edit_id = (int)$_POST['edit_id'];
    $stmtOriginal = $pdo->prepare("SELECT r.*, u.full_name FROM requests r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
    $stmtOriginal->execute([$edit_id]);
    $originalRequest = $stmtOriginal->fetch();

    if (!$originalRequest) {
        $error = "រកមិនឃើញសំណើដែលត្រូវកែសម្រួលទេ។";
    } else {
        // Permission Check Logic
        $canEditThisRequest = false;
        if ($isAdmin || $originalRequest['user_id'] == $currentUserId) {
            $canEditThisRequest = true;
        } else {
            // Check if the current user is the manager of the requester
            $stmtManagerCheck = $pdo->prepare("SELECT manager_id FROM users WHERE id = ?");
            $stmtManagerCheck->execute([$originalRequest['user_id']]);
            $requester = $stmtManagerCheck->fetch();
            if ($requester && $requester['manager_id'] == $currentUserId) {
                $canEditThisRequest = true;
            }
        }

        if ($canEditThisRequest) {
            $updateFields = [];
            foreach ($requestFields as $field) {
                if ($field === 'signature') continue; // Skip signature field from regular POST data
                if (isset($_POST[$field])) {
                    $updateFields[$field] = $_POST[$field] === '' ? null : $_POST[$field];
                }
            }

            // --- NEW: HANDLE SIGNATURE UPDATE/DELETE ---
            // 1) Prefer processed data URL from client (already background-removed)
            if (isset($_POST['signature_data']) && is_string($_POST['signature_data']) && $_POST['signature_data'] !== '') {
                $dataUrl = $_POST['signature_data'];
                if (preg_match('#^data:image\/(png|jpeg|jpg|gif);base64,#i', $dataUrl)) {
                    // Save old signature to history before updating
                    if (!empty($originalRequest['signature'])) {
                        $stmtHistory = $pdo->prepare(
                            "INSERT INTO signature_history (request_id, old_signature, changed_by_user_id, changed_at) VALUES (?, ?, ?, NOW())"
                        );
                        $stmtHistory->execute([$edit_id, $originalRequest['signature'], $currentUserId]);
                    }
                    $updateFields['signature'] = $dataUrl;
                } else {
                    $_SESSION['error_message'] = "ទម្រង់ទិន្នន័យហត្ថលេខាមិនត្រឹមត្រូវ។ សូមប្រើ PNG/JPG/GIF តែប៉ុណ្ណោះ។";
                    header("Location: " . BASE_URL);
                    exit;
                }
            } elseif (!empty($_POST['delete_signature']) && $_POST['delete_signature'] === '1') {
                // 2) Delete signature request
                if (!empty($originalRequest['signature'])) {
                    $stmtHistory = $pdo->prepare(
                        "INSERT INTO signature_history (request_id, old_signature, changed_by_user_id, changed_at) VALUES (?, ?, ?, NOW())"
                    );
                    $stmtHistory->execute([$edit_id, $originalRequest['signature'], $currentUserId]);
                }
                $updateFields['signature'] = null; // clear
            } elseif (isset($_FILES['new_signature']) && $_FILES['new_signature']['error'] == UPLOAD_ERR_OK) {
                // 3) Fallback: raw file upload (no client processing)
                $file = $_FILES['new_signature'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (in_array($file['type'], $allowedTypes)) {
                    if (!empty($originalRequest['signature'])) {
                        $stmtHistory = $pdo->prepare(
                            "INSERT INTO signature_history (request_id, old_signature, changed_by_user_id, changed_at) VALUES (?, ?, ?, NOW())"
                        );
                        $stmtHistory->execute([$edit_id, $originalRequest['signature'], $currentUserId]);
                    }
                    $imageData = file_get_contents($file['tmp_name']);
                    $updateFields['signature'] = 'data:' . $file['type'] . ';base64,' . base64_encode($imageData);
                } else {
                    $_SESSION['error_message'] = "รูปแบบไฟล์ហត្ថលេខាមិនត្រឹមត្រូវ។ សូមប្រើតែไฟล์ JPG, PNG, ឬ GIF។";
                    header("Location: " . BASE_URL);
                    exit;
                }
            }
            // --- NEW: HANDLE DEPARTMENT HEAD SIGNATURE UPDATE/DELETE ---
            if (isset($_POST['department_head_signature_data']) && is_string($_POST['department_head_signature_data']) && $_POST['department_head_signature_data'] !== '') {
                $dataUrl = $_POST['department_head_signature_data'];
                if (preg_match('#^data:image\/(png|jpeg|jpg|gif);base64,#i', $dataUrl)) {
                    // Save old department head signature to history before updating
                    if (!empty($originalRequest['department_head_signature'])) {
                        $stmtHistory = $pdo->prepare(
                            "INSERT INTO signature_history (request_id, old_signature, changed_by_user_id, changed_at) VALUES (?, ?, ?, NOW())"
                        );
                        $stmtHistory->execute([$edit_id, $originalRequest['department_head_signature'], $currentUserId]);
                    }
                    $updateFields['department_head_signature'] = $dataUrl;
                    // optionally set department_head_signature_date if column exists
                    if (in_array('department_head_signature_date', $existingRequestColumns)) {
                        $updateFields['department_head_signature_date'] = date('Y-m-d');
                    }
                } else {
                    $_SESSION['error_message'] = "ទម្រង់ទិន្នន័យហត្ថលេខាប្រធានផ្នែកមិនត្រឹមត្រូវ។";
                    header("Location: " . BASE_URL);
                    exit;
                }
            } elseif (!empty($_POST['delete_department_head_signature']) && $_POST['delete_department_head_signature'] === '1') {
                // Delete department head signature
                if (!empty($originalRequest['department_head_signature'])) {
                    $stmtHistory = $pdo->prepare(
                        "INSERT INTO signature_history (request_id, old_signature, changed_by_user_id, changed_at) VALUES (?, ?, ?, NOW())"
                    );
                    $stmtHistory->execute([$edit_id, $originalRequest['department_head_signature'], $currentUserId]);
                }
                $updateFields['department_head_signature'] = null;
                if (in_array('department_head_signature_date', $existingRequestColumns)) {
                    $updateFields['department_head_signature_date'] = null;
                }
            } elseif (isset($_FILES['new_dept_head_signature']) && $_FILES['new_dept_head_signature']['error'] == UPLOAD_ERR_OK) {
                $file = $_FILES['new_dept_head_signature'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (in_array($file['type'], $allowedTypes)) {
                    if (!empty($originalRequest['department_head_signature'])) {
                        $stmtHistory = $pdo->prepare(
                            "INSERT INTO signature_history (request_id, old_signature, changed_by_user_id, changed_at) VALUES (?, ?, ?, NOW())"
                        );
                        $stmtHistory->execute([$edit_id, $originalRequest['department_head_signature'], $currentUserId]);
                    }
                    $imageData = file_get_contents($file['tmp_name']);
                    $updateFields['department_head_signature'] = 'data:' . $file['type'] . ';base64,' . base64_encode($imageData);
                    if (in_array('department_head_signature_date', $existingRequestColumns)) {
                        $updateFields['department_head_signature_date'] = date('Y-m-d');
                    }
                } else {
                    $_SESSION['error_message'] = "រូបភាពហត្ថលេខាប្រធានផ្នែកមិនត្រឹមត្រូវ។ សូមប្រើ JPG/PNG/GIF។";
                    header("Location: " . BASE_URL);
                    exit;
                }
            }
            // --- END: HANDLE SIGNATURE UPDATE/DELETE ---

            if (!empty($updateFields)) {
                $setParts = []; $updateValues = [];
                foreach ($updateFields as $key => $value) { $setParts[] = "$key = ?"; $updateValues[] = $value; }
                $setClause = implode(', ', $setParts);
                $updateValues[] = $edit_id;
                try {
                    $stmtUpdate = $pdo->prepare("UPDATE requests SET $setClause WHERE id = ?");
                    $stmtUpdate->execute($updateValues);
                    $_SESSION['success_message'] = "សំណើ (ID: $edit_id) ត្រូវបានកែសម្រួលដោយជោគជ័យ។";
                } catch (PDOException $e) { $error = "កំហុសក្នុងការកែសម្រួល: " . $e->getMessage(); }
            } else {
                $_SESSION['success_message'] = "មិនមានការផ្លាស់ប្តូរត្រូវបានធ្វើឡើងចំពោះសំណើ (ID: $edit_id)។";
            }
            header("Location: " . BASE_URL);
            exit;
        } else {
            $_SESSION['error_message'] = "អ្នកមិនមានសិទ្ធិកែសម្រួលសំណើនេះទេ។";
            header("Location: " . BASE_URL);
            exit;
        }
    }
}


// --- NEW: HANDLE AJAX REQUEST FOR SIGNATURE HISTORY ---
if (isset($_GET['action']) && $_GET['action'] == 'get_signature_history' && isset($_GET['request_id'])) {
    header('Content-Type: application/json');
    $requestId = (int)$_GET['request_id'];
    
    try {
        $sql = "SELECT sh.old_signature, sh.changed_at, u.full_name 
                FROM signature_history sh
                LEFT JOIN users u ON sh.changed_by_user_id = u.id
                WHERE sh.request_id = ? 
                ORDER BY sh.changed_at DESC";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$requestId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'history' => $history]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
    }
    exit;
}
// --- END AJAX HANDLER ---

// --- NEW: QUICK SIGNATURE UPLOAD (AJAX) ---
if (isset($_GET['action']) && $_GET['action'] === 'quick_signature_upload') {
    header('Content-Type: application/json');

    try {
        // Validate inputs
        $requestId = isset($_POST['request_id']) ? (int)$_POST['request_id'] : 0;
        if ($requestId <= 0) {
            throw new Exception('Request ID មិនត្រឹមត្រូវ');
        }

        // Fetch request and check permission (admin, owner, or manager)
        $stmtOriginal = $pdo->prepare("SELECT r.*, u.manager_id FROM requests r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
        $stmtOriginal->execute([$requestId]);
        $original = $stmtOriginal->fetch();
        if (!$original) {
            throw new Exception('រកមិនឃើញសំណើ');
        }

        $canEdit = false;
        if ($isAdmin || $original['user_id'] == $currentUserId) {
            $canEdit = true;
        } else if (!empty($original['manager_id']) && (int)$original['manager_id'] === (int)$currentUserId) {
            $canEdit = true;
        }
        if (!$canEdit) {
            throw new Exception('អ្នកមិនមានសិទ្ធិធ្វើសកម្មភាពនេះទេ');
        }

        // Get signature data (prefer processed data URL)
        $dataUrl = isset($_POST['signature_data']) ? trim($_POST['signature_data']) : '';
        if ($dataUrl === '' && isset($_FILES['signature_file']) && $_FILES['signature_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['signature_file'];
            $allowedTypes = ['image/png','image/jpeg','image/jpg','image/gif'];
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception('ប្រភេទឯកសារមិនអនុញ្ញាត');
            }
            $imgData = file_get_contents($file['tmp_name']);
            $dataUrl = 'data:' . $file['type'] . ';base64,' . base64_encode($imgData);
        }

        if (!preg_match('#^data:image\/(png|jpeg|jpg|gif);base64,#i', $dataUrl)) {
            throw new Exception('ទិន្នន័យហត្ថលេខាមិនត្រឹមត្រូវ');
        }

        // Save old signature to history if exists
        if (!empty($original['signature'])) {
            $stmtHistory = $pdo->prepare("INSERT INTO signature_history (request_id, old_signature, changed_by_user_id, changed_at) VALUES (?, ?, ?, NOW())");
            $stmtHistory->execute([$requestId, $original['signature'], $currentUserId]);
        }

        // Update request with new signature and date (set date to today if empty)
        $stmtUpd = $pdo->prepare("UPDATE requests SET signature = ?, signature_date = COALESCE(signature_date, CURDATE()) WHERE id = ?");
        $stmtUpd->execute([$dataUrl, $requestId]);

        // Return updated history count
        $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM signature_history WHERE request_id = ?");
        $stmtCnt->execute([$requestId]);
        $historyCount = (int)$stmtCnt->fetchColumn();

        echo json_encode(['success' => true, 'signature' => $dataUrl, 'history_count' => $historyCount]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
// --- END QUICK SIGNATURE UPLOAD ---


// --- HANDLE DELETE REQUEST ---
if (isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    $stmtFetch = $pdo->prepare("SELECT r.*, u.full_name FROM requests r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
    $stmtFetch->execute([$delete_id]);
    $requestToDelete = $stmtFetch->fetch();
    if ($requestToDelete) {
        // Permission: Admin OR owner of the request OR the manager of the requester
        $canDelete = false;
        if ($isAdmin || ((int)$requestToDelete['user_id'] === (int)$currentUserId)) {
            $canDelete = true;
        } else {
            // Check if current user is manager of the requester
            try {
                $stmtManager = $pdo->prepare("SELECT manager_id FROM users WHERE id = ? LIMIT 1");
                $stmtManager->execute([(int)$requestToDelete['user_id']]);
                $mgr = $stmtManager->fetch(PDO::FETCH_ASSOC);
                if ($mgr && (int)$mgr['manager_id'] === (int)$currentUserId) {
                    $canDelete = true;
                }
            } catch (Exception $e) {
                // ignore and leave canDelete as false
            }
        }
        if ($canDelete) {
            try {
                $stmtDelete = $pdo->prepare("DELETE FROM requests WHERE id = ?");
                $stmtDelete->execute([$delete_id]);
                $_SESSION['success_message'] = "សំណើ (ID: $delete_id) ត្រូវបានលុបដោយជោគជ័យ។";
            } catch (PDOException $e) { $error = "កំហុសក្នុងការលុប: " . $e->getMessage(); }
        } else {
            $error = "អ្នកមិនមានសិទ្ធិលុបសំណើនេះទេ។";
        }
    } else { $error = "រកមិនឃើញសំណើដែលត្រូវលុបទេ។"; }
    header("Location: " . BASE_URL);
    exit;
}

// --- HANDLE BULK DELETE REQUEST ---
if (isset($_POST['bulk_delete_ids'])) {
    $idsString = $_POST['bulk_delete_ids'];
    $ids = array_map('intval', explode(',', $idsString));
    $ids = array_filter($ids); // Remove empty values

    if (!empty($ids)) {
        // Check permissions for each ID (allow admin, owner, or manager of the requester)
        $allowedIds = [];
        foreach ($ids as $id) {
            $stmtFetch = $pdo->prepare("SELECT r.user_id, u.manager_id FROM requests r LEFT JOIN users u ON r.user_id = u.id WHERE r.id = ?");
            $stmtFetch->execute([$id]);
            $request = $stmtFetch->fetch(PDO::FETCH_ASSOC);
            if (!$request) continue;
            $isAllowed = false;
            if ($isAdmin || (int)$request['user_id'] === (int)$currentUserId) {
                $isAllowed = true;
            } elseif (!empty($request['manager_id']) && (int)$request['manager_id'] === (int)$currentUserId) {
                $isAllowed = true;
            }
            if ($isAllowed) $allowedIds[] = $id;
        }

        if (!empty($allowedIds)) {
            try {
                $placeholders = implode(',', array_fill(0, count($allowedIds), '?'));
                $stmtDelete = $pdo->prepare("DELETE FROM requests WHERE id IN ($placeholders)");
                $stmtDelete->execute($allowedIds);
                $deletedCount = $stmtDelete->rowCount();
                $_SESSION['success_message'] = "លុបសំណើចំនួន $deletedCount ដោយជោគជ័យ។";
            } catch (PDOException $e) {
                $error = "កំហុសក្នុងការលុបច្រើន: " . $e->getMessage();
            }
        } else {
            $error = "អ្នកមិនមានសិទ្ធិលុបសំណើទាំងនេះទេ។";
        }
    } else {
        $error = "មិនមានសំណើណាមួយត្រូវលុបទេ។";
    }
    header("Location: " . BASE_URL);
    exit;
}

// --- PREPARE DATA FOR DISPLAY ---

// Build a list of user IDs that the current user is allowed to edit/manage
$editableUserIds = [$currentUserId]; // User can always edit their own
if (!$isAdmin) {
    $stmtSubs = $pdo->prepare("SELECT id FROM users WHERE manager_id = ? AND status = 'active'");
    $stmtSubs->execute([$currentUserId]);
    $subordinateIds = $stmtSubs->fetchAll(PDO::FETCH_COLUMN);
    $editableUserIds = array_merge($editableUserIds, $subordinateIds);
}

// --- FETCH TOTAL COUNT FOR PAGINATION ---
$countSql = "SELECT COUNT(*) as total FROM requests r";
$countParams = [];

if (!$isAdmin) {
    $placeholders = implode(',', array_fill(0, count($editableUserIds), '?'));
    $countSql .= " WHERE r.user_id IN ($placeholders)";
    $countParams = $editableUserIds;
}

try {
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($countParams);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);
} catch (PDOException $e) {
    $totalRecords = 0;
    $totalPages = 1;
}

// --- FETCH REQUESTS FOR DISPLAY (MODIFIED FOR HISTORY COUNT) ---
$sql = "SELECT r.*, u.full_name AS user_full_name,
        (SELECT COUNT(*) FROM signature_history sh WHERE sh.request_id = r.id) AS signature_history_count
        FROM requests r 
        LEFT JOIN users u ON r.user_id = u.id";
$params = [];

if (!$isAdmin) {
    // A non-admin user (regular employee or manager) will have their view filtered.
    // We already built the $editableUserIds array which contains the user's own ID and their subordinates' IDs.
    $placeholders = implode(',', array_fill(0, count($editableUserIds), '?'));
    $sql .= " WHERE r.user_id IN ($placeholders)";
    $params = $editableUserIds;
}
// If $isAdmin is true, no WHERE clause is added, so they see ALL requests, which is correct.

$sql .= " ORDER BY r.request_date DESC, r.id DESC LIMIT ? OFFSET ?";

try {
    $stmt = $pdo->prepare($sql);
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "កំហុសមូលដ្ឋានទិន្នន័យពេលទាញទិន្នន័យ: " . $e->getMessage();
    $requests = [];
}

// Retrieve flash messages
if (empty($success) && isset($_SESSION['success_message'])) { $success = $_SESSION['success_message']; unset($_SESSION['success_message']); }
if (empty($error) && isset($_SESSION['error_message'])) { $error = $_SESSION['error_message']; unset($_SESSION['error_message']); }
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2x/Logo-Van-Van-1.png">
    <title>គ្រប់គ្រងសំណើ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://kit.fontawesome.com/a2e0a6ad5b.js" crossorigin="anonymous"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap');

        body {
            background: linear-gradient(135deg, #f5f7fa, #c3cfe2);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 20px;
        }

        @keyframes bgZoom {
            from { background-size: 100% 100%; }
            to { background-size: 110% 110%; }
        }

        /* Floating Animation for Theme Icons */
        @keyframes floatUpDown {
            0% { transform: translateY(0) rotate(-15deg); }
            50% { transform: translateY(-15px) rotate(-10deg); }
            100% { transform: translateY(0) rotate(-15deg); }
        }

        /* Season/Festival Theme Overrides */
        <?php if ($currentTheme === 'kny'): ?>
        :root { --primary-btn: #f59e0b; --primary-btn-hover: #d97706; }
        .report-title { color: #d97706 !important; }
        .btn-success { background: linear-gradient(90deg, #f59e0b, #d97706) !important; border: none !important; }
        th { background-color: #f59e0b !important; color: #fff !important; }
        .report-container::after { 
            content: ""; position: absolute; bottom: -20px; right: -20px; width: 120px; height: 120px;
            background-image: url('https://i.ibb.co/qFRZ8SCK/khmer-new-year.png');
            background-size: contain; background-repeat: no-repeat;
            opacity: 0.12; filter: drop-shadow(0 5px 8px rgba(0,0,0,0.1));
            animation: floatUpDown 6s ease-in-out infinite;
        }
        /* Fireworks Overlay for KNY */
        body::after {
            content: "";
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-image: url('https://media.tenor.com/XesYJjyNYgAAAAAi/fireworks-putukan.gif');
            background-size: cover; background-repeat: no-repeat;
            pointer-events: none; z-index: -1; opacity: 0.35; mix-blend-mode: screen;
        }
        
        <?php elseif ($currentTheme === 'pb'): ?>
        :root { --primary-btn: #ea580c; --primary-btn-hover: #c2410c; }
        .report-title { color: #c2410c !important; }
        .btn-success { background: linear-gradient(90deg, #ea580c, #c2410c) !important; border: none !important; }
        th { background-color: #ea580c !important; color: #fff !important; }
        .report-container::after { 
            content: "\f67f"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
            position: absolute; bottom: -10px; right: -10px; font-size: 80px;
            opacity: 0.1; color: #ea580c; animation: floatUpDown 6s ease-in-out infinite;
        }

        <?php elseif ($currentTheme === 'cny'): ?>
        :root { --primary-btn: #dc2626; --primary-btn-hover: #b91c1c; }
        .report-title { color: #b91c1c !important; }
        .btn-success { background: linear-gradient(90deg, #dc2626, #b91c1c) !important; border: none !important; }
        th { background-color: #dc2626 !important; color: #fff !important; }
        .report-container::after { 
            content: ""; position: absolute; bottom: -20px; right: -20px; width: 120px; height: 120px;
            background-image: url('https://i.ibb.co/G4K8Mv36/chinese-new-year.png');
            background-size: contain; background-repeat: no-repeat;
            opacity: 0.12; filter: drop-shadow(0 5px 8px rgba(0,0,0,0.1));
            animation: floatUpDown 6s ease-in-out infinite;
        }

        <?php elseif ($currentTheme === 'wf'): ?>
        :root { --primary-btn: #0284c7; --primary-btn-hover: #0369a1; }
        .report-title { color: #0369a1 !important; }
        .btn-success { background: linear-gradient(90deg, #0284c7, #0369a1) !important; border: none !important; }
        th { background-color: #0284c7 !important; color: #fff !important; }
        .report-container::after { 
            content: "\f773"; font-family: "Font Awesome 6 Free"; font-weight: 900; 
            position: absolute; bottom: -10px; right: -10px; font-size: 100px;
            opacity: 0.1; color: #0284c7; animation: floatUpDown 6s ease-in-out infinite;
        }
        <?php endif; ?>
        /* Ensure header and important UI text is white on themed backgrounds */
        .report-container thead th,
        .report-container thead th a,
        .report-container thead th span,
        .report-container thead th .badge,
        table thead th,
        th {
            color: #fff !important;
        }

        /* Apply Theme Background Image */
        <?php if (!empty($bgImage)): ?>
        body {
            background-image: url('<?php echo $bgImage; ?>') !important;
            background-size: cover !important;
            background-position: center !important;
            background-attachment: fixed !important;
            background-repeat: no-repeat !important;
            animation: bgZoom 20s ease-in-out infinite alternate !important;
        }

        /* Overlay to ensure readability */
        body::before {
            content: "";
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.7);
            z-index: -2;
        }
        <?php endif; ?>
        body, .btn, .modal-title, .form-table td, .main-footer th, .report-title, input::placeholder, .span, .form-label {
            font-family: 'Noto Sans Khmer', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .required-field::after { content: " *"; color: red; }
        .btn-edit { background-color: #ffc107; border: none; padding: 6px 12px; font-size: 0.9rem; border-radius: 5px; color: white; transition: background-color 0.3s ease; margin-right: 5px; }
        .btn-edit:hover { background-color: #e0a800; color: white; }
        .btn-delete { background-color: #dc3545; border: none; padding: 6px 12px; font-size: 0.9rem; border-radius: 5px; color: white; transition: background-color 0.3s ease; }
        .btn-delete:hover { background-color: #c82333; color: white; }
        .btn-detail { background-color: #17a2b8; border: none; padding: 6px 12px; font-size: 0.9rem; border-radius: 5px; color: white; transition: background-color 0.3s ease; margin-right: 5px; }
        .btn-detail:hover { background-color: #138496; color: white; }
        .btn-print { background-color: #28a745; border: none; padding: 6px 12px; font-size: 0.9rem; border-radius: 5px; color: white; transition: background-color 0.3s ease; }
        .btn-print:hover { background-color: #218838; color: white; }
        .edit-field { width: 100%; padding: 5px; border: 1px solid #ced4da; border-radius: 4px; display: none; font-family: 'Noto Sans Khmer', sans-serif; }
        .detail-item.editing .display-text { display: none; }
        .detail-item.editing .edit-field { display: block; }
        .report-container { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); padding: 2rem; max-width: 1200px; margin: 0 auto; }
        .report-title { color: #2c3e50; font-size: 2rem; font-weight: 700; text-align: center; margin-bottom: 1.5rem; text-transform: uppercase; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e0e6f0; font-family: 'Noto Sans Khmer', sans-serif; }
        th { background-color: #3498db; color: white; font-weight: 600; }
        tr:hover { background-color: #f5f7fa; }
        .btn-back { background-color: #7f8c8d; border: none; padding: 10px 20px; font-size: 1rem; border-radius: 8px; transition: background-color 0.3s ease, transform 0.2s ease; color: white; text-decoration: none; display: inline-block; margin-top: 20px; }
        .btn-back:hover { background-color: #6c757d; transform: translateY(-2px); }
        .modal-content { border-radius: 15px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); }
        .modal-header { background-color: #3498db; color: white; border-top-left-radius: 15px; border-top-right-radius: 15px; }
        .modal-title { font-weight: 600; }
        .modal-body { padding: 2rem; background-color: #f8f9fa; }
        .section-header { font-size: 1.2rem; font-weight: 600; color: #2c3e50; margin-bottom: 1rem; border-bottom: 2px solid #3498db; padding-bottom: 0.3rem; font-family: 'Noto Sans Khmer', sans-serif;}
        .detail-row { display: flex; flex-wrap: wrap; gap: 1rem; }
        .detail-item { flex: 1 1 45%; background: white; padding: 1rem; border-radius: 8px; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05); margin-bottom: 1rem; }
        .detail-item i { color: #3498db; margin-right: 0.5rem; }
        .detail-item strong { color: #2c3e50; font-weight: 600; font-family: 'Noto Sans Khmer', sans-serif;}
        .detail-item span { color: #34495e; font-family: 'Noto Sans Khmer', sans-serif;}
        .modal-footer { border-top: none; padding: 1rem 2rem; }
        .print-request-form { font-family: 'Noto Sans Khmer', sans-serif; margin-bottom: 20px; width: 800px; margin: 0 auto; }
        .print-request-form .container { border: 2px solid #000; padding: 10px; }
        .print-request-form .header { text-align: center; }
        .print-request-form .header img { max-width: 200px; height: auto; }
        .print-request-form .form-table { width: 100%; border-collapse: collapse; }
        .print-request-form .form-table td { border: 1px solid #000; padding: 8px; font-family: 'Noto Sans Khmer', sans-serif; font-size: 14px; }
        .icon-group { display: flex; flex-wrap: wrap; gap: 15px; margin: 10px 0; padding: 10px; background: #f9f9f9; border-radius: 8px; border: 1px solid #e0e6f0; align-items: center; justify-content: center; }
        .request-icon-print { display: flex; align-items: center; font-size: 10px; font-family: 'Noto Sans Khmer', sans-serif; padding: 6px 10px; border-radius: 8px; background-color: #f0f0f0; color: #555; opacity: 0.9; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06); transition: transform 220ms ease, box-shadow 220ms ease, opacity 220ms ease, background 220ms ease; cursor: default; }
        .request-icon-print:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(0,0,0,0.08); opacity: 1; }
        .request-icon-print:focus { outline: none; box-shadow: 0 6px 14px rgba(0,0,0,0.12); }
        .request-icon-print.selected {
            background: linear-gradient(90deg, #34d399 0%, #10b981 100%) !important;
            color: #ffffff !important;
            opacity: 1 !important;
            font-weight: 700 !important;
            box-shadow: 0 10px 30px rgba(16,185,129,0.18) !important;
            transform: translateY(-4px);
            border: 1px solid rgba(16,185,129,0.18) !important;
        }
        /* small badge-like appearance for selected state on small screens */
        @media (max-width: 480px) {
            .request-icon-print { padding: 6px 8px; font-size: 11px; }
            .request-icon-print.selected { transform: none; box-shadow: 0 6px 18px rgba(16,185,129,0.14) !important; }
        }
        .print-request-form .main-footer { width: 100%; border: none; border-collapse: collapse; margin-top: 20px; }
        .print-request-form .main-footer th {  background-color: transparent; border: none;  padding: 8px;  font-family: 'Noto Sans Khmer', sans-serif;  font-size: 14px;  color: black;  }
        .print-request-form .main-footer tr { border: none; }
        .table-actions button, .table-actions a { margin-right: 5px; margin-bottom: 5px; }

        /* Premium Table UI Redesign */
        .report-container {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid #f1f5f9;
            padding: 30px;
        }

        .action-bar h2 {
            font-size: 1.75rem;
            color: #0f172a;
            font-weight: 800;
        }

        .search-wrapper {
            margin-bottom: 30px;
        }

        #searchInput {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            height: 48px;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        #searchInput:focus {
            background: #ffffff;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .main-table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 1rem;
            vertical-align: top;
            border-color: #f1f5f9;
        }

        .main-table thead th {
            background: #f8fafc;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
            padding: 16px 20px;
            border-bottom: 2px solid #f1f5f9 !important;
        }

        .main-table tbody tr {
            transition: background-color 0.2s ease;
            border-bottom: 1px solid #f1f5f9;
        }

        .main-table tbody tr:hover {
            background-color: #f9fafb !important;
        }

        .main-table tbody td {
            padding: 18px 20px;
            font-size: 0.9rem;
            color: #334155;
            vertical-align: middle;
        }

        .id-badge {
            font-family: 'Monaco', 'Consolas', monospace;
            color: #64748b;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .requester-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .requester-avatar {
            width: 36px;
            height: 36px;
            background: #eff6ff;
            color: #3b82f6;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            flex-shrink: 0;
            border: 1px solid #dbeafe;
        }

        /* Modern Badges */
        .badge-request {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid transparent;
        }
        
        .badge-annual { background: #ecfdf5; color: #059669; border-color: #d1fae5; }
        .badge-late { background: #fff1f2; color: #e11d48; border-color: #ffe4e6; }
        .badge-early { background: #fffbeb; color: #d97706; border-color: #fef3c7; }
        .badge-ot { background: #f0f9ff; color: #0284c7; border-color: #e0f2fe; }
        .badge-forgot { background: #f8fafc; color: #475569; border-color: #e2e8f0; }
        .badge-default { background: #f9fafb; color: #64748b; border-color: #f1f5f9; }

        /* Actions Dropdown Refinement */
        .btn-action-trigger {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            padding: 0;
        }

        .btn-action-trigger:hover, .btn-action-trigger:focus {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #334155;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .dropdown-menu {
            padding: 8px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            min-width: 210px;
        }

        .dropdown-item {
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 0.875rem;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .dropdown-item:hover {
            background-color: #f1f5f9;
            color: #0f172a;
        }

        .dropdown-item i {
            font-size: 1rem;
            width: 20px;
            display: flex;
            justify-content: center;
        }

        .dropdown-header {
            padding: 8px 12px 4px;
            font-size: 0.7rem;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .dropdown-divider {
            margin: 6px 0;
            border-color: #f1f5f9;
        }

        /* Redesign Delete Button in Dropdown */
        .dropdown-item.text-danger:hover {
            background-color: #fef2f2;
            color: #dc2626;
        }

        .back-btn {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .back-btn:hover {
            background: #e2e8f0;
            color: #1e293b;
        }

    /* Use Font Awesome caret icon instead of Bootstrap's default caret for our dropdowns */
    .dropdown-toggle.fa-caret-override::after { display: none !important; }
    .dropdown-toggle.fa-caret-override i.fa, .dropdown-toggle.fa-caret-override i.fas { font-size: 0.95rem; margin-left: 0; vertical-align: middle; }
    
    .table-actions .dropdown-toggle::after {
        display: none !important;
    }
    .table-actions .btn-link:focus {
        box-shadow: none;
    }
    .table-actions .dropdown-item:active {
        background-color: #f8f9fa;
        color: inherit;
    }
    .table-actions .dropdown-menu {
        min-width: 200px;
    }
    .table-actions .dropdown-item i {
        width: 20px;
        text-align: center;
    }
    @media print { body * { visibility: hidden; } .print-request-form, .print-request-form * { visibility: visible; } .print-request-form { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 0; } .report-container { display: none; } .no-print { display: none !important; } @page { size: A5; margin: 3mm; } .request-icon-print { background-color: #f0f0f0 !important; color: #555 !important; opacity: 0.7 !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; } .request-icon-print.selected { background-color: #28a745 !important; color: #ffffff !important; opacity: 1 !important; font-weight: bold !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; } }
        @media (max-width: 768px) { .report-container { padding: 0.5rem; } th, td { font-size: 11px; padding: 8px; } .detail-item { flex: 1 1 100%; } .request-icon-print { font-size: 9px; padding: 5px 8px; } .print-logo { max-width: 150px; height: auto; } .report-title { font-size: 1.5rem; } .btn-detail, .btn-delete, .btn-print, .btn-edit { font-size: 0.8rem; padding: 5px 10px; } }
        .span { display: block; text-align: center; margin: 10px 0; font-family: 'Noto Sans Khmer', sans-serif;}
        .back-btn { background: #6c757d; border: none; padding: 10px 15px; font-size: 1rem; border-radius: 8px; transition: all 0.3s ease; display: inline-flex; align-items: center; font-family: 'Noto Sans Khmer', sans-serif; color: white; text-decoration: none; cursor: pointer; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); }
        .back-btn:hover { background: #5a6268; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(108, 117, 125, 0.4); color: white; }
        .back-btn i { margin-right: 8px; font-size: 1.1rem; }
        @media (max-width: 768px) { .back-btn { font-size: 0.9rem; padding: 8px 12px; border-radius: 10px; } .back-btn i { font-size: 1rem; margin-right: 6px; } }
        /* Improved alert styles */
        .alert {
            margin-top: 15px;
            margin-bottom: 15px;
            padding: 0.9rem 1rem;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: 0 6px 18px rgba(16,24,40,0.06);
            border: 1px solid transparent;
            font-family: 'Noto Sans Khmer', sans-serif;
            transition: transform .18s ease, opacity .25s ease;
        }
        .alert .alert-icon {
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #fff;
            font-size: 1rem;
        }
        .alert .alert-body { flex: 1 1 auto; }
        .alert .alert-close { margin-left: 0.5rem; background: transparent; border: none; color: inherit; }

        .alert-success { background: linear-gradient(180deg, #e6fbef 0%, #dff7ee 100%); border-color: #bfead0; color: #0f5132; }
        .alert-success .alert-icon { background: #28a745; box-shadow: 0 2px 6px rgba(40,167,69,0.18); }

        .alert-danger { background: linear-gradient(180deg, #fff1f0 0%, #ffe7e6 100%); border-color: #f5c2c7; color: #842029; }
        .alert-danger .alert-icon { background: #dc3545; box-shadow: 0 2px 6px rgba(220,53,69,0.18); }

        .alert-info { background: linear-gradient(180deg, #eef8ff 0%, #e6f4ff 100%); border-color: #cfe9ff; color: #055160; }
        .alert-info .alert-icon { background: #0dcaf0; box-shadow: 0 2px 6px rgba(13,202,240,0.12); }

        /* Fade-out helper */
        .alert.fade-out { opacity: 0; transform: translateY(-6px); }
        /* Floating toast container (top-right) */
        .floating-alerts {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 1060;
            display: flex;
            flex-direction: column;
            gap: 0.65rem;
            max-width: 360px;
            pointer-events: none; /* let clicks pass to close buttons only */
        }
        .floating-alerts .alert { pointer-events: auto; }
        .action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        
        /* --- NEW & MODIFIED STYLES FOR SIGNATURE --- */
        #signature-display-area { 
            border: 1px solid #ddd; 
            border-radius: 8px; 
            width: 100%; 
            min-height: 200px; /* Increased height */
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 5px; 
            background-color: #f9f9f9;
            margin-bottom: 15px; /* Added margin */
        }
        #signature-image { 
            max-width: 100%; 
            max-height: 190px; /* Adjusted max height */
            object-fit: contain; 
        }
        .signature-controls {
            display: none; /* Hidden by default */
            margin-top: 10px;
        }
        .history-item {
            border-bottom: 1px solid #eee;
            padding: 15px 10px;
        }
        .history-item:last-child {
            border-bottom: none;
        }
        .history-signature-img {
            max-width: 250px;
            max-height: 100px;
            border: 1px solid #ccc;
            border-radius: 4px;
            margin-top: 5px;
        }

    .report-container {
      background: rgba(255, 255, 255, 0.95);
      padding: 25px;
      margin: 40px auto;
      max-width: 1200px;
      border-radius: 10px;
      box-shadow: 0 3px 10px rgba(0,0,0,0.1);
      position: relative;
      overflow: hidden;
      border: 1px solid rgba(255, 255, 255, 0.3);
    }
    #loadingSpinner {
      display: none;
      text-align: center;
      padding: 20px;
      color: #007bff;
    }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="action-bar no-print">
            <h2 class="report-title" style="margin-bottom:0;">បញ្ជីសំណើ</h2>
            <div>
                <?php if ($isAdmin || in_array($currentUserId, $editableUserIds)): ?>
                <button type="button" class="btn btn-danger me-2" id="bulkDeleteBtn" style="display:none;">
                    <i class="fas fa-trash"></i> លុបអ្នកជ្រើសរើស
                </button>
                <?php endif; ?>
                <a href="../homes.php" class="btn btn-secondary shadow-sm">
                <i class="fas fa-arrow-left"></i> ត្រឡប់ក្រោយ
            </a>
            </div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success no-print"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger no-print"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="search-wrapper no-print">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" class="form-control" placeholder="ស្វែងរកតាម ID, ឈ្មោះ, ប្រភេទសំណើ, ឬមូលហេតុ...">
        </div>

        <?php if (empty($requests)): ?>
            <div class="text-center py-5">
                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                <p class="text-muted">មិនមានសំណើណាមួយត្រូវបានរកឃើញទេ។</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table main-table">
                    <thead>
                        <tr>
                            <?php if ($isAdmin || in_array($currentUserId, $editableUserIds)): ?>
                            <th class="no-print" style="width: 50px;"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                            <?php endif; ?>
                            <th style="width: 100px;">ID</th>
                            <th>ប្រភេទសំណើ</th>
                            <th>ឈ្មោះអ្នកស្នើសុំ</th>
                            <th>ផ្នែក / សាខា</th>
                            <th>កាលបរិច្ឆេទ</th>
                            <th style="min-width: 200px;">មូលហេតុ</th>
                            <th class="no-print text-center">សកម្មភាព</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): 
                            $type = $request['request_type'] ?? 'N/A';
                            $badgeClass = 'badge-default';
                            if (stripos($type, 'Annual') !== false || stripos($type, 'សម្រាកប្រចាំឆ្នាំ') !== false) $badgeClass = 'badge-annual';
                            elseif (stripos($type, 'Late') !== false || stripos($type, 'មកយឺត') !== false) $badgeClass = 'badge-late';
                            elseif (stripos($type, 'Early') !== false || stripos($type, 'ចេញមុន') !== false) $badgeClass = 'badge-early';
                            elseif (stripos($type, 'OT') !== false || stripos($type, 'ថែម') !== false) $badgeClass = 'badge-ot';
                            elseif (stripos($type, 'Forgot') !== false || stripos($type, 'ភ្លេច') !== false) $badgeClass = 'badge-forgot';
                        ?>
                            <tr>
                                <?php if ($isAdmin || in_array($currentUserId, $editableUserIds)): ?>
                                <td class="no-print"><input type="checkbox" class="rowCheckbox form-check-input" value="<?php echo $request['id']; ?>"></td>
                                <?php endif; ?>
                                <td><span class="id-badge">#<?php echo htmlspecialchars($request['id'] ?? 'N/A'); ?></span></td>
                                <td>
                                    <span class="badge-request <?php echo $badgeClass; ?>">
                                        <?php echo htmlspecialchars($type); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="requester-info">
                                        <div class="requester-avatar">
                                            <?php 
                                                $initials = '';
                                                if (!empty($request['requester_name'])) {
                                                    $parts = explode(' ', $request['requester_name']);
                                                    foreach ($parts as $p) $initials .= mb_substr($p, 0, 1, 'UTF-8');
                                                }
                                                echo htmlspecialchars(mb_substr($initials, 0, 2, 'UTF-8'));
                                            ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($request['requester_name'] ?? 'N/A'); ?></div>
                                            <div class="text-muted small">ID: <?php echo htmlspecialchars($request['user_id'] ?? '-'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark"><?php echo htmlspecialchars($request['department'] ?? 'N/A'); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($request['branch'] ?? 'N/A'); ?></div>
                                </td>
                                <td>
                                    <div class="fw-medium"><?php echo htmlspecialchars(isset($request['request_date']) ? date("d-m-Y", strtotime($request['request_date'])) : 'N/A'); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars(isset($request['created_at']) ? date("H:i", strtotime($request['created_at'])) : ''); ?></div>
                                </td>
                                <td>
                                    <div style="max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($request['reason'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($request['reason'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td class="no-print table-actions text-end align-middle">
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-action-trigger dropdown-toggle" type="button" id="actionsDropdown<?php echo $request['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="actionsDropdown<?php echo $request['id']; ?>">
                                            <li class="dropdown-header">សកម្មភាពសំណើ</li>
                                            <li>
                                                <button class="dropdown-item btn-detail" 
                                                    data-request-id="<?php echo (int)$request['id']; ?>"
                                                    data-can-edit="<?php echo ($isAdmin || (in_array($request['user_id'], $editableUserIds))) ? 'true' : 'false'; ?>">
                                                    <i class="fas fa-edit text-warning"></i> មើល និងកែសម្រួល
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item btn-table-print" 
                                                    data-request-id="<?php echo (int)$request['id']; ?>">
                                                    <i class="fas fa-print text-success"></i> បោះពុម្ពសំណើ
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item btn-table-pdf" 
                                                    data-request-id="<?php echo (int)$request['id']; ?>">
                                                    <i class="fas fa-file-pdf text-danger"></i> ទាញយកជា PDF
                                                </button>
                                            </li>
                                            
                                            <?php if ($isAdmin || in_array($request['user_id'], $editableUserIds)): ?>
                                                <li class="dropdown-divider"></li>
                                                <li class="dropdown-header">ហត្ថលេខា</li>
                                                <li>
                                                    <button class="dropdown-item btn-dept-detail" 
                                                        data-request-id="<?php echo (int)$request['id']; ?>">
                                                        <i class="fas fa-user-tie text-primary"></i> ហត្ថលេខាប្រធាន
                                                    </button>
                                                </li>
                                                <li>
                                                    <button class="dropdown-item btn-quick-signature" 
                                                        data-request-id="<?php echo (int)$request['id']; ?>">
                                                        <i class="fas fa-signature text-info"></i> ហត្ថលេខាផ្ទាល់ខ្លួន
                                                    </button>
                                                </li>
                                            <?php endif; ?>

                                            <?php if ($isAdmin || (int)$request['user_id'] === (int)$currentUserId): ?>
                                                <li class="dropdown-divider"></li>
                                                <li>
                                                    <button class="dropdown-item text-danger btn-delete font-weight-bold" 
                                                        data-id="<?php echo $request['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($request['request_type'] . ' ដោយ ' . $request['requester_name']); ?>"
                                                        data-bs-toggle="modal" data-bs-target="#deleteConfirmModal">
                                                        <i class="fas fa-trash"></i> លុបសំណើនេះ
                                                    </button>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php
                    $baseUrl = BASE_URL . '?';
                    $queryParams = $_GET;
                    unset($queryParams['page']); // Remove page from params to rebuild

                    // Previous button
                    if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo $baseUrl . http_build_query(array_merge($queryParams, ['page' => $page - 1])); ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                    <?php endif;

                    // Page numbers
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);

                    if ($startPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo $baseUrl . http_build_query(array_merge($queryParams, ['page' => 1])); ?>">1</a>
                        </li>
                        <?php if ($startPage > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif;
                    endif;

                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo $baseUrl . http_build_query(array_merge($queryParams, ['page' => $i])); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor;

                    if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo $baseUrl . http_build_query(array_merge($queryParams, ['page' => $totalPages])); ?>"><?php echo $totalPages; ?></a>
                        </li>
                    <?php endif;

                    // Next button
                    if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo $baseUrl . http_build_query(array_merge($queryParams, ['page' => $page + 1])); ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <p class="text-center text-muted mt-2">
                ទំព័រ <?php echo $page; ?> នៃ <?php echo $totalPages; ?> (សរុប <?php echo $totalRecords; ?> កំណត់ត្រា)
            </p>
            <?php endif; ?>
            <!-- END PAGINATION -->

        <?php endif; ?>

        <div class="text-center mt-4 no-print">
            <button type="button" class="btn btn-info" id="printRequestFormButton">
                <i class="fas fa-print"></i> បោះពុម្ពសំណើ (ទាំងអស់ដែលបង្ហាញ)
            </button>
            <button type="button" class="back-btn btn btn-secondary" onclick="window.location.href='../requests/requests_menu.php'">
                <i class="fas fa-arrow-left"></i> ត្រឡប់ទៅ Menu
            </button>
        </div>
    </div>

    <div class="modal fade" id="addRequestModal" tabindex="-1" aria-labelledby="addRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form method="POST" action="<?php echo BASE_URL; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addRequestModalLabel"><i class="fas fa-plus-circle"></i> បន្ថែមសំណើថ្មី</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>ទម្រង់បន្ថែមសំណើនឹងបង្ហាញនៅទីនេះ...</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times"></i> បោះបង់</button>
                        <button type="submit" name="submit_add_request" class="btn btn-primary"><i class="fas fa-plus-circle"></i> បន្ថែមសំណើ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailModalLabel"><i class="fas fa-info-circle"></i> ពត៌មានលំអិតនៃសំណើ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <!-- MODIFIED: Added enctype for file uploads -->
                <form method="POST" id="editForm" action="<?php echo BASE_URL; ?>" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="edit_id" id="edit_id_field">
                        
                        <div class="section-header"><i class="fas fa-user"></i> ព័ត៌មានបុគ្គល</div>
                        <div class="detail-row">
                            <div class="detail-item"><i class="fas fa-id-badge"></i> <strong>ID:</strong> <span class="display-text" data-field="id"></span></div>
                            <?php if ($isAdmin): ?>
                                <div class="detail-item"><i class="fas fa-user-tag"></i> <strong>អ្នកប្រើ ID:</strong> 
                                    <span class="display-text" data-field="user_id"></span>
                                    <input type="text" name="user_id" class="edit-field form-control form-control-sm" data-edit-field="user_id">
                                </div>
                            <?php endif; ?>
                            <div class="detail-item"><i class="fas fa-user"></i> <strong>ឈ្មោះអ្នកស្នើសុំ:</strong> 
                                <span class="display-text" data-field="requester_name"></span>
                                <input type="text" name="requester_name" class="edit-field form-control form-control-sm" data-edit-field="requester_name" <?php echo !$isAdmin ? 'readonly' : ''; ?>>
                            </div>
                            <div class="detail-item"><i class="fas fa-building"></i> <strong>ផ្នែក:</strong> 
                                <span class="display-text" data-field="department"></span>
                                <input type="text" name="department" class="edit-field form-control form-control-sm" data-edit-field="department">
                            </div>
                            <div class="detail-item"><i class="fas fa-briefcase"></i> <strong>តំណែង:</strong> 
                                <span class="display-text" data-field="position"></span>
                                <input type="text" name="position" class="edit-field form-control form-control-sm" data-edit-field="position">
                            </div>
                            <div class="detail-item"><i class="fas fa-map-marker-alt"></i> <strong>សាខា:</strong> 
                                <span class="display-text" data-field="branch"></span>
                                <input type="text" name="branch" class="edit-field form-control form-control-sm" data-edit-field="branch">
                            </div>
                            <div class="detail-item"><i class="fas fa-phone"></i> <strong>លេខទូរស័ព្ទ:</strong> 
                                <span class="display-text" data-field="contact_number"></span>
                                <input type="text" name="contact_number" class="edit-field form-control form-control-sm" data-edit-field="contact_number">
                            </div>
                        </div>
                        <div class="section-header mt-3"><i class="fas fa-file-alt"></i> ព័ត៌មានសំណើ</div>
                        <div class="detail-row">
                            <div class="detail-item"><i class="fas fa-clipboard-list"></i> <strong>ប្រភេទសំណើ:</strong> 
                                <span class="display-text" data-field="request_type"></span>
                                <input type="text" name="request_type" class="edit-field form-control form-control-sm" data-edit-field="request_type">
                            </div>
                            <div class="detail-item"><i class="fas fa-calendar-day"></i> <strong>កាលបរិច្ឆេទស្នើសុំ:</strong> 
                                <span class="display-text" data-field="request_date" data-format="date"></span>
                                <input type="date" name="request_date" class="edit-field form-control form-control-sm" data-edit-field="request_date">
                            </div>
                            <div class="detail-item"><i class="fas fa-calendar-check"></i> <strong>ថ្ងៃចូលធ្វើការវិញ:</strong> 
                                <span class="display-text" data-field="return_date" data-format="date"></span>
                                <input type="date" name="return_date" class="edit-field form-control form-control-sm" data-edit-field="return_date">
                            </div>
                            <div class="detail-item"><i class="fas fa-sort-numeric-down"></i> <strong>ចំនួនថ្ងៃឈប់:</strong> 
                                <span class="display-text" data-field="number_of_days"></span>
                                <input type="number" step="0.1" name="number_of_days" class="edit-field form-control form-control-sm" data-edit-field="number_of_days">
                            </div>
                            <div class="detail-item"><i class="fas fa-hourglass-half"></i> <strong>ថ្ងៃឈប់នៅសល់:</strong> 
                                <span class="display-text" data-field="remaining_days"></span>
                                <input type="number" step="0.1" name="remaining_days" class="edit-field form-control form-control-sm" data-edit-field="remaining_days">
                            </div>
                            <div class="detail-item" style="flex-basis: 100%;"><i class="fas fa-comment"></i> <strong>មូលហេតុ:</strong> 
                                <span class="display-text" data-field="reason" style="white-space: pre-wrap;"></span>
                                <textarea name="reason" class="edit-field form-control form-control-sm" data-edit-field="reason" rows="3"></textarea>
                            </div>
                            <div class="detail-item"><i class="fas fa-user-tie"></i> <strong>ប្រគល់ការងារឱ្យ:</strong> 
                                <span class="display-text" data-field="assigned_to"></span>
                                <input type="text" name="assigned_to" class="edit-field form-control form-control-sm" data-edit-field="assigned_to">
                            </div>
                            <div class="detail-item"><i class="fas fa-map"></i> <strong>ទីតាំងពេលឈប់:</strong> 
                                <span class="display-text" data-field="location"></span>
                                <input type="text" name="location" class="edit-field form-control form-control-sm" data-edit-field="location">
                            </div>
                        </div>
                        <div class="section-header mt-3"><i class="fas fa-clock"></i> ព័ត៌មានពេលវេលា</div>
                        <div class="detail-row">
                            <div class="detail-item"><i class="fas fa-sign-in-alt"></i> <strong>ម៉ោងចូល (ចេញមុន):</strong> 
                                <span class="display-text" data-field="time_in" data-format="time"></span>
                                <input type="time" name="time_in" class="edit-field form-control form-control-sm" data-edit-field="time_in">
                            </div>
                            <div class="detail-item"><i class="fas fa-sign-out-alt"></i> <strong>ម៉ោងចេញ (ចេញមុន):</strong> 
                                <span class="display-text" data-field="time_out" data-format="time"></span>
                                <input type="time" name="time_out" class="edit-field form-control form-control-sm" data-edit-field="time_out">
                            </div>
                            <div class="detail-item"><i class="fas fa-hourglass"></i> <strong>ម៉ោងសរុប (ចេញមុន):</strong> 
                                <span class="display-text" data-field="total_hours"></span>
                                <input type="text" name="total_hours" class="edit-field form-control form-control-sm" data-edit-field="total_hours">
                            </div>
                            <div class="detail-item"><i class="fas fa-sign-in-alt"></i> <strong>ម៉ោងចូលសង:</strong> 
                                <span class="display-text" data-field="repay_time_in" data-format="time"></span>
                                <input type="time" name="repay_time_in" class="edit-field form-control form-control-sm" data-edit-field="repay_time_in">
                            </div>
                            <div class="detail-item"><i class="fas fa-sign-out-alt"></i> <strong>ម៉ោងចេញសង:</strong> 
                                <span class="display-text" data-field="repay_time_out" data-format="time"></span>
                                <input type="time" name="repay_time_out" class="edit-field form-control form-control-sm" data-edit-field="repay_time_out">
                            </div>
                            <div class="detail-item"><i class="fas fa-hourglass-end"></i> <strong>ម៉ោងសងសរុប:</strong> 
                                <span class="display-text" data-field="repay_total_hours"></span>
                                <input type="text" name="repay_total_hours" class="edit-field form-control form-control-sm" data-edit-field="repay_total_hours">
                            </div>
                            <div class="detail-item"><i class="fas fa-exclamation-triangle"></i> <strong>ម៉ោងមកយឺត:</strong> 
                                <span class="display-text" data-field="late_hours"></span>
                                <input type="text" name="late_hours" class="edit-field form-control form-control-sm" data-edit-field="late_hours">
                            </div>
                            <div class="detail-item"><i class="fas fa-fingerprint"></i> <strong>ភ្លេចស្កេនចូល:</strong> 
                                <span class="display-text" data-field="forgot_scan_in"></span>
                                <input type="text" name="forgot_scan_in" class="edit-field form-control form-control-sm" data-edit-field="forgot_scan_in">
                            </div>
                            <div class="detail-item"><i class="fas fa-fingerprint"></i> <strong>ភ្លេចស្កេនចេញ:</strong> 
                                <span class="display-text" data-field="forgot_scan_out"></span>
                                <input type="text" name="forgot_scan_out" class="edit-field form-control form-control-sm" data-edit-field="forgot_scan_out">
                            </div>
                        </div>

                        <!-- MODIFIED: SIGNATURE SECTION -->
                        <div class="section-header mt-3"><i class="fas fa-signature"></i> ហត្ថលេខាអ្នកស្នើសុំ</div>
                        <div class="detail-row">
                             <div class="detail-item"><i class="fas fa-calendar-alt"></i> <strong>ថ្ងៃចុះហត្ថលេខា:</strong> 
                                <span class="display-text" data-field="signature_date" data-format="date"></span>
                                <input type="date" name="signature_date" class="edit-field form-control form-control-sm" data-edit-field="signature_date">
                            </div>
                            <div class="detail-item" style="flex-basis: 100%;">
                                <div id="signature-display-area">
                                    <img id="signature-image" src="" alt="ហត្ថលេខា" style="display: none;">
                                    <span id="no-signature-text" style="color: #888;">មិនមានហត្ថលេខា</span>
                                </div>

                                <!-- NEW: Signature Edit Controls -->
                                <div class="signature-controls">
                                    <label for="new_signature_upload" class="form-label">ផ្ទុកឡើងហត្ថលេខាថ្មី (Upload New Signature):</label>
                                    <input class="form-control form-control-sm" type="file" id="new_signature_upload" name="new_signature" accept="image/png, image/jpeg, image/gif">
                                    <div class="form-text">ជ្រើសរើសរូបភាពសម្រាប់ហត្ថលេខាថ្មី។ ប្រព័ន្ធនឹងកាត់ចេញផ្ទៃខាងក្រោយដោយស្វ័យប្រវត្តិ ហើយរក្សាទុកជារូបភាព PNG ថ្លា។</div>

                                    <!-- Hidden fields for processed data URL and delete action -->
                                    <input type="hidden" name="signature_data" id="signature_data_input" value="">
                                    <input type="hidden" name="delete_signature" id="delete_signature_input" value="0">

                                    <button type="button" class="btn btn-outline-danger btn-sm mt-2" id="delete_signature_btn" style="display:none;">
                                        <i class="fas fa-trash"></i> លុបហត្ថលេខា
                                    </button>
                                    <button type="button" class="btn btn-outline-info btn-sm mt-2" id="pull_prev_signature_btn" style="display:none;">
                                        <i class="fas fa-magic"></i> ប្រើហត្ថលេខាមុន
                                    </button>
                                </div>

                                <!-- NEW: History Button -->
                                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="view_signature_history_btn" style="display: none;">
                                    <i class="fas fa-history"></i> មើលប្រវត្តិ (<span id="history_count">0</span>)
                                </button>
                            </div>
                        </div>

                        <!-- NEW: DEPARTMENT HEAD SIGNATURE SECTION -->
                        <div class="section-header mt-3"><i class="fas fa-user-tie"></i> ហត្ថលេខាប្រធានផ្នែក</div>
                        <div class="detail-row">
                            <div class="detail-item" style="flex-basis: 100%;">
                                <div id="dept-signature-display-area">
                                    <img id="dept-signature-image" src="" alt="ហត្ថលេខា​ប្រធាន" style="display: none; max-width:100%; max-height:190px; object-fit:contain;">
                                    <span id="no-dept-signature-text" style="color: #888;">មិនមានហត្ថលេខាប្រធានផ្នែក</span>
                                </div>

                                <div class="dept-signature-controls" style="display:none; margin-top:10px;">
                                    <label for="new_dept_head_signature_upload" class="form-label">ផ្ទុកឡើងហត្ថលេខាប្រធានផ្នែក (Upload):</label>
                                    <input class="form-control form-control-sm" type="file" id="new_dept_head_signature_upload" name="new_dept_head_signature" accept="image/png, image/jpeg, image/gif">
                                    <div class="form-text">ជ្រើសរើសរូបភាពសម្រាប់ហត្ថលេខាប្រធានផ្នែក។ ប្រព័ន្ធនឹងលុបផ្ទៃខាងក្រោយដោយស្វ័យប្រវត្តិ ហើយរក្សាទុកជារូបភាព PNG ថ្លា។</div>

                                    <input type="hidden" name="department_head_signature_data" id="department_head_signature_data_input" value="">
                                    <input type="hidden" name="delete_department_head_signature" id="delete_department_head_signature_input" value="0">

                                    <button type="button" class="btn btn-outline-danger btn-sm mt-2" id="delete_dept_head_signature_btn" style="display:none;">
                                        <i class="fas fa-trash"></i> លុបហត្ថលេខាប្រធាន
                                    </button>
                                </div>

                                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="view_dept_signature_history_btn" style="display:none;">
                                    <i class="fas fa-history"></i> មើលប្រវត្តិប្រធាន (<span id="dept_history_count">0</span>)
                                </button>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="detail_close_button"><i class="fas fa-times"></i> បិទ</button>
                        <button type="button" class="btn btn-warning" id="detail_edit_button" style="display:none;"><i class="fas fa-edit"></i> កែសម្រួល</button>
                        <button type="submit" class="btn btn-primary" id="detail_save_button" style="display: none;"><i class="fas fa-save"></i> រក្សាទុក</button>
                        <button type="button" class="btn btn-info" id="detail_print_button"><i class="fas fa-print"></i> បោះពុម្ព</button>
                        <button type="button" class="btn btn-success" id="detail_download_pdf_button"><i class="fas fa-file-pdf"></i> ទាញយក PDF</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle"></i> បញ្ជាក់ការលុប</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>តើអ្នកពិតជាចង់លុបសំណើ "<span id="deleteRequestNameDisplay"></span>" មែនទេ?</p>
                    <p class="text-danger">សកម្មភាពនេះមិនអាចមិនធ្វើវិញបានទេ។</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="<?php echo BASE_URL; ?>" style="display: inline;">
                        <input type="hidden" name="delete_id" id="deleteConfirmIdInput">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-ban"></i> បោះបង់</button>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> លុប</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- BULK DELETE CONFIRM MODAL -->
    <div class="modal fade" id="bulkDeleteConfirmModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="bulkDeleteModalLabel"><i class="fas fa-exclamation-triangle"></i> បញ្ជាក់ការលុបច្រើន</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>តើអ្នកពិតជាចង់លុបសំណើដែលបានជ្រើសរើស <span id="bulkDeleteCount"></span> មែនទេ?</p>
                    <p class="text-danger">សកម្មភាពនេះមិនអាចមិនធ្វើវិញបានទេ។</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="<?php echo BASE_URL; ?>" style="display: inline;">
                        <input type="hidden" name="bulk_delete_ids" id="bulkDeleteIdsInput">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-ban"></i> បោះបង់</button>
                        <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> លុបច្រើន</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- NEW: QUICK SIGNATURE UPLOAD MODAL -->
    <div class="modal fade" id="quickSignatureModal" tabindex="-1" aria-labelledby="quickSignatureModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="quickSignatureModalLabel"><i class="fas fa-signature"></i> Upload ហត្ថលេខា</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="quick_request_id" value="">
                    <div class="mb-3" style="border:1px solid #e5e5e5; border-radius:8px; min-height:160px; display:flex; align-items:center; justify-content:center; background:#f9f9f9;">
                        <img id="quick_signature_preview" src="" alt="preview" style="max-width:100%; max-height:140px; display:none; object-fit:contain;"/>
                        <span id="quick_signature_empty" class="text-muted">មិនមានហត្ថលេខា</span>
                    </div>
                    <input class="form-control" type="file" id="quick_signature_file" accept="image/png, image/jpeg, image/gif">
                    <div class="form-text">ជ្រើសរើសរូបភាពហត្ថលេខា (PNG/JPG/GIF)。 ប្រព័ន្ធនឹងលុបផ្ទៃស បំលែងទៅ PNG ថ្លា។</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">បិទ</button>
                    <button type="button" class="btn btn-primary" id="quick_signature_save_btn"><i class="fas fa-upload"></i> រក្សាទុក</button>
                </div>
            </div>
        </div>
    </div>

                <!-- NEW: DEPARTMENT-HEAD SIGNATURE MODAL -->
                <div class="modal fade" id="deptSignatureModal" tabindex="-1" aria-labelledby="deptSignatureModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deptSignatureModalLabel"><i class="fas fa-user-tie"></i> ហត្ថលេខាប្រធានផ្នែក</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" id="dept_modal_request_id" value="">
                                <div class="mb-3" style="border:1px solid #e5e5e5; border-radius:8px; min-height:160px; display:flex; align-items:center; justify-content:center; background:#f9f9f9;">
                                    <img id="dept_modal_preview" src="" alt="preview" style="max-width:100%; max-height:140px; display:none; object-fit:contain;"/>
                                    <span id="dept_modal_empty" class="text-muted">មិនមានហត្ថលេខាប្រធាន</span>
                                </div>
                                <input class="form-control" type="file" id="dept_modal_file" accept="image/png, image/jpeg, image/gif">
                                <div class="form-text">ជ្រើសរើសរូបភាពហត្ថលេខាប្រធាន (PNG/JPG/GIF)。 ប្រព័ន្ធនឹងលុបផ្ទៃខាងក្រោយដោយស្វ័យប្រវត្តិ ហើយរក្សាទុកជារូបភាព PNG ថ្លា។</div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">បិទ</button>
                                <button type="button" class="btn btn-primary" id="dept_modal_save_btn"><i class="fas fa-upload"></i> រក្សាទុក</button>
                            </div>
                        </div>
                    </div>
                </div>

    <!-- NEW: SIGNATURE HISTORY MODAL -->
    <div class="modal fade" id="signatureHistoryModal" tabindex="-1" aria-labelledby="signatureHistoryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="signatureHistoryModalLabel"><i class="fas fa-history"></i> ប្រវត្តិហត្ថលេខា</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="signatureHistoryContent" style="max-height: 70vh; overflow-y: auto;">
                    <!-- History content will be loaded here by JavaScript -->
                    <div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>
                </div>
                 <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">បិទ</button>
                </div>
            </div>
        </div>
    </div>


    <div class="print-request-form" id="printableForm" style="display: none;">
        <div class="header">
            <img src="https://i.ibb.co/x86F4TfC/Logo-Van-Van-2.png" alt="VanVan Cambodia Logo" class="print-logo">
        </div>
        <span class="span">សំណើសុំច្បាប់ឈប់សម្រាក់ ប្ដូរដេអូស ចេញមុនម៉ោង មកយឺត និងភ្លេចស្កេនមេដៃវត្តមាន</span>
        <div class="container" id="printContainer">
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const isAdminJS = <?php echo json_encode($isAdmin); ?>;
        const currentUserIdJS = <?php echo json_encode($currentUserId); ?>;
        const currentUserFullNameJS = <?php echo json_encode($currentUserFullName); ?>;

        document.addEventListener('DOMContentLoaded', function () {
            const detailModalEl = document.getElementById('detailModal');
            const detailModalInstance = new bootstrap.Modal(detailModalEl);
            // NEW: Instance for History Modal
            const signatureHistoryModalEl = document.getElementById('signatureHistoryModal');
            const signatureHistoryModalInstance = new bootstrap.Modal(signatureHistoryModalEl);
            let currentRequestForDetailModal;

            function formatDate(dateString) {
                if (dateString === 'N/A') return 'N/A';
                if (!dateString || dateString === '0000-00-00') return '';
                try {
                    const date = new Date(dateString);
                    if (isNaN(date.getTime())) return dateString;
                    const day = String(date.getDate()).padStart(2, '0');
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const year = date.getFullYear();
                    return `${day}-${month}-${year}`;
                } catch (e) { 
                    return dateString;
                }
            }
            
            function formatTime(timeString) {
                if (!timeString || timeString === '00:00:00' || timeString === 'N/A') return 'N/A';
                return timeString.substring(0, 5);
            }

            function populateDetailModal(requestData, canEditThisRequest) {
                currentRequestForDetailModal = requestData;
                document.getElementById('edit_id_field').value = requestData.id || '';
                
                detailModalEl.querySelectorAll('.display-text').forEach(span => {
                    const fieldName = span.dataset.field;
                    let value = requestData[fieldName] !== null && requestData[fieldName] !== undefined ? requestData[fieldName] : 'N/A';
                    if (span.dataset.format === 'date') value = formatDate(value);
                    if (span.dataset.format === 'time') value = formatTime(value);
                    span.textContent = value;
                });

                detailModalEl.querySelectorAll('.edit-field').forEach(input => {
                    const fieldName = input.dataset.editField;
                    let value = requestData[fieldName] || '';
                    if (input.type === 'date' && value) {
                        try {
                            const d = new Date(value);
                            if (!isNaN(d.getTime())) {
                                value = d.toISOString().split('T')[0];
                            } else { value = ''; }
                        } catch (e) { value = ''; }
                    }
                    input.value = value;
                    input.readOnly = !isAdminJS && (fieldName === 'requester_name' || fieldName === 'user_id');
                });

                const signatureImage = document.getElementById('signature-image');
                const noSignatureText = document.getElementById('no-signature-text');
                
                // Reset file input
                document.getElementById('new_signature_upload').value = '';
                // Reset hidden inputs
                const sigDataInput = document.getElementById('signature_data_input');
                const delSigInput = document.getElementById('delete_signature_input');
                if (sigDataInput) sigDataInput.value = '';
                if (delSigInput) delSigInput.value = '0';

                if (requestData.signature && requestData.signature.startsWith('data:image')) {
                    signatureImage.src = requestData.signature;
                    signatureImage.style.display = 'block';
                    noSignatureText.style.display = 'none';
                } else {
                    signatureImage.src = ''; 
                    signatureImage.style.display = 'none';
                    noSignatureText.style.display = 'block';
                }

                // Toggle delete button
                const deleteBtn = document.getElementById('delete_signature_btn');
                if (deleteBtn) {
                    deleteBtn.style.display = (requestData.signature && requestData.signature.startsWith('data:image')) ? 'inline-block' : 'none';
                }

                // NEW: Handle history button visibility and count
                const historyBtn = document.getElementById('view_signature_history_btn');
                const historyCountSpan = document.getElementById('history_count');
                const historyCount = requestData.signature_history_count || 0;
                historyCountSpan.textContent = historyCount;
                if (historyCount > 0) {
                    historyBtn.style.display = 'inline-block';
                } else {
                    historyBtn.style.display = 'none';
                }

                // Handle pull previous signature button
                const pullPrevSigBtn = document.getElementById('pull_prev_signature_btn');
                if (pullPrevSigBtn) {
                    pullPrevSigBtn.style.display = 'none'; // hidden by default
                    if (!requestData.signature || !requestData.signature.startsWith('data:image')) {
                        // If signature missing, check if this user has any previous one
                        const uId = requestData.user_id;
                        const rName = requestData.requester_name;
                        fetch(`<?php echo BASE_URL; ?>?action=get_latest_signature&user_id=${uId}&requester_name=${rName}`)
                            .then(res => res.json())
                            .then(json => {
                                if (json.success && json.data && json.data.signature) {
                                    pullPrevSigBtn.style.display = 'inline-block';
                                    pullPrevSigBtn.dataset.prevSig = json.data.signature;
                                    pullPrevSigBtn.dataset.prevDate = json.data.signature_date;
                                }
                            });
                    }
                }

                toggleDetailModalEditMode(false, canEditThisRequest);
            }

            function toggleDetailModalEditMode(isEditing, canEditThisRequest) {
                const editButton = document.getElementById('detail_edit_button');
                const saveButton = document.getElementById('detail_save_button');
                const closeButton = document.getElementById('detail_close_button');
                const printButton = document.getElementById('detail_print_button');
                const downloadPdfButton = document.getElementById('detail_download_pdf_button');
                
                detailModalEl.querySelectorAll('.detail-item').forEach(item => {
                    const displaySpan = item.querySelector('.display-text');
                    const editInput = item.querySelector('.edit-field');
                    if (displaySpan && editInput) {
                        displaySpan.style.display = isEditing ? 'none' : 'inline-block';
                        editInput.style.display = isEditing ? 'block' : 'none';
                    }
                });

                // NEW: Show/hide signature upload controls
                const signatureControls = document.querySelector('.signature-controls');
                if (signatureControls) {
                    signatureControls.style.display = isEditing && canEditThisRequest ? 'block' : 'none';
                }

                if (editButton) editButton.style.display = (isEditing || !canEditThisRequest) ? 'none' : 'inline-block';
                if (saveButton) saveButton.style.display = isEditing && canEditThisRequest ? 'inline-block' : 'none';
                if (printButton) printButton.style.display = isEditing ? 'none' : 'inline-block';
                if (downloadPdfButton) downloadPdfButton.style.display = isEditing ? 'none' : 'inline-block';
                if (closeButton) closeButton.innerHTML = isEditing ? '<i class="fas fa-times"></i> បោះបង់' : '<i class="fas fa-times"></i> បិទ';
            }

            // Direct click handler for View/Edit button for better reliability
            document.addEventListener('click', function(e) {
                const btn = e.target.closest('.btn-detail');
                if (btn) {
                    console.log('View/Edit button clicked manually');
                    const requestId = btn.getAttribute('data-request-id');
                    const canEditThisRequest = btn.getAttribute('data-can-edit') === 'true';
                    
                    if (!requestId) return;

                    // Reset and Show Modal
                    const editIdField = document.getElementById('edit_id_field');
                    if (editIdField) editIdField.value = requestId;
                    detailModalEl.querySelectorAll('.display-text').forEach(span => span.textContent = 'កំពុងផ្ទុក...');
                    detailModalEl.querySelectorAll('.edit-field').forEach(input => input.value = '');
                    
                    const sigImg = document.getElementById('signature-image');
                    const deptSigImg = document.getElementById('dept-signature-image');
                    if (sigImg) sigImg.style.display = 'none';
                    if (deptSigImg) deptSigImg.style.display = 'none';

                    detailModalInstance.show();

                    console.log('Fetching details for ID (Manual):', requestId);
                    fetch(`<?php echo BASE_URL; ?>?action=get_request_details&id=${requestId}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.data) {
                                populateDetailModal(data.data, canEditThisRequest);
                            } else {
                                alert('បរាជ័យក្នុងការទាញយកទិន្នន័យ: ' + (data.message || 'Unknown error'));
                            }
                        })
                        .catch(err => {
                            console.error('Error fetching details:', err);
                            alert('មានបញ្ហាក្នុងការតភ្ជាប់ទៅកាន់ Server');
                        });
                }
            });

            // Handle Print from table dropdown
            document.addEventListener('click', async function(e) {
                const printBtn = e.target.closest('.btn-table-print');
                if (printBtn) {
                    const requestId = printBtn.getAttribute('data-request-id');
                    if (!requestId) return;

                    const originalContent = printBtn.innerHTML;
                    printBtn.disabled = true;
                    printBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> កំពុងរៀបចំ...';

                    try {
                        const res = await fetch(`<?php echo BASE_URL; ?>?action=get_request_details&id=${requestId}`);
                        const data = await res.json();
                        if (data.success && data.data) {
                            const printContentEl = document.getElementById('printableForm');
                            printContentEl.style.display = 'block';
                            populatePrintForm([data.data]);
                            setTimeout(() => { 
                                window.print(); 
                                printContentEl.style.display = 'none'; 
                            }, 250);
                        } else {
                            alert('បរាជ័យក្នុងការទាញយកទិន្នន័យ');
                        }
                    } catch (err) {
                        console.error('Error fetching print details:', err);
                        alert('មានកំហុសក្នុងការតភ្ជាប់ Server');
                    } finally {
                        printBtn.disabled = false;
                        printBtn.innerHTML = originalContent;
                    }
                }

                // Handle PDF Download from table dropdown
                const pdfBtn = e.target.closest('.btn-table-pdf');
                if (pdfBtn) {
                    const requestId = pdfBtn.getAttribute('data-request-id');
                    if (!requestId) return;

                    const originalContent = pdfBtn.innerHTML;
                    pdfBtn.disabled = true;
                    pdfBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> កំពុងបង្កើត...';

                    try {
                        const res = await fetch(`<?php echo BASE_URL; ?>?action=get_request_details&id=${requestId}`);
                        const data = await res.json();
                        if (data.success && data.data) {
                            currentRequestForDetailModal = data.data; // Essential for downloadRequestAsPDF
                            await downloadRequestAsPDF();
                        } else {
                            alert('បរាជ័យក្នុងការទាញយកទិន្នន័យ');
                        }
                    } catch (err) {
                        console.error('Error fetching PDF details:', err);
                        alert('មានកំហុសក្នុងការតភ្ជាប់ Server');
                    } finally {
                        pdfBtn.disabled = false;
                        pdfBtn.innerHTML = originalContent;
                    }
                }
            });
            
            detailModalEl.addEventListener('hide.bs.modal', function(event) {
                // Reset to view mode when modal is hidden
                toggleDetailModalEditMode(false, false);
            });

            document.getElementById('detail_edit_button')?.addEventListener('click', function() {
                toggleDetailModalEditMode(true, true); // Assuming if button is visible, they can edit
            });

            document.getElementById('detail_close_button')?.addEventListener('click', function(e) {
                // Check if in edit mode (by checking save button visibility)
                if (document.getElementById('detail_save_button')?.style.display !== 'none') {
                    e.preventDefault(); // Prevent modal from closing immediately
                    // Re-populate original data to cancel changes before closing
                    populateDetailModal(currentRequestForDetailModal, true); 
                    toggleDetailModalEditMode(false, true);
                } else {
                    detailModalInstance.hide();
                }
            });

            // Utility: read file as DataURL
            function readFileAsDataURL(file) {
                return new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = e => resolve(e.target.result);
                    reader.onerror = () => reject(new Error('មិនអាចអានរូបភាពបានទេ'));
                    reader.readAsDataURL(file);
                });
            }

            // Utility: remove white/background to transparent PNG
            async function removeBackgroundToPng(dataUrl, targetW, targetH) {
                const img = await new Promise((resolve, reject) => {
                    const im = new Image();
                    im.onload = () => resolve(im);
                    im.onerror = () => reject(new Error('មិនអាចផ្ទុករូបភាពបានទេ'));
                    im.src = dataUrl;
                });

                const scale = Math.min((targetW||img.naturalWidth)/img.naturalWidth, (targetH||img.naturalHeight)/img.naturalHeight, 1);
                const w = Math.max(1, Math.round(img.naturalWidth * scale));
                const h = Math.max(1, Math.round(img.naturalHeight * scale));

                const off = document.createElement('canvas');
                off.width = w; off.height = h;
                const ctx = off.getContext('2d');
                ctx.drawImage(img, 0, 0, w, h);

                // Estimate bg color from corners
                function sample(x, y, bw=8, bh=8){
                    const sx = Math.max(0, Math.min(w-bw, x));
                    const sy = Math.max(0, Math.min(h-bh, y));
                    const d = ctx.getImageData(sx, sy, bw, bh).data; let r=0,g=0,b=0,n=0;
                    for(let i=0;i<d.length;i+=4){ r+=d[i]; g+=d[i+1]; b+=d[i+2]; n++; }
                    return [r/n,g/n,b/n];
                }
                const c1=sample(0,0), c2=sample(w-8,0), c3=sample(0,h-8), c4=sample(w-8,h-8);
                const bg=[(c1[0]+c2[0]+c3[0]+c4[0])/4,(c1[1]+c2[1]+c3[1]+c4[1])/4,(c1[2]+c2[2]+c3[2]+c4[2])/4];
                // Fix bg average calculation (fallback to white if NaN)
                for (let i=0;i<3;i++){ if(!isFinite(bg[i])) bg[i]=255; }

                const imgData = ctx.getImageData(0,0,w,h); const d=imgData.data;
                const tol=35, tol2=tol*tol, whiteThr=245;
                function dist2(r,g,b){ const dr=r-bg[0], dg=g-bg[1], db=b-bg[2]; return dr*dr+dg*dg+db*db; }
                for(let i=0;i<d.length;i+=4){
                    const r=d[i], g=d[i+1], b=d[i+2];
                    const nearWhite = (r>=whiteThr && g>=whiteThr && b>=whiteThr);
                    const nearBg = dist2(r,g,b) <= tol2;
                    if (nearWhite || nearBg) d[i+3]=0;
                }
                ctx.putImageData(imgData,0,0);
                return off.toDataURL('image/png');
            }

            // Process uploaded signature: clean background, preview, and store hidden data
            document.getElementById('new_signature_upload').addEventListener('change', async function(event) {
                const file = event.target.files[0];
                if (!file) return;
                if (!/^image\//i.test(file.type)) { alert('សូមជ្រើសរើសឯកសាររូបភាព (PNG/JPG/GIF)'); return; }
                try {
                    const raw = await readFileAsDataURL(file);
                    const display = document.getElementById('signature-image');
                    const noText = document.getElementById('no-signature-text');
                    const area = document.getElementById('signature-display-area');
                    const cleaned = await removeBackgroundToPng(raw, area.clientWidth-10, area.clientHeight-10);
                    display.src = cleaned; display.style.display = 'block'; noText.style.display='none';
                    // set hidden fields
                    document.getElementById('signature_data_input').value = cleaned;
                    document.getElementById('delete_signature_input').value = '0';
                    // show delete button since there will be a signature after save
                    const delBtn = document.getElementById('delete_signature_btn');
                    if (delBtn) delBtn.style.display = 'inline-block';
                } catch (e) {
                    alert('មានបញ្ហាក្នុងការដំណើរការហត្ថលេខា: ' + e.message);
                }
            });

            // Department head signature processing (similar to requester signature)
            document.getElementById('new_dept_head_signature_upload')?.addEventListener('change', async function(event) {
                const file = event.target.files[0];
                if (!file) return;
                if (!/^image\//i.test(file.type)) { alert('សូមជ្រើសរើសឯកសាររូបភាព (PNG/JPG/GIF)'); return; }
                try {
                    const raw = await readFileAsDataURL(file);
                    const display = document.getElementById('dept-signature-image');
                    const noText = document.getElementById('no-dept-signature-text');
                    const area = document.getElementById('dept-signature-display-area');
                    const cleaned = await removeBackgroundToPng(raw, area.clientWidth-10, area.clientHeight-10);
                    display.src = cleaned; display.style.display = 'block'; noText.style.display='none';
                    // set hidden fields
                    document.getElementById('department_head_signature_data_input').value = cleaned;
                    document.getElementById('delete_department_head_signature_input').value = '0';
                    // show delete button
                    const delBtn = document.getElementById('delete_dept_head_signature_btn');
                    if (delBtn) delBtn.style.display = 'inline-block';
                    // show controls area
                    const controls = document.querySelector('.dept-signature-controls'); if (controls) controls.style.display = 'block';
                } catch (e) {
                    alert('មានបញ្ហាក្នុងការដំណើរការហត្ថលេខាប្រធានផ្នែក: ' + e.message);
                }
            });

            // Delete department head signature handler
            document.getElementById('delete_dept_head_signature_btn')?.addEventListener('click', function(){
                if (!confirm('តើអ្នកចង់លុបហត្ថលេខាប្រធានផ្នែកនេះមែនទេ?')) return;
                document.getElementById('department_head_signature_data_input').value = '';
                document.getElementById('delete_department_head_signature_input').value = '1';
                // UI
                const img = document.getElementById('dept-signature-image');
                const noText = document.getElementById('no-dept-signature-text');
                img.src = ''; img.style.display='none'; noText.style.display='block';
            });

            // Delete signature handler
            document.getElementById('delete_signature_btn')?.addEventListener('click', function(){
                if (!confirm('តើអ្នកចង់លុបហត្ថលេខានេះមែនទេ?')) return;
                document.getElementById('signature_data_input').value = '';
                document.getElementById('delete_signature_input').value = '1';
                // UI
                const img = document.getElementById('signature-image');
                const noText = document.getElementById('no-signature-text');
                img.src = ''; img.style.display='none'; noText.style.display='block';
            });
            
            // Pull previous signature handler
            document.getElementById('pull_prev_signature_btn')?.addEventListener('click', function(){
                const sig = this.dataset.prevSig;
                const date = this.dataset.prevDate;
                if (!sig) return;
                
                const img = document.getElementById('signature-image');
                const noText = document.getElementById('no-signature-text');
                const sigDataInput = document.getElementById('signature_data_input');
                
                img.src = sig; img.style.display='block'; noText.style.display='none';
                if (sigDataInput) sigDataInput.value = sig;
                // Optionally update signature date if needed
                
                this.style.display = 'none'; // hide after pulling
                const delBtn = document.getElementById('delete_signature_btn');
                if (delBtn) delBtn.style.display = 'inline-block';
                
                showAlert('ទាញយកហត្ថលេខាមុនបានជោគជ័យ! សូមចុចរក្សាទុកដើម្បីរក្សាការផ្លាស់ប្តូរ។', 'success');
            });
            
            // NEW: Event Listener for History Button Click
            document.getElementById('view_signature_history_btn').addEventListener('click', function() {
                if (!currentRequestForDetailModal) return;

                const requestId = currentRequestForDetailModal.id;
                const historyContentEl = document.getElementById('signatureHistoryContent');
                // Show loading spinner
                historyContentEl.innerHTML = '<div class="text-center p-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
                
                signatureHistoryModalInstance.show();

                fetch(`<?php echo BASE_URL; ?>?action=get_signature_history&request_id=${requestId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.history.length > 0) {
                            let content = '';
                            data.history.forEach(item => {
                                const changedDate = new Date(item.changed_at).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' });
                                content += `
                                    <div class="history-item">
                                        <p class="mb-1"><strong><i class="fas fa-calendar-alt text-muted"></i> បានផ្លាស់ប្តូរនៅ:</strong> ${changedDate}</p>
                                        <p class="mb-2"><strong><i class="fas fa-user text-muted"></i> ផ្លាស់ប្តូរដោយ:</strong> ${item.full_name || 'អ្នកប្រើមិនស្គាល់'}</p>
                                        <p class="mb-1"><strong>ហត្ថលេខាមុន:</strong></p>
                                        ${item.old_signature ? `<img src="${item.old_signature}" class="history-signature-img">` : '<span class="text-muted">មិនមានទិន្នន័យ</span>'}
                                    </div>
                                `;
                            });
                            historyContentEl.innerHTML = content;
                        } else {
                            historyContentEl.innerHTML = '<p class="text-center p-3">មិនមានប្រវត្តិហត្ថលេខាទេ។</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching signature history:', error);
                        historyContentEl.innerHTML = '<p class="text-center text-danger p-3">មានកំហុសក្នុងការទាញយកប្រវត្តិ។</p>';
                    });
            });
            
            // Enable delete modal for users who can delete
            document.getElementById('deleteConfirmModal')?.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                document.getElementById('deleteConfirmIdInput').value = button.getAttribute('data-id');
                document.getElementById('deleteRequestNameDisplay').textContent = button.getAttribute('data-name');
            });
            
            function populatePrintForm(requestsToPrint) {
                const container = document.getElementById('printContainer');
                container.innerHTML = '';
                requestsToPrint.forEach(request => {
                    const requestType = (request.request_type || '').toLowerCase();
                    const annualClass = requestType.includes('សម្រាកប្រចាំឆ្នាំ') || requestType.includes('annual') ? 'selected' : '';
                    const sickClass = requestType.includes('សម្រាកដោយជំងឺ') || requestType.includes('sick') ? 'selected' : '';
                    const forgotFpClass = requestType.includes('ភ្លេចស្កេនមេដៃ') || requestType.includes('forgot') ? 'selected' : '';
                    const maternityClass = requestType.includes('សម្រាកលំហែមាតុភាព') || requestType.includes('maternity') ? 'selected' : '';
                    const otClass = (requestType.includes('ថែមម៉ោង') || requestType.includes('ot')) && !requestType.includes('forgot') ? 'selected' : '';
                    const earlyClass = requestType.includes('ចេញមុនម៉ោង') || requestType.includes('early') ? 'selected' : '';
                    const changingOffClass = requestType.includes('ប្តូរថ្ងៃសម្រាក') || requestType.includes('changing') ? 'selected' : '';
                    const specialClass = requestType.includes('សម្រាកពិសេស') || requestType.includes('special') ? 'selected' : '';
                    const lateClass = requestType.includes('មកយឺត') || requestType.includes('late') ? 'selected' : '';
                    const reqSafe = (key, def = 'N/A') => request[key] != null && request[key] !== '' ? request[key] : def;
                    const signatureDataUrl = reqSafe('signature', '');
                    let signatureCellHtml;
                    const cellHeight = '75px'; 
                    if (signatureDataUrl && signatureDataUrl.startsWith('data:image')) {
                        signatureCellHtml = `<th style="position: relative; height: ${cellHeight}; vertical-align: middle;"><img src="${signatureDataUrl}" style="position: absolute; max-width: 250px; max-height: 100px; object-fit: contain; left: 40%; top: 40%; transform: translate(-50%, -50%); pointer-events: none;"></th>`;
                    } else {
                        signatureCellHtml = `<th style="height: ${cellHeight}; vertical-align: middle;"></th>`;
                    }
                    // Department head signature (if exists)
                    const deptSignatureDataUrl = reqSafe('department_head_signature', '');
                    let deptSignatureCellHtml;
                    if (deptSignatureDataUrl && deptSignatureDataUrl.startsWith('data:image')) {
                        deptSignatureCellHtml = `<th style="position: relative; height: ${cellHeight}; vertical-align: middle;"><img src="${deptSignatureDataUrl}" style="position: absolute; max-width: 250px; max-height: 100px; object-fit: contain; left: 40%; top: 40%; transform: translate(-50%, -50%); pointer-events: none;"></th>`;
                    } else {
                        deptSignatureCellHtml = `<th style="height: ${cellHeight}; vertical-align: middle;"></th>`;
                    }
                    const signatureDisplayDate = reqSafe('signature_date', null) || reqSafe('request_date');
                    
                    const formContent = `
                        <table class="form-table">
                            <tr><td colspan="5" class="value"><div class="icon-group"><div class="request-icon-print ${annualClass}">សម្រាកប្រចាំឆ្នាំ (Annual Leave)</div><div class="request-icon-print ${sickClass}">សម្រាកដោយជំងឺ (Sick Leave)</div><div class="request-icon-print ${forgotFpClass}">ភ្លេចស្កេនមេដៃ (Forgot FP)</div><div class="request-icon-print ${maternityClass}">សម្រាកលំហែមាតុភាព (Maternity Leave)</div><div class="request-icon-print ${otClass}">ថែមម៉ោង (OT)</div><div class="request-icon-print ${earlyClass}">ចេញមុនម៉ោង (Early)</div><div class="request-icon-print ${changingOffClass}">ប្តូរថ្ងៃសម្រាក (Changing day off)</div><div class="request-icon-print ${specialClass}">សម្រាកពិសេស (Special Leave)</div><div class="request-icon-print ${lateClass}">មកយឺត (Late)</div></div></td></tr>
                            <tr><td style="text-align: left; width:8rem;">ឈ្មោះអ្នកស្នើរសុំ៖</td><td>${reqSafe('requester_name')}</td><td>ចំនួនថ្ងៃ/ច្បាប់នៅសល់៖</td><td>${reqSafe('number_of_days')} ថ្ងៃ</td><td>${reqSafe('remaining_days')} ថ្ងៃ</td></tr>
                            <tr><td style="text-align: left; width:8rem;">ផ្នែក/មុខតំណែង/សាខា៖</td><td>${reqSafe('department')}</td><td>${reqSafe('position')}</td><td colspan="2">${reqSafe('branch')}</td></tr>
                            <tr><td style="text-align: left;">ថ្ងៃខែឆ្នាំសុំឈប់៖</td><td>${formatDate(reqSafe('request_date'))}</td><td>ចំនួនម៉ោងយឺត/ចេញមុន៖</td><td colspan="2">${reqSafe('late_hours')}</td></tr>
                            <tr><td style="text-align: left;">ថ្ងៃចូលធ្វើការវិញ/ថ្ងៃសងវិញ៖</td><td>${formatDate(reqSafe('return_date'))}</td><td>ភ្លេចស្កេនមេដៃ៖</td><td>${reqSafe('forgot_scan_in')}</td><td>${reqSafe('forgot_scan_out')}</td></tr>
                            <tr><td style="text-align: left;">ម៉ោងចេញចូល(ការងារ)៖</td><td style="text-align: left;"><span style="display: inline-flex;">ម៉ោងចូល៖</span><span style="padding-left: 1rem; display: inline-flex;">${formatTime(reqSafe('time_in'))}</span></td><td style="text-align: left;"><span style="display: inline-flex;">ម៉ោងចេញ៖</span><span style="padding-left: 1rem; display: inline-flex;">${formatTime(reqSafe('time_out'))}</span></td><td colspan="2" style="text-align: left;"><span style="display: inline-flex;">ម៉ោងសរុប៖</span><span style="padding-left: 1rem; display: inline-flex;">${reqSafe('total_hours')}</span></td></tr>
                            <tr><td style="text-align: left;">ម៉ោងធ្វើការសងវិញ៖</td><td style="text-align: left;"><span style="display: inline-flex;">ម៉ោងចូលសង៖</span><span style="padding-left: 0.2rem; display: inline-flex;">${formatTime(reqSafe('repay_time_in'))}</span></td><td style="text-align: left;"><span style="display: inline-flex;">ម៉ោងចេញសង៖</span><span style="padding-left: 0.2rem; display: inline-flex;">${formatTime(reqSafe('repay_time_out'))}</span></td><td colspan="2" style="text-align: left;"><span style="display: inline-flex;">ម៉ោងសងសរុប៖</span><span style="padding-left: 0.2rem; display: inline-flex;">${reqSafe('repay_total_hours')}</span></td></tr>
                            <tr><td style="text-align: left;">មូលហេតុ៖</td><td colspan="4" style="text-align: center; white-space: pre-wrap;">${reqSafe('reason')}</td></tr>
                            <tr><td style="text-align: left;">ទីកន្លែងអំឡុងពេលឈប់៖</td><td colspan="4" style="text-align: left;">${reqSafe('location')}</td></tr>
                            <tr><td style="text-align: left;">លេខទំនាក់ទំនងបន្ទាន់៖</td><td style="text-align: left;">${reqSafe('contact_number')}</td><td>ប្រគល់ការងារឱ្យ៖</td><td colspan="2" style="text-align: left;">${reqSafe('assigned_to')}</td></tr>
                        </table>
                        <table class="main-footer">
                            <tr>
                                <th style="text-align: left; vertical-align: middle;">បញ្ជាក់/អនុម័តដោយ</th>
                                <th style="vertical-align: middle;">ឈ្មោះ (Name)</th>
                                <th style="vertical-align: middle;">ហត្ថលេខា (Signature)</th>
                                <th colspan="2" style="vertical-align: middle;">ថ្ងៃខែឆ្នាំ (Date)</th>
                            </tr>
                            <tr>
                                <th style="height: ${cellHeight}; text-align: left; vertical-align: middle;">អ្នកស្នើរសុំ</th>
                                <th style="height: ${cellHeight}; vertical-align: middle;">${reqSafe('requester_name')}</th>
                                ${signatureCellHtml}
                                <th colspan="2" style="height: ${cellHeight}; vertical-align: middle;">${formatDate(signatureDisplayDate)}</th>
                            </tr>
                            <tr>
                                <th style="height: ${cellHeight}; text-align: left; vertical-align: middle;">ប្រធានផ្នែក</th>
                                <th style="height: ${cellHeight}; vertical-align: middle;">${reqSafe('department_head_name', '')}</th>
                                ${deptSignatureCellHtml}
                                <th colspan="2" style="height: ${cellHeight}; vertical-align: middle;">${formatDate(reqSafe('department_head_signature_date', ''))}</th>
                            </tr>
                            <tr>
                                <th style="height: ${cellHeight}; text-align: left; vertical-align: middle;">ប្រធានធនធានមនុស្ស</th>
                                <th style="height: ${cellHeight}; vertical-align: middle;">_________________________</th>
                                <th style="height: ${cellHeight}; vertical-align: middle;">_________________________</th>
                                <th colspan="2" style="height: ${cellHeight}; vertical-align: middle;">_________________________</th>
                            </tr>
                            <tr>
                                <th style="height: ${cellHeight}; text-align: left; vertical-align: middle;">ប្រធានគ្រប់គ្រងទូទៅ</th>
                                <th style="height: ${cellHeight}; vertical-align: middle;">_________________________</th>
                                <th style="height: ${cellHeight}; vertical-align: middle;">_________________________</th>
                                <th colspan="2" style="height: ${cellHeight}; vertical-align: middle;">_________________________</th>
                            </tr>
                            <tr>
                                <th style="height: ${cellHeight}; text-align: left; vertical-align: middle;">អគ្គនាយិកា</th>
                                <th style="height: ${cellHeight}; vertical-align: middle;">_________________________</th>
                                <th style="height: ${cellHeight}; vertical-align: middle;">_________________________</th>
                                <th colspan="2" style="height: ${cellHeight}; vertical-align: middle;">_________________________</th>
                            </tr>
                        </table>
                        <div style="page-break-after: always;"></div>`;
                    
                    container.insertAdjacentHTML('beforeend', formContent);
                });
            }

            document.getElementById('printRequestFormButton')?.addEventListener('click', async function() {
                const visibleButtons = Array.from(document.querySelectorAll('table tbody tr:not([style*="display: none"]) .btn-detail'));
                if (visibleButtons.length === 0) { alert("គ្មានទិន្នន័យសម្រាប់បោះពុម្ព"); return; }
                
                const originalText = this.innerHTML;
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> កំពុងទាញយកទិន្នន័យ...';
                
                try {
                    const requestsToPrint = [];
                    for (const btn of visibleButtons) {
                        const id = btn.getAttribute('data-request-id');
                        const res = await fetch(`<?php echo BASE_URL; ?>?action=get_request_details&id=${id}`);
                        const data = await res.json();
                        if (data.success) requestsToPrint.push(data.data);
                    }
                    
                    const printContentEl = document.getElementById('printableForm');
                    printContentEl.style.display = 'block';
                    populatePrintForm(requestsToPrint);
                    setTimeout(() => { window.print(); printContentEl.style.display = 'none'; }, 250);
                } catch (e) {
                    alert('មានកំហុសក្នុងការរៀបចំការបោះពុម្ព');
                    console.error(e);
                } finally {
                    this.disabled = false;
                    this.innerHTML = originalText;
                }
            });
            
            document.getElementById('detail_print_button')?.addEventListener('click', function() {
                if (!currentRequestForDetailModal) { alert("មិនមានទិន្នន័យសំណើដើម្បីបោះពុម្ពពី Modal ទេ។"); return; }
                const printContentEl = document.getElementById('printableForm');
                printContentEl.style.display = 'block';
                populatePrintForm([currentRequestForDetailModal]);
                setTimeout(() => { window.print(); printContentEl.style.display = 'none'; }, 250);
            });

            async function downloadRequestAsPDF() {
                if (!currentRequestForDetailModal) {
                    alert("មិនមានទិន្នន័យសំណើដើម្បីបង្កើតជា PDF ទេ។");
                    return;
                }

                const button = document.getElementById('detail_download_pdf_button');
                const originalButtonText = button.innerHTML;
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> កំពុងបង្កើត...';
                
                const printFormContainer = document.getElementById('printableForm');
                populatePrintForm([currentRequestForDetailModal]);

                printFormContainer.style.position = 'absolute';
                printFormContainer.style.left = '-9999px';
                printFormContainer.style.top = '0';
                printFormContainer.style.display = 'block';
                printFormContainer.style.padding = '15px';

                const formToCapture = document.getElementById('printableForm');
                
                try {
                    // Temporarily apply PDF capture mode to remove theme/backgrounds
                    const styleId = 'pdf-capture-style';
                    let styleEl = document.getElementById(styleId);
                    if (!styleEl) {
                        styleEl = document.createElement('style');
                        styleEl.id = styleId;
                        styleEl.innerHTML = `
                            .pdf-mode, .pdf-mode * { background-image: none !important; background-color: #ffffff !important; box-shadow: none !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
                            .pdf-mode th, .pdf-mode .request-icon-print, .pdf-mode td { background-color: transparent !important; color: #000 !important; }
                            .pdf-mode img { background: transparent !important; }
                        `;
                        document.head.appendChild(styleEl);
                    }
                    formToCapture.classList.add('pdf-mode');

                    const canvas = await html2canvas(formToCapture, { 
                        scale: 2.5, 
                        useCORS: true, 
                        backgroundColor: null 
                    });
                    const imgData = canvas.toDataURL('image/png');
                    const { jsPDF } = window.jspdf;
                    
                    const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a5' });
                    const pdfWidth = pdf.internal.pageSize.getWidth();
                    const pdfHeight = pdf.internal.pageSize.getHeight();
                    const imgProps = pdf.getImageProperties(imgData);
                    const imgRatio = imgProps.height / imgProps.width;
                    
                    let finalWidth = pdfWidth - 10;
                    let finalHeight = finalWidth * imgRatio;

                    if (finalHeight > pdfHeight - 10) {
                        finalHeight = pdfHeight - 10;
                        finalWidth = finalHeight / imgRatio;
                    }
                    
                    const x = (pdfWidth - finalWidth) / 2;
                    const y = (pdfHeight - finalHeight) / 2;
                    
                    pdf.addImage(imgData, 'PNG', x, y, finalWidth, finalHeight);
                    const fileName = `Request-Form-ID-${currentRequestForDetailModal.id}-${currentRequestForDetailModal.requester_name}.pdf`;
                    pdf.save(fileName);

                } catch (error) {
                    console.error("Error generating PDF:", error);
                    alert("មានបញ្ហាក្នុងការបង្កើត PDF។ សូមព្យាយាមម្តងទៀត។");
                } finally {
                    printFormContainer.style.display = 'none';
                    printFormContainer.style.position = '';
                    printFormContainer.style.left = '';
                    printFormContainer.style.top = '';
                    printFormContainer.style.padding = '';
                    // Remove temporary PDF capture overrides
                    try {
                        formToCapture.classList.remove('pdf-mode');
                        const styleElRem = document.getElementById('pdf-capture-style');
                        if (styleElRem && styleElRem.parentNode) styleElRem.parentNode.removeChild(styleElRem);
                    } catch (e) {
                        console.warn('Could not remove pdf-mode styles:', e);
                    }
                    button.disabled = false;
                    button.innerHTML = originalButtonText;
                }
            }

            document.getElementById('detail_download_pdf_button')?.addEventListener('click', downloadRequestAsPDF);

            document.getElementById('searchInput')?.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                document.querySelectorAll('table tbody tr').forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(searchTerm) ? '' : 'none';
                });
            });

            // ================= QUICK SIGNATURE UPLOAD LOGIC =================
            const quickModalEl = document.getElementById('quickSignatureModal');
            const quickModalInstance = new bootstrap.Modal(quickModalEl);
            const quickFileInput = document.getElementById('quick_signature_file');
            const quickPreviewImg = document.getElementById('quick_signature_preview');
            const quickEmptyText = document.getElementById('quick_signature_empty');
            const quickRequestIdInput = document.getElementById('quick_request_id');
            const quickSaveBtn = document.getElementById('quick_signature_save_btn');

            // Background removal utility (reuse simplified variant)
            async function cleanSignature(dataUrl) {
                const img = await new Promise((resolve, reject) => { const im = new Image(); im.onload=()=>resolve(im); im.onerror=()=>reject(new Error('មិនអាចអានរូបភាពបាន')); im.src=dataUrl; });
                const w = img.naturalWidth; const h = img.naturalHeight; const c = document.createElement('canvas'); c.width=w; c.height=h; const ctx=c.getContext('2d'); ctx.drawImage(img,0,0); const d=ctx.getImageData(0,0,w,h); const a=d.data;
                function sample(x,y){ const k=ctx.getImageData(x,y,8,8).data; let r=0,g=0,b=0,n=0; for(let i=0;i<k.length;i+=4){r+=k[i];g+=k[i+1];b+=k[i+2];n++;} return [r/n,g/n,b/n]; }
                const corners=[sample(0,0),sample(w-8,0),sample(0,h-8),sample(w-8,h-8)]; let bg=[0,0,0]; for(let i=0;i<3;i++){ bg[i]=(corners[0][i]+corners[1][i]+corners[2][i]+corners[3][i])/4; if(!isFinite(bg[i])) bg[i]=255; }
                const tol=40, tol2=tol*tol, whiteThr=245; function dist2(r,g,b){const dr=r-bg[0],dg=g-bg[1],db=b-bg[2];return dr*dr+dg*dg+db*db;} for(let i=0;i<a.length;i+=4){const r=a[i],g=a[i+1],b=a[i+2]; if((r>=whiteThr&&g>=whiteThr&&b>=whiteThr)|| dist2(r,g,b)<=tol2) a[i+3]=0;} ctx.putImageData(d,0,0); return c.toDataURL('image/png');
            }

            quickFileInput.addEventListener('change', async (e) => {
                const file = e.target.files[0];
                if (!file) return;
                if (!/^image\//i.test(file.type)) { showAlert('សូមជ្រើសរើសរូបភាព', 'danger'); return; }
                try {
                    const reader = new FileReader();
                    const raw = await new Promise((res,rej)=>{ reader.onload=()=>res(reader.result); reader.onerror=()=>rej(new Error('អានឯកសារបរាជ័យ')); reader.readAsDataURL(file); });
                    const cleaned = await cleanSignature(raw);
                    quickPreviewImg.src = cleaned;
                    quickPreviewImg.style.display='block';
                    quickEmptyText.style.display='none';
                    quickPreviewImg.dataset.cleaned = cleaned; // store
                } catch (err) {
                    showAlert('មានបញ្ហាក្នុងការដំណើរការ: ' + err.message, 'danger');
                }
            });

            document.querySelectorAll('.btn-quick-signature').forEach(btn => {
                btn.addEventListener('click', () => {
                    quickRequestIdInput.value = btn.getAttribute('data-request-id');
                    quickFileInput.value = '';
                    quickPreviewImg.src = ''; quickPreviewImg.style.display='none';
                    quickEmptyText.style.display='block';
                    quickModalInstance.show();
                });
            });

            quickSaveBtn.addEventListener('click', async () => {
                const reqId = quickRequestIdInput.value;
                const dataUrl = quickPreviewImg.dataset.cleaned || '';
                if (!reqId) { showAlert('Request ID មិនត្រឹមត្រូវ', 'danger'); return; }
                if (!dataUrl) { showAlert('សូមជ្រើសរើសរូបភាពមុន', 'danger'); return; }
                quickSaveBtn.disabled = true;
                quickSaveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> កំពុងរក្សាទុក...';
                try {
                    const fd = new FormData();
                    fd.append('request_id', reqId);
                    fd.append('signature_data', dataUrl);
                    const resp = await fetch('<?php echo BASE_URL; ?>?action=quick_signature_upload', { method: 'POST', body: fd });
                    const json = await resp.json();
                    if (!json.success) throw new Error(json.message || 'Upload បរាជ័យ');
                    // No need to update data-request attribute as we now fetch via AJAX on every modal open
                    showAlert('អាប់ឡូដហត្ថលេខាជោគជ័យ!', 'success');
                    quickModalInstance.hide();
                } catch (err) {
                    showAlert('មានបញ្ហា: ' + err.message, 'danger');
                } finally {
                    quickSaveBtn.disabled = false;
                    quickSaveBtn.innerHTML = '<i class="fas fa-upload"></i> រក្សាទុក';
                }
            });
            // ================= END QUICK SIGNATURE UPLOAD =================

            // ============= DEPARTMENT-HEAD SIGNATURE MODAL LOGIC =============
            const deptModalEl = document.getElementById('deptSignatureModal');
            const deptModalInstance = new bootstrap.Modal(deptModalEl);
            const deptModalFile = document.getElementById('dept_modal_file');
            const deptModalPreview = document.getElementById('dept_modal_preview');
            const deptModalEmpty = document.getElementById('dept_modal_empty');
            const deptModalRequestId = document.getElementById('dept_modal_request_id');
            const deptModalSaveBtn = document.getElementById('dept_modal_save_btn');

            // Open modal when clicking the per-row button
            document.querySelectorAll('.btn-dept-detail').forEach(btn => {
                btn.addEventListener('click', () => {
                    const requestId = btn.getAttribute('data-request-id');
                    if (!requestId) return;
                    
                    deptModalRequestId.value = requestId;
                    deptModalFile.value = '';
                    deptModalPreview.src = '';
                    deptModalPreview.style.display = 'none';
                    deptModalEmpty.style.display = 'block';

                    console.log('Fetching dept signature details for ID:', requestId);
                    fetch(`<?php echo BASE_URL; ?>?action=get_request_details&id=${requestId}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.success && data.data) {
                                const req = data.data;
                                if (req.department_head_signature && req.department_head_signature.startsWith('data:image')) {
                                    deptModalPreview.src = req.department_head_signature;
                                    deptModalPreview.style.display = 'block';
                                    deptModalEmpty.style.display = 'none';
                                    deptModalPreview.dataset.cleaned = req.department_head_signature;
                                } else {
                                    deptModalPreview.dataset.cleaned = '';
                                }
                            }
                            deptModalInstance.show();
                        })
                        .catch(err => {
                            console.error('Error opening dept modal:', err);
                            alert('មិនអាចបើក modal បាន');
                            deptModalInstance.show(); // Still show modal even if fetch fails
                        });
                });
            });

            // Process selected file in dept modal
            deptModalFile.addEventListener('change', async (e) => {
                const file = e.target.files[0];
                if (!file) return;
                if (!/^image\//i.test(file.type)) { showAlert('សូមជ្រើសរើសរូបភាព', 'danger'); return; }
                try {
                    const raw = await readFileAsDataURL(file);
                    const area = { clientWidth: 400, clientHeight: 200 };
                    const cleaned = await removeBackgroundToPng(raw, area.clientWidth-10, area.clientHeight-10);
                    deptModalPreview.src = cleaned; deptModalPreview.style.display = 'block'; deptModalEmpty.style.display = 'none';
                    deptModalPreview.dataset.cleaned = cleaned;
                } catch (err) {
                    console.error(err);
                    showAlert('មានបញ្ហាក្នុងការដំណើរការរូបភាព: ' + err.message, 'danger');
                }
            });

            // Save dept head signature from modal
            deptModalSaveBtn.addEventListener('click', async () => {
                const reqId = deptModalRequestId.value;
                const cleaned = deptModalPreview.dataset.cleaned || '';
                if (!reqId) { showAlert('Request ID មិនត្រឹមត្រូវ', 'danger'); return; }
                if (!cleaned) { showAlert('សូមជ្រើសរើសរូបភាពមុន', 'danger'); return; }
                deptModalSaveBtn.disabled = true;
                deptModalSaveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> កំពុងរក្សាទុក...';
                try {
                    const fd = new FormData();
                    fd.append('request_id', reqId);
                    fd.append('signature_data', cleaned);
                    fd.append('field', 'department_head_signature');
                    const resp = await fetch('<?php echo BASE_URL; ?>?action=quick_signature_upload', { method: 'POST', body: fd });
                    const json = await resp.json();
                    if (!json.success) throw new Error(json.message || 'Upload បរាជ័យ');

                    // No need to update data-request attribute as we now fetch via AJAX on every modal open

                    // Update inline thumbnail if exists
                    const thumb = document.getElementById('dept-sign-thumb-' + reqId);
                    const placeholder = document.getElementById('dept-sign-placeholder-' + reqId);
                    const delBtn = document.querySelector('.btn-dept-delete[data-request-id="' + reqId + '"]');
                    if (thumb) { thumb.src = json.signature; thumb.style.display = 'inline-block'; }
                    if (placeholder) { placeholder.style.display = 'none'; }
                    if (delBtn) delBtn.style.display = 'inline-block';

                    showAlert('អាប់ឡូដហត្ថលេខាប្រធានជោគជ័យ', 'success');
                    deptModalInstance.hide();
                } catch (err) {
                    console.error(err);
                    showAlert('មានកំហុស: ' + err.message, 'danger');
                } finally {
                    deptModalSaveBtn.disabled = false;
                    deptModalSaveBtn.innerHTML = '<i class="fas fa-upload"></i> រក្សាទុក';
                }
            });
            // ============= END DEPT-HEAD SIGNATURE MODAL LOGIC =============

            // Signature action buttons are visible by default; caret toggle removed.

            // ================= BULK DELETE FUNCTIONALITY =================
            // Select All checkbox
            document.getElementById('selectAll')?.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.rowCheckbox');
                checkboxes.forEach(cb => cb.checked = this.checked);
                updateBulkDeleteButton();
            });

            // Individual checkboxes
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('rowCheckbox')) {
                    updateBulkDeleteButton();
                    // Update selectAll state
                    const allCheckboxes = document.querySelectorAll('.rowCheckbox');
                    const checkedBoxes = document.querySelectorAll('.rowCheckbox:checked');
                    const selectAll = document.getElementById('selectAll');
                    if (selectAll) {
                        selectAll.checked = allCheckboxes.length === checkedBoxes.length && allCheckboxes.length > 0;
                        selectAll.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < allCheckboxes.length;
                    }
                }
            });

            function updateBulkDeleteButton() {
                const checkedBoxes = document.querySelectorAll('.rowCheckbox:checked');
                const bulkBtn = document.getElementById('bulkDeleteBtn');
                if (bulkBtn) {
                    bulkBtn.style.display = checkedBoxes.length > 0 ? 'inline-block' : 'none';
                }
            }

            // Bulk delete button click
            document.getElementById('bulkDeleteBtn')?.addEventListener('click', function() {
                const checkedBoxes = document.querySelectorAll('.rowCheckbox:checked');
                if (checkedBoxes.length === 0) return;

                const ids = Array.from(checkedBoxes).map(cb => cb.value);
                document.getElementById('bulkDeleteIdsInput').value = ids.join(',');
                document.getElementById('bulkDeleteCount').textContent = checkedBoxes.length;

                const modal = new bootstrap.Modal(document.getElementById('bulkDeleteConfirmModal'));
                modal.show();
            });
            // ================= END BULK DELETE =================
        });
    </script>

    <!-- Auto-dismiss and enhance alert markup behavior -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Enhance existing alert elements by injecting icon and close button if missing
            document.querySelectorAll('.alert').forEach(function(alert){
                if (!alert.classList.contains('no-enhance')) {
                    // determine type
                    var type = 'info';
                    if (alert.classList.contains('alert-success')) type = 'success';
                    if (alert.classList.contains('alert-danger') || alert.classList.contains('alert-warning')) type = 'danger';

                    // create icon if not present
                    if (!alert.querySelector('.alert-icon')){
                        var icon = document.createElement('span');
                        icon.className = 'alert-icon';
                        icon.setAttribute('aria-hidden','true');
                        if (type === 'success') icon.innerHTML = '<i class="fas fa-check"></i>';
                        else if (type === 'danger') icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                        else icon.innerHTML = '<i class="fas fa-info"></i>';
                        alert.insertBefore(icon, alert.firstChild);
                    }

                    // wrap body text
                    if (!alert.querySelector('.alert-body')){
                        var body = document.createElement('div'); body.className = 'alert-body';
                        // move non-icon children into body
                        var nodes = Array.from(alert.childNodes).filter(n => !n.classList || !n.classList.contains('alert-icon'));
                        nodes.forEach(function(n){ body.appendChild(n); });
                        alert.appendChild(body);
                    }

                    // add close button
                    if (!alert.querySelector('.alert-close')){
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'btn-close alert-close';
                        btn.setAttribute('aria-label','Close');
                        btn.addEventListener('click', function(){ alert.remove(); });
                        alert.appendChild(btn);
                    }

                    // auto-dismiss after 5s
                    setTimeout(function(){
                        alert.classList.add('fade-out');
                        setTimeout(function(){ if (alert.parentElement) alert.remove(); }, 260);
                    }, 5000);
                }
            });
        });
    </script>

    <script>
        // Floating alerts: create container if not exists
        (function(){
            let container = document.getElementById('floatingAlerts');
            if (!container) {
                container = document.createElement('div');
                container.id = 'floatingAlerts';
                container.className = 'floating-alerts';
                document.body.appendChild(container);
            }

            // showAlert: message (string), type: 'success'|'danger'|'info', timeout in ms
            window.showAlert = function(message, type='success', timeout=4500) {
                try {
                    const el = document.createElement('div');
                    el.className = 'alert alert-' + (type === 'danger' ? 'danger' : (type === 'info' ? 'info' : 'success'));
                    el.setAttribute('role','alert');

                    const icon = document.createElement('span'); icon.className = 'alert-icon';
                    if (type === 'success') icon.innerHTML = '<i class="fas fa-check"></i>';
                    else if (type === 'danger') icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                    else icon.innerHTML = '<i class="fas fa-info"></i>';

                    const body = document.createElement('div'); body.className = 'alert-body'; body.innerHTML = message;

                    const closeBtn = document.createElement('button');
                    closeBtn.type = 'button'; closeBtn.className = 'btn-close alert-close'; closeBtn.setAttribute('aria-label','Close');
                    closeBtn.addEventListener('click', function(){
                        el.classList.add('fade-out'); setTimeout(()=>el.remove(), 260);
                    });

                    el.appendChild(icon);
                    el.appendChild(body);
                    el.appendChild(closeBtn);

                    // prepend to container so newest appear on top
                    container.insertBefore(el, container.firstChild);

                    // auto-dismiss
                    if (timeout && timeout > 0) {
                        setTimeout(function(){ el.classList.add('fade-out'); setTimeout(()=>el.remove(), 260); }, timeout);
                    }
                    return el;
                } catch (e) {
                    console.error('showAlert failed', e);
                }
            };
        })();
    </script>
</body>
</html>