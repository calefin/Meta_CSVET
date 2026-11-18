<?php

namespace App\Services;

use App\Mail\AppNotificationMail;
use App\Models\InviteToken;
use App\Models\SystematicReview;
use App\Models\SystematicReviewDecision;
use App\Models\SystematicReviewRecord;
use App\Models\SystematicReviewScreener;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Esteira de revisão sistemática/metanálise: importação dos registros,
 * triagem cega em dupla pelo portal, confiabilidade (kappa), conflitos
 * desempatados pelo professor, fluxograma PRISMA e texto de Métodos.
 */
class SystematicReviewService
{
    /** Motivo especial da etapa de texto completo (vai para "not retrieved" no PRISMA). */
    public const NOT_RETRIEVED = 'Texto completo não obtido';

    public const TA_OFF_TOPIC = 'Fora do tema da revisão (título/resumo claramente irrelevante)';

    public const MAYBE_RULE_NOTE = 'Regra do protocolo: "talvez" de um revisor leva ao texto completo.';

    /** Triagem priorizada (v1.1): relevante para qualquer um dos revisores vai ao texto completo. */
    public const EITHER_RULE_NOTE = 'Regra do protocolo: relevante (ou talvez) para um dos revisores leva ao texto completo.';

    /** Não lido por nenhum revisor depois da regra de parada e da checagem aleatória. */
    public const STOP_RULE_REASON = 'Não triado: regra de parada do aprendizado ativo (após checagem aleatória)';

    public const STOP_RULE_NOTE = 'Regra de parada do protocolo (aprendizado ativo + checagem aleatória).';

    protected function al(): ActiveLearningService
    {
        return app(ActiveLearningService::class);
    }

    /** Triagem de título/resumo priorizada e já fora do piloto de calibração. */
    public function prioritisedNow(SystematicReview $review, ?string $stage = null): bool
    {
        $stage ??= $review->stage;

        return $stage === SystematicReview::STAGE_TA && $review->prioritisedScreening() && !$review->inPilot($stage);
    }

    // ------------------------------------------------------------------
    // Importação
    // ------------------------------------------------------------------

    /**
     * @return array{parsed: int, new: int, duplicates: int, format: string}
     */
    public function import(SystematicReview $review, string $content, string $filename, ?string $sourceLabel = null): array
    {
        $parsed = ReferenceImporter::parse($content, $filename, filled($sourceLabel) ? trim($sourceLabel) : null);
        $records = $parsed['records'];
        $meta = $parsed['meta'];

        if (!$records) {
            throw new \RuntimeException('Nenhum registro com título foi encontrado no arquivo.');
        }

        $new = 0;
        DB::transaction(function () use ($review, $records, &$new) {
            $existing = $review->records()->pluck('id', 'dedupe_key')->all();
            // Título + ano e PMID dos que já estão no projeto (registro com DOI numa base e sem DOI em outra).
            foreach ($review->records()->select(['id', 'title', 'year', 'pmid'])->cursor() as $old) {
                $existing[SystematicReviewRecord::dedupeKey(null, null, $old->title, $old->year)] ??= $old->id;
                if (filled($old->pmid)) {
                    $existing[SystematicReviewRecord::dedupeKey(null, $old->pmid, null)] ??= $old->id;
                }
            }
            $seq = (int) $review->records()->max('seq');

            foreach ($records as $r) {
                $key = SystematicReviewRecord::dedupeKey($r['doi'], $r['pmid'], $r['title'], $r['year']);
                $pmidKey = filled($r['pmid']) ? SystematicReviewRecord::dedupeKey(null, $r['pmid'], null) : null;
                // Mesmo título sem DOI em outra base também é duplicata.
                $titleKey = SystematicReviewRecord::dedupeKey(null, null, $r['title'], $r['year']);
                // Online num ano e no fascículo no seguinte: as bases divergem no ano. Só para títulos longos (não junta "Editorial", "Erratum"...).
                $nearKeys = [];
                if ($r['year'] && strlen(SystematicReviewRecord::normalizeTitle($r['title'])) >= 40) {
                    foreach ([(int) $r['year'] - 1, (int) $r['year'] + 1] as $y) {
                        $nearKeys[] = SystematicReviewRecord::dedupeKey(null, null, $r['title'], (string) $y);
                    }
                }

                $dupId = $existing[$key] ?? ($pmidKey ? $existing[$pmidKey] ?? null : null) ?? $existing[$titleKey]
                    ?? collect($nearKeys)->map(fn ($k) => $existing[$k] ?? null)->first(fn ($id) => $id !== null);
                if ($dupId) {
                    $this->mergeSources($dupId, $r);
                    continue;
                }

                $record = $review->records()->create([
                    'seq' => ++$seq,
                    'random_key' => random_int(1, 2_000_000_000),
                    'dedupe_key' => $key,
                    'title' => Str::limit($r['title'], 2000, ''),
                    'abstract' => $r['abstract'],
                    'authors' => $r['authors'] ? Str::limit($r['authors'], 4000, '…') : null,
                    'year' => $r['year'],
                    'journal' => $r['journal'] ? Str::limit($r['journal'], 490, '') : null,
                    'doi' => $r['doi'] ? Str::limit($r['doi'], 250, '') : null,
                    'pmid' => $r['pmid'],
                    'url' => $r['url'] ? Str::limit($r['url'], 990, '') : null,
                    'sources' => Str::limit(implode(', ', $r['sources']), 250, ''),
                    'keywords' => $r['keywords'],
                    'extra' => $r['extra'],
                ]);
                $existing[$key] = $record->id;
                if ($titleKey !== $key) {
                    $existing[$titleKey] = $record->id;
                }
                if ($pmidKey) {
                    $existing[$pmidKey] ??= $record->id;
                }
                $new++;
            }
        });

        $duplicates = count($records) - $new;
        $counts = $review->search_counts ?? [];
        foreach ($meta['sources'] as $name => $n) {
            $counts['sources'][$name] = ($counts['sources'][$name] ?? 0) + $n;
        }
        $counts['duplicates_removed'] = ($counts['duplicates_removed'] ?? 0) + $meta['duplicates_removed'] + $duplicates;
        // Registro PRISMA-S de cada busca (base, plataforma, sintaxe, data, resultados).
        foreach ($meta['searches'] as $search) {
            $counts['searches'][] = array_map(fn ($v) => is_scalar($v) ? Str::limit((string) $v, 4000, '') : null, array_intersect_key($search, array_flip(['database', 'platform', 'query', 'date', 'results', 'filters', 'notes'])));
        }
        $counts['imports'][] = [
            'at' => now()->format('Y-m-d H:i'),
            'file' => Str::limit($filename, 120),
            'format' => $meta['format'],
            'parsed' => count($records),
            'new' => $new,
        ];
        if (filled($meta['query']) && blank($review->protocol['search_string'] ?? null)) {
            $protocol = $review->protocol ?? [];
            $protocol['search_string'] = $meta['query'];
            $review->protocol = $protocol;
        }

        $review->search_counts = $counts;
        if ($meta['date'] && !$review->search_date) {
            try {
                $review->search_date = \Illuminate\Support\Carbon::parse($meta['date']);
            } catch (\Throwable) {
            }
        }
        $review->save();

        $review->logEvent("Importação: {$filename}", "{$meta['format']} · " . count($records) . " lidos, {$new} novos, {$duplicates} duplicatas");

        return ['parsed' => count($records), 'new' => $new, 'duplicates' => $duplicates + $meta['duplicates_removed'], 'format' => $meta['format']];
    }

    /**
     * Completa SÓ o resumo vazio de registros que já estão no projeto, a partir de um .json
     * {"records": [{title, year, doi, pmid, abstract, abstract_source}]}. Não cria registro, não mexe
     * nas contagens do PRISMA (search_counts) nem nas bases de origem, e só vale antes de liberar a
     * triagem (os dois triadores precisam ver o mesmo texto). A fonte de cada resumo fica em extra.
     *
     * @return array{read: int, filled: int, not_found: int, already: int, by_source: array<string, int>}
     */
    public function completeAbstracts(SystematicReview $review, string $content): array
    {
        // Depois de liberada: só durante a triagem de título/resumo e só em registro que ninguém votou,
        // para os dois triadores da dupla julgarem exatamente o mesmo texto.
        $released = $review->status === SystematicReview::STATUS_SCREENING;
        if (!$released && $review->status !== SystematicReview::STATUS_PROTOCOL) {
            throw new \RuntimeException('Os resumos só podem ser completados até a triagem de título/resumo.');
        }
        $data = json_decode($content, true);
        $rows = is_array($data) ? ($data['records'] ?? null) : null;
        if (!is_array($rows) || !$rows) {
            throw new \RuntimeException('Arquivo sem a lista "records".');
        }

        $index = [];
        foreach ($review->records()->select(['id', 'title', 'year', 'doi', 'pmid'])->cursor() as $old) {
            if (filled($old->doi)) {
                $index[SystematicReviewRecord::dedupeKey($old->doi, null, null)] ??= $old->id;
            }
            if (filled($old->pmid)) {
                $index[SystematicReviewRecord::dedupeKey(null, $old->pmid, null)] ??= $old->id;
            }
            $index[SystematicReviewRecord::dedupeKey(null, null, $old->title, $old->year)] ??= $old->id;
        }

        $stats = ['read' => count($rows), 'filled' => 0, 'not_found' => 0, 'already' => 0, 'voted' => 0, 'by_source' => []];
        DB::transaction(function () use ($review, $rows, $index, &$stats) {
            foreach ($rows as $r) {
                $abstract = trim((string) ($r['abstract'] ?? ''));
                if ($abstract === '' || blank($r['title'] ?? null)) {
                    $stats['not_found']++;
                    continue;
                }
                $keys = array_filter([
                    filled($r['doi'] ?? null) ? SystematicReviewRecord::dedupeKey($r['doi'], null, null) : null,
                    filled($r['pmid'] ?? null) ? SystematicReviewRecord::dedupeKey(null, (string) $r['pmid'], null) : null,
                    SystematicReviewRecord::dedupeKey(null, null, $r['title'], isset($r['year']) ? (string) $r['year'] : null),
                ]);
                $id = collect($keys)->map(fn ($k) => $index[$k] ?? null)->first(fn ($v) => $v !== null);
                $record = $id ? SystematicReviewRecord::where('systematic_review_id', $review->id)->find($id) : null;
                if (!$record) {
                    $stats['not_found']++;
                    continue;
                }
                if (filled($record->abstract)) {
                    $stats['already']++;
                    continue;
                }
                if ($record->decisions()->exists()) {
                    $stats['voted']++;
                    continue;
                }
                $source = Str::limit(trim((string) ($r['abstract_source'] ?? '')) ?: 'não informada', 80, '');
                $record->update([
                    'abstract' => $abstract,
                    'extra' => array_merge($record->extra ?? [], ['abstract_source' => $source]),
                ]);
                $stats['filled']++;
                $stats['by_source'][$source] = ($stats['by_source'][$source] ?? 0) + 1;
            }
        });

        arsort($stats['by_source']);
        $review->logEvent('Resumos completados', "{$stats['filled']} resumos preenchidos em registros já importados ("
            . collect($stats['by_source'])->map(fn ($n, $s) => "{$n} {$s}")->implode(', ')
            . "); {$stats['not_found']} não localizados; {$stats['already']} já tinham resumo"
            . ($released ? "; preenchido após a liberação, só em registros sem voto ({$stats['voted']} já votados ficaram como estavam)" : '')
            . '. Contagens do PRISMA inalteradas.');

        return $stats;
    }

