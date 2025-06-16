<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DividendPayment;
use App\Models\Portfolio;
use App\Models\PortfolioHolding;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StockDataService;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class PortfolioService
{
    public function __construct(
        private StockDataService $stockDataService
    ) {}
    /**
     * Create a new portfolio for a user
     */
    public function create(User $user, array $portfolioData): Portfolio
    {
        $this->validatePortfolioData($portfolioData);
        
        // Check for duplicate portfolio name for this user
        if ($this->portfolioNameExists($user->id, $portfolioData['name'])) {
            throw new Exception('Portfolio name already exists');
        }
        
        $portfolioData['user_id'] = $user->id;
        $portfolioData['is_active'] = true;
        
        return Portfolio::create($portfolioData);
    }
    
    /**
     * Update an existing portfolio
     */
    public function update(Portfolio $portfolio, array $portfolioData): Portfolio
    {
        // Remove fields that shouldn't be updated directly
        unset($portfolioData['user_id'], $portfolioData['id']);
        
        // Check for duplicate name if name is being changed
        if (isset($portfolioData['name']) && $portfolioData['name'] !== $portfolio->name) {
            if ($this->portfolioNameExists($portfolio->user_id, $portfolioData['name'])) {
                throw new Exception('Portfolio name already exists');
            }
        }
        
        $portfolio->update($portfolioData);
        return $portfolio->fresh();
    }
    
    /**
     * Get all portfolios for a user
     */
    public function getUserPortfolios(User $user, bool $activeOnly = true): Collection
    {
        $query = $user->portfolios()->with(['holdings.stock.quote']);
        
        if ($activeOnly) {
            $query->active();
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }
    
    /**
     * Get portfolio by ID with authorization check
     */
    public function getPortfolio(int $portfolioId, User $user): Portfolio
    {
        $portfolio = Portfolio::with(['holdings.stock.quote', 'transactions'])
            ->where('id', $portfolioId)
            ->where('user_id', $user->id)
            ->first();
            
        if (!$portfolio) {
            throw new Exception('Portfolio not found or access denied');
        }
        
        return $portfolio;
    }
    
    /**
     * Delete a portfolio (soft delete by deactivating)
     */
    public function delete(Portfolio $portfolio): bool
    {
        $portfolio->is_active = false;
        return $portfolio->save();
    }
    
    /**
     * Add a stock holding to a portfolio
     */
    public function addHolding(Portfolio $portfolio, array $holdingData): PortfolioHolding
    {
        $this->validateHoldingData($holdingData);

        // Ensure the stock exists in our database
        $stock = $this->stockDataService->getOrCreateStock($holdingData['stock_symbol']);
        if (!$stock) {
            throw new Exception('Invalid stock symbol or unable to fetch stock data');
        }

        // Ensure we have historical data for portfolio value calculations
        $this->stockDataService->ensureHistoricalData($holdingData['stock_symbol'], 365);

        // Check if holding already exists
        $existingHolding = $portfolio->holdings()
            ->where('stock_symbol', $holdingData['stock_symbol'])
            ->first();

        if ($existingHolding) {
            throw new Exception('Stock already exists in portfolio');
        }

        $holdingData['portfolio_id'] = $portfolio->id;
        $holdingData['is_active'] = true;

        return PortfolioHolding::create($holdingData);
    }
    
    /**
     * Update a portfolio holding
     */
    public function updateHolding(PortfolioHolding $holding, array $holdingData): PortfolioHolding
    {
        // Remove fields that shouldn't be updated directly
        unset($holdingData['portfolio_id'], $holdingData['id']);
        
        $holding->update($holdingData);
        return $holding->fresh();
    }
    
    /**
     * Remove a holding from portfolio
     */
    public function removeHolding(PortfolioHolding $holding): bool
    {
        $holding->is_active = false;
        return $holding->save();
    }
    
    /**
     * Add a transaction to a portfolio
     */
    public function addTransaction(Portfolio $portfolio, array $transactionData): Transaction
    {
        $this->validateTransactionData($transactionData);

        // Ensure the stock exists in our database
        $stock = $this->stockDataService->getOrCreateStock($transactionData['stock_symbol']);
        if (!$stock) {
            throw new Exception('Invalid stock symbol or unable to fetch stock data');
        }

        // Ensure we have historical data for portfolio value calculations
        $this->stockDataService->ensureHistoricalData($transactionData['stock_symbol'], 365);

        $transactionData['portfolio_id'] = $portfolio->id;

        $transaction = Transaction::create($transactionData);

        // Update the corresponding holding
        $transaction->updateHolding();

        return $transaction;
    }

    /**
     * Update a transaction
     */
    public function updateTransaction(Transaction $transaction, array $transactionData): Transaction
    {
        $this->validateTransactionData($transactionData);

        // Store old values for holding recalculation
        $oldQuantity = $transaction->quantity;
        $oldPrice = $transaction->price;
        $oldType = $transaction->transaction_type;

        // Update transaction
        $transaction->update($transactionData);

        // Recalculate holdings based on the change
        $this->recalculateHoldingFromTransactionUpdate($transaction, $oldQuantity, $oldPrice, $oldType);

        return $transaction;
    }

    /**
     * Delete a transaction
     */
    public function deleteTransaction(Transaction $transaction): void
    {
        // Store values for holding recalculation
        $portfolioId = $transaction->portfolio_id;
        $stockSymbol = $transaction->stock_symbol;

        // Delete the transaction
        $transaction->delete();

        // Recalculate holdings for this stock
        $this->recalculateHoldingForStock($portfolioId, $stockSymbol);
    }

    /**
     * Recalculate holding after transaction update
     */
    private function recalculateHoldingFromTransactionUpdate(Transaction $transaction, float $oldQuantity, float $oldPrice, string $oldType): void
    {
        $this->recalculateHoldingForStock($transaction->portfolio_id, $transaction->stock_symbol);
    }

    /**
     * Recalculate holding for a specific stock in a portfolio
     */
    private function recalculateHoldingForStock(int $portfolioId, string $stockSymbol): void
    {
        // Get all transactions for this stock in this portfolio
        $transactions = Transaction::where('portfolio_id', $portfolioId)
            ->where('stock_symbol', $stockSymbol)
            ->orderBy('transaction_date')
            ->orderBy('created_at')
            ->get();

        // Find or create the holding
        $holding = PortfolioHolding::firstOrCreate([
            'portfolio_id' => $portfolioId,
            'stock_symbol' => $stockSymbol
        ]);

        // Recalculate from scratch
        $totalQuantity = 0;
        $totalCostBasis = 0;
        $lastTransactionDate = null;

        foreach ($transactions as $transaction) {
            if ($transaction->transaction_type === 'buy') {
                $totalQuantity += $transaction->quantity;
                $totalCostBasis += ($transaction->quantity * $transaction->price) + $transaction->fees;
            } elseif ($transaction->transaction_type === 'sell') {
                $totalQuantity -= $transaction->quantity;
                // For sells, we don't add to cost basis
            }
            $lastTransactionDate = $transaction->transaction_date;
        }

        // Update the holding
        if ($totalQuantity > 0) {
            $holding->quantity = $totalQuantity;
            $holding->avg_cost_basis = $totalCostBasis / $totalQuantity;
            $holding->last_transaction_date = $lastTransactionDate;
            $holding->save();
        } else {
            // If no shares left, delete the holding
            $holding->delete();
        }
    }

    /**
     * Get portfolio performance summary
     */
    public function getPortfolioSummary(Portfolio $portfolio): array
    {
        $holdings = $portfolio->holdings()->active()->with(['stock.quote', 'stock.dividends'])->get();

        $totalValue = 0;
        $totalCostBasis = 0;
        $holdingsData = [];

        foreach ($holdings as $holding) {
            $currentValue = $holding->getCurrentValue();
            $costBasis = $holding->getTotalCostBasis();

            $totalValue += $currentValue;
            $totalCostBasis += $costBasis;

            // Calculate dividend yield
            $dividendYield = $holding->stock?->getAnnualDividendYield() ?? 0;
            $annualDividends = 0;
            if ($holding->stock) {
                $oneYearAgo = \App\Helpers\DateTimeHelper::now();
                $oneYearAgo->modify('-1 year');
                $annualDividends = $holding->stock->dividends()
                    ->where('ex_date', '>=', $oneYearAgo->format('Y-m-d'))
                    ->where('dividend_type', 'regular')
                    ->sum('amount');
            }

            $holdingsData[] = [
                'symbol' => $holding->stock_symbol,
                'name' => $holding->stock?->name ?? $holding->stock_symbol,
                'sector' => $holding->stock?->sector ?? 'Other',
                'industry' => $holding->stock?->industry ?? 'Unknown',
                'quantity' => $holding->quantity,
                'avg_cost_basis' => $holding->avg_cost_basis,
                'current_price' => $holding->stock?->quote?->current_price ?? 0,
                'current_value' => $currentValue,
                'cost_basis' => $costBasis,
                'gain_loss' => $currentValue - $costBasis,
                'gain_loss_percent' => $costBasis > 0 ? (($currentValue - $costBasis) / $costBasis) * 100 : 0,
                'dividend_yield' => $dividendYield,
                'annual_dividend' => $annualDividends,
                'weight' => 0 // Will be calculated after we have total value
            ];
        }

        // Calculate weights and portfolio dividend yield
        $totalAnnualDividends = 0;
        foreach ($holdingsData as &$holding) {
            $holding['weight'] = $totalValue > 0 ? ($holding['current_value'] / $totalValue) * 100 : 0;
            $totalAnnualDividends += $holding['annual_dividend'] * $holding['quantity'];
        }

        $portfolioDividendYield = $totalValue > 0 ? ($totalAnnualDividends / $totalValue) * 100 : 0;

        return [
            'portfolio' => [
                'id' => $portfolio->id,
                'name' => $portfolio->name,
                'type' => $portfolio->portfolio_type,
                'currency' => $portfolio->currency
            ],
            'performance' => [
                'total_value' => $totalValue,
                'total_cost_basis' => $totalCostBasis,
                'total_gain_loss' => $totalValue - $totalCostBasis,
                'total_gain_loss_percent' => $totalCostBasis > 0 ? (($totalValue - $totalCostBasis) / $totalCostBasis) * 100 : 0,
                'total_annual_dividends' => $totalAnnualDividends,
                'portfolio_dividend_yield' => $portfolioDividendYield,
                'holdings_count' => count($holdingsData)
            ],
            'holdings' => $holdingsData
        ];
    }

    /**
     * Get historical portfolio performance data
     */
    public function getPortfolioHistoricalPerformance(Portfolio $portfolio, int $days = 30): array
    {
        $holdings = $portfolio->holdings()->active()->with('stock')->get();

        if ($holdings->isEmpty()) {
            return [
                'labels' => [],
                'portfolio_values' => [],
                'cost_basis_values' => []
            ];
        }

        // Get the earliest transaction date for this portfolio
        $earliestTransaction = $portfolio->transactions()
            ->orderBy('transaction_date', 'asc')
            ->first();

        if (!$earliestTransaction) {
            return [
                'labels' => [],
                'portfolio_values' => [],
                'cost_basis_values' => []
            ];
        }

        $earliestDate = new \DateTime($earliestTransaction->transaction_date->format('Y-m-d'));
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify("-{$days} days");

        // Use the later of the two dates (earliest transaction or requested start date)
        if ($earliestDate > $startDate) {
            $startDate = $earliestDate;
        }

        $labels = [];
        $portfolioValues = [];
        $costBasisValues = [];

        // Track the last known prices for each stock to use on non-trading days
        $lastKnownPrices = [];

        // Get all business days in the range from first transaction onwards
        $currentDate = clone $startDate;
        while ($currentDate <= $endDate) {
            if ($currentDate->format('N') <= 5) { // Monday = 1, Friday = 5
                $dateStr = $currentDate->format('Y-m-d');
                $labels[] = $currentDate->format('M j');

                $totalValue = 0;
                $totalCostBasis = 0;

                // Only calculate values if we have transactions by this date
                if ($currentDate >= $earliestDate) {
                    // Calculate cost basis and portfolio value based on transactions up to this date
                    $costBasisForDate = $this->calculateCostBasisUpToDate($portfolio, $dateStr);
                    $totalCostBasis = $costBasisForDate;

                    foreach ($holdings as $holding) {
                        $symbol = $holding->stock_symbol;

                        // Get the quantity held up to this date
                        $quantityForDate = $this->calculateQuantityUpToDate($portfolio, $symbol, $dateStr);

                        if ($quantityForDate > 0) {
                            // Get historical price for this date
                            $historicalPrice = $this->stockDataService->getHistoricalPrice(
                                $symbol,
                                $dateStr
                            );

                            if ($historicalPrice) {
                                // Update last known price for this stock
                                $lastKnownPrices[$symbol] = $historicalPrice;
                                $totalValue += $quantityForDate * $historicalPrice;
                            } else {
                                // Use last known price if available (for weekends/holidays)
                                if (isset($lastKnownPrices[$symbol])) {
                                    $totalValue += $quantityForDate * $lastKnownPrices[$symbol];
                                }
                                // If no last known price, try to get the most recent available price
                                else {
                                    $recentPrice = $this->stockDataService->getMostRecentPrice($symbol, $dateStr);
                                    if ($recentPrice) {
                                        $lastKnownPrices[$symbol] = $recentPrice;
                                        $totalValue += $quantityForDate * $recentPrice;
                                    }
                                }
                            }
                        }
                    }
                }
                // If before first transaction, values remain 0

                $portfolioValues[] = round($totalValue, 2);
                $costBasisValues[] = round($totalCostBasis, 2);
            }

            $currentDate->modify('+1 day');
        }

        return [
            'labels' => $labels,
            'portfolio_values' => $portfolioValues,
            'cost_basis_values' => $costBasisValues
        ];
    }

    /**
     * Get individual stock historical performance data
     */
    public function getStockHistoricalPerformance(Portfolio $portfolio, int $days = 30): array
    {
        $holdings = $portfolio->holdings()->active()->with('stock')->get();

        if ($holdings->isEmpty()) {
            return [];
        }

        $stockPerformance = [];

        foreach ($holdings as $holding) {
            $symbol = $holding->stock_symbol;

            // Calculate performance based on cost basis vs current value (more meaningful for investors)
            $currentValue = $holding->getCurrentValue();
            $costBasis = $holding->getTotalCostBasis();
            $avgCostBasis = $holding->avg_cost_basis;
            $currentPrice = $holding->stock?->quote?->current_price ?? 0;

            $performancePercent = $avgCostBasis > 0 ?
                (($currentPrice - $avgCostBasis) / $avgCostBasis) * 100 : 0;

            $stockPerformance[] = [
                'symbol' => $symbol,
                'name' => $holding->stock?->name ?? $symbol,
                'performance_percent' => round($performancePercent, 2),
                'avg_cost_basis' => $avgCostBasis,
                'current_price' => $currentPrice,
                'total_gain_loss' => round($currentValue - $costBasis, 2),
                'total_gain_loss_percent' => $costBasis > 0 ? round((($currentValue - $costBasis) / $costBasis) * 100, 2) : 0
            ];
        }

        return $stockPerformance;
    }
    
    /**
     * Validate portfolio data
     */
    private function validatePortfolioData(array $data): void
    {
        $required = ['name'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '{$field}' is required");
            }
        }
        
        if (strlen($data['name']) > 100) {
            throw new Exception('Portfolio name must be 100 characters or less');
        }
        
        if (isset($data['portfolio_type']) && !in_array($data['portfolio_type'], ['personal', 'retirement', 'trading', 'savings', 'other'])) {
            throw new Exception('Invalid portfolio type');
        }
        
        if (isset($data['currency']) && strlen($data['currency']) !== 3) {
            throw new Exception('Currency must be a 3-character code');
        }
    }
    
    /**
     * Validate holding data
     */
    private function validateHoldingData(array $data): void
    {
        $required = ['stock_symbol', 'quantity', 'avg_cost_basis'];
        
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new Exception("Field '{$field}' is required");
            }
        }
        
        if ($data['quantity'] <= 0) {
            throw new Exception('Quantity must be greater than 0');
        }
        
        if ($data['avg_cost_basis'] < 0) {
            throw new Exception('Average cost basis cannot be negative');
        }
    }
    
    /**
     * Validate transaction data
     */
    private function validateTransactionData(array $data): void
    {
        $required = ['stock_symbol', 'transaction_type', 'quantity', 'price', 'transaction_date'];



        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                throw new Exception("Field '{$field}' is required");
            }
        }
        
        $validTypes = ['buy', 'sell', 'dividend', 'split', 'transfer_in', 'transfer_out'];
        if (!in_array($data['transaction_type'], $validTypes)) {
            throw new Exception('Invalid transaction type');
        }
        
        if ($data['quantity'] <= 0) {
            throw new Exception('Quantity must be greater than 0');
        }
        
        if ($data['price'] < 0) {
            throw new Exception('Price cannot be negative');
        }
    }
    
    /**
     * Check if portfolio name exists for user
     */
    private function portfolioNameExists(int $userId, string $name): bool
    {
        return Portfolio::where('user_id', $userId)
            ->where('name', $name)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Calculate total cost basis up to a specific date
     */
    private function calculateCostBasisUpToDate(Portfolio $portfolio, string $date): float
    {
        $transactions = $portfolio->transactions()
            ->where('transaction_date', '<=', $date)
            ->where('transaction_type', 'buy')
            ->get();

        $totalCostBasis = 0;
        foreach ($transactions as $transaction) {
            $totalCostBasis += $transaction->quantity * $transaction->price;
        }

        return $totalCostBasis;
    }

    /**
     * Calculate quantity held for a specific symbol up to a specific date
     */
    private function calculateQuantityUpToDate(Portfolio $portfolio, string $symbol, string $date): float
    {
        $transactions = $portfolio->transactions()
            ->where('stock_symbol', $symbol)
            ->where('transaction_date', '<=', $date)
            ->get();

        $totalQuantity = 0;
        foreach ($transactions as $transaction) {
            if ($transaction->transaction_type === 'buy') {
                $totalQuantity += $transaction->quantity;
            } elseif ($transaction->transaction_type === 'sell') {
                $totalQuantity -= $transaction->quantity;
            }
        }

        return max(0, $totalQuantity); // Don't allow negative quantities
    }

    /**
     * Get portfolio events (transactions and dividend payments) for chart annotations
     */
    public function getPortfolioEvents(Portfolio $portfolio, int $days = 60): array
    {
        try {
            $startDate = new \DateTime();
            $startDate->modify("-{$days} days");
            $events = [];
        } catch (\Exception $e) {
            error_log("Error creating start date in getPortfolioEvents: " . $e->getMessage());
            return [];
        }

        // Get transactions within the date range
        $transactions = $portfolio->transactions()
            ->where('transaction_date', '>=', $startDate->format('Y-m-d'))
            ->orderBy('transaction_date', 'asc')
            ->get();

        foreach ($transactions as $transaction) {
            $events[] = [
                'type' => 'transaction',
                'subtype' => $transaction->transaction_type,
                'date' => $transaction->transaction_date->format('Y-m-d'),
                'symbol' => $transaction->stock_symbol,
                'quantity' => $transaction->quantity,
                'price' => $transaction->price,
                'amount' => $transaction->getTotalAmount(),
                'description' => $this->getTransactionDescription($transaction),
                'color' => $transaction->transaction_type === 'buy' ? '#10b981' : '#ef4444', // green for buy, red for sell
                'icon' => $transaction->transaction_type === 'buy' ? '📈' : '📉'
            ];
        }

        // Get dividend payments within the date range
        try {
            $dividendPayments = DividendPayment::where('portfolio_id', $portfolio->id)
                ->where('payment_date', '>=', $startDate->format('Y-m-d'))
                ->orderBy('payment_date', 'asc')
                ->get();
        } catch (\Exception $e) {
            error_log("Error fetching dividend payments in getPortfolioEvents: " . $e->getMessage());
            $dividendPayments = collect(); // Empty collection
        }

        foreach ($dividendPayments as $payment) {
            $events[] = [
                'type' => 'dividend',
                'subtype' => $payment->payment_type,
                'date' => $payment->payment_date->format('Y-m-d'),
                'symbol' => $payment->stock_symbol,
                'amount' => $payment->total_dividend_amount,
                'shares' => $payment->payment_type === 'drip' ? $payment->drip_shares_purchased : null,
                'description' => $this->getDividendDescription($payment),
                'color' => $payment->payment_type === 'drip' ? '#8b5cf6' : '#f59e0b', // purple for DRIP, orange for cash
                'icon' => $payment->payment_type === 'drip' ? '🔄' : '💰'
            ];
        }

        // Sort all events by date
        usort($events, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return $events;
    }

    /**
     * Generate description for transaction events
     */
    private function getTransactionDescription(Transaction $transaction): string
    {
        $action = $transaction->transaction_type === 'buy' ? 'Bought' : 'Sold';
        $shares = number_format($transaction->quantity, 0);
        $price = number_format($transaction->price, 2);

        return "{$action} {$shares} shares of {$transaction->stock_symbol} @ \${$price}";
    }

    /**
     * Generate description for dividend events
     */
    private function getDividendDescription(DividendPayment $payment): string
    {
        $amount = number_format($payment->total_dividend_amount, 2);

        if ($payment->payment_type === 'drip') {
            $shares = number_format($payment->drip_shares_purchased, 3);
            return "DRIP: \${$amount} → {$shares} shares of {$payment->stock_symbol}";
        } else {
            return "Cash dividend: \${$amount} from {$payment->stock_symbol}";
        }
    }
}
