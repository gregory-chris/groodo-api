# GrooDo API - GitHub Copilot Instructions

## Project Context
This is a RESTful API for GrooDo (a calendar-based todo app) built with:
- **Language**: PHP 8.1+
- **Framework**: Slim Framework 4
- **Authentication**: JWT (JSON Web Tokens)
- **Database**: SQLite with PDO
- **Testing**: PHPUnit
- **Logging**: Monolog

## Core Architecture Principles

### 1. Stateless Design
- Use JWT tokens for authentication (24-hour expiry, auto-extend on use)
- No server-side sessions
- All state in database or client tokens

### 2. Response Format (MANDATORY)
All API responses MUST follow this exact format:

```php
// Success response
return $response->withJson([
    'result' => 'success',
    'data' => $data
], 200);

// Error response
return $response->withJson([
    'result' => 'failure',
    'error' => $errorMessage
], $statusCode);
```

### 3. Security Requirements
- Validate ALL inputs before processing
- Use prepared statements for ALL database queries
- Hash passwords with `password_hash()` (bcrypt)
- Sanitize data before logging (never log passwords/tokens)
- CORS: Only allow `*.greq.me` domains
- JWT signature validation on every authenticated request
- Routes are case-insensitive (handled by CaseInsensitiveRouteMiddleware)

### 4. Comprehensive Logging
Log EVERYTHING with Monolog:
- Every HTTP request (method, URI, user_id if authenticated)
- All database operations (query, parameters, affected rows)
- Authentication attempts and results
- Business logic steps with context
- Errors with full stack traces

Example logging pattern:
```php
$this->logger->info('User created task', [
    'user_id' => $userId,
    'task_title' => $title,
    'task_date' => $date,
    'result' => 'success'
]);
```

## PHP Code Standards

### File Structure
```php
<?php
declare(strict_types=1);

namespace App\Controllers; // or Models, Services, etc.

// Use statements
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ExampleController
{
    // Constructor with property promotion (PHP 8.1+)
    public function __construct(
        private LoggerInterface $logger,
        private ExampleService $service
    ) {}
    
    // Methods with full type hints
    public function method(Request $request, Response $response): Response
    {
        // Implementation
    }
}
```

### Naming Conventions
- **Classes**: PascalCase (`UserController`, `TaskService`)
- **Methods/Functions**: camelCase (`createTask`, `validateEmail`)
- **Variables**: camelCase (`$userId`, `$taskTitle`)
- **Constants**: UPPER_SNAKE_CASE (`MAX_TASKS_PER_DAY`)
- **API Routes**: lowercase with kebab-case (`/api/users/signup`, `/api/users/confirm-email`)
- **Database tables**: lowercase_snake_case (`users`, `tasks`)
- **Database columns**: snake_case (`created_at`, `user_id`, `is_email_confirmed`)
- **Booleans**: Prefix with `is`, `has`, `can` (`$isConfirmed`, `$hasAccess`)
- **Arrays**: Plural nouns (`$tasks`, `$users`)

### Type Hints (REQUIRED)
- Always use strict typing: `declare(strict_types=1);`
- Type hint all parameters
- Declare all return types
- Use nullable types when appropriate: `?string`, `?int`
- Use union types for flexibility: `string|int`

### PSR-12 Compliance
- Maximum line length: 120 characters
- 4 spaces for indentation (no tabs)
- Opening braces on same line for methods
- One blank line after namespace declaration
- One blank line after use statements

## Input Validation Rules

When generating validation code, use these exact rules:

```php
// Email validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new ValidationException('Invalid email format');
}

// Password validation (8+ chars, letters + numbers)
if (strlen($password) < 8) {
    throw new ValidationException('Password must be at least 8 characters');
}
if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    throw new ValidationException('Password must contain both letters and numbers');
}

// Full name (max 40 chars, letters/spaces/dashes only)
if (strlen($fullName) > 40) {
    throw new ValidationException('Full name must not exceed 40 characters');
}
if (!preg_match('/^[A-Za-z\s\-]+$/', $fullName)) {
    throw new ValidationException('Full name can only contain letters, spaces, and dashes');
}

// Task title (max 256 chars, required)
if (empty($title)) {
    throw new ValidationException('Task title is required');
}
if (strlen($title) > 256) {
    throw new ValidationException('Task title must not exceed 256 characters');
}

// Task description (max 2048 chars, optional)
if (strlen($description) > 2048) {
    throw new ValidationException('Task description must not exceed 2048 characters');
}

// Date validation (ISO 8601 format)
$date = new \DateTime($dateString);
if (!$date) {
    throw new ValidationException('Invalid date format. Use ISO 8601 (YYYY-MM-DD)');
}
```

## Database Conventions

