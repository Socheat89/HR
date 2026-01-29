<?php
session_start();

// If the user is not logged in, redirect them to the new combined login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login_register.php");
    exit();
}
?>