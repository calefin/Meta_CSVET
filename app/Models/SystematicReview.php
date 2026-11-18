<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Projeto de revisão sistemática / metanálise. O professor é o gerente:
 * monta o protocolo, registra (OSF), importa os registros das bases, escolhe
 * os triadores, desempata os conflitos e encerra cada etapa. Os alunos só
 * fazem a triagem cega pelo portal, depois de assinar o termo.
 */
class SystematicReview extends Model
{
    public const STATUS_PROTOCOL = 'protocol';
    public const STATUS_SCREENING = 'screening';
    public const STATUS_CONFLICTS = 'conflicts';
    public const STATUS_SCREENING_DONE = 'screening_done';
    public const STATUS_ARCHIVED = 'archived';

    public const STATUS_LABELS = [
        self::STATUS_PROTOCOL => 'Protocolo / preparação',
        self::STATUS_SCREENING => 'Em triagem (alunos)',
        self::STATUS_CONFLICTS => 'Conflitos com você',
        self::STATUS_SCREENING_DONE => 'Triagem concluída',
        self::STATUS_ARCHIVED => 'Arquivado',
    ];

    public const STAGE_TA = 'title_abstract';
    public const STAGE_FT = 'full_text';

    public const STAGE_LABELS = [
        self::STAGE_TA => 'Título e resumo',
        self::STAGE_FT => 'Texto completo',
    ];

    public const REVIEW_TYPES = [
        'meta_analysis' => 'Revisão sistemática com metanálise',
        'systematic_review' => 'Revisão sistemática (sem metanálise)',
        'scoping' => 'Revisão de escopo (scoping review)',
        'diagnostic' => 'Metanálise de acurácia diagnóstica',
        'prevalence' => 'Metanálise de prevalência/proporção',
    ];

    public const FRAMEWORKS = [
        'PICO' => 'PICO — População, Intervenção, Comparação, Desfecho',
        'PECO' => 'PECO — População, Exposição, Comparação, Desfecho',
        'CoCoPop' => 'CoCoPop — Condição, Contexto, População (prevalência)',
        'PIRD' => 'PIRD — População, Teste índice, Referência, Diagnóstico',
        'PCC' => 'PCC — População, Conceito, Contexto (escopo)',
    ];

    /** Campos do protocolo (PRISMA-P), na ordem em que aparecem no documento. */
    public const PROTOCOL_FIELDS = [
        'title_en' => 'Title (English)',
        'background' => 'Rationale / Background',
        'question' => 'Review question',
        'population' => 'Population / Condition',
        'intervention' => 'Intervention / Exposure / Index test / Concept',
        'comparator' => 'Comparator / Reference standard / Context',
        'outcomes' => 'Outcomes',
        'study_designs' => 'Eligible study designs',
        'inclusion_criteria' => 'Inclusion criteria',
        'exclusion_criteria' => 'Exclusion criteria',
        'databases' => 'Information sources',
        'search_string' => 'Search strategy',
        'limits' => 'Limits (language, dates)',
        'screening_method' => 'Study selection',
        'data_items' => 'Data extraction items',
        'rob_tool' => 'Risk of bias assessment',
        'synthesis' => 'Data synthesis / Meta-analysis plan',
        'heterogeneity' => 'Heterogeneity, subgroups and sensitivity analyses',
        'certainty' => 'Certainty of evidence / Publication bias',
    ];

    public const DEFAULT_EXCLUSION_REASONS = [
        'População errada',
        'Intervenção/exposição errada',
        'Desenho de estudo inelegível',
        'Sem dados quantitativos',
        'Relato de caso isolado (n < 5)',
        'Somente in vitro',
        'Revisão/editorial/carta',
        'Idioma não elegível',
        'Duplicata não detectada',
    ];

