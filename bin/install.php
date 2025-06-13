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

/**
 * Portfolio Tracker Installation Script
 * 
 * This script handles the initial setup and installation of the Portfolio Tracker application.
 * It checks if the database is empty and runs the necessary setup procedures.
 */
class PortfolioInstaller
{
    private $db;
    private $isVerbose;
    
    public function __construct(bool $verbose = true)
    {
        $this->isVerbose = $verbose;
        $this->initializeDatabase();
    }
    
    private function initializeDatabase(): void
    {
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
        
        $this->db = Capsule::connection();
    }
    
    public function install(): bool
    {
        $this->log("🚀 Starting Portfolio Tracker installation...\n");
        
        try {
            // Check database connection
            if (!$this->checkDatabaseConnection()) {
                $this->log("❌ Database connection failed. Please check your configuration.", 'error');
                return false;
            }
            
            // Check if installation is needed
            if (!$this->needsInstallation()) {
                $this->log("✅ Application is already installed and configured.");
                return true;
            }
            
            // Run installation steps
            $this->log("📋 Running installation steps...\n");
            
            if (!$this->runMigrations()) {
                return false;
            }
            
            if (!$this->setupSystemConfiguration()) {
                return false;
            }
            
            if (!$this->createDefaultAdminUser()) {
                return false;
            }
            
            if (!$this->setupDataSources()) {
                return false;
            }
            
            if (!$this->finalizeInstallation()) {
                return false;
            }
            
            $this->log("\n🎉 Installation completed successfully!");
            $this->log("📝 Please review the default admin credentials and change them immediately.");
            $this->log("🔐 Default admin: admin@portfolio-tracker.local / admin123");
            
            return true;
            
        } catch (Exception $e) {
            $this->log("❌ Installation failed: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    private function checkDatabaseConnection(): bool
    {
        try {
            $this->db->getPdo();
            $this->log("✅ Database connection successful");
            return true;
        } catch (Exception $e) {
            $this->log("❌ Database connection failed: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    private function needsInstallation(): bool
    {
        try {
            // Check if system_info table exists and has installation record
            $result = $this->db->select("
                SELECT COUNT(*) as count 
                FROM information_schema.tables 
                WHERE table_schema = ? AND table_name = 'system_info'
            ", [$_ENV['DB_DATABASE'] ?? 'portfolio_tracker']);
            
            if ($result[0]->count == 0) {
                $this->log("📦 Fresh installation detected - system_info table not found");
                return true;
            }
            
            // Check if installation is complete
            $installationStatus = $this->db->select("
                SELECT value FROM system_info 
                WHERE key_name = 'installation_status'
            ");
            
            if (empty($installationStatus) || $installationStatus[0]->value !== 'complete') {
                $this->log("📦 Incomplete installation detected");
                return true;
            }
            
            $this->log("✅ Installation already complete");
            return false;
            
        } catch (Exception $e) {
            $this->log("📦 Fresh installation detected - " . $e->getMessage());
            return true;
        }
    }
    
    private function runMigrations(): bool
    {
        $this->log("⚡ Running database migrations...");
        
        // Use the existing migration runner
        $migrationCommand = ROOT_PATH . '/bin/migrate.php run';
        $output = [];
        $returnCode = 0;
        
        exec("php $migrationCommand 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            $this->log("❌ Migration failed:", 'error');
            foreach ($output as $line) {
                $this->log("   " . $line, 'error');
            }
            return false;
        }
        
        $this->log("✅ Database migrations completed");
        return true;
    }
    
    private function setupSystemConfiguration(): bool
    {
        $this->log("⚙️  Setting up system configuration...");
        
        $defaultSettings = [
            'app_name' => $_ENV['APP_NAME'] ?? 'Personal Portfolio Tracker',
            'app_version' => '1.0.0',
            'app_timezone' => $_ENV['APP_TIMEZONE'] ?? 'America/New_York',
            'default_currency' => 'USD',
            'market_hours_start' => $_ENV['MARKET_HOURS_START'] ?? '09:30',
            'market_hours_end' => $_ENV['MARKET_HOURS_END'] ?? '16:00',
            'market_timezone' => $_ENV['MARKET_TIMEZONE'] ?? 'America/New_York',
            'default_fetch_interval' => $_ENV['DEFAULT_FETCH_INTERVAL'] ?? '900',
            'api_rate_limit' => $_ENV['API_RATE_LIMIT'] ?? '60',
            'backup_enabled' => $_ENV['BACKUP_ENABLED'] ?? 'true',
            'health_check_enabled' => $_ENV['HEALTH_CHECK_ENABLED'] ?? 'true',
            'installation_status' => 'in_progress',
            'installation_date' => date('Y-m-d H:i:s'),
        ];
        
        try {
            foreach ($defaultSettings as $key => $value) {
                $this->db->table('system_settings')->updateOrInsert(
                    ['setting_key' => $key],
                    [
                        'setting_key' => $key,
                        'setting_value' => $value,
                        'setting_type' => is_bool($value) ? 'boolean' : (is_numeric($value) ? 'integer' : 'string'),
                        'description' => $this->getSettingDescription($key),
                        'is_public' => in_array($key, ['app_name', 'app_version', 'default_currency']),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]
                );
            }
            
            $this->log("✅ System configuration completed");
            return true;
            
        } catch (Exception $e) {
            $this->log("❌ System configuration failed: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    private function createDefaultAdminUser(): bool
    {
        $this->log("👤 Creating default admin user...");
        
        try {
            // Check if any admin users exist
            $adminCount = $this->db->table('users')
                ->where('role', 'admin')
                ->count();
            
            if ($adminCount > 0) {
                $this->log("✅ Admin user already exists, skipping creation");
                return true;
            }
            
            // Create default admin user
            $adminData = [
                'username' => 'admin',
                'email' => 'admin@portfolio-tracker.local',
                'password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
                'role' => 'admin',
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'is_active' => true,
                'email_verified_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $userId = $this->db->table('users')->insertGetId($adminData);
            
            // Create user preferences
            $this->db->table('user_preferences')->insert([
                'user_id' => $userId,
                'theme' => 'auto',
                'timezone' => $_ENV['APP_TIMEZONE'] ?? 'America/New_York',
                'currency' => 'USD',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->log("✅ Default admin user created successfully");
            return true;
            
        } catch (Exception $e) {
            $this->log("❌ Failed to create admin user: " . $e->getMessage(), 'error');
            return false;
        }
    }

    private function setupDataSources(): bool
    {
        $this->log("📊 Setting up default data sources...");

        try {
            $defaultDataSources = [
                [
                    'name' => 'Yahoo Finance',
                    'provider' => 'yahoo',
                    'priority' => 1,
                    'is_active' => true,
                    'base_url' => 'https://query1.finance.yahoo.com',
                    'rate_limit' => 2000,
                    'supports_real_time' => true,
                    'supports_historical' => true,
                    'supports_dividends' => true,
                    'config' => json_encode([
                        'endpoints' => [
                            'quote' => '/v8/finance/chart/{symbol}',
                            'historical' => '/v8/finance/chart/{symbol}',
                            'dividends' => '/v8/finance/chart/{symbol}'
                        ]
                    ]),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            ];

            foreach ($defaultDataSources as $source) {
                $this->db->table('data_sources')->updateOrInsert(
                    ['name' => $source['name']],
                    $source
                );
            }

            $this->log("✅ Data sources configured");
            return true;

        } catch (Exception $e) {
            $this->log("❌ Failed to setup data sources: " . $e->getMessage(), 'error');
            return false;
        }
    }

    private function finalizeInstallation(): bool
    {
        $this->log("🏁 Finalizing installation...");

        try {
            // Update installation status
            $this->db->table('system_settings')->updateOrInsert(
                ['setting_key' => 'installation_status'],
                [
                    'setting_key' => 'installation_status',
                    'setting_value' => 'complete',
                    'setting_type' => 'string',
                    'description' => 'Installation completion status',
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            );

            // Create initial health check record
            $this->db->table('health_check')->updateOrInsert(
                ['id' => 1],
                [
                    'status' => 'installed',
                    'checked_at' => date('Y-m-d H:i:s')
                ]
            );

            $this->log("✅ Installation finalized");
            return true;

        } catch (Exception $e) {
            $this->log("❌ Failed to finalize installation: " . $e->getMessage(), 'error');
            return false;
        }
    }

    private function getSettingDescription(string $key): string
    {
        $descriptions = [
            'app_name' => 'Application name displayed to users',
            'app_version' => 'Current application version',
            'app_timezone' => 'Default application timezone',
            'default_currency' => 'Default currency for portfolios',
            'market_hours_start' => 'Market opening time (HH:MM format)',
            'market_hours_end' => 'Market closing time (HH:MM format)',
            'market_timezone' => 'Timezone for market hours',
            'default_fetch_interval' => 'Default interval for data fetching in seconds',
            'api_rate_limit' => 'API rate limit per minute',
            'backup_enabled' => 'Enable automatic database backups',
            'health_check_enabled' => 'Enable health check monitoring',
            'installation_status' => 'Current installation status',
            'installation_date' => 'Date when application was installed'
        ];

        return $descriptions[$key] ?? '';
    }

    public function checkStatus(): void
    {
        $this->log("📊 Checking installation status...\n");

        try {
            if (!$this->checkDatabaseConnection()) {
                return;
            }

            // Check if system_info table exists
            $result = $this->db->select("
                SELECT COUNT(*) as count
                FROM information_schema.tables
                WHERE table_schema = ? AND table_name = 'system_info'
            ", [$_ENV['DB_DATABASE'] ?? 'portfolio_tracker']);

            if ($result[0]->count == 0) {
                $this->log("❌ Not installed - system_info table not found");
                return;
            }

            // Get installation status
            $status = $this->db->table('system_settings')
                ->where('setting_key', 'installation_status')
                ->first();

            if (!$status) {
                $this->log("❌ Installation status unknown");
                return;
            }

            $this->log("✅ Installation status: " . $status->setting_value);

            if ($status->setting_value === 'complete') {
                // Show additional info
                $installDate = $this->db->table('system_settings')
                    ->where('setting_key', 'installation_date')
                    ->first();

                if ($installDate) {
                    $this->log("📅 Installed on: " . $installDate->setting_value);
                }

                $adminCount = $this->db->table('users')
                    ->where('role', 'admin')
                    ->count();

                $this->log("👥 Admin users: " . $adminCount);
            }

        } catch (Exception $e) {
            $this->log("❌ Status check failed: " . $e->getMessage(), 'error');
        }
    }

    private function log(string $message, string $level = 'info'): void
    {
        if (!$this->isVerbose && $level === 'info') {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        echo "[$timestamp] $message\n";

        // Also log to file if possible
        $logFile = ROOT_PATH . '/storage/logs/install.log';
        if (is_writable(dirname($logFile))) {
            file_put_contents($logFile, "[$timestamp] [$level] $message\n", FILE_APPEND | LOCK_EX);
        }
    }
}

// Command line interface
$command = $argv[1] ?? 'install';
$verbose = !in_array('--quiet', $argv);

$installer = new PortfolioInstaller($verbose);

switch ($command) {
    case 'install':
        $success = $installer->install();
        exit($success ? 0 : 1);

    case 'status':
        $installer->checkStatus();
        break;

    default:
        echo "Usage: php bin/install.php [install|status] [--quiet]\n";
        echo "  install - Run the installation process\n";
        echo "  status  - Check installation status\n";
        echo "  --quiet - Suppress verbose output\n";
        exit(1);
}
