<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ValidationService;
use App\Services\LoggingService;
use Tests\TestCase;

class ValidationServiceTest extends TestCase
{
    private ValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validationService = new ValidationService($this->logger);
    }

    public function testValidateEmailWithValidEmail(): void
    {
        $result = $this->validationService->validateEmail('test@example.com');
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateEmailWithInvalidEmail(): void
    {
        $result = $this->validationService->validateEmail('invalid-email');
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Invalid email format', $result['errors']);
    }

    public function testValidateEmailWithEmptyEmail(): void
    {
        $result = $this->validationService->validateEmail('');
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Email is required', $result['errors']);
    }

    public function testValidatePasswordWithValidPassword(): void
    {
        $result = $this->validationService->validatePassword('password123');
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidatePasswordWithShortPassword(): void
    {
        $result = $this->validationService->validatePassword('pass1');
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Password must be at least 8 characters long', $result['errors']);
    }

    public function testValidatePasswordWithoutNumbers(): void
    {
        $result = $this->validationService->validatePassword('password');
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Password must contain at least one number', $result['errors']);
    }

    public function testValidatePasswordWithoutLetters(): void
    {
        $result = $this->validationService->validatePassword('12345678');
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Password must contain at least one letter', $result['errors']);
    }

    public function testValidateFullNameWithValidName(): void
    {
        $result = $this->validationService->validateFullName('John Doe');
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateFullNameWithDash(): void
    {
        $result = $this->validationService->validateFullName('Mary-Jane Smith');
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateFullNameWithApostrophe(): void
    {
        $result = $this->validationService->validateFullName("O'Connor");
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateFullNameTooLong(): void
    {
        $longName = str_repeat('a', 41);
        $result = $this->validationService->validateFullName($longName);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Full name is too long (maximum 40 characters)', $result['errors']);
    }

    public function testValidateFullNameTooShort(): void
    {
        $result = $this->validationService->validateFullName('A');
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Full name is too short (minimum 2 characters)', $result['errors']);
    }

    public function testValidateTaskTitleWithValidTitle(): void
    {
        $result = $this->validationService->validateTaskTitle('Complete project');
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateTaskTitleTooLong(): void
    {
        $longTitle = str_repeat('a', 257);
        $result = $this->validationService->validateTaskTitle($longTitle);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Task title is too long (maximum 256 characters)', $result['errors']);
    }

    public function testValidateTaskTitleEmpty(): void
    {
        $result = $this->validationService->validateTaskTitle('');
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Task title is required', $result['errors']);
    }

    public function testValidateTaskDescriptionWithValidDescription(): void
    {
        $result = $this->validationService->validateTaskDescription('This is a valid description');
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateTaskDescriptionTooLong(): void
    {
        $longDescription = str_repeat('a', 50001);
        $result = $this->validationService->validateTaskDescription($longDescription);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Task description is too long (maximum 50000 characters)', $result['errors']);
    }

    public function testValidateTaskDescriptionNull(): void
    {
        $result = $this->validationService->validateTaskDescription(null);
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateDateWithValidDate(): void
    {
        $result = $this->validationService->validateDate('2025-09-28');
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateDateWithInvalidFormat(): void
    {
        $result = $this->validationService->validateDate('28/09/2025');
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Date must be in ISO 8601 format (YYYY-MM-DD)', $result['errors']);
    }

    public function testValidateDateWithInvalidDate(): void
    {
        $result = $this->validationService->validateDate('2025-02-30');
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Invalid date', $result['errors']);
    }

    public function testValidateTasksPerDayLimitWithinLimit(): void
    {
        $result = $this->validationService->validateTasksPerDayLimit(25);
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateTasksPerDayLimitExceeded(): void
    {
        $result = $this->validationService->validateTasksPerDayLimit(50);
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Maximum 50 tasks allowed per day', $result['errors']);
    }

    public function testValidatePaginationParamsWithValidParams(): void
    {
        $result = $this->validationService->validatePaginationParams('10', '20');
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals(10, $result['limit']);
        $this->assertEquals(20, $result['offset']);
    }

    public function testValidatePaginationParamsWithDefaults(): void
    {
        $result = $this->validationService->validatePaginationParams(null, null);
        
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals(100, $result['limit']);
        $this->assertEquals(0, $result['offset']);
    }

    public function testValidatePaginationParamsWithInvalidLimit(): void
    {
        $result = $this->validationService->validatePaginationParams('-5', '0');
        
        $this->assertFalse($result['valid']);
        $this->assertContains('Limit must be a positive integer', $result['errors']);
    }

    public function testIsValidIdWithValidId(): void
    {
        $this->assertTrue($this->validationService->isValidId('123'));
    }

    public function testIsValidIdWithInvalidId(): void
    {
        $this->assertFalse($this->validationService->isValidId('abc'));
        $this->assertFalse($this->validationService->isValidId('0'));
        $this->assertFalse($this->validationService->isValidId('-1'));
        $this->assertFalse($this->validationService->isValidId(null));
    }

    public function testSanitizeInput(): void
    {
        $input = "  \0 Hello World \0  ";
        $result = $this->validationService->sanitizeInput($input);
        
        $this->assertEquals('Hello World', $result);
    }
}
