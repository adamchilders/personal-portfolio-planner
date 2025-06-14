<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\DateTimeHelper;

class ApiKey extends Model
{
    protected $table = 'api_keys';
    
    protected $fillable = [
        'provider',
        'api_key',
        'is_active',
        'rate_limit_per_minute',
        'rate_limit_per_day',
        'last_used',
        'usage_count_today',
        'usage_reset_date',
        'notes'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'rate_limit_per_minute' => 'integer',
        'rate_limit_per_day' => 'integer',
        'usage_count_today' => 'integer',
        'last_used' => 'datetime',
        'usage_reset_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    // Helper methods
    public function isActive(): bool
    {
        return $this->is_active && !empty($this->api_key);
    }
    
    public function canMakeRequest(): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        
        // Reset daily usage if needed
        $this->resetDailyUsageIfNeeded();
        
        // Check daily limit
        if ($this->rate_limit_per_day && $this->usage_count_today >= $this->rate_limit_per_day) {
            return false;
        }
        
        return true;
    }
    
    public function recordUsage(): void
    {
        $this->resetDailyUsageIfNeeded();
        
        $this->increment('usage_count_today');
        $this->update(['last_used' => DateTimeHelper::now()]);
    }
    
    public function getRemainingDailyRequests(): ?int
    {
        if (!$this->rate_limit_per_day) {
            return null; // Unlimited
        }
        
        $this->resetDailyUsageIfNeeded();
        return max(0, $this->rate_limit_per_day - $this->usage_count_today);
    }
    
    public function getUsagePercentage(): float
    {
        if (!$this->rate_limit_per_day) {
            return 0.0; // Unlimited
        }
        
        $this->resetDailyUsageIfNeeded();
        return ($this->usage_count_today / $this->rate_limit_per_day) * 100;
    }
    
    private function resetDailyUsageIfNeeded(): void
    {
        $today = DateTimeHelper::now()->format('Y-m-d');
        
        if ($this->usage_reset_date->format('Y-m-d') !== $today) {
            $this->update([
                'usage_count_today' => 0,
                'usage_reset_date' => $today
            ]);
        }
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('api_key', '!=', '');
    }
    
    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }
    
    // Static methods
    public static function getActiveKey(string $provider): ?self
    {
        return static::forProvider($provider)->active()->first();
    }
    
    public static function hasActiveKey(string $provider): bool
    {
        return static::getActiveKey($provider) !== null;
    }
}