    /** Fases do POP (painel do professor) — base; ajustada por tipo em pipeline(). */
    public const PIPELINE = [
        'protocol' => 'Fase 0 — Protocolo PRISMA-P escrito (PICO + critérios + sintaxe)',
        'pilot_search' => 'Fase 0 — Teste piloto da busca no PubMed (artigos clássicos aparecem?)',
        'registered' => 'Fase 0 — Protocolo registrado no OSF (DOI) ANTES da triagem',
        'search_done' => 'Fase 1 — Buscas em ≥ 2 bases bibliográficas executadas e exportadas',
        'grey_lit' => 'Fase 1 — Literatura cinzenta (Google Scholar/Publish or Perish, teses, anais) e listas de referências',
        'dedup_done' => 'Fase 1 — Duplicatas removidas e registros importados aqui',
        'ta_done' => 'Fase 2 — Triagem de título/resumo em dupla cega + conflitos',
        'ft_done' => 'Fase 3 — Leitura do texto completo (elegibilidade) em dupla',
        'extraction' => 'Fase 3 — Extração de dados em DUPLA independente na planilha padronizada',
        'rob' => 'Fase 3 — Risco de viés em dupla (RoB 2 / ROBINS-I / SYRCLE / JBI / QUIPS / NOS)',
        'analysis' => 'Fase 4 — Metanálise no R (forest, funnel, Egger, leave-one-out)',
        'certainty' => 'Fase 4 — Certeza da evidência (GRADE) e viés de publicação',
        'search_update' => 'Fase 5 — Busca atualizada (a última busca deve ter ≤ 12 meses na submissão)',
        'data_deposit' => 'Fase 5 — Planilha de extração, scripts R e lista de excluídos depositados com DOI (OSF/Zenodo)',
        'manuscript' => 'Fase 5 — Manuscrito PRISMA 2020 (em inglês) + checklist com páginas/linhas',
        'submitted' => 'Fase 6 — Submetido à revista',
    ];

    /** Diretriz de relato por tipo de revisão. */
    public const GUIDELINES = [
        'meta_analysis' => 'PRISMA 2020',
        'systematic_review' => 'PRISMA 2020 + SWiM',
        'scoping' => 'PRISMA-ScR',
        'diagnostic' => 'PRISMA-DTA',
        'prevalence' => 'PRISMA 2020 (+ MOOSE)',
    ];

    public const REGISTRATION_PLATFORMS = [
        'OSF' => 'OSF Registries',
        'PROSPERO' => 'PROSPERO',
        'Research Registry' => 'Research Registry',
        'Animal Study Registry' => 'Animal Study Registry',
        'INPLASY' => 'INPLASY',
    ];

    protected $fillable = [
        'title',
        'short_name',
        'review_type',
        'framework',
        'is_training',
        'training_closes_at',
        'status',
        'stage',
        'protocol',
        'exclusion_reasons',
        'highlight_terms',
        'screener_instructions',
        'pilot_size',
        'pilot_released_at',
        'pilot_rounds',
        'registration_platform',
        'registration_id',
        'registered_at',
        'embargo_until',
        'registration_waiver',
        'search_date',
        'search_counts',
        'screening_checklist',
        'screening_tips',
        'exclusion_reasons_pt',
        'checklist',
        'screening_started_at',
        'stage_closed_at',
        'history',
        'notes',
        'protocol_code',
        'folder',
        'family',
        'pipeline',
        'protocol_version',
        'protocol_sha256',
        'protocol_frozen_at',
        'protocol_pdf_path',
        'protocol_pdf_sha256',
        'osf_project_url',
        'extraction_opened_at',
        'extraction_pilot_size',
        'extraction_pilot_released_at',
        'extraction_form_version',
        'manuscript',
        'ta_qc',
        'ai_extraction',
    ];

    protected $attributes = [
        'status' => self::STATUS_PROTOCOL,
        'stage' => self::STAGE_TA,
        'review_type' => 'meta_analysis',
        'framework' => 'PICO',
    ];

    protected $casts = [
        'is_training' => 'boolean',
        'training_closes_at' => 'datetime',
        'protocol' => 'array',
        'exclusion_reasons' => 'array',
        'exclusion_reasons_pt' => 'array',
        'highlight_terms' => 'array',
        'screening_checklist' => 'array',
        'search_counts' => 'array',
        'checklist' => 'array',
        'stage_closed_at' => 'array',
        'history' => 'array',
        'pilot_size' => 'integer',
        'pilot_released_at' => 'datetime',
        'pilot_rounds' => 'array',
        'registered_at' => 'date',
        'embargo_until' => 'date',
        'search_date' => 'date',
        'screening_started_at' => 'datetime',
        'pipeline' => 'array',
        'protocol_frozen_at' => 'datetime',
        'extraction_opened_at' => 'datetime',
        'extraction_pilot_released_at' => 'datetime',
        'extraction_pilot_size' => 'integer',
        'manuscript' => 'array',
        'ta_qc' => 'array',
        'ai_extraction' => 'array',
    ];

