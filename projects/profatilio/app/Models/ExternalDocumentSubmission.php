<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalDocumentSubmission extends Model
{
    /**
     * Chave ASCII estável => rótulo. Usar a chave (não o texto acentuado)
     * como valor de formulário evita depender da codificação UTF-8 do
     * cliente sobreviver intacta até o servidor.
     */
    public const DOCUMENT_TYPE_OPTIONS = [
        'plano_estagio' => 'Plano de Estágio',
        'termo_compromisso' => 'Termo de Compromisso de Estágio',
    ];

    public const DOCUMENT_TYPES = [
        'Plano de Estágio',
        'Termo de Compromisso de Estágio',
    ];

    public const STATUS_PENDING = 'Aguardando Análise';
    public const STATUS_RETURNED = 'Devolvido';
    public const STATUS_SIGNED = 'Assinado';

    protected $fillable = [
        'external_class_group_id',
        'external_class_participant_id',
        'document_type',
        'reference_month_start',
        'reference_month_end',
        'reference_year',
        'file_path',
        'original_filename',
        'status',
        'returned_file_path',
        'return_reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'reference_month_start' => 'integer',
        'reference_month_end' => 'integer',
        'reference_year' => 'integer',
    ];

    public function group()
    {
        return $this->belongsTo(ExternalClassGroup::class, 'external_class_group_id');
    }

    public function participant()
    {
        return $this->belongsTo(ExternalClassParticipant::class, 'external_class_participant_id');
    }

    public function referencePeriodLabel(): string
    {
        $months = ['', 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        $start = $months[$this->reference_month_start] ?? '';
        $end = $months[$this->reference_month_end] ?? '';

        if ($this->reference_month_start === $this->reference_month_end || !$this->reference_month_end) {
            return "{$start}/{$this->reference_year}";
        }

        return "{$start} a {$end}/{$this->reference_year}";
    }
}
