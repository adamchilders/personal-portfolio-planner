<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\MiddlewareInterface;
use Slim\Psr7\Factory\ResponseFactory;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthService $authService,
        private ResponseFactory $responseFactory
    ) {}
    
    public function process(Request $request, RequestHandler $handler): Response
    {
        $sessionToken = $this->authService->getSessionTokenFromRequest($request);
        
        if (!$sessionToken) {
            return $this->unauthorizedResponse('No session token provided');
        }
        
        $user = $this->authService->getUserFromSession($sessionToken);
        
        if (!$user) {
            return $this->unauthorizedResponse('Invalid or expired session');
        }
        
        if (!$user->isActive()) {
            return $this->unauthorizedResponse('Account is deactivated');
        }
        
        // Add user to request attributes
        $request = $request->withAttribute('user', $user);
        $request = $request->withAttribute('session_token', $sessionToken);
        
        return $handler->handle($request);
    }
    
    private function unauthorizedResponse(string $message): Response
    {
        $response = $this->responseFactory->createResponse(401);
        
        $data = [
            'error' => 'Unauthorized',
            'message' => $message,
            'timestamp' => date('c')
        ];
        
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
