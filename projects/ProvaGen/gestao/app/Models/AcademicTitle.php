<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicTitle extends Model
{
    use \App\Models\Concerns\BelongsToOwner;

    protected $fillable = [
        'student_id',
        'type', // TCC, IC
        'title',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function submissions()
    {
        return $this->hasMany(DocumentSubmission::class);
    }

    public function deadlines()
    {
        return $this->hasMany(Deadline::class);
    }

    /**
     * Projetos ainda não finalizados: TCC sem submissão "TCC" concluída, ou
     * IC sem o projeto arquivado ("Processo Concluído - Projeto Arquivado").
     * Usado pelo dashboard de andamento e pelos widgets de KPI de IC, para
     * não misturar projetos já encerrados na visão de "vigentes".
     */
    public function scopeVigente($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($qq) {
                $qq->where('type', 'TCC')
                    ->whereDoesntHave('submissions', function ($sq) {
                        $sq->where('document_type', 'TCC')
                            ->where(function ($s) {
                                $s->where('status', 'like', 'Processo Concluído%')
                                    ->orWhereIn('status', ['approved', 'returned_signed', 'Cancelado']);
                            });
                    });
            })->orWhere(function ($qq) {
                $qq->where('type', 'IC')
                    ->whereDoesntHave('submissions', function ($sq) {
                        $sq->where('document_type', 'Iniciação Científica')
                            ->where('status', 'Processo Concluído - Projeto Arquivado');
                    });
            });
        });
    }

    /**
     * Calcula em que mês da vigência de 12 meses (padrão IC) o projeto está,
     * a partir das datas de início/término registradas no Termo de Aceite de IC
     * (mesma fonte usada por getValidityAttribute()).
     */
    public function getIcMonthProgress(): array
    {
        $submission = $this->submissions()->where('document_type', 'Termo de Aceite de IC')->first();
        $startStr = $submission->metadata['ic_start_date'] ?? null;
        $endStr = $submission->metadata['ic_end_date'] ?? null;

        if (!$submission || !$startStr || !$endStr) {
            return [
                'month' => null,
                'total_months' => null,
                'percent' => null,
                'label' => 'Vigência não definida',
            ];
        }

        $start = \Carbon\Carbon::parse($startStr);
        $end = \Carbon\Carbon::parse($endStr);
        $now = now();

        // +1 dia arredonda o último mês parcial para cima (ex.: 01/08/2026 a
        // 31/07/2027 é uma vigência de 12 meses, não 11 — diffInMonths trunca).
        $totalMonths = max(1, (int) $start->diffInMonths($end->copy()->addDay()));

        if ($now->lessThan($start)) {
            $monthsPassed = 0;
        } else {
            $monthsPassed = min($totalMonths, (int) $start->diffInMonths($now) + 1);
        }

        $percent = (int) round(($monthsPassed / $totalMonths) * 100);

        return [
            'month' => $monthsPassed,
            'total_months' => $totalMonths,
            'percent' => $percent,
            'label' => "Mês {$monthsPassed} de {$totalMonths}",
        ];
    }

    /**
     * Monta os 3 passos do pipeline de produção do TCC (Concepção -> Objetivos
     * -> Redação) a partir das submissões mais recentes de cada tipo para um
     * aluno. Método estático (por student_id) porque a "Concepção de TCC"
     * pode existir antes mesmo de haver um AcademicTitle (o título só é criado
     * quando o tema é aprovado) — usado tanto por TccPipelineWidget (aluno,
     * pode não ter título ainda) quanto por getTccSteps() abaixo (admin, já
     * com título criado).
     */
    public static function buildTccSteps(int $studentId): array
    {
        $stages = [
            'Concepção de TCC' => 'Concepção',
            'Objetivos de TCC' => 'Objetivos',
            'TCC' => 'Redação do TCC',
        ];

        $steps = [];
        foreach ($stages as $documentType => $label) {
            $submission = DocumentSubmission::where('student_id', $studentId)
                ->where('document_type', $documentType)
                ->latest('updated_at')
                ->first();

            if (!$submission) {
                $state = 'locked';
                $caption = 'Ainda não iniciado';
            } elseif ($submission->isConcluded()) {
                $state = 'done';
                $caption = 'Concluído';
            } else {
                $state = 'current';
                $caption = $submission->canStudentEdit() ? 'Aguardando você' : 'Em análise pelo orientador';
            }

            $steps[] = [
                'label' => $label,
                'state' => $state,
                'caption' => $caption,
                'submission' => $submission,
            ];
        }

        return $steps;
    }

    /**
     * As 5 etapas de redação do TCC (Introdução, Materiais e Métodos,
     * Resultados, Discussão, Conclusão). Desde 2026-08-24 elas não têm mais
     * ordem obrigatória entre si — o aluno pode enviar qualquer uma, em
     * qualquer ordem, assim que os Objetivos de TCC estiverem aprovados. O
     * orientador corrige cada uma independentemente ("em paralelo").
     * Resultados é a única opcional (nem todo TCC tem resultados
     * experimentais — ex.: revisão de literatura).
     * Fonte única de verdade tanto para o checklist do Dashboard
     * (TccPipelineWidget) quanto para a trava de "todas aprovadas" que libera
     * a versão final (DocumentSubmissionResource), evitando duplicar a lista.
     */
    public static function buildTccWritingSegments(int $studentId): array
    {
        $segments = [
            'Introdução de TCC' => ['label' => 'Introdução', 'optional' => false, 'approved_status' => 'Processo Concluído - Introdução Aprovada'],
            'Materiais e Métodos de TCC' => ['label' => 'Materiais e Métodos', 'optional' => false, 'approved_status' => 'Processo Concluído - Materiais e Métodos Aprovados'],
            'Resultados de TCC' => ['label' => 'Resultados', 'optional' => true, 'approved_status' => 'Processo Concluído - Resultados Aprovados'],
            'Discussão de TCC' => ['label' => 'Discussão', 'optional' => false, 'approved_status' => 'Processo Concluído - Discussão Aprovada'],
            'Conclusão de TCC' => ['label' => 'Conclusão', 'optional' => false, 'approved_status' => 'Processo Concluído - Conclusão Aprovada'],
        ];

        $result = [];
        foreach ($segments as $documentType => $meta) {
            $submission = DocumentSubmission::where('student_id', $studentId)
                ->where('document_type', $documentType)
                ->latest('updated_at')
                ->first();

            if (!$submission) {
                $state = 'not_started';
                $caption = $meta['optional'] ? 'Opcional — não enviado' : 'Ainda não enviado';
            } elseif ($submission->status === $meta['approved_status']) {
                $state = 'done';
                $caption = 'Aprovado';
            } elseif ($submission->status === 'Cancelado') {
                // isConcluded() também é true para "Cancelado" — tratado à parte
                // para não contar como aprovado e liberar o TCC indevidamente.
                $state = 'not_started';
                $caption = 'Cancelado — envie novamente';
            } else {
                $state = 'pending';
                $caption = $submission->canStudentEdit() ? 'Aguardando você' : 'Em análise pelo orientador';
            }

            $result[] = [
                'label' => $meta['label'],
                'document_type' => $documentType,
                'optional' => $meta['optional'],
                'state' => $state,
                'caption' => $caption,
                'submission' => $submission,
            ];
        }

        return $result;
    }

    /**
     * Rótulos das etapas de redação obrigatórias (Resultados é opcional) que
     * ainda não foram aprovadas — vazio quando a versão final (TCC) já pode
     * ser solicitada.
     */
    public static function missingTccWritingStages(int $studentId): array
    {
        return collect(self::buildTccWritingSegments($studentId))
            ->reject(fn (array $segment) => $segment['optional'])
            ->reject(fn (array $segment) => $segment['state'] === 'done')
            ->pluck('label')
            ->all();
    }

    /**
     * Etapas do pipeline de TCC para este projeto específico.
     */
    public function getTccSteps(): array
    {
        return self::buildTccSteps($this->student_id);
    }

    /**
     * Calcula o andamento do projeto a partir da submissão mais recente,
     * no mesmo espírito de Student::getICDeadlineInfo(). Usado para exibir
     * a coluna "Andamento" no Arquivo de Projetos e para identificar
     * orientandos parados (aguardando o aluno há muito tempo ou com
     * prazo manual vencido).
     */
    public function getProgressInfo(): array
    {
        $latest = $this->relationLoaded('submissions')
            ? $this->submissions->sortByDesc('updated_at')->first()
            : $this->submissions()->latest('updated_at')->first();

        if (!$latest) {
            return [
                'waiting_on' => null,
                'days_inactive' => 0,
                'is_stalled' => false,
                'stall_reason' => null,
                'label' => 'Sem submissões',
                'color' => 'gray',
            ];
        }

        $daysInactive = (int) now()->diffInDays($latest->updated_at, true);

        if ($latest->isConcluded()) {
            $waitingOn = 'concluido';
        } elseif ($latest->canStudentEdit()) {
            $waitingOn = 'aluno';
        } else {
            $waitingOn = 'professor';
        }

        // Só prazos de "cobrança" (entrega) entram no cálculo de parado; a
        // data de apresentação é informativa e não indica que o aluno deve algo.
        $overdueDeadline = $this->relationLoaded('deadlines')
            ? $this->deadlines->where('type', 'cobranca')->first(fn (Deadline $d) => $d->isOverdue())
            : $this->deadlines()->where('status', 'pending')->where('type', 'cobranca')->get()->first(fn (Deadline $d) => $d->isOverdue());

        $isStalled = $waitingOn === 'aluno' && ($daysInactive > 15 || $overdueDeadline !== null);

        $stallReason = null;
        if ($isStalled) {
            $stallReason = $overdueDeadline
                ? "Prazo \"{$overdueDeadline->title}\" vencido"
                : "Sem novas ações do aluno há {$daysInactive} dias";
        }

        $label = match (true) {
            $waitingOn === 'concluido' => 'Concluído',
            $isStalled => "Aguardando o aluno há {$daysInactive} dias",
            $waitingOn === 'aluno' => 'Aguardando o aluno',
            default => 'Aguardando o professor',
        };

        $color = match (true) {
            $waitingOn === 'concluido' => 'success',
            $isStalled => 'danger',
            $waitingOn === 'aluno' => 'warning',
            default => 'info',
        };

        return [
            'waiting_on' => $waitingOn,
            'days_inactive' => $daysInactive,
            'is_stalled' => $isStalled,
            'stall_reason' => $stallReason,
            'label' => $label,
            'color' => $color,
        ];
    }

    /**
     * Obtém a vigência do projeto a partir do Termo de Aceite de IC.
     */
    public function getValidityAttribute()
    {
        $submission = $this->submissions()
            ->where('document_type', 'Termo de Aceite de IC')
            ->first();
            
        if ($submission && isset($submission->metadata['ic_start_date']) && isset($submission->metadata['ic_end_date'])) {
            $start = \Carbon\Carbon::parse($submission->metadata['ic_start_date'])->format('d/m/Y');
            $end = \Carbon\Carbon::parse($submission->metadata['ic_end_date'])->format('d/m/Y');
            return "{$start} a {$end}";
        }
        
        return 'N/A';
    }

    /**
     * Obtém o tipo de bolsa a partir do Termo de Aceite de IC.
     */
    public function getScholarshipTypeAttribute()
    {
        $submission = $this->submissions()
            ->where('document_type', 'Termo de Aceite de IC')
            ->first();
            
        return $submission->metadata['ic_scholarship_type'] ?? 'N/A';
    }

    /**
     * Normaliza títulos acadêmicos / temas de pesquisa (Sentence Case inteligente, mantendo siglas).
     */
    public static function normalizeTitle(?string $title): string
    {
        if (empty($title)) {
            return '';
        }

        // Remove espaços duplicados e nas bordas
        $title = trim($title);
        $title = preg_replace('/\s+/', ' ', $title);

        if (empty($title)) {
            return '';
        }

        // Se o título estiver inteiramente em letras maiúsculas, converte primeiro para minúsculas
        if (mb_strtoupper($title, 'UTF-8') === $title) {
            $title = mb_strtolower($title, 'UTF-8');
        }

        // Capitaliza a primeira letra de todo o título
        $firstLetter = mb_substr($title, 0, 1, 'UTF-8');
        $remainder = mb_substr($title, 1, null, 'UTF-8');
        $title = mb_strtoupper($firstLetter, 'UTF-8') . $remainder;

        // Capitaliza a primeira letra após dois pontos (ex: "Título: Subtítulo")
        if (str_contains($title, ':')) {
            $parts = explode(':', $title);
            $normalizedParts = [];
            foreach ($parts as $index => $part) {
                $part = trim($part);
                if ($index > 0 && !empty($part)) {
                    $firstL = mb_substr($part, 0, 1, 'UTF-8');
                    $rem = mb_substr($part, 1, null, 'UTF-8');
                    $part = mb_strtoupper($firstL, 'UTF-8') . $rem;
                }
                $normalizedParts[] = $part;
            }
            $title = implode(': ', $normalizedParts);
        }

        // Normaliza siglas comuns conhecidas para letras maiúsculas
        $acronyms = [
            'tcc' => 'TCC',
            'ic' => 'IC',
            'ceua' => 'CEUA',
            'cep' => 'CEP',
            'usp' => 'USP',
            'lgpd' => 'LGPD',
            'abnt' => 'ABNT',
            'rgm' => 'RGM',
            'tcc:' => 'TCC:',
            'ic:' => 'IC:',
        ];

        $words = explode(' ', $title);
        foreach ($words as &$word) {
            $cleanWord = mb_strtolower(trim($word, '.,:;()'), 'UTF-8');
            if (isset($acronyms[$cleanWord])) {
                $punctuation = preg_replace('/[a-zA-Z0-9áéíóúâêîôûãõçÁÉÍÓÚÂÊÎÔÛÃÕÇ]/u', '', $word);
                $word = $acronyms[$cleanWord] . $punctuation;
            }
        }
        $title = implode(' ', $words);

        return $title;
    }

    /**
     * Mutator para normalizar automaticamente o título ao salvar.
     */
    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = self::normalizeTitle($value);
    }

    protected static function booted()
    {
        // Imutabilidade de Títulos Vinculados:
        // "O aluno pode adicionar novos títulos, mas ele não pode editar ou apagar um título que já possui um processo ativo ou concluído."
        static::updating(function ($title) {
            if ($title->submissions()->count() > 0) {
                throw new \Exception("Não é permitido editar um título que possui processos ativos ou concluídos vinculados.");
            }
        });

        static::deleting(function ($title) {
            if ($title->submissions()->count() > 0) {
                throw new \Exception("Não é permitido apagar um título que possui processos ativos ou concluídos vinculados.");
            }
        });
    }
}
