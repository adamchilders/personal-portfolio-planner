<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PortfolioService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PortfolioController
{
    public function __construct(
        private PortfolioService $portfolioService
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
