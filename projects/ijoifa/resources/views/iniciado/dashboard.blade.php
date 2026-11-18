<x-layouts.iniciado title="Painel">
    <h1 class="font-display text-2xl text-orisa-earth">Painel do Iniciado</h1>
    <p class="mt-2 text-ebony/70">Bem-vindo, {{ auth()->user()->name }}.</p>

    <div class="mt-4 max-w-sm">
        <x-ose-calendar-widget />
    </div>

    <a href="{{ route('iniciado.messages') }}" class="mt-4 inline-block rounded bg-orisa-earth px-4 py-2 text-white hover:bg-orisa-earth/90">
        Mensagens
    </a>

    {{-- Telegram Bot --}}
    <div class="mt-8 rounded-lg border border-[#229ED9]/30 bg-white p-5 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#229ED9]/10">
                <svg viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5 text-[#229ED9]" aria-hidden="true">
                    <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.96 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                </svg>
            </div>
            <div>
                <h2 class="font-display text-lg text-ebony">Assistente da Casa no Telegram</h2>
                <p class="text-sm text-ebony/60">@ijoifa_bot</p>
            </div>
        </div>

        <p class="mt-3 text-sm text-ebony/70 leading-relaxed">
            Use o bot do Ìjọ Ifá Èjìogbè no Telegram para consultar o calendário litúrgico, ver os próximos eventos e entrar em contato diretamente com o Babalaô.
        </p>

        <div class="mt-4 space-y-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-ebony/40">Comandos disponíveis</p>
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ([
                    ['/hoje',     'Semana Yorùbá e Ọ̀sẹ̀ de hoje'],
                    ['/ciclo',    'Posição no ciclo de 17 dias'],
                    ['/agenda',   'Próximos eventos da casa'],
                    ['/consulta', 'Link para solicitar consulta'],
                    ['/ajuda',    'Lista todos os comandos'],
                ] as [$cmd, $desc])
                    <div class="flex items-start gap-2 rounded-md bg-sacred-sand/60 px-3 py-2">
                        <code class="shrink-0 rounded bg-[#229ED9]/10 px-1.5 py-0.5 text-xs font-mono text-[#229ED9]">{{ $cmd }}</code>
                        <span class="text-xs text-ebony/70">{{ $desc }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-5">
            <x-telegram-button label="Abrir @ijoifa_bot" />
        </div>
    </div>

    <div class="mt-6">
        <livewire:shared.events-board />
    </div>

    <div class="mt-6">
        <livewire:shared.announcements-list />
    </div>
</x-layouts.iniciado>
