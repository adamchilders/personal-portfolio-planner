-- Create dividend safety cache table
-- This table stores cached dividend safety analysis for each stock symbol
-- Data is shared across all portfolios and updated at most once per day

CREATE TABLE dividend_safety_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(10) NOT NULL UNIQUE,
    
    -- Safety Analysis Results
    safety_score INT NOT NULL DEFAULT 0,
    safety_grade VARCHAR(2) NOT NULL DEFAULT 'N/A',
    
    -- Individual Factor Scores (0-100)
    payout_ratio_score INT DEFAULT 0,
    fcf_coverage_score INT DEFAULT 0,
    debt_ratio_score INT DEFAULT 0,
    dividend_growth_score INT DEFAULT 0,
    earnings_stability_score INT DEFAULT 0,
    
    -- Factor Values for Display
    payout_ratio DECIMAL(5,2) DEFAULT 0.00,
    fcf_coverage DECIMAL(8,2) DEFAULT 0.00,
    debt_to_equity DECIMAL(5,2) DEFAULT 0.00,
    dividend_growth_consistency DECIMAL(5,2) DEFAULT 0.00,
    earnings_stability DECIMAL(5,2) DEFAULT 0.00,
    
    -- Warnings and Recommendations
    warnings JSON DEFAULT NULL,
    
    -- Cache Management
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Performance Indexes
    INDEX idx_symbol (symbol),
    INDEX idx_last_updated (last_updated),
    INDEX idx_safety_score (safety_score)
);

-- Insert some initial data for common dividend stocks (optional)
INSERT INTO dividend_safety_cache (symbol, safety_score, safety_grade, warnings, last_updated) VALUES
('AAPL', 0, 'N/A', '[]', '2024-01-01 00:00:00'),
('MSFT', 0, 'N/A', '[]', '2024-01-01 00:00:00'),
('JNJ', 0, 'N/A', '[]', '2024-01-01 00:00:00'),
('KO', 0, 'N/A', '[]', '2024-01-01 00:00:00'),
('PG', 0, 'N/A', '[]', '2024-01-01 00:00:00')
ON DUPLICATE KEY UPDATE symbol = VALUES(symbol);
