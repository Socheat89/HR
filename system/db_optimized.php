<?php
/**
 * Optimized Database Connection Helper
 * features: persistent connections, prepared statement caching, query result caching
 */

require_once __DIR__ . '/../db_connection.php';

class DatabaseOptimized {
    private static $instance = null;
    private $pdo;
    private $queryCache = [];
    private $cacheEnabled = true;
    private $cacheExpiry = 60;

    private function __construct() {
        $options = [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::ATTR_TIMEOUT => 5,
        ];

        try {
            // Use centralized connection with optimized options
            $this->pdo = getPDO($options);
        } catch (Exception $e) {
            error_log("Optimized DB Connection Error: " . $e->getMessage());
            die("Connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    public function query($sql, $params = [], $bypassCache = false) {
        $cacheKey = md5($sql . serialize($params));
        if ($this->cacheEnabled && !$bypassCache && isset($this->queryCache[$cacheKey])) {
            return $this->queryCache[$cacheKey];
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll();

        if ($this->cacheEnabled && !$bypassCache) {
            $this->queryCache[$cacheKey] = $result;
        }

        return $result;
    }

    public function beginTransaction() { return $this->pdo->beginTransaction(); }
    public function commit() { return $this->pdo->commit(); }
    public function rollback() { return $this->pdo->rollBack(); }
    public function clearCache() { $this->queryCache = []; }
    public function disableCache() { $this->cacheEnabled = false; }
    public function enableCache() { $this->cacheEnabled = true; }
    private function __clone() {}
    public function __wakeup() { throw new Exception("Cannot unserialize singleton"); }
}

function db() { return DatabaseOptimized::getInstance(); }
function pdo() { return DatabaseOptimized::getInstance()->getConnection(); }
?>
