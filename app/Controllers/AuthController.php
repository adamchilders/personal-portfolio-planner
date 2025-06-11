<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    public function __construct(
        private AuthService $authService
    ) {}
    
    /**
     * Login endpoint
     */
    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        if (empty($data['identifier']) || empty($data['password'])) {
            return $this->errorResponse($response, 'Username/email and password are required', 400);
        }
        
        $ipAddress = $this->getClientIp($request);
        $userAgent = $request->getHeaderLine('User-Agent');
        
        $result = $this->authService->login(
            $data['identifier'],
            $data['password'],
            $ipAddress,
            $userAgent
        );
        
        if (!$result['success']) {
            return $this->errorResponse($response, $result['message'], 401);
        }
        
        // Set session cookie
        $cookie = $this->authService->generateSessionCookie($result['token']);
        $response = $response->withHeader('Set-Cookie', $this->formatCookie($cookie));
        
        $responseData = [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $result['user']->id,
                'username' => $result['user']->username,
                'email' => $result['user']->email,
                'role' => $result['user']->role,
                'display_name' => $result['user']->display_name
            ],
            'token' => $result['token']
        ];
        
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Register endpoint
     */
    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        $required = ['username', 'email', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->errorResponse($response, "Field '{$field}' is required", 400);
            }
        }
        
        $result = $this->authService->register($data);
        
        if (!$result['success']) {
            return $this->errorResponse($response, $result['message'], 400);
        }
        
        $responseData = [
            'success' => true,
            'message' => 'Registration successful',
            'user' => [
                'id' => $result['user']->id,
                'username' => $result['user']->username,
                'email' => $result['user']->email,
                'role' => $result['user']->role,
                'display_name' => $result['user']->display_name
            ],
            'is_first_user' => $result['is_first_user']
        ];
        
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }
    
    /**
     * Logout endpoint
     */
    public function logout(Request $request, Response $response): Response
    {
        $sessionToken = $this->authService->getSessionTokenFromRequest($request);
        
        if ($sessionToken) {
            $this->authService->logout($sessionToken);
        }
        
        // Clear session cookie
        $response = $response->withHeader('Set-Cookie', 'portfolio_session=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/; httponly');
        
        $responseData = [
            'success' => true,
            'message' => 'Logout successful'
        ];
        
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Get current user info
     */
    public function me(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        
        $responseData = [
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'display_name' => $user->display_name,
                'is_active' => $user->is_active,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'last_login' => $user->last_login?->toISOString(),
                'created_at' => $user->created_at->toISOString()
            ]
        ];
        
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Check authentication status
     */
    public function status(Request $request, Response $response): Response
    {
        $sessionToken = $this->authService->getSessionTokenFromRequest($request);
        
        if (!$sessionToken) {
            return $this->errorResponse($response, 'No session token', 401);
        }
        
        $user = $this->authService->getUserFromSession($sessionToken);
        
        if (!$user) {
            return $this->errorResponse($response, 'Invalid session', 401);
        }
        
        $responseData = [
            'authenticated' => true,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'role' => $user->role,
                'display_name' => $user->display_name
            ]
        ];
        
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    /**
     * Check if system has users (for welcome screen)
     */
    public function hasUsers(Request $request, Response $response): Response
    {
        $hasUsers = $this->authService->hasUsers();
        
        $responseData = [
            'has_users' => $hasUsers,
            'needs_setup' => !$hasUsers
        ];
        
        $response->getBody()->write(json_encode($responseData));
        return $response->withHeader('Content-Type', 'application/json');
    }
    
    private function errorResponse(Response $response, string $message, int $status = 400): Response
    {
        $data = [
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ];
        
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
    
    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();

        // Check for IP from various headers
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {
                $ip = trim(explode(',', (string)$serverParams[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }
    
    private function formatCookie(array $cookie): string
    {
        $parts = [
            $cookie['name'] . '=' . $cookie['value']
        ];
        
        if (isset($cookie['expires'])) {
            $parts[] = 'expires=' . gmdate('D, d M Y H:i:s T', $cookie['expires']);
        }
        
        if (isset($cookie['path'])) {
            $parts[] = 'path=' . $cookie['path'];
        }
        
        if (isset($cookie['secure']) && $cookie['secure']) {
            $parts[] = 'secure';
        }
        
        if (isset($cookie['httponly']) && $cookie['httponly']) {
            $parts[] = 'httponly';
        }
        
        if (isset($cookie['samesite'])) {
            $parts[] = 'samesite=' . $cookie['samesite'];
        }
        
        return implode('; ', $parts);
    }
}
