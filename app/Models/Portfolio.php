<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Portfolio extends Model
{
    protected $table = 'portfolios';
    
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'portfolio_type',
        'currency',
        'is_active',
        'is_public'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function holdings(): HasMany
    {
        return $this->hasMany(PortfolioHolding::class);
    }
    
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
    
    public function snapshots(): HasMany
    {
        return $this->hasMany(PortfolioSnapshot::class);
    }
    
    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }
    
    public function isPublic(): bool
    {
        return $this->is_public;
    }
    
    public function getTotalValue(): float
    {
        return $this->holdings()
            ->where('is_active', true)
            ->join('stock_quotes', 'portfolio_holdings.stock_symbol', '=', 'stock_quotes.symbol')
            ->selectRaw('SUM(portfolio_holdings.quantity * stock_quotes.current_price) as total_value')
            ->value('total_value') ?? 0.0;
    }
    
    public function getTotalCostBasis(): float
    {
        return $this->holdings()
            ->where('is_active', true)
            ->selectRaw('SUM(quantity * avg_cost_basis) as total_cost')
            ->value('total_cost') ?? 0.0;
    }
    
    public function getTotalGainLoss(): float
    {
        return $this->getTotalValue() - $this->getTotalCostBasis();
    }
    
    public function getTotalGainLossPercent(): float
    {
        $costBasis = $this->getTotalCostBasis();
        if ($costBasis <= 0) {
            return 0.0;
        }
        
        return (($this->getTotalValue() - $costBasis) / $costBasis) * 100;
    }
    
    public function getHoldingsCount(): int
    {
        return $this->holdings()->where('is_active', true)->count();
    }
    
    public function getUniqueSymbols(): array
    {
        return $this->holdings()
            ->where('is_active', true)
            ->pluck('stock_symbol')
            ->unique()
            ->values()
            ->toArray();
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }
    
    public function scopeByType($query, string $type)
    {
        return $query->where('portfolio_type', $type);
    }
    
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
