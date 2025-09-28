# GrooDo API Development Plan

This document outlines the step-by-step implementation plan for the GrooDo RESTful API. Each section contains detailed tasks with checkboxes to track progress.

## Phase 1: Project Setup & Foundation

### 1.1 Project Structure Setup
- [ ] Create project directory structure
  - [ ] `/src` - Main application code
  - [ ] `/src/Controllers` - API controllers
  - [ ] `/src/Models` - Data models
  - [ ] `/src/Middleware` - Custom middleware
  - [ ] `/src/Services` - Business logic services
  - [ ] `/src/Utils` - Utility classes
  - [ ] `/config` - Configuration files
  - [ ] `/database` - Database files and migrations
  - [ ] `/logs` - Log files directory
  - [ ] `/public` - Web server document root
  - [ ] `/tests` - Unit and integration tests
  - [ ] `/vendor` - Composer dependencies

### 1.2 Composer & Dependencies
- [ ] Initialize composer project (`composer init`)
- [ ] Install core dependencies:
  - [ ] `slim/slim` - Slim Framework 4
  - [ ] `slim/psr7` - PSR-7 implementation
  - [ ] `firebase/php-jwt` - JWT token handling
  - [ ] `phpmailer/phpmailer` - Email functionality
  - [ ] `monolog/monolog` - Logging
  - [ ] `vlucas/phpdotenv` - Environment variables
- [ ] Install development dependencies:
  - [ ] `phpunit/phpunit` - Testing framework
  - [ ] `squizlabs/php_codesniffer` - Code standards

### 1.3 Configuration Files
- [ ] Create `.env.example` with all required environment variables
- [ ] Create `.env` file for local development
- [ ] Create `config/database.php` - Database configuration
- [ ] Create `config/jwt.php` - JWT configuration
- [ ] Create `config/email.php` - Email configuration
- [ ] Create `config/cors.php` - CORS configuration
- [ ] Create `config/logging.php` - Logging configuration

### 1.4 Basic Application Structure
- [ ] Create `public/index.php` - Application entry point
- [ ] Create `src/App.php` - Main application class
- [ ] Set up Slim framework with dependency injection
- [ ] Configure error handling and logging
- [ ] Set up CORS middleware for `*.greq.me` domains

## Phase 2: Database Setup

### 2.1 Database Schema Design
- [ ] Create SQLite database file at `/database/groodo-api.sqlite`
- [ ] Design `users` table schema:
  - [ ] `id` (INTEGER PRIMARY KEY AUTOINCREMENT)
  - [ ] `email` (TEXT UNIQUE NOT NULL)
  - [ ] `full_name` (TEXT NOT NULL)
  - [ ] `password_hash` (TEXT NOT NULL)
  - [ ] `is_email_confirmed` (INTEGER DEFAULT 0)
  - [ ] `auth_token` (TEXT)
  - [ ] `auth_expires_at` (TEXT)
  - [ ] `email_confirmation_token` (TEXT)
  - [ ] `password_reset_token` (TEXT)
  - [ ] `created_at` (TEXT NOT NULL)
  - [ ] `updated_at` (TEXT NOT NULL)

- [ ] Design `tasks` table schema:
  - [ ] `id` (INTEGER PRIMARY KEY AUTOINCREMENT)
  - [ ] `user_id` (INTEGER NOT NULL)
  - [ ] `title` (TEXT NOT NULL)
  - [ ] `description` (TEXT)
  - [ ] `date` (TEXT NOT NULL) - ISO 8601 date
  - [ ] `order` (INTEGER NOT NULL)
  - [ ] `completed` (INTEGER DEFAULT 0)
  - [ ] `created_at` (TEXT NOT NULL)
  - [ ] `updated_at` (TEXT NOT NULL)
  - [ ] FOREIGN KEY constraint on `user_id`

### 2.2 Database Connection & Models
- [ ] Create `src/Utils/Database.php` - PDO connection wrapper
- [ ] Create `src/Models/BaseModel.php` - Base model with common functionality
- [ ] Create `src/Models/User.php` - User model with CRUD operations
- [ ] Create `src/Models/Task.php` - Task model with CRUD operations
- [ ] Add database indexes for performance:
  - [ ] Index on `users.email`
  - [ ] Index on `users.auth_token`
  - [ ] Index on `tasks.user_id`
  - [ ] Index on `tasks.date`
  - [ ] Composite index on `tasks.user_id, tasks.date, tasks.order`

