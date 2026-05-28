<?php
session_start();
require 'admin/includes/db.php';
$id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT image_url FROM users WHERE id = ?");
$stmt->execute([$id]);
$url = $stmt->fetchColumn();
echo "Image URL: " . $url;
?>
