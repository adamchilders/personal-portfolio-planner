<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Stock;
use App\Models\StockQuote;
use App\Helpers\DateTimeHelper;
use Exception;

class StockDataService
{
    private const YAHOO_FINANCE_BASE_URL = 'https://query1.finance.yahoo.com/v8/finance/chart/';
    private const YAHOO_SEARCH_URL = 'https://query1.finance.yahoo.com/v1/finance/search';
    
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
     * Get current stock quote
     */
    public function getStockQuote(string $symbol): ?array
    {
        // For development/testing, provide mock data for common symbols
        if ($this->shouldUseMockData()) {
            return $this->getMockQuote($symbol);
        }

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
            error_log("Stock quote error for {$symbol}: " . $e->getMessage());
            // Fallback to mock data if API fails
            return $this->getMockQuote($symbol);
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
     * Check if we should use mock data (for development/testing)
     */
    private function shouldUseMockData(): bool
    {
        return ($_ENV['USE_MOCK_STOCK_DATA'] ?? 'false') === 'true';
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
