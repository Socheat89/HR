<?php
session_start();
require_once 'log.php';

// Only admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = 'អ្នកគ្មានសិទ្ធិចូលទំព័រនេះទេ។';
    header("Location: login.php");
    exit();
}

// Database connection
try {
    $db = new PDO("mysql:host=localhost;dbname=samann1_admin_panel;charset=utf8mb4", "samann1_admin_panel", "admin_panel@2025");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $_SESSION['error'] = 'មានបញ្ហាក្នុងការតភ្ជាប់ទិន្នន័យ។ សូមព្យាយាមម្តងទៀត។';
    error_log("DB Connection Error: " . $e->getMessage());
    header("Location: error.php");
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $poll_id = filter_input(INPUT_POST, 'poll_id', FILTER_VALIDATE_INT);
    $winner_id = filter_input(INPUT_POST, 'winner_id', FILTER_VALIDATE_INT);
    $description = trim($_POST['description-text']);

    if ($poll_id && $winner_id && $description !== '') {
        try {
            // Check if description already exists
            $stmt_check = $db->prepare("SELECT id FROM certificate_positions WHERE poll_id = :poll_id AND winner_id = :winner_id AND element_id = 'description'");
            $stmt_check->execute(['poll_id' => $poll_id, 'winner_id' => $winner_id]);
            $existing = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Update
                $stmt = $db->prepare("UPDATE certificate_positions SET content = :content WHERE poll_id = :poll_id AND winner_id = :winner_id AND element_id = 'description'");
            } else {
                // Insert
                $stmt = $db->prepare("INSERT INTO certificate_positions (poll_id, winner_id, element_id, x, y, content) VALUES (:poll_id, :winner_id, 'description', 0, 0, :content)");
            }
            $stmt->execute(['poll_id' => $poll_id, 'winner_id' => $winner_id, 'content' => $description]);
            $message = 'បានរក្សាទុកការពិពណ៌នា ដោយជោគជ័យ។';
        } catch (PDOException $e) {
            $message = 'មានបញ្ហាក្នុងការរក្សាទុក។ សូមព្យាយាមម្តងទៀត។';
            error_log("Save Description Error: " . $e->getMessage());
        }
    } else {
        $message = 'សូមបំពេញទិន្នន័យទាំងអស់។';
    }
}

// Fetch polls for select
$polls = [];
try {
    $stmt_polls = $db->query("SELECT id, question FROM polls ORDER BY id DESC");
    $polls = $stmt_polls->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch Polls Error: " . $e->getMessage());
}

// Fetch users for select
$users = [];
try {
    $stmt_users = $db->query("SELECT id, COALESCE(full_name, username) AS name FROM users ORDER BY name");
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Fetch Users Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>បញ្ចូលការពិពណ៌នា</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #333;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
        }
        h1 {
            text-align: center;
            color: #4a5568;
            margin-bottom: 30px;
            font-weight: 600;
        }
        form {
            display: flex;
            flex-direction: column;
        }
        label {
            margin-top: 20px;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2d3748;
        }
        select, textarea {
            width: 100%;
            padding: 12px;
            margin-bottom: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        textarea {
            height: 150px;
            resize: vertical;
            font-family: inherit;
        }
        button {
            margin-top: 30px;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .message {
            color: #38a169;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        .error {
            color: #e53e3e;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>បញ្ចូលការពិពណ៌នា</h1>
        <?php if ($message): ?>
            <p class="message"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <form method="post">
            <label for="poll_id">ជ្រើសរើសការបោះឆ្នោត:</label>
            <select name="poll_id" id="poll_id" required>
                <option value="">-- ជ្រើសរើស --</option>
                <?php foreach ($polls as $poll): ?>
                    <option value="<?php echo $poll['id']; ?>"><?php echo htmlspecialchars($poll['question']); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="winner_id">ជ្រើសរើសអ្នកឈ្នះ:</label>
            <select name="winner_id" id="winner_id" required>
                <option value="">-- ជ្រើសរើស --</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="description-text">ការពិពណ៌នា:</label>
            <textarea name="description-text" id="description-text" placeholder="បញ្ចូលការពិពណ៌នា..." required></textarea>

            <button type="submit">រក្សាទុក</button>
        </form>
    </div>
</body>
</html>