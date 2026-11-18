<?php

namespace App\Services;

use App\Models\SrAnalysisRun;
use App\Models\SrDatasetLock;
use App\Models\SrExtraction;
use App\Models\SrGradeRow;
use App\Models\SrStudy;
use App\Models\SystematicReview;
use App\Models\SystematicReviewScreener;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fases P7–P14 do pipeline: estudos, extração e risco de viés em duplicata
 * (cegos), reconciliação, trava da base com SHA-256, análise com reexecução
 * independente, GRADE, submissão e o cálculo da fase atual do projeto.
 */
class SystematicReviewExtractionService
{
    // ------------------------------------------------------------------
    // Abertura da extração (P7a) e estudos
    // ------------------------------------------------------------------

    public function openProblems(SystematicReview $review): array
    {
        $problems = [];
        if ($review->extractionOpen()) {
            $problems[] = 'A extração já foi aberta.';
        }
        if ($review->status !== SystematicReview::STATUS_SCREENING_DONE) {
            $problems[] = 'Encerre a triagem (texto completo) antes de abrir a extração.';
        }
        if ($this->includedRecords($review)->count() === 0) {
            $problems[] = 'Nenhum estudo incluído.';
        }
        if ($review->activeScreeners()->count() < 2) {
            $problems[] = 'A extração é em dupla: são necessários dois revisores ativos.';
        }

        return $problems;
    }

    protected function includedRecords(SystematicReview $review)
    {
        $column = $review->isStageClosed(SystematicReview::STAGE_FT) ? 'ft_decision' : 'ta_decision';

        return $review->records()->where($column, 'include')->orderBy('seq');
    }

    /** Cria um estudo por registro incluído (relatos do mesmo estudo podem ser mesclados depois). */
    public function open(SystematicReview $review, int $pilotSize = 3): void
    {
        if ($problems = $this->openProblems($review)) {
            throw new \RuntimeException(implode(' ', $problems));
        }
        DB::transaction(function () use ($review, $pilotSize) {
            foreach ($this->includedRecords($review)->get()->values() as $i => $r) {
                $first = Str::of((string) $r->authors)->before(',')->before(';')->trim()->toString();
                $review->studies()->create([
                    'label' => Str::limit(($first ?: 'Study') . ' ' . ($r->year ?: 's.d.'), 115, ''),
                    'record_ids' => [$r->id],
                    'position' => $i + 1,
                    'pilot' => $i < $pilotSize,
                ]);
            }
            $review->update([
                'extraction_opened_at' => now(),
                'extraction_pilot_size' => $pilotSize,
                'extraction_form_version' => $review->protocol_version ?: '1.0',
            ]);
        });
        $review->logEvent('Extração e risco de viés abertos (P7)', $review->studies()->count() . ' estudo(s); piloto em ' . $pilotSize);

        $sr = app(SystematicReviewService::class);
        foreach ($review->activeScreeners()->get() as $s) {
            $sr->notifyScreener($s, 'Nova etapa: extração de dados e risco de viés',
                "Olá, {$s->name}!\n\nA triagem de \"{$review->title}\" terminou e começa a extração de dados e a avaliação do risco de viés, em dupla e de forma independente."
                . ($pilotSize > 0 ? " Começamos por um piloto em {$pilotSize} estudo(s)." : '')
                . "\n\nAbra \"Extração e Risco de Viés\" no Portal do Aluno: o guia da etapa explica o que fazer, por quê e o que estudar.");
        }
    }

    /** Agrupa relatos do mesmo estudo (antes de haver extração enviada nos relatos mesclados). */
    public function merge(SrStudy $target, array $sourceIds): void
    {
        $sources = SrStudy::whereIn('id', $sourceIds)->where('systematic_review_id', $target->systematic_review_id)->where('id', '!=', $target->id)->get();
        foreach ($sources as $s) {
            if ($s->extractions()->where('status', SrExtraction::STATUS_SUBMITTED)->exists()) {
                throw new \RuntimeException("{$s->code()} já tem extração enviada; reabra-a antes de mesclar.");
            }
        }
        DB::transaction(function () use ($target, $sources) {
            $ids = $target->record_ids ?? [];
            foreach ($sources as $s) {
                $ids = array_merge($ids, $s->record_ids ?? []);
                $s->extractions()->delete();
                $s->update(['status' => 'merged', 'notes' => trim(($s->notes ?? '') . "\nMesclado em {$target->code()}")]);
            }
            $target->update(['record_ids' => array_values(array_unique($ids))]);
        });
        $target->systematicReview->logEvent("Relatos mesclados em {$target->code()} ({$target->label})", $sources->map->code()->implode(', '));
    }

    public function releasePilot(SystematicReview $review, ?string $note = null): void
    {
        $review->update(['extraction_pilot_released_at' => now()]);
        $review->logEvent('Piloto de extração concluído: extração completa liberada', $note);
        $sr = app(SystematicReviewService::class);
        foreach ($review->activeScreeners()->get() as $s) {
            $sr->notifyScreener($s, 'Extração completa liberada', "Olá, {$s->name}!\n\nO piloto da extração de \"{$review->title}\" foi conferido. Agora todos os estudos estão disponíveis em \"Extração e Risco de Viés\"."
                . (filled($note) ? "\n\nRecado do Prof. Atilio: {$note}" : ''));
        }
    }

    // ------------------------------------------------------------------
    // Formulário (gerado do protocolo)
    // ------------------------------------------------------------------

    /** @return array<int, array{name: string, type: string, options: array, description: string}> */
    public function fields(SystematicReview $review): array
    {
        return collect(data_get($review->pipeline, 'extraction.fields', []))->map(function ($f) {
            $type = (string) ($f['type'] ?? 'text');
            $options = [];
            if (str_starts_with($type, 'category')) {
                $options = array_values(array_filter(array_map('trim', explode('|', Str::after($type, ':')))));
                $type = 'category';
            }

            return ['name' => $f['name'], 'type' => in_array($type, ['text', 'integer', 'number', 'category'], true) ? $type : 'text',
                'options' => $options, 'description' => (string) ($f['description'] ?? '')];
        })->values()->all();
    }

    public function outcomeDefs(SystematicReview $review): array
    {
        return collect(data_get($review->pipeline, 'extraction.outcomes', []))->keyBy('id')->all();
    }

