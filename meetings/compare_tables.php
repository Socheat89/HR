<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=samann1_admin_panel", "root", "");
    $tables = ['meetings', 'meetings-register'];
    foreach($tables as $table) {
        echo "Table: $table\n";
        $stmt = $pdo->query("DESCRIBE `$table` ");
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  - " . $row['Field'] . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
