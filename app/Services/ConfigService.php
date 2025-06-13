<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\DateTimeHelper;

class ConfigService
{
    /**
     * Get historical data configuration
     */
    public static function getHistoricalDataDays(): int
    {
        return (int)($_ENV['HISTORICAL_DATA_DAYS'] ?? 365);
    }
    
    /**
     * Get the time when historical data should be updated (after market close)
     */
    public static function getHistoricalUpdateTime(): string
    {
        return $_ENV['HISTORICAL_UPDATE_TIME'] ?? '16:05';
    }
    
    /**
     * Get market close time
     */
    public static function getMarketCloseTime(): string
    {
        return $_ENV['MARKET_CLOSE_TIME'] ?? '16:00';
    }
    
    /**
     * Get market timezone
     */
    public static function getMarketTimezone(): string
    {
        return $_ENV['MARKET_TIMEZONE'] ?? 'America/New_York';
    }
    
    /**
     * Get quote cache timeout for market hours (minutes)
     */
    public static function getQuoteCacheMarketHours(): int
    {
        return (int)($_ENV['QUOTE_CACHE_MARKET_HOURS'] ?? 15);
    }
    
    /**
     * Get quote cache timeout for after hours (minutes)
     */
    public static function getQuoteCacheAfterHours(): int
    {
        return (int)($_ENV['QUOTE_CACHE_AFTER_HOURS'] ?? 30);
    }
    
    /**
     * Check if current time is during market hours (9:30 AM - 4:00 PM ET)
     */
    public static function isMarketHours(): bool
    {
        $now = DateTimeHelper::now();
        $marketTimezone = new \DateTimeZone(self::getMarketTimezone());
        $marketTime = $now->setTimezone($marketTimezone);
        
        $dayOfWeek = (int)$marketTime->format('N'); // 1 = Monday, 7 = Sunday
        
        // Skip weekends
        if ($dayOfWeek >= 6) {
            return false;
        }
        
        $hour = (int)$marketTime->format('H');
        $minute = (int)$marketTime->format('i');
        $timeInMinutes = $hour * 60 + $minute;
        
        $marketStart = 9 * 60 + 30; // 9:30 AM
        $marketEnd = 16 * 60; // 4:00 PM
        
        return $timeInMinutes >= $marketStart && $timeInMinutes <= $marketEnd;
    }
    
    /**
     * Check if it's time to update historical data (a few minutes after market close)
     */
    public static function isHistoricalUpdateTime(): bool
    {
        $now = DateTimeHelper::now();
        $marketTimezone = new \DateTimeZone(self::getMarketTimezone());
        $marketTime = $now->setTimezone($marketTimezone);
        
        $dayOfWeek = (int)$marketTime->format('N'); // 1 = Monday, 7 = Sunday
        
        // Only update on weekdays
        if ($dayOfWeek >= 6) {
            return false;
        }
        
        $currentTime = $marketTime->format('H:i');
        $updateTime = self::getHistoricalUpdateTime();
        
        return $currentTime === $updateTime;
    }
    
    /**
     * Get the appropriate quote cache timeout based on market hours
     */
    public static function getQuoteCacheTimeout(): int
    {
        if (self::isMarketHours()) {
            return self::getQuoteCacheMarketHours();
        } else {
            return self::getQuoteCacheAfterHours();
        }
    }
    
    /**
     * Get all configuration values as an array
     */
    public static function getAllConfig(): array
    {
        return [
            'historical_data' => [
                'days' => self::getHistoricalDataDays(),
                'update_time' => self::getHistoricalUpdateTime(),
                'market_close_time' => self::getMarketCloseTime(),
                'market_timezone' => self::getMarketTimezone()
            ],
            'quote_cache' => [
                'market_hours_minutes' => self::getQuoteCacheMarketHours(),
                'after_hours_minutes' => self::getQuoteCacheAfterHours(),
                'current_timeout' => self::getQuoteCacheTimeout()
            ],
            'market_status' => [
                'is_market_hours' => self::isMarketHours(),
                'is_historical_update_time' => self::isHistoricalUpdateTime(),
                'current_market_time' => DateTimeHelper::now()
                    ->setTimezone(new \DateTimeZone(self::getMarketTimezone()))
                    ->format('Y-m-d H:i:s T')
            ]
        ];
    }
    
    /**
     * Validate configuration values
     */
    public static function validateConfig(): array
    {
        $errors = [];
        
        // Validate historical data days
        $days = self::getHistoricalDataDays();
        if ($days < 1 || $days > 3650) { // Max 10 years
            $errors[] = "HISTORICAL_DATA_DAYS must be between 1 and 3650, got: {$days}";
        }
        
        // Validate time formats
        $updateTime = self::getHistoricalUpdateTime();
        if (!preg_match('/^\d{2}:\d{2}$/', $updateTime)) {
            $errors[] = "HISTORICAL_UPDATE_TIME must be in HH:MM format, got: {$updateTime}";
        }
        
        $closeTime = self::getMarketCloseTime();
        if (!preg_match('/^\d{2}:\d{2}$/', $closeTime)) {
            $errors[] = "MARKET_CLOSE_TIME must be in HH:MM format, got: {$closeTime}";
        }
        
        // Validate timezone
        $timezone = self::getMarketTimezone();
        try {
            new \DateTimeZone($timezone);
        } catch (\Exception $e) {
            $errors[] = "Invalid MARKET_TIMEZONE: {$timezone}";
        }
        
        // Validate cache timeouts
        $marketHours = self::getQuoteCacheMarketHours();
        if ($marketHours < 1 || $marketHours > 60) {
            $errors[] = "QUOTE_CACHE_MARKET_HOURS must be between 1 and 60, got: {$marketHours}";
        }
        
        $afterHours = self::getQuoteCacheAfterHours();
        if ($afterHours < 1 || $afterHours > 120) {
            $errors[] = "QUOTE_CACHE_AFTER_HOURS must be between 1 and 120, got: {$afterHours}";
        }
        
        return $errors;
    }
}
