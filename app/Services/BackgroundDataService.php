<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Stock;
use App\Models\StockQuote;
use App\Models\StockPrice;
use App\Models\PortfolioHolding;
use App\Services\ConfigService;
use App\Helpers\DateTimeHelper;
use Exception;

class BackgroundDataService
{
    private StockDataService $stockDataService;
    
    public function __construct(StockDataService $stockDataService)
    {
        $this->stockDataService = $stockDataService;
    }
    
    /**
     * Fetch fresh data for all stocks currently held in portfolios
     */
    public function fetchPortfolioStockData(bool $force = false): array
    {
        $results = [
            'total_symbols' => 0,
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ];
        
        try {
            // Get unique stock symbols from all active portfolio holdings
            $symbols = $this->getActivePortfolioSymbols();
            $results['total_symbols'] = count($symbols);
            
            if (empty($symbols)) {
                $this->log('No stocks found in any portfolio');
                return $results;
            }
            
            $this->log("Found {$results['total_symbols']} unique stocks in portfolios: " . implode(', ', $symbols));
            
            foreach ($symbols as $symbol) {
                try {
                    $updated = $this->updateStockData($symbol, $force);

                    if ($updated === 'updated') {
                        $results['updated']++;
                        $this->log("✅ Updated data for {$symbol}");
                    } elseif ($updated === 'skipped') {
                        $results['skipped']++;
                        $this->log("⏭️ Skipped {$symbol} (data is fresh)");
                    } else {
                        $results['failed']++;
                        $this->log("❌ Failed to update {$symbol}");
                        $results['errors'][] = "Failed to fetch data for {$symbol}";
                    }
                    
                    // Rate limiting - small delay between API calls
                    usleep(250000); // 250ms delay
                    
                } catch (Exception $e) {
                    $results['failed']++;
                    $error = "Error updating {$symbol}: " . $e->getMessage();
                    $results['errors'][] = $error;
                    $this->log("❌ {$error}");
                }
            }
            
            $this->log("✅ Background data fetch completed: {$results['updated']} updated, {$results['skipped']} skipped, {$results['failed']} failed");
            
        } catch (Exception $e) {
            $error = "Background data fetch failed: " . $e->getMessage();
            $results['errors'][] = $error;
            $this->log("❌ {$error}");
        }
        
        return $results;
    }
    
    /**
     * Get unique stock symbols from all active portfolio holdings
     */
    private function getActivePortfolioSymbols(): array
    {
        return PortfolioHolding::join('portfolios', 'portfolio_holdings.portfolio_id', '=', 'portfolios.id')
            ->where('portfolio_holdings.is_active', true)
            ->where('portfolios.is_active', true)
            ->where('portfolio_holdings.quantity', '>', 0)
            ->distinct()
            ->pluck('portfolio_holdings.stock_symbol')
            ->map(fn($symbol) => strtoupper($symbol))
            ->unique()
            ->values()
            ->toArray();
    }
    
    /**
     * Update stock data if needed
     */
    private function updateStockData(string $symbol, bool $force = false): string
    {
        // Check if we need to update this stock
        if (!$force && !$this->shouldUpdateStock($symbol)) {
            return 'skipped';
        }
        
        // Fetch fresh data from API
        $quoteData = $this->stockDataService->fetchQuoteFromAPI($symbol);
        
        if (!$quoteData) {
            return 'failed';
        }
        
        // Ensure stock record exists
        $stock = Stock::find($symbol);
        $isNewStock = false;
        if (!$stock) {
            $stock = Stock::create([
                'symbol' => $symbol,
                'name' => $quoteData['name'],
                'exchange' => $quoteData['exchange'],
                'currency' => $quoteData['currency'],
                'is_active' => true
            ]);
            $isNewStock = true;
        }

        // Update the quote data
        $success = $this->stockDataService->updateStockQuote($stock, $quoteData);

        // If this is a new stock, fetch dividend data
        if ($isNewStock && $success) {
            $this->log("🆕 New stock detected: {$symbol}, fetching dividend data...");
            $this->fetchDividendDataForStock($symbol);
        }

        return $success ? 'updated' : 'failed';
    }
    
