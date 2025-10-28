# Authentication Requirements

## Overview

The GrooDo API uses **JWT (JSON Web Token)** based authentication for securing endpoints. The API is completely stateless - no server-side sessions are maintained.

## Authentication Flow

### 1. User Registration
- **Endpoint**: `POST /api/users/signUp`
- **Purpose**: Create a new user account
- **Email Confirmation**: Required before account activation
- **Response**: User details (without sensitive data)

### 2. Email Confirmation
- **Endpoint**: `POST /api/users/confirmEmail`
- **Purpose**: Activate user account using email confirmation token
- **Token Expiration**: 1 hour
- **Response**: Account activation confirmation

### 3. Sign In
- **Endpoint**: `POST /api/users/signIn`
- **Purpose**: Authenticate user and receive JWT token
- **Requirements**: Confirmed email address
- **Response**: JWT token + user profile

### 4. Sign Out
- **Endpoint**: `POST /api/users/signOut`
- **Purpose**: Invalidate current JWT token
- **Authentication**: Required
- **Response**: Confirmation message

## JWT Token Details

### Token Properties
- **Algorithm**: HS256 (HMAC with SHA-256)
- **Expiration**: 7 days from issue time
- **Auto-Refresh**: Token is automatically refreshed on each authenticated request
- **Issuer**: `groodo-api`
- **Audience**: `groodo-app`

### Token Structure
```json
{
  "iss": "groodo-api",
  "aud": "groodo-app", 
  "iat": 1759046495,
  "exp": 1759132895,
  "user_id": 2
}
```

### Token Usage
Include the JWT token in the `Authorization` header of all protected requests:

```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

## Protected Endpoints

All endpoints requiring authentication will return `403 Forbidden` if:
- No `Authorization` header is provided
- Invalid or expired JWT token
- Token signature verification fails

### User Endpoints (Protected)
- `POST /api/users/signOut`
- `GET /api/users/profile`

### Task Endpoints (All Protected)
- `GET /api/tasks`
- `POST /api/tasks`
- `GET /api/task/:taskId`
- `PUT /api/task/:taskId`
- `DELETE /api/task/:taskId`
- `POST /api/task/:taskId/updateOrder`

## Password Security

### Password Requirements
- **Minimum Length**: 8 characters
- **Required Characters**: At least one letter AND one number
- **Hashing**: PHP's built-in `password_hash()` function with `PASSWORD_DEFAULT`
- **Verification**: PHP's built-in `password_verify()` function

### Password Reset Flow
1. **Request Reset**: `POST /api/users/resetPassword` with email
2. **Email Sent**: Password reset link with token (1-hour expiration)
3. **Reset Password**: Use token to set new password
4. **Token Invalidation**: Reset token is cleared after successful password change

## Security Features

### Bot Detection
- **User-Agent Validation**: Requests without proper User-Agent headers are blocked
- **Pattern Detection**: Common bot/crawler patterns are identified and blocked
- **Rate Limiting**: Authentication endpoints have rate limiting protection

### CORS Policy
- **Allowed Origins**: `*.greq.me` domains only
- **Allowed Methods**: GET, POST, PUT, DELETE, OPTIONS
- **Allowed Headers**: Content-Type, Authorization, X-Requested-With
- **Credentials**: Allowed for cross-origin requests

### Input Validation
- **Email Format**: RFC-compliant email validation
- **SQL Injection**: All database queries use prepared statements
- **XSS Prevention**: Input sanitization and output encoding
- **Data Validation**: Strict validation rules for all user inputs

## Error Responses

### Authentication Errors
```json
{
  "result": "failure",
  "error": "Authorization header required"
}
```

```json
{
  "result": "failure", 
  "error": "Invalid or expired token"
}
```

### Common Status Codes
- **200**: Success
- **400**: Bad Request (validation errors)
- **401**: Unauthorized (invalid credentials)
- **403**: Forbidden (missing/invalid token)
- **404**: Not Found
- **409**: Conflict (duplicate email)
- **500**: Internal Server Error

## Best Practices

### For API Consumers
1. **Store Tokens Securely**: Use secure storage mechanisms (not localStorage for sensitive apps)
2. **Handle Token Refresh**: Implement automatic token refresh logic
3. **Graceful Degradation**: Handle authentication failures gracefully
4. **HTTPS Only**: Always use HTTPS in production
5. **Token Expiration**: Check token expiration and refresh proactively

### Security Considerations
1. **Token Transmission**: Only send tokens over HTTPS
2. **Token Storage**: Avoid storing tokens in insecure locations
3. **Token Scope**: Tokens are user-specific and cannot access other users' data
4. **Session Management**: Implement proper sign-out functionality
5. **Error Handling**: Don't expose sensitive information in error messages

## Example Authentication Flow

```javascript
// 1. Sign In
const signInResponse = await fetch('/api/users/signIn', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'password123'
  })
});

const { data } = await signInResponse.json();
const token = data.token;

// 2. Use Token for Protected Requests
const tasksResponse = await fetch('/api/tasks', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});

// 3. Sign Out
await fetch('/api/users/signOut', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  }
});
```
