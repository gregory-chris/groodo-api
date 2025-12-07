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

    // ========================================
    // Session Management Methods (Multi-Session Support)
    // ========================================

    /**
     * Create a new session for a user
     * 
     * @param int $userId The user ID
     * @param string $token The JWT token
     * @param string $expiresAt Token expiration timestamp (ISO 8601)
     * @param array $sessionData Additional session data (ip_address, user_agent, device_info, etc.)
     * @return int The created session ID
     */
    public function createSession(int $userId, string $token, string $expiresAt, array $sessionData = []): int
    {
        $this->logger->debug('Creating new session', ['user_id' => $userId]);

        $now = date('c');
        
        $data = [
            'user_id' => $userId,
            'token' => $token,
            'expires_at' => $expiresAt,
            'ip_address' => $sessionData['ip_address'] ?? null,
            'user_agent' => $sessionData['user_agent'] ?? null,
            'accept_language' => $sessionData['accept_language'] ?? null,
            'device_type' => $sessionData['device_type'] ?? null,
            'screen_width' => $sessionData['screen_width'] ?? null,
            'screen_height' => $sessionData['screen_height'] ?? null,
            'device_pixel_ratio' => $sessionData['device_pixel_ratio'] ?? null,
            'timezone' => $sessionData['timezone'] ?? null,
            'timezone_offset' => $sessionData['timezone_offset'] ?? null,
            'platform' => $sessionData['platform'] ?? null,
            'browser' => $sessionData['browser'] ?? null,
            'browser_version' => $sessionData['browser_version'] ?? null,
            'created_at' => $now,
            'last_active_at' => $now,
        ];

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $this->database->query(
            "INSERT INTO user_sessions ({$columns}) VALUES ({$placeholders})",
            array_values($data)
        );

        $sessionId = (int) $this->database->lastInsertId();
        
        $this->logger->info('Session created', [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'expires_at' => $expiresAt
        ]);

        return $sessionId;
    }

    /**
     * Find a session by its token
     * 
     * @param string $token The JWT token
     * @return array|null The session data or null if not found
     */
    public function findSessionByToken(string $token): ?array
    {
        $stmt = $this->database->query(
            "SELECT * FROM user_sessions WHERE token = ?",
            [$token]
        );
        
        $session = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $session !== false ? $session : null;
    }

    /**
     * Get all sessions for a user
     * 
     * @param int $userId The user ID
     * @return array List of sessions
     */
    public function getUserSessions(int $userId): array
    {
        $stmt = $this->database->query(
            "SELECT * FROM user_sessions WHERE user_id = ? ORDER BY created_at DESC",
            [$userId]
        );
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get the count of active sessions for a user
     * 
     * @param int $userId The user ID
     * @return int Number of active sessions
     */
    public function getSessionCount(int $userId): int
    {
        $stmt = $this->database->query(
            "SELECT COUNT(*) as count FROM user_sessions WHERE user_id = ?",
            [$userId]
        );
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Delete a session by its ID
     * 
     * @param int $sessionId The session ID
     * @return bool True if deleted
     */
    public function deleteSession(int $sessionId): bool
    {
        $this->logger->debug('Deleting session', ['session_id' => $sessionId]);
        
        $stmt = $this->database->query(
            "DELETE FROM user_sessions WHERE id = ?",
            [$sessionId]
        );
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a session by its token
     * 
     * @param string $token The JWT token
     * @return bool True if deleted
     */
    public function deleteSessionByToken(string $token): bool
    {
        $this->logger->debug('Deleting session by token');
        
        $stmt = $this->database->query(
            "DELETE FROM user_sessions WHERE token = ?",
            [$token]
        );
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete the oldest session for a user
     * Used to enforce the maximum sessions limit
     * 
     * @param int $userId The user ID
     * @return bool True if a session was deleted
     */
    public function deleteOldestSession(int $userId): bool
    {
        $this->logger->debug('Deleting oldest session', ['user_id' => $userId]);
        
        // Find the oldest session
        $stmt = $this->database->query(
            "SELECT id FROM user_sessions WHERE user_id = ? ORDER BY created_at ASC LIMIT 1",
            [$userId]
        );
        
        $session = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($session === false) {
            return false;
        }
        
        return $this->deleteSession((int) $session['id']);
    }

    /**
     * Delete all sessions for a user (sign out from all devices)
     * 
     * @param int $userId The user ID
     * @return int Number of sessions deleted
     */
    public function deleteAllUserSessions(int $userId): int
    {
        $this->logger->info('Deleting all sessions for user', ['user_id' => $userId]);
        
        $stmt = $this->database->query(
            "DELETE FROM user_sessions WHERE user_id = ?",
            [$userId]
        );
        
        $deletedCount = $stmt->rowCount();
        
        $this->logger->info('Deleted user sessions', [
            'user_id' => $userId,
            'count' => $deletedCount
        ]);
        
        return $deletedCount;
    }

    /**
     * Update the last_active_at timestamp for a session
     * 
     * @param int $sessionId The session ID
     * @return bool True if updated
     */
    public function updateSessionActivity(int $sessionId): bool
    {
        $stmt = $this->database->query(
            "UPDATE user_sessions SET last_active_at = ? WHERE id = ?",
            [date('c'), $sessionId]
        );
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Update the expiration time for a session (token extension)
     * 
     * @param string $token The JWT token
     * @param string $expiresAt New expiration timestamp (ISO 8601)
     * @return bool True if updated
     */
    public function updateSessionExpiration(string $token, string $expiresAt): bool
    {
        $this->logger->debug('Updating session expiration');
        
        $stmt = $this->database->query(
            "UPDATE user_sessions SET expires_at = ?, last_active_at = ? WHERE token = ?",
            [$expiresAt, date('c'), $token]
        );
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Check if a session is expired
     * 
     * @param string $expiresAt Expiration timestamp (ISO 8601)
     * @return bool True if expired
     */
    public function isSessionExpired(string $expiresAt): bool
    {
        $expirationTime = strtotime($expiresAt);
        $currentTime = time();
        
        return $currentTime > $expirationTime;
    }

    /**
     * Cleanup expired sessions from the database
     * 
     * @return int Number of sessions deleted
     */
    public function cleanupExpiredSessions(): int
    {
        $this->logger->info('Cleaning up expired sessions');
        
        $currentTime = date('c');
        
        $stmt = $this->database->query(
            "DELETE FROM user_sessions WHERE expires_at < ?",
            [$currentTime]
        );
        
        $deletedCount = $stmt->rowCount();
        
        $this->logger->info('Cleaned up expired sessions', ['count' => $deletedCount]);
        
        return $deletedCount;
    }

    // ========================================
    // End of Session Management Methods
    // ========================================

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
