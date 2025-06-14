<?php

declare(strict_types=1);

namespace App\Services;

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

        $transactionData['portfolio_id'] = $portfolio->id;

        $transaction = Transaction::create($transactionData);

        // Update the corresponding holding
        $transaction->updateHolding();

        return $transaction;
    }
    
    /**
     * Get portfolio performance summary
     */
    public function getPortfolioSummary(Portfolio $portfolio): array
    {
        $holdings = $portfolio->holdings()->active()->with('stock.quote')->get();

        $totalValue = 0;
        $totalCostBasis = 0;
        $holdingsData = [];

        foreach ($holdings as $holding) {
            $currentValue = $holding->getCurrentValue();
            $costBasis = $holding->getTotalCostBasis();

            $totalValue += $currentValue;
            $totalCostBasis += $costBasis;

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
                'weight' => 0 // Will be calculated after we have total value
            ];
        }

        // Calculate weights
        foreach ($holdingsData as &$holding) {
            $holding['weight'] = $totalValue > 0 ? ($holding['current_value'] / $totalValue) * 100 : 0;
        }

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
                    foreach ($holdings as $holding) {
                        $symbol = $holding->stock_symbol;

                        // Get historical price for this date
                        $historicalPrice = $this->stockDataService->getHistoricalPrice(
                            $symbol,
                            $dateStr
                        );

                        if ($historicalPrice) {
                            // Update last known price for this stock
                            $lastKnownPrices[$symbol] = $historicalPrice;
                            $totalValue += $holding->quantity * $historicalPrice;
                        } else {
                            // Use last known price if available (for weekends/holidays)
                            if (isset($lastKnownPrices[$symbol])) {
                                $totalValue += $holding->quantity * $lastKnownPrices[$symbol];
                            }
                            // If no last known price, try to get the most recent available price
                            else {
                                $recentPrice = $this->stockDataService->getMostRecentPrice($symbol, $dateStr);
                                if ($recentPrice) {
                                    $lastKnownPrices[$symbol] = $recentPrice;
                                    $totalValue += $holding->quantity * $recentPrice;
                                }
                            }
                        }

                        $totalCostBasis += $holding->getTotalCostBasis();
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
            $historicalData = $this->stockDataService->getHistoricalPrices($symbol, $days);

            if (!empty($historicalData)) {
                // Calculate performance over the period
                $firstPrice = $historicalData[0]['close'] ?? 0;
                $lastPrice = end($historicalData)['close'] ?? 0;

                $performancePercent = $firstPrice > 0 ?
                    (($lastPrice - $firstPrice) / $firstPrice) * 100 : 0;

                $stockPerformance[] = [
                    'symbol' => $symbol,
                    'name' => $holding->stock?->name ?? $symbol,
                    'performance_percent' => round($performancePercent, 2),
                    'first_price' => $firstPrice,
                    'last_price' => $lastPrice,
                    'data_points' => count($historicalData)
                ];
            } else {
                // Fallback to current gain/loss if no historical data
                $currentValue = $holding->getCurrentValue();
                $costBasis = $holding->getTotalCostBasis();
                $performancePercent = $costBasis > 0 ?
                    (($currentValue - $costBasis) / $costBasis) * 100 : 0;

                $stockPerformance[] = [
                    'symbol' => $symbol,
                    'name' => $holding->stock?->name ?? $symbol,
                    'performance_percent' => round($performancePercent, 2),
                    'first_price' => 0,
                    'last_price' => 0,
                    'data_points' => 0
                ];
            }
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
}
