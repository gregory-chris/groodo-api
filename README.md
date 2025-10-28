# GrooDo API

A secure RESTful API for calendar-based todo task management. GrooDo helps users organize their daily tasks by date with drag-and-drop ordering capabilities.

## What is GrooDo?

GrooDo is a calendar-based todo application that allows users to:
- **Organize tasks by date** - View and manage tasks in a calendar format
- **Drag-and-drop ordering** - Easily reorder tasks within each day
- **User accounts** - Secure registration and authentication system
- **Email notifications** - Account confirmation and password reset emails
- **Daily task limits** - Maximum 50 tasks per day to encourage focus

## API Endpoints

### Authentication
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/users/signUp` | POST | User registration with email confirmation |
| `/api/users/signIn` | POST | User authentication (returns JWT token) |
| `/api/users/signOut` | POST | Sign out and invalidate token |
| `/api/users/profile` | GET | Get current user profile |
| `/api/users/resetPassword` | POST | Request password reset email |

### Task Management
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/tasks` | GET | List user's tasks (with date filtering) |
| `/api/tasks` | POST | Create a new task |
| `/api/task/:id` | GET | Get specific task details |
| `/api/task/:id` | PUT | Update task (title, description, completed) |
| `/api/task/:id` | DELETE | Delete task |
| `/api/task/:id/updateOrder` | POST | Move task to different position/date |

### System
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/health` | GET | API health check |

## Key Features

- **🔐 JWT Authentication** - Secure, stateless authentication
- **📅 Date-based Organization** - Tasks organized by calendar dates
- **🔄 Drag-and-Drop Support** - API endpoints for task reordering
- **👤 User Management** - Complete user lifecycle management
- **🛡️ Security** - Input validation, rate limiting, CORS protection
- **📧 Email Integration** - Account confirmation and password reset

## Documentation

For detailed API documentation including request/response examples, authentication requirements, and error handling:

- **📚 [Complete API Reference](docs/api-reference.md)** - Comprehensive endpoint documentation
- **🔐 [Authentication Guide](docs/authentication.md)** - JWT authentication and security details

## Response Format

All API responses follow a consistent JSON format:

```json
{
  "result": "success|failure",
  "data": { /* response data */ },
  "error": "error message (on failure)"
}
```

## Authentication

The API uses JWT (JSON Web Token) authentication:
1. Sign up and confirm email address
2. Sign in to receive JWT token
3. Include token in `Authorization: Bearer <token>` header
4. Tokens auto-refresh on each request (7-day expiration)

## Base URL

- **Production**: `https://groodo-api.greq.me`
- **Development**: `http://localhost:8000`

---

For complete technical documentation, see the [API Reference](docs/api-reference.md) and [Authentication Guide](docs/authentication.md).