    public function robTools(SystematicReview $review): array
    {
        return collect(data_get($review->pipeline, 'risk_of_bias.tools', []))->map(fn ($t) => [
            'code' => $t['code'], 'name' => $t['name'] ?? $t['code'], 'domains' => array_values((array) ($t['domains'] ?? [])),
        ])->values()->all();
    }

    public const OUTCOME_NUMBERS = ['n', 'mean', 'sd', 'events', 'proportion', 'effect', 'ci_low', 'ci_high'];

    // ------------------------------------------------------------------
    // Extração do aluno (P7b)
    // ------------------------------------------------------------------

    public function availableStudies(SystematicReviewScreener $screener)
    {
        // Sempre a situação atual da revisão (piloto liberado, estudos mesclados).
        $review = SystematicReview::findOrFail($screener->systematic_review_id);
        $q = $review->activeStudies();

        return $review->inExtractionPilot() ? $q->where('pilot', true) : $q;
    }

    public function extractionFor(SystematicReviewScreener $screener, SrStudy $study): SrExtraction
    {
        return SrExtraction::firstOrNew(
            ['sr_study_id' => $study->id, 'systematic_review_screener_id' => $screener->id],
            ['form_version' => $screener->systematicReview->extraction_form_version, 'status' => SrExtraction::STATUS_DRAFT]
        );
    }

    /** Validações automáticas do pipeline (seção 6). */
    public function validate(SystematicReview $review, array $data, bool $strict): array
    {
        $errors = [];
        $values = (array) ($data['field_values'] ?? []);
        $sources = (array) ($data['sources'] ?? []);
        foreach ($this->fields($review) as $f) {
            $v = $values[$f['name']] ?? null;
            if (blank($v)) {
                continue;
            }
            if ($f['type'] === 'integer' && !preg_match('/^-?\d+$/', (string) $v)) {
                $errors[] = "{$f['name']}: use um número inteiro.";
            }
            if ($f['type'] === 'number' && !is_numeric(str_replace(',', '.', (string) $v))) {
                $errors[] = "{$f['name']}: use um número.";
            }
            if ($f['type'] === 'category' && $f['options'] && !in_array($v, $f['options'], true)) {
                $errors[] = "{$f['name']}: escolha uma das opções do protocolo.";
            }
            if (in_array($f['type'], ['integer', 'number'], true) && blank($sources[$f['name']] ?? null)) {
                $errors[] = "{$f['name']}: informe de onde saiu o número (página, tabela ou figura).";
            }
        }

        $defs = $this->outcomeDefs($review);
        foreach (array_values((array) ($data['outcomes'] ?? [])) as $i => $o) {
            $row = 'Desfecho ' . ($i + 1) . (filled($o['outcome_id'] ?? null) ? " ({$o['outcome_id']})" : '');
            if (blank($o['outcome_id'] ?? null) || !isset($defs[$o['outcome_id']])) {
                $errors[] = "{$row}: escolha um desfecho do protocolo.";
            }
            $num = fn ($k) => isset($o[$k]) && $o[$k] !== '' && $o[$k] !== null ? (float) str_replace(',', '.', (string) $o[$k]) : null;
            $hasNumber = collect(self::OUTCOME_NUMBERS)->contains(fn ($k) => $num($k) !== null);
            if ($hasNumber && blank($o['source_location'] ?? null)) {
                $errors[] = "{$row}: informe de onde saíram os números (página, tabela ou figura).";
            }
            if ($num('n') !== null && ($num('n') < 0 || floor($num('n')) != $num('n'))) {
                $errors[] = "{$row}: n deve ser um número inteiro ≥ 0.";
            }
            if ($num('events') !== null && $num('n') !== null && $num('events') > $num('n')) {
                $errors[] = "{$row}: eventos não podem ser maiores que n.";
            }
            if ($num('sd') !== null && $num('sd') <= 0) {
                $errors[] = "{$row}: o desvio-padrão deve ser maior que zero.";
            }
            if ($num('proportion') !== null && ($num('proportion') < 0 || $num('proportion') > 1)) {
                $errors[] = "{$row}: a proporção deve estar entre 0 e 1 (ex.: 12% = 0,12).";
            }
        }

        if ($strict) {
            foreach ($this->robTools($review) as $tool) {
                foreach ($tool['domains'] as $domain) {
                    $d = $data['rob'][$tool['code']][$domain] ?? [];
                    if (blank($d['judgement'] ?? null)) {
                        $errors[] = "Risco de viés ({$tool['code']}) — \"{$domain}\": escolha o julgamento.";
                    } elseif (blank($d['support'] ?? null)) {
                        $errors[] = "Risco de viés ({$tool['code']}) — \"{$domain}\": justifique com o trecho/página do artigo.";
                    }
                }
                if (blank($data['rob'][$tool['code']]['_overall'] ?? null)) {
                    $errors[] = "Risco de viés ({$tool['code']}): escolha o julgamento geral.";
                }
            }
        }

        return $errors;
    }

    public function save(SystematicReviewScreener $screener, SrStudy $study, array $data, bool $submit = false): SrExtraction
    {
        $screener->unsetRelation('systematicReview');
        $review = $screener->systematicReview;
        if ($screener->removed_at) {
            throw new \RuntimeException('Você não participa mais deste projeto.');
        }
        if (!$screener->hasSignedTerms()) {
            throw new \RuntimeException('Assine o termo antes de extrair.');
        }
        if (!$review->extractionOpen() || $study->systematic_review_id !== $review->id
            || !$this->availableStudies($screener)->whereKey($study->id)->exists()) {
            throw new \RuntimeException('Este estudo não está disponível para você agora.');
        }
        $extraction = $this->extractionFor($screener, $study);
        if ($extraction->isSubmitted()) {
            throw new \RuntimeException('Você já enviou este estudo. Peça ao professor para reabrir se precisar corrigir.');
        }
        if ($errors = $this->validate($review, $data, $submit)) {
            throw new \RuntimeException(implode("\n", $errors));
        }
        // Protocolo v1.1: o que o modelo pré-preencheu só é enviado depois de conferido no PDF.
        if ($submit && $extraction->aiPrefilled() && empty($data['ai_verified'])) {
            throw new \RuntimeException('Este estudo veio pré-preenchido por IA: marque que conferiu CADA valor no PDF (e apagou o que não está no artigo) antes de enviar.');
        }

        $extraction->fill([
            'field_values' => $data['field_values'] ?? [],
            'sources' => $data['sources'] ?? [],
            'outcomes' => array_values($data['outcomes'] ?? []),
            'rob' => $data['rob'] ?? [],
            'notes' => $data['notes'] ?? null,
            'form_version' => $review->extraction_form_version,
        ]);
        if ($submit) {
            $extraction->status = SrExtraction::STATUS_SUBMITTED;
            $extraction->submitted_at = now();
            if ($extraction->aiPrefilled()) {
                $extraction->ai_verified_at = now();
                $extraction->ai_changes = $this->aiChanges($extraction->ai_prefill, $extraction->field_values ?? [], $extraction->outcomes ?? []);
            }
        }
        $extraction->save();

        if ($submit) {
            $this->checkCompletion($review->fresh());
        }

        return $extraction;
    }

