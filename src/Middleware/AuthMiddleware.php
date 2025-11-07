<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\JwtService;
use App\Models\User;
use App\Utils\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;

class AuthMiddleware implements MiddlewareInterface
{
    private JwtService $jwtService;
    private User $userModel;
    private ResponseHelper $responseHelper;
    private LoggerInterface $logger;

    public function __construct(
        JwtService $jwtService,
        User $userModel,
        ResponseHelper $responseHelper,
        LoggerInterface $logger
    ) {
        $this->jwtService = $jwtService;
        $this->userModel = $userModel;
        $this->responseHelper = $responseHelper;
        $this->logger = $logger;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $this->logger->debug('Processing authentication middleware');

        // Extract authorization header
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (empty($authHeader)) {
            $this->logger->warning('Missing authorization header');
            return $this->responseHelper->error('Authorization header required', 403);
        }

        // Extract token from header
        $token = $this->jwtService->extractTokenFromHeader($authHeader);
        
        if ($token === null) {
            $this->logger->warning('Invalid authorization header format');
            return $this->responseHelper->error('Invalid authorization header format', 403);
        }

        // Check if test token is enabled and matches
        $testTokenEnabled = filter_var($_ENV['AUTH_TOKEN_FOR_TESTS_ENABLED'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
        $testToken = $_ENV['AUTH_TOKEN_FOR_TESTS'] ?? null;

        if ($testTokenEnabled && $testToken !== null && $token === $testToken) {
            $this->logger->info('Test token authentication enabled and token matched');
            
            // Decode token to get user_id (test token is still a valid JWT)
            $validation = $this->jwtService->validateToken($token);
            
            if ($validation && $validation['valid']) {
                $userId = $validation['user_id'];
                
                // Try to get user from database, but don't fail if not found (for testing)
                $user = $this->userModel->findById($userId);
                
                // If user doesn't exist, create a minimal user object for testing
                if ($user === null) {
                    $this->logger->debug('Test user not found in database, using minimal user object', ['user_id' => $userId]);
                    $user = [
                        'id' => $userId,
                        'email' => 'test@example.com',
                        'full_name' => 'Test User',
                        'is_email_confirmed' => 1,
                        'auth_token' => $token,
                        'auth_expires_at' => null,
                        'created_at' => date('c'),
                        'updated_at' => date('c'),
                    ];
                }
                
                // Add user data to request attributes
                $request = $request->withAttribute('user_id', $userId);
                $request = $request->withAttribute('user', $user);
                $request = $request->withAttribute('auth_token', $token);

                $this->logger->debug('Test token authentication successful', [
                    'user_id' => $userId,
                    'email' => $user['email']
                ]);

                return $handler->handle($request);
            }
        }

        // Normal JWT token validation
        $validation = $this->jwtService->validateToken($token);
        
        if (!$validation || !$validation['valid']) {
            $error = $validation['error'] ?? 'Invalid token';
            $this->logger->warning('JWT token validation failed', [
                'error' => $error,
                'error_code' => $validation['error_code'] ?? 'UNKNOWN'
            ]);
            return $this->responseHelper->error($error, 403);
        }

        $userId = $validation['user_id'];
        
        // Verify user exists and token matches database
        $user = $this->userModel->findById($userId);
        
        if ($user === null) {
            $this->logger->warning('User not found for valid JWT token', ['user_id' => $userId]);
            return $this->responseHelper->error('User not found', 403);
        }

        // Check if user's auth token matches (optional additional security)
        if ($user['auth_token'] !== null && $user['auth_token'] !== $token) {
            $this->logger->warning('Token mismatch in database', ['user_id' => $userId]);
            return $this->responseHelper->error('Token invalid', 403);
        }

        // Check if database token is expired
        if ($user['auth_expires_at'] !== null && $this->userModel->isTokenExpired($user['auth_expires_at'])) {
            $this->logger->warning('Database token expired', [
                'user_id' => $userId,
                'expires_at' => $user['auth_expires_at']
            ]);
            return $this->responseHelper->error('Token expired', 403);
        }

        // Check if token is expiring soon and extend its validity to 7 days (604800 seconds) from now, without generating a new token
        if ($this->jwtService->isTokenExpiringSoon($validation['expires_at'])) {
            $this->logger->info('Token expiring soon, extending', ['user_id' => $userId]);

            $newExpiresAt = time() + 7 * 24 * 3600; // 7 days from now in seconds (UTC)
            $isoNewExpiresAt = gmdate('c', $newExpiresAt);

            // Update only the expiration in the database, keep the token the same
            $this->userModel->updateAuthToken(
                $userId,
                $token,
                $isoNewExpiresAt
            );

            $this->logger->info('Token expiration extended successfully', [
                'user_id' => $userId,
                'new_expires_at' => $isoNewExpiresAt
            ]);
        }

        // Add user data to request attributes
        $request = $request->withAttribute('user_id', $userId);
        $request = $request->withAttribute('user', $user);
        $request = $request->withAttribute('auth_token', $token);

        $this->logger->debug('Authentication successful', [
            'user_id' => $userId,
            'email' => $user['email']
        ]);

        return $handler->handle($request);
    }
}
