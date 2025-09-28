<?php
declare(strict_types=1);

namespace App\Services;

use Psr\Log\LoggerInterface;

class PasswordService
{
    private LoggerInterface $logger;
    private int $minLength;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->minLength = (int)($_ENV['PASSWORD_MIN_LENGTH'] ?? 8);
    }

    public function hashPassword(string $password): string
    {
        $this->logger->debug('Hashing password');

        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            
            if ($hash === false) {
                $this->logger->error('Password hashing failed');
                throw new \RuntimeException('Password hashing failed');
            }

            $this->logger->debug('Password hashed successfully');
            return $hash;
        } catch (\Exception $e) {
            $this->logger->error('Password hashing error', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        $this->logger->debug('Verifying password');

        try {
            $isValid = password_verify($password, $hash);
            
            $this->logger->debug('Password verification completed', [
                'valid' => $isValid
            ]);
            
            return $isValid;
        } catch (\Exception $e) {
            $this->logger->error('Password verification error', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function validatePasswordStrength(string $password): array
    {
        $this->logger->debug('Validating password strength');

        $errors = [];

        // Check minimum length
        if (strlen($password) < $this->minLength) {
            $errors[] = "Password must be at least {$this->minLength} characters long";
        }

        // Check for at least one letter
        if (!preg_match('/[a-zA-Z]/', $password)) {
            $errors[] = "Password must contain at least one letter";
        }

        // Check for at least one number
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }

        $isValid = empty($errors);

        $this->logger->debug('Password strength validation completed', [
            'valid' => $isValid,
            'error_count' => count($errors)
        ]);

        return [
            'valid' => $isValid,
            'errors' => $errors
        ];
    }

    public function needsRehash(string $hash): bool
    {
        $this->logger->debug('Checking if password hash needs rehashing');

        $needsRehash = password_needs_rehash($hash, PASSWORD_DEFAULT);
        
        $this->logger->debug('Password rehash check completed', [
            'needs_rehash' => $needsRehash
        ]);

        return $needsRehash;
    }

    public function generateSecureToken(int $length = 32): string
    {
        $this->logger->debug('Generating secure token', ['length' => $length]);

        try {
            $token = bin2hex(random_bytes($length));
            
            $this->logger->debug('Secure token generated successfully');
            return $token;
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate secure token', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function generateEmailConfirmationToken(): string
    {
        $this->logger->debug('Generating email confirmation token');
        return $this->generateSecureToken(32);
    }

    public function generatePasswordResetToken(): string
    {
        $this->logger->debug('Generating password reset token');
        return $this->generateSecureToken(32);
    }

    public function isTokenExpired(string $createdAt, int $expirationSeconds): bool
    {
        $createdTime = strtotime($createdAt);
        $currentTime = time();
        $expirationTime = $createdTime + $expirationSeconds;

        $isExpired = $currentTime > $expirationTime;

        $this->logger->debug('Token expiration check', [
            'created_at' => $createdAt,
            'expiration_seconds' => $expirationSeconds,
            'is_expired' => $isExpired
        ]);

        return $isExpired;
    }

    public function generateStrongPassword(int $length = 12): string
    {
        $this->logger->debug('Generating strong password', ['length' => $length]);

        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $symbols = '!@#$%^&*()_+-=[]{}|;:,.<>?';

        // Ensure at least one character from each category
        $password = '';
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $symbols[random_int(0, strlen($symbols) - 1)];

        // Fill the rest with random characters
        $allChars = $lowercase . $uppercase . $numbers . $symbols;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Shuffle the password
        $password = str_shuffle($password);

        $this->logger->debug('Strong password generated successfully');
        return $password;
    }

    public function getPasswordStrengthScore(string $password): array
    {
        $score = 0;
        $feedback = [];

        // Length check
        $length = strlen($password);
        if ($length >= 8) $score += 1;
        if ($length >= 12) $score += 1;
        if ($length >= 16) $score += 1;

        // Character variety checks
        if (preg_match('/[a-z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Add lowercase letters';
        }

        if (preg_match('/[A-Z]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Add uppercase letters';
        }

        if (preg_match('/[0-9]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Add numbers';
        }

        if (preg_match('/[^a-zA-Z0-9]/', $password)) {
            $score += 1;
        } else {
            $feedback[] = 'Add special characters';
        }

        // Determine strength level
        $strength = match(true) {
            $score <= 2 => 'weak',
            $score <= 4 => 'fair',
            $score <= 6 => 'good',
            default => 'strong'
        };

        return [
            'score' => $score,
            'max_score' => 7,
            'strength' => $strength,
            'feedback' => $feedback
        ];
    }
}
