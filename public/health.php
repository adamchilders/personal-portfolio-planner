<?php

declare(strict_types=1);

// Health check endpoint for Portfolio Tracker
// This endpoint provides application health status for monitoring and load balancers

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Define the root path
define('ROOT_PATH', dirname(__DIR__));

// Simple health check without full application bootstrap for performance
$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'version' => '1.0.0',
    'checks' => []
];

try {
    // Load environment variables for database connection
    if (file_exists(ROOT_PATH . '/.env')) {
        $envFile = file_get_contents(ROOT_PATH . '/.env');
        $envLines = explode("\n", $envFile);
        $env = [];
        
        foreach ($envLines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $env[trim($key)] = trim($value, '"\'');
            }
        }
    } else {
        throw new Exception('Environment file not found');
    }
    
    // Database health check
    try {
        $dbHost = $env['DB_HOST'] ?? 'mysql';
        $dbPort = $env['DB_PORT'] ?? '3306';
        $dbName = $env['DB_DATABASE'] ?? 'portfolio_tracker';
        $dbUser = $env['DB_USERNAME'] ?? 'portfolio_user';
        $dbPass = $env['DB_PASSWORD'] ?? 'password';
        
        $pdo = new PDO(
            "mysql:host={$dbHost};port={$dbPort};dbname={$dbName}",
            $dbUser,
            $dbPass,
            [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]
        );
        
        // Simple query to test database
        $stmt = $pdo->query('SELECT 1');
        $result = $stmt->fetch();
        
        if ($result) {
            $health['checks']['database'] = [
                'status' => 'healthy',
                'response_time_ms' => 0 // Could measure actual time if needed
            ];
        } else {
            throw new Exception('Database query failed');
        }
        
    } catch (Exception $e) {
        $health['status'] = 'unhealthy';
        $health['checks']['database'] = [
            'status' => 'unhealthy',
            'error' => $e->getMessage()
        ];
    }
    
    // Redis health check
    try {
        $redisHost = $env['REDIS_HOST'] ?? 'redis';
        $redisPort = (int)($env['REDIS_PORT'] ?? 6379);
        $redisPass = $env['REDIS_PASSWORD'] ?? '';
        
        $redis = new Redis();
        $redis->connect($redisHost, $redisPort, 5); // 5 second timeout
        
        if (!empty($redisPass)) {
            $redis->auth($redisPass);
        }
        
        $pong = $redis->ping();
        
        if ($pong === '+PONG' || $pong === 'PONG' || $pong === true) {
            $health['checks']['redis'] = [
                'status' => 'healthy',
                'response_time_ms' => 0
            ];
        } else {
            throw new Exception('Redis ping failed');
        }
        
        $redis->close();
        
    } catch (Exception $e) {
        $health['status'] = 'unhealthy';
        $health['checks']['redis'] = [
            'status' => 'unhealthy',
            'error' => $e->getMessage()
        ];
    }
    
    // File system health check
    try {
        $storageDir = ROOT_PATH . '/storage/logs';
        
        if (!is_dir($storageDir)) {
            throw new Exception('Storage directory not found');
        }
        
        if (!is_writable($storageDir)) {
            throw new Exception('Storage directory not writable');
        }
        
        // Test write capability
        $testFile = $storageDir . '/health_check_' . time() . '.tmp';
        if (file_put_contents($testFile, 'test') === false) {
            throw new Exception('Cannot write to storage directory');
        }
        
        // Clean up test file
        unlink($testFile);
        
        $health['checks']['filesystem'] = [
            'status' => 'healthy',
            'storage_writable' => true
        ];
        
    } catch (Exception $e) {
        $health['status'] = 'unhealthy';
        $health['checks']['filesystem'] = [
            'status' => 'unhealthy',
            'error' => $e->getMessage()
        ];
    }
    
    // Application installation check
    try {
        if (isset($pdo)) {
            // Check if application is installed
            $stmt = $pdo->prepare("
                SELECT value FROM system_settings 
                WHERE setting_key = 'installation_status'
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['value'] === 'complete') {
                $health['checks']['installation'] = [
                    'status' => 'complete',
                    'installed' => true
                ];
            } else {
                $health['checks']['installation'] = [
                    'status' => 'pending',
                    'installed' => false
                ];
            }
        }
        
    } catch (Exception $e) {
        // Installation check is not critical for health
        $health['checks']['installation'] = [
            'status' => 'unknown',
            'error' => $e->getMessage()
        ];
    }
    
} catch (Exception $e) {
    $health['status'] = 'unhealthy';
    $health['error'] = $e->getMessage();
}

// Set appropriate HTTP status code
if ($health['status'] === 'healthy') {
    http_response_code(200);
} else {
    http_response_code(503); // Service Unavailable
}

// Output health status
echo json_encode($health, JSON_PRETTY_PRINT);
exit;
