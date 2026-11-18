<x-filament-panels::page>
    {{-- Página custom do Filament: estilos próprios (sem Tailwind do app). --}}
    <style>
        .ex-card { border: 1px solid rgba(148,163,184,.35); border-radius: 1rem; padding: 1rem 1.25rem; background: rgba(255,255,255,.75); }
        .dark .ex-card { background: rgba(15,23,42,.6); }
        .ex-eyebrow { font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #0d9488; }
        .ex-title { font-size: 1.05rem; font-weight: 700; margin: .2rem 0 .4rem; line-height: 1.35; overflow-wrap: anywhere; }
        .ex-meta { font-size: .85rem; opacity: .8; overflow-wrap: anywhere; }
        .ex-btn { display: inline-flex; align-items: center; justify-content: center; gap: .35rem; padding: .55rem 1rem; min-height: 2.6rem; border-radius: .6rem; font-weight: 600; font-size: .9rem; border: 1px solid transparent; cursor: pointer; background: #0d9488; color: #fff; }
        .ex-btn.gray { background: transparent; color: inherit; border-color: rgba(148,163,184,.5); }
        .ex-list { display: grid; gap: .6rem; }
        .ex-study { display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; }
        .ex-badge { font-size: .75rem; font-weight: 700; padding: .15rem .55rem; border-radius: 999px; background: rgba(148,163,184,.2); }
        .ex-badge.ok { background: rgba(5,150,105,.15); color: #047857; }
        .ex-badge.draft { background: rgba(245,158,11,.15); color: #b45309; }
        .ex-bar { height: .5rem; border-radius: 999px; background: rgba(148,163,184,.25); overflow: hidden; margin-top: .3rem; }
        .ex-bar > div { height: 100%; background: #0d9488; }
        .ex-actions { display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; gap: .5rem; margin-top: 1rem; }
        @media (max-width: 640px) {
            .ex-card { padding: .85rem .9rem; }
            .ex-actions { position: sticky; bottom: 0; z-index: 20; background: rgba(255,255,255,.97); padding: .6rem .2rem calc(.6rem + env(safe-area-inset-bottom)); border-top: 1px solid rgba(148,163,184,.35); }
            .dark .ex-actions { background: rgba(15,23,42,.97); }
        }
        details summary { cursor: pointer; font-weight: 600; }
    </style>

    @if ($mode === 'list')
        @if ($assignments->isEmpty())
            <p style="opacity:.75">Nenhum projeto em extração para você ainda.</p>
        @else
            <div class="ex-list">
                @foreach ($assignments as $a)
                    <div class="ex-card">
                        <div class="ex-eyebrow">{{ $a->systematicReview->displayCode() ?? $a->systematicReview->protocol_code }} · extração e risco de viés</div>
                        <div class="ex-title">{{ $a->systematicReview->title }}</div>
                        <button type="button" class="ex-btn" wire:click="choose({{ $a->id }})">Abrir</button>
                    </div>
                @endforeach
            </div>
        @endif
    @else
        <div style="display:grid;gap:1rem">
            <div class="ex-card">
                <div class="ex-eyebrow">{{ $review->inExtractionPilot() ? 'Piloto de extração' : 'Extração e risco de viés' }} · em dupla e às cegas</div>
                <div class="ex-title">{{ \Illuminate\Support\Str::limit($review->title, 120) }}</div>
                <div class="ex-meta">{{ $done }} de {{ $studies->count() }} estudo(s) enviados por você</div>
                <div class="ex-bar"><div style="width: {{ $studies->count() ? round(100 * $done / $studies->count()) : 0 }}%"></div></div>
                <div style="margin-top:.7rem"><button type="button" class="ex-btn gray" wire:click="back">← {{ $mode === 'form' ? 'Lista de estudos' : 'Projetos' }}</button></div>
            </div>

            @if ($mode === 'studies')
                <div class="ex-card">
                    <details @if ($done === 0) open @endif>
                        <summary>📘 Guia desta etapa: o que fazer, por quê, como evitar viés e o que estudar</summary>
                        <div style="margin-top:.6rem">
                            @include('filament.partials.stage-guide', ['key' => 'extraction', 'review' => $review,
                                'extraRefs' => \App\Support\StageGuides::robRefs($review), 'showProjectRefs' => true])
                        </div>
                    </details>
                </div>
                <div class="ex-list">
                    @foreach ($studies as $s)
                        @php $e = $mine[$s->id] ?? null; @endphp
                        <div class="ex-card ex-study">
                            <div>
                                <div class="ex-eyebrow">{{ $s->code() }} @if ($s->pilot) · piloto @endif</div>
                                <div style="font-weight:700">{{ $s->label }}</div>
                            </div>
                            <div style="display:flex;gap:.5rem;align-items:center">
                                @if ($e?->isSubmitted())<span class="ex-badge ok">enviado</span>
                                @elseif ($e)<span class="ex-badge draft">rascunho</span>
                                @else<span class="ex-badge">a fazer</span>@endif
                                <button type="button" class="ex-btn" wire:click="open({{ $s->id }})">{{ $e?->isSubmitted() ? 'Ver' : 'Extrair' }}</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="ex-card">
                    <div class="ex-eyebrow">{{ $study->code() }}</div>
                    <div class="ex-title">{{ $study->label }}</div>
                    @foreach ($records as $r)
                        <div class="ex-meta" style="margin-top:.3rem">{{ $r->title }} — {{ $r->citation() }}
                            @if ($r->link()) · <a href="{{ $r->link() }}" target="_blank" rel="noopener" style="color:#0d9488;font-weight:600">abrir o artigo ↗</a>@endif
                        </div>
                    @endforeach
                    @if ($submitted)
                        <p style="margin-top:.6rem;font-size:.85rem">✅ Você já enviou este estudo. Para corrigir, peça ao professor para reabrir.</p>
                    @endif
                </div>

                <form wire:submit="saveDraft">
                    {{ $this->form }}

                    @unless ($submitted)
                        <div class="ex-actions">
                            <button type="submit" class="ex-btn gray">Salvar rascunho</button>
                            <button type="button" class="ex-btn" wire:click="submit" wire:confirm="Enviar este estudo? Depois do envio só o professor pode reabrir.">Enviar estudo</button>
                        </div>
                    @endunless
                </form>
            @endif
        </div>
    @endif
</x-filament-panels::page>
