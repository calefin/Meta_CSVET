<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Estudo incluído (um ou mais relatos/registros agrupados). Recebe duas
 * extrações independentes e a versão reconciliada que vai para a base travada.
 */
class SrStudy extends Model
{
    protected $table = 'sr_studies';

    protected $fillable = [
        'systematic_review_id', 'label', 'record_ids', 'position', 'pilot', 'status',
        'reconciled_values', 'reconciled_outcomes', 'reconciled_rob', 'reconciled_at', 'reconciled_by', 'reconcile_notes', 'notes',
    ];

    protected $casts = [
        'record_ids' => 'array',
        'pilot' => 'boolean',
        'reconciled_values' => 'array',
        'reconciled_outcomes' => 'array',
        'reconciled_rob' => 'array',
        'reconciled_at' => 'datetime',
    ];

    public function systematicReview()
    {
        return $this->belongsTo(SystematicReview::class);
    }

    public function extractions()
    {
        return $this->hasMany(SrExtraction::class);
    }

    public function recordsQuery()
    {
        return SystematicReviewRecord::whereIn('id', $this->record_ids ?? []);
    }

    public function code(): string
    {
        return sprintf('STU-%04d', $this->id);
    }

    public function submittedCount(): int
    {
        return $this->extractions->where('status', SrExtraction::STATUS_SUBMITTED)->count();
    }

    public function isReconciled(): bool
    {
        return $this->reconciled_at !== null;
    }
}
