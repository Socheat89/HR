<?php
include 'includes/auth.php';
if (!isLoggedIn()) {
    header("Location: index.php");
    exit();
}
include 'includes/db.php';
$conn = include 'includes/db.php';

// Handle form submission for adding a user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $fullName = trim($_POST['full_name']);
        $profileImage = $_FILES['profile_image'];
        $jdPdf = $_FILES['jd_pdf'];
        $workflowPdf = $_FILES['workflow_pdf'];

        // Validate required inputs
        if (empty($username) || empty($password) || empty($email) || empty($fullName)) {
            throw new Exception("Required fields (Username, Password, Email, Full Name) must be filled.");
        }

        // Check if username or email already exists
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
        $checkStmt->bindParam(':username', $username);
        $checkStmt->bindParam(':email', $email);
        $checkStmt->execute();
        if ($checkStmt->fetchColumn() > 0) {
            throw new Exception("Username or email already exists.");
        }

        // Handle profile image upload (required)
        if ($profileImage['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($profileImage['type'], $allowedTypes)) {
                throw new Exception("Invalid file type for profile image. Only JPEG, PNG, and GIF are allowed.");
            }
            $profileFileName = uniqid('profile_', true) . '.' . pathinfo($profileImage['name'], PATHINFO_EXTENSION);
            $profileTargetPath = 'uploads/profiles/' . $profileFileName;
            if (!move_uploaded_file($profileImage['tmp_name'], $profileTargetPath)) {
                throw new Exception("Failed to upload profile image.");
            }
            $profileImageUrl = $profileTargetPath;
        } else {
            throw new Exception("Profile image upload failed. Error code: " . $profileImage['error']);
        }

        // Handle JD PDF upload (optional)
        $jdPdfUrl = null; // Default to null if not uploaded
        if ($jdPdf['error'] === UPLOAD_ERR_OK) {
            $allowedType = 'application/pdf';
            if ($jdPdf['type'] !== $allowedType) {
                throw new Exception("Invalid file type for JD. Only PDF files are allowed.");
            }
            if ($jdPdf['size'] > 5 * 1024 * 1024) { // 5MB limit
                throw new Exception("File size for JD must not exceed 5MB.");
            }
            $jdFileName = uniqid('jd_', true) . '.pdf';
            $jdTargetPath = 'uploads/jd_pdfs/' . $jdFileName;
            if (!move_uploaded_file($jdPdf['tmp_name'], $jdTargetPath)) {
                throw new Exception("Failed to upload JD PDF.");
            }
            $jdPdfUrl = $jdTargetPath;
        } elseif ($jdPdf['error'] !== UPLOAD_ERR_NO_FILE) {
            throw new Exception("JD PDF upload failed. Error code: " . $jdPdf['error']);
        }

        // Handle Workflow PDF upload (optional)
        $workflowPdfUrl = null; // Default to null if not uploaded
        if ($workflowPdf['error'] === UPLOAD_ERR_OK) {
            $allowedType = 'application/pdf';
            if ($workflowPdf['type'] !== $allowedType) {
                throw new Exception("Invalid file type for Workflow. Only PDF files are allowed.");
            }
            if ($workflowPdf['size'] > 5 * 1024 * 1024) { // 5MB limit
                throw new Exception("File size for Workflow must not exceed 5MB.");
            }
            $workflowDir = 'uploads/workflow_pdfs/';
            if (!is_dir($workflowDir)) {
                mkdir($workflowDir, 0777, true);
            }
            $workflowFileName = uniqid('workflow_', true) . '.pdf';
            $workflowTargetPath = $workflowDir . $workflowFileName;
            if (!move_uploaded_file($workflowPdf['tmp_name'], $workflowTargetPath)) {
                throw new Exception("Failed to upload Workflow PDF.");
            }
            $workflowPdfUrl = $workflowTargetPath;
        } elseif ($workflowPdf['error'] !== UPLOAD_ERR_NO_FILE) {
            throw new Exception("Workflow PDF upload failed. Error code: " . $workflowPdf['error']);
        }

        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Insert into database
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, full_name, image_url, jd_pdf, workflow_pdf) 
                                VALUES (:username, :password, :email, :role, :full_name, :image_url, :jd_pdf, :workflow_pdf)");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':full_name', $fullName);
        $stmt->bindParam(':image_url', $profileImageUrl);
        $stmt->bindParam(':jd_pdf', $jdPdfUrl, PDO::PARAM_STR | PDO::PARAM_NULL); // Allow null
        $stmt->bindParam(':workflow_pdf', $workflowPdfUrl, PDO::PARAM_STR | PDO::PARAM_NULL); // Allow null
        $stmt->execute();

        $success_message = "User added successfully!";
        header("Refresh: 2; url=dashboard.php");
        exit();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log("Upload error: " . $e->getMessage());
    }
}

// Helper function to interpret PHP upload error codes
function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return "The uploaded file exceeds the upload_max_filesize directive in php.ini.";
        case UPLOAD_ERR_FORM_SIZE:
            return "The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form.";
        case UPLOAD_ERR_PARTIAL:
            return "The uploaded file was only partially uploaded.";
        case UPLOAD_ERR_NO_FILE:
            return "No file was uploaded.";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing a temporary folder.";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk.";
        case UPLOAD_ERR_EXTENSION:
            return "A PHP extension stopped the file upload.";
        default:
            return "Unknown upload error.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f9f9f9;
            font-family: 'Poppins', sans-serif;
            color: #4a4a4a;
        }
        .form-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        h2 {
            font-size: 2rem;
            color: #ff7f50;
            text-align: center;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #ddd;
            transition: border-color 0.3s ease;
        }
        .form-control:focus {
            border-color: #ff7f50;
            box-shadow: none;
        }
        .btn-primary {
            background-color: #ff7f50;
            border: none;
            border-radius: 10px;
            transition: background-color 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #ff6347;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2><i class="fas fa-user-plus"></i> Add a New User</h2>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php elseif (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="full_name" class="form-label">Full Name</label>
                <input type="text" name="full_name" id="full_name" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" id="username" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" id="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select name="role" id="role" class="form-select" required>
                    <option value="admin">Admin</option>
                    <option value="employee">Employee</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="profile_image" class="form-label">Profile Image</label>
                <input type="file" name="profile_image" id="profile_image" class="form-control" accept="image/*" required>
            </div>

            <div class="mb-3">
                <label for="jd_pdf" class="form-label">JD PDF (Optional)</label>
                <input type="file" name="jd_pdf" id="jd_pdf" class="form-control" accept=".pdf">
            </div>

            <div class="mb-3">
                <label for="workflow_pdf" class="form-label">Workflow PDF (Optional)</label>
                <input type="file" name="workflow_pdf" id="workflow_pdf" class="form-control" accept=".pdf">
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-3">Add User</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>