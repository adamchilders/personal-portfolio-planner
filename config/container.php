<?php

declare(strict_types=1);

use DI\Container;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Predis\Client as RedisClient;
use Illuminate\Database\Capsule\Manager as Capsule;

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

return $container;
