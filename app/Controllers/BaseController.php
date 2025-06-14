<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;

abstract class BaseController
{
    /**
     * Return a JSON response
     */
    protected function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
    
    /**
     * Return a success JSON response
     */
    protected function successResponse(Response $response, array $data = [], string $message = 'Success'): Response
    {
        return $this->jsonResponse($response, array_merge([
            'success' => true,
            'message' => $message
        ], $data));
    }
    
    /**
     * Return an error JSON response
     */
    protected function errorResponse(Response $response, string $message, int $status = 400, array $data = []): Response
    {
        return $this->jsonResponse($response, array_merge([
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ], $data), $status);
    }
    
    /**
     * Validate required fields in request data
     */
    protected function validateRequired(array $data, array $required): array
    {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            throw new \InvalidArgumentException('Missing required fields: ' . implode(', ', $missing));
        }
        
        return $data;
    }
    
    /**
     * Get pagination parameters from request
     */
    protected function getPaginationParams(array $queryParams): array
    {
        $page = max(1, (int)($queryParams['page'] ?? 1));
        $limit = min(100, max(1, (int)($queryParams['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        return [
            'page' => $page,
            'limit' => $limit,
            'offset' => $offset
        ];
    }
    
    /**
     * Format pagination response
     */
    protected function paginatedResponse(Response $response, array $data, int $total, array $pagination): Response
    {
        $totalPages = ceil($total / $pagination['limit']);
        
        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $data,
            'pagination' => [
                'page' => $pagination['page'],
                'limit' => $pagination['limit'],
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $pagination['page'] < $totalPages,
                'has_prev' => $pagination['page'] > 1
            ]
        ]);
    }
}
