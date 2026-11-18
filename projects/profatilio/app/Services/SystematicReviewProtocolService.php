<?php

namespace App\Services;

use App\Models\ResearchCall;
use App\Models\SystematicReview;
use App\Models\SystematicReviewDecision;
use App\Models\SystematicReviewDeviation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Protocolo registrado (pipeline_PXX.json, schema calefi-sr-pipeline/1.0):
 * importação, congelamento com hash, registro no OSF, desvios/emendas e as
 * exportações no padrão de nomes do guia (PNN_*), inclusive o pacote por
 * componente do OSF. "O protocolo registrado manda."
 */
class SystematicReviewProtocolService
{
    public const SCHEMA = 'calefi-sr-pipeline/1.0';

    /** Família do protocolo → tipo de revisão e framework do sistema. */
    public const FAMILY_MAP = [
        'intervention' => ['meta_analysis', 'PICO'],
        'arm' => ['meta_analysis', 'PICO'],
        'prevalence' => ['prevalence', 'CoCoPop'],
        'prognostic' => ['meta_analysis', 'PECO'],
        'dta' => ['diagnostic', 'PIRD'],
        'correlation' => ['meta_analysis', 'PECO'],
    ];

    public const OSF_COMPONENTS = [
        '01_Protocolo_e_Registro',
        '02_Busca',
        '03_Triagem',
        '04_Extracao_e_Risco_de_Vies',
        '05_Analise',
        '06_Manuscrito',
    ];

    // ------------------------------------------------------------------
    // Importação (P0)
    // ------------------------------------------------------------------

    /** Valida e devolve o pipeline decodificado. */
    public function parse(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException('O arquivo não é um JSON válido.');
        }
        if (($data['schema'] ?? null) !== self::SCHEMA) {
            throw new \RuntimeException('Schema inesperado: use um pipeline_PXX.json no formato ' . self::SCHEMA . '.');
        }
        foreach (['protocol_id', 'title', 'family', 'eligibility', 'search', 'screening', 'extraction', 'risk_of_bias', 'synthesis'] as $key) {
            if (blank($data[$key] ?? null)) {
                throw new \RuntimeException("O JSON {$data['protocol_id']} não tem o campo obrigatório \"{$key}\".");
            }
        }
        if (!preg_match('/^P\d{2}$/', (string) $data['protocol_id'])) {
            throw new \RuntimeException('protocol_id deve ser P01…P99.');
        }

