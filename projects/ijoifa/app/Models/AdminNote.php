<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminNote extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'admin_id',
        'target_user_id',
        'category',
        'body',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
        ];
    }

    /**
     * Notes become immutable 24h after creation (§10.3 of the spec).
     */
    public function isLocked(): bool
    {
        return $this->locked_at !== null || $this->created_at?->addHours(24)->isPast();
    }

    protected static function booted(): void
    {
        static::updating(function (AdminNote $note) {
            if ($note->isLocked()) {
                throw new \RuntimeException('Esta anotacao esta bloqueada para edicao apos 24h da criacao.');
            }
        });
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
