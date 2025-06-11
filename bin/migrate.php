#!/usr/bin/env php
<?php

declare(strict_types=1);

// Define the root path
define('ROOT_PATH', dirname(__DIR__));

// Require the autoloader
require ROOT_PATH . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(ROOT_PATH);
$dotenv->load();

use Illuminate\Database\Capsule\Manager as Capsule;

// Initialize database connection
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

// Migration runner class
class MigrationRunner
{
    private $db;
    private $migrationsPath;
    
    public function __construct()
    {
        $this->db = Capsule::connection();
        $this->migrationsPath = ROOT_PATH . '/database/migrations';
        
        // Ensure migrations table exists
        $this->createMigrationsTable();
    }
    
    private function createMigrationsTable(): void
    {
        $this->db->statement("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
    
    public function run(): void
    {
        echo "🚀 Running database migrations...\n\n";
        
        $migrationFiles = $this->getMigrationFiles();
        $executedMigrations = $this->getExecutedMigrations();
        
        $pendingMigrations = array_diff($migrationFiles, $executedMigrations);
        
        if (empty($pendingMigrations)) {
            echo "✅ No pending migrations found.\n";
            return;
        }
        
        foreach ($pendingMigrations as $migration) {
            $this->executeMigration($migration);
        }
        
        echo "\n🎉 All migrations completed successfully!\n";
    }
    
    private function getMigrationFiles(): array
    {
        $files = glob($this->migrationsPath . '/*.sql');
        return array_map('basename', $files);
    }
    
    private function getExecutedMigrations(): array
    {
        return $this->db->table('migrations')
            ->pluck('migration')
            ->toArray();
    }
    
    private function executeMigration(string $migration): void
    {
        echo "⚡ Executing migration: {$migration}\n";
        
        $migrationPath = $this->migrationsPath . '/' . $migration;
        $sql = file_get_contents($migrationPath);
        
        try {
            // Split SQL file by semicolons and execute each statement
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($stmt) => !empty($stmt) && !str_starts_with($stmt, '--')
            );
            
            $this->db->beginTransaction();
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $this->db->statement($statement);
                }
            }
            
            // Record migration as executed
            $this->db->table('migrations')->insert([
                'migration' => $migration,
                'executed_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->db->commit();
            echo "   ✅ Migration {$migration} completed successfully\n";
            
        } catch (Exception $e) {
            $this->db->rollBack();
            echo "   ❌ Migration {$migration} failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    public function status(): void
    {
        echo "📊 Migration Status:\n\n";
        
        $migrationFiles = $this->getMigrationFiles();
        $executedMigrations = $this->getExecutedMigrations();
        
        foreach ($migrationFiles as $migration) {
            $status = in_array($migration, $executedMigrations) ? '✅ Executed' : '⏳ Pending';
            echo "   {$status} - {$migration}\n";
        }
        
        echo "\nTotal migrations: " . count($migrationFiles) . "\n";
        echo "Executed: " . count($executedMigrations) . "\n";
        echo "Pending: " . (count($migrationFiles) - count($executedMigrations)) . "\n";
    }
}

// Command line interface
$command = $argv[1] ?? 'run';

$runner = new MigrationRunner();

switch ($command) {
    case 'run':
        $runner->run();
        break;
    case 'status':
        $runner->status();
        break;
    default:
        echo "Usage: php bin/migrate.php [run|status]\n";
        echo "  run    - Execute pending migrations\n";
        echo "  status - Show migration status\n";
        exit(1);
}
