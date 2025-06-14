<?php

require_once __DIR__ . '/vendor/autoload.php';

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
    // Drop and recreate the table with correct structure
    echo "Dropping existing api_keys table...\n";
    Capsule::statement('DROP TABLE IF EXISTS api_keys');

    echo "Creating new api_keys table...\n";
    Capsule::statement("
    CREATE TABLE api_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        provider VARCHAR(50) NOT NULL UNIQUE,
        api_key VARCHAR(255) NOT NULL,
        is_active BOOLEAN NOT NULL DEFAULT TRUE,
        rate_limit_per_minute INT DEFAULT NULL,
        rate_limit_per_day INT DEFAULT NULL,
        last_used TIMESTAMP NULL,
        usage_count_today INT NOT NULL DEFAULT 0,
        usage_reset_date DATE NOT NULL DEFAULT (CURDATE()),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_provider (provider),
        INDEX idx_active (is_active),
        INDEX idx_last_used (last_used)
    )
    ");

    echo "Inserting default records...\n";
    Capsule::table('api_keys')->insert([
        [
            'provider' => 'yahoo_finance',
            'api_key' => 'free',
            'is_active' => true,
            'rate_limit_per_minute' => 60,
            'rate_limit_per_day' => 2000,
            'notes' => 'Free Yahoo Finance API - no key required'
        ],
        [
            'provider' => 'financial_modeling_prep',
            'api_key' => '',
            'is_active' => false,
            'rate_limit_per_minute' => 300,
            'rate_limit_per_day' => 10000,
            'notes' => 'Financial Modeling Prep API - requires paid subscription'
        ]
    ]);

    echo "✅ API keys table created and populated successfully!\n";
    
    // Verify
    $count = Capsule::table('api_keys')->count();
    echo "📊 Found {$count} API key records\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
