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
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $host = $request->getUri()->getHost();
        $method = $request->getMethod();

        $this->logger->debug('Processing CORS middleware', [
            'host' => $host,
            'method' => $method
        ]);

        // Handle preflight OPTIONS request
        if ($method === 'OPTIONS') {
            $response = new \Slim\Psr7\Response();
            return $this->addCorsHeaders($response, $host);
        }

        // Process the actual request and add CORS headers
        $response = $handler->handle($request);
        return $this->addCorsHeaders($response, $host);
    }

    private function addCorsHeaders(Response $response, string $host): Response
    {
        // Determine the allowed origin based on the request host
        if (strtolower($host) === 'groodo-api.greq.me') {
            // Production: API called from production domain
            $allowedOrigin = 'https://groodo.greq.me';
            $this->logger->debug('Production host detected, setting CORS to groodo.greq.me');
        } elseif (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true) || 
                  str_starts_with(strtolower($host), 'localhost:')) {
            // Localhost: Allow all origins
            $allowedOrigin = '*';
            $this->logger->debug('Localhost detected, allowing all origins');
        } else {
            // Unknown host: Allow all origins (permissive for development)
            $allowedOrigin = '*';
            $this->logger->debug('Unknown host, allowing all origins', ['host' => $host]);
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Max-Age', '3600');

        // Only add credentials header if not using wildcard origin
        if ($allowedOrigin !== '*') {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        $this->logger->debug('CORS headers added', ['allowed_origin' => $allowedOrigin]);

        return $response;
    }
}
