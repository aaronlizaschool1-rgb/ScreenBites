<?php

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

    private function __construct()
    {
        $configFile = dirname(__DIR__) . '/config/database.php';

        if (!file_exists($configFile)) {
            throw new RuntimeException("Database configuration file not found at: {$configFile}");
        }

        $config = require$configFile;

        try {

            $initialDsn = sprintf(
                '%s:host=%s;port=%d;charset=%s',
                $config['driver'],$config['host'],
                $config['port'],$config['charset']
            );

            $this->connection = new PDO(
                $initialDsn,$config['username'],
                $config['password'],$config['options']
            );

            $dbName = $config['database'];$this->connection->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $this->connection->exec("USE `{$dbName}`;");

            $this->autoMigrateIfMissing();

        } catch (PDOException $e) {
            error_log("[Database Connection Error] " . $e->getMessage());
            throw new RuntimeException("Could not connect to the database. Verify that MySQL is running in XAMPP.");
        }
    }

    private function autoMigrateIfMissing(): void
    {

        $stmt =$this->connection->query("SHOW TABLES LIKE 'users'");
        $tableExists = $stmt &&$stmt->rowCount() > 0;

        if (!$tableExists) {
            $schemaFile = dirname(__DIR__) . '/database/schema.sql';$seedsFile  = dirname(__DIR__) . '/database/seeds.sql';

            if (file_exists($schemaFile)) {
                $schemaSql = file_get_contents($schemaFile);
                if (!empty($schemaSql)) {
                    $this->connection->exec($schemaSql);
                }
            }

            if (file_exists($seedsFile)) {
                $seedsSql = file_get_contents($seedsFile);
                if (!empty($seedsSql)) {
                    $this->connection->exec($seedsSql);
                }
            }
        }
    }

    private function __clone() {}

    public function __wakeup()
    {
        throw new RuntimeException("Cannot unserialize singleton Database instance.");
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }

    public function query(string $sql, array$params = []): PDOStatement
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("[Query Error] {$e->getMessage()} \vert{} SQL: {$sql}");
            throw $e;
        }
    }

    public function fetchOne(string $sql, array$params = []): ?array
    {
        $stmt =$this->query($sql,$params);
        $result =$stmt->fetch();
        return $result !== false ? $result : null;
    }

    public function fetchAll(string $sql, array$params = []): array
    {
        return $this->query($sql,$params)->fetchAll();
    }

    public function fetchColumn(string $sql, array$params = []): mixed
    {
        $stmt =$this->query($sql,$params);
        $result =$stmt->fetchColumn();
        return $result !== false ? $result : null;
    }

    public function execute(string $sql, array$params = []): int
    {
        return $this->query($sql,$params)->rowCount();
    }

    public function lastInsertId(): string
    {
        return $this->connection->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        if (!$this->connection->inTransaction()) {
            return $this->connection->beginTransaction();
        }
        return false;
    }

    public function commit(): bool
    {
        if ($this->connection->inTransaction()) {
            return $this->connection->commit();
        }
        return false;
    }

    public function rollBack(): bool
    {
        if ($this->connection->inTransaction()) {
            return $this->connection->rollBack();
        }
        return false;
    }

    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result =$callback($this);$this->commit();
            return $result;
        } catch (\Throwable $e) {$this->rollBack();
            error_log("[Transaction Failed] Rolled back: " . $e->getMessage());
            throw $e;
        }
    }
}
