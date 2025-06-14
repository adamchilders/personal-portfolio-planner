<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataProviderConfig extends Model
{
    protected $table = 'data_provider_config';
    
    protected $fillable = [
        'data_type',
        'primary_provider',
        'fallback_provider',
        'is_active',
        'config_options',
        'notes'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'config_options' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    // Data type constants
    public const DATA_TYPE_STOCK_QUOTES = 'stock_quotes';
    public const DATA_TYPE_HISTORICAL_PRICES = 'historical_prices';
    public const DATA_TYPE_DIVIDEND_DATA = 'dividend_data';
    public const DATA_TYPE_COMPANY_PROFILES = 'company_profiles';
    public const DATA_TYPE_FINANCIAL_STATEMENTS = 'financial_statements';
    public const DATA_TYPE_ANALYST_ESTIMATES = 'analyst_estimates';
    public const DATA_TYPE_INSIDER_TRADING = 'insider_trading';
    public const DATA_TYPE_INSTITUTIONAL_HOLDINGS = 'institutional_holdings';
    
    // Provider constants
    public const PROVIDER_YAHOO_FINANCE = 'yahoo_finance';
    public const PROVIDER_FINANCIAL_MODELING_PREP = 'financial_modeling_prep';
    
    /**
     * Get the primary provider for a data type
     */
    public static function getPrimaryProvider(string $dataType): ?string
    {
        $config = static::where('data_type', $dataType)
                       ->where('is_active', true)
                       ->first();
        
        return $config?->primary_provider;
    }
    
    /**
     * Get the fallback provider for a data type
     */
    public static function getFallbackProvider(string $dataType): ?string
    {
        $config = static::where('data_type', $dataType)
                       ->where('is_active', true)
                       ->first();
        
        return $config?->fallback_provider;
    }
    
    /**
     * Get complete configuration for a data type
     */
    public static function getConfig(string $dataType): ?array
    {
        $config = static::where('data_type', $dataType)
                       ->where('is_active', true)
                       ->first();
        
        if (!$config) {
            return null;
        }
        
        return [
            'data_type' => $config->data_type,
            'primary_provider' => $config->primary_provider,
            'fallback_provider' => $config->fallback_provider,
            'config_options' => $config->config_options ?? [],
            'notes' => $config->notes
        ];
    }
    
    /**
     * Update provider configuration
     */
    public static function updateConfig(string $dataType, array $data): bool
    {
        try {
            static::updateOrCreate(
                ['data_type' => $dataType],
                array_intersect_key($data, array_flip([
                    'primary_provider',
                    'fallback_provider',
                    'is_active',
                    'config_options',
                    'notes'
                ]))
            );
            
            return true;
        } catch (\Exception $e) {
            error_log("Error updating data provider config for {$dataType}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all available data types
     */
    public static function getAvailableDataTypes(): array
    {
        return [
            self::DATA_TYPE_STOCK_QUOTES => 'Stock Quotes',
            self::DATA_TYPE_HISTORICAL_PRICES => 'Historical Prices',
            self::DATA_TYPE_DIVIDEND_DATA => 'Dividend Data',
            self::DATA_TYPE_COMPANY_PROFILES => 'Company Profiles',
            self::DATA_TYPE_FINANCIAL_STATEMENTS => 'Financial Statements',
            self::DATA_TYPE_ANALYST_ESTIMATES => 'Analyst Estimates',
            self::DATA_TYPE_INSIDER_TRADING => 'Insider Trading',
            self::DATA_TYPE_INSTITUTIONAL_HOLDINGS => 'Institutional Holdings'
        ];
    }
    
    /**
     * Get all available providers
     */
    public static function getAvailableProviders(): array
    {
        return [
            self::PROVIDER_YAHOO_FINANCE => 'Yahoo Finance (Free)',
            self::PROVIDER_FINANCIAL_MODELING_PREP => 'Financial Modeling Prep (Premium)'
        ];
    }
    
    /**
     * Check if a provider is available for a data type
     */
    public static function isProviderAvailable(string $provider, string $dataType): bool
    {
        // Check if API key exists and is active
        $apiKey = ApiKey::getActiveKey($provider);
        if (!$apiKey || !$apiKey->canMakeRequest()) {
            return false;
        }
        
        // Check provider capabilities
        $capabilities = static::getProviderCapabilities();
        return in_array($dataType, $capabilities[$provider] ?? []);
    }
    
    /**
     * Get provider capabilities
     */
    public static function getProviderCapabilities(): array
    {
        return [
            self::PROVIDER_YAHOO_FINANCE => [
                self::DATA_TYPE_STOCK_QUOTES,
                self::DATA_TYPE_HISTORICAL_PRICES,
                self::DATA_TYPE_DIVIDEND_DATA,
                self::DATA_TYPE_COMPANY_PROFILES
            ],
            self::PROVIDER_FINANCIAL_MODELING_PREP => [
                self::DATA_TYPE_STOCK_QUOTES,
                self::DATA_TYPE_HISTORICAL_PRICES,
                self::DATA_TYPE_DIVIDEND_DATA,
                self::DATA_TYPE_COMPANY_PROFILES,
                self::DATA_TYPE_FINANCIAL_STATEMENTS,
                self::DATA_TYPE_ANALYST_ESTIMATES,
                self::DATA_TYPE_INSIDER_TRADING,
                self::DATA_TYPE_INSTITUTIONAL_HOLDINGS
            ]
        ];
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeForDataType($query, string $dataType)
    {
        return $query->where('data_type', $dataType);
    }
}
