<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'samann1_admin_panel';
$username = 'samann1_admin_panel';
$password = 'admin_panel@2025';

// Telegram bot configurations
const TELEGRAM_BOT_TOKEN = "7886992632:AAFAlFae5FReigReJqPH8-QsKXowReyUNV0";
const CHAT_ID = "-1002296068912";

// Check if user is logged in
$currentUser = isset($_SESSION['username']) ? $_SESSION['username'] : 'Unknown User';
if ($currentUser === 'Unknown User') {
    header("Location: login.php");
    exit;
}

// Connect to database with UTF-8 support
try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create survey_responses table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS survey_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(100) NOT NULL,
        response TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    $conn->exec($sql);
} catch(PDOException $e) {
    die("ការតភ្ជាប់បរាជ័យ: " . $e->getMessage());
}

// Fetch full name based on session username
try {
    $stmt = $conn->prepare("SELECT full_name FROM users WHERE username = :username LIMIT 1");
    $stmt->bindParam(':username', $currentUser);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $full_name = $user ? $user['full_name'] : 'អ្នកប្រើមិនស្គាល់';
} catch(PDOException $e) {
    $error = "កំហុសក្នុងការទាញឈ្មោះ: " . $e->getMessage();
}

// Function to send message to Telegram
function sendToTelegram($message) {
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = [
        "chat_id" => CHAT_ID,
        "text" => $message,
        "parse_mode" => "HTML"
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return $result;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && $full_name !== 'អ្នកប្រើមិនស្គាល់') {
    $response = filter_input(INPUT_POST, 'response', FILTER_SANITIZE_STRING);
    
    if ($response) {
        try {
            $stmt = $conn->prepare("INSERT INTO survey_responses (full_name, response) VALUES (:full_name, :response)");
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':response', $response);
            $stmt->execute();
            
            // Prepare Telegram message
            $telegram_message = "📊 ការស្រង់មតិថ្មី\n";
            $telegram_message .= "បញ្ចេញមតិដោយ: " . $full_name . "\n";
            $telegram_message .= "ចំណាប់អារម្មណ៍: " . $response . "\n";
            $telegram_message .= "ពេលវេលា: " . date('Y-m-d H:i:s');
            
            sendToTelegram($telegram_message);
            
            $success = "ការស្រង់មតិបានជោគជ័យ!";
        } catch(PDOException $e) {
            $error = "កំហុស: " . $e->getMessage();
        }
    } else {
        $error = "សូមបញ្ចូលមតិ!";
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <link rel="icon" type="image/x-icon" href="https://i.ibb.co/r2JWnd2x/Logo-Van-Van-1.png">
    <title>ការស្រង់មតិអំពី Wi-Fi នៅហាង៣១៨ និងការិយាល័យកណ្ដាល</title>
    <style>
        /* Mobile-first design optimized for phone screens */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Khmer', 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #4a90e2, #9013fe);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 10px;
            overflow-x: hidden;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            width: 100%;
            max-width: 400px; /* Optimized for phone width */
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(to right, #4a90e2, #9013fe);
            border-radius: 15px 15px 0 0;
        }

        h2 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 20px;
            font-size: 20px; /* Smaller for mobile */
            font-weight: 700;
            line-height: 1.2;
        }

        .logout {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
        }

        .logout a {
            color: #4a90e2;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            padding: 5px 8px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .logout a:hover {
            background: #9013fe;
            color: white;
        }

        .name-display {
            background: #f7f9ff;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #4a90e2;
            color: #34495e;
            font-size: 16px;
            line-height: 1.4;
            word-wrap: break-word;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
        }

        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #d6deff;
            border-radius: 8px;
            font-size: 14px;
            color: #333;
            background: #f9faff;
            resize: vertical;
            min-height: 120px; /* Adjusted for mobile */
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: border-color 0.3s ease;
        }

        textarea:focus {
            border-color: #4a90e2;
            box-shadow: 0 4px 10px rgba(74, 144, 226, 0.2);
            outline: none;
        }

        button {
            background: linear-gradient(135deg, #4a90e2, #9013fe);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            -webkit-tap-highlight-color: transparent; /* Remove tap highlight on mobile */
        }

        button:hover {
            transform: scale(1.02); /* Slight scale instead of lift for mobile */
            box-shadow: 0 5px 15px rgba(74, 144, 226, 0.4);
        }

        .message {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .success {
            background: #e8f8e8;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        /* Media query for larger screens (optional) */
        @media (min-width: 768px) {
            .container {
                max-width: 600px;
                padding: 30px;
            }

            h2 {
                font-size: 24px;
            }

            .logout a {
                font-size: 16px;
                padding: 5px 10px;
            }

            .name-display {
                font-size: 18px;
                padding: 20px;
            }

            label {
                font-size: 16px;
            }

            textarea {
                font-size: 15px;
                min-height: 150px;
            }

            button {
                padding: 14px;
                font-size: 18px;
            }

            .message {
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logout">
            <a href="logout.php">ចាកចេញ</a>
        </div>
        <h2>ការស្រង់មតិអំពីល្បឿន Internet នៅហាង POS  </h2>
        
        <?php if(isset($success)): ?>
            <div class="message success"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($full_name !== 'អ្នកប្រើមិនស្គាល់'): ?>
            <div class="name-display">
                សួស្តីលោក/កញ្ញា: <?php echo htmlspecialchars($full_name); ?>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="response">មតិរបស់អ្នក:</label>
                    <textarea id="response" name="response" required></textarea>
                </div>
                
                <button type="submit">បញ្ជូន</button>
            </form>
        <?php else: ?>
            <div class="message error">
                មិនអាចរកឃើញឈ្មោះអ្នកប្រើ! សូមចូលម្តងទៀត។
            </div>
        <?php endif; ?>
    </div>
</body>
</html>