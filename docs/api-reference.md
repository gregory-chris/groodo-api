# GrooDo API Reference

## Overview

The GrooDo API is a RESTful web service for managing calendar-based todo tasks. It provides secure user authentication, task management, and comprehensive data validation.

**Base URL**: `https://groodo-api.greq.me` (production) | `http://localhost:8000` (development)

**Authentication**: [JWT Bearer Token](authentication.md) (see [Authentication Requirements](authentication.md))

**Response Format**: All responses follow a standardized JSON format:
```json
{
  "result": "success|failure",
  "data": { /* response data */ },
  "error": "error message (on failure)"
}
```

## Table of Contents

- [Health Check](#health-check)
- [User Management](#user-management)
- [Task Management](#task-management)
- [Error Handling](#error-handling)
- [Rate Limits](#rate-limits)

---

## Health Check

### Check API Health
Check if the API is running and healthy.

**Endpoint**: `GET /health`  
**Authentication**: None required

#### Response
```json
{
  "result": "success",
  "data": {
    "status": "healthy",
    "timestamp": "2025-09-28T08:00:00+00:00",
    "version": "1.0.0"
  }
}
```

---

## User Management

### User Registration
Create a new user account. Email confirmation is required before the account can be used.

**Endpoint**: `POST /api/users/signUp`  
**Authentication**: None required

#### Request Body
```json
{
  "email": "user@example.com",
  "fullName": "John Doe",
  "password": "password123"
}
```

#### Validation Rules
- **email**: Valid email format, unique across all users
- **fullName**: 2-40 characters, letters/spaces/dashes/apostrophes only
- **password**: Minimum 8 characters, must contain letters and numbers

#### Response (201 Created)
```json
{
  "result": "success",
  "data": {
    "message": "Registration successful. Please check your email to confirm your account.",
    "user": {
      "id": 1,
      "email": "user@example.com",
      "fullName": "John Doe",
      "isEmailConfirmed": false
    }
  }
}
```

#### Error Responses
- **400**: Validation errors, duplicate email
- **500**: Email sending failure (user still created)

---

### Email Confirmation
Confirm user email address using the token sent via email.

**Endpoint**: `POST /api/users/confirmEmail`  
**Authentication**: None required

#### Request Body
```json
{
  "token": "email_confirmation_token_here"
}
```

#### Response (200 OK)
```json
{
  "result": "success",
  "data": {
    "message": "Email confirmed successfully. You can now sign in."
  }
}
```

#### Error Responses
- **400**: Missing or invalid token
- **404**: Token not found or expired (1-hour expiration)

---

### User Sign In
Authenticate user and receive JWT token for accessing protected endpoints.

**Endpoint**: `POST /api/users/signIn`  
**Authentication**: None required

#### Request Body
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

#### Response (200 OK)
```json
{
  "result": "success",
  "data": {
    "message": "Sign-in successful",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "expiresAt": "2025-09-29T08:00:00+00:00",
    "expiresIn": 86400,
    "user": {
      "id": 1,
      "email": "user@example.com",
      "fullName": "John Doe",
      "isEmailConfirmed": true
    }
  }
}
```

#### Error Responses
- **401**: Invalid email or password
- **403**: Email not confirmed

---

### User Sign Out
Invalidate the current JWT token.

**Endpoint**: `POST /api/users/signOut`  
**Authentication**: Required

#### Response (200 OK)
```json
{
  "result": "success",
  "data": {
    "message": "Sign-out successful"
  }
}
```

---

### Get User Profile
Retrieve the current user's profile information.

**Endpoint**: `GET /api/users/profile`  
**Authentication**: Required

#### Response (200 OK)
```json
{
  "result": "success",
  "data": {
    "id": 1,
    "email": "user@example.com",
    "fullName": "John Doe",
    "isEmailConfirmed": true
  }
}
```

---

### Password Reset Request
Request a password reset email with a secure token.

**Endpoint**: `POST /api/users/resetPassword`  
**Authentication**: None required

#### Request Body
```json
{
  "email": "user@example.com"
}
```

#### Response (200 OK)
```json
{
  "result": "success",
  "data": {
    "message": "If an account with that email exists, a password reset link has been sent."
  }
}
```

**Note**: Response is always successful for security reasons, even if email doesn't exist.

---

## Task Management

All task endpoints require authentication. Tasks are private to each user.

### List Tasks
Retrieve tasks for the authenticated user with optional filtering and pagination.

**Endpoint**: `GET /api/tasks`  
**Authentication**: Required

#### Query Parameters
- `from` (optional): Start date filter (ISO 8601 format: YYYY-MM-DD)
- `until` (optional): End date filter (ISO 8601 format: YYYY-MM-DD)  
- `limit` (optional): Number of tasks to return (default: 100, max: 100)
- `offset` (optional): Number of tasks to skip (default: 0)

#### Example Request
```
GET /api/tasks?from=2025-09-28&until=2025-09-30&limit=10&offset=0
```

#### Response (200 OK)
```json
{
  "result": "success",
  "data": [
    {
      "id": 1,
      "userId": 1,
      "title": "Complete project documentation",
      "description": "Write comprehensive API documentation",
      "date": "2025-09-28",
      "order": 1,
      "completed": false,
      "createdAt": "2025-09-28T08:00:00+00:00",
      "updatedAt": "2025-09-28T08:00:00+00:00"
    }
  ]
}
```

---

### Create Task
Create a new task for the authenticated user.

**Endpoint**: `POST /api/tasks`  
**Authentication**: Required

#### Request Body
```json
{
  "title": "Complete project documentation",
  "description": "Write comprehensive API documentation",
  "date": "2025-09-28",
  "completed": false
}
```

#### Validation Rules
- **title**: Required, maximum 256 characters
- **description**: Optional, maximum 2048 characters
- **date**: Required, ISO 8601 date format (YYYY-MM-DD)
- **completed**: Optional, boolean (default: false)
- **Daily Limit**: Maximum 50 tasks per date per user

#### Response (201 Created)
```json
{
  "result": "success",
  "data": {
    "id": 1,
    "userId": 1,
    "title": "Complete project documentation",
    "description": "Write comprehensive API documentation",
    "date": "2025-09-28",
    "order": 1,
    "completed": false,
    "createdAt": "2025-09-28T08:00:00+00:00",
    "updatedAt": "2025-09-28T08:00:00+00:00"
  }
}
```

#### Error Responses
- **400**: Validation errors, daily task limit exceeded

---

### Get Single Task
Retrieve a specific task by ID (must belong to authenticated user).

**Endpoint**: `GET /api/task/{taskId}`  
**Authentication**: Required

#### Path Parameters
- `taskId`: Integer ID of the task

#### Response (200 OK)
```json
{
  "result": "success",
  "data": {
    "id": 1,
    "userId": 1,
    "title": "Complete project documentation",
    "description": "Write comprehensive API documentation",
    "date": "2025-09-28",
    "order": 1,
    "completed": false,
    "createdAt": "2025-09-28T08:00:00+00:00",
    "updatedAt": "2025-09-28T08:00:00+00:00"
  }
}
```

#### Error Responses
- **400**: Invalid task ID format
- **404**: Task not found or doesn't belong to user

---

### Update Task
Update an existing task (must belong to authenticated user).

**Endpoint**: `PUT /api/task/{taskId}`  
**Authentication**: Required

#### Path Parameters
- `taskId`: Integer ID of the task

#### Request Body
```json
{
  "title": "Updated task title",
  "description": "Updated description",
  "completed": true
}
```

#### Validation Rules
- **title**: Optional, maximum 256 characters
- **description**: Optional, maximum 2048 characters  
- **completed**: Optional, boolean

**Note**: Only provided fields will be updated. Date cannot be changed via this endpoint.

#### Response (200 OK)
```json
{
  "result": "success",
  "data": {
    "id": 1,
    "userId": 1,
    "title": "Updated task title",
    "description": "Updated description",
    "date": "2025-09-28",
    "order": 1,
    "completed": true,
    "createdAt": "2025-09-28T08:00:00+00:00",
    "updatedAt": "2025-09-28T08:15:00+00:00"
  }
}
```

#### Error Responses
- **400**: Invalid task ID or validation errors
- **404**: Task not found or doesn't belong to user

---

### Delete Task
Delete a task (must belong to authenticated user). Remaining tasks for that date will be automatically reordered.

**Endpoint**: `DELETE /api/task/{taskId}`  
**Authentication**: Required

#### Path Parameters
- `taskId`: Integer ID of the task

#### Response (200 OK)
```json
{
  "result": "success",
  "data": {
    "message": "Task deleted successfully",
    "deletedTask": {
      "id": 1,
      "title": "Deleted task title"
    }
  }
}
```

#### Error Responses
- **400**: Invalid task ID format
- **404**: Task not found or doesn't belong to user

---

### Update Task Order
Move a task to a different position or date. Supports drag-and-drop functionality.

**Endpoint**: `POST /api/task/{taskId}/updateOrder`  
**Authentication**: Required

#### Path Parameters
- `taskId`: Integer ID of the task to move

#### Request Body
```json
{
  "date": "2025-09-28",
  "after": 2
}
```

#### Parameters
- **date**: Required, ISO 8601 date format - target date for the task
- **after**: Optional, integer ID of task to place after (empty/null = first position)

#### Response (200 OK)
```json
{
  "result": "success",
  "data": {
    "id": 1,
    "userId": 1,
    "title": "Moved task",
    "description": "Task description",
    "date": "2025-09-28",
    "order": 2,
    "completed": false,
    "createdAt": "2025-09-28T08:00:00+00:00",
    "updatedAt": "2025-09-28T08:20:00+00:00"
  }
}
```

#### Error Responses
- **400**: Invalid task ID, date format, or after task ID
- **404**: Task not found, doesn't belong to user, or after task not found

---

## Error Handling

### Standard Error Response Format
```json
{
  "result": "failure",
  "error": "Error message describing what went wrong"
}
```

### Validation Error Response Format
```json
{
  "result": "failure",
  "error": "Validation failed",
  "validation_errors": [
    "Email is required",
    "Password must be at least 8 characters long"
  ]
}
```

### HTTP Status Codes

| Code | Meaning | Usage |
|------|---------|-------|
| 200 | OK | Successful GET, PUT, DELETE requests |
| 201 | Created | Successful POST requests (resource created) |
| 400 | Bad Request | Validation errors, malformed requests |
| 401 | Unauthorized | Invalid credentials |
| 403 | Forbidden | Missing/invalid authentication token |
| 404 | Not Found | Resource not found |
| 409 | Conflict | Duplicate resource (e.g., email already exists) |
| 500 | Internal Server Error | Server-side errors |

### Common Error Messages

#### Authentication Errors
- `"Authorization header required"`
- `"Invalid or expired token"`
- `"Invalid token signature"`
- `"Please confirm your email address before signing in"`

#### Validation Errors
- `"Email is required"`
- `"Invalid email format"`
- `"Password must be at least 8 characters long"`
- `"Task title is required"`
- `"Maximum 50 tasks allowed per day"`

#### Resource Errors
- `"Task not found"`
- `"Email already exists"`
- `"User not found"`

---

## Rate Limits

### Authentication Endpoints
- **Limit**: 10 requests per minute per IP address
- **Applies to**: `/api/users/signIn`, `/api/users/signUp`, `/api/users/resetPassword`
- **Response**: `429 Too Many Requests` when exceeded

### General API Endpoints
- **Limit**: 100 requests per minute per authenticated user
- **Applies to**: All other protected endpoints
- **Response**: `429 Too Many Requests` when exceeded

### Rate Limit Headers
```http
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1640995200
```

---

## CORS Policy

### Allowed Origins
- `*.greq.me` (production domains)
- `localhost:*` (development)

### Allowed Methods
- GET, POST, PUT, DELETE, OPTIONS

### Allowed Headers
- Content-Type, Authorization, X-Requested-With

---

## Data Formats

### Date Format
All dates use **ISO 8601 format**: `YYYY-MM-DD`
- Example: `"2025-09-28"`

### DateTime Format  
All timestamps use **ISO 8601 format with timezone**: `YYYY-MM-DDTHH:mm:ss+00:00`
- Example: `"2025-09-28T08:00:00+00:00"`
- Timezone: All timestamps are in UTC

### Boolean Values
- `true` or `false` (JSON boolean, not strings)

---

## Examples

### Complete Task Management Flow

```javascript
// 1. Sign in to get token
const signInResponse = await fetch('/api/users/signIn', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'password123'
  })
});
const { data: signInData } = await signInResponse.json();
const token = signInData.token;

// 2. Create a new task
const createResponse = await fetch('/api/tasks', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    title: 'Complete API documentation',
    description: 'Write comprehensive API docs',
    date: '2025-09-28'
  })
});
const { data: newTask } = await createResponse.json();

// 3. Get all tasks
const tasksResponse = await fetch('/api/tasks', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});
const { data: tasks } = await tasksResponse.json();

// 4. Update task
const updateResponse = await fetch(`/api/task/${newTask.id}`, {
  method: 'PUT',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    completed: true
  })
});

// 5. Move task to different position
const moveResponse = await fetch(`/api/task/${newTask.id}/updateOrder`, {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    date: '2025-09-28',
    after: null // Move to first position
  })
});
```

---

## Support

For questions, issues, or feature requests, please refer to the project documentation or contact the development team.

**API Version**: 1.0.0  
**Last Updated**: September 28, 2025