    protected function mergeSources(int $recordId, array $r): void
    {
        $record = SystematicReviewRecord::find($recordId);
        $sources = collect(explode(',', (string) $record->sources))->map(fn ($s) => trim($s))
            ->merge($r['sources'])->filter()->unique()->implode(', ');
        $updates = ['sources' => Str::limit($sources, 250, '')];
        foreach (['abstract', 'doi', 'pmid', 'journal', 'authors', 'year'] as $field) {
            if (blank($record->{$field}) && filled($r[$field])) {
                $updates[$field] = $r[$field];
            }
        }
        $record->update($updates);
    }

    // ------------------------------------------------------------------
    // Triadores
    // ------------------------------------------------------------------

    /**
     * Inclui um triador: orientando já cadastrado (pelo id do usuário) ou
     * externo (nome + e-mail). Externo sem conta recebe convite e é obrigado
     * a criar o login de aluno antes de ver qualquer coisa do projeto.
     */
    public function addScreener(SystematicReview $review, array $data): SystematicReviewScreener
    {
        $user = !empty($data['user_id']) ? User::find($data['user_id']) : null;
        $email = mb_strtolower(trim((string) ($user?->email ?? $data['email'] ?? '')));
        $name = trim((string) ($user?->name ?? $data['name'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Informe um e-mail válido (o aluno precisa de login para triar).');
        }
        if ($name === '') {
            throw new \RuntimeException('Informe o nome do aluno.');
        }

        $user ??= User::where('email', $email)->first();
        if ($user && !in_array($user->role, ['student', 'admin'], true)) {
            throw new \RuntimeException('Esse e-mail já pertence a uma conta que não é de aluno. Use outro e-mail.');
        }
        if ($review->screeners()->where('email', $email)->whereNull('removed_at')->exists()) {
            throw new \RuntimeException('Essa pessoa já é triadora deste projeto.');
        }

        $screener = $review->screeners()->where('email', $email)->first();
        $attributes = [
            'name' => $name,
            'email' => $email,
            'user_id' => $user?->id,
            'label' => $data['label'] ?? null,
            'stages' => $data['stages'] ?? null,
            'removed_at' => null,
            'invited_at' => now(),
        ];
        $screener ? $screener->update($attributes) : $screener = $review->screeners()->create($attributes);

        $review->logEvent("Triador incluído: {$name}", $user ? 'conta existente' : 'externo — convite para criar conta');

        if ($user) {
            // skip_invite (seleção de alunos): o e-mail da seleção já avisa, sem revelar o título antes do termo.
            if ($review->status === SystematicReview::STATUS_SCREENING && empty($data['skip_invite'])) {
                $this->notifyScreener($screener, 'Você foi incluído(a) numa triagem', $this->assignmentText($screener), 'project');
            }
        } elseif (empty($data['skip_invite'])) {
            // skip_invite: quem chama manda um convite único (ex.: aluno aprovado
            // na seleção que entra em dois projetos de uma vez).
            $this->inviteExternal($screener);
        }

        return $screener;
    }

    /**
     * Convite (InviteToken externo) amarrado ao e-mail. Depois de validar o
     * e-mail, a conta é liberada direto (o professor já escolheu o aluno).
     */
    public function inviteExternal(SystematicReviewScreener $screener): string
    {
        InviteToken::where('email', $screener->email)->whereNull('used_at')->delete();
        $invite = InviteToken::create([
            'email' => $screener->email,
            'category' => 'external',
            'auto_approve' => true,
            'expires_at' => now()->addDays(30),
        ]);
        $url = url('/invite?token=' . $invite->token);

        $review = $screener->systematicReview;
        $this->mail($screener->email, new AppNotificationMail(
            title: 'Convite para projeto de pesquisa',
            messageContent: "Olá, {$screener->name}!\n\n"
                . "O Prof. Atilio convidou você para participar da triagem do projeto de revisão sistemática \"{$review->title}\".\n\n"
                . "Para participar é OBRIGATÓRIO criar o seu login de aluno na plataforma profatilio.com.br pelo botão abaixo (use este mesmo e-mail). "
                . "Depois de validar o e-mail, entre no Portal do Aluno, leia e assine o termo de confidencialidade e comece a triagem em \"Triagem Cega\".\n\n"
                . 'O convite vale por 30 dias.',
            actionUrl: $url,
            actionText: 'Criar meu login',
            subject: 'Convite: triagem de revisão sistemática — Prof. Atilio',
        ));

        $screener->update(['invited_at' => now()]);

        return $url;
    }

    public function removeScreener(SystematicReviewScreener $screener, ?string $reason = null): void
    {
        $screener->update(['removed_at' => now()]);
        $screener->systematicReview->logEvent("Triador removido: {$screener->name}", $reason);
        $this->checkStageCompletion($screener->systematicReview->fresh());
    }

    public function signTerms(SystematicReviewScreener $screener, string $signature, ?string $ip, ?string $userAgent): void
    {
        $review = $screener->systematicReview;
        $text = SystematicReviewScreener::termsText($review, $screener->name, $screener->email);

        if (self::normalizeName($signature) !== self::normalizeName($screener->name)
            && self::normalizeName($signature) !== self::normalizeName((string) $screener->user?->getRawOriginal('name'))) {
            throw new \RuntimeException('Digite o seu nome completo exatamente como está no cadastro: ' . $screener->name);
        }

        $screener->update([
            'terms_accepted_at' => now(),
            'terms_version' => SystematicReviewScreener::TERMS_VERSION,
            'terms_text' => $text,
            'terms_sha256' => hash('sha256', $text),
            'terms_signature' => trim($signature),
            'terms_ip' => $ip,
            'terms_user_agent' => Str::limit((string) $userAgent, 490, ''),
        ]);

        $review->logEvent("Termo assinado por {$screener->name}", 'IP ' . ($ip ?: '—') . ' · SHA-256 ' . substr(hash('sha256', $text), 0, 16) . '…');
    }

    protected static function normalizeName(string $name): string
    {
        return Str::of($name)->ascii()->lower()->squish()->toString();
    }

    // ------------------------------------------------------------------
    // Triagem
    // ------------------------------------------------------------------

    /**
     * Validações duras antes de liberar a triagem: protocolo com critérios,
     * registro prospectivo (ou justificativa), registros importados e ao
     * menos um triador.
     *
     * @return array<int, string>
     */
    public function startProblems(SystematicReview $review): array
    {
        $problems = [];
        if (blank($review->protocol['inclusion_criteria'] ?? null) || blank($review->protocol['exclusion_criteria'] ?? null)) {
            $problems[] = 'Preencha os critérios de inclusão e de exclusão do protocolo (os alunos triam por eles).';
        }
        if ($review->protocol_code && !$review->isFrozen()) {
            $problems[] = 'Congele o protocolo (fase P1) antes de começar: critérios, códigos e campos ficam somente leitura e o SHA-256 é registrado.';
        }
        if ($review->protocol_code && blank($review->registration_id)) {
            $problems[] = 'Grave o DOI do registro no OSF (fase P2): o registro tem de existir ANTES da triagem.';
        } elseif (!$review->isRegistered()) {
            $problems[] = 'Informe o registro do protocolo (DOI/ID do OSF) — ele deve ser feito ANTES da triagem — ou justifique a ausência.';
        }
        if ($review->records()->count() === 0) {
            $problems[] = 'Importe os registros das bases (JSON do script, .ris, .nbib ou .csv).';
        }
        if ($review->activeScreeners()->count() === 0) {
            $problems[] = 'Inclua pelo menos um triador (o ideal são dois, em dupla cega).';
        }

        return $problems;
    }

    public function start(SystematicReview $review): void
    {
        if ($problems = $this->startProblems($review)) {
            throw new \RuntimeException(implode(' ', $problems));
        }

        // Projeto pequeno (lição do P07): com menos de 250 registros, o piloto é de 20% (mínimo 20),
        // para sobrar amostra para uma 2ª rodada. Só ajusta o tamanho padrão de 50.
        $total = $review->stageRecords(SystematicReview::STAGE_TA)->count();
        $pilot = (int) $review->pilot_size;
        if (!$review->is_training && $pilot === 50 && $total > 0 && $total < 250) {
            $pilot = min($total, max(20, (int) ceil(0.2 * $total)));
        }

        $review->update([
            'status' => SystematicReview::STATUS_SCREENING,
            'stage' => SystematicReview::STAGE_TA,
            'screening_started_at' => $review->screening_started_at ?? now(),
            'pilot_size' => $pilot,
        ]);
        $this->tick($review, 'registered', (bool) $review->registration_id);
        $this->tick($review, 'dedup_done');

        // Triagem real liberada: o treino da dupla sai do portal, para não haver dois projetos na tela.
        if (!$review->is_training) {
            SystematicReview::where('is_training', true)
                ->where('short_name', 'TREINO ' . \App\Filament\Pages\CentralMetanalises::codeOf($review))
                ->where('status', '!=', SystematicReview::STATUS_ARCHIVED)
                ->update(['status' => SystematicReview::STATUS_ARCHIVED]);
        }
        $review->logEvent('Triagem de título/resumo liberada aos alunos', $review->inPilot() ? "Piloto com {$review->pilot_size} registros" : null);

        foreach ($review->activeScreeners()->get() as $screener) {
            if ($screener->user_id && $screener->participatesIn(SystematicReview::STAGE_TA)) {
                $this->notifyScreener($screener, $review->is_training ? 'Treino da triagem liberado' : 'Triagem liberada', $this->assignmentText($screener), 'project');
            }
        }
    }

    public function assignmentText(SystematicReviewScreener $screener): string
    {
        $review = $screener->systematicReview;
        $n = $review->screenableRecords()->count();

        if ($review->is_training) {
            $until = $review->training_closes_at
                ? 'até ' . $review->training_closes_at->format('d/m') . ' às ' . $review->training_closes_at->format('H\hi')
                : 'até o prazo que aparece no portal';

            return "Olá, {$screener->name}!\n\n"
                . "Está liberado o TREINO da triagem: {$n} trabalhos sobre um tema fora dos nossos projetos (selante de teto e mastite em vacas). "
                . "Serve só para conhecer a plataforma: nada aqui conta para o seu projeto, e não há termo para assinar.\n\n"
                . 'Entre no Portal do Aluno → "Treino da triagem" (menu Programa de Metanálises) e decida cada trabalho sozinho(a), '
                . "pelos critérios que aparecem na tela. O treino fica aberto {$until}.\n\n"
                . 'Quando você e a sua dupla terminarem, ou quando o prazo acabar, o treino fecha e você vê ali mesmo as suas decisões, '
                . 'as da sua dupla e o gabarito comentado de cada trabalho. Depois o professor discute as divergências com vocês.';
        }

        // Cada aluno está em mais de um projeto: deixa claro que são triagens diferentes e que todas contam.
        $codeOf = fn (SystematicReview $r) => $r->selectionCode() ?? $r->title;
        $order = fn (string $c) => SystematicReview::selectionSortKey($c);
        $mine = SystematicReviewScreener::query()->whereNull('removed_at')
            ->where(fn ($q) => $screener->user_id ? $q->where('user_id', $screener->user_id) : $q->where('email', $screener->email))
            ->whereHas('systematicReview', fn ($q) => $q->where('is_training', false)->where('status', '!=', SystematicReview::STATUS_ARCHIVED))
            ->with('systematicReview')->get()
            ->map(fn ($s) => $codeOf($s->systematicReview))
            ->unique()->sortBy($order)->values();
        $code = $review->selectionCode();
        $several = $mine->count() > 1
            ? "Atenção: você participa de {$mine->count()} projetos diferentes (" . $mine->implode(', ') . ') e precisa fazer a triagem de TODOS. '
                . "Não é a mesma tarefa repetida: cada projeto tem tema, critérios e trabalhos próprios. Você recebe um e-mail como este para cada um.\n\n"
            : '';

        return "Olá, {$screener->name}!\n\n"
            . "A triagem ({$review->stageLabel()}) do projeto " . ($code ? "{$code}, " : '') . "\"{$review->title}\", está liberada para você: {$n} trabalhos"
            . ($review->inPilot() ? ' (fase piloto de calibração)' : '') . ".\n\n"
            . $several
            . "ESTA É A TRIAGEM OFICIAL, NÃO É TREINO. Cada decisão sua entra no artigo: no fluxograma PRISMA, na concordância (kappa) "
            . "publicada e na lista de estudos incluídos. Um trabalho excluído por engano sai da metanálise. Leia título e resumo com atenção "
            . "e siga os critérios do protocolo que aparecem na tela.\n\n"
            . ($review->inPilot()
                ? "Os primeiros {$review->pilot_size} trabalhos são a calibração da dupla (também contam): o professor confere a concordância antes de liberar o restante.\n\n"
                : '')
            . ($screener->hasSignedTerms() ? '' : "Antes de começar, leia e assine o termo de confidencialidade (aparece na primeira tela).\n\n")
            . 'Entre no Portal do Aluno → "Triagem Cega". Lá aparece o quadro "Seus projetos no programa", com o que falta em cada um. '
            . 'Se precisar, use o botão "Ver em português" abaixo do resumo (tradução automática de apoio; em caso de dúvida, vale o original). '
            . 'Decida sozinho(a), seguindo o protocolo.';
    }

    /**
     * Registra (ou muda) o voto do aluno. Tudo é validado no servidor: termo
     * assinado, etapa aberta, registro visível para ele, motivo de exclusão.
     */
    public function decide(SystematicReviewScreener $screener, SystematicReviewRecord $record, string $decision, ?string $reason = null, ?string $note = null, ?array $checklist = null): SystematicReviewDecision
    {
        $review = $screener->systematicReview;
        $stage = $review->stage;

        if ($screener->removed_at) {
            throw new \RuntimeException('Você não participa mais deste projeto.');
        }
        if (!$screener->hasSignedTerms()) {
            throw new \RuntimeException('Assine o termo antes de triar.');
        }
        if ($review->trainingExpired()) {
            app(ScreeningTrainingService::class)->close($review, 'prazo encerrado');

            throw new \RuntimeException('O prazo do treino terminou. Os resultados já estão na tela do treino.');
        }
        if ($review->status !== SystematicReview::STATUS_SCREENING || !$screener->participatesIn($stage)) {
            throw new \RuntimeException('A triagem deste projeto não está aberta para você agora.');
        }
        if ($screener->hasFinished($stage)) {
            throw new \RuntimeException('Você já concluiu esta etapa. Fale com o professor se precisar reabrir.');
        }
        if ($record->systematic_review_id !== $review->id || !$review->screenableRecords()->whereKey($record->id)->exists()) {
            throw new \RuntimeException('Este trabalho não está na sua lista.');
        }
        if ($this->prioritisedNow($review, $stage) && $this->al()->isQcScreener($screener)
            && !in_array($record->id, $this->al()->currentRound($review)['record_ids'] ?? [], true)) {
            throw new \RuntimeException('Na checagem aleatória, decida só os trabalhos sorteados.');
        }
        if (!array_key_exists($decision, SystematicReviewRecord::DECISIONS)) {
            throw new \RuntimeException('Decisão inválida.');
        }
        if (!in_array($decision, $review->allowedDecisions($stage), true)) {
            throw new \RuntimeException('O protocolo não permite "' . SystematicReviewRecord::DECISIONS[$decision] . '" nesta etapa.');
        }

        $allowed = $this->reasonsFor($review, $stage);
        if ($decision === 'exclude') {
            if (blank($reason) || !in_array($reason, $allowed, true)) {
                throw new \RuntimeException('Escolha o motivo da exclusão.');
            }
        } else {
            $reason = null;
        }

        // $note null = mantém a observação já gravada (ex.: o aluno só trocou a decisão); '' apaga.
        $values = ['decision' => $decision, 'reason' => $reason];
        if ($note !== null) {
            $values['note'] = filled($note) ? Str::limit(trim($note), 1000, '') : null;
        }
        // Checklist: null = mantém o gravado (o aluno só trocou a decisão). Só índices de perguntas que existem
        // e respostas válidas; serve para a reunião de calibração (onde a dupla divergiu).
        if ($checklist !== null) {
            $n = count($review->screening_checklist ?? []);
            $clean = [];
            foreach ($checklist as $i => $answer) {
                if (is_numeric($i) && (int) $i >= 0 && (int) $i < $n && in_array($answer, ['sim', 'nao', 'incerto'], true)) {
                    $clean[(string) (int) $i] = $answer;
                }
            }
            if ($clean) {
                ksort($clean, SORT_NUMERIC);
                $values['checklist'] = $clean;
            }
        }
        $vote = SystematicReviewDecision::updateOrCreate(
            [
                'systematic_review_record_id' => $record->id,
                'systematic_review_screener_id' => $screener->id,
                'stage' => $stage,
            ],
            $values,
        );

        if ($review->inPilot()) {
            $this->checkPilotCompletion($review);
        }

        return $vote;
    }

    public function reasonsFor(SystematicReview $review, string $stage): array
    {
        $reasons = $review->exclusionReasons();
        if ($stage === SystematicReview::STAGE_FT && !in_array(self::NOT_RETRIEVED, $reasons, true)) {
            array_unshift($reasons, self::NOT_RETRIEVED);
        }
        // Título/resumo de protocolo importado: os códigos E… são do texto completo;
        // "fora do tema" cobre o trabalho claramente irrelevante.
        if ($stage === SystematicReview::STAGE_TA && $review->protocol_code && !in_array(self::TA_OFF_TOPIC, $reasons, true)) {
            array_unshift($reasons, self::TA_OFF_TOPIC);
        }

        return $reasons;
    }

    /**
     * Ordem de apresentação própria de cada triador (embaralhamento fixo):
     * evita que cansaço e efeito de ordem caiam nos mesmos trabalhos para os
     * dois, o que inflaria a concordância.
     *
     * @return array<int, int> ids na ordem
     */
    public function orderFor(SystematicReviewScreener $screener, ?string $stage = null): array
    {
        $review = $screener->systematicReview;
        if ($this->prioritisedNow($review, $stage)) {
            $decided = array_keys($this->al()->labels($screener));
            // Checagem aleatória: o Revisor 2 vê só a amostra sorteada.
            if ($this->al()->isQcScreener($screener)) {
                $sample = $this->al()->currentRound($review)['record_ids'] ?? [];

                return array_values(array_merge(array_intersect($decided, $sample), array_diff($sample, $decided)));
            }

            // Já decididos (na ordem em que decidiu); depois o que ficou do piloto sem decisão dele
            // (liberação antes de ele terminar a rodada); depois os mais prováveis primeiro.
            $pilotLeft = array_values(array_diff($review->pilotSampleIds(), $decided));
            $ranking = array_values(array_diff($this->al()->ranking($screener), $pilotLeft));

            return array_values(array_merge($decided, $pilotLeft, $ranking));
        }

        $ids = $review->screenableRecords($stage)->pluck('id')->all();
        // HMAC (não CRC32): o CRC é linear, então as ordens de triadores
        // diferentes saíam correlacionadas — muitas vezes idênticas.
        $key = fn ($id) => hash_hmac('sha256', "{$screener->id}:{$id}", (string) config('app.key'));
        usort($ids, fn ($a, $b) => [$key($a), $a] <=> [$key($b), $b]);

        return $ids;
    }

    /**
     * Ritmo: mediana de segundos entre decisões seguidas (pausas > 10 min
     * não contam). Menos de 5 s por trabalho sugere triagem sem leitura.
     *
     * @return array{median: ?float, n: int, fast: bool}
     */
    public function pace(SystematicReviewScreener $screener, ?string $stage = null): array
    {
        $stage ??= $screener->systematicReview->stage;
        $times = $screener->decisions()->where('stage', $stage)->orderBy('created_at')->pluck('created_at')
            ->map(fn ($t) => \Illuminate\Support\Carbon::parse($t)->getTimestamp())->values();
        $gaps = [];
        for ($i = 1; $i < $times->count(); $i++) {
            $gap = $times[$i] - $times[$i - 1];
            if ($gap <= 600) {
                $gaps[] = $gap;
            }
        }
        if (count($gaps) < 10) {
            return ['median' => null, 'n' => count($gaps), 'fast' => false];
        }
        sort($gaps);
        $mid = intdiv(count($gaps), 2);
        $median = count($gaps) % 2 ? $gaps[$mid] : ($gaps[$mid - 1] + $gaps[$mid]) / 2;

        return ['median' => (float) $median, 'n' => count($gaps), 'fast' => $median < 5];
    }

    /** @return array{done: int, total: int} */
    public function progress(SystematicReviewScreener $screener, ?string $stage = null): array
    {
        $review = $screener->systematicReview;
        $stage ??= $review->stage;
        $ids = $review->screenableRecords($stage)->pluck('id');
        if ($this->prioritisedNow($review, $stage) && $this->al()->isQcScreener($screener)) {
            $ids = collect($this->al()->currentRound($review)['record_ids'] ?? []);
        }

        return [
            'done' => $screener->decisions()->where('stage', $stage)->whereIn('systematic_review_record_id', $ids)->count(),
            'total' => $ids->count(),
        ];
    }

    public function finish(SystematicReviewScreener $screener): void
    {
        $review = $screener->systematicReview;
        $stage = $review->stage;
        if ($review->inPilot()) {
            throw new \RuntimeException('Ainda é a fase piloto: aguarde o professor liberar a triagem completa.');
        }
        $prioritised = $this->prioritisedNow($review, $stage);
        $inQc = $prioritised && $this->al()->isQcScreener($screener);
        if ($prioritised && !$inQc) {
            $status = $this->al()->stoppingStatus($screener);
            if (!$status['met']) {
                $faltam = collect($status['criteria'])->reject(fn ($c) => $c['ok'])->pluck('text')->map(fn ($t) => "• {$t}")->implode("\n");
                throw new \RuntimeException("A regra de parada do protocolo ainda não foi cumprida. Continue triando. Falta:\n{$faltam}");
            }
        } else {
            $p = $this->progress($screener, $stage);
            if ($p['done'] < $p['total']) {
                throw new \RuntimeException('Faltam ' . ($p['total'] - $p['done']) . ' trabalhos sem decisão.');
            }
        }

        $finished = $screener->finished_at ?? [];
        $finished[$stage] = now()->format('Y-m-d H:i:s');
        $screener->update(['finished_at' => $finished]);

        if ($inQc) {
            $review->logEvent("{$screener->name} concluiu a checagem aleatória");
            $hits = $this->al()->finishQc($review->fresh());
            if ($hits > 0) {
                $this->resumeAfterQc($review->fresh(), $hits);

                return;
            }
        } else {
            $detail = null;
            if ($prioritised) {
                $s = $this->al()->stoppingStatus($screener);
                $detail = "Regra de parada cumprida: {$s['screened']} de {$s['total']} triados · {$s['relevant']} relevantes · {$s['streak']} irrelevantes seguidos";
            }
            $review->logEvent("{$screener->name} concluiu a triagem ({$review->stageLabel()})", $detail);
        }

        $this->checkStageCompletion($review->fresh());
    }

    public function reopenScreener(SystematicReviewScreener $screener): void
    {
        $review = $screener->systematicReview;
        $finished = $screener->finished_at ?? [];
        unset($finished[$review->stage]);
        $screener->update(['finished_at' => $finished]);
        if ($review->status === SystematicReview::STATUS_CONFLICTS) {
            $review->update(['status' => SystematicReview::STATUS_SCREENING]);
        }
        $review->logEvent("Triagem de {$screener->name} reaberta ({$review->stageLabel()})");
        $this->notifyScreener($screener, 'Sua triagem foi reaberta', "Olá, {$screener->name}!\n\nO Prof. Atilio reabriu a sua triagem de \"{$review->title}\" para ajustes. Entre em \"Triagem Cega\" no portal.", 'project');
    }

    protected function participating(SystematicReview $review, ?string $stage = null): Collection
    {
        $stage ??= $review->stage;

        return $review->activeScreeners()->get()->filter(fn ($s) => $s->participatesIn($stage))->values();
    }

    protected function checkPilotCompletion(SystematicReview $review): void
    {
        $screeners = $this->participating($review);
        if ($screeners->isEmpty() || $screeners->contains(fn ($s) => ($p = $this->progress($s)) && $p['done'] < $p['total'])) {
            return;
        }
        $round = $review->pilotRound();
        $event = 'Piloto concluído pelos triadores' . ($round > 1 ? " (rodada {$round})" : '');
        if (collect($review->history ?? [])->contains(fn ($e) => $e['event'] === $event)) {
            return;
        }

        $rel = $this->pilotReliability($review);
        $review->logEvent($event, $rel['summary']);

        // Treino: termina aqui (não há triagem completa a liberar) e os resultados abrem para a dupla.
        if ($review->is_training) {
            app(ScreeningTrainingService::class)->close($review, 'a dupla decidiu todos os trabalhos');

            return;
        }

        $this->notifyProfessor(
            'Piloto da triagem concluído',
            'Os triadores concluíram a fase piloto' . ($round > 1 ? " (rodada {$round} de calibração)" : '') . " de \"{$review->title}\".\n\n{$rel['summary']}\n\n"
                . 'Confira as discordâncias. Kappa de 0,60 ou mais: clique em "Liberar triagem completa". Abaixo de 0,60: dê a devolutiva na conversa do projeto e abra uma "Nova rodada de calibração", como manda o protocolo.',
            $review,
        );
    }

    /**
     * Quando todos os triadores da etapa concluíram: aplica o consenso,
     * calcula a confiabilidade e passa os conflitos ao professor.
     */
    public function checkStageCompletion(SystematicReview $review): void
    {
        if ($review->status !== SystematicReview::STATUS_SCREENING) {
            return;
        }
        $stage = $review->stage;
        $screeners = $this->participating($review);
        if ($screeners->isEmpty() || $screeners->contains(fn ($s) => !$s->hasFinished($stage))) {
            return;
        }

        // Triagem priorizada: antes de fechar, checagem aleatória do que ninguém leu.
        if ($this->prioritisedNow($review, $stage) && ($review->ta_qc['status'] ?? null) !== 'passed') {
            if ($this->al()->startQc($review, $screeners->pluck('id')->all())) {
                $review->refresh();
                $round = $this->al()->currentRound($review);
                $qcScreener = $review->secondScreener();
                if ($qcScreener) {
                    $this->notifyScreener($qcScreener, 'Checagem aleatória: sua vez', "Olá, {$qcScreener->name}!\n\n"
                        . "Os dois revisores de \"{$review->title}\" cumpriram a regra de parada. Agora você confere uma amostra aleatória de "
                        . count($round['record_ids'] ?? []) . ' trabalhos que nenhum dos dois leu (é o controle de qualidade registrado no protocolo). '
                        . 'Entre em "Triagem Cega" no portal e decida cada um normalmente.', 'project');
                }
                $this->notifyProfessor('Checagem aleatória aberta', "Os dois revisores de \"{$review->title}\" cumpriram a regra de parada. "
                    . ($qcScreener?->name ?? 'O Revisor 2') . ' confere agora ' . count($round['record_ids'] ?? []) . ' registros sorteados entre os ' . ($round['unscreened'] ?? 0) . ' não lidos.', $review);

                return;
            }
            $qc = $review->ta_qc ?? [];
            $qc['status'] = 'passed';
            $review->forceFill(['ta_qc' => $qc])->save();
        }

        $this->applyConsensus($review, $stage, $screeners->pluck('id')->all());
        $conflicts = $this->conflictsQuery($review, $stage)->count();
        $rel = $this->reliability($review, $stage);

        $review->update(['status' => SystematicReview::STATUS_CONFLICTS]);
        $review->logEvent("Triagem de {$review->stageLabel()} concluída pelos alunos", "{$conflicts} conflito(s) · {$rel['summary']}");

        $this->notifyProfessor(
            'Triagem concluída — sua vez',
            "Todos os triadores concluíram a etapa \"{$review->stageLabel()}\" de \"{$review->title}\".\n\n"
                . "{$rel['summary']}\nConflitos para você desempatar: {$conflicts}.\n\n"
                . 'Resolva os conflitos e clique em "Encerrar etapa".',
            $review,
        );
    }

    /**
     * Grava a decisão final dos registros com consenso (todos incluíram ou
     * todos excluíram). Divergência ou "talvez" fica para o professor.
     */
    public function applyConsensus(SystematicReview $review, string $stage, array $screenerIds): void
    {
        if ($this->prioritisedNow($review, $stage)) {
            $this->applyPrioritisedConsensus($review, $screenerIds);

            return;
        }
        $column = SystematicReview::decisionColumn($stage);
        $prefix = $stage === SystematicReview::STAGE_FT ? 'ft' : 'ta';
        $votes = SystematicReviewDecision::whereIn('systematic_review_screener_id', $screenerIds)
            ->where('stage', $stage)
            ->whereIn('systematic_review_record_id', $review->stageRecords($stage)->select('id'))
            ->get()
            ->groupBy('systematic_review_record_id');

        foreach ($review->stageRecords($stage)->whereNull($column)->get() as $record) {
            $v = $votes->get($record->id, collect());
            if ($v->count() < count($screenerIds)) {
                continue;
            }
            $distinct = $v->pluck('decision')->unique();
            // Regra do protocolo registrado: "talvez" de qualquer revisor na
            // etapa de título/resumo leva o registro ao texto completo.
            if ($stage === SystematicReview::STAGE_TA && $review->maybeAdvancesToFullText() && $distinct->contains('maybe')) {
                $record->update([
                    $column => 'include',
                    "{$prefix}_reason" => null,
                    "{$prefix}_resolved_by_professor" => false,
                    'resolution_note' => self::MAYBE_RULE_NOTE,
                ]);

                continue;
            }
            if ($distinct->count() === 1 && in_array($distinct->first(), ['include', 'exclude'], true)) {
                $record->update([
                    $column => $distinct->first(),
                    "{$prefix}_consensus" => $distinct->first(),
                    "{$prefix}_reason" => $distinct->first() === 'exclude' ? $v->pluck('reason')->filter()->first() : null,
                    "{$prefix}_resolved_by_professor" => false,
                ]);
            }
        }
    }

    /**
     * Título/resumo priorizado (protocolo v1.1): relevante ou talvez para
     * qualquer revisor → texto completo; todos que leram excluíram → excluído;
     * ninguém leu, com a checagem aleatória aprovada → excluído pela regra de parada.
     */
    protected function applyPrioritisedConsensus(SystematicReview $review, array $screenerIds): void
    {
        $votes = SystematicReviewDecision::whereIn('systematic_review_screener_id', $screenerIds)
            ->where('stage', SystematicReview::STAGE_TA)
            ->get()
            ->groupBy('systematic_review_record_id');
        $passed = ($review->ta_qc['status'] ?? null) === 'passed';

        foreach ($review->stageRecords(SystematicReview::STAGE_TA)->whereNull('ta_decision')->get() as $record) {
            $v = $votes->get($record->id, collect());
            if ($v->isEmpty()) {
                if ($passed) {
                    $record->update(['ta_decision' => 'exclude', 'ta_reason' => self::STOP_RULE_REASON,
                        'ta_resolved_by_professor' => false, 'resolution_note' => self::STOP_RULE_NOTE]);
                }

                continue;
            }
            $distinct = $v->pluck('decision')->unique();
            if ($distinct->count() === 1 && in_array($distinct->first(), ['include', 'exclude'], true) && $v->count() === count($screenerIds)) {
                $record->update([
                    'ta_decision' => $distinct->first(),
                    'ta_consensus' => $distinct->first(),
                    'ta_reason' => $distinct->first() === 'exclude' ? $v->pluck('reason')->filter()->first() : null,
                    'ta_resolved_by_professor' => false,
                ]);

                continue;
            }
            if ($distinct->contains('include') || $distinct->contains('maybe')) {
                $record->update(['ta_decision' => 'include', 'ta_reason' => null, 'ta_resolved_by_professor' => false, 'resolution_note' => self::EITHER_RULE_NOTE]);

                continue;
            }
            // Só um leu e excluiu.
            $record->update(['ta_decision' => 'exclude', 'ta_reason' => $v->pluck('reason')->filter()->first(), 'ta_resolved_by_professor' => false]);
        }
    }

    /** A checagem achou elegíveis: os dois voltam a triar até a regra valer de novo. */
    protected function resumeAfterQc(SystematicReview $review, int $hits): void
    {
        foreach ($this->participating($review) as $screener) {
            $finished = $screener->finished_at ?? [];
            unset($finished[SystematicReview::STAGE_TA]);
            $screener->update(['finished_at' => $finished]);
            $this->notifyScreener($screener, 'Triagem reaberta pela checagem aleatória', "Olá, {$screener->name}!\n\n"
                . "A checagem aleatória de \"{$review->title}\" encontrou {$hits} trabalho(s) possivelmente elegível(is) entre os não lidos. "
                . 'Como manda o protocolo, a triagem foi reaberta: continue em "Triagem Cega" até cumprir de novo a regra de parada (100 irrelevantes seguidos).', 'project');
        }
        $this->notifyProfessor('Checagem aleatória achou elegíveis', "A checagem aleatória de \"{$review->title}\" achou {$hits} possivelmente elegível(is). A triagem dos dois revisores foi reaberta automaticamente.", $review);
    }

    public function conflictsQuery(SystematicReview $review, ?string $stage = null)
    {
        $stage ??= $review->stage;

        return $review->stageRecords($stage)->whereNull(SystematicReview::decisionColumn($stage));
    }

    public function resolve(SystematicReviewRecord $record, string $stage, string $decision, ?string $reason = null, ?string $note = null): void
    {
        if (!in_array($decision, ['include', 'exclude'], true)) {
            throw new \RuntimeException('A decisão final deve ser incluir ou excluir.');
        }
        $review = $record->systematicReview;
        if (!$this->professorCanResolve($review) || $stage !== $review->stage) {
            throw new \RuntimeException('Você só desempata depois que todos os triadores concluírem a etapa — decidir antes quebra a triagem em dupla cega.');
        }
        if ($decision === 'exclude' && blank($reason)) {
            throw new \RuntimeException('Informe o motivo da exclusão (vai para o fluxograma PRISMA).');
        }
        $prefix = $stage === SystematicReview::STAGE_FT ? 'ft' : 'ta';
        $consensus = $record->{"{$prefix}_consensus"};
        if ($consensus && $consensus !== $decision) {
            if (blank($note)) {
                throw new \RuntimeException('Os triadores concordaram em "' . SystematicReviewRecord::DECISIONS[$consensus] . '". Para mudar o consenso, escreva a justificativa (fica registrada no relatório).');
            }
            $review->logEvent('Consenso dos triadores sobreposto: #' . $record->seq, SystematicReviewRecord::DECISIONS[$consensus] . ' → ' . SystematicReviewRecord::DECISIONS[$decision] . ' · ' . trim($note));
        }
        $record->update([
            "{$prefix}_decision" => $decision,
            "{$prefix}_reason" => $decision === 'exclude' ? $reason : null,
            "{$prefix}_resolved_by_professor" => true,
            'resolution_note' => filled($note) ? trim($note) : $record->resolution_note,
        ]);
        $review->logEvent('Decisão do professor: #' . $record->seq . ' → ' . SystematicReviewRecord::DECISIONS[$decision], $reason);
    }

    /**
     * O professor desempata só quando todos concluíram (status de conflitos) —
     * ou quando a etapa não tem nenhum triador.
     */
    public function professorCanResolve(SystematicReview $review): bool
    {
        return $review->status === SystematicReview::STATUS_CONFLICTS
            || ($review->status === SystematicReview::STATUS_SCREENING && $this->participating($review)->isEmpty());
    }

    public function pilotKappa(SystematicReview $review): ?float
    {
        return $this->pilotReliability($review)['pairs'][0]['kappa']['kappa'] ?? null;
    }

    public function pilotKappaBinary(SystematicReview $review): ?float
    {
        return $this->pilotReliability($review)['pairs'][0]['kappa_binary']['kappa'] ?? null;
    }

    /**
     * Kappa efetivo para o portão da triagem de títulos e resumos (T&A).
     * No protocolo OSF (item 11b), 'talvez' e 'incluir' avançam ambos para a leitura do texto completo.
     * Portanto, a decisão de elegibilidade preliminar na triagem T&A é operacionalmente binária (avançar vs excluir).
     * Retorna o maior entre o kappa de 3 categorias e o kappa binário.
     */
    public function pilotEffectiveKappa(SystematicReview $review): ?float
    {
        $pair = $this->pilotReliability($review)['pairs'][0] ?? null;
        if (!$pair) {
            return null;
        }
        $k3 = $pair['kappa']['kappa'] ?? null;
        $kb = $pair['kappa_binary']['kappa'] ?? null;

        if ($k3 === null && $kb === null) {
            return null;
        }

        return max($k3 ?? -1.0, $kb ?? -1.0);
    }

    /** Rodadas de calibração previstas no protocolo (leva 2+): depois da 3ª, só adjudicação guiada. */
    public const MAX_PILOT_ROUNDS = 3;

    /**
     * Regra do paradoxo da prevalência (Feinstein & Cicchetti 1990; Byrt et al. 1993), protocolos da leva 2+:
     * com menos de 10% (ou menos de 5) registros mandados adiante por algum revisor, a calibração passa
     * se a concordância binária for ≥ 88% e o PABAK ≥ 0,60.
     *
     * @return array{applies: bool, passes: bool, agreement: ?float, pabak: ?float, advanced: int, n: int}
     */
    public function pilotPrevalenceRule(SystematicReview $review): array
    {
        $pair = $this->pilotReliability($review)['pairs'][0] ?? null;
        $n = (int) ($pair['n'] ?? 0);
        $advanced = (int) ($pair['advanced'] ?? 0);
        $agreement = $pair['kappa_binary']['agreement'] ?? null;
        $pabak = $pair['pabak'] ?? null;
        $applies = $n > 0 && ($advanced < 5 || $advanced / $n < 0.10);

        return [
            'applies' => $applies,
            'passes' => $applies && $agreement !== null && $agreement >= 0.88 && $pabak !== null && $pabak >= 0.60,
            'agreement' => $agreement,
            'pabak' => $pabak,
            'advanced' => $advanced,
            'n' => $n,
        ];
    }

    /** Portão do piloto sem justificativa: κ efetivo ≥ 0,60 ou a regra do paradoxo da prevalência. */
    public function pilotPassesGate(SystematicReview $review): bool
    {
        $k = $this->pilotEffectiveKappa($review);

        return ($k !== null && $k >= 0.60) || $this->pilotPrevalenceRule($review)['passes'];
    }

    /**
     * Confiabilidade da rodada de calibração ATUAL. Na 1ª rodada, usa todos os votos do piloto.
     * Da 2ª em diante, usa só a amostra nova (a anterior já foi discutida com a dupla).
     */
    public function pilotReliability(SystematicReview $review): array
    {
        if ($review->pilotRound() === 1) {
            return $this->reliability($review, SystematicReview::STAGE_TA);
        }
        $rel = $this->reliability($review, SystematicReview::STAGE_TA, $review->currentPilotRecordIds());
        $rel['summary'] = "Rodada {$review->pilotRound()} de calibração (amostra nova):\n" . $rel['summary']
            . collect($review->pilot_rounds)->map(fn ($r) => "\nRodada {$r['round']} (já discutida): κ = " . self::fmt($r['kappa'] ?? null)
                . ' · κ binário = ' . self::fmt($r['kappa_binary'] ?? null)
                . ', concordância ' . self::fmt(isset($r['agreement']) ? $r['agreement'] * 100 : null, 1) . "%, n = {$r['n']}")->implode('');

        return $rel;
    }

    /**
     * Protocolo registrado: com kappa abaixo de 0,60, a dupla discute os critérios e tria uma NOVA amostra-piloto.
     * Esta função fecha a rodada atual (guardando o kappa dela) e acrescenta ao piloto os próximos $size
     * registros da ordem aleatória fixa. As decisões das rodadas anteriores continuam valendo.
     */
    public function newPilotRound(SystematicReview $review, int $size): int
    {
        if ($review->is_training || $review->status !== SystematicReview::STATUS_SCREENING || !$review->inPilot()) {
            throw new \RuntimeException('Só dá para abrir nova rodada durante o piloto da triagem de título e resumo.');
        }
        if ($size < 10 || $size > 200) {
            throw new \RuntimeException('A nova amostra deve ter de 10 a 200 registros.');
        }
        if ($review->pilotRound() >= self::MAX_PILOT_ROUNDS) {
            throw new \RuntimeException('O protocolo prevê no máximo ' . self::MAX_PILOT_ROUNDS . ' rodadas de calibração. Agora é a adjudicação guiada: '
                . 'arbitre as divergências por escrito na conversa e use "Liberar triagem completa" com a justificativa (registre o desvio).');
        }
        $screeners = $this->participating($review);
        if ($screeners->contains(fn ($s) => ($p = $this->progress($s)) && $p['done'] < $p['total'])) {
            throw new \RuntimeException('Os dois triadores precisam terminar a rodada atual do piloto antes de abrir outra.');
        }
        $total = $review->stageRecords(SystematicReview::STAGE_TA)->count();
        $start = (int) $review->pilot_size;
        if ($start >= $total) {
            throw new \RuntimeException('Não há registros fora do piloto para sortear uma nova amostra.');
        }

        $rel = $this->pilotReliability($review);
        $pair = $rel['pairs'][0] ?? null;
        $round = $review->pilotRound();
        $rounds = $review->pilot_rounds ?? [];
        $rounds[] = [
            'round' => $round,
            'size' => $start - $review->pilotRoundStart(),
            'n' => $pair['n'] ?? 0,
            'kappa' => $pair['kappa']['kappa'] ?? null,
            'agreement' => $pair['kappa']['agreement'] ?? null,
            'kappa_binary' => $pair['kappa_binary']['kappa'] ?? null,
            'closed_at' => now()->format('Y-m-d H:i:s'),
        ];
        $added = min($size, $total - $start);
        $review->update(['pilot_rounds' => $rounds, 'pilot_size' => $start + $added]);
        $review->logEvent('Nova rodada de calibração (rodada ' . ($round + 1) . ')',
            "Rodada {$round} fechada. {$rel['summary']}\nNova amostra aleatória: {$added} registros.");

        foreach ($screeners as $screener) {
            $this->notifyScreener($screener, 'Nova rodada de calibração do piloto', "Olá, {$screener->name}!\n\n"
                . "Como manda o protocolo registrado, o kappa da rodada {$round} de \"{$review->title}\" ficou abaixo de 0,60. Por isso, a dupla tria uma NOVA amostra-piloto: "
                . "{$added} trabalhos sorteados, diferentes dos anteriores.\n\n"
                . 'Antes de começar, leia a devolutiva do professor na conversa do projeto e aplique os critérios. '
                . 'As decisões que você já deu continuam valendo, então não volte a elas. Continue em "Triagem Cega" no portal.', 'project');
        }

        return $added;
    }

    public function releasePilot(SystematicReview $review, ?string $justification = null): void
    {
        $k3 = $this->pilotKappa($review);
        $kb = $this->pilotKappaBinary($review);
        $prevalence = $this->pilotPrevalenceRule($review);

        if (!$this->pilotPassesGate($review) && blank($justification)) {
            throw new \RuntimeException('Ambos os kappas do piloto (3 categorias: ' . self::fmt($k3) . ' e binário: ' . self::fmt($kb) . ') estão abaixo de 0,60'
                . ($prevalence['applies'] ? ' e a regra da prevalência baixa não foi atendida (conc. binária ' . self::fmt(($prevalence['agreement'] ?? 0) * 100, 1) . '%, PABAK ' . self::fmt($prevalence['pabak']) . ')' : '')
                . ': registre o que foi feito (ex.: reunião de alinhamento, critérios esclarecidos) para liberar.');
        }

        $rel = $this->pilotReliability($review);
        $pair = $rel['pairs'][0] ?? null;
        $round = $review->pilotRound();
        $rounds = $review->pilot_rounds ?? [];
        $start = $review->pilotRoundStart();
        $size = max(0, (int) $review->pilot_size - $start);

        if ($size > 0 || $round > 1) {
            $rounds[] = [
                'round' => $round,
                'size' => $size,
                'n' => $pair['n'] ?? 0,
                'kappa' => $pair['kappa']['kappa'] ?? null,
                'agreement' => $pair['kappa']['agreement'] ?? null,
                'kappa_binary' => $pair['kappa_binary']['kappa'] ?? null,
                'closed_at' => now()->format('Y-m-d H:i:s'),
                'released' => true,
            ];
        }

        $review->update([
            'pilot_rounds' => $rounds,
            'pilot_released_at' => now(),
        ]);

        $decisionBasis = ($k3 !== null && $k3 >= 0.60)
            ? 'Critério atendido: κ (3 categorias) = ' . self::fmt($k3)
            : (($kb !== null && $kb >= 0.60)
                ? 'Critério atendido via κ binário (incluir+talvez × excluir) = ' . self::fmt($kb) . ' [conforme item 11b do protocolo OSF]'
                : ($prevalence['passes']
                    ? 'Critério atendido pela regra da prevalência baixa: ' . $prevalence['advanced'] . '/' . $prevalence['n'] . ' avançados, concordância binária '
                        . self::fmt($prevalence['agreement'] * 100, 1) . '% e PABAK ' . self::fmt($prevalence['pabak']) . ' (Feinstein e Cicchetti 1990; Byrt et al. 1993)'
                    : 'Liberado via adjudicação com justificativa do orientador'));

        $review->logEvent('Triagem completa liberada após o piloto', $rel['summary'] . "\nBase da decisão: {$decisionBasis}" . (filled($justification) ? "\nJustificativa: " . trim($justification) : ''));

        foreach ($this->participating($review) as $screener) {
            $this->notifyScreener($screener, 'Triagem completa liberada', "Olá, {$screener->name}!\n\n"
                . "O piloto de \"{$review->title}\" foi conferido e a triagem completa está liberada: "
                . $review->screenableRecords()->count() . ' trabalhos. '
                . ($review->prioritisedScreening()
                    ? 'O sistema aprende com as SUAS decisões e mostra primeiro os trabalhos mais prováveis; quem decide é sempre você. Você não precisa ler todos: pode concluir quando o painel mostrar a regra de parada cumprida. '
                    : '')
                . 'Continue em "Triagem Cega" no portal.', 'project');
        }
    }

    /**
     * Garante que todas as rodadas do piloto (incluindo a rodada de liberação) estejam
     * consolidadas no array pilot_rounds, para projetos liberados antes ou após atualizações.
     */
    public function ensurePilotRoundsConsistent(SystematicReview $review): void
    {
        if ($review->pilot_released_at && $review->pilot_size > 0) {
            $rounds = $review->pilot_rounds ?? [];
            $totalRoundSize = (int) collect($rounds)->sum('size');
            if ($totalRoundSize < (int) $review->pilot_size) {
                $roundNum = count($rounds) + 1;
                $start = $totalRoundSize;
                $size = (int) $review->pilot_size - $start;
                $recordIds = $review->stageRecords(SystematicReview::STAGE_TA)
                    ->reorder('random_key')->orderBy('id')
                    ->skip($start)->take($size)->pluck('id')->all();

                $rel = $this->reliability($review, SystematicReview::STAGE_TA, $recordIds);
                $pair = $rel['pairs'][0] ?? null;

                $rounds[] = [
                    'round' => $roundNum,
                    'size' => $size,
                    'n' => $pair['n'] ?? 0,
                    'kappa' => $pair['kappa']['kappa'] ?? null,
                    'agreement' => $pair['kappa']['agreement'] ?? null,
                    'kappa_binary' => $pair['kappa_binary']['kappa'] ?? null,
                    'closed_at' => $review->pilot_released_at->format('Y-m-d H:i:s'),
                    'released' => true,
                ];
                $review->update(['pilot_rounds' => $rounds]);
            }
        }
    }

    /**
     * Encerra a etapa atual. Exige decisão final em todos os registros.
     * Da etapa de título/resumo dá para seguir para o texto completo.
     */
    public function closeStage(SystematicReview $review, bool $continueToFullText = false): void
    {
        $stage = $review->stage;
        $screenerIds = $this->participating($review)->pluck('id')->all();
        if ($screenerIds) {
            $this->applyConsensus($review, $stage, $screenerIds);
        }
        $pending = $this->conflictsQuery($review, $stage)->count();
        if ($pending > 0) {
            throw new \RuntimeException("Ainda há {$pending} trabalho(s) sem decisão final nesta etapa.");
        }

        $closed = $review->stage_closed_at ?? [];
        $closed[$stage] = now()->format('Y-m-d H:i:s');
        $review->stage_closed_at = $closed;

        if ($stage === SystematicReview::STAGE_TA) {
            $this->tick($review, 'ta_done');
        } else {
            $this->tick($review, 'ft_done');
        }

        $included = $review->stageRecords($stage)->where(SystematicReview::decisionColumn($stage), 'include')->count();

        if ($stage === SystematicReview::STAGE_TA && $continueToFullText) {
            $review->stage = SystematicReview::STAGE_FT;
            $review->status = SystematicReview::STATUS_SCREENING;
            $review->save();
            $review->logEvent('Etapa título/resumo encerrada — iniciada a leitura do texto completo', "{$included} incluídos para texto completo");
            app(SystematicReviewProtocolService::class)->notifyOsfPending($review, 'A etapa de título/resumo foi encerrada.');
            foreach ($this->participating($review) as $screener) {
                $this->notifyScreener($screener, 'Nova etapa: texto completo', "Olá, {$screener->name}!\n\n"
                    . "A triagem por título/resumo de \"{$review->title}\" terminou. Agora são {$included} trabalhos para ler o TEXTO COMPLETO e decidir a elegibilidade. "
                    . 'Baixe o PDF pelo link do DOI/PubMed (ou peça ao professor) e registre a decisão em "Triagem Cega". Excluir exige o motivo.', 'project');
            }

            return;
        }

        $review->status = SystematicReview::STATUS_SCREENING_DONE;
        $review->save();
        $review->logEvent("Triagem encerrada ({$review->stageLabel($stage)})", "{$included} estudo(s) incluído(s)");
        app(SystematicReviewProtocolService::class)->notifyOsfPending($review, "A etapa de {$review->stageLabel($stage)} foi encerrada.");

        foreach ($this->participating($review) as $screener) {
            $this->notifyScreener($screener, 'Triagem encerrada', "Olá, {$screener->name}!\n\nA triagem de \"{$review->title}\" foi encerrada pelo Prof. Atilio. Obrigado pela participação! Os próximos passos serão combinados com você.");
        }
    }

    public function tick(SystematicReview $review, string $key, bool $condition = true): void
    {
        if (!$condition || $review->checklistDone($key)) {
            return;
        }
        $checklist = $review->checklist ?? [];
        $checklist[$key] = now()->format('Y-m-d');
        $review->forceFill(['checklist' => $checklist])->saveQuietly();
    }

    // ------------------------------------------------------------------
    // Confiabilidade e relatórios
    // ------------------------------------------------------------------

    /**
     * Kappa de Cohen entre cada par de triadores (3 categorias e binário
     * incluir+talvez × excluir), concordância bruta e IC 95%.
     *
     * @return array{pairs: array<int, array>, summary: string}
     */
    public function reliability(SystematicReview $review, string $stage, ?array $recordIds = null): array
    {
        $screeners = $review->activeScreeners()->get()->filter(fn ($s) => $s->participatesIn($stage))->values();
        $votes = SystematicReviewDecision::whereIn('systematic_review_screener_id', $screeners->pluck('id'))
            ->where('stage', $stage)
            ->when($recordIds !== null, fn ($q) => $q->whereIn('systematic_review_record_id', $recordIds))
            ->get()
            ->groupBy('systematic_review_screener_id')
            ->map(fn ($g) => $g->pluck('decision', 'systematic_review_record_id'));

        $pairs = [];
        for ($i = 0; $i < $screeners->count(); $i++) {
            for ($j = $i + 1; $j < $screeners->count(); $j++) {
                $a = $votes->get($screeners[$i]->id, collect());
                $b = $votes->get($screeners[$j]->id, collect());
                $common = $a->keys()->intersect($b->keys())->values();
                $ra = $common->map(fn ($id) => $a[$id])->all();
                $rb = $common->map(fn ($id) => $b[$id])->all();
                $bin = fn ($d) => $d === 'exclude' ? 'exclude' : 'include';

                $kb = self::cohenKappa(array_map($bin, $ra), array_map($bin, $rb));
                $advanced = 0;
                foreach ($ra as $k => $x) {
                    if ($x !== 'exclude' || $rb[$k] !== 'exclude') {
                        $advanced++;
                    }
                }
                $pairs[] = [
                    'a' => $screeners[$i]->name,
                    'b' => $screeners[$j]->name,
                    'n' => $common->count(),
                    'kappa' => self::cohenKappa($ra, $rb),
                    'kappa_binary' => $kb,
                    // PABAK = 2·Po − 1 sobre a decisão binária (excluir × avançar); Byrt et al. 1993.
                    'pabak' => $kb['agreement'] === null ? null : 2 * $kb['agreement'] - 1,
                    // Registros que pelo menos um dos dois mandou adiante (incluir ou talvez).
                    'advanced' => $advanced,
                ];
            }
        }

        $summary = collect($pairs)->map(function ($p) {
            $k = $p['kappa'];
            $kb = $p['kappa_binary'];
            if ($k['kappa'] === null) {
                return "{$p['a']} × {$p['b']}: sem dados suficientes";
            }

            return "{$p['a']} × {$p['b']}: κ (3 cat.) = " . self::fmt($k['kappa']) . ' (' . self::kappaLabel($k['kappa']) . ')'
                . ' · κ binário = ' . self::fmt($kb['kappa']) . ' (' . self::kappaLabel($kb['kappa']) . ')'
                . ', conc. bruta ' . self::fmt($k['agreement'] * 100, 1) . '%'
                . ', conc. binária ' . self::fmt(($kb['agreement'] ?? 0) * 100, 1) . '% · PABAK = ' . self::fmt($p['pabak'] ?? null)
                . " · avançados por ≥1 revisor: {$p['advanced']}/{$p['n']}, n = {$p['n']}";
        })->implode("\n") ?: 'Confiabilidade: precisa de 2 triadores.';

        return ['pairs' => $pairs, 'summary' => $summary];
    }

    /**
     * @return array{kappa: ?float, agreement: ?float, ci_low: ?float, ci_high: ?float, n: int}
     */
    public static function cohenKappa(array $a, array $b): array
    {
        $n = count($a);
        if ($n === 0) {
            return ['kappa' => null, 'agreement' => null, 'ci_low' => null, 'ci_high' => null, 'n' => 0];
        }
        $agree = 0;
        $ca = [];
        $cb = [];
        foreach ($a as $i => $x) {
            $y = $b[$i];
            if ($x === $y) {
                $agree++;
            }
            $ca[$x] = ($ca[$x] ?? 0) + 1;
            $cb[$y] = ($cb[$y] ?? 0) + 1;
        }
        $po = $agree / $n;
        $pe = 0.0;
        foreach (array_unique(array_merge(array_keys($ca), array_keys($cb))) as $cat) {
            $pe += (($ca[$cat] ?? 0) / $n) * (($cb[$cat] ?? 0) / $n);
        }
        if (abs(1 - $pe) < 1e-12) {
            // Todos votaram a mesma categoria: kappa é indefinido; concordância perfeita conta como 1.
            return ['kappa' => $po == 1.0 ? 1.0 : null, 'agreement' => $po, 'ci_low' => null, 'ci_high' => null, 'n' => $n];
        }
        $kappa = ($po - $pe) / (1 - $pe);
        $se = sqrt(($po * (1 - $po)) / ($n * (1 - $pe) ** 2));

        return [
            'kappa' => $kappa,
            'agreement' => $po,
            'ci_low' => max(-1, $kappa - 1.96 * $se),
            'ci_high' => min(1, $kappa + 1.96 * $se),
            'n' => $n,
        ];
    }

    /** Landis & Koch (1977). */
    public static function kappaLabel(?float $k): string
    {
        return match (true) {
            $k === null => 'indefinido',
            $k < 0 => 'pobre',
            $k <= 0.20 => 'leve',
            $k <= 0.40 => 'razoável',
            $k <= 0.60 => 'moderada',
            $k <= 0.80 => 'substancial',
            default => 'quase perfeita',
        };
    }

    public static function fmt(?float $v, int $decimals = 2): string
    {
        return $v === null ? '—' : number_format($v, $decimals, ',', '.');
    }

    /**
     * Números do fluxograma PRISMA 2020.
     */
    public function prisma(SystematicReview $review): array
    {
        $counts = $review->search_counts ?? [];
        $records = $review->records();
        $ftStarted = $review->stage === SystematicReview::STAGE_FT || $review->isStageClosed(SystematicReview::STAGE_FT);

        $taExcluded = (clone $records)->where('ta_decision', 'exclude')->count();
        $sought = (clone $records)->where('ta_decision', 'include')->count();
        $notRetrieved = (clone $records)->where('ft_decision', 'exclude')->where('ft_reason', self::NOT_RETRIEVED)->count();
        // reorder(): records() já vem ordenado por seq, e o MySQL (ONLY_FULL_GROUP_BY) recusa
        // "GROUP BY motivo ORDER BY seq". SQLite e MariaDB aceitam, por isso só quebrava em produção.
        $ftReasons = (clone $records)->reorder()->where('ft_decision', 'exclude')->where('ft_reason', '!=', self::NOT_RETRIEVED)
            ->selectRaw('ft_reason, count(*) as n')->groupBy('ft_reason')->orderByDesc('n')->pluck('n', 'ft_reason')->all();
        $taReasons = (clone $records)->reorder()->where('ta_decision', 'exclude')
            ->selectRaw('ta_reason, count(*) as n')->groupBy('ta_reason')->orderByDesc('n')->pluck('n', 'ta_reason')->all();

        $stopRule = (clone $records)->where('ta_decision', 'exclude')->where('ta_reason', self::STOP_RULE_REASON)->count();

        return [
            'sources' => $counts['sources'] ?? [],
            'identified' => array_sum($counts['sources'] ?? []),
            'duplicates' => (int) ($counts['duplicates_removed'] ?? 0),
            'screened' => $records->count(),
            // Triagem priorizada: excluídos sem leitura humana (regra de parada + checagem aleatória).
            'ta_stop_rule' => $stopRule,
            'ta_screened_manually' => $records->count() - $stopRule,
            'ta_excluded' => $taExcluded,
            'ta_reasons' => $taReasons,
            'sought' => $sought,
            'not_retrieved' => $notRetrieved,
            'assessed' => $ftStarted ? $sought - $notRetrieved : null,
            'ft_excluded' => $ftStarted ? array_sum($ftReasons) : null,
            'ft_reasons' => $ftReasons,
            'included' => $ftStarted
                ? (clone $records)->where('ft_decision', 'include')->count()
                : null,
        ];
    }

    /**
     * Parágrafo de Métodos (inglês) pronto para o manuscrito.
     */
    public function methodsText(SystematicReview $review): string
    {
        $screeners = $review->screeners()->whereNotNull('terms_accepted_at')->get();
        $n = $screeners->count();
        $numberWords = [1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four'];
        $who = $numberWords[$n] ?? (string) $n;
        $prisma = $this->prisma($review);
        $sources = implode(', ', array_keys($prisma['sources'])) ?: '[databases]';
        $date = $review->search_date?->format('F j, Y') ?? '[search date]';

        $text = "The review protocol was prospectively registered"
            . ($review->registration_id ? " in {$review->registration_platform} ({$review->registration_id})" : ' [registration]')
            . ($review->registered_at ? ' on ' . $review->registered_at->format('F j, Y') : '')
            . " and the review is reported following the {$review->reportingGuideline()} statement. "
            . "Searches were run in {$sources} up to {$date}. "
            . "Records were deduplicated ({$prisma['duplicates']} duplicates removed) and {$prisma['screened']} unique records were screened. ";

        foreach ([SystematicReview::STAGE_TA => 'titles and abstracts', SystematicReview::STAGE_FT => 'full texts'] as $stage => $label) {
            if (!$review->isStageClosed($stage) && $review->stage !== $stage) {
                continue;
            }
            $rel = $this->reliability($review, $stage);
            $pair = $rel['pairs'][0] ?? null;
            $text .= ($stage === SystematicReview::STAGE_TA ? "{$who} reviewer" . ($n === 1 ? '' : 's') . ' independently screened ' : 'The same reviewers independently assessed ')
                . "{$label} against the eligibility criteria using a blinded web-based screening platform";
            if ($pair && $pair['kappa']['kappa'] !== null) {
                $k = $pair['kappa'];
                $text .= sprintf(
                    '; inter-rater agreement was %.1f%% (Cohen\'s κ = %.2f%s, %s)',
                    $k['agreement'] * 100,
                    $k['kappa'],
                    $k['ci_low'] !== null ? sprintf(', 95%% CI %.2f–%.2f', $k['ci_low'], $k['ci_high']) : '',
                    self::kappaLabelEn($k['kappa'])
                );
            }
            $text .= '. ';
        }

        if ($review->prioritisedScreening()) {
            $rule = $review->stoppingRule();
            $rounds = collect($review->ta_qc['rounds'] ?? []);
            $text .= 'Title and abstract screening was prioritised by active learning implemented in the screening platform: '
                . 'records were represented by TF-IDF features of title, abstract and keywords, and a multinomial naive Bayes classifier with equal class priors was retrained on each reviewer\'s own labels after every '
                . ActiveLearningService::RETRAIN_EVERY . ' decisions, so that each reviewer saw the remaining records in order of predicted relevance; every screened record was labelled by a human reviewer. '
                . 'Each reviewer stopped when all validation-set studies had been identified, at least ' . (int) round($rule['min_fraction'] * 100) . '% of records had been screened, '
                . 'the number of screened records was at least ' . (int) $rule['ratio'] . ' times the number labelled relevant and the last ' . $rule['consecutive'] . ' consecutively screened records were irrelevant. '
                . 'A record proceeded to full-text assessment if either reviewer labelled it relevant. '
                . ($rounds->isNotEmpty()
                    ? 'A random sample of the records reached by neither reviewer (' . $rounds->sum(fn ($r) => count($r['record_ids'] ?? [])) . ' records in ' . $rounds->count() . ' round' . ($rounds->count() > 1 ? 's' : '') . ') was screened by the second reviewer as a quality check'
                        . ($rounds->sum('hits') > 0 ? ', and screening was resumed whenever a potentially eligible record was found' : ' and no potentially eligible record was found') . '. '
                    : '')
                . ($prisma['ta_stop_rule'] > 0 ? "In total, {$prisma['ta_screened_manually']} records were screened manually and {$prisma['ta_stop_rule']} were excluded without manual screening by the pre-specified stopping rule. " : '');
        }
        $text .= 'Disagreements and records marked as uncertain were resolved by a senior reviewer (A.S.C.).';
        if ($review->pilot_size > 0) {
            $this->ensurePilotRoundsConsistent($review);
            $rounds = collect($review->pilot_rounds ?? []);
            if ($rounds->isEmpty()) {
                $text .= " A calibration exercise on a random sample of {$review->pilot_size} records was performed before full screening.";
            } else {
                $parts = $rounds->map(fn ($r) => "{$r['size']} records" . (isset($r['kappa']) ? sprintf(' (κ = %.2f)', $r['kappa']) : ''))->all();
                if ($review->inPilot()) {
                    $current = $this->pilotReliability($review)['pairs'][0]['kappa'] ?? null;
                    $rem = (int) $review->pilot_size - $review->pilotRoundStart();
                    if ($rem > 0) {
                        $parts[] = "{$rem} records" . (isset($current['kappa']) ? sprintf(' (κ = %.2f)', $current['kappa']) : '');
                    }
                }
                $text .= (count($parts) > 1 ? ' Calibration was performed on successive random samples of ' : ' Calibration was performed on a random sample of ')
                    . implode(', then ', $parts)
                    . '; as pre-specified, whenever agreement was below κ = 0.60 the eligibility criteria were discussed with the reviewers and a new random sample was screened before full screening.';
            }
        }
        if ($prisma['included'] !== null) {
            $text .= " Overall, {$prisma['included']} studies were included.";
        }

        return $text;
    }

    public static function kappaLabelEn(float $k): string
    {
        return match (true) {
            $k < 0 => 'poor agreement',
            $k <= 0.20 => 'slight agreement',
            $k <= 0.40 => 'fair agreement',
            $k <= 0.60 => 'moderate agreement',
            $k <= 0.80 => 'substantial agreement',
            default => 'almost perfect agreement',
        };
    }

    /**
     * Protocolo em Markdown (PRISMA-P), para colar no OSF e guardar na pasta.
     */
    public function protocolMarkdown(SystematicReview $review): string
    {
        $md = "# {$review->title}\n\n";
        $md .= "- **Review type:** {$review->typeLabel()}\n";
        $md .= "- **Framework:** {$review->framework}\n";
        $md .= '- **Registration:** ' . ($review->registrationLine() ?: 'not yet registered') . "\n";
        if ($review->embargo_until) {
            $md .= '- **Embargo until:** ' . $review->embargo_until->format('Y-m-d') . "\n";
        }
        $md .= "- **Coordinator:** Prof. Dr. Atilio Sersun Calefi\n\n";

        foreach (SystematicReview::PROTOCOL_FIELDS as $key => $label) {
            if ($value = $review->protocolValue($key)) {
                $md .= "## {$label}\n\n" . ($key === 'search_string' ? "```text\n{$value}\n```" : $value) . "\n\n";
            }
        }
        $md .= "## Screening exclusion reasons\n\n" . collect($review->exclusionReasons())->map(fn ($r) => "- {$r}")->implode("\n") . "\n";

        return $md;
    }

    // ------------------------------------------------------------------
    // Avisos
    // ------------------------------------------------------------------

    /**
     * Mensagem livre do professor a triadores deste projeto ({nome} = primeiro nome).
     * Para a dupla toda, entra também na conversa registrada do projeto.
     */
    public function messageScreeners(SystematicReview $review, $screeners, string $title, string $body): int
    {
        $n = 0;
        $names = [];
        $screeners = collect($screeners);
        // Mensagem à dupla inteira também entra na conversa: o botão do e-mail leva até ela.
        $toPair = $screeners->reject(fn ($s) => $s->removed_at || $s->systematic_review_id !== $review->id)->count() === $review->activeScreeners()->count();
        foreach ($screeners as $s) {
            if ($s->removed_at || $s->systematic_review_id !== $review->id) {
                continue;
            }
            $first = Str::before(trim($s->name), ' ') ?: $s->name;
            $this->notifyScreener($s, trim($title), str_replace(['{nome}', '{nome_completo}'], [$first, $s->name], trim($body)), $toPair && $s->hasSignedTerms() ? 'chat' : 'project');
            $names[] = $first;
            $n++;
        }
        $review->logEvent('Mensagem do professor: ' . Str::limit(trim($title), 120), $n . ' destinatário(s)');
        if ($n > 0 && $n === $review->activeScreeners()->count()) {
            \App\Models\SrMessage::create(['systematic_review_id' => $review->id, 'user_id' => auth()->id(), 'author_name' => 'Prof. Atilio',
                'author_role' => \App\Models\SrMessage::ROLE_PROFESSOR, 'body' => trim($title) . "\n\n" . str_replace(['{nome}', '{nome_completo}'], ['dupla', 'dupla'], trim($body))]);
        }

        return $n;
    }

    // ------------------------------------------------------------------
    // Conversa registrada do projeto (professor + dupla)
    // ------------------------------------------------------------------

    /** Pode ler e escrever: o admin, ou o triador ativo deste projeto com o termo assinado. */
    public function chatScreener(SystematicReview $review, ?User $user): ?SystematicReviewScreener
    {
        if (!$user) {
            return null;
        }

        return $review->activeScreeners()->where('user_id', $user->id)->get()->first(fn ($s) => $s->hasSignedTerms());
    }

    public function canChat(SystematicReview $review, ?User $user): bool
    {
        return $user && ($user->role === 'admin' || $this->chatScreener($review, $user));
    }

    /** Grava a mensagem e avisa os demais participantes (e-mail/Telegram). */
    public function postMessage(SystematicReview $review, User $user, string $body): \App\Models\SrMessage
    {
        $body = trim($body);
        if (!$this->canChat($review, $user)) {
            throw new \RuntimeException('Você não participa da conversa deste projeto.');
        }
        if ($body === '') {
            throw new \RuntimeException('Escreva a mensagem.');
        }
        if (mb_strlen($body) > 3000) {
            throw new \RuntimeException('Mensagem longa demais (máximo de 3.000 caracteres).');
        }
        $isProfessor = $user->role === 'admin';
        $message = \App\Models\SrMessage::create([
            'systematic_review_id' => $review->id,
            'user_id' => $user->id,
            'author_name' => $isProfessor ? 'Prof. Atilio' : ($this->chatScreener($review, $user)?->name ?? $user->name),
            'author_role' => $isProfessor ? \App\Models\SrMessage::ROLE_PROFESSOR : \App\Models\SrMessage::ROLE_STUDENT,
            'body' => $body,
        ]);

        $code = $review->selectionCode() ?? $review->protocol_code ?? 'projeto';
        $text = "{$message->author_name} escreveu na conversa do projeto {$code}:\n\n" . $body
            . "\n\nPara ler e responder, use o botão abaixo. A conversa fica no Portal do Aluno: Painel › seu projeto › \"Conversa\" (ou menu Triagem Cega › o projeto › Conversa do projeto). Responder a este e-mail não chega à conversa."
            . "\n\n🔒 Revisão cega: não revele decisões, estudos, contagens ou dados da sua triagem ou extração, nem pergunte os da dupla, até o professor encerrar a etapa.";
        foreach ($review->activeScreeners()->get() as $s) {
            if ($s->user_id !== $user->id && $s->hasSignedTerms()) {
                $this->notifyScreener($s, "Nova mensagem no projeto {$code}", $text, 'chat');
            }
        }
        if (!$isProfessor) {
            $this->notifyProfessor("Nova mensagem no projeto {$code}", $text, $review);
        }

        return $message;
    }

    /**
     * E-mail + Telegram ao aluno. $open: null = lista da Triagem Cega; 'project' = a página do projeto;
     * 'chat' = a conversa do projeto já aberta (o aluno não precisa procurar onde a mensagem está).
     */
    public function notifyScreener(SystematicReviewScreener $screener, string $title, string $body, ?string $open = null): void
    {
        $training = (bool) $screener->systematicReview->is_training;
        $url = url($training ? '/portal/treino-triagem' : '/portal/triagem-cega') . ($open ? '?projeto=' . $screener->id : '') . ($open === 'chat' ? '#conversa' : '');
        $this->mail($screener->email, new AppNotificationMail(
            title: $title,
            messageContent: $body,
            actionUrl: $url,
            actionText: $training ? 'Abrir o treino da triagem' : match ($open) {
                'chat' => 'Abrir a conversa do projeto',
                'project' => 'Abrir o projeto no portal',
                default => 'Abrir a Triagem Cega',
            },
            subject: ($screener->systematicReview->displayCode() ? '[' . $screener->systematicReview->displayCode() . '] ' : '') . $title . ' — ' . Str::limit($screener->systematicReview->title, 60),
        ));

        $chat = $screener->user?->telegram_chat_id ?: $screener->user?->student?->telegram_chat_id;
        if ($chat) {
            $this->telegram($chat, $title, $body, $url);
        }
    }

    public function notifyProfessor(string $title, string $body, SystematicReview $review): void
    {
        $url = \App\Filament\Resources\SystematicReviewResource::getUrl('view', ['record' => $review], panel: 'admin');
        $this->mail((string) config('services.research.professor_email'), new AppNotificationMail(
            title: $title,
            messageContent: $body,
            actionUrl: $url,
            actionText: 'Abrir no painel',
            subject: $title . ' — ' . Str::limit($review->title, 60),
        ));

        $admin = User::where('role', 'admin')->whereNotNull('telegram_chat_id')->orderBy('id')->first();
        if ($admin) {
            $this->telegram($admin->telegram_chat_id, $title, $body, $url);
        }
    }

    protected function telegram(string $chatId, string $title, string $body, ?string $url = null): void
    {
        try {
            app(TelegramService::class)->sendMessage(
                $chatId,
                '<b>' . e($title) . "</b>\n\n" . e(Str::limit($body, 3300))
                    . ($url ? "\n\n👉 <a href=\"" . e($url) . '">Abrir</a>' : '')
            );
        } catch (\Throwable $e) {
            Log::error('Revisão sistemática: falha ao enviar Telegram: ' . $e->getMessage());
        }
    }

    protected function mail(string $to, AppNotificationMail $mail): void
    {
        try {
            Mail::to($to)->send($mail);
        } catch (\Throwable $e) {
            Log::error("Revisão sistemática: falha ao enviar e-mail para {$to}: " . $e->getMessage());
        }
    }
}
