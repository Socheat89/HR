<?php
require_once 'db.php';

try {
    $stmt = $db->query("DESCRIBE peer_votes");
    echo "Table Structure:\n";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    $stmt = $db->query("SHOW INDEX FROM peer_votes");
    echo "\nIndexes:\n";
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
