# Personal Portfolio Tracker - Project Plan

## Project Overview
A comprehensive stock portfolio tracking application built with PHP 8.4, designed to monitor multiple personal portfolios with real-time stock data from various APIs (Yahoo Finance, Polygon.io, etc.). The application will be containerized with Docker for easy deployment on home server platforms.

## Core Features & Requirements

### 1. User Management & Authentication
- **Multi-user support** with username/password authentication
- **Welcome screen** for first-time setup when no users exist
- **User roles**: Admin (can manage API keys, schedules) and Regular users
- **Session management** with secure login/logout
- **Password reset functionality**

### 2. Portfolio Management
- **Multiple portfolios per user** (Personal, Retirement, Trading, etc.)
- **Portfolio CRUD operations** (Create, Read, Update, Delete)
- **Stock holdings tracking** with purchase date, quantity, cost basis
- **Portfolio performance metrics** (total value, gains/losses, percentage returns)
- **Portfolio comparison** and analytics

### 3. Stock Data Management
- **Smart API usage**: Only fetch data for stocks currently held in portfolios
- **Deduplication**: Single data pull for stocks across multiple portfolios
- **Multi-source data**: Yahoo Finance (free), Polygon.io (paid), Alpha Vantage (paid)
- **Data types**: Real-time prices, historical data, dividends, stock splits
- **Caching strategy**: Database storage with configurable refresh intervals

### 4. Background Data Processing
- **Scheduled data fetching** via cron jobs or background workers
- **Admin-configurable schedules** (every 15 minutes during market hours, daily after close)
- **API rate limiting** and error handling
- **Data validation** and integrity checks
- **Retry mechanisms** for failed API calls

### 5. Database Design
- **User management**: users, user_sessions, user_preferences
- **Portfolio data**: portfolios, portfolio_holdings, transactions
- **Stock data**: stocks, stock_prices, dividends, stock_splits
- **System config**: api_keys, data_sources, schedules, system_settings
- **Audit logs**: user_activity, api_calls, system_events

### 6. API Integration
- **Yahoo Finance API** (free tier)
- **Polygon.io** (paid tier with better data quality)
- **Alpha Vantage** (backup/alternative paid source)
- **Configurable API priorities** (fallback chain)
- **API key management** stored securely in database
- **Rate limiting** and quota management

### 7. Admin Interface
- **API key management** (add/edit/delete API credentials)
- **Data fetch scheduling** configuration
- **User management** (view users, reset passwords)
- **System monitoring** (API usage, error logs, performance metrics)
- **Database maintenance** tools

### 8. User Interface
- **Dashboard**: Portfolio overview, recent performance, alerts
- **Portfolio views**: Detailed holdings, performance charts, transaction history
- **Stock research**: Individual stock performance, news, fundamentals
- **Reports**: Gains/losses, dividend tracking, tax reporting
- **Mobile-responsive design**

## Technical Architecture

### Technology Stack
- **Backend**: PHP 8.4 with modern features (attributes, enums, readonly properties)
- **Framework**: Slim Framework 4 or Laravel 10 for API and web routes
- **Database**: MySQL 8.0 or PostgreSQL 15
- **Caching**: Redis for session storage and API response caching
- **Queue System**: Redis-based job queue for background processing
- **Frontend**: Modern PHP templating (Twig) with Alpine.js for interactivity
- **CSS Framework**: Tailwind CSS for responsive design

### Docker Architecture
```
├── docker-compose.yml
├── Dockerfile (PHP 8.4-fpm)
├── nginx/
│   └── default.conf
├── mysql/
│   └── init.sql
└── redis/
    └── redis.conf
```

### Directory Structure
```
├── app/
│   ├── Controllers/
│   ├── Models/
│   ├── Services/
│   ├── Middleware/
│   └── Jobs/
├── config/
├── database/
│   ├── migrations/
│   └── seeds/
├── public/
├── resources/
│   ├── views/
│   ├── css/
│   └── js/
├── storage/
│   └── logs/
└── tests/
```

## Development Phases

### Phase 1: Foundation (Weeks 1-2)
- [ ] Docker environment setup
- [ ] Database schema design and migrations
- [ ] Basic authentication system
- [ ] User management (registration, login, profile)
- [ ] Welcome screen for first-time setup

### Phase 2: Core Portfolio Features (Weeks 3-4)
- [ ] Portfolio CRUD operations
- [ ] Stock holdings management
- [ ] Transaction recording (buy/sell)
- [ ] Basic portfolio dashboard

### Phase 3: API Integration (Weeks 5-6)
- [ ] Yahoo Finance API integration
- [ ] Polygon.io API integration
- [ ] API key management system
- [ ] Background job system for data fetching
- [ ] Data caching and storage

