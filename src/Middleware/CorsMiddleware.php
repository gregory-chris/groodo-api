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
        $this->logger->debug('CORS origin check: evaluating origin', ['origin' => $origin]);

        if (empty($origin)) {
            $this->logger->warning('CORS origin check: empty Origin header');
            return false;
        }

        $parsedOrigin = parse_url($origin);
        if (!$parsedOrigin || !isset($parsedOrigin['host'])) {
            $this->logger->warning('CORS origin check: failed to parse origin or missing host', ['origin' => $origin]);
            return false;
        }

        $originHost = strtolower($parsedOrigin['host']);
        $this->logger->debug('CORS origin check: parsed origin host', ['origin_host' => $originHost]);

        $allowedList = array_map(static function ($o) { return trim(strtolower((string)$o)); }, $this->config['allowed_origins']);
        $this->logger->debug('CORS origin check: allowed origins list', ['allowed_origins' => $allowedList]);

        foreach ($allowedList as $allowedOrigin) {
            $this->logger->debug('CORS origin check: testing allowed origin', [
                'origin_host' => $originHost,
                'allowed_origin' => $allowedOrigin
            ]);

            if ($this->matchOrigin($originHost, $allowedOrigin)) {
                $this->logger->debug('CORS origin check: match found', [
                    'origin_host' => $originHost,
                    'allowed_origin' => $allowedOrigin
                ]);
                return true;
            }
        }

        $this->logger->debug('CORS origin check: no matches found', ['origin_host' => $originHost]);
        return false;
    }

    private function matchOrigin(string $originHost, string $allowedOrigin): bool
    {
        if ($allowedOrigin === '') {
            $this->logger->debug('CORS origin match: empty allowed origin entry');
            return false;
        }

        // If allowed origin includes a scheme, extract host; otherwise treat as host
        $allowedHost = parse_url($allowedOrigin, PHP_URL_HOST) ?: $allowedOrigin;

        // Normalize: remove leading dots and wildcard prefix
        $allowedHost = preg_replace('/^\*\./', '', ltrim($allowedHost, '.'));

        if ($allowedHost === '') {
            $this->logger->debug('CORS origin match: computed empty allowed host');
            return false;
        }

        // Exact host match
        if ($originHost === $allowedHost) {
            $this->logger->debug('CORS origin match: exact host match', [
                'origin_host' => $originHost,
                'allowed_host' => $allowedHost
            ]);
            return true;
        }

        // Subdomain suffix match
        $suffixMatch = str_ends_with($originHost, '.' . $allowedHost);
        if ($suffixMatch) {
            $this->logger->debug('CORS origin match: subdomain suffix match', [
                'origin_host' => $originHost,
                'allowed_host' => $allowedHost
            ]);
        } else {
            $this->logger->debug('CORS origin match: no match', [
                'origin_host' => $originHost,
                'allowed_host' => $allowedHost
            ]);
        }
        return $suffixMatch;
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
