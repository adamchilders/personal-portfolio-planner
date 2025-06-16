<?php

declare(strict_types=1);

namespace App\Console;

use App\Services\DividendSafetyService;
use App\Models\DividendSafetyCache;
use App\Models\Holding;

class DividendSafetyCacheCommand
{
    private DividendSafetyService $dividendSafetyService;
    
    public function __construct(DividendSafetyService $dividendSafetyService)
    {
        $this->dividendSafetyService = $dividendSafetyService;
    }
    
    /**
     * Update dividend safety cache for all held stocks
     */
    public function updateCache(): void
    {
        echo "Starting dividend safety cache update...\n";
        
        // Get all unique stock symbols from active holdings
        $symbols = Holding::where('is_active', true)
            ->distinct()
            ->pluck('stock_symbol')
            ->toArray();
        
        if (empty($symbols)) {
            echo "No active holdings found. Nothing to update.\n";
            return;
        }
        
        echo "Found " . count($symbols) . " unique stocks in portfolios: " . implode(', ', $symbols) . "\n";
        
        // Get symbols that need updating
        $symbolsNeedingUpdate = DividendSafetyCache::getSymbolsNeedingUpdate($symbols);
        
        if (empty($symbolsNeedingUpdate)) {
            echo "All cached data is fresh. No updates needed.\n";
            return;
        }
        
        echo "Updating " . count($symbolsNeedingUpdate) . " symbols: " . implode(', ', $symbolsNeedingUpdate) . "\n";
        
        // Bulk update
        $results = $this->dividendSafetyService->bulkUpdateSafetyData($symbolsNeedingUpdate);
        
        $successful = 0;
        $failed = 0;
        
        foreach ($results as $symbol => $result) {
            if ($result['score'] > 0 || empty($result['warnings'])) {
                $successful++;
                echo "✓ {$symbol}: Score {$result['score']} ({$result['grade']})\n";
            } else {
                $failed++;
                echo "✗ {$symbol}: Failed - " . implode(', ', $result['warnings']) . "\n";
            }
        }
        
        echo "\nUpdate complete: {$successful} successful, {$failed} failed\n";
    }
    
    /**
     * Show cache statistics
     */
    public function showStats(): void
    {
        $stats = $this->dividendSafetyService->getCacheStats();
        
        echo "Dividend Safety Cache Statistics:\n";
        echo "================================\n";
        echo "Total cached entries: {$stats['total_cached']}\n";
        echo "Fresh entries (< 24h): {$stats['fresh_entries']}\n";
        echo "Stale entries (> 24h): {$stats['stale_entries']}\n";
        echo "Cache hit rate: {$stats['cache_hit_rate']}%\n";
        
        if ($stats['total_cached'] > 0) {
            echo "\nRecent cache entries:\n";
            $recent = DividendSafetyCache::orderBy('last_updated', 'desc')
                ->limit(10)
                ->get();
            
            foreach ($recent as $entry) {
                $age = $entry->last_updated ? 
                    round((time() - $entry->last_updated->getTimestamp()) / 3600, 1) : 'unknown';
                echo "  {$entry->symbol}: Score {$entry->safety_score} ({$entry->safety_grade}) - {$age}h ago\n";
            }
        }
    }
    
    /**
     * Clean up old cache entries
     */
    public function cleanup(): void
    {
        echo "Cleaning up old dividend safety cache entries...\n";
        
        $deleted = $this->dividendSafetyService->cleanupOldCache();
        
        if ($deleted > 0) {
            echo "Deleted {$deleted} old cache entries (older than 30 days)\n";
        } else {
            echo "No old entries found to clean up\n";
        }
    }
    
    /**
     * Force refresh specific symbols
     */
    public function refreshSymbols(array $symbols): void
    {
        if (empty($symbols)) {
            echo "No symbols provided\n";
            return;
        }
        
        echo "Force refreshing dividend safety data for: " . implode(', ', $symbols) . "\n";
        
        $results = $this->dividendSafetyService->bulkUpdateSafetyData($symbols);
        
        foreach ($results as $symbol => $result) {
            if ($result['score'] > 0 || empty($result['warnings'])) {
                echo "✓ {$symbol}: Score {$result['score']} ({$result['grade']})\n";
            } else {
                echo "✗ {$symbol}: Failed - " . implode(', ', $result['warnings']) . "\n";
            }
        }
    }
    
    /**
     * Show help information
     */
    public function showHelp(): void
    {
        echo "Dividend Safety Cache Management\n";
        echo "===============================\n";
        echo "Available commands:\n";
        echo "  update    - Update cache for all held stocks (only stale entries)\n";
        echo "  stats     - Show cache statistics and recent entries\n";
        echo "  cleanup   - Remove old cache entries (older than 30 days)\n";
        echo "  refresh   - Force refresh specific symbols (comma-separated)\n";
        echo "  help      - Show this help message\n";
        echo "\nExamples:\n";
        echo "  php console.php dividend-cache update\n";
        echo "  php console.php dividend-cache stats\n";
        echo "  php console.php dividend-cache refresh AAPL,MSFT,JNJ\n";
    }
}
