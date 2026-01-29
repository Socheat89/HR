<?php
// បញ្ចូលឯកសារចាំបាច់
include 'includes/auth.php';
include 'includes/db.php';

// បង្កើតការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ
$conn = include 'includes/db.php';

// ចាប់ផ្តើមអថេរសម្រាប់សារកំហុស
$error_message = '';

// ដោះស្រាយការបញ្ជូនទិន្នន័យពីទម្រង់
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $email = trim($_POST['email']);

    try {
        // ពិនិត្យភាពត្រឹមត្រូវនៃទិន្នន័យ
        if (empty($username) || empty($password) || empty($email)) {
            throw new Exception("សូមបំពេញគ្រប់ប្រអប់");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("ទម្រង់អ៊ីមែលមិនត្រឹមត្រូវ");
        }

        // ពិនិត្យមើលថាតើឈ្មោះអ្នកប្រើប្រាស់ ឬអ៊ីមែលមានរួចហើយឬនៅ
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username OR email = :email");
        $checkStmt->bindParam(':username', 'username');
        $checkStmt->bindParam(':email', $email);
        $checkStmt->execute();
        if ($checkStmt->fetchColumn() > 0) {
            throw new Exception("ឈ្មោះអ្នកប្រើប្រាស់ ឬអ៊ីមែលនេះមានរួចហើយ");
        }

        // បំប្លែងពាក្យសម្ងាត់
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // បញ្ចូលទិន្នន័យទៅក្នុងមូលដ្ឋានទិន្នន័យ
        $stmt = $conn->prepare("INSERT INTO users (username, password, email) VALUES (:username, :password, :email)");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        // បញ្ជូនបន្តទៅទំព័រចូល បន្ទាប់ពីការចុះឈ្មោះបានជោគជ័យ
        // យើងសន្មត់ថាទំព័រចូលរបស់អ្នកមានឈ្មោះថា 'index.php' ឬ 'login.php'
        header("Location: login.php?registered=true");
        exit();
    } catch (Exception $e) {
        // រក្សាទុកសារកំហុសដើម្បីបង្ហាញក្នុង HTML
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ចុះឈ្មោះ</title>
    <!-- ប្រើប្រាស់ stylesheet ដូចគ្នានឹងទំព័រចូលដែរ -->
    <!-- តំណទៅ Google Fonts សម្រាប់ពុម្ពអក្សរខ្មែរទំនើប -->
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* General Body Styling */
body {
    font-family: 'Kantumruy Pro', sans-serif;
    background: linear-gradient(135deg, #71b7e6, #9b59b6);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    margin: 0;
    color: #333;
}

/* Wrapper for Centering and Padding */
.login-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    padding: 20px;
}

/* Container Styling (Used for both Login and Register) */
.login-container {
    background-color: #ffffff;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 400px;
    text-align: center;
    transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
}

.login-container:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
}

.login-container h2 {
    margin-top: 0;
    margin-bottom: 25px;
    color: #333;
    font-weight: 600;
    font-size: 28px;
}

/* Input Group Styling */
.input-group {
    text-align: left;
    margin-bottom: 20px;
    position: relative;
}

.input-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #555;
    font-size: 15px;
}

.input-group input {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 16px;
    box-sizing: border-box; /* Ensures padding doesn't affect width */
    transition: border-color 0.3s, box-shadow 0.3s;
    font-family: 'Kantumruy Pro', sans-serif;
}

.input-group input:focus {
    outline: none;
    border-color: #8e44ad;
    box-shadow: 0 0 8px rgba(142, 68, 173, 0.2);
}

/* Button Styling */
button {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 8px;
    background: linear-gradient(135deg, #71b7e6, #9b59b6);
    color: #ffffff;
    font-size: 18px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s, transform 0.2s;
    margin-top: 10px;
    font-family: 'Kantumruy Pro', sans-serif;
}

button:hover {
    background: linear-gradient(-135deg, #71b7e6, #9b59b6);
    transform: translateY(-3px);
}

/* Link Styling (Used for both pages) */
.register-link {
    margin-top: 25px;
    font-size: 14px;
}

.register-link a {
    color: #8e44ad;
    text-decoration: none;
    font-weight: 600;
}

.register-link a:hover {
    text-decoration: underline;
}

/* Message Styling */
.success-message {
    color: #27ae60;
    background-color: #e9f7ef;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #a3d9b8;
    text-align: left;
}

.error-message {
    color: #c0392b;
    background-color: #fbeae5;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #eabfb9;
    text-align: left;
}
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <h2>ចុះឈ្មោះ</h2>

            <!-- បង្ហាញសារកំហុសប្រសិនបើការចុះឈ្មោះបរាជ័យ -->
            <?php if (!empty($error_message)): ?>
                <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>

            <!-- ទម្រង់ចុះឈ្មោះ -->
            <form method="POST" action="register.php">
                <div class="input-group">
                    <label for="username">ឈ្មោះ​អ្នកប្រើប្រាស់</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="input-group">
                    <label for="email">អ៊ីមែល</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="input-group">
                    <label for="password">ពាក្យសម្ងាត់</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">ចុះឈ្មោះ</button>
            </form>

            <!-- តំណទៅទំព័រចូល -->
            <p class="register-link">មានគណនីរួចហើយ? <a href="login.php">ចូលនៅទីនេះ</a></p>
        </div>
    </div>
</body>
</html>