### Table Schema Patterns
```sql
CREATE TABLE table_name (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### Query Patterns
Always use prepared statements:
```php
// SELECT
$stmt = $this->db->prepare('SELECT * FROM tasks WHERE user_id = ? AND date = ?');
$stmt->execute([$userId, $date]);
$results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

// INSERT
$stmt = $this->db->prepare('INSERT INTO tasks (user_id, title, description, date, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->execute([$userId, $title, $description, $date, $now, $now]);
$taskId = $this->db->lastInsertId();

// UPDATE
$stmt = $this->db->prepare('UPDATE tasks SET title = ?, updated_at = ? WHERE id = ? AND user_id = ?');
$stmt->execute([$title, $now, $taskId, $userId]);

// DELETE
$stmt = $this->db->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ?');
$stmt->execute([$taskId, $userId]);
```

### DateTime Handling
- ALWAYS use UTC timezone
- Store as TEXT in ISO 8601 format
- Use `gmdate('Y-m-d\TH:i:s\Z')` for current UTC time

```php
$now = gmdate('Y-m-d\TH:i:s\Z');
$date = (new \DateTime($input))->format('Y-m-d\TH:i:s\Z');
```

## Error Handling Patterns

### Controller Error Handling
```php
public function handleRequest(Request $request, Response $response): Response
{
    try {
        // Log request
        $this->logger->info('Processing request', [
            'method' => $request->getMethod(),
            'uri' => $request->getUri()->getPath(),
            'user_id' => $request->getAttribute('user_id')
        ]);

        // Validate input
        $data = $this->validateInput($request);
        
        // Business logic
        $result = $this->service->process($data);
        
        // Log success
        $this->logger->info('Request successful', ['result_count' => count($result)]);
        
        return $response->withJson([
            'result' => 'success',
            'data' => $result
        ], 200);
        
    } catch (ValidationException $e) {
        $this->logger->warning('Validation error', ['error' => $e->getMessage()]);
        return $response->withJson([
            'result' => 'failure',
            'error' => $e->getMessage()
        ], 400);
        
    } catch (UnauthorizedException $e) {
        $this->logger->warning('Unauthorized access', ['error' => $e->getMessage()]);
        return $response->withJson([
            'result' => 'failure',
            'error' => $e->getMessage()
        ], 403);
        
    } catch (NotFoundException $e) {
        $this->logger->info('Resource not found', ['error' => $e->getMessage()]);
        return $response->withJson([
            'result' => 'failure',
            'error' => $e->getMessage()
        ], 404);
        
    } catch (\Exception $e) {
        $this->logger->error('Unexpected error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return $response->withJson([
            'result' => 'failure',
            'error' => 'Internal server error'
        ], 500);
    }
}
```

### HTTP Status Codes
- `200`: Success
- `400`: Bad Request (validation errors)
- `403`: Forbidden (authentication/authorization failures)
- `404`: Not Found
- `500`: Internal Server Error

## Business Logic Rules

### Task Management
- Maximum 50 tasks per user per date
- Task statuses: pending, completed
- Tasks can be reordered (use `order_index` column)
- Tasks can be moved between dates

### Authentication
- JWT tokens expire in 24 hours
- Extend token expiry on each authenticated request
- Email confirmation required for new accounts
- Confirmation tokens expire in 1 hour
- Implement basic bot detection for auth endpoints

### User Management
- Email must be unique
- Password must be hashed before storage
- Track email confirmation status
- Track account creation and last login

## Dependency Injection Pattern

All classes should use constructor injection:

```php
class UserController
{
    public function __construct(
        private LoggerInterface $logger,
        private UserService $userService,
        private ValidationService $validationService,
        private JwtService $jwtService
    ) {}
}
```

Register dependencies in `src/dependencies.php`:
```php
$container->set(UserService::class, function (ContainerInterface $c) {
    return new UserService(
        $c->get(\PDO::class),
        $c->get(LoggerInterface::class),
        $c->get(PasswordService::class)
    );
});
```

## Testing Requirements

### Unit Test Pattern
```php
<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ExampleServiceTest extends TestCase
{
    private ExampleService $service;
    
    protected function setUp(): void
    {
        // Mock dependencies
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new ExampleService($this->logger);
    }
    
    public function testSuccessScenario(): void
    {
        $result = $this->service->process($validInput);
        $this->assertEquals($expected, $result);
    }
    
    public function testValidationFailure(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->process($invalidInput);
    }
}
```

### Integration Test Pattern
```php
<?php
declare(strict_types=1);

namespace Tests\Integration;

