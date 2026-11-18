<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-3">
        <x-filament::section class="md:col-span-2">
            <x-slot name="heading">Revisão ligada ao pós-doc</x-slot>
            @if ($review)
                <div class="text-sm space-y-1">
                    <div><strong>{{ $review['code'] }}</strong> · {{ $review['status'] }} · {{ $review['records'] }} registros</div>
                    @if ($project->registration_doi)
                        <div>Registro no OSF: <a class="text-primary-600 underline" href="https://doi.org/{{ $project->registration_doi }}" target="_blank" rel="noopener">{{ $project->registration_doi }}</a> (sob embargo)</div>
                    @endif
                    @foreach ($review['screeners'] as $s)
                        <div>{{ $s['name'] }}: {{ $s['progress']['done'] ?? 0 }}/{{ $s['progress']['total'] ?? 0 }}</div>
                    @endforeach
                    @if ($review['rounds'])
                        <div class="text-gray-500">{{ implode(' · ', $review['rounds']) }}</div>
                    @endif
                    <div><a class="text-primary-600 underline" href="{{ $review['url'] }}">Abrir a revisão</a></div>
                </div>
            @else
                <p class="text-sm text-gray-500">Nenhuma revisão do programa com o código {{ $project->protocol_code ?: '—' }}.</p>
            @endif
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Formalização</x-slot>
            <div class="text-3xl font-bold">{{ $checkDone }}/{{ $checkTotal }}</div>
            <div class="text-sm text-gray-500">passos feitos · {{ \App\Models\PostdocProject::STATUS[$project->status] ?? $project->status }}</div>
            @if ($project->starts_at)
                <div class="text-sm mt-2">{{ $project->starts_at->format('d/m/Y') }} a {{ $project->ends_at?->format('d/m/Y') ?? '—' }}</div>
            @endif
        </x-filament::section>
    </div>

    <form wire:submit="save" class="mt-6">
        {{ $this->form }}
        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit" size="lg">Salvar</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
