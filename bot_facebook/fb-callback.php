<?php
/**
 * File: fb-callback.php
 * Description: Handles the redirect from Facebook, gets tokens, and lists pages.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

$fb = new Facebook\Facebook([
  'app_id' => FB_APP_ID,
  'app_secret' => FB_APP_SECRET,
  'default_graph_version' => 'v19.0',
]);

$helper = $fb->getRedirectLoginHelper();

try {
  $accessToken = $helper->getAccessToken();
} catch(Exception $e) {
  die ('Facebook SDK returned an error: ' . $e->getMessage());
}

if (!isset($accessToken)) {
  die('No access token received.');
}

// Exchange for a long-lived token
$oAuth2Client = $fb->getOAuth2Client();
if (!$accessToken->isLongLived()) {
  try {
    $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
  } catch (Facebook\Exception\SDKException $e) {
    die("Error getting long-lived access token: " . $e->getMessage());
  }
}

// Get user's pages
try {
  $response = $fb->get('/me/accounts?fields=id,name,access_token,tasks', (string) $accessToken);
  $pages = $response->getGraphEdge()->asArray();
} catch(Exception $e) {
  die('Error getting pages: ' . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="km">
<head>
    <meta charset="UTF-8">
    <title>ជ្រើសរើស Page</title>
    <link href="https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kantumruy Pro', sans-serif; background-color: #f0f2f5; margin: 20px; }
        .container { max-width: 700px; margin: auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { border-bottom: 1px solid #ddd; padding-bottom: 15px; }
        ul { list-style: none; padding: 0; }
        li { background: #f9f9f9; padding: 15px; border: 1px solid #eee; margin-bottom: 10px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; }
        a.connect-btn { background-color: #28a745; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; }
        .no-permission { color: #dc3545; }
    </style>
</head>
<body>
<div class="container">
    <h1>សូមជ្រើសរើស Page ដែលអ្នកចង់ភ្ជាប់</h1>
    <p>អ្នកត្រូវតែជា Admin របស់ Page ទើបអាចភ្ជាប់ Bot បាន។</p>
    <ul>
        <?php if (empty($pages)): ?>
            <li>មិនមាន Page សម្រាប់ជ្រើសរើសទេ។</li>
        <?php else: ?>
            <?php foreach ($pages as $page): ?>
                <li>
                    <span><strong><?php echo htmlspecialchars($page['name']); ?></strong></span>
                    <?php if (in_array('MANAGE', $page['tasks'])): // Check if user has Admin role ?>
                        <a href="select_page.php?page_id=<?php echo $page['id']; ?>&page_name=<?php echo urlencode($page['name']); ?>&page_access_token=<?php echo urlencode($page['access_token']); ?>" class="connect-btn">ភ្ជាប់ Page នេះ</a>
                    <?php else: ?>
                        <span class="no-permission">អ្នកមិនមានសិទ្ធិគ្រប់គ្រង (MANAGE) Page នេះទេ</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
    </ul>
</div>
</body>
</html>