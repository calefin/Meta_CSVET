<div>
    <h1 class="font-display text-2xl text-ifa-green">Meus dados (LGPD)</h1>

    <div class="mt-4 rounded-lg border border-orisa-earth/20 bg-white p-4">
        <h2 class="font-medium">Portabilidade</h2>
        <p class="mt-1 text-sm text-ebony/70">Exporte uma cópia dos seus dados cadastrados no sistema.</p>
        <div class="mt-2 flex gap-3">
            <a href="{{ route('my-data.export.json') }}" target="_blank" class="rounded border border-ifa-green px-3 py-1.5 text-sm text-ifa-green hover:bg-sacred-sand">Exportar JSON</a>
            <a href="{{ route('my-data.export.pdf') }}" target="_blank" class="rounded border border-ifa-green px-3 py-1.5 text-sm text-ifa-green hover:bg-sacred-sand">Exportar PDF</a>
        </div>
    </div>

    <div class="mt-4 rounded-lg border border-orisa-earth/20 bg-white p-4">
        <h2 class="font-medium">Direito ao esquecimento</h2>
        <p class="mt-1 text-sm text-ebony/70">
            Solicite a exclusão do seu cadastro. O pedido será analisado pela administração antes de qualquer remoção,
            já que registros espirituais podem exigir avaliação antes do descarte.
        </p>

        @if ($pendingRequest)
            <p class="mt-2 text-sm text-orun-blue">Você já tem uma solicitação pendente, enviada em {{ $pendingRequest->created_at->format('d/m/Y') }}.</p>
        @else
            <form wire:submit="requestDeletion" class="mt-2 flex gap-2">
                <input type="text" wire:model="deletionNotes" placeholder="Motivo (opcional)" class="flex-1 rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                <button type="submit" class="rounded bg-danger px-4 py-2 text-sm text-white hover:bg-danger/90">Solicitar exclusão</button>
            </form>
        @endif

        @if ($history->isNotEmpty())
            <ul class="mt-3 space-y-1 text-xs text-ebony/60">
                @foreach ($history as $item)
                    <li>{{ $item->created_at->format('d/m/Y') }} — {{ match($item->status) { 'pending' => 'Pendente', 'completed' => 'Concluída', 'rejected' => 'Rejeitada' } }}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
