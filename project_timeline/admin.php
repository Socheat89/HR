<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Fetch users for dropdown
try {
    $user_stmt = $pdo->query("SELECT id, username FROM users");
    $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching users: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if all required fields are set
    if (isset($_POST['user_id'], $_POST['title'], $_POST['start_date'], $_POST['end_date'], $_POST['status'])) {
        $user_id = $_POST['user_id'];
        $title = $_POST['title'];
        $description = isset($_POST['description']) ? $_POST['description'] : ''; // Optional field
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $status = $_POST['status'];

        try {
            $stmt = $pdo->prepare("INSERT INTO projects (user_id, title, description, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $title, $description, $start_date, $end_date, $status]);
            $success_message = "Project created successfully!";
        } catch (PDOException $e) {
            $error_message = "Error creating project: " . $e->getMessage();
        }
    } else {
        $error_message = "Error: All required fields must be filled.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Project Timeline</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #3498db, #2c3e50);
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }
        .form-container:hover {
            transform: translateY(-5px);
        }
        h2 {
            text-align: center;
            font-size: 2em;
            color: #2980b9;
            margin-bottom: 25px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.1);
        }
        .form-group {
            position: relative;
            margin: 15px 0;
        }
        .form-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #3498db;
            font-size: 1.2em;
        }
        input, textarea, select {
            width: 100%;
            padding: 12px 12px 12px 40px;
            font-size: 1.1em;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: #f9f9f9;
            color: #333;
            box-sizing: border-box;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        input:focus, textarea:focus, select:focus {
            border-color: #3498db;
            box-shadow: 0 0 8px rgba(52, 152, 219, 0.3);
            outline: none;
        }
        textarea {
            height: 100px;
            resize: vertical;
        }
        input[type="submit"] {
            width: 100%;
            padding: 12px;
            font-size: 1.2em;
            border: none;
            border-radius: 8px;
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            color: #fff;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        input[type="submit"]:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
        }
        .message {
            text-align: center;
            margin: 15px 0;
            font-size: 1.1em;
        }
        .success {
            color: #27ae60;
        }
        .error {
            color: #e74c3c;
        }
        @media (max-width: 600px) {
            .form-container {
                padding: 20px;
            }
            h2 {
                font-size: 1.6em;
            }
            input, textarea, select {
                font-size: 1em;
                padding: 10px 10px 10px 35px;
            }
            input[type="submit"] {
                font-size: 1.1em;
            }
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>បង្កើតគម្រោងថ្មី</h2>
        <?php if (isset($success_message)): ?>
            <p class="message success"><?php echo $success_message; ?></p>
        <?php elseif (isset($error_message)): ?>
            <p class="message error"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="form-group">
                <i class="fas fa-user"></i>
                <select name="user_id" required>
                    <option value="">ជ្រើសរើសអ្នកប្រើប្រាស់</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <i class="fas fa-project-diagram"></i>
                <input type="text" name="title" placeholder="ចំណងជើងគម្រោង" required>
            </div>
            <div class="form-group">
                <i class="fas fa-align-left"></i>
                <textarea name="description" placeholder="ការពិពណ៌នា"></textarea>
            </div>
            <div class="form-group">
                <i class="fas fa-calendar-alt"></i>
                <input type="date" name="start_date" required>
            </div>
            <div class="form-group">
                <i class="fas fa-calendar-check"></i>
                <input type="date" name="end_date" required>
            </div>
            <div class="form-group">
                <i class="fas fa-tasks"></i>
                <select name="status" required>
                    <option value="">ជ្រើសរើសស្ថានភាព</option>
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <input type="submit" value="បង្កើតគម្រោង">
        </form>
    </div>
</body>
</html>