### 2.3 Database Migration System
- [ ] Create `src/Utils/Migration.php` - Simple migration system
- [ ] Create initial migration files for tables
- [ ] Add migration runner script

## Phase 3: Authentication System

### 3.1 JWT Service
- [ ] Create `src/Services/JwtService.php`:
  - [ ] `generateToken($userId)` - Generate JWT with 1-day expiration
  - [ ] `validateToken($token)` - Validate and decode JWT
  - [ ] `refreshToken($token)` - Extend token expiration
  - [ ] Handle token expiration and validation errors

### 3.2 Password Service
- [ ] Create `src/Services/PasswordService.php`:
  - [ ] `hashPassword($password)` - Hash password using PHP's password_hash()
  - [ ] `verifyPassword($password, $hash)` - Verify password
  - [ ] `validatePasswordStrength($password)` - Validate 8+ chars, letters + numbers

### 3.3 Authentication Middleware
- [ ] Create `src/Middleware/AuthMiddleware.php`:
  - [ ] Extract JWT from Authorization header
  - [ ] Validate token and get user ID
  - [ ] Extend token expiration on each request
  - [ ] Add user data to request attributes
  - [ ] Return 403 for invalid/missing tokens

### 3.4 Security Middleware
- [ ] Create `src/Middleware/SecurityMiddleware.php`:
  - [ ] Basic bot detection (User-Agent validation)
  - [ ] Rate limiting for auth endpoints
  - [ ] Request validation for auth operations

## Phase 4: User Management & Authentication Endpoints

### 4.1 User Controller Setup
- [ ] Create `src/Controllers/UserController.php`
- [ ] Implement standardized JSON response format
- [ ] Add input validation helper methods

### 4.2 User Registration (`POST /api/users/signUp`)
- [ ] Validate input data:
  - [ ] Email format validation
  - [ ] Password strength validation (8+ chars, letters + numbers)
  - [ ] Full name validation (40 chars max, only dash/space special chars)
- [ ] Check if email already exists
- [ ] Hash password using PasswordService
- [ ] Generate email confirmation token (1-hour expiration)
- [ ] Save user to database
- [ ] Send confirmation email
- [ ] Return success response with user data (excluding sensitive fields)

### 4.3 Email Confirmation (`POST /api/users/confirmEmail`)
- [ ] Validate confirmation token
- [ ] Check token expiration (1 hour)
- [ ] Update user's `is_email_confirmed` status
- [ ] Clear confirmation token
- [ ] Return success response

### 4.4 User Sign In (`POST /api/users/signIn`)
- [ ] Validate email format
- [ ] Find user by email
- [ ] Verify password using PasswordService
- [ ] Check if email is confirmed
- [ ] Generate JWT token (1-day expiration)
- [ ] Update user's auth token and expiration
- [ ] Return success response with token and user data

### 4.5 User Sign Out (`POST /api/users/signOut`)
- [ ] Validate JWT token
- [ ] Clear user's auth token in database
- [ ] Return success response

### 4.6 Password Reset Request (`POST /api/users/resetPassword`)
- [ ] Validate email format
- [ ] Find user by email
- [ ] Generate password reset token (1-hour expiration)
- [ ] Save token to database
- [ ] Send password reset email
- [ ] Return success response

### 4.7 User Profile (`GET /api/users/profile`)
- [ ] Require authentication (use AuthMiddleware)
- [ ] Get user data from database
- [ ] Return user profile (exclude password, tokens, expiration dates)
- [ ] Extend auth token expiration

## Phase 5: Email Service

### 5.1 Email Service Setup
- [ ] Create `src/Services/EmailService.php`:
  - [ ] Configure PHPMailer with Gmail SMTP
  - [ ] Use environment variables for credentials
  - [ ] Set up TLS encryption and authentication

### 5.2 Email Templates
- [ ] Create email confirmation template:
  - [ ] HTML and plain text versions
  - [ ] Include confirmation link with token
  - [ ] Professional styling
- [ ] Create password reset template:
  - [ ] HTML and plain text versions
  - [ ] Include reset link with token
  - [ ] Security warnings and instructions

