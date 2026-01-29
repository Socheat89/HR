<?php
require_once 'db_config.php';

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8";
            $this->conn = new PDO($dsn, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            return $this->conn;
        } catch(PDOException $exception) {
            // Detailed error message for debugging
            die(json_encode([
                'status' => 'error',
                'message' => 'Database connection failed',
                'details' => [
                    'error' => $exception->getMessage(),
                    'host' => $this->host,
                    'database' => $this->db_name,
                    'username' => $this->username
                ]
            ]));
        }
    }

    // Test connection method
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            if ($conn) {
                return "Database connection successful!";
            }
        } catch(Exception $e) {
            return "Connection failed: " . $e->getMessage();
        }
    }
}

// Test the connection immediately
if (isset($_GET['test'])) {
    $db = new Database();
    echo $db->testConnection();
}
?>