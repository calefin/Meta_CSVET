{{-- Aviso dentro de um projeto: o aluno também tem OUTRO projeto com algo a fazer (não é tarefa repetida). --}}
@if ($others->isNotEmpty())
    <div class="tc-notice" style="margin-top:.6rem">
        <strong>Você também tem {{ $others->count() === 1 ? 'outro projeto' : $others->count() . ' outros projetos' }} para fazer</strong>
        (é outro tema, com critérios e trabalhos próprios, não é repetição):
        @foreach ($others as $o)
            <span style="display:inline-flex;align-items:center;gap:.4rem;margin:.3rem .5rem 0 0">
                <strong>{{ $o['code'] }}</strong>
                <span>·
                    @if (!$o['signed'])
                        falta assinar o termo
                    @elseif ($o['goal'] ?? null)
                        meta {{ $o['goal']['n'] }}: faltam {{ $o['goal']['size'] - $o['goal']['done'] }} de {{ $o['goal']['size'] }}
                    @else
                        faltam {{ $o['total'] - $o['done'] }} de {{ $o['total'] }}
                    @endif
                </span>
                <button type="button" class="tc-btn" style="padding:.3rem .7rem;font-size:.8rem" wire:click="choose({{ $o['screener']->id }})">Abrir</button>
            </span>
        @endforeach
    </div>
@endif
