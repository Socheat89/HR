<?php
include 'includes/auth.php';
logout();
header("Location: ../index.php");
exit();
?>