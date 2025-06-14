<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Dividend;
use App\Models\Stock;
use App\Models\PortfolioHolding;
use App\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

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

try {
    // Check if we have any dividend data
    echo "Checking dividend data in database...\n";
    $dividendCount = Dividend::count();
    echo "Total dividends in database: {$dividendCount}\n";

    if ($dividendCount > 0) {
        echo "\nSample dividend records:\n";
        $sampleDividends = Dividend::orderBy('created_at', 'desc')->limit(5)->get();
        foreach ($sampleDividends as $dividend) {
            echo "- {$dividend->symbol}: \${$dividend->amount} on {$dividend->ex_date}\n";
        }
    }

    // Check what stocks we have in portfolios
    echo "\nChecking stocks in portfolios...\n";
    $uniqueSymbols = PortfolioHolding::where('is_active', true)->distinct()->pluck('stock_symbol');
    echo "Unique symbols in portfolios: " . $uniqueSymbols->implode(', ') . "\n";

    // Check if these stocks have dividend data
    echo "\nDividend data for portfolio stocks:\n";
    foreach ($uniqueSymbols as $symbol) {
        $dividendCount = Dividend::where('symbol', $symbol)->count();
        echo "{$symbol}: {$dividendCount} dividend records\n";
    }

    // Check if stocks exist in stocks table
    echo "\nChecking if stocks exist in stocks table:\n";
    foreach ($uniqueSymbols as $symbol) {
        $stockExists = Stock::where('symbol', $symbol)->exists();
        echo "{$symbol}: " . ($stockExists ? "exists" : "missing") . " in stocks table\n";
    }

    // Check users
    echo "\nChecking users in database:\n";
    $users = User::all();
    foreach ($users as $user) {
        echo "User: {$user->email}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
