<?php

declare(strict_types=1);

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controllers\AuthController;
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;

/** @var App $app */

// Health check endpoint
$app->get('/health', function (Request $request, Response $response) {
    $data = [
        'status' => 'healthy',
        'environment' => $_ENV['APP_ENV'] ?? 'production',
        'timestamp' => date('c'),
        'services' => [
            'database' => 'checking...',
            'redis' => 'checking...',
        ]
    ];
    
    // Check database connection
    try {
        $database = $this->get('database');
        $database->getConnection()->getPdo();
        $data['services']['database'] = 'connected';
    } catch (Exception $e) {
        $data['services']['database'] = 'error: ' . $e->getMessage();
        $data['status'] = 'unhealthy';
    }
    
    // Check Redis connection
    try {
        $redis = $this->get('redis');
        $redis->ping();
        $data['services']['redis'] = 'connected';
    } catch (Exception $e) {
        $data['services']['redis'] = 'error: ' . $e->getMessage();
        $data['status'] = 'unhealthy';
    }
    
    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($data['status'] === 'healthy' ? 200 : 503);
});

// Simple health check for load balancers
$app->get('/ping', function (Request $request, Response $response) {
    $response->getBody()->write('pong');
    return $response->withHeader('Content-Type', 'text/plain');
});

// Authentication routes
$app->group('/auth', function ($group) {
    $group->post('/login', [AuthController::class, 'login']);
    $group->post('/register', [AuthController::class, 'register']);
    $group->post('/logout', [AuthController::class, 'logout']);
    $group->get('/status', [AuthController::class, 'status']);
    $group->get('/has-users', [AuthController::class, 'hasUsers']);

    // Protected auth routes
    $group->get('/me', [AuthController::class, 'me'])->add(AuthMiddleware::class);
});

// Welcome page
$app->get('/', function (Request $request, Response $response) {
    $data = [
        'title' => 'Personal Portfolio Tracker',
        'message' => 'Welcome to your Portfolio Tracking Application!',
        'version' => '1.0.0',
        'environment' => $_ENV['APP_ENV'] ?? 'production',
        'features' => [
            'Multi-portfolio support',
            'Real-time stock data',
            'Smart API integration',
            'Background data processing',
            'Secure authentication',
            'Admin interface'
        ]
    ];
    
    // For now, return JSON. Later we'll use Twig templates
    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// API routes group
$app->group('/api', function ($group) {

    // API status check
    $group->get('/status', function (Request $request, Response $response) {
        $data = [
            'api_version' => '1.0.0',
            'status' => 'operational',
            'timestamp' => date('c')
        ];

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // Placeholder for future API endpoints
    $group->get('/portfolios', function (Request $request, Response $response) {
        $data = [
            'message' => 'Portfolio API endpoint - Coming soon!',
            'endpoints' => [
                'GET /api/portfolios' => 'List all portfolios',
                'POST /api/portfolios' => 'Create new portfolio',
                'GET /api/portfolios/{id}' => 'Get portfolio details',
                'PUT /api/portfolios/{id}' => 'Update portfolio',
                'DELETE /api/portfolios/{id}' => 'Delete portfolio'
            ]
        ];

        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    });

});

// Admin routes group (placeholder)
$app->group('/admin', function ($group) {

    $group->get('', function (Request $request, Response $response) {
        $data = [
            'message' => 'Admin Interface - Coming soon!',
            'features' => [
                'User management',
                'API key configuration',
                'Data fetch scheduling',
                'System monitoring',
                'Database maintenance'
            ]
        ];

        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    });

});

// Catch-all route for 404s
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function (Request $request, Response $response) {
    $data = [
        'error' => 'Not Found',
        'message' => 'The requested endpoint does not exist.',
        'path' => $request->getUri()->getPath(),
        'method' => $request->getMethod()
    ];
    
    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(404);
});
