<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;
use DI\Container;

abstract class ApiTestCase extends TestCase
{
    protected App $app;
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create container
        $this->container = new Container();
        
        // Set up dependencies manually for testing
        $this->setupDependencies();
        
        // Create Slim app
        $this->app = new App(new ResponseFactory(), $this->container);
        
        // Load routes manually for testing
        $this->setupRoutes();
    }

    private function setupDependencies(): void
    {
        // Database
        $this->container->set('Database', function() {
            return $this->database;
        });
        
        $this->container->set(\App\Utils\Database::class, function() {
            return $this->database;
        });

        // Logger
        $this->container->set('LoggerInterface', function() {
            return $this->logger;
        });
        
        $this->container->set(\Psr\Log\LoggerInterface::class, function() {
            return $this->logger;
        });

        // Services
        $this->container->set('LoggingService', function() {
            return new \App\Services\LoggingService($this->logger);
        });

        $this->container->set('JwtService', function() {
            return new \App\Services\JwtService($this->logger);
        });

        $this->container->set('PasswordService', function() {
            return new \App\Services\PasswordService($this->logger);
        });

        $this->container->set('ValidationService', function() {
            return new \App\Services\ValidationService($this->logger);
        });

        $this->container->set('EmailService', function() {
            return new \App\Services\EmailService($this->logger);
        });

        // Models
        $this->container->set('User', function() {
            return new \App\Models\User($this->database, $this->logger);
        });

        $this->container->set('Task', function() {
            return new \App\Models\Task($this->database, $this->logger);
        });

        // Utils
        $this->container->set('ResponseHelper', function() {
            return new \App\Utils\ResponseHelper();
        });

        // Controllers
        $this->container->set('UserController', function() {
            return new \App\Controllers\UserController(
                $this->container->get('User'),
                $this->container->get('JwtService'),
                $this->container->get('PasswordService'),
                $this->container->get('ValidationService'),
                $this->container->get('EmailService'),
                $this->container->get('ResponseHelper'),
                $this->logger
            );
        });

        $this->container->set('TaskController', function() {
            return new \App\Controllers\TaskController(
                $this->container->get('Task'),
                $this->container->get('ValidationService'),
                $this->container->get('ResponseHelper'),
                $this->logger
            );
        });

        // Middleware
        $this->container->set('AuthMiddleware', function() {
            return new \App\Middleware\AuthMiddleware(
                $this->container->get('JwtService'),
                $this->container->get('User'),
                $this->container->get('ResponseHelper'),
                $this->logger
            );
        });

        $this->container->set('SecurityMiddleware', function() {
            return new \App\Middleware\SecurityMiddleware(
                $this->container->get('ResponseHelper'),
                $this->logger
            );
        });

        $this->container->set('CorsMiddleware', function() {
            return new \App\Middleware\CorsMiddleware($this->logger);
        });

        $this->container->set('LoggingMiddleware', function() {
            return new \App\Middleware\LoggingMiddleware(
                $this->container->get('LoggingService'),
                $this->logger
            );
        });
    }

    private function setupRoutes(): void
    {
        // Add global middleware
        $this->app->add($this->container->get('LoggingMiddleware'));
        $this->app->add($this->container->get('CorsMiddleware'));

        // Health check endpoint
        $this->app->get('/health', function ($request, $response) {
            $responseHelper = new \App\Utils\ResponseHelper();
            return $responseHelper->success([
                'status' => 'healthy',
                'timestamp' => date('c'),
                'version' => '1.0.0'
            ]);
        });

        // Capture container for use in closures
        $container = $this->container;

        // API group
        $apiGroup = $this->app->group('/api', function ($group) use ($container) {
            // User/Authentication routes
            $group->post('/users/signUp', [\App\Controllers\UserController::class, 'signUp']);
            $group->post('/users/confirmEmail', [\App\Controllers\UserController::class, 'confirmEmail']);
            $group->post('/users/signIn', [\App\Controllers\UserController::class, 'signIn']);
            $group->post('/users/resetPassword', [\App\Controllers\UserController::class, 'resetPassword']);

            // Protected user routes
            $group->post('/users/signOut', [\App\Controllers\UserController::class, 'signOut'])
                ->add($container->get('AuthMiddleware'));
            $group->get('/users/profile', [\App\Controllers\UserController::class, 'profile'])
                ->add($container->get('AuthMiddleware'));

            // Task routes (all protected)
            $group->get('/tasks', [\App\Controllers\TaskController::class, 'getTasks'])
                ->add($container->get('AuthMiddleware'));
            $group->post('/tasks', [\App\Controllers\TaskController::class, 'createTask'])
                ->add($container->get('AuthMiddleware'));
            $group->get('/task/{taskId}', [\App\Controllers\TaskController::class, 'getTask'])
                ->add($container->get('AuthMiddleware'));
            $group->put('/task/{taskId}', [\App\Controllers\TaskController::class, 'updateTask'])
                ->add($container->get('AuthMiddleware'));
            $group->delete('/task/{taskId}', [\App\Controllers\TaskController::class, 'deleteTask'])
                ->add($container->get('AuthMiddleware'));
            $group->post('/task/{taskId}/updateOrder', [\App\Controllers\TaskController::class, 'updateTaskOrder'])
                ->add($container->get('AuthMiddleware'));
        });

        // Add security middleware to API routes (disabled for testing)
        // $apiGroup->add($this->container->get('SecurityMiddleware'));
    }

    protected function createRequest(
        string $method,
        string $uri,
        array $headers = [],
        array $data = []
    ): \Psr\Http\Message\ServerRequestInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri);
        
        // Add default User-Agent for testing to pass security middleware
        if (!isset($headers['User-Agent'])) {
            $headers['User-Agent'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
        }
        
        // Add headers
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        
        // Add body data for POST/PUT requests
        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request->getBody()->write(json_encode($data));
            $request = $request->withParsedBody($data);
        }
        
        return $request;
    }

    protected function makeRequest(
        string $method,
        string $uri,
        array $headers = [],
        array $data = []
    ): \Psr\Http\Message\ResponseInterface {
        $request = $this->createRequest($method, $uri, $headers, $data);
        return $this->app->handle($request);
    }

    protected function makeAuthenticatedRequest(
        string $method,
        string $uri,
        string $token,
        array $data = []
    ): \Psr\Http\Message\ResponseInterface {
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'User-Agent' => 'Mozilla/5.0 (Test)'
        ];
        
        return $this->makeRequest($method, $uri, $headers, $data);
    }

    protected function getResponseData(\Psr\Http\Message\ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        return json_decode($body, true) ?? [];
    }

    protected function assertJsonResponse(
        \Psr\Http\Message\ResponseInterface $response,
        int $expectedStatusCode = 200
    ): array {
        $this->assertEquals($expectedStatusCode, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        
        $data = $this->getResponseData($response);
        $this->assertIsArray($data);
        
        return $data;
    }

    protected function assertSuccessResponse(
        \Psr\Http\Message\ResponseInterface $response,
        int $expectedStatusCode = 200
    ): array {
        $data = $this->assertJsonResponse($response, $expectedStatusCode);
        
        $this->assertArrayHasKey('result', $data);
        $this->assertEquals('success', $data['result']);
        $this->assertArrayHasKey('data', $data);
        
        return $data['data'];
    }

    protected function assertErrorResponse(
        \Psr\Http\Message\ResponseInterface $response,
        int $expectedStatusCode = 400
    ): string {
        $data = $this->assertJsonResponse($response, $expectedStatusCode);
        
        $this->assertArrayHasKey('result', $data);
        $this->assertEquals('failure', $data['result']);
        $this->assertArrayHasKey('error', $data);
        
        return $data['error'];
    }

    protected function createTestUserViaApi(array $userData = []): array
    {
        $defaultData = [
            'email' => 'test' . time() . '@example.com',
            'fullName' => 'Test User',
            'password' => 'password123'
        ];
        
        $userData = array_merge($defaultData, $userData);
        
        $response = $this->makeRequest('POST', '/api/users/signUp', [], $userData);
        $responseData = $this->assertSuccessResponse($response, 201);
        
        return array_merge($userData, $responseData);
    }

    protected function signInUser(string $email, string $password): string
    {
        $response = $this->makeRequest('POST', '/api/users/signIn', [], [
            'email' => $email,
            'password' => $password
        ]);
        
        $data = $this->assertSuccessResponse($response);
        
        $this->assertArrayHasKey('token', $data);
        return $data['token'];
    }
}
