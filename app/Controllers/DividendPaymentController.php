<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Services\PortfolioService;
use App\Services\DividendPaymentService;
use App\Models\DividendPayment;

class DividendPaymentController extends BaseController
{
    private PortfolioService $portfolioService;
    private DividendPaymentService $dividendPaymentService;
    
    public function __construct(
        PortfolioService $portfolioService,
        DividendPaymentService $dividendPaymentService
    ) {
        $this->portfolioService = $portfolioService;
        $this->dividendPaymentService = $dividendPaymentService;
    }
    
    /**
     * Get pending dividend payments for a portfolio
     */
    public function getPendingPayments(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];
        
        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            $pendingPayments = $this->dividendPaymentService->getPendingDividendPayments($portfolio);
            
            return $this->successResponse($response, [
                'portfolio_id' => $portfolio->id,
                'portfolio_name' => $portfolio->name,
                'pending_payments' => $pendingPayments,
                'count' => count($pendingPayments)
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 404);
        }
    }
    
    /**
     * Record a dividend payment
     */
    public function recordPayment(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];
        $data = $request->getParsedBody();
        
        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            
            // Validate required fields
            $this->validatePaymentData($data);
            
            $dividendPayment = $this->dividendPaymentService->recordDividendPayment($portfolio, $data);
            
            return $this->successResponse($response, [
                'message' => 'Dividend payment recorded successfully',
                'payment' => [
                    'id' => $dividendPayment->id,
                    'stock_symbol' => $dividendPayment->stock_symbol,
                    'payment_type' => $dividendPayment->payment_type,
                    'total_amount' => $dividendPayment->total_dividend_amount,
                    'payment_date' => $dividendPayment->payment_date->format('Y-m-d'),
                    'drip_shares' => $dividendPayment->drip_shares_purchased
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        }
    }

    /**
     * Process multiple dividend payments at once
     */
    public function processBulkPayments(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];
        $data = $request->getParsedBody();

        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);

            if (!isset($data['payments']) || !is_array($data['payments'])) {
                return $this->errorResponse($response, 'Payments array is required', 400);
            }

            // Validate each payment
            foreach ($data['payments'] as $index => $paymentData) {
                try {
                    $this->validatePaymentData($paymentData);
                } catch (\Exception $e) {
                    return $this->errorResponse($response, "Payment {$index}: " . $e->getMessage(), 400);
                }
            }

            $results = $this->dividendPaymentService->processBulkDividendPayments($portfolio, $data['payments']);

            return $this->successResponse($response, [
                'message' => "Bulk processing completed: {$results['successful']}/{$results['total_processed']} payments processed successfully",
                'summary' => $results
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        }
    }

    /**
     * Get dividend analytics for a portfolio
     */
    public function getDividendAnalytics(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];

        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            $analytics = $this->dividendPaymentService->getDividendAnalytics($portfolio);

            return $this->successResponse($response, [
                'portfolio_id' => $portfolio->id,
                'portfolio_name' => $portfolio->name,
                'analytics' => $analytics
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 404);
        }
    }
    
    /**
     * Get dividend payment history for a portfolio
     */
    public function getPaymentHistory(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];
        $queryParams = $request->getQueryParams();
        $symbol = $queryParams['symbol'] ?? null;
        
        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            
            $query = DividendPayment::where('portfolio_id', $portfolio->id)
                ->with(['dividend', 'stock'])
                ->orderBy('payment_date', 'desc');
            
            if ($symbol) {
                $query->where('stock_symbol', strtoupper($symbol));
            }
            
            $payments = $query->get();
            
            $paymentHistory = $payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'stock_symbol' => $payment->stock_symbol,
                    'stock_name' => $payment->stock->name ?? $payment->stock_symbol,
                    'payment_date' => $payment->payment_date->format('Y-m-d'),
                    'ex_date' => $payment->dividend->ex_date->format('Y-m-d'),
                    'shares_owned' => $payment->shares_owned,
                    'dividend_per_share' => $payment->dividend_per_share,
                    'total_amount' => $payment->total_dividend_amount,
                    'payment_type' => $payment->payment_type,
                    'drip_shares_purchased' => $payment->drip_shares_purchased,
                    'drip_price_per_share' => $payment->drip_price_per_share,
                    'notes' => $payment->notes,
                    'created_at' => $payment->created_at->toISOString()
                ];
            });
            
            return $this->successResponse($response, [
                'portfolio_id' => $portfolio->id,
                'portfolio_name' => $portfolio->name,
                'payments' => $paymentHistory,
                'count' => $payments->count(),
                'total_dividends' => $payments->sum('total_dividend_amount')
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 404);
        }
    }
    
    /**
     * Update a dividend payment
     */
    public function updatePayment(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];
        $paymentId = (int)$args['paymentId'];
        $data = $request->getParsedBody();
        
        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            
            $payment = DividendPayment::where('portfolio_id', $portfolio->id)
                ->where('id', $paymentId)
                ->firstOrFail();
            
            // Only allow updating notes for now
            $payment->update([
                'notes' => $data['notes'] ?? $payment->notes
            ]);
            
            return $this->successResponse($response, [
                'message' => 'Dividend payment updated successfully',
                'payment' => [
                    'id' => $payment->id,
                    'notes' => $payment->notes
                ]
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 404);
        }
    }
    
    /**
     * Delete a dividend payment
     */
    public function deletePayment(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];
        $paymentId = (int)$args['paymentId'];
        
        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);
            
            $payment = DividendPayment::where('portfolio_id', $portfolio->id)
                ->where('id', $paymentId)
                ->firstOrFail();
            
            // TODO: Implement reversal of holdings and transaction changes
            $payment->delete();
            
            return $this->successResponse($response, [
                'message' => 'Dividend payment deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 404);
        }
    }
    
    /**
     * Validate payment data
     */
    private function validatePaymentData(array $data): void
    {
        $required = ['dividend_id', 'payment_date', 'shares_owned', 'total_dividend_amount', 'payment_type'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new \InvalidArgumentException("Field '{$field}' is required");
            }
        }
        
        if (!in_array($data['payment_type'], ['cash', 'drip'])) {
            throw new \InvalidArgumentException("Payment type must be 'cash' or 'drip'");
        }
        
        if ($data['payment_type'] === 'drip') {
            if (!isset($data['drip_shares_purchased']) || !isset($data['drip_price_per_share'])) {
                throw new \InvalidArgumentException("DRIP payments require drip_shares_purchased and drip_price_per_share");
            }
        }
        
        if ($data['shares_owned'] <= 0) {
            throw new \InvalidArgumentException("Shares owned must be greater than 0");
        }
        
        if ($data['total_dividend_amount'] <= 0) {
            throw new \InvalidArgumentException("Total dividend amount must be greater than 0");
        }
    }

    /**
     * Validate and optionally clean up invalid dividend payments
     */
    public function validateDividendPayments(Request $request, Response $response, array $args): Response
    {
        $user = $request->getAttribute('user');
        $portfolioId = (int)$args['id'];
        $queryParams = $request->getQueryParams();
        $cleanup = isset($queryParams['cleanup']) && $queryParams['cleanup'] === 'true';

        try {
            $portfolio = $this->portfolioService->getPortfolio($portfolioId, $user);

            if ($cleanup) {
                // Remove invalid payments
                $removedPayments = $this->dividendPaymentService->removeInvalidDividendPayments($portfolio);

                return $this->successResponse($response, [
                    'message' => 'Invalid dividend payments cleaned up successfully',
                    'removed_payments' => $removedPayments,
                    'count' => count($removedPayments)
                ]);
            } else {
                // Just validate and return invalid payments
                $invalidPayments = $this->dividendPaymentService->validateExistingDividendPayments($portfolio);

                return $this->successResponse($response, [
                    'message' => 'Dividend payment validation completed',
                    'invalid_payments' => $invalidPayments,
                    'count' => count($invalidPayments)
                ]);
            }

        } catch (\Exception $e) {
            return $this->errorResponse($response, $e->getMessage(), 400);
        }
    }
}
