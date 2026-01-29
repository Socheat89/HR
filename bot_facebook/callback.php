<?php
// callback.php
session_start();
require_once __DIR__ . '/vendor/autoload.php';

$fb = new Facebook\Facebook([
  'app_id' => '1961555027951118',
  'app_secret' => 'ce13a053b73eea23e3c91a4eb15289d8',
  'default_graph_version' => 'v19.0',
]);

$helper = $fb->getRedirectLoginHelper();

try {
  $accessToken = $helper->getAccessToken();
} catch(Facebook\Exception\ResponseException $e) {
  echo 'Graph returned an error: ' . $e->getMessage();
  exit;
} catch(Facebook\Exception\SDKException $e) {
  echo 'Facebook SDK returned an error: ' . $e->getMessage();
  exit;
}

if (! isset($accessToken)) {
  if ($helper->getError()) {
    header('HTTP/1.0 401 Unauthorized');
    echo "Error: " . $helper->getError() . "\n";
    echo "Error Code: " . $helper->getErrorCode() . "\n";
    echo "Error Reason: " . $helper->getErrorReason() . "\n";
    echo "Error Description: " . $helper->getErrorDescription() . "\n";
  } else {
    header('HTTP/1.0 400 Bad Request');
    echo 'Bad request';
  }
  exit;
}

// បានទទួល User Access Token
$oAuth2Client = $fb->getOAuth2Client();
$tokenMetadata = $oAuth2Client->debugToken($accessToken);
$tokenMetadata->validateAppId('{YOUR_APP_ID}'); // ផ្ទៀងផ្ទាត់ App ID
$tokenMetadata->validateExpiration();

// បំប្លែង Short-lived Token ទៅជា Long-lived Token
if (! $accessToken->isLongLived()) {
  try {
    $accessToken = $oAuth2Client->getLongLivedAccessToken($accessToken);
  } catch (Facebook\Exception\SDKException $e) {
    echo "<p>Error getting long-lived access token: " . $e->getMessage() . "</p>\n\n";
    exit;
  }
}

// រក្សាទុក Long-lived User Token នៅក្នុង Session
$_SESSION['fb_user_access_token'] = (string) $accessToken;

// ឥឡូវអ្នកអាចប្រើ User Access Token នេះដើម្បីទាញយកបញ្ជី Pages
try {
  // ទាញយក Pages ដែល User គ្រប់គ្រង
  $response = $fb->get('/me/accounts', $accessToken);
  $pages = $response->getGraphEdge()->asArray();

  echo "<h1>សូមជ្រើសរើស Page សម្រាប់ភ្ជាប់ជាមួយ Bot</h1>";
  echo "<ul>";
  foreach ($pages as $page) {
    // Page Access Token គឺ $page['access_token']
    // អ្នកអាចរក្សាទុក Page ID ($page['id']) និង Page Access Token នេះនៅក្នុង Database
    echo "<li>";
    echo htmlspecialchars($page['name']);
    // បង្កើតប៊ូតុងសម្រាប់ជ្រើសរើស Page នេះ
    echo " - <a href='select_page.php?page_id=" . $page['id'] . "&page_access_token=" . $page['access_token'] . "'>Connect this Page</a>";
    echo "</li>";
  }
  echo "</ul>";

} catch(Facebook\Exception\ResponseException $e) {
  echo 'Graph returned an error: ' . $e->getMessage();
  exit;
} catch(Facebook\Exception\SDKException $e) {
  echo 'Facebook SDK returned an error: ' . $e->getMessage();
  exit;
}

?>