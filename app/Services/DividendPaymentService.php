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
     * Process multiple dividend payments at once
     */
    public function processBulkDividendPayments(Portfolio $portfolio, array $paymentsData): array
    {
        $results = [];
        $successful = 0;
        $failed = 0;

        foreach ($paymentsData as $paymentData) {
            try {
                $dividendPayment = $this->recordDividendPayment($portfolio, $paymentData);
                $results[] = [
                    'success' => true,
                    'dividend_id' => $paymentData['dividend_id'],
                    'stock_symbol' => $dividendPayment->stock_symbol,
                    'payment_id' => $dividendPayment->id,
                    'amount' => $dividendPayment->total_dividend_amount
                ];
                $successful++;
            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'dividend_id' => $paymentData['dividend_id'] ?? null,
                    'stock_symbol' => $paymentData['stock_symbol'] ?? 'unknown',
                    'error' => $e->getMessage()
                ];
                $failed++;
            }
        }

        return [
            'total_processed' => count($paymentsData),
            'successful' => $successful,
            'failed' => $failed,
            'results' => $results
        ];
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
     * Get shares owned on a specific date based on transaction history
     */
    private function getSharesOwnedOnDate(PortfolioHolding $holding, string $date): float
    {
        // Get all transactions for this stock up to the given date
        $transactions = Transaction::where('portfolio_id', $holding->portfolio_id)
            ->where('stock_symbol', $holding->stock_symbol)
            ->where('transaction_date', '<=', $date)
            ->orderBy('transaction_date', 'asc')
            ->get();

        $totalShares = 0.0;

        foreach ($transactions as $transaction) {
            switch ($transaction->transaction_type) {
                case 'buy':
                case 'transfer_in':
                    $totalShares += (float)$transaction->quantity;
                    break;

                case 'sell':
                case 'transfer_out':
                    $totalShares -= (float)$transaction->quantity;
                    break;

                case 'split':
                    // For stock splits, multiply by the split ratio
                    // The price field contains the split ratio (e.g., 2.0 for 2:1 split)
                    $totalShares *= (float)$transaction->price;
                    break;

                case 'dividend':
                    // Dividend transactions don't affect share count
                    // (unless it's a DRIP, which would be recorded as a separate buy transaction)
                    break;
            }
        }

        return max(0.0, $totalShares);
    }

    /**
     * Get dividend analytics for a portfolio
     */
    public function getDividendAnalytics(Portfolio $portfolio): array
    {
        // Get all dividend payments for this portfolio
        $payments = DividendPayment::where('portfolio_id', $portfolio->id)
            ->with(['dividend', 'stock'])
            ->orderBy('payment_date', 'desc')
            ->get();

        if ($payments->isEmpty()) {
            return [
                'total_dividends_received' => 0,
                'annual_dividend_income' => 0,
                'dividend_yield' => 0,
                'payment_count' => 0,
                'top_dividend_stocks' => [],
                'monthly_breakdown' => [],
                'drip_vs_cash' => ['drip' => 0, 'cash' => 0]
            ];
        }

        $totalDividends = $payments->sum('total_dividend_amount');
        $paymentCount = $payments->count();

        // Calculate annual dividend income (last 12 months)
        $oneYearAgo = DateTimeHelper::now()->modify('-1 year')->format('Y-m-d');
        $annualDividends = $payments->where('payment_date', '>=', $oneYearAgo)
            ->sum('total_dividend_amount');

        // Calculate portfolio dividend yield
        $portfolioValue = $portfolio->total_value ?? 0;
        $dividendYield = $portfolioValue > 0 ? ($annualDividends / $portfolioValue) * 100 : 0;

        // Top dividend paying stocks
        $stockDividends = $payments->groupBy('stock_symbol')->map(function ($stockPayments) {
            return [
                'symbol' => $stockPayments->first()->stock_symbol,
                'name' => $stockPayments->first()->stock->name ?? $stockPayments->first()->stock_symbol,
                'total_dividends' => $stockPayments->sum('total_dividend_amount'),
                'payment_count' => $stockPayments->count(),
                'last_payment' => $stockPayments->max('payment_date')
            ];
        })->sortByDesc('total_dividends')->take(10)->values();

        // Monthly breakdown (last 12 months)
        $monthlyBreakdown = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = DateTimeHelper::now()->modify("-{$i} months");
            $monthKey = $month->format('Y-m');
            $monthName = $month->format('M Y');

            $monthlyAmount = $payments->filter(function ($payment) use ($monthKey) {
                return $payment->payment_date->format('Y-m') === $monthKey;
            })->sum('total_dividend_amount');

            $monthlyBreakdown[] = [
                'month' => $monthName,
                'amount' => $monthlyAmount
            ];
        }

        // DRIP vs Cash breakdown
        $dripAmount = $payments->where('payment_type', 'drip')->sum('total_dividend_amount');
        $cashAmount = $payments->where('payment_type', 'cash')->sum('total_dividend_amount');

        return [
            'total_dividends_received' => $totalDividends,
            'annual_dividend_income' => $annualDividends,
            'dividend_yield' => round($dividendYield, 2),
            'payment_count' => $paymentCount,
            'top_dividend_stocks' => $stockDividends,
            'monthly_breakdown' => $monthlyBreakdown,
            'drip_vs_cash' => [
                'drip' => $dripAmount,
                'cash' => $cashAmount,
                'drip_percentage' => $totalDividends > 0 ? round(($dripAmount / $totalDividends) * 100, 1) : 0
            ]
        ];
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