    /**
     * Check if stock data should be updated based on cache timeout
     */
    private function shouldUpdateStock(string $symbol): bool
    {
        $quote = StockQuote::find($symbol);
        
        if (!$quote || !$quote->quote_time) {
            return true; // No data exists, need to fetch
        }
        
        $maxAgeMinutes = $this->getQuoteCacheTimeout();
        
        return $quote->isStale($maxAgeMinutes);
    }
    
    /**
     * Get appropriate cache timeout based on market hours
     */
    private function getQuoteCacheTimeout(): int
    {
        return ConfigService::getQuoteCacheTimeout();
    }

    /**
     * Check if current time is during market hours
     */
    private function isMarketHours(): bool
    {
        return ConfigService::isMarketHours();
    }
    
    /**
     * Log message with timestamp
     */
    private function log(string $message): void
    {
        $timestamp = DateTimeHelper::now()->format('Y-m-d H:i:s');
        error_log("[{$timestamp}] BackgroundDataService: {$message}");
    }
    
    /**
     * Get statistics about current stock data freshness
     */
    public function getDataFreshnessStats(): array
    {
        $symbols = $this->getActivePortfolioSymbols();
        $stats = [
            'total_stocks' => count($symbols),
            'fresh_data' => 0,
            'stale_data' => 0,
            'missing_data' => 0,
            'oldest_data' => null,
            'newest_data' => null
        ];
        
        foreach ($symbols as $symbol) {
            $quote = StockQuote::find($symbol);
            
            if (!$quote || !$quote->quote_time) {
                $stats['missing_data']++;
                continue;
            }
            
            $maxAgeMinutes = $this->getQuoteCacheTimeout();
            
            if ($quote->isStale($maxAgeMinutes)) {
                $stats['stale_data']++;
            } else {
                $stats['fresh_data']++;
            }
            
            // Track oldest and newest data
            if (!$stats['oldest_data'] || $quote->quote_time < $stats['oldest_data']) {
                $stats['oldest_data'] = $quote->quote_time;
            }
            
            if (!$stats['newest_data'] || $quote->quote_time > $stats['newest_data']) {
                $stats['newest_data'] = $quote->quote_time;
            }
        }
        
        return $stats;
    }

