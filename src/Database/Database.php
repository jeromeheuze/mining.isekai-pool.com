<?php

namespace YentenPool\Database;

use YentenPool\Config\ConfigManager;
use PDO;
use PDOException;

/**
 * Database Connection Manager
 * Handles MySQL database connections and queries
 */
class Database
{
    private static $instance = null;
    private $pdo;
    private $config;

    /**
     * Private constructor for singleton pattern
     */
    private function __construct()
    {
        $this->config = ConfigManager::getInstance();
        $this->connect();
    }

    /**
     * Get singleton instance
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establish database connection
     */
    private function connect()
    {
        $dbConfig = $this->config->getDatabaseConfig();
        
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']};charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ];

        try {
            $this->pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
        } catch (PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get PDO instance
     */
    public function getPdo()
    {
        return $this->pdo;
    }

    /**
     * Execute a prepared statement
     */
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new \Exception("Database query failed: " . $e->getMessage());
        }
    }

    /**
     * Fetch single row
     */
    public function fetch($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Insert record and return last insert ID
     */
    public function insert($table, $data)
    {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        
        return $this->pdo->lastInsertId();
    }

    /**
     * Update records
     */
    public function update($table, $data, $where, $whereParams = [])
    {
        $setClause = [];
        foreach (array_keys($data) as $column) {
            $setClause[] = "{$column} = :{$column}";
        }
        $setClause = implode(', ', $setClause);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Delete records
     */
    public function delete($table, $where, $params = [])
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit()
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback()
    {
        return $this->pdo->rollback();
    }

    /**
     * Check if table exists
     */
    public function tableExists($tableName)
    {
        $sql = "SHOW TABLES LIKE :table";
        $result = $this->fetch($sql, ['table' => $tableName]);
        return !empty($result);
    }

    /**
     * Get table structure
     */
    public function getTableStructure($tableName)
    {
        $sql = "DESCRIBE {$tableName}";
        return $this->fetchAll($sql);
    }

    /**
     * Execute raw SQL (for migrations, etc.)
     */
    public function execute($sql)
    {
        try {
            return $this->pdo->exec($sql);
        } catch (PDOException $e) {
            throw new \Exception("SQL execution failed: " . $e->getMessage());
        }
    }

    /**
     * Test database connection
     */
    public function testConnection()
    {
        try {
            $this->query("SELECT 1");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get database statistics
     */
    public function getStats()
    {
        $stats = [];
        
        // Get table sizes
        $sql = "SELECT 
                    table_name,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()";
        $stats['tables'] = $this->fetchAll($sql);
        
        // Get connection info
        $stats['connection'] = [
            'host' => $this->config->get('database.host'),
            'database' => $this->config->get('database.name'),
            'charset' => 'utf8mb4'
        ];
        
        return $stats;
    }
}
