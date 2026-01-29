<?php
session_start();

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: payroll-manager.php");
    exit;
}

$servername = "localhost";
$username_db = "samann1_payroll-manager";
$password_db = "payroll-manager@2025";
$dbname = "samann1_payroll-manager";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("ការតភ្ជាប់បានបរាជ័យ: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$username = $password = "";
$login_err = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["username"]))) {
        $login_err = "សូមបញ្ចូលឈ្មោះអ្នកប្រើប្រាស់។";
    } else {
        $username = trim($_POST["username"]);
    }

    if (empty(trim($_POST["password"]))) {
        $login_err = "សូមបញ្ចូលពាក្យសម្ងាត់។";
    } else {
        $password = trim($_POST["password"]);
    }

    if (empty($login_err)) {
        $sql = "SELECT id, username, password, role FROM users WHERE username = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = $username;
            
            if ($stmt->execute()) {
                $stmt->store_result();
                
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id, $username, $hashed_password, $role);
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            session_start();
                            
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["role"] = $role;
                            
                            header("location: payroll-manager.php");
                        } else {
                            $login_err = "ឈ្មោះអ្នកប្រើប្រាស់ ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវទេ។";
                        }
                    }
                } else {
                    $login_err = "ឈ្មោះអ្នកប្រើប្រាស់ ឬពាក្យសម្ងាត់មិនត្រឹមត្រូវទេ។";
                }
            } else {
                echo "មានបញ្ហាអ្វីមួយកើតឡើង។ សូមព្យាយាមម្តងទៀត។";
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
    <title>ចូលប្រើប្រព័ន្ធ</title>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kantumruy Pro', sans-serif; background-color: #f4f7f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .wrapper { width: 360px; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #2c3e50; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; }
        input[type="text"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 5px; box-sizing: border-box; }
        .btn { width: 100%; padding: 10px; border: none; background-color: #3498db; color: white; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .btn:hover { background-color: #2980b9; }
        .alert-danger { color: #e74c3c; background-color: #fceeee; border: 1px solid #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center; }
        .register-link { text-align: center; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <h2>ចូលប្រើប្រព័ន្ធ</h2>
        <p>សូមបំពេញព័ត៌មានដើម្បីចូល។</p>
        <?php 
        if(!empty($login_err)){
            echo '<div class="alert-danger">' . $login_err . '</div>';
        }        
        ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label>ឈ្មោះអ្នកប្រើប្រាស់</label>
                <input type="text" name="username" value="<?php echo $username; ?>">
            </div>    
            <div class="form-group">
                <label>ពាក្យសម្ងាត់</label>
                <input type="password" name="password">
            </div>
            <div class="form-group">
                <input type="submit" class="btn" value="ចូលប្រើ">
            </div>
            <p class="register-link">មិនទាន់មានគណនី? <a href="register.php">ចុះឈ្មោះនៅទីនេះ</a></p>
        </form>
    </div>
</body>
</html>