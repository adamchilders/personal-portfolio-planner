<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Helpers\DateTimeHelper;

class UserSession extends Model
{
    protected $table = 'user_sessions';

    // Disable automatic updated_at since we use last_activity
    const UPDATED_AT = 'last_activity';

    protected $fillable = [
        'user_id',
        'session_token',
        'expires_at',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'last_activity' => 'datetime'
    ];
    
    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    // Helper methods
    public function isExpired(): bool
    {
        return $this->expires_at < DateTimeHelper::now();
    }
    
    public function isValid(): bool
    {
        return !$this->isExpired();
    }
    
    public function extend(int $minutes = 120): void
    {
        $this->expires_at = DateTimeHelper::addMinutes($minutes);
        $this->save();
    }

    public function updateActivity(): void
    {
        $this->last_activity = DateTimeHelper::now();
        $this->save();
    }
    
    // Scopes
    public function scopeValid($query)
    {
        return $query->where('expires_at', '>', DateTimeHelper::now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', DateTimeHelper::now());
    }
    
    public function scopeByToken($query, string $token)
    {
        return $query->where('session_token', $token);
    }
    
    // Static methods
    public static function createForUser(User $user, ?string $ipAddress = null, ?string $userAgent = null): self
    {
        return self::create([
            'user_id' => $user->id,
            'session_token' => bin2hex(random_bytes(32)),
            'expires_at' => DateTimeHelper::addMinutes(120),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent
        ]);
    }
    
    public static function findByToken(string $token): ?self
    {
        return self::byToken($token)->valid()->first();
    }
    
    public static function cleanupExpired(): int
    {
        return self::expired()->delete();
    }
}
