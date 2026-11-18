<div>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-2xl text-ifa-green">Artigos</h1>
        <button wire:click="create" class="rounded bg-ifa-green px-4 py-2 text-white hover:bg-ifa-green/90">
            Novo artigo
        </button>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-3">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Buscar por título..." class="w-full rounded border border-orisa-earth/30 px-3 py-2 text-xs sm:max-w-xs focus:border-ifa-green focus:ring-ifa-green">
    </div>

    <div class="mt-4 overflow-x-auto rounded border border-orisa-earth/20 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-sacred-sand">
                <tr>
                    <th class="px-4 py-2">Título</th>
                    <th class="px-4 py-2">Categoria</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Publicado em</th>
                    <th class="px-4 py-2">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($articles as $article)
                    <tr class="border-t border-orisa-earth/10" wire:key="article-{{ $article->id }}">
                        <td class="px-4 py-2">
                            {{ $article->title }}
                            @if ($article->status === 'published')
                                <a href="{{ route('blog.show', $article) }}" target="_blank" class="ml-1 text-xs text-ifa-green hover:underline">ver</a>
                            @endif
                        </td>
                        <td class="px-4 py-2">{{ $categories[$article->category] ?? $article->category }}</td>
                        <td class="px-4 py-2">
                            @if ($article->status === 'published')
                                <span class="inline-block rounded-full bg-ifa-green/10 px-2 py-1 text-[10px] font-semibold text-ifa-green">Publicado</span>
                            @else
                                <span class="inline-block rounded-full bg-warning/10 px-2 py-1 text-[10px] font-semibold text-warning">Rascunho</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-xs text-ebony/60">{{ $article->published_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <button wire:click="edit({{ $article->id }})" class="text-ifa-green hover:underline">Editar</button>
                            <button wire:click="delete({{ $article->id }})" wire:confirm="Remover este artigo?" class="ml-2 text-danger hover:underline">Remover</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $articles->links() }}</div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4">
            <div class="w-full max-w-3xl rounded-lg bg-white p-6">
                <h2 class="font-display text-xl text-ifa-green">{{ $editingId ? 'Editar' : 'Novo' }} artigo</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium">Título</label>
                        <input type="text" wire:model="title" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                        @error('title') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium">Categoria</label>
                            <select wire:model="category" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                                @foreach ($categories as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium">Status</label>
                            <select wire:model="status" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2">
                                <option value="draft">Rascunho</option>
                                <option value="published">Publicado</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Resumo (usado como descrição para buscadores se não houver uma abaixo)</label>
                        <textarea wire:model="excerpt" rows="2" maxlength="500" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2"></textarea>
                        @error('excerpt') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Conteúdo</label>
                        <div
                            wire:key="article-editor-{{ $editingId ?? 'new' }}"
                            wire:ignore
                            x-data="{ editor: null, htmlMode: false, htmlText: '' }"
                            x-init="editor = window.IjoifaEditor.mount($refs.editorEl, {
                                initialHtml: @js($content),
                                onChange: (html) => $wire.set('content', html),
                            })"
                        >
                            <div class="flex justify-end gap-3">
                                <button type="button" class="mb-1 text-xs text-orun-blue hover:underline"
                                    x-on:click="const md = prompt('Cole o texto em Markdown:'); if (md !== null) { editor.importMarkdown(md); }">importar Markdown</button>
                                <button type="button" class="mb-1 text-xs text-orun-blue hover:underline"
                                    x-on:click="if (!htmlMode) { htmlText = editor.getHtml(); } htmlMode = !htmlMode;"
                                    x-text="htmlMode ? 'voltar ao editor visual' : 'ver/editar HTML'"></button>
                            </div>
                            <div x-show="!htmlMode" x-ref="editorEl" class="rounded border border-orisa-earth/30 bg-white" style="min-height: 12rem"></div>
                            <div x-show="htmlMode" x-cloak>
                                <textarea x-model="htmlText" rows="14" class="w-full rounded border border-orisa-earth/30 px-3 py-2 font-mono text-xs" placeholder="<p>HTML do artigo...</p>"></textarea>
                                <button type="button" class="mt-2 rounded bg-ifa-green px-3 py-1.5 text-xs text-white hover:bg-ifa-green/90"
                                    x-on:click="editor.setHtml(htmlText); htmlMode = false;">Aplicar HTML e voltar ao editor visual</button>
                            </div>
                        </div>
                        @error('content') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium">Imagem de capa (opcional)</label>
                        @if ($currentCoverImagePath && !$coverImage)
                            <img src="{{ Illuminate\Support\Facades\Storage::url($currentCoverImagePath) }}" class="mt-2 h-32 w-auto rounded border border-orisa-earth/20 object-cover">
                        @endif
                        <input type="file" wire:model="coverImage" accept="image/*" class="mt-1 block w-full text-sm">
                        <div wire:loading wire:target="coverImage" class="mt-1 text-xs text-ebony/50">enviando imagem...</div>
                        @error('coverImage') <p class="mt-1 text-sm text-danger">{{ $message }}</p> @enderror
                    </div>

                    <div class="rounded border border-orisa-earth/10 bg-sacred-sand/30 p-4">
                        <p class="text-xs font-semibold text-ifa-green">SEO (opcional — usa título/resumo se deixar em branco)</p>
                        <div class="mt-2">
                            <label class="block text-xs font-medium">Título para buscadores</label>
                            <input type="text" wire:model="metaTitle" maxlength="255" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm">
                        </div>
                        <div class="mt-2">
                            <label class="block text-xs font-medium">Descrição para buscadores</label>
                            <textarea wire:model="metaDescription" rows="2" maxlength="300" class="mt-1 w-full rounded border border-orisa-earth/30 px-3 py-2 text-sm"></textarea>
                        </div>
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
