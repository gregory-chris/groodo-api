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

// Configure container dependencies
require __DIR__ . '/../src/dependencies.php';

// Configure routes
require __DIR__ . '/../src/routes.php';

// Load CORS configuration
$corsConfig = require __DIR__ . '/../config/cors.php';
$GLOBALS['corsConfig'] = $corsConfig;

// Helper function to add CORS headers to error responses
function addCorsHeadersToResponse($response, Request $request, array $corsConfig) {
    $origin = $request->getHeaderLine('Origin');
    
    // If no origin header, no CORS headers needed
    if (empty($origin)) {
        return $response;
    }
    
    // Parse origin
    $parsedOrigin = parse_url($origin);
    if ($parsedOrigin === false || !isset($parsedOrigin['host'])) {
        return $response;
    }
    
    $originHost = strtolower($parsedOrigin['host']);
    
    // Check if origin is localhost (development)
    $localhostPatterns = ['localhost', '127.0.0.1', '::1'];
    $isLocalhost = in_array($originHost, $localhostPatterns);
    
    if ($isLocalhost) {
        // Echo back the exact origin for localhost
        $allowedOrigin = $origin;
    } else {
        // Check against allowed origins from config
        $allowedOrigin = null;
        foreach ($corsConfig['allowed_origins'] as $configOrigin) {
            $parsedConfigOrigin = parse_url(trim($configOrigin));
            if ($parsedConfigOrigin !== false && 
                isset($parsedConfigOrigin['host']) && 
                strtolower($parsedConfigOrigin['host']) === $originHost) {
                $allowedOrigin = $origin;
                break;
            }
        }
        
        // If origin not allowed, don't add CORS headers
        if ($allowedOrigin === null) {
            return $response;
        }
    }
    
    // Build allowed methods and headers from config
    $allowedMethods = implode(', ', $corsConfig['allowed_methods']);
    $allowedHeaders = implode(', ', $corsConfig['allowed_headers']);
    $maxAge = (string)$corsConfig['max_age'];
    
    $response = $response
        ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
        ->withHeader('Access-Control-Allow-Methods', $allowedMethods)
        ->withHeader('Access-Control-Allow-Headers', $allowedHeaders)
        ->withHeader('Access-Control-Max-Age', $maxAge);
    
    // Add credentials header if enabled in config
    if ($corsConfig['credentials']) {
        $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
    }
    
    return $response;
}

// Add body parsing middleware (first)
$app->addBodyParsingMiddleware();

// Add case-insensitive route middleware
$app->add(\App\Middleware\CaseInsensitiveRouteMiddleware::class);

// Add routing middleware
$app->addRoutingMiddleware();

// Add error middleware and configure custom error handlers (last, so it catches all errors)
$errorMiddleware = $app->addErrorMiddleware(
    (bool)($_ENV['APP_DEBUG'] ?? false),
    true,
    true
);

// Custom error handler for not found routes
$errorMiddleware->setErrorHandler(HttpNotFoundException::class, function (
    Request $request,
    \Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app) {
    $logger = $app->getContainer()->get(LoggerInterface::class);
    $logger->info('Route not found (404)', [
        'method' => $request->getMethod(),
        'uri' => (string)$request->getUri()
    ]);

    $response = new \Slim\Psr7\Response();
    $response->getBody()->write(json_encode([
        'result' => 'failure',
        'error' => 'Endpoint not found'
    ], JSON_UNESCAPED_UNICODE));
    
    $response = $response
        ->withStatus(404)
        ->withHeader('Content-Type', 'application/json');
    
    // Add CORS headers
    $response = addCorsHeadersToResponse($response, $request, $GLOBALS['corsConfig']);
    
    return $response;
});

// Custom error handler for method not allowed
$errorMiddleware->setErrorHandler(HttpMethodNotAllowedException::class, function (
    Request $request,
    \Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app) {
    $logger = $app->getContainer()->get(LoggerInterface::class);
    
    // Cast to HttpMethodNotAllowedException to access getAllowedMethods()
    $allowedMethods = [];
    if ($exception instanceof HttpMethodNotAllowedException) {
        $allowedMethods = $exception->getAllowedMethods();
    }
    
    $logger->info('Method not allowed (405)', [
        'method' => $request->getMethod(),
        'uri' => (string)$request->getUri(),
        'allowed_methods' => $allowedMethods
    ]);

    $response = new \Slim\Psr7\Response();
    $responseData = [
        'result' => 'failure',
        'error' => 'Method not allowed'
    ];
    
    if (!empty($allowedMethods)) {
        $responseData['allowed_methods'] = $allowedMethods;
    }
    
    $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE));
    
    $response = $response
        ->withStatus(405)
        ->withHeader('Content-Type', 'application/json');
    
    if (!empty($allowedMethods)) {
        $response = $response->withHeader('Allow', implode(', ', $allowedMethods));
    }
    
    // Add CORS headers
    $response = addCorsHeadersToResponse($response, $request, $GLOBALS['corsConfig']);
    
    return $response;
});

// Run app
$app->run();
