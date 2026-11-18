<div>
    <h1 class="font-display text-2xl text-ifa-green">Solicitações de exclusão (LGPD)</h1>

    <div class="mt-4 space-y-3">
        @foreach ($requests as $request)
            <div class="rounded-lg border border-orisa-earth/20 bg-white p-4" wire:key="del-{{ $request->id }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span lang="yo">{{ $request->user->name }}</span>
                    <span class="text-xs {{ $request->status === 'pending' ? 'text-orisa-earth' : ($request->status === 'completed' ? 'text-success' : 'text-danger') }}">
                        {{ match($request->status) { 'pending' => 'Pendente', 'completed' => 'Concluída', 'rejected' => 'Rejeitada' } }}
                    </span>
                </div>
                @if ($request->notes)
                    <p class="mt-1 text-sm text-ebony/70">{{ $request->notes }}</p>
                @endif
                <p class="mt-1 text-xs text-ebony/50">Solicitado em {{ $request->created_at->format('d/m/Y H:i') }}</p>

                @if ($request->status === 'pending')
                    <div class="mt-2 flex gap-2">
                        <button wire:click="resolve({{ $request->id }}, 'completed')" wire:confirm="Confirmar exclusão do cadastro? Esta ação deve ser feita manualmente após revisão." class="text-xs text-success hover:underline">Aprovar exclusão</button>
                        <button wire:click="resolve({{ $request->id }}, 'rejected')" class="text-xs text-danger hover:underline">Rejeitar</button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="mt-4">{{ $requests->links() }}</div>
</div>
