# Portfolio Tracker API Reference

This document provides comprehensive documentation for all API endpoints in the Portfolio Tracker application.

## Base URL
```
http://localhost:8000/api
```

## Authentication
Most endpoints require authentication via session-based auth. Include the session cookie in requests.

## Response Format
All API responses follow this format:
```json
{
  "success": true|false,
  "data": {...},           // On success
  "error": "Error message", // On failure
  "timestamp": "ISO 8601 date"
}
```

---

## Authentication Endpoints

### POST /auth/login
**Purpose**: User login
**Authentication**: None required
**Request Body**:
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```
**Response**:
```json
{
  "success": true,
  "user": {
    "id": 1,
    "email": "user@example.com",
    "first_name": "John",
    "last_name": "Doe"
  }
}
```

### POST /auth/register
**Purpose**: User registration
**Authentication**: None required
**Request Body**:
```json
{
  "email": "user@example.com",
  "password": "password123",
  "first_name": "John",
  "last_name": "Doe"
}
```
**Response**: Same as login

### POST /auth/logout
**Purpose**: User logout
**Authentication**: Required
**Request Body**: None
**Response**:
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

### GET /auth/me
**Purpose**: Get current user information
**Authentication**: Required
**Response**:
```json
{
  "success": true,
  "user": {
    "id": 1,
    "email": "user@example.com",
    "first_name": "John",
    "last_name": "Doe"
  }
}
```

---

## Portfolio Endpoints

### GET /api/portfolios
**Purpose**: List all user portfolios
**Authentication**: Required
**Response**:
```json
{
  "success": true,
  "portfolios": [
    {
      "id": 1,
      "name": "My Portfolio",
      "type": "investment",
      "currency": "USD",
      "created_at": "2025-01-01T00:00:00Z"
    }
  ]
}
```

### POST /api/portfolios
**Purpose**: Create new portfolio
**Authentication**: Required
**Request Body**:
```json
{
  "name": "My New Portfolio",
  "type": "investment",
  "currency": "USD"
}
```
**Response**:
```json
{
  "success": true,
  "portfolio": {
    "id": 2,
    "name": "My New Portfolio",
    "type": "investment",
    "currency": "USD",
    "created_at": "2025-01-01T00:00:00Z"
  }
}
```

### GET /api/portfolios/{id}
**Purpose**: Get portfolio details with holdings and performance
**Authentication**: Required
**Response**:
```json
{
  "success": true,
  "portfolio": {
    "id": 1,
    "name": "My Portfolio",
    "type": "investment",
    "currency": "USD"
  },
  "holdings": [
    {
      "symbol": "AAPL",
      "name": "Apple Inc.",
      "quantity": 100,
      "avg_cost_basis": 150.00,
      "current_price": 175.00,
      "current_value": 17500.00,
      "gain_loss": 2500.00,
      "gain_loss_percent": 16.67,
      "weight": 35.5
    }
  ],
  "performance": {
    "total_value": 49250.00,
    "total_cost_basis": 45000.00,
    "total_gain_loss": 4250.00,
    "total_gain_loss_percent": 9.44
  }
}
```

### PUT /api/portfolios/{id}
**Purpose**: Update portfolio
**Authentication**: Required
**Request Body**:
```json
{
  "name": "Updated Portfolio Name",
  "type": "retirement",
  "currency": "USD"
}
```
**Response**: Same as GET /api/portfolios/{id}

### DELETE /api/portfolios/{id}
**Purpose**: Delete portfolio
**Authentication**: Required
**Response**:
```json
{
  "success": true,
  "message": "Portfolio deleted successfully"
}
```

---

## Transaction Endpoints

### GET /api/portfolios/{id}/transactions
**Purpose**: Get all transactions for a portfolio
**Authentication**: Required
**Query Parameters**:
- `symbol` (optional): Filter by stock symbol
**Response**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "portfolio_id": 1,
      "stock_symbol": "AAPL",
      "transaction_type": "buy",
      "quantity": 100,
      "price": 150.00,
      "fees": 9.99,
      "total_amount": 15009.99,
      "transaction_date": "2025-01-01",
      "notes": "Initial purchase",
      "created_at": "2025-01-01T00:00:00Z"
    }
  ]
}
```

### POST /api/portfolios/{id}/transactions
**Purpose**: Add new transaction
**Authentication**: Required
**Request Body**:
```json
{
  "stock_symbol": "AAPL",
  "transaction_type": "buy",
  "quantity": 100,
  "price": 150.00,
  "fees": 9.99,
  "transaction_date": "2025-01-01",
  "notes": "Initial purchase"
}
```
**Response**:
```json
{
  "success": true,
  "transaction": {
    "id": 1,
    "portfolio_id": 1,
    "stock_symbol": "AAPL",
    "transaction_type": "buy",
    "quantity": 100,
    "price": 150.00,
    "fees": 9.99,
    "total_amount": 15009.99,
    "transaction_date": "2025-01-01",
    "notes": "Initial purchase",
    "created_at": "2025-01-01T00:00:00Z"
  }
}
```