### 5.3 Email Sending Methods
- [ ] `sendEmailConfirmation($user, $token)` - Send confirmation email
- [ ] `sendPasswordReset($user, $token)` - Send password reset email
- [ ] Add error handling and logging for email failures

## Phase 6: Task Management System

### 6.1 Task Controller Setup
- [ ] Create `src/Controllers/TaskController.php`
- [ ] Add authentication requirement for all endpoints
- [ ] Implement input validation methods

### 6.2 List Tasks (`GET /api/tasks`)
- [ ] Require authentication
- [ ] Parse query parameters:
  - [ ] `from` (ISO 8601 date) - optional
  - [ ] `until` (ISO 8601 date) - optional
  - [ ] `limit` (integer) - optional, default 100
  - [ ] `offset` (integer) - optional, default 0
- [ ] Validate date formats
- [ ] Query tasks for authenticated user with filters
- [ ] Order by date ASC, then by order ASC
- [ ] Return paginated results

### 6.3 Create Task (`POST /api/tasks`)
- [ ] Require authentication
- [ ] Validate input data:
  - [ ] Title (required, max 256 chars)
  - [ ] Description (optional, max 2048 chars)
  - [ ] Date (required, ISO 8601 format)
- [ ] Check daily task limit (50 tasks per day)
- [ ] Calculate next order number for the date
- [ ] Create task in database
- [ ] Return created task data

### 6.4 Get Single Task (`GET /api/task/:taskId`)
- [ ] Require authentication
- [ ] Validate task ID format
- [ ] Find task by ID and user ID
- [ ] Return 404 if task not found or doesn't belong to user
- [ ] Return task data

### 6.5 Update Task (`PUT /api/task/:taskId`)
- [ ] Require authentication
- [ ] Validate task ID and input data
- [ ] Find task by ID and user ID
- [ ] Return 404 if task not found or doesn't belong to user
- [ ] Validate updated data (title, description, completed status)
- [ ] Update task in database
- [ ] Return updated task data

### 6.6 Delete Task (`DELETE /api/task/:taskId`)
- [ ] Require authentication
- [ ] Validate task ID format
- [ ] Find task by ID and user ID
- [ ] Return 404 if task not found or doesn't belong to user
- [ ] Delete task from database
- [ ] Reorder remaining tasks for that date
- [ ] Return success response

### 6.7 Update Task Order (`POST /api/task/:taskId/updateOrder`)
- [ ] Require authentication
- [ ] Validate input data:
  - [ ] `date` (required, ISO 8601 format)
  - [ ] `after` (optional, task ID to place after, empty = first position)
- [ ] Find task by ID and user ID
- [ ] Validate target date and after task (if provided)
- [ ] Update task's date and recalculate order numbers
- [ ] Handle moving between different dates
- [ ] Return updated task data

## Phase 7: Logging System

### 7.1 Logging Service Setup
- [ ] Create `src/Services/LoggingService.php`
- [ ] Configure Monolog with file handler
- [ ] Set log file location (OS-specific common location)
- [ ] Configure log rotation and retention

### 7.2 Request Logging Middleware
- [ ] Create `src/Middleware/LoggingMiddleware.php`:
  - [ ] Log all incoming requests with details
  - [ ] Log request method, URI, headers, body
  - [ ] Log user ID for authenticated requests
  - [ ] Log response status and execution time

### 7.3 Database Logging
- [ ] Add logging to all database operations:
  - [ ] Log SQL queries and parameters
  - [ ] Log number of affected rows
  - [ ] Log query execution time

### 7.4 Application Logging
- [ ] Add detailed logging throughout the application:
  - [ ] Authentication attempts and results
  - [ ] Email sending attempts and results
  - [ ] Task operations with full data
  - [ ] Error conditions and exceptions

## Phase 8: Input Validation & Error Handling

### 8.1 Validation Service
- [ ] Create `src/Services/ValidationService.php`:
  - [ ] Email format validation
  - [ ] Password strength validation
  - [ ] Full name validation (length, allowed characters)
  - [ ] Task title/description length validation
  - [ ] Date format validation (ISO 8601)
  - [ ] Daily task limit validation

### 8.2 Error Response Standardization
- [ ] Create `src/Utils/ResponseHelper.php`:
  - [ ] `success($data)` - Return standardized success response
  - [ ] `error($message, $statusCode)` - Return standardized error response
  - [ ] Ensure all responses follow the format: `{"result":"success/failure", "data/error":"..."}`

