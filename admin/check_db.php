<?php
require_once 'includes/db.php';
$stmt = $conn->prepare("DESCRIBE users");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $conn->prepare("DESCRIBE requests");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
