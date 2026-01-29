<?php
// All-in-One TikTok Dashboard (index.php)

session_start();

// ===================================================================
// 1. CONFIGURATION - ការកំណត់
// !!! សំខាន់៖ សូមជំនួសព័ត៌មានខាងក្រោមជាមួយនឹងព័ត៌មានពិតប្រាកដពី App របស់អ្នក
// ===================================================================
define('TIKTOK_CLIENT_KEY', 'awxg1ftly3fszdmd'); // ដាក់ Client Key របស់អ្នកនៅទីនេះ
define('TIKTOK_CLIENT_SECRET', 'X5wUYMDjCKtCU0B5sZPtCy7RZOcpHpSO'); // ដាក់ Client Secret របស់អ្នកនៅទីនេះ
define('TIKTOK_REDIRECT_URI', 'https://app.vvc.asia/social-media/index.php'); // ដាក់ URL របស់ไฟล์នេះ

// ===================================================================
// 2. API HELPER FUNCTIONS - Functions សម្រាប់ហៅ API
// ===================================================================

/**
 * ទាញយក Access Token ពី TikTok បន្ទាប់ពី user បានយល់ព្រម
 */
function get_tiktok_access_token($code) {
    $url = 'https://open.tiktokapis.com/v2/oauth/token/';
    $params = [
        'client_key'    => TIKTOK_CLIENT_KEY,
        'client_secret' => TIKTOK_CLIENT_SECRET,
        'code'          => $code,
        'grant_type'    => 'authorization_code',
        'redirect_uri'  => TIKTOK_REDIRECT_URI,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

/**
 * ទាញយកព័ត៌មាន Profile របស់អ្នកប្រើប្រាស់
 */
function get_user_info($access_token) {
    $url = 'https://open.tiktokapis.com/v2/user/info/?fields=open_id,display_name,avatar_url';
    $headers = ['Authorization: Bearer ' . $access_token];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

/**
 * ទាញយកបញ្ជីវីដេអូ
 */
function get_user_videos($access_token, $max_count = 12) {
    $url = 'https://open.tiktokapis.com/v2/video/list/?fields=id,title,cover_image_url,share_url,like_count,comment_count,share_count,view_count';
    $headers = [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json',
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['max_count' => $max_count]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// ===================================================================
// 3. PAGE LOGIC - Logic សម្រាប់គ្រប់គ្រងទំព័រ
// ===================================================================

$logged_in = false;
$user_info = null;
$video_list = null;

// ចាកចេញ (Logout)
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header('Location: ' . strtok($_SERVER["REQUEST_URI"], '?')); // Redirect to clean URL
    exit();
}

// ទទួលការឆ្លើយតបពី TikTok (Callback)
if (isset($_GET['code'])) {
    $token_data = get_tiktok_access_token($_GET['code']);
    if (isset($token_data['data']['access_token'])) {
        $_SESSION['tiktok_access_token'] = $token_data['data']['access_token'];
        // បញ្ជូនទៅកាន់ Dashboard ដើម្បីលុប query string ចេញពី URL
        header('Location: ' . TIKTOK_REDIRECT_URI);
        exit();
    } else {
        // បង្ហាញ Error ប្រសិនបើមិនអាចទាញយក Token បាន
        die("Error getting access token: <pre>" . print_r($token_data, true) . "</pre>");
    }
}

// ពិនិត្យមើលថាតើអ្នកប្រើប្រាស់បាន Login រួចហើយឬនៅ
if (isset($_SESSION['tiktok_access_token'])) {
    $logged_in = true;
    $access_token = $_SESSION['tiktok_access_token'];
    $user_info = get_user_info($access_token);
    $video_list = get_user_videos($access_token);

    // Basic check for expired token. A real app would use the refresh token.
    if (isset($user_info['error']) && $user_info['error']['code'] == 10002) {
        $logged_in = false;
        session_destroy();
    }
}

?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All-in-One TikTok Dashboard</title>
    <style>
        /* ===================================================================
           4. CSS STYLES - កូដសម្រាប់ตกแต่ง
           =================================================================== */
        @import url('https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;700&display=swap');
        body {
            font-family: 'Kantumruy Pro', sans-serif;
            background-color: #f0f2f5;
            color: #1c1e21;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            background-color: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        h1, h2, h3 { color: #000; }
        .login-container { text-align: center; padding: 50px 20px; }
        .login-btn, .logout-btn {
            background-color: #fe2c55;
            color: white;
            padding: 12px 28px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            display: inline-block;
            transition: background-color 0.3s;
        }
        .login-btn:hover { background-color: #e42048; }
        .logout-btn { background-color: #555; }
        .logout-btn:hover { background-color: #333; }
        .profile-info { text-align: center; margin-bottom: 30px; }
        .avatar { width: 100px; height: 100px; border-radius: 50%; border: 4px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .profile-info h2 { margin-top: 10px; }

        .dashboard-section {
            margin-bottom: 30px;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            background-color: #f9f9f9;
        }
        .video-upload form { display: flex; flex-direction: column; gap: 15px; }
        .video-upload label { font-weight: bold; }
        .video-upload input, .video-upload textarea { padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-family: 'Kantumruy Pro', sans-serif;}
        .video-upload button {
            background-color: #25d366; color: white; border: none; padding: 12px;
            border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 16px;
        }
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
        }
        .video-card {
            border: 1px solid #ddd; border-radius: 8px; overflow: hidden;
            text-align: left; background-color: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            display: flex; flex-direction: column;
        }
        .video-card img { width: 100%; height: auto; display: block; aspect-ratio: 9/16; object-fit: cover; }
        .video-card-content { padding: 10px; flex-grow: 1; }
        .video-card-content p { margin: 0 0 10px 0; font-size: 14px; font-weight: bold; min-height: 40px;}
        .video-stats { display: flex; justify-content: space-around; font-size: 12px; color: #666; padding: 5px 10px; border-top: 1px solid #eee; }
        .video-card a {
            display: block; padding: 10px; background-color: #f0f2f5; text-decoration: none;
            color: #333; font-size: 14px; text-align: center; font-weight: bold;
        }
        .error { color: #d93025; font-weight: bold; text-align: center; padding: 15px; background-color: #fce8e6; border: 1px solid #f9bdbb; border-radius: 8px;}
    </style>
</head>
<body>

    <div class="container">
        <!-- ===================================================================
           5. HTML VIEW - កូដសម្រាប់បង្ហាញលើ Browser
           =================================================================== -->
        <header>
            <h1>ផ្ទាំងគ្រប់គ្រង TikTok</h1>
            <?php if ($logged_in): ?>
                <a href="?action=logout" class="logout-btn">ចាកចេញ</a>
            <?php endif; ?>
        </header>

        <?php if (!$logged_in): ?>
            <!-- ទំព័រសម្រាប់ Login -->
            <div class="login-container">
                <h2>សូមភ្ជាប់គណនី TikTok របស់អ្នក</h2>
                <p>ដើម្បីចាប់ផ្តើមគ្រប់គ្រងមាតិការបស់អ្នក សូមចុចប៊ូតុងខាងក្រោម។</p>
                <?php
                    $scopes = 'user.info.basic,video.list,video.upload';
                    $auth_url = 'https://www.tiktok.com/v2/auth/authorize?client_key=' . TIKTOK_CLIENT_KEY . '&scope=' . $scopes . '&response_type=code&redirect_uri=' . urlencode(TIKTOK_REDIRECT_URI) . '&state=' . uniqid();
                ?>
                <a href="<?php echo $auth_url; ?>" class="login-btn">ភ្ជាប់ជាមួយ TikTok</a>
            </div>

        <?php else: ?>
            <!-- ទំព័រ Dashboard ពេល Login រួច -->
            <section class="profile-info">
                <?php if (isset($user_info['data'])): ?>
                    <img src="<?php echo htmlspecialchars($user_info['data']['avatar_url']); ?>" alt="Avatar" class="avatar">
                    <h2>សូមស្វាគមន៍, <?php echo htmlspecialchars($user_info['data']['display_name']); ?>!</h2>
                <?php else: ?>
                    <p class="error">មិនអាចទាញយកព័ត៌មានគណនីបានទេ។ សូមព្យាយាមចាកចេញ (Logout) ហើយភ្ជាប់ម្ដងទៀត។</p>
                <?php endif; ?>
            </section>

            <section class="dashboard-section video-upload">
                <h3>បង្ហោះវីដេអូថ្មី</h3>
                <form action="#" method="post" onsubmit="alert('មុខងារនេះគ្រាន់តែជាការបង្ហាញ! ការបង្ហោះពិតប្រាកដត្រូវការកូដ Backend ស្មុគស្មាញបន្ថែមទៀត។'); return false;">
                    <label for="video_file">ជ្រើសរើសវីដេអូ:</label>
                    <input type="file" id="video_file" name="video_file" required>
                    <label for="caption">ចំណងជើង (Caption):</label>
                    <textarea id="caption" name="caption" rows="3" placeholder="សរសេរចំណងជើង និង #hashtags..."></textarea>
                    <button type="submit">បង្ហោះឥឡូវនេះ (Demo)</button>
                </form>
            </section>

            <section class="dashboard-section video-list">
                <h3>វីដេអូចុងក្រោយរបស់អ្នក</h3>
                <div class="grid-container">
                    <?php if (isset($video_list['data']['videos']) && !empty($video_list['data']['videos'])): ?>
                        <?php foreach ($video_list['data']['videos'] as $video): ?>
                            <div class="video-card">
                                <img src="<?php echo htmlspecialchars($video['cover_image_url']); ?>" alt="Video Cover">
                                <div class="video-card-content">
                                    <p><?php echo htmlspecialchars($video['title'] ?: 'វីដេអូគ្មានចំណងជើង'); ?></p>
                                </div>
                                <div class="video-stats">
                                    <span>❤️ <?php echo $video['like_count']; ?></span>
                                    <span>💬 <?php echo $video['comment_count']; ?></span>
                                    <span>👀 <?php echo $video['view_count']; ?></span>
                                </div>
                                <a href="<?php echo htmlspecialchars($video['share_url']); ?>" target="_blank">មើលនៅលើ TikTok</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>មិនមានវីដេអូ ឬមិនអាចទាញយកបានទេ។</p>
                    <?php endif; ?>
                </div>
            </section>

        <?php endif; ?>
    </div>
</body>
</html>