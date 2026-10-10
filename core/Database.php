<?php
/**
 * ScreenBites - Core Database Engine (PDO Singleton Wrapper)
 * 
 * Provides safe parameter-bound query execution, transaction management,
 * and connection pooling for native PHP.
 * 
 * @author Aaron Louis F. Macapagal
 */

declare(strict_types=1);

namespace Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

class Database
{
    private static ?Database $instance = null;
    private PDO $connection;

    /**
     * Private constructor to enforce Singleton pattern
     */
    private function __construct()
    {
        $configFile = dirname(__DIR__) . '/config/database.php';

        if (!file_exists($configFile)) {
            throw new RuntimeException("Database configuration file not found at: {$configFile}");
        }

        /** @var array $config */
        $config = require $configFile;

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            $this->connection = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                $config['options']
            );
        } catch (PDOException $e) {
            // Log real error internally and hide sensitive credentials from user display
            error_log("[Database Connection Error] " . $e->getMessage());
            throw new RuntimeException("Could not connect to the database. Verify that MySQL is running in XAMPP.");
        }
    }

    /**
     * Prevent object cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new RuntimeException("Cannot unserialize singleton Database instance.");
    }

    /**
     * Get the global Database singleton instance
     * 
     * @return Database
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the raw underlying PDO connection instance
     * 
     * @return PDO
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Prepare and execute a SQL statement with parameters
     * 
     * @param string $sql The prepared SQL query
     * @param array $params Positional or named parameter bindings
     * @return PDOStatement
     * @throws PDOException
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("[Query Error] {$e->getMessage()} | SQL: {$sql}");
            throw $e;
        }
    }

    /**
     * Fetch a single row as an associative array
     * 
     * @param string $sql
     * @param array $params
     * @return array|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Fetch all matching rows as an array of associative arrays
     * 
     * @param string $sql
     * @param array $params
     * @return array
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Fetch a single column value from the first row
     * 
     * @param string $sql
     * @param array $params
     * @return mixed
     */
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : null;
    }

    /**
     * Execute an INSERT, UPDATE, or DELETE query and return the affected rows
     * 
     * @param string $sql
     * @param array $params
     * @return int Number of affected rows
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Retrieve the ID of the last inserted row
     * 
     * @return string
     */
    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Start a database transaction
     * 
     * @return bool
     */
    public function beginTransaction(): bool
    {
        if (!$this->connection->inTransaction()) {
            return $this->connection->beginTransaction();
        }
        return false;
    }

    /**
     * Commit the current active transaction
     * 
     * @return bool
     */
    public function commit(): bool
    {
        if ($this->connection->inTransaction()) {
            return $this->connection->commit();
        }
        return false;
    }

    /**
     * Roll back the current active transaction
     * 
     * @return bool
     */
    public function rollBack(): bool
    {
        if ($this->connection->inTransaction()) {
            return $this->connection->rollBack();
        }
        return false;
    }

    /**
     * Execute a closure inside a database transaction automatically rolling back on failure
     * 
     * @param callable $callback function(Database $db): mixed
     * @return mixed
     * @throws \Throwable
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            error_log("[Transaction Failed] Rolled back: " . $e->getMessage());
            throw $e;
        }
    }
}