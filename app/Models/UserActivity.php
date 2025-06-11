<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivity extends Model
{
    protected $table = 'user_activity';
    public $timestamps = false;
    
    protected $fillable = [
        'user_id',
        'action',
        'resource_type',
        'resource_id',
        'details',
        'ip_address',
        'user_agent'
    ];
    
    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime'
    ];
    
    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    // Static methods for logging activities
    public static function log(int $userId, string $action, string $resourceType = null, int $resourceId = null, array $details = [], string $ipAddress = null, string $userAgent = null): self
    {
        return self::create([
            'user_id' => $userId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'details' => $details,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => now()
        ]);
    }
}
