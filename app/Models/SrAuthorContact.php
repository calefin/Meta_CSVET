<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pedido de dados a autores (envio, lembretes em 2 e 4 semanas, resposta). */
class SrAuthorContact extends Model
{
    protected $table = 'sr_author_contacts';

    protected $fillable = ['systematic_review_id', 'sr_study_id', 'author', 'email', 'request', 'sent_at', 'reminder1_at', 'reminder2_at', 'responded_at', 'response'];

    protected $casts = [
        'sent_at' => 'date',
        'reminder1_at' => 'date',
        'reminder2_at' => 'date',
        'responded_at' => 'date',
    ];

    public function study()
    {
        return $this->belongsTo(SrStudy::class, 'sr_study_id');
    }

    /** Próximo lembrete devido (2 e 4 semanas após o envio), se ainda sem resposta. */
    public function nextReminder(): ?string
    {
        if ($this->responded_at) {
            return null;
        }
        if (!$this->reminder1_at) {
            return $this->sent_at->copy()->addWeeks(2)->isPast() ? 'Lembrete 1 (2 semanas) vencido' : null;
        }
        if (!$this->reminder2_at) {
            return $this->sent_at->copy()->addWeeks(4)->isPast() ? 'Lembrete 2 (4 semanas) vencido' : null;
        }

        return null;
    }
}
