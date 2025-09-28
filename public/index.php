<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use DI\Container;
use Dotenv\Dotenv;

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

// Add body parsing middleware
$app->addBodyParsingMiddleware();

// Configure container dependencies
require __DIR__ . '/../src/dependencies.php';

// Configure routes
require __DIR__ . '/../src/routes.php';

// Run app
$app->run();
