<?php
// Include authentication and database connection files
include 'includes/auth.php';
include 'includes/db.php';

// Ensure session is started only if it's not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect unauthorized users
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Get the database connection
$conn = include 'includes/db.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get the user ID from the URL
if (!isset($_GET['id'])) {
    redirectToErrorPage("User ID is missing.");
}
$id = $_GET['id'];

// Fetch the user details
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        redirectToErrorPage("User not found.");
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    redirectToErrorPage("An error occurred while fetching the user.");
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        redirectToErrorPage("Invalid CSRF token.");
    }

    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = trim($_POST['role']);
    $password = trim($_POST['password']);

    try {
        // Validate inputs
        if (empty($username) || empty($email)) {
            throw new Exception("Username and email are required.");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        // Handle PDF upload
        $jd_pdf_path = $user['jd_pdf']; // Keep existing path if no new file is uploaded
        if (isset($_FILES['jd_pdf']) && $_FILES['jd_pdf']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['jd_pdf'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            // Validate file
            if ($file_ext !== 'pdf') {
                throw new Exception("Only PDF files are allowed.");
            }
            if ($file_size > 5 * 1024 * 1024) { // 5MB limit
                throw new Exception("File size must not exceed 5MB.");
            }

            // Define upload directory and file name
            $upload_dir = 'uploads/jd_pdfs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $new_file_name = uniqid() . '.' . $file_ext;
            $jd_pdf_path = $upload_dir . $new_file_name;

            // Move the uploaded file
            if (!move_uploaded_file($file_tmp, $jd_pdf_path)) {
                throw new Exception("Failed to upload the PDF file.");
            }
        }

        // Start a transaction to ensure atomicity
        $conn->beginTransaction();

        // Hash the password only if a new one is provided
        $hashed_password = null;
        if (!empty($password)) {
            if (strlen($password) < 8) {
                throw new Exception("Password must be at least 8 characters long.");
            }
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        }

        // Update the user details including JD PDF
        if ($hashed_password) {
            $stmt = $conn->prepare("UPDATE users SET username = :username, email = :email, role = :role, password = :password, jd_pdf = :jd_pdf WHERE id = :id");
            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'password' => $hashed_password,
                'jd_pdf' => $jd_pdf_path,
                'id' => $id
            ]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = :username, email = :email, role = :role, jd_pdf = :jd_pdf WHERE id = :id");
            $stmt->execute([
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'jd_pdf' => $jd_pdf_path,
                'id' => $id
            ]);
        }

        // Commit the transaction
        $conn->commit();
        $message = "User updated successfully!";
        $user['jd_pdf'] = $jd_pdf_path; // Update local user data for display
    } catch (Exception $e) {
        // Rollback the transaction in case of error
        $conn->rollBack();
        if ($e->getCode() == 23000) { // Duplicate entry error
            $message = "Error: Username or email already exists.";
        } else {
            $message = "Error: " . $e->getMessage();
            error_log("Error: " . $e->getMessage());
        }
    }
}

/**
 * Redirects to an error page with a message.
 *
 * @param string $message The error message to display.
 */
function redirectToErrorPage($message) {
    $_SESSION['error_message'] = $message;
    header("Location: error.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <!-- Include Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom styles for better UI */
        .file-upload-label {
            display: inline-block;
            padding: 8px 16px;
            background-color: #4f46e5;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .file-upload-label:hover {
            background-color: #4338ca;
        }
        .file-name {
            margin-top: 8px;
            color: #6b7280;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-lg max-w-md w-full">
        <h2 class="text-2xl font-bold text-center mb-6">Edit User</h2>

        <?php if ($message): ?>
            <p class="<?php echo strpos($message, 'successfully') !== false ? 'text-green-600' : 'text-red-600'; ?> text-center mb-4">
                <?php echo htmlspecialchars($message); ?>
            </p>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <!-- Username Field -->
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    value="<?php echo htmlspecialchars($user['username']); ?>" 
                    required 
                    placeholder="Username" 
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                >
            </div>

            <!-- Email Field -->
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="<?php echo htmlspecialchars($user['email']); ?>" 
                    required 
                    placeholder="Email" 
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                >
            </div>

            <!-- Role Field -->
            <div>
                <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                <select 
                    id="role" 
                    name="role" 
                    required 
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                >
                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="employee" <?php echo $user['role'] === 'employee' ? 'selected' : ''; ?>>Employee</option>
                </select>
            </div>

            <!-- Password Field -->
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">New Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="Leave blank to keep current password" 
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                >
            </div>

            <!-- JD PDF Upload Field -->
            <div>
                <label for="jd_pdf" class="block text-sm font-medium text-gray-700">Job Description (PDF)</label>
                <?php if (!empty($user['jd_pdf'])): ?>
                    <p class="text-sm text-gray-600">
                        Current JD: <a href="<?php echo htmlspecialchars($user['jd_pdf']); ?>" target="_blank" class="text-indigo-600 hover:underline">View PDF</a>
                    </p>
                <?php endif; ?>
                <input type="file" id="jd_pdf" name="jd_pdf" accept=".pdf" class="hidden">
                <label for="jd_pdf" class="file-upload-label">Upload New PDF</label>
                <p class="file-name text-sm" id="file-name">No file chosen</p>
            </div>

            <!-- Submit Button -->
            <button 
                type="submit" 
                class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
                Update User
            </button>
        </form>

        <!-- Back Button -->
        <a href="dashboard.php" class="block mt-4 text-center text-indigo-600 hover:text-indigo-800">
            Back to Dashboard
        </a>
    </div>

    <script>
        // Display the selected file name
        document.getElementById('jd_pdf').addEventListener('change', function() {
            const fileName = this.files.length > 0 ? this.files[0].name : 'No file chosen';
            document.getElementById('file-name').textContent = fileName;
        });
    </script>
</body>
</html>