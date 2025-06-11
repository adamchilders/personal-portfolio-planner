<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Model
{
    protected $table = 'users';
    
    protected $fillable = [
        'username',
        'email',
        'password_hash',
        'role',
        'first_name',
        'last_name',
        'is_active',
        'email_verified_at'
    ];
    
    protected $hidden = [
        'password_hash'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_login' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    // Relationships
    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }
    
    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }
    
    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }
    
    public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class);
    }
    
    public function passwordResetTokens(): HasMany
    {
        return $this->hasMany(PasswordResetToken::class);
    }
    
    public function emailVerificationTokens(): HasMany
    {
        return $this->hasMany(EmailVerificationToken::class);
    }
    
    // Helper methods
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
    
    public function isActive(): bool
    {
        return $this->is_active;
    }
    
    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }
    
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
    
    public function getDisplayNameAttribute(): string
    {
        $fullName = $this->getFullNameAttribute();
        return !empty($fullName) ? $fullName : $this->username;
    }
    
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password_hash);
    }
    
    public function setPassword(string $password): void
    {
        $this->password_hash = password_hash($password, PASSWORD_DEFAULT);
    }
    
    public function updateLastLogin(): void
    {
        $this->last_login = new \DateTime();
        $this->save();
    }
    
    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }
    
    public function scopeUsers($query)
    {
        return $query->where('role', 'user');
    }
    
    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }
}
