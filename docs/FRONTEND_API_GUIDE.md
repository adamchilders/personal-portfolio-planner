# Frontend API Integration Guide

Quick reference for frontend developers working with the Portfolio Tracker API.

## API Helper Method

The frontend uses `this.apiCall(endpoint, method, data)` for all API requests:

```javascript
// GET request
const response = await this.apiCall('/portfolios');

// POST request
const response = await this.apiCall('/portfolios', 'POST', {
    name: 'My Portfolio',
    type: 'investment',
    currency: 'USD'
});

// PUT request
const response = await this.apiCall('/portfolios/1', 'PUT', updateData);

// DELETE request
const response = await this.apiCall('/portfolios/1', 'DELETE');
```

## Common API Patterns

### 1. Loading States
```javascript
async someAction() {
    try {
        this.showLoading('Loading...');
        const response = await this.apiCall('/endpoint');
        // Process response
        this.showSuccess('Success!');
    } catch (error) {
        this.showError('Failed to load data');
        console.error('Error:', error);
    }
}
```

### 2. Modal Operations
```javascript
async showEditModal(id) {
    try {
        // Store current content for restoration
        const currentAppContent = document.getElementById('app').innerHTML;
        
        this.showLoading('Loading details...');
        
        const response = await this.apiCall(`/endpoint/${id}`);
        
        // Restore content before showing modal
        document.getElementById('app').innerHTML = currentAppContent;
        
        this.showModal(this.getModalHTML(response.data));
    } catch (error) {
        this.showError('Failed to load details');
    }
}
```

### 3. Form Submissions
```javascript
async submitForm(formData) {
    try {
        this.showLoading('Saving...');
        
        const response = await this.apiCall('/endpoint', 'POST', formData);
        
        this.showSuccess('Saved successfully!');
        
        // Refresh the view
        setTimeout(() => {
            this.showDashboard(); // or appropriate view
        }, 1500);
        
    } catch (error) {
        this.showError('Failed to save: ' + error.message);
    }
}
```

## Endpoint Quick Reference

### Authentication
- `POST /auth/login` - Login user
- `POST /auth/logout` - Logout user
- `GET /auth/me` - Get current user

### Portfolios
- `GET /portfolios` - List portfolios
- `POST /portfolios` - Create portfolio
- `GET /portfolios/{id}` - Get portfolio details
- `PUT /portfolios/{id}` - Update portfolio
- `DELETE /portfolios/{id}` - Delete portfolio

### Transactions
- `GET /portfolios/{id}/transactions` - List transactions
- `POST /portfolios/{id}/transactions` - Add transaction
- `GET /portfolios/{id}/transactions/{transactionId}` - Get transaction
- `PUT /portfolios/{id}/transactions/{transactionId}` - Update transaction
- `DELETE /portfolios/{id}/transactions/{transactionId}` - Delete transaction

### Stocks
- `GET /stocks/search?q={query}` - Search stocks
- `GET /stocks/{symbol}` - Get stock info
- `GET /stocks/{symbol}/quote` - Get current quote
- `GET /stocks/{symbol}/history` - Get historical data
- `POST /stocks/quotes` - Get multiple quotes
- `GET /stocks/missing-historical-data` - Check missing data
- `POST /stocks/backfill-historical-data` - Backfill data

## Data Structures

### Portfolio Object
```javascript
{
    id: 1,
    name: "My Portfolio",
    type: "investment",
    currency: "USD",
    created_at: "2025-01-01T00:00:00Z"
}
```

### Transaction Object
```javascript
{
    id: 1,
    portfolio_id: 1,
    stock_symbol: "AAPL",
    transaction_type: "buy", // "buy", "sell", "dividend"
    quantity: 100,
    price: 150.00,
    fees: 9.99,
    total_amount: 15009.99,
    transaction_date: "2025-01-01",
    notes: "Initial purchase",
    created_at: "2025-01-01T00:00:00Z"
}
```

### Holding Object
```javascript
{
    symbol: "AAPL",
    name: "Apple Inc.",
    quantity: 100,
    avg_cost_basis: 150.00,
    current_price: 175.00,
    current_value: 17500.00,
    gain_loss: 2500.00,
    gain_loss_percent: 16.67,
    weight: 35.5
}
```

### Stock Quote Object
```javascript
{
    symbol: "AAPL",
    price: 175.00,
    change: 2.50,
    change_percent: 1.45,
    volume: 50000000,
    last_updated: "2025-01-01T16:00:00Z"
}
```

## Error Handling

### Standard Error Response
```javascript
{
    success: false,
    error: "Error Type",
    message: "Human readable error message",
    timestamp: "2025-01-01T00:00:00Z"
}
```

### Common Error Handling
```javascript
try {
    const response = await this.apiCall('/endpoint');
    // Success - response.data contains the result
} catch (error) {
    // Error - error.message contains the error message
    if (error.message.includes('Unauthorized')) {
        // Redirect to login
        this.showLogin();
    } else if (error.message.includes('Not Found')) {
        // Show not found message
        this.showError('Resource not found');
    } else {
        // Generic error
        this.showError('An error occurred: ' + error.message);
    }
}
```

## Best Practices

### 1. Always Handle Loading States
```javascript
// ✅ Good
this.showLoading('Loading...');
const response = await this.apiCall('/endpoint');
// Process response

// ❌ Bad - No loading indicator
const response = await this.apiCall('/endpoint');
```

### 2. Use Proper Error Messages
```javascript
// ✅ Good
catch (error) {
    this.showError('Failed to save portfolio: ' + error.message);
}

// ❌ Bad - Generic error
catch (error) {
    this.showError('Something went wrong');
}
```

### 3. Validate Data Before Sending
```javascript
// ✅ Good
if (!formData.name || !formData.type) {
    this.showError('Please fill in all required fields');
    return;
}
const response = await this.apiCall('/portfolios', 'POST', formData);

// ❌ Bad - No validation
const response = await this.apiCall('/portfolios', 'POST', formData);
```

### 4. Use Consistent Endpoint Patterns
```javascript
// ✅ Good - Consistent with API structure
await this.apiCall(`/portfolios/${portfolioId}/transactions/${transactionId}`);

// ❌ Bad - Wrong endpoint structure
await this.apiCall(`/transactions/${transactionId}`);
```

### 5. Handle Success States
```javascript
// ✅ Good
const response = await this.apiCall('/portfolios', 'POST', data);
this.showSuccess('Portfolio created successfully!');
setTimeout(() => this.showDashboard(), 1500);

// ❌ Bad - No user feedback
const response = await this.apiCall('/portfolios', 'POST', data);
this.showDashboard();
```

## Common Gotchas

1. **Modal Loading**: Always store and restore app content when showing modals after loading
2. **Portfolio Context**: Use `this.getCurrentPortfolioId()` to get current portfolio ID
3. **Date Formats**: API expects dates in YYYY-MM-DD format
4. **Decimal Precision**: Use `this.formatNumber()` for displaying financial values
5. **Authentication**: All API calls except auth endpoints require valid session

---

**Remember**: Always refer to the full API_REFERENCE.md for complete endpoint documentation!
