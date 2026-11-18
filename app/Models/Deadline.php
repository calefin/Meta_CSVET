<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deadline extends Model
{
    /**
     * Dias antes da apresentação em que um aviso de contagem regressiva é
     * disparado (uma única vez cada), para prazos do tipo 'apresentacao'.
     */
    public const PRESENTATION_MILESTONES = [30, 15, 7, 3, 1, 0];

    protected $fillable = [
        'student_id',
        'academic_title_id',
        'title',
        'type',
        'due_date',
        'recurrence_days',
        'status',
        'last_charged_at',
        'notified_milestones',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'last_charged_at' => 'datetime',
        'notified_milestones' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicTitle()
    {
        return $this->belongsTo(AcademicTitle::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApresentacao(): bool
    {
        return $this->type === 'apresentacao';
    }

    public function isOverdue(): bool
    {
        return $this->isPending() && $this->due_date && $this->due_date->isPast();
    }

    /**
     * Determina se já é hora de disparar uma nova cobrança: o prazo venceu e
     * (nunca foi cobrado, ou já passou o intervalo de recorrência desde a última cobrança).
     * Prazos sem recorrência (`recurrence_days` nulo) só cobram uma vez.
     * Não se aplica a prazos do tipo 'apresentacao' (ver getNextMilestone()).
     */
    public function isDueForCharge(): bool
    {
        if ($this->isApresentacao()) {
            return false;
        }

        if (!$this->isOverdue()) {
            return false;
        }

        if (!$this->last_charged_at) {
            return true;
        }

        if (!$this->recurrence_days) {
            return false;
        }

        return $this->last_charged_at->addDays($this->recurrence_days)->isPast();
    }

    /**
     * Para prazos do tipo 'apresentacao': retorna o próximo marco de contagem
     * regressiva (em dias) que ainda não foi avisado e já foi atingido, ou
     * null se nenhum aviso estiver pendente agora.
     */
    public function getNextMilestone(): ?int
    {
        if (!$this->isApresentacao() || !$this->isPending() || !$this->due_date) {
            return null;
        }

        $daysRemaining = (int) now()->startOfDay()->diffInDays($this->due_date->copy()->startOfDay(), false);

        if ($daysRemaining < 0) {
            return null;
        }

        $notified = $this->notified_milestones ?? [];

        foreach (self::PRESENTATION_MILESTONES as $milestone) {
            if ($daysRemaining <= $milestone && !in_array($milestone, $notified)) {
                return $milestone;
            }
        }

        return null;
    }
}
