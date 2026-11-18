<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Linha da tabela de resumo dos achados (GRADE) por desfecho. */
class SrGradeRow extends Model
{
    public const CERTAINTY = [
        'high' => 'Alta ⊕⊕⊕⊕',
        'moderate' => 'Moderada ⊕⊕⊕◯',
        'low' => 'Baixa ⊕⊕◯◯',
        'very_low' => 'Muito baixa ⊕◯◯◯',
    ];

    public const DOMAINS = [
        'rob' => 'Risco de viés',
        'inconsistency' => 'Inconsistência',
        'indirectness' => 'Evidência indireta',
        'imprecision' => 'Imprecisão',
        'pubbias' => 'Viés de publicação',
    ];

    protected $table = 'sr_grade_rows';

    protected $fillable = ['systematic_review_id', 'outcome_id', 'outcome_name', 'k', 'n', 'effect', 'ci', 'pi', 'certainty', 'reasons', 'notes'];

    protected $casts = ['reasons' => 'array'];

    public function systematicReview()
    {
        return $this->belongsTo(SystematicReview::class);
    }
}
