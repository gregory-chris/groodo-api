# GrooDo API Development Plan

This document outlines the step-by-step implementation plan for the GrooDo RESTful API. Each section contains detailed tasks with checkboxes to track progress.

## Phase 1: Project Setup & Foundation

### 1.1 Project Structure Setup
- [x] Create project directory structure
  - [x] `/src` - Main application code
  - [x] `/src/Controllers` - API controllers
  - [x] `/src/Models` - Data models
  - [x] `/src/Middleware` - Custom middleware
  - [x] `/src/Services` - Business logic services
  - [x] `/src/Utils` - Utility classes
  - [x] `/config` - Configuration files
  - [x] `/database` - Database files and migrations
  - [x] `/logs` - Log files directory
  - [x] `/public` - Web server document root
  - [x] `/tests` - Unit and integration tests
  - [x] `/vendor` - Composer dependencies

### 1.2 Composer & Dependencies
- [x] Initialize composer project (`composer init`)
- [x] Install core dependencies:
  - [x] `slim/slim` - Slim Framework 4
  - [x] `slim/psr7` - PSR-7 implementation
  - [x] `firebase/php-jwt` - JWT token handling
  - [x] `phpmailer/phpmailer` - Email functionality
  - [x] `monolog/monolog` - Logging
  - [x] `vlucas/phpdotenv` - Environment variables
  - [x] `php-di/php-di` - Dependency injection
- [x] Install development dependencies:
  - [x] `phpunit/phpunit` - Testing framework
  - [x] `squizlabs/php_codesniffer` - Code standards

### 1.3 Configuration Files
- [x] Create `.env.example` with all required environment variables
- [x] Create `.env` file for local development
- [x] Create `config/database.php` - Database configuration
- [x] Create `config/jwt.php` - JWT configuration
- [x] Create `config/email.php` - Email configuration
- [x] Create `config/cors.php` - CORS configuration
- [x] Create `config/logging.php` - Logging configuration

### 1.4 Basic Application Structure
- [x] Create `public/index.php` - Application entry point
- [x] Create `src/dependencies.php` - Dependency injection configuration
- [x] Create `src/routes.php` - Route definitions
- [x] Set up Slim framework with dependency injection
- [ ] Configure error handling and logging
- [ ] Set up CORS middleware for `*.greq.me` domains

## Phase 2: Database Setup

### 2.1 Database Schema Design
- [x] Create SQLite database file at `/database/groodo-api.sqlite`
- [x] Design `users` table schema:
  - [x] `id` (INTEGER PRIMARY KEY AUTOINCREMENT)
  - [x] `email` (TEXT UNIQUE NOT NULL)
  - [x] `full_name` (TEXT NOT NULL)
  - [x] `password_hash` (TEXT NOT NULL)
  - [x] `is_email_confirmed` (INTEGER DEFAULT 0)
  - [x] `auth_token` (TEXT)
  - [x] `auth_expires_at` (TEXT)
  - [x] `email_confirmation_token` (TEXT)
  - [x] `password_reset_token` (TEXT)
  - [x] `created_at` (TEXT NOT NULL)
  - [x] `updated_at` (TEXT NOT NULL)

- [x] Design `tasks` table schema:
  - [x] `id` (INTEGER PRIMARY KEY AUTOINCREMENT)
  - [x] `user_id` (INTEGER NOT NULL)
  - [x] `title` (TEXT NOT NULL)
  - [x] `description` (TEXT)
  - [x] `date` (TEXT NOT NULL) - ISO 8601 date
  - [x] `order_index` (INTEGER NOT NULL)
  - [x] `completed` (INTEGER DEFAULT 0)
  - [x] `created_at` (TEXT NOT NULL)
  - [x] `updated_at` (TEXT NOT NULL)
  - [x] FOREIGN KEY constraint on `user_id`

### 2.2 Database Connection & Models
- [x] Create `src/Utils/Database.php` - PDO connection wrapper
- [x] Create `src/Models/BaseModel.php` - Base model with common functionality
- [x] Create `src/Models/User.php` - User model with CRUD operations
- [x] Create `src/Models/Task.php` - Task model with CRUD operations
- [x] Add database indexes for performance:
  - [x] Index on `users.email`
  - [x] Index on `users.auth_token`
  - [x] Index on `users.email_confirmation_token`
  - [x] Index on `users.password_reset_token`
  - [x] Index on `tasks.user_id`
  - [x] Index on `tasks.date`
  - [x] Composite index on `tasks.user_id, tasks.date, tasks.order_index`

