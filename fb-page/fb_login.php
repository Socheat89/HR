<?php
session_start();
$config = require 'fb_config.php';
$app_id = $config['app_id'];
$redirect_uri = $config['redirect_uri'];
$scope = 'pages_manage_posts,pages_show_list';
$url = "https://www.facebook.com/v18.0/dialog/oauth?client_id=$app_id&redirect_uri=" . urlencode($redirect_uri) . "&scope=$scope";
header("Location: $url");
exit;
?>