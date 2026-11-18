<x-layouts.consulente :title="__('app.nav.dashboard')">
    <h1 class="font-display text-2xl text-orun-blue">{{ __('app.nav.dashboard') }}</h1>
    <p class="mt-2 text-ebony/70">{{ auth()->user()->name }}</p>

    <div class="mt-6 flex flex-wrap gap-4">
        <a href="{{ route('consulente.consultations') }}" class="inline-block rounded bg-orun-blue px-4 py-2 text-white hover:bg-orun-blue/90">
            Ver minhas consultas
        </a>
        <a href="{{ route('consulente.appointments') }}" class="inline-block rounded bg-orisa-earth px-4 py-2 text-white hover:bg-orisa-earth/90">
            Meus agendamentos
        </a>
        <a href="{{ route('consulente.messages') }}" class="inline-block rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
            Mensagens
        </a>
    </div>

    <div class="mt-6">
        <livewire:shared.events-board />
    </div>

    <div class="mt-6">
        <livewire:shared.announcements-list />
    </div>
</x-layouts.consulente>