    public function reopen(SrExtraction $extraction, ?string $reason = null): void
    {
        $extraction->update(['status' => SrExtraction::STATUS_DRAFT, 'submitted_at' => null]);
        $extraction->study->update(['reconciled_at' => null]);
        $review = $extraction->study->systematicReview;
        $review->logEvent("Extração reaberta: {$extraction->study->code()} — {$extraction->screener->name}", $reason);
        app(SystematicReviewService::class)->notifyScreener($extraction->screener, 'Extração reaberta para correção',
            "Olá, {$extraction->screener->name}!\n\nO professor reabriu a sua extração de {$extraction->study->label} ({$extraction->study->code()})." . (filled($reason) ? "\n\nMotivo: {$reason}" : ''));
    }

    /** Avisa o professor quando os dois revisores enviaram tudo o que está disponível. */
    protected function checkCompletion(SystematicReview $review): void
    {
        $studies = $review->inExtractionPilot() ? $review->activeStudies()->where('pilot', true)->get() : $review->activeStudies()->get();
        $screeners = $review->activeScreeners()->count();
        $submitted = SrExtraction::whereIn('sr_study_id', $studies->pluck('id'))->where('status', SrExtraction::STATUS_SUBMITTED)->count();
        if ($studies->isEmpty() || $submitted < $studies->count() * $screeners) {
            return;
        }
        $pilot = $review->inExtractionPilot();
        $key = $pilot ? 'Piloto de extração enviado pelos dois revisores' : 'Extração e RoB enviados pelos dois revisores';
        if (collect($review->history ?? [])->contains(fn ($e) => $e['event'] === $key)) {
            return;
        }
        $review->logEvent($key);
        app(SystematicReviewService::class)->notifyProfessor($pilot ? 'Piloto de extração concluído' : 'Extração concluída: reconciliar',
            $pilot ? "Os dois revisores enviaram o piloto de extração de \"{$review->title}\". Compare as divergências, ajuste só as definições do formulário (sem mudar os campos registrados) e libere a extração completa."
                : "Os dois revisores enviaram a extração e o risco de viés de todos os estudos de \"{$review->title}\". Reconcilie as divergências e trave a base.", $review);
    }

    // ------------------------------------------------------------------
    // Pré-extração por modelo de linguagem (protocolo v1.1, só Revisor 1)
    // ------------------------------------------------------------------

    /** Prompt fixo, gerado do dicionário de extração do protocolo. */
    public function aiPrompt(SystematicReview $review): string
    {
        $fields = collect($this->fields($review))->map(fn ($f) => "- \"{$f['name']}\" ({$f['type']}" . ($f['options'] ? ': ' . implode(' | ', $f['options']) : '') . ')'
            . ($f['description'] ? " — {$f['description']}" : ''))->implode("\n");
        $outcomes = collect($this->outcomeDefs($review))->map(fn ($o, $id) => "- {$id}: " . ($o['name'] ?? '') . (filled($o['measure'] ?? null) ? " ({$o['measure']})" : ''))->implode("\n");

        return "You are assisting data extraction for a registered systematic review (protocol {$review->protocol_code} v{$review->protocol_version}).\n"
            . "Read the attached full-text article and return ONLY a JSON object, with no commentary, in this format:\n"
            . "{\"field_values\": {\"<field name>\": \"<value>\"}, \"sources\": {\"<field name>\": \"<page, table or figure>\"},\n"
            . " \"outcomes\": [{\"outcome_id\": \"<id>\", \"group_label\": \"\", \"timepoint\": \"\", \"n\": null, \"events\": null, \"mean\": null, \"sd\": null,\n"
            . "   \"proportion\": null, \"effect\": null, \"ci_low\": null, \"ci_high\": null, \"source_location\": \"<page, table or figure>\", \"digitised\": false}]}\n\n"
            . "Rules:\n"
            . "1. Copy raw values exactly as reported. Never calculate, convert, impute or round.\n"
            . "2. Give the page, table or figure for every value. If a value is not reported, leave it out.\n"
            . "3. Use only the field names and outcome ids listed below. Proportions between 0 and 1.\n"
            . "4. One outcome row per outcome x group x time point. Mark digitised=true only for values read from a figure.\n"
            . "5. Do not assess risk of bias and do not interpret results.\n\n"
            . "Fields:\n{$fields}\n\nOutcomes:\n{$outcomes}\n";
    }

    /** Grava o modelo usado (nome, versão) e o prompt fixo vigente, com SHA-256. */
    public function setAiModel(SystematicReview $review, string $model, string $version, string $by): void
    {
        $prompt = $this->aiPrompt($review);
        $review->update(['ai_extraction' => array_merge($review->ai_extraction ?? [], [
            'model' => trim($model), 'version' => trim($version), 'prompt' => $prompt, 'prompt_sha256' => hash('sha256', $prompt),
            'set_by' => $by, 'set_at' => now()->format('Y-m-d H:i:s'),
        ])]);
        $review->logEvent('Modelo de IA da pré-extração registrado', trim($model) . ' ' . trim($version) . ' · prompt SHA-256 ' . substr(hash('sha256', $prompt), 0, 12));
    }

