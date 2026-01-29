<?php
$servername = "localhost"; // ឬ IP របស់ Server Database
$username = "samann1_facebook-bot"; // ប្តូរជា username របស់អ្នក
$password = "facebook-bot!@#"; // ប្តូរជា password របស់អ្នក
$dbname = "samann1_facebook-bot";       // ប្តូរជាឈ្មោះ Database របស់អ្នក

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    // កំណត់ PDO error mode ទៅជា exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // បើភ្ជាប់មិន
    die("ERROR: Could not connect. " . $e->getMessage());
}
?>