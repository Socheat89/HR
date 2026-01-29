<?php
// --- កំណត់ค่า DATABASE និងលេខកូដសម្ងាត់ ---
$servername = "localhost";
$username_db = "samann1_payroll-manager";
$password_db = "payroll-manager@2025";
$dbname = "samann1_payroll-manager";

// --- កំណត់លេខកូដសម្ងាត់សម្រាប់แต่ละសិទ្ធិ ---
// Manager ត្រូវប្រាប់លេខកូដនេះដល់អ្នកប្រើប្រាស់ថ្មី
$REGISTRATION_CODES = [
    "account" => "account",
    "admin" => "administration",
    "manager" => "manager"
    // យើងមិនដាក់លេខកូដសម្រាប់ Manager ទេ ដើម្បីសុវត្ថិភាព
];

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) { die("ការតភ្ជាប់បានបរាជ័យ: " . $conn->connect_error); }
$conn->set_charset("utf8mb4");

$username = $password = $confirm_password = $reg_code = "";
$username_err = $password_err = $confirm_password_err = $reg_code_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validate registration code
    if (empty(trim($_POST["registration_code"]))) {
        $reg_code_err = "សូមបញ្ចូលលេខកូដចុះឈ្មោះ។";
    } elseif (!array_key_exists(trim($_POST["registration_code"]), $REGISTRATION_CODES)) {
        $reg_code_err = "លេខកូដចុះឈ្មោះមិនត្រឹមត្រូវទេ។";
    } else {
        $reg_code = trim($_POST["registration_code"]);
    }

    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "សូមបញ្ចូលឈ្មោះអ្នកប្រើប្រាស់។";
    } else {
        $sql = "SELECT id FROM users WHERE username = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = trim($_POST["username"]);
            if ($stmt->execute()) {
                $stmt->store_result();
                if ($stmt->num_rows == 1) {
                    $username_err = "ឈ្មោះអ្នកប្រើប្រាស់នេះមានរួចហើយ។";
                } else {
                    $username = trim($_POST["username"]);
                }
            } else { echo "មានបញ្ហាអ្វីមួយកើតឡើង។"; }
            $stmt->close();
        }
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "សូមបញ្ចូលពាក្យសម្ងាត់។";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "ពាក្យសម្ងាត់ត្រូវមានយ៉ាងតិច 6 តួអក្សរ។";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "សូមបញ្ជាក់ពាក្យសម្ងាត់ម្តងទៀត។";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "ពាក្យសម្ងាត់មិនត្រូវគ្នាទេ។";
        }
    }
    
    // Check input errors before inserting in database
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err) && empty($reg_code_err)) {
        
        $role = $REGISTRATION_CODES[$reg_code]; // Get role from the valid code
        $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?)";
         
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sss", $param_username, $param_password, $param_role);
            
            $param_username = $username;
            $param_password = password_hash($password, PASSWORD_DEFAULT);
            $param_role = $role;
            
            if ($stmt->execute()) {
                // Registration successful, now log the user in
                session_start();
                
                $_SESSION["loggedin"] = true;
                $_SESSION["id"] = $conn->insert_id; // Get the ID of the new user
                $_SESSION["username"] = $username;
                $_SESSION["role"] = $role;
                
                // Redirect to main page
                header("location: payroll-manager.php");
                exit;
            } else {
                echo "មានបញ្ហាអ្វីមួយកើតឡើង។";
            }
            $stmt->close();
        }
    }
    
    $conn->close();
}
?>
 
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>ចុះឈ្មោះ</title>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kantumruy Pro', sans-serif; background-color: #f4f7f9; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px 0; }
        .wrapper { width: 400px; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #2c3e50; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 5px; box-sizing: border-box; }
        .btn { width: 100%; padding: 10px; border: none; background-color: #2ecc71; color: white; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .btn:hover { background-color: #27ae60; }
        small { color: red; font-size: 0.9em; }
        .login-link { text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2>បង្កើតគណនីថ្មី</h2>
        <p>សូមបំពេញដើម្បីបង្កើតគណនី។</p>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>ឈ្មោះអ្នកប្រើប្រាស់</label>
                <input type="text" name="username" value="<?php echo $username; ?>">
                <?php if(!empty($username_err)){ echo '<small>' . $username_err . '</small>'; } ?>
            </div>    
            <div class="form-group">
                <label>ពាក្យសម្ងាត់</label>
                <input type="password" name="password">
                 <?php if(!empty($password_err)){ echo '<small>' . $password_err . '</small>'; } ?>
            </div>
            <div class="form-group">
                <label>បញ្ជាក់ពាក្យសម្ងាត់</label>
                <input type="password" name="confirm_password">
                 <?php if(!empty($confirm_password_err)){ echo '<small>' . $confirm_password_err . '</small>'; } ?>
            </div>
             <div class="form-group">
                <label>លេខកូដចុះឈ្មោះ</label>
                <input type="text" name="registration_code">
                 <?php if(!empty($reg_code_err)){ echo '<small>' . $reg_code_err . '</small>'; } ?>
            </div>
            <div class="form-group">
                <input type="submit" class="btn" value="ចុះឈ្មោះ">
            </div>
            <p class="login-link">មានគណនីហើយមែនទេ? <a href="login.php">ចូលប្រើនៅទីនេះ</a></p>
        </form>
    </div>
</body>
</html>