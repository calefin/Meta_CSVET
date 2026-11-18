<?php

namespace App\Console\Commands;

use App\Models\ResearchCall;
use Illuminate\Console\Command;

/**
 * Cria (ou atualiza com os textos-padrão) a chamada das metanálises e a
 * publica — atalho para o SSH, sem depender do painel.
 */
class PublishResearchCall extends Command
{
    protected $signature = 'app:publicar-selecao
                            {--prazo= : Último dia de inscrições (AAAA-MM-DD)}
                            {--rascunho : Deixa em rascunho em vez de abrir as inscrições}
                            {--manter-textos : Se a chamada já existe, não sobrescreve os textos editados no painel}
                            {--encerrar : Só encerra as inscrições (não mexe nos textos)}
                            {--reabrir : Reabre as inscrições de uma chamada encerrada}';

    protected $description = 'Cria/atualiza a chamada da seleção das metanálises (títulos mascarados) e abre as inscrições';

    public function handle(): int
    {
        $defaults = ResearchCall::defaults();
        $deadline = $this->option('prazo');
        if ($deadline && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
            $this->error('Use o prazo no formato AAAA-MM-DD, por exemplo 2026-11-15.');

            return self::FAILURE;
        }

        $call = ResearchCall::where('slug', $defaults['slug'])->first();

        if ($this->option('encerrar')) {
            if (!$call) {
                $this->error('A chamada ainda não existe.');

                return self::FAILURE;
            }
            $call->update(['status' => ResearchCall::STATUS_CLOSED]);
            $this->info('Inscrições encerradas. As inscrições recebidas continuam no painel.');

            return self::SUCCESS;
        }

        if (!$call) {
            $call = ResearchCall::create($defaults);
            $this->info('Chamada criada.');
        } elseif (!$this->option('manter-textos')) {
            // Atualiza os textos, mas preserva os vínculos com as Revisões Sistemáticas.
            $links = collect($call->projects ?? [])->pluck('systematic_review_id', 'code');
            $defaults['projects'] = collect($defaults['projects'])
                ->map(fn ($p) => ['systematic_review_id' => $links[$p['code']] ?? null] + $p)
                ->all();
            $call->update(collect($defaults)->except(['slug', 'status'])->all());
            $this->info('Textos da chamada atualizados (vínculos dos projetos mantidos).');
        }

        // Chamada encerrada continua encerrada ao atualizar os textos (só reabre com --reabrir).
        $status = match (true) {
            (bool) $this->option('rascunho') => ResearchCall::STATUS_DRAFT,
            $call->status === ResearchCall::STATUS_CLOSED && !$this->option('reabrir') => ResearchCall::STATUS_CLOSED,
            default => ResearchCall::STATUS_OPEN,
        };
        $call->update([
            'status' => $status,
            'closes_at' => $deadline ?: $call->closes_at,
        ]);
        if ($status === ResearchCall::STATUS_CLOSED) {
            $this->warn('A chamada está encerrada e continua encerrada. Para abrir de novo: php artisan app:publicar-selecao --reabrir');
        }

        $this->line('Situação: ' . ResearchCall::STATUS_LABELS[$call->status]
            . ($call->closes_at ? ' · inscrições até ' . $call->closes_at->format('d/m/Y') : ''));
        $this->line('Página: ' . $call->publicUrl());

        return self::SUCCESS;
    }
}
