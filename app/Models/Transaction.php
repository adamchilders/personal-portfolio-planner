<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Helpers\DateTimeHelper;

class Transaction extends Model
{
    protected $table = 'transactions';
    
    protected $fillable = [
        'portfolio_id',
        'stock_symbol',
        'transaction_type',
        'quantity',
        'price',
        'fees',
        'transaction_date',
        'settlement_date',
        'notes',
        'external_id'
    ];
    
    protected $casts = [
        'quantity' => 'decimal:6',
        'price' => 'decimal:4',
        'fees' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'transaction_date' => 'date',
        'settlement_date' => 'date',
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
    public function isBuy(): bool
    {
        return in_array($this->transaction_type, ['buy', 'transfer_in']);
    }
    
    public function isSell(): bool
    {
        return in_array($this->transaction_type, ['sell', 'transfer_out']);
    }
    
    public function isDividend(): bool
    {
        return $this->transaction_type === 'dividend';
    }
    
    public function isSplit(): bool
    {
        return $this->transaction_type === 'split';
    }
    
    public function getTotalAmount(): float
    {
        return (float)($this->quantity * $this->price + $this->fees);
    }
    
    public function getNetAmount(): float
    {
        $amount = $this->quantity * $this->price;
        
        if ($this->isBuy()) {
            return -($amount + $this->fees); // Negative for purchases
        } elseif ($this->isSell()) {
            return $amount - $this->fees; // Positive for sales
        } elseif ($this->isDividend()) {
            return $amount; // Positive for dividends
        }
        
        return 0.0;
    }
    
    public function updateHolding(): void
    {
        if ($this->isDividend() || $this->isSplit()) {
            return; // These don't affect holdings directly
        }
        
        $holding = PortfolioHolding::where('portfolio_id', $this->portfolio_id)
            ->where('stock_symbol', $this->stock_symbol)
            ->first();
            
        if (!$holding && $this->isBuy()) {
            // Create new holding
            $holding = PortfolioHolding::create([
                'portfolio_id' => $this->portfolio_id,
                'stock_symbol' => $this->stock_symbol,
                'quantity' => 0,
                'avg_cost_basis' => 0,
                'is_active' => true
            ]);
        }
        
        if ($holding) {
            $holding->updateFromTransaction($this);
        }
    }
    
    // Scopes
    public function scopeForPortfolio($query, int $portfolioId)
    {
        return $query->where('portfolio_id', $portfolioId);
    }
    
    public function scopeForSymbol($query, string $symbol)
    {
        return $query->where('stock_symbol', $symbol);
    }
    
    public function scopeByType($query, string $type)
    {
        return $query->where('transaction_type', $type);
    }
    
    public function scopeBuys($query)
    {
        return $query->whereIn('transaction_type', ['buy', 'transfer_in']);
    }
    
    public function scopeSells($query)
    {
        return $query->whereIn('transaction_type', ['sell', 'transfer_out']);
    }
    
    public function scopeDividends($query)
    {
        return $query->where('transaction_type', 'dividend');
    }
    
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('transaction_date', '>=', DateTimeHelper::now()->modify("-{$days} days"));
    }
    
    public function scopeOrderByDate($query, string $direction = 'desc')
    {
        return $query->orderBy('transaction_date', $direction);
    }
}