        return $data;
    }

    /**
     * Cria ou atualiza a revisão do protocolo. Protocolo congelado não é
     * sobrescrito: mudanças só por emenda.
     *
     * @return array{0: SystematicReview, 1: bool} [revisão, criada agora]
     */
    public function import(string $json): array
    {
        $p = $this->parse($json);
        $review = SystematicReview::where('protocol_code', $p['protocol_id'])->first();

        if ($review?->isFrozen()) {
            throw new \RuntimeException("{$p['protocol_id']}: o protocolo já está congelado. Mudanças só por emenda (registre a emenda e importe a nova versão depois de aprová-la).");
        }

        [$type, $framework] = self::FAMILY_MAP[$p['family']] ?? ['meta_analysis', 'PICO'];
        $attributes = [
            'protocol_code' => $p['protocol_id'],
            'folder' => $p['folder'] ?? null,
            'family' => $p['family'],
            'title' => Str::limit($p['title'], 495, ''),
            'short_name' => Str::limit($p['protocol_id'] . ' · ' . ($p['label_pt'] ?? $p['short_title'] ?? ''), 80, ''),
            'review_type' => $type,
            'framework' => $framework,
            'pipeline' => $p,
            'protocol_version' => (string) ($p['version'] ?? '1.0'),
            'protocol' => $this->protocolFields($p),
            'exclusion_reasons' => $this->exclusionCodes($p),
            // Rótulos em português dos códigos (tela da triagem). Leva 2+: screening.exclusion_reasons_pt = {"E1": "…"}.
            'exclusion_reasons_pt' => array_filter((array) data_get($p, 'screening.exclusion_reasons_pt', [])) ?: null,
            'highlight_terms' => ['include' => array_values(array_slice((array) ($p['keywords'] ?? []), 0, 30)), 'exclude' => []],
            'registration_platform' => 'OSF',
            'pilot_size' => (int) data_get($p, 'screening.stages.0.calibration.sample', 0),
        ];

        // Leva 2+: o checklist de triagem em português vem no próprio pipeline (q, h, code).
        $checklist = collect((array) ($p['screening_checklist_pt'] ?? []))
            ->filter(fn ($i) => is_array($i) && filled($i['q'] ?? null))
            ->map(fn ($i) => ['q' => (string) $i['q'], 'h' => $i['h'] ?? null, 'code' => $i['code'] ?? null])->values()->all();
        if ($checklist) {
            $attributes['screening_checklist'] = $checklist;
        }

        $created = !$review;
        DB::transaction(function () use (&$review, $attributes, $created, $p) {
            $review = $created ? SystematicReview::create($attributes) : tap($review)->update($attributes);
            $review->logEvent($created ? "Protocolo {$p['protocol_id']} importado do pipeline JSON" : "Protocolo {$p['protocol_id']} atualizado pelo pipeline JSON",
                'versão ' . ($p['version'] ?? '1.0') . ' · ' . ($p['date'] ?? ''));
            $this->linkToCall($review);
        });

        return [$review, $created];
    }

    /** Liga a revisão ao código da seleção (P01–P10 → PET-01…; P11–P20 → PAT-01…). */
    public function linkToCall(SystematicReview $review): ?string
    {
        $code = $review->selectionCode();
        if (!$code) {
            return null;
        }

        foreach (ResearchCall::all() as $call) {
            $projects = $call->projects ?? [];
            $changed = false;
            foreach ($projects as &$project) {
                if (($project['code'] ?? null) === $code && empty($project['systematic_review_id'])) {
                    $project['systematic_review_id'] = $review->id;
                    $changed = true;
                }
            }
            unset($project);
            if ($changed) {
                $call->update(['projects' => $projects]);
            }
            $this->addApprovedStudents($call, $review, $code);
        }

        return $code;
    }

    /**
     * Alunos aprovados na seleção ANTES de a revisão existir entram agora como
     * triadores (líder e revisor cego), sem convite: o e-mail da aprovação já avisou.
     */
    protected function addApprovedStudents(ResearchCall $call, SystematicReview $review, string $code): void
    {
        if ((int) ($call->projectsByCode()[$code]['systematic_review_id'] ?? 0) !== $review->id) {
            return;
        }
        $apps = \App\Models\ResearchApplication::where('research_call_id', $call->id)
            ->where('status', \App\Models\ResearchApplication::STATUS_APPROVED)
            ->where(fn ($q) => $q->where('lead_project_code', $code)->orWhere('reviewer_project_code', $code))
            ->orderBy('id')->get();
        foreach ($apps as $app) {
            if ($review->screeners()->where('email', mb_strtolower(trim($app->email)))->whereNull('removed_at')->exists()) {
                continue;
            }
            $label = $app->lead_project_code === $code ? 'Líder (1º autor)' : 'Revisor(a) cego(a)';
            try {
                app(SystematicReviewService::class)->addScreener($review, [
                    'user_id' => \App\Models\User::where('email', $app->email)->value('id'),
                    'name' => $app->name, 'email' => $app->email, 'label' => $label,
                    'stages' => array_keys(SystematicReview::STAGE_LABELS), 'skip_invite' => true,
                ]);
            } catch (\RuntimeException $e) {
                \Illuminate\Support\Facades\Log::info("Importação: {$app->email} não incluído em {$code}: " . $e->getMessage());
            }
        }
    }

    protected function protocolFields(array $p): array
    {
        $pico = collect(data_get($p, 'eligibility.pico', []));
        // Elementos variam por família: Intervention / Condition / Exposure / Index test(s) /
        // Prognostic factor(s); Comparator / Reference standard / Target condition and reference standard.
        $element = function (array $needles, array $exclude = []) use ($pico) {
            return $pico->first(function ($e) use ($needles, $exclude) {
                $name = mb_strtolower($e['element'] ?? '');

                return Str::contains($name, $needles) && !Str::contains($name, $exclude);
            })['definition'] ?? null;
        };
        $list = fn ($items, string $prefix = '') => collect((array) $items)->values()
            ->map(fn ($t, $i) => ($prefix !== '' ? "{$prefix}" . ($i + 1) . ': ' : ($i + 1) . '. ') . (is_array($t) ? json_encode($t, JSON_UNESCAPED_UNICODE) : $t))
            ->implode("\n");
        $osf = (array) ($p['osf_fields'] ?? []);

        $databases = collect(data_get($p, 'search.databases', []));
        $synthesis = (array) ($p['synthesis'] ?? []);

        return array_filter([
            'title_en' => $p['title'],
            'background' => $osf['376-18'] ?? null,
            'question' => trim("Primary:\n" . $list(data_get($p, 'questions.primary', []))
                . "\n\nSecondary:\n" . $list(data_get($p, 'questions.secondary', []))
                . "\n\nHypotheses:\n" . $list(data_get($p, 'questions.hypotheses', []))),
            'population' => $element(['population']),
            'intervention' => $element(['intervention', 'exposure', 'index', 'prognostic', 'concept', 'condition'], ['reference', 'population']),
            'comparator' => $element(['compar', 'reference', 'context']),
            'outcomes' => $element(['outcome']) ?: $list(collect(data_get($p, 'extraction.outcomes', []))->map(fn ($o) => "{$o['id']} (" . ($o['priority'] ?? 'outcome') . "): {$o['name']}")),
            'study_designs' => $element(['study', 'design']),
            'inclusion_criteria' => $list(data_get($p, 'eligibility.inclusion', [])),
            'exclusion_criteria' => $list(data_get($p, 'eligibility.exclusion', []), 'E'),
            'databases' => $databases->map(fn ($d) => $d['name'] . (filled($d['interface'] ?? null) ? " ({$d['interface']})" : ''))->implode("\n")
                . (data_get($p, 'search.supplementary') ? "\n\nSupplementary:\n" . $list(data_get($p, 'search.supplementary')) : ''),
            'search_string' => $databases->map(fn ($d) => "{$d['name']} — {$d['interface']}:\n{$d['query']}")->implode("\n\n"),
            'limits' => 'Language: ' . data_get($p, 'eligibility.language', 'none') . ' · Dates: ' . data_get($p, 'eligibility.date_limits', 'none'),
            'screening_method' => $osf['376-78'] ?? null,
            'data_items' => collect(data_get($p, 'extraction.fields', []))->map(fn ($f) => "{$f['name']} [{$f['type']}]: {$f['description']}")->implode("\n")
                . "\n\nOutcomes:\n" . collect(data_get($p, 'extraction.outcomes', []))->map(fn ($o) => "{$o['id']} ({$o['priority']}, {$o['type']}, {$o['measure']}): {$o['name']} — {$o['timepoint']}")->implode("\n"),
            'rob_tool' => collect(data_get($p, 'risk_of_bias.tools', []))->map(fn ($t) => "{$t['code']} — {$t['name']}: " . implode('; ', (array) ($t['domains'] ?? [])))->implode("\n")
                . (filled(data_get($p, 'risk_of_bias.rule')) ? "\n\n" . data_get($p, 'risk_of_bias.rule') : ''),
            'synthesis' => trim(($synthesis['model'] ?? '') . "\n\nEffect measures:\n" . $list($synthesis['effect_measures'] ?? [])
                . "\n\nMinimum studies: pooling k ≥ " . ($synthesis['min_studies_pooling'] ?? '—')
                . '; meta-regression k ≥ ' . ($synthesis['min_studies_metaregression_per_covariate'] ?? '—') . ' per covariate'
                . '; publication bias k ≥ ' . ($synthesis['min_studies_publication_bias'] ?? '—')
                . (filled($synthesis['narrative_synthesis'] ?? null) ? "\nNarrative synthesis: {$synthesis['narrative_synthesis']}" : '')),
            'heterogeneity' => trim("Subgroups:\n" . $list($synthesis['subgroups'] ?? []) . "\n\nMeta-regression:\n" . $list($synthesis['meta_regression'] ?? [])
                . "\n\nSensitivity:\n" . $list($synthesis['sensitivity'] ?? [])),
            'certainty' => trim(($synthesis['publication_bias'] ?? '') . "\n\n" . ($synthesis['certainty'] ?? '')),
        ], fn ($v) => filled($v));
    }

    /** Códigos de exclusão do texto completo (E1 — …), usados na triagem e no PRISMA. */
    protected function exclusionCodes(array $p): array
    {
        $reasons = collect(data_get($p, 'screening.stages', []))
            ->flatMap(fn ($stage) => (array) ($stage['exclusion_reasons'] ?? []))
            ->map(fn ($r) => Str::limit(trim(($r['code'] ?? '') . ' — ' . ($r['label'] ?? '')), 250, '…'))
            ->filter()->unique()->values()->all();

        return $reasons ?: SystematicReview::DEFAULT_EXCLUSION_REASONS;
    }

    // ------------------------------------------------------------------
    // Congelamento (P1), registro OSF (P2), desvios e emendas
    // ------------------------------------------------------------------

    /** Problemas que impedem congelar o protocolo. */
    public function freezeProblems(SystematicReview $review): array
    {
        $problems = [];
        if ($review->isFrozen()) {
            $problems[] = 'O protocolo já está congelado.';
        }
        if (blank($review->protocol['inclusion_criteria'] ?? null) || blank($review->protocol['exclusion_criteria'] ?? null)) {
            $problems[] = 'Faltam os critérios de inclusão/exclusão.';
        }
        if (SystematicReviewDecision::whereIn('systematic_review_record_id', $review->records()->select('id'))->exists()) {
            $problems[] = 'Já existe triagem registrada: o protocolo precisa ser congelado antes de qualquer triagem.';
        }

        return $problems;
    }

    public function freeze(SystematicReview $review, ?string $pdfContents = null, ?string $pdfName = null): void
    {
        if ($problems = $this->freezeProblems($review)) {
            throw new \RuntimeException(implode(' ', $problems));
        }
        $pdfPath = null;
        $pdfHash = null;
        if ($pdfContents !== null) {
            $pdfHash = hash('sha256', $pdfContents);
            $pdfPath = 'systematic-reviews/protocols/' . $review->filePrefix() . '_v' . ($review->protocol_version ?: '1.0') . '_' . substr($pdfHash, 0, 12) . '.pdf';
            Storage::disk('local')->put($pdfPath, $pdfContents);
        }
        $review->update([
            'protocol_frozen_at' => now(),
            'protocol_sha256' => $review->computeProtocolHash(),
            'protocol_pdf_path' => $pdfPath,
            'protocol_pdf_sha256' => $pdfHash,
            'protocol_version' => $review->protocol_version ?: '1.0',
        ]);
        $review->logEvent('Protocolo congelado (versão ' . $review->protocol_version . ')',
            'SHA-256 do protocolo ' . substr($review->protocol_sha256, 0, 16) . '…' . ($pdfHash ? ' · PDF ' . substr($pdfHash, 0, 16) . '…' : ''));
        app(SystematicReviewService::class)->tick($review, 'protocol');
        $this->notifyOsfPending($review, 'O protocolo foi congelado.');
    }

    /** Problemas que impedem desfazer o congelamento (só antes do registro no OSF e de qualquer triagem). */
    public function unfreezeProblems(SystematicReview $review): array
    {
        $problems = [];
        if (!$review->isFrozen()) {
            $problems[] = 'O protocolo não está congelado.';
        }
        if ($review->registration_id) {
            $problems[] = 'O protocolo já foi registrado no OSF: mudanças só por emenda.';
        }
        if (SystematicReviewDecision::whereIn('systematic_review_record_id', $review->records()->select('id'))->exists()) {
            $problems[] = 'Já existe triagem registrada: mudanças só por emenda.';
        }

        return $problems;
    }

    /**
     * Desfaz um congelamento feito por engano (ex.: PDF de outro protocolo), antes do registro.
     * Remove o PDF anexado e deixa o motivo no histórico; congele de novo em seguida.
     */
    public function unfreeze(SystematicReview $review, string $reason): void
    {
        if ($problems = $this->unfreezeProblems($review)) {
            throw new \RuntimeException(implode(' ', $problems));
        }
        $old = ['sha' => $review->protocol_sha256, 'pdf' => $review->protocol_pdf_sha256, 'at' => $review->protocol_frozen_at];
        if ($review->protocol_pdf_path) {
            Storage::disk('local')->delete($review->protocol_pdf_path);
        }
        $review->update([
            'protocol_frozen_at' => null,
            'protocol_sha256' => null,
            'protocol_pdf_path' => null,
            'protocol_pdf_sha256' => null,
        ]);
        $checklist = $review->checklist ?? [];
        unset($checklist['protocol']);
        $review->forceFill(['checklist' => $checklist])->saveQuietly();
        $review->logEvent('Congelamento desfeito (antes do registro no OSF)',
            $reason . ' · era de ' . $old['at']?->format('d/m/Y H:i') . ' · SHA-256 ' . substr((string) $old['sha'], 0, 16) . '…'
            . ($old['pdf'] ? ' · PDF ' . substr($old['pdf'], 0, 16) . '…' : ''));
    }

    /** Problemas que impedem gravar o registro do OSF. */
    public function registrationProblems(SystematicReview $review): array
    {
        $problems = [];
        if (!$review->isFrozen()) {
            $problems[] = 'Congele o protocolo antes de registrar no OSF.';
        }
        if (SystematicReviewDecision::whereIn('systematic_review_record_id', $review->records()->select('id'))->exists()) {
            $problems[] = 'Já existe triagem registrada: o registro tem de ser feito antes de qualquer triagem.';
        }

        return $problems;
    }

    public function register(SystematicReview $review, array $data): void
    {
        if ($problems = $this->registrationProblems($review)) {
            throw new \RuntimeException(implode(' ', $problems));
        }
        $doi = trim((string) ($data['registration_id'] ?? ''));
        if (!preg_match('~^(https?://(dx\.)?doi\.org/)?10\.17605/OSF\.IO/[A-Z0-9]{4,}$~i', $doi)) {
            throw new \RuntimeException('Informe o DOI do registro no formato 10.17605/OSF.IO/XXXXX.');
        }
        $doi = preg_replace('~^https?://(dx\.)?doi\.org/~i', '', $doi);

        $review->update([
            'registration_platform' => 'OSF',
            'registration_id' => '10.17605/OSF.IO/' . strtoupper(Str::afterLast($doi, '/')),
            'osf_project_url' => $data['osf_project_url'] ?? null,
            'registered_at' => $data['registered_at'] ?? now()->toDateString(),
            'embargo_until' => $data['embargo_until'] ?? null,
        ]);
        $review->logEvent('Registro no OSF gravado', $review->registration_id . ($review->embargo_until ? ' · embargo até ' . $review->embargo_until->format('d/m/Y') : ' · público'));
        app(SystematicReviewService::class)->tick($review, 'registered');
    }

    public function addDeviation(SystematicReview $review, array $data, bool $approve = true): SystematicReviewDeviation
    {
        $deviation = $review->deviations()->create([
            'type' => $data['type'],
            'section' => $data['section'],
            'description' => $data['description'],
            'justification' => $data['justification'],
            'requested_by' => $data['requested_by'] ?? null,
            'osf_update_ref' => $data['osf_update_ref'] ?? null,
            'approved_at' => $approve ? now() : null,
        ]);

        if ($deviation->type === 'amendment' && $approve) {
            $next = $this->nextVersion($review->protocol_version ?: '1.0');
            $deviation->update(['protocol_version_after' => $next]);
            // Emenda: libera nova importação/edição e exige novo congelamento (nova versão).
            $review->update(['protocol_version' => $next, 'protocol_frozen_at' => null]);
        }
        $review->logEvent(SystematicReviewDeviation::TYPES[$deviation->type] . ' registrada: ' . $deviation->section,
            Str::limit($deviation->description, 200) . ($deviation->protocol_version_after ? ' · nova versão ' . $deviation->protocol_version_after : ''));

        return $deviation;
    }

    protected function nextVersion(string $version): string
    {
        [$major, $minor] = array_pad(array_map('intval', explode('.', $version)), 2, 0);

        return $major . '.' . ($minor + 1);
    }

    // ------------------------------------------------------------------
    // Pendências no OSF: o sistema avisa quando há algo a exportar/subir
    // ------------------------------------------------------------------

    /**
     * O que falta subir no OSF agora, em ordem. Cada passo diz o componente
     * de destino e os arquivos (todos no menu "Baixar" ou no pacote .zip).
     *
     * @return array<int, array{key: string, title: string, component: string, files: array<int, string>, action: string}>
     */
    public function osfPending(SystematicReview $review): array
    {
        $px = $review->filePrefix();
        $done = fn (string $key) => $review->checklistDone("osf_{$key}");
        $steps = [];

        if ($review->pipeline && !$review->isFrozen()) {
            $steps[] = ['key' => 'freeze', 'title' => 'Congelar o protocolo (P1) antes de registrar no OSF', 'component' => '—',
                'files' => [], 'action' => 'Protocolo e OSF → Congelar protocolo'];
        }
        if ($review->isFrozen() && blank($review->registration_id)) {
            $steps[] = ['key' => 'register', 'title' => 'Registrar o protocolo no OSF (P2) e gravar o DOI', 'component' => '01_Protocolo_e_Registro',
                'files' => ["Protocol_{$px}.pdf", "OSF_Form_{$px}.txt", "{$px}_EXTRACAO_modelo.csv", "{$px}_dicionario_extracao.csv"],
                'action' => 'Baixar → Ficha OSF; depois Protocolo e OSF → Registro no OSF'];
        }
        if (filled($review->registration_id) && !empty($review->search_counts['searches'] ?? []) && !$done('search')) {
            $steps[] = ['key' => 'search', 'title' => 'Subir o log das buscas e os arquivos RIS de cada base', 'component' => '02_Busca',
                'files' => ["{$px}_BUSCA_log.csv", "{$px}_BUSCA_<base>_<AAAA-MM-DD>.ris (os arquivos exportados das bases)"], 'action' => 'Baixar → Log das buscas'];
        }
        foreach ([SystematicReview::STAGE_TA => ['ta', 'TA', 'título/resumo'], SystematicReview::STAGE_FT => ['ft', 'FT', 'texto completo']] as $stage => [$key, $tag, $label]) {
            if ($review->isStageClosed($stage) && !$done($key)) {
                $steps[] = ['key' => $key, 'title' => "Etapa de {$label} encerrada: subir as decisões fechadas e as contagens PRISMA", 'component' => '03_Triagem',
                    'files' => ["{$px}_TRIAGEM_{$tag}_decisoes_<data>.csv", "{$px}_PRISMA_contagens.csv"], 'action' => 'Baixar → Pacote para o OSF (ou os arquivos avulsos)'];
            }
        }
        $lock = $review->latestLock();
        if ($lock && !$review->checklistDone("osf_lock_v{$lock->version}")) {
            $steps[] = ['key' => "lock_v{$lock->version}", 'title' => "Base travada v{$lock->version}: subir extração e risco de viés travados", 'component' => '04_Extracao_e_Risco_de_Vies',
                'files' => ["{$px}_EXTRACAO_travada_v{$lock->version}.csv", "{$px}_RoB_v{$lock->version}.csv", "{$px}_CONTATOS_autores.csv"], 'action' => 'Baixar → Pacote para o OSF (ou os arquivos avulsos)'];
        }
        $confirmed = $review->analysisRuns()->where('rerun_matches', true)->latest()->first();
        if ($confirmed && !$review->checklistDone("osf_analysis_{$confirmed->id}")) {
            $steps[] = ['key' => "analysis_{$confirmed->id}", 'title' => 'Análise reexecutada com resultado idêntico: subir script, saídas e sessionInfo', 'component' => '05_Analise',
                'files' => array_merge([(string) $confirmed->script_name, 'sessionInfo.txt'], collect($confirmed->outputs ?? [])->pluck('name')->all()), 'action' => 'Baixar → Pacote para o OSF'];
        }
        if (filled($review->manuscript['submitted_at'] ?? null) && !$done('deposit')) {
            $steps[] = ['key' => 'deposit', 'title' => 'Artigo submetido: depositar base, scripts e decisões (CC BY 4.0) e tornar os componentes públicos', 'component' => '03, 04 e 05',
                'files' => [], 'action' => 'No OSF: Settings → License CC BY 4.0; tornar os componentes 01–05 públicos (o 06 só com a versão publicada)'];
        }
        $deviations = $review->deviations()->count();
        if ($deviations > (int) ($review->checklist['osf_deviations_count'] ?? 0)) {
            $steps[] = ['key' => 'deviations', 'title' => 'Há desvio/emenda novo: atualizar o registro de desvios no OSF', 'component' => '01_Protocolo_e_Registro',
                'files' => ["{$px}_DESVIOS.csv"], 'action' => 'Baixar → Desvios e emendas (emenda: anexar também o protocolo atualizado)'];
        }

        return $steps;
    }

    /** "Marquei como enviado ao OSF": tira os passos da lista de pendências. */
    public function markUploaded(SystematicReview $review, array $keys): void
    {
        $checklist = $review->checklist ?? [];
        foreach ($keys as $key) {
            if ($key === 'deviations') {
                $checklist['osf_deviations_count'] = $review->deviations()->count();
            } elseif (in_array($key, ['search', 'ta', 'ft', 'deposit'], true) || preg_match('/^(lock_v\d+|analysis_\d+)$/', $key)) {
                $checklist["osf_{$key}"] = now()->format('Y-m-d');
            }
        }
        $review->forceFill(['checklist' => $checklist])->saveQuietly();
        $review->logEvent('Envio ao OSF registrado', implode(', ', $keys));
    }

    /** Avisa o professor (e-mail + Telegram) quando surge algo para exportar ao OSF. */
    public function notifyOsfPending(SystematicReview $review, string $reason): void
    {
        $steps = $this->osfPending($review);
        if (!$steps) {
            return;
        }
        $body = "{$reason}\n\nPendências no OSF de {$review->filePrefix()}:\n"
            . collect($steps)->map(fn ($s) => "• {$s['title']}" . ($s['component'] !== '—' ? " → componente {$s['component']}" : '')
                . ($s['files'] ? "\n  Arquivos: " . implode(', ', $s['files']) : '') . "\n  Onde: {$s['action']}")->implode("\n")
            . "\n\nDepois de subir, clique em \"Marquei como enviado ao OSF\" na revisão.";
        app(SystematicReviewService::class)->notifyProfessor('Exportar dados para o OSF', $body, $review);
    }

    // ------------------------------------------------------------------
    // Exportações (padrão de nomes do guia, seção 9/10)
    // ------------------------------------------------------------------

    public function osfFormText(SystematicReview $review): string
    {
        $meta = (array) data_get($review->pipeline, 'registration.metadata', []);
        $out = "FICHA DE REGISTRO NO OSF — {$review->filePrefix()}\n"
            . 'Template: ' . data_get($review->pipeline, 'registration.template', 'Generalized Systematic Review Registration')
            . ' (schema ' . data_get($review->pipeline, 'registration.template_schema_id', '—') . ")\n"
            . 'Versão do protocolo: ' . ($review->protocol_version ?: '1.0')
            . ($review->protocol_sha256 ? ' · SHA-256 ' . $review->protocol_sha256 : ' · (protocolo ainda não congelado)') . "\n\n";
        foreach ($meta as $key => $value) {
            $out .= '=== ' . strtoupper((string) $key) . " ===\n" . (is_array($value) ? implode(', ', $value) : $value) . "\n\n";
        }
        foreach ($review->osfFields() as $key => $value) {
            $out .= "=== {$key} ===\n" . (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $value) . "\n\n";
        }

        return $out;
    }

    public function osfResponsesJson(SystematicReview $review): string
    {
        return json_encode([
            'registration_schema' => data_get($review->pipeline, 'registration.template_schema_id'),
            'registration_responses' => (object) $review->osfFields(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public function pipelineJson(SystematicReview $review): string
    {
        return json_encode($review->pipeline ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /** PNN_BUSCA_log.csv: buscas executadas (importação) + bases planejadas no protocolo. */
    public function searchLogRows(SystematicReview $review): array
    {
        $rows = [['project', 'database', 'interface', 'query', 'run_date', 'n_records', 'status']];
        $ran = collect($review->search_counts['searches'] ?? []);
        foreach ($ran as $s) {
            $rows[] = [$review->filePrefix(), $s['database'] ?? '', $s['platform'] ?? '', $s['query'] ?? '', $s['date'] ?? '', $s['results'] ?? '', 'executada'];
        }
        $ranNames = $ran->pluck('database')->map(fn ($n) => mb_strtolower((string) $n))->all();
        foreach ((array) data_get($review->pipeline, 'search.databases', []) as $db) {
            if (!in_array(mb_strtolower($db['name']), $ranNames, true)) {
                $rows[] = [$review->filePrefix(), $db['name'], $db['interface'] ?? '', $db['query'] ?? '', '', '', 'planejada (protocolo)'];
            }
        }
        foreach ($review->validationSet() as $v) {
            $rows[] = [$review->filePrefix(), 'validation_set', $v['key'] ?? '', $v['citation'] ?? '', '', $v['pmid'] ?? '', 'estudo de validação'];
        }

        return $rows;
    }

    /** PNN_TRIAGEM_TA|FT_decisoes: uma linha por registro × revisor + decisão final e método. */
    public function decisionRows(SystematicReview $review, string $stage): array
    {
        $prefix = $stage === SystematicReview::STAGE_FT ? 'ft' : 'ta';
        $screeners = $review->screeners()->orderBy('id')->get()->values();
        $roles = $screeners->mapWithKeys(fn ($s, $i) => [$s->id => $s->label ?: 'Revisor ' . ($i + 1)]);
        $rows = [['record_id', 'seq', 'title', 'year', 'doi', 'pmid', 'stage', 'reviewer', 'reviewer_role', 'decision', 'exclusion_code', 'decided_at',
            'final_decision', 'final_exclusion_code', 'resolution_method']];

        $records = $review->records()->with(['decisions' => fn ($q) => $q->where('stage', $stage)])->orderBy('seq')->get();
        foreach ($records as $r) {
            $final = $r->{"{$prefix}_decision"};
            $method = match (true) {
                !$final => 'pendente',
                (bool) $r->{"{$prefix}_resolved_by_professor"} => 'adjudicação',
                $prefix === 'ta' && $r->resolution_note === SystematicReviewService::MAYBE_RULE_NOTE => 'regra do protocolo (talvez → texto completo)',
                $prefix === 'ta' && $r->resolution_note === SystematicReviewService::EITHER_RULE_NOTE => 'regra do protocolo (relevante para um revisor → texto completo)',
                $prefix === 'ta' && $r->resolution_note === SystematicReviewService::STOP_RULE_NOTE => 'regra de parada (não triado; aprendizado ativo + checagem aleatória)',
                default => 'consenso',
            };
            $base = [sprintf('REC-%06d', $r->id), $r->seq, $r->title, $r->year, $r->doi, $r->pmid, strtoupper($prefix)];
            $decisions = $r->decisions->sortBy('systematic_review_screener_id');
            if ($decisions->isEmpty()) {
                $rows[] = array_merge($base, ['', '', '', '', '', $final, $r->{"{$prefix}_reason"}, $method]);
            }
            foreach ($decisions as $d) {
                $s = $screeners->firstWhere('id', $d->systematic_review_screener_id);
                $rows[] = array_merge($base, [$s?->name, $roles[$d->systematic_review_screener_id] ?? '', $d->decision, self::code($d->reason),
                    $d->updated_at?->toIso8601String(), $final, self::code($r->{"{$prefix}_reason"}), $method]);
            }
        }

        return $rows;
    }

    /** "E3 — texto" → "E3" (mantém o texto quando não há código). */
    public static function code(?string $reason): ?string
    {
        if (blank($reason)) {
            return $reason;
        }

        return preg_match('/^(E\d+)\s+—/u', $reason, $m) ? $m[1] : $reason;
    }

    /** PNN_PRISMA_contagens.csv: fluxograma + kappa por etapa + checagem de consistência. */
    public function prismaRows(SystematicReview $review): array
    {
        $service = app(SystematicReviewService::class);
        $p = $service->prisma($review);
        $rows = [['item', 'value', 'notes']];
        foreach ($p['sources'] as $db => $n) {
            $rows[] = ["identified_by_database[{$db}]", $n, ''];
        }
        $rows[] = ['identified_total', $p['identified'], ''];
        $rows[] = ['duplicates_removed', $p['duplicates'], ''];
        $rows[] = ['screened_TA', $p['screened'], 'registros únicos'];
        $rows[] = ['excluded_TA', $p['ta_excluded'], ''];
        $rows[] = ['sought_FT', $p['sought'], ''];
        $rows[] = ['not_retrieved', $p['not_retrieved'], ''];
        $rows[] = ['assessed_FT', $p['assessed'] ?? '', $p['assessed'] === null ? 'etapa ainda não iniciada' : ''];
        foreach ($p['ft_reasons'] as $reason => $n) {
            $rows[] = ['excluded_FT_by_reason[' . self::code($reason) . ']', $n, $reason];
        }
        $rows[] = ['included', $p['included'] ?? '', ''];
        if ($p['assessed'] !== null && $p['included'] !== null) {
            $ok = $p['assessed'] === $p['included'] + array_sum($p['ft_reasons']);
            $rows[] = ['check_assessed_equals_included_plus_excluded', $ok ? 'OK' : 'DIVERGENTE', 'assessed_FT = included + soma dos excluídos por código'];
        }
        foreach (SystematicReview::STAGE_LABELS as $stage => $label) {
            $rel = $service->reliability($review, $stage);
            foreach ($rel['pairs'] ?? [] as $pair) {
                $k = $pair['kappa'] ?? [];
                $rows[] = ["kappa[{$stage}]", SystematicReviewService::fmt($k['kappa'] ?? null),
                    $pair['a'] . ' × ' . $pair['b'] . ' · n=' . $pair['n'] . ' · concordância '
                    . SystematicReviewService::fmt(isset($k['agreement']) ? $k['agreement'] * 100 : null, 1) . '%'
                    . (isset($k['ci_low']) ? ' · IC95% ' . SystematicReviewService::fmt($k['ci_low']) . '–' . SystematicReviewService::fmt($k['ci_high']) : '')];
            }
        }
        $rows[] = ['generated_at', now()->toIso8601String(), 'America/Sao_Paulo'];

        return $rows;
    }

    public function deviationRows(SystematicReview $review): array
    {
        $rows = [['id', 'type', 'section', 'description', 'justification', 'requested_by', 'approved_at', 'osf_update_ref', 'protocol_version_after']];
        foreach ($review->deviations()->oldest()->get() as $d) {
            $rows[] = [$d->id, $d->type, $d->section, $d->description, $d->justification, $d->requested_by, $d->approved_at?->toIso8601String(), $d->osf_update_ref, $d->protocol_version_after];
        }

        return $rows;
    }

    /** PNN_EXTRACAO_modelo.csv: formato longo (estudo × desfecho), gerado dos campos do protocolo. */
    public function extractionTemplateRows(SystematicReview $review): array
    {
        $fields = collect(data_get($review->pipeline, 'extraction.fields', []))->pluck('name')->all();
        $header = array_merge(['study_id', 'report_ids', 'study_label', 'reviewer', 'form_version'], $fields,
            ['outcome_id', 'outcome_name', 'timepoint', 'group_label', 'n', 'mean', 'sd', 'events', 'proportion', 'effect', 'ci_low', 'ci_high', 'source_location', 'digitised', 'notes']);
        $rows = [$header];
        foreach (collect(data_get($review->pipeline, 'extraction.outcomes', [])) as $o) {
            $row = array_fill_keys($header, '');
            $row['outcome_id'] = $o['id'] ?? '';
            $row['outcome_name'] = $o['name'] ?? '';
            $row['timepoint'] = $o['timepoint'] ?? '';
            $row['form_version'] = $review->protocol_version ?: '1.0';
            $rows[] = array_values($row);
        }

        return $rows;
    }

    /** PNN_dicionario_extracao.csv: definição de cada campo e desfecho. */
    public function dictionaryRows(SystematicReview $review): array
    {
        $rows = [['name', 'type', 'description', 'kind']];
        foreach ((array) data_get($review->pipeline, 'extraction.fields', []) as $f) {
            $rows[] = [$f['name'] ?? '', $f['type'] ?? '', $f['description'] ?? '', 'field'];
        }
        foreach ((array) data_get($review->pipeline, 'extraction.outcomes', []) as $o) {
            $rows[] = [$o['id'] ?? '', ($o['type'] ?? '') . ' · ' . ($o['measure'] ?? ''), ($o['name'] ?? '') . ' — ' . ($o['timepoint'] ?? '') . ' (' . ($o['priority'] ?? '') . ')', 'outcome'];
        }
        foreach ((array) data_get($review->pipeline, 'risk_of_bias.tools', []) as $t) {
            foreach ((array) ($t['domains'] ?? []) as $domain) {
                $rows[] = [$t['code'] ?? '', 'judgement: low|some concerns|high|unclear', $domain, 'rob_domain'];
            }
        }

        return $rows;
    }

    public static function csvString(array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($out, array_map(fn ($v) => self::safeCell($v), $row), ';', '"', '');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /** Evita injeção de fórmula ao abrir no Excel. */
    public static function safeCell($value): string
    {
        $value = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }

    /**
     * Pacote para o OSF, já separado por componente. Só entram exportações de
     * etapas FECHADAS (o OSF não oculta arquivos entre colaboradores).
     *
     * @return string caminho do .zip temporário
     */
    public function osfPackage(SystematicReview $review): string
    {
        $px = $review->filePrefix();
        $today = now()->format('Y-m-d');
        $files = [
            "01_Protocolo_e_Registro/pipeline_{$px}.json" => $this->pipelineJson($review),
            "01_Protocolo_e_Registro/{$px}_OSF_registration_responses.json" => $this->osfResponsesJson($review),
            "01_Protocolo_e_Registro/OSF_Form_{$px}.txt" => $this->osfFormText($review),
            "01_Protocolo_e_Registro/PROTOCOLO_PRISMA-P_{$px}.md" => app(SystematicReviewService::class)->protocolMarkdown($review),
            "01_Protocolo_e_Registro/{$px}_DESVIOS.csv" => self::csvString($this->deviationRows($review)),
            "02_Busca/{$px}_BUSCA_log.csv" => self::csvString($this->searchLogRows($review)),
            "03_Triagem/{$px}_PRISMA_contagens.csv" => self::csvString($this->prismaRows($review)),
            "04_Extracao_e_Risco_de_Vies/{$px}_EXTRACAO_modelo.csv" => self::csvString($this->extractionTemplateRows($review)),
            "04_Extracao_e_Risco_de_Vies/{$px}_dicionario_extracao.csv" => self::csvString($this->dictionaryRows($review)),
        ];
        if ($review->protocol_pdf_path && Storage::disk('local')->exists($review->protocol_pdf_path)) {
            $files["01_Protocolo_e_Registro/Protocol_{$px}_v{$review->protocol_version}.pdf"] = Storage::disk('local')->get($review->protocol_pdf_path);
        }
        if ($lock = $review->latestLock()) {
            $ex = app(SystematicReviewExtractionService::class);
            $files["04_Extracao_e_Risco_de_Vies/{$px}_EXTRACAO_travada_v{$lock->version}.csv"] = $lock->extraction_csv;
            $files["04_Extracao_e_Risco_de_Vies/{$px}_RoB_v{$lock->version}.csv"] = $lock->rob_csv;
            $files["04_Extracao_e_Risco_de_Vies/{$px}_CONTATOS_autores.csv"] = self::csvString($ex->contactRows($review));
            if ($review->llmAssistedExtraction() && filled($review->ai_extraction['model'] ?? null)) {
                $files["04_Extracao_e_Risco_de_Vies/{$px}_IA_extracao.csv"] = self::csvString($ex->aiRows($review));
                $files["04_Extracao_e_Risco_de_Vies/{$px}_IA_prompt_extracao.txt"] = (string) ($review->ai_extraction['prompt'] ?? '');
            }
            if ($review->gradeRows()->exists()) {
                $files["05_Analise/{$px}_GRADE_SoF.csv"] = self::csvString($ex->gradeRows($review));
            }
            $run = $review->analysisRuns()->where('rerun_matches', true)->latest()->first();
            if ($run) {
                $disk = Storage::disk('local');
                if ($run->script_path && $disk->exists($run->script_path)) {
                    $files['05_Analise/' . basename($run->script_path)] = $disk->get($run->script_path);
                }
                foreach ($run->outputs ?? [] as $o) {
                    if ($disk->exists($o['path'])) {
                        $files["05_Analise/resultados/{$o['name']}"] = $disk->get($o['path']);
                    }
                }
                if ($run->session_info) {
                    $files['05_Analise/sessionInfo.txt'] = $run->session_info;
                }
            }
        }
        foreach ([SystematicReview::STAGE_TA => 'TA', SystematicReview::STAGE_FT => 'FT'] as $stage => $tag) {
            if ($review->isStageClosed($stage)) {
                $closed = \Illuminate\Support\Carbon::parse(($review->stage_closed_at ?? [])[$stage] ?? now())->format('Y-m-d');
                $files["03_Triagem/{$px}_TRIAGEM_{$tag}_decisoes_{$closed}.csv"] = self::csvString($this->decisionRows($review, $stage));
            }
        }

        $manifest = "PACOTE OSF — {$px} — gerado em " . now()->format('d/m/Y H:i') . " (America/Sao_Paulo)\n"
            . "Projeto: {$review->title}\n"
            . 'Protocolo: versão ' . ($review->protocol_version ?: '1.0') . ($review->protocol_sha256 ? " · SHA-256 {$review->protocol_sha256}" : ' · NÃO CONGELADO') . "\n"
            . 'Registro OSF: ' . ($review->registration_id ?: 'ainda não registrado') . "\n\n"
            . "Envie cada pasta para o componente do OSF de mesmo nome. Só entram exportações de etapas FECHADAS;\n"
            . "nunca suba planilhas de triagem em andamento (o OSF não oculta arquivos entre colaboradores).\n\n"
            . "SHA-256 de cada arquivo:\n";
        foreach ($files as $name => $contents) {
            $manifest .= hash('sha256', $contents) . "  {$name}\n";
        }

        $tmp = tempnam(sys_get_temp_dir(), 'osfpkg');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach (self::OSF_COMPONENTS as $component) {
            $zip->addEmptyDir("{$px}_OSF_{$today}/{$component}");
        }
        foreach ($files as $name => $contents) {
            $zip->addFromString("{$px}_OSF_{$today}/{$name}", $contents);
        }
        $zip->addFromString("{$px}_OSF_{$today}/LEIA-ME_e_SHA256.txt", $manifest);
        $zip->close();

        return $tmp;
    }
}
