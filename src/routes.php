<?php
declare(strict_types=1);

use App\Controllers\UserController;
use App\Controllers\TaskController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\LoggingMiddleware;
use App\Middleware\SecurityMiddleware;
use Slim\Routing\RouteCollectorProxy;

// Add global middleware
$app->add(LoggingMiddleware::class);
$app->add(CorsMiddleware::class);

// Handle all OPTIONS requests (CORS preflight)
$app->options('/{routes:.+}', function ($request, $response) {
    // CORS headers are added by CorsMiddleware
    return $response;
});

// Health check endpoint
$app->get('/health', function ($request, $response) {
    $responseHelper = new \App\Utils\ResponseHelper();
    return $responseHelper->success([
        'status' => 'healthy',
        'timestamp' => date('c'),
        'version' => '1.0.0'
    ]);
});

// API routes group
$app->group('/api', function (RouteCollectorProxy $group) {
    
    // User/Authentication routes
    $group->group('/users', function (RouteCollectorProxy $userGroup) {
        $userGroup->post('/signup', [UserController::class, 'signUp']);
                  //->add(SecurityMiddleware::class);
        $userGroup->post('/signin', [UserController::class, 'signIn']);
                  //->add(SecurityMiddleware::class);
        $userGroup->post('/signout', [UserController::class, 'signOut'])
                  ->add(AuthMiddleware::class);
        $userGroup->get('/confirm-email', [UserController::class, 'confirmEmail']);
        $userGroup->post('/reset-password', [UserController::class, 'resetPassword'])
                  ->add(SecurityMiddleware::class);
        $userGroup->get('/profile', [UserController::class, 'getProfile'])
                  ->add(AuthMiddleware::class);
    });
    
    // Task routes
    $group->group('/tasks', function (RouteCollectorProxy $taskGroup) {
        $taskGroup->get('', [TaskController::class, 'getTasks']);
        $taskGroup->post('', [TaskController::class, 'createTask']);
    })->add(AuthMiddleware::class);
    
    $group->group('/task', function (RouteCollectorProxy $taskGroup) {
        $taskGroup->get('/{taskId:[0-9]+}', [TaskController::class, 'getTask']);
        $taskGroup->put('/{taskId:[0-9]+}', [TaskController::class, 'updateTask']);
        $taskGroup->delete('/{taskId:[0-9]+}', [TaskController::class, 'deleteTask']);
    $taskGroup->post('/{taskId:[0-9]+}/updateorder', [TaskController::class, 'updateTaskOrder']);
    })->add(AuthMiddleware::class);
});
