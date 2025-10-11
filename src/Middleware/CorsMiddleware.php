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
        $host = $request->getUri()->getHost();
        $scheme = $request->getUri()->getScheme(); // Get the request protocol (http or https)

        $this->logger->debug('CORS request details', [
            'origin' => $origin,
            'method' => $method,
            'host' => $host,
            'scheme' => $scheme,
            'uri' => $request->getUri()->getPath()
        ]);

        // If running on localhost, allow all origins (no CORS headers needed)
        $isLocalhost = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        
        if ($isLocalhost) {
            $this->logger->debug('Localhost detected, skipping CORS restrictions');
            
            // Handle preflight OPTIONS request
            if ($method === 'OPTIONS') {
                $this->logger->debug('Handling localhost preflight request');
                $response = new \Slim\Psr7\Response();
                return $this->addLocalhostHeaders($response);
            }

            // Process the actual request
            $response = $handler->handle($request);
            return $this->addLocalhostHeaders($response);
        }

        // Production environment - enforce CORS restrictions
        // Handle preflight OPTIONS request
        if ($method === 'OPTIONS') {
            $this->logger->debug('Handling CORS preflight request');
            
            $response = new \Slim\Psr7\Response();
            return $this->addCorsHeaders($response, $origin, $scheme);
        }

        // Process the actual request
        $response = $handler->handle($request);

        // Add CORS headers to the response
        return $this->addCorsHeaders($response, $origin, $scheme);
    }

    private function addCorsHeaders(Response $response, string $origin, string $requestScheme): Response
    {
        $this->logger->debug('Adding CORS headers', ['origin' => $origin, 'request_scheme' => $requestScheme]);

        // Check if origin is allowed (by domain, regardless of protocol)
        if ($this->isOriginAllowed($origin)) {
            // Use the same protocol as the incoming request
            $allowedOrigin = $this->normalizeOriginProtocol($origin, $requestScheme);
            $response = $response->withHeader('Access-Control-Allow-Origin', $allowedOrigin);
            
            $this->logger->debug('Origin allowed', ['origin' => $origin, 'allowed_origin' => $allowedOrigin]);
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

    private function addLocalhostHeaders(Response $response): Response
    {
        $this->logger->debug('Adding localhost CORS headers (allow all)');

        // Allow all origins for localhost
        $response = $response->withHeader('Access-Control-Allow-Origin', '*');

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

        // Note: Access-Control-Allow-Credentials cannot be used with wildcard origin
        // So we skip it for localhost

        $response = $response->withHeader(
            'Access-Control-Max-Age',
            (string)$this->config['max_age']
        );

        $this->logger->debug('Localhost CORS headers added successfully');

        return $response;
    }

    private function isOriginAllowed(string $origin): bool
    {
        $this->logger->debug('CORS origin check: evaluating origin', ['origin' => $origin]);

        if (empty($origin)) {
            $this->logger->warning('CORS origin check: empty Origin header');
            return false;
        }

        // Parse the origin to extract domain (ignore protocol)
        $parsedOrigin = parse_url($origin);
        if (!$parsedOrigin || !isset($parsedOrigin['host'])) {
            return false;
        }

        $originHost = $parsedOrigin['host'];
        $originPort = $parsedOrigin['port'] ?? null;

        // Check against allowed origins (domain matching only)
        foreach ($this->config['allowed_origins'] as $allowedOrigin) {
            $parsedAllowed = parse_url(trim($allowedOrigin));
            if (!$parsedAllowed || !isset($parsedAllowed['host'])) {
                continue;
            }

            $allowedHost = $parsedAllowed['host'];
            $allowedPort = $parsedAllowed['port'] ?? null;

            // Match host and port (ignore protocol)
            if ($originHost === $allowedHost && $originPort === $allowedPort) {
                return true;
            }
        }

        $this->logger->debug('CORS origin check: no matches found', ['origin_host' => $originHost]);
        return false;
    }

    private function normalizeOriginProtocol(string $origin, string $requestScheme): string
    {
        // Parse the origin to extract domain
        $parsed = parse_url($origin);
        if (!$parsed || !isset($parsed['host'])) {
            return $origin; // Return as-is if parsing fails
        }

        // Reconstruct the origin with the request's scheme
        $normalizedOrigin = $requestScheme . '://' . $parsed['host'];
        
        // Include port if present
        if (isset($parsed['port'])) {
            $normalizedOrigin .= ':' . $parsed['port'];
        }

        $this->logger->debug('Normalized origin protocol', [
            'original_origin' => $origin,
            'request_scheme' => $requestScheme,
            'normalized_origin' => $normalizedOrigin
        ]);

        return $normalizedOrigin;
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
