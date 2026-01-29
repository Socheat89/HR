<?php
// ជួយបង្ហាញកំហុសលម្អិត
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// បញ្ជូនទៅទំព័រ Login ប្រសិនបើ Session OTP មិនមាន
// ការពារកុំឱ្យគេចូលមកទំព័រនេះដោយផ្ទាល់
if (!isset($_SESSION["otp_code"]) || !isset($_SESSION["otp_user_id"])) {
    header("location: login.php");
    exit;
}

// ពិនិត្យមើលថាតើ OTP ផុតកំណត់ឬនៅ (ឧទាហរណ៍ ៥ នាទី)
if (isset($_SESSION["otp_time"]) && (time() - $_SESSION["otp_time"] > 300)) { // 300 វិនាទី = 5 នាទី
    // លុប Session OTP ដែលហួសកំណត់ចោល
    unset($_SESSION["otp_code"], $_SESSION["otp_user_id"], $_SESSION["otp_username"], $_SESSION["otp_role"], $_SESSION["otp_time"]);
    
    // បង្កើតសារកំហុសហើយបញ្ជូនกลับទៅទំព័រ Login
    $_SESSION['otp_error'] = "កូដ OTP បានផុតកំណត់ហើយ។ សូមព្យាយាមចូលម្តងទៀត។";
    header("location: login.php");
    exit;
}

$otp_err = "";

// ដំណើរការនៅពេលអ្នកប្រើប្រាស់ចុច Submit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["otp"]))) {
        $otp_err = "សូមបញ្ចូលកូដ OTP។";
    } else {
        $user_otp = trim($_POST["otp"]);
        
        // ផ្ទៀងផ្ទាត់ OTP ដែលអ្នកប្រើប្រាស់បានបញ្ចូល
        if ($user_otp == $_SESSION["otp_code"]) {
            // OTP ត្រឹមត្រូវ! បញ្ចប់ដំណើរការ Login

            // កំណត់ Session ថា Login បានជោគជ័យสมบูรณ์
            $_SESSION["loggedin_final"] = true;
            $_SESSION["id"] = $_SESSION["otp_user_id"];
            $_SESSION["username"] = $_SESSION["otp_username"];
            $_SESSION["role"] = $_SESSION["otp_role"];
            
            // លុប Session OTP បណ្ដោះអាសន្នចោល
            unset($_SESSION["otp_code"], $_SESSION["otp_user_id"], $_SESSION["otp_username"], $_SESSION["otp_role"], $_SESSION["otp_time"]);
            
            // បញ្ជូនអ្នកប្រើប្រាស់ទៅកាន់ទំព័រ Dashboard
            header("location: payroll-manager.php");
            exit;
        } else {
            // OTP មិនត្រឹមត្រូវ
            $otp_err = "កូដ OTP មិនត្រឹមត្រូវទេ។ សូមព្យាយាមម្តងទៀត។";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>ផ្ទៀងផ្ទាត់កូដ OTP</title>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kantumruy Pro', sans-serif; background-color: #f4f7f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .wrapper { width: 360px; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #2c3e50; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        input[type="text"] { width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 5px; box-sizing: border-box; text-align: center; letter-spacing: 5px; font-size: 1.2em; }
        .btn { width: 100%; padding: 10px; border: none; background-color: #27ae60; color: white; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .btn:hover { background-color: #229954; }
        .alert-danger { color: #e74c3c; background-color: #fceeee; border: 1px solid #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; }
        .info-text { text-align: center; margin-bottom: 20px; color: #555; }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2>ផ្ទៀងផ្ទាត់ OTP</h2>
        <p class="info-text">កូដផ្ទៀងផ្ទាត់ត្រូវបានផ្ញើទៅកាន់គណនី Telegram របស់អ្នក។ សូមពិនិត្យមើលសារ។</p>
        
        <?php 
        if (!empty($otp_err)) {
            echo '<div class="alert-danger">' . $otp_err . '</div>';
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>កូដ OTP</label>
                <input type="text" name="otp" maxlength="6" pattern="\d{6}" title="សូមបញ្ចូលលេខ ៦ ខ្ទង់" required autofocus>
            </div>
            <div class="form-group">
                <input type="submit" class="btn" value="ផ្ទៀងផ្ទាត់">
            </div>
        </form>
    </div>
</body>
</html>