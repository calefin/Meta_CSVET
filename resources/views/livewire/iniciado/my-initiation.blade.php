<div>
    <h1 class="font-display text-2xl text-orisa-earth">Minha iniciação</h1>

    @if ($initiation)
        <div class="mt-4 rounded-lg border border-orisa-earth/20 bg-white p-6">
            <p class="text-sm"><strong>Odù:</strong> <span lang="yo">{{ $initiation->odu->name }}</span></p>
            <p class="mt-1 text-sm"><strong>Data:</strong> {{ $initiation->initiation_date->format('d/m/Y') }}</p>
            @if ($initiation->notes)
                <p class="mt-2 text-sm text-ebony/70">{{ $initiation->notes }}</p>
            @endif

            @if ($initiation->dayRecords->isNotEmpty())
                <h3 class="mt-4 font-medium">Odù dos ebós e demais dias</h3>
                <ul class="mt-1 space-y-1 text-sm">
                    @foreach ($initiation->dayRecords as $day)
                        <li><strong>{{ $day->day_label }}</strong> — <span lang="yo">{{ $day->odu->name }}</span></li>
                    @endforeach
                </ul>
            @endif

            @if ($initiation->ewos->isNotEmpty())
                <h3 class="mt-4 font-medium">Ewó</h3>
                <ul class="mt-1 list-inside list-disc space-y-1 text-sm">
                    @foreach ($initiation->ewos as $ewo)
                        <li>{{ $ewo->description }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @else
        <p class="mt-4 text-sm text-ebony/60">Seus dados de iniciação ainda não foram registrados pela administração.</p>
    @endif

    @if ($orisaRecords->isNotEmpty())
        <div class="mt-6 rounded-lg border border-orisa-earth/20 bg-white p-6">
            <h2 class="font-display text-lg text-orisa-earth">Outros Orisás feitos</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @foreach ($orisaRecords as $record)
                    <li class="rounded border border-orisa-earth/10 p-2">
                        <strong>{{ $record->orisa_name }}</strong>
                        @if ($record->spiritual_name) (Orúkọ: <span class="italic font-bold">{{ $record->spiritual_name }}</span>) @endif
                        — {{ $record->performed_at->format('d/m/Y') }}
                        @if ($record->odu) — <span lang="yo">{{ $record->odu->name }}</span> @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