    /**
     * Pré-preenche a extração do Revisor 1 (líder) com a saída JSON do modelo.
     * O Revisor 2 nunca recebe nem vê o pré-preenchimento.
     */
    public function importAiPrefill(SrStudy $study, string $json, string $by): SrExtraction
    {
        $review = $study->systematicReview;
        if (!$review->llmAssistedExtraction()) {
            throw new \RuntimeException('O protocolo registrado deste projeto não prevê pré-extração por IA.');
        }
        if (!$review->extractionOpen()) {
            throw new \RuntimeException('Abra a extração antes.');
        }
        if (blank($review->ai_extraction['model'] ?? null)) {
            throw new \RuntimeException('Registre antes o modelo e a versão da IA ("Modelo de IA e prompt").');
        }
        $lead = $review->leadScreener();
        if (!$lead) {
            throw new \RuntimeException('Não há Revisor 1 (líder) neste projeto.');
        }
        $data = json_decode(trim(preg_replace('/^```(json)?|```$/m', '', trim($json))), true);
        if (!is_array($data)) {
            throw new \RuntimeException('A resposta da IA não é um JSON válido. Cole só o objeto JSON.');
        }

        $known = collect($this->fields($review))->pluck('name')->all();
        $defs = $this->outcomeDefs($review);
        $values = collect((array) ($data['field_values'] ?? []))->only($known)->map(fn ($v) => is_scalar($v) ? trim((string) $v) : null)->filter(fn ($v) => filled($v))->all();
        $sources = collect((array) ($data['sources'] ?? []))->only(array_keys($values))->map(fn ($v) => is_scalar($v) ? trim((string) $v) : null)->filter()->all();
        $outcomes = collect((array) ($data['outcomes'] ?? []))->filter(fn ($o) => is_array($o) && isset($defs[$o['outcome_id'] ?? null]))
            ->map(fn ($o) => collect($o)->only(array_merge(['outcome_id', 'group_label', 'timepoint', 'source_location', 'digitised'], self::OUTCOME_NUMBERS))
                ->map(fn ($v, $k) => $k === 'digitised' ? (bool) $v : (is_scalar($v) ? (string) $v : null))->all())
            ->values()->all();
        if (!$values && !$outcomes) {
            throw new \RuntimeException('Nenhum campo ou desfecho do protocolo foi reconhecido no JSON.');
        }

        $extraction = $this->extractionFor($lead, $study);
        if ($extraction->isSubmitted()) {
            throw new \RuntimeException('O Revisor 1 já enviou este estudo.');
        }
        if ($extraction->exists && !$extraction->aiPrefilled() && (array_filter((array) $extraction->field_values) || $extraction->outcomes)) {
            throw new \RuntimeException('O Revisor 1 já começou este estudo sem IA: o pré-preenchimento não substitui o que ele digitou.');
        }
        $extraction->fill([
            'field_values' => $values,
            'sources' => $sources,
            'outcomes' => $outcomes,
            'ai_prefill' => ['field_values' => $values, 'sources' => $sources, 'outcomes' => $outcomes,
                'model' => $review->ai_extraction['model'], 'version' => $review->ai_extraction['version'] ?? null, 'prompt_sha256' => $review->ai_extraction['prompt_sha256'] ?? null],
            'ai_prefilled_at' => now(),
            'ai_verified_at' => null,
            'ai_changes' => null,
            'form_version' => $review->extraction_form_version,
        ]);
        $extraction->status = SrExtraction::STATUS_DRAFT;
        $extraction->save();

        $ai = $review->ai_extraction ?? [];
        $ai['first_used_at'] ??= now()->format('Y-m-d');
        $ai['last_used_at'] = now()->format('Y-m-d');
        $review->forceFill(['ai_extraction' => $ai])->save();
        $review->logEvent("Pré-extração por IA importada: {$study->code()} (Revisor 1: {$lead->name})", count($values) . ' campo(s), ' . count($outcomes) . " linha(s) de desfecho · por {$by}");

        return $extraction;
    }

    /** Quantos valores propostos pelo modelo o Revisor 1 manteve, alterou ou apagou. */
    public function aiChanges(array $prefill, array $finalValues, array $finalOutcomes): array
    {
        $proposed = $kept = $changed = $removed = 0;
        foreach ((array) ($prefill['field_values'] ?? []) as $name => $v) {
            $proposed++;
            $f = $finalValues[$name] ?? null;
            blank($f) ? $removed++ : (self::norm($f) === self::norm($v) ? $kept++ : $changed++);
        }
        $final = collect($finalOutcomes)->keyBy(fn ($o) => self::outcomeKey((array) $o));
        foreach ((array) ($prefill['outcomes'] ?? []) as $o) {
            $row = $final->get(self::outcomeKey($o));
            foreach (self::OUTCOME_NUMBERS as $n) {
                if (!isset($o[$n]) || $o[$n] === '' || $o[$n] === null) {
                    continue;
                }
                $proposed++;
                $f = $row[$n] ?? null;
                ($f === null || $f === '') ? $removed++ : (self::norm($f) === self::norm($o[$n]) ? $kept++ : $changed++);
            }
        }

        return ['proposed' => $proposed, 'kept' => $kept, 'changed' => $changed, 'removed' => $removed,
            'pct_corrected' => $proposed ? round(100 * ($changed + $removed) / $proposed, 1) : 0.0];
    }

    /** Planilha da IA na extração: proposto × final, por estudo (vai para o OSF, componente 04). */
    public function aiRows(SystematicReview $review): array
    {
        $ai = $review->ai_extraction ?? [];
        $rows = [['study_id', 'study_label', 'reviewer', 'model', 'version', 'prompt_sha256', 'prefilled_at', 'verified_at', 'proposed', 'kept', 'changed', 'removed', 'pct_corrected']];
        $extractions = SrExtraction::whereIn('sr_study_id', $review->studies()->pluck('id'))->whereNotNull('ai_prefilled_at')->with(['study', 'screener'])->get();
        foreach ($extractions as $e) {
            $c = $e->ai_changes ?? [];
            $rows[] = [$e->study?->code(), $e->study?->label, $e->screener?->name, $e->ai_prefill['model'] ?? ($ai['model'] ?? ''), $e->ai_prefill['version'] ?? '',
                $e->ai_prefill['prompt_sha256'] ?? '', $e->ai_prefilled_at?->toIso8601String(), $e->ai_verified_at?->toIso8601String(),
                $c['proposed'] ?? '', $c['kept'] ?? '', $c['changed'] ?? '', $c['removed'] ?? '', $c['pct_corrected'] ?? ''];
        }
        $all = $extractions->pluck('ai_changes')->filter();
        $p = $all->sum('proposed');
        $rows[] = ['TOTAL', '', '', $ai['model'] ?? '', $ai['version'] ?? '', $ai['prompt_sha256'] ?? '', $ai['first_used_at'] ?? '', $ai['last_used_at'] ?? '',
            $p, $all->sum('kept'), $all->sum('changed'), $all->sum('removed'), $p ? round(100 * ($all->sum('changed') + $all->sum('removed')) / $p, 1) : ''];

        return $rows;
    }

