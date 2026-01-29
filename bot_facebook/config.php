<?php
/**
 * File: config.php
 * Description: Contains database and Telegram bot configuration and establishes the connection.
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('DB_HOST', 'localhost');
define('DB_USER', 'samann1_facebook-bot');
define('DB_PASS', 'facebook-bot!@#');
define('DB_NAME', 'samann1_facebook-bot');
define('PAGE_ACCESS_TOKEN', 'EAAb4Bh6lZCg4BPZAXsnRolBj1leOHcsYEyf54Q3V0nlO46WyFkYSrumZBcoqvIkwCEHFAEBBwZAORS1glTI6sZArK8rONClFJ6dOTri7zFyYIgqNMzexI5z3fAxRQAMRK4RsVdP4TXQtdjjjeaBnOKi2JsWiZAGTZAM4JZBGVKZA89BZBEGg7WyZCGIgHMsJBineBlQu0lTRgrfZBgZDZD');
define('VERIFY_TOKEN', 'abc123');
define('TELEGRAM_BOT_TOKEN', '8178885681:AAGg694AoxzeiOmPIScwve9JXIqrdSCkmpw'); // Replace with your bot token
define('TELEGRAM_CHAT_ID', '-4815497441'); // Replace with your chat ID

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>