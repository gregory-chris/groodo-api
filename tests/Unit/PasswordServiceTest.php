<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PasswordService;
use Tests\TestCase;

class PasswordServiceTest extends TestCase
{
    private PasswordService $passwordService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->passwordService = new PasswordService($this->logger);
    }

    public function testHashPassword(): void
    {
        $password = 'password123';
        $hash = $this->passwordService->hashPassword($password);
        
        $this->assertNotEmpty($hash);
        $this->assertNotEquals($password, $hash);
        $this->assertTrue(password_verify($password, $hash));
    }

    public function testVerifyPasswordWithCorrectPassword(): void
    {
        $password = 'password123';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $result = $this->passwordService->verifyPassword($password, $hash);
        
        $this->assertTrue($result);
    }

    public function testVerifyPasswordWithIncorrectPassword(): void
    {
        $password = 'password123';
        $wrongPassword = 'wrongpassword';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $result = $this->passwordService->verifyPassword($wrongPassword, $hash);
        
        $this->assertFalse($result);
    }

    public function testValidatePasswordStrengthWithValidPassword(): void
    {
        $result = $this->passwordService->validatePasswordStrength('password123');
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidatePasswordStrengthWithShortPassword(): void
    {
        $result = $this->passwordService->validatePasswordStrength('pass1');
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Password must be at least 8 characters long', $result['errors']);
    }

    public function testValidatePasswordStrengthWithoutLetters(): void
    {
        $result = $this->passwordService->validatePasswordStrength('12345678');
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Password must contain at least one letter', $result['errors']);
    }

    public function testValidatePasswordStrengthWithoutNumbers(): void
    {
        $result = $this->passwordService->validatePasswordStrength('password');
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Password must contain at least one number', $result['errors']);
    }

    public function testNeedsRehash(): void
    {
        $password = 'password123';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $result = $this->passwordService->needsRehash($hash);
        
        // With current PHP version and default algorithm, this should be false
        $this->assertFalse($result);
    }

    public function testGenerateSecureToken(): void
    {
        $token = $this->passwordService->generateSecureToken(32);
        
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex characters
    }

    public function testGenerateEmailConfirmationToken(): void
    {
        $token = $this->passwordService->generateEmailConfirmationToken();
        
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex characters
    }

    public function testGeneratePasswordResetToken(): void
    {
        $token = $this->passwordService->generatePasswordResetToken();
        
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex characters
    }

    public function testIsTokenExpiredWithExpiredToken(): void
    {
        $createdAt = date('c', time() - 7200); // 2 hours ago
        $expirationSeconds = 3600; // 1 hour
        
        $result = $this->passwordService->isTokenExpired($createdAt, $expirationSeconds);
        
        $this->assertTrue($result);
    }

    public function testIsTokenExpiredWithValidToken(): void
    {
        $createdAt = date('c', time() - 1800); // 30 minutes ago
        $expirationSeconds = 3600; // 1 hour
        
        $result = $this->passwordService->isTokenExpired($createdAt, $expirationSeconds);
        
        $this->assertFalse($result);
    }

    public function testGenerateStrongPassword(): void
    {
        $password = $this->passwordService->generateStrongPassword(12);
        
        $this->assertEquals(12, strlen($password));
        
        // Test that it contains required character types
        $this->assertMatchesRegularExpression('/[a-z]/', $password, 'Password should contain lowercase letters');
        $this->assertMatchesRegularExpression('/[A-Z]/', $password, 'Password should contain uppercase letters');
        $this->assertMatchesRegularExpression('/[0-9]/', $password, 'Password should contain numbers');
        $this->assertMatchesRegularExpression('/[^a-zA-Z0-9]/', $password, 'Password should contain special characters');
    }

    public function testGetPasswordStrengthScoreWithWeakPassword(): void
    {
        $result = $this->passwordService->getPasswordStrengthScore('abc');
        
        $this->assertEquals('weak', $result['strength']);
        $this->assertLessThanOrEqual(2, $result['score']);
        $this->assertNotEmpty($result['feedback']);
    }

    public function testGetPasswordStrengthScoreWithStrongPassword(): void
    {
        $result = $this->passwordService->getPasswordStrengthScore('MyStr0ngP@ssw0rd!');
        
        $this->assertEquals('strong', $result['strength']);
        $this->assertGreaterThanOrEqual(6, $result['score']);
    }

    public function testGenerateUniqueTokens(): void
    {
        $token1 = $this->passwordService->generateSecureToken();
        $token2 = $this->passwordService->generateSecureToken();
        
        $this->assertNotEquals($token1, $token2, 'Generated tokens should be unique');
    }
}