### Phase 4: Advanced Features (Weeks 7-8)
- [ ] Admin interface for system management
- [ ] Configurable data fetch scheduling
- [ ] Performance analytics and reporting
- [ ] Charts and visualizations
- [ ] Mobile responsiveness

### Phase 5: Production Ready (Weeks 9-10)
- [ ] Comprehensive testing suite
- [ ] Error handling and logging
- [ ] Database upgrade system
- [ ] Documentation and deployment guides
- [ ] Security hardening

## Additional Features (Nice-to-Have)

### Enhanced Analytics
- **Risk analysis**: Portfolio diversification, beta calculations
- **Benchmark comparison**: S&P 500, sector indices
- **Tax optimization**: Tax-loss harvesting suggestions
- **Rebalancing alerts**: When allocations drift from targets

### Notifications & Alerts
- **Price alerts**: When stocks hit target prices
- **Dividend notifications**: Upcoming ex-dividend dates
- **Portfolio alerts**: Significant gains/losses
- **Email/SMS integration** for important notifications

### Import/Export Features
- **Brokerage integration**: Import from CSV exports
- **Tax reporting**: Generate tax documents
- **Data backup**: Export portfolio data
- **Third-party sync**: Integration with popular portfolio trackers

### Advanced UI Features
- **Dark mode** toggle
- **Customizable dashboard** widgets
- **Advanced charting** with technical indicators
- **News integration** for held stocks
- **Watchlist** for stocks not yet purchased

## Security Considerations
- **Input validation** and sanitization
- **SQL injection** prevention
- **XSS protection** with proper output encoding
- **CSRF tokens** for form submissions
- **Rate limiting** on API endpoints
- **Secure session management**
- **Environment variable** protection
- **Database encryption** for sensitive data

## Deployment Strategy
- **Docker Compose** for local development
- **Production deployment** guides for Proxmox/TrueNAS
- **Automated backups** configuration
- **SSL/TLS** setup instructions
- **Reverse proxy** configuration (Nginx Proxy Manager)
- **Health checks** and monitoring

## Database Upgrade System
- **Version tracking** in database
- **Migration scripts** for schema changes
- **Data migration** tools for major updates
- **Rollback capabilities** for failed upgrades
- **Backup verification** before upgrades

## Detailed Database Schema

### Core Tables
```sql
-- Users and Authentication
users (id, username, email, password_hash, role, created_at, updated_at, last_login)
user_sessions (id, user_id, session_token, expires_at, ip_address, user_agent)
user_preferences (user_id, theme, timezone, currency, notifications_enabled)

-- Portfolio Management
portfolios (id, user_id, name, description, created_at, updated_at, is_active)
portfolio_holdings (id, portfolio_id, stock_symbol, quantity, avg_cost_basis, first_purchase_date)
transactions (id, portfolio_id, stock_symbol, type, quantity, price, fees, transaction_date, notes)

-- Stock Data
stocks (symbol, name, exchange, sector, industry, market_cap, last_updated)
stock_prices (symbol, date, open, high, low, close, volume, adjusted_close)
dividends (symbol, ex_date, payment_date, amount, currency)
stock_splits (symbol, date, ratio, created_at)

-- System Configuration
api_keys (id, provider, key_name, encrypted_key, is_active, rate_limit, created_at)
data_sources (id, name, provider, priority, is_active, base_url, rate_limit)
fetch_schedules (id, name, cron_expression, data_type, is_active, last_run, next_run)
system_settings (key, value, description, updated_at)

-- Logging and Monitoring
api_calls (id, provider, endpoint, response_time, status_code, error_message, created_at)
user_activity (id, user_id, action, resource_type, resource_id, ip_address, created_at)
system_events (id, event_type, severity, message, context, created_at)
```

### API Integration Specifications

#### Yahoo Finance Integration
- **Endpoint**: `https://query1.finance.yahoo.com/v8/finance/chart/{symbol}`
- **Rate Limit**: 2000 requests/hour (free tier)
- **Data Available**: Real-time quotes, historical prices, basic fundamentals
- **Update Frequency**: Every 15 minutes during market hours

#### Polygon.io Integration
- **Endpoint**: `https://api.polygon.io/v2/aggs/ticker/{symbol}/range/1/day/{from}/{to}`
- **Rate Limit**: 5 calls/minute (free), 1000/minute (paid)
- **Data Available**: Real-time data, historical OHLC, dividends, splits
- **Update Frequency**: Real-time for paid tier, 15-minute delay for free

