-- Migration: Create API keys management table
-- Created: 2025-06-14

-- API keys table for managing external data provider credentials
CREATE TABLE api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL UNIQUE,
    api_key VARCHAR(255) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    rate_limit_per_minute INT DEFAULT NULL,
    rate_limit_per_day INT DEFAULT NULL,
    last_used TIMESTAMP NULL,
    usage_count_today INT NOT NULL DEFAULT 0,
    usage_reset_date DATE NOT NULL DEFAULT (CURDATE()),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_provider (provider),
    INDEX idx_active (is_active),
    INDEX idx_last_used (last_used)
);

-- Insert default providers
INSERT INTO api_keys (provider, api_key, is_active, rate_limit_per_minute, rate_limit_per_day, notes) VALUES
('yahoo_finance', 'free', TRUE, 60, 2000, 'Free Yahoo Finance API - no key required'),
('financial_modeling_prep', '', FALSE, 300, 10000, 'Financial Modeling Prep API - requires paid subscription');
