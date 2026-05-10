# GrooDo API - GitHub Copilot Instructions

## Project Context
- REST API for GrooDo, a calendar-based todo app.
- Stack: PHP 8.1+, Slim 4, SQLite via PDO, PHPUnit, Monolog.

## Non-Negotiable Rules

### Response Format
- Every API response must use this shape exactly:
- Success: `result = success`, `data = ...`
- Error: `result = failure`, `error = ...`

### Security
- Validate all input before processing.
- Use prepared statements for every database query.
- Hash passwords with `password_hash()`; never store plain text passwords.
- Never log passwords, tokens, or other sensitive data.
- Allow CORS only for `*.greq.me` origins.
- Validate JWT signatures on every authenticated request.
- Keep routes case-insensitive through the existing middleware.
- Do not expose internal details in error responses.

### Logging
- Log every request, authentication attempt, database operation, important business action, and unexpected error.
- Include useful context such as method, URI, user ID, affected record IDs, and result.

### PHP Standards
- Always use `declare(strict_types=1);`.
- Type-hint all parameters and return values.
- Follow PSR-12 with 4-space indentation.
- Keep naming consistent: PascalCase classes, camelCase methods and variables, UPPER_SNAKE_CASE constants, snake_case database columns, lowercase kebab-case routes.

### Dates and Persistence
- Use UTC for all timestamps.
- Store datetimes as ISO 8601 text.
- Use `gmdate('Y-m-d\TH:i:s\Z')` for current UTC timestamps.

### Error Handling
- Use these status codes consistently: `200`, `400`, `403`, `404`, `500`.
- Treat validation failures as `400`, auth or authorization failures as `403`, missing resources as `404`, and unexpected exceptions as `500`.

## Business Rules

### Tasks
- Maximum `50` tasks per user per date.
- Valid task statuses: `pending`, `completed`.
- Support reorder via `order_index` and moving tasks between dates.

### Authentication and Users
- JWT expiry is `24` hours and should extend on authenticated use.
- New accounts require email confirmation.
- Email confirmation tokens expire in `1` hour.
- Apply basic bot detection on auth endpoints.
- User email must be unique.
- Track email confirmation status, account creation time, and last login.

### Validation Limits
- Password minimum length: `8`, and it must contain letters and numbers.
- Full name maximum length: `40` and allow only letters, spaces, and dashes.
- Task title is required and has a maximum length of `256`.
- Task description is optional and has a maximum length of `2048`.
- Validate dates as ISO 8601 input.

## Architecture and Workflow
- Prefer constructor injection for services and controllers.
- Register dependencies in `src/dependencies.php`.
- Keep code in the existing structure under `src/Controllers`, `src/Models`, `src/Services`, `src/Middleware`, `src/Utils`, and `src/Exceptions`.
- When adding or changing behavior, update the relevant unit and integration tests.
- Update API documentation when request or response contracts change.

## Priority
- This is production code. Favor security, correctness, logging, tests, and maintainability over convenience.
