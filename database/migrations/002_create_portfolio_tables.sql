-- Migration: Create portfolio management tables
-- Created: 2025-06-11

-- Portfolios table
CREATE TABLE portfolios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    portfolio_type ENUM('personal', 'retirement', 'trading', 'savings', 'other') NOT NULL DEFAULT 'personal',
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    is_public BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_active (is_active),
    INDEX idx_type (portfolio_type),
    UNIQUE KEY unique_user_portfolio_name (user_id, name)
);

-- Portfolio holdings table
CREATE TABLE portfolio_holdings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    stock_symbol VARCHAR(20) NOT NULL,
    quantity DECIMAL(15, 6) NOT NULL DEFAULT 0,
    avg_cost_basis DECIMAL(15, 4) NOT NULL DEFAULT 0,
    first_purchase_date DATE,
    last_transaction_date TIMESTAMP,
    notes TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_stock_symbol (stock_symbol),
    INDEX idx_active (is_active),
    UNIQUE KEY unique_portfolio_stock (portfolio_id, stock_symbol)
);

-- Transactions table
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    stock_symbol VARCHAR(20) NOT NULL,
    transaction_type ENUM('buy', 'sell', 'dividend', 'split', 'transfer_in', 'transfer_out') NOT NULL,
    quantity DECIMAL(15, 6) NOT NULL,
    price DECIMAL(15, 4) NOT NULL,
    fees DECIMAL(10, 4) NOT NULL DEFAULT 0,
    total_amount DECIMAL(15, 4) GENERATED ALWAYS AS (quantity * price + fees) STORED,
    transaction_date DATE NOT NULL,
    settlement_date DATE,
    notes TEXT,
    external_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_stock_symbol (stock_symbol),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_transaction_date (transaction_date),
    INDEX idx_external_id (external_id)
);

-- Portfolio performance snapshots table (for historical tracking)
CREATE TABLE portfolio_snapshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    portfolio_id INT NOT NULL,
    snapshot_date DATE NOT NULL,
    total_value DECIMAL(15, 4) NOT NULL,
    total_cost_basis DECIMAL(15, 4) NOT NULL,
    total_gain_loss DECIMAL(15, 4) GENERATED ALWAYS AS (total_value - total_cost_basis) STORED,
    total_gain_loss_percent DECIMAL(8, 4) GENERATED ALWAYS AS (
        CASE 
            WHEN total_cost_basis > 0 THEN ((total_value - total_cost_basis) / total_cost_basis) * 100
            ELSE 0 
        END
    ) STORED,
    holdings_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id) ON DELETE CASCADE,
    INDEX idx_portfolio_id (portfolio_id),
    INDEX idx_snapshot_date (snapshot_date),
    UNIQUE KEY unique_portfolio_snapshot_date (portfolio_id, snapshot_date)
);
