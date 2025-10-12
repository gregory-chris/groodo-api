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

            // Update user's auth token in database
            try {
                $this->userModel->updateAuthToken(
                    $user['id'],
                    $tokenData['token'],
                    $tokenData['expires_at']
                );
            } catch (\Exception $updateError) {
                $this->logger->error('Error updating auth token', [
                    'user_id' => $user['id'],
                    'error' => $updateError->getMessage()
                ]);
                // Continue anyway - token was generated successfully
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

            // Clear auth token from database
            $this->userModel->clearAuthToken($userId);

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