### 2.3 Database Migration System
- [x] Create `src/Utils/Migration.php` - Simple migration system
- [x] Create migration runner script (`migrate.php`)
- [x] Successfully created database with all tables and indexes

## Phase 3: Authentication System

### 3.1 JWT Service
- [x] Create `src/Services/JwtService.php`:
  - [x] `generateToken($userId)` - Generate JWT with 1-day expiration
  - [x] `validateToken($token)` - Validate and decode JWT
  - [x] `refreshToken($token)` - Extend token expiration
  - [x] Handle token expiration and validation errors

### 3.2 Password Service
- [x] Create `src/Services/PasswordService.php`:
  - [x] `hashPassword($password)` - Hash password using PHP's password_hash()
  - [x] `verifyPassword($password, $hash)` - Verify password
  - [x] `validatePasswordStrength($password)` - Validate 8+ chars, letters + numbers

### 3.3 Authentication Middleware
- [x] Create `src/Middleware/AuthMiddleware.php`:
  - [x] Extract JWT from Authorization header
  - [x] Validate token and get user ID
  - [x] Extend token expiration on each request
  - [x] Add user data to request attributes
  - [x] Return 403 for invalid/missing tokens

### 3.4 Security Middleware
- [x] Create `src/Middleware/SecurityMiddleware.php`:
  - [x] Basic bot detection (User-Agent validation)
  - [x] Rate limiting for auth endpoints
  - [x] Request validation for auth operations

### 3.5 Additional Middleware & Utilities
- [x] Create `src/Middleware/CorsMiddleware.php` - CORS handling for *.greq.me domains
- [x] Create `src/Middleware/LoggingMiddleware.php` - Comprehensive request/response logging
- [x] Create `src/Utils/ResponseHelper.php` - Standardized JSON response formatting
- [x] Create `src/Services/ValidationService.php` - Input validation service

## Phase 4: User Management & Authentication Endpoints

### 4.1 User Controller Setup
- [x] Create `src/Controllers/UserController.php`
- [x] Implement standardized JSON response format
- [x] Add input validation helper methods

### 4.2 User Registration (`POST /api/users/signUp`)
- [x] Validate input data:
  - [x] Email format validation
  - [x] Password strength validation (8+ chars, letters + numbers)
  - [x] Full name validation (40 chars max, only dash/space special chars)
- [x] Check if email already exists
- [x] Hash password using PasswordService
- [x] Generate email confirmation token (1-hour expiration)
- [x] Save user to database
- [x] Send confirmation email (graceful error handling)
- [x] Return success response with user data (excluding sensitive fields)

### 4.3 Email Confirmation (`POST /api/users/confirmEmail`)
- [x] Validate confirmation token
- [x] Check token expiration (1 hour)
- [x] Update user's `is_email_confirmed` status
- [x] Clear confirmation token
- [x] Return success response

### 4.4 User Sign In (`POST /api/users/signIn`)
- [x] Validate email format
- [x] Find user by email
- [x] Verify password using PasswordService
- [x] Check if email is confirmed
- [x] Generate JWT token (1-day expiration)
- [x] Update user's auth token and expiration
- [x] Return success response with token and user data

### 4.5 User Sign Out (`POST /api/users/signOut`)
- [x] Validate JWT token
- [x] Clear user's auth token in database
- [x] Return success response

### 4.6 Password Reset Request (`POST /api/users/resetPassword`)
- [x] Validate email format
- [x] Find user by email
- [x] Generate password reset token (1-hour expiration)
- [x] Save token to database
- [x] Send password reset email
- [x] Return success response

### 4.7 User Profile (`GET /api/users/profile`)
- [x] Require authentication (use AuthMiddleware)
- [x] Get user data from database
- [x] Return user profile (exclude password, tokens, expiration dates)
- [x] Extend auth token expiration

### 4.8 Email Service Implementation
- [x] Create `src/Services/EmailService.php`
- [x] Configure PHPMailer with Gmail SMTP
- [x] Create HTML and text email templates
- [x] Implement confirmation and password reset emails
- [x] Add graceful error handling for email failures

