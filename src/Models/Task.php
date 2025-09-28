<?php
declare(strict_types=1);

namespace App\Models;

use App\Utils\Database;
use Psr\Log\LoggerInterface;

class Task extends BaseModel
{
    protected string $table = 'tasks';

    public function __construct(Database $database, LoggerInterface $logger)
    {
        parent::__construct($database, $logger);
    }

    public function findById(int $id): ?array
    {
        return $this->find($id);
    }

    public function findByIdAndUserId(int $id, int $userId): ?array
    {
        $this->logger->debug("Finding task by ID and user ID", [
            'task_id' => $id,
            'user_id' => $userId
        ]);
        
        $stmt = $this->database->query(
            "SELECT * FROM {$this->table} WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $this->logger->debug("FindByIdAndUserId operation completed", [
            'task_id' => $id,
            'user_id' => $userId,
            'found' => $result !== false
        ]);
        
        return $result ?: null;
    }

    public function findByUserId(int $userId, ?string $fromDate = null, ?string $untilDate = null, int $limit = 100, int $offset = 0): array
    {
        $this->logger->debug("Finding tasks by user ID", [
            'user_id' => $userId,
            'from_date' => $fromDate,
            'until_date' => $untilDate,
            'limit' => $limit,
            'offset' => $offset
        ]);

        $sql = "SELECT * FROM {$this->table} WHERE user_id = ?";
        $params = [$userId];

        if ($fromDate !== null) {
            $sql .= " AND date >= ?";
            $params[] = $fromDate;
        }

        if ($untilDate !== null) {
            $sql .= " AND date <= ?";
            $params[] = $untilDate;
        }

        $sql .= " ORDER BY date ASC, order_index ASC";

        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
            if ($offset > 0) {
                $sql .= " OFFSET {$offset}";
            }
        }

        $stmt = $this->database->query($sql, $params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->logger->debug("FindByUserId operation completed", [
            'user_id' => $userId,
            'result_count' => count($results)
        ]);

        return $results;
    }

    public function createTask(array $taskData): int
    {
        $this->logger->info('Creating new task', [
            'user_id' => $taskData['user_id'],
            'title' => $taskData['title'],
            'date' => $taskData['date']
        ]);

        // Get the next order index for this date
        $orderIndex = $this->getNextOrderIndex($taskData['user_id'], $taskData['date']);

        return $this->create([
            'user_id' => $taskData['user_id'],
            'title' => $taskData['title'],
            'description' => $taskData['description'] ?? '',
            'date' => $taskData['date'],
            'order_index' => $orderIndex,
            'completed' => $taskData['completed'] ?? 0,
        ]);
    }

    public function updateTask(int $id, int $userId, array $taskData): bool
    {
        $this->logger->info('Updating task', [
            'task_id' => $id,
            'user_id' => $userId
        ]);

        // Ensure we only update tasks belonging to the user
        $sql = "UPDATE {$this->table} SET ";
        $setClauses = [];
        $params = [];

        foreach ($taskData as $column => $value) {
            if ($column !== 'id' && $column !== 'user_id') {
                $setClauses[] = "{$column} = ?";
                $params[] = $value;
            }
        }

        $setClauses[] = "updated_at = ?";
        $params[] = date('c');

        $sql .= implode(', ', $setClauses);
        $sql .= " WHERE id = ? AND user_id = ?";
        $params[] = $id;
        $params[] = $userId;

        $stmt = $this->database->query($sql, $params);
        $success = $stmt->rowCount() > 0;

        $this->logger->info('Updated task', [
            'task_id' => $id,
            'user_id' => $userId,
            'success' => $success,
            'affected_rows' => $stmt->rowCount()
        ]);

        return $success;
    }

    public function deleteTask(int $id, int $userId): bool
    {
        $this->logger->info('Deleting task', [
            'task_id' => $id,
            'user_id' => $userId
        ]);

        // Get task info before deletion for reordering
        $task = $this->findByIdAndUserId($id, $userId);
        if ($task === null) {
            return false;
        }

        $stmt = $this->database->query(
            "DELETE FROM {$this->table} WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
        
        $success = $stmt->rowCount() > 0;

        if ($success) {
            // Reorder remaining tasks for that date
            $this->reorderTasksAfterDeletion($userId, $task['date'], $task['order_index']);
        }

        $this->logger->info('Deleted task', [
            'task_id' => $id,
            'user_id' => $userId,
            'success' => $success
        ]);

        return $success;
    }

    public function updateTaskOrder(int $id, int $userId, string $newDate, ?int $afterTaskId = null): bool
    {
        $this->logger->info('Updating task order', [
            'task_id' => $id,
            'user_id' => $userId,
            'new_date' => $newDate,
            'after_task_id' => $afterTaskId
        ]);

        $task = $this->findByIdAndUserId($id, $userId);
        if ($task === null) {
            return false;
        }

        $oldDate = $task['date'];
        $oldOrderIndex = $task['order_index'];

        try {
            $this->database->beginTransaction();

            if ($afterTaskId === null) {
                // Move to first position
                $newOrderIndex = 1;
                $this->shiftTasksDown($userId, $newDate, $newOrderIndex);
            } else {
                // Move after specific task
                $afterTask = $this->findByIdAndUserId($afterTaskId, $userId);
                if ($afterTask === null || $afterTask['date'] !== $newDate) {
                    $this->database->rollback();
                    return false;
                }
                
                $newOrderIndex = $afterTask['order_index'] + 1;
                $this->shiftTasksDown($userId, $newDate, $newOrderIndex);
            }

            // Update the task
            $this->database->query(
                "UPDATE {$this->table} SET date = ?, order_index = ?, updated_at = ? WHERE id = ? AND user_id = ?",
                [$newDate, $newOrderIndex, date('c'), $id, $userId]
            );

            // If moved from different date, reorder old date
            if ($oldDate !== $newDate) {
                $this->reorderTasksAfterDeletion($userId, $oldDate, $oldOrderIndex);
            }

            $this->database->commit();

            $this->logger->info('Task order updated successfully', [
                'task_id' => $id,
                'old_date' => $oldDate,
                'new_date' => $newDate,
                'new_order_index' => $newOrderIndex
            ]);

            return true;
        } catch (\Exception $e) {
            $this->database->rollback();
            $this->logger->error('Failed to update task order', [
                'task_id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getTasksCountForDate(int $userId, string $date): int
    {
        return $this->count([
            'user_id' => $userId,
            'date' => $date
        ]);
    }

    public function formatTaskForResponse(array $task): array
    {
        return [
            'id' => (int)$task['id'],
            'userId' => (int)$task['user_id'],
            'title' => $task['title'],
            'description' => $task['description'],
            'date' => $task['date'],
            'order' => (int)$task['order_index'],
            'completed' => (bool)$task['completed'],
            'createdAt' => $task['created_at'],
            'updatedAt' => $task['updated_at'],
        ];
    }

    private function getNextOrderIndex(int $userId, string $date): int
    {
        $stmt = $this->database->query(
            "SELECT MAX(order_index) as max_order FROM {$this->table} WHERE user_id = ? AND date = ?",
            [$userId, $date]
        );
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return ($result['max_order'] ?? 0) + 1;
    }

    private function shiftTasksDown(int $userId, string $date, int $fromOrderIndex): void
    {
        $this->database->query(
            "UPDATE {$this->table} SET order_index = order_index + 1 WHERE user_id = ? AND date = ? AND order_index >= ?",
            [$userId, $date, $fromOrderIndex]
        );
    }

    private function reorderTasksAfterDeletion(int $userId, string $date, int $deletedOrderIndex): void
    {
        $this->database->query(
            "UPDATE {$this->table} SET order_index = order_index - 1 WHERE user_id = ? AND date = ? AND order_index > ?",
            [$userId, $date, $deletedOrderIndex]
        );
    }
}
