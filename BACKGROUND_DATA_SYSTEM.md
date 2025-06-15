# Background Data Fetching System

The Portfolio Tracker includes an intelligent background data fetching system that efficiently manages both real-time quotes and historical price data while minimizing API calls and respecting rate limits.

## Overview

Instead of fetching stock data on-demand (which can be slow and hit rate limits), the system:

1. **Proactively fetches** real-time quotes and historical data for stocks currently held in portfolios
2. **Caches data** in the database with timestamps
3. **Uses smart refresh intervals** based on market hours
4. **Serves cached data** to users for fast response times
5. **Stores historical OHLCV data** for portfolio performance analysis

## Architecture

### Components

- **BackgroundDataService**: Core service that manages both quote and historical data fetching
- **StockDataService**: Updated to prioritize cached data over API calls, includes historical data methods
- **CLI Script**: `bin/fetch-stock-data.php` for manual execution and cron jobs
- **Database Storage**: Uses `stock_quotes` for real-time data, `stock_prices` for historical OHLCV data, and `dividends` for dividend history
- **API Endpoints**: RESTful endpoints for accessing both current and historical data

### Data Flow

```
Background Job → Yahoo Finance API → Database Cache → User Requests
```

## Cache Strategy

### Refresh Intervals

- **Market Hours** (9:30 AM - 4:00 PM ET, Mon-Fri): 15 minutes
- **After Hours**: 30 minutes
- **Weekends**: 30 minutes

### Smart Fetching

- Only fetches data for stocks **currently held in portfolios**
- Automatically discovers new stocks when trades are added
- Removes unused stocks from regular updates
- Rate limiting: 250ms delay between API calls

## Usage

### Manual Execution

```bash
# Basic data fetch (respects cache timeouts)
php bin/fetch-stock-data.php

# Force update all data regardless of cache age
php bin/fetch-stock-data.php --force

# Fetch historical price data (default: 365 days)
php bin/fetch-stock-data.php --historical

# Fetch historical data for specific period
php bin/fetch-stock-data.php --historical --days=90

# Fetch dividend data (default: 365 days)
php bin/fetch-stock-data.php --dividends

# Fetch dividend data for specific period
php bin/fetch-stock-data.php --dividends --days=365

# Show data freshness statistics
php bin/fetch-stock-data.php --stats

# Show help
php bin/fetch-stock-data.php --help
```

### Docker Environment

```bash
# Run inside Docker container
docker-compose -f docker-compose.nginx.yml exec app php bin/fetch-stock-data.php --stats
```

### Automated Scheduling

Set up cron jobs for automatic data fetching:

```bash
# Run the setup script
./bin/setup-cron.sh
```

**Recommended Cron Schedule:**

```cron
# Real-time quotes: Every 15 minutes during market hours
*/15 9-16 * * 1-5 /usr/bin/php /path/to/bin/fetch-stock-data.php

# Real-time quotes: Every 30 minutes after hours
*/30 0-9,16-23 * * 1-5 /usr/bin/php /path/to/bin/fetch-stock-data.php
0,30 * * * 0,6 /usr/bin/php /path/to/bin/fetch-stock-data.php

# Historical data: Daily at 4:05 PM ET (5 minutes after market close)
5 16 * * 1-5 /usr/bin/php /path/to/bin/fetch-stock-data.php --historical

# Dividend data: Daily at 4:10 PM ET (10 minutes after market close)
10 16 * * 1-5 /usr/bin/php /path/to/bin/fetch-stock-data.php --dividends
```

## Benefits

### Performance
- **Fast API responses**: Data served from database cache
- **Reduced latency**: No waiting for external API calls
- **Predictable response times**: Consistent user experience

### Efficiency
- **Minimal API calls**: Only fetch data for held stocks
- **Smart scheduling**: More frequent updates during market hours
- **Automatic dividend updates**: Daily dividend data fetching and new stock detection
- **Rate limit compliance**: Built-in delays and error handling

### Reliability
- **Fallback mechanisms**: Stale data if API fails
- **Error handling**: Graceful degradation
- **Monitoring**: Detailed logging and statistics

## Monitoring

### Data Freshness Statistics

```bash
php bin/fetch-stock-data.php --stats
```