### GET /api/portfolios/{id}/transactions/{transactionId}
**Purpose**: Get specific transaction details
**Authentication**: Required
**Response**:
```json
{
  "success": true,
  "transaction": {
    "id": 1,
    "portfolio_id": 1,
    "stock_symbol": "AAPL",
    "transaction_type": "buy",
    "quantity": 100,
    "price": 150.00,
    "fees": 9.99,
    "total_amount": 15009.99,
    "transaction_date": "2025-01-01",
    "notes": "Initial purchase",
    "created_at": "2025-01-01T00:00:00Z"
  }
}
```

### PUT /api/portfolios/{id}/transactions/{transactionId}
**Purpose**: Update transaction
**Authentication**: Required
**Request Body**: Same as POST transaction
**Response**: Same as GET transaction

### DELETE /api/portfolios/{id}/transactions/{transactionId}
**Purpose**: Delete transaction
**Authentication**: Required
**Response**:
```json
{
  "success": true,
  "message": "Transaction deleted successfully"
}
```

---

## Stock Data Endpoints

### GET /api/stocks/search
**Purpose**: Search for stocks by symbol or name
**Authentication**: Required
**Query Parameters**:
- `q`: Search query (required)
**Response**:
```json
{
  "success": true,
  "results": [
    {
      "symbol": "AAPL",
      "name": "Apple Inc.",
      "exchange": "NASDAQ",
      "currency": "USD"
    }
  ]
}
```

### GET /api/stocks/{symbol}
**Purpose**: Get stock information and current quote
**Authentication**: Required
**Response**:
```json
{
  "success": true,
  "stock": {
    "symbol": "AAPL",
    "name": "Apple Inc.",
    "exchange": "NASDAQ",
    "currency": "USD",
    "current_price": 175.00,
    "change": 2.50,
    "change_percent": 1.45,
    "volume": 50000000,
    "market_cap": 2800000000000,
    "last_updated": "2025-01-01T16:00:00Z"
  }
}
```

### GET /api/stocks/{symbol}/quote
**Purpose**: Get current stock quote only
**Authentication**: Required
**Response**:
```json
{
  "success": true,
  "quote": {
    "symbol": "AAPL",
    "price": 175.00,
    "change": 2.50,
    "change_percent": 1.45,
    "volume": 50000000,
    "last_updated": "2025-01-01T16:00:00Z"
  }
}
```

### GET /api/stocks/{symbol}/history
**Purpose**: Get historical price data
**Authentication**: Required
**Query Parameters**:
- `period`: Time period (1d, 5d, 1mo, 3mo, 6mo, 1y, 2y, 5y, 10y, ytd, max)
- `interval`: Data interval (1m, 2m, 5m, 15m, 30m, 60m, 90m, 1h, 1d, 5d, 1wk, 1mo, 3mo)
**Response**:
```json
{
  "success": true,
  "symbol": "AAPL",
  "period": "1mo",
  "interval": "1d",
  "meta": {
    "start_date": "2024-12-01",
    "end_date": "2025-01-01",
    "actual_start": "2024-12-02",
    "actual_end": "2024-12-31"
  },
  "count": 22,
  "prices": [
    {
      "date": "2024-12-02",
      "open": 170.00,
      "high": 175.00,
      "low": 168.00,
      "close": 172.50,
      "adjusted_close": 172.50,
      "volume": 45000000
    }
  ]
}
```

### GET /api/stocks/{symbol}/dividends
**Purpose**: Get dividend history for a stock
**Authentication**: Required
**Query Parameters**:
- `days` (optional): Number of days to look back (default: 365, max: 1825)
**Response**:
```json
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
    },
    {
      "symbol": "AAPL",
      "ex_date": "2024-08-09",
      "amount": 0.24,
      "payment_date": "2024-08-15",
      "record_date": "2024-08-12",
      "dividend_type": "regular"
    }
  ]
}
```

### POST /api/stocks/{symbol}/dividends/update
**Purpose**: Fetch and update dividend data from Yahoo Finance
**Authentication**: Required
**Query Parameters**:
- `days` (optional): Number of days to fetch (default: 365, max: 1825)
**Response**:
```json
{
  "success": true,
  "symbol": "AAPL",
  "message": "Dividend data updated successfully",
  "count": 4,
  "total_amount": 0.96,
  "dividends": [
    {
      "symbol": "AAPL",
      "ex_date": "2024-11-08",
      "amount": 0.24,
      "timestamp": 1731024000
    }
  ]
}
```

