<?php
// Centralized Database Connection
require_once __DIR__ . '/../db_connection.php';

try {
    $pdo = getPDO();
    
    $columns = [
        'delivery_female_morning',
        'delivery_male_morning',
        'delivery_female_evening',
        'delivery_male_evening'
    ];
    
    foreach ($columns as $col) {
        $stmt = $pdo->query("SHOW COLUMNS FROM ks2_consolidated_staff LIKE '$col'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE ks2_consolidated_staff ADD COLUMN $col INT DEFAULT 0");
            echo "Added column $col<br>\n";
        } else {
            echo "Column $col already exists<br>\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
