<?php
declare(strict_types=1);

namespace App\Utils;

use Psr\Http\Message\ResponseInterface as Response;
use Slim\Psr7\Response as SlimResponse;

class ResponseHelper
{
    public function success($data, int $statusCode = 200): Response
    {
        $response = new SlimResponse($statusCode);
        
        $responseData = [
            'result' => 'success',
            'data' => $data
        ];

        $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function error(string $message, int $statusCode = 400): Response
    {
        $response = new SlimResponse($statusCode);
        
        $responseData = [
            'result' => 'failure',
            'error' => $message
        ];

        $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function validationError(array $errors, int $statusCode = 400): Response
    {
        $response = new SlimResponse($statusCode);
        
        $responseData = [
            'result' => 'failure',
            'error' => 'Validation failed',
            'validation_errors' => $errors
        ];

        $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function notFound(string $message = 'Resource not found'): Response
    {
        return $this->error($message, 404);
    }

    public function unauthorized(string $message = 'Unauthorized'): Response
    {
        return $this->error($message, 401);
    }

    public function forbidden(string $message = 'Forbidden'): Response
    {
        return $this->error($message, 403);
    }

    public function internalError(string $message = 'Internal server error'): Response
    {
        return $this->error($message, 500);
    }

    public function created($data): Response
    {
        return $this->success($data, 201);
    }

    public function noContent(): Response
    {
        return new SlimResponse(204);
    }

    public function tooManyRequests(string $message = 'Too many requests'): Response
    {
        return $this->error($message, 429);
    }

    public function conflict(string $message = 'Conflict'): Response
    {
        return $this->error($message, 409);
    }

    public function unprocessableEntity(string $message = 'Unprocessable entity'): Response
    {
        return $this->error($message, 422);
    }

    public function withJson(Response $response, array $data, int $statusCode = 200): Response
    {
        $response = $response->withStatus($statusCode);
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function paginated($data, int $total, int $page, int $limit, int $statusCode = 200): Response
    {
        $totalPages = ceil($total / $limit);
        
        $responseData = [
            'result' => 'success',
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ];

        $response = new SlimResponse($statusCode);
        $response->getBody()->write(json_encode($responseData, JSON_UNESCAPED_UNICODE));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
}
