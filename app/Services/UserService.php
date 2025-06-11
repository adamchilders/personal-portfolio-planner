<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserPreference;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class UserService
{
    /**
     * Create a new user
     */
    public function create(array $userData): User
    {
        // Validate required fields
        $this->validateUserData($userData);
        
        // Check for existing username/email
        if ($this->usernameExists($userData['username'])) {
            throw new Exception('Username already exists');
        }
        
        if ($this->emailExists($userData['email'])) {
            throw new Exception('Email already exists');
        }
        
        // Hash password
        $userData['password_hash'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        unset($userData['password']);
        
        // Create user
        $user = User::create($userData);
        
        // Create default preferences
        $this->createDefaultPreferences($user);
        
        return $user;
    }
    
    /**
     * Update user information
     */
    public function update(User $user, array $userData): User
    {
        // Remove sensitive fields that shouldn't be updated directly
        unset($userData['password_hash'], $userData['id']);
        
        // Handle password update separately
        if (isset($userData['password'])) {
            $user->setPassword($userData['password']);
            unset($userData['password']);
        }
        
        // Validate username/email uniqueness if they're being changed
        if (isset($userData['username']) && $userData['username'] !== $user->username) {
            if ($this->usernameExists($userData['username'])) {
                throw new Exception('Username already exists');
            }
        }
        
        if (isset($userData['email']) && $userData['email'] !== $user->email) {
            if ($this->emailExists($userData['email'])) {
                throw new Exception('Email already exists');
            }
        }
        
        $user->update($userData);
        return $user->fresh();
    }
    
    /**
     * Find user by ID
     */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }
    
    /**
     * Find user by username
     */
    public function findByUsername(string $username): ?User
    {
        return User::where('username', $username)->first();
    }
    
    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }
    
    /**
     * Find user by username or email
     */
    public function findByUsernameOrEmail(string $identifier): ?User
    {
        return User::where('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();
    }
    
    /**
     * Get all users with pagination
     */
    public function getUsers(int $page = 1, int $perPage = 20): Collection
    {
        return User::with('preferences')
            ->orderBy('created_at', 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();
    }
    
    /**
     * Get active users
     */
    public function getActiveUsers(): Collection
    {
        return User::active()->get();
    }
    
    /**
     * Get admin users
     */
    public function getAdmins(): Collection
    {
        return User::admins()->get();
    }
    
    /**
     * Activate user
     */
    public function activate(User $user): User
    {
        $user->is_active = true;
        $user->save();
        return $user;
    }
    
    /**
     * Deactivate user
     */
    public function deactivate(User $user): User
    {
        $user->is_active = false;
        $user->save();
        return $user;
    }
    
    /**
     * Delete user (soft delete by deactivating)
     */
    public function delete(User $user): bool
    {
        // Deactivate instead of hard delete to preserve data integrity
        return $this->deactivate($user)->save();
    }
    
    /**
     * Check if username exists
     */
    public function usernameExists(string $username): bool
    {
        return User::where('username', $username)->exists();
    }
    
    /**
     * Check if email exists
     */
    public function emailExists(string $email): bool
    {
        return User::where('email', $email)->exists();
    }
    
    /**
     * Validate user data
     */
    private function validateUserData(array $userData): void
    {
        $required = ['username', 'email', 'password'];
        
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                throw new Exception("Field '{$field}' is required");
            }
        }
        
        // Validate email format
        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }
        
        // Validate username format (alphanumeric and underscore only)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $userData['username'])) {
            throw new Exception('Username can only contain letters, numbers, and underscores');
        }
        
        // Validate username length
        if (strlen($userData['username']) < 3 || strlen($userData['username']) > 50) {
            throw new Exception('Username must be between 3 and 50 characters');
        }
        
        // Validate password strength
        if (strlen($userData['password']) < 8) {
            throw new Exception('Password must be at least 8 characters long');
        }
    }
    
    /**
     * Create default preferences for user
     */
    private function createDefaultPreferences(User $user): UserPreference
    {
        return UserPreference::create([
            'user_id' => $user->id,
            'theme' => 'auto',
            'timezone' => $_ENV['APP_TIMEZONE'] ?? 'America/New_York',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'notifications_enabled' => true,
            'email_notifications' => true
        ]);
    }
}
