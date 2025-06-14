-- Migration: Create data provider configuration table
-- Created: 2025-06-14

-- Data provider configuration table
CREATE TABLE data_provider_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data_type VARCHAR(50) NOT NULL UNIQUE,
    primary_provider VARCHAR(50) NOT NULL,
    fallback_provider VARCHAR(50) DEFAULT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    config_options JSON DEFAULT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_data_type (data_type),
    INDEX idx_primary_provider (primary_provider),
    INDEX idx_active (is_active)
);

-- Insert default configuration
INSERT INTO data_provider_config (data_type, primary_provider, fallback_provider, notes) VALUES
('stock_quotes', 'yahoo_finance', 'financial_modeling_prep', 'Real-time stock price quotes'),
('historical_prices', 'yahoo_finance', 'financial_modeling_prep', 'Historical stock price data'),
('dividend_data', 'yahoo_finance', 'financial_modeling_prep', 'Dividend payment information'),
('company_profiles', 'yahoo_finance', 'financial_modeling_prep', 'Company information and profiles'),
('financial_statements', 'financial_modeling_prep', NULL, 'Income statements, balance sheets, cash flow'),
('analyst_estimates', 'financial_modeling_prep', NULL, 'Analyst price targets and estimates'),
('insider_trading', 'financial_modeling_prep', NULL, 'Insider trading data'),
('institutional_holdings', 'financial_modeling_prep', NULL, 'Institutional ownership data');
