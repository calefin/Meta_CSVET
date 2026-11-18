<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationLifeArea extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'consultation_id',
        'area',
        'odu_1_id',
        'odu_2_id',
        'odu_3_id',
        'notes',
    ];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function odu1(): BelongsTo
    {
        return $this->belongsTo(Odu::class, 'odu_1_id');
    }

    public function odu2(): BelongsTo
    {
        return $this->belongsTo(Odu::class, 'odu_2_id');
    }

    public function odu3(): BelongsTo
    {
        return $this->belongsTo(Odu::class, 'odu_3_id');
    }
}
