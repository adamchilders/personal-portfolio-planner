<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Stock;
use App\Models\StockQuote;
use App\Models\StockPrice;
use App\Models\Dividend;
use App\Models\PortfolioHolding;
use App\Models\DataProviderConfig;
use App\Services\ConfigService;
use App\Helpers\DateTimeHelper;
use App\Services\FinancialModelingPrepService;
use Exception;

class StockDataService
{
    private const YAHOO_FINANCE_BASE_URL = 'https://query1.finance.yahoo.com/v8/finance/chart/';
    private const YAHOO_SEARCH_URL = 'https://query1.finance.yahoo.com/v1/finance/search';

    private FinancialModelingPrepService $fmpService;

    public function __construct()
    {
        $this->fmpService = new FinancialModelingPrepService();
    }

    /**
     * Search for stocks by symbol or name
     */
    public function searchStocks(string $query, int $limit = 10): array
    {
        try {
            $url = self::YAHOO_SEARCH_URL . '?' . http_build_query([
                'q' => $query,
                'quotesCount' => $limit,
                'newsCount' => 0
            ]);
            
            $response = $this->makeHttpRequest($url);
            $data = json_decode($response, true);
            
            if (!isset($data['quotes'])) {
                return [];
            }
            
            $results = [];
            foreach ($data['quotes'] as $quote) {
                if (isset($quote['symbol']) && isset($quote['shortname'])) {
                    $results[] = [
                        'symbol' => $quote['symbol'],
                        'name' => $quote['shortname'] ?? $quote['longname'] ?? $quote['symbol'],
                        'exchange' => $quote['exchange'] ?? null,
                        'type' => $quote['quoteType'] ?? 'EQUITY'
                    ];
                }
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Stock search error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get current stock quote - primarily from cache, with fallback to API
     *
     * This method prioritizes cached data and only hits the API as a fallback.
     * The background job system should handle regular data updates.
     */
    public function getStockQuote(string $symbol): ?array
    {
        // For development/testing, provide mock data for common symbols
        if ($this->shouldUseMockData()) {
            return $this->getMockQuote($symbol);
        }

        // First, try to get cached data (even if slightly stale)
        $cachedQuote = $this->getAnyQuote($symbol);
        if ($cachedQuote) {
            return $cachedQuote;
        }

        // No cached data exists, fetch from API as fallback
        // (This should rarely happen if background jobs are working)
        error_log("No cached data for {$symbol}, fetching from API as fallback");
        $freshQuote = $this->fetchQuoteFromAPI($symbol);

        if ($freshQuote) {
            // Cache the fresh data for future use
            $this->cacheQuote($symbol, $freshQuote);
            return $freshQuote;
        }

        // Last resort: fallback to mock data
        error_log("API fetch failed for {$symbol}, using mock data");
        return $this->getMockQuote($symbol);
    }

    /**
     * Get cached quote if it's still fresh
     */
    private function getCachedQuote(string $symbol): ?array
    {
        $quote = StockQuote::find($symbol);

        if (!$quote || !$quote->quote_time) {
            return null;
        }

        $maxAgeMinutes = $this->getQuoteCacheTimeout();

        if ($quote->isStale($maxAgeMinutes)) {
            return null;
        }

        // Convert database model to array format
        return $this->convertQuoteToArray($quote);
    }

    /**
     * Get any cached quote data (fresh or stale)
     */
    private function getAnyQuote(string $symbol): ?array
    {
        $quote = StockQuote::find($symbol);

        if (!$quote) {
            return null;
        }

        return $this->convertQuoteToArray($quote);
    }

    /**
     * Get stale quote data (for fallback when API fails)
     */
    private function getStaleQuote(string $symbol): ?array
    {
        $quote = StockQuote::find($symbol);

        if (!$quote) {
            return null;
        }

        return $this->convertQuoteToArray($quote);
    }

    /**
     * Fetch quote directly from Yahoo Finance API
     */
    public function fetchQuoteFromAPI(string $symbol): ?array
    {
        try {
            $url = self::YAHOO_FINANCE_BASE_URL . urlencode($symbol);
            $response = $this->makeHttpRequest($url);
            $data = json_decode($response, true);

            if (!isset($data['chart']['result'][0])) {
                return null;
            }

            $result = $data['chart']['result'][0];
            $meta = $result['meta'];

            return [
                'symbol' => $symbol,
                'current_price' => $meta['regularMarketPrice'] ?? 0,
                'change_amount' => ($meta['regularMarketPrice'] ?? 0) - ($meta['previousClose'] ?? 0),
                'change_percent' => $this->calculateChangePercent(
                    $meta['regularMarketPrice'] ?? 0,
                    $meta['previousClose'] ?? 0
                ),
                'volume' => $meta['regularMarketVolume'] ?? 0,
                'market_cap' => $meta['marketCap'] ?? null,
                'fifty_two_week_high' => $meta['fiftyTwoWeekHigh'] ?? null,
                'fifty_two_week_low' => $meta['fiftyTwoWeekLow'] ?? null,
                'quote_time' => DateTimeHelper::now(),
                'market_state' => $this->getMarketState($meta['marketState'] ?? 'CLOSED'),
                'currency' => $meta['currency'] ?? 'USD',
                'exchange' => $meta['exchangeName'] ?? null,
                'name' => $meta['longName'] ?? $meta['shortName'] ?? $symbol
            ];

        } catch (Exception $e) {
            error_log("Stock quote API error for {$symbol}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get or create stock record
     */
    public function getOrCreateStock(string $symbol): ?Stock
    {
        // First try to find existing stock
        $stock = Stock::find($symbol);
        
        if ($stock) {
            return $stock;
        }
        
        // If not found, fetch data and create new stock
        $quoteData = $this->getStockQuote($symbol);
        
        if (!$quoteData) {
            return null;
        }
        
        try {
            $stock = Stock::create([
                'symbol' => $symbol,
                'name' => $quoteData['name'],
                'exchange' => $quoteData['exchange'],
                'currency' => $quoteData['currency'],
                'is_active' => true
            ]);

            // Also create/update the quote
            $this->updateStockQuote($stock, $quoteData);

            // Automatically fetch 1 year of historical data for new stocks
            $this->log("New stock {$symbol} created, fetching 1 year of historical data...");
            $this->fetchHistoricalData($symbol, 365);

            // Automatically fetch dividend data for new stocks
            $this->log("Fetching dividend data for new stock {$symbol}...");
            $dividends = $this->fetchDividendDataWithFallback($symbol, 365);
            if (!empty($dividends)) {
                $this->storeDividendData($dividends);
                $this->log("✅ Fetched " . count($dividends) . " dividend records for {$symbol}");
            } else {
                $this->log("⏭️ No dividend data found for {$symbol}");
            }

            return $stock;

        } catch (Exception $e) {
            error_log("Error creating stock {$symbol}: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update stock quote data
     */
    public function updateStockQuote(Stock $stock, ?array $quoteData = null): bool
    {
        if (!$quoteData) {
            $quoteData = $this->getStockQuote($stock->symbol);
        }
        
        if (!$quoteData) {
            return false;
        }
        
        try {
            StockQuote::updateOrCreate(
                ['symbol' => $stock->symbol],
                [
                    'current_price' => $quoteData['current_price'],
                    'change_amount' => $quoteData['change_amount'],
                    'change_percent' => $quoteData['change_percent'],
                    'volume' => $quoteData['volume'],
                    'market_cap' => $quoteData['market_cap'],
                    'fifty_two_week_high' => $quoteData['fifty_two_week_high'],
                    'fifty_two_week_low' => $quoteData['fifty_two_week_low'],
                    'quote_time' => $quoteData['quote_time'],
                    'market_state' => $quoteData['market_state']
                ]
            );
            
            return true;
            
        } catch (Exception $e) {
            error_log("Error updating quote for {$stock->symbol}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update quotes for multiple stocks
     */
    public function updateMultipleQuotes(array $symbols): array
    {
        $results = [];
        
        foreach ($symbols as $symbol) {
            $stock = Stock::find($symbol);
            if ($stock) {
                $success = $this->updateStockQuote($stock);
                $results[$symbol] = $success;
            }
        }
        
        return $results;
    }
    
    /**
     * Get stocks that need quote updates (older than specified minutes)
     */
    public function getStaleQuotes(int $maxAgeMinutes = 15): array
    {
        $cutoffTime = DateTimeHelper::now()->modify("-{$maxAgeMinutes} minutes");
        
        return StockQuote::where('quote_time', '<', $cutoffTime)
            ->orWhereNull('quote_time')
            ->pluck('symbol')
            ->toArray();
    }
    
    /**
     * Ensure stock has sufficient historical data for portfolio calculations
     */
    public function ensureHistoricalData(string $symbol, int $days = 365): bool
    {
        // Check if we have recent historical data
        $latestPrice = StockPrice::where('symbol', $symbol)
            ->orderBy('price_date', 'desc')
            ->first();

        $cutoffDate = (new \DateTime())->modify("-{$days} days")->format('Y-m-d');

        // If no data or data is too old, fetch fresh data
        if (!$latestPrice || $latestPrice->price_date < $cutoffDate) {
            $this->log("Stock {$symbol} missing sufficient historical data, fetching {$days} days...");
            return $this->fetchHistoricalData($symbol, $days);
        }

        return true;
    }

    /**
     * Get all stocks that are missing historical data
     */
    public function getStocksMissingHistoricalData(): array
    {
        // Get all active stocks
        $allStocks = Stock::where('is_active', true)->pluck('symbol')->toArray();

        $stocksMissingData = [];
        $cutoffDate = (new \DateTime())->modify('-365 days')->format('Y-m-d');

        foreach ($allStocks as $symbol) {
            $latestPrice = StockPrice::where('symbol', $symbol)
                ->orderBy('price_date', 'desc')
                ->first();

            // If no data or data is too old, mark as missing
            if (!$latestPrice || $latestPrice->price_date < $cutoffDate) {
                $stocksMissingData[] = [
                    'symbol' => $symbol,
                    'latest_date' => $latestPrice ? $latestPrice->price_date : null,
                    'days_missing' => $latestPrice ?
                        (new \DateTime())->diff(new \DateTime($latestPrice->price_date))->days :
                        365
                ];
            }
        }

        return $stocksMissingData;
    }

    /**
     * Backfill historical data for multiple stocks
     */
    public function backfillHistoricalData(array $symbols = null, int $days = 365): array
    {
        // If no symbols provided, get all stocks missing data
        if ($symbols === null) {
            $missingData = $this->getStocksMissingHistoricalData();
            $symbols = array_column($missingData, 'symbol');
        }

        $results = [];
        $totalSymbols = count($symbols);

        $this->log("Starting historical data backfill for {$totalSymbols} stocks...");

        foreach ($symbols as $index => $symbol) {
            $this->log("Processing {$symbol} (" . ($index + 1) . "/{$totalSymbols})...");

            try {
                // Fetch historical price data
                $priceSuccess = $this->fetchHistoricalData($symbol, $days);

                // Also fetch dividend data
                $dividends = $this->fetchDividendData($symbol, $days);
                $dividendSuccess = true;
                if (!empty($dividends)) {
                    $dividendSuccess = $this->storeDividendData($dividends);
                }

                $success = $priceSuccess && $dividendSuccess;
                $message = [];

                if ($priceSuccess) {
                    $message[] = 'Historical price data fetched successfully';
                } else {
                    $message[] = 'Failed to fetch price data';
                }

                if (!empty($dividends)) {
                    if ($dividendSuccess) {
                        $message[] = count($dividends) . ' dividend records fetched';
                    } else {
                        $message[] = 'Failed to store dividend data';
                    }
                } else {
                    $message[] = 'No dividend data found';
                }

                $results[$symbol] = [
                    'success' => $success,
                    'message' => implode(', ', $message)
                ];

                // Rate limiting between stocks
                if ($index < $totalSymbols - 1) {
                    usleep(1000000); // 1 second delay between stocks
                }

            } catch (Exception $e) {
                $results[$symbol] = [
                    'success' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ];
            }
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $this->log("Backfill completed: {$successCount}/{$totalSymbols} stocks processed successfully");

        return $results;
    }

    /**
     * Fetch dividend data with configurable provider selection
     */
    public function fetchDividendDataWithFallback(string $symbol, int $days = 365): array
    {
        // Get configured providers for dividend data
        $config = DataProviderConfig::getConfig(DataProviderConfig::DATA_TYPE_DIVIDEND_DATA);

        if (!$config) {
            // Fallback to old logic if no configuration
            return $this->fetchDividendDataLegacy($symbol, $days);
        }

        $primaryProvider = $config['primary_provider'];
        $fallbackProvider = $config['fallback_provider'];

        // Try primary provider first
        $dividends = $this->fetchDividendDataFromProvider($symbol, $days, $primaryProvider);

        // If primary failed and we have a fallback, try it
        if (empty($dividends) && $fallbackProvider) {
            $this->log("Primary provider {$primaryProvider} failed for {$symbol}, trying fallback {$fallbackProvider}");
            $dividends = $this->fetchDividendDataFromProvider($symbol, $days, $fallbackProvider);
        }

        return $dividends;
    }

    /**
     * Fetch dividend data from a specific provider
     */
    private function fetchDividendDataFromProvider(string $symbol, int $days, string $provider): array
    {
        try {
            switch ($provider) {
                case DataProviderConfig::PROVIDER_YAHOO_FINANCE:
                    return $this->fetchDividendData($symbol, $days);

                case DataProviderConfig::PROVIDER_FINANCIAL_MODELING_PREP:
                    if ($this->fmpService->isAvailable()) {
                        return $this->fmpService->fetchDividendData($symbol);
                    } else {
                        $this->log("FMP service not available for {$symbol}");
                        return [];
                    }

                default:
                    $this->log("Unknown provider {$provider} for {$symbol}");
                    return [];
            }
        } catch (Exception $e) {
            $this->log("Error fetching dividend data from {$provider} for {$symbol}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Legacy dividend data fetching with FMP fallback
     */
    private function fetchDividendDataLegacy(string $symbol, int $days = 365): array
    {
        // First try Yahoo Finance (free)
        $yahooDividends = $this->fetchDividendData($symbol, $days);

        // If we have Yahoo data but missing payment dates, try FMP for complete data
        if (!empty($yahooDividends) && $this->fmpService->isAvailable()) {
            $hasPaymentDates = false;
            foreach ($yahooDividends as $dividend) {
                if (!empty($dividend['payment_date']) && $dividend['payment_date'] !== null) {
                    $hasPaymentDates = true;
                    break;
                }
            }

            // If Yahoo data lacks payment dates, try FMP
            if (!$hasPaymentDates) {
                try {
                    $this->log("Yahoo Finance data lacks payment dates for {$symbol}, trying FMP...");
                    $fmpDividends = $this->fmpService->fetchDividendData($symbol);

                    if (!empty($fmpDividends)) {
                        $this->log("✅ Using FMP dividend data for {$symbol} (includes payment dates)");
                        return $fmpDividends;
                    }
                } catch (Exception $e) {
                    $this->log("FMP fallback failed for {$symbol}: " . $e->getMessage());
                }
            }
        }

        // If no Yahoo data and FMP is available, try FMP directly
        if (empty($yahooDividends) && $this->fmpService->isAvailable()) {
            try {
                $this->log("No Yahoo Finance data for {$symbol}, trying FMP...");
                $fmpDividends = $this->fmpService->fetchDividendData($symbol);

                if (!empty($fmpDividends)) {
                    $this->log("✅ Using FMP dividend data for {$symbol}");
                    return $fmpDividends;
                }
            } catch (Exception $e) {
                $this->log("FMP fallback failed for {$symbol}: " . $e->getMessage());
            }
        }

        // Return Yahoo data (even if incomplete) or empty array
        return $yahooDividends;
    }

    /**
     * Fetch dividend data for a stock
     */
    public function fetchDividendData(string $symbol, int $days = 365): array
    {
        try {
            $endDate = new \DateTime();
            $startDate = (clone $endDate)->modify("-{$days} days");

            // Use Yahoo Finance chart API with events=div to get dividend data
            $url = self::YAHOO_FINANCE_BASE_URL . urlencode($symbol) .
                   '?period1=' . $startDate->getTimestamp() .
                   '&period2=' . $endDate->getTimestamp() .
                   '&interval=1d&events=div';

            $this->log("Fetching dividend data for {$symbol} from {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");

            $response = $this->makeHttpRequest($url);
            $data = json_decode($response, true);

            if (!isset($data['chart']['result'][0])) {
                $this->log("No dividend data found for {$symbol}");
                return [];
            }

            $result = $data['chart']['result'][0];

            // Check if dividend events exist
            if (!isset($result['events']['dividends'])) {
                $this->log("No dividend events found for {$symbol}");
                return [];
            }

            $dividends = [];
            foreach ($result['events']['dividends'] as $timestamp => $dividendData) {
                $exDate = new \DateTime(date('Y-m-d', $timestamp));

                $dividends[] = [
                    'symbol' => $symbol,
                    'ex_date' => $exDate->format('Y-m-d'),
                    'record_date' => null, // Yahoo Finance doesn't provide record dates
                    'payment_date' => null, // Yahoo Finance doesn't provide payment dates
                    'amount' => (float)$dividendData['amount'],
                    'timestamp' => $timestamp
                ];
            }

            // Sort by date (newest first)
            usort($dividends, function($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });

            $this->log("Found " . count($dividends) . " dividend payments for {$symbol}");
            return $dividends;

        } catch (Exception $e) {
            $this->log("Error fetching dividend data for {$symbol}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get dividend history for a stock from database
     */
    public function getDividendHistory(string $symbol, int $days = 365): array
    {
        try {
            $cutoffDate = (new \DateTime())->modify("-{$days} days")->format('Y-m-d');

            $dividends = Dividend::where('symbol', $symbol)
                ->where('ex_date', '>=', $cutoffDate)
                ->orderBy('ex_date', 'desc')
                ->get();

            return $dividends->map(function ($dividend) {
                return [
                    'symbol' => $dividend->symbol,
                    'ex_date' => $dividend->ex_date,
                    'amount' => (float)$dividend->amount,
                    'payment_date' => $dividend->payment_date,
                    'record_date' => $dividend->record_date,
                    'dividend_type' => $dividend->dividend_type
                ];
            })->toArray();

        } catch (Exception $e) {
            error_log("Error getting dividend history for {$symbol}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Store dividend data in database
     */
    public function storeDividendData(array $dividends): bool
    {
        try {
            foreach ($dividends as $dividend) {
                // Validate required fields
                if (!isset($dividend['symbol']) || !isset($dividend['ex_date']) || !isset($dividend['amount'])) {
                    continue;
                }

                Dividend::updateOrCreate(
                    [
                        'symbol' => $dividend['symbol'],
                        'ex_date' => $dividend['ex_date']
                    ],
                    [
                        'amount' => $dividend['amount'],
                        'payment_date' => $dividend['payment_date'] ?? null,
                        'record_date' => $dividend['record_date'] ?? null,
                        'dividend_type' => 'regular'
                    ]
                );
            }

            return true;
        } catch (Exception $e) {
            error_log("Error storing dividend data: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Validate stock symbol format
     */
    public function isValidSymbol(string $symbol): bool
    {
        // Basic validation - alphanumeric and dots/dashes
        return preg_match('/^[A-Z0-9.-]{1,20}$/i', $symbol) === 1;
    }
    
    /**
     * Make HTTP request with error handling
     */
    private function makeHttpRequest(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Mozilla/5.0 (compatible; PortfolioTracker/1.0)',
                    'Accept: application/json'
                ],
                'timeout' => 10
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception("Failed to fetch data from URL: {$url}");
        }
        
        return $response;
    }
    
    /**
     * Calculate percentage change
     */
    private function calculateChangePercent(float $current, float $previous): float
    {
        if ($previous <= 0) {
            return 0.0;
        }
        
        return (($current - $previous) / $previous) * 100;
    }
    
    /**
     * Normalize market state
     */
    private function getMarketState(string $state): string
    {
        return match(strtoupper($state)) {
            'PRE', 'PREPRE' => 'PRE',
            'REGULAR', 'OPEN' => 'REGULAR',
            'POST', 'POSTPOST' => 'POST',
            default => 'CLOSED'
        };
    }

    /**
     * Cache quote data in database
     */
    private function cacheQuote(string $symbol, array $quoteData): void
    {
        try {
            StockQuote::updateOrCreate(
                ['symbol' => $symbol],
                [
                    'current_price' => $quoteData['current_price'],
                    'change_amount' => $quoteData['change_amount'],
                    'change_percent' => $quoteData['change_percent'],
                    'volume' => $quoteData['volume'],
                    'market_cap' => $quoteData['market_cap'],
                    'fifty_two_week_high' => $quoteData['fifty_two_week_high'],
                    'fifty_two_week_low' => $quoteData['fifty_two_week_low'],
                    'quote_time' => $quoteData['quote_time'],
                    'market_state' => $quoteData['market_state']
                ]
            );
        } catch (Exception $e) {
            error_log("Error caching quote for {$symbol}: " . $e->getMessage());
        }
    }

    /**
     * Convert StockQuote model to array format
     */
    private function convertQuoteToArray(StockQuote $quote): array
    {
        return [
            'symbol' => $quote->symbol,
            'current_price' => (float)$quote->current_price,
            'change_amount' => (float)$quote->change_amount,
            'change_percent' => (float)$quote->change_percent,
            'volume' => $quote->volume,
            'market_cap' => $quote->market_cap,
            'fifty_two_week_high' => (float)$quote->fifty_two_week_high,
            'fifty_two_week_low' => (float)$quote->fifty_two_week_low,
            'quote_time' => $quote->quote_time,
            'market_state' => $quote->market_state,
            'currency' => 'USD', // Default, could be stored in stocks table
            'exchange' => $quote->stock?->exchange ?? null,
            'name' => $quote->stock?->name ?? $quote->symbol
        ];
    }

    /**
     * Get appropriate cache timeout based on market hours
     */
    private function getQuoteCacheTimeout(): int
    {
        if ($this->isMarketHours()) {
            return 15; // 15 minutes during market hours
        } else {
            return 30; // 30 minutes after hours
        }
    }

    /**
     * Check if current time is during market hours (9:30 AM - 4:00 PM ET)
     */
    private function isMarketHours(): bool
    {
        $now = DateTimeHelper::now();
        $marketTimezone = new \DateTimeZone($_ENV['MARKET_TIMEZONE'] ?? 'America/New_York');
        $marketTime = $now->setTimezone($marketTimezone);

        $dayOfWeek = (int)$marketTime->format('N'); // 1 = Monday, 7 = Sunday

        // Skip weekends
        if ($dayOfWeek >= 6) {
            return false;
        }

        $hour = (int)$marketTime->format('H');
        $minute = (int)$marketTime->format('i');
        $timeInMinutes = $hour * 60 + $minute;

        $marketStart = 9 * 60 + 30; // 9:30 AM
        $marketEnd = 16 * 60; // 4:00 PM

        return $timeInMinutes >= $marketStart && $timeInMinutes <= $marketEnd;
    }

    /**
     * Fetch and store historical price data for a stock
     * Only fetches missing data to avoid unnecessary API calls
     */
    public function fetchHistoricalData(string $symbol, ?int $days = null): bool
    {
        // Use configured default if not specified
        $days = $days ?? ConfigService::getHistoricalDataDays();

        if ($this->shouldUseMockData()) {
            return $this->generateMockHistoricalData($symbol, $days);
        }

        // Check what data we already have
        $missingDateRanges = $this->getMissingHistoricalDateRanges($symbol, $days);

        if (empty($missingDateRanges)) {
            $this->log("All historical data for {$symbol} is already up to date");
            return true;
        }

        $totalStored = 0;

        // Fetch only missing date ranges
        foreach ($missingDateRanges as $range) {
            $stored = $this->fetchHistoricalDataRange($symbol, $range['start'], $range['end']);
            $totalStored += $stored;

            // Rate limiting between ranges
            if (count($missingDateRanges) > 1) {
                usleep(500000); // 500ms delay
            }
        }

        $this->log("Stored {$totalStored} new historical price records for {$symbol}");
        return $totalStored > 0;
    }

    /**
     * Get missing date ranges for historical data
     */
    private function getMissingHistoricalDateRanges(string $symbol, int $days): array
    {
        $endDate = DateTimeHelper::now();
        $startDate = clone $endDate;
        $startDate->modify("-{$days} days");

        // Get existing data dates
        $existingDates = StockPrice::where('symbol', $symbol)
            ->where('price_date', '>=', $startDate->format('Y-m-d'))
            ->where('price_date', '<=', $endDate->format('Y-m-d'))
            ->pluck('price_date')
            ->map(function($date) {
                return is_string($date) ? $date : $date->format('Y-m-d');
            })
            ->toArray();

        // Generate all expected business days
        $expectedDates = $this->getBusinessDays($startDate, $endDate);

        // Find missing dates
        $missingDates = array_diff($expectedDates, $existingDates);

        if (empty($missingDates)) {
            return [];
        }

        // Group consecutive missing dates into ranges
        sort($missingDates);
        $ranges = [];
        $rangeStart = $missingDates[0];
        $rangeEnd = $missingDates[0];

        for ($i = 1; $i < count($missingDates); $i++) {
            $currentDate = $missingDates[$i];
            $prevDate = $missingDates[$i - 1];

            // Check if dates are consecutive business days
            if ($this->isNextBusinessDay($prevDate, $currentDate)) {
                $rangeEnd = $currentDate;
            } else {
                // End current range and start new one
                $ranges[] = ['start' => $rangeStart, 'end' => $rangeEnd];
                $rangeStart = $currentDate;
                $rangeEnd = $currentDate;
            }
        }

        // Add the last range
        $ranges[] = ['start' => $rangeStart, 'end' => $rangeEnd];

        return $ranges;
    }

    /**
     * Fetch historical data for a specific date range
     */
    private function fetchHistoricalDataRange(string $symbol, string $startDate, string $endDate): int
    {
        try {
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);

            $url = self::YAHOO_FINANCE_BASE_URL . urlencode($symbol) .
                   '?period1=' . $start->getTimestamp() .
                   '&period2=' . $end->getTimestamp() .
                   '&interval=1d';

            $response = $this->makeHttpRequest($url);
            $data = json_decode($response, true);

            if (!isset($data['chart']['result'][0]['timestamp'])) {
                return 0;
            }

            $result = $data['chart']['result'][0];
            $timestamps = $result['timestamp'];
            $quotes = $result['indicators']['quote'][0];
            $adjClose = $result['indicators']['adjclose'][0]['adjclose'] ?? null;

            $stored = 0;
            for ($i = 0; $i < count($timestamps); $i++) {
                $date = date('Y-m-d', $timestamps[$i]);

                // Skip if any required data is null
                if (is_null($quotes['close'][$i]) || is_null($quotes['volume'][$i])) {
                    continue;
                }

                $priceData = [
                    'symbol' => $symbol,
                    'price_date' => $date,
                    'open_price' => $quotes['open'][$i],
                    'high_price' => $quotes['high'][$i],
                    'low_price' => $quotes['low'][$i],
                    'close_price' => $quotes['close'][$i],
                    'adjusted_close' => $adjClose[$i] ?? $quotes['close'][$i],
                    'volume' => $quotes['volume'][$i]
                ];

                if ($this->storeHistoricalPrice($priceData)) {
                    $stored++;
                }
            }

            $this->log("Stored {$stored} historical price records for {$symbol} ({$startDate} to {$endDate})");
            return $stored;

        } catch (Exception $e) {
            error_log("Historical data fetch error for {$symbol}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Store historical price data in database
     */
    private function storeHistoricalPrice(array $priceData): bool
    {
        try {
            StockPrice::updateOrCreate(
                [
                    'symbol' => $priceData['symbol'],
                    'price_date' => $priceData['price_date']
                ],
                $priceData
            );
            return true;
        } catch (Exception $e) {
            error_log("Error storing historical price: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get historical price for a specific date
     */
    public function getHistoricalPrice(string $symbol, string $date): ?float
    {
        try {
            $stockPrice = StockPrice::where('symbol', $symbol)
                ->where('price_date', $date)
                ->first();

            return $stockPrice ? (float) $stockPrice->close_price : null;
        } catch (Exception $e) {
            error_log("Error getting historical price for {$symbol} on {$date}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get the most recent available price for a symbol before or on a given date
     */
    public function getMostRecentPrice(string $symbol, string $beforeDate): ?float
    {
        try {
            $stockPrice = StockPrice::where('symbol', $symbol)
                ->where('price_date', '<=', $beforeDate)
                ->orderBy('price_date', 'desc')
                ->first();

            return $stockPrice ? (float) $stockPrice->close_price : null;
        } catch (Exception $e) {
            error_log("Error getting most recent price for {$symbol} before {$beforeDate}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get historical prices for a symbol over a period
     */
    public function getHistoricalPrices(string $symbol, int $days = 30): array
    {
        try {
            $endDate = new \DateTime();
            $startDate = (clone $endDate)->modify("-{$days} days");

            $prices = StockPrice::where('symbol', $symbol)
                ->where('price_date', '>=', $startDate->format('Y-m-d'))
                ->where('price_date', '<=', $endDate->format('Y-m-d'))
                ->orderBy('price_date', 'asc')
                ->get();

            return $prices->map(function ($price) {
                return [
                    'date' => $price->price_date,
                    'open' => $price->open_price,
                    'high' => $price->high_price,
                    'low' => $price->low_price,
                    'close' => $price->close_price,
                    'volume' => $price->volume
                ];
            })->toArray();
        } catch (Exception $e) {
            error_log("Error getting historical prices for {$symbol}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate mock historical data for development
     */
    private function generateMockHistoricalData(string $symbol, int $days): bool
    {
        $mockQuote = $this->getMockQuote($symbol);
        if (!$mockQuote) {
            return false;
        }

        $basePrice = $mockQuote['current_price'];
        $stored = 0;

        for ($i = $days; $i >= 0; $i--) {
            $dateTime = DateTimeHelper::now();
            $dateTime->modify("-{$i} days");
            $date = $dateTime->format('Y-m-d');

            // Generate realistic price variations (±2% daily)
            $variation = mt_rand(-200, 200) / 10000; // -2% to +2%
            $dayPrice = $basePrice * (1 + $variation);

            $open = $dayPrice * (1 + mt_rand(-50, 50) / 10000);
            $high = max($open, $dayPrice) * (1 + mt_rand(0, 100) / 10000);
            $low = min($open, $dayPrice) * (1 - mt_rand(0, 100) / 10000);
            $volume = mt_rand(1000000, 50000000);

            $priceData = [
                'symbol' => $symbol,
                'price_date' => $date,
                'open_price' => round($open, 2),
                'high_price' => round($high, 2),
                'low_price' => round($low, 2),
                'close_price' => round($dayPrice, 2),
                'adjusted_close' => round($dayPrice, 2),
                'volume' => $volume
            ];

            if ($this->storeHistoricalPrice($priceData)) {
                $stored++;
            }
        }

        $this->log("Generated {$stored} mock historical price records for {$symbol}");
        return $stored > 0;
    }

    /**
     * Check if we should use mock data (for development/testing)
     */
    private function shouldUseMockData(): bool
    {
        return ($_ENV['USE_MOCK_STOCK_DATA'] ?? 'false') === 'true';
    }

    /**
     * Get business days between two dates
     */
    private function getBusinessDays(\DateTime $startDate, \DateTime $endDate): array
    {
        $businessDays = [];
        $current = clone $startDate;

        while ($current <= $endDate) {
            $dayOfWeek = (int)$current->format('N'); // 1 = Monday, 7 = Sunday

            // Skip weekends (Saturday = 6, Sunday = 7)
            if ($dayOfWeek < 6) {
                $businessDays[] = $current->format('Y-m-d');
            }

            $current->modify('+1 day');
        }

        return $businessDays;
    }

    /**
     * Check if second date is the next business day after first date
     */
    private function isNextBusinessDay(string $date1, string $date2): bool
    {
        $d1 = new \DateTime($date1);
        $d2 = new \DateTime($date2);

        // Add one day to first date
        $nextDay = clone $d1;
        $nextDay->modify('+1 day');

        // Skip weekends
        while ((int)$nextDay->format('N') >= 6) {
            $nextDay->modify('+1 day');
        }

        return $nextDay->format('Y-m-d') === $d2->format('Y-m-d');
    }

    /**
     * Log message with timestamp
     */
    private function log(string $message): void
    {
        $timestamp = DateTimeHelper::now()->format('Y-m-d H:i:s');
        error_log("[{$timestamp}] StockDataService: {$message}");
    }

    /**
     * Get mock stock quote data for testing
     */
    private function getMockQuote(string $symbol): ?array
    {
        $mockData = [
            'AAPL' => [
                'name' => 'Apple Inc.',
                'current_price' => 227.52,
                'previous_close' => 225.77,
                'volume' => 45234567,
                'market_cap' => 3456789012345,
                'fifty_two_week_high' => 237.23,
                'fifty_two_week_low' => 164.08,
                'exchange' => 'NASDAQ'
            ],
            'MSFT' => [
                'name' => 'Microsoft Corporation',
                'current_price' => 415.26,
                'previous_close' => 413.65,
                'volume' => 23456789,
                'market_cap' => 3123456789012,
                'fifty_two_week_high' => 468.35,
                'fifty_two_week_low' => 309.45,
                'exchange' => 'NASDAQ'
            ],
            'GOOGL' => [
                'name' => 'Alphabet Inc.',
                'current_price' => 175.84,
                'previous_close' => 174.12,
                'volume' => 18765432,
                'market_cap' => 2234567890123,
                'fifty_two_week_high' => 193.31,
                'fifty_two_week_low' => 129.40,
                'exchange' => 'NASDAQ'
            ],
            'TSLA' => [
                'name' => 'Tesla, Inc.',
                'current_price' => 248.98,
                'previous_close' => 251.44,
                'volume' => 67890123,
                'market_cap' => 789012345678,
                'fifty_two_week_high' => 299.29,
                'fifty_two_week_low' => 138.80,
                'exchange' => 'NASDAQ'
            ],
            'MO' => [
                'name' => 'Altria Group, Inc.',
                'current_price' => 59.62,
                'previous_close' => 58.95,
                'volume' => 12345678,
                'market_cap' => 108765432109,
                'fifty_two_week_high' => 62.84,
                'fifty_two_week_low' => 40.35,
                'exchange' => 'NYSE'
            ],
            'NVDA' => [
                'name' => 'NVIDIA Corporation',
                'current_price' => 875.30,
                'previous_close' => 869.45,
                'volume' => 34567890,
                'market_cap' => 2156789012345,
                'fifty_two_week_high' => 974.00,
                'fifty_two_week_low' => 390.50,
                'exchange' => 'NASDAQ'
            ],
            'SPY' => [
                'name' => 'SPDR S&P 500 ETF Trust',
                'current_price' => 589.12,
                'previous_close' => 587.33,
                'volume' => 45678901,
                'market_cap' => 567890123456,
                'fifty_two_week_high' => 595.38,
                'fifty_two_week_low' => 440.82,
                'exchange' => 'NYSE'
            ],
            'AMZN' => [
                'name' => 'Amazon.com, Inc.',
                'current_price' => 186.51,
                'previous_close' => 184.92,
                'volume' => 23456789,
                'market_cap' => 1987654321098,
                'fifty_two_week_high' => 201.20,
                'fifty_two_week_low' => 139.52,
                'exchange' => 'NASDAQ'
            ]
        ];

        if (!isset($mockData[$symbol])) {
            return null;
        }

        $data = $mockData[$symbol];
        $changeAmount = $data['current_price'] - $data['previous_close'];

        return [
            'symbol' => $symbol,
            'current_price' => $data['current_price'],
            'change_amount' => $changeAmount,
            'change_percent' => $this->calculateChangePercent($data['current_price'], $data['previous_close']),
            'volume' => $data['volume'],
            'market_cap' => $data['market_cap'],
            'fifty_two_week_high' => $data['fifty_two_week_high'],
            'fifty_two_week_low' => $data['fifty_two_week_low'],
            'quote_time' => DateTimeHelper::now(),
            'market_state' => 'REGULAR',
            'currency' => 'USD',
            'exchange' => $data['exchange'],
            'name' => $data['name']
        ];
    }
}
