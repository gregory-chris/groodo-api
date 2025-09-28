<?php
declare(strict_types=1);

namespace Tests\Integration;

class UserEndpointsTest extends ApiTestCase
{
    public function testHealthEndpoint(): void
    {
        $response = $this->makeRequest('GET', '/health');
        $data = $this->assertSuccessResponse($response);
        
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('healthy', $data['status']);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('version', $data);
    }

    public function testUserSignUp(): void
    {
        $userData = [
            'email' => 'newuser@example.com',
            'fullName' => 'New User',
            'password' => 'password123'
        ];
        
        $response = $this->makeRequest('POST', '/api/users/signUp', [], $userData);
        $data = $this->assertSuccessResponse($response, 201);
        
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals('newuser@example.com', $data['user']['email']);
        $this->assertEquals('New User', $data['user']['fullName']);
        $this->assertFalse($data['user']['isEmailConfirmed']);
    }

    public function testUserSignUpWithInvalidEmail(): void
    {
        $userData = [
            'email' => 'invalid-email',
            'fullName' => 'Test User',
            'password' => 'password123'
        ];
        
        $response = $this->makeRequest('POST', '/api/users/signUp', [], $userData);
        $error = $this->assertErrorResponse($response, 400);
        
        $this->assertStringContainsString('Invalid email format', $error);
    }

    public function testUserSignUpWithWeakPassword(): void
    {
        $userData = [
            'email' => 'test@example.com',
            'fullName' => 'Test User',
            'password' => 'weak'
        ];
        
        $response = $this->makeRequest('POST', '/api/users/signUp', [], $userData);
        $error = $this->assertErrorResponse($response, 400);
        
        $this->assertStringContainsString('Password must be at least 8 characters long', $error);
    }

    public function testUserSignUpWithDuplicateEmail(): void
    {
        $userData = [
            'email' => 'duplicate@example.com',
            'fullName' => 'First User',
            'password' => 'password123'
        ];
        
        // Create first user
        $this->makeRequest('POST', '/api/users/signUp', [], $userData);
        
        // Try to create second user with same email
        $userData['fullName'] = 'Second User';
        $response = $this->makeRequest('POST', '/api/users/signUp', [], $userData);
        $error = $this->assertErrorResponse($response, 400);
        
        $this->assertStringContainsString('Email already exists', $error);
    }

    public function testUserSignIn(): void
    {
        // First create a user
        $userData = $this->createTestUserViaApi([
            'email' => 'signin@example.com',
            'password' => 'password123'
        ]);
        
        // Confirm email manually for testing
        $user = $this->database->query(
            "SELECT id FROM users WHERE email = ?", 
            [$userData['email']]
        )->fetch();
        
        $this->database->query(
            "UPDATE users SET is_email_confirmed = 1, email_confirmation_token = NULL WHERE id = ?",
            [$user['id']]
        );
        
        // Now sign in
        $response = $this->makeRequest('POST', '/api/users/signIn', [], [
            'email' => $userData['email'],
            'password' => $userData['password']
        ]);
        
        $data = $this->assertSuccessResponse($response);
        
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('expiresAt', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertEquals($userData['email'], $data['user']['email']);
    }

    public function testUserSignInWithUnconfirmedEmail(): void
    {
        $userData = $this->createTestUserViaApi([
            'email' => 'unconfirmed@example.com',
            'password' => 'password123'
        ]);
        
        $response = $this->makeRequest('POST', '/api/users/signIn', [], [
            'email' => $userData['email'],
            'password' => $userData['password']
        ]);
        
        $error = $this->assertErrorResponse($response, 403);
        $this->assertStringContainsString('Email not confirmed', $error);
    }

    public function testUserSignInWithInvalidCredentials(): void
    {
        $userData = $this->createTestUserViaApi([
            'email' => 'invalid@example.com',
            'password' => 'password123'
        ]);
        
        $response = $this->makeRequest('POST', '/api/users/signIn', [], [
            'email' => $userData['email'],
            'password' => 'wrongpassword'
        ]);
        
        $error = $this->assertErrorResponse($response, 403);
        $this->assertStringContainsString('Invalid credentials', $error);
    }

    public function testUserProfile(): void
    {
        // Create and confirm user
        $userData = $this->createTestUserViaApi([
            'email' => 'profile@example.com',
            'password' => 'password123'
        ]);
        
        $user = $this->database->query(
            "SELECT id FROM users WHERE email = ?", 
            [$userData['email']]
        )->fetch();
        
        $this->database->query(
            "UPDATE users SET is_email_confirmed = 1, email_confirmation_token = NULL WHERE id = ?",
            [$user['id']]
        );
        
        // Sign in to get token
        $token = $this->signInUser($userData['email'], $userData['password']);
        
        // Get profile
        $response = $this->makeAuthenticatedRequest('GET', '/api/users/profile', $token);
        $data = $this->assertSuccessResponse($response);
        
        $this->assertEquals($userData['email'], $data['email']);
        $this->assertEquals($userData['fullName'], $data['fullName']);
        $this->assertTrue($data['isEmailConfirmed']);
        
        // Ensure sensitive data is not included
        $this->assertArrayNotHasKey('password_hash', $data);
        $this->assertArrayNotHasKey('auth_token', $data);
    }

    public function testUserProfileWithoutAuthentication(): void
    {
        $response = $this->makeRequest('GET', '/api/users/profile');
        $error = $this->assertErrorResponse($response, 403);
        
        $this->assertStringContainsString('Authorization header missing', $error);
    }

    public function testUserSignOut(): void
    {
        // Create and confirm user
        $userData = $this->createTestUserViaApi([
            'email' => 'signout@example.com',
            'password' => 'password123'
        ]);
        
        $user = $this->database->query(
            "SELECT id FROM users WHERE email = ?", 
            [$userData['email']]
        )->fetch();
        
        $this->database->query(
            "UPDATE users SET is_email_confirmed = 1, email_confirmation_token = NULL WHERE id = ?",
            [$user['id']]
        );
        
        // Sign in to get token
        $token = $this->signInUser($userData['email'], $userData['password']);
        
        // Sign out
        $response = $this->makeAuthenticatedRequest('POST', '/api/users/signOut', $token);
        $data = $this->assertSuccessResponse($response);
        
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('signed out', $data['message']);
        
        // Verify token is no longer valid
        $response = $this->makeAuthenticatedRequest('GET', '/api/users/profile', $token);
        $this->assertErrorResponse($response, 403);
    }

    public function testPasswordResetRequest(): void
    {
        $userData = $this->createTestUserViaApi([
            'email' => 'reset@example.com',
            'password' => 'password123'
        ]);
        
        $response = $this->makeRequest('POST', '/api/users/resetPassword', [], [
            'email' => $userData['email']
        ]);
        
        $data = $this->assertSuccessResponse($response);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('password reset', $data['message']);
        
        // Verify reset token was set in database
        $this->assertDatabaseHas('users', [
            'email' => $userData['email']
        ]);
        
        $user = $this->database->query(
            "SELECT password_reset_token FROM users WHERE email = ?",
            [$userData['email']]
        )->fetch();
        
        $this->assertNotNull($user['password_reset_token']);
    }

    public function testPasswordResetWithNonExistentEmail(): void
    {
        $response = $this->makeRequest('POST', '/api/users/resetPassword', [], [
            'email' => 'nonexistent@example.com'
        ]);
        
        // Should still return success for security reasons
        $data = $this->assertSuccessResponse($response);
        $this->assertStringContainsString('password reset', $data['message']);
    }
}
