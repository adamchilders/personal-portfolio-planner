<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\StockDataService;
use App\Models\Stock;
use App\Models\StockPrice;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class StockController
{
    public function __construct(
        private StockDataService $stockDataService
    ) {}
    
    /**
     * Search for stocks
     */
    public function search(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $query = $queryParams['q'] ?? '';
        $limit = min((int)($queryParams['limit'] ?? 10), 50);
        
        if (empty($query) || strlen($query) < 1) {
            return $this->errorResponse($response, 'Search query is required', 400);
        }
        
        try {
            $results = $this->stockDataService->searchStocks($query, $limit);
            
            $responseData = [
                'query' => $query,
                'results' => $results,
                'count' => count($results)
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Search failed: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get stock quote
     */
    public function quote(Request $request, Response $response, array $args): Response
    {
        $symbol = strtoupper($args['symbol'] ?? '');
        
        if (empty($symbol)) {
            return $this->errorResponse($response, 'Stock symbol is required', 400);
        }
        
        if (!$this->stockDataService->isValidSymbol($symbol)) {
            return $this->errorResponse($response, 'Invalid stock symbol format', 400);
        }
        
        try {
            $quoteData = $this->stockDataService->getStockQuote($symbol);
            
            if (!$quoteData) {
                return $this->errorResponse($response, 'Stock not found or data unavailable', 404);
            }
            
            $response->getBody()->write(json_encode($quoteData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Failed to fetch quote: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get historical price data for a stock
     */
    public function history(Request $request, Response $response, array $args): Response
    {
        $symbol = strtoupper($args['symbol'] ?? '');
        $queryParams = $request->getQueryParams();

        if (empty($symbol)) {
            return $this->errorResponse($response, 'Stock symbol is required', 400);
        }

        if (!$this->stockDataService->isValidSymbol($symbol)) {
            return $this->errorResponse($response, 'Invalid stock symbol format', 400);
        }

        // Parse query parameters
        $days = min((int)($queryParams['days'] ?? 30), 365); // Max 1 year
        $startDate = $queryParams['start'] ?? null;
        $endDate = $queryParams['end'] ?? null;

        try {
            $query = StockPrice::where('symbol', $symbol);

            if ($startDate && $endDate) {
                // Use date range if provided
                $query->whereBetween('price_date', [$startDate, $endDate]);
            } else {
                // Use days parameter
                $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));
                $query->where('price_date', '>=', $cutoffDate);
            }

            $prices = $query->orderBy('price_date', 'desc')->get();

            if ($prices->isEmpty()) {
                return $this->errorResponse($response, 'No historical data available for this symbol', 404);
            }

            $responseData = [
                'symbol' => $symbol,
                'period' => [
                    'days' => $days,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'actual_start' => $prices->last()->price_date,
                    'actual_end' => $prices->first()->price_date
                ],
                'count' => $prices->count(),
                'prices' => $prices->map(function ($price) {
                    return [
                        'date' => $price->price_date,
                        'open' => (float)$price->open_price,
                        'high' => (float)$price->high_price,
                        'low' => (float)$price->low_price,
                        'close' => (float)$price->close_price,
                        'adjusted_close' => (float)$price->adjusted_close,
                        'volume' => $price->volume
                    ];
                })->values()
            ];

            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Failed to fetch historical data: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get stock information with quote
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $symbol = strtoupper($args['symbol'] ?? '');
        
        if (empty($symbol)) {
            return $this->errorResponse($response, 'Stock symbol is required', 400);
        }
        
        try {
            $stock = $this->stockDataService->getOrCreateStock($symbol);
            
            if (!$stock) {
                return $this->errorResponse($response, 'Stock not found', 404);
            }
            
            // Load the quote relationship
            $stock->load('quote');
            
            $responseData = [
                'symbol' => $stock->symbol,
                'name' => $stock->name,
                'exchange' => $stock->exchange,
                'sector' => $stock->sector,
                'industry' => $stock->industry,
                'currency' => $stock->currency,
                'country' => $stock->country,
                'is_active' => $stock->is_active,
                'quote' => $stock->quote ? [
                    'current_price' => $stock->quote->current_price,
                    'change_amount' => $stock->quote->change_amount,
                    'change_percent' => $stock->quote->change_percent,
                    'volume' => $stock->quote->volume,
                    'market_cap' => $stock->quote->market_cap,
                    'fifty_two_week_high' => $stock->quote->fifty_two_week_high,
                    'fifty_two_week_low' => $stock->quote->fifty_two_week_low,
                    'quote_time' => $stock->quote->quote_time->toISOString(),
                    'market_state' => $stock->quote->market_state,
                    'formatted_price' => $stock->quote->getFormattedPrice(),
                    'formatted_change' => $stock->quote->getFormattedChange(),
                    'formatted_change_percent' => $stock->quote->getFormattedChangePercent(),
                    'change_direction' => $stock->quote->getChangeDirection()
                ] : null,
                'last_updated' => $stock->last_updated?->toISOString(),
                'created_at' => $stock->created_at->toISOString()
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Failed to fetch stock data: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Update stock quote
     */
    public function updateQuote(Request $request, Response $response, array $args): Response
    {
        $symbol = strtoupper($args['symbol'] ?? '');
        
        if (empty($symbol)) {
            return $this->errorResponse($response, 'Stock symbol is required', 400);
        }
        
        try {
            $stock = Stock::find($symbol);
            
            if (!$stock) {
                return $this->errorResponse($response, 'Stock not found', 404);
            }
            
            $success = $this->stockDataService->updateStockQuote($stock);
            
            if (!$success) {
                return $this->errorResponse($response, 'Failed to update quote', 500);
            }
            
            // Reload the stock with updated quote
            $stock->load('quote');
            
            $responseData = [
                'success' => true,
                'message' => 'Quote updated successfully',
                'symbol' => $stock->symbol,
                'quote' => $stock->quote ? [
                    'current_price' => $stock->quote->current_price,
                    'change_amount' => $stock->quote->change_amount,
                    'change_percent' => $stock->quote->change_percent,
                    'quote_time' => $stock->quote->quote_time->toISOString(),
                    'market_state' => $stock->quote->market_state
                ] : null
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Failed to update quote: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get multiple stock quotes
     */
    public function multipleQuotes(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $symbols = $data['symbols'] ?? [];
        
        if (empty($symbols) || !is_array($symbols)) {
            return $this->errorResponse($response, 'Symbols array is required', 400);
        }
        
        if (count($symbols) > 50) {
            return $this->errorResponse($response, 'Maximum 50 symbols allowed', 400);
        }
        
        try {
            $quotes = [];
            
            foreach ($symbols as $symbol) {
                $symbol = strtoupper(trim($symbol));
                
                if ($this->stockDataService->isValidSymbol($symbol)) {
                    $quoteData = $this->stockDataService->getStockQuote($symbol);
                    if ($quoteData) {
                        $quotes[$symbol] = $quoteData;
                    }
                }
            }
            
            $responseData = [
                'quotes' => $quotes,
                'count' => count($quotes),
                'requested' => count($symbols)
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, 'Failed to fetch quotes: ' . $e->getMessage(), 500);
        }
    }
    
    private function errorResponse(Response $response, string $message, int $status = 400): Response
    {
        $data = [
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ];
        
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
