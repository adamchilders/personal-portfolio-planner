<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserSession;
use App\Helpers\DateTimeHelper;
use Exception;

class AuthService
{
    private const string SESSION_COOKIE_NAME = 'portfolio_session';
    private const int SESSION_LIFETIME_MINUTES = 120;
    
    public function __construct(
        private UserService $userService
    ) {}
    
    /**
     * Authenticate user with username/email and password
     */
    public function authenticate(string $identifier, string $password): ?User
    {
        $user = $this->userService->findByUsernameOrEmail($identifier);
        
        if (!$user || !$user->isActive() || !$user->verifyPassword($password)) {
            return null;
        }
        
        $user->updateLastLogin();
        return $user;
    }
    
    /**
     * Create a new session for the user
     */
    public function createSession(User $user, ?string $ipAddress = null, ?string $userAgent = null): UserSession
    {
        // Clean up old sessions for this user
        $this->cleanupUserSessions($user);
        
        return UserSession::createForUser($user, $ipAddress, $userAgent);
    }
    
    /**
     * Login user and create session
     */
    public function login(string $identifier, string $password, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $user = $this->authenticate($identifier, $password);
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Invalid credentials'
            ];
        }
        
        $session = $this->createSession($user, $ipAddress, $userAgent);
        
        return [
            'success' => true,
            'user' => $user,
            'session' => $session,
            'token' => $session->session_token
        ];
    }
    
    /**
     * Logout user by invalidating session
     */
    public function logout(string $sessionToken): bool
    {
        $session = UserSession::findByToken($sessionToken);
        
        if ($session) {
            $session->delete();
            return true;
        }
        
        return false;
    }
    
    /**
     * Get user from session token
     */
    public function getUserFromSession(string $sessionToken): ?User
    {
        // First try to find a valid session
        $session = UserSession::findByToken($sessionToken);

        if (!$session) {
            // If no valid session found, try to find any session with this token
            // (including expired ones) and check if it's recently expired
            $session = UserSession::where('session_token', $sessionToken)->first();

            if (!$session) {
                return null;
            }

            // If session expired less than 24 hours ago, allow renewal
            $expiredTime = $session->expires_at;
            $now = DateTimeHelper::now();
            $hoursSinceExpired = ($now->getTimestamp() - $expiredTime->getTimestamp()) / 3600;

            if ($hoursSinceExpired > 24) {
                // Session is too old, don't renew
                return null;
            }
        }

        // Update session activity and extend expiration
        $session->updateActivity();
        $session->extend(self::SESSION_LIFETIME_MINUTES);

        return $session->user;
    }
    
    /**
     * Validate session token
     */
    public function validateSession(string $sessionToken): bool
    {
        $session = UserSession::findByToken($sessionToken);
        return $session !== null;
    }
    
    /**
     * Extend session expiration
     */
    public function extendSession(string $sessionToken, ?int $minutes = null): bool
    {
        $session = UserSession::findByToken($sessionToken);
        
        if (!$session) {
            return false;
        }
        
        $session->extend($minutes ?? self::SESSION_LIFETIME_MINUTES);
        return true;
    }
    
    /**
     * Register a new user
     */
    public function register(array $userData): array
    {
        try {
            // Check if this is the first user (make them admin)
            $isFirstUser = User::count() === 0;
            
            $userData['role'] = $isFirstUser ? 'admin' : 'user';
            $userData['is_active'] = true;
            
            $user = $this->userService->create($userData);
            
            return [
                'success' => true,
                'user' => $user,
                'is_first_user' => $isFirstUser
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if any users exist in the system
     */
    public function hasUsers(): bool
    {
        return User::count() > 0;
    }
    
    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions(): int
    {
        return UserSession::cleanupExpired();
    }
    
    /**
     * Clean up old sessions for a specific user (keep only the latest 5)
     */
    private function cleanupUserSessions(User $user): void
    {
        $sessions = $user->sessions()
            ->orderBy('created_at', 'desc')
            ->offset(5)
            ->limit(100) // Add limit to make offset work in MySQL
            ->get();

        foreach ($sessions as $session) {
            $session->delete();
        }
    }
    
    /**
     * Generate session cookie
     */
    public function generateSessionCookie(string $sessionToken): array
    {
        return [
            'name' => self::SESSION_COOKIE_NAME,
            'value' => $sessionToken,
            'expires' => time() + self::SESSION_LIFETIME_MINUTES * 60,
            'path' => '/',
            'secure' => $_ENV['APP_ENV'] === 'production',
            'httponly' => true,
            'samesite' => 'Lax'
        ];
    }
    
    /**
     * Get session token from request
     */
    public function getSessionTokenFromRequest($request): ?string
    {
        // Try to get from cookie first
        $cookies = $request->getCookieParams();
        if (isset($cookies[self::SESSION_COOKIE_NAME])) {
            return $cookies[self::SESSION_COOKIE_NAME];
        }
        
        // Try to get from Authorization header
        $authHeader = $request->getHeaderLine('Authorization');
        if (str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }
        
        return null;
    }
}
