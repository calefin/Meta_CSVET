<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class InviteToken extends Model
{
    use Prunable, \App\Models\Concerns\BelongsToOwner;

    protected $fillable = [
        'token',
        'email',
        'category',
        'used_at',
        'expires_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($inviteToken) {
            if (empty($inviteToken->token)) {
                $inviteToken->token = bin2hex(random_bytes(16));
            }
        });
    }

    /**
     * Get the prunable model query.
     */
    public function prunable(): \Illuminate\Database\Eloquent\Builder
    {
        return static::where('expires_at', '<=', now()->subDays(30))
            ->whereNull('used_at');
    }
}
