<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl text-ifa-green">Avisos e comunicados</h1>
        <button wire:click="create" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
            Novo aviso
        </button>
    </div>

    <div class="mt-4 space-y-3">
        @foreach ($announcements as $announcement)
            <div class="rounded-lg border border-orisa-earth/20 bg-white p-4" wire:key="ann-{{ $announcement->id }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-display text-lg text-ifa-green">{{ $announcement->title }}</h3>
                    <span class="text-xs text-ebony/60">
                        {{ match($announcement->target_group) { 'all' => 'Toda a casa', 'iniciados' => 'Iniciados', 'consulentes' => 'Consulentes' } }}
                    </span>
                </div>
                <p class="mt-2 text-sm text-ebony/80">{{ $announcement->body }}</p>
                @if ($announcement->expires_at)
                    <p class="mt-1 text-xs text-ebony/50">Expira em {{ $announcement->expires_at->format('d/m/Y') }}</p>
                @endif

                <div class="mt-2 flex gap-3 text-xs">
                    <button wire:click="showReads({{ $announcement->id }})" class="text-orun-blue hover:underline">
                        {{ $announcement->reads_count }} confirmacoes de leitura
                    </button>
                    <button wire:click="archive({{ $announcement->id }})" wire:confirm="Arquivar este aviso?" class="text-danger hover:underline">Arquivar</button>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-4">{{ $announcements->links() }}</div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">Novo aviso</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Titulo</label>
                        <input type="text" wire:model="title" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('title') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Mensagem</label>
                        <textarea wire:model="body" rows="4" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"></textarea>
                        @error('body') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Destinatarios</label>
                        <select wire:model="target_group" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                            <option value="all">Toda a casa</option>
                            <option value="iniciados">Iniciados</option>
                            <option value="consulentes">Consulentes</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Expira em (opcional)</label>
                        <input type="date" wire:model="expires_at" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showModal', false)" class="rounded px-4 py-2 text-ebony/70 hover:bg-sacred-sand">Cancelar</button>
                        <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">Publicar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($viewingAnnouncement)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-sm rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">Confirmacoes — {{ $viewingAnnouncement->title }}</h2>
                <ul class="mt-3 max-h-72 space-y-1 overflow-y-auto text-sm">
                    @foreach ($readsData as $row)
                        <li class="flex items-center justify-between border-b border-orisa-earth/10 py-1">
                            <span lang="yo">{{ $row['name'] }}</span>
                            <span class="{{ $row['read'] ? 'text-success' : 'text-ebony/40' }}">{{ $row['read'] ? 'Ciente' : 'Pendente' }}</span>
                        </li>
                    @endforeach
                </ul>
                <button wire:click="$set('viewingReadsFor', null)" class="mt-4 rounded bg-sacred-sand px-4 py-2 text-sm hover:bg-sacred-sand/70">Fechar</button>
            </div>
        </div>
    @endif
</div>
