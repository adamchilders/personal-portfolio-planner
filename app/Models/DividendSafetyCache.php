<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\DateTimeHelper;
use Illuminate\Database\Eloquent\Model;
use DateTime;

class DividendSafetyCache extends Model
{
    protected $table = 'dividend_safety_cache';
    
    protected $fillable = [
        'symbol',
        'safety_score',
        'safety_grade',
        'payout_ratio_score',
        'fcf_coverage_score',
        'debt_ratio_score',
        'dividend_growth_score',
        'earnings_stability_score',
        'payout_ratio',
        'fcf_coverage',
        'debt_to_equity',
        'dividend_growth_consistency',
        'earnings_stability',
        'warnings'
    ];

    protected $casts = [
        'safety_score' => 'int',
        'payout_ratio_score' => 'int',
        'fcf_coverage_score' => 'int',
        'debt_ratio_score' => 'int',
        'dividend_growth_score' => 'int',
        'earnings_stability_score' => 'int',
        'payout_ratio' => 'float',
        'fcf_coverage' => 'float',
        'debt_to_equity' => 'float',
        'dividend_growth_consistency' => 'float',
        'earnings_stability' => 'float',
        'warnings' => 'json',
        'last_updated' => 'datetime',
        'created_at' => 'datetime'
    ];
    
    /**
     * Find cached safety data by symbol
     */
    public static function findBySymbol(string $symbol): ?self
    {
        return static::where('symbol', strtoupper($symbol))->first();
    }
    
    /**
     * Check if cached data is fresh (updated within last 24 hours)
     */
    public function isFresh(): bool
    {
        if (!$this->last_updated) {
            return false;
        }
        
        $now = DateTimeHelper::now();
        $lastUpdated = $this->last_updated;
        
        // Consider fresh if updated within last 24 hours
        $hoursSinceUpdate = ($now->getTimestamp() - $lastUpdated->getTimestamp()) / 3600;
        
        return $hoursSinceUpdate < 24;
    }
    
    /**
     * Check if data needs updating (older than 24 hours or score is 0)
     */
    public function needsUpdate(): bool
    {
        return !$this->isFresh() || $this->safety_score === 0;
    }
    
    /**
     * Update or create safety cache for a symbol
     */
    public static function updateSafetyData(string $symbol, array $safetyData): self
    {
        $symbol = strtoupper($symbol);
        
        $cacheData = [
            'symbol' => $symbol,
            'safety_score' => $safetyData['score'] ?? 0,
            'safety_grade' => $safetyData['grade'] ?? 'N/A',
            'warnings' => json_encode($safetyData['warnings'] ?? [])
        ];
        
        // Add factor scores if available
        if (isset($safetyData['factors'])) {
            $factors = $safetyData['factors'];
            $cacheData['payout_ratio_score'] = $factors['payout_ratio']['score'] ?? 0;
            $cacheData['fcf_coverage_score'] = $factors['fcf_coverage']['score'] ?? 0;
            $cacheData['debt_ratio_score'] = $factors['debt_ratio']['score'] ?? 0;
            $cacheData['dividend_growth_score'] = $factors['dividend_growth']['score'] ?? 0;
            $cacheData['earnings_stability_score'] = $factors['earnings_stability']['score'] ?? 0;
            
            // Add factor values
            $cacheData['payout_ratio'] = $factors['payout_ratio']['value'] ?? 0.0;
            $cacheData['fcf_coverage'] = $factors['fcf_coverage']['value'] ?? 0.0;
            $cacheData['debt_to_equity'] = $factors['debt_ratio']['value'] ?? 0.0;
            $cacheData['dividend_growth_consistency'] = $factors['dividend_growth']['value'] ?? 0.0;
            $cacheData['earnings_stability'] = $factors['earnings_stability']['value'] ?? 0.0;
        }
        
        // Update or create record
        $existing = static::findBySymbol($symbol);
        
        if ($existing) {
            $existing->update($cacheData);
            return $existing;
        } else {
            return static::create($cacheData);
        }
    }
    
    /**
     * Get cached safety data in the format expected by the service
     */
    public function toSafetyData(): array
    {
        return [
            'score' => $this->safety_score,
            'grade' => $this->safety_grade,
            'warnings' => $this->warnings ?? [],
            'factors' => [
                'payout_ratio' => [
                    'score' => $this->payout_ratio_score,
                    'value' => $this->payout_ratio,
                    'description' => 'Dividend Payout Ratio'
                ],
                'fcf_coverage' => [
                    'score' => $this->fcf_coverage_score,
                    'value' => $this->fcf_coverage,
                    'description' => 'Free Cash Flow Coverage'
                ],
                'debt_ratio' => [
                    'score' => $this->debt_ratio_score,
                    'value' => $this->debt_to_equity,
                    'description' => 'Debt-to-Equity Ratio'
                ],
                'dividend_growth' => [
                    'score' => $this->dividend_growth_score,
                    'value' => $this->dividend_growth_consistency,
                    'description' => 'Dividend Growth Consistency'
                ],
                'earnings_stability' => [
                    'score' => $this->earnings_stability_score,
                    'value' => $this->earnings_stability,
                    'description' => 'Earnings Stability'
                ]
            ],
            'cached_at' => $this->last_updated?->toISOString(),
            'is_cached' => true
        ];
    }
    
    /**
     * Get symbols that need safety data updates
     */
    public static function getSymbolsNeedingUpdate(array $symbols = []): array
    {
        $query = static::query();
        
        if (!empty($symbols)) {
            $query->whereIn('symbol', array_map('strtoupper', $symbols));
        }
        
        // Get records that are stale or have no score
        $staleRecords = $query->where(function($q) {
            $q->where('last_updated', '<', DateTimeHelper::now()->modify('-24 hours'))
              ->orWhere('safety_score', 0);
        })->get();
        
        $needsUpdate = [];
        foreach ($staleRecords as $record) {
            $needsUpdate[] = $record->symbol;
        }
        
        // Also include symbols that don't exist in cache yet
        if (!empty($symbols)) {
            $existingSymbols = static::whereIn('symbol', array_map('strtoupper', $symbols))
                ->pluck('symbol')
                ->toArray();
            
            $missingSymbols = array_diff(array_map('strtoupper', $symbols), $existingSymbols);
            $needsUpdate = array_merge($needsUpdate, $missingSymbols);
        }
        
        return array_unique($needsUpdate);
    }
    
    /**
     * Clean up old cache entries (older than 30 days)
     */
    public static function cleanupOldEntries(): int
    {
        $cutoffDate = DateTimeHelper::now()->modify('-30 days');
        
        return static::where('last_updated', '<', $cutoffDate)->delete();
    }
}
