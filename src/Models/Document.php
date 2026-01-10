<?php
declare(strict_types=1);

namespace App\Models;

use App\Utils\Database;
use Psr\Log\LoggerInterface;

class Document extends BaseModel
{
    protected string $table = 'documents';

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
        $this->logger->debug("Finding document by ID and user ID", [
            'document_id' => $id,
            'user_id' => $userId
        ]);
        
        $stmt = $this->database->query(
            "SELECT * FROM {$this->table} WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $this->logger->debug("FindByIdAndUserId operation completed", [
            'document_id' => $id,
            'user_id' => $userId,
            'found' => $result !== false
        ]);
        
        return $result ?: null;
    }

    /**
     * Find all documents for a user (regardless of parent)
     */
    public function findAllByUserId(int $userId, int $limit = 1000, int $offset = 0): array
    {
        $this->logger->debug("Finding all documents by user ID", [
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
     * Find documents by user ID with optional parent filter
     * When parentId is null, returns root documents (no parent)
     */
    public function findByUserId(int $userId, ?int $parentId = null, int $limit = 1000, int $offset = 0): array
    {
        $this->logger->debug("Finding documents by user ID", [
            'user_id' => $userId,
            'parent_id' => $parentId,
            'limit' => $limit,
            'offset' => $offset
        ]);

        if ($parentId === null) {
            // Get root documents (no parent)
            $sql = "SELECT * FROM {$this->table} WHERE user_id = ? AND parent_id IS NULL ORDER BY created_at DESC";
            $params = [$userId];
        } else {
            // Get documents with specific parent
            $sql = "SELECT * FROM {$this->table} WHERE user_id = ? AND parent_id = ? ORDER BY created_at DESC";
            $params = [$userId, $parentId];
        }

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
            'parent_id' => $parentId,
            'result_count' => count($results)
        ]);

        return $results;
    }

    public function findByParentId(int $parentId, int $userId): array
    {
        $this->logger->debug("Finding documents by parent ID", [
            'parent_id' => $parentId,
            'user_id' => $userId
        ]);

        $stmt = $this->database->query(
            "SELECT * FROM {$this->table} WHERE parent_id = ? AND user_id = ? ORDER BY created_at DESC",
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

    /**
     * Get the depth of a document in the hierarchy
     * Root documents have depth 0, their children have depth 1, etc.
     */
    public function getDocumentDepth(int $documentId, int $userId, int $maxDepth = 5): int
    {
        $document = $this->findByIdAndUserId($documentId, $userId);
        if ($document === null || $document['parent_id'] === null) {
            return 0;
        }

        $depth = 0;
        $currentDocument = $document;
        
        while ($currentDocument !== null && $currentDocument['parent_id'] !== null && $depth < $maxDepth) {
            $depth++;
            $currentDocument = $this->findByIdAndUserId((int)$currentDocument['parent_id'], $userId);
            
            if ($depth >= $maxDepth) {
                break;
            }
        }

        return $depth;
    }

    /**
     * Validate that adding a child to the given parent won't exceed max nesting depth
     * Returns true if nesting is allowed, false if it would exceed the limit
     */
    public function validateNestingDepth(int $parentId, int $userId, int $maxDepth = 5): bool
    {
        $parentDocument = $this->findByIdAndUserId($parentId, $userId);
        if ($parentDocument === null) {
            return false;
        }

        // Get parent's depth and check if we can add one more level
        $parentDepth = $this->getDocumentDepth($parentId, $userId, $maxDepth);
        
        // If parent is at depth maxDepth-1, we can still add a child (child will be at maxDepth-1)
        // But if parent is already at maxDepth-1, adding a child would make it depth maxDepth which exceeds limit
        // So we allow if parentDepth < maxDepth - 1 (to leave room for one more level)
        return $parentDepth < $maxDepth - 1;
    }

    /**
     * Check if a document has any children
     */
    public function hasChildren(int $documentId, int $userId): bool
    {
        $this->logger->debug("Checking if document has children", [
            'document_id' => $documentId,
            'user_id' => $userId
        ]);

        $stmt = $this->database->query(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE parent_id = ? AND user_id = ?",
            [$documentId, $userId]
        );
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $hasChildren = (int)$result['count'] > 0;

        $this->logger->debug("HasChildren check completed", [
            'document_id' => $documentId,
            'user_id' => $userId,
            'has_children' => $hasChildren,
            'child_count' => (int)$result['count']
        ]);

        return $hasChildren;
    }

    /**
     * Get count of children for a document
     */
    public function getChildrenCount(int $documentId, int $userId): int
    {
        $stmt = $this->database->query(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE parent_id = ? AND user_id = ?",
            [$documentId, $userId]
        );
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    public function createDocument(array $documentData): int
    {
        $this->logger->info('Creating new document', [
            'user_id' => $documentData['user_id'],
            'title' => $documentData['title'],
            'parent_id' => $documentData['parent_id'] ?? null
        ]);

        return $this->create([
            'user_id' => $documentData['user_id'],
            'parent_id' => $documentData['parent_id'] ?? null,
            'title' => $documentData['title'],
            'content' => $documentData['content'] ?? null,
        ]);
    }

    public function updateDocument(int $id, int $userId, array $documentData): bool
    {
        $this->logger->info('Updating document', [
            'document_id' => $id,
            'user_id' => $userId
        ]);

        // Ensure we only update documents belonging to the user
        $sql = "UPDATE {$this->table} SET ";
        $setClauses = [];
        $params = [];

        foreach ($documentData as $column => $value) {
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

        $this->logger->info('Updated document', [
            'document_id' => $id,
            'user_id' => $userId,
            'success' => $success,
            'affected_rows' => $stmt->rowCount()
        ]);

        return $success;
    }

    /**
     * Delete a document
     * Returns false if document has children (deletion not allowed)
     */
    public function deleteDocument(int $id, int $userId): bool
    {
        $this->logger->info('Attempting to delete document', [
            'document_id' => $id,
            'user_id' => $userId
        ]);

        // First check if document exists and belongs to user
        $document = $this->findByIdAndUserId($id, $userId);
        if ($document === null) {
            $this->logger->warning('Document not found for deletion', [
                'document_id' => $id,
                'user_id' => $userId
            ]);
            return false;
        }

        // Check if document has children - prevent deletion if so
        if ($this->hasChildren($id, $userId)) {
            $this->logger->warning('Cannot delete document with children', [
                'document_id' => $id,
                'user_id' => $userId,
                'child_count' => $this->getChildrenCount($id, $userId)
            ]);
            return false;
        }

        $stmt = $this->database->query(
            "DELETE FROM {$this->table} WHERE id = ? AND user_id = ?",
            [$id, $userId]
        );
        
        $success = $stmt->rowCount() > 0;

        $this->logger->info('Deleted document', [
            'document_id' => $id,
            'user_id' => $userId,
            'success' => $success
        ]);

        return $success;
    }

    public function formatDocumentForResponse(array $document): array
    {
        return [
            'id' => (int)$document['id'],
            'userId' => (int)$document['user_id'],
            'parentId' => isset($document['parent_id']) && $document['parent_id'] !== null ? (int)$document['parent_id'] : null,
            'title' => $document['title'],
            'content' => $document['content'],
            'createdAt' => $document['created_at'],
            'updatedAt' => $document['updated_at'],
        ];
    }

    /**
     * Format document for list response (without content/body)
     */
    public function formatDocumentListItem(array $document): array
    {
        return [
            'id' => (int)$document['id'],
            'userId' => (int)$document['user_id'],
            'parentId' => isset($document['parent_id']) && $document['parent_id'] !== null ? (int)$document['parent_id'] : null,
            'title' => $document['title'],
            'createdAt' => $document['created_at'],
            'updatedAt' => $document['updated_at'],
        ];
    }
}


