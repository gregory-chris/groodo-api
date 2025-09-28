<?php
declare(strict_types=1);

namespace Tests;

use App\Utils\Database;
use App\Utils\Migration;
use App\Services\LoggingService;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected Database $database;
    protected \Psr\Log\LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create logger for testing
        $this->logger = LoggingService::createLogger();
        
        // Create database connection
        $this->database = new Database($this->logger);
        
        // Set up test database
        $this->setUpDatabase();
    }

    protected function tearDown(): void
    {
        // Clean up test database
        $this->tearDownDatabase();
        
        parent::tearDown();
    }

    protected function setUpDatabase(): void
    {
        // Create migration instance and set up tables
        $migration = new Migration($this->database, $this->logger);
        $migration->createTables();
    }

    protected function tearDownDatabase(): void
    {
        // Drop all tables after each test
        $migration = new Migration($this->database, $this->logger);
        $migration->dropTables();
    }

    protected function createTestUser(array $userData = []): array
    {
        $defaultData = [
            'email' => 'test@example.com',
            'full_name' => 'Test User',
            'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
            'is_email_confirmed' => 1,
            'email_confirmation_token' => null,
        ];

        $userData = array_merge($defaultData, $userData);

        $stmt = $this->database->query(
            "INSERT INTO users (email, full_name, password_hash, is_email_confirmed, email_confirmation_token, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $userData['email'],
                $userData['full_name'],
                $userData['password_hash'],
                $userData['is_email_confirmed'],
                $userData['email_confirmation_token'],
                date('c'),
                date('c')
            ]
        );

        $userId = (int)$this->database->lastInsertId();
        $userData['id'] = $userId;

        return $userData;
    }

    protected function createTestTask(int $userId, array $taskData = []): array
    {
        $defaultData = [
            'title' => 'Test Task',
            'description' => 'Test task description',
            'date' => '2025-09-28',
            'order_index' => 1,
            'completed' => 0,
        ];

        $taskData = array_merge($defaultData, $taskData);

        $stmt = $this->database->query(
            "INSERT INTO tasks (user_id, title, description, date, order_index, completed, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $userId,
                $taskData['title'],
                $taskData['description'],
                $taskData['date'],
                $taskData['order_index'],
                $taskData['completed'],
                date('c'),
                date('c')
            ]
        );

        $taskId = (int)$this->database->lastInsertId();
        $taskData['id'] = $taskId;
        $taskData['user_id'] = $userId;

        return $taskData;
    }

    protected function assertDatabaseHas(string $table, array $conditions): void
    {
        $whereClause = [];
        $params = [];
        
        foreach ($conditions as $column => $value) {
            $whereClause[] = "{$column} = ?";
            $params[] = $value;
        }

        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE " . implode(' AND ', $whereClause);
        $stmt = $this->database->query($sql, $params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertGreaterThan(0, $result['count'], "Failed asserting that table '{$table}' contains matching record.");
    }

    protected function assertDatabaseMissing(string $table, array $conditions): void
    {
        $whereClause = [];
        $params = [];
        
        foreach ($conditions as $column => $value) {
            $whereClause[] = "{$column} = ?";
            $params[] = $value;
        }

        $sql = "SELECT COUNT(*) as count FROM {$table} WHERE " . implode(' AND ', $whereClause);
        $stmt = $this->database->query($sql, $params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals(0, $result['count'], "Failed asserting that table '{$table}' does not contain matching record.");
    }
}