### 4.9 API Testing & Validation
- [x] Successfully tested user registration
- [x] Successfully tested email confirmation
- [x] Successfully tested user sign-in with JWT
- [x] Successfully tested authenticated endpoints
- [x] Successfully tested task creation and retrieval
- [x] Verified all middleware working correctly

## Phase 5: Email Service ✅ COMPLETED

### 5.1 Email Service Setup
- [x] Create `src/Services/EmailService.php`:
  - [x] Configure PHPMailer with Gmail SMTP
  - [x] Use environment variables for credentials
  - [x] Set up TLS encryption and authentication

### 5.2 Email Templates
- [x] Create email confirmation template:
  - [x] HTML and plain text versions
  - [x] Include confirmation link with token
  - [x] Professional styling
- [x] Create password reset template:
  - [x] HTML and plain text versions
  - [x] Include reset link with token
  - [x] Security warnings and instructions

### 5.3 Email Sending Methods
- [x] `sendEmailConfirmation($user, $token)` - Send confirmation email
- [x] `sendPasswordReset($user, $token)` - Send password reset email
- [x] Add error handling and logging for email failures

## Phase 6: Task Management System (Partially Completed)

### 6.1 Task Controller Setup
- [x] Create `src/Controllers/TaskController.php`
- [x] Add authentication requirement for all endpoints
- [x] Implement input validation methods

### 6.2 List Tasks (`GET /api/tasks`)
- [x] Require authentication
- [x] Parse query parameters:
  - [x] `from` (ISO 8601 date) - optional
  - [x] `until` (ISO 8601 date) - optional
  - [x] `limit` (integer) - optional, default 100
  - [x] `offset` (integer) - optional, default 0
- [x] Validate date formats
- [x] Query tasks for authenticated user with filters
- [x] Order by date ASC, then by order ASC
- [x] Return paginated results

### 6.3 Create Task (`POST /api/tasks`)
- [x] Require authentication
- [x] Validate input data:
  - [x] Title (required, max 256 chars)
  - [x] Description (optional, max 2048 chars)
  - [x] Date (required, ISO 8601 format)
- [x] Check daily task limit (50 tasks per day)
- [x] Calculate next order number for the date
- [x] Create task in database
- [x] Return created task data

### 6.4 Get Single Task (`GET /api/task/:taskId`) - NEEDS IMPLEMENTATION
- [ ] Require authentication
- [ ] Validate task ID format
- [ ] Find task by ID and user ID
- [ ] Return 404 if task not found or doesn't belong to user
- [ ] Return task data

### 6.5 Update Task (`PUT /api/task/:taskId`) - NEEDS IMPLEMENTATION
- [ ] Require authentication
- [ ] Validate task ID and input data
- [ ] Find task by ID and user ID
- [ ] Return 404 if task not found or doesn't belong to user
- [ ] Validate updated data (title, description, completed status)
- [ ] Update task in database
- [ ] Return updated task data

### 6.6 Delete Task (`DELETE /api/task/:taskId`) - NEEDS IMPLEMENTATION
- [ ] Require authentication
- [ ] Validate task ID format
- [ ] Find task by ID and user ID
- [ ] Return 404 if task not found or doesn't belong to user
- [ ] Delete task from database
- [ ] Reorder remaining tasks for that date
- [ ] Return success response

### 6.7 Update Task Order (`POST /api/task/:taskId/updateOrder`) - NEEDS IMPLEMENTATION
- [ ] Require authentication
- [ ] Validate input data:
  - [ ] `date` (required, ISO 8601 format)
  - [ ] `after` (optional, task ID to place after, empty = first position)
- [ ] Find task by ID and user ID
- [ ] Validate target date and after task (if provided)
- [ ] Update task's date and recalculate order numbers
- [ ] Handle moving between different dates
- [ ] Return updated task data

## Phase 7: Logging System ✅ COMPLETED

### 7.1 Logging Service Setup
- [x] Create `src/Services/LoggingService.php`
- [x] Configure Monolog with file handler
- [x] Set log file location (OS-specific common location)
- [x] Configure log rotation and retention

### 7.2 Request Logging Middleware
- [x] Create `src/Middleware/LoggingMiddleware.php`:
  - [x] Log all incoming requests with details
  - [x] Log request method, URI, headers, body
  - [x] Log user ID for authenticated requests
  - [x] Log response status and execution time

