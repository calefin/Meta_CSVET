<x-filament-widgets::widget>
    <div>
        <h3 class="text-base font-bold text-gray-200 mb-3">🚀 Ações Rápidas</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            @foreach ($this->getActions() as $action)
                @php
                    $classes = match ($action['color']) {
                        'primary' => 'border-teal-900/50 bg-teal-950/20 hover:bg-teal-950/40 text-teal-400',
                        'info' => 'border-sky-900/50 bg-sky-950/20 hover:bg-sky-950/40 text-sky-400',
                        'warning' => 'border-amber-900/50 bg-amber-950/20 hover:bg-amber-950/40 text-amber-400',
                        default => 'border-slate-700/50 bg-slate-800/20 hover:bg-slate-800/40 text-slate-300',
                    };
                @endphp
                <a href="{{ $action['url'] }}"
                   class="flex flex-col gap-2 p-4 rounded-xl border transition-all shadow-sm {{ $classes }}">
                    <x-filament::icon :icon="$action['icon']" class="w-6 h-6" />
                    <span class="text-sm font-semibold">{{ $action['label'] }}</span>
                    <span class="text-xs text-gray-400">{{ $action['description'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
