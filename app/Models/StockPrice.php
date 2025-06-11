<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Helpers\DateTimeHelper;

class StockPrice extends Model
{
    protected $table = 'stock_prices';
    
    protected $fillable = [
        'symbol',
        'price_date',
        'open_price',
        'high_price',
        'low_price',
        'close_price',
        'adjusted_close',
        'volume'
    ];
    
    protected $casts = [
        'price_date' => 'date',
        'open_price' => 'decimal:4',
        'high_price' => 'decimal:4',
        'low_price' => 'decimal:4',
        'close_price' => 'decimal:4',
        'adjusted_close' => 'decimal:4',
        'volume' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    // Relationships
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'symbol', 'symbol');
    }
    
    // Helper methods
    public function getDailyChange(): float
    {
        return (float)($this->close_price - $this->open_price);
    }
    
    public function getDailyChangePercent(): float
    {
        if ($this->open_price <= 0) {
            return 0.0;
        }
        
        return (($this->close_price - $this->open_price) / $this->open_price) * 100;
    }
    
    public function getDailyRange(): array
    {
        return [
            'low' => $this->low_price,
            'high' => $this->high_price,
            'range' => $this->high_price - $this->low_price
        ];
    }
    
    public function isPositiveDay(): bool
    {
        return $this->close_price > $this->open_price;
    }
    
    public function isNegativeDay(): bool
    {
        return $this->close_price < $this->open_price;
    }
    
    // Scopes
    public function scopeForSymbol($query, string $symbol)
    {
        return $query->where('symbol', $symbol);
    }
    
    public function scopeForDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('price_date', [$startDate, $endDate]);
    }
    
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('price_date', '>=', DateTimeHelper::now()->modify("-{$days} days"));
    }
    
    public function scopeOrderByDate($query, string $direction = 'desc')
    {
        return $query->orderBy('price_date', $direction);
    }
}
