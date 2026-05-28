<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_admin_panel", "root", "");
    $stmt = $pdo->query("DESCRIBE `meetings-register` ");
    echo "Columns in `meetings-register`:\n";
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
