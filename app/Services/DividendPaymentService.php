<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Portfolio;
use App\Models\PortfolioHolding;
use App\Models\Dividend;
use App\Models\DividendPayment;
use App\Models\Transaction;
use App\Models\Stock;
use App\Helpers\DateTimeHelper;
use Exception;

class DividendPaymentService
{
    private PortfolioService $portfolioService;
    
    public function __construct(PortfolioService $portfolioService)
    {
        $this->portfolioService = $portfolioService;
    }
    
    /**
     * Get pending dividend payments for a portfolio
     */
    public function getPendingDividendPayments(Portfolio $portfolio): array
    {
        // Get all holdings in the portfolio
        $holdings = $portfolio->holdings()->where('is_active', true)->get();
        
        if ($holdings->isEmpty()) {
            return [];
        }
        
        $pendingPayments = [];
        $today = DateTimeHelper::now()->format('Y-m-d');
        
        foreach ($holdings as $holding) {
            // Get recent dividends for this stock (last 90 days or future payments within 30 days)
            $recentDividends = Dividend::where('symbol', $holding->stock_symbol)
                ->where(function($query) use ($today) {
                    $query->where(function($q) use ($today) {
                        // Past payments within 90 days
                        $q->where('payment_date', '<=', $today)
                          ->where('payment_date', '>=', date('Y-m-d', strtotime('-90 days')));
                    })->orWhere(function($q) use ($today) {
                        // Future payments within 30 days
                        $q->where('payment_date', '>', $today)
                          ->where('payment_date', '<=', date('Y-m-d', strtotime('+30 days')));
                    });
                })
                ->orderBy('payment_date', 'desc')
                ->get();
            
            foreach ($recentDividends as $dividend) {
                // Check if payment already recorded
                $existingPayment = DividendPayment::where('portfolio_id', $portfolio->id)
                    ->where('dividend_id', $dividend->id)
                    ->first();
                
                if (!$existingPayment) {
                    // Calculate shares owned on ex-date
                    $sharesOnExDate = $this->getSharesOwnedOnDate($holding, $dividend->ex_date->format('Y-m-d'));
                    
                    if ($sharesOnExDate > 0) {
                        $totalDividend = $sharesOnExDate * $dividend->amount;
                        
                        $pendingPayments[] = [
                            'dividend_id' => $dividend->id,
                            'stock_symbol' => $dividend->symbol,
                            'stock_name' => $holding->stock->name ?? $dividend->symbol,
                            'ex_date' => $dividend->ex_date->format('Y-m-d'),
                            'payment_date' => $dividend->payment_date ? $dividend->payment_date->format('Y-m-d') : null,
                            'dividend_per_share' => $dividend->amount,
                            'shares_owned' => $sharesOnExDate,
                            'total_dividend_amount' => $totalDividend,
                            'current_stock_price' => $this->getCurrentStockPrice($dividend->symbol)
                        ];
                    }
                }
            }
        }
        
        // Sort by payment date (most recent first)
        usort($pendingPayments, function($a, $b) {
            return strtotime($b['payment_date'] ?? '1970-01-01') - strtotime($a['payment_date'] ?? '1970-01-01');
        });
        
        return $pendingPayments;
    }
    
    /**
     * Record a dividend payment
     */
    public function recordDividendPayment(Portfolio $portfolio, array $paymentData): DividendPayment
    {
        $dividend = Dividend::findOrFail($paymentData['dividend_id']);
        
        // Validate that this dividend hasn't been recorded yet
        $existingPayment = DividendPayment::where('portfolio_id', $portfolio->id)
            ->where('dividend_id', $dividend->id)
            ->first();
        
        if ($existingPayment) {
            throw new Exception('Dividend payment already recorded for this dividend');
        }
        
        // Create the dividend payment record
        $dividendPayment = DividendPayment::create([
            'portfolio_id' => $portfolio->id,
            'dividend_id' => $dividend->id,
            'stock_symbol' => $dividend->symbol,
            'payment_date' => $paymentData['payment_date'],
            'shares_owned' => $paymentData['shares_owned'],
            'dividend_per_share' => $dividend->amount,
            'total_dividend_amount' => $paymentData['total_dividend_amount'],
            'payment_type' => $paymentData['payment_type'],
            'drip_shares_purchased' => $paymentData['drip_shares_purchased'] ?? null,
            'drip_price_per_share' => $paymentData['drip_price_per_share'] ?? null,
            'notes' => $paymentData['notes'] ?? null,
            'is_confirmed' => true
        ]);
        
        // Update portfolio holdings based on payment type
        $this->updateHoldingsForDividendPayment($portfolio, $dividendPayment);
        
        // Create transaction record
        $this->createDividendTransaction($portfolio, $dividendPayment);
        
        return $dividendPayment;
    }
    
