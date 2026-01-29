<?php
// config.php should contain these constants
define('BOT_TOKEN', '7599531092:AAHkvzpFsSwZHxHXRPvJJpKSQH-KO-HPuAM');
define('CHAT_ID', '-1002288036113');

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';

    // Validate input
    if (empty($content)) {
        $response = "Please fill in the content.";
    } elseif (strlen($content) > 4096) { // Telegram message limit
        $response = "Content is too long (max 4096 characters).";
    } else {
        // Prepare message for Telegram
        $message = "<b>ការបញ្ជេញមតិ:</b>\n" . htmlspecialchars($content);

        // Telegram API URL
        $telegramApiUrl = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";

        // Data to send
        $data = [
            'chat_id' => CHAT_ID,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];


        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $telegramApiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        // Execute cURL request
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Parse Telegram response
        $resultData = json_decode($result, true);
        if ($httpCode == 200 && $resultData['ok']) {
            $response = "Article sent to Telegram successfully!";
            $showSuccessPopup = true; // Flag to show success popup
        } else {
            $response = "Failed to send article: " . ($resultData['description'] ?? 'Unknown error');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Form</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:ital,wght@0,100..700;1,100..700&family=Koulen&family=Noto+Sans+Khmer:wght@100..900&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Kantumruy Pro', Arial, sans-serif;
            background-color: #f4f7fa;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 2rem;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #34495e;
        }

        textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #dfe6e9;
            border-radius: 8px;
            font-size: 1rem;
            resize: vertical;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        textarea:focus {
            border-color: #3498db;
            box-shadow: 0 0 8px rgba(52, 152, 219, 0.2);
            outline: none;
        }

        button {
            display: block;
            font-family: 'Kantumruy Pro', Arial, sans-serif;
            width: 100%;
            padding: 12px;
            background-color: #3498db;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        button:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }

        button:active {
            transform: translateY(0);
        }

        .response {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            font-size: 1rem;
        }

        .success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        /* Popup Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            opacity: 1;
        }

        .modal-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            transform: scale(0.9);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }

        .modal.show .modal-content {
            transform: scale(1);
            opacity: 1;
        }

        .modal-content p {
            font-size: 1.1rem;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .modal-content button {
            width: auto;
            padding: 10px 20px;
            min-width: 100px;
        }

        /* Media Queries for Responsive Design */
        @media screen and (max-width: 480px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 15px;
                border-radius: 10px;
            }

            h2 {
                font-size: 1.5rem;
                margin-bottom: 15px;
            }

            textarea {
                padding: 10px;
                font-size: 0.95rem;
            }

            button {
                padding: 10px;
                font-size: 1rem;
            }

            .response {
                font-size: 0.9rem;
                padding: 10px;
            }

            .modal-content {
                padding: 15px;
            }

            .modal-content p {
                font-size: 1rem;
            }
        }

        @media screen and (min-width: 481px) and (max-width: 768px) {
            .container {
                max-width: 90%;
                padding: 20px;
            }

            h2 {
                font-size: 1.8rem;
            }

            textarea {
                font-size: 1rem;
            }

            button {
                font-size: 1.05rem;
            }
        }

        @media screen and (min-width: 769px) and (max-width: 1024px) {
            .container {
                max-width: 80%;
            }

            h2 {
                font-size: 1.9rem;
            }
        }

        @media screen and (min-width: 1025px) {
            .container {
                max-width: 700px;
            }

            h2 {
                font-size: 2rem;
            }

            textarea {
                font-size: 1.05rem;
            }

            button {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Feedback Form</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="content">Article Content:</label>
                <textarea id="content" name="content" rows="10" required><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
            </div>
            <button type="submit">បញ្ជូនសំណើរ</button>
        </form>

        <?php if (isset($response)): ?>
            <div class="response <?php echo strpos($response, 'successfully') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($response); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Welcome Popup Modal -->
    <div id="welcomeModal" class="modal">
        <div class="modal-content">
            <p>សូមបញ្ចេញមតិនិងស្នើរសុំរបស់អ្នកទាំងអស់គ្នានៅទីនេះ! ប្រកបដោយសុជីវធម៍។ សូមអរគុណ!</p>
            <button onclick="closeModal('welcomeModal')">OK</button>
        </div>
    </div>

    <!-- Success Popup Modal -->
    <div id="successModal" class="modal">
        <div class="modal-content">
            <p>សូមអរគុណ! សម្រាប់ការបញ្ជេញមតិរបស់អ្នក!</p>
            <button onclick="closeModal('successModal')">OK</button>
        </div>
    </div>

    <script>
        // Show the welcome modal only on initial page load (no form submission)
        window.onload = function() {
            <?php if (!isset($showSuccessPopup) || !$showSuccessPopup): ?>
                const welcomeModal = document.getElementById('welcomeModal');
                welcomeModal.style.display = 'flex';
                setTimeout(() => welcomeModal.classList.add('show'), 10);
            <?php endif; ?>
        };

        // Show the success modal after successful submission
        <?php if (isset($showSuccessPopup) && $showSuccessPopup): ?>
            window.addEventListener('load', function() {
                const successModal = document.getElementById('successModal');
                successModal.style.display = 'flex';
                setTimeout(() => successModal.classList.add('show'), 10);
            });
        <?php endif; ?>

        // Close the specified modal with animation
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300); // Match transition duration
        }

        // Close modals when clicking outside the modal content
        document.getElementById('welcomeModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeModal('welcomeModal');
            }
        });

        document.getElementById('successModal').addEventListener('click', function(event) {
            if (event.target === this) {
                closeModal('successModal');
            }
        });
    </script>
</body>
</html>