<x-filament-panels::page>
    {{-- Página custom do Filament não carrega o Tailwind do app: estilos próprios. --}}
    <style>
        .tc-card { border: 1px solid rgba(148,163,184,.35); border-radius: 1rem; padding: 1rem 1.25rem; background: rgba(255,255,255,.75); }
        .dark .tc-card { background: rgba(15,23,42,.6); }
        .tc-list { display: grid; gap: 1rem; }
        .tc-eyebrow { font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #0d9488; }
        .tc-title { font-size: 1.1rem; font-weight: 700; margin: .25rem 0 .5rem; line-height: 1.35; }
        .tc-meta { font-size: .85rem; opacity: .8; }
        .tc-btn { display: inline-flex; align-items: center; gap: .35rem; padding: .55rem 1rem; border-radius: .6rem; font-weight: 600; font-size: .9rem; border: 1px solid transparent; cursor: pointer; background: #0d9488; color: #fff; }
        .tc-btn:disabled { opacity: .45; cursor: not-allowed; }
        .tc-btn.gray { background: transparent; color: inherit; border-color: rgba(148,163,184,.5); }
        .tc-btn.inc { background: #16a34a; }
        .tc-btn.exc { background: #dc2626; }
        .tc-btn.may { background: #d97706; }
        .tc-btn.sel { outline: 3px solid rgba(13,148,136,.45); outline-offset: 2px; }
        .tc-kbd { font-size: .7rem; padding: 0 .3rem; border-radius: .25rem; background: rgba(255,255,255,.25); border: 1px solid rgba(255,255,255,.4); }
        .tc-gray .tc-kbd { background: rgba(148,163,184,.15); border-color: rgba(148,163,184,.4); }
        .tc-terms { white-space: pre-wrap; font-size: .875rem; line-height: 1.65; max-height: 55vh; overflow-y: auto; padding: 1rem; border-radius: .75rem; background: rgba(148,163,184,.08); border: 1px solid rgba(148,163,184,.3); }
        .tc-input { width: 100%; padding: .55rem .75rem; border-radius: .6rem; border: 1px solid rgba(148,163,184,.6); background: transparent; color: inherit; font-size: .95rem; }
        .tc-bar { height: .5rem; border-radius: 999px; background: rgba(148,163,184,.25); overflow: hidden; }
        .tc-bar > div { height: 100%; background: #0d9488; }
        .tc-prog { margin: -.1rem 0 .75rem; padding: .6rem .75rem; border-radius: .75rem; background: rgba(13,148,136,.08); border: 1px solid rgba(13,148,136,.25); font-size: .9rem; }
        .tc-actions-prog { font-size: .8rem; font-weight: 600; color: #0f766e; margin-bottom: .4rem; }
        .dark .tc-actions-prog { color: #5eead4; }
        .tc-abstract { font-size: .95rem; line-height: 1.7; white-space: pre-line; margin-top: .75rem; }
        .tc-check { margin-top: 1rem; padding: .8rem; border: 1px solid rgba(13,148,136,.35); border-radius: .6rem; background: rgba(13,148,136,.05); }
        .tc-check-row { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .4rem .8rem; padding: .5rem 0; border-top: 1px dashed rgba(127,127,127,.25); }
        .tc-check-row:first-of-type { border-top: 0; }
        .tc-check-q { font-weight: 600; font-size: .9rem; }
        .tc-check-h { font-size: .78rem; opacity: .75; margin-top: .15rem; }
        .tc-check-opts { display: flex; gap: .3rem; flex-wrap: wrap; align-items: flex-start; }
        .tc-pill { font-size: .78rem; padding: .25rem .6rem; border-radius: 999px; border: 1px solid rgba(127,127,127,.45); background: transparent; cursor: pointer; }
        .tc-pill-sim.on { background: #16a34a; border-color: #16a34a; color: #fff; }
        .tc-pill-nao.on { background: #dc2626; border-color: #dc2626; color: #fff; }
        .tc-pill-incerto.on { background: #d97706; border-color: #d97706; color: #fff; }
        .tc-check-sug { margin-top: .6rem; padding: .55rem .7rem; border-radius: .5rem; background: rgba(245,158,11,.12); font-size: .88rem; }
        .tc-reason.tc-sug { outline: 2px solid #f59e0b; outline-offset: 1px; }
        .tc-row { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .tc-between { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; justify-content: space-between; }
        .tc-reasons { display: grid; gap: .4rem; margin-top: .75rem; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
        .tc-reason { text-align: left; padding: .5rem .75rem; border-radius: .6rem; border: 1px solid rgba(220,38,38,.45); background: rgba(220,38,38,.06); color: inherit; cursor: pointer; font-size: .875rem; }
        .tc-reason:hover { background: rgba(220,38,38,.14); }
        .tc-protocol { font-size: .85rem; line-height: 1.6; }
        .tc-protocol dt { font-weight: 700; margin-top: .5rem; }
        .tc-protocol dd { white-space: pre-line; opacity: .9; }
        .tc-mine { font-size: .85rem; padding: .4rem .7rem; border-radius: .5rem; background: rgba(13,148,136,.1); }
        .tc-notice { font-size: .9rem; padding: .75rem 1rem; border-radius: .75rem; background: #fffbeb; color: #78350f; border: 1px solid #fcd34d; }
        .dark .tc-notice { background: rgba(120,53,15,.25); color: #fde68a; }
        details summary { cursor: pointer; font-weight: 600; }
        .tc-stop { list-style: none; padding: 0; margin: .5rem 0 0; display: grid; gap: .3rem; font-size: .875rem; }
        .tc-stop li { padding: .35rem .6rem; border-radius: .5rem; background: rgba(148,163,184,.1); overflow-wrap: anywhere; }
        .tc-stop li.ok { background: rgba(22,163,74,.1); }
        /* Decisão: botões grandes e iguais; no celular ficam presos no rodapé da tela. */
        .tc-title, .tc-abstract, .tc-meta, .tc-protocol dd, .tc-mine { overflow-wrap: anywhere; }
        .tc-actions { margin-top: 1rem; display: grid; gap: .5rem; }
        .tc-decide { display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; gap: .5rem; }
        .tc-reason { height: auto; white-space: normal; line-height: 1.4; flex-shrink: 0; }
        .tc-decide .tc-btn { justify-content: center; min-height: 2.9rem; font-size: 1rem; }
        .tc-nav { display: flex; justify-content: space-between; gap: .5rem; }
        .tc-nav .tc-btn { min-height: 2.6rem; }
        @media (hover: none) { .tc-kbd { display: none; } }
        @media (max-width: 640px) {
            .tc-card { padding: .85rem .9rem; border-radius: .85rem; }
            .tc-title { font-size: 1.05rem; }
            .tc-abstract { font-size: 1rem; }
            .tc-kbd { display: none; }
            .tc-actions { position: sticky; bottom: 0; z-index: 20; margin: 1rem -.9rem -.85rem; padding: .6rem .9rem calc(.6rem + env(safe-area-inset-bottom));
                /* Fundo sólido: com transparência o resumo aparecia por trás da lista de motivos de exclusão. */
                background: #fff; border-top: 1px solid rgba(148,163,184,.35); box-shadow: 0 -6px 18px rgba(15,23,42,.10); border-radius: 0 0 .85rem .85rem; }
            .dark .tc-actions { background: #0f172a; }
            .tc-reasons { display: flex; flex-direction: column; max-height: 45vh; overflow-y: auto; margin-top: 0; overscroll-behavior: contain; }
            .tc-reason { min-height: 2.75rem; }
            .tc-decide .tc-btn { padding: .55rem .4rem; font-size: .95rem; }
            .tc-nav .tc-btn { min-height: 2.3rem; padding: .35rem .8rem; font-size: .85rem; }
            .tc-btn { min-height: 2.6rem; }
            .tc-terms { max-height: 50vh; }
        }
    </style>

    @if ($mode === 'list')
        @if ($assignments->isEmpty())
            <p style="opacity:.75">Nenhuma triagem atribuída a você ainda. Ela aparece aqui quando o Prof. Atilio incluir o seu e-mail num projeto.</p>
        @else
            @if ($projects->count() > 1)
                @php $pending = $projects->where('todo', true)->count(); @endphp
                {{-- Quadro "você tem N projetos": parte dos alunos achou que o 2º projeto era a mesma tarefa repetida. --}}
                <div class="tc-card" style="margin-bottom:1rem;border-width:2px;border-color:{{ $pending ? '#d97706' : '#16a34a' }}">
                    <div class="tc-eyebrow" style="color:{{ $pending ? '#b45309' : '#15803d' }}">Seus projetos no programa</div>
                    <div class="tc-title">Você participa de {{ $projects->count() }} projetos diferentes e precisa fazer a triagem de TODOS.</div>
                    <p class="tc-meta" style="margin-bottom:.75rem">Não é a mesma tarefa repetida: cada projeto tem tema, critérios e lista de trabalhos próprios. Mesmo que um trabalho pareça com o do outro projeto, decida cada um pelos critérios daquele projeto.</p>
                    <div style="display:grid;gap:.5rem">
                        @foreach ($projects as $i => $p)
                            <div class="tc-between" style="padding:.6rem .8rem;border-radius:.6rem;background:{{ $p['todo'] ? 'rgba(217,119,6,.08)' : 'rgba(22,163,74,.08)' }}">
                                <div>
                                    <strong>Projeto {{ $i + 1 }} de {{ $projects->count() }} · {{ $p['code'] }}</strong>
                                    <span class="tc-meta"> ·
                                        @if (!$p['signed'])
                                            falta assinar o termo
                                        @elseif ($p['finished'])
                                            ✅ {{ $p['pilot'] ? 'piloto concluído' : 'etapa concluída' }}
                                        @elseif ($p['open'] && $p['goal'])
                                            piloto · <strong>meta {{ $p['goal']['n'] }}: {{ $p['goal']['done'] }} de {{ $p['goal']['size'] }} · faltam {{ $p['goal']['size'] - $p['goal']['done'] }}</strong>
                                            ({{ $p['done'] }} de {{ $p['total'] }} no piloto)
                                        @elseif ($p['open'])
                                            {{ $p['pilot'] ? 'piloto: ' : '' }}{{ $p['done'] }} de {{ $p['total'] }} decididos · <strong>faltam {{ $p['total'] - $p['done'] }}</strong>
                                        @elseif ($p['review']->status === \App\Models\SystematicReview::STATUS_PROTOCOL)
                                            em preparação (ainda não abriu)
                                        @else
                                            {{ $p['review']->stageLabel() }} · nada a fazer agora
                                        @endif
                                    </span>
                                </div>
                                @if ($p['todo'])
                                    <button type="button" class="tc-btn" wire:click="choose({{ $p['screener']->id }})">{{ $p['signed'] ? 'Fazer agora' : 'Assinar o termo' }}</button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    @if ($pending)
                        <p class="tc-meta" style="margin-top:.6rem">Faltam <strong>{{ $pending }}</strong> {{ $pending === 1 ? 'projeto' : 'projetos' }}. A etapa só termina quando todos estiverem ✅.</p>
                    @endif
                </div>
            @endif
            <div class="tc-list">
                @foreach ($assignments as $a)
                    @php $r = $a->systematicReview; $signed = $a->hasSignedTerms(); @endphp
                    @if ($r->is_training)
                        <div class="tc-card">
                            <div class="tc-eyebrow">🧪 Treino · não conta para o seu projeto</div>
                            <div class="tc-title">{{ $r->title }}</div>
                            <div class="tc-meta">
                                @if ($r->status === \App\Models\SystematicReview::STATUS_PROTOCOL)
                                    O treino ainda não foi liberado. Você recebe um e-mail quando abrir.
                                @elseif ($r->status === \App\Models\SystematicReview::STATUS_SCREENING)
                                    Aberto{{ $r->training_closes_at ? ' até ' . $r->training_closes_at->format('d/m') . ' às ' . $r->training_closes_at->format('H\hi') : '' }}
                                    @if ($a->hasFinished($r->stage)) · você já decidiu todos; os resultados aparecem quando o treino fechar @endif
                                @else
                                    Encerrado: os resultados e o gabarito comentado estão liberados.
                                @endif
                            </div>
                            <div class="tc-row" style="margin-top:.75rem">
                                <button type="button" class="tc-btn" wire:click="choose({{ $a->id }})">{{ $r->status === \App\Models\SystematicReview::STATUS_SCREENING_DONE ? 'Ver os resultados' : 'Abrir o treino' }}</button>
                            </div>
                        </div>
                        @continue
                    @endif
                    <div class="tc-card">
                        <div class="tc-eyebrow">{{ $r->displayCode() ?? $r->typeLabel() }}{{ $a->label ? ' · ' . $a->label : '' }}</div>
                        {{-- Título real só depois do termo de confidencialidade. --}}
                        <div class="tc-title">{{ $signed ? $r->title : 'Projeto ' . ($r->displayCode() ?? '') . ' — assine o termo para ver o título e o protocolo' }}</div>
                        <div class="tc-meta">
                            @if ($r->status === \App\Models\SystematicReview::STATUS_PROTOCOL)
                                Em preparação (registro do protocolo no OSF) ·
                            @else
                                Etapa: {{ $r->stageLabel() }} ·
                            @endif
                            Termo de confidencialidade: <strong>{{ $signed ? 'assinado' : 'PENDENTE' }}</strong>
                            @if ($signed && $a->hasFinished($r->stage) && $r->status !== \App\Models\SystematicReview::STATUS_PROTOCOL) · você já concluiu esta etapa @endif
                        </div>
                        @php $unreadHere = $a->unreadMessagesCount(); @endphp
                        <div class="tc-row" style="margin-top:.75rem">
                            <button type="button" class="tc-btn" wire:click="choose({{ $a->id }})">{{ $signed ? 'Abrir' : 'Ler e assinar o termo' }}</button>
                            @if ($signed)
                                <a class="tc-btn gray" href="{{ \App\Filament\Pages\TriagemCega::getUrl(['projeto' => $a->id]) }}#conversa">💬 Conversa
                                    @if ($unreadHere > 0)<span style="padding:0 .4rem;border-radius:999px;background:#d97706;color:#fff;font-size:.75rem">{{ $unreadHere }} {{ $unreadHere === 1 ? 'nova' : 'novas' }}</span>@endif
                                </a>
                            @else
                                <span class="tc-meta">A conversa com a dupla e o professor abre depois que você assinar o termo.</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    @elseif ($mode === 'terms')
        <div class="tc-card" style="margin-bottom:1rem">
            @include('filament.partials.stage-guide', ['key' => 'terms', 'review' => $review])
        </div>
        <div class="tc-card">
            <div class="tc-eyebrow">Antes de começar</div>
            <div class="tc-title">Termo de confidencialidade e participação</div>
            <p class="tc-meta" style="margin-bottom:.75rem">Leia com atenção. A assinatura eletrônica fica registrada com data, hora, IP e a impressão digital (SHA-256) do texto.</p>
            <div class="tc-terms">{{ $terms }}</div>

            <label class="tc-row" style="margin-top:1rem;font-size:.9rem">
                <input type="checkbox" wire:model="termsRead">
                Li o termo inteiro e concordo com todas as condições.
            </label>
            <div style="margin-top:.75rem;max-width:32rem">
                <label style="font-size:.85rem;font-weight:600">Assinatura: digite o seu nome completo ({{ $screener->name }})</label>
                <input type="text" class="tc-input" wire:model="signature" autocomplete="name" wire:keydown.enter="sign">
            </div>
            <div class="tc-row" style="margin-top:1rem">
                <button type="button" class="tc-btn" wire:click="sign" wire:loading.attr="disabled">Assinar e começar</button>
                <button type="button" class="tc-btn gray" wire:click="back">Voltar</button>
            </div>
        </div>

    @elseif ($mode === 'waiting' && $review->is_training)
        <div class="tc-card">
            <div class="tc-eyebrow">🧪 Treino · não conta para o seu projeto</div>
            <div class="tc-title">{{ $review->title }}</div>
            @if ($preparing ?? false)
                <p>O treino ainda não foi liberado. Você recebe um e-mail quando abrir.</p>
            @elseif ($finished)
                <p>✅ Você decidiu todos os trabalhos. Os resultados (as suas decisões, as da sua dupla e o gabarito comentado) aparecem aqui quando a sua dupla terminar
                    @if ($review->training_closes_at) ou quando o treino fechar, em {{ $review->training_closes_at->format('d/m') }} às {{ $review->training_closes_at->format('H\hi') }}@endif.</p>
            @else
                <p>O treino não está aberto agora.</p>
            @endif
            <div class="tc-row" style="margin-top:.75rem"><button type="button" class="tc-btn gray" wire:click="back">Voltar</button></div>
        </div>

    @elseif ($mode === 'training_results')
        <div class="tc-card" style="margin-bottom:1rem">
            <div class="tc-between">
                <div>
                    <div class="tc-eyebrow">🧪 Treino encerrado · resultados</div>
                    <div class="tc-title">{{ $review->title }}</div>
                </div>
                <button type="button" class="tc-btn gray" wire:click="back">Voltar</button>
            </div>
            <p class="tc-meta">Compare as suas decisões com as da sua dupla e com o gabarito. Os trabalhos em que vocês discordaram aparecem primeiro: são os melhores para aprender. Nada aqui conta para o seu projeto.</p>
        </div>
        @include('filament.partials.training-results', ['results' => $results, 'meId' => $screener->id])

    @elseif ($mode === 'waiting')
        @if (($others ?? collect())->isNotEmpty())
            <div class="tc-card" style="margin-bottom:1rem">@include('filament.partials.sr-other-projects', ['others' => $others])</div>
        @endif
        <div class="tc-card" style="margin-bottom:1rem">
            @include('filament.partials.sr-journey', ['journey' => $journey, 'you' => $you])
        </div>
        <div class="tc-card">
            <div class="tc-eyebrow">{{ $review->displayCode() ? $review->displayCode() . ' · ' : '' }}{{ $review->stageLabel() }}</div>
            <div class="tc-title">{{ $review->title }}</div>
            @if ($preparing ?? false)
                <p>✅ Termo assinado. Este projeto está <strong>em preparação</strong>: o protocolo está sendo registrado no OSF e, em seguida, o professor faz as buscas. A triagem abre aqui mesmo, e você recebe um e-mail quando isso acontecer. <strong>O que você faz agora está em "Etapa atual", mais abaixo.</strong>
                    @unless ($review->protocol_pdf_path) O PDF do protocolo aparece nesta página quando o professor congelar o protocolo. @endunless</p>
            @elseif ($finished && $review->qcOpen())
                <p>✅ Você cumpriu a regra de parada. Agora está sendo feita a checagem aleatória do que ninguém leu. Se ela encontrar algum trabalho possivelmente elegível, a sua triagem reabre e você será avisado(a).</p>
            @elseif ($finished)
                <p>✅ Você concluiu esta etapa. O Prof. Atilio está conferindo as decisões — você será avisado(a) se houver nova etapa.</p>
            @elseif ($review->status === \App\Models\SystematicReview::STATUS_SCREENING_DONE)
                <p>A triagem deste projeto foi encerrada. Obrigado pela participação!</p>
            @else
                <p>A triagem não está aberta para você agora. Aguarde o aviso do professor.</p>
            @endif
            <div class="tc-row" style="margin-top:.75rem">
                <a class="tc-btn gray" href="{{ route('systematic-review.my-terms', $screener) }}" target="_blank">Ver o termo que assinei</a>
                @if ($review->protocol_pdf_path)
                    <a class="tc-btn" href="{{ route('systematic-review.my-protocol', $screener) }}" target="_blank">Protocolo do projeto (PDF)</a>
                @endif
            </div>
        </div>
        <div class="tc-card" style="margin-top:1rem">
            <div class="tc-eyebrow">Seu papel e a sua dupla</div>
            <p style="margin-top:.35rem"><strong>{{ $screener->label ?: 'Triador(a)' }}</strong> neste projeto{{ $review->displayCode() ? ' (' . $review->displayCode() . ')' : '' }}.</p>
            @if ($partner ?? null)
                <p class="tc-meta" style="margin-top:.35rem">Dupla: <strong>{{ $partner->name }}</strong> ({{ $partner->label ?: 'triador(a)' }}) · <a href="mailto:{{ $partner->email }}" style="color:#0d9488;font-weight:600">{{ $partner->email }}</a></p>
                <p class="tc-meta" style="margin-top:.35rem">Combinem prazos e horários à vontade. Só não comentem nem comparem decisões da triagem ou da extração até o professor encerrar cada etapa: isso quebra o cegamento.</p>
            @endif
        </div>
        @include('filament.partials.sr-rules', ['screener' => $screener, 'open' => true])
        @include('filament.partials.sr-chat', ['chat' => $chat ?? null, 'openChat' => true])
        <div class="tc-card" style="margin-top:1rem">
            @include('filament.partials.stage-guide', ['key' => ($preparing ?? false) ? 'preparing' : 'waiting', 'review' => $review,
                'extraRefs' => array_merge(['cochrane', 'grade'], \App\Support\StageGuides::robRefs($review)), 'showProjectRefs' => true])
        </div>

    @else
        {{-- Modo triagem --}}
        <div
            x-data="{
                reasons: false, cur() { return Number($refs.rec?.dataset.id) || null },
                ck: {}, ckN: 0, ckItems: [], ckWarn: false,
                pass(d) {
                    if (!this.ckN) return true;
                    if (Object.keys(this.ck).length < this.ckN) {
                        this.ckWarn = true;
                        document.querySelector('.tc-check')?.scrollIntoView({ block: 'center', behavior: 'smooth' });
                        return false;
                    }
                    const v = Object.values(this.ck);
                    if (d === 'include' && v.includes('nao')) return confirm('Você respondeu NÃO em um critério do checklist, mas escolheu INCLUIR. Confira: tem certeza?');
                    if (d === 'include' && v.includes('incerto')) return confirm('Você respondeu NÃO DÁ PARA SABER em um critério. Nesse caso o indicado é TALVEZ. Incluir mesmo assim?');
                    if (d === 'exclude' && !v.includes('nao')) return confirm('Nenhum critério ficou NÃO. Só exclua se o resumo mostrar CLARAMENTE que o trabalho não serve; na dúvida, TALVEZ. Excluir mesmo assim?');
                    if (d === 'maybe' && v.every(x => x === 'sim')) return confirm('Todos os critérios ficaram SIM: o indicado é INCLUIR. Marcar Talvez mesmo assim?');
                    if (d === 'maybe' && v.includes('nao')) return confirm('Você respondeu NÃO em um critério: o indicado é EXCLUIR, com o código do primeiro critério que falhou. Talvez é só quando algum critério NÃO DÁ PARA SABER pelo resumo, e não quer dizer “é do tema”. A decisão é sua: marcar Talvez mesmo assim?');
                    return true;
                },
                sug() {
                    if (!this.ckN) return null;
                    for (let i = 0; i < this.ckN; i++) { if (this.ck[i] === 'nao') return { d: 'exclude', reason: this.ckItems[i].reason, q: this.ckItems[i].q }; }
                    const v = Object.values(this.ck);
                    if (v.includes('incerto')) return { d: 'maybe' };
                    if (v.length === this.ckN && v.every(x => x === 'sim')) return { d: 'include' };
                    return null;
                }
            }"
            x-on:keydown.window="
                if (['INPUT','TEXTAREA','SELECT'].includes($event.target.tagName)) return;
                const k = $event.key.toLowerCase();
                {{-- Sem atalho numérico para o motivo: a tecla 1 gravava o E1 (motivo específico) como se fosse 'fora do tema'. --}}
                if (k === 'escape') { reasons = false; return; }
                if (k === 'i') { if (pass('include')) $wire.decide('include', null, cur()); reasons = false; }
                else if (k === 'e') { if (pass('exclude')) reasons = true; }
                else if (k === 't' && @js(in_array('maybe', $allowed ?? [], true))) { if (pass('maybe')) $wire.decide('maybe', null, cur()); reasons = false; }
                else if (k === 'arrowright') { $wire.go(1); reasons = false; }
                else if (k === 'arrowleft') { $wire.go(-1); reasons = false; }
            "
            style="display:grid;gap:1rem"
        >
            <div class="tc-card">
                <div class="tc-between">
                    <div>
                        @if ($review->is_training)
                            <div class="tc-eyebrow">🧪 Treino · não conta para o seu projeto{{ $review->training_closes_at ? ' · fecha em ' . $review->training_closes_at->format('d/m') . ' às ' . $review->training_closes_at->format('H\hi') : '' }}</div>
                        @else
                            <div class="tc-eyebrow">{{ $review->displayCode() ? $review->displayCode() . ' · ' : '' }}{{ $review->stageLabel() }} @if ($review->inPilot()) · PILOTO DE CALIBRAÇÃO @endif</div>
                        @endif
                        <div style="font-weight:600">{{ \Illuminate\Support\Str::limit($review->title, 110) }}</div>
                    </div>
                    <div class="tc-row">
                        <label class="tc-row" style="font-size:.85rem"><input type="checkbox" wire:model.live="onlyPending"> só pendentes</label>
                        <button type="button" class="tc-btn gray" wire:click="back">Projetos</button>
                    </div>
                </div>
                @unless ($review->is_training)
                    <div style="margin-top:.6rem">@include('filament.partials.sr-journey', ['journey' => $journey, 'compact' => true])</div>
                @endunless
                @if ($stop)
                    {{-- Triagem priorizada (protocolo v1.1): o aluno para pela regra de parada, não ao ler tudo. --}}
                    <div class="tc-between" style="margin-top:.6rem;font-size:.85rem">
                        <span>{{ $stop['screened'] }} triados · {{ $stop['relevant'] }} relevantes · {{ $stop['streak'] }} irrelevantes seguidos</span>
                        <span>{{ $stop['total'] }} no total</span>
                    </div>
                    <div class="tc-bar" style="margin-top:.3rem"><div style="width: {{ min(100, round(100 * $stop['streak'] / max(1, $review->stoppingRule()['consecutive']))) }}%"></div></div>
                    <details style="margin-top:.6rem" @if ($stop['met']) open @endif>
                        <summary>🎯 Regra de parada do protocolo {{ $stop['met'] ? '— cumprida' : '' }}</summary>
                        <ul class="tc-stop">
                            @foreach ($stop['criteria'] as $c)
                                <li class="{{ $c['ok'] ? 'ok' : '' }}">{{ $c['ok'] ? '✅' : '⬜' }} {{ $c['text'] }}</li>
                            @endforeach
                        </ul>
                        <p class="tc-meta" style="margin-top:.4rem">O sistema aprende só com as <strong>suas</strong> decisões e mostra primeiro os trabalhos mais prováveis. Quem decide é sempre você. Na dúvida, marque Talvez.</p>
                    </details>
                    @if ($stop['met'])
                        <div class="tc-notice" style="margin-top:.75rem">
                            Regra de parada cumprida. Você pode continuar triando ou concluir.
                            <button type="button" class="tc-btn" style="margin-left:.5rem" wire:click="finish" wire:confirm="Concluir a sua triagem? Depois o seu colega faz uma checagem aleatória do que ninguém leu; se ela achar algo elegível, a triagem reabre.">Concluir minha triagem</button>
                        </div>
                    @endif
                @else
                    @if ($qc)
                        <div class="tc-notice" style="margin-top:.6rem">🔎 <strong>Checagem aleatória.</strong> Estes trabalhos foram sorteados entre os que nenhum dos dois leu. Decida cada um normalmente, sem pressa: é o controle de qualidade registrado no protocolo.</div>
                    @endif
                    <div class="tc-between" style="margin-top:.6rem;font-size:.85rem">
                        <span>{{ $progress['done'] }} de {{ $progress['total'] }} decididos</span>
                        <span>{{ $progress['total'] ? round(100 * $progress['done'] / $progress['total']) : 0 }}%</span>
                    </div>
                    <div class="tc-bar" style="margin-top:.3rem"><div style="width: {{ $progress['total'] ? round(100 * $progress['done'] / $progress['total']) : 0 }}%"></div></div>
                    @if (!$review->is_training && $review->inPilot() && ($goal = \App\Filament\Pages\TriagemCega::pilotGoal($progress['done'], $progress['total'], $review->pilotRoundStart())))
                        <div class="tc-meta" style="margin-top:.4rem">🎯 <strong>Meta {{ $goal['n'] }} de 2 do piloto{{ $review->pilotRound() > 1 ? ' (rodada ' . $review->pilotRound() . ' de calibração, amostra nova)' : '' }}:</strong> {{ $goal['done'] }} de {{ $goal['size'] }} · faltam {{ $goal['size'] - $goal['done'] }}{{ $goal['n'] === 1 ? ' (depois vem a meta 2, com os outros ' . ($progress['total'] - $goal['size']) . ')' : '' }}</div>
                    @endif
                @endif
                @include('filament.partials.sr-other-projects', ['others' => $others ?? collect()])
                @if (!$stop && $progress['total'] > 0 && $progress['done'] === $progress['total'])
                    <div class="tc-notice" style="margin-top:.75rem">
                        @if ($review->is_training)
                            ✅ Você decidiu todos os trabalhos. Os resultados, com o gabarito comentado, aparecem nesta página quando a sua dupla terminar ou quando o treino fechar.
                        @elseif ($review->inPilot())
                            ✅ Piloto concluído neste projeto. Aguarde o professor conferir a calibração e liberar o restante — você será avisado(a).
                        @else
                            Tudo decidido! Revise se quiser (desmarque "só pendentes") e conclua.
                            <button type="button" class="tc-btn" style="margin-left:.5rem" wire:click="finish" wire:confirm="Concluir a sua triagem? Depois disso só o professor pode reabrir.">Concluir minha triagem</button>
                        @endif
                    </div>
                @endif
            </div>

            <div class="tc-card">
                <details @if ($progress['done'] === 0) open @endif>
                    <summary>📘 Guia desta etapa: o que fazer, por quê, como evitar viés e o que estudar</summary>
                    <div style="margin-top:.6rem">
                        @include('filament.partials.stage-guide', [
                            'key' => $review->inPilot() ? 'pilot' : $review->stage,
                            'review' => $review,
                            'showProjectRefs' => true,
                        ])
                    </div>
                </details>
            </div>
            {{-- Regras do papel: abertas antes do primeiro voto; depois, a um clique. --}}
            <div style="margin-top:-1rem">@include('filament.partials.sr-rules', ['screener' => $screener, 'open' => $progress['done'] === 0])</div>

            @if ($record)
                {{-- data-id: o atalho de teclado decide o trabalho que está na tela, mesmo com a resposta anterior ainda a caminho. --}}
                <div class="tc-card" x-ref="rec" data-id="{{ $record->id }}">
                    {{-- Progresso junto do trabalho (o do topo some ao rolar). Conta o que o aluno já analisou, não a
                         posição na ordem embaralhada: ao pular os votados, a posição saltava (2 → 17) e parecia ordem errada. --}}
                    <div class="tc-prog">
                        <div class="tc-between">
                            <strong>📊 Analisados: {{ $counter['done'] }} de {{ $counter['size'] }}@if ($counter['meta']) <span style="font-weight:600;opacity:.75">· meta {{ $counter['meta'] }} de 2 do piloto</span>@endif</strong>
                            <span>faltam <strong>{{ $counter['size'] - $counter['done'] }}</strong></span>
                        </div>
                        <div class="tc-bar" style="margin-top:.35rem"><div style="width: {{ $counter['size'] ? round(100 * $counter['done'] / $counter['size']) : 0 }}%"></div></div>
                        <div class="tc-meta" style="margin-top:.35rem">
                            @if ($counter['decided'])
                                ✔ Este trabalho já tem o seu voto (marcado abaixo). Pode mudar, se quiser.
                            @else
                                Este é o <strong>trabalho {{ $counter['n'] }} de {{ $counter['size'] }}</strong>. A ordem é sorteada só para você e o sistema pula os que você já votou.
                            @endif
                        </div>
                    </div>
                    <div class="tc-between" style="justify-content:flex-end">
                        @if ($record->link())
                            <a href="{{ $record->link() }}" target="_blank" rel="noopener" style="font-size:.85rem;color:#0d9488;font-weight:600">
                                {{ $review->stage === 'full_text' ? 'Abrir o artigo (DOI/PubMed) ↗' : 'Ver na fonte ↗' }}
                            </a>
                        @endif
                    </div>
                    <div class="tc-title">{!! \App\Filament\Pages\TriagemCega::highlight($record->title, $green, $red) !!}</div>
                    <div class="tc-meta">{{ $record->citation() }}</div>
                    <div class="tc-abstract">{!! $record->abstract ? \App\Filament\Pages\TriagemCega::highlight($record->abstract, $green, $red) : '<em style="opacity:.6">Sem resumo disponível — decida pelo título ou abra a fonte.</em>' !!}</div>
                    @if ($record->keywords)
                        <div class="tc-meta" style="margin-top:.6rem"><strong>Palavras-chave:</strong> {!! \App\Filament\Pages\TriagemCega::highlight(\Illuminate\Support\Str::limit($record->keywords, 400), $green, $red) !!}</div>
                    @endif

                    {{-- Tradução de apoio, feita no navegador do aluno (tradutor embutido do Chrome, no próprio aparelho).
                         Não é gravada no banco nem exportada; o modelo de priorização usa só o original. --}}
                    <div class="tc-translate" wire:key="tr-{{ $record->id }}"
                         x-data="{
                            st: 'idle', t: '', a: '', id: {{ $record->id }},
                            src: @js($record->title), abs: @js($record->abstract),
                            ok: 'Translator' in self,
                            get auto() { try { return localStorage.getItem('tc_tr_auto') !== '0' } catch (e) { return true } },
                            set auto(v) { try { localStorage.setItem('tc_tr_auto', v ? '1' : '0') } catch (e) {} },
                            init() {
                                try { const c = JSON.parse(sessionStorage.getItem('tc_tr_' + this.id) || 'null'); if (c) { this.t = c.t; this.a = c.a; this.st = 'done'; return; } } catch (e) {}
                                if (this.ok && this.auto) this.go(false);
                            },
                            lang: '',
                            // Idioma do original: antes o tradutor partia sempre do inglês e estragava resumos em espanhol,
                            // alemão, francês ou já em português.
                            async detect() {
                                const txt = ((this.src || '') + ' ' + (this.abs || '')).slice(0, 2000);
                                try {
                                    if ('LanguageDetector' in self) {
                                        const d = await (window.__tcLd = window.__tcLd || LanguageDetector.create());
                                        const r = (await d.detect(txt))[0];
                                        if (r && r.confidence > 0.6) return r.detectedLanguage.split('-')[0];
                                    }
                                } catch (e) {}
                                const low = ' ' + txt.toLowerCase().replace(/[^a-zà-ÿ]+/g, ' ') + ' ';
                                const w = (list) => list.reduce((n, x) => n + low.split(' ' + x + ' ').length - 1, 0);
                                const score = { pt: w(['não', 'são', 'foram', 'uma', 'com', 'em', 'do', 'da']), es: w(['los', 'las', 'del', 'el', 'fueron', 'con', 'una']),
                                                en: w(['the', 'of', 'and', 'was', 'were', 'with']), de: w(['der', 'die', 'und', 'bei', 'mit', 'wurde']), fr: w(['les', 'des', 'et', 'dans', 'une', 'avec']) };
                                const best = Object.entries(score).sort((a, b) => b[1] - a[1])[0];
                                return best[1] > 2 ? best[0] : 'en';
                            },
                            async go(user = true) {
                                if (!this.ok) return;
                                this.st = 'loading';
                                try {
                                    this.lang = this.lang || await this.detect();
                                    if (this.lang === 'pt') { this.st = 'pt'; return; }
                                    const opt = { sourceLanguage: this.lang, targetLanguage: 'pt' };
                                    const av = await Translator.availability(opt);
                                    if (av === 'unavailable') { this.st = 'error'; return; }
                                    if (av !== 'available' && !user) { this.st = 'idle'; return; }
                                    window.__tcTr = window.__tcTr || {};
                                    window.__tcTr[this.lang] = window.__tcTr[this.lang] || Translator.create(opt);
                                    const tr = await window.__tcTr[this.lang];
                                    this.t = await tr.translate(this.src || '');
                                    this.a = this.abs ? await tr.translate(this.abs) : '';
                                    this.st = 'done';
                                    try { sessionStorage.setItem('tc_tr_' + this.id, JSON.stringify({ t: this.t, a: this.a })) } catch (e) {}
                                } catch (e) { if (window.__tcTr) delete window.__tcTr[this.lang]; this.st = 'error'; }
                            },
                            full() { return (this.src || '') + '\n\n' + (this.abs || ''); },
                            gt() { return 'https://translate.google.com/?sl=auto&tl=pt&op=translate&text=' + encodeURIComponent(this.full().slice(0, 4500)); },
                            dl() { return 'https://www.deepl.com/translator#auto/pt-BR/' + encodeURIComponent(this.full().slice(0, 1500)).replace(/%2F/g, '%5C%2F'); }
                         }">
                        <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin-top:.75rem">
                            <template x-if="ok && st !== 'done'">
                                <button type="button" class="tc-btn gray tc-gray" x-on:click="go(true)" :disabled="st === 'loading'">
                                    <span x-text="st === 'loading' ? 'Traduzindo…' : '🌐 Ver em português'"></span>
                                </button>
                            </template>
                            <template x-if="ok">
                                <label style="font-size:.8rem;opacity:.8;display:inline-flex;gap:.3rem;align-items:center">
                                    <input type="checkbox" :checked="auto" x-on:change="auto = $event.target.checked; if (auto && st !== 'done') go(true)"> traduzir sempre
                                </label>
                            </template>
                            <span style="font-size:.8rem;opacity:.85">Tradução melhor:</span>
                            <a :href="gt()" target="_blank" rel="noopener" style="font-size:.8rem;color:#0d9488;font-weight:600">Google Tradutor ↗</a>
                            <a :href="dl()" target="_blank" rel="noopener" style="font-size:.8rem;color:#0d9488;font-weight:600" title="O DeepL gratuito traduz até 1.500 caracteres">DeepL ↗</a>
                        </div>
                        <div x-show="st === 'pt'" style="font-size:.8rem;opacity:.8;margin-top:.4rem">Este trabalho já está em português.</div>
                        <div x-show="st === 'error'" style="font-size:.8rem;opacity:.8;margin-top:.4rem">O tradutor do navegador não traduz este idioma. Use o Google Tradutor ou o DeepL.</div>
                        <template x-if="st === 'done'">
                            <div class="tc-notice" style="margin-top:.6rem">
                                <div style="font-size:.75rem;opacity:.75;margin-bottom:.35rem">Tradução rápida do navegador, feita no próprio aparelho: erra termos técnicos. Para ler melhor, use o Google Tradutor ou o DeepL. Em caso de dúvida, vale o original acima (onde estão os destaques).</div>
                                <div style="font-weight:600" x-text="t"></div>
                                <div class="tc-abstract" style="margin-top:.4rem" x-show="a" x-text="a"></div>
                            </div>
                        </template>
                    </div>

                    @if (filled($review->screening_tips))
                        {{-- Ajuda de leitura igual para todos os registros e para os dois triadores (não há sugestão por artigo). --}}
                        <details class="tc-tips" open style="margin:.6rem 0;padding:.55rem .75rem;border-radius:.6rem;border:1px solid #f59e0b;background:rgba(245,158,11,.08)">
                            <summary style="font-weight:700;font-size:.9rem;cursor:pointer">⚠️ Atenção neste projeto <span style="font-weight:400;opacity:.75">: erros mais comuns. Quem decide é você.</span></summary>
                            <div style="white-space:pre-line;font-size:.85rem;margin-top:.35rem;line-height:1.45">{{ $review->screening_tips }}</div>
                        </details>
                    @endif

                    @if (!empty($checkItems))
                        {{-- Checklist PICO: o próprio aluno responde; a indicação sai das respostas DELE (não há sugestão da máquina). --}}
                        <div class="tc-check" wire:key="ck-{{ $record->id }}"
                             x-init="ckItems = @js($checkItems); ckN = ckItems.length; ck = @js((object) ($mine?->checklist ?? [])); ckWarn = false">
                            <div style="font-weight:700;font-size:.9rem">✅ Checklist do protocolo <span style="font-weight:400;opacity:.75">: responda pelo que o título/resumo mostra</span></div>
                            @foreach ($checkItems as $i => $item)
                                <div class="tc-check-row">
                                    <div>
                                        <div class="tc-check-q">{{ $i + 1 }}. {{ $item['q'] }}</div>
                                        @if ($item['h'])<div class="tc-check-h">{{ $item['h'] }}</div>@endif
                                    </div>
                                    <div class="tc-check-opts">
                                        @foreach (['sim' => 'Sim', 'nao' => 'Não', 'incerto' => 'Não dá para saber'] as $val => $label)
                                            <button type="button" class="tc-pill tc-pill-{{ $val }}" :class="{ 'on': ck[{{ $i }}] === @js($val) }"
                                                    x-on:click="ck = { ...ck, [{{ $i }}]: (ck[{{ $i }}] === @js($val) ? undefined : @js($val)) }; Object.keys(ck).forEach(k => ck[k] === undefined && delete ck[k]); if (Object.keys(ck).length === ckN) ckWarn = false; $wire.$set('checklist', ck, false); $wire.$set('checklistRecordId', {{ $record->id }}, false)">{{ $label }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                            <div class="tc-check-sug" x-show="ckWarn" x-cloak style="background:rgba(220,38,38,.12)">
                                ⚠️ Responda <strong>todas</strong> as perguntas antes de decidir. Se o resumo não mostra, marque "Não dá para saber".
                            </div>
                            <div class="tc-check-sug" x-show="sug()" x-cloak>
                                <template x-if="sug() && sug().d === 'exclude'">
                                    <div>👉 Pelas suas respostas: <strong>Excluir</strong><span x-show="sug().reason">, motivo <strong x-text="(sug().reason || '').split(' — ')[0]"></strong> (já marcado na lista)</span>. Confira se o título/resumo mostra isso CLARAMENTE; se não mostra, a resposta é "Não dá para saber".</div>
                                </template>
                                <template x-if="sug() && sug().d === 'maybe'">
                                    <div>👉 Pelas suas respostas: <strong>{{ in_array('maybe', $allowed, true) ? 'Talvez' : 'confira no texto completo' }}</strong>. O resumo não permite decidir; na dúvida, o trabalho segue para o texto completo.</div>
                                </template>
                                <template x-if="sug() && sug().d === 'include'">
                                    <div>👉 Pelas suas respostas: <strong>Incluir</strong>. Todos os critérios parecem atendidos.</div>
                                </template>
                            </div>
                            <div class="tc-check-h" style="margin-top:.4rem">A decisão é sua: o checklist só organiza a leitura. As respostas ficam guardadas para a reunião de calibração com o professor.</div>
                        </div>
                    @endif

                    @if ($mine)
                        <div class="tc-mine" style="margin-top:.9rem">
                            Sua decisão: <strong>{{ \App\Models\SystematicReviewRecord::DECISIONS[$mine->decision] }}</strong>
                            @if ($mine->reason) — {{ $mine->reason }} @endif
                            (pode mudar)
                        </div>
                    @endif

                    @if ($maybeRule)
                        <div class="tc-notice" style="margin-top:.9rem;font-size:.85rem">
                            <strong>Na dúvida, marque "Talvez".</strong> Nesta etapa, "talvez" leva o trabalho para o texto completo (regra do protocolo). Não exclua por suposição: exclua só quando o título/resumo mostrar claramente que o trabalho não atende aos critérios.
                        </div>
                    @elseif ($review->stage === 'full_text')
                        <div class="tc-notice" style="margin-top:.9rem;font-size:.85rem">
                            <strong>Texto completo:</strong> leia o artigo inteiro e decida <strong>Incluir</strong> ou <strong>Excluir</strong>. Para excluir, escolha um único código (E1, E2…). Se não conseguir o texto, use "Texto completo não obtido" e avise o professor.
                        </div>
                    @endif

                    <div style="margin-top:1rem">
                        <input type="text" class="tc-input" wire:model="note" placeholder="Dúvida ou observação para o professor (opcional; leve à reunião)" maxlength="1000">
                    </div>

                    {{-- Barra de decisão: no celular fica presa no rodapé da tela (não precisa rolar a cada trabalho). --}}
                    <div class="tc-actions">
                        <div class="tc-actions-prog">📊 {{ $counter['done'] }} de {{ $counter['size'] }} analisados · faltam {{ $counter['size'] - $counter['done'] }}@if ($counter['meta']) · meta {{ $counter['meta'] }}@endif</div>
                        <div x-show="reasons" x-cloak x-ref="reasons" class="tc-reasons"
                             x-effect="if (reasons) $nextTick(() => $el.scrollIntoView({ block: 'nearest', behavior: 'smooth' }))">
                            <div style="font-size:.8rem;font-weight:600">
                                Por que excluir? Escolha {{ $review->stage === 'full_text' ? 'UM código de exclusão' : 'o motivo' }} (vai para o fluxograma PRISMA).
                                <span style="display:block;font-weight:400;opacity:.85">Primeiro o tipo de publicação (revisão, congresso, duplicata); depois, o código da PRIMEIRA pergunta do checklist que falha. Sem relação nenhuma com o projeto: "Fora do tema".</span>
                            </div>
                            @php
                                // Tipo de publicação primeiro; o resto na ordem do protocolo (o código gravado não muda).
                                $isPubType = fn (string $r) => (bool) preg_match('/review|editorial|letter|commentary|conference|duplicate|not obtainable|fora do tema/iu', $r);
                                $orderedReasons = collect($reasons)->values()->sortBy(fn ($r, $i) => ($isPubType($r) ? 0 : 1000) + $i);
                            @endphp
                            @foreach ($orderedReasons as $reason)
                                @php
                                    [$code, $label] = preg_match('/^(E\d+)\s+—\s+(.*)$/us', $reason, $m) ? [$m[1], $m[2]] : [null, $reason];
                                    $pt = $review->exclusionReasonPt($reason) ?? \App\Filament\Pages\TriagemCega::reasonPt($label);
                                @endphp
                                <button type="button" class="tc-reason" :class="{ 'tc-sug': sug() && sug().reason === @js($reason) }" wire:click="decide('exclude', @js($reason), {{ $record->id }})" x-on:click="reasons = false" wire:loading.attr="disabled">
                                    @if ($pt)
                                        @if ($code)<strong>{{ $code }}</strong> — @endif<strong>{{ $pt }}</strong>
                                        <span style="display:block;font-size:.74rem;opacity:.7;margin-top:.15rem">{{ $label }}</span>
                                    @else
                                        @if ($code)<strong>{{ $code }}</strong> — @endif{{ $label }}
                                    @endif
                                </button>
                            @endforeach
                            <button type="button" class="tc-btn gray tc-gray" x-on:click="reasons = false">Cancelar <span class="tc-kbd">Esc</span></button>
                        </div>

                        <div class="tc-decide">
                            <button type="button" @class(['tc-btn inc', 'sel' => $mine?->decision === 'include']) x-on:click="reasons = false; if (pass('include')) $wire.decide('include', null, {{ $record->id }})" wire:loading.attr="disabled">✔ Incluir <span class="tc-kbd">I</span></button>
                            <button type="button" @class(['tc-btn exc', 'sel' => $mine?->decision === 'exclude']) x-on:click="if (reasons) { reasons = false } else if (pass('exclude')) { reasons = true }" :aria-expanded="reasons">✖ Excluir <span class="tc-kbd">E</span></button>
                            @if (in_array('maybe', $allowed, true))
                                <button type="button" @class(['tc-btn may', 'sel' => $mine?->decision === 'maybe']) x-on:click="reasons = false; if (pass('maybe')) $wire.decide('maybe', null, {{ $record->id }})" wire:loading.attr="disabled">? Talvez <span class="tc-kbd">T</span></button>
                            @endif
                        </div>
                        <div class="tc-nav">
                            <button type="button" class="tc-btn gray tc-gray" wire:click="go(-1)" x-on:click="reasons = false">← Anterior <span class="tc-kbd">←</span></button>
                            <button type="button" class="tc-btn gray tc-gray" wire:click="go(1)" x-on:click="reasons = false">Próximo → <span class="tc-kbd">→</span></button>
                        </div>
                    </div>
                </div>
            @else
                <div class="tc-card">Nenhum trabalho {{ $onlyPending ? 'pendente' : '' }} na lista.</div>
            @endif

            <div class="tc-card">
                <details>
                    <summary>Protocolo, critérios e instruções</summary>
                    <div class="tc-protocol" style="margin-top:.5rem">
                        <p style="white-space:pre-line">{{ $review->screener_instructions ?: \App\Models\SystematicReview::DEFAULT_INSTRUCTIONS }}</p>
                        <dl>
                            @foreach (['question', 'population', 'intervention', 'comparator', 'outcomes', 'study_designs', 'inclusion_criteria', 'exclusion_criteria'] as $key)
                                @if ($value = $review->protocolValue($key))
                                    <dt>{{ \App\Models\SystematicReview::PROTOCOL_FIELDS[$key] }}</dt>
                                    <dd>{{ $value }}</dd>
                                @endif
                            @endforeach
                        </dl>
                        <p style="margin-top:.75rem;opacity:.75">Atalhos: <strong>I</strong> incluir · <strong>E</strong> excluir (depois 1–9 para o motivo) · <strong>T</strong> talvez · <strong>← →</strong> navegar.</p>
                    </div>
                </details>
            </div>
            @include('filament.partials.sr-chat', ['chat' => $chat ?? null, 'openChat' => false])
        </div>
    @endif
</x-filament-panels::page>
