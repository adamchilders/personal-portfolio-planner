-- Migration: Create system configuration and monitoring tables
-- Created: 2025-06-11

-- API keys table (encrypted storage)
CREATE TABLE api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    key_name VARCHAR(100) NOT NULL,
    encrypted_key TEXT NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    rate_limit INT,
    daily_quota INT,
    usage_count INT NOT NULL DEFAULT 0,
    last_used TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_provider (provider),
    INDEX idx_active (is_active),
    INDEX idx_last_used (last_used),
    UNIQUE KEY unique_provider_key_name (provider, key_name)
);

-- Data sources configuration table
CREATE TABLE data_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    provider VARCHAR(50) NOT NULL,
    priority INT NOT NULL DEFAULT 1,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    base_url VARCHAR(255),
    rate_limit INT,
    supports_real_time BOOLEAN NOT NULL DEFAULT FALSE,
    supports_historical BOOLEAN NOT NULL DEFAULT TRUE,
    supports_dividends BOOLEAN NOT NULL DEFAULT FALSE,
    config JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_provider (provider),
    INDEX idx_priority (priority),
    INDEX idx_active (is_active)
);

-- Data fetch schedules table
CREATE TABLE fetch_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    schedule_type ENUM('real_time', 'market_hours', 'daily', 'weekly', 'custom') NOT NULL,
    cron_expression VARCHAR(100),
    data_type ENUM('quotes', 'historical', 'dividends', 'splits', 'fundamentals') NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    last_run TIMESTAMP NULL,
    next_run TIMESTAMP NULL,
    run_count INT NOT NULL DEFAULT 0,
    failure_count INT NOT NULL DEFAULT 0,
    max_failures INT NOT NULL DEFAULT 5,
    config JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_schedule_type (schedule_type),
    INDEX idx_data_type (data_type),
    INDEX idx_active (is_active),
    INDEX idx_next_run (next_run)
);

-- System settings table
CREATE TABLE system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') NOT NULL DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_public (is_public)
);

-- API call logs table
CREATE TABLE api_calls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL DEFAULT 'GET',
    request_params JSON,
    response_time INT,
    status_code INT,
    response_size INT,
    error_message TEXT,
    symbols_requested JSON,
    symbols_returned JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_provider (provider),
    INDEX idx_endpoint (endpoint),
    INDEX idx_status_code (status_code),
    INDEX idx_created_at (created_at),
    INDEX idx_provider_created (provider, created_at)
);

-- User activity logs table
CREATE TABLE user_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50),
    resource_id INT,
    details JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created_at (created_at)
);

-- System events table
CREATE TABLE system_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    severity ENUM('info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    context JSON,
    source VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_event_type (event_type),
    INDEX idx_severity (severity),
    INDEX idx_created_at (created_at),
    INDEX idx_source (source)
);
