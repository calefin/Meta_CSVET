<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ConsultationRequest extends Model
{
    protected $fillable = [
        'user_id',
        'modality',
        'description',
        'attachment_path',
        'status',
        'draft_data',
    ];

    protected function casts(): array
    {
        return [
            'draft_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function consultation(): HasOne
    {
        return $this->hasOne(Consultation::class, 'request_id');
    }
}
