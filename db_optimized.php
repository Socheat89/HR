<?php
/**
 * Optimized Database Connection Helper
 * ការតភ្ជាប់មូលដ្ឋានទិន្នន័យដែលបានធ្វើឱ្យប្រសើរឡើង
 * 
 * Features:
 * - Persistent connections (ការតភ្ជាប់អចិន្ត្រៃយ៍)
 * - Prepared statement caching
 * - Query result caching
 * - Optimized PDO settings
 */

// Database configuration
define('DB_HOST_OPT', 'localhost');
define('DB_NAME_OPT', 'samann1_admin_panel');
define('DB_USER_OPT', 'samann1_admin_panel');
define('DB_PASS_OPT', 'admin_panel@2025');

class DatabaseOptimized {
    private static $instance = null;
    private $pdo;
    private $queryCache = [];
    private $cacheEnabled = true;
    private $cacheExpiry = 60; // Cache for 60 seconds
    
    private function __construct() {
        $dsn = "mysql:host=" . DB_HOST_OPT . ";dbname=" . DB_NAME_OPT . ";charset=utf8mb4";
        
        $options = [
            // Persistent connection - reuses existing connections (faster)
            PDO::ATTR_PERSISTENT => true,
            
            // Throw exceptions on errors
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            
            // Return associative arrays by default
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            
            // Use native prepared statements (more secure and faster)
            PDO::ATTR_EMULATE_PREPARES => false,
            
            // MySQL specific: Enable query buffering for faster reads
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            
            // Set connection timeout
            PDO::ATTR_TIMEOUT => 5,
        ];
        
        try {
            $this->pdo = new PDO($dsn, DB_USER_OPT, DB_PASS_OPT, $options);
            
            // Set MySQL session variables for optimization
            $this->pdo->exec("SET SESSION sql_mode = 'TRADITIONAL'");
            $this->pdo->exec("SET SESSION group_concat_max_len = 1000000");
            
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("ការតភ្ជាប់មូលដ្ឋានទិន្នន័យបរាជ័យ");
        }
    }
    
    /**
     * Get singleton instance (Singleton pattern for connection reuse)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get PDO connection
     */
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * Execute a cached query (for SELECT queries that don't change often)
     * សម្រាប់ query ដែលមិនផ្លាស់ប្តូរញឹកញាប់
     */
    public function cachedQuery($sql, $params = [], $cacheKey = null) {
        $key = $cacheKey ?? md5($sql . serialize($params));
        
        // Check cache
        if ($this->cacheEnabled && isset($this->queryCache[$key])) {
            $cached = $this->queryCache[$key];
            if (time() - $cached['time'] < $this->cacheExpiry) {
                return $cached['data'];
            }
        }
        
        // Execute query
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll();
        
        // Store in cache
        if ($this->cacheEnabled) {
            $this->queryCache[$key] = [
                'data' => $result,
                'time' => time()
            ];
        }
        
        return $result;
    }
    
    /**
     * Execute a single query with prepared statement
     */
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Get single value (optimized for COUNT, SUM, etc.)
     */
    public function fetchValue($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get single row
     */
    public function fetchRow($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    /**
     * Get all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Execute INSERT/UPDATE/DELETE and return affected rows
     */
    public function execute($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }
    
    /**
     * Clear query cache
     */
    public function clearCache() {
        $this->queryCache = [];
    }
    
    /**
     * Disable caching (for real-time data)
     */
    public function disableCache() {
        $this->cacheEnabled = false;
    }
    
    /**
     * Enable caching
     */
    public function enableCache() {
        $this->cacheEnabled = true;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Quick helper function to get database instance
 */
function db() {
    return DatabaseOptimized::getInstance();
}

/**
 * Quick helper to get PDO connection
 */
function pdo() {
    return DatabaseOptimized::getInstance()->getConnection();
}
?>
