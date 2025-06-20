<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApiKey;
use App\Models\Dividend;
use Exception;

class FinancialModelingPrepService
{
    private const BASE_URL = 'https://financialmodelingprep.com/api/v3';
    private const PROVIDER_NAME = 'financial_modeling_prep';
    
    private ?ApiKey $apiKey = null;
    
    public function __construct()
    {
        $this->apiKey = ApiKey::getActiveKey(self::PROVIDER_NAME);
    }
    
    /**
     * Check if FMP service is available
     */
    public function isAvailable(): bool
    {
        return $this->apiKey && $this->apiKey->canMakeRequest();
    }
    
    /**
     * Fetch dividend data for a stock from FMP
     */
    public function fetchDividendData(string $symbol): array
    {
        if (!$this->isAvailable()) {
            throw new Exception('Financial Modeling Prep API is not available');
        }
        
        try {
            $url = self::BASE_URL . '/historical-price-full/stock_dividend/' . urlencode($symbol) . 
                   '?apikey=' . $this->apiKey->api_key;
            
            $this->log("Fetching FMP dividend data for {$symbol}");
            
            $response = $this->makeHttpRequest($url);
            $data = json_decode($response, true);
            
            // Record API usage
            $this->apiKey->recordUsage();
            
            if (!isset($data['historical']) || !is_array($data['historical'])) {
                $this->log("No dividend data found for {$symbol} in FMP");
                return [];
            }
            
            $dividends = [];
            foreach ($data['historical'] as $dividendData) {
                // FMP provides: date (ex-dividend date), label, adjDividend, dividend, recordDate, paymentDate, declarationDate
                $dividends[] = [
                    'symbol' => $symbol,
                    'ex_date' => $dividendData['date'] ?? null, // This is the ex-dividend date in FMP
                    'payment_date' => $dividendData['paymentDate'] ?? null,
                    'record_date' => $dividendData['recordDate'] ?? null,
                    'declaration_date' => $dividendData['declarationDate'] ?? null,
                    'amount' => (float)($dividendData['adjDividend'] ?? $dividendData['dividend'] ?? 0),
                    'dividend_type' => 'regular'
                ];
            }
            
            // Sort by ex-dividend date (newest first)
            usort($dividends, function($a, $b) {
                return strtotime($b['ex_date'] ?? '1970-01-01') - strtotime($a['ex_date'] ?? '1970-01-01');
            });
            
            $this->log("Found " . count($dividends) . " dividend payments for {$symbol} from FMP");
            return $dividends;
            
        } catch (Exception $e) {
            $this->log("Error fetching FMP dividend data for {$symbol}: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Fetch financial statements for dividend safety analysis
     */
    public function fetchFinancialStatements(string $symbol, int $years = 5): array
    {
        if (!$this->isAvailable()) {
            throw new Exception('Financial Modeling Prep API is not available');
        }

        try {
            $statements = [
                'income_statements' => $this->fetchIncomeStatements($symbol, $years),
                'balance_sheets' => $this->fetchBalanceSheets($symbol, $years),
                'cash_flow_statements' => $this->fetchCashFlowStatements($symbol, $years),
                'key_metrics' => $this->fetchKeyMetrics($symbol, $years)
            ];

            $this->log("Fetched financial statements for {$symbol}");
            return $statements;

        } catch (Exception $e) {
            $this->log("Error fetching financial statements for {$symbol}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch income statements
     */
    public function fetchIncomeStatements(string $symbol, int $years = 5): array
    {
        $url = self::BASE_URL . '/income-statement/' . urlencode($symbol) .
               '?period=annual&limit=' . $years . '&apikey=' . $this->apiKey->api_key;

        $response = $this->makeHttpRequest($url);
        $this->apiKey->recordUsage();

        return json_decode($response, true) ?: [];
    }

    /**
     * Fetch balance sheets
     */
    public function fetchBalanceSheets(string $symbol, int $years = 5): array
    {
        $url = self::BASE_URL . '/balance-sheet-statement/' . urlencode($symbol) .
               '?period=annual&limit=' . $years . '&apikey=' . $this->apiKey->api_key;

        $response = $this->makeHttpRequest($url);
        $this->apiKey->recordUsage();

        return json_decode($response, true) ?: [];
    }

    /**
     * Fetch cash flow statements
     */
    public function fetchCashFlowStatements(string $symbol, int $years = 5): array
    {
        $url = self::BASE_URL . '/cash-flow-statement/' . urlencode($symbol) .
               '?period=annual&limit=' . $years . '&apikey=' . $this->apiKey->api_key;

        $response = $this->makeHttpRequest($url);
        $this->apiKey->recordUsage();

        return json_decode($response, true) ?: [];
    }

    /**
     * Fetch key metrics
     */
    public function fetchKeyMetrics(string $symbol, int $years = 5): array
    {
        $url = self::BASE_URL . '/key-metrics/' . urlencode($symbol) .
               '?period=annual&limit=' . $years . '&apikey=' . $this->apiKey->api_key;

        $response = $this->makeHttpRequest($url);
        $this->apiKey->recordUsage();

        return json_decode($response, true) ?: [];
    }

    /**
     * Fetch upcoming dividend calendar from FMP
     */
    public function fetchDividendCalendar(string $fromDate, string $toDate): array
    {
        if (!$this->isAvailable()) {
            throw new Exception('Financial Modeling Prep API is not available');
        }
        
        try {
            $url = self::BASE_URL . '/stock_dividend_calendar' . 
                   '?from=' . urlencode($fromDate) . 
                   '&to=' . urlencode($toDate) . 
                   '&apikey=' . $this->apiKey->api_key;
            
            $this->log("Fetching FMP dividend calendar from {$fromDate} to {$toDate}");
            
            $response = $this->makeHttpRequest($url);
            $data = json_decode($response, true);
            
            // Record API usage
            $this->apiKey->recordUsage();
            
            if (!is_array($data)) {
                return [];
            }
            
            $dividends = [];
            foreach ($data as $dividendData) {
                $dividends[] = [
                    'symbol' => $dividendData['symbol'] ?? '',
                    'ex_date' => $dividendData['date'] ?? null,
                    'payment_date' => $dividendData['paymentDate'] ?? null,
                    'record_date' => $dividendData['recordDate'] ?? null,
                    'declaration_date' => $dividendData['declarationDate'] ?? null,
                    'amount' => (float)($dividendData['dividend'] ?? 0),
                    'dividend_type' => 'regular'
                ];
            }
            
            $this->log("Found " . count($dividends) . " upcoming dividends from FMP");
            return $dividends;
            
        } catch (Exception $e) {
            $this->log("Error fetching FMP dividend calendar: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get API usage statistics
     */
    public function getUsageStats(): array
    {
        if (!$this->apiKey) {
            return [
                'available' => false,
                'reason' => 'No API key configured'
            ];
        }
        
        return [
            'available' => $this->apiKey->isActive(),
            'provider' => self::PROVIDER_NAME,
            'daily_limit' => $this->apiKey->rate_limit_per_day,
            'usage_today' => $this->apiKey->usage_count_today,
            'remaining_requests' => $this->apiKey->getRemainingDailyRequests(),
            'usage_percentage' => $this->apiKey->getUsagePercentage(),
            'last_used' => $this->apiKey->last_used?->toISOString()
        ];
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
                'timeout' => 15
            ]
        ]);
        
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception("Failed to fetch data from FMP API: {$url}");
        }
        
        return $response;
    }
    
    /**
     * Log messages
     */
    private function log(string $message): void
    {
        error_log("[FMP] " . $message);
    }
}
