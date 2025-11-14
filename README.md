# GrooDo API

A secure RESTful API for calendar-based todo task management. GrooDo helps users organize their daily tasks by date with drag-and-drop ordering capabilities.

## What is GrooDo?

GrooDo is a calendar-based todo application that allows users to:
- **Organize tasks by date** - View and manage tasks in a calendar format
- **Project organization** - Group tasks into projects for better organization
- **Drag-and-drop ordering** - Easily reorder tasks within each day
- **Task hierarchies** - Create parent-child relationships between tasks
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
| `/api/tasks` | GET | List user's tasks (with date/project filtering) |
| `/api/tasks` | POST | Create a new task |
| `/api/task/:id` | GET | Get specific task details |
| `/api/task/:id` | PUT | Update task (title, description, completed) |
| `/api/task/:id` | DELETE | Delete task |
| `/api/task/:id/updateOrder` | POST | Move task to different position/date |
| `/api/task/:id/assign-project` | POST | Assign task to a project |
| `/api/task/:id/unassign-project` | POST | Remove task from project |
| `/api/task/:id/assign-parent` | POST | Assign task to a parent task |
| `/api/task/:id/unassign-parent` | POST | Remove task from parent |

### Project Management
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/projects` | GET | List user's projects |
| `/api/projects` | POST | Create a new project |
| `/api/project/:id` | GET | Get specific project details |
| `/api/project/:id` | PUT | Update project (full update) |
| `/api/project/:id` | PATCH | Update project (partial update) |
| `/api/project/:id` | DELETE | Delete project |
| `/api/project/:id/tasks` | GET | Get all tasks for a project |

### System
| Endpoint | Method | Description |
|----------|--------|-------------|
| `/health` | GET | API health check |

## Key Features

- **🔐 JWT Authentication** - Secure, stateless authentication
- **📅 Date-based Organization** - Tasks organized by calendar dates
- **📁 Project Management** - Organize tasks into projects with custom fields
- **🔄 Drag-and-Drop Support** - API endpoints for task reordering
- **🌳 Task Hierarchies** - Create parent-child relationships between tasks
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
