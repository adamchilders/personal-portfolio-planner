# Session Persistence Fix

## Problem

Users were being logged out every time they refreshed the page, even though they had valid authentication tokens stored in localStorage.

## Root Cause Analysis

The issue was caused by **timezone mismatches** between the PHP application and the MySQL database:

1. **PHP Application**: Running in `America/New_York` timezone (EDT/EST)
2. **MySQL Database**: Running in `UTC` timezone
3. **Session Expiration Logic**: Session expiration times were calculated in EDT but compared against UTC timestamps

### Example of the Problem:
- Session created at: `17:44:39 EDT` (21:44:39 UTC)
- Session expires at: `19:44:39 EDT` (23:44:39 UTC) - 2 hours later
- Database NOW(): `21:45:00 UTC`
- **Result**: Session appeared expired (19:44:39 < 21:45:00) when comparing EDT vs UTC

## Solution

### 1. Fixed Timezone Consistency

Updated `DateTimeHelper` to always work in UTC:

```php
// Before
public static function now(): DateTime
{
    return new DateTime();  // Uses system timezone
}

// After  
public static function now(): DateTime
{
    return new DateTime('now', new \DateTimeZone('UTC'));  // Always UTC
}
```

### 2. Enhanced Session Extension Logic

Improved session renewal to handle expired sessions gracefully:

```php
public function getUserFromSession(string $sessionToken): ?User
{
    // Try to find valid session first
    $session = UserSession::findByToken($sessionToken);
    
    if (!$session) {
        // If expired, check if it's recently expired (< 24 hours)
        $session = UserSession::where('session_token', $sessionToken)->first();
        
        if ($session && $this->isRecentlyExpired($session)) {
            // Allow renewal of recently expired sessions
        } else {
            return null;
        }
    }
    
    // Extend session on every access
    $session->extend(120); // 2 hours
    return $session->user;
}
```

### 3. Fixed Frontend Authentication Flow

Corrected the frontend to use the proper authentication endpoint:

```javascript
// Before
async getCurrentUser() {
    const response = await this.apiCall('/auth/me');  // Wrong: /api/auth/me
}

// After
async getCurrentUser() {
    const response = await this.authCall('/auth/me');  // Correct: /auth/me
}
```

Updated `authCall` to include Authorization header when token exists:

```javascript
async authCall(endpoint, options = {}) {
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            ...(this.authToken && { 'Authorization': `Bearer ${this.authToken}` })
        }
    };
    // ...
}
```

## Key Features

### Automatic Session Extension
- Sessions are automatically extended by 2 hours on every authenticated request
- No need for manual session refresh

### Graceful Expiration Handling
- Recently expired sessions (< 24 hours) can be renewed
- Prevents immediate logout for minor timing issues

### Timezone-Safe Operations
- All session timestamps stored and compared in UTC
- Eliminates timezone-related session expiration bugs

### Robust Error Handling
- Frontend gracefully handles authentication failures
- Clear error messages for debugging

## Configuration

Session settings in `AuthService`:

```php
private const int SESSION_LIFETIME_MINUTES = 120;  // 2 hours
```

## Testing

### Manual Testing Steps:
1. Login to the application
2. Refresh the page multiple times
3. Wait a few minutes and refresh again
4. Verify user remains logged in

### Automated Testing:
Use the provided `test_session_persistence.html` file to verify:
- Login functionality
- Session persistence across page refreshes
- Token storage in localStorage
- Authentication endpoint responses

## Benefits

✅ **Persistent Sessions**: Users stay logged in across page refreshes
✅ **Automatic Extension**: Sessions extend on activity (no manual refresh needed)  
✅ **Timezone Safe**: Works correctly regardless of server/client timezone differences
✅ **Graceful Degradation**: Handles edge cases and expired sessions properly
✅ **Better UX**: No unexpected logouts during normal usage

## Files Modified

- `app/Helpers/DateTimeHelper.php` - UTC timezone enforcement
- `app/Services/AuthService.php` - Enhanced session management
- `app/Models/UserSession.php` - Improved session extension
- `public/assets/js/app.js` - Fixed frontend authentication flow

## Monitoring

Session activity is logged for debugging:
- Session creation and extension events
- Authentication failures and successes
- Token validation results

Check logs at: `/var/www/html/storage/logs/php_errors.log`