    // ------------------------------------------------------------------
    // Reconciliação (P7c)
    // ------------------------------------------------------------------

    protected static function norm($v): string
    {
        $s = Str::of((string) $v)->squish()->lower()->toString();

        return is_numeric(str_replace(',', '.', $s)) ? (string) (float) str_replace(',', '.', $s) : $s;
    }

    protected static function outcomeKey(array $o): string
    {
        return ($o['outcome_id'] ?? '') . '|' . Str::lower(trim((string) ($o['group_label'] ?? ''))) . '|' . Str::lower(trim((string) ($o['timepoint'] ?? '')));
    }

    /** @return array{0: ?SrExtraction, 1: ?SrExtraction} */
    public function pair(SrStudy $study): array
    {
        $list = $study->extractions()->where('status', SrExtraction::STATUS_SUBMITTED)->orderBy('systematic_review_screener_id')->get()->values();

        return [$list[0] ?? null, $list[1] ?? null];
    }

    /**
     * Divergências entre as duas extrações enviadas.
     *
     * @return array<int, array{key: string, label: string, a: mixed, b: mixed}>
     */
    public function divergences(SrStudy $study): array
    {
        [$a, $b] = $this->pair($study);
        if (!$a || !$b) {
            return [];
        }
        $review = $study->systematicReview;
        $out = [];
        foreach ($this->fields($review) as $f) {
            $va = $a->field_values[$f['name']] ?? null;
            $vb = $b->field_values[$f['name']] ?? null;
            if (self::norm($va) !== self::norm($vb)) {
                $out[] = ['key' => 'field:' . $f['name'], 'label' => "Campo: {$f['name']}", 'a' => $va, 'b' => $vb];
            }
        }
        $oa = collect($a->outcomes ?? [])->keyBy(fn ($o) => self::outcomeKey($o));
        $ob = collect($b->outcomes ?? [])->keyBy(fn ($o) => self::outcomeKey($o));
        foreach ($oa->keys()->merge($ob->keys())->unique() as $k) {
            $ra = $oa->get($k);
            $rb = $ob->get($k);
            $title = 'Desfecho ' . str_replace('|', ' · ', trim($k, '|'));
            if (!$ra || !$rb) {
                $out[] = ['key' => 'row:' . $k, 'label' => "{$title} (só um revisor extraiu)", 'a' => $ra ? 'extraído' : '—', 'b' => $rb ? 'extraído' : '—'];

                continue;
            }
            foreach (self::OUTCOME_NUMBERS as $n) {
                if (self::norm($ra[$n] ?? null) !== self::norm($rb[$n] ?? null)) {
                    $out[] = ['key' => "out:{$k}:{$n}", 'label' => "{$title} — {$n}", 'a' => $ra[$n] ?? null, 'b' => $rb[$n] ?? null];
                }
            }
        }
        foreach ($this->robTools($review) as $tool) {
            foreach (array_merge($tool['domains'], ['_overall']) as $domain) {
                $ja = $domain === '_overall' ? ($a->rob[$tool['code']]['_overall'] ?? null) : ($a->rob[$tool['code']][$domain]['judgement'] ?? null);
                $jb = $domain === '_overall' ? ($b->rob[$tool['code']]['_overall'] ?? null) : ($b->rob[$tool['code']][$domain]['judgement'] ?? null);
                if ($ja !== $jb) {
                    $out[] = ['key' => "rob:{$tool['code']}:{$domain}", 'label' => "RoB {$tool['code']}: " . ($domain === '_overall' ? 'julgamento geral' : $domain),
                        'a' => SrExtraction::ROB_JUDGEMENTS[$ja] ?? $ja, 'b' => SrExtraction::ROB_JUDGEMENTS[$jb] ?? $jb];
                }
            }
        }

        return $out;
    }

    /**
     * Grava a versão reconciliada: base = revisor A; cada divergência recebe
     * a escolha ('a' | 'b'). Linhas de desfecho só de um revisor: 'a' mantém
     * a linha de quem a extraiu, 'b' descarta.
     */
    public function reconcile(SrStudy $study, array $choices, ?string $notes, string $by): void
    {
        [$a, $b] = $this->pair($study);
        if (!$a || !$b) {
            throw new \RuntimeException('Os dois revisores precisam ter enviado este estudo.');
        }
        $divs = collect($this->divergences($study))->keyBy('key');
        foreach ($divs as $key => $d) {
            if (!in_array($choices[$key] ?? null, ['a', 'b'], true)) {
                throw new \RuntimeException("Escolha a versão correta para: {$d['label']}.");
            }
        }

        $values = $a->field_values ?? [];
        $sources = $a->sources ?? [];
        foreach ($divs as $key => $d) {
            if (str_starts_with($key, 'field:') && $choices[$key] === 'b') {
                $name = Str::after($key, 'field:');
                $values[$name] = $b->field_values[$name] ?? null;
                $sources[$name] = $b->sources[$name] ?? null;
            }
        }
        $oa = collect($a->outcomes ?? [])->keyBy(fn ($o) => self::outcomeKey($o));
        $ob = collect($b->outcomes ?? [])->keyBy(fn ($o) => self::outcomeKey($o));
        $rows = [];
        foreach ($oa->keys()->merge($ob->keys())->unique() as $k) {
            $ra = $oa->get($k);
            $rb = $ob->get($k);
            if (!$ra || !$rb) {
                if (($choices['row:' . $k] ?? 'a') === 'a') {
                    $rows[] = $ra ?: $rb;
                }

                continue;
            }
            $row = $ra;
            foreach (self::OUTCOME_NUMBERS as $n) {
                if (($choices["out:{$k}:{$n}"] ?? 'a') === 'b') {
                    $row[$n] = $rb[$n] ?? null;
                    $row['source_location'] = trim(($ra['source_location'] ?? '') . ' / ' . ($rb['source_location'] ?? ''), ' /');
                }
            }
            $rows[] = $row;
        }
        $rob = $a->rob ?? [];
        foreach ($divs as $key => $d) {
            if (str_starts_with($key, 'rob:') && $choices[$key] === 'b') {
                [, $tool, $domain] = explode(':', $key, 3);
                if ($domain === '_overall') {
                    $rob[$tool]['_overall'] = $b->rob[$tool]['_overall'] ?? null;
                } else {
                    $rob[$tool][$domain] = $b->rob[$tool][$domain] ?? null;
                }
            }
        }

        $study->update([
            'reconciled_values' => ['values' => $values, 'sources' => $sources],
            'reconciled_outcomes' => array_values($rows),
            'reconciled_rob' => $rob,
            'reconciled_at' => now(),
            'reconciled_by' => $by,
            'reconcile_notes' => $notes,
        ]);
        $study->systematicReview->logEvent("Estudo reconciliado: {$study->code()} ({$study->label})", count($divs) . ' divergência(s) resolvida(s) por ' . $by);
    }

