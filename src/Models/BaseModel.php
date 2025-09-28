<?php
declare(strict_types=1);

namespace App\Models;

use App\Utils\Database;
use Psr\Log\LoggerInterface;
use PDO;

abstract class BaseModel
{
    protected Database $database;
    protected LoggerInterface $logger;
    protected string $table;

    public function __construct(Database $database, LoggerInterface $logger)
    {
        $this->database = $database;
        $this->logger = $logger;
    }

    protected function find(int $id): ?array
    {
        $this->logger->debug("Finding {$this->table} by ID", ['id' => $id]);
        
        $stmt = $this->database->query(
            "SELECT * FROM {$this->table} WHERE id = ?",
            [$id]
        );
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->logger->debug("Find operation completed", [
            'table' => $this->table,
            'id' => $id,
            'found' => $result !== false
        ]);
        
        return $result ?: null;
    }

    protected function findBy(string $column, $value): ?array
    {
        $this->logger->debug("Finding {$this->table} by column", [
            'column' => $column,
            'value' => $this->sanitizeLogValue($column, $value)
        ]);
        
        $stmt = $this->database->query(
            "SELECT * FROM {$this->table} WHERE {$column} = ?",
            [$value]
        );
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->logger->debug("FindBy operation completed", [
            'table' => $this->table,
            'column' => $column,
            'found' => $result !== false
        ]);
        
        return $result ?: null;
    }

    protected function findAll(array $conditions = [], string $orderBy = 'id ASC', int $limit = 0, int $offset = 0): array
    {
        $this->logger->debug("Finding all {$this->table} records", [
            'conditions' => $this->sanitizeLogData($conditions),
            'order_by' => $orderBy,
            'limit' => $limit,
            'offset' => $offset
        ]);

        $sql = "SELECT * FROM {$this->table}";
        $params = [];

        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $column => $value) {
                $whereClause[] = "{$column} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }

        $sql .= " ORDER BY {$orderBy}";

        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
            if ($offset > 0) {
                $sql .= " OFFSET {$offset}";
            }
        }

        $stmt = $this->database->query($sql, $params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->logger->debug("FindAll operation completed", [
            'table' => $this->table,
            'result_count' => count($results)
        ]);

        return $results;
    }

    protected function create(array $data): int
    {
        $this->logger->debug("Creating new {$this->table} record", [
            'data' => $this->sanitizeLogData($data)
        ]);

        // Add timestamps
        $now = date('c');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $this->database->query($sql, array_values($data));
        $id = (int)$this->database->lastInsertId();

        $this->logger->info("Created new {$this->table} record", [
            'id' => $id,
            'data' => $this->sanitizeLogData($data)
        ]);

        return $id;
    }

    protected function update(int $id, array $data): bool
    {
        $this->logger->debug("Updating {$this->table} record", [
            'id' => $id,
            'data' => $this->sanitizeLogData($data)
        ]);

        // Add updated timestamp
        $data['updated_at'] = date('c');

        $columns = array_keys($data);
        $setClause = array_map(fn($col) => "{$col} = ?", $columns);

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClause) . " WHERE id = ?";
        $params = array_merge(array_values($data), [$id]);

        $stmt = $this->database->query($sql, $params);
        $success = $stmt->rowCount() > 0;

        $this->logger->info("Updated {$this->table} record", [
            'id' => $id,
            'success' => $success,
            'affected_rows' => $stmt->rowCount()
        ]);

        return $success;
    }

    protected function delete(int $id): bool
    {
        $this->logger->debug("Deleting {$this->table} record", ['id' => $id]);

        $stmt = $this->database->query("DELETE FROM {$this->table} WHERE id = ?", [$id]);
        $success = $stmt->rowCount() > 0;

        $this->logger->info("Deleted {$this->table} record", [
            'id' => $id,
            'success' => $success,
            'affected_rows' => $stmt->rowCount()
        ]);

        return $success;
    }

    protected function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $params = [];

        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $column => $value) {
                $whereClause[] = "{$column} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }

        $stmt = $this->database->query($sql, $params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)$result['count'];
    }

    private function sanitizeLogValue(string $key, $value)
    {
        // Don't log sensitive data
        if (stripos($key, 'password') !== false ||
            stripos($key, 'token') !== false ||
            stripos($key, 'secret') !== false) {
            return '[REDACTED]';
        }
        return $value;
    }

    private function sanitizeLogData(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $sanitized[$key] = $this->sanitizeLogValue($key, $value);
        }
        return $sanitized;
    }
}
