<?php
declare(strict_types=1);

namespace App\Models;

use App\Utils\Database;
use Psr\Log\LoggerInterface;

class User extends BaseModel
{
    protected string $table = 'users';

    public function __construct(Database $database, LoggerInterface $logger)
    {
        parent::__construct($database, $logger);
    }

    public function findById(int $id): ?array
    {
        return $this->find($id);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }

    public function findByAuthToken(string $token): ?array
    {
        return $this->findBy('auth_token', $token);
    }

    public function findByEmailConfirmationToken(string $token): ?array
    {
        return $this->findBy('email_confirmation_token', $token);
    }

    public function findByPasswordResetToken(string $token): ?array
    {
        return $this->findBy('password_reset_token', $token);
    }

    public function createUser(array $userData): int
    {
        $this->logger->info('Creating new user', [
            'email' => $userData['email'],
            'full_name' => $userData['full_name']
        ]);

        return $this->create([
            'email' => $userData['email'],
            'full_name' => $userData['full_name'],
            'password_hash' => $userData['password_hash'],
            'is_email_confirmed' => $userData['is_email_confirmed'] ?? 0,
            'email_confirmation_token' => $userData['email_confirmation_token'] ?? null,
        ]);
    }

    public function updateUser(int $id, array $userData): bool
    {
        $this->logger->info('Updating user', ['id' => $id]);
        return $this->update($id, $userData);
    }

    public function updateAuthToken(int $id, string $token, string $expiresAt): bool
    {
        $this->logger->debug('Updating user auth token', ['user_id' => $id]);
        
        return $this->update($id, [
            'auth_token' => $token,
            'auth_expires_at' => $expiresAt,
        ]);
    }

    public function clearAuthToken(int $id): bool
    {
        $this->logger->debug('Clearing user auth token', ['user_id' => $id]);
        
        return $this->update($id, [
            'auth_token' => null,
            'auth_expires_at' => null,
        ]);
    }

    public function confirmEmail(int $id): bool
    {
        $this->logger->info('Confirming user email', ['user_id' => $id]);
        
        return $this->update($id, [
            'is_email_confirmed' => 1,
            'email_confirmation_token' => null,
        ]);
    }

    public function setEmailConfirmationToken(int $id, string $token): bool
    {
        $this->logger->debug('Setting email confirmation token', ['user_id' => $id]);
        
        return $this->update($id, [
            'email_confirmation_token' => $token,
        ]);
    }

    public function setPasswordResetToken(int $id, string $token): bool
    {
        $this->logger->debug('Setting password reset token', ['user_id' => $id]);
        
        return $this->update($id, [
            'password_reset_token' => $token,
        ]);
    }

    public function updatePassword(int $id, string $passwordHash): bool
    {
        $this->logger->info('Updating user password', ['user_id' => $id]);
        
        return $this->update($id, [
            'password_hash' => $passwordHash,
            'password_reset_token' => null,
        ]);
    }

    public function deleteUser(int $id): bool
    {
        $this->logger->warning('Deleting user', ['user_id' => $id]);
        return $this->delete($id);
    }

    public function isEmailTaken(string $email): bool
    {
        $user = $this->findByEmail($email);
        return $user !== null;
    }

    public function getUserProfile(int $id): ?array
    {
        $user = $this->findById($id);
        
        if ($user === null) {
            return null;
        }

        // Return user profile without sensitive data
        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'fullName' => $user['full_name'],
            'isEmailConfirmed' => (bool)$user['is_email_confirmed'],
            'createdAt' => $user['created_at'],
            'updatedAt' => $user['updated_at'],
        ];
    }

    public function isTokenExpired(string $expiresAt): bool
    {
        $expirationTime = strtotime($expiresAt);
        $currentTime = time();
        
        return $currentTime > $expirationTime;
    }

    public function cleanupExpiredTokens(): int
    {
        $this->logger->info('Cleaning up expired tokens');
        
        $currentTime = date('c');
        
        // Clear expired auth tokens
        $stmt = $this->database->query(
            "UPDATE {$this->table} SET auth_token = NULL, auth_expires_at = NULL WHERE auth_expires_at < ?",
            [$currentTime]
        );
        
        $clearedCount = $stmt->rowCount();
        
        $this->logger->info('Cleaned up expired tokens', ['count' => $clearedCount]);
        
        return $clearedCount;
    }
}