    /**
     * Update portfolio holdings based on dividend payment type
     */
    private function updateHoldingsForDividendPayment(Portfolio $portfolio, DividendPayment $payment): void
    {
        $holding = PortfolioHolding::where('portfolio_id', $portfolio->id)
            ->where('stock_symbol', $payment->stock_symbol)
            ->first();
        
        if (!$holding) {
            throw new Exception('Holding not found for dividend payment');
        }
        
        if ($payment->isDrip() && $payment->drip_shares_purchased > 0) {
            // DRIP: Add shares and adjust cost basis
            $oldQuantity = $holding->quantity;
            $oldTotalCost = $oldQuantity * $holding->avg_cost_basis;
            
            // Add DRIP shares
            $newQuantity = $oldQuantity + $payment->drip_shares_purchased;
            
            // The DRIP shares were "purchased" with the dividend, so we don't add to cost basis
            // Instead, we reduce the overall cost basis by the dividend amount
            $newTotalCost = $oldTotalCost - $payment->total_dividend_amount;
            $newAvgCostBasis = $newQuantity > 0 ? $newTotalCost / $newQuantity : 0;
            
            $holding->update([
                'quantity' => $newQuantity,
                'avg_cost_basis' => max(0, $newAvgCostBasis), // Ensure non-negative
                'last_transaction_date' => DateTimeHelper::now()
            ]);
        } else {
            // Cash dividend: Reduce cost basis by dividend amount
            $oldTotalCost = $holding->quantity * $holding->avg_cost_basis;
            $newTotalCost = $oldTotalCost - $payment->total_dividend_amount;
            $newAvgCostBasis = $holding->quantity > 0 ? $newTotalCost / $holding->quantity : 0;
            
            $holding->update([
                'avg_cost_basis' => max(0, $newAvgCostBasis), // Ensure non-negative
                'last_transaction_date' => DateTimeHelper::now()
            ]);
        }
    }
    
    /**
     * Create transaction record for dividend payment
     */
    private function createDividendTransaction(Portfolio $portfolio, DividendPayment $payment): void
    {
        if ($payment->isDrip() && $payment->drip_shares_purchased > 0) {
            // Create a buy transaction for DRIP shares
            Transaction::create([
                'portfolio_id' => $portfolio->id,
                'stock_symbol' => $payment->stock_symbol,
                'transaction_type' => 'buy',
                'quantity' => $payment->drip_shares_purchased,
                'price' => $payment->drip_price_per_share,
                'fees' => 0,
                'transaction_date' => $payment->payment_date,
                'notes' => "DRIP purchase from dividend payment (ID: {$payment->id})"
            ]);
        }
        
        // Always create a dividend transaction record
        Transaction::create([
            'portfolio_id' => $portfolio->id,
            'stock_symbol' => $payment->stock_symbol,
            'transaction_type' => 'dividend',
            'quantity' => $payment->shares_owned,
            'price' => $payment->dividend_per_share,
            'fees' => 0,
            'transaction_date' => $payment->payment_date,
            'notes' => "Dividend payment - {$payment->payment_type} (ID: {$payment->id})"
        ]);
    }
    
    /**
     * Get shares owned on a specific date
     */
    private function getSharesOwnedOnDate(PortfolioHolding $holding, string $date): float
    {
        // For now, return current quantity
        // TODO: Implement historical calculation based on transaction history
        return (float)$holding->quantity;
    }
    
    /**
     * Get current stock price
     */
    private function getCurrentStockPrice(string $symbol): ?float
    {
        $stock = Stock::where('symbol', $symbol)->with('quote')->first();
        return $stock && $stock->quote ? (float)$stock->quote->current_price : null;
    }
}
