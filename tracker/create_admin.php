<?php
// --- Create Admin User Script ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db.php'; // Database connection

// Admin user details
$username = 'Admin';
$login_id = 'admin';
$password = 'admin123'; // Change this to a secure password
$role = 'admin';

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE login_id = ? AND role = 'admin'");
    $stmt->execute([$login_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        echo "Admin user already exists with login_id: $login_id\n";
        echo "You can login with:\n";
        echo "Login ID: $login_id\n";
        echo "Password: $password\n";
        exit;
    }

    // Insert new admin user
    $stmt = $pdo->prepare("INSERT INTO users (username, login_id, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$username, $login_id, $hashed_password, $role]);

    echo "Admin user created successfully!\n";
    echo "Login credentials:\n";
    echo "Login ID: $login_id\n";
    echo "Password: $password\n";
    echo "\nPlease change the password after first login for security.\n";

} catch (PDOException $e) {
    echo 'Error creating admin user: ' . $e->getMessage() . "\n";
}
?>