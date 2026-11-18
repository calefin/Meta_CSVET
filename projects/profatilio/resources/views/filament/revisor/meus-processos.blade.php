<x-filament-panels::page>
    {{-- Página custom do Filament não carrega o Tailwind do app: estilos próprios. --}}
    <style>
        .rv-list { display: grid; gap: 1rem; }
        .rv-card { border: 1px solid rgba(148,163,184,.35); border-radius: 1rem; padding: 1rem 1.25rem; background: rgba(255,255,255,.7); }
        .dark .rv-card { background: rgba(15,23,42,.6); }
        .rv-eyebrow { font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #0d9488; }
        .rv-title { font-size: 1.05rem; font-weight: 700; margin: .2rem 0 .4rem; }
        .rv-meta { font-size: .85rem; opacity: .85; display: flex; flex-wrap: wrap; gap: .25rem 1rem; }
        .rv-turn { margin-top: .6rem; font-size: .9rem; padding: .5rem .75rem; border-radius: .6rem; background: #fffbeb; color: #78350f; }
        .rv-turn.me { background: #ecfdf5; color: #065f46; font-weight: 700; }
        .rv-btn { display: inline-block; margin-top: .75rem; padding: .5rem 1rem; border-radius: .6rem; background: #0d9488; color: #fff; font-weight: 600; font-size: .9rem; text-decoration: none; }
        .rv-pend { color: #b91c1c; font-weight: 600; }
        .rv-empty { opacity: .75; }
    </style>

    @if ($items->isEmpty())
        <p class="rv-empty">Nenhum processo vinculado à sua conta ainda. Os processos aparecem aqui quando o Prof. Atilio inclui o seu e-mail.</p>
    @else
        <div class="rv-list">
            @foreach ($items as $me)
                @php
                    $review = $me->articleReview;
                    $active = $review->reviewers->firstWhere('status', \App\Models\ArticleReviewer::STATUS_IN_REVIEW);
                @endphp
                <div class="rv-card">
                    <div class="rv-eyebrow">{{ $review->workTypeLabel() }} · {{ $review->statusLabel() }}</div>
                    <div class="rv-title">{{ $review->title }}</div>
                    <div class="rv-meta">
                        <span>Sua situação: <strong>{{ $me->statusLabel() }}</strong></span>
                        @if ($review->journal)<span>Revista: {{ $review->journal }}</span>@endif
                        <span>Ficha: @if ($me->isProfileComplete()) confirmada @else <span class="rv-pend">pendente</span> @endif</span>
                    </div>
                    @if ($active)
                        <div @class(['rv-turn', 'me' => $active->id === $me->id])>
                            {{ $active->id === $me->id ? 'É a sua vez! Prazo: ' . $active->due_at?->format('d/m/Y') : 'Agora está com: ' . $active->name }}
                        </div>
                    @endif
                    <a class="rv-btn" href="{{ route('article-review.account.show', ['articleReviewer' => $me->id]) }}">Abrir processo</a>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
