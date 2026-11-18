<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Initiation extends Model
{
    protected $fillable = [
        'user_id',
        'odu_id',
        'spiritual_name',
        'initiation_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'initiation_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function odu(): BelongsTo
    {
        return $this->belongsTo(Odu::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dayRecords(): HasMany
    {
        return $this->hasMany(InitiationDayRecord::class)->orderBy('order');
    }

    public function ewos(): HasMany
    {
        return $this->hasMany(InitiationEwo::class);
    }
}