#### Alpha Vantage Integration
- **Endpoint**: `https://www.alphavantage.co/query?function=TIME_SERIES_DAILY&symbol={symbol}`
- **Rate Limit**: 5 calls/minute, 500/day (free)
- **Data Available**: Historical data, fundamentals, technical indicators
- **Use Case**: Backup data source and fundamental analysis

## Implementation Guidelines

### Code Organization Principles
- **Single Responsibility**: Each class has one clear purpose
- **Dependency Injection**: Use containers for better testability
- **Interface Segregation**: Define contracts for external services
- **Repository Pattern**: Abstract database operations
- **Service Layer**: Business logic separated from controllers

### Error Handling Strategy
- **Graceful Degradation**: App continues working if one API fails
- **Retry Logic**: Exponential backoff for failed API calls
- **Circuit Breaker**: Temporarily disable failing data sources
- **User Notifications**: Inform users of data delays or issues
- **Comprehensive Logging**: Track all errors for debugging

### Performance Optimization
- **Database Indexing**: Optimize queries for portfolio views
- **API Response Caching**: Cache stock data based on market hours
- **Lazy Loading**: Load portfolio data on demand
- **Background Processing**: Move heavy operations to job queues
- **CDN Integration**: Serve static assets efficiently

### Testing Strategy
- **Unit Tests**: Test individual components and services
- **Integration Tests**: Test API integrations and database operations
- **Feature Tests**: Test complete user workflows
- **Performance Tests**: Ensure app handles multiple portfolios efficiently
- **Security Tests**: Validate authentication and authorization

## Docker Configuration Details

### docker-compose.yml Structure
```yaml
version: '3.8'
services:
  app:
    build: .
    volumes:
      - ./:/var/www/html
      - ./storage/logs:/var/www/html/storage/logs
    environment:
      - DB_HOST=mysql
      - REDIS_HOST=redis
    depends_on:
      - mysql
      - redis

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/default.conf:/etc/nginx/conf.d/default.conf
      - ./public:/var/www/html/public

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_DATABASE}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./database/init.sql:/docker-entrypoint-initdb.d/init.sql

  redis:
    image: redis:alpine
    volumes:
      - redis_data:/data

  scheduler:
    build: .
    command: php artisan schedule:work
    depends_on:
      - mysql
      - redis
```

### Deployment Checklist
- [ ] **Environment Variables**: Set up .env file with secure values
- [ ] **SSL Certificates**: Configure HTTPS with Let's Encrypt or custom certs
- [ ] **Firewall Rules**: Restrict access to necessary ports only
- [ ] **Database Backups**: Set up automated daily backups
- [ ] **Log Rotation**: Configure log file management
- [ ] **Health Monitoring**: Set up uptime monitoring
- [ ] **Resource Limits**: Configure Docker memory and CPU limits

## Monitoring & Maintenance

### Health Checks
- **Database Connectivity**: Verify MySQL/PostgreSQL connection
- **Redis Availability**: Check cache and session storage
- **API Endpoints**: Monitor external API response times
- **Disk Space**: Alert when storage reaches 80% capacity
- **Memory Usage**: Monitor PHP memory consumption

### Backup Strategy
- **Database Dumps**: Daily automated backups with 30-day retention
- **Configuration Backup**: Weekly backup of Docker configs and .env files
- **User Data Export**: Monthly full data exports for disaster recovery
- **Backup Verification**: Automated restore testing monthly

### Update Procedures
1. **Pre-update Backup**: Full system backup before any changes
2. **Database Migration**: Run migration scripts safely
3. **Configuration Updates**: Update Docker images and configs
4. **Testing**: Verify all functionality post-update
5. **Rollback Plan**: Quick rollback procedure if issues arise

### Security Maintenance
- **Regular Updates**: Keep PHP, MySQL, and dependencies updated
- **Security Scanning**: Monthly vulnerability assessments
- **Access Auditing**: Review user access and permissions quarterly
- **API Key Rotation**: Rotate external API keys annually
- **SSL Certificate Renewal**: Automated certificate renewal

## Future Enhancement Roadmap

### Version 2.0 Features
- **Mobile App**: React Native companion app
- **Advanced Analytics**: Machine learning for portfolio optimization
- **Social Features**: Share portfolio performance (anonymized)
- **API Access**: RESTful API for third-party integrations

### Version 3.0 Features
- **Multi-Currency Support**: International stock markets
- **Cryptocurrency Integration**: Bitcoin, Ethereum tracking
- **Options Trading**: Track options positions and Greeks
- **Automated Trading**: Integration with brokerage APIs

This comprehensive plan provides a solid foundation for building a professional-grade portfolio tracking application that can scale and evolve with user needs while maintaining security, performance, and reliability standards.
