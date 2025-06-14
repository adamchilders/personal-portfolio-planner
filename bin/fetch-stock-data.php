#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Background Stock Data Fetcher
 * 
 * This script fetches fresh stock data for all stocks currently held in portfolios.
 * It's designed to run as a background job/cron task.
 * 
 * Usage:
 *   php bin/fetch-stock-data.php [options]
 * 
 * Options:
 *   --force       Force update even if data is fresh
 *   --stats       Show data freshness statistics
 *   --historical  Fetch historical price data (30 days)
 *   --dividends   Fetch dividend data
 *   --days=N      Number of days of historical data (default: 30)
 *   --help        Show this help message
 */

// Bootstrap the application
require_once __DIR__ . '/../bootstrap/app.php';

use App\Services\BackgroundDataService;
use App\Services\StockDataService;
use App\Services\ConfigService;
use App\Helpers\DateTimeHelper;

class StockDataFetcher
{
    private BackgroundDataService $backgroundDataService;
    private bool $force = false;
    private bool $showStats = false;
    private bool $historical = false;
    private bool $dividends = false;
    private ?int $days = null;
    
    public function __construct()
    {
        $stockDataService = new StockDataService();
        $this->backgroundDataService = new BackgroundDataService($stockDataService);
    }
    
    public function run(array $args): int
    {
        $this->parseArguments($args);
        
        if (in_array('--help', $args) || in_array('-h', $args)) {
            $this->showHelp();
            return 0;
        }
        
        $this->log("🚀 Starting background stock data fetch");
        $this->log("📅 " . DateTimeHelper::now()->format('Y-m-d H:i:s T'));
        
        if ($this->showStats) {
            $this->displayStats();
        }

        if ($this->historical) {
            // Fetch historical data
            $days = $this->days ?? ConfigService::getHistoricalDataDays();
            $this->log("📈 Fetching historical price data ({$days} days)...");
            $historicalResults = $this->backgroundDataService->fetchHistoricalData($this->days, $this->force);
            $this->displayResults($historicalResults, 'Historical Data');

            if ($historicalResults['failed'] > 0) {
                return 1;
            }
        } elseif ($this->dividends) {
            // Fetch dividend data
            $days = $this->days ?? ConfigService::getHistoricalDataDays();
            $this->log("💰 Fetching dividend data ({$days} days)...");
            $dividendResults = $this->backgroundDataService->fetchDividendData($this->days, $this->force);
            $this->displayResults($dividendResults, 'Dividend Data');

            if ($dividendResults['failed'] > 0) {
                return 1;
            }
        } else {
            // Fetch fresh quote data
            $results = $this->backgroundDataService->fetchPortfolioStockData($this->force);
            $this->displayResults($results, 'Quote Data');

            // Return appropriate exit code
            return $results['failed'] > 0 ? 1 : 0;
        }
        

        return 0;
    }
    
    private function parseArguments(array $args): void
    {
        $this->force = in_array('--force', $args);
        $this->showStats = in_array('--stats', $args);
        $this->historical = in_array('--historical', $args);
        $this->dividends = in_array('--dividends', $args);

        // Parse --days=N option
        foreach ($args as $arg) {
            if (strpos($arg, '--days=') === 0) {
                $this->days = (int)substr($arg, 7);
                break;
            }
        }

        // Use configured default if not specified
        if ($this->days === null) {
            $this->days = ConfigService::getHistoricalDataDays();
        }
    }
    
    private function displayStats(): void
    {
        $this->log("📊 Current data freshness statistics:");
        
        $stats = $this->backgroundDataService->getDataFreshnessStats();
        
        $this->log("   Total stocks in portfolios: {$stats['total_stocks']}");
        $this->log("   Fresh data: {$stats['fresh_data']}");
        $this->log("   Stale data: {$stats['stale_data']}");
        $this->log("   Missing data: {$stats['missing_data']}");
        
        if ($stats['oldest_data']) {
            $this->log("   Oldest data: " . $stats['oldest_data']->format('Y-m-d H:i:s T'));
        }
        
        if ($stats['newest_data']) {
            $this->log("   Newest data: " . $stats['newest_data']->format('Y-m-d H:i:s T'));
        }
        
        $this->log("");
    }
    
    private function displayResults(array $results, string $type = 'Data fetch'): void
    {
        $this->log("📈 {$type} results:");
        $this->log("   Total symbols: {$results['total_symbols']}");
        $this->log("   Updated: {$results['updated']}");
        $this->log("   Skipped (fresh): {$results['skipped']}");
        $this->log("   Failed: {$results['failed']}");
        
        if (!empty($results['errors'])) {
            $this->log("");
            $this->log("❌ Errors encountered:");
            foreach ($results['errors'] as $error) {
                $this->log("   • {$error}");
            }
        }
        
        $this->log("");
        
        if ($results['failed'] > 0) {
            $this->log("⚠️ Some updates failed. Check logs for details.");
        } elseif ($results['updated'] > 0) {
            $this->log("✅ All updates completed successfully!");
        } else {
            $this->log("ℹ️ No updates needed - all data is fresh.");
        }
    }
    
    private function showHelp(): void
    {
        echo "Background Stock Data Fetcher\n";
        echo "============================\n\n";
        echo "This script fetches fresh stock data for all stocks currently held in portfolios.\n";
        echo "It's designed to run as a background job/cron task.\n\n";
        echo "Usage:\n";
        echo "  php bin/fetch-stock-data.php [options]\n\n";
        echo "Options:\n";
        echo "  --force       Force update even if data is fresh\n";
        echo "  --stats       Show data freshness statistics before fetching\n";
        echo "  --historical  Fetch historical price data instead of quotes\n";
        echo "  --dividends   Fetch dividend data instead of quotes\n";
        echo "  --days=N      Number of days of historical data (default: " . ConfigService::getHistoricalDataDays() . ")\n";
        echo "  --help        Show this help message\n\n";
        echo "Examples:\n";
        echo "  php bin/fetch-stock-data.php\n";
        echo "  php bin/fetch-stock-data.php --stats\n";
        echo "  php bin/fetch-stock-data.php --force\n";
        echo "  php bin/fetch-stock-data.php --historical\n";
        echo "  php bin/fetch-stock-data.php --historical --days=90\n";
        echo "  php bin/fetch-stock-data.php --dividends\n";
        echo "  php bin/fetch-stock-data.php --dividends --days=365\n\n";
        echo "Recommended cron schedule:\n";
        echo "  # Every 15 minutes during market hours (9:30 AM - 4:00 PM ET)\n";
        echo "  */15 9-16 * * 1-5 /usr/bin/php /path/to/bin/fetch-stock-data.php\n\n";
        echo "  # Every 30 minutes after hours\n";
        echo "  */30 0-9,16-23 * * 1-5 /usr/bin/php /path/to/bin/fetch-stock-data.php\n";
        echo "  0,30 * * * 0,6 /usr/bin/php /path/to/bin/fetch-stock-data.php\n\n";
    }
    
    private function log(string $message): void
    {
        echo $message . "\n";
    }
}

// Run the fetcher
try {
    $fetcher = new StockDataFetcher();
    $exitCode = $fetcher->run($argv);
    exit($exitCode);
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
