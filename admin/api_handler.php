<?php
// Start session (ensure it's started at the very top)
session_start();

// Set JSON content type and UTF-8 encoding
header('Content-Type: application/json; charset=UTF-8');

// --- Includes (Ensure these paths are correct) ---
include 'includes/auth.php';
if (!isLoggedIn()) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'សូមចូលគណនីសិន!']);
    exit();
}

// Corrected database inclusion: Include once and assign the returned connection.
$conn = include 'includes/db.php';

// Check if the database connection was successful
if ($conn === false || !$conn instanceof PDO) {
    http_response_code(500); // Internal Server Error
    error_log("Failed to get a valid PDO connection from 'includes/db.php'.");
    echo json_encode(['status' => 'error', 'message' => 'មានកំហុសក្នុងការតភ្ជាប់មូលដ្ឋានទិន្នន័យ សូមទាក់ទងអ្នកគ្រប់គ្រងប្រព័ន្ធ']);
    exit();
}

// Set time zone
date_default_timezone_set('Asia/Phnom_Penh');

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

// Determine the action from GET or POST request
$action = $_REQUEST['action'] ?? '';

// Debug: Log all request data
error_log("API Request - Action: '" . $action . "', Method: " . $_SERVER['REQUEST_METHOD']);
error_log("API Request - GET params: " . json_encode($_GET));
error_log("API Request - POST params: " . json_encode($_POST));

// Validate action
if (empty($action)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'សកម្មភាពមិនត្រឹមត្រូវ: គ្មានសកម្មភាពត្រូវបានបញ្ជាក់']);
    exit();
}

