<div>
    <a href="{{ route('admin.library.categories') }}" class="text-sm text-ifa-green hover:underline">&larr; Voltar para categorias</a>

    <div class="mt-2 flex items-center justify-between">
        <div>
            <h1 class="font-display text-2xl text-ifa-green">{{ $category->name }}</h1>
            @if ($category->description)
                <p class="text-sm text-ebony/60">{{ $category->description }}</p>
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <!-- Documents List -->
        <div class="lg:col-span-2 space-y-4">
            <h2 class="font-display text-lg text-ifa-green">Documentos Exclusivos</h2>
            <div class="overflow-hidden rounded-lg border border-orisa-earth/20 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="bg-sacred-sand">
                        <tr>
                            <th class="px-4 py-2.5">Título</th>
                            <th class="px-4 py-2.5">Idioma</th>
                            <th class="px-4 py-2.5">Áudio</th>
                            <th class="px-4 py-2.5">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-orisa-earth/10">
                        @foreach ($documents as $document)
                            <tr class="hover:bg-sacred-sand/10 transition-colors" wire:key="doc-{{ $document->id }}">
                                <td class="px-4 py-3 font-semibold text-ebony">{{ $document->title }}</td>
                                <td class="px-4 py-3 uppercase text-xs text-ebony/60">{{ $document->language }}</td>
                                <td class="px-4 py-3">
                                    @if ($document->audio_path)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-ifa-green/10 px-2 py-0.5 text-xs text-ifa-green">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
                                            </svg>
                                            Sim
                                        </span>
                                    @else
                                        <span class="text-xs text-ebony/40">Não</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 space-x-2">
                                    <button wire:click="edit({{ $document->id }})" class="text-ifa-green hover:underline font-medium">editar</button>
                                    <button wire:click="delete({{ $document->id }})" wire:confirm="Remover este documento?" class="text-danger hover:underline font-medium">remover</button>
                                </td>
                            </tr>
                        @endforeach
                        @if ($documents->isEmpty())
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-sm text-ebony/40">Nenhum documento cadastrado nesta categoria.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Form Panel -->
        <div class="rounded-lg border border-orisa-earth/20 bg-white p-6 shadow-sm">
            <h2 class="font-display text-lg text-ifa-green mb-4">
                {{ $editingId ? 'Editar Documento' : 'Novo Documento' }}
            </h2>

            <form wire:submit="save" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-ebony">Título</label>
                    <input type="text" wire:model="title" placeholder="Ex: Reza para prosperidade" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                    @error('title') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-sm font-medium text-ebony">Idioma</label>
                        <select wire:model="language" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green">
                            <option value="pt-BR">Português</option>
                            <option value="en">Inglês</option>
                            <option value="yo">Yorùbá</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-ebony">Descrição curta</label>
                    <textarea wire:model="description" rows="2" placeholder="Breve resumo sobre o conteúdo..." class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm focus:border-ifa-green focus:ring-ifa-green"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-ebony">Conteúdo Exclusivo (HTML/Texto)</label>
                    <div
                        wire:key="editor-{{ $editingId ?? 'new' }}"
                        wire:ignore
                        class="mt-1"
                        x-data="{ editor: null }"
                        x-init="editor = window.IjoifaEditor.mount($refs.editorEl, {
                            initialHtml: @js($content),
                            onChange: (html) => $wire.set('content', html),
                        })"
                    >
                        <div class="flex justify-end">
                            <button
                                type="button"
                                class="mb-1 text-xs text-ifa-green hover:underline animate-pulse-subtle"
                                x-data
                                x-on:click="
                                    const md = prompt('Cole o texto em Markdown:');
                                    if (md !== null) { editor.importMarkdown(md); }
                                "
                            >importar Markdown</button>
                        </div>
                        <div x-ref="editorEl" class="bg-white rounded border border-orisa-earth/20" style="min-height: 12rem"></div>
                    </div>
                    @error('content') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-ebony">Arquivo de Áudio (Opcional)</label>
                    @if ($currentAudioPath)
                        <div class="mt-1 flex items-center justify-between rounded bg-sacred-sand p-2 text-xs">
                            <span class="truncate text-ebony/70">Áudio atual carregado</span>
                            <button type="button" wire:click="deleteAudio" wire:confirm="Excluir o áudio atual?" class="text-danger hover:underline">remover áudio</button>
                        </div>
                    @endif
                    <input type="file" wire:model="audio" accept="audio/*" class="mt-1 block w-full text-xs text-ebony file:mr-4 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-sacred-sand file:text-ifa-green hover:file:bg-sacred-sand/80">
                    @error('audio') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    <div wire:loading wire:target="audio" class="text-xs text-ebony/50 mt-1">enviando áudio...</div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    @if ($editingId)
                        <button type="button" wire:click="cancelEdit" class="rounded px-4 py-2 text-xs font-semibold text-ebony/70 hover:bg-sacred-sand">cancelar</button>
                    @endif
                    <button type="submit" class="rounded bg-ifa-green px-4 py-2 text-xs font-bold text-white hover:bg-ifa-green/90 transition-colors shadow-sm">
                        {{ $editingId ? 'salvar alterações' : 'criar documento' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
