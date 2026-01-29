<?php
// ចាប់ផ្តើម session ដើម្បី​ប្រើ​សម្រាប់​ការ​បង្ហាញ​សារ
session_start();

// កំណត់​អថេរ​សម្រាប់​បង្ហាញ​សារ
$success_message = '';
$error_message = '';

// ពិនិត្យ​មើល​ថា​តើ​ទម្រង់​ត្រូវ​បាន​បញ្ជូន​មក​ឬ​នៅ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // រួមបញ្ចូល​ឯកសារ​សម្រាប់​ភ្ជាប់​ទៅ​កាន់ Database
    // សូម​ប្រាកដ​ថា​ទីតាំង​នេះ​ត្រឹមត្រូវ
    require_once 'includes/db.php';
    $conn = include 'includes/db.php';

    // យក​ទិន្នន័យ​ពី​ទម្រង់
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $email = trim($_POST['email']);
    $role = $_POST['role']; // 'admin' or 'super_admin'

    // --- ការត្រួតពិនិត្យ​ទិន្នន័យ​พื้นฐาน ---
    if (empty($full_name) || empty($username) || empty($password) || empty($email) || empty($role)) {
        $error_message = "សូម​បំពេញ​គ្រប់​ប្រអប់​ទាំងអស់។";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "ទម្រង់​អ៊ីមែល​មិន​ត្រឹមត្រូវ​ទេ។";
    } elseif (!in_array($role, ['admin', 'super_admin'])) {
        $error_message = "តួនាទី​ដែល​បាន​ជ្រើសរើស​មិន​ត្រឹមត្រូវ​ទេ។";
    } else {
        try {
            // --- ពិនិត្យ​មើល​ว่า username ឬ email មាន​រួច​ហើយ​ឬ​នៅ ---
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
            $stmt_check->execute([':username' => $username, ':email' => $email]);
            
            if ($stmt_check->fetch()) {
                $error_message = "ឈ្មោះគណនី (Username) ឬ​អ៊ីមែល​នេះ​មាន​ក្នុង​ប្រព័ន្ធ​រួច​ហើយ។";
            } else {
                // --- បើ​មិន​ទាន់​មាន ចាប់ផ្តើម​បញ្ចូល​ទិន្នន័យ ---

                // เข้ารหัส​ពាក្យសម្ងាត់​ដើម្បី​សុវត្ថិភាព
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                // រៀបចំ SQL query សម្រាប់​បញ្ចូល​ទិន្នន័យ
                $sql = "INSERT INTO users (full_name, username, password, email, role, status, created_at) 
                        VALUES (:full_name, :username, :password, :email, :role, 'active', NOW())";
                
                $stmt_insert = $conn->prepare($sql);

                // ភ្ជាប់​តម្លៃ​ទៅ​នឹង parameters
                $stmt_insert->bindParam(':full_name', $full_name);
                $stmt_insert->bindParam(':username', $username);
                $stmt_insert->bindParam(':password', $hashed_password);
                $stmt_insert->bindParam(':email', $email);
                $stmt_insert->bindParam(':role', $role);

                // ដំណើរការ​ query
                if ($stmt_insert->execute()) {
                    $success_message = "គណនី ". htmlspecialchars($role) ." ឈ្មោះ ". htmlspecialchars($full_name) ." ត្រូវ​បាន​បង្កើត​ដោយ​ជោគជ័យ!";
                } else {
                    $error_message = "មាន​បញ្ហា​ក្នុង​ការ​បង្កើត​គណនី។ សូម​ព្យាយាម​ម្តង​ទៀត។";
                }
            }
        } catch (PDOException $e) {
            // ចាប់​កំហុស​ពី Database
            $error_message = "Database Error: " . $e->getMessage();
            // សម្រាប់​การดีบัก: error_log($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ឧបករណ៍បង្កើតគណនី Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Khmer:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-bg: #161b22;
            --secondary-bg: #0d1117;
            --card-bg: #1c2128;
            --border-color: rgba(255, 255, 255, 0.1);
            --accent-color: #ffd700;
            --text-primary: #f0f6fc;
            --text-secondary: #c9d1d9;
            --success: #2ea043;
            --danger: #da3633;
        }
        body {
            font-family: 'Noto Sans Khmer', sans-serif;
            background-color: var(--primary-bg);
            color: var(--text-primary);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .container {
            width: 100%;
            max-width: 500px;
            background-color: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
        }
        h1 {
            color: var(--accent-color);
            text-align: center;
            margin-bottom: 1.5rem;
            font-weight: 700;
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-secondary);
        }
        .form-input, .form-select {
            width: 100%;
            padding: 10px 14px;
            background-color: var(--primary-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.3);
        }
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            background-color: var(--accent-color);
            color: var(--secondary-bg);
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn:hover {
            background-color: #ffea70;
        }
        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 500;
        }
        .message.success {
            background-color: rgba(46, 160, 67, 0.2);
            color: var(--success);
            border: 1px solid var(--success);
        }
        .message.error {
            background-color: rgba(218, 54, 51, 0.2);
            color: var(--danger);
            border: 1px solid var(--danger);
        }
        .security-warning {
            color: #ffc107;
            background-color: rgba(255, 193, 7, 0.1);
            border: 1px solid #ffc107;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 2rem;
            text-align: center;
        }
    </style>
</head>
<body>

    <div class="container">
        <h1><i class="fas fa-user-shield"></i> បង្កើតគណនី Admin</h1>

        <?php if ($success_message): ?>
            <div class="message success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="message error">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="create_admin.php">
            <div class="form-group">
                <label for="full_name" class="form-label">ឈ្មោះពេញ</label>
                <input type="text" id="full_name" name="full_name" class="form-input" required>
            </div>

            <div class="form-group">
                <label for="username" class="form-label">ឈ្មោះគណនី (Username)</label>
                <input type="text" id="username" name="username" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label for="email" class="form-label">អ៊ីមែល</label>
                <input type="email" id="email" name="email" class="form-input" required>
            </div>

            <div class="form-group">
                <label for="password" class="form-label">លេខសម្ងាត់</label>
                <input type="password" id="password" name="password" class="form-input" required>
            </div>

            <div class="form-group">
                <label for="role" class="form-label">ជ្រើសរើសតួនាទី</label>
                <select id="role" name="role" class="form-select" required>
                    <option value="" disabled selected>-- សូមជ្រើសរើស --</option>
                    <option value="admin">Admin ធម្មតា</option>
                    <option value="super_admin">Super Admin</option>
                </select>
            </div>

            <button type="submit" class="btn">បង្កើតគណនី</button>
        </form>

        <div class="security-warning">
            <strong><i class="fas fa-exclamation-triangle"></i> ការព្រមានអំពីសុវត្ថិភាព៖</strong><br>
            សូមលុប ឬប្តូរឈ្មោះឯកសារនេះ បន្ទាប់ពីប្រើប្រាស់រួចរាល់ ដើម្បីការពារប្រព័ន្ធរបស់អ្នក។
        </div>
    </div>
    
    <!-- បញ្ចូល Font Awesome Icon (ស្រេចចិត្ត) -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

</body>
</html>