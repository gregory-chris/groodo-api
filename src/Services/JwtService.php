<?php
declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Psr\Log\LoggerInterface;

class JwtService
{
    private array $config;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->config = require __DIR__ . '/../../config/jwt.php';
    }

    public function generateToken(int $userId): array
    {
        $this->logger->debug('Generating JWT token', ['user_id' => $userId]);

        $issuedAt = time();
        $expirationTime = $issuedAt + $this->config['expiration'];
        
        $payload = [
            'iss' => 'groodo-api',
            'aud' => 'groodo-app',
            'iat' => $issuedAt,
            'exp' => $expirationTime,
            'user_id' => $userId,
        ];

        try {
            $token = JWT::encode($payload, $this->config['secret'], $this->config['algorithm']);
            $expiresAt = date('c', $expirationTime);

            $this->logger->info('JWT token generated successfully', [
                'user_id' => $userId,
                'expires_at' => $expiresAt
            ]);

            return [
                'token' => $token,
                'expires_at' => $expiresAt,
                'expires_in' => $this->config['expiration']
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate JWT token', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function validateToken(string $token): ?array
    {
        $this->logger->debug('Validating JWT token');

        try {
            // Set leeway for clock skew
            JWT::$leeway = $this->config['leeway'];
            
            $decoded = JWT::decode($token, new Key($this->config['secret'], $this->config['algorithm']));
            $payload = (array)$decoded;

            $this->logger->debug('JWT token validated successfully', [
                'user_id' => $payload['user_id'],
                'expires_at' => date('c', $payload['exp'])
            ]);

            return [
                'user_id' => $payload['user_id'],
                'issued_at' => $payload['iat'],
                'expires_at' => $payload['exp'],
                'valid' => true
            ];
        } catch (ExpiredException $e) {
            $this->logger->warning('JWT token expired', [
                'error' => $e->getMessage()
            ]);
            return [
                'valid' => false,
                'error' => 'Token expired',
                'error_code' => 'TOKEN_EXPIRED'
            ];
        } catch (SignatureInvalidException $e) {
            $this->logger->warning('JWT token signature invalid', [
                'error' => $e->getMessage()
            ]);
            return [
                'valid' => false,
                'error' => 'Invalid token signature',
                'error_code' => 'INVALID_SIGNATURE'
            ];
        } catch (\Exception $e) {
            $this->logger->error('JWT token validation failed', [
                'error' => $e->getMessage()
            ]);
            return [
                'valid' => false,
                'error' => 'Invalid token',
                'error_code' => 'INVALID_TOKEN'
            ];
        }
    }

    public function refreshToken(string $token): ?array
    {
        $this->logger->debug('Refreshing JWT token');

        $validation = $this->validateToken($token);
        
        if (!$validation || !$validation['valid']) {
            $this->logger->warning('Cannot refresh invalid token');
            return null;
        }

        $userId = $validation['user_id'];
        
        $this->logger->info('Refreshing token for user', ['user_id' => $userId]);
        
        return $this->generateToken($userId);
    }

    public function extractTokenFromHeader(string $authHeader): ?string
    {
        $this->logger->debug('Extracting token from authorization header');

        if (!str_starts_with($authHeader, 'Bearer ')) {
            $this->logger->warning('Invalid authorization header format');
            return null;
        }

        $token = substr($authHeader, 7); // Remove "Bearer " prefix
        
        if (empty($token)) {
            $this->logger->warning('Empty token in authorization header');
            return null;
        }

        return $token;
    }

    public function isTokenExpiringSoon(int $expirationTime, int $thresholdSeconds = 24 * 3600): bool // 24 hours
    {
        $currentTime = time();
        $timeUntilExpiration = $expirationTime - $currentTime;
        
        return $timeUntilExpiration <= $thresholdSeconds;
    }

    public function getTokenPayload(string $token): ?array
    {
        try {
            // Decode without verification for payload inspection
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return null;
            }

            $payload = json_decode(base64_decode($parts[1]), true);
            return $payload;
        } catch (\Exception $e) {
            $this->logger->debug('Failed to extract token payload', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function generateSecureSecret(int $length = 64): string
    {
        $this->logger->info('Generating secure JWT secret', ['length' => $length]);
        
        return bin2hex(random_bytes($length / 2));
    }
}
