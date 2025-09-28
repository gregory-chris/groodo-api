<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Task;
use App\Services\ValidationService;
use App\Utils\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class TaskController
{
    private Task $taskModel;
    private ValidationService $validationService;
    private ResponseHelper $responseHelper;
    private LoggerInterface $logger;

    public function __construct(
        Task $taskModel,
        ValidationService $validationService,
        ResponseHelper $responseHelper,
        LoggerInterface $logger
    ) {
        $this->taskModel = $taskModel;
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

            // Get tasks
            $tasks = $this->taskModel->findByUserId($userId, $fromDate, $untilDate, $limit, $offset);

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

            // Validate input data
            $validation = $this->validationService->validateTaskCreation($data);
            if (!$validation['valid']) {
                return $this->responseHelper->validationError($validation['errors']);
            }

            $date = $data['date'];

            // Check daily task limit
            $currentCount = $this->taskModel->getTasksCountForDate($userId, $date);
            $limitValidation = $this->validationService->validateTasksPerDayLimit($currentCount);
            if (!$limitValidation['valid']) {
                return $this->responseHelper->validationError($limitValidation['errors']);
            }

            // Create task
            $taskId = $this->taskModel->createTask([
                'user_id' => $userId,
                'title' => $this->validationService->sanitizeInput($data['title']),
                'description' => isset($data['description']) ? $this->validationService->sanitizeInput($data['description']) : '',
                'date' => $date,
                'completed' => $data['completed'] ?? false
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
        // Implementation will be added in the next phase
        return $this->responseHelper->success(['message' => 'Get task endpoint - coming soon']);
    }

    public function updateTask(Request $request, Response $response): Response
    {
        // Implementation will be added in the next phase
        return $this->responseHelper->success(['message' => 'Update task endpoint - coming soon']);
    }

    public function deleteTask(Request $request, Response $response): Response
    {
        // Implementation will be added in the next phase
        return $this->responseHelper->success(['message' => 'Delete task endpoint - coming soon']);
    }

    public function updateTaskOrder(Request $request, Response $response): Response
    {
        // Implementation will be added in the next phase
        return $this->responseHelper->success(['message' => 'Update task order endpoint - coming soon']);
    }
}