class TaskEndpointsTest extends ApiTestCase
{
    public function testCreateTask(): void
    {
        $token = $this->authenticateUser();
        
        $response = $this->request('POST', '/api/tasks', [
            'title' => 'Test Task',
            'description' => 'Test Description',
            'date' => '2023-10-15'
        ], $token);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode((string)$response->getBody(), true);
        $this->assertEquals('success', $data['result']);
        $this->assertArrayHasKey('task_id', $data['data']);
    }
}
```

## Documentation Standards

### PHPDoc Blocks
```php
/**
 * Creates a new task for the authenticated user
 *
 * @param int $userId The ID of the user creating the task
 * @param string $title The task title (max 256 chars)
 * @param string $description The task description (max 2048 chars)
 * @param string $date The task date in ISO 8601 format
 * @return array The created task data with ID
 * @throws ValidationException If input validation fails
 * @throws \PDOException If database operation fails
 */
public function createTask(int $userId, string $title, string $description, string $date): array
{
    // Implementation
}
```

## Performance Considerations

### Database Optimization
- Use indexes on frequently queried columns (user_id, date, email)
- Use LIMIT and OFFSET for pagination
- Avoid N+1 queries
- Use transactions for multi-step operations

### Memory Management
- Unset large variables when done
- Use generators for large result sets
- Avoid loading entire tables into memory

## Security Checklist

When generating code, ensure:
- [ ] All inputs are validated
- [ ] All database queries use prepared statements
- [ ] Passwords are hashed (never stored plain text)
- [ ] Sensitive data is not logged
- [ ] JWT tokens are validated properly
- [ ] CORS origins are checked
- [ ] Error messages don't expose internal details
- [ ] SQL injection is prevented
- [ ] XSS is prevented through proper output encoding

## Common Mistakes to Avoid

1. **Never concatenate user input into SQL queries**
   ```php
   // WRONG
   $query = "SELECT * FROM users WHERE email = '$email'";
   
   // CORRECT
   $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
   $stmt->execute([$email]);
   ```

2. **Never log sensitive data**
   ```php
   // WRONG
   $this->logger->info('Login attempt', ['password' => $password]);
   
   // CORRECT
   $this->logger->info('Login attempt', ['email' => $email]);
   ```

3. **Never use plain text passwords**
   ```php
   // WRONG
   $stmt->execute([$email, $password]);
   
   // CORRECT
   $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
   $stmt->execute([$email, $hashedPassword]);
   ```

4. **Always use UTC for datetime**
   ```php
   // WRONG
   $now = date('Y-m-d H:i:s');
   
   // CORRECT
   $now = gmdate('Y-m-d\TH:i:s\Z');
   ```

5. **Always follow response format**
   ```php
   // WRONG
   return $response->withJson(['success' => true, 'message' => 'Done']);
   
   // CORRECT
   return $response->withJson(['result' => 'success', 'data' => $data], 200);
   ```

## Project-Specific Constants

Use these values when generating code:
- `MAX_TASKS_PER_DAY = 50`
- `JWT_EXPIRY_HOURS = 24`
- `EMAIL_CONFIRMATION_EXPIRY_HOURS = 1`
- `PASSWORD_MIN_LENGTH = 8`
- `FULL_NAME_MAX_LENGTH = 40`
- `TASK_TITLE_MAX_LENGTH = 256`
- `TASK_DESCRIPTION_MAX_LENGTH = 2048`

## File Organization

```
src/
├── Controllers/          # HTTP request handlers
│   ├── TaskController.php
│   └── UserController.php
├── Models/              # Database entities
│   ├── BaseModel.php
│   ├── Task.php
│   └── User.php
├── Services/            # Business logic
│   ├── EmailService.php
│   ├── JwtService.php
│   ├── PasswordService.php
│   └── ValidationService.php
├── Middleware/          # Request/response interceptors
│   ├── AuthMiddleware.php
│   ├── CorsMiddleware.php
│   └── LoggingMiddleware.php
├── Utils/               # Helper classes
│   ├── Database.php
│   ├── ResponseHelper.php
│   └── Migration.php
├── Exceptions/          # Custom exceptions
│   ├── ValidationException.php
│   ├── UnauthorizedException.php
│   └── NotFoundException.php
├── dependencies.php     # DI container configuration
└── routes.php          # Route definitions
```

## Quick Reference

### Creating a New Endpoint
1. Add route in `src/routes.php`
2. Create controller method
3. Implement service logic
4. Add model methods if needed
5. Write unit tests
6. Write integration tests
7. Update API documentation

### Creating a New Model
1. Create class extending `BaseModel`
2. Define table name
3. Implement CRUD methods
4. Add validation logic
5. Log all database operations
6. Write unit tests

### Adding Middleware
1. Create middleware class implementing interface
2. Register in `src/dependencies.php`
3. Add to route or route group in `src/routes.php`
4. Test thoroughly

---

**Remember**: This is a production API. Every line of code should be secure, logged, tested, and maintainable. When in doubt, prioritize security and data integrity over convenience.