### POST /api/stocks/{symbol}/update-quote
**Purpose**: Force update stock quote from external API
**Authentication**: Required
**Response**:
```json
{
  "success": true,
  "message": "Quote updated successfully",
  "quote": {
    "symbol": "AAPL",
    "price": 175.00,
    "change": 2.50,
    "change_percent": 1.45,
    "last_updated": "2025-01-01T16:00:00Z"
  }
}
```

### POST /api/stocks/quotes
**Purpose**: Get multiple stock quotes at once
**Authentication**: Required
**Request Body**:
```json
{
  "symbols": ["AAPL", "GOOGL", "MSFT"]
}
```
**Response**:
```json
{
  "success": true,
  "quotes": {
    "AAPL": {
      "symbol": "AAPL",
      "price": 175.00,
      "change": 2.50,
      "change_percent": 1.45,
      "last_updated": "2025-01-01T16:00:00Z"
    },
    "GOOGL": {
      "symbol": "GOOGL",
      "price": 2800.00,
      "change": -15.00,
      "change_percent": -0.53,
      "last_updated": "2025-01-01T16:00:00Z"
    }
  }
}
```

### GET /api/stocks/missing-historical-data
**Purpose**: Get list of stocks missing historical data
**Authentication**: Required
**Response**:
```json
{
  "success": true,
  "message": "Found 2 stocks missing historical data",
  "stocks_missing_data": 2,
  "stocks": [
    {
      "symbol": "AAPL",
      "latest_date": "2024-06-01",
      "days_missing": 180
    },
    {
      "symbol": "GOOGL",
      "latest_date": null,
      "days_missing": 365
    }
  ]
}
```

### POST /api/stocks/backfill-historical-data
**Purpose**: Backfill historical data for stocks missing data
**Authentication**: Required
**Query Parameters**:
- `symbols` (optional): Comma-separated list of symbols to backfill
- `days` (optional): Number of days to backfill (default: 365)
**Request Body**: None (uses query parameters)
**Response**:
```json
{
  "success": true,
  "message": "Historical data backfill completed: 2/2 stocks processed successfully",
  "stocks_processed": 2,
  "successful": 2,
  "failed": 0,
  "results": {
    "AAPL": {
      "success": true,
      "message": "Historical data fetched successfully"
    },
    "GOOGL": {
      "success": true,
      "message": "Historical data fetched successfully"
    }
  }
}
```

---

## System Endpoints

### GET /api/status
**Purpose**: API health check
**Authentication**: None required
**Response**:
```json
{
  "api_version": "1.0.0",
  "status": "operational",
  "timestamp": "2025-01-01T00:00:00Z"
}
```

### GET /api/config
**Purpose**: Get system configuration
**Authentication**: Required
**Response**:
```json
{
  "config": {
    "yahoo_finance_enabled": true,
    "historical_data_days": 365,
    "cache_duration_minutes": 15
  },
  "validation": {
    "valid": true,
    "errors": []
  },
  "timestamp": "2025-01-01T00:00:00Z"
}
```

### GET /api/docs
**Purpose**: Get API documentation
**Authentication**: None required
**Response**: Returns this documentation in JSON format

---

## Error Responses

All endpoints may return these error responses:

### 400 Bad Request
```json
{
  "success": false,
  "error": "Bad Request",
  "message": "Invalid request data",
  "timestamp": "2025-01-01T00:00:00Z"
}
```

### 401 Unauthorized
```json
{
  "success": false,
  "error": "Unauthorized",
  "message": "Invalid or expired session",
  "timestamp": "2025-01-01T00:00:00Z"
}
```

### 404 Not Found
```json
{
  "success": false,
  "error": "Not Found",
  "message": "The requested resource does not exist",
  "timestamp": "2025-01-01T00:00:00Z"
}
```

### 500 Internal Server Error
```json
{
  "success": false,
  "error": "Internal Server Error",
  "message": "An unexpected error occurred",
  "timestamp": "2025-01-01T00:00:00Z"
}
```

---

## Notes

1. **Authentication**: Session-based authentication is used. Login to get a session cookie.
2. **Rate Limiting**: External API calls (Yahoo Finance) are rate-limited to prevent abuse.
3. **Data Validation**: All input data is validated before processing.
4. **Error Handling**: Comprehensive error responses with meaningful messages.
5. **Timestamps**: All timestamps are in ISO 8601 format (UTC).
6. **Decimal Precision**: Financial values use appropriate decimal precision.

---

**Last Updated**: 2025-06-13
**Version**: 1.0.0
