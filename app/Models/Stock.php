<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Helpers\DateTimeHelper;

class Stock extends Model
{
    protected $table = 'stocks';
    protected $primaryKey = 'symbol';
    protected $keyType = 'string';
    public $incrementing = false;

    // Use last_updated instead of updated_at
    const UPDATED_AT = 'last_updated';
    
    protected $fillable = [
        'symbol',
        'name',
        'exchange',
        'sector',
        'industry',
        'market_cap',
        'currency',
        'country',
        'is_active'
    ];
    
    protected $casts = [
        'market_cap' => 'integer',
        'is_active' => 'boolean',
        'last_updated' => 'datetime',
        'created_at' => 'datetime'
    ];
    
    // Relationships
    public function prices(): HasMany
    {
        return $this->hasMany(StockPrice::class, 'symbol', 'symbol');
    }
    
    public function quote(): HasOne
    {
        return $this->hasOne(StockQuote::class, 'symbol', 'symbol');
    }
    
    public function dividends(): HasMany
    {
        return $this->hasMany(Dividend::class, 'symbol', 'symbol');
    }
    
    public function splits(): HasMany
    {
        return $this->hasMany(StockSplit::class, 'symbol', 'symbol');
    }
    
    public function holdings(): HasMany
    {
        return $this->hasMany(PortfolioHolding::class, 'stock_symbol', 'symbol');
    }
    
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'stock_symbol', 'symbol');
    }
    
    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active;
    }
    
    public function getCurrentPrice(): ?float
    {
        return $this->quote?->current_price ? (float)$this->quote->current_price : null;
    }
    
    public function getPriceChange(): ?float
    {
        return $this->quote?->change_amount ? (float)$this->quote->change_amount : null;
    }

    public function getPriceChangePercent(): ?float
    {
        return $this->quote?->change_percent ? (float)$this->quote->change_percent : null;
    }
    
    public function getLatestPrice(int $days = 1): ?StockPrice
    {
        return $this->prices()
            ->where('price_date', '>=', DateTimeHelper::now()->modify("-{$days} days"))
            ->orderBy('price_date', 'desc')
            ->first();
    }

    public function getPriceHistory(int $days = 30): \Illuminate\Database\Eloquent\Collection
    {
        return $this->prices()
            ->where('price_date', '>=', DateTimeHelper::now()->modify("-{$days} days"))
            ->orderBy('price_date', 'asc')
            ->get();
    }

    public function getRecentDividends(int $months = 12): \Illuminate\Database\Eloquent\Collection
    {
        return $this->dividends()
            ->where('ex_date', '>=', DateTimeHelper::now()->modify("-{$months} months"))
            ->orderBy('ex_date', 'desc')
            ->get();
    }
    
    public function getAnnualDividendYield(): ?float
    {
        $currentPrice = $this->getCurrentPrice();
        if (!$currentPrice || $currentPrice <= 0) {
            return null;
        }
        
        $annualDividends = $this->dividends()
            ->where('ex_date', '>=', DateTimeHelper::now()->modify('-1 year'))
            ->where('dividend_type', 'regular')
            ->sum('amount');
            
        return $annualDividends > 0 ? ($annualDividends / $currentPrice) * 100 : 0.0;
    }
    
    public function getMarketCapFormatted(): string
    {
        if (!$this->market_cap) {
            return 'N/A';
        }
        
        $cap = $this->market_cap;
        
        if ($cap >= 1000000000000) {
            return number_format($cap / 1000000000000, 2) . 'T';
        } elseif ($cap >= 1000000000) {
            return number_format($cap / 1000000000, 2) . 'B';
        } elseif ($cap >= 1000000) {
            return number_format($cap / 1000000, 2) . 'M';
        }
        
        return number_format($cap);
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeBySector($query, string $sector)
    {
        return $query->where('sector', $sector);
    }
    
    public function scopeByExchange($query, string $exchange)
    {
        return $query->where('exchange', $exchange);
    }
    
    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('symbol', 'LIKE', "%{$term}%")
              ->orWhere('name', 'LIKE', "%{$term}%");
        });
    }
}
