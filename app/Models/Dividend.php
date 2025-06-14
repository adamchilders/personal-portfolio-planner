<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Helpers\DateTimeHelper;

class Dividend extends Model
{
    protected $table = 'dividends';
    public $timestamps = true;

    public const UPDATED_AT = null; // Only use created_at, not updated_at
    
    protected $fillable = [
        'symbol',
        'ex_date',
        'payment_date',
        'record_date',
        'amount',
        'currency',
        'dividend_type'
    ];
    
    protected $casts = [
        'ex_date' => 'date',
        'payment_date' => 'date',
        'record_date' => 'date',
        'amount' => 'decimal:6',
        'created_at' => 'datetime'
    ];
    
    // Relationships
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'symbol', 'symbol');
    }
    
    // Helper methods
    public function isRegular(): bool
    {
        return $this->dividend_type === 'regular';
    }
    
    public function isSpecial(): bool
    {
        return $this->dividend_type === 'special';
    }
    
    public function isStock(): bool
    {
        return $this->dividend_type === 'stock';
    }
    
    public function getFormattedAmount(): string
    {
        return '$' . number_format($this->amount, 4);
    }
    
    public function isUpcoming(): bool
    {
        return $this->ex_date > DateTimeHelper::now()->format('Y-m-d');
    }

    public function isPaid(): bool
    {
        return $this->payment_date && $this->payment_date <= DateTimeHelper::now()->format('Y-m-d');
    }
    
    // Scopes
    public function scopeForSymbol($query, string $symbol)
    {
        return $query->where('symbol', $symbol);
    }
    
    public function scopeRegular($query)
    {
        return $query->where('dividend_type', 'regular');
    }
    
    public function scopeSpecial($query)
    {
        return $query->where('dividend_type', 'special');
    }
    
    public function scopeUpcoming($query)
    {
        return $query->where('ex_date', '>', DateTimeHelper::now()->format('Y-m-d'));
    }

    public function scopeRecent($query, int $months = 12)
    {
        return $query->where('ex_date', '>=', DateTimeHelper::now()->modify("-{$months} months"));
    }
    
    public function scopeOrderByExDate($query, string $direction = 'desc')
    {
        return $query->orderBy('ex_date', $direction);
    }
}
