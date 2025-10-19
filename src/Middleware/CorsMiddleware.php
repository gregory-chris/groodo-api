<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;

class CorsMiddleware implements MiddlewareInterface
{
    private array $config;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->config = require __DIR__ . '/../../config/cors.php';
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $origin = $request->getHeaderLine('Origin');
        $method = $request->getMethod();

        $this->logger->debug('Processing CORS middleware', [
            'origin' => $origin,
            'method' => $method,
            'uri' => $request->getUri()->getPath()
        ]);

        // Handle preflight OPTIONS request
        if ($method === 'OPTIONS') {
            $this->logger->debug('Handling CORS preflight request');
            $response = new \Slim\Psr7\Response();
            return $this->addCorsHeaders($response, $origin);
        }

        // Process the actual request and add CORS headers
        $response = $handler->handle($request);
        return $this->addCorsHeaders($response, $origin);
    }

    private function addCorsHeaders(Response $response, string $origin): Response
    {
        // Determine the allowed origin
        $allowedOrigin = $this->determineAllowedOrigin($origin);

        if ($allowedOrigin === null) {
            $this->logger->warning('Origin not allowed', ['origin' => $origin]);
            return $response;
        }

        // Get configuration values
        $allowedMethods = implode(', ', $this->config['allowed_methods']);
        $allowedHeaders = implode(', ', $this->config['allowed_headers']);
        $maxAge = (string)$this->config['max_age'];

        // Add CORS headers
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Methods', $allowedMethods)
            ->withHeader('Access-Control-Allow-Headers', $allowedHeaders)
            ->withHeader('Access-Control-Max-Age', $maxAge);

        // Add credentials header if enabled in config
        if ($this->config['credentials']) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        // Add exposed headers if configured
        if (!empty($this->config['exposed_headers'])) {
            $exposedHeaders = implode(', ', $this->config['exposed_headers']);
            $response = $response->withHeader('Access-Control-Expose-Headers', $exposedHeaders);
        }

        $this->logger->debug('CORS headers added', [
            'allowed_origin' => $allowedOrigin,
            'origin' => $origin
        ]);

        return $response;
    }

    private function determineAllowedOrigin(string $origin): ?string
    {
        // If no origin header, it's a same-origin request
        if (empty($origin)) {
            $this->logger->debug('No origin header, same-origin request');
            return null;
        }

        // Parse the origin
        $parsedOrigin = parse_url($origin);
        if ($parsedOrigin === false || !isset($parsedOrigin['host'])) {
            $this->logger->warning('Invalid origin format', ['origin' => $origin]);
            return null;
        }

        $originHost = strtolower($parsedOrigin['host']);

        // Check if origin is localhost (development environment)
        if ($this->isLocalhost($originHost)) {
            $this->logger->debug('Localhost origin detected, allowing', ['origin' => $origin]);
            return $origin; // Echo back the exact origin
        }

        // Check against allowed origins from config
        $allowedOrigins = $this->config['allowed_origins'];
        
        foreach ($allowedOrigins as $allowedOrigin) {
            $allowedOrigin = trim($allowedOrigin);
            $parsedAllowed = parse_url($allowedOrigin);
            
            if ($parsedAllowed === false || !isset($parsedAllowed['host'])) {
                continue;
            }

            $allowedHost = strtolower($parsedAllowed['host']);
            $allowedPort = $parsedAllowed['port'] ?? null;
            $originPort = $parsedOrigin['port'] ?? null;

            // Match host and port
            if ($originHost === $allowedHost && $originPort === $allowedPort) {
                $this->logger->debug('Origin allowed from config', [
                    'origin' => $origin,
                    'matched_config' => $allowedOrigin
                ]);
                return $origin; // Echo back the exact origin
            }
        }

        $this->logger->warning('Origin not in allowed list', [
            'origin' => $origin,
            'allowed_origins' => $allowedOrigins
        ]);

        return null;
    }

    private function isLocalhost(string $host): bool
    {
        $localhostPatterns = ['localhost', '127.0.0.1', '::1'];
        
        foreach ($localhostPatterns as $pattern) {
            if ($host === $pattern) {
                return true;
            }
        }

        return false;
    }
}
