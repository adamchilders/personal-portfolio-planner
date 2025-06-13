<?php

declare(strict_types=1);

use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Controllers\AuthController;
use App\Controllers\PortfolioController;
use App\Controllers\StockController;
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
    // Debug endpoint to test POST requests
    $group->post('/test', function (Request $request, Response $response) {
        try {
            $contentType = $request->getHeaderLine('Content-Type');
            $method = $request->getMethod();

            $response->getBody()->write(json_encode([
                'success' => true,
                'method' => $method,
                'content_type' => $contentType,
                'message' => 'POST request received successfully'
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    });

    // Login endpoint - supports both POST (production) and GET (development)
    $group->post('/login', [AuthController::class, 'login']);
    $group->get('/login', [AuthController::class, 'login']);

    // Register endpoint - supports both POST (production) and GET (development)
    $group->post('/register', [AuthController::class, 'register']);
    $group->get('/register', [AuthController::class, 'register']);
    $group->post('/logout', [AuthController::class, 'logout']);
    $group->get('/status', [AuthController::class, 'status']);
    $group->get('/has-users', [AuthController::class, 'hasUsers']);

    // Protected auth routes
    $group->get('/me', [AuthController::class, 'me'])->add(AuthMiddleware::class);
});

// Portfolio routes (protected)
$app->group('/api/portfolios', function ($group) {
    $group->get('', [PortfolioController::class, 'index']);
    $group->post('', [PortfolioController::class, 'create']);
    $group->get('/{id:[0-9]+}', [PortfolioController::class, 'show']);
    $group->put('/{id:[0-9]+}', [PortfolioController::class, 'update']);
    $group->delete('/{id:[0-9]+}', [PortfolioController::class, 'delete']);

    // Portfolio holdings and transactions
    $group->post('/{id:[0-9]+}/holdings', [PortfolioController::class, 'addHolding']);
    $group->post('/{id:[0-9]+}/transactions', [PortfolioController::class, 'addTransaction']);

    // Portfolio historical data
    $group->get('/{id:[0-9]+}/performance', [PortfolioController::class, 'getHistoricalPerformance']);
    $group->get('/{id:[0-9]+}/stocks/performance', [PortfolioController::class, 'getStockPerformance']);
})->add(AuthMiddleware::class);

// Stock routes (protected)
$app->group('/api/stocks', function ($group) {
    $group->get('/search', [StockController::class, 'search']);
    $group->get('/{symbol:[A-Z0-9.-]+}/quote', [StockController::class, 'quote']);
    $group->get('/{symbol:[A-Z0-9.-]+}/history', [StockController::class, 'history']);
    $group->get('/{symbol:[A-Z0-9.-]+}', [StockController::class, 'show']);
    $group->post('/{symbol:[A-Z0-9.-]+}/update-quote', [StockController::class, 'updateQuote']);
    $group->post('/quotes', [StockController::class, 'multipleQuotes']);
})->add(AuthMiddleware::class);

// Frontend routes - serve the main application
$app->get('/', function (Request $request, Response $response) {
    $html = file_get_contents(ROOT_PATH . '/templates/index.html');
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

// Serve static assets
$app->get('/assets/{type}/{file}', function (Request $request, Response $response, array $args) {
    $type = $args['type'];
    $file = $args['file'];

    // Security check - only allow specific file types and prevent directory traversal
    $allowedTypes = ['css', 'js', 'images'];
    if (!in_array($type, $allowedTypes) || strpos($file, '..') !== false) {
        return $response->withStatus(404);
    }

    $filePath = ROOT_PATH . "/public/assets/{$type}/{$file}";

    if (!file_exists($filePath)) {
        return $response->withStatus(404);
    }

    // Set appropriate content type
    $contentType = match($type) {
        'css' => 'text/css',
        'js' => 'application/javascript',
        'images' => mime_content_type($filePath) ?: 'application/octet-stream',
        default => 'application/octet-stream'
    };

    $response->getBody()->write(file_get_contents($filePath));
    return $response->withHeader('Content-Type', $contentType);
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

    // Configuration endpoint
    $group->get('/config', function (Request $request, Response $response) {
        $config = \App\Services\ConfigService::getAllConfig();
        $errors = \App\Services\ConfigService::validateConfig();

        $data = [
            'config' => $config,
            'validation' => [
                'valid' => empty($errors),
                'errors' => $errors
            ],
            'timestamp' => date('c')
        ];

        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withHeader('Content-Type', 'application/json');
    });

    // API documentation endpoint
    $group->get('/docs', function (Request $request, Response $response) {
        $data = [
            'api_version' => '1.0.0',
            'documentation' => 'Portfolio Tracker API',
            'endpoints' => [
                'Authentication' => [
                    'POST /auth/login' => 'User login',
                    'POST /auth/register' => 'User registration',
                    'POST /auth/logout' => 'User logout',
                    'GET /auth/me' => 'Get current user info'
                ],
                'Portfolios' => [
                    'GET /api/portfolios' => 'List all portfolios',
                    'POST /api/portfolios' => 'Create new portfolio',
                    'GET /api/portfolios/{id}' => 'Get portfolio details',
                    'PUT /api/portfolios/{id}' => 'Update portfolio',
                    'DELETE /api/portfolios/{id}' => 'Delete portfolio',
                    'POST /api/portfolios/{id}/holdings' => 'Add holding to portfolio',
                    'POST /api/portfolios/{id}/transactions' => 'Add transaction to portfolio'
                ],
                'Stocks' => [
                    'GET /api/stocks/search?q={query}' => 'Search for stocks',
                    'GET /api/stocks/{symbol}/quote' => 'Get current stock quote',
                    'GET /api/stocks/{symbol}/history' => 'Get historical price data',
                    'GET /api/stocks/{symbol}' => 'Get stock information',
                    'POST /api/stocks/{symbol}/update-quote' => 'Update stock quote',
                    'POST /api/stocks/quotes' => 'Get multiple stock quotes'
                ]
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
