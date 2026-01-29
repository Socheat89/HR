<?php
session_start();
$config = require 'fb_config.php';
$app_id = $config['app_id'];
$app_secret = $config['app_secret'];
$redirect_uri = $config['redirect_uri'];

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    $url = "https://graph.facebook.com/v18.0/oauth/access_token?client_id=$app_id&client_secret=$app_secret&redirect_uri=" . urlencode($redirect_uri) . "&code=$code";
    $response = file_get_contents($url);
    $data = json_decode($response, true);
    if (isset($data['access_token'])) {
        $access_token = $data['access_token'];
        $pages_url = "https://graph.facebook.com/me/accounts?access_token=$access_token";
        $pages_response = file_get_contents($pages_url);
        $pages_data = json_decode($pages_response, true);
        if (isset($pages_data['data'])) {
            $_SESSION['pages'] = $pages_data['data'];
            $_SESSION['user_access_token'] = $access_token;
        }
    }
}
header("Location: index.php");
exit;
?>