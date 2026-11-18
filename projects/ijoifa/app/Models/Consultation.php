<?php

namespace App\Models;

use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consultation extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'request_id',
        'user_id',
        'admin_id',
        'consulted_at',
        'modality',
        'odu_main_id',
        'odu_witness_1_id',
        'odu_witness_2_id',
        'ire_ibi',
        'ire_ibi_type_id',
        'general_guidance',
        'created_by',
        'created_ip',
    ];

    /**
     * Odu and classification fields are locked once the consultation is saved
     * (see §10.4 of the spec: registro imutavel). Only general_guidance addendums
     * are allowed afterwards, recorded as separate dated entries.
     */
    private const LOCKED_AFTER_CREATE = [
        'odu_main_id',
        'odu_witness_1_id',
        'odu_witness_2_id',
        'ire_ibi',
        'ire_ibi_type_id',
    ];

    protected function casts(): array
    {
        return [
            'consulted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Consultation $consultation) {
            Audit::record('consultation.create', $consultation);
        });

        static::updating(function (Consultation $consultation) {
            foreach (self::LOCKED_AFTER_CREATE as $field) {
                if ($consultation->isDirty($field)) {
                    Audit::record('consultation.blocked_edit_attempt', $consultation);

                    throw new \RuntimeException("O campo '{$field}' e imutavel apos o registro da consulta.");
                }
            }
        });
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ConsultationRequest::class, 'request_id');
    }

    public function consulente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function oduMain(): BelongsTo
    {
        return $this->belongsTo(Odu::class, 'odu_main_id');
    }

    public function oduWitness1(): BelongsTo
    {
        return $this->belongsTo(Odu::class, 'odu_witness_1_id');
    }

    public function oduWitness2(): BelongsTo
    {
        return $this->belongsTo(Odu::class, 'odu_witness_2_id');
    }

    public function ireIbiType(): BelongsTo
    {
        return $this->belongsTo(IreIbiType::class);
    }

    public function guidanceAddendums(): HasMany
    {
        return $this->hasMany(ConsultationGuidanceAddendum::class);
    }

    public function lifeAreas(): HasMany
    {
        return $this->hasMany(ConsultationLifeArea::class);
    }

    public function recommendedWorks(): HasMany
    {
        return $this->hasMany(RecommendedWork::class);
    }
}