    // ------------------------------------------------------------------
    // Trava da base (P7c → P8)
    // ------------------------------------------------------------------

    public function lockProblems(SystematicReview $review): array
    {
        $problems = [];
        $studies = $review->activeStudies()->with('extractions')->get();
        if ($studies->isEmpty()) {
            $problems[] = 'Não há estudos.';
        }
        $screeners = $review->activeScreeners()->count();
        foreach ($studies as $s) {
            if ($s->submittedCount() < min(2, max(1, $screeners))) {
                $problems[] = "{$s->code()} ({$s->label}): falta extração enviada pelos dois revisores.";
            } elseif (!$s->isReconciled()) {
                $problems[] = "{$s->code()} ({$s->label}): divergências ainda não reconciliadas.";
            } else {
                foreach ($this->validate($review, ['field_values' => $s->reconciled_values['values'] ?? [], 'sources' => $s->reconciled_values['sources'] ?? [],
                    'outcomes' => $s->reconciled_outcomes ?? [], 'rob' => $s->reconciled_rob ?? []], true) as $e) {
                    $problems[] = "{$s->code()}: {$e}";
                }
            }
        }
        $dupes = $studies->groupBy(fn ($s) => Str::lower($s->label))->filter(fn ($g) => $g->count() > 1);
        foreach ($dupes as $label => $g) {
            $problems[] = "Rótulos repetidos (\"{$label}\": " . $g->map->code()->implode(', ') . '): confirme se não são o mesmo estudo (amostras sobrepostas) e renomeie (ex.: "Silva 2019a").';
        }
        $last = $review->latestLock();
        if ($last && !$studies->contains(fn ($s) => $s->reconciled_at && $s->reconciled_at->gt($last->created_at))) {
            $problems[] = "Nada mudou desde a versão travada v{$last->version}.";
        }

        return array_values(array_unique($problems));
    }

    public function lock(SystematicReview $review, string $by, ?string $justification = null): SrDatasetLock
    {
        if ($problems = $this->lockProblems($review)) {
            throw new \RuntimeException(implode("\n", $problems));
        }
        $last = $review->latestLock();
        if ($last && blank($justification)) {
            throw new \RuntimeException('Nova versão da base travada exige justificativa (fica na trilha de auditoria).');
        }
        $extraction = SystematicReviewProtocolService::csvString($this->extractionRows($review));
        $rob = SystematicReviewProtocolService::csvString($this->robRows($review));
        $lock = $review->datasetLocks()->create([
            'version' => ($last?->version ?? 0) + 1,
            'extraction_csv' => $extraction,
            'extraction_sha256' => hash('sha256', $extraction),
            'rob_csv' => $rob,
            'rob_sha256' => hash('sha256', $rob),
            'locked_by' => $by,
            'justification' => $justification,
        ]);
        $review->logEvent("Base travada v{$lock->version}", "extração SHA-256 " . substr($lock->extraction_sha256, 0, 16) . '… · RoB ' . substr($lock->rob_sha256, 0, 16) . '…' . ($justification ? " · {$justification}" : ''));
        $sr = app(SystematicReviewService::class);
        $sr->tick($review, 'extraction');
        $sr->tick($review, 'rob');
        app(SystematicReviewProtocolService::class)->notifyOsfPending($review->fresh(), "A base de extração foi travada (versão {$lock->version}).");

        return $lock;
    }

    /** Base reconciliada em formato longo (estudo × desfecho), no padrão do modelo de extração. */
    public function extractionRows(SystematicReview $review): array
    {
        $fields = collect($this->fields($review))->pluck('name')->all();
        $header = array_merge(['study_id', 'study_label', 'record_ids', 'doi', 'pmid', 'form_version'], $fields, array_map(fn ($f) => "{$f}__source", $fields),
            ['outcome_id', 'outcome_name', 'timepoint', 'group_label', 'n', 'mean', 'sd', 'events', 'proportion', 'effect', 'ci_low', 'ci_high', 'source_location', 'digitised']);
        $rows = [$header];
        $defs = $this->outcomeDefs($review);
        foreach ($review->activeStudies()->get() as $s) {
            $recs = $s->recordsQuery()->get();
            $base = [$s->code(), $s->label, $recs->map(fn ($r) => sprintf('REC-%06d', $r->id))->implode(' '), $recs->pluck('doi')->filter()->implode(' '),
                $recs->pluck('pmid')->filter()->implode(' '), $review->extraction_form_version];
            $values = $s->reconciled_values['values'] ?? [];
            $sources = $s->reconciled_values['sources'] ?? [];
            $base = array_merge($base, array_map(fn ($f) => $values[$f] ?? '', $fields), array_map(fn ($f) => $sources[$f] ?? '', $fields));
            $outcomes = $s->reconciled_outcomes ?: [[]];
            foreach ($outcomes as $o) {
                $rows[] = array_merge($base, [$o['outcome_id'] ?? '', $defs[$o['outcome_id'] ?? '']['name'] ?? '', $o['timepoint'] ?? '', $o['group_label'] ?? '',
                    $o['n'] ?? '', $o['mean'] ?? '', $o['sd'] ?? '', $o['events'] ?? '', $o['proportion'] ?? '', $o['effect'] ?? '', $o['ci_low'] ?? '', $o['ci_high'] ?? '',
                    $o['source_location'] ?? '', !empty($o['digitised']) ? 'true' : 'false']);
            }
        }

        return $rows;
    }

