<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use DI\Container;
use Dotenv\Dotenv;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;

// Load Composer autoloader
require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Create DI Container
$container = new Container();

// Set container to create App with on AppFactory
AppFactory::setContainer($container);

// Create App
$app = AppFactory::create();

// Add error middleware
$errorMiddleware = $app->addErrorMiddleware(
    (bool)($_ENV['APP_DEBUG'] ?? false),
    true,
    true
);

// Add routing middleware
$app->addRoutingMiddleware();

// Add case-insensitive route middleware (before routing)
// AI suggested to remove this middleware for returning unsupported endpoints/methods
$app->add(\App\Middleware\CaseInsensitiveRouteMiddleware::class);

// Add body parsing middleware
$app->addBodyParsingMiddleware();

// Configure container dependencies
require __DIR__ . '/../src/dependencies.php';

// Configure routes
require __DIR__ . '/../src/routes.php';

// Custom error handlers for unsupported endpoints/methods
$errorMiddleware->setErrorHandler(HttpNotFoundException::class, function (
    Request $request,
    \Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app) {
    $logger = $app->getContainer()->get(LoggerInterface::class);
    $logger->info('Unsupported endpoint (route not found)', [
        'method' => $request->getMethod(),
        'uri' => (string)$request->getUri()
    ]);

    $responseHelper = new \App\Utils\ResponseHelper();
    return $responseHelper->error('Endpoint not supported', 400);
});

$errorMiddleware->setErrorHandler(HttpMethodNotAllowedException::class, function (
    Request $request,
    \Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app) {
    $logger = $app->getContainer()->get(LoggerInterface::class);
    $logger->info('Unsupported endpoint (method not allowed)', [
        'method' => $request->getMethod(),
        'uri' => (string)$request->getUri()
    ]);

    $responseHelper = new \App\Utils\ResponseHelper();
    return $responseHelper->error('Endpoint not supported', 400);
});

// Run app
$app->run();
