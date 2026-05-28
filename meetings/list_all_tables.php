<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_admin_panel", "root", "");
    $stmt = $pdo->query("SHOW TABLES");
    echo "Tables in samann1_admin_panel:\n";
    while($row = $stmt->fetch(PDO::FETCH_NUM)) {
        echo "- " . $row[0] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