    public function robRows(SystematicReview $review): array
    {
        $rows = [['study_id', 'study_label', 'tool', 'domain', 'judgement', 'support']];
        foreach ($review->activeStudies()->get() as $s) {
            foreach ($this->robTools($review) as $tool) {
                foreach ($tool['domains'] as $domain) {
                    $d = $s->reconciled_rob[$tool['code']][$domain] ?? [];
                    $rows[] = [$s->code(), $s->label, $tool['code'], $domain, $d['judgement'] ?? '', $d['support'] ?? ''];
                }
                $rows[] = [$s->code(), $s->label, $tool['code'], 'Overall', $s->reconciled_rob[$tool['code']]['_overall'] ?? '', ''];
            }
        }

        return $rows;
    }

    /** As duas extrações lado a lado (auditoria da duplicata). */
    public function duplicateRows(SystematicReview $review): array
    {
        $rows = [['study_id', 'study_label', 'reviewer', 'status', 'submitted_at', 'item', 'value', 'source_location']];
        foreach ($review->activeStudies()->with('extractions.screener')->get() as $s) {
            foreach ($s->extractions as $e) {
                foreach (($e->field_values ?? []) as $k => $v) {
                    $rows[] = [$s->code(), $s->label, $e->screener?->name, $e->status, $e->submitted_at?->toIso8601String(), "field:{$k}", $v, $e->sources[$k] ?? ''];
                }
                foreach (($e->outcomes ?? []) as $o) {
                    foreach (self::OUTCOME_NUMBERS as $n) {
                        if (($o[$n] ?? '') !== '' && ($o[$n] ?? null) !== null) {
                            $rows[] = [$s->code(), $s->label, $e->screener?->name, $e->status, $e->submitted_at?->toIso8601String(), 'outcome:' . self::outcomeKey($o) . ":{$n}", $o[$n], $o['source_location'] ?? ''];
                        }
                    }
                }
                foreach (($e->rob ?? []) as $tool => $domains) {
                    foreach ((array) $domains as $domain => $d) {
                        $rows[] = [$s->code(), $s->label, $e->screener?->name, $e->status, $e->submitted_at?->toIso8601String(), "rob:{$tool}:{$domain}",
                            is_array($d) ? ($d['judgement'] ?? '') : $d, is_array($d) ? ($d['support'] ?? '') : ''];
                    }
                }
            }
        }

        return $rows;
    }

    // ------------------------------------------------------------------
    // Análise (P8) e GRADE (P9)
    // ------------------------------------------------------------------

    /** @param array<int, array{name: string, contents: string}> $outputs */
    public function registerAnalysis(SystematicReview $review, string $scriptName, string $scriptContents, array $outputs, ?string $sessionInfo, string $by, ?string $notes = null, bool $postHoc = false): SrAnalysisRun
    {
        $lock = $review->latestLock();
        if (!$lock) {
            throw new \RuntimeException('Trave a base antes de registrar a análise: o script roda sobre a versão travada.');
        }
        $run = $review->analysisRuns()->create([
            'sr_dataset_lock_id' => $lock->id,
            'script_name' => $scriptName,
            'script_sha256' => hash('sha256', $scriptContents),
            'session_info' => $sessionInfo,
            'run_by' => $by,
            'notes' => $notes,
            'post_hoc' => $postHoc,
        ]);
        $dir = 'systematic-reviews/analysis/' . $review->filePrefix() . "/run_{$run->id}";
        Storage::disk('local')->put("{$dir}/" . basename($scriptName), $scriptContents);
        $files = [];
        foreach ($outputs as $o) {
            $path = "{$dir}/outputs/" . basename($o['name']);
            Storage::disk('local')->put($path, $o['contents']);
            $files[] = ['name' => basename($o['name']), 'path' => $path, 'sha256' => hash('sha256', $o['contents'])];
        }
        $run->update(['script_path' => "{$dir}/" . basename($scriptName), 'outputs' => $files]);
        $review->logEvent("Análise registrada sobre a base v{$lock->version}", basename($scriptName) . ' · SHA-256 ' . substr($run->script_sha256, 0, 16) . '…' . ($postHoc ? ' · POST HOC' : ''));
        if ($postHoc) {
            app(SystematicReviewProtocolService::class)->addDeviation($review, ['type' => 'deviation', 'section' => 'Análise estatística (post hoc)',
                'description' => 'Análise não prevista no protocolo: ' . basename($scriptName) . (filled($notes) ? " — {$notes}" : ''),
                'justification' => $notes ?: 'Rotulada como post hoc no artigo.', 'requested_by' => $by]);
        }

        return $run;
    }

    public function confirmRerun(SrAnalysisRun $run, string $by, bool $matches, ?string $notes = null): void
    {
        if (Str::lower(trim($by)) === Str::lower(trim((string) $run->run_by))) {
            throw new \RuntimeException('A reexecução tem de ser feita por outra pessoa (não por quem rodou a análise).');
        }
        $run->update(['rerun_by' => $by, 'rerun_at' => now(), 'rerun_matches' => $matches, 'rerun_notes' => $notes]);
        $review = $run->systematicReview;
        $review->logEvent('Reexecução independente ' . ($matches ? 'confirmou resultado idêntico' : 'encontrou DIFERENÇA'), "por {$by}" . ($notes ? " · {$notes}" : ''));
        if ($matches) {
            app(SystematicReviewService::class)->tick($review, 'analysis');
            app(SystematicReviewProtocolService::class)->notifyOsfPending($review->fresh(), 'A análise foi reexecutada com resultado idêntico.');
        }
    }

    /** Cria as linhas do GRADE a partir dos desfechos do protocolo (primários primeiro). */
    public function seedGrade(SystematicReview $review): int
    {
        $n = 0;
        foreach (collect(data_get($review->pipeline, 'extraction.outcomes', []))->sortBy(fn ($o) => ($o['priority'] ?? '') === 'primary' ? 0 : 1) as $o) {
            $row = $review->gradeRows()->firstOrCreate(['outcome_id' => $o['id']], ['outcome_name' => Str::limit($o['name'] ?? $o['id'], 495, '')]);
            $n += $row->wasRecentlyCreated ? 1 : 0;
        }

        return $n;
    }

