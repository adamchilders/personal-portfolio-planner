-- Initial database setup for Portfolio Tracker
-- This file is executed when the MySQL container starts for the first time

-- Ensure the database exists
CREATE DATABASE IF NOT EXISTS portfolio_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create the application user if it doesn't exist
CREATE USER IF NOT EXISTS 'portfolio_user'@'%' IDENTIFIED BY 'secure_password_change_me';

-- Grant privileges to the application user
GRANT ALL PRIVILEGES ON portfolio_tracker.* TO 'portfolio_user'@'%';

-- Flush privileges to ensure they take effect
FLUSH PRIVILEGES;

-- Use the portfolio_tracker database
USE portfolio_tracker;

-- Create a simple health check table
CREATE TABLE IF NOT EXISTS health_check (
    id INT AUTO_INCREMENT PRIMARY KEY,
    status VARCHAR(50) NOT NULL DEFAULT 'healthy',
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert initial health check record
INSERT INTO health_check (status) VALUES ('initialized') ON DUPLICATE KEY UPDATE status = 'initialized';

-- Create system_info table for tracking database version and migrations
CREATE TABLE IF NOT EXISTS system_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) NOT NULL UNIQUE,
    value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert initial system information
INSERT INTO system_info (key_name, value) VALUES 
    ('database_version', '1.0.0'),
    ('installation_date', NOW()),
    ('last_migration', '001_initial_setup')
ON DUPLICATE KEY UPDATE 
    value = VALUES(value),
    updated_at = CURRENT_TIMESTAMP;
