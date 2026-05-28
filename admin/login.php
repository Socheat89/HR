<?php
// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Prevent session fixation
if (!isset($_SESSION['initialized'])) {
    session_regenerate_id(true);
    $_SESSION['initialized'] = true;
}

// Include database connection
$conn = require_once 'includes/db.php'; // Include and get PDO object
if (!$conn instanceof PDO) {
    die("Error: Database connection is not a PDO object. Check includes/db.php.");
}

// Handle login form submission
$error = '';
$debug = ''; // For debugging output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['username']) || empty($_POST['password'])) {
        $error = 'សូមបញ្ចូលឈ្មោះអ្នកប្រើ និងពាក្យសម្ងាត់!';
    } else {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        // Debugging: Log the input
        $debug .= "Input Username: '$username'\n";
        $debug .= "Input Password: '$password'\n";

        try {
            // Prepare and execute query
            $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Debugging: Check if user was found
            if ($user) {
                $debug .= "User found in database: " . print_r($user, true) . "\n";
                $debug .= "Stored Password Hash: " . $user['password'] . "\n";
                $debug .= "Password Verify Result: " . (password_verify($password, $user['password']) ? 'Match' : 'No Match') . "\n";
            } else {
                $debug .= "No user found with username: '$username'\n";
            }

            if ($user && password_verify($password, $user['password'])) {
                // Successful login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                session_regenerate_id(true);
                header("Location: dashboard.php");
                exit();
            } else {
                $error = 'ឈ្មោះអ្នកប្រើ ឬ ពាក្យសម្ងាត់មិនត្រឹមត្រូវទេ!';
            }
        } catch (PDOException $e) {
            $error = 'កំហុសមូលដ្ឋានទិន្នន័យ: ' . htmlspecialchars($e->getMessage());
            $debug .= "Database Error: " . $e->getMessage() . "\n";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ចូលប្រព័ន្ធ - HR Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Khmer&display=swap" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #050049 0%, #1e1e2f 100%);
            font-family: 'Khmer', sans-serif;
            color: #fff;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.1);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 400px;
            animation: fadeIn 1s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-container h2 {
            color: #ffd700;
            text-align: center;
            margin-bottom: 1.5rem;
            font-weight: bold;
            text-shadow: 0 0 5px rgba(255, 215, 0, 0.5);
        }
        .form-control {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid #ffd700;
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.3);
            border-color: #ffd700;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
            color: #fff;
        }
        .form-control::placeholder {
            color: #ccc;
        }
        .btn-login {
            background: linear-gradient(145deg, #ffd700, #d4af37);
            color: #1e1e2f;
            font-weight: bold;
            border: none;
            padding: 10px;
            border-radius: 25px;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            background: linear-gradient(145deg, #d4af37, #ffd700);
            transform: scale(1.05);
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.7);
        }
        .error-message {
            color: #f8d7da;
            background: rgba(255, 0, 0, 0.2);
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 1rem;
        }
        .debug-message {
            color: #fff;
            background: rgba(0, 0, 0, 0.5);
            padding: 10px;
            border-radius: 5px;
            text-align: left;
            margin-bottom: 1rem;
            white-space: pre-wrap;
            font-size: 0.9rem;
        }
        .logo {
            display: block;
            margin: 0 auto 1rem;
            width: 150px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <img src="https://i.ibb.co/HTksMQd/Logo-Van-Van-2.png" alt="Logo" class="logo">
        <h2>ចូលប្រព័ន្ធ</h2>
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($debug)): ?>
            <div class="debug-message"><?php echo htmlspecialchars($debug); ?></div>
        <?php endif; ?>
        <form method="POST" action="../auth/login.php">
            <div class="mb-3">
                <label for="username" class="form-label">ឈ្មោះអ្នកប្រើ</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="បញ្ចូលឈ្មោះអ្នកប្រើ" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">ពាក្យសម្ងាត់</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="បញ្ចូលពាក្យសម្ងាត់" required>
            </div>
            <button type="submit" class="btn btn-login">ចូល</button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>