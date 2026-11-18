<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Desvio (decisão pontual diferente do protocolo) ou emenda (mudança de
 * método antes de a etapa começar, com nova versão do protocolo). Tudo entra
 * na seção "Deviations from protocol" do artigo.
 */
class SystematicReviewDeviation extends Model
{
    public const TYPES = [
        'deviation' => 'Desvio',
        'amendment' => 'Emenda (nova versão do protocolo)',
    ];

    protected $fillable = [
        'systematic_review_id',
        'type',
        'section',
        'description',
        'justification',
        'requested_by',
        'approved_at',
        'osf_update_ref',
        'protocol_version_after',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function systematicReview()
    {
        return $this->belongsTo(SystematicReview::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
