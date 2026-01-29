<?php
/**
 * File: login.php
 * Description: Initiates the Facebook Login process.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php'; // ត្រូវតែ Run `composer require facebook/graph-sdk`

$fb = new Facebook\Facebook([
  'app_id' => FB_APP_ID,
  'app_secret' => FB_APP_SECRET,
  'default_graph_version' => 'v19.0',
]);

$helper = $fb->getRedirectLoginHelper();

// Permission ដែលត្រូវការ
$permissions = ['public_profile', 'pages_show_list', 'pages_messaging', 'pages_read_engagement', 'pages_manage_metadata'];
$loginUrl = $helper->getLoginUrl(FB_REDIRECT_URI, $permissions);

?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>Login to Manage Bot</title>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Kantumruy Pro', sans-serif; display: flex; flex-direction:column; justify-content: center; align-items: center; height: 100vh; background-color: #f0f2f5; margin:0; }
        .login-container { text-align: center; background: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .login-btn {
            background-color: #1877F2;
            color: white;
            padding: 15px 30px;
            font-size: 1.2rem;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            display: inline-block;
            margin-top: 20px;
        }
        .login-btn i { margin-right: 10px; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>ភ្ជាប់ជាមួយ Facebook</h1>
        <p>ដើម្បីរៀបចំ Bot ឆ្លើយតបសារសម្រាប់ Page របស់អ្នក</p>
        <a href="<?php echo htmlspecialchars($loginUrl); ?>" class="login-btn">
            <i class="fab fa-facebook-square"></i> បន្តជាមួយ Facebook
        </a>
    </div>
</body>
</html>