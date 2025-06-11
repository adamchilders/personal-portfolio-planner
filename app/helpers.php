<?php

declare(strict_types=1);

/**
 * Helper functions for the Portfolio Tracker application
 */

if (!function_exists('env')) {
    /**
     * Get environment variable with optional default value
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Convert string representations to proper types
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $value,
        };
    }
}

if (!function_exists('config')) {
    /**
     * Get configuration value (placeholder for future config system)
     */
    function config(string $key, mixed $default = null): mixed
    {
        // For now, just use env() - later we'll implement a proper config system
        return env($key, $default);
    }
}

if (!function_exists('logger')) {
    /**
     * Get logger instance (placeholder)
     */
    function logger(): ?object
    {
        // This will be implemented when we have proper DI container access
        return null;
    }
}

if (!function_exists('formatCurrency')) {
    /**
     * Format currency value
     */
    function formatCurrency(float $amount, string $currency = 'USD'): string
    {
        return match ($currency) {
            'USD' => '$' . number_format($amount, 2),
            'EUR' => '€' . number_format($amount, 2),
            'GBP' => '£' . number_format($amount, 2),
            default => $currency . ' ' . number_format($amount, 2),
        };
    }
}

if (!function_exists('formatPercentage')) {
    /**
     * Format percentage value
     */
    function formatPercentage(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals) . '%';
    }
}

if (!function_exists('isMarketOpen')) {
    /**
     * Check if market is currently open (simplified version)
     */
    function isMarketOpen(string $timezone = 'America/New_York'): bool
    {
        $now = new DateTime('now', new DateTimeZone($timezone));
        $hour = (int) $now->format('H');
        $dayOfWeek = (int) $now->format('N'); // 1 = Monday, 7 = Sunday
        
        // Basic check: Monday-Friday, 9:30 AM - 4:00 PM EST
        return $dayOfWeek <= 5 && $hour >= 9 && $hour < 16;
    }
}

if (!function_exists('generateApiKey')) {
    /**
     * Generate a secure API key
     */
    function generateApiKey(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
}

if (!function_exists('maskApiKey')) {
    /**
     * Mask API key for display purposes
     */
    function maskApiKey(string $apiKey): string
    {
        if (strlen($apiKey) <= 8) {
            return str_repeat('*', strlen($apiKey));
        }
        
        return substr($apiKey, 0, 4) . str_repeat('*', strlen($apiKey) - 8) . substr($apiKey, -4);
    }
}
