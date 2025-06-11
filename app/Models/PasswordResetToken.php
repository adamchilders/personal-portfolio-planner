<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordResetToken extends Model
{
    protected $table = 'password_reset_tokens';
    
    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'used_at'
    ];
    
    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'created_at' => 'datetime'
    ];
    
    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    // Helper methods
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
    
    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }
    
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }
    
    public function markAsUsed(): void
    {
        $this->used_at = now();
        $this->save();
    }
}
