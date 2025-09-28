<?php
declare(strict_types=1);

namespace App\Utils;

use PDO;
use PDOException;
use Psr\Log\LoggerInterface;

class Database
{
    private ?PDO $connection = null;
    private array $config;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->config = require __DIR__ . '/../../config/database.php';
    }

    public function getConnection(): PDO
    {
        if ($this->connection === null) {
            $this->connect();
        }

        return $this->connection;
    }

    private function connect(): void
    {
        try {
            $this->logger->debug('Connecting to database', [
                'database' => $this->config['database']
            ]);

            // Ensure database directory exists
            $dbDir = dirname($this->config['database']);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
                $this->logger->info('Created database directory', ['path' => $dbDir]);
            }

            $dsn = "sqlite:" . $this->config['database'];
            $this->connection = new PDO($dsn, null, null, $this->config['options']);

            $this->logger->info('Database connection established successfully');
        } catch (PDOException $e) {
            $this->logger->error('Database connection failed', [
                'error' => $e->getMessage(),
                'database' => $this->config['database']
            ]);
            throw $e;
        }
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $startTime = microtime(true);
        
        try {
            $this->logger->debug('Executing database query', [
                'sql' => $sql,
                'params' => $this->sanitizeParams($params)
            ]);

            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->debug('Query executed successfully', [
                'execution_time_ms' => $executionTime,
                'affected_rows' => $stmt->rowCount()
            ]);

            return $stmt;
        } catch (PDOException $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->error('Database query failed', [
                'sql' => $sql,
                'params' => $this->sanitizeParams($params),
                'error' => $e->getMessage(),
                'execution_time_ms' => $executionTime
            ]);
            throw $e;
        }
    }

    public function beginTransaction(): bool
    {
        $this->logger->debug('Beginning database transaction');
        return $this->getConnection()->beginTransaction();
    }

    public function commit(): bool
    {
        $this->logger->debug('Committing database transaction');
        return $this->getConnection()->commit();
    }

    public function rollback(): bool
    {
        $this->logger->debug('Rolling back database transaction');
        return $this->getConnection()->rollBack();
    }

    public function lastInsertId(): string
    {
        return $this->getConnection()->lastInsertId();
    }

    private function sanitizeParams(array $params): array
    {
        $sanitized = [];
        foreach ($params as $key => $value) {
            // Don't log sensitive data
            if (is_string($key) && (
                stripos($key, 'password') !== false ||
                stripos($key, 'token') !== false ||
                stripos($key, 'secret') !== false
            )) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
}
