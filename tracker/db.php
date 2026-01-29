<?php
// --- DATABASE CONNECTION ---
$host = 'localhost';
$db   = 'samann1_tracker'; // បញ្ចូលឈ្មោះ Database របស់អ្នក
$user = 'samann1_tracker';    // បញ្ចូល Username របស់អ្នក
$pass = 'tracker@2025';  // បញ្ចូល Password របស់អ្នក
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // បញ្ឈប់ការដំណើរការភ្លាមៗ បើភ្ជាប់មិនជោគជ័យ
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>