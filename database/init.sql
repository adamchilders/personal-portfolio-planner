-- Portfolio Tracker Database Initialization
-- This file runs all migrations for local development

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS portfolio_tracker;
USE portfolio_tracker;

-- Source all migration files
SOURCE /docker-entrypoint-initdb.d/../migrations/001_create_users_tables.sql;
SOURCE /docker-entrypoint-initdb.d/../migrations/002_create_portfolio_tables.sql;
SOURCE /docker-entrypoint-initdb.d/../migrations/003_create_stock_data_tables.sql;
SOURCE /docker-entrypoint-initdb.d/../migrations/004_create_system_tables.sql;

-- Insert default admin user (password: admin123)
INSERT IGNORE INTO users (id, username, email, password_hash, role, created_at, updated_at) VALUES 
(1, 'admin', 'admin@portfolio-tracker.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW(), NOW());

-- Insert sample portfolio for admin user
INSERT IGNORE INTO portfolios (id, user_id, name, description, created_at, updated_at) VALUES 
(1, 1, 'My Portfolio', 'Default portfolio for testing', NOW(), NOW());

-- Insert sample API key settings
INSERT IGNORE INTO settings (key_name, key_value, description, created_at, updated_at) VALUES 
('api_key_alpha_vantage', '', 'Alpha Vantage API Key for stock data', NOW(), NOW()),
('api_key_finnhub', '', 'Finnhub API Key for stock data', NOW(), NOW()),
('api_refresh_interval', '300', 'Stock data refresh interval in seconds', NOW(), NOW());

COMMIT;
