# GrooDo RESTful API

Groodo is a todo app, but the tasks are assigned to a specific date. The view and the management are calendar-like, with columns representing a specific date, and the tasks are displayed in a user-defined order. The user may move the tasks between different dates and reorder tasks within a specific date using drag-n-drop.

This project is a server side of the Groodo app that is responsible for users/authentication and the tasks management. It provides a RESTful API with CRUD operations.

## The APIs

### Users / Authentication

- /api/users/signIn
- /api/users/signOut
- /api/users/signUp
- /api/users/resetPassword
- /api/users/confirmEmail
- GET /api/users/profile
    * Endpoint available only for authenticated users (otherwise 403)
    * User details (the basic details only, without password, tokens and expiration dates)

### Tasks

- GET /api/tasks
    * List tasks of the authenticated user. If the user is not logged in, respond with 403
    * available query parameters: from (ISO 8601), until (ISO 8601), limit, offset
- POST /api/tasks
- GET/PUT/DELETE /api/task/:taskId
- POST /api/task/:taskId/updateOrder
    'date' and 'after' must be set. if 'after' is empty, the task becomes the first task in that date

## The payloads

### Users/Authentication

Base profile structure
```json
{
    "id": 123,
    "email": "string",
    "fullName": "string",
    "password": "String",
    "isEmailConfirmed": "boolean",
    "authToken": "string",
    "authExpiresAt": "ISO 8601 datetime string",
    "emailConfirmationToken": "string",
    "passwordResetToken": "string",
    "createdAt": "ISO 8601 datetime string",
    "updatedAt": "ISO 8601 datetime string",
}
```

### Task

Base task structure
```json
{
    "id": 123,
    "userId": 456,
    "title": "sample title",
    "description": "sample title description",
    "date": "ISO 8601 datetime string",
    "order": 10,
    "completed": false,
    "createdAt": "ISO 8601 datetime string",
    "updatedAt": "ISO 8601 datetime string"
}
```

## Tech stack

- PHP (latest stable)
- Use composer
- Slim framework
- SQLite database

## Authentication & Security

- **Authentication**: JWT tokens (stateless service)
- **Password Hashing**: Use built-in PHP password hashing mechanism (password_hash/password_verify)
- **Token Expiration**:
    * Auth tokens: 1 day
    * Email confirmation/password reset tokens: 1 hour
- **Token Extension**: Every authenticated request extends the auth token to 1 day from current time
- **CORS**: Allow only from same domain. Service will be under "groodo-api.greq.me", allow from "*.greq.me" domains
- **Security Validations**: Basic validations on signIn/Out/Up requests to ensure requests come from real users/browsers, not bots/scripts

## Response Format

All responses must be in JSON format with standardized structure:
- **Success**: `{"result":"success", "data": {...}}`
- **Failure**: `{"result":"failure", "error":"error message"}`

## HTTP Status Codes

- **200**: All successful operations
- **400**: Bad request (validation errors, malformed data)
- **403**: Forbidden (authentication required, insufficient permissions)
- **404**: Not found (resource doesn't exist)
- **500**: Internal server error

## Database

- **File Location**: `/database/groodo-api.sqlite`
- **All datetime fields**: Stored in UTC

## Input Validation

### User Data
- **Email**: Must be valid email format
- **Password**: Minimum 8 characters, must contain letters and numbers
- **Full Name**: No special characters except dash (-) or space, maximum 40 characters

### Task Data
- **Title**: Maximum 256 characters
- **Description**: Maximum 2048 characters
- **Tasks per day**: Maximum 50 tasks per date

## Email Configuration

- **SMTP Client**: Gmail SMTP (placeholders for configuration)
- **Email Templates**: For confirmation and password reset
- **Configuration placeholders**:
    * SMTP_HOST=smtp.gmail.com
    * SMTP_PORT=587
    * SMTP_USERNAME=[sgchris@gmail.com]
    * SMTP_PASSWORD=[your-app-password]
    * SMTP_ENCRYPTION=tls

## Performance & Caching

- **Caching**: No caching implemented at this stage

## Things to consider

- Add logging 
    * place it in OS common location (e.g. in Windows it should be under "AppData/Local")
    * the log must be very verbose
        e.g. "received GET on tasks" + details, "fetched 1 record from the DB", "responded 200";
        "received add new task request, user id 45" "task details {title:"foo bar", description:"baz bat ...", ...}" (full JSON)
        etc.
        
        Log all the info and all the steps in every request
- Add basic security validations upon signIn/Out/Up requests. 
    * Make sure that the request looks like a request from a real user from a browser rather than by a bot/script. Just basic validations, don't make it too complicated.
