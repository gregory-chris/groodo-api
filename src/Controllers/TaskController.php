<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Task;
use App\Models\Project;
use App\Services\ValidationService;
use App\Utils\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class TaskController
{
    private Task $taskModel;
    private Project $projectModel;
    private ValidationService $validationService;
    private ResponseHelper $responseHelper;
    private LoggerInterface $logger;

    public function __construct(
        Task $taskModel,
        Project $projectModel,
        ValidationService $validationService,
        ResponseHelper $responseHelper,
        LoggerInterface $logger
    ) {
        $this->taskModel = $taskModel;
        $this->projectModel = $projectModel;
        $this->validationService = $validationService;
        $this->responseHelper = $responseHelper;
        $this->logger = $logger;
    }

    public function getTasks(Request $request, Response $response): Response
    {
        $this->logger->info('Get tasks request started');

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

            // Validate date filters
            $fromDate = null;
            $untilDate = null;

            if (isset($queryParams['from'])) {
                $fromValidation = $this->validationService->validateDate($queryParams['from']);
                if (!$fromValidation['valid']) {
                    return $this->responseHelper->validationError($fromValidation['errors']);
                }
                $fromDate = $queryParams['from'];
            }

            if (isset($queryParams['until'])) {
                $untilValidation = $this->validationService->validateDate($queryParams['until']);
                if (!$untilValidation['valid']) {
                    return $this->responseHelper->validationError($untilValidation['errors']);
                }
                $untilDate = $queryParams['until'];
            }

            // Validate project filter
            $projectId = null;
            if (isset($queryParams['projectId'])) {
                if (!$this->validationService->isValidId($queryParams['projectId'])) {
                    return $this->responseHelper->error('Invalid project ID', 400);
                }
                $projectId = (int)$queryParams['projectId'];
                
                // Verify project belongs to user
                $project = $this->projectModel->findByIdAndUserId($projectId, $userId);
                if ($project === null) {
                    return $this->responseHelper->error('Project not found', 400);
                }
            }

            // Get tasks
            $tasks = $this->taskModel->findByUserId($userId, $fromDate, $untilDate, $projectId, $limit, $offset);

            // Format tasks for response
            $formattedTasks = array_map(
                fn($task) => $this->taskModel->formatTaskForResponse($task),
                $tasks
            );

            $this->logger->info('Tasks retrieved successfully', [
                'user_id' => $userId,
                'count' => count($formattedTasks),
                'from_date' => $fromDate,
                'until_date' => $untilDate
            ]);

            return $this->responseHelper->success($formattedTasks);

        } catch (\Exception $e) {
            $this->logger->error('Get tasks failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to retrieve tasks');
        }
    }

    public function createTask(Request $request, Response $response): Response
    {
        $this->logger->info('Create task request started');

        try {
            $userId = $request->getAttribute('user_id');
            $data = $request->getParsedBody();

            // Validate project and parent if provided (needed for date validation logic)
            $projectId = isset($data['projectId']) && $data['projectId'] !== null ? (int)$data['projectId'] : null;
            $parentId = isset($data['parentId']) && $data['parentId'] !== null ? (int)$data['parentId'] : null;

            // Validate input data (validation checks if date is required based on projectId)
            $validation = $this->validationService->validateTaskCreation($data);
            if (!$validation['valid']) {
                return $this->responseHelper->validationError($validation['errors']);
            }

            // Date is optional when projectId is provided, required otherwise
            $date = isset($data['date']) && $data['date'] !== '' ? $data['date'] : null;

            // Check daily task limit only if date is provided
            if ($date !== null) {
                $currentCount = $this->taskModel->getTasksCountForDate($userId, $date);
                $limitValidation = $this->validationService->validateTasksPerDayLimit($currentCount);
                if (!$limitValidation['valid']) {
                    return $this->responseHelper->validationError($limitValidation['errors']);
                }
            }

            // Validate project and parent if provided

            if ($projectId !== null) {
                $projectValidation = $this->validationService->validateProjectId($projectId, $userId, $this->projectModel);
                if (!$projectValidation['valid']) {
                    return $this->responseHelper->validationError($projectValidation['errors']);
                }
            }

            if ($parentId !== null) {
                $parentValidation = $this->validationService->validateParentTaskId($parentId, $userId, $this->taskModel);
                if (!$parentValidation['valid']) {
                    return $this->responseHelper->validationError($parentValidation['errors']);
                }
            }

            // Create task
            $taskId = $this->taskModel->createTask([
                'user_id' => $userId,
                'title' => $this->validationService->sanitizeInput($data['title']),
                'description' => isset($data['description']) ? $this->validationService->sanitizeInput($data['description']) : '',
                'date' => $date, // Can be null if projectId is provided
                'completed' => $data['completed'] ?? false,
                'project_id' => $projectId,
                'parent_id' => $parentId,
            ]);

            // Get created task
            $task = $this->taskModel->findByIdAndUserId($taskId, $userId);
            $formattedTask = $this->taskModel->formatTaskForResponse($task);

            $this->logger->info('Task created successfully', [
                'user_id' => $userId,
                'task_id' => $taskId,
                'title' => $data['title'],
                'date' => $date
            ]);

            return $this->responseHelper->created($formattedTask);

        } catch (\Exception $e) {
            $this->logger->error('Create task failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to create task');
        }
    }

    public function getTask(Request $request, Response $response): Response
    {
        $this->logger->info('Get single task request started');

        try {
            $userId = $request->getAttribute('user_id');
            $taskId = (int)$request->getAttribute('taskId');

            // Validate task ID
            if (!$this->validationService->isValidId((string)$taskId)) {
                $this->logger->warning('Invalid task ID provided', ['task_id' => $taskId]);
                return $this->responseHelper->error('Invalid task ID', 400);
            }

            // Find task by ID and user ID
            $task = $this->taskModel->findByIdAndUserId($taskId, $userId);

            if ($task === null) {
                $this->logger->warning('Task not found or access denied', [
                    'task_id' => $taskId,
                    'user_id' => $userId
                ]);
                return $this->responseHelper->notFound('Task not found');
            }

            // Format task for response
            $formattedTask = $this->taskModel->formatTaskForResponse($task);

            $this->logger->info('Task retrieved successfully', [
                'task_id' => $taskId,
                'user_id' => $userId
            ]);

            return $this->responseHelper->success($formattedTask);

        } catch (\Exception $e) {
            $this->logger->error('Get task failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to retrieve task');
        }
    }

    public function updateTask(Request $request, Response $response): Response
    {
        $this->logger->info('Update task request started');

        try {
            $userId = $request->getAttribute('user_id');
            $taskId = (int)$request->getAttribute('taskId');
            $data = $request->getParsedBody();

            // Validate task ID
            if (!$this->validationService->isValidId((string)$taskId)) {
                $this->logger->warning('Invalid task ID provided', ['task_id' => $taskId]);
                return $this->responseHelper->error('Invalid task ID', 400);
            }

            // Find task by ID and user ID
            $existingTask = $this->taskModel->findByIdAndUserId($taskId, $userId);

            if ($existingTask === null) {
                $this->logger->warning('Task not found or access denied for update', [
                    'task_id' => $taskId,
                    'user_id' => $userId
                ]);
                return $this->responseHelper->notFound('Task not found');
            }

            // Validate input data
            $validation = $this->validationService->validateTaskUpdate($data);
            if (!$validation['valid']) {
                return $this->responseHelper->validationError($validation['errors']);
            }

            // Prepare update data
            $updateData = [];
            
            if (isset($data['title'])) {
                $updateData['title'] = $this->validationService->sanitizeInput($data['title']);
            }
            
            if (isset($data['description'])) {
                $updateData['description'] = $this->validationService->sanitizeInput($data['description']);
            }
            
            if (isset($data['completed'])) {
                $updateData['completed'] = (bool)$data['completed'] ? 1 : 0;
            }
            
            if (isset($data['order'])) {
                $updateData['order_index'] = intval($data['order']);
            }
            
            if (isset($data['date'])) {
                // Validate date format
                $dateValidation = $this->validationService->validateDate($data['date']);
                if (!$dateValidation['valid']) {
                    return $this->responseHelper->validationError($dateValidation['errors']);
                }
                
                // Check if moving to a different date and validate daily task limit
                if ($existingTask['date'] !== $data['date']) {
                    $currentCount = $this->taskModel->getTasksCountForDate($userId, $data['date']);
                    $limitValidation = $this->validationService->validateTasksPerDayLimit($currentCount);
                    if (!$limitValidation['valid']) {
                        return $this->responseHelper->validationError($limitValidation['errors']);
                    }
                }
                
                $updateData['date'] = $data['date'];
            }

            // Handle project assignment
            if (isset($data['projectId'])) {
                $projectId = $data['projectId'] !== null ? (int)$data['projectId'] : null;
                
                if ($projectId !== null) {
                    $projectValidation = $this->validationService->validateProjectId($projectId, $userId, $this->projectModel);
                    if (!$projectValidation['valid']) {
                        return $this->responseHelper->validationError($projectValidation['errors']);
                    }
                }
                
                $updateData['project_id'] = $projectId;
                
                // If task has children, update their project_id too
                if ($projectId !== null) {
                    $this->taskModel->updateChildrenProjectId($taskId, $userId, $projectId);
                }
            }

            // Handle parent assignment
            if (isset($data['parentId'])) {
                $parentId = $data['parentId'] !== null ? (int)$data['parentId'] : null;
                
                if ($parentId !== null) {
                    $parentValidation = $this->validationService->validateParentTaskId($parentId, $userId, $this->taskModel);
                    if (!$parentValidation['valid']) {
                        return $this->responseHelper->validationError($parentValidation['errors']);
                    }
                    
                    // Get parent task to ensure project_id matches
                    $parentTask = $this->taskModel->findByIdAndUserId($parentId, $userId);
                    if ($parentTask === null || $parentTask['project_id'] === null) {
                        return $this->responseHelper->error('Parent task must belong to a project', 400);
                    }
                    
                    // Set child's project_id to match parent's project_id
                    $updateData['project_id'] = $parentTask['project_id'];
                }
                
                $updateData['parent_id'] = $parentId;
            }

            // Update task
            $success = $this->taskModel->updateTask($taskId, $userId, $updateData);

            if (!$success) {
                $this->logger->error('Failed to update task in database', [
                    'task_id' => $taskId,
                    'user_id' => $userId
                ]);
                return $this->responseHelper->internalError('Failed to update task');
            }

            // Get updated task
            $updatedTask = $this->taskModel->findByIdAndUserId($taskId, $userId);
            $formattedTask = $this->taskModel->formatTaskForResponse($updatedTask);

            $this->logger->info('Task updated successfully', [
                'task_id' => $taskId,
                'user_id' => $userId,
                'updated_fields' => array_keys($updateData)
            ]);

            return $this->responseHelper->success($formattedTask);

        } catch (\Exception $e) {
            $this->logger->error('Update task failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to update task');
        }
    }

    public function deleteTask(Request $request, Response $response): Response
    {
        $this->logger->info('Delete task request started');

        try {
            $userId = $request->getAttribute('user_id');
            $taskId = (int)$request->getAttribute('taskId');

            // Validate task ID
            if (!$this->validationService->isValidId((string)$taskId)) {
                $this->logger->warning('Invalid task ID provided', ['task_id' => $taskId]);
                return $this->responseHelper->error('Invalid task ID', 400);
            }

            // Find task by ID and user ID to ensure it exists and belongs to user
            $existingTask = $this->taskModel->findByIdAndUserId($taskId, $userId);

            if ($existingTask === null) {
                $this->logger->warning('Task not found or access denied for deletion', [
                    'task_id' => $taskId,
                    'user_id' => $userId
                ]);
                return $this->responseHelper->notFound('Task not found');
            }

            // Delete task (this will also handle reordering in the model)
            $success = $this->taskModel->deleteTask($taskId, $userId);

            if (!$success) {
                $this->logger->error('Failed to delete task from database', [
                    'task_id' => $taskId,
                    'user_id' => $userId
                ]);
                return $this->responseHelper->internalError('Failed to delete task');
            }

            $this->logger->info('Task deleted successfully', [
                'task_id' => $taskId,
                'user_id' => $userId,
                'task_title' => $existingTask['title']
            ]);

            return $this->responseHelper->success([
                'message' => 'Task deleted successfully',
                'deletedTask' => [
                    'id' => $taskId,
                    'title' => $existingTask['title']
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Delete task failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to delete task');
        }
    }

    public function updateTaskOrder(Request $request, Response $response): Response
    {
        $this->logger->info('Update task order request started');

        try {
            $userId = $request->getAttribute('user_id');
            $taskId = (int)$request->getAttribute('taskId');
            $data = $request->getParsedBody();

            // Validate task ID
            if (!$this->validationService->isValidId((string)$taskId)) {
                $this->logger->warning('Invalid task ID provided', ['task_id' => $taskId]);
                return $this->responseHelper->error('Invalid task ID', 400);
            }

            // Validate required input data
            if (!isset($data['date']) || empty($data['date'])) {
                return $this->responseHelper->error('Date is required', 400);
            }

            // Validate date format
            $dateValidation = $this->validationService->validateDate($data['date']);
            if (!$dateValidation['valid']) {
                return $this->responseHelper->validationError($dateValidation['errors']);
            }

            $newDate = $data['date'];
            $afterTaskId = isset($data['after']) && !empty($data['after']) ? (int)$data['after'] : null;

            // Find task by ID and user ID to ensure it exists and belongs to user
            $existingTask = $this->taskModel->findByIdAndUserId($taskId, $userId);

            if ($existingTask === null) {
                $this->logger->warning('Task not found or access denied for order update', [
                    'task_id' => $taskId,
                    'user_id' => $userId
                ]);
                return $this->responseHelper->notFound('Task not found');
            }

            // If afterTaskId is provided, validate it exists and belongs to user
            if ($afterTaskId !== null) {
                if (!$this->validationService->isValidId((string)$afterTaskId)) {
                    return $this->responseHelper->error('Invalid after task ID', 400);
                }

                $afterTask = $this->taskModel->findByIdAndUserId($afterTaskId, $userId);
                if ($afterTask === null) {
                    return $this->responseHelper->error('After task not found', 400);
                }
            }

            // Update task order
            $success = $this->taskModel->updateTaskOrder($taskId, $userId, $newDate, $afterTaskId);

            if (!$success) {
                $this->logger->error('Failed to update task order in database', [
                    'task_id' => $taskId,
                    'user_id' => $userId,
                    'new_date' => $newDate,
                    'after_task_id' => $afterTaskId
                ]);
                return $this->responseHelper->internalError('Failed to update task order');
            }

            // Get updated task
            $updatedTask = $this->taskModel->findByIdAndUserId($taskId, $userId);
            $formattedTask = $this->taskModel->formatTaskForResponse($updatedTask);

            $this->logger->info('Task order updated successfully', [
                'task_id' => $taskId,
                'user_id' => $userId,
                'old_date' => $existingTask['date'],
                'new_date' => $newDate,
                'after_task_id' => $afterTaskId,
                'new_order' => $updatedTask['order_index']
            ]);

            return $this->responseHelper->success($formattedTask);

        } catch (\Exception $e) {
            $this->logger->error('Update task order failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to update task order');
        }
    }

    public function assignTaskToProject(Request $request, Response $response): Response
    {
        $this->logger->info('Assign task to project request started');

        try {
            $userId = $request->getAttribute('user_id');
            $taskId = (int)$request->getAttribute('taskId');
            $data = $request->getParsedBody();

            // Validate task ID
            if (!$this->validationService->isValidId((string)$taskId)) {
                return $this->responseHelper->error('Invalid task ID', 400);
            }

            // Validate assignment data
            $validation = $this->validationService->validateTaskProjectAssignment($data, $userId, $this->taskModel, $this->projectModel);
            if (!$validation['valid']) {
                return $this->responseHelper->validationError($validation['errors']);
            }

            // Find task
            $task = $this->taskModel->findByIdAndUserId($taskId, $userId);
            if ($task === null) {
                return $this->responseHelper->notFound('Task not found');
            }

            $projectId = isset($data['projectId']) ? (int)$data['projectId'] : null;

            // Update task project_id
            $updateData = ['project_id' => $projectId];
            
            // If task has children, update their project_id too
            if ($projectId !== null) {
                $this->taskModel->updateChildrenProjectId($taskId, $userId, $projectId);
            }

            $success = $this->taskModel->updateTask($taskId, $userId, $updateData);

            if (!$success) {
                return $this->responseHelper->internalError('Failed to assign task to project');
            }

            $updatedTask = $this->taskModel->findByIdAndUserId($taskId, $userId);
            $formattedTask = $this->taskModel->formatTaskForResponse($updatedTask);

            $this->logger->info('Task assigned to project successfully', [
                'task_id' => $taskId,
                'project_id' => $projectId,
                'user_id' => $userId
            ]);

            return $this->responseHelper->success($formattedTask);

        } catch (\Exception $e) {
            $this->logger->error('Assign task to project failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to assign task to project');
        }
    }

    public function assignTaskToParent(Request $request, Response $response): Response
    {
        $this->logger->info('Assign task to parent request started');

        try {
            $userId = $request->getAttribute('user_id');
            $taskId = (int)$request->getAttribute('taskId');
            $data = $request->getParsedBody();

            // Validate task ID
            if (!$this->validationService->isValidId((string)$taskId)) {
                return $this->responseHelper->error('Invalid task ID', 400);
            }

            // Validate assignment data
            $validation = $this->validationService->validateTaskParentAssignment($data, $userId, $this->taskModel);
            if (!$validation['valid']) {
                return $this->responseHelper->validationError($validation['errors']);
            }

            // Find task
            $task = $this->taskModel->findByIdAndUserId($taskId, $userId);
            if ($task === null) {
                return $this->responseHelper->notFound('Task not found');
            }

            $parentId = isset($data['parentId']) ? (int)$data['parentId'] : null;

            if ($parentId !== null) {
                // Get parent task
                $parentTask = $this->taskModel->findByIdAndUserId($parentId, $userId);
                if ($parentTask === null || $parentTask['project_id'] === null) {
                    return $this->responseHelper->error('Parent task must belong to a project', 400);
                }

                // Validate that child's project_id matches parent's project_id
                if ($task['project_id'] !== $parentTask['project_id']) {
                    // Update child's project_id to match parent
                    $updateData = [
                        'parent_id' => $parentId,
                        'project_id' => $parentTask['project_id']
                    ];
                } else {
                    $updateData = ['parent_id' => $parentId];
                }
            } else {
                $updateData = ['parent_id' => null];
            }

            $success = $this->taskModel->updateTask($taskId, $userId, $updateData);

            if (!$success) {
                return $this->responseHelper->internalError('Failed to assign task to parent');
            }

            $updatedTask = $this->taskModel->findByIdAndUserId($taskId, $userId);
            $formattedTask = $this->taskModel->formatTaskForResponse($updatedTask);

            $this->logger->info('Task assigned to parent successfully', [
                'task_id' => $taskId,
                'parent_id' => $parentId,
                'user_id' => $userId
            ]);

            return $this->responseHelper->success($formattedTask);

        } catch (\Exception $e) {
            $this->logger->error('Assign task to parent failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to assign task to parent');
        }
    }

    public function unassignTaskFromProject(Request $request, Response $response): Response
    {
        $this->logger->info('Unassign task from project request started');

        try {
            $userId = $request->getAttribute('user_id');
            $taskId = (int)$request->getAttribute('taskId');

            // Validate task ID
            if (!$this->validationService->isValidId((string)$taskId)) {
                return $this->responseHelper->error('Invalid task ID', 400);
            }

            // Find task
            $task = $this->taskModel->findByIdAndUserId($taskId, $userId);
            if ($task === null) {
                return $this->responseHelper->notFound('Task not found');
            }

            // Unassign project (set project_id to NULL)
            // Also remove parent_id if task is nested
            $updateData = [
                'project_id' => null,
                'parent_id' => null
            ];

            // If task has children, unassign their project_id too
            $this->taskModel->updateChildrenProjectId($taskId, $userId, null);

            $success = $this->taskModel->updateTask($taskId, $userId, $updateData);

            if (!$success) {
                return $this->responseHelper->internalError('Failed to unassign task from project');
            }

            $updatedTask = $this->taskModel->findByIdAndUserId($taskId, $userId);
            $formattedTask = $this->taskModel->formatTaskForResponse($updatedTask);

            $this->logger->info('Task unassigned from project successfully', [
                'task_id' => $taskId,
                'user_id' => $userId
            ]);

            return $this->responseHelper->success($formattedTask);

        } catch (\Exception $e) {
            $this->logger->error('Unassign task from project failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to unassign task from project');
        }
    }

    public function unassignTaskFromParent(Request $request, Response $response): Response
    {
        $this->logger->info('Unassign task from parent request started');

        try {
            $userId = $request->getAttribute('user_id');
            $taskId = (int)$request->getAttribute('taskId');

            // Validate task ID
            if (!$this->validationService->isValidId((string)$taskId)) {
                return $this->responseHelper->error('Invalid task ID', 400);
            }

            // Find task
            $task = $this->taskModel->findByIdAndUserId($taskId, $userId);
            if ($task === null) {
                return $this->responseHelper->notFound('Task not found');
            }

            // Unassign parent (set parent_id to NULL, keep project_id unchanged)
            $updateData = ['parent_id' => null];

            $success = $this->taskModel->updateTask($taskId, $userId, $updateData);

            if (!$success) {
                return $this->responseHelper->internalError('Failed to unassign task from parent');
            }

            $updatedTask = $this->taskModel->findByIdAndUserId($taskId, $userId);
            $formattedTask = $this->taskModel->formatTaskForResponse($updatedTask);

            $this->logger->info('Task unassigned from parent successfully', [
                'task_id' => $taskId,
                'user_id' => $userId
            ]);

            return $this->responseHelper->success($formattedTask);

        } catch (\Exception $e) {
            $this->logger->error('Unassign task from parent failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to unassign task from parent');
        }
    }
}
