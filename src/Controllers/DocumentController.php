<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Document;
use App\Services\ValidationService;
use App\Utils\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class DocumentController
{
    private Document $documentModel;
    private ValidationService $validationService;
    private ResponseHelper $responseHelper;
    private LoggerInterface $logger;

    public function __construct(
        Document $documentModel,
        ValidationService $validationService,
        ResponseHelper $responseHelper,
        LoggerInterface $logger
    ) {
        $this->documentModel = $documentModel;
        $this->validationService = $validationService;
        $this->responseHelper = $responseHelper;
        $this->logger = $logger;
    }

    public function getDocuments(Request $request, Response $response): Response
    {
        $this->logger->info('Get documents request started');

        try {
            $userId = $request->getAttribute('user_id');
            $queryParams = $request->getQueryParams();

            // Validate pagination parameters
            $paginationValidation = $this->validationService->validatePaginationParams(
                $queryParams['limit'] ?? null,
                $queryParams['offset'] ?? null
            );

            if (!$paginationValidation['valid']) {
                return $this->responseHelper->validationError($paginationValidation['errors']);
            }

            $limit = $paginationValidation['limit'];
            $offset = $paginationValidation['offset'];

            // Check for parentId filter
            $parentId = null;
            if (isset($queryParams['parentId'])) {
                if ($queryParams['parentId'] === 'null' || $queryParams['parentId'] === '') {
                    // Explicitly requesting root documents
                    $parentId = null;
                } elseif (!$this->validationService->isValidId($queryParams['parentId'])) {
                    return $this->responseHelper->error('Invalid parent document ID', 400);
                } else {
                    $parentId = (int)$queryParams['parentId'];
                    
                    // Verify parent belongs to user
                    $parentDocument = $this->documentModel->findByIdAndUserId($parentId, $userId);
                    if ($parentDocument === null) {
                        return $this->responseHelper->error('Parent document not found', 400);
                    }
                }
            }

            // Get documents
            $documents = $this->documentModel->findByUserId($userId, $parentId, $limit, $offset);

            // Format documents for response
            $formattedDocuments = array_map(
                fn($document) => $this->documentModel->formatDocumentForResponse($document),
                $documents
            );

            $this->logger->info('Documents retrieved successfully', [
                'user_id' => $userId,
                'count' => count($formattedDocuments),
                'parent_id' => $parentId
            ]);

            return $this->responseHelper->success($formattedDocuments);

        } catch (\Exception $e) {
            $this->logger->error('Get documents failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to retrieve documents');
        }
    }

    public function createDocument(Request $request, Response $response): Response
    {
        $this->logger->info('Create document request started');

        try {
            $userId = $request->getAttribute('user_id');
            $data = $request->getParsedBody();

            // Validate input data
            $validation = $this->validationService->validateDocumentCreation($data);
            if (!$validation['valid']) {
                return $this->responseHelper->validationError($validation['errors']);
            }

            // Handle parent document if provided
            $parentId = isset($data['parentId']) && $data['parentId'] !== null ? (int)$data['parentId'] : null;

            if ($parentId !== null) {
                // Validate parent document
                $parentValidation = $this->validationService->validateParentDocumentId(
                    $parentId,
                    $userId,
                    $this->documentModel
                );
                
                if (!$parentValidation['valid']) {
                    return $this->responseHelper->validationError($parentValidation['errors']);
                }
            }

            // Create document
            $documentId = $this->documentModel->createDocument([
                'user_id' => $userId,
                'parent_id' => $parentId,
                'title' => $this->validationService->sanitizeInput($data['title']),
                'content' => isset($data['content']) ? $data['content'] : null,
            ]);

            // Get created document
            $document = $this->documentModel->findByIdAndUserId($documentId, $userId);
            $formattedDocument = $this->documentModel->formatDocumentForResponse($document);

            $this->logger->info('Document created successfully', [
                'user_id' => $userId,
                'document_id' => $documentId,
                'title' => $data['title'],
                'parent_id' => $parentId
            ]);

            return $this->responseHelper->created($formattedDocument);

        } catch (\Exception $e) {
            $this->logger->error('Create document failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to create document');
        }
    }

    public function getDocument(Request $request, Response $response): Response
    {
        $this->logger->info('Get single document request started');

        try {
            $userId = $request->getAttribute('user_id');
            $documentId = (int)$request->getAttribute('documentId');

            // Validate document ID
            if (!$this->validationService->isValidId((string)$documentId)) {
                $this->logger->warning('Invalid document ID provided', ['document_id' => $documentId]);
                return $this->responseHelper->error('Invalid document ID', 400);
            }

            // Find document by ID and user ID
            $document = $this->documentModel->findByIdAndUserId($documentId, $userId);

            if ($document === null) {
                $this->logger->warning('Document not found or access denied', [
                    'document_id' => $documentId,
                    'user_id' => $userId
                ]);
                return $this->responseHelper->notFound('Document not found');
            }

            // Format document for response
            $formattedDocument = $this->documentModel->formatDocumentForResponse($document);

            $this->logger->info('Document retrieved successfully', [
                'document_id' => $documentId,
                'user_id' => $userId
            ]);

            return $this->responseHelper->success($formattedDocument);

        } catch (\Exception $e) {
            $this->logger->error('Get document failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to retrieve document');
        }
    }

    public function updateDocument(Request $request, Response $response): Response
    {
        $this->logger->info('Update document request started');

        try {
            $userId = $request->getAttribute('user_id');
            $documentId = (int)$request->getAttribute('documentId');
            $data = $request->getParsedBody();

            // Validate document ID
            if (!$this->validationService->isValidId((string)$documentId)) {
                $this->logger->warning('Invalid document ID provided', ['document_id' => $documentId]);
                return $this->responseHelper->error('Invalid document ID', 400);
            }

            // Find document by ID and user ID
            $existingDocument = $this->documentModel->findByIdAndUserId($documentId, $userId);

            if ($existingDocument === null) {
                $this->logger->warning('Document not found or access denied for update', [
                    'document_id' => $documentId,
                    'user_id' => $userId
                ]);
                return $this->responseHelper->notFound('Document not found');
            }

            // Validate input data
            $validation = $this->validationService->validateDocumentUpdate($data);
            if (!$validation['valid']) {
                return $this->responseHelper->validationError($validation['errors']);
            }

            // Prepare update data
            $updateData = [];
            
            if (isset($data['title'])) {
                $updateData['title'] = $this->validationService->sanitizeInput($data['title']);
            }
            
            if (array_key_exists('content', $data)) {
                // Allow setting content to null or empty string
                $updateData['content'] = $data['content'];
            }

            // Handle parent reassignment
            if (array_key_exists('parentId', $data)) {
                $newParentId = $data['parentId'] !== null ? (int)$data['parentId'] : null;
                
                if ($newParentId !== null) {
                    // Validate parent document
                    $parentValidation = $this->validationService->validateParentDocumentId(
                        $newParentId,
                        $userId,
                        $this->documentModel,
                        $documentId // Pass current document ID to prevent self-reference
                    );
                    
                    if (!$parentValidation['valid']) {
                        return $this->responseHelper->validationError($parentValidation['errors']);
                    }
                    
                    // Additional check: prevent circular reference
                    // (a document's child cannot become its parent)
                    if ($this->wouldCreateCircularReference($documentId, $newParentId, $userId)) {
                        return $this->responseHelper->error('Cannot set a descendant as parent (circular reference)', 400);
                    }
                }
                
                $updateData['parent_id'] = $newParentId;
            }

            // Update document
            $success = $this->documentModel->updateDocument($documentId, $userId, $updateData);

            if (!$success) {
                $this->logger->error('Failed to update document in database', [
                    'document_id' => $documentId,
                    'user_id' => $userId
                ]);
                return $this->responseHelper->internalError('Failed to update document');
            }

            // Get updated document
            $updatedDocument = $this->documentModel->findByIdAndUserId($documentId, $userId);
            $formattedDocument = $this->documentModel->formatDocumentForResponse($updatedDocument);

            $this->logger->info('Document updated successfully', [
                'document_id' => $documentId,
                'user_id' => $userId,
                'updated_fields' => array_keys($updateData)
            ]);

            return $this->responseHelper->success($formattedDocument);

        } catch (\Exception $e) {
            $this->logger->error('Update document failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to update document');
        }
    }

    public function deleteDocument(Request $request, Response $response): Response
    {
        $this->logger->info('Delete document request started');

        try {
            $userId = $request->getAttribute('user_id');
            $documentId = (int)$request->getAttribute('documentId');

            // Validate document ID
            if (!$this->validationService->isValidId((string)$documentId)) {
                $this->logger->warning('Invalid document ID provided', ['document_id' => $documentId]);
                return $this->responseHelper->error('Invalid document ID', 400);
            }

            // Find document by ID and user ID to ensure it exists and belongs to user
            $existingDocument = $this->documentModel->findByIdAndUserId($documentId, $userId);

            if ($existingDocument === null) {
                $this->logger->warning('Document not found or access denied for deletion', [
                    'document_id' => $documentId,
                    'user_id' => $userId
                ]);
                return $this->responseHelper->notFound('Document not found');
            }

            // Check if document has children
            if ($this->documentModel->hasChildren($documentId, $userId)) {
                $childCount = $this->documentModel->getChildrenCount($documentId, $userId);
                $this->logger->warning('Cannot delete document with children', [
                    'document_id' => $documentId,
                    'user_id' => $userId,
                    'child_count' => $childCount
                ]);
                return $this->responseHelper->error(
                    "Cannot delete document with children. Please delete or move the {$childCount} child document(s) first.",
                    400
                );
            }

            // Delete document
            $success = $this->documentModel->deleteDocument($documentId, $userId);

            if (!$success) {
                $this->logger->error('Failed to delete document from database', [
                    'document_id' => $documentId,
                    'user_id' => $userId
                ]);
                return $this->responseHelper->internalError('Failed to delete document');
            }

            $this->logger->info('Document deleted successfully', [
                'document_id' => $documentId,
                'user_id' => $userId,
                'document_title' => $existingDocument['title']
            ]);

            return $this->responseHelper->success([
                'message' => 'Document deleted successfully',
                'deletedDocument' => [
                    'id' => $documentId,
                    'title' => $existingDocument['title']
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Delete document failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to delete document');
        }
    }

    /**
     * Check if setting newParentId as parent of documentId would create a circular reference
     * (i.e., if newParentId is a descendant of documentId)
     */
    private function wouldCreateCircularReference(int $documentId, int $newParentId, int $userId): bool
    {
        // Walk up from newParentId and see if we ever hit documentId
        $currentId = $newParentId;
        $maxIterations = 10; // Safety limit
        $iterations = 0;
        
        while ($currentId !== null && $iterations < $maxIterations) {
            if ($currentId === $documentId) {
                return true; // Found circular reference
            }
            
            $current = $this->documentModel->findByIdAndUserId($currentId, $userId);
            if ($current === null) {
                break;
            }
            
            $currentId = $current['parent_id'] !== null ? (int)$current['parent_id'] : null;
            $iterations++;
        }
        
        return false;
    }
}

