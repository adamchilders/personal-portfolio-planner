-- Migration: Create dividend payments tracking table
-- Created: 2025-06-14

-- Dividend payments table to track actual dividend receipts
CREATE TABLE dividend_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    dividend_id INT NOT NULL,
    stock_symbol VARCHAR(20) NOT NULL,
    payment_date DATE NOT NULL,
    shares_owned DECIMAL(15, 6) NOT NULL,
    dividend_per_share DECIMAL(10, 6) NOT NULL,
    total_dividend_amount DECIMAL(15, 4) NOT NULL,
    payment_type ENUM('cash', 'drip') NOT NULL DEFAULT 'cash',
    drip_shares_purchased DECIMAL(15, 6) NULL,
    drip_price_per_share DECIMAL(15, 4) NULL,
    notes TEXT,
    is_confirmed BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    FOREIGN KEY (dividend_id) REFERENCES dividends(id) ON DELETE CASCADE,
    FOREIGN KEY (stock_symbol) REFERENCES stocks(symbol) ON DELETE CASCADE,
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_dividend_id (dividend_id),
    INDEX idx_stock_symbol (stock_symbol),
    INDEX idx_payment_date (payment_date),
    INDEX idx_payment_type (payment_type),
    INDEX idx_confirmed (is_confirmed),
    UNIQUE KEY unique_portfolio_dividend (portfolio_id, dividend_id)
);
