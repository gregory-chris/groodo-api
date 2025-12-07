<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Services\JwtService;
use App\Services\PasswordService;
use App\Services\ValidationService;
use App\Services\EmailService;
use App\Utils\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class UserController
{
    private const MAX_SESSIONS_PER_USER = 6;

    private User $userModel;
    private JwtService $jwtService;
    private PasswordService $passwordService;
    private ValidationService $validationService;
    private EmailService $emailService;
    private ResponseHelper $responseHelper;
    private LoggerInterface $logger;

    public function __construct(
        User $userModel,
        JwtService $jwtService,
        PasswordService $passwordService,
        ValidationService $validationService,
        EmailService $emailService,
        ResponseHelper $responseHelper,
        LoggerInterface $logger
    ) {
        $this->userModel = $userModel;
        $this->jwtService = $jwtService;
        $this->passwordService = $passwordService;
        $this->validationService = $validationService;
        $this->emailService = $emailService;
        $this->responseHelper = $responseHelper;
        $this->logger = $logger;
    }

    /**
     * Extract client/session information from the request
     * Combines server-side captured info with optional client-provided deviceInfo
     */
    private function extractSessionData(Request $request, ?array $deviceInfo = null): array
    {
        $serverParams = $request->getServerParams();
        
        // Get IP address (check for proxy headers first)
        $ipAddress = $this->getClientIpAddress($request);
        
        // Get User-Agent
        $userAgent = $request->getHeaderLine('User-Agent') ?: null;
        
        // Get Accept-Language
        $acceptLanguage = $request->getHeaderLine('Accept-Language') ?: null;
        
        // Build session data array
        $sessionData = [
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent ? substr($userAgent, 0, 512) : null, // Limit length
            'accept_language' => $acceptLanguage ? substr($acceptLanguage, 0, 128) : null,
        ];
        
        // Add client-provided device info if available
        if ($deviceInfo !== null && is_array($deviceInfo)) {
            $sessionData['device_type'] = isset($deviceInfo['deviceType']) 
                ? $this->validationService->sanitizeInput((string)$deviceInfo['deviceType']) 
                : null;
            $sessionData['screen_width'] = isset($deviceInfo['screenWidth']) 
                ? (int)$deviceInfo['screenWidth'] 
                : null;
            $sessionData['screen_height'] = isset($deviceInfo['screenHeight']) 
                ? (int)$deviceInfo['screenHeight'] 
                : null;
            $sessionData['device_pixel_ratio'] = isset($deviceInfo['devicePixelRatio']) 
                ? (float)$deviceInfo['devicePixelRatio'] 
                : null;
            $sessionData['timezone'] = isset($deviceInfo['timezone']) 
                ? $this->validationService->sanitizeInput((string)$deviceInfo['timezone']) 
                : null;
            $sessionData['timezone_offset'] = isset($deviceInfo['timezoneOffset']) 
                ? (int)$deviceInfo['timezoneOffset'] 
                : null;
            $sessionData['platform'] = isset($deviceInfo['platform']) 
                ? $this->validationService->sanitizeInput((string)$deviceInfo['platform']) 
                : null;
            $sessionData['browser'] = isset($deviceInfo['browser']) 
                ? $this->validationService->sanitizeInput((string)$deviceInfo['browser']) 
                : null;
            $sessionData['browser_version'] = isset($deviceInfo['browserVersion']) 
                ? $this->validationService->sanitizeInput((string)$deviceInfo['browserVersion']) 
                : null;
        }
        
        return $sessionData;
    }

    /**
     * Get the client's IP address, checking proxy headers
     */
    private function getClientIpAddress(Request $request): ?string
    {
        // Check X-Forwarded-For header (may contain multiple IPs)
        $forwardedFor = $request->getHeaderLine('X-Forwarded-For');
        if (!empty($forwardedFor)) {
            // Take the first IP in the list (original client)
            $ips = explode(',', $forwardedFor);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        
        // Check X-Real-IP header
        $realIp = $request->getHeaderLine('X-Real-IP');
        if (!empty($realIp) && filter_var($realIp, FILTER_VALIDATE_IP)) {
            return $realIp;
        }
        
        // Fall back to REMOTE_ADDR
        $serverParams = $request->getServerParams();
        $remoteAddr = $serverParams['REMOTE_ADDR'] ?? null;
        if ($remoteAddr !== null && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            return $remoteAddr;
        }
        
        return null;
    }

    public function signUp(Request $request, Response $response): Response
    {
        $this->logger->info('User registration attempt started');

        try {
            $data = $request->getParsedBody();

            // Validate input data
            $validation = $this->validationService->validateUserRegistration($data);
            if (!$validation['valid']) {
                $this->logger->warning('User registration validation failed', [
                    'errors' => $validation['errors']
                ]);
                return $this->responseHelper->validationError($validation['errors']);
            }

            $email = $this->validationService->sanitizeInput($data['email']);
            $password = $data['password'];
            $fullName = $this->validationService->sanitizeInput($data['fullName']);

            // Check if email already exists
            if ($this->userModel->isEmailTaken($email)) {
                $this->logger->warning('Registration attempt with existing email', [
                    'email' => $email
                ]);
                return $this->responseHelper->error('Email already registered', 409);
            }

            // Hash password
            $passwordHash = $this->passwordService->hashPassword($password);

            // Generate email confirmation token
            $confirmationToken = $this->passwordService->generateEmailConfirmationToken();

            // Create user
            $userId = $this->userModel->createUser([
                'email' => $email,
                'full_name' => $fullName,
                'password_hash' => $passwordHash,
                'is_email_confirmed' => 0,
                'email_confirmation_token' => $confirmationToken,
            ]);

            // Send confirmation email (don't fail registration if email fails)
            try {
                $emailSent = $this->emailService->sendEmailConfirmation($email, $fullName, $confirmationToken);
                
                if (!$emailSent) {
                    $this->logger->warning('Failed to send confirmation email', [
                        'user_id' => $userId,
                        'email' => $email
                    ]);
                }
            } catch (\Exception $emailError) {
                $this->logger->warning('Email service error during registration', [
                    'user_id' => $userId,
                    'email' => $email,
                    'error' => $emailError->getMessage()
                ]);
                $emailSent = false;
            }

            $this->logger->info('User registered successfully', [
                'user_id' => $userId,
                'email' => $email,
                'email_sent' => $emailSent
            ]);

            return $this->responseHelper->created([
                'message' => 'User registered successfully. Please check your email to confirm your account.',
                'user' => [
                    'id' => $userId,
                    'email' => $email,
                    'fullName' => $fullName,
                    'isEmailConfirmed' => false
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('User registration failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Registration failed: ' . $e->getMessage());
        }
    }

    public function signIn(Request $request, Response $response): Response
    {
        $this->logger->info('User sign-in attempt started');

        try {
            $data = $request->getParsedBody();

            $email = $this->validationService->sanitizeInput($data['email'] ?? '');
            $password = $data['password'] ?? '';

            // Validate input
            if (empty($email) || empty($password)) {
                $this->logger->warning('Sign-in attempt with missing credentials');
                return $this->responseHelper->error('Email and password are required', 400);
            }

            // Find user by email (wrap in try-catch to handle database errors)
            try {
                $user = $this->userModel->findByEmail($email);
            } catch (\Exception $dbError) {
                $this->logger->error('Database error while finding user', [
                    'email' => $email,
                    'error' => $dbError->getMessage()
                ]);
                return $this->responseHelper->internalError('Sign-in failed. Please try again later.');
            }

            if ($user === null) {
                $this->logger->warning('Sign-in attempt with non-existent email', [
                    'email' => $email
                ]);
                return $this->responseHelper->error('Invalid email or password', 401);
            }

            // Verify user has required fields
            if (!isset($user['password_hash']) || !isset($user['id']) || !isset($user['is_email_confirmed'])) {
                $this->logger->error('User record is missing required fields', [
                    'user_id' => $user['id'] ?? 'unknown',
                    'email' => $email,
                    'missing_fields' => array_diff(['password_hash', 'id', 'is_email_confirmed'], array_keys($user))
                ]);
                return $this->responseHelper->internalError('User data is corrupted. Please contact support.');
            }

            // Verify password
            try {
                $passwordValid = $this->passwordService->verifyPassword($password, $user['password_hash']);
            } catch (\Exception $verifyError) {
                $this->logger->error('Error verifying password', [
                    'user_id' => $user['id'],
                    'email' => $email,
                    'error' => $verifyError->getMessage()
                ]);
                return $this->responseHelper->internalError('Sign-in failed. Please try again later.');
            }

            if (!$passwordValid) {
                $this->logger->warning('Sign-in attempt with invalid password', [
                    'user_id' => $user['id'],
                    'email' => $email
                ]);
                return $this->responseHelper->error('Invalid email or password', 401);
            }

            // Check if email is confirmed
            if (!$user['is_email_confirmed']) {
                $this->logger->warning('Sign-in attempt with unconfirmed email', [
                    'user_id' => $user['id'],
                    'email' => $email
                ]);
                return $this->responseHelper->error('Please confirm your email address before signing in', 403);
            }

            // Generate JWT token
            try {
                $tokenData = $this->jwtService->generateToken($user['id']);
            } catch (\Exception $tokenError) {
                $this->logger->error('Error generating JWT token', [
                    'user_id' => $user['id'],
                    'error' => $tokenError->getMessage()
                ]);
                return $this->responseHelper->internalError('Sign-in failed. Please try again later.');
            }

            // Create new session with multi-session support
            try {
                // Check current session count and enforce limit
                $sessionCount = $this->userModel->getSessionCount($user['id']);
                
                if ($sessionCount >= self::MAX_SESSIONS_PER_USER) {
                    // Delete the oldest session to make room for the new one
                    $deleted = $this->userModel->deleteOldestSession($user['id']);
                    
                    if (!$deleted) {
                        // Deletion failed - log warning but try to create session anyway
                        // This could happen due to race conditions where another request already deleted it
                        $this->logger->warning('Failed to delete oldest session, attempting to create anyway', [
                            'user_id' => $user['id'],
                            'session_count' => $sessionCount
                        ]);
                    } else {
                        $this->logger->info('Deleted oldest session due to limit', [
                            'user_id' => $user['id'],
                            'previous_count' => $sessionCount,
                            'max_allowed' => self::MAX_SESSIONS_PER_USER
                        ]);
                    }
                }
                
                // Extract session data from request
                $deviceInfo = $data['deviceInfo'] ?? null;
                $sessionData = $this->extractSessionData($request, $deviceInfo);
                
                // Create the new session
                $sessionId = $this->userModel->createSession(
                    $user['id'],
                    $tokenData['token'],
                    $tokenData['expires_at'],
                    $sessionData
                );
                
                $this->logger->debug('Session created', [
                    'session_id' => $sessionId,
                    'user_id' => $user['id'],
                    'ip_address' => $sessionData['ip_address'] ?? 'unknown',
                    'device_type' => $sessionData['device_type'] ?? 'unknown'
                ]);
            } catch (\Exception $sessionError) {
                // Session creation failed - this is a critical error
                // Without a session, the token will be rejected by AuthMiddleware
                $this->logger->error('Error creating session - sign-in failed', [
                    'user_id' => $user['id'],
                    'error' => $sessionError->getMessage()
                ]);
                return $this->responseHelper->internalError('Sign-in failed. Please try again later.');
            }

            $this->logger->info('User signed in successfully', [
                'user_id' => $user['id'],
                'email' => $email
            ]);

            return $this->responseHelper->success([
                'message' => 'Sign-in successful',
                'token' => $tokenData['token'],
                'expiresAt' => $tokenData['expires_at'],
                'expiresIn' => $tokenData['expires_in'],
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'fullName' => $user['full_name'],
                    'isEmailConfirmed' => (bool)$user['is_email_confirmed']
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('User sign-in failed with exception', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Sign-in failed. Please try again later.');
        }
    }

    public function signOut(Request $request, Response $response): Response
    {
        $this->logger->info('User sign-out attempt started');

        try {
            $userId = $request->getAttribute('user_id');
            $authToken = $request->getAttribute('auth_token');

            // Delete only the current session (not all sessions)
            if ($authToken) {
                $deleted = $this->userModel->deleteSessionByToken($authToken);
                
                if ($deleted) {
                    $this->logger->info('User session deleted successfully', [
                        'user_id' => $userId
                    ]);
                } else {
                    $this->logger->warning('Session not found for deletion', [
                        'user_id' => $userId
                    ]);
                }
            }

            $this->logger->info('User signed out successfully', [
                'user_id' => $userId
            ]);

            return $this->responseHelper->success([
                'message' => 'Sign-out successful'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('User sign-out failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Sign-out failed');
        }
    }

    /**
     * Sign out from all devices (delete all sessions for the user)
     */
    public function signOutAll(Request $request, Response $response): Response
    {
        $this->logger->info('User sign-out-all attempt started');

        try {
            $userId = $request->getAttribute('user_id');

            // Delete all sessions for this user
            $deletedCount = $this->userModel->deleteAllUserSessions($userId);

            $this->logger->info('User signed out from all devices', [
                'user_id' => $userId,
                'sessions_deleted' => $deletedCount
            ]);

            return $this->responseHelper->success([
                'message' => 'Signed out from all devices successfully',
                'sessionsDeleted' => $deletedCount
            ]);

        } catch (\Exception $e) {
            $this->logger->error('User sign-out-all failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Sign-out from all devices failed');
        }
    }

    public function confirmEmail(Request $request, Response $response): Response
    {
        $this->logger->info('Email confirmation attempt started');

        try {
            // Get token from query parameters
            $queryParams = $request->getQueryParams();
            $token = $this->validationService->sanitizeInput($queryParams['token'] ?? '');

            if (empty($token)) {
                $this->logger->warning('Email confirmation attempt without token');
                return $this->renderErrorPage($response, 'Missing Confirmation Token', 
                    'The confirmation link is invalid or incomplete. Please check your email and try again.');
            }

            // Find user by confirmation token
            $user = $this->userModel->findByEmailConfirmationToken($token);
            if ($user === null) {
                $this->logger->warning('Email confirmation attempt with invalid token', [
                    'token' => substr($token, 0, 8) . '...'
                ]);
                return $this->renderErrorPage($response, 'Invalid Confirmation Link', 
                    'This confirmation link is invalid or has already been used. Please try signing up again or contact support.');
            }

            // Check if email is already confirmed
            if ($user['is_email_confirmed']) {
                $this->logger->info('Email confirmation attempt for already confirmed email', [
                    'user_id' => $user['id']
                ]);
                // Redirect to homepage - already confirmed
                return $response
                    ->withHeader('Location', 'https://groodo.greq.me/?confirmed=already')
                    ->withStatus(302);
            }

            // Check token expiration (1 hour)
            $tokenExpiration = (int)($_ENV['EMAIL_TOKEN_EXPIRATION'] ?? 3600);
            if ($this->passwordService->isTokenExpired($user['updated_at'], $tokenExpiration)) {
                $this->logger->warning('Email confirmation attempt with expired token', [
                    'user_id' => $user['id']
                ]);
                return $this->renderErrorPage($response, 'Confirmation Link Expired', 
                    'This confirmation link has expired. For security reasons, confirmation links are only valid for 1 hour. Please sign up again or contact support.');
            }

            // Confirm email
            $this->userModel->confirmEmail($user['id']);

            $this->logger->info('Email confirmed successfully', [
                'user_id' => $user['id'],
                'email' => $user['email']
            ]);

            // Redirect to homepage with success message
            return $response
                ->withHeader('Location', 'https://groodo.greq.me/?confirmed=success')
                ->withStatus(302);

        } catch (\Exception $e) {
            $this->logger->error('Email confirmation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->renderErrorPage($response, 'Confirmation Failed', 
                'An unexpected error occurred while confirming your email. Please try again or contact support.');
        }
    }

    private function renderErrorPage(Response $response, string $title, string $message): Response
    {
        $html = "
        <!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title} - GrooDo</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .container {
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    max-width: 500px;
                    width: 100%;
                    padding: 40px;
                    text-align: center;
                }
                .icon {
                    font-size: 64px;
                    margin-bottom: 20px;
                }
                h1 {
                    color: #1a202c;
                    font-size: 28px;
                    font-weight: 600;
                    margin-bottom: 16px;
                }
                p {
                    color: #4a5568;
                    font-size: 16px;
                    line-height: 1.6;
                    margin-bottom: 30px;
                }
                .button {
                    display: inline-block;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 14px 32px;
                    text-decoration: none;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 600;
                    transition: transform 0.2s, box-shadow 0.2s;
                }
                .button:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                }
                .footer {
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #e2e8f0;
                    color: #718096;
                    font-size: 14px;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='icon'>⚠️</div>
                <h1>{$title}</h1>
                <p>{$message}</p>
                <a href='https://groodo.greq.me' class='button'>Go to GrooDo</a>
                <div class='footer'>
                    <strong>GrooDo</strong> - Organize your tasks, maximize your productivity
                </div>
            </div>
        </body>
        </html>
        ";

        $response->getBody()->write($html);
        return $response
            ->withHeader('Content-Type', 'text/html')
            ->withStatus(400);
    }

    public function resetPassword(Request $request, Response $response): Response
    {
        $this->logger->info('Password reset request started');

        try {
            $data = $request->getParsedBody();
            $email = $this->validationService->sanitizeInput($data['email'] ?? '');

            // Validate email
            $emailValidation = $this->validationService->validateEmail($email);
            if (!$emailValidation['valid']) {
                return $this->responseHelper->validationError($emailValidation['errors']);
            }

            // Find user by email
            $user = $this->userModel->findByEmail($email);
            if ($user === null) {
                // Don't reveal if email exists or not for security
                $this->logger->warning('Password reset request for non-existent email', [
                    'email' => $email
                ]);
                return $this->responseHelper->success([
                    'message' => 'If the email exists, a password reset link has been sent.'
                ]);
            }

            // Generate password reset token
            $resetToken = $this->passwordService->generatePasswordResetToken();

            // Save reset token
            $this->userModel->setPasswordResetToken($user['id'], $resetToken);

            // Send password reset email
            $emailSent = $this->emailService->sendPasswordReset($email, $user['full_name'], $resetToken);

            if (!$emailSent) {
                $this->logger->error('Failed to send password reset email', [
                    'user_id' => $user['id'],
                    'email' => $email
                ]);
                return $this->responseHelper->internalError('Failed to send password reset email');
            }

            $this->logger->info('Password reset email sent', [
                'user_id' => $user['id'],
                'email' => $email
            ]);

            return $this->responseHelper->success([
                'message' => 'If the email exists, a password reset link has been sent.'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Password reset request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Password reset request failed');
        }
    }

    public function getProfile(Request $request, Response $response): Response
    {
        $this->logger->info('Get user profile request started');

        try {
            $userId = $request->getAttribute('user_id');

            // Get user profile (sensitive data already filtered in model)
            $userProfile = $this->userModel->getUserProfile($userId);

            if ($userProfile === null) {
                $this->logger->warning('Profile request for non-existent user', [
                    'user_id' => $userId
                ]);
                return $this->responseHelper->notFound('User not found');
            }

            $this->logger->info('User profile retrieved successfully', [
                'user_id' => $userId
            ]);

            return $this->responseHelper->success($userProfile);

        } catch (\Exception $e) {
            $this->logger->error('Get user profile failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->responseHelper->internalError('Failed to retrieve user profile');
        }
    }
}