    public function gradeRows(SystematicReview $review): array
    {
        $rows = [['outcome_id', 'outcome', 'k', 'n', 'effect', 'ci_95', 'prediction_interval', 'certainty', 'downgrade_rob', 'downgrade_inconsistency', 'downgrade_indirectness', 'downgrade_imprecision', 'downgrade_publication_bias', 'notes']];
        foreach ($review->gradeRows()->get() as $g) {
            $r = $g->reasons ?? [];
            $rows[] = [$g->outcome_id, $g->outcome_name, $g->k, $g->n, $g->effect, $g->ci, $g->pi, SrGradeRow::CERTAINTY[$g->certainty] ?? '',
                $r['rob'] ?? '', $r['inconsistency'] ?? '', $r['indirectness'] ?? '', $r['imprecision'] ?? '', $r['pubbias'] ?? '', $g->notes];
        }

        return $rows;
    }

    public function contactRows(SystematicReview $review): array
    {
        $rows = [['study_id', 'author', 'email', 'request', 'sent_at', 'reminder_2w', 'reminder_4w', 'responded_at', 'response']];
        foreach ($review->authorContacts()->with('study')->get() as $c) {
            $rows[] = [$c->study?->code(), $c->author, $c->email, $c->request, $c->sent_at?->toDateString(), $c->reminder1_at?->toDateString(), $c->reminder2_at?->toDateString(), $c->responded_at?->toDateString(), $c->response];
        }

        return $rows;
    }

    public function meetingRows(SystematicReview $review): array
    {
        $rows = [['held_at', 'participants', 'decisions', 'pending', 'next_meeting']];
        foreach ($review->meetings()->get() as $m) {
            $rows[] = [$m->held_at?->format('Y-m-d H:i'), $m->participants, $m->decisions, $m->pending, $m->next_at?->toDateString()];
        }

        return $rows;
    }

    // ------------------------------------------------------------------
    // Manuscrito e submissão (P10–P14)
    // ------------------------------------------------------------------

    public const MANUSCRIPT_CHECKS = [
        'osf_cited' => 'Registro do OSF citado no resumo e nos métodos (DOI)',
        'embargo' => 'Embargo encerrado (registro público) ou link de visualização para revisores',
        'prisma' => 'Checklist PRISMA 2020 completo (+ PRISMA-S; PRISMA-DTA se aplicável)',
        'flow' => 'Fluxograma PRISMA com números idênticos aos do sistema',
        'searches' => 'Estratégias de busca completas, com data, no material suplementar',
        'kappa' => 'Kappa de cada etapa relatado',
        'rob' => 'Tabela e gráficos de risco de viés (robvis)',
        'sof' => 'Tabela de resumo dos achados com certeza GRADE',
        'deviations' => 'Desvios do protocolo declarados',
        'deposit' => 'Base travada, scripts R e decisões depositados no OSF (CC BY 4.0) e citados',
        'credit' => 'Contribuições CRediT, conflitos de interesse e financiamento conferidos',
        'approval' => 'Todos os coautores aprovaram a versão final por escrito',
    ];

    public function updateManuscript(SystematicReview $review, array $data): void
    {
        $m = array_merge($review->manuscript ?? [], array_intersect_key($data, array_flip(['checks', 'journal', 'submitted_at', 'decision', 'article_doi', 'published_at', 'notes'])));
        $review->update(['manuscript' => $m]);
        $sr = app(SystematicReviewService::class);
        if (count(array_filter((array) ($m['checks'] ?? []))) >= count(self::MANUSCRIPT_CHECKS)) {
            $sr->tick($review, 'manuscript');
        }
        if (filled($m['submitted_at'] ?? null)) {
            $sr->tick($review, 'submitted');
        }
        $review->logEvent('Manuscrito/submissão atualizado', collect(['journal' => 'revista', 'submitted_at' => 'submetido', 'decision' => 'decisão', 'article_doi' => 'DOI'])
            ->filter(fn ($l, $k) => filled($m[$k] ?? null))->map(fn ($l, $k) => "{$l}: {$m[$k]}")->implode(' · '));
    }

    // ------------------------------------------------------------------
    // Fase atual (P0–P14)
    // ------------------------------------------------------------------

    /** @return array{code: string, label: string} */
    public function phase(SystematicReview $review): array
    {
        $m = $review->manuscript ?? [];
        $lock = $review->latestLock();
        $confirmed = $review->analysisRuns()->where('rerun_matches', true)->exists();
        $gradeDone = $review->gradeRows()->count() > 0 && $review->gradeRows()->whereNull('certainty')->doesntExist();

        return match (true) {
            filled($m['article_doi'] ?? null) => ['code' => 'P14', 'label' => 'Publicado'],
            filled($m['submitted_at'] ?? null) => ['code' => 'P12–P13', 'label' => 'Submetido / revisão por pares'],
            $gradeDone && $confirmed => ['code' => 'P10–P11', 'label' => 'Redação e revisão interna'],
            $confirmed => ['code' => 'P9', 'label' => 'GRADE e tabela de resumo dos achados'],
            (bool) $lock => ['code' => 'P8', 'label' => 'Análise estatística e reexecução'],
            $review->extractionOpen() && $review->inExtractionPilot() => ['code' => 'P7a', 'label' => 'Piloto de extração'],
            $review->extractionOpen() => ['code' => 'P7b–c', 'label' => 'Extração, risco de viés e reconciliação'],
            $review->status === SystematicReview::STATUS_SCREENING_DONE => ['code' => 'P7', 'label' => 'Pronto para abrir a extração'],
            $review->status !== SystematicReview::STATUS_PROTOCOL && $review->stage === SystematicReview::STAGE_FT => ['code' => 'P6', 'label' => 'Texto completo'],
            $review->status !== SystematicReview::STATUS_PROTOCOL && $review->inPilot() => ['code' => 'P5a', 'label' => 'Calibração (piloto)'],
            $review->status !== SystematicReview::STATUS_PROTOCOL => ['code' => 'P5b', 'label' => 'Triagem título/resumo'],
            $review->records()->exists() => ['code' => 'P4', 'label' => 'Buscas importadas — montar a dupla'],
            filled($review->registration_id) => ['code' => 'P3', 'label' => 'Buscas'],
            $review->isFrozen() => ['code' => 'P2', 'label' => 'Registro no OSF'],
            (bool) $review->pipeline => ['code' => 'P1', 'label' => 'Congelar o protocolo'],
            default => ['code' => 'P0', 'label' => 'Preparação'],
        };
    }
}
