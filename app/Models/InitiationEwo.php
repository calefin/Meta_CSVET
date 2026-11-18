<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InitiationEwo extends Model
{
    protected $fillable = [
        'initiation_id',
        'description',
    ];

    public function initiation(): BelongsTo
    {
        return $this->belongsTo(Initiation::class);
    }
}
