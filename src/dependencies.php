<?php
declare(strict_types=1);

use App\Utils\Database;
use App\Services\LoggingService;
use App\Services\JwtService;
use App\Services\PasswordService;
use App\Services\EmailService;
use App\Services\ValidationService;
use App\Utils\ResponseHelper;
use App\Models\User;
use App\Models\Task;
use App\Models\Project;
use App\Models\Document;
use App\Controllers\UserController;
use App\Controllers\TaskController;
use App\Controllers\ProjectController;
use App\Controllers\DocumentController;
use App\Middleware\AuthMiddleware;
use App\Middleware\SecurityMiddleware;
use App\Middleware\CorsMiddleware;
use App\Middleware\LoggingMiddleware;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

// Database
$container->set(Database::class, function (ContainerInterface $c) {
    return new Database($c->get(LoggerInterface::class));
});

// Logging Service
$container->set(LoggerInterface::class, function () {
    return LoggingService::createLogger();
});

$container->set(LoggingService::class, function (ContainerInterface $c) {
    return new LoggingService($c->get(LoggerInterface::class));
});

// JWT Service
$container->set(JwtService::class, function (ContainerInterface $c) {
    return new JwtService($c->get(LoggerInterface::class));
});

// Password Service
$container->set(PasswordService::class, function (ContainerInterface $c) {
    return new PasswordService($c->get(LoggerInterface::class));
});

// Email Service
$container->set(EmailService::class, function (ContainerInterface $c) {
    return new EmailService($c->get(LoggerInterface::class));
});

// Validation Service
$container->set(ValidationService::class, function (ContainerInterface $c) {
    return new ValidationService($c->get(LoggerInterface::class));
});

// Response Helper
$container->set(ResponseHelper::class, function () {
    return new ResponseHelper();
});

// Models
$container->set(User::class, function (ContainerInterface $c) {
    return new User($c->get(Database::class), $c->get(LoggerInterface::class));
});

$container->set(Task::class, function (ContainerInterface $c) {
    return new Task($c->get(Database::class), $c->get(LoggerInterface::class));
});

$container->set(Project::class, function (ContainerInterface $c) {
    return new Project($c->get(Database::class), $c->get(LoggerInterface::class));
});

$container->set(Document::class, function (ContainerInterface $c) {
    return new Document($c->get(Database::class), $c->get(LoggerInterface::class));
});

// Controllers
$container->set(UserController::class, function (ContainerInterface $c) {
    return new UserController(
        $c->get(User::class),
        $c->get(JwtService::class),
        $c->get(PasswordService::class),
        $c->get(ValidationService::class),
        $c->get(EmailService::class),
        $c->get(ResponseHelper::class),
        $c->get(LoggerInterface::class)
    );
});

$container->set(TaskController::class, function (ContainerInterface $c) {
    return new TaskController(
        $c->get(Task::class),
        $c->get(Project::class),
        $c->get(ValidationService::class),
        $c->get(ResponseHelper::class),
        $c->get(LoggerInterface::class)
    );
});

$container->set(ProjectController::class, function (ContainerInterface $c) {
    return new ProjectController(
        $c->get(Project::class),
        $c->get(Task::class),
        $c->get(ValidationService::class),
        $c->get(ResponseHelper::class),
        $c->get(LoggerInterface::class)
    );
});

$container->set(DocumentController::class, function (ContainerInterface $c) {
    return new DocumentController(
        $c->get(Document::class),
        $c->get(ValidationService::class),
        $c->get(ResponseHelper::class),
        $c->get(LoggerInterface::class)
    );
});

// Middleware
$container->set(AuthMiddleware::class, function (ContainerInterface $c) {
    return new AuthMiddleware(
        $c->get(JwtService::class),
        $c->get(User::class),
        $c->get(ResponseHelper::class),
        $c->get(LoggerInterface::class)
    );
});

$container->set(SecurityMiddleware::class, function (ContainerInterface $c) {
    return new SecurityMiddleware(
        $c->get(ResponseHelper::class),
        $c->get(LoggerInterface::class)
    );
});

$container->set(CorsMiddleware::class, function (ContainerInterface $c) {
    return new CorsMiddleware($c->get(LoggerInterface::class));
});

$container->set(LoggingMiddleware::class, function (ContainerInterface $c) {
    return new LoggingMiddleware(
        $c->get(LoggingService::class),
        $c->get(LoggerInterface::class)
    );
});
