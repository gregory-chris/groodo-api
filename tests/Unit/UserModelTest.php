<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    private User $userModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userModel = new User($this->database, $this->logger);
    }

    public function testCreateUser(): void
    {
        $userData = [
            'email' => 'test@example.com',
            'full_name' => 'Test User',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'is_email_confirmed' => 0,
            'email_confirmation_token' => 'test-token',
        ];

        $userId = $this->userModel->createUser($userData);

        $this->assertIsInt($userId);
        $this->assertGreaterThan(0, $userId);

        // Verify user was created in database
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'email' => 'test@example.com',
            'full_name' => 'Test User',
        ]);
    }

    public function testFindById(): void
    {
        $testUser = $this->createTestUser();

        $user = $this->userModel->findById($testUser['id']);

        $this->assertNotNull($user);
        $this->assertEquals($testUser['email'], $user['email']);
        $this->assertEquals($testUser['full_name'], $user['full_name']);
    }

    public function testFindByIdWithNonExistentUser(): void
    {
        $user = $this->userModel->findById(999);

        $this->assertNull($user);
    }

    public function testFindByEmail(): void
    {
        $testUser = $this->createTestUser(['email' => 'unique@example.com']);

        $user = $this->userModel->findByEmail('unique@example.com');

        $this->assertNotNull($user);
        $this->assertEquals($testUser['id'], $user['id']);
        $this->assertEquals('unique@example.com', $user['email']);
    }

    public function testFindByEmailWithNonExistentEmail(): void
    {
        $user = $this->userModel->findByEmail('nonexistent@example.com');

        $this->assertNull($user);
    }

    public function testFindByAuthToken(): void
    {
        $testUser = $this->createTestUser();
        $authToken = 'test-auth-token';
        
        // Update user with auth token
        $this->userModel->updateAuthToken($testUser['id'], $authToken, date('c', time() + 3600));

        $user = $this->userModel->findByAuthToken($authToken);

        $this->assertNotNull($user);
        $this->assertEquals($testUser['id'], $user['id']);
        $this->assertEquals($authToken, $user['auth_token']);
    }

    public function testFindByEmailConfirmationToken(): void
    {
        $confirmationToken = 'test-confirmation-token';
        $testUser = $this->createTestUser([
            'email_confirmation_token' => $confirmationToken,
            'is_email_confirmed' => 0
        ]);

        $user = $this->userModel->findByEmailConfirmationToken($confirmationToken);

        $this->assertNotNull($user);
        $this->assertEquals($testUser['id'], $user['id']);
        $this->assertEquals($confirmationToken, $user['email_confirmation_token']);
    }

    public function testUpdateUser(): void
    {
        $testUser = $this->createTestUser();

        $success = $this->userModel->updateUser($testUser['id'], [
            'full_name' => 'Updated Name'
        ]);

        $this->assertTrue($success);

        // Verify update in database
        $this->assertDatabaseHas('users', [
            'id' => $testUser['id'],
            'full_name' => 'Updated Name'
        ]);
    }

    public function testUpdateAuthToken(): void
    {
        $testUser = $this->createTestUser();
        $authToken = 'new-auth-token';
        $expiresAt = date('c', time() + 3600);

        $success = $this->userModel->updateAuthToken($testUser['id'], $authToken, $expiresAt);

        $this->assertTrue($success);

        // Verify update in database
        $this->assertDatabaseHas('users', [
            'id' => $testUser['id'],
            'auth_token' => $authToken,
            'auth_expires_at' => $expiresAt
        ]);
    }

    public function testClearAuthToken(): void
    {
        $testUser = $this->createTestUser();
        
        // First set an auth token
        $this->userModel->updateAuthToken($testUser['id'], 'some-token', date('c', time() + 3600));

        // Then clear it
        $success = $this->userModel->clearAuthToken($testUser['id']);

        $this->assertTrue($success);

        // Verify token was cleared
        $user = $this->userModel->findById($testUser['id']);
        $this->assertNull($user['auth_token']);
        $this->assertNull($user['auth_expires_at']);
    }

    public function testConfirmEmail(): void
    {
        $testUser = $this->createTestUser([
            'is_email_confirmed' => 0,
            'email_confirmation_token' => 'test-token'
        ]);

        $success = $this->userModel->confirmEmail($testUser['id']);

        $this->assertTrue($success);

        // Verify email was confirmed
        $this->assertDatabaseHas('users', [
            'id' => $testUser['id'],
            'is_email_confirmed' => 1
        ]);

        // Verify token was cleared
        $user = $this->userModel->findById($testUser['id']);
        $this->assertNull($user['email_confirmation_token']);
    }

    public function testIsEmailTaken(): void
    {
        $this->createTestUser(['email' => 'taken@example.com']);

        $this->assertTrue($this->userModel->isEmailTaken('taken@example.com'));
        $this->assertFalse($this->userModel->isEmailTaken('available@example.com'));
    }

    public function testGetUserProfile(): void
    {
        $testUser = $this->createTestUser([
            'email' => 'profile@example.com',
            'full_name' => 'Profile User'
        ]);

        $profile = $this->userModel->getUserProfile($testUser['id']);

        $this->assertNotNull($profile);
        $this->assertEquals($testUser['id'], $profile['id']);
        $this->assertEquals('profile@example.com', $profile['email']);
        $this->assertEquals('Profile User', $profile['fullName']);
        $this->assertTrue($profile['isEmailConfirmed']);

        // Verify sensitive data is not included
        $this->assertArrayNotHasKey('password_hash', $profile);
        $this->assertArrayNotHasKey('auth_token', $profile);
        $this->assertArrayNotHasKey('email_confirmation_token', $profile);
    }

    public function testGetUserProfileWithNonExistentUser(): void
    {
        $profile = $this->userModel->getUserProfile(999);

        $this->assertNull($profile);
    }

    public function testIsTokenExpired(): void
    {
        $expiredTime = date('c', time() - 3600); // 1 hour ago
        $validTime = date('c', time() + 3600); // 1 hour from now

        $this->assertTrue($this->userModel->isTokenExpired($expiredTime));
        $this->assertFalse($this->userModel->isTokenExpired($validTime));
    }

    public function testDeleteUser(): void
    {
        $testUser = $this->createTestUser();

        $success = $this->userModel->deleteUser($testUser['id']);

        $this->assertTrue($success);

        // Verify user was deleted
        $this->assertDatabaseMissing('users', [
            'id' => $testUser['id']
        ]);
    }

    public function testSetEmailConfirmationToken(): void
    {
        $testUser = $this->createTestUser();
        $token = 'new-confirmation-token';

        $success = $this->userModel->setEmailConfirmationToken($testUser['id'], $token);

        $this->assertTrue($success);

        // Verify token was set
        $this->assertDatabaseHas('users', [
            'id' => $testUser['id'],
            'email_confirmation_token' => $token
        ]);
    }

    public function testSetPasswordResetToken(): void
    {
        $testUser = $this->createTestUser();
        $token = 'password-reset-token';

        $success = $this->userModel->setPasswordResetToken($testUser['id'], $token);

        $this->assertTrue($success);

        // Verify token was set
        $this->assertDatabaseHas('users', [
            'id' => $testUser['id'],
            'password_reset_token' => $token
        ]);
    }

    public function testUpdatePassword(): void
    {
        $testUser = $this->createTestUser();
        $newPasswordHash = password_hash('newpassword123', PASSWORD_DEFAULT);

        $success = $this->userModel->updatePassword($testUser['id'], $newPasswordHash);

        $this->assertTrue($success);

        // Verify password was updated and reset token was cleared
        $user = $this->userModel->findById($testUser['id']);
        $this->assertEquals($newPasswordHash, $user['password_hash']);
        $this->assertNull($user['password_reset_token']);
    }
}
