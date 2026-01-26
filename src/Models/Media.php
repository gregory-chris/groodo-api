<?php
declare(strict_types=1);

namespace App\Models;

use App\Utils\Database;
use Psr\Log\LoggerInterface;

class Media extends BaseModel
{
    protected string $table = 'media';

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
        $this->logger->debug("Finding media by ID and user ID", [
            'media_id' => $id,
            'user_id' => $userId
        ]);
        
        $stmt = $this->database->query(
            "SELECT * FROM {$this->table} WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $this->logger->debug("FindByIdAndUserId operation completed", [
            'media_id' => $id,
            'user_id' => $userId,
            'found' => $result !== false
        ]);
        
        return $result ?: null;
    }

    /**
     * Find all media for a user
     */
    public function findAllByUserId(int $userId, int $limit = 1000, int $offset = 0): array
    {
        $this->logger->debug("Finding all media by user ID", [
            'user_id' => $userId,
            'limit' => $limit,
            'offset' => $offset
        ]);

        $sql = "SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY created_at DESC";
        $params = [$userId];

        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
            if ($offset > 0) {
                $sql .= " OFFSET {$offset}";
            }
        }

        $stmt = $this->database->query($sql, $params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->logger->debug("FindAllByUserId operation completed", [
            'user_id' => $userId,
            'result_count' => count($results)
        ]);

        return $results;
    }

    /**
     * Find media by task ID
     */
    public function findByTaskId(int $taskId, int $userId): array
    {
        $this->logger->debug("Finding media by task ID", [
            'task_id' => $taskId,
            'user_id' => $userId
        ]);

        $stmt = $this->database->query(
            "SELECT * FROM {$this->table} WHERE task_id = ? AND user_id = ? ORDER BY created_at DESC",
            [$taskId, $userId]
        );
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->logger->debug("FindByTaskId operation completed", [
            'task_id' => $taskId,
            'user_id' => $userId,
            'result_count' => count($results)
        ]);

        return $results;
    }

    /**
     * Find media by project ID
     */
    public function findByProjectId(int $projectId, int $userId): array
    {
        $this->logger->debug("Finding media by project ID", [
            'project_id' => $projectId,
            'user_id' => $userId
        ]);

        $stmt = $this->database->query(
            "SELECT * FROM {$this->table} WHERE project_id = ? AND user_id = ? ORDER BY created_at DESC",
            [$projectId, $userId]
        );
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->logger->debug("FindByProjectId operation completed", [
            'project_id' => $projectId,
            'user_id' => $userId,
            'result_count' => count($results)
        ]);

        return $results;
    }

    /**
     * Find media by document ID
     */
    public function findByDocumentId(int $documentId, int $userId): array
    {
        $this->logger->debug("Finding media by document ID", [
            'document_id' => $documentId,
            'user_id' => $userId
        ]);

        $stmt = $this->database->query(
            "SELECT * FROM {$this->table} WHERE document_id = ? AND user_id = ? ORDER BY created_at DESC",
            [$documentId, $userId]
        );
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->logger->debug("FindByDocumentId operation completed", [
            'document_id' => $documentId,
            'user_id' => $userId,
            'result_count' => count($results)
        ]);

        return $results;
    }

    /**
     * Find media by profile user ID (user profile images)
     */
    public function findByProfileUserId(int $profileUserId, int $userId): array
    {
        $this->logger->debug("Finding media by profile user ID", [
            'profile_user_id' => $profileUserId,
            'user_id' => $userId
        ]);

        $stmt = $this->database->query(
            "SELECT * FROM {$this->table} WHERE profile_user_id = ? AND user_id = ? ORDER BY created_at DESC",
            [$profileUserId, $userId]
        );
        
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->logger->debug("FindByProfileUserId operation completed", [
            'profile_user_id' => $profileUserId,
            'user_id' => $userId,
            'result_count' => count($results)
        ]);

        return $results;
    }

    /**
     * Create a new media record
     */
    public function createMedia(array $mediaData): int
    {
        $this->logger->info('Creating new media record', [
            'user_id' => $mediaData['user_id'],
            'original_filename' => $mediaData['original_filename'],
            'media_type' => $mediaData['media_type'],
            'task_id' => $mediaData['task_id'] ?? null,
            'project_id' => $mediaData['project_id'] ?? null,
            'document_id' => $mediaData['document_id'] ?? null,
            'profile_user_id' => $mediaData['profile_user_id'] ?? null
        ]);

        return $this->create([
            'user_id' => $mediaData['user_id'],
            'task_id' => $mediaData['task_id'] ?? null,
            'project_id' => $mediaData['project_id'] ?? null,
            'document_id' => $mediaData['document_id'] ?? null,
            'profile_user_id' => $mediaData['profile_user_id'] ?? null,
            'original_filename' => $mediaData['original_filename'],
            'stored_filename' => $mediaData['stored_filename'],
            'file_path' => $mediaData['file_path'],
            'preview_path' => $mediaData['preview_path'] ?? null,
            'mime_type' => $mediaData['mime_type'],
            'file_size' => $mediaData['file_size'],
            'media_type' => $mediaData['media_type'],
            'width' => $mediaData['width'] ?? null,
            'height' => $mediaData['height'] ?? null,
        ]);
    }

    /**
     * Delete a media record
     */
    public function deleteMedia(int $id, int $userId): bool
    {
        $this->logger->info('Attempting to delete media', [
            'media_id' => $id,
            'user_id' => $userId
        ]);

        // First check if media exists and belongs to user
        $media = $this->findByIdAndUserId($id, $userId);
        if ($media === null) {
            $this->logger->warning('Media not found for deletion', [
                'media_id' => $id,
                'user_id' => $userId
            ]);
            return false;
        }

        $stmt = $this->database->query(
            "DELETE FROM {$this->table} WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
        
        $success = $stmt->rowCount() > 0;

        $this->logger->info('Deleted media', [
            'media_id' => $id,
            'user_id' => $userId,
            'success' => $success
        ]);

        return $success;
    }

    /**
     * Format media record for API response
     */
    public function formatMediaForResponse(array $media): array
    {
        return [
            'id' => (int)$media['id'],
            'userId' => (int)$media['user_id'],
            'taskId' => isset($media['task_id']) && $media['task_id'] !== null ? (int)$media['task_id'] : null,
            'projectId' => isset($media['project_id']) && $media['project_id'] !== null ? (int)$media['project_id'] : null,
            'documentId' => isset($media['document_id']) && $media['document_id'] !== null ? (int)$media['document_id'] : null,
            'profileUserId' => isset($media['profile_user_id']) && $media['profile_user_id'] !== null ? (int)$media['profile_user_id'] : null,
            'originalFilename' => $media['original_filename'],
            'filePath' => $media['file_path'],
            'previewPath' => $media['preview_path'],
            'mimeType' => $media['mime_type'],
            'fileSize' => (int)$media['file_size'],
            'mediaType' => $media['media_type'],
            'width' => isset($media['width']) && $media['width'] !== null ? (int)$media['width'] : null,
            'height' => isset($media['height']) && $media['height'] !== null ? (int)$media['height'] : null,
            'createdAt' => $media['created_at'],
            'updatedAt' => $media['updated_at'],
        ];
    }

    /**
     * Format media record for list response (minimal data)
     */
    public function formatMediaListItem(array $media): array
    {
        return [
            'id' => (int)$media['id'],
            'originalFilename' => $media['original_filename'],
            'filePath' => $media['file_path'],
            'previewPath' => $media['preview_path'],
            'mimeType' => $media['mime_type'],
            'mediaType' => $media['media_type'],
            'createdAt' => $media['created_at'],
        ];
    }
}
