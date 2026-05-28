<?php
// Centralized Database Connection
require_once __DIR__ . '/../db_connection.php';

try {
    $pdo = getPDO();
    $stmt = $pdo->query("DESCRIBE ks2_consolidated_staff");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
