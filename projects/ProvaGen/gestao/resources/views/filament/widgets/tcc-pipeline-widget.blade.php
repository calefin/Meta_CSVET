<x-filament-widgets::widget>
    <div class="p-6 rounded-xl border border-white/10 bg-white/[0.02] dark:bg-slate-900/40">
        <h3 class="text-base font-bold text-gray-200 mb-1">🧭 Pipeline do TCC</h3>
        <p class="text-xs text-gray-500 mb-5">Acompanhe o andamento das etapas de produção do seu TCC.</p>

        @php $steps = $this->getSteps(); @endphp
        <div class="flex items-start">
            @foreach ($steps as $index => $step)
                @php
                    $circleClasses = match ($step['state']) {
                        'done' => 'bg-teal-600 text-white border-teal-600',
                        'current' => 'bg-amber-500 text-white border-amber-500 ring-4 ring-amber-500/20',
                        default => 'bg-transparent text-gray-500 border-gray-600',
                    };
                    $labelClasses = match ($step['state']) {
                        'done' => 'text-teal-400',
                        'current' => 'text-amber-400',
                        default => 'text-gray-500',
                    };
                    $lineClasses = ($steps[$index]['state'] ?? 'locked') === 'done' ? 'bg-teal-600' : 'bg-gray-700';
                @endphp
                <div class="flex-1 flex flex-col items-center text-center {{ $index === 0 ? '' : 'relative' }}">
                    @if ($index > 0)
                        <div class="absolute top-4 right-1/2 w-full h-0.5 {{ $lineClasses }}" style="width: 100%; left: -50%;"></div>
                    @endif
                    <div class="relative z-10 w-8 h-8 rounded-full border-2 flex items-center justify-center font-bold text-sm {{ $circleClasses }}">
                        @if ($step['state'] === 'done')
                            <x-filament::icon icon="heroicon-o-check" class="w-4 h-4" />
                        @else
                            {{ $index + 1 }}
                        @endif
                    </div>
                    <span class="mt-2 text-xs font-semibold {{ $labelClasses }}">{{ $step['label'] }}</span>
                    <span class="text-[11px] text-gray-500">{{ $step['caption'] }}</span>
                </div>
            @endforeach
        </div>

        @if (collect($steps)->contains('state', 'locked') && !collect($steps)->contains('state', 'current'))
            <div class="mt-5 text-center">
                <a href="{{ $this->getCreateUrl() }}" class="text-sm font-semibold text-teal-400 hover:text-teal-300">
                    Continuar para a próxima etapa &rarr;
                </a>
            </div>
        @endif

        @php $writingSegments = ($steps[1]['state'] ?? null) === 'done' ? $this->getWritingSegments() : []; @endphp
        @if (!empty($writingSegments))
            <div class="mt-6 pt-5 border-t border-white/10">
                <h4 class="text-xs font-bold text-gray-300 uppercase tracking-wide mb-1">Etapas de Redação</h4>
                <p class="text-[11px] text-gray-500 mb-4">
                    Envie estas 5 etapas na ordem que preferir — o orientador corrige cada uma independentemente.
                    Assim que todas as obrigatórias forem aprovadas, você poderá solicitar a versão final do TCC.
                </p>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    @foreach ($writingSegments as $segment)
                        @php
                            $badgeClasses = match ($segment['state']) {
                                'done' => 'bg-teal-600 text-white border-teal-600',
                                'pending' => 'bg-amber-500 text-white border-amber-500',
                                default => 'bg-transparent text-gray-500 border-gray-600',
                            };
                            $captionClasses = match ($segment['state']) {
                                'done' => 'text-teal-400',
                                'pending' => 'text-amber-400',
                                default => 'text-gray-500',
                            };
                        @endphp
                        <div class="flex flex-col items-center text-center gap-1.5 p-2 rounded-lg bg-white/[0.02]">
                            <div class="w-7 h-7 rounded-full border-2 flex items-center justify-center {{ $badgeClasses }}">
                                @if ($segment['state'] === 'done')
                                    <x-filament::icon icon="heroicon-o-check" class="w-3.5 h-3.5" />
                                @else
                                    <x-filament::icon icon="heroicon-o-minus" class="w-3.5 h-3.5" />
                                @endif
                            </div>
                            <span class="text-[11px] font-semibold text-gray-300">
                                {{ $segment['label'] }}@if ($segment['optional'])<span class="text-gray-500"> (opcional)</span>@endif
                            </span>
                            <span class="text-[10px] {{ $captionClasses }}">{{ $segment['caption'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
