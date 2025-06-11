<?php

declare(strict_types=1);

use DI\Container;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Predis\Client as RedisClient;
use Illuminate\Database\Capsule\Manager as Capsule;
use App\Services\AuthService;
use App\Services\UserService;
use App\Services\PortfolioService;
use App\Controllers\AuthController;
use App\Controllers\PortfolioController;
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;
use Slim\Psr7\Factory\ResponseFactory;

/** @var Container $container */

// Database configuration
$container->set('database', function () {
    $capsule = new Capsule;
    
    $capsule->addConnection([
        'driver' => $_ENV['DB_CONNECTION'] ?? 'mysql',
        'host' => $_ENV['DB_HOST'] ?? 'mysql',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'database' => $_ENV['DB_DATABASE'] ?? 'portfolio_tracker',
        'username' => $_ENV['DB_USERNAME'] ?? 'portfolio_user',
        'password' => $_ENV['DB_PASSWORD'] ?? 'password',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ]);
    
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    
    return $capsule;
});

// Database configuration
$container->set('database', function () {
    $capsule = new Capsule;

    $capsule->addConnection([
        'driver' => $_ENV['DB_CONNECTION'] ?? 'mysql',
        'host' => $_ENV['DB_HOST'] ?? 'mysql',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'database' => $_ENV['DB_DATABASE'] ?? 'portfolio_tracker',
        'username' => $_ENV['DB_USERNAME'] ?? 'portfolio_user',
        'password' => $_ENV['DB_PASSWORD'] ?? 'password',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ]);

    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    return $capsule;
});

// Redis configuration
$container->set('redis', function () {
    return new RedisClient([
        'scheme' => 'tcp',
        'host' => $_ENV['REDIS_HOST'] ?? 'redis',
        'port' => $_ENV['REDIS_PORT'] ?? 6379,
        'password' => $_ENV['REDIS_PASSWORD'] ?? null,
        'database' => $_ENV['REDIS_DATABASE'] ?? 0,
    ]);
});

// Logger configuration
$container->set('logger', function () {
    $logger = new Logger('portfolio_tracker');
    
    $logLevel = match ($_ENV['LOG_LEVEL'] ?? 'info') {
        'debug' => Logger::DEBUG,
        'info' => Logger::INFO,
        'notice' => Logger::NOTICE,
        'warning' => Logger::WARNING,
        'error' => Logger::ERROR,
        'critical' => Logger::CRITICAL,
        'alert' => Logger::ALERT,
        'emergency' => Logger::EMERGENCY,
        default => Logger::INFO,
    };
    
    $handler = new StreamHandler(
        ROOT_PATH . '/storage/logs/app.log',
        $logLevel
    );
    
    $logger->pushHandler($handler);
    
    return $logger;
});

// Twig configuration
$container->set('view', function () {
    $twig = \Slim\Views\Twig::create(ROOT_PATH . '/resources/views', [
        'cache' => $_ENV['APP_ENV'] === 'production' ? ROOT_PATH . '/storage/cache' : false,
        'debug' => $_ENV['APP_DEBUG'] === 'true',
    ]);
    
    // Add global variables
    $twig->getEnvironment()->addGlobal('app_name', $_ENV['APP_NAME'] ?? 'Portfolio Tracker');
    $twig->getEnvironment()->addGlobal('app_env', $_ENV['APP_ENV'] ?? 'production');
    
    return $twig;
});

// Response Factory
$container->set(ResponseFactory::class, function () {
    return new ResponseFactory();
});

// Services
$container->set(UserService::class, function () {
    return new UserService();
});

$container->set(AuthService::class, function ($container) {
    return new AuthService($container->get(UserService::class));
});

$container->set(PortfolioService::class, function () {
    return new PortfolioService();
});

// Controllers
$container->set(AuthController::class, function ($container) {
    return new AuthController($container->get(AuthService::class));
});

$container->set(PortfolioController::class, function ($container) {
    return new PortfolioController($container->get(PortfolioService::class));
});

// Middleware
$container->set(AuthMiddleware::class, function ($container) {
    return new AuthMiddleware(
        $container->get(AuthService::class),
        $container->get(ResponseFactory::class)
    );
});

$container->set(AdminMiddleware::class, function ($container) {
    return new AdminMiddleware($container->get(ResponseFactory::class));
});

// Middleware aliases for Slim
$container->set('AuthMiddleware', function ($container) {
    return $container->get(AuthMiddleware::class);
});

$container->set('AdminMiddleware', function ($container) {
    return $container->get(AdminMiddleware::class);
});

return $container;
