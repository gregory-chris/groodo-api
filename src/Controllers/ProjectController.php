<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Services\ValidationService;
use App\Utils\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class ProjectController
{
    private Project $projectModel;
    private Task $taskModel;
    private ValidationService $validationService;
    private ResponseHelper $responseHelper;
    private LoggerInterface $logger;

    public function __construct(
        Project $projectModel,
        Task $taskModel,
        ValidationService $validationService,
        ResponseHelper $responseHelper,
        LoggerInterface $logger
    ) {
        $this->projectModel = $projectModel;
        $this->taskModel = $taskModel;
        $this->validationService = $validationService;
        $this->responseHelper = $responseHelper;
        $this->logger = $logger;
    }

    public function getProjects(Request $request, Response $response): Response
    {
        $this->logger->info('Get projects request started');

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

            // Get projects
            $projects = $this->projectModel->findByUserId($userId, $limit, $offset);

            // Format projects for response
            $formattedProjects = array_map(
                fn($project) => $this->projectModel->formatProjectForResponse($project),
                $projects
            );

            $this->logger->info('Projects retrieved successfully', [
                'user_id' => $userId,
                'count' => count($formattedProjects)
            ]);

            return $this->responseHelper->success($formattedProjects);

        } catch (\Exception $e) {
            $this->logger->error('Get projects failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to retrieve projects');
        }
    }

    public function createProject(Request $request, Response $response): Response
    {
        $this->logger->info('Create project request started');

        try {
            $userId = $request->getAttribute('user_id');
            $data = $request->getParsedBody();

            // Validate input data
            $validation = $this->validationService->validateProjectCreation($data);
            if (!$validation['valid']) {
                return $this->responseHelper->validationError($validation['errors']);
            }

            // Create project
            $projectId = $this->projectModel->createProject([
                'user_id' => $userId,
                'name' => $this->validationService->sanitizeInput($data['name']),
                'description' => isset($data['description']) ? $this->validationService->sanitizeInput($data['description']) : null,
                'url' => isset($data['url']) ? $this->validationService->sanitizeInput($data['url']) : null,
                'github_url' => isset($data['githubUrl']) ? $this->validationService->sanitizeInput($data['githubUrl']) : null,
                'color' => $data['color'] ?? null,
                'custom_fields' => $data['customFields'] ?? null,
            ]);

            // Get created project
            $project = $this->projectModel->findByIdAndUserId($projectId, $userId);
            $formattedProject = $this->projectModel->formatProjectForResponse($project);

            $this->logger->info('Project created successfully', [
                'user_id' => $userId,
                'project_id' => $projectId,
                'name' => $data['name']
            ]);

            return $this->responseHelper->created($formattedProject);

        } catch (\Exception $e) {
            $this->logger->error('Create project failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to create project');
        }
    }

    public function getProject(Request $request, Response $response): Response
    {
        $this->logger->info('Get single project request started');

        try {
            $userId = $request->getAttribute('user_id');
            $projectIdParam = $request->getAttribute('projectId');

            // Validate project ID (may be string like "01")
            if (!$this->validationService->isValidId($projectIdParam)) {
                $this->logger->warning('Invalid project ID provided', ['project_id' => $projectIdParam]);
                return $this->responseHelper->error('Invalid project ID', 400);
            }

            // Convert to integer for database operations
            $projectId = (int)$projectIdParam;

            // Find project by ID and user ID
            $project = $this->projectModel->findByIdAndUserId($projectId, $userId);

            if ($project === null) {
                $this->logger->warning('Project not found or access denied', [
                    'project_id' => $projectId,
                    'user_id' => $userId
                ]);
                return $this->responseHelper->notFound('Project not found');
            }

            // Format project for response
            $formattedProject = $this->projectModel->formatProjectForResponse($project);

            $this->logger->info('Project retrieved successfully', [
                'project_id' => $projectId,
                'user_id' => $userId
            ]);

            return $this->responseHelper->success($formattedProject);

        } catch (\Exception $e) {
            $this->logger->error('Get project failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to retrieve project');
        }
    }

    public function updateProject(Request $request, Response $response): Response
    {
        $this->logger->info('Update project request started');

        try {
            $userId = $request->getAttribute('user_id');
            $projectId = (int)$request->getAttribute('projectId');
            $data = $request->getParsedBody();

            // Validate project ID
            if (!$this->validationService->isValidId((string)$projectId)) {
                $this->logger->warning('Invalid project ID provided', ['project_id' => $projectId]);
                return $this->responseHelper->error('Invalid project ID', 400);
            }

            // Find project by ID and user ID
            $existingProject = $this->projectModel->findByIdAndUserId($projectId, $userId);

            if ($existingProject === null) {
                $this->logger->warning('Project not found or access denied for update', [
                    'project_id' => $projectId,
                    'user_id' => $userId
                ]);
                return $this->responseHelper->notFound('Project not found');
            }

            // Validate input data
            $validation = $this->validationService->validateProjectUpdate($data);
            if (!$validation['valid']) {
                return $this->responseHelper->validationError($validation['errors']);
            }

            // Prepare update data
            $updateData = [];
            
            if (isset($data['name'])) {
                $updateData['name'] = $this->validationService->sanitizeInput($data['name']);
            }
            
            if (isset($data['description'])) {
                $updateData['description'] = $this->validationService->sanitizeInput($data['description']);
            }
            
            if (isset($data['url'])) {
                $updateData['url'] = $this->validationService->sanitizeInput($data['url']);
            }
            
            if (isset($data['githubUrl'])) {
                $updateData['github_url'] = $this->validationService->sanitizeInput($data['githubUrl']);
            }
            
            if (isset($data['color'])) {
                $updateData['color'] = $data['color'];
            }
            
            if (isset($data['customFields'])) {
                $updateData['custom_fields'] = $data['customFields'];
            }

            // Update project
            $success = $this->projectModel->updateProject($projectId, $userId, $updateData);

            if (!$success) {
                $this->logger->error('Failed to update project in database', [
                    'project_id' => $projectId,
                    'user_id' => $userId
                ]);
                return $this->responseHelper->internalError('Failed to update project');
            }

            // Get updated project
            $updatedProject = $this->projectModel->findByIdAndUserId($projectId, $userId);
            $formattedProject = $this->projectModel->formatProjectForResponse($updatedProject);

            $this->logger->info('Project updated successfully', [
                'project_id' => $projectId,
                'user_id' => $userId,
                'updated_fields' => array_keys($updateData)
            ]);

            return $this->responseHelper->success($formattedProject);

        } catch (\Exception $e) {
            $this->logger->error('Update project failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to update project');
        }
    }

    public function deleteProject(Request $request, Response $response): Response
    {
        $this->logger->info('Delete project request started');

        try {
            $userId = $request->getAttribute('user_id');
            $projectId = (int)$request->getAttribute('projectId');

            // Validate project ID
            if (!$this->validationService->isValidId((string)$projectId)) {
                $this->logger->warning('Invalid project ID provided', ['project_id' => $projectId]);
                return $this->responseHelper->error('Invalid project ID', 400);
            }

            // Find project by ID and user ID to ensure it exists and belongs to user
            $existingProject = $this->projectModel->findByIdAndUserId($projectId, $userId);

            if ($existingProject === null) {
                $this->logger->warning('Project not found or access denied for deletion', [
                    'project_id' => $projectId,
                    'user_id' => $userId
                ]);
                return $this->responseHelper->notFound('Project not found');
            }

            // Delete project (cascade delete will handle tasks via foreign key)
            $success = $this->projectModel->deleteProject($projectId, $userId);

            if (!$success) {
                $this->logger->error('Failed to delete project from database', [
                    'project_id' => $projectId,
                    'user_id' => $userId
                ]);
                return $this->responseHelper->internalError('Failed to delete project');
            }

            $this->logger->info('Project deleted successfully', [
                'project_id' => $projectId,
                'user_id' => $userId,
                'project_name' => $existingProject['name']
            ]);

            return $this->responseHelper->success([
                'message' => 'Project deleted successfully',
                'deletedProject' => [
                    'id' => $projectId,
                    'name' => $existingProject['name']
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Delete project failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to delete project');
        }
    }

    public function getProjectTasks(Request $request, Response $response): Response
    {
        $this->logger->info('Get project tasks request started');

        try {
            $userId = $request->getAttribute('user_id');
            $projectId = (int)$request->getAttribute('projectId');
            $queryParams = $request->getQueryParams();

            // Validate project ID
            if (!$this->validationService->isValidId((string)$projectId)) {
                $this->logger->warning('Invalid project ID provided', ['project_id' => $projectId]);
                return $this->responseHelper->error('Invalid project ID', 400);
            }

            // Verify project belongs to user
            $project = $this->projectModel->findByIdAndUserId($projectId, $userId);
            if ($project === null) {
                return $this->responseHelper->notFound('Project not found');
            }

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

            // Get tasks for this project
            $tasks = $this->taskModel->findByProjectId($projectId, $userId, $limit, $offset);

            // Format tasks for response
            $formattedTasks = array_map(
                fn($task) => $this->taskModel->formatTaskForResponse($task),
                $tasks
            );

            $this->logger->info('Project tasks retrieved successfully', [
                'user_id' => $userId,
                'project_id' => $projectId,
                'count' => count($formattedTasks)
            ]);

            return $this->responseHelper->success($formattedTasks);

        } catch (\Exception $e) {
            $this->logger->error('Get project tasks failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to retrieve project tasks');
        }
    }
}

