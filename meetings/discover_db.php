<?php
try {
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $stmt = $pdo->query("SHOW DATABASES");
    $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Databases:\n";
    foreach($dbs as $db) {
        echo "- $db\n";
        try {
            $pdo->query("USE `$db` ");
            $stmt2 = $pdo->query("SHOW TABLES LIKE 'meetings' ");
            if($stmt2->fetch()) {
                echo "  [FOUND meetings table here!]\n";
                $stmt3 = $pdo->query("SELECT COUNT(*) FROM meetings");
                echo "  Count: " . $stmt3->fetchColumn() . "\n";
            }
            $stmt2 = $pdo->query("SHOW TABLES LIKE 'absent_meetings' ");
            if($stmt2->fetch()) {
                echo "  [FOUND absent_meetings table here!]\n";
                $stmt3 = $pdo->query("SELECT COUNT(*) FROM absent_meetings");
                echo "  Count: " . $stmt3->fetchColumn() . "\n";
            }
        } catch (Exception $e) {}
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
