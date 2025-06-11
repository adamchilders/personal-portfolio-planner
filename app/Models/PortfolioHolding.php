<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Helpers\DateTimeHelper;

class PortfolioHolding extends Model
{
    protected $table = 'portfolio_holdings';
    
    protected $fillable = [
        'portfolio_id',
        'stock_symbol',
        'quantity',
        'avg_cost_basis',
        'first_purchase_date',
        'last_transaction_date',
        'notes',
        'is_active'
    ];
    
    protected $casts = [
        'quantity' => 'decimal:6',
        'avg_cost_basis' => 'decimal:4',
        'first_purchase_date' => 'date',
        'last_transaction_date' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    // Relationships
    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }
    
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'stock_symbol', 'symbol');
    }
    
    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }
    
    public function getTotalCostBasis(): float
    {
        return (float)($this->quantity * $this->avg_cost_basis);
    }
    
    public function getCurrentValue(): float
    {
        $stock = $this->stock;
        if (!$stock || !$stock->quote) {
            return 0.0;
        }
        
        return (float)($this->quantity * $stock->quote->current_price);
    }
    
    public function getGainLoss(): float
    {
        return $this->getCurrentValue() - $this->getTotalCostBasis();
    }
    
    public function getGainLossPercent(): float
    {
        $costBasis = $this->getTotalCostBasis();
        if ($costBasis <= 0) {
            return 0.0;
        }
        
        return (($this->getCurrentValue() - $costBasis) / $costBasis) * 100;
    }
    
    public function updateFromTransaction(Transaction $transaction): void
    {
        if ($transaction->transaction_type === 'buy') {
            $this->addShares($transaction->quantity, $transaction->price);
        } elseif ($transaction->transaction_type === 'sell') {
            $this->removeShares($transaction->quantity);
        }
        
        $this->last_transaction_date = $transaction->transaction_date;
        $this->save();
    }
    
    private function addShares(float $quantity, float $price): void
    {
        $currentValue = $this->quantity * $this->avg_cost_basis;
        $newValue = $quantity * $price;
        $totalQuantity = $this->quantity + $quantity;
        
        if ($totalQuantity > 0) {
            $this->avg_cost_basis = ($currentValue + $newValue) / $totalQuantity;
        }
        
        $this->quantity = $totalQuantity;
        
        if (!$this->first_purchase_date) {
            $this->first_purchase_date = DateTimeHelper::now()->format('Y-m-d');
        }
    }
    
    private function removeShares(float $quantity): void
    {
        $this->quantity = max(0, $this->quantity - $quantity);
        
        if ($this->quantity <= 0) {
            $this->is_active = false;
        }
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeForPortfolio($query, int $portfolioId)
    {
        return $query->where('portfolio_id', $portfolioId);
    }
    
    public function scopeForSymbol($query, string $symbol)
    {
        return $query->where('stock_symbol', $symbol);
    }
}