    public const DEFAULT_INSTRUCTIONS = "1. Leia o título e o resumo de cada trabalho e decida SOZINHO(A), seguindo os critérios do protocolo abaixo.\n"
        . "2. Incluir: atende à pergunta (população, intervenção/exposição e desfecho) — ou pode atender e só o texto completo dirá.\n"
        . "3. Excluir: claramente não atende. Escolha SEMPRE o motivo.\n"
        . "4. Talvez: na dúvida real, marque Talvez (o professor decide). Não use Talvez para ganhar tempo.\n"
        . "5. Não converse com o outro triador sobre as decisões até o professor encerrar a etapa — a triagem é cega e a concordância é medida.\n"
        . "6. Dá para voltar e mudar uma decisão até clicar em \"Concluir minha triagem\".";

    protected static function booted(): void
    {
        static::saving(function (SystematicReview $review) {
            if (blank($review->short_name)) {
                $review->short_name = Str::limit($review->title, 60, '');
            }
        });
    }

    public function records()
    {
        return $this->hasMany(SystematicReviewRecord::class)->orderBy('seq');
    }

    public function screeners()
    {
        return $this->hasMany(SystematicReviewScreener::class)->orderBy('id');
    }

    public function activeScreeners()
    {
        return $this->screeners()->whereNull('removed_at');
    }

