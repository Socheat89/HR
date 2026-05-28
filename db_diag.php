<?php
try {
    $db = new PDO("mysql:host=localhost;dbname=samann1_admin_panel", "root", "");
    $stmt = $db->query("SHOW TABLES LIKE 'report_tasks'");
    if ($stmt->fetch()) {
        echo "Table report_tasks EXISTS in samann1_admin_panel\n";
    } else {
        echo "Table report_tasks MISSING in samann1_admin_panel\n";
    }
    
    $stmt = $db->query("SHOW TABLES LIKE 'daily_reports'");
    if ($stmt->fetch()) {
        echo "Table daily_reports EXISTS in samann1_admin_panel\n";
    } else {
        echo "Table daily_reports MISSING in samann1_admin_panel\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
