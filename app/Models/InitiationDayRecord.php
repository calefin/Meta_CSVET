<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InitiationDayRecord extends Model
{
    protected $fillable = [
        'initiation_id',
        'day_label',
        'odu_id',
        'notes',
        'order',
    ];

    public function initiation(): BelongsTo
    {
        return $this->belongsTo(Initiation::class);
    }

    public function odu(): BelongsTo
    {
        return $this->belongsTo(Odu::class);
    }
}
