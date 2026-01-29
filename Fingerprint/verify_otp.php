<?php
session_start();

// Check if OTP verification is pending
if (!isset($_SESSION['otp']) || !isset($_SESSION['admin_id_pending'])) {
    header('Location: admin.php');
    exit;
}

$pdo = new PDO("mysql:host=localhost;dbname=samann1_fingerprint_db", "samann1_Fingerprint", "Fingerprint@2025");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (isset($_POST['verify_otp'])) {
    $enteredOtp = trim($_POST['otp'] ?? '');
    if (empty($enteredOtp)) {
        $otpError = 'សូមបញ្ចូល OTP!';
    } elseif ($enteredOtp === $_SESSION['otp'] && time() < $_SESSION['otp_expiry']) {
        // OTP is valid
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $_SESSION['admin_id_pending'];
        $_SESSION['admin_username'] = $_SESSION['admin_username_pending'];

        // Update last login
        $stmt = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['admin_id']]);

        // Clean up OTP session data
        unset($_SESSION['otp']);
        unset($_SESSION['otp_expiry']);
        unset($_SESSION['admin_id_pending']);
        unset($_SESSION['admin_username_pending']);

        header('Location: admin.php');
        exit;
    } else {
        $otpError = 'OTP មិនត្រឹមត្រូវ ឬផុតកំណត់!';
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ផ្ទៀងផ្ទាត់ OTP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Khmer&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body {
            font-family: 'Khmer', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        .card { margin-top: 50px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h2><i class="fas fa-shield-alt"></i> ផ្ទៀងផ្ទាត់ OTP</h2>
                        <p>សូមបញ្ចូលកូដ OTP ដែលបានផ្ញើទៅអ៊ីមែលរបស់អ្នក។</p>
                        <?php if (isset($otpError)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($otpError); ?></div>
                        <?php endif; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="otp" class="form-label">កូដ OTP</label>
                                <input type="text" class="form-control" id="otp" name="otp" required>
                            </div>
                            <button type="submit" name="verify_otp" class="btn btn-primary w-100"><i class="fas fa-check"></i> ផ្ទៀងផ្ទាត់</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>