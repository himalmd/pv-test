<?php

declare(strict_types=1);

namespace Snaply\Database;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Database connection wrapper providing PDO access.
 *
 * This class wraps PDO to provide:
 * - Lazy connection initialization
 * - Consistent configuration (UTF-8, exceptions, fetch mode)
 * - Simple query execution helpers
 */
class Connection
{
    private ?PDO $pdo = null;
    private string $dsn;
    private string $username;
    private string $password;

    /**
     * @var array<int, mixed>
     */
    private array $options;

    /**
     * Create a new database connection wrapper.
     *
     * @param string $host Database host
     * @param string $database Database name
     * @param string $username Database username
     * @param string $password Database password
     * @param int $port Database port (default: 3306)
     * @param string $charset Character set (default: utf8mb4)
     */
    public function __construct(
        string $host,
        string $database,
        string $username,
        string $password,
        int $port = 3306,
        string $charset = 'utf8mb4'
    ) {
        $this->dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $host,
            $port,
            $database,
            $charset
        );
        $this->username = $username;
        $this->password = $password;
        $this->options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci",
        ];
    }

    /**
     * Create a connection from a configuration array.
     *
     * @param array<string, mixed> $config Configuration with keys: host, database, username, password, port?, charset?
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            $config['host'] ?? 'localhost',
            $config['database'] ?? '',
            $config['username'] ?? '',
            $config['password'] ?? '',
            $config['port'] ?? 3306,
            $config['charset'] ?? 'utf8mb4'
        );
    }

    /**
     * Get the underlying PDO instance, connecting if necessary.
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }

        return $this->pdo;
    }

    /**
     * Establish the database connection.
     *
     * @throws PDOException If connection fails
     */
    public function connect(): void
    {
        $this->pdo = new PDO(
            $this->dsn,
            $this->username,
            $this->password,
            $this->options
        );
    }

    /**
     * Close the database connection.
     */
    public function disconnect(): void
    {
        $this->pdo = null;
    }

    /**
     * Check if connected to the database.
     */
    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    /**
     * Execute a query and return the statement.
     *
     * @param string $sql SQL query with placeholders
     * @param array<string|int, mixed> $params Parameters to bind
     *
     * @return PDOStatement The executed statement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Execute a query and return all rows.
     *
     * @param string $sql SQL query with placeholders
     * @param array<string|int, mixed> $params Parameters to bind
     *
     * @return array<int, array<string, mixed>> Array of rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Execute a query and return a single row.
     *
     * @param string $sql SQL query with placeholders
     * @param array<string|int, mixed> $params Parameters to bind
     *
     * @return array<string, mixed>|null The row or null if not found
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return $result === false ? null : $result;
    }

    /**
     * Execute a query and return a single column value.
     *
     * @param string $sql SQL query with placeholders
     * @param array<string|int, mixed> $params Parameters to bind
     *
     * @return mixed The column value or null
     */
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        $result = $this->query($sql, $params)->fetchColumn();
        return $result === false ? null : $result;
    }

    /**
     * Execute an INSERT, UPDATE, or DELETE and return affected row count.
     *
     * @param string $sql SQL query with placeholders
     * @param array<string|int, mixed> $params Parameters to bind
     *
     * @return int Number of affected rows
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Get the last inserted ID.
     *
     * @return int The last insert ID
     */
    public function lastInsertId(): int
    {
        return (int) $this->getPdo()->lastInsertId();
    }

    /**
     * Begin a transaction.
     */
    public function beginTransaction(): bool
    {
        return $this->getPdo()->beginTransaction();
    }

    /**
     * Commit the current transaction.
     */
    public function commit(): bool
    {
        return $this->getPdo()->commit();
    }

    /**
     * Roll back the current transaction.
     */
    public function rollBack(): bool
    {
        return $this->getPdo()->rollBack();
    }

    /**
     * Check if inside a transaction.
     */
    public function inTransaction(): bool
    {
        return $this->getPdo()->inTransaction();
    }

    /**
     * Execute a callback within a transaction.
     *
     * @template T
     * @param callable(): T $callback The callback to execute
     * @return T The callback's return value
     *
     * @throws \Throwable Re-throws any exception after rolling back
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();

        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }
}
