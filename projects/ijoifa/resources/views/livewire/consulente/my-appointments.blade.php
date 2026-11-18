<div>
    <h1 class="font-display text-2xl text-orun-blue">Meus agendamentos</h1>

    <div class="mt-6 space-y-3">
        @forelse ($appointments as $appointment)
            <div class="rounded-lg border border-orisa-earth/20 bg-white p-4" wire:key="appt-{{ $appointment->id }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-medium">{{ $appointment->scheduled_at->format('d/m/Y H:i') }}</span>
                    <span class="rounded-full px-3 py-1 text-xs font-medium
                        @class([
                            'bg-sacred-sand text-ebony/70' => $appointment->status === 'pending',
                            'bg-orun-blue/10 text-orun-blue' => $appointment->status === 'confirmed',
                            'bg-success/10 text-success' => $appointment->status === 'completed',
                            'bg-danger/10 text-danger' => $appointment->status === 'cancelled',
                        ])">
                        @switch($appointment->status)
                            @case('pending') Pendente @break
                            @case('confirmed') Confirmado @break
                            @case('completed') Realizado @break
                            @case('cancelled') Cancelado @break
                        @endswitch
                    </span>
                </div>
                <p class="mt-1 text-sm text-ebony/70">
                    {{ $appointment->modality === 'online' ? 'Online' : 'Presencial' }}
                    @if ($appointment->type) — {{ $appointment->type }} @endif
                </p>
                @if ($appointment->modality === 'online' && $appointment->video_link && $appointment->status === 'confirmed')
                    <a href="{{ $appointment->video_link }}" target="_blank" class="mt-2 inline-block text-sm text-orun-blue hover:underline">Entrar na videochamada</a>
                @endif
            </div>
        @empty
            <p class="text-sm text-ebony/60">Nenhum agendamento ainda.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $appointments->links() }}</div>
</div>
