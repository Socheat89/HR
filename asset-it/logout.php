<?php
session_start();

// Unset all of the session variables.
$_SESSION = array();

// Destroy the session.
session_destroy();

// Redirect to login page with a logged-out message.
header('Location: login.php?status=logged_out');
exit();
?>