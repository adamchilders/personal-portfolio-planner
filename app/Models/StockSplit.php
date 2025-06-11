<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Helpers\DateTimeHelper;

class StockSplit extends Model
{
    protected $table = 'stock_splits';
    public $timestamps = false;
    
    protected $fillable = [
        'symbol',
        'split_date',
        'split_ratio',
        'split_factor'
    ];
    
    protected $casts = [
        'split_date' => 'date',
        'split_factor' => 'decimal:6',
        'created_at' => 'datetime'
    ];
    
    // Relationships
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'symbol', 'symbol');
    }
    
    // Helper methods
    public function isForwardSplit(): bool
    {
        return $this->split_factor > 1.0;
    }
    
    public function isReverseSplit(): bool
    {
        return $this->split_factor < 1.0;
    }
    
    public function getFormattedRatio(): string
    {
        if ($this->split_factor > 1.0) {
            return $this->split_ratio . ' (Forward Split)';
        } elseif ($this->split_factor < 1.0) {
            return $this->split_ratio . ' (Reverse Split)';
        }
        
        return $this->split_ratio;
    }
    
    public function adjustQuantity(float $quantity): float
    {
        return $quantity * $this->split_factor;
    }
    
    public function adjustPrice(float $price): float
    {
        return $price / $this->split_factor;
    }
    
    // Scopes
    public function scopeForSymbol($query, string $symbol)
    {
        return $query->where('symbol', $symbol);
    }
    
    public function scopeForward($query)
    {
        return $query->where('split_factor', '>', 1.0);
    }
    
    public function scopeReverse($query)
    {
        return $query->where('split_factor', '<', 1.0);
    }
    
    public function scopeRecent($query, int $years = 5)
    {
        return $query->where('split_date', '>=', DateTimeHelper::now()->modify("-{$years} years"));
    }
    
    public function scopeOrderByDate($query, string $direction = 'desc')
    {
        return $query->orderBy('split_date', $direction);
    }
}
