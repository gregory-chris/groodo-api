<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\LoggingService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;

class LoggingMiddleware implements MiddlewareInterface
{
    private LoggingService $loggingService;
    private LoggerInterface $logger;

    public function __construct(LoggingService $loggingService, LoggerInterface $logger)
    {
        $this->loggingService = $loggingService;
        $this->logger = $logger;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $startTime = microtime(true);
        $requestId = $this->generateRequestId();
        
        // Add request ID to the request for tracking
        $request = $request->withAttribute('request_id', $requestId);

        $method = $request->getMethod();
        $uri = $request->getUri()->getPath();
        $queryString = $request->getUri()->getQuery();
        $clientIp = $this->getClientIp($request);
        $userAgent = $request->getHeaderLine('User-Agent');
        
        // Log incoming request
        $this->loggingService->logRequest($method, $uri, null, [
            'request_id' => $requestId,
            'client_ip' => $clientIp,
            'user_agent' => $userAgent,
            'query_string' => $queryString,
            'content_type' => $request->getHeaderLine('Content-Type'),
            'content_length' => $request->getHeaderLine('Content-Length'),
        ]);

        // Log request body for POST/PUT requests (excluding sensitive data)
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $body = (string)$request->getBody();
            $this->logRequestBody($body, $uri, $requestId);
        }

        try {
            // Process the request
            $response = $handler->handle($request);
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $statusCode = $response->getStatusCode();
            
            // Get user ID if available from auth middleware
            $userId = $request->getAttribute('user_id');
            
            // Log successful response
            $this->loggingService->logResponse($statusCode, $executionTime, [
                'request_id' => $requestId,
                'user_id' => $userId,
                'method' => $method,
                'uri' => $uri,
                'client_ip' => $clientIp,
                'response_size' => $response->getHeaderLine('Content-Length') ?: 'unknown',
            ]);

            // Log response body for debugging (only in debug mode)
            if ($_ENV['APP_DEBUG'] ?? false) {
                $this->logResponseBody($response, $requestId);
            }

            return $response;
            
        } catch (\Throwable $e) {
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            
            // Log error
            $this->logger->error('Request processing failed', [
                'request_id' => $requestId,
                'method' => $method,
                'uri' => $uri,
                'client_ip' => $clientIp,
                'execution_time_ms' => round($executionTime * 1000, 2),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw the exception
            throw $e;
        }
    }

    private function generateRequestId(): string
    {
        return uniqid('req_', true);
    }

    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();
        
        // Check for IP from various headers (for proxy/load balancer scenarios)
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {
                $ip = $serverParams[$header];
                // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }

    private function logRequestBody(string $body, string $uri, string $requestId): void
    {
        if (empty($body)) {
            return;
        }

        // Don't log sensitive endpoints
        $sensitiveEndpoints = [
            '/api/users/signIn',
            '/api/users/signUp',
            '/api/users/resetPassword'
        ];

        if (in_array($uri, $sensitiveEndpoints)) {
            $this->logger->debug('Request body received (content hidden for security)', [
                'request_id' => $requestId,
                'uri' => $uri,
                'body_size' => strlen($body)
            ]);
            return;
        }

        // Parse JSON body if possible
        $parsedBody = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $this->logger->debug('Request body received', [
                'request_id' => $requestId,
                'uri' => $uri,
                'body' => $this->sanitizeLogData($parsedBody)
            ]);
        } else {
            $this->logger->debug('Request body received (raw)', [
                'request_id' => $requestId,
                'uri' => $uri,
                'body' => substr($body, 0, 1000) // Limit body size in logs
            ]);
        }
    }

    private function logResponseBody(Response $response, string $requestId): void
    {
        $body = (string)$response->getBody();
        
        if (empty($body)) {
            return;
        }

        // Parse JSON response if possible
        $parsedBody = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $this->logger->debug('Response body sent', [
                'request_id' => $requestId,
                'body' => $parsedBody
            ]);
        } else {
            $this->logger->debug('Response body sent (raw)', [
                'request_id' => $requestId,
                'body' => substr($body, 0, 1000) // Limit body size in logs
            ]);
        }
    }

    private function sanitizeLogData(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            // Don't log sensitive data
            if (is_string($key) && (
                stripos($key, 'password') !== false ||
                stripos($key, 'token') !== false ||
                stripos($key, 'secret') !== false ||
                stripos($key, 'key') !== false
            )) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeLogData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
}
