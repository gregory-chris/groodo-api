<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Utils\ResponseHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;

class SecurityMiddleware implements MiddlewareInterface
{
    private ResponseHelper $responseHelper;
    private LoggerInterface $logger;
    private array $rateLimits = [];

    public function __construct(ResponseHelper $responseHelper, LoggerInterface $logger)
    {
        $this->responseHelper = $responseHelper;
        $this->logger = $logger;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $this->logger->debug('Processing security middleware');

        $clientIp = $this->getClientIp($request);
        $userAgent = $request->getHeaderLine('User-Agent');
        $uri = $request->getUri()->getPath();

        $this->logger->debug('Security check initiated', [
            'client_ip' => $clientIp,
            'user_agent' => $userAgent,
            'uri' => $uri
        ]);

        // Basic bot detection
        if ($this->isSuspiciousRequest($request)) {
            $this->logger->warning('Suspicious request detected', [
                'client_ip' => $clientIp,
                'user_agent' => $userAgent,
                'uri' => $uri,
                'reason' => 'Bot detection triggered'
            ]);
            return $this->responseHelper->error('Request blocked', 403);
        }

        // Rate limiting for authentication endpoints
        if ($this->isAuthEndpoint($uri)) {
            if ($this->isRateLimited($clientIp)) {
                $this->logger->warning('Rate limit exceeded', [
                    'client_ip' => $clientIp,
                    'uri' => $uri
                ]);
                return $this->responseHelper->error('Too many requests. Please try again later.', 429);
            }
        }

        $this->logger->debug('Security checks passed');
        return $handler->handle($request);
    }

    private function getClientIp(Request $request): string
    {
        // Check for IP from various headers (for proxy/load balancer scenarios)
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($headers as $header) {
            $serverParams = $request->getServerParams();
            if (!empty($serverParams[$header])) {
                $ip = $serverParams[$header];
                // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
    }

    private function isSuspiciousRequest(Request $request): bool
    {
        $userAgent = $request->getHeaderLine('User-Agent');
        $referer = $request->getHeaderLine('Referer');

        // Check for missing or suspicious User-Agent
        if (empty($userAgent)) {
            return true;
        }

        // Common bot patterns (basic detection)
        $botPatterns = [
            '/bot/i',
            '/crawler/i',
            '/spider/i',
            '/scraper/i',
            '/curl/i',
            '/wget/i',
            '/python/i',
            '/php/i',
            '/java/i',
            '/go-http-client/i',
            '/postman/i'
        ];

        foreach ($botPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }

        // Check for very short User-Agent (likely automated)
        if (strlen($userAgent) < 10) {
            return true;
        }

        // Check for missing common browser headers
        $acceptHeader = $request->getHeaderLine('Accept');
        if (empty($acceptHeader)) {
            return true;
        }

        // Check for suspicious Accept header
        if (!str_contains($acceptHeader, 'text/html') && 
            !str_contains($acceptHeader, 'application/json') &&
            !str_contains($acceptHeader, '*/*')) {
            return true;
        }

        return false;
    }

    private function isAuthEndpoint(string $uri): bool
    {
        $authEndpoints = [
            '/api/users/signIn',
            '/api/users/signUp',
            '/api/users/resetPassword'
        ];

        return in_array($uri, $authEndpoints);
    }

    private function isRateLimited(string $clientIp): bool
    {
        $maxRequests = (int)($_ENV['RATE_LIMIT_AUTH_REQUESTS'] ?? 10);
        $timeWindow = (int)($_ENV['RATE_LIMIT_AUTH_WINDOW'] ?? 300); // 5 minutes
        $currentTime = time();

        // Initialize rate limit data for IP if not exists
        if (!isset($this->rateLimits[$clientIp])) {
            $this->rateLimits[$clientIp] = [
                'requests' => [],
                'blocked_until' => 0
            ];
        }

        $ipData = &$this->rateLimits[$clientIp];

        // Check if IP is currently blocked
        if ($ipData['blocked_until'] > $currentTime) {
            return true;
        }

        // Clean old requests outside the time window
        $ipData['requests'] = array_filter(
            $ipData['requests'],
            fn($timestamp) => ($currentTime - $timestamp) < $timeWindow
        );

        // Check if limit exceeded
        if (count($ipData['requests']) >= $maxRequests) {
            // Block IP for the time window
            $ipData['blocked_until'] = $currentTime + $timeWindow;
            
            $this->logger->warning('IP blocked due to rate limiting', [
                'client_ip' => $clientIp,
                'request_count' => count($ipData['requests']),
                'blocked_until' => date('c', $ipData['blocked_until'])
            ]);
            
            return true;
        }

        // Add current request timestamp
        $ipData['requests'][] = $currentTime;

        return false;
    }

    private function validateRequestHeaders(Request $request): bool
    {
        // Check for required headers that legitimate browsers send
        $requiredHeaders = ['User-Agent', 'Accept'];
        
        foreach ($requiredHeaders as $header) {
            if (empty($request->getHeaderLine($header))) {
                return false;
            }
        }

        return true;
    }

    private function detectSqlInjectionAttempt(Request $request): bool
    {
        $body = (string)$request->getBody();
        $queryParams = $request->getQueryParams();
        
        // Common SQL injection patterns
        $sqlPatterns = [
            '/(\bUNION\b|\bSELECT\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bDROP\b)/i',
            '/(\bOR\b|\bAND\b)\s+\d+\s*=\s*\d+/i',
            '/[\'";]\s*(OR|AND)\s+[\'"]?\d+[\'"]?\s*=\s*[\'"]?\d+/i',
            '/\b(exec|execute|sp_|xp_)\b/i'
        ];

        // Check request body
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $body)) {
                return true;
            }
        }

        // Check query parameters
        foreach ($queryParams as $param) {
            if (is_string($param)) {
                foreach ($sqlPatterns as $pattern) {
                    if (preg_match($pattern, $param)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function cleanupRateLimits(): void
    {
        $currentTime = time();
        
        foreach ($this->rateLimits as $ip => $data) {
            // Remove expired blocks and old requests
            if ($data['blocked_until'] < $currentTime && empty($data['requests'])) {
                unset($this->rateLimits[$ip]);
            }
        }
        
        $this->logger->debug('Rate limit cleanup completed', [
            'remaining_ips' => count($this->rateLimits)
        ]);
    }
}