**Output:**
```
📊 Current data freshness statistics:
   Total stocks in portfolios: 5
   Fresh data: 4
   Stale data: 1
   Missing data: 0
   Oldest data: 2025-06-13 14:30:15 EDT
   Newest data: 2025-06-13 16:45:22 EDT
```

### Execution Results

```
📈 Data fetch results:
   Total symbols: 5
   Updated: 2
   Skipped (fresh): 3
   Failed: 0
```

## API Endpoints

### Real-Time Data

```bash
# Get current stock quote
GET /api/stocks/{symbol}/quote

# Example
curl -H "Authorization: Bearer {token}" \
  http://localhost:8000/api/stocks/AAPL/quote
```

### Historical Data

```bash
# Get historical price data (default: 30 days)
GET /api/stocks/{symbol}/history

# Get specific number of days
GET /api/stocks/{symbol}/history?days=90

# Get date range
GET /api/stocks/{symbol}/history?start=2025-01-01&end=2025-01-31

# Example response
{
  "symbol": "AAPL",
  "period": {
    "days": 7,
    "actual_start": "2025-06-06",
    "actual_end": "2025-06-13"
  },
  "count": 7,
  "prices": [
    {
      "date": "2025-06-13",
      "open": 196.45,
      "high": 197.20,
      "low": 195.80,
      "close": 196.45,
      "adjusted_close": 196.45,
      "volume": 50700000
    }
    // ... more price records
  ]
}
```

### Dividend Data

```bash
# Get dividend history (default: 365 days)
GET /api/stocks/{symbol}/dividends

# Get specific number of days
GET /api/stocks/{symbol}/dividends?days=365

# Update dividend data from configured provider (FMP primary, Yahoo Finance fallback)
POST /api/stocks/{symbol}/dividends/update

# Example response
{
  "success": true,
  "symbol": "AAPL",
  "period_days": 365,
  "count": 4,
  "total_amount": 0.96,
  "dividends": [
    {
      "symbol": "AAPL",
      "ex_date": "2024-11-08",
      "amount": 0.24,
      "payment_date": "2024-11-14",
      "record_date": "2024-11-11",
      "dividend_type": "regular"
    }
    // ... more dividend records
  ]
}
```

## Configuration

### Environment Variables

```env
# Stock Data Configuration
USE_MOCK_STOCK_DATA=false

# Historical Data Configuration
HISTORICAL_DATA_DAYS=365
HISTORICAL_UPDATE_TIME=16:05
MARKET_CLOSE_TIME=16:00
MARKET_TIMEZONE=America/New_York

# Data Fetch Intervals (minutes)
QUOTE_CACHE_MARKET_HOURS=15
QUOTE_CACHE_AFTER_HOURS=30
```

### Configuration Management

The system includes a centralized configuration service (`ConfigService`) that:

- **Validates settings** on startup
- **Provides defaults** for missing values
- **Centralizes configuration logic** across all services
- **Exposes configuration via API** at `/api/config`

**View current configuration:**
```bash
curl http://localhost:8000/api/config
```

### Database Tables

- **stocks**: Master stock information (symbol, name, exchange, etc.)
- **stock_quotes**: Real-time quote cache with timestamps
- **stock_prices**: Historical OHLCV data (Open, High, Low, Close, Volume)
- **dividends**: Dividend history (ex-date, amount, payment date, etc.)
- **portfolio_holdings**: Determines which stocks to fetch

## Development

### Testing

```bash
# Test with mock data
USE_MOCK_STOCK_DATA=true php bin/fetch-stock-data.php --stats

# Test with real API
USE_MOCK_STOCK_DATA=false php bin/fetch-stock-data.php --force
```

### Adding New Stocks

When users add trades for new stocks:
1. Stock is automatically added to the database
2. Dividend data is fetched immediately for the new stock
3. Background job will include it in the next fetch cycle
4. Data becomes available immediately for portfolio calculations

## Troubleshooting

### Common Issues

**Script hangs or times out:**
- Check database connection
- Verify Yahoo Finance API accessibility
- Review error logs

**Stale data:**
- Run with `--force` flag to refresh
- Check cron job configuration
- Verify market hours settings

**High API usage:**
- Ensure only portfolio stocks are being fetched
- Check for duplicate cron jobs
- Review rate limiting settings

### Logs

Error logs are written to the system error log and include:
- API failures and retries
- Database connection issues
- Stock symbol validation errors
- Cache update failures
