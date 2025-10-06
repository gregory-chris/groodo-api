# GrooDo API - Greq Tests

This folder contains Greq test files for the GrooDo API.

## Prerequisites

1. **Install Greq**: Follow instructions at [https://github.com/sgchris/greq](https://github.com/sgchris/greq)
2. **Start the API server**: 
   ```bash
   cd ../public
   php -S localhost:8000
   ```
3. **Run database migration**:
   ```bash
   cd ..
   php migrate.php
   ```

## Cleaning Test Data

Before running tests, clean up any existing test users from the database.

### Interactive Mode (with confirmation)
```bash
php clean-test-users.php
```

This will:
- Show all test users found in the database
- Ask for confirmation before deletion
- Display detailed output with colors
- Use transactions for safe deletion

### Automated Mode (no confirmation)
```bash
php clean-test-users-auto.php
```

This will:
- Automatically delete all test users
- Perfect for CI/CD pipelines and automated workflows
- Minimal output
- Fast execution

### Test Users Cleaned
Both scripts remove users with these email addresses:
- `testuser@example.com`
- `test@example.com`
- `invalid@example.com`
- `user@test.com`
- `demo@example.com`
- `newuser@example.com`
- `another@example.com`

## Running Tests

### Run Individual Tests

```bash
# Run signup test
greq 01-signup-success.greq --verbose

# Run duplicate email test
greq 02-signup-duplicate-email.greq --verbose

# Run invalid data test
greq 03-signup-invalid-data.greq --verbose
```

### Run All Tests

```bash
greq *.greq --verbose
```

### Using PowerShell Script

```powershell
# Clean and run all tests
.\run-tests.ps1 --clean

# Just run tests
.\run-tests.ps1
```

### Using Batch Script (Windows)

```cmd
REM Clean and run all tests
run-tests.bat --clean

REM Just run tests
run-tests.bat
```

## Test Files

### 01-signup-success.greq
- **Purpose**: Tests successful user registration
- **Test User**: testuser@example.com
- **Expected**: HTTP 200/201, success response
- **Dependencies**: None (runs independently)
- **What it validates**:
  - User creation with valid data
  - Response format matches API standard
  - All user fields are returned correctly
  - Email confirmation status is false initially
  - Response time under 3 seconds

### 02-signup-duplicate-email.greq
- **Purpose**: Tests duplicate email detection
- **Test User**: Attempts to reuse testuser@example.com
- **Expected**: HTTP 409, error response
- **Dependencies**: `01-signup-success.greq` (requires existing user)
- **What it validates**:
  - Duplicate email is rejected
  - Proper error message returned
  - HTTP status code 409 (Conflict)

### 03-signup-invalid-data.greq
- **Purpose**: Tests input validation
- **Test Data**: Invalid password (too short)
- **Expected**: HTTP 400, validation error
- **Dependencies**: None (runs independently)
- **What it validates**:
  - Password length validation
  - Password format validation
  - Proper validation error messages
  - No user data returned on failure

### 04-signin-success.greq
- **Purpose**: Tests sign-in with unconfirmed email (expected to fail)
- **Test User**: testuser@example.com (from 01-signup-success)
- **Expected**: HTTP 403, email not confirmed error
- **Dependencies**: `01-signup-success.greq`
- **What it validates**:
  - Sign-in is blocked for unconfirmed emails
  - Proper error message about email confirmation
  - No token returned on failure

### 05-signin-invalid-credentials.greq
- **Purpose**: Tests sign-in with wrong password
- **Test User**: testuser@example.com with wrong password
- **Expected**: HTTP 401, invalid credentials error
- **Dependencies**: None (runs independently)
- **What it validates**:
  - Invalid password is rejected
  - Proper error message returned
  - No token or user data returned
  - Security: doesn't reveal if email exists

### 06-signin-success-confirmed.greq
- **Purpose**: Tests successful sign-in after email confirmation
- **Test User**: testuser@example.com (email must be confirmed first)
- **Expected**: HTTP 200, success with JWT token
- **Dependencies**: `01-signup-success.greq`
- **Prerequisites**: Run `php confirm-test-user-email.php` first
- **What it validates**:
  - Successful authentication
  - JWT token is returned
  - Token expiration time is included
  - User data is returned
  - Email confirmation status is true

## Test Flow

### Basic Flow (Authentication Required)
```
Manual Cleanup
    ↓
php clean-test-users-auto.php
    ↓
01-signup-success.greq (Create testuser@example.com)
    ↓
02-signup-duplicate-email.greq (Test duplicate)
    ↓
04-signin-success.greq (Attempt signin - fails due to unconfirmed email)

03-signup-invalid-data.greq (Independent validation test)
05-signin-invalid-credentials.greq (Independent - wrong password)
```

### Full Sign-In Flow (With Email Confirmation)
```
Manual Cleanup
    ↓
php clean-test-users-auto.php
    ↓
01-signup-success.greq (Create testuser@example.com)
    ↓
04-signin-success.greq (Attempt signin - expect 403)
    ↓
php confirm-test-user-email.php (Manually confirm email)
    ↓
06-signin-success-confirmed.greq (Successful signin with token)
```

## Workflow Examples

### Fresh Test Run
```bash
# Clean database
php clean-test-users-auto.php

# Run all tests
greq *.greq --verbose
```

### Test Complete Authentication Flow
```bash
# 1. Clean database
php clean-test-users-auto.php

# 2. Create user
greq 01-signup-success.greq --verbose

# 3. Try to sign in (should fail - email not confirmed)
greq 04-signin-success.greq --verbose

# 4. Manually confirm email
php confirm-test-user-email.php

# 5. Sign in successfully
greq 06-signin-success-confirmed.greq --verbose
```

### Test Sign-In Failures
```bash
# Test unconfirmed email (403)
php clean-test-users-auto.php
greq 01-signup-success.greq
greq 04-signin-success.greq --verbose

# Test invalid password (401)
greq 05-signin-invalid-credentials.greq --verbose
```

### Debug Specific Test
```bash
# Clean database
php clean-test-users-auto.php

# Run one test with verbose output
greq 01-signup-success.greq --verbose

# Check API logs
tail -f ../public/logs/groodo-api-*.log
```

### Continuous Testing
```bash
# Watch mode (run after each file change)
while true; do
    clear
    php clean-test-users-auto.php
    greq *.greq
    sleep 5
done
```

## Environment

- **Host**: localhost:8000
- **Protocol**: HTTP (is-http: true)
- **Database**: SQLite at `../database/groodo-api.sqlite`
- **Logs**: `../public/logs/groodo-api-YYYY-MM-DD.log`

## Troubleshooting

### Test Fails with "Connection Refused"
**Cause**: PHP server is not running

**Solution**:
```bash
cd ../public
php -S localhost:8000
```

### Test Fails with "No such table: users"
**Cause**: Database tables not created

**Solution**:
```bash
cd ..
php migrate.php
```

### Test Fails with "Email already registered"
**Cause**: Test user already exists from previous run

**Solution**:
```bash
php clean-test-users-auto.php
```

### 500 Internal Server Error
**Cause**: Application error (multiple possible reasons)

**Solution**: Check the API logs
```bash
# View latest log file
cat ../public/logs/groodo-api-*.log | tail -100

# Watch logs in real-time
tail -f ../public/logs/groodo-api-*.log
```

### Test Times Out
**Cause**: Server is slow or not responding

**Solutions**:
- Check if server is running
- Check server load
- Increase timeout in greq files (add `timeout: 10000` for 10 seconds)

### Bot Detection Blocks Request
**Cause**: SecurityMiddleware detects missing User-Agent

**Solution**: This is expected for Greq tests. The middleware was temporarily disabled for signup/signin routes.

## Tips

1. **View Verbose Output**: Always use `--verbose` flag to see detailed request/response information
2. **Check Logs**: API logs contain full error details with stack traces
3. **Test Individually**: Run tests one by one when debugging specific features
4. **Clean Between Runs**: Use cleanup scripts to reset the database between test runs
5. **Use Transactions**: Cleanup scripts use transactions to ensure atomic operations
6. **Automate**: Use `clean-test-users-auto.php` in scripts and CI/CD pipelines

## File Structure

```
greq/
├── clean-test-users.php           # Interactive cleanup (with confirmation)
├── clean-test-users-auto.php      # Automated cleanup (no confirmation)
├── confirm-test-user-email.php    # Manually confirm test user email
├── 01-signup-success.greq         # User registration test
├── 02-signup-duplicate-email.greq # Duplicate email test
├── 03-signup-invalid-data.greq    # Validation test
├── 04-signin-success.greq         # Sign-in with unconfirmed email (403)
├── 05-signin-invalid-credentials.greq # Sign-in with wrong password (401)
├── 06-signin-success-confirmed.greq   # Sign-in after email confirmation (200)
├── run-tests.ps1                  # PowerShell test runner
├── run-tests.bat                  # Batch test runner
└── README.md                      # This file
```

## Adding New Tests

1. Create a new `.greq` file with a numbered prefix (e.g., `04-new-test.greq`)
2. Add test user emails to cleanup scripts if needed
3. Document the test in this README
4. Update dependencies if the test relies on other tests

## CI/CD Integration

```yaml
# Example GitHub Actions workflow
- name: Setup Database
  run: php migrate.php

- name: Clean Test Data
  run: php greq/clean-test-users-auto.php

- name: Run API Tests
  run: greq greq/*.greq --verbose
```

## Contributing

When adding new tests:
- Follow the existing naming convention
- Add comprehensive validation conditions
- Document dependencies clearly
- Update cleanup scripts if new test users are added
- Keep test data separate from production data
