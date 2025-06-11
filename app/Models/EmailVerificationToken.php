<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailVerificationToken extends Model
{
    protected $table = 'email_verification_tokens';
    
    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'verified_at'
    ];
    
    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
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
    
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
    
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isVerified();
    }
    
    public function markAsVerified(): void
    {
        $this->verified_at = now();
        $this->save();
    }
}
