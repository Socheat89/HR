<?php
/**
 * File: setup_profile.php
 * Version: 2.0 (Single-file, User-Friendly)
 * Description: Sets the "Get Started" button and Greeting Text for a Facebook Page.
 * How to use:
 *   1. EDIT the $page_access_token variable below with your Page Access Token.
 *   2. UPLOAD this single file to your web server.
 *   3. RUN this file in your browser ONE TIME.
 * Example URL: https://app.vvc.asia/bot_facebook/setup_profile.php
 */

// --- 1. ការកំណត់រចនាសម្ព័ន្ធ (CONFIGURATION) ---
// !!! សំខាន់៖ សូមបិទភ្ជាប់ PAGE_ACCESS_TOKEN របស់អ្នកនៅទីនេះ !!!
// !!! IMPORTANT: Paste your PAGE_ACCESS_TOKEN here !!!
$page_access_token = 'EAAb4Bh6lZCg4BPP5emomBDYPxuexwURHj897Rkin1XbGBJ6UUscu7IMBUzRVLfGOPyoAUPcgSNHPNe9PryEOrCgHDPDCQukTnalbaLjkTLehoXyODCkZBofce4f3xcCQ30MzBrwzakkPwF1zbpmsIZACETekX45ZBWsrQUKNDcnsXitR0mTZBEgR2tg1SvluQTn0ZBpmCzLpg2830gaWEACD4ct7MWnrITezK5kDGZC';


// --- 2. រៀបចំទិន្នន័យសម្រាប់ API (PREPARE API DATA) ---

// Greeting text (សារស្វាគមន៍ដែលបង្ហាញមុនពេល user ចុច Get Started)
$greeting_text = [
    [
        'locale' => 'default', // សម្រាប់ភាសាខ្មែរ
        'text' => 'សូមស្វាគមន៍ {{user_first_name}}! ចុចប៊ូតុងខាងក្រោមដើម្បីចាប់ផ្ដើម។'
    ],
    [
        'locale' => 'en_US', // សម្រាប់ភាសាអង់គ្លេស
        'text' => 'Welcome {{user_first_name}}! Tap the button below to get started.'
    ]
];

// Get Started button payload (ข้อมูลដែលនឹងส่งទៅ Webhook ពេល user ចុច)
$get_started_data = [
    'payload' => 'USER_TAPPED_GET_STARTED'
];

// ទិន្នន័យทั้งหมดដែលត្រូវส่งទៅ Facebook API
$data_payload = [
    'greeting' => $greeting_text,
    'get_started' => $get_started_data
];


// --- 3. ដំណើរការហៅ API (EXECUTE API CALL) ---

// ចាប់ផ្តើមដំណើរការតែក្នុងករណី Token ត្រូវបានបំពេញ
if ($page_access_token != '' && $page_access_token != 'EAAb4Bh6lZCg4BPEfwqAGcTHscSfv7PhO8XuzEiW16dPtLE3ZCJmt1rSXFpeXSN80RmIEmWGbvdtTUgFP034mxi8VwzRGwKkrwtHvV5w0mMUW5wj7abPTeljUGCNUwXvSEg7rgZCsnJ1s5PdGXUN3tlSHMFpvZBfWVaO3rPoOCZC4BLco0PnBPkA2FbQlmHbXBcQjMUAZDZD') {
    $url = 'https://graph.facebook.com/v19.0/me/messenger_profile?access_token=' . $page_access_token;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response_body = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
}

// --- 4. បង្ហាញលទ្ធផល (DISPLAY RESULT) ---
?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messenger Profile Setup</title>
    <style>
        body { font-family: 'Kantumruy Pro', 'Helvetica', sans-serif; line-height: 1.6; margin: 20px; background-color: #f4f4f9; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #ddd; padding-bottom: 10px;}
        .message { padding: 15px; border-radius: 5px; margin-top: 20px; border-left-width: 5px; border-left-style: solid; }
        .success { background-color: #e9f7ef; border-color: #28a745; color: #155724; }
        .error { background-color: #f8d7da; border-color: #dc3545; color: #721c24; }
        .warning { background-color: #fff3cd; border-color: #ffc107; color: #856404; }
        code { background: #eee; padding: 2px 5px; border-radius: 3px; font-family: 'Courier New', Courier, monospace; }
        pre { background: #2d2d2d; color: #f1f1f1; padding: 15px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
    <div class="container">
        <h1>តម្លើង Messenger Profile (Get Started Button)</h1>

        <?php if ($page_access_token == '' || $page_access_token == 'YOUR_PAGE_ACCESS_TOKEN_GOES_HERE'): ?>
            <div class="message error">
                <strong><big>កំហុសក្នុងការកំណត់រចនាសម្ព័ន្ធ (Configuration Error)</big></strong><br>
                សូមបើកไฟล์ <code>setup_profile.php</code> ហើយធ្វើការកែសម្រួលตัวแปร <code>$page_access_token</code> ដោយបិទភ្ជាប់ Page Access Token របស់អ្នក។<br><br>
                Please open the <code>setup_profile.php</code> file and replace <code>'YOUR_PAGE_ACCESS_TOKEN_GOES_HERE'</code> with your actual Page Access Token.
            </div>

        <?php elseif ($curl_error): ?>
            <div class="message error">
                <strong>cURL Error:</strong><br>
                <code><?php echo htmlspecialchars($curl_error); ?></code>
            </div>

        <?php else: ?>
            <p><strong>URL ដែលបានហៅ (Request URL):</strong> <code><?php echo htmlspecialchars(str_replace($page_access_token, 'ACCESS_TOKEN_HIDDEN', $url)); ?></code></p>
            <p><strong>HTTP Status Code:</strong> <code><?php echo $http_code; ?></code></p>
            
            <?php
            $response_data = json_decode($response_body, true);
            if ($http_code == 200 && isset($response_data['result']) && $response_data['result'] === 'success'):
            ?>
                <div class="message success">
                    <strong>ជោគជ័យ (SUCCESS)</strong><br>
                    ប៊ូតុង 'Get Started' និងសារស្វាគមន៍ត្រូវបានតម្លើងដោយជោគជ័យសម្រាប់ Page របស់អ្នក។
                </div>
            <?php else: ?>
                <div class="message error">
                    <strong>បរាជ័យ (ERROR)</strong><br>
                    ការតម្លើងបានបរាជ័យ។ សូមពិនិត្យមើលព័ត៌មានខាងក្រោម៖<br>
                    <ul>
                        <li>តើ <code>Page Access Token</code> របស់អ្នកត្រឹមត្រូវ និងមិនទាន់ផុតកំណត់មែនទេ?</li>
                        <li>តើ Token របស់អ្នកមានសិទ្ធិ (permissions) <code>pages_messaging</code> និង <code>pages_manage_metadata</code> ហើយឬនៅ?</li>
                    </ul>
                </div>
            <?php endif; ?>

            <p><strong>ការឆ្លើយតបពី Facebook API (Facebook API Response):</strong></p>
            <pre><?php echo htmlspecialchars(json_encode($response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>

        <?php endif; ?>
    </div>
</body>
</html>