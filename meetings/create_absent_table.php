<?php
require_once __DIR__ . '/includes/db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS `absent-register` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `id_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `gender` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `date` date DEFAULT NULL,
        `time` time DEFAULT NULL,
        `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `meeting_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    echo "Table `absent-register` created successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
