<?php

declare(strict_types=1);

namespace App\Helpers;

use DateTime;
use DateTimeInterface;

class DateTimeHelper
{
    /**
     * Get current DateTime instance in UTC
     */
    public static function now(): DateTime
    {
        return new DateTime('now', new \DateTimeZone('UTC'));
    }
    
    /**
     * Create DateTime from string in UTC
     */
    public static function create(string $time = 'now'): DateTime
    {
        return new DateTime($time, new \DateTimeZone('UTC'));
    }
    
    /**
     * Format DateTime for database storage
     */
    public static function formatForDatabase(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }
    
    /**
     * Format DateTime for API responses (ISO 8601)
     */
    public static function formatForApi(DateTimeInterface $dateTime): string
    {
        return $dateTime->format('c');
    }
    
    /**
     * Create DateTime with added minutes
     */
    public static function addMinutes(int $minutes, ?DateTime $from = null): DateTime
    {
        $dateTime = $from ? clone $from : self::now();
        $dateTime->modify("+{$minutes} minutes");
        return $dateTime;
    }
    
    /**
     * Check if DateTime is in the past
     */
    public static function isPast(DateTimeInterface $dateTime): bool
    {
        return $dateTime < self::now();
    }
    
    /**
     * Check if DateTime is in the future
     */
    public static function isFuture(DateTimeInterface $dateTime): bool
    {
        return $dateTime > self::now();
    }
}
