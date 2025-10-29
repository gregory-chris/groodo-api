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

    public function findByUserId(int $userId, ?string $fromDate = null, ?string $untilDate = null, ?int $projectId = null, int $limit = 100, int $offset = 0): array
    {
        $this->logger->debug("Finding tasks by user ID", [
            'user_id' => $userId,
            'from_date' => $fromDate,
            'until_date' => $untilDate,
            'project_id' => $projectId,
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

        if ($projectId !== null) {
            $sql .= " AND project_id = ?";
            $params[] = $projectId;
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

    public function findByProjectId(int $projectId, int $userId, int $limit = 100, int $offset = 0): array
    {
        $this->logger->debug("Finding tasks by project ID", [
            'project_id' => $projectId,
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);

        $sql = "SELECT * FROM {$this->table} WHERE project_id = ? AND user_id = ? AND parent_id IS NULL ORDER BY order_index ASC";
        
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
            if ($offset > 0) {
                $sql .= " OFFSET {$offset}";
            }
        }

        $stmt = $this->database->query($sql, [$projectId, $userId]);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->logger->debug("FindByProjectId operation completed", [
            'project_id' => $projectId,
            'user_id' => $userId,
            'result_count' => count($results)
        ]);

        return $results;
    }

    public function findByParentId(int $parentId, int $userId): array
    {
        $this->logger->debug("Finding tasks by parent ID", [
            'parent_id' => $parentId,
            'user_id' => $userId
        ]);

        $stmt = $this->database->query(
            "SELECT * FROM {$this->table} WHERE parent_id = ? AND user_id = ? ORDER BY order_index ASC",
            [$parentId, $userId]
        );
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->logger->debug("FindByParentId operation completed", [
            'parent_id' => $parentId,
            'user_id' => $userId,
            'result_count' => count($results)
        ]);

        return $results;
    }

    public function getParentTask(int $taskId, int $userId): ?array
    {
        $task = $this->findByIdAndUserId($taskId, $userId);
        if ($task === null || $task['parent_id'] === null) {
            return null;
        }

        return $this->findByIdAndUserId((int)$task['parent_id'], $userId);
    }

    public function getTaskDepth(int $taskId, int $userId, int $maxDepth = 2): int
    {
        $task = $this->findByIdAndUserId($taskId, $userId);
        if ($task === null || $task['parent_id'] === null) {
            return 0;
        }

        $depth = 0;
        $currentTask = $task;
        
        while ($currentTask !== null && $currentTask['parent_id'] !== null && $depth < $maxDepth) {
            $depth++;
            $currentTask = $this->findByIdAndUserId((int)$currentTask['parent_id'], $userId);
            
            if ($depth >= $maxDepth) {
                break;
            }
        }

        return $depth;
    }

    public function validateNestingDepth(int $parentId, int $userId, int $maxDepth = 2): bool
    {
        $parentTask = $this->findByIdAndUserId($parentId, $userId);
        if ($parentTask === null) {
            return false;
        }

        $parentDepth = $this->getTaskDepth($parentId, $userId, $maxDepth);
        return $parentDepth < $maxDepth;
    }

    public function validateParentProjectMatch(int $taskId, int $parentId, int $userId): bool
    {
        $task = $this->findByIdAndUserId($taskId, $userId);
        $parentTask = $this->findByIdAndUserId($parentId, $userId);

        if ($task === null || $parentTask === null) {
            return false;
        }

        // Parent must have a project_id
        if ($parentTask['project_id'] === null) {
            return false;
        }

        // Child's project_id must match parent's project_id
        return $task['project_id'] === $parentTask['project_id'];
    }

    public function createTask(array $taskData): int
    {
        $this->logger->info('Creating new task', [
            'user_id' => $taskData['user_id'],
            'title' => $taskData['title'],
            'date' => $taskData['date'],
            'project_id' => $taskData['project_id'] ?? null,
            'parent_id' => $taskData['parent_id'] ?? null
        ]);

        $projectId = $taskData['project_id'] ?? null;
        $parentId = $taskData['parent_id'] ?? null;

        // If parent_id is provided, inherit project_id from parent
        if ($parentId !== null) {
            $parentTask = $this->findByIdAndUserId($parentId, $taskData['user_id']);
            if ($parentTask === null || $parentTask['project_id'] === null) {
                throw new \RuntimeException('Parent task not found or does not belong to a project');
            }
            $projectId = $parentTask['project_id'];
        }

        // Get the next order index for this date and project
        $orderIndex = $this->getNextOrderIndex($taskData['user_id'], $taskData['date'], $projectId);

        return $this->create([
            'user_id' => $taskData['user_id'],
            'title' => $taskData['title'],
            'description' => $taskData['description'] ?? '',
            'date' => $taskData['date'],
            'order_index' => $orderIndex,
            'completed' => $taskData['completed'] ?? 0,
            'project_id' => $projectId,
            'parent_id' => $parentId,
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
            // Reorder remaining tasks for that date and project
            $projectId = $task['project_id'] !== null ? (int)$task['project_id'] : null;
            $this->reorderTasksAfterDeletion($userId, $task['date'], $task['order_index'], $projectId);
        }

        $this->logger->info('Deleted task', [
            'task_id' => $id,
            'user_id' => $userId,
            'success' => $success
        ]);

        return $success;
    }

    public function updateTaskOrder(int $id, int $userId, string $newDate, ?int $afterTaskId = null, ?int $projectId = null): bool
    {
        $this->logger->info('Updating task order', [
            'task_id' => $id,
            'user_id' => $userId,
            'new_date' => $newDate,
            'after_task_id' => $afterTaskId,
            'project_id' => $projectId
        ]);

        $task = $this->findByIdAndUserId($id, $userId);
        if ($task === null) {
            return false;
        }

        $oldDate = $task['date'];
        $oldOrderIndex = $task['order_index'];
        $oldProjectId = $task['project_id'] !== null ? (int)$task['project_id'] : null;

        // Use provided projectId or task's current projectId
        $targetProjectId = $projectId ?? $oldProjectId;

        try {
            $this->database->beginTransaction();

            if ($afterTaskId === null) {
                // Move to first position
                $newOrderIndex = 1;
                $this->shiftTasksDown($userId, $newDate, $newOrderIndex, $targetProjectId);
            } else {
                // Move after specific task
                $afterTask = $this->findByIdAndUserId($afterTaskId, $userId);
                if ($afterTask === null || $afterTask['date'] !== $newDate) {
                    $this->database->rollback();
                    return false;
                }
                
                $newOrderIndex = $afterTask['order_index'] + 1;
                $this->shiftTasksDown($userId, $newDate, $newOrderIndex, $targetProjectId);
            }

            // Update the task
            $this->database->query(
                "UPDATE {$this->table} SET date = ?, order_index = ?, updated_at = ? WHERE id = ? AND user_id = ?",
                [$newDate, $newOrderIndex, date('c'), $id, $userId]
            );

            // If moved from different date or project, reorder old location
            if ($oldDate !== $newDate || $oldProjectId !== $targetProjectId) {
                $this->reorderTasksAfterDeletion($userId, $oldDate, $oldOrderIndex, $oldProjectId);
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
            'projectId' => isset($task['project_id']) && $task['project_id'] !== null ? (int)$task['project_id'] : null,
            'parentId' => isset($task['parent_id']) && $task['parent_id'] !== null ? (int)$task['parent_id'] : null,
            'createdAt' => $task['created_at'],
            'updatedAt' => $task['updated_at'],
        ];
    }

    private function getNextOrderIndex(int $userId, string $date, ?int $projectId = null): int
    {
        $sql = "SELECT MAX(order_index) as max_order FROM {$this->table} WHERE user_id = ? AND date = ?";
        $params = [$userId, $date];

        if ($projectId !== null) {
            $sql .= " AND project_id = ?";
            $params[] = $projectId;
        } else {
            $sql .= " AND project_id IS NULL";
        }

        $stmt = $this->database->query($sql, $params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return ($result['max_order'] ?? 0) + 1;
    }

    public function updateChildrenProjectId(int $taskId, int $userId, ?int $newProjectId): void
    {
        $this->logger->info('Updating children project_id', [
            'task_id' => $taskId,
            'user_id' => $userId,
            'new_project_id' => $newProjectId
        ]);

        $this->database->query(
            "UPDATE {$this->table} SET project_id = ?, updated_at = ? WHERE parent_id = ? AND user_id = ?",
            [$newProjectId, date('c'), $taskId, $userId]
        );
    }

    private function shiftTasksDown(int $userId, string $date, int $fromOrderIndex, ?int $projectId = null): void
    {
        $sql = "UPDATE {$this->table} SET order_index = order_index + 1 WHERE user_id = ? AND date = ? AND order_index >= ?";
        $params = [$userId, $date, $fromOrderIndex];

        if ($projectId !== null) {
            $sql .= " AND project_id = ?";
            $params[] = $projectId;
        } else {
            $sql .= " AND project_id IS NULL";
        }

        $this->database->query($sql, $params);
    }

    private function reorderTasksAfterDeletion(int $userId, string $date, int $deletedOrderIndex, ?int $projectId = null): void
    {
        $sql = "UPDATE {$this->table} SET order_index = order_index - 1 WHERE user_id = ? AND date = ? AND order_index > ?";
        $params = [$userId, $date, $deletedOrderIndex];

        if ($projectId !== null) {
            $sql .= " AND project_id = ?";
            $params[] = $projectId;
        } else {
            $sql .= " AND project_id IS NULL";
        }

        $this->database->query($sql, $params);
    }
}
