<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Extração + risco de viés de UM revisor para UM estudo (cega até a reconciliação). */
class SrExtraction extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';

    public const ROB_JUDGEMENTS = [
        'low' => 'Baixo',
        'some' => 'Algumas preocupações',
        'high' => 'Alto',
        'unclear' => 'Incerto (não relatado)',
    ];

    protected $table = 'sr_extractions';

    protected $fillable = [
        'sr_study_id', 'systematic_review_screener_id', 'form_version', 'field_values', 'sources', 'outcomes', 'rob', 'notes', 'status', 'submitted_at',
        'ai_prefill', 'ai_prefilled_at', 'ai_verified_at', 'ai_changes',
    ];

    protected $casts = [
        'field_values' => 'array',
        'sources' => 'array',
        'outcomes' => 'array',
        'rob' => 'array',
        'submitted_at' => 'datetime',
        'ai_prefill' => 'array',
        'ai_prefilled_at' => 'datetime',
        'ai_verified_at' => 'datetime',
        'ai_changes' => 'array',
    ];

    /** Pré-preenchido por modelo de linguagem (só o Revisor 1, protocolo v1.1). */
    public function aiPrefilled(): bool
    {
        return !empty($this->ai_prefill);
    }

    public function study()
    {
        return $this->belongsTo(SrStudy::class, 'sr_study_id');
    }

    public function screener()
    {
        return $this->belongsTo(SystematicReviewScreener::class, 'systematic_review_screener_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }
}
