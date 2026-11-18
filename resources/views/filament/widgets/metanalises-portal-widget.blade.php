<x-filament-widgets::widget>
    {{-- Estilos inline: o tema do portal não compila classes Tailwind novas. --}}
    <div style="border:1px solid rgba(13,148,136,.35);background:rgba(13,148,136,.06);border-radius:.85rem;padding:1rem 1.1rem">
        <div style="font-weight:700;font-size:1rem">🔬 Programa de Revisões Sistemáticas e Metanálises</div>
        <div style="font-size:.85rem;opacity:.8;margin-top:.15rem">Seus projetos: em que etapa cada um está (verde = feito, azul = agora), o que você faz e a conversa com a dupla e o professor (botão 💬 Conversa).</div>
        <div style="display:flex;flex-direction:column;gap:.6rem;margin-top:.8rem">
            @foreach ($this->getProjects() as $p)
                <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.6rem;padding:.7rem .85rem;border-radius:.65rem;background:rgba(127,127,127,.07);border:1px solid {{ $p['urgent'] ? 'rgba(217,119,6,.55)' : 'rgba(100,116,139,.25)' }}">
                    <div style="min-width:0;flex:1 1 15rem">
                        <div style="font-weight:700;font-size:.9rem">{{ $p['code'] }} · {{ $p['role'] }}
                            <span style="font-weight:600;font-size:.75rem;margin-left:.35rem;padding:.1rem .45rem;border-radius:999px;{{ $p['signed'] ? 'background:rgba(5,150,105,.12);color:#047857' : 'background:rgba(217,119,6,.15);color:#b45309' }}">{{ $p['signed'] ? '✓ termo assinado' : '⏳ termo pendente' }}</span>
                        </div>
                        <div style="margin-top:.35rem">@include('filament.partials.sr-journey', ['journey' => $p['journey'], 'you' => $p['action']])</div>
                        <div style="font-size:.78rem;opacity:.75;margin-top:.2rem">
                            @if ($p['signed'])
                                💬 Conversa com a dupla e o professor: {{ $p['last'] ? 'última mensagem de ' . $p['last'] : 'nenhuma mensagem ainda' }}
                            @else
                                💬 A conversa do projeto abre depois que você assinar o termo.
                            @endif
                        </div>
                    </div>
                    <div style="display:flex;flex-wrap:wrap;gap:.4rem">
                        @if ($p['chatUrl'])
                            <a href="{{ $p['chatUrl'] }}" style="display:inline-flex;align-items:center;gap:.35rem;border:1px solid rgba(13,148,136,.55);color:#0d9488;font-weight:600;font-size:.85rem;padding:.5rem .9rem;border-radius:.5rem;text-decoration:none;white-space:nowrap">
                                💬 Conversa
                                @if ($p['unread'] > 0)<span style="padding:0 .4rem;border-radius:999px;background:#d97706;color:#fff;font-size:.72rem">{{ $p['unread'] }} {{ $p['unread'] === 1 ? 'nova' : 'novas' }}</span>@endif
                            </a>
                        @endif
                        <a href="{{ $p['url'] }}" style="display:inline-block;background:#0d9488;color:#fff;font-weight:600;font-size:.85rem;padding:.5rem .9rem;border-radius:.5rem;text-decoration:none;white-space:nowrap">{{ $p['button'] }}</a>
                    </div>
                </div>
            @endforeach
        </div>
        @if ($training = $this->getTraining())
            <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.6rem;margin-top:.8rem;padding:.7rem .85rem;border-radius:.65rem;border:1px dashed {{ $training['urgent'] ? 'rgba(217,119,6,.7)' : 'rgba(100,116,139,.45)' }}">
                <div style="min-width:0;flex:1 1 15rem">
                    <div style="font-weight:700;font-size:.9rem">🧪 Treino da triagem <span style="font-weight:600;font-size:.75rem;opacity:.75">· não conta para o seu projeto</span></div>
                    <div style="font-size:.8rem;opacity:.8;margin-top:.15rem">{{ $training['text'] }}</div>
                </div>
                <a href="{{ $training['url'] }}" style="display:inline-block;background:#0d9488;color:#fff;font-weight:600;font-size:.85rem;padding:.5rem .9rem;border-radius:.5rem;text-decoration:none;white-space:nowrap">{{ $training['button'] }}</a>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
