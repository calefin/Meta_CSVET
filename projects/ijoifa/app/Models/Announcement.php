<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'admin_id',
        'title',
        'body',
        'target_group',
        'expires_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }
}
