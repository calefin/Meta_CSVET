<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl text-ifa-green">Eventos da casa</h1>
        <button wire:click="create" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
            Novo evento
        </button>
    </div>

    <div class="mt-4 space-y-3">
        @foreach ($events as $event)
            <div class="rounded-lg border border-orisa-earth/20 bg-white p-4" wire:key="evt-{{ $event->id }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-display text-lg text-ifa-green">{{ $event->title }}</h3>
                    <span class="text-xs text-ebony/60">
                        {{ match($event->target_group) { 'all' => 'Toda a casa', 'iniciados' => 'Iniciados', 'consulentes' => 'Consulentes' } }}
                    </span>
                </div>
                <p class="mt-1 text-xs text-ebony/50">
                    {{ $event->starts_on->format('d/m/Y') }}
                    @if ($event->ends_on && ! $event->ends_on->equalTo($event->starts_on))
                        a {{ $event->ends_on->format('d/m/Y') }}
                    @endif
                </p>
                @if ($event->description)
                    <p class="mt-2 text-sm text-ebony/80">{{ $event->description }}</p>
                @endif

                <div class="mt-2 flex gap-3 text-xs">
                    <button wire:click="edit({{ $event->id }})" class="text-orun-blue hover:underline">Editar</button>
                    <button wire:click="delete({{ $event->id }})" wire:confirm="Excluir este evento?" class="text-danger hover:underline">Excluir</button>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-4">{{ $events->links() }}</div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">{{ $editingId ? 'Editar evento' : 'Novo evento' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Título</label>
                        <input type="text" wire:model="title" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('title') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium">Início</label>
                            <input type="date" wire:model="starts_on" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                            @error('starts_on') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Fim (opcional)</label>
                            <input type="date" wire:model="ends_on" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                            @error('ends_on') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Descrição (opcional)</label>
                        <textarea wire:model="description" rows="3" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Destinatários</label>
                        <select wire:model="target_group" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                            <option value="all">Toda a casa</option>
                            <option value="iniciados">Iniciados</option>
                            <option value="consulentes">Consulentes</option>
                        </select>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showModal', false)" class="rounded px-4 py-2 text-ebony/70 hover:bg-sacred-sand">Cancelar</button>
                        <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
