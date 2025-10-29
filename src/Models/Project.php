<?php
declare(strict_types=1);

namespace App\Models;

use App\Utils\Database;
use Psr\Log\LoggerInterface;

class Project extends BaseModel
{
    protected string $table = 'projects';

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
        $this->logger->debug("Finding project by ID and user ID", [
            'project_id' => $id,
            'user_id' => $userId
        ]);
        
        $stmt = $this->database->query(
            "SELECT * FROM {$this->table} WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $this->logger->debug("FindByIdAndUserId operation completed", [
            'project_id' => $id,
            'user_id' => $userId,
            'found' => $result !== false
        ]);
        
        return $result ?: null;
    }

    public function findByUserId(int $userId, int $limit = 100, int $offset = 0): array
    {
        $this->logger->debug("Finding projects by user ID", [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);

        $sql = "SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY created_at DESC";
        
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
            if ($offset > 0) {
                $sql .= " OFFSET {$offset}";
            }
        }

        $stmt = $this->database->query($sql, [$userId]);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->logger->debug("FindByUserId operation completed", [
            'user_id' => $userId,
            'result_count' => count($results)
        ]);

        return $results;
    }

    public function createProject(array $projectData): int
    {
        $this->logger->info('Creating new project', [
            'user_id' => $projectData['user_id'],
            'name' => $projectData['name']
        ]);

        return $this->create([
            'user_id' => $projectData['user_id'],
            'name' => $projectData['name'],
            'description' => $projectData['description'] ?? null,
            'url' => $projectData['url'] ?? null,
            'github_url' => $projectData['github_url'] ?? null,
            'color' => $projectData['color'] ?? null,
            'custom_fields' => isset($projectData['custom_fields']) ? json_encode($projectData['custom_fields']) : null,
        ]);
    }

    public function updateProject(int $id, int $userId, array $projectData): bool
    {
        $this->logger->info('Updating project', [
            'project_id' => $id,
            'user_id' => $userId
        ]);

        // Ensure we only update projects belonging to the user
        $sql = "UPDATE {$this->table} SET ";
        $setClauses = [];
        $params = [];

        foreach ($projectData as $column => $value) {
            if ($column !== 'id' && $column !== 'user_id') {
                if ($column === 'custom_fields' && is_array($value)) {
                    $setClauses[] = "{$column} = ?";
                    $params[] = json_encode($value);
                } else {
                    $setClauses[] = "{$column} = ?";
                    $params[] = $value;
                }
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

        $this->logger->info('Updated project', [
            'project_id' => $id,
            'user_id' => $userId,
            'success' => $success,
            'affected_rows' => $stmt->rowCount()
        ]);

        return $success;
    }

    public function deleteProject(int $id, int $userId): bool
    {
        $this->logger->info('Deleting project', [
            'project_id' => $id,
            'user_id' => $userId
        ]);

        // Get project info before deletion
        $project = $this->findByIdAndUserId($id, $userId);
        if ($project === null) {
            return false;
        }

        // Delete project (cascade delete will handle tasks via foreign key)
        $stmt = $this->database->query(
            "DELETE FROM {$this->table} WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
        
        $success = $stmt->rowCount() > 0;

        $this->logger->info('Deleted project', [
            'project_id' => $id,
            'user_id' => $userId,
            'success' => $success
        ]);

        return $success;
    }

    public function getProjectTasksCount(int $projectId, int $userId): int
    {
        // First verify project belongs to user
        $project = $this->findByIdAndUserId($projectId, $userId);
        if ($project === null) {
            return 0;
        }

        return $this->count([
            'project_id' => $projectId
        ]);
    }

    public function formatProjectForResponse(array $project): array
    {
        $customFields = null;
        if (!empty($project['custom_fields'])) {
            $decoded = json_decode($project['custom_fields'], true);
            $customFields = $decoded !== null ? $decoded : null;
        }

        return [
            'id' => (int)$project['id'],
            'userId' => (int)$project['user_id'],
            'name' => $project['name'],
            'description' => $project['description'],
            'url' => $project['url'],
            'githubUrl' => $project['github_url'],
            'color' => $project['color'],
            'customFields' => $customFields,
            'createdAt' => $project['created_at'],
            'updatedAt' => $project['updated_at'],
        ];
    }
}

