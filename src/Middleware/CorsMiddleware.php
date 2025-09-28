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
        $this->logger->debug('Processing CORS middleware');

        $origin = $request->getHeaderLine('Origin');
        $method = $request->getMethod();

        $this->logger->debug('CORS request details', [
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

        // Process the actual request
        $response = $handler->handle($request);

        // Add CORS headers to the response
        return $this->addCorsHeaders($response, $origin);
    }

    private function addCorsHeaders(Response $response, string $origin): Response
    {
        $this->logger->debug('Adding CORS headers', ['origin' => $origin]);

        // Check if origin is allowed
        if ($this->isOriginAllowed($origin)) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $origin);
            
            $this->logger->debug('Origin allowed', ['origin' => $origin]);
        } else {
            $this->logger->warning('Origin not allowed', ['origin' => $origin]);
            // For security, don't set CORS headers for disallowed origins
            return $response;
        }

        // Add other CORS headers
        $response = $response->withHeader(
            'Access-Control-Allow-Methods',
            implode(', ', $this->config['allowed_methods'])
        );

        $response = $response->withHeader(
            'Access-Control-Allow-Headers',
            implode(', ', $this->config['allowed_headers'])
        );

        if (!empty($this->config['exposed_headers'])) {
            $response = $response->withHeader(
                'Access-Control-Expose-Headers',
                implode(', ', $this->config['exposed_headers'])
            );
        }

        if ($this->config['credentials']) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        $response = $response->withHeader(
            'Access-Control-Max-Age',
            (string)$this->config['max_age']
        );

        $this->logger->debug('CORS headers added successfully');

        return $response;
    }

    private function isOriginAllowed(string $origin): bool
    {
        if (empty($origin)) {
            return false;
        }

        foreach ($this->config['allowed_origins'] as $allowedOrigin) {
            if ($this->matchOrigin($origin, trim($allowedOrigin))) {
                return true;
            }
        }

        return false;
    }

    private function matchOrigin(string $origin, string $allowedOrigin): bool
    {
        // Exact match
        if ($origin === $allowedOrigin) {
            return true;
        }

        // Wildcard match for subdomains
        if (str_starts_with($allowedOrigin, '*.')) {
            $domain = substr($allowedOrigin, 2);
            
            // Parse the origin URL to get the host
            $parsedOrigin = parse_url($origin);
            if (!$parsedOrigin || !isset($parsedOrigin['host'])) {
                return false;
            }
            
            $originHost = $parsedOrigin['host'];
            
            // Check if it's an exact match of the domain
            if ($originHost === $domain) {
                return true;
            }
            
            // Check if it's a subdomain
            if (str_ends_with($originHost, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    public function getAllowedOrigins(): array
    {
        return $this->config['allowed_origins'];
    }

    public function getAllowedMethods(): array
    {
        return $this->config['allowed_methods'];
    }

    public function getAllowedHeaders(): array
    {
        return $this->config['allowed_headers'];
    }

    public function isCredentialsAllowed(): bool
    {
        return $this->config['credentials'];
    }

    public function getMaxAge(): int
    {
        return $this->config['max_age'];
    }
}
