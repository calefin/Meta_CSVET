<div x-data="{ open: false }">
    @if ($items->isNotEmpty())
        <button type="button" @click="open = !open" class="flex w-full items-center justify-between text-left">
            <h2 class="font-display text-lg text-ifa-green">
                Mural de eventos
                <span class="ml-1 text-xs font-normal text-ebony/40">({{ $items->count() }})</span>
            </h2>
            <span class="text-xs text-ifa-green" x-text="open ? 'ocultar ▲' : 'mostrar ▼'"></span>
        </button>
        <div x-show="open" x-cloak class="mt-2 space-y-2">
            @php
                $typeStyles = [
                    'orun' => 'border-l-4 border-emerald-500',
                    'isan' => 'border-l-4 border-blue-500',
                    'itadogun' => 'border-l-4 border-amber-500',
                    'manual' => 'border-l-4 border-orisa-earth/40',
                ];
            @endphp
            @foreach ($items as $item)
                <div class="rounded border border-orisa-earth/20 bg-white p-3 text-sm {{ $typeStyles[$item['type']] }}" wire:key="evt-{{ $item['type'] }}-{{ $item['date']->toDateString() }}-{{ $loop->index }}">
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-medium" @if($item['type'] !== 'manual') lang="yo" @endif>{{ $item['label'] }}</p>
                        <span class="whitespace-nowrap text-xs text-ebony/50">
                            {{ $item['date']->format('d/m/Y') }}
                            @if ($item['ends_on'] && ! $item['ends_on']->equalTo($item['date']))
                                a {{ $item['ends_on']->format('d/m/Y') }}
                            @endif
                        </span>
                    </div>
                    @if ($item['description'])
                        <p class="mt-1 text-ebony/70">{{ $item['description'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