### 8.3 Exception Handling
- [ ] Create custom exception classes:
  - [ ] `ValidationException` - Input validation errors
  - [ ] `AuthenticationException` - Authentication failures
  - [ ] `AuthorizationException` - Permission denied
  - [ ] `NotFoundException` - Resource not found
- [ ] Set up global exception handler
- [ ] Map exceptions to appropriate HTTP status codes

## Phase 9: Testing

### 9.1 Unit Tests
- [ ] Set up PHPUnit configuration
- [ ] Create test database setup/teardown
- [ ] Write unit tests for:
  - [ ] JwtService methods
  - [ ] PasswordService methods
  - [ ] ValidationService methods
  - [ ] User model CRUD operations
  - [ ] Task model CRUD operations

### 9.2 Integration Tests
- [ ] Write integration tests for all API endpoints:
  - [ ] User registration flow
  - [ ] Email confirmation flow
  - [ ] Sign in/out flow
  - [ ] Password reset flow
  - [ ] Task CRUD operations
  - [ ] Task ordering operations

### 9.3 API Testing
- [ ] Create Postman collection or similar for manual testing
- [ ] Test all success scenarios
- [ ] Test all error scenarios
- [ ] Test authentication and authorization
- [ ] Test input validation

## Phase 10: Security & Performance

### 10.1 Security Hardening
- [ ] Implement rate limiting for authentication endpoints
- [ ] Add CSRF protection considerations
- [ ] Validate and sanitize all inputs
- [ ] Implement secure headers
- [ ] Review and test authentication flows

### 10.2 Performance Optimization
- [ ] Add database indexes for common queries
- [ ] Optimize SQL queries
- [ ] Implement connection pooling if needed
- [ ] Add query logging and performance monitoring

### 10.3 Security Testing
- [ ] Test for SQL injection vulnerabilities
- [ ] Test authentication bypass attempts
- [ ] Test authorization bypass attempts
- [ ] Test input validation edge cases

## Phase 11: Documentation & Deployment

### 11.1 API Documentation
- [ ] Create comprehensive API documentation
- [ ] Document all endpoints with examples
- [ ] Document authentication requirements
- [ ] Document error responses
- [ ] Create Postman collection for testing

### 11.2 Deployment Preparation
- [ ] Create deployment checklist
- [ ] Set up production environment variables
- [ ] Configure production logging
- [ ] Set up database backup strategy
- [ ] Configure web server (Apache/Nginx)

### 11.3 Monitoring & Maintenance
- [ ] Set up log monitoring
- [ ] Create health check endpoint
- [ ] Document maintenance procedures
- [ ] Create backup and restore procedures

## Phase 12: Final Testing & Launch

### 12.1 End-to-End Testing
- [ ] Test complete user registration and confirmation flow
- [ ] Test complete task management workflow
- [ ] Test error handling and edge cases
- [ ] Test with real email service
- [ ] Performance testing under load

### 12.2 Production Deployment
- [ ] Deploy to production environment
- [ ] Configure domain and SSL certificate
- [ ] Test all endpoints in production
- [ ] Monitor logs and performance
- [ ] Create production database backup

### 12.3 Post-Launch
- [ ] Monitor application performance
- [ ] Monitor error logs
- [ ] Gather user feedback
- [ ] Plan future enhancements

---

## Progress Tracking

**Overall Progress: 0/12 phases completed**

- [ ] Phase 1: Project Setup & Foundation
- [ ] Phase 2: Database Setup  
- [ ] Phase 3: Authentication System
- [ ] Phase 4: User Management & Authentication Endpoints
- [ ] Phase 5: Email Service
- [ ] Phase 6: Task Management System
- [ ] Phase 7: Logging System
- [ ] Phase 8: Input Validation & Error Handling
- [ ] Phase 9: Testing
- [ ] Phase 10: Security & Performance
- [ ] Phase 11: Documentation & Deployment
- [ ] Phase 12: Final Testing & Launch

## Notes

- Each checkbox represents a specific, actionable task
- Complete tasks in order within each phase for best results
- Test thoroughly after completing each major component
- Keep security and logging in mind throughout development
- Update this plan as needed based on implementation discoveries
