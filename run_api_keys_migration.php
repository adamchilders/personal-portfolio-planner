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
    echo "Running API keys migration...\n";
    
    // Read and execute the migration
    $migrationSql = file_get_contents(__DIR__ . '/database/migrations/005_create_api_keys_table.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $migrationSql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !str_starts_with($statement, '--')) {
            echo "Executing: " . substr($statement, 0, 50) . "...\n";
            Capsule::statement($statement);
        }
    }
    
    echo "✅ API keys migration completed successfully!\n";
    
    // Verify the table was created
    $result = Capsule::select("SHOW TABLES LIKE 'api_keys'");
    if (count($result) > 0) {
        echo "✅ api_keys table created successfully\n";
        
        // Check if default records exist
        $count = Capsule::table('api_keys')->count();
        echo "📊 Found {$count} API key records\n";
    } else {
        echo "❌ api_keys table was not created\n";
    }
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
