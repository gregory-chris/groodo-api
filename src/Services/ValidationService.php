<?php
declare(strict_types=1);

namespace App\Services;

use Psr\Log\LoggerInterface;

class ValidationService
{
    private LoggerInterface $logger;
    private int $maxTasksPerDay;
    private int $passwordMinLength;
    private int $maxTaskNestingDepth;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->maxTasksPerDay = (int)($_ENV['MAX_TASKS_PER_DAY'] ?? 50);
        $this->passwordMinLength = (int)($_ENV['PASSWORD_MIN_LENGTH'] ?? 8);
        $this->maxTaskNestingDepth = (int)($_ENV['MAX_TASK_NESTING_DEPTH'] ?? 2);
    }

    public function validateEmail(string $email): array
    {
        $this->logger->debug('Validating email format');

        $errors = [];

        if (empty($email)) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        } elseif (strlen($email) > 255) {
            $errors[] = 'Email is too long (maximum 255 characters)';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validatePassword(string $password): array
    {
        $this->logger->debug('Validating password');

        $errors = [];

        if (empty($password)) {
            $errors[] = 'Password is required';
        } else {
            if (strlen($password) < $this->passwordMinLength) {
                $errors[] = "Password must be at least {$this->passwordMinLength} characters long";
            }

            if (!preg_match('/[a-zA-Z]/', $password)) {
                $errors[] = 'Password must contain at least one letter';
            }

            if (!preg_match('/[0-9]/', $password)) {
                $errors[] = 'Password must contain at least one number';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validateFullName(string $fullName): array
    {
        $this->logger->debug('Validating full name');

        $errors = [];

        if (empty($fullName)) {
            $errors[] = 'Full name is required';
        } else {
            if (strlen($fullName) > 40) {
                $errors[] = 'Full name is too long (maximum 40 characters)';
            }

            if (strlen($fullName) < 2) {
                $errors[] = 'Full name is too short (minimum 2 characters)';
            }

            // Allow letters, spaces, dashes, and apostrophes (for names like O'Connor)
            if (!preg_match('/^[a-zA-Z\s\-\']+$/', $fullName)) {
                $errors[] = 'Full name can only contain letters, spaces, dashes, and apostrophes';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validateTaskTitle(string $title): array
    {
        $this->logger->debug('Validating task title');

        $errors = [];

        if (empty($title)) {
            $errors[] = 'Task title is required';
        } else {
            if (strlen($title) > 256) {
                $errors[] = 'Task title is too long (maximum 256 characters)';
            }

            if (strlen(trim($title)) === 0) {
                $errors[] = 'Task title cannot be empty or contain only whitespace';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validateTaskDescription(?string $description): array
    {
        $this->logger->debug('Validating task description');

        $errors = [];

        if ($description !== null && strlen($description) > 50000) {
            $errors[] = 'Task description is too long (maximum 50000 characters)';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validateDate(string $date): array
    {
        $this->logger->debug('Validating date format');

        $errors = [];

        if (empty($date)) {
            $errors[] = 'Date is required';
        } else {
            // Validate ISO 8601 date format (YYYY-MM-DD)
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $errors[] = 'Date must be in ISO 8601 format (YYYY-MM-DD)';
            } else {
                // Validate that it's a real date
                $dateParts = explode('-', $date);
                if (count($dateParts) === 3) {
                    $year = (int)$dateParts[0];
                    $month = (int)$dateParts[1];
                    $day = (int)$dateParts[2];
                    
                    if (!checkdate($month, $day, $year)) {
                        $errors[] = 'Invalid date';
                    }
                } else {
                    $errors[] = 'Invalid date format';
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate date format (optional - doesn't require date to be present)
     */
    public function validateOptionalDate(?string $date): array
    {
        $this->logger->debug('Validating optional date format');

        $errors = [];

        // If date is provided, validate format
        if ($date !== null && $date !== '') {
            // Validate ISO 8601 date format (YYYY-MM-DD)
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $errors[] = 'Date must be in ISO 8601 format (YYYY-MM-DD)';
            } else {
                // Validate that it's a real date
                $dateParts = explode('-', $date);
                if (count($dateParts) === 3) {
                    $year = (int)$dateParts[0];
                    $month = (int)$dateParts[1];
                    $day = (int)$dateParts[2];
                    
                    if (!checkdate($month, $day, $year)) {
                        $errors[] = 'Invalid date';
                    }
                } else {
                    $errors[] = 'Invalid date format';
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validateTasksPerDayLimit(int $currentCount): array
    {
        $this->logger->debug('Validating tasks per day limit', [
            'current_count' => $currentCount,
            'max_allowed' => $this->maxTasksPerDay
        ]);

        $errors = [];

        if ($currentCount >= $this->maxTasksPerDay) {
            $errors[] = "Maximum {$this->maxTasksPerDay} tasks allowed per day";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validatePaginationParams(?string $limit, ?string $offset): array
    {
        $this->logger->debug('Validating pagination parameters');

        $errors = [];
        $validatedLimit = 1000; // default
        $validatedOffset = 0; // default

        if ($limit !== null) {
            if (!is_numeric($limit) || (int)$limit < 1) {
                $errors[] = 'Limit must be a positive integer';
            } elseif ((int)$limit > 1000) {
                $errors[] = 'Limit cannot exceed 1000';
            } else {
                $validatedLimit = (int)$limit;
            }
        }

        if ($offset !== null) {
            if (!is_numeric($offset) || (int)$offset < 0) {
                $errors[] = 'Offset must be a non-negative integer';
            } else {
                $validatedOffset = (int)$offset;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'limit' => $validatedLimit,
            'offset' => $validatedOffset
        ];
    }

    public function validateUserRegistration(array $data): array
    {
        $this->logger->debug('Validating user registration data');

        $allErrors = [];

        // Validate email
        $emailValidation = $this->validateEmail($data['email'] ?? '');
        if (!$emailValidation['valid']) {
            $allErrors = array_merge($allErrors, $emailValidation['errors']);
        }

        // Validate password
        $passwordValidation = $this->validatePassword($data['password'] ?? '');
        if (!$passwordValidation['valid']) {
            $allErrors = array_merge($allErrors, $passwordValidation['errors']);
        }

        // Validate full name
        $fullNameValidation = $this->validateFullName($data['fullName'] ?? '');
        if (!$fullNameValidation['valid']) {
            $allErrors = array_merge($allErrors, $fullNameValidation['errors']);
        }

        return [
            'valid' => empty($allErrors),
            'errors' => $allErrors
        ];
    }

    public function validateTaskCreation(array $data): array
    {
        $this->logger->debug('Validating task creation data');

        $allErrors = [];

        // Validate title
        $titleValidation = $this->validateTaskTitle($data['title'] ?? '');
        if (!$titleValidation['valid']) {
            $allErrors = array_merge($allErrors, $titleValidation['errors']);
        }

        // Validate description (optional)
        $descriptionValidation = $this->validateTaskDescription($data['description'] ?? null);
        if (!$descriptionValidation['valid']) {
            $allErrors = array_merge($allErrors, $descriptionValidation['errors']);
        }

        // Validate date - required if projectId is not provided, optional if projectId is provided
        $projectId = isset($data['projectId']) && $data['projectId'] !== null ? (int)$data['projectId'] : null;
        
        if ($projectId === null) {
            // Date is required when no projectId is provided
            $dateValidation = $this->validateDate($data['date'] ?? '');
            if (!$dateValidation['valid']) {
                $allErrors = array_merge($allErrors, $dateValidation['errors']);
            }
        } else {
            // Date is optional when projectId is provided, but if provided, must be valid format
            $dateValidation = $this->validateOptionalDate($data['date'] ?? null);
            if (!$dateValidation['valid']) {
                $allErrors = array_merge($allErrors, $dateValidation['errors']);
            }
        }

        return [
            'valid' => empty($allErrors),
            'errors' => $allErrors
        ];
    }

    public function validateTaskUpdate(array $data): array
    {
        $this->logger->debug('Validating task update data');

        $allErrors = [];

        // Validate title if provided
        if (isset($data['title'])) {
            $titleValidation = $this->validateTaskTitle($data['title']);
            if (!$titleValidation['valid']) {
                $allErrors = array_merge($allErrors, $titleValidation['errors']);
            }
        }

        // Validate description if provided
        if (isset($data['description'])) {
            $descriptionValidation = $this->validateTaskDescription($data['description']);
            if (!$descriptionValidation['valid']) {
                $allErrors = array_merge($allErrors, $descriptionValidation['errors']);
            }
        }

        // Validate completed status if provided
        if (isset($data['completed']) && !is_bool($data['completed'])) {
            $allErrors[] = 'Completed status must be a boolean';
        }

        return [
            'valid' => empty($allErrors),
            'errors' => $allErrors
        ];
    }

    public function sanitizeInput(string $input): string
    {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Trim whitespace
        $input = trim($input);
        
        return $input;
    }

    public function isValidId(?string $id): bool
    {
        return $id !== null && is_numeric($id) && (int)$id > 0;
    }

    public function validateProjectName(string $name): array
    {
        $this->logger->debug('Validating project name');

        $errors = [];

        if (empty($name)) {
            $errors[] = 'Project name is required';
        } else {
            if (strlen($name) > 256) {
                $errors[] = 'Project name is too long (maximum 256 characters)';
            }

            if (strlen(trim($name)) === 0) {
                $errors[] = 'Project name cannot be empty or contain only whitespace';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validateProjectDescription(?string $description): array
    {
        $this->logger->debug('Validating project description');

        $errors = [];

        if ($description !== null && strlen($description) > 2048) {
            $errors[] = 'Project description is too long (maximum 2048 characters)';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validateUrl(?string $url): array
    {
        $this->logger->debug('Validating URL');

        $errors = [];

        if ($url !== null && !empty($url)) {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $errors[] = 'Invalid URL format';
            } elseif (strlen($url) > 2048) {
                $errors[] = 'URL is too long (maximum 2048 characters)';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validateColor(?string $color): array
    {
        $this->logger->debug('Validating color');

        $errors = [];

        if ($color !== null && !empty($color)) {
            // Validate hex color format (#RRGGBB or #RRGGBBAA)
            if (!preg_match('/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/', $color)) {
                $errors[] = 'Color must be a valid hex color code (e.g., #FF0000 or #FF0000FF)';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validateCustomFields(?string $customFieldsJson): array
    {
        $this->logger->debug('Validating custom fields JSON');

        $errors = [];

        if ($customFieldsJson !== null && !empty($customFieldsJson)) {
            $decoded = json_decode($customFieldsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errors[] = 'Invalid JSON format for custom fields';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validateProjectCreation(array $data): array
    {
        $this->logger->debug('Validating project creation data');

        $allErrors = [];

        // Validate name
        $nameValidation = $this->validateProjectName($data['name'] ?? '');
        if (!$nameValidation['valid']) {
            $allErrors = array_merge($allErrors, $nameValidation['errors']);
        }

        // Validate description (optional)
        $descriptionValidation = $this->validateProjectDescription($data['description'] ?? null);
        if (!$descriptionValidation['valid']) {
            $allErrors = array_merge($allErrors, $descriptionValidation['errors']);
        }

        // Validate URL (optional)
        $urlValidation = $this->validateUrl($data['url'] ?? null);
        if (!$urlValidation['valid']) {
            $allErrors = array_merge($allErrors, $urlValidation['errors']);
        }

        // Validate GitHub URL (optional)
        $githubUrlValidation = $this->validateUrl($data['githubUrl'] ?? null);
        if (!$githubUrlValidation['valid']) {
            $allErrors = array_merge($allErrors, $githubUrlValidation['errors']);
        }

        // Validate color (optional)
        $colorValidation = $this->validateColor($data['color'] ?? null);
        if (!$colorValidation['valid']) {
            $allErrors = array_merge($allErrors, $colorValidation['errors']);
        }

        // Validate custom fields (optional)
        if (isset($data['customFields'])) {
            $customFieldsJson = is_array($data['customFields']) ? json_encode($data['customFields']) : $data['customFields'];
            $customFieldsValidation = $this->validateCustomFields($customFieldsJson);
            if (!$customFieldsValidation['valid']) {
                $allErrors = array_merge($allErrors, $customFieldsValidation['errors']);
            }
        }

        return [
            'valid' => empty($allErrors),
            'errors' => $allErrors
        ];
    }

    public function validateProjectUpdate(array $data): array
    {
        $this->logger->debug('Validating project update data');

        $allErrors = [];

        // Validate name if provided
        if (isset($data['name'])) {
            $nameValidation = $this->validateProjectName($data['name']);
            if (!$nameValidation['valid']) {
                $allErrors = array_merge($allErrors, $nameValidation['errors']);
            }
        }

        // Validate description if provided
        if (isset($data['description'])) {
            $descriptionValidation = $this->validateProjectDescription($data['description']);
            if (!$descriptionValidation['valid']) {
                $allErrors = array_merge($allErrors, $descriptionValidation['errors']);
            }
        }

        // Validate URL if provided
        if (isset($data['url'])) {
            $urlValidation = $this->validateUrl($data['url']);
            if (!$urlValidation['valid']) {
                $allErrors = array_merge($allErrors, $urlValidation['errors']);
            }
        }

        // Validate GitHub URL if provided
        if (isset($data['githubUrl'])) {
            $githubUrlValidation = $this->validateUrl($data['githubUrl']);
            if (!$githubUrlValidation['valid']) {
                $allErrors = array_merge($allErrors, $githubUrlValidation['errors']);
            }
        }

        // Validate color if provided
        if (isset($data['color'])) {
            $colorValidation = $this->validateColor($data['color']);
            if (!$colorValidation['valid']) {
                $allErrors = array_merge($allErrors, $colorValidation['errors']);
            }
        }

        // Validate custom fields if provided
        if (isset($data['customFields'])) {
            $customFieldsJson = is_array($data['customFields']) ? json_encode($data['customFields']) : $data['customFields'];
            $customFieldsValidation = $this->validateCustomFields($customFieldsJson);
            if (!$customFieldsValidation['valid']) {
                $allErrors = array_merge($allErrors, $customFieldsValidation['errors']);
            }
        }

        return [
            'valid' => empty($allErrors),
            'errors' => $allErrors
        ];
    }

    public function validateProjectId(?int $projectId, int $userId, \App\Models\Project $projectModel): array
    {
        $this->logger->debug('Validating project ID', [
            'project_id' => $projectId,
            'user_id' => $userId
        ]);

        $errors = [];

        if ($projectId === null) {
            return [
                'valid' => true,
                'errors' => []
            ];
        }

        $project = $projectModel->findByIdAndUserId($projectId, $userId);
        if ($project === null) {
            $errors[] = 'Project not found or access denied';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validateParentTaskId(?int $parentId, int $userId, \App\Models\Task $taskModel): array
    {
        $this->logger->debug('Validating parent task ID', [
            'parent_id' => $parentId,
            'user_id' => $userId
        ]);

        $errors = [];

        if ($parentId === null) {
            return [
                'valid' => true,
                'errors' => []
            ];
        }

        $parentTask = $taskModel->findByIdAndUserId($parentId, $userId);
        if ($parentTask === null) {
            $errors[] = 'Parent task not found or access denied';
        } elseif ($parentTask['project_id'] === null) {
            $errors[] = 'Parent task must belong to a project';
        } elseif (!$taskModel->validateNestingDepth($parentId, $userId, $this->maxTaskNestingDepth)) {
            $errors[] = "Maximum nesting depth of {$this->maxTaskNestingDepth} exceeded";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validateTaskProjectAssignment(array $data, int $userId, \App\Models\Task $taskModel, \App\Models\Project $projectModel): array
    {
        $this->logger->debug('Validating task project assignment', [
            'user_id' => $userId
        ]);

        $allErrors = [];

        // Validate project ID
        $projectId = $data['projectId'] ?? null;
        if ($projectId !== null) {
            $projectValidation = $this->validateProjectId($projectId, $userId, $projectModel);
            if (!$projectValidation['valid']) {
                $allErrors = array_merge($allErrors, $projectValidation['errors']);
            }
        }

        return [
            'valid' => empty($allErrors),
            'errors' => $allErrors
        ];
    }

    public function validateTaskParentAssignment(array $data, int $userId, \App\Models\Task $taskModel): array
    {
        $this->logger->debug('Validating task parent assignment', [
            'user_id' => $userId
        ]);

        $allErrors = [];

        // Validate parent ID
        $parentId = $data['parentId'] ?? null;
        if ($parentId !== null) {
            $parentValidation = $this->validateParentTaskId($parentId, $userId, $taskModel);
            if (!$parentValidation['valid']) {
                $allErrors = array_merge($allErrors, $parentValidation['errors']);
            }
        }

        return [
            'valid' => empty($allErrors),
            'errors' => $allErrors
        ];
    }

    public function getMaxTaskNestingDepth(): int
    {
        return $this->maxTaskNestingDepth;
    }

    // ========================================
    // Document Validation Methods
    // ========================================

    private int $maxDocumentNestingDepth = 5;

    public function validateDocumentTitle(string $title): array
    {
        $this->logger->debug('Validating document title');

        $errors = [];

        if (empty($title)) {
            $errors[] = 'Document title is required';
        } else {
            if (strlen($title) > 256) {
                $errors[] = 'Document title is too long (maximum 256 characters)';
            }

            if (strlen(trim($title)) === 0) {
                $errors[] = 'Document title cannot be empty or contain only whitespace';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validateDocumentContent(?string $content): array
    {
        $this->logger->debug('Validating document content');

        // No length limit for document content as per requirements
        // Just return valid - content is optional and unlimited
        return [
            'valid' => true,
            'errors' => []
        ];
    }

    public function validateDocumentCreation(array $data): array
    {
        $this->logger->debug('Validating document creation data');

        $allErrors = [];

        // Validate title (required)
        $titleValidation = $this->validateDocumentTitle($data['title'] ?? '');
        if (!$titleValidation['valid']) {
            $allErrors = array_merge($allErrors, $titleValidation['errors']);
        }

        // Validate content (optional, no length limit)
        $contentValidation = $this->validateDocumentContent($data['content'] ?? null);
        if (!$contentValidation['valid']) {
            $allErrors = array_merge($allErrors, $contentValidation['errors']);
        }

        return [
            'valid' => empty($allErrors),
            'errors' => $allErrors
        ];
    }

    public function validateDocumentUpdate(array $data): array
    {
        $this->logger->debug('Validating document update data');

        $allErrors = [];

        // Validate title if provided
        if (isset($data['title'])) {
            $titleValidation = $this->validateDocumentTitle($data['title']);
            if (!$titleValidation['valid']) {
                $allErrors = array_merge($allErrors, $titleValidation['errors']);
            }
        }

        // Validate content if provided (optional, no length limit)
        if (isset($data['content'])) {
            $contentValidation = $this->validateDocumentContent($data['content']);
            if (!$contentValidation['valid']) {
                $allErrors = array_merge($allErrors, $contentValidation['errors']);
            }
        }

        return [
            'valid' => empty($allErrors),
            'errors' => $allErrors
        ];
    }

    public function validateParentDocumentId(?int $parentId, int $userId, \App\Models\Document $documentModel, ?int $currentDocumentId = null): array
    {
        $this->logger->debug('Validating parent document ID', [
            'parent_id' => $parentId,
            'user_id' => $userId,
            'current_document_id' => $currentDocumentId
        ]);

        $errors = [];

        if ($parentId === null) {
            return [
                'valid' => true,
                'errors' => []
            ];
        }

        // Check self-reference
        if ($currentDocumentId !== null && $parentId === $currentDocumentId) {
            $errors[] = 'Document cannot be its own parent';
            return [
                'valid' => false,
                'errors' => $errors
            ];
        }

        // Check if parent exists and belongs to user
        $parentDocument = $documentModel->findByIdAndUserId($parentId, $userId);
        if ($parentDocument === null) {
            $errors[] = 'Parent document not found or access denied';
            return [
                'valid' => false,
                'errors' => $errors
            ];
        }

        // Validate nesting depth
        if (!$documentModel->validateNestingDepth($parentId, $userId, $this->maxDocumentNestingDepth)) {
            $errors[] = "Maximum nesting depth of {$this->maxDocumentNestingDepth} exceeded";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function validateDocumentNestingDepth(int $parentId, int $userId, \App\Models\Document $documentModel): array
    {
        $this->logger->debug('Validating document nesting depth', [
            'parent_id' => $parentId,
            'user_id' => $userId
        ]);

        $errors = [];

        if (!$documentModel->validateNestingDepth($parentId, $userId, $this->maxDocumentNestingDepth)) {
            $errors[] = "Maximum nesting depth of {$this->maxDocumentNestingDepth} exceeded";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    public function getMaxDocumentNestingDepth(): int
    {
        return $this->maxDocumentNestingDepth;
    }
}
