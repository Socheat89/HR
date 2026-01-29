<?php
session_start();
include 'admin/includes/db.php'; // Database connection
$conn = include 'admin/includes/db.php';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: logintest.php");
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $error = '';

    try {
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Successful login
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header("Location: home.php"); // Redirect to home.php
            exit();
        } else {
            $error = 'ឈ្មោះអ្នកប្រើ ឬ ពាក្យសម្ងាត់មិនត្រឹមត្រូវទេ!';
        }
    } catch (PDOException $e) {
        $error = 'កំហុសមូលដ្ឋានទិន្នន័យ: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2x/Logo-Van-Van-1.png">
    <title>ចូលប្រព័ន្ធ - HR Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" integrity="sha512-5Hs3dF2AEPkpNAR7UiOHba+lRSJNeM2ECkwxUIxC1Q/FLycGTbNapWXB4tP889k5T5Ju8fs4b1P5z/iB4nMfSQ==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;700&display=swap" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: url('https://png.pngtree.com/background/20230401/original/pngtree-khmer-new-year-frame-vector-picture-image_2253486.jpg') no-repeat center center/cover;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Noto Sans Khmer', sans-serif;
            overflow: hidden;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            position: relative;
            animation: slideUp 0.8s ease-in-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header img {
            width: 120px;
            margin-bottom: 1rem;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .login-header h2 {
            color: #d4af37;
            font-weight: 700;
            font-size: 1.8rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .form-control {
            border: 2px solid #d4af37;
            border-radius: 10px;
            padding: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }
        .form-control:focus {
            border-color: #ffd700;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
            background: #fff;
            outline: none;
        }
        .form-label {
            color: #333;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .btn-login {
            background: linear-gradient(135deg, #ffd700, #d4af37);
            border: none;
            padding: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e1e2f;
            border-radius: 25px;
            width: 100%;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #d4af37, #ffd700);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.5);
        }
        .error-message {
            background: rgba(255, 0, 0, 0.1);
            color: #dc3545;
            padding: 10px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            border: 1px solid #dc3545;
        }
        .flower {
            position: absolute;
            width: 50px;
            height: 50px;
            background-image: url('https://i.ibb.co/mrtxdVKp/Khmer-flowe.png');
            background-size: cover;
            animation: fall 10s linear infinite;
            z-index: -1;
        }
        @keyframes fall {
            0% { transform: translateY(-100vh) rotate(0deg); opacity: 1; }
            100% { transform: translateY(100vh) rotate(360deg); opacity: 0; }
        }
        @media (max-width: 576px) {
            .login-container {
                padding: 1.5rem;
                max-width: 90%;
            }
            .login-header img {
                width: 100px;
            }
            .login-header h2 {
                font-size: 1.5rem;
            }
        }
        .install-prompt {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: linear-gradient(135deg, #ffd700, #d4af37);
            color: #1e1e2f;
            padding: 15px;
            border-radius: 10px;
            display: none;
            z-index: 1000;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        .install-prompt button {
            background: #fff;
            color: #d4af37;
            border: none;
            padding: 5px 15px;
            margin-left: 10px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .install-prompt button:hover {
            background: #d4af37;
            color: #fff;
        }
    </style>
</head>
<body>
    <!-- Falling Flowers Animation -->
    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;">
        <div class="flower" style="left: 10%; animation-duration: 8s;"></div>
        <div class="flower" style="left: 30%; animation-duration: 10s;"></div>
        <div class="flower" style="left: 50%; animation-duration: 9s;"></div>
        <div class="flower" style="left: 70%; animation-duration: 11s;"></div>
        <div class="flower" style="left: 90%; animation-duration: 7s;"></div>
    </div>

    <!-- Login Container -->
    <div class="login-container">
        <div class="login-header">
            <img src="https://i.ibb.co/HTksMQd/Logo-Van-Van-2.png" alt="Logo">
            <h2>ចូលប្រព័ន្ធ</h2>
        </div>
        <?php if (isset($error) && $error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="logintest.php">
            <div class="mb-3">
                <label for="username" class="form-label">ឈ្មោះអ្នកប្រើ</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="បញ្ចូលឈ្មោះអ្នកប្រើ" required>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">ពាក្យសម្ងាត់</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="បញ្ចូលពាក្យសម្ងាត់" required>
            </div>
            <button type="submit" class="btn btn-login">ចូល</button>
        </form>
    </div>

    <!-- Install Prompt -->
    <div id="installPrompt" class="install-prompt">
        <span>បន្ថែម HR App ទៅអេក្រង់ដើមរបស់អ្នកសម្រាប់ការចូលប្រើរហ័ស!</span>
        <button onclick="installApp()">ដំឡើង</button>
        <button onclick="dismissPrompt()">មិនមែនឥឡូវនេះទេ</button>
    </div>

    <!-- JavaScript -->
    <script>
        // Register Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/service-worker.js')
                    .then(registration => {
                        console.log('Service Worker registered with scope:', registration.scope);
                    })
                    .catch(error => {
                        console.error('Service Worker registration failed:', error);
                    });
            });
        }

        let deferredPrompt;
        const installPrompt = document.getElementById('installPrompt');

        // Handle beforeinstallprompt event
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;

            // Show prompt only if not already installed
            if (!window.matchMedia('(display-mode: standalone)').matches) {
                const lastDismissed = localStorage.getItem('installPromptDismissed');
                if (!lastDismissed || (Date.now() - lastDismissed) > 24 * 60 * 60 * 1000) {
                    setTimeout(() => {
                        installPrompt.style.display = 'flex';
                        installPrompt.style.alignItems = 'center';
                        installPrompt.style.justifyContent = 'space-between';
                    }, 2000);
                }
            }
        });

        // Install the app
        function installApp() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the install prompt');
                    } else {
                        console.log('User dismissed the install prompt');
                    }
                    deferredPrompt = null;
                    installPrompt.style.display = 'none';
                });
            }
        }

        // Dismiss the prompt
        function dismissPrompt() {
            installPrompt.style.display = 'none';
            localStorage.setItem('installPromptDismissed', Date.now());
        }

        // Hide prompt if already installed
        window.addEventListener('appinstalled', () => {
            console.log('HR App was installed');
            installPrompt.style.display = 'none';
        });
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>