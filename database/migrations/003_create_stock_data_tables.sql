-- Migration: Create stock data and market information tables
-- Created: 2025-06-11

-- Stocks master table
CREATE TABLE stocks (
    symbol VARCHAR(20) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    exchange VARCHAR(50),
    sector VARCHAR(100),
    industry VARCHAR(100),
    market_cap BIGINT,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    country VARCHAR(2) NOT NULL DEFAULT 'US',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_exchange (exchange),
    INDEX idx_sector (sector),
    INDEX idx_industry (industry),
    INDEX idx_active (is_active),
    INDEX idx_last_updated (last_updated)
);

-- Stock prices table (daily OHLCV data)
CREATE TABLE stock_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(20) NOT NULL,
    price_date DATE NOT NULL,
    open_price DECIMAL(15, 4),
    high_price DECIMAL(15, 4),
    low_price DECIMAL(15, 4),
    close_price DECIMAL(15, 4) NOT NULL,
    adjusted_close DECIMAL(15, 4),
    volume BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (symbol) REFERENCES stocks(symbol) ON DELETE CASCADE,
    INDEX idx_symbol (symbol),
    INDEX idx_price_date (price_date),
    INDEX idx_symbol_date (symbol, price_date),
    UNIQUE KEY unique_symbol_date (symbol, price_date)
);

-- Real-time stock quotes table (latest prices)
CREATE TABLE stock_quotes (
    symbol VARCHAR(20) PRIMARY KEY,
    current_price DECIMAL(15, 4) NOT NULL,
    change_amount DECIMAL(15, 4),
    change_percent DECIMAL(8, 4),
    volume BIGINT,
    market_cap BIGINT,
    pe_ratio DECIMAL(8, 2),
    dividend_yield DECIMAL(8, 4),
    fifty_two_week_high DECIMAL(15, 4),
    fifty_two_week_low DECIMAL(15, 4),
    quote_time TIMESTAMP NOT NULL,
    market_state ENUM('PRE', 'REGULAR', 'POST', 'CLOSED') NOT NULL DEFAULT 'CLOSED',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (symbol) REFERENCES stocks(symbol) ON DELETE CASCADE,
    INDEX idx_quote_time (quote_time),
    INDEX idx_market_state (market_state),
    INDEX idx_updated_at (updated_at)
);

-- Dividends table
CREATE TABLE dividends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(20) NOT NULL,
    ex_date DATE NOT NULL,
    payment_date DATE,
    record_date DATE,
    amount DECIMAL(10, 6) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    dividend_type ENUM('regular', 'special', 'stock') NOT NULL DEFAULT 'regular',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (symbol) REFERENCES stocks(symbol) ON DELETE CASCADE,
    INDEX idx_symbol (symbol),
    INDEX idx_ex_date (ex_date),
    INDEX idx_payment_date (payment_date),
    UNIQUE KEY unique_symbol_ex_date (symbol, ex_date)
);

-- Stock splits table
CREATE TABLE stock_splits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(20) NOT NULL,
    split_date DATE NOT NULL,
    split_ratio VARCHAR(20) NOT NULL,
    split_factor DECIMAL(10, 6) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (symbol) REFERENCES stocks(symbol) ON DELETE CASCADE,
    INDEX idx_symbol (symbol),
    INDEX idx_split_date (split_date),
    UNIQUE KEY unique_symbol_split_date (symbol, split_date)
);
