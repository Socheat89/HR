<?php
include 'includes/auth.php'; // Include authentication logic
if (!isLoggedIn()) {
    header("Location: ../index.php");
    exit();
}
include 'includes/db.php'; // Include database connection
$conn = include 'includes/db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $email = trim($_POST['email']);
        $role = $_POST['role'];
        $profileImage = $_FILES['profile_image'];
        $jdImage = $_FILES['jd_image'];

        // Validate inputs
        if (empty($username) || empty($password) || empty($email)) {
            throw new Exception("All fields are required.");
        }

        // Check if username or email already exists
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
        $checkStmt->bindParam(':username', $username);
        $checkStmt->bindParam(':email', $email);
        $checkStmt->execute();
        if ($checkStmt->fetchColumn() > 0) {
            throw new Exception("Username or email already exists.");
        }

        // Handle profile image upload
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
            throw new Exception("Profile image upload failed.");
        }

        // Handle JD image upload
        if ($jdImage['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($jdImage['type'], $allowedTypes)) {
                throw new Exception("Invalid file type for JD image. Only JPEG, PNG, and GIF are allowed.");
            }
            $jdFileName = uniqid('jd_', true) . '.' . pathinfo($jdImage['name'], PATHINFO_EXTENSION);
            $jdTargetPath = 'uploads/jd/' . $jdFileName;
            if (!move_uploaded_file($jdImage['tmp_name'], $jdTargetPath)) {
                throw new Exception("Failed to upload JD image.");
            }
            $jdImageUrl = $jdTargetPath;
        } else {
            throw new Exception("JD image upload failed.");
        }

        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Insert into database
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, image_url, jd_part) 
                                VALUES (:username, :password, :email, :role, :image_url, :jd_part)");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':image_url', $profileImageUrl);
        $stmt->bindParam(':jd_part', $jdImageUrl);
        $stmt->execute();

        $success_message = "Employee added successfully!";
        header("Refresh: 2; url=dashboard.php"); // Redirect to dashboard after 2 seconds
        exit();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Global Styles */
        body {
            background-color: #f9f9f9;
            font-family: 'Poppins', sans-serif;
            color: #4a4a4a;
        }

        /* Form Container */
        .form-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        /* Header Styling */
        h2 {
            font-size: 2rem;
            color: #ff7f50; /* Coral color */
            text-align: center;
            margin-bottom: 20px;
            font-weight: bold;
        }

        /* Input Fields */
        .form-control {
            border-radius: 10px;
            border: 2px solid #ddd;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: #ff7f50;
            box-shadow: none;
        }

        /* Buttons */
        .btn-primary {
            background-color: #ff7f50;
            border: none;
            border-radius: 10px;
            transition: background-color 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #ff6347;
        }

        /* Error and Success Messages */
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2><i class="fas fa-user-plus"></i> Add Employee</h2>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php elseif (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
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
                <label for="jd_image" class="form-label">Job Description Image</label>
                <input type="file" name="jd_image" id="jd_image" class="form-control" accept="image/*" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-3">Add Employee</button>
        </form>
    </div>

    <!-- Bootstrap JS (Optional, for interactive components) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>