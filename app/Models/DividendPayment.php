<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DividendPayment extends Model
{
    protected $table = 'dividend_payments';
    
    protected $fillable = [
        'portfolio_id',
        'dividend_id',
        'stock_symbol',
        'payment_date',
        'shares_owned',
        'dividend_per_share',
        'total_dividend_amount',
        'payment_type',
        'drip_shares_purchased',
        'drip_price_per_share',
        'notes',
        'is_confirmed'
    ];
    
    protected $casts = [
        'payment_date' => 'date',
        'shares_owned' => 'decimal:6',
        'dividend_per_share' => 'decimal:6',
        'total_dividend_amount' => 'decimal:4',
        'drip_shares_purchased' => 'decimal:6',
        'drip_price_per_share' => 'decimal:4',
        'is_confirmed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    // Relationships
    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }
    
    public function dividend(): BelongsTo
    {
        return $this->belongsTo(Dividend::class);
    }
    
    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class, 'stock_symbol', 'symbol');
    }
    
    // Helper methods
    public function isCash(): bool
    {
        return $this->payment_type === 'cash';
    }
    
    public function isDrip(): bool
    {
        return $this->payment_type === 'drip';
    }
    
    public function getFormattedAmount(): string
    {
        return '$' . number_format($this->total_dividend_amount, 2);
    }
    
    public function getFormattedDripShares(): string
    {
        return $this->drip_shares_purchased ? number_format($this->drip_shares_purchased, 6) : '0';
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
    
    public function scopeCash($query)
    {
        return $query->where('payment_type', 'cash');
    }
    
    public function scopeDrip($query)
    {
        return $query->where('payment_type', 'drip');
    }
    
    public function scopeConfirmed($query)
    {
        return $query->where('is_confirmed', true);
    }
    
    public function scopePending($query)
    {
        return $query->where('is_confirmed', false);
    }
}
