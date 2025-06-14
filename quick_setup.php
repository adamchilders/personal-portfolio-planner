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
    echo "Quick setup for admin system...\n";
    
    // Create api_keys table if not exists
    echo "Creating api_keys table...\n";
    Capsule::statement("
    CREATE TABLE IF NOT EXISTS api_keys (
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
    
    // Insert default API keys if not exist
    $existingKeys = Capsule::table('api_keys')->count();
    if ($existingKeys == 0) {
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
        echo "✅ Default API keys inserted\n";
    }
    
    // Create data_provider_config table if not exists
    echo "Creating data_provider_config table...\n";
    Capsule::statement("
    CREATE TABLE IF NOT EXISTS data_provider_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        data_type VARCHAR(50) NOT NULL UNIQUE,
        primary_provider VARCHAR(50) NOT NULL,
        fallback_provider VARCHAR(50) DEFAULT NULL,
        is_active BOOLEAN NOT NULL DEFAULT TRUE,
        config_options JSON DEFAULT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_data_type (data_type),
        INDEX idx_primary_provider (primary_provider),
        INDEX idx_active (is_active)
    )
    ");
    
    // Insert default configurations if not exist
    $existingConfigs = Capsule::table('data_provider_config')->count();
    if ($existingConfigs == 0) {
        Capsule::table('data_provider_config')->insert([
            [
                'data_type' => 'stock_quotes',
                'primary_provider' => 'yahoo_finance',
                'fallback_provider' => 'financial_modeling_prep',
                'notes' => 'Real-time stock price quotes'
            ],
            [
                'data_type' => 'historical_prices',
                'primary_provider' => 'yahoo_finance',
                'fallback_provider' => 'financial_modeling_prep',
                'notes' => 'Historical stock price data'
            ],
            [
                'data_type' => 'dividend_data',
                'primary_provider' => 'yahoo_finance',
                'fallback_provider' => 'financial_modeling_prep',
                'notes' => 'Dividend payment information'
            ],
            [
                'data_type' => 'company_profiles',
                'primary_provider' => 'yahoo_finance',
                'fallback_provider' => 'financial_modeling_prep',
                'notes' => 'Company information and profiles'
            ]
        ]);
        echo "✅ Default data provider configs inserted\n";
    }
    
    echo "✅ Quick setup completed!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
