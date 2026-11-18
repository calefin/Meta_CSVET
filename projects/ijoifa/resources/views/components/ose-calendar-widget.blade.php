@php
    $ose = \App\Support\OseCalendar::today();
    $yoruba = \App\Support\YorubaWeek::forDate(now());
@endphp

<div class="rounded-lg border border-orisa-earth/20 bg-white p-4">
    <p class="text-xs uppercase tracking-wide text-ebony/40">{{ now()->translatedFormat('l, d \d\e F') }}</p>
    <p class="text-[11px] text-ebony/50" lang="yo">{{ $yoruba['name'] }} &middot; {{ $yoruba['meaning'] }}</p>
    <p class="mt-1 font-display text-lg text-ifa-green" lang="yo">{{ $ose['name'] }}</p>
    <p class="text-sm text-ebony/60">Dia de {{ implode(', ', $ose['orisas']) }}</p>
    @if (isset($ose['sub_name']))
        <p class="mt-1 text-xs font-medium text-orun-blue" lang="yo">{{ $ose['sub_name'] }}</p>
    @endif
</div>
