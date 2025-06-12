<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Helpers\DateTimeHelper;

class StockQuote extends Model
{
    protected $table = 'stock_quotes';
    protected $primaryKey = 'symbol';
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'symbol',
        'current_price',
        'change_amount',
        'change_percent',
        'volume',
        'market_cap',
        'pe_ratio',
        'dividend_yield',
        'fifty_two_week_high',
        'fifty_two_week_low',
        'quote_time',
        'market_state'
    ];
    
    protected $casts = [
        'current_price' => 'decimal:4',
        'change_amount' => 'decimal:4',
        'change_percent' => 'decimal:4',
        'volume' => 'integer',
        'market_cap' => 'integer',
        'pe_ratio' => 'decimal:2',
        'dividend_yield' => 'decimal:4',
        'fifty_two_week_high' => 'decimal:4',
        'fifty_two_week_low' => 'decimal:4',
        'quote_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    // Relationships
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'symbol', 'symbol');
    }
    
    // Helper methods
    public function isPositiveChange(): bool
    {
        return $this->change_amount > 0;
    }
    
    public function isNegativeChange(): bool
    {
        return $this->change_amount < 0;
    }
    
    public function getChangeDirection(): string
    {
        if ($this->change_amount > 0) {
            return 'up';
        } elseif ($this->change_amount < 0) {
            return 'down';
        }
        return 'neutral';
    }
    
    public function getFormattedPrice(): string
    {
        return '$' . number_format((float)$this->current_price, 2);
    }

    public function getFormattedChange(): string
    {
        $sign = $this->change_amount >= 0 ? '+' : '';
        return $sign . '$' . number_format((float)$this->change_amount, 2);
    }

    public function getFormattedChangePercent(): string
    {
        $sign = $this->change_percent >= 0 ? '+' : '';
        return $sign . number_format((float)$this->change_percent, 2) . '%';
    }
    
    public function getFormattedVolume(): string
    {
        $volume = (int)$this->volume;
        if ($volume >= 1000000) {
            return number_format($volume / 1000000, 1) . 'M';
        } elseif ($volume >= 1000) {
            return number_format($volume / 1000, 1) . 'K';
        }

        return number_format($volume);
    }
    
    public function isMarketOpen(): bool
    {
        return $this->market_state === 'REGULAR';
    }
    
    public function isPreMarket(): bool
    {
        return $this->market_state === 'PRE';
    }
    
    public function isAfterHours(): bool
    {
        return $this->market_state === 'POST';
    }
    
    public function isMarketClosed(): bool
    {
        return $this->market_state === 'CLOSED';
    }
    
    public function getMarketStateLabel(): string
    {
        return match($this->market_state) {
            'PRE' => 'Pre-Market',
            'REGULAR' => 'Market Open',
            'POST' => 'After Hours',
            'CLOSED' => 'Market Closed',
            default => 'Unknown'
        };
    }
    
    public function isStale(int $minutes = 15): bool
    {
        return $this->quote_time->diffInMinutes(DateTimeHelper::now()) > $minutes;
    }

    // Scopes
    public function scopeRecent($query, int $minutes = 15)
    {
        return $query->where('quote_time', '>=', DateTimeHelper::now()->modify("-{$minutes} minutes"));
    }

    public function scopeStale($query, int $minutes = 15)
    {
        return $query->where('quote_time', '<', DateTimeHelper::now()->modify("-{$minutes} minutes"));
    }
    
    public function scopeByMarketState($query, string $state)
    {
        return $query->where('market_state', $state);
    }
}
