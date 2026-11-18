<div class="flex items-center gap-1 text-sm" aria-label="{{ __('app.common.language') }}">
    @if ($localizedUrls)
        @foreach ($locales as $code => $label)
            <a
                href="{{ $localizedUrls[$code] }}"
                class="rounded px-2 py-1 transition {{ $current === $code ? 'bg-ifa-green text-white' : 'text-ebony hover:bg-sacred-sand' }}"
            >
                {{ $label }}
            </a>
        @endforeach
    @else
        @foreach ($locales as $code => $label)
            <button
                type="button"
                wire:click="setLocale('{{ $code }}')"
                class="rounded px-2 py-1 transition {{ $current === $code ? 'bg-ifa-green text-white' : 'text-ebony hover:bg-sacred-sand' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    @endif
</div>
