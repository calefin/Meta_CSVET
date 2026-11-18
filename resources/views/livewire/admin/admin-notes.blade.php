<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl text-ifa-green">
            Anotações privadas
            @if ($targetUser)
                — <span lang="yo">{{ $targetUser->name }}</span>
            @endif
        </h1>
        <button wire:click="openForm" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
            Nova anotação
        </button>
    </div>

    <p class="mt-1 text-xs text-ebony/50">Estas anotações nunca são exibidas para o usuário.</p>

    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar no texto..."
        class="mt-4 w-full max-w-sm rounded border border-orisa-earth/30 px-3 py-2 text-sm">

    <div class="mt-4 space-y-3">
        @foreach ($notes as $note)
            <div class="rounded-lg border border-orisa-earth/20 bg-white p-4" wire:key="note-{{ $note->id }}">
                <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-ebony/60">
                    <span>
                        {{ $note->created_at->format('d/m/Y H:i') }} —
                        @if (! $targetUser) <span lang="yo">{{ $note->targetUser->name }}</span> — @endif
                        {{ ucfirst($note->category) }}
                    </span>
                    @if ($note->isLocked())
                        <span class="text-ebony/40">Bloqueada</span>
                    @endif
                </div>
                <p class="mt-2 text-sm">{{ $note->body }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4">{{ $notes->links() }}</div>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">Nova anotação</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    @if (! $targetUser)
                        <div>
                            <label class="block text-sm font-medium">Usuário</label>
                            <select wire:model="targetUserId" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                                <option value="">— Selecione —</option>
                                @foreach ($targetUsers as $user)
                                    <option value="{{ $user->id }}" lang="yo">{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label class="block text-sm font-medium">Categoria</label>
                        <select wire:model="category" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                            <option value="espiritual">Espiritual</option>
                            <option value="financeiro">Financeiro</option>
                            <option value="saude">Saúde</option>
                            <option value="comportamento">Comportamento</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Texto</label>
                        <textarea wire:model="body" rows="4" lang="yo" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"></textarea>
                        @error('body') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showForm', false)" class="rounded px-4 py-2 text-ebony/70 hover:bg-sacred-sand">Cancelar</button>
                        <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