    /** Conversa registrada do projeto. */
    public function messages()
    {
        return $this->hasMany(SrMessage::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Prefixo da seleção de alunos → deslocamento do número do protocolo.
     * PET-01…10 = P01–P10; PAT-01…10 = P11–P20; AQU-01…10 = P21–P30 (peixes e anfíbios);
     * BEM-01…10 = P31–P40 (etologia e bem-estar).
     */
    public const SELECTION_PREFIXES = ['PET' => 0, 'PAT' => 10, 'AQU' => 20, 'BEM' => 30];

    /** Código do projeto na seleção de alunos (P01 → PET-01; P21 → AQU-01; P31 → BEM-01). */
    public function selectionCode(): ?string
    {
        return self::selectionCodeFor((int) substr((string) $this->protocol_code, 1));
    }

    /**
     * Código que o professor e os alunos usam para falar do projeto: "PET-01 · P01".
     * O P01 não revela o tema (só a numeração do protocolo); facilita a comunicação.
     */
    public function displayCode(): ?string
    {
        $codes = array_values(array_filter([$this->selectionCode(), $this->is_training ? null : $this->protocol_code]));

        return $codes ? implode(' · ', $codes) : null;
    }

    public static function selectionCodeFor(int $n): ?string
    {
        foreach (array_reverse(self::SELECTION_PREFIXES, true) as $prefix => $offset) {
            if ($n > $offset && $n <= $offset + 10) {
                return sprintf('%s-%02d', $prefix, $n - $offset);
            }
        }

        return null;
    }

    /** "PET-01" → "P01", "AQU-03" → "P23"; null se não for código de seleção. */
    public static function protocolCodeFor(string $selectionCode): ?string
    {
        if (!preg_match('/^(' . implode('|', array_keys(self::SELECTION_PREFIXES)) . ')-?0*(\d{1,2})$/i', trim($selectionCode), $m)) {
            return null;
        }
        $n = (int) $m[2];

        return $n >= 1 && $n <= 10 ? sprintf('P%02d', $n + self::SELECTION_PREFIXES[strtoupper($m[1])]) : null;
    }

    /** Ordem dos códigos nas listas: PET, PAT, AQU, BEM. */
    public static function selectionSortKey(string $code): string
    {
        $i = array_search(strtoupper(substr($code, 0, 3)), array_keys(self::SELECTION_PREFIXES), true);

        return ($i === false ? 9 : $i) . $code;
    }

    /**
     * Rótulo em português do motivo de exclusão (pelo código "E3" ou pelo texto inteiro).
     * Vem do pipeline (exclusion_reasons_pt) ou do admin; sem rótulo, null.
     */
    public function exclusionReasonPt(string $reason): ?string
    {
        $code = preg_match('/^(E\d+)(?!\d)/u', trim($reason), $m) ? $m[1] : null;
        $map = $this->exclusion_reasons_pt ?? [];

        return $code && filled($map[$code] ?? null) ? (string) $map[$code] : null;
    }

    /**
     * Revisor 1 (líder) e Revisor 2 (revisor cego) pelo rótulo da seleção;
     * sem rótulo, a ordem de inclusão.
     */
    public function leadScreener(): ?SystematicReviewScreener
    {
        $active = $this->activeScreeners()->get();

        return $active->first(fn ($s) => str_starts_with((string) $s->label, 'Líder')) ?? $active->first();
    }

    public function secondScreener(): ?SystematicReviewScreener
    {
        $active = $this->activeScreeners()->get();
        $lead = $this->leadScreener();

        return $active->first(fn ($s) => str_starts_with((string) $s->label, 'Revisor') && $s->id !== $lead?->id)
            ?? $active->first(fn ($s) => $s->id !== $lead?->id);
    }

    // ------------------------------------------------------------------
    // Protocolo v1.1: triagem priorizada por aprendizado ativo
    // ------------------------------------------------------------------

    /** O protocolo registrado prevê priorização (pipeline: screening.stages.0.prioritisation). */
    public function prioritisedScreening(): bool
    {
        return filled(data_get($this->pipeline, 'screening.stages.0.prioritisation'));
    }

    /**
     * Regra de parada registrada (valores do protocolo; padrão do SAFE adaptado).
     *
     * @return array{min_fraction: float, ratio: float, consecutive: int, validation: bool, qc_fraction: float, qc_min: int}
     */
    public function stoppingRule(): array
    {
        $r = (array) data_get($this->pipeline, 'screening.stages.0.prioritisation.stopping_rule', []);
        $qc = (array) data_get($this->pipeline, 'screening.stages.0.prioritisation.quality_check', []);

        return [
            'min_fraction' => (float) ($r['min_fraction_screened'] ?? 0.10),
            'ratio' => (float) ($r['min_screened_to_relevant_ratio'] ?? 2),
            'consecutive' => (int) ($r['consecutive_irrelevant'] ?? 100),
            'validation' => (bool) ($r['all_validation_set_found'] ?? true),
            'qc_fraction' => is_numeric($qc['fraction'] ?? null) ? (float) $qc['fraction'] : 0.10,
            'qc_min' => is_numeric($qc['minimum'] ?? null) ? (int) $qc['minimum'] : 100,
        ];
    }

    /** Checagem aleatória aberta agora (Revisor 2 tria a amostra do que ninguém leu). */
    public function qcOpen(): bool
    {
        return ($this->ta_qc['status'] ?? null) === 'open';
    }

    /** @return array<int, int> todos os registros que já entraram em alguma checagem aleatória */
    public function qcRecordIds(): array
    {
        return collect($this->ta_qc['rounds'] ?? [])->pluck('record_ids')->flatten()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** O protocolo prevê pré-extração por modelo de linguagem para o Revisor 1. */
    public function llmAssistedExtraction(): bool
    {
        return filled(data_get($this->pipeline, 'extraction.llm_assistance'));
    }

    public function articleReviews()
    {
        return $this->hasMany(ArticleReview::class);
    }

    public function deviations()
    {
        return $this->hasMany(SystematicReviewDeviation::class)->latest();
    }

    // Fases P7–P14: estudos, extração/RoB, trava, análise, GRADE, autores, atas.
    public function studies()
    {
        return $this->hasMany(SrStudy::class)->orderBy('position');
    }

    public function activeStudies()
    {
        return $this->studies()->where('status', 'active');
    }

    public function datasetLocks()
    {
        return $this->hasMany(SrDatasetLock::class)->orderByDesc('version');
    }

    public function latestLock(): ?SrDatasetLock
    {
        return $this->datasetLocks()->first();
    }

    public function analysisRuns()
    {
        return $this->hasMany(SrAnalysisRun::class)->latest();
    }

    public function gradeRows()
    {
        return $this->hasMany(SrGradeRow::class)->orderBy('outcome_id');
    }

    public function authorContacts()
    {
        return $this->hasMany(SrAuthorContact::class)->latest('sent_at');
    }

    public function meetings()
    {
        return $this->hasMany(SrMeeting::class)->latest('held_at');
    }

    public function extractionOpen(): bool
    {
        return $this->extraction_opened_at !== null;
    }

    /** Piloto de extração ainda não liberado: só os estudos-piloto ficam disponíveis. */
    public function inExtractionPilot(): bool
    {
        return $this->extractionOpen() && $this->extraction_pilot_size > 0 && !$this->extraction_pilot_released_at;
    }

    /** Protocolo congelado: critérios, códigos e campos ficam somente leitura (mudanças só por emenda). */
    public function isFrozen(): bool
    {
        return $this->protocol_frozen_at !== null;
    }

    /** Prefixo dos arquivos no padrão do guia (P05_...), ou o nome curto. */
    public function filePrefix(): string
    {
        return $this->protocol_code ?: $this->baseFileStem();
    }

    /**
     * Regra do protocolo importado: "talvez" de qualquer revisor na etapa de
     * título/resumo leva o registro ao texto completo.
     */
    public function maybeAdvancesToFullText(): bool
    {
        $rule = (string) data_get($this->pipeline, 'screening.stages.0.maybe_rule', '');

        return str_contains(mb_strtolower($rule), 'full text');
    }

    /** Decisões permitidas na etapa (o protocolo importado pode restringir; ex.: texto completo só incluir/excluir). */
    public function allowedDecisions(?string $stage = null): array
    {
        $stage ??= $this->stage;
        $fromProtocol = collect(data_get($this->pipeline, 'screening.stages', []))->firstWhere('id', $stage)['decisions'] ?? null;
        $all = array_keys(SystematicReviewRecord::DECISIONS);

        return $fromProtocol ? array_values(array_intersect($all, (array) $fromProtocol)) : $all;
    }

    /** Respostas do registro OSF (chaves do template Generalized SR Registration). */
    public function osfFields(): array
    {
        return (array) data_get($this->pipeline, 'osf_fields', []);
    }

    /** Conjunto de validação (estudos que as buscas devem recuperar). */
    public function validationSet(): array
    {
        return (array) data_get($this->pipeline, 'search.validation_set', []);
    }

    /** Hash do protocolo: JSON canônico do pipeline + campos do protocolo + códigos de exclusão. */
    public function computeProtocolHash(): string
    {
        return hash('sha256', json_encode([
            'pipeline' => self::sortKeys($this->pipeline ?? []),
            'protocol' => self::sortKeys($this->protocol ?? []),
            'exclusion_reasons' => array_values($this->exclusion_reasons ?? []),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected static function sortKeys(array $a): array
    {
        if (!array_is_list($a)) {
            ksort($a);
        }

        return array_map(fn ($v) => is_array($v) ? self::sortKeys($v) : $v, $a);
    }

    public function logEvent(string $event, ?string $details = null): void
    {
        $history = $this->history ?? [];
        $history[] = [
            'at' => now()->format('Y-m-d H:i:s'),
            'event' => $event,
            'details' => $details,
            'actor' => auth()->check() ? auth()->user()->name : null,
        ];

        $this->forceFill(['history' => $history])->saveQuietly();
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function stageLabel(?string $stage = null): string
    {
        return self::STAGE_LABELS[$stage ?? $this->stage] ?? (string) $stage;
    }

    public function typeLabel(): string
    {
        return self::REVIEW_TYPES[$this->review_type] ?? $this->review_type;
    }

    public function protocolValue(string $key): ?string
    {
        $value = $this->protocol[$key] ?? null;

        return filled($value) ? (string) $value : null;
    }

    public function exclusionReasons(): array
    {
        $reasons = array_values(array_filter(array_map('trim', $this->exclusion_reasons ?? [])));

        return $reasons ?: self::DEFAULT_EXCLUSION_REASONS;
    }

    /**
     * Checklist de apoio à triagem com o motivo de exclusão já resolvido para a etapa
     * (o código "E9" vira o texto completo "E9 — …" que está na lista de motivos).
     *
     * @param  array<int, string>  $reasons  motivos válidos na etapa
     * @return array<int, array{q: string, h: ?string, reason: ?string}>
     */
    public function screeningChecklistFor(array $reasons): array
    {
        return collect($this->screening_checklist ?? [])
            ->filter(fn ($item) => is_array($item) && filled($item['q'] ?? null))
            ->map(function ($item) use ($reasons) {
                $code = trim((string) ($item['code'] ?? ''));
                $reason = $code === '' ? null : collect($reasons)->first(fn ($r) => preg_match('/^' . preg_quote($code, '/') . '\s+—/u', $r) === 1);

                return ['q' => (string) $item['q'], 'h' => filled($item['h'] ?? null) ? (string) $item['h'] : null, 'reason' => $reason];
            })
            ->values()->all();
    }

    public function isRegistered(): bool
    {
        return filled($this->registration_id) || filled($this->registration_waiver);
    }

    public function registrationLine(): ?string
    {
        if (blank($this->registration_id)) {
            return null;
        }

        return trim(($this->registration_platform ?: 'OSF') . ' ' . $this->registration_id);
    }

    public function isStageClosed(string $stage): bool
    {
        return filled($this->stage_closed_at[$stage] ?? null);
    }

    /** Coluna da decisão final da etapa no registro. */
    public static function decisionColumn(string $stage): string
    {
        return $stage === self::STAGE_FT ? 'ft_decision' : 'ta_decision';
    }

    /**
     * Registros que entram numa etapa: todos na de título/resumo; só os
     * incluídos na de texto completo.
     */
    public function stageRecords(?string $stage = null)
    {
        $stage ??= $this->stage;
        $query = $this->records();

        if ($stage === self::STAGE_FT) {
            $query->where('ta_decision', 'include');
        }

        return $query;
    }

    /**
     * Registros visíveis aos triadores agora: na fase piloto, só os N
     * primeiros (calibração); depois que o professor libera, todos.
     */
    public function screenableRecords(?string $stage = null)
    {
        $stage ??= $this->stage;
        $query = $this->stageRecords($stage);

        if ($this->inPilot($stage)) {
            // Amostra aleatória (fixa) — os primeiros importados costumam ser de uma
            // base só e de um período só, o que distorce a calibração.
            $ids = (clone $query)->reorder('random_key')->orderBy('id')->limit($this->pilot_size)->pluck('id');
            $query = $this->records()->whereIn('id', $ids);
        }

        return $query;
    }

    /**
     * Toda a amostra de calibração (todas as rodadas), na ordem fixa por random_key.
     * Serve para pôr no topo da fila priorizada o que o aluno deixou do piloto sem decidir.
     *
     * @return array<int, int>
     */
    public function pilotSampleIds(): array
    {
        if ((int) $this->pilot_size <= 0) {
            return [];
        }

        return $this->stageRecords(self::STAGE_TA)->reorder('random_key')->orderBy('id')->limit((int) $this->pilot_size)->pluck('id')->all();
    }

    /** Treino aberto cujo prazo já passou: deve fechar e mostrar os resultados. */
    public function trainingExpired(): bool
    {
        return $this->is_training && $this->status === self::STATUS_SCREENING
            && $this->training_closes_at !== null && $this->training_closes_at->isPast();
    }

    /** Rodada de calibração em andamento (1 = amostra-piloto original). */
    public function pilotRound(): int
    {
        return count($this->pilot_rounds ?? []) + 1;
    }

    /** Quantos registros do piloto pertencem às rodadas já fechadas (a rodada atual começa depois deles). */
    public function pilotRoundStart(): int
    {
        return (int) collect($this->pilot_rounds ?? [])->sum('size');
    }

    /**
     * Registros da rodada de calibração atual: a mesma ordem fixa por random_key,
     * pulando os das rodadas anteriores (cada rodada é uma amostra aleatória nova).
     *
     * @return array<int, int>
     */
    public function currentPilotRecordIds(): array
    {
        $start = $this->pilotRoundStart();

        return $this->stageRecords(self::STAGE_TA)->reorder('random_key')->orderBy('id')
            ->skip($start)->take(max(0, (int) $this->pilot_size - $start))->pluck('id')->all();
    }

    public function inPilot(?string $stage = null): bool
    {
        return ($stage ?? $this->stage) === self::STAGE_TA
            && $this->pilot_size > 0
            && $this->pilot_released_at === null;
    }

    public function baseFileStem(): string
    {
        $stem = Str::of($this->short_name ?: $this->title)->ascii()
            ->replaceMatches('/[^A-Za-z0-9]+/', '_')->trim('_')->limit(50, '')->trim('_')->toString();

        return $stem !== '' ? $stem : 'Revisao_Sistematica';
    }

    public function reportingGuideline(): string
    {
        return self::GUIDELINES[$this->review_type] ?? 'PRISMA 2020';
    }

    /**
     * Esteira ajustada ao tipo de revisão: escopo não tem risco de viés nem
     * metanálise; acurácia usa QUADAS-2 e modelo bivariado; prevalência usa
     * JBI e metanálise de proporções; RS sem metanálise usa SWiM.
     *
     * @return array<string, string>
     */
    public function pipeline(): array
    {
        $p = self::PIPELINE;
        $guideline = $this->reportingGuideline();
        $p['manuscript'] = "Fase 5 — Manuscrito {$guideline} (em inglês) + checklist com páginas/linhas";

        switch ($this->review_type) {
            case 'scoping':
                $p['protocol'] = 'Fase 0 — Protocolo (JBI/PRISMA-ScR) escrito (PCC + critérios + sintaxe)';
                $p['extraction'] = 'Fase 3 — Mapeamento (charting) dos dados com formulário testado em dupla';
                unset($p['rob'], $p['certainty']);
                $p['analysis'] = 'Fase 4 — Síntese descritiva e mapa de evidências (sem metanálise nem risco de viés)';
                break;
            case 'systematic_review':
                $p['analysis'] = 'Fase 4 — Síntese sem metanálise (SWiM): agrupamento, métrica padronizada e tabelas';
                break;
            case 'diagnostic':
                $p['rob'] = 'Fase 3 — Risco de viés e aplicabilidade em dupla (QUADAS-2)';
                $p['analysis'] = 'Fase 4 — Metanálise bivariada/HSROC (sensibilidade, especificidade, curva SROC) no R';
                break;
            case 'prevalence':
                $p['rob'] = 'Fase 3 — Qualidade em dupla (JBI Critical Appraisal para prevalência)';
                $p['analysis'] = 'Fase 4 — Metanálise de proporções (GLMM logit ou Freeman-Tukey) e meta-regressão no R';
                break;
        }

        return $p;
    }

    /**
     * Alertas das exigências comuns das revistas (não bloqueiam).
     *
     * @return array<int, string>
     */
    public function journalWarnings(): array
    {
        $warnings = [];
        $databases = array_keys($this->search_counts['sources'] ?? []);
        if ($this->records()->exists() && count($databases) < 2) {
            $warnings[] = 'Só ' . count($databases) . ' base bibliográfica importada — as revistas exigem no mínimo 2, além de literatura cinzenta.';
        }
        if ($this->search_date && $this->search_date->lt(now()->subMonths(11))) {
            $warnings[] = 'A última busca é de ' . $this->search_date->format('d/m/Y') . ': atualize antes de submeter (a busca deve ter no máximo 12 meses).';
        }
        if (blank($this->registration_id)) {
            $warnings[] = 'Sem registro do protocolo (OSF/PROSPERO): a maioria das revistas exige registro prospectivo.';
        }
        if ($this->screening_started_at && $this->activeScreeners()->count() < 2) {
            $warnings[] = 'Triagem com um único triador: as revistas exigem triagem e extração em dupla independente.';
        }

        return $warnings;
    }

    public function checklistDone(string $key): bool
    {
        return filled($this->checklist[$key] ?? null);
    }
}
