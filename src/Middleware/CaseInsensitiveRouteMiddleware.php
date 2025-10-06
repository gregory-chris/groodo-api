<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Middleware to make routes case-insensitive by converting the URI path to lowercase
 */
class CaseInsensitiveRouteMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $uri = $request->getUri();
        $path = $uri->getPath();
        
        // Convert path to lowercase
        $lowerPath = strtolower($path);
        
        // If path changed, create new request with lowercase path
        if ($path !== $lowerPath) {
            $uri = $uri->withPath($lowerPath);
            $request = $request->withUri($uri);
        }
        
        return $handler->handle($request);
    }
}
