<x-filament-panels::page>
    <div class="space-y-5">
        <style>
            .email-composer-bar {
                background: linear-gradient(135deg, rgba(13,148,136,0.08) 0%, rgba(2,132,199,0.08) 100%);
                border: 1px solid rgba(13,148,136,0.2);
                border-radius: 0.75rem;
                padding: 1rem 1.5rem;
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }
        </style>

        <div class="email-composer-bar">
            <span class="text-2xl">✉️</span>
            <div>
                <p class="text-sm font-bold text-teal-300">Compositor de E-mail</p>
                <p class="text-xs text-slate-400 font-light leading-relaxed">
                    Os e-mails são enviados em fila em segundo plano para evitar timeout.
                    O link do bot do Telegram é adicionado automaticamente no rodapé de todas as mensagens.
                </p>
            </div>
        </div>

        <form wire:submit.prevent class="space-y-5">
            {{ $this->form }}
        </form>
    </div>
</x-filament-panels::page>