    /**
     * Fetch historical data for all portfolio stocks
     */
    public function fetchHistoricalData(?int $days = null, bool $force = false): array
    {
        $results = [
            'total_symbols' => 0,
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        try {
            // Use configured default if not specified
            $days = $days ?? ConfigService::getHistoricalDataDays();

            $symbols = $this->getActivePortfolioSymbols();
            $results['total_symbols'] = count($symbols);

            if (empty($symbols)) {
                $this->log('No stocks found in any portfolio for historical data fetch');
                return $results;
            }

            $this->log("Fetching {$days} days of historical data for {$results['total_symbols']} stocks");

            foreach ($symbols as $symbol) {
                try {
                    $shouldFetch = $force || $this->shouldFetchHistoricalData($symbol, $days);

                    if (!$shouldFetch) {
                        $results['skipped']++;
                        $this->log("⏭️ Skipped historical data for {$symbol} (already exists)");
                        continue;
                    }

                    $success = $this->stockDataService->fetchHistoricalData($symbol, $days);

                    if ($success) {
                        $results['updated']++;
                        $this->log("✅ Fetched historical data for {$symbol}");
                    } else {
                        $results['failed']++;
                        $this->log("❌ Failed to fetch historical data for {$symbol}");
                        $results['errors'][] = "Failed to fetch historical data for {$symbol}";
                    }

                    // Rate limiting - longer delay for historical data
                    usleep(500000); // 500ms delay

                } catch (Exception $e) {
                    $results['failed']++;
                    $error = "Error fetching historical data for {$symbol}: " . $e->getMessage();
                    $results['errors'][] = $error;
                    $this->log("❌ {$error}");
                }
            }

            $this->log("✅ Historical data fetch completed: {$results['updated']} updated, {$results['skipped']} skipped, {$results['failed']} failed");

        } catch (Exception $e) {
            $error = "Historical data fetch failed: " . $e->getMessage();
            $results['errors'][] = $error;
            $this->log("❌ {$error}");
        }

        return $results;
    }

    /**
     * Check if we should fetch historical data for a stock
     */
    private function shouldFetchHistoricalData(string $symbol, int $days): bool
    {
        // Check if we have recent historical data
        $cutoffDate = DateTimeHelper::now();
        $cutoffDate->modify("-{$days} days");

        $existingCount = StockPrice::where('symbol', $symbol)
            ->where('price_date', '>=', $cutoffDate->format('Y-m-d'))
            ->count();

        // If we have less than 80% of expected records, fetch fresh data
        $expectedRecords = $days * 0.7; // Account for weekends/holidays

        return $existingCount < $expectedRecords;
    }

    /**
     * Fetch dividend data for all portfolio stocks
     */
    public function fetchDividendData(?int $days = null, bool $force = false): array
    {
        $results = [
            'total_symbols' => 0,
            'updated' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        try {
            // Use configured default if not specified
            $days = $days ?? ConfigService::getHistoricalDataDays();

            $symbols = $this->getActivePortfolioSymbols();
            $results['total_symbols'] = count($symbols);

            if (empty($symbols)) {
                $this->log('No stocks found in any portfolio for dividend data fetch');
                return $results;
            }

            $this->log("Fetching {$days} days of dividend data for {$results['total_symbols']} stocks");

            foreach ($symbols as $symbol) {
                try {
                    $shouldFetch = $force || $this->shouldFetchDividendData($symbol, $days);

                    if (!$shouldFetch) {
                        $results['skipped']++;
                        $this->log("⏭️ Skipped dividend data for {$symbol} (already exists)");
                        continue;
                    }

                    // Fetch dividend data from Yahoo Finance
                    $dividends = $this->stockDataService->fetchDividendData($symbol, $days);

                    if (!empty($dividends)) {
                        // Store the dividend data
                        $stored = $this->stockDataService->storeDividendData($dividends);

                        if ($stored) {
                            $results['updated']++;
                            $this->log("✅ Fetched " . count($dividends) . " dividend records for {$symbol}");
                        } else {
                            $results['failed']++;
                            $this->log("❌ Failed to store dividend data for {$symbol}");
                            $results['errors'][] = "Failed to store dividend data for {$symbol}";
                        }
                    } else {
                        $results['skipped']++;
                        $this->log("⏭️ No dividend data found for {$symbol}");
                    }

                    // Rate limiting - longer delay for dividend data
                    usleep(500000); // 500ms delay

                } catch (Exception $e) {
                    $results['failed']++;
                    $error = "Error fetching dividend data for {$symbol}: " . $e->getMessage();
                    $results['errors'][] = $error;
                    $this->log("❌ {$error}");
                }
            }

            $this->log("📊 Dividend data fetch completed: {$results['updated']} updated, {$results['skipped']} skipped, {$results['failed']} failed");

        } catch (Exception $e) {
            $error = "Error in dividend data fetch: " . $e->getMessage();
            $results['errors'][] = $error;
            $this->log("❌ {$error}");
        }

        return $results;
    }

    /**
     * Check if we should fetch dividend data for a stock
     */
    private function shouldFetchDividendData(string $symbol, int $days): bool
    {
        // If we have no dividend data or it's been more than 7 days since last update, fetch fresh data
        $lastUpdate = \App\Models\Dividend::where('symbol', $symbol)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastUpdate) {
            return true; // No dividend data exists
        }

        $daysSinceUpdate = DateTimeHelper::now()->diff($lastUpdate->created_at)->days;
        return $daysSinceUpdate > 7; // Update if older than 7 days
    }

    /**
     * Fetch dividend data for a specific stock (used when new stocks are added)
     */
    public function fetchDividendDataForStock(string $symbol, ?int $days = null): bool
    {
        try {
            $days = $days ?? ConfigService::getHistoricalDataDays();

            $this->log("Fetching dividend data for new stock: {$symbol}");

            // Fetch dividend data from Yahoo Finance
            $dividends = $this->stockDataService->fetchDividendData($symbol, $days);

            if (!empty($dividends)) {
                // Store the dividend data
                $stored = $this->stockDataService->storeDividendData($dividends);

                if ($stored) {
                    $this->log("✅ Fetched " . count($dividends) . " dividend records for {$symbol}");
                    return true;
                } else {
                    $this->log("❌ Failed to store dividend data for {$symbol}");
                    return false;
                }
            } else {
                $this->log("⏭️ No dividend data found for {$symbol}");
                return true; // Not an error if no dividends exist
            }

        } catch (Exception $e) {
            $this->log("❌ Error fetching dividend data for {$symbol}: " . $e->getMessage());
            return false;
        }
    }
}
