<?php

declare(strict_types=1);

// Define the root path
define('ROOT_PATH', __DIR__);

require_once __DIR__ . '/vendor/autoload.php';

use App\Console\DividendSafetyCacheCommand;
use App\Services\DividendSafetyService;
use App\Services\FinancialModelingPrepService;
use App\Services\StockDataService;
use Illuminate\Database\Capsule\Manager as Capsule;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
try {
    $dotenv->load();
} catch (Dotenv\Exception\InvalidPathException $e) {
    // .env file not found - continue without it
}

// Initialize database connection
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => $_ENV['DB_CONNECTION'] ?? 'mysql',
    'host' => $_ENV['DB_HOST'] ?? 'mysql',
    'port' => $_ENV['DB_PORT'] ?? '3306',
    'database' => $_ENV['DB_DATABASE'] ?? 'portfolio_tracker',
    'username' => $_ENV['DB_USERNAME'] ?? 'portfolio_user',
    'password' => $_ENV['DB_PASSWORD'] ?? 'portfolio_password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);
$capsule->setAsGlobal();
$capsule->bootEloquent();

// Initialize services
$fmpService = new FinancialModelingPrepService();
$stockDataService = new StockDataService();
$dividendSafetyService = new DividendSafetyService($fmpService, $stockDataService);

// Initialize command
$command = new DividendSafetyCacheCommand($dividendSafetyService);

// Parse command line arguments
$action = $argv[1] ?? 'help';
$params = array_slice($argv, 2);

try {
    switch ($action) {
        case 'dividend-cache':
            $subAction = $params[0] ?? 'help';
            
            switch ($subAction) {
                case 'update':
                    $command->updateCache();
                    break;
                    
                case 'stats':
                    $command->showStats();
                    break;
                    
                case 'cleanup':
                    $command->cleanup();
                    break;
                    
                case 'refresh':
                    $symbols = isset($params[1]) ? explode(',', strtoupper($params[1])) : [];
                    $command->refreshSymbols($symbols);
                    break;
                    
                case 'help':
                default:
                    $command->showHelp();
                    break;
            }
            break;
            
        case 'help':
        default:
            echo "Portfolio Tracker Console\n";
            echo "========================\n";
            echo "Available commands:\n";
            echo "  dividend-cache [action] - Manage dividend safety cache\n";
            echo "  help                   - Show this help message\n";
            echo "\nFor specific command help, use:\n";
            echo "  php console.php dividend-cache help\n";
            break;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
