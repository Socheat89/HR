<?php
/**
 * File: select_page.php
 * Description: Saves the selected page's credentials to the database.
 */
require_once __DIR__ . '/config.php';

if (isset($_GET['page_id']) && isset($_GET['page_access_token']) && isset($_GET['page_name'])) {
    $page_id = $_GET['page_id'];
    $page_token = urldecode($_GET['page_access_token']);
    $page_name = urldecode($_GET['page_name']);

    // រក្សាទុកក្នុង Database
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?), (?, ?), (?, ?)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

    $key1 = 'connected_page_id';
    $key2 = 'connected_page_name';
    $key3 = 'page_access_token';

    $stmt->bind_param("ssssss", $key1, $page_id, $key2, $page_name, $key3, $page_token);
    $stmt->execute();
    $stmt->close();

    // Redirect ទៅកាន់ Dashboard
    header("Location: dashboard.php");
    exit();
} else {
    die("Invalid request.");
}
?>