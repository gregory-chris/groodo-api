<?php
declare(strict_types=1);

namespace App\Services;

use Psr\Log\LoggerInterface;

class ValidationService
{
    private LoggerInterface $logger;
    private int $maxTasksPerDay;
    private int $passwordMinLength;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->maxTasksPerDay = (int)($_ENV['MAX_TASKS_PER_DAY'] ?? 50);
        $this->passwordMinLength = (int)($_ENV['PASSWORD_MIN_LENGTH'] ?? 8);
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

        if ($description !== null && strlen($description) > 2048) {
            $errors[] = 'Task description is too long (maximum 2048 characters)';
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
        $validatedLimit = 100; // default
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

        // Validate date
        $dateValidation = $this->validateDate($data['date'] ?? '');
        if (!$dateValidation['valid']) {
            $allErrors = array_merge($allErrors, $dateValidation['errors']);
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
}
