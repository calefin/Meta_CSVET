<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $fillable = [
        'user_id',
        'institution',
        'campus',
        'academic_level',
        'research_topic',
        'tcc_status',
        'phone',
        'registration',
        'terms_accepted',
        'terms_accepted_at',
        'status',
        'category',
        'activation_token',
        'activated_at',
        'telegram_chat_id',
        'telegram_link_token',
        'guide_seen_at',
        'cpf',
        'lattes_url',
        'period',
        'course_concluded_at',
        'last_semester_declared',
    ];

    protected $casts = [
        'terms_accepted' => 'boolean',
        'terms_accepted_at' => 'datetime',
        'activated_at' => 'datetime',
        'guide_seen_at' => 'datetime',
        'course_concluded_at' => 'datetime',
    ];

    /**
     * Mutator para normalizar automaticamente o tema de pesquisa do estudante.
     */
    public function setResearchTopicAttribute($value)
    {
        $this->attributes['research_topic'] = \App\Models\AcademicTitle::normalizeTitle($value);
    }

    protected static function booted()
    {
        static::saving(function ($student) {
            if ($student->isDirty('terms_accepted') && $student->terms_accepted) {
                $student->terms_accepted_at = now();
            }

            if ($student->isDirty('institution') && !empty($student->institution)) {
                $student->institution = self::normalizeInstitution($student->institution);
            }

            if ($student->isDirty('status') && $student->status === 'active' && empty($student->activated_at)) {
                $student->activated_at = now();
            }
        });

        static::saved(function ($student) {
            if (($student->wasRecentlyCreated || $student->isDirty('status')) && $student->status === 'active') {
                if ($student->tcc_status === 'needs_evaluation') {
                    $exists = \App\Models\DocumentSubmission::where('student_id', $student->id)
                        ->where('document_type', 'Concepção de TCC')
                        ->exists();
                    if (!$exists) {
                        // Pré-preenche a identificação básica (Campus/Período) a partir do
                        // cadastro do aluno, para o processo não nascer totalmente em branco.
                        // Os demais campos do tema (área, espécie, tipo, ideias) continuam
                        // obrigatórios no formulário e devem ser preenchidos pelo aluno.
                        //
                        // Status inicial é "Aguardando Tema do Aluno" (bola com o aluno), e não
                        // "Aguardando Definição de Tema" (bola com o professor) — senão o
                        // processo aparece na fila/notificações do orientador assim que o aluno
                        // é ativado, antes mesmo de ele preencher o formulário.
                        $prefill = array_filter([
                            'campus' => $student->campus,
                            'period' => $student->period,
                        ], fn ($v) => filled($v));

                        \App\Models\DocumentSubmission::create([
                            'student_id' => $student->id,
                            'title' => $student->research_topic ?: 'Tema a definir',
                            'document_type' => 'Concepção de TCC',
                            'status' => 'Aguardando Tema do Aluno',
                            'file_size_kb' => 0,
                            'metadata' => $prefill ?: null,
                        ]);
                    }
                }
            }
        });
    }

    /**
     * Normaliza nomes de universidades para manter consistência dos dados.
     */
    public static function normalizeInstitution(string $name): string
    {
        $normalized = trim($name);
        $upper = mb_strtoupper($normalized);

        $searchTerms = [
            'CRUZEIRO DO SUL',
            'UNICSUL',
            'CRUZEIRO',
        ];

        foreach ($searchTerms as $term) {
            if (mb_strpos($upper, $term) !== false) {
                return 'Universidade Cruzeiro do Sul';
            }
        }

        return $normalized;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function submissions()
    {
        return $this->hasMany(DocumentSubmission::class);
    }

    public function titles()
    {
        return $this->hasMany(AcademicTitle::class);
    }

    public function deadlines()
    {
        return $this->hasMany(Deadline::class);
    }

    public function telegramMessages()
    {
        return $this->hasMany(TelegramMessage::class);
    }

    public function certificateRequests()
    {
        return $this->hasMany(CertificateRequest::class);
    }

    public function semesterDeclarations()
    {
        return $this->hasMany(StudentSemesterDeclaration::class);
    }

    /**
     * Se a pesquisa semestral do aluno ainda não foi respondida para o
     * semestre corrente (e ele não concluiu o curso), o portal deve
     * bloquear o acesso até que ele responda.
     */
    public function needsSemesterSurvey(): bool
    {
        if ($this->course_concluded_at) {
            return false;
        }

        // Aluno externo (mentoria/tutoria) não cursa TCC/Estágio/IC com o professor —
        // nenhuma opção da pesquisa se aplica e a única saída ("Já concluí o curso")
        // bloqueia novos processos. O middleware o prendia na pesquisa logo após o login.
        if ($this->category === 'external') {
            return false;
        }

        return $this->last_semester_declared !== \App\Support\Semester::current();
    }

    /**
     * Calcula as informações de prazo trimestral de IC para o aluno.
     * Retorna um array com metadados do prazo e cores para exibição visual.
     */
    public function getICDeadlineInfo(): array
    {
        // Encontra o Termo de Aceite de IC do aluno (que inicia a vigência)
        $icAcceptance = \App\Models\DocumentSubmission::where('student_id', $this->id)
            ->where('document_type', 'Termo de Aceite de IC')
            ->first();

        if (!$icAcceptance) {
            return [
                'has_ic' => false,
                'status' => 'none',
                'days_remaining' => 0,
                'due_date' => null,
                'quarter' => '',
                'color' => 'gray',
                'label' => '-',
            ];
        }

        $metadata = $icAcceptance->metadata ?? [];
        $startDateStr = $metadata['ic_start_date'] ?? null;
        
        if (!$startDateStr) {
            $startDate = \Carbon\Carbon::parse($icAcceptance->created_at);
        } else {
            $startDate = \Carbon\Carbon::parse($startDateStr);
        }

        $now = now();
        $referenceDate = $startDate;
        
        // Calcula meses passados desde o início
        $monthsPassed = $referenceDate->diffInMonths($now);
        $currentQuarterIndex = (int) ($monthsPassed / 3);
        
        // Início e fim do trimestre atual
        $quarterStart = $referenceDate->copy()->addMonths($currentQuarterIndex * 3);
        $quarterEnd = $quarterStart->copy()->addMonths(3);
        
        $quarterName = ($currentQuarterIndex + 1) . 'º Trimestre';
        $daysRemaining = $now->diffInDays($quarterEnd, false);

        // Verifica se o aluno entregou um Relatório no trimestre atual
        $hasSubmittedReport = \App\Models\DocumentSubmission::where('student_id', $this->id)
            ->where('document_type', 'Relatório')
            ->where('created_at', '>=', $quarterStart)
            ->where('created_at', '<', $quarterEnd)
            ->whereIn('status', [
                'Aguardando Revisão do Professor', 
                'Processo Concluído - Versão Final Definida',
                'reviewing',
                'approved',
                'returned_signed'
            ])
            ->exists();

        // Caso o gatilho de alertas_disabled esteja marcado e a data seja deste trimestre
        $alertsDisabled = $metadata['alerts_disabled'] ?? false;
        $alertsDisabledAt = $metadata['alerts_disabled_at'] ?? null;
        
        if ($alertsDisabled && $alertsDisabledAt) {
            try {
                $disabledDate = \Carbon\Carbon::createFromFormat('d/m/Y - H:i:s', $alertsDisabledAt);
                if ($disabledDate->between($quarterStart, $quarterEnd)) {
                    $hasSubmittedReport = true;
                }
            } catch (\Exception $e) {
                // Ignore parsing errors
            }
        }

        if ($hasSubmittedReport) {
            return [
                'has_ic' => true,
                'status' => 'submitted',
                'days_remaining' => $daysRemaining,
                'due_date' => $quarterEnd,
                'quarter' => $quarterName,
                'color' => 'success',
                'label' => 'Entregue',
            ];
        }

        if ($daysRemaining < 0) {
            return [
                'has_ic' => true,
                'status' => 'overdue',
                'days_remaining' => $daysRemaining,
                'due_date' => $quarterEnd,
                'quarter' => $quarterName,
                'color' => 'danger',
                'label' => 'Atrasado (' . abs($daysRemaining) . 'd)',
            ];
        } elseif ($daysRemaining < 15) {
            return [
                'has_ic' => true,
                'status' => 'critical',
                'days_remaining' => $daysRemaining,
                'due_date' => $quarterEnd,
                'quarter' => $quarterName,
                'color' => 'danger',
                'label' => 'Crítico (' . $daysRemaining . 'd)',
            ];
        } elseif ($daysRemaining <= 30) {
            return [
                'has_ic' => true,
                'status' => 'warning',
                'days_remaining' => $daysRemaining,
                'due_date' => $quarterEnd,
                'quarter' => $quarterName,
                'color' => 'warning',
                'label' => 'Alerta (' . $daysRemaining . 'd)',
            ];
        } else {
            return [
                'has_ic' => true,
                'status' => 'on_time',
                'days_remaining' => $daysRemaining,
                'due_date' => $quarterEnd,
                'quarter' => $quarterName,
                'color' => 'success',
                'label' => 'Em dia (' . $daysRemaining . 'd)',
            ];
        }
    }
}

