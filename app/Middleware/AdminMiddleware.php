<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Factory\ResponseFactory;

class AdminMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactory $responseFactory
    ) {}
    
    public function process(Request $request, RequestHandler $handler): Response
    {
        $user = $request->getAttribute('user');
        
        if (!$user) {
            return $this->forbiddenResponse('Authentication required');
        }
        
        if (!$user->isAdmin()) {
            return $this->forbiddenResponse('Admin access required');
        }
        
        return $handler->handle($request);
    }
    
    private function forbiddenResponse(string $message): Response
    {
        $response = $this->responseFactory->createResponse(403);
        
        $data = [
            'error' => 'Forbidden',
            'message' => $message,
            'timestamp' => date('c')
        ];
        
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