try {
    switch ($action) {
        case 'get_employees':
            // Alias the DB column to 'annual_leave_days' for front-end consistency
            $stmt = $conn->query("SELECT id, username, full_name, email, role, position, department, gender, image_url, jd_pdf, workflow_pdf, base_salary, bank_name, bank_account_number, bank_qr_code_url, nssf_id, manager_id, annual_leave_balance AS annual_leave_days FROM users WHERE status = 'active' AND LOWER(full_name) NOT IN ('admin','adminbt') AND LOWER(username) NOT IN ('admin','adminbt') ORDER BY full_name");
            $employees_flat = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $employee_tree_data = $employees_flat; // Create a copy for tree building
            $employee_tree = buildTree($employee_tree_data);

            echo json_encode(['status' => 'success', 'employees' => $employee_tree, 'employees_flat' => $employees_flat]);
            break;

        case 'add_user':
            if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'administration'])) {
                throw new Exception('អ្នកមិនមានសិទ្ធិធ្វើសកម្មភាពនេះទេ។');
            }
            
            $profileDir = 'uploads/profiles/';
            $jdDir = 'uploads/jd_pdfs/';
            $workflowDir = 'uploads/workflow_pdfs/';
            $bankQrDir = 'uploads/bank_qrs/';
            if (!is_dir($profileDir)) mkdir($profileDir, 0777, true);
            if (!is_dir($jdDir)) mkdir($jdDir, 0777, true);
            if (!is_dir($workflowDir)) mkdir($workflowDir, 0777, true);
            if (!is_dir($bankQrDir)) mkdir($bankQrDir, 0777, true);

            $username = trim($_POST['username']);
            $password = $_POST['password'];
            $email = trim($_POST['email']);
            $role = $_POST['role'];
            $fullName = trim($_POST['full_name']);
            $position = trim(filter_input(INPUT_POST, 'position', FILTER_SANITIZE_STRING));
            $department = trim(filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING));
            $gender = trim(filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING));
            $manager_id = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
            $profileImage = $_FILES['profile_image'];
            $jdPdf = $_FILES['jd_pdf'];
            $workflowPdf = $_FILES['workflow_pdf'];
            $base_salary = filter_input(INPUT_POST, 'base_salary', FILTER_VALIDATE_FLOAT);
            // Annual leave (AL) - allow decimals like 0.5
            $annual_leave_days = filter_input(INPUT_POST, 'annual_leave_days', FILTER_VALIDATE_FLOAT);
            if ($annual_leave_days === null || $annual_leave_days === false) {
                $annual_leave_days = 0.0;
            }
            $bank_name = trim(filter_input(INPUT_POST, 'bank_name', FILTER_SANITIZE_STRING));
            $bank_account_number = trim(filter_input(INPUT_POST, 'bank_account_number', FILTER_SANITIZE_STRING));
            $nssf_id = trim(filter_input(INPUT_POST, 'nssf_id', FILTER_SANITIZE_STRING));

            if (empty($username) || empty($password) || empty($email) || empty($fullName)) {
                throw new Exception("សូមបំពេញគ្រប់ប្រអប់ដែលមានសញ្ញា *");
            }
            if (!isset($profileImage) || $profileImage['error'] !== UPLOAD_ERR_OK) {
                 throw new Exception("តម្រូវឲ្យ Upload រូបភាព Profile។");
            }

            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
            $checkStmt->execute([':username' => $username, ':email' => $email]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception("ឈ្មោះគណនី (Username) ឬអ៊ីមែលនេះមានរួចហើយ។");
            }
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($profileImage['type'], $allowedTypes) || $profileImage['size'] > 5000000) {
                throw new Exception("រូបភាព Profile ត្រូវតែជាប្រភេទ JPG, PNG, GIF ហើយមានទំហំតូចជាង 5MB។");
            }
            $profileFileName = uniqid('profile_', true) . '.' . pathinfo($profileImage['name'], PATHINFO_EXTENSION);
            $profileTargetPath = $profileDir . $profileFileName;
            if (!move_uploaded_file($profileImage['tmp_name'], $profileTargetPath)) {
                throw new Exception("ការ Upload រូបភាព Profile បានបរាជ័យ។");
            }

            $jdPdfUrl = null;
            if (isset($jdPdf) && $jdPdf['error'] === UPLOAD_ERR_OK) {
                if ($jdPdf['type'] !== 'application/pdf' || $jdPdf['size'] > 5000000) {
                    throw new Exception("ឯកសារ JD ត្រូវតែជា PDF ហើយមានទំហំតូចជាង 5MB។");
                }
                $jdFileName = uniqid('jd_', true) . '.pdf';
                $jdTargetPath = $jdDir . $jdFileName;
                if (move_uploaded_file($jdPdf['tmp_name'], $jdTargetPath)) {
                    $jdPdfUrl = $jdTargetPath;
                }
            }

            $workflowPdfUrl = null;
            if (isset($workflowPdf) && $workflowPdf['error'] === UPLOAD_ERR_OK) {
                if ($workflowPdf['type'] !== 'application/pdf' || $workflowPdf['size'] > 5000000) {
                    throw new Exception("ឯកសារ Workflow ត្រូវតែជា PDF ហើយមានទំហំតូចជាង 5MB។");
                }
                $workflowFileName = uniqid('workflow_', true) . '.pdf';
                $workflowTargetPath = $workflowDir . $workflowFileName;
                if (move_uploaded_file($workflowPdf['tmp_name'], $workflowTargetPath)) {
                    $workflowPdfUrl = $workflowTargetPath;
                }
            }

            $bankQrCodeUrl = null;
            if (isset($_FILES['bank_qr_code']) && $_FILES['bank_qr_code']['error'] === UPLOAD_ERR_OK) {
                $bankQrFile = $_FILES['bank_qr_code'];
                if (!in_array($bankQrFile['type'], $allowedTypes) || $bankQrFile['size'] > 5000000) {
                    throw new Exception("Bank QR Code ត្រូវតែជាប្រភេទ JPG, PNG, GIF ហើយមានទំហំតូចជាង 5MB។");
                }
                $bankQrFileName = uniqid('bank_qr_', true) . '.' . pathinfo($bankQrFile['name'], PATHINFO_EXTENSION);
                $bankQrTargetPath = $bankQrDir . $bankQrFileName;
                if (move_uploaded_file($bankQrFile['tmp_name'], $bankQrTargetPath)) {
                    $bankQrCodeUrl = $bankQrTargetPath;
                }
            }
            
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $conn->prepare(
                "INSERT INTO users (username, password, email, role, position, department, gender, full_name, image_url, jd_pdf, workflow_pdf, base_salary, bank_name, bank_account_number, bank_qr_code_url, nssf_id, manager_id, annual_leave_balance) 
                 VALUES (:username, :password, :email, :role, :position, :department, :gender, :full_name, :image_url, :jd_pdf, :workflow_pdf, :base_salary, :bank_name, :bank_account_number, :bank_qr_code_url, :nssf_id, :manager_id, :annual_leave_days)"
            );

            $stmt->execute([
                ':username' => $username,
                ':password' => $hashedPassword,
                ':email' => $email,
                ':role' => $role,
                ':position' => $position,
                ':department' => $department,
                ':gender' => $gender,
                ':full_name' => $fullName,
                ':image_url' => $profileTargetPath,
                ':jd_pdf' => $jdPdfUrl,
                ':workflow_pdf' => $workflowPdfUrl,
                ':base_salary' => $base_salary,
                ':bank_name' => $bank_name,
                ':bank_account_number' => $bank_account_number,
                ':bank_qr_code_url' => $bankQrCodeUrl, 
                ':nssf_id' => $nssf_id,
                ':manager_id' => $manager_id,
                ':annual_leave_days' => $annual_leave_days
            ]);

            echo json_encode(['status' => 'success', 'message' => 'អ្នកប្រើប្រាស់ថ្មីត្រូវបានបន្ថែមដោយជោគជ័យ!']);
            break;

        case 'update_user':
            if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'administration','accounting'])) {
                throw new Exception('អ្នកមិនមានសិទ្ធិធ្វើសកម្មភាពនេះទេ។');
            }
            
            $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $fullName = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING));
            $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING));
            $password = $_POST['password'];
            $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
            $role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
            $position = trim(filter_input(INPUT_POST, 'position', FILTER_SANITIZE_STRING));
            $department = trim(filter_input(INPUT_POST, 'department', FILTER_SANITIZE_STRING));
            $gender = trim(filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING));
            $manager_id = !empty($_POST['manager_id']) ? (int)$_POST['manager_id'] : null;
            $existing_image_url = filter_input(INPUT_POST, 'existing_image_url', FILTER_SANITIZE_STRING);
            $existing_jd_url = filter_input(INPUT_POST, 'existing_jd_url', FILTER_SANITIZE_STRING);
            $existing_workflow_url = filter_input(INPUT_POST, 'existing_workflow_url', FILTER_SANITIZE_STRING);
            $existing_bank_qr_url = filter_input(INPUT_POST, 'existing_bank_qr_url', FILTER_SANITIZE_STRING); 
            // Normalize base_salary to accept values like "$1,200.00" or "1,200"
            $base_salary = filter_input(INPUT_POST, 'base_salary', FILTER_VALIDATE_FLOAT);
            // Annual leave (AL)
            $annual_leave_days = filter_input(INPUT_POST, 'annual_leave_days', FILTER_VALIDATE_FLOAT);
            if ($annual_leave_days === null || $annual_leave_days === false) {
                $annual_leave_days = 0.0;
            }
            $bank_name = trim(filter_input(INPUT_POST, 'bank_name', FILTER_SANITIZE_STRING));
            $bank_account_number = trim(filter_input(INPUT_POST, 'bank_account_number', FILTER_SANITIZE_STRING));
            $nssf_id = trim(filter_input(INPUT_POST, 'nssf_id', FILTER_SANITIZE_STRING));

            // Fetch existing values to gracefully handle partial updates
            $existingStmt = $conn->prepare("SELECT full_name, username, email, role, position, department, gender, image_url, jd_pdf, workflow_pdf, base_salary, bank_name, bank_account_number, bank_qr_code_url, nssf_id, manager_id, annual_leave_balance FROM users WHERE id = ?");
            $existingStmt->execute([$user_id]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                throw new Exception('មិនមានអ្នកប្រើប្រាស់នេះទេ។');
            }

            // Default missing core fields to existing values
            if (!$fullName) { $fullName = $existing['full_name']; }
            if (!$username) { $username = $existing['username']; }
            if (!$role) { $role = $existing['role']; }
            // Email: only validate if provided; otherwise keep existing (may be empty)
            if ($email === false) {
                // If an invalid email was submitted, keep existing to avoid blocking payroll-only edits
                $email = $existing['email'];
            } elseif ($email === null) {
                $email = $existing['email'];
            }
            // Normalize base_salary if validation failed but a raw value was sent
            if ($base_salary === null || $base_salary === false) {
                if (isset($_POST['base_salary'])) {
                    $rawBase = $_POST['base_salary'];
                    $cleanBase = preg_replace('/[^\d\.\-]/', '', (string)$rawBase);
                    $base_salary = is_numeric($cleanBase) ? (float)$cleanBase : (float)$existing['base_salary'];
                } else {
                    $base_salary = (float)$existing['base_salary'];
                }
            }

            $profileDir = 'uploads/profiles/';
            $jdDir = 'uploads/jd_pdfs/';
            $workflowDir = 'uploads/workflow_pdfs/';
            $bankQrDir = 'uploads/bank_qrs/'; 
            if (!is_dir($profileDir)) mkdir($profileDir, 0777, true);
            if (!is_dir($jdDir)) mkdir($jdDir, 0777, true);
            if (!is_dir($workflowDir)) mkdir($workflowDir, 0777, true);
            if (!is_dir($bankQrDir)) mkdir($bankQrDir, 0777, true); 

            $image_path_to_db = $existing_image_url;
            $jd_path_to_db = $existing_jd_url;
            $workflow_path_to_db = $existing_workflow_url;
            $bank_qr_path_to_db = $existing_bank_qr_url; 

            $allowed_img_types = ['image/jpeg', 'image/png', 'image/gif'];
            $allowed_pdf_type = 'application/pdf';
            
            if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['image_file'];
                if (in_array($file['type'], $allowed_img_types) && $file['size'] < 5000000) {
                    $new_filename = uniqid('profile_', true) . '_' . basename($file['name']);
                    $destination = $profileDir . $new_filename;
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        $image_path_to_db = $destination;
                        if (!empty($existing_image_url) && file_exists($existing_image_url) && strpos($existing_image_url, 'placeholder') === false) {
                            unlink($existing_image_url);
                        }
                    } else {
                        throw new Exception('ការ Upload រូបភាពបានបរាជ័យ។');
                    }
                } else {
                    throw new Exception('ប្រភេទ File មិនត្រឹមត្រូវ ឬ File មានទំហំធំពេក (JPG, PNG, GIF ហើយតូចជាង 5MB)។');
                }
            }

            if (isset($_FILES['jd_pdf']) && $_FILES['jd_pdf']['error'] === UPLOAD_ERR_OK) {
                $jdPdf = $_FILES['jd_pdf'];
                if ($jdPdf['type'] !== $allowed_pdf_type || $jdPdf['size'] > 5000000) {
                    throw new Exception("ឯកសារ JD ត្រូវតែជា PDF និងមានទំហំតូចជាង 5MB។");
                }
                $jdFileName = uniqid('jd_', true) . '.pdf';
                $jdTargetPath = $jdDir . $jdFileName;
                if (move_uploaded_file($jdPdf['tmp_name'], $jdTargetPath)) {
                    $jd_path_to_db = $jdTargetPath;
                    if (!empty($existing_jd_url) && file_exists($existing_jd_url)) {
                        unlink($existing_jd_url);
                    }
                } else {
                    throw new Exception("ការ Upload ឯកសារ JD បានបរាជ័យ។");
                }
            }

            if (isset($_FILES['workflow_pdf']) && $_FILES['workflow_pdf']['error'] === UPLOAD_ERR_OK) {
                $workflowPdf = $_FILES['workflow_pdf'];
                if ($workflowPdf['type'] !== $allowed_pdf_type || $workflowPdf['size'] > 5000000) {
                    throw new Exception("ឯកសារ Workflow ត្រូវតែជា PDF និងមានទំហំតូចជាង 5MB។");
                }
                $workflowFileName = uniqid('workflow_', true) . '.pdf';
                $workflowTargetPath = $workflowDir . $workflowFileName;
                if (move_uploaded_file($workflowPdf['tmp_name'], $workflowTargetPath)) {
                    $workflow_path_to_db = $workflowTargetPath;
                    if (!empty($existing_workflow_url) && file_exists($existing_workflow_url)) {
                        unlink($existing_workflow_url);
                    }
                } else {
                    throw new Exception("ការ Upload ឯកសារ Workflow បានបរាជ័យ។");
                }
            }
            
            if (isset($_FILES['bank_qr_code']) && $_FILES['bank_qr_code']['error'] === UPLOAD_ERR_OK) {
                $bankQrFile = $_FILES['bank_qr_code'];
                if (in_array($bankQrFile['type'], $allowed_img_types) && $bankQrFile['size'] < 5000000) {
                    $new_qr_filename = uniqid('bank_qr_', true) . '_' . basename($bankQrFile['name']);
                    $qr_destination = $bankQrDir . $new_qr_filename;
                    if (move_uploaded_file($bankQrFile['tmp_name'], $qr_destination)) {
                        $bank_qr_path_to_db = $qr_destination;
                        if (!empty($existing_bank_qr_url) && file_exists($existing_bank_qr_url)) {
                            unlink($existing_bank_qr_url);
                        }
                    } else {
                        throw new Exception('ការ Upload Bank QR Code បានបរាជ័យ។');
                    }
                } else {
                    throw new Exception('ប្រភេទ File មិនត្រឹមត្រូវ ឬ File មានទំហំធំពេកសម្រាប់ QR Code (JPG, PNG, GIF ហើយតូចជាង 5MB)។');
                }
            }

            // Do not block on email presence; allow payroll-only edits when email is empty/unchanged
            if (!$user_id || !$fullName || !$username || !$role) {
                 throw new Exception('ទិន្នន័យមិនត្រឹមត្រូវ សូមព្យាយាមម្តងទៀត។');
            }
            
            // Check uniqueness only if fields changed
            if ($username !== $existing['username']) {
                $checkUsername = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $checkUsername->execute([$username, $user_id]);
                if ($checkUsername->fetch()) {
                    throw new Exception("ឈ្មោះគណនី (Username) នេះមានអ្នកផ្សេងប្រើហើយ។");
                }
            }
            if (!empty($email) && $email !== $existing['email']) {
                $checkEmail = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $checkEmail->execute([$email, $user_id]);
                if ($checkEmail->fetch()) {
                    throw new Exception("Email នេះមានអ្នកផ្សេងប្រើហើយ។");
                }
            }

            $query = "UPDATE users SET 
                         full_name = ?, username = ?, email = ?, role = ?, position = ?, department = ?, gender = ?, image_url = ?, jd_pdf = ?, workflow_pdf = ?,
                         base_salary = ?, bank_name = ?, bank_account_number = ?, bank_qr_code_url = ?, nssf_id = ?, manager_id = ?, annual_leave_balance = ?";
            $params = [
                $fullName, $username, $email, $role, $position, $department, $gender, $image_path_to_db, $jd_path_to_db, $workflow_path_to_db,
                $base_salary, $bank_name, $bank_account_number, $bank_qr_path_to_db, $nssf_id, $manager_id, $annual_leave_days
            ];

            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
                $query .= ", password = ?";
                $params[] = $hashedPassword;
            }

            $query .= " WHERE id = ?";
            $params[] = $user_id;

            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            
            echo json_encode(['status' => 'success', 'message' => 'ព័ត៌មានអ្នកប្រើប្រាស់ត្រូវបានធ្វើបច្ចុប្បន្នភាពដោយជោគជ័យ!']);
            break;

        default:
            // Debug: Log when we hit the default case
            error_log("Invalid action received: '" . $action . "'");
            http_response_code(400); // Bad Request
            echo json_encode(['status' => 'error', 'message' => 'សកម្មភាពមិនត្រឹមត្រូវ: ' . $action]);
            break;
    }
} catch (Exception $e) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => "មានបញ្ហា៖ " . $e->getMessage()]);
}

exit();