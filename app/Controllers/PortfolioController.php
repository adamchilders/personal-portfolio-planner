<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PortfolioService;
use App\Services\DividendSafetyService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PortfolioController
{
    public function __construct(
        private PortfolioService $portfolioService,
        private DividendSafetyService $dividendSafetyService
    ) {}
    
    /**
     * Get all portfolios for the authenticated user
     */
    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        
        try {
            $portfolios = $this->portfolioService->getUserPortfolios($user);
            
            $portfoliosData = $portfolios->map(function ($portfolio) {
                return [
                    'id' => $portfolio->id,
                    'name' => $portfolio->name,
                    'description' => $portfolio->description,
                    'type' => $portfolio->portfolio_type,
                    'currency' => $portfolio->currency,
                    'is_public' => $portfolio->is_public,
                    'holdings_count' => $portfolio->getHoldingsCount(),
                    'total_value' => $portfolio->getTotalValue(),
                    'total_cost_basis' => $portfolio->getTotalCostBasis(),
                    'total_gain_loss' => $portfolio->getTotalGainLoss(),
                    'total_gain_loss_percent' => $portfolio->getTotalGainLossPercent(),
                    'created_at' => $portfolio->created_at->toISOString(),
                    'updated_at' => $portfolio->updated_at->toISOString()
                ];
            });
            
            $responseData = [
                'portfolios' => $portfoliosData,
                'total_portfolios' => $portfolios->count()
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        }
    }
    
    /**
     * Create a new portfolio
     */
    public function create(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');
        $data = $request->getParsedBody();
        
        try {
            $portfolio = $this->portfolioService->create($user, $data);
            
            $responseData = [
                'success' => true,
                'message' => 'Portfolio created successfully',
                'portfolio' => [
                    'id' => $portfolio->id,
                    'name' => $portfolio->name,
                    'description' => $portfolio->description,
                    'type' => $portfolio->portfolio_type,
                    'currency' => $portfolio->currency,
                    'is_public' => $portfolio->is_public,
                    'created_at' => $portfolio->created_at->toISOString()
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        }
    }
    
    /**
     * Get a specific portfolio with detailed information
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];
        
        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            $summary = $this->portfolioService->getPortfolioSummary($portfolio);
            
            $response->getBody()->write(json_encode($summary));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 404);
        }
    }
    
    /**
     * Update a portfolio
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];
        $data = $request->getParsedBody();
        
        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            $updatedPortfolio = $this->portfolioService->update($portfolio, $data);
            
            $responseData = [
                'success' => true,
                'message' => 'Portfolio updated successfully',
                'portfolio' => [
                    'id' => $updatedPortfolio->id,
                    'name' => $updatedPortfolio->name,
                    'description' => $updatedPortfolio->description,
                    'type' => $updatedPortfolio->portfolio_type,
                    'currency' => $updatedPortfolio->currency,
                    'is_public' => $updatedPortfolio->is_public,
                    'updated_at' => $updatedPortfolio->updated_at->toISOString()
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        }
    }
    
    /**
     * Delete a portfolio
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];
        
        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            $this->portfolioService->delete($portfolio);
            
            $responseData = [
                'success' => true,
                'message' => 'Portfolio deleted successfully'
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        }
    }
    
    /**
     * Add a holding to a portfolio
     */
    public function addHolding(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];
        $data = $request->getParsedBody();
        
        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            $holding = $this->portfolioService->addHolding($portfolio, $data);
            
            $responseData = [
                'success' => true,
                'message' => 'Holding added successfully',
                'holding' => [
                    'id' => $holding->id,
                    'stock_symbol' => $holding->stock_symbol,
                    'quantity' => $holding->quantity,
                    'avg_cost_basis' => $holding->avg_cost_basis,
                    'total_cost_basis' => $holding->getTotalCostBasis(),
                    'created_at' => $holding->created_at->toISOString()
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        }
    }

    /**
     * Delete a holding from a portfolio (removes all transactions for that stock)
     */
    public function deleteHolding(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];
        $symbol = strtoupper($args['symbol']);

        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);

            // Find the holding
            $holding = $portfolio->holdings()
                ->where('stock_symbol', $symbol)
                ->where('is_active', true)
                ->first();

            if (!$holding) {
                return $this->errorResponse($response, 'Holding not found', 404);
            }

            // Delete all transactions for this stock in this portfolio
            $deletedTransactions = $portfolio->transactions()
                ->where('stock_symbol', $symbol)
                ->delete();

            // Delete all dividend payments for this stock in this portfolio
            $deletedDividends = \App\Models\DividendPayment::where('portfolio_id', $portfolio->id)
                ->where('stock_symbol', $symbol)
                ->delete();

            // Remove the holding
            $this->portfolioService->removeHolding($holding);

            $responseData = [
                'success' => true,
                'message' => "All {$symbol} trades and dividend payments deleted successfully",
                'transactions_deleted' => $deletedTransactions,
                'dividend_payments_deleted' => $deletedDividends
            ];

            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        }
    }

    /**
     * Get transactions for a portfolio
     */
    public function getTransactions(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];

        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            $transactions = $portfolio->transactions()
                ->orderBy('transaction_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            $transactionsData = $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'stock_symbol' => $transaction->stock_symbol,
                    'transaction_type' => $transaction->transaction_type,
                    'quantity' => $transaction->quantity,
                    'price' => $transaction->price,
                    'fees' => $transaction->fees,
                    'total_amount' => $transaction->getTotalAmount(),
                    'transaction_date' => $transaction->transaction_date->toDateString(),
                    'notes' => $transaction->notes,
                    'created_at' => $transaction->created_at->toISOString()
                ];
            });

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $transactionsData,
                'total_transactions' => $transactions->count()
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 404);
        }
    }

    /**
     * Add a transaction to a portfolio - handles both POST and GET for development compatibility
     */
    public function addTransaction(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];

        $method = $request->getMethod();

        // Handle both POST (production) and GET (development) requests
        if ($method === 'POST') {
            $data = $request->getParsedBody();
        } else {
            // GET request - use query parameters
            $data = $request->getQueryParams();
        }



        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            $transaction = $this->portfolioService->addTransaction($portfolio, $data);
            
            $responseData = [
                'success' => true,
                'message' => 'Transaction added successfully',
                'transaction' => [
                    'id' => $transaction->id,
                    'stock_symbol' => $transaction->stock_symbol,
                    'transaction_type' => $transaction->transaction_type,
                    'quantity' => $transaction->quantity,
                    'price' => $transaction->price,
                    'fees' => $transaction->fees,
                    'total_amount' => $transaction->getTotalAmount(),
                    'transaction_date' => $transaction->transaction_date->toDateString(),
                    'created_at' => $transaction->created_at->toISOString()
                ]
            ];
            
            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        }
    }

    /**
     * Get a specific transaction
     */
    public function getTransaction(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];
        $transactionId = (int)$args['transactionId'];

        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            $transaction = $portfolio->transactions()->where('id', $transactionId)->first();

            if (!$transaction) {
                return $this->errorResponse($response, 'Transaction not found', 404);
            }

            $transactionData = [
                'id' => $transaction->id,
                'portfolio_id' => $transaction->portfolio_id,
                'stock_symbol' => $transaction->stock_symbol,
                'transaction_type' => $transaction->transaction_type,
                'quantity' => $transaction->quantity,
                'price' => $transaction->price,
                'fees' => $transaction->fees,
                'total_amount' => $transaction->getTotalAmount(),
                'transaction_date' => $transaction->transaction_date->toDateString(),
                'notes' => $transaction->notes,
                'created_at' => $transaction->created_at->toISOString()
            ];

            $response->getBody()->write(json_encode([
                'success' => true,
                'transaction' => $transactionData
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 404);
        }
    }

    /**
     * Update a specific transaction
     */
    public function updateTransaction(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];
        $transactionId = (int)$args['transactionId'];
        $data = $request->getParsedBody();

        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            $transaction = $portfolio->transactions()->where('id', $transactionId)->first();

            if (!$transaction) {
                return $this->errorResponse($response, 'Transaction not found', 404);
            }

            // Update the transaction
            $updatedTransaction = $this->portfolioService->updateTransaction($transaction, $data);

            $responseData = [
                'success' => true,
                'message' => 'Transaction updated successfully',
                'transaction' => [
                    'id' => $updatedTransaction->id,
                    'stock_symbol' => $updatedTransaction->stock_symbol,
                    'transaction_type' => $updatedTransaction->transaction_type,
                    'quantity' => $updatedTransaction->quantity,
                    'price' => $updatedTransaction->price,
                    'fees' => $updatedTransaction->fees,
                    'total_amount' => $updatedTransaction->getTotalAmount(),
                    'transaction_date' => $updatedTransaction->transaction_date->toDateString(),
                    'updated_at' => $updatedTransaction->updated_at->toISOString()
                ]
            ];

            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        }
    }

    /**
     * Delete a specific transaction
     */
    public function deleteTransaction(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];
        $transactionId = (int)$args['transactionId'];

        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            $transaction = $portfolio->transactions()->where('id', $transactionId)->first();

            if (!$transaction) {
                return $this->errorResponse($response, 'Transaction not found', 404);
            }

            // Delete the transaction
            $this->portfolioService->deleteTransaction($transaction);

            $responseData = [
                'success' => true,
                'message' => 'Transaction deleted successfully'
            ];

            $response->getBody()->write(json_encode($responseData));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        }
    }

    /**
     * Get portfolio historical performance data
     */
    public function getHistoricalPerformance(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];
        $days = (int) ($request->getQueryParams()['days'] ?? 30);

        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            $historicalData = $this->portfolioService->getPortfolioHistoricalPerformance($portfolio, $days);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $historicalData
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        }
    }

    /**
     * Get individual stock historical performance data
     */
    public function getStockPerformance(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];
        $days = (int) ($request->getQueryParams()['days'] ?? 30);

        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            $stockPerformance = $this->portfolioService->getStockHistoricalPerformance($portfolio, $days);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $stockPerformance
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        }
    }

    /**
     * Get portfolio events (transactions and dividend payments) for chart annotations
     */
    public function getPortfolioEvents(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];
        $days = (int) ($request->getQueryParams()['days'] ?? 60);

        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            $events = $this->portfolioService->getPortfolioEvents($portfolio, $days);

            $response->getBody()->write(json_encode([
                'success' => true,
                'events' => $events
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            // Log the error for debugging
            error_log("Portfolio Events Error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());

            return $this->errorResponse($response, $e->getMessage(), 500);
        }
    }

    /**
     * Get dividend safety analysis for a portfolio
     */
    public function getDividendSafety(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];

        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            $safetyAnalysis = $this->dividendSafetyService->getPortfolioDividendSafety($portfolio);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $safetyAnalysis
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        }
    }

    /**
     * Get dividend safety score for a specific stock
     */
    public function getStockDividendSafety(Request $request, Response $response, array $args): Response
    {
        $symbol = strtoupper($args['symbol']);

        try {
            $safetyData = $this->dividendSafetyService->calculateDividendSafetyScore($symbol);

            $response->getBody()->write(json_encode([
                'success' => true,
                'symbol' => $symbol,
                'data' => $safetyData
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
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
