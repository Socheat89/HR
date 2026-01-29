<?php
// Database configuration
$host = 'localhost';
$dbname = 'samann1_admin_panel';
$username = 'samann1_admin_panel'; // Change to your MySQL username
$password = 'admin_panel@2025'; // Change to your MySQL password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Create table if it doesn't exist
$pdo->exec("CREATE TABLE IF NOT EXISTS urls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_url TEXT NOT NULL,
    short_code VARCHAR(6) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Handle URL shortening
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['original_url'])) {
    $original_url = filter_var($_POST['original_url'], FILTER_SANITIZE_URL);
    // Validate URL, including Telegram links
    if (!filter_var($original_url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\/(t\.me|telegram\.me)\/[a-zA-Z0-9_]+$/i', $original_url)) {
        $error = "Invalid URL. Only valid URLs (including t.me links) are allowed.";
    } else {
        // Check if URL already exists
        $stmt = $pdo->prepare("SELECT short_code FROM urls WHERE original_url = ?");
        $stmt->execute([$original_url]);
        $existing = $stmt->fetch();

        if ($existing) {
            $short_url = "http://$_SERVER[HTTP_HOST]/$existing[short_code]";
            $result = "Shortened URL: <a href='$short_url' target='_blank'>$short_url</a>";
        } else {
            // Generate unique short code
            do {
                $short_code = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 6);
                $stmt = $pdo->prepare("SELECT short_code FROM urls WHERE short_code = ?");
                $stmt->execute([$short_code]);
            } while ($stmt->fetch());

            // Insert new URL
            $stmt = $pdo->prepare("INSERT INTO urls (original_url, short_code) VALUES (?, ?)");
            $stmt->execute([$original_url, $short_code]);

            $short_url = "http://$_SERVER[HTTP_HOST]/$short_code";
            $result = "Shortened URL: <a href='$short_url' target='_blank'>$short_url</a>";
        }
    }
    echo json_encode(['result' => $result ?? '', 'error' => $error ?? '']);
    exit;
}

// Handle redirection
if (isset($_GET['code'])) {
    $short_code = $_GET['code'];
    $stmt = $pdo->prepare("SELECT original_url FROM urls WHERE short_code = ?");
    $stmt->execute([$short_code]);
    $url = $stmt->fetch();

    if ($url) {
        header("Location: " . $url['original_url']);
        exit;
    } else {
        http_response_code(404);
        echo "URL not found";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>កម្មវិធីបង្កើត URL ខ្លី</title>
    <style>
        body {
            font-family: 'Khmer OS', Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f0f0f0;
        }
        .container {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        input {
            padding: 10px;
            width: 300px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-family: 'Khmer OS', Arial, sans-serif;
        }
        button {
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: 'Khmer OS', Arial, sans-serif;
        }
        button:hover {
            background-color: #218838;
        }
        #result, #error {
            margin-top: 20px;
            word-break: break-all;
            font-family: 'Khmer OS', Arial, sans-serif;
        }
        #error {
            color: red;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>បង្កើត URL ខ្លី</h1>
        <form id="urlForm">
            <input type="url" id="originalUrl" placeholder="បញ្ចូលតំណភ្ជាប់ (ឧ. https://t.me/sk_store_kh)" required>
            <button type="submit">បង្កើតខ្លី</button>
        </form>
        <div id="result"></div>
        <div id="error"></div>
    </div>

    <script>
        document.getElementById('urlForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const originalUrl = document.getElementById('originalUrl').value;
            const resultDiv = document.getElementById('result');
            const errorDiv = document.getElementById('error');

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `original_url=${encodeURIComponent(originalUrl)}`
                });
                const data = await response.json();
                resultDiv.innerHTML = data.result || '';
                errorDiv.innerHTML = data.error || '';
            } catch (error) {
                errorDiv.innerHTML = 'កំហុស៖ មិនអាចបង្កើត URL ខ្លីបានទេ';
            }
        });
    </script>
</body>
</html>