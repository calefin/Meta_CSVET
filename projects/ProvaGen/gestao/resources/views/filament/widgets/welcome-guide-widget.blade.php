<x-filament-widgets::widget>
    <div class="p-6 rounded-xl border border-teal-900/50 bg-teal-950/20 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-start gap-4">
            <div class="p-3 bg-teal-900/40 text-teal-400 rounded-lg flex-shrink-0">
                <x-filament::icon icon="heroicon-o-light-bulb" class="w-6 h-6" />
            </div>
            <div>
                <h3 class="text-base font-bold text-teal-400">👋 Primeiro acesso? Veja como funciona a plataforma</h3>
                <p class="text-sm text-gray-300 mt-1">
                    Um guia rápido mostrando onde enviar cada documento, como funcionam as correções e onde
                    acompanhar seus prazos. Leva 2 minutos e fica sempre disponível no menu "Minha Conta".
                </p>
            </div>
        </div>
        <div class="flex-shrink-0 w-full md:w-auto flex items-center gap-2">
            <button type="button" wire:click="dismiss"
                    class="px-3 py-2.5 text-sm font-medium text-gray-400 hover:text-gray-200 transition">
                Dispensar
            </button>
            <a href="{{ $this->getGuideUrl() }}"
               class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-semibold text-white bg-teal-600 hover:bg-teal-500 rounded-lg transition-all shadow-md w-full md:w-auto">
                Ver Guia Rápido
            </a>
        </div>
    </div>
</x-filament-widgets::widget>