### 7.3 Database Logging
- [x] Add logging to all database operations:
  - [x] Log SQL queries and parameters
  - [x] Log number of affected rows
  - [x] Log query execution time

### 7.4 Application Logging
- [x] Add detailed logging throughout the application:
  - [x] Authentication attempts and results
  - [x] Email sending attempts and results
  - [x] Task operations with full data
  - [x] Error conditions and exceptions

## Phase 8: Input Validation & Error Handling ✅ COMPLETED

### 8.1 Validation Service
- [x] Create `src/Services/ValidationService.php`:
  - [x] Email format validation
  - [x] Password strength validation
  - [x] Full name validation (length, allowed characters)
  - [x] Task title/description length validation
  - [x] Date format validation (ISO 8601)
  - [x] Daily task limit validation

### 8.2 Error Response Standardization
- [x] Create `src/Utils/ResponseHelper.php`:
  - [x] `success($data)` - Return standardized success response
  - [x] `error($message, $statusCode)` - Return standardized error response
  - [x] Ensure all responses follow the format: `{"result":"success/failure", "data/error":"..."}`

### 8.3 Exception Handling
- [x] Comprehensive exception handling implemented in all controllers
- [x] Proper HTTP status code mapping (200, 400, 403, 404, 500)
- [x] Detailed error logging with context
- [x] User-friendly error messages for API consumers

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
- [x] Implement rate limiting for authentication endpoints
- [ ] Add CSRF protection considerations
- [x] Validate and sanitize all inputs
- [ ] Implement secure headers
- [x] Review and test authentication flows

### 10.2 Performance Optimization
- [x] Add database indexes for common queries
- [x] Optimize SQL queries
- [ ] Implement connection pooling if needed
- [x] Add query logging and performance monitoring

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
- [x] Set up log monitoring (comprehensive logging implemented)
- [x] Create health check endpoint
- [ ] Document maintenance procedures
- [ ] Create backup and restore procedures

## Phase 12: Final Testing & Launch

### 12.1 End-to-End Testing
- [x] Test complete user registration and confirmation flow
- [x] Test complete task management workflow (basic CRUD)
- [x] Test error handling and edge cases
- [ ] Test with real email service (currently using placeholders)
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

**Overall Progress: 7/12 phases completed**

- [x] Phase 1: Project Setup & Foundation ✅
- [x] Phase 2: Database Setup ✅
- [x] Phase 3: Authentication System ✅
- [x] Phase 4: User Management & Authentication Endpoints ✅
- [x] Phase 5: Email Service ✅
- [ ] Phase 6: Task Management System (Partially completed - basic endpoints working, need individual task CRUD)
- [x] Phase 7: Logging System ✅
- [x] Phase 8: Input Validation & Error Handling ✅
- [ ] Phase 9: Testing (Manual testing completed, need automated tests)
- [ ] Phase 10: Security & Performance (Partially completed - basic security implemented)
- [ ] Phase 11: Documentation & Deployment (Health endpoint implemented)
- [ ] Phase 12: Final Testing & Launch (Basic testing completed)

## Remaining Tasks Summary

### High Priority (Core Functionality)
1. **Complete Task CRUD Operations** (Phase 6):
   - Implement GET /api/task/:taskId
   - Implement PUT /api/task/:taskId  
   - Implement DELETE /api/task/:taskId
   - Implement POST /api/task/:taskId/updateOrder

### Medium Priority (Quality & Testing)
2. **Automated Testing** (Phase 9):
   - Set up PHPUnit configuration
   - Write unit tests for services and models
   - Write integration tests for API endpoints

3. **Security Enhancements** (Phase 10):
   - Add CSRF protection
   - Implement secure headers
   - Security vulnerability testing

### Low Priority (Production Readiness)
4. **Documentation & Deployment** (Phase 11):
   - Create comprehensive API documentation
   - Set up production deployment procedures
   - Configure web server settings

5. **Production Testing** (Phase 12):
   - Test with real email service
   - Performance testing under load
   - Production deployment and monitoring

## Notes

- Each checkbox represents a specific, actionable task
- Complete tasks in order within each phase for best results
- Test thoroughly after completing each major component
- Keep security and logging in mind throughout development
- Update this plan as needed based on implementation discoveries
