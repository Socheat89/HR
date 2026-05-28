<?php
require_once __DIR__ . "/../db_connection.php";
try {
    $pdo = getPDO();
} catch (Exception $e) {
    die("Connection Failed: " . $e->getMessage());
}
?>
