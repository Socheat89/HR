<?php
// កំណត់អថេរសម្រាប់បង្ហាញសារទៅកាន់អ្នកប្រើប្រាស់
$statusMessage = '';

// ពិនិត្យមើលថាតើទិន្នន័យត្រូវបានផ្ញើមកតាមរយៈ POST request (ពេលអ្នកប្រើប្រាស់ចុចប៊ូតុង submit)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // ---------------- CONFIGURATION ----------------
    // ជំនួស "YOUR_BOT_TOKEN" ជាមួយ Bot Token របស់អ្នក
    $botToken = "8257816240:AAEFuHuFbIlYWK1ySSpnu30f2XMYrZy9OVc";

    // ជំនួស "YOUR_CHAT_ID" ជាមួយ Chat ID របស់អ្នក
    $chatId = "-1002722014866";
    // ---------------------------------------------

    // យកข้อความจากฟอร์มที่ส่งมา
    $messageText = isset($_POST['message']) ? $_POST['message'] : '';

    // ពិនិត្យមើលថាសារមិនទទេ
    if (!empty($messageText)) {
        
        // បង្កើត URL សម្រាប់ Telegram Bot API
        $website = "https://api.telegram.org/bot" . $botToken;
        // យើងប្រើ parse_mode=HTML ដើម្បីអាចប្រើប្រាស់ tags ខ្លះៗក្នុងសារបាន (ជាជម្រើស)
        $url = $website . "/sendMessage?chat_id=" . $chatId . "&text=" . urlencode($messageText) . "&parse_mode=HTML";

        // ប្រើ file_get_contents ដើម្បីផ្ញើសំណើ
        $response = @file_get_contents($url); // ប្រើ @ ដើម្បីបិទ error display

        if ($response === false) {
            $statusMessage = "<p style='color: red;'>មានបញ្ហាក្នុងការផ្ញើសារ! សូមពិនិត្យមើល Bot Token ឬ Chat ID របស់អ្នក។</p>";
        } else {
            $responseArray = json_decode($response, true);
            if ($responseArray['ok']) {
                $statusMessage = "<p style='color: green;'>សាររបស់អ្នកត្រូវបានផ្ញើដោយជោគជ័យ!</p>";
            } else {
                $statusMessage = "<p style='color: red;'>ការផ្ញើសារបានបរាជ័យ! Telegram response: " . htmlspecialchars($responseArray['description']) . "</p>";
            }
        }
    } else {
        $statusMessage = "<p style='color: orange;'>សូមបញ្ចូលសារជាមុនសិន!</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ផ្ញើសារទៅ Telegram Bot</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Koulen&family=Noto+Sans+Khmer:wght@400;700&display=swap');
        
        body {
            font-family: 'Noto Sans Khmer', sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f0f2f5;
            margin: 0;
        }
        .container {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            width: 90%;
            max-width: 500px;
        }
        h2 {
            font-family: 'Koulen', cursive;
            text-align: center;
            color: #333;
            margin-top: 0;
        }
        textarea {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
            margin-bottom: 20px;
            box-sizing: border-box;
            font-family: 'Noto Sans Khmer', sans-serif;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #0088cc;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            font-family: 'Koulen', cursive;
            letter-spacing: 1px;
        }
        button:hover {
            background-color: #0077b3;
        }
        .status-message {
            text-align: center;
            margin-bottom: 15px;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="container">
        <h2>ទម្រង់ផ្ញើសារទៅកាន់ Telegram</h2>

        <!-- បង្ហាញលទ្ធផលនៃការផ្ញើសារនៅទីនេះ -->
        <div class="status-message">
            <?php echo $statusMessage; ?>
        </div>

        <!-- Form នឹងបញ្ជូនទិន្នន័យមកកាន់ឯកសារខ្លួនឯង -->
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <textarea name="message" rows="6" placeholder="សូមបញ្ចូលសាររបស់អ្នកនៅទីនេះ..." required></textarea>
            <button type="submit">ផ្ញើសារ</button>
        </form>
    </div>

</body>
</html>