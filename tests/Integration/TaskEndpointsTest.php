<?php
declare(strict_types=1);

namespace Tests\Integration;

class TaskEndpointsTest extends ApiTestCase
{
    private string $userToken;
    private array $testUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create and confirm a test user for all task tests
        $this->testUser = $this->createTestUserViaApi([
            'email' => 'taskuser@example.com',
            'password' => 'password123'
        ]);
        
        // Confirm email manually
        $user = $this->database->query(
            "SELECT id FROM users WHERE email = ?", 
            [$this->testUser['email']]
        )->fetch();
        
        $this->database->query(
            "UPDATE users SET is_email_confirmed = 1, email_confirmation_token = NULL WHERE id = ?",
            [$user['id']]
        );
        
        // Get auth token
        $this->userToken = $this->signInUser($this->testUser['email'], $this->testUser['password']);
    }

    public function testCreateTask(): void
    {
        $taskData = [
            'title' => 'Test Task',
            'description' => 'This is a test task',
            'date' => '2025-09-28'
        ];
        
        $response = $this->makeAuthenticatedRequest('POST', '/api/tasks', $this->userToken, $taskData);
        $data = $this->assertSuccessResponse($response, 201);
        
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals($taskData['title'], $data['title']);
        $this->assertEquals($taskData['description'], $data['description']);
        $this->assertEquals($taskData['date'], $data['date']);
        $this->assertEquals(1, $data['order']);
        $this->assertFalse($data['completed']);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertArrayHasKey('updatedAt', $data);
    }

    public function testCreateTaskWithoutAuthentication(): void
    {
        $taskData = [
            'title' => 'Test Task',
            'date' => '2025-09-28'
        ];
        
        $response = $this->makeRequest('POST', '/api/tasks', [], $taskData);
        $error = $this->assertErrorResponse($response, 403);
        
        $this->assertStringContainsString('Authorization header missing', $error);
    }

    public function testCreateTaskWithInvalidData(): void
    {
        $taskData = [
            'title' => '', // Empty title
            'date' => '2025-09-28'
        ];
        
        $response = $this->makeAuthenticatedRequest('POST', '/api/tasks', $this->userToken, $taskData);
        $error = $this->assertErrorResponse($response, 400);
        
        $this->assertStringContainsString('Task title is required', $error);
    }

    public function testCreateTaskWithInvalidDate(): void
    {
        $taskData = [
            'title' => 'Test Task',
            'date' => 'invalid-date'
        ];
        
        $response = $this->makeAuthenticatedRequest('POST', '/api/tasks', $this->userToken, $taskData);
        $error = $this->assertErrorResponse($response, 400);
        
        $this->assertStringContainsString('Date must be in ISO 8601 format', $error);
    }

    public function testGetTasks(): void
    {
        // Create some test tasks
        $task1Data = [
            'title' => 'First Task',
            'description' => 'First task description',
            'date' => '2025-09-28'
        ];
        
        $task2Data = [
            'title' => 'Second Task',
            'description' => 'Second task description',
            'date' => '2025-09-29'
        ];
        
        $this->makeAuthenticatedRequest('POST', '/api/tasks', $this->userToken, $task1Data);
        $this->makeAuthenticatedRequest('POST', '/api/tasks', $this->userToken, $task2Data);
        
        // Get all tasks
        $response = $this->makeAuthenticatedRequest('GET', '/api/tasks', $this->userToken);
        $data = $this->assertSuccessResponse($response);
        
        $this->assertIsArray($data);
        $this->assertCount(2, $data);
        
        // Verify tasks are ordered by date and order
        $this->assertEquals('First Task', $data[0]['title']);
        $this->assertEquals('Second Task', $data[1]['title']);
    }

    public function testGetTasksWithDateFilter(): void
    {
        // Create tasks on different dates
        $this->makeAuthenticatedRequest('POST', '/api/tasks', $this->userToken, [
            'title' => 'Task 1',
            'date' => '2025-09-28'
        ]);
        
        $this->makeAuthenticatedRequest('POST', '/api/tasks', $this->userToken, [
            'title' => 'Task 2',
            'date' => '2025-09-29'
        ]);
        
        // Get tasks for specific date
        $response = $this->makeAuthenticatedRequest(
            'GET', 
            '/api/tasks?from=2025-09-28&until=2025-09-28', 
            $this->userToken
        );
        $data = $this->assertSuccessResponse($response);
        
        $this->assertCount(1, $data);
        $this->assertEquals('Task 1', $data[0]['title']);
    }

    public function testGetTasksWithPagination(): void
    {
        // Create multiple tasks
        for ($i = 1; $i <= 5; $i++) {
            $this->makeAuthenticatedRequest('POST', '/api/tasks', $this->userToken, [
                'title' => "Task {$i}",
                'date' => '2025-09-28'
            ]);
        }
        
        // Get first 2 tasks
        $response = $this->makeAuthenticatedRequest(
            'GET', 
            '/api/tasks?limit=2&offset=0', 
            $this->userToken
        );
        $data = $this->assertSuccessResponse($response);
        
        $this->assertCount(2, $data);
        $this->assertEquals('Task 1', $data[0]['title']);
        $this->assertEquals('Task 2', $data[1]['title']);
        
        // Get next 2 tasks
        $response = $this->makeAuthenticatedRequest(
            'GET', 
            '/api/tasks?limit=2&offset=2', 
            $this->userToken
        );
        $data = $this->assertSuccessResponse($response);
        
        $this->assertCount(2, $data);
        $this->assertEquals('Task 3', $data[0]['title']);
        $this->assertEquals('Task 4', $data[1]['title']);
    }

    public function testGetSingleTask(): void
    {
        // Create a task
        $response = $this->makeAuthenticatedRequest('POST', '/api/tasks', $this->userToken, [
            'title' => 'Single Task',
            'description' => 'Single task description',
            'date' => '2025-09-28'
        ]);
        $taskData = $this->assertSuccessResponse($response, 201);
        $taskId = $taskData['id'];
        
        // Get the single task
        $response = $this->makeAuthenticatedRequest('GET', "/api/task/{$taskId}", $this->userToken);
        $data = $this->assertSuccessResponse($response);
        
        $this->assertEquals($taskId, $data['id']);
        $this->assertEquals('Single Task', $data['title']);
        $this->assertEquals('Single task description', $data['description']);
    }

    public function testGetNonExistentTask(): void
    {
        $response = $this->makeAuthenticatedRequest('GET', '/api/task/999', $this->userToken);
        $error = $this->assertErrorResponse($response, 404);
        
        $this->assertStringContainsString('Task not found', $error);
    }

    public function testUpdateTask(): void
    {
        // Create a task
        $response = $this->makeAuthenticatedRequest('POST', '/api/tasks', $this->userToken, [
            'title' => 'Original Task',
            'description' => 'Original description',
            'date' => '2025-09-28'
        ]);
        $taskData = $this->assertSuccessResponse($response, 201);
        $taskId = $taskData['id'];
        
        // Update the task
        $updateData = [
            'title' => 'Updated Task',
            'description' => 'Updated description',
            'completed' => true
        ];
        
        $response = $this->makeAuthenticatedRequest('PUT', "/api/task/{$taskId}", $this->userToken, $updateData);
        $data = $this->assertSuccessResponse($response);
        
        $this->assertEquals($taskId, $data['id']);
        $this->assertEquals('Updated Task', $data['title']);
        $this->assertEquals('Updated description', $data['description']);
        $this->assertTrue($data['completed']);
        
        // Verify updated timestamp changed
        $this->assertNotEquals($taskData['updatedAt'], $data['updatedAt']);
    }

    public function testUpdateNonExistentTask(): void
    {
        $response = $this->makeAuthenticatedRequest('PUT', '/api/task/999', $this->userToken, [
            'title' => 'Updated Task'
        ]);
        $error = $this->assertErrorResponse($response, 404);
        
        $this->assertStringContainsString('Task not found', $error);
    }

    public function testDeleteTask(): void
    {
        // Create a task
        $response = $this->makeAuthenticatedRequest('POST', '/api/tasks', $this->userToken, [
            'title' => 'Task to Delete',
            'date' => '2025-09-28'
        ]);
        $taskData = $this->assertSuccessResponse($response, 201);
        $taskId = $taskData['id'];
        
        // Delete the task
        $response = $this->makeAuthenticatedRequest('DELETE', "/api/task/{$taskId}", $this->userToken);
        $data = $this->assertSuccessResponse($response);
        
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('deleted successfully', $data['message']);
        $this->assertEquals($taskId, $data['deletedTask']['id']);
        
        // Verify task is deleted
        $response = $this->makeAuthenticatedRequest('GET', "/api/task/{$taskId}", $this->userToken);
        $this->assertErrorResponse($response, 404);
    }

    public function testDeleteNonExistentTask(): void
    {
        $response = $this->makeAuthenticatedRequest('DELETE', '/api/task/999', $this->userToken);
        $error = $this->assertErrorResponse($response, 404);
        
        $this->assertStringContainsString('Task not found', $error);
    }

    public function testUpdateTaskOrder(): void
    {
        // Create multiple tasks
        $response1 = $this->makeAuthenticatedRequest('POST', '/api/tasks', $this->userToken, [
            'title' => 'Task 1',
            'date' => '2025-09-28'
        ]);
        $task1 = $this->assertSuccessResponse($response1, 201);
        
        $response2 = $this->makeAuthenticatedRequest('POST', '/api/tasks', $this->userToken, [
            'title' => 'Task 2',
            'date' => '2025-09-28'
        ]);
        $task2 = $this->assertSuccessResponse($response2, 201);
        
        // Move task 2 to first position
        $response = $this->makeAuthenticatedRequest(
            'POST', 
            "/api/task/{$task2['id']}/updateOrder", 
            $this->userToken, 
            [
                'date' => '2025-09-28',
                'after' => ''
            ]
        );
        $data = $this->assertSuccessResponse($response);
        
        $this->assertEquals(1, $data['order']);
        
        // Verify order in task list
        $response = $this->makeAuthenticatedRequest('GET', '/api/tasks', $this->userToken);
        $tasks = $this->assertSuccessResponse($response);
        
        $this->assertEquals('Task 2', $tasks[0]['title']);
        $this->assertEquals('Task 1', $tasks[1]['title']);
        $this->assertEquals(1, $tasks[0]['order']);
        $this->assertEquals(2, $tasks[1]['order']);
    }

    public function testUpdateTaskOrderToNewDate(): void
    {
        // Create a task
        $response = $this->makeAuthenticatedRequest('POST', '/api/tasks', $this->userToken, [
            'title' => 'Task to Move',
            'date' => '2025-09-28'
        ]);
        $task = $this->assertSuccessResponse($response, 201);
        
        // Move task to different date
        $response = $this->makeAuthenticatedRequest(
            'POST', 
            "/api/task/{$task['id']}/updateOrder", 
            $this->userToken, 
            [
                'date' => '2025-09-29',
                'after' => ''
            ]
        );
        $data = $this->assertSuccessResponse($response);
        
        $this->assertEquals('2025-09-29', $data['date']);
        $this->assertEquals(1, $data['order']);
    }

    public function testTasksAreIsolatedBetweenUsers(): void
    {
        // Create another user
        $otherUser = $this->createTestUserViaApi([
            'email' => 'otheruser@example.com',
            'password' => 'password123'
        ]);
        
        $user = $this->database->query(
            "SELECT id FROM users WHERE email = ?", 
            [$otherUser['email']]
        )->fetch();
        
        $this->database->query(
            "UPDATE users SET is_email_confirmed = 1, email_confirmation_token = NULL WHERE id = ?",
            [$user['id']]
        );
        
        $otherToken = $this->signInUser($otherUser['email'], $otherUser['password']);
        
        // Create task with first user
        $this->makeAuthenticatedRequest('POST', '/api/tasks', $this->userToken, [
            'title' => 'User 1 Task',
            'date' => '2025-09-28'
        ]);
        
        // Create task with second user
        $this->makeAuthenticatedRequest('POST', '/api/tasks', $otherToken, [
            'title' => 'User 2 Task',
            'date' => '2025-09-28'
        ]);
        
        // Verify each user only sees their own tasks
        $response1 = $this->makeAuthenticatedRequest('GET', '/api/tasks', $this->userToken);
        $tasks1 = $this->assertSuccessResponse($response1);
        
        $response2 = $this->makeAuthenticatedRequest('GET', '/api/tasks', $otherToken);
        $tasks2 = $this->assertSuccessResponse($response2);
        
        $this->assertCount(1, $tasks1);
        $this->assertCount(1, $tasks2);
        $this->assertEquals('User 1 Task', $tasks1[0]['title']);
        $this->assertEquals('User 2 Task', $tasks2[0]['title']);
    }
}
