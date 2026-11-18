<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Voto de um triador sobre um registro numa etapa. Só o próprio triador e o
 * professor enxergam — a triagem é cega entre os alunos.
 */
class SystematicReviewDecision extends Model
{
    protected $fillable = [
        'systematic_review_record_id',
        'systematic_review_screener_id',
        'stage',
        'decision',
        'reason',
        'note',
        'checklist',
    ];

    protected $casts = [
        'checklist' => 'array',
    ];

    public function record()
    {
        return $this->belongsTo(SystematicReviewRecord::class, 'systematic_review_record_id');
    }

    public function screener()
    {
        return $this->belongsTo(SystematicReviewScreener::class, 'systematic_review_screener_id');
    }
}
