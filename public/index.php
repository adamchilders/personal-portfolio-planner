<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use DI\Container;

// Define the root path
define('ROOT_PATH', dirname(__DIR__));

// Require the autoloader
require ROOT_PATH . '/vendor/autoload.php';

// Load environment variables (optional in Kubernetes)
$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
try {
    $dotenv->load();
} catch (Dotenv\Exception\InvalidPathException $e) {
    // .env file not found - this is OK in Kubernetes where env vars come from secrets/configmaps
    // Continue without loading .env file
}

// Create container
$container = new Container();

// Set container to create App with on AppFactory
AppFactory::setContainer($container);

// Create App
$app = AppFactory::create();

// Add error middleware
$errorMiddleware = $app->addErrorMiddleware(
    $_ENV['APP_DEBUG'] === 'true',
    true,
    true
);

// Add CORS middleware (must be added before routing)
$app->add($container->get(\App\Middleware\CorsMiddleware::class));

// Add routing middleware
$app->addRoutingMiddleware();

// Add body parsing middleware
$app->addBodyParsingMiddleware();

// Configure container
require ROOT_PATH . '/config/container.php';

// Initialize database connection
$container->get('database');

// Load routes
require ROOT_PATH . '/config/routes.php';

// Run app
$app->run();
