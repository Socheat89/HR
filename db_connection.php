<?php
// Central Database Connection File with Fallback Logic
// Supports multiple user/password combinations for local vs production environments

// Define list of possible database credentials
$db_configs = [
    // Configuration 1: Production / Server Environment (Specific User)
    [
        'host' => 'localhost',
        'dbname' => 'samann1_admin_panel',
        'username' => 'samann1_admin_panel',
        'password' => 'admin_panel@2025'
    ],
    // Configuration 2: Local Development Environment (Root User)
    [
        'host' => 'localhost',
        'dbname' => 'samann1_admin_panel',
        'username' => 'root',
        'password' => ''
    ]
];

/**
 * Get PDO Connection
 * Iterates through configurations until a successful connection is made.
 * @param array $customOptions Additional PDO options to override defaults
 * @return PDO
 */
function getPDO($customOptions = []) {
    global $db_configs;
    
    $defaultOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $options = $customOptions + $defaultOptions;

    foreach ($db_configs as $config) {
        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
            $conn = new PDO($dsn, $config['username'], $config['password'], $options);
            $conn->exec("SET NAMES 'utf8mb4'");
            return $conn;
        } catch (PDOException $e) {
            // Connection failed for this config, try the next one
            continue;
        }
    }
    
    // If loop finishes without returning, all attempts failed
    error_log("All database connection attempts failed.");
    die("មានបញ្ហាក្នុងការតភ្ជាប់ទៅមូលដ្ឋានទិន្នន័យ។ សូមព្យាយាមម្តងទៀត។");
}

/**
 * Get MySQLi Connection
 * Iterates through configurations until a successful connection is made.
 * @return mysqli
 */
function getMySQLi() {
    global $db_configs;

    foreach ($db_configs as $config) {
        try {
            // Suppress warnings for connection attempts
            $conn = @new mysqli($config['host'], $config['username'], $config['password'], $config['dbname']);
            
            if (!$conn->connect_error) {
                $conn->set_charset("utf8mb4");
                return $conn;
            }
        } catch (Exception $e) {
            continue;
        }
    }

    die("Database connection failed: Unable to connect with any provided credentials.");
}

// Global variables for backward compatibility if needed by some older scripts
// Note: These will only reflect the LAST configuration in the list, 
// so relying on them directly without using the functions is discouraged.
$host = 'localhost';
$dbname = 'samann1_admin_panel';
// $username and $password cannot be reliably set globally here without knowing which one works.
?